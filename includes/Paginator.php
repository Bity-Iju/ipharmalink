<?php

/**
 * iPharmaLink :: Paginator
 * ---------------------------------------------------------------------------
 * Pagination is a first-class concern: the platform never renders an
 * unbounded list. Total counts come from a COUNT(*) and rows are fetched
 * with a validated LIMIT/OFFSET.
 */

declare(strict_types=1);

namespace App;

final class Paginator implements \IteratorAggregate, \Countable
{
    /** @var list<array<string,mixed>> */
    private array $items;
    private int $total;
    private int $perPage;
    private int $currentPage;

    public function __construct(array $items, int $total, int $perPage, int $currentPage)
    {
        $this->items       = $items;
        $this->total       = max(0, $total);
        $this->perPage     = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return list<array<string,mixed>> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function previousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    public function nextPage(): int
    {
        return min($this->lastPage(), $this->currentPage + 1);
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->currentPage * $this->perPage);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Page numbers with ellipsis, e.g. [1, '…', 4, 5, 6, '…', 12]
     *
     * @return list<int|string>
     */
    public function window(int $each = 2): array
    {
        $last = $this->lastPage();
        if ($last <= 7) {
            return range(1, $last);
        }
        $window  = range(max(1, $this->currentPage - $each), min($last, $this->currentPage + $each));
        $output  = [];
        $previous = 0;
        foreach ($window as $page) {
            if ($previous > 0 && $page - $previous > 1) {
                $output[] = '…';
            }
            $output[] = $page;
            $previous = $page;
        }
        if ($previous < $last) {
            $output[] = '…';
        }
        return $output;
    }

    /**
     * Build a URL preserving the existing query string.
     *
     * @param array<string,mixed> $overrides
     */
    public function url(int $page, array $overrides = []): string
    {
        $query = array_merge($_GET, $overrides, ['page' => max(1, $page)]);
        $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
        return '?' . http_build_query($query);
    }

    // -----------------------------------------------------------------------
    //  Factory
    // -----------------------------------------------------------------------

    /**
     * Run a COUNT + SELECT pair safely.
     *
     * @param  callable(Database):int   $countQuery
     * @param  callable(Database,int,int):array $rowsQuery
     */
    public static function build(callable $countQuery, callable $rowsQuery, int $perPage = 24, int $currentPage = 1): self
    {
        $db       = Database::instance();
        $perPage  = max(1, min(100, $perPage));
        $page     = max(1, $currentPage);
        $total    = (int) $countQuery($db);
        $lastPage = max(1, (int) ceil($total / $perPage));

        // A deleted/filtered page should land on the last available one.
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $offset = ($page - 1) * $perPage;

        return new self($rowsQuery($db, $perPage, $offset), $total, $perPage, $page);
    }
}
