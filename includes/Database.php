<?php

/**
 * iPharmaLink :: PDO database gateway
 * ---------------------------------------------------------------------------
 * A thin, explicit wrapper around PDO. Design goals:
 *   - One shared connection (singleton) — no re-connect churn.
 *   - Prepared statements everywhere. No method here ever concatenates a
 *     caller-supplied value into SQL. Sorting/column names go through
 *     self::column() which whitelists.
 *   - Convenience helpers for the common single/multi row patterns.
 */

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;
    private int $queryCount = 0;
    private float $queryTime  = 0.0;

    /** Identifiers we are willing to interpolate (ORDER BY / GROUP BY). */
    private const SAFE_COLUMNS = [
        'id',
        'name',
        'slug',
        'price',
        'discount_price',
        'stock_qty',
        'created_at',
        'updated_at',
        'rating_avg',
        'rating_count',
        'sales_count',
        'views',
        'total',
        'status',
        'expiry_date',
        'orders',
        'orders_count',
        'rating',
        'relevance',
        'popularity',
        'newest',
        'low_stock',
        'subtotal',
        'revenue',
        'quantity',
        'delivery_fee',
        'paid_at',
        'placed_at',
        'full_name',
        'email',
        'order_number',
        'product_name',
        'amount',
        'is_active',
        'is_featured',
        'expires_at',
    ];

    private function __construct()
    {
        $host    = Config::str('db.host');
        $port    = Config::str('db.port', '3306');
        $name    = Config::str('db.name');
        $charset = Config::str('db.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            $this->pdo = new PDO($dsn, Config::str('db.user'), Config::str('db.password'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements: values never reach the SQL parser.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // Never show credentials or SQL to the visitor.
            ErrorHandler::fatal('Database connection failed. Check config/.env and the database server.');
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // -----------------------------------------------------------------------
    //  Query primitives
    // -----------------------------------------------------------------------

    /**
     * Run a prepared statement.
     *
     * @param array<string|int,mixed> $params
     */
    /**
     * Run a prepared statement.
     *
     * @param array<string|int,mixed> $params
     * @param array<string,mixed>      $extra named bindings merged in last
     */
    public function run(string $sql, array $params = [], array $extra = []): PDOStatement
    {
        $start = microtime(true);
        try {
            $stmt = $this->pdo->prepare($sql);

            // The codebase uses both `?` (with either list or string keys) and
            // explicit `:name` placeholders. Decide per parameter: if the name
            // actually appears in the SQL, bind by name; otherwise bind
            // positionally in the order the values were supplied.
            $positional = 0;

            foreach ($extra + $params as $key => $value) {
                $type = match (true) {
                    is_int($value)  => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    $value === null => PDO::PARAM_NULL,
                    default         => PDO::PARAM_STR,
                };

                if (is_int($key)) {
                    $stmt->bindValue($key + 1, $value, $type);
                    continue;
                }

                $name = ltrim($key, ':');

                if (str_contains($sql, ':' . $name)) {
                    $stmt->bindValue(':' . $name, $value, $type);
                } else {
                    $stmt->bindValue(++$positional, $value, $type);
                }
            }

            $stmt->execute();
        } catch (PDOException $e) {
            Logger::error('SQL failure: ' . $e->getMessage(), ['sql' => $sql]);
            throw $e;
        } finally {
            $this->queryCount++;
            $this->queryTime += microtime(true) - $start;
        }
        return $stmt;
    }

    /**
     * @param  array<string|int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param  array<string|int,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params */
    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $params */
    public function column(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_map(static fn(string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            self::table($table),
            implode(', ', array_map(static fn(string $c): string => "`{$c}`", $columns)),
            implode(', ', $placeholders)
        );
        $this->run($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param  array<string,mixed> $data
     * @param  array<string,mixed> $whereParams
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        // Positional placeholders throughout: the caller's WHERE clause is free
        // to use either style, and PDO refuses a statement that mixes the two.
        $set    = [];
        $params = [];

        foreach (array_keys($data) as $column) {
            $set[] = sprintf('`%s` = ?', self::table((string) $column));
        }
        foreach ($data as $value) {
            $params[] = $value;
        }

        // WHERE params are bound by name where the clause uses :name, otherwise
        // appended positionally in their given order.
        $whereBound = [];
        foreach ($whereParams as $key => $value) {
            if (is_string($key) && str_contains($where, ':' . ltrim($key, ':'))) {
                $whereBound[ltrim($key, ':')] = $value;
            } else {
                $params[] = $value;
            }
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', self::table($table), implode(', ', $set), $where);

        return $this->run($sql, $params, $whereBound)->rowCount();
    }

    /** @param array<string,mixed> $params */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', self::table($table), $where);
        return $this->run($sql, $params)->rowCount();
    }

    // -----------------------------------------------------------------------
    //  Transactions
    // -----------------------------------------------------------------------

    /**
     * Execute $callback inside a transaction, committing on success and
     * rolling back on any throwable. This is the single entry point used by
     * order placement, stock movement and payouts.
     *
     * @template T
     * @param  callable(self):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $callback($this);
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    //  Whitelisting helpers — the only sanctioned way to interpolate SQL
    // -----------------------------------------------------------------------

    /**
     * Whitelist a column name for interpolation into ORDER BY / GROUP BY.
     * Renamed from column() to avoid colliding with the fetch helper above.
     */
    public static function safeColumn(string $name, string $fallback = 'id'): string
    {
        $name = strtolower(preg_replace('/[^a-z0-9_]/', '', $name) ?? '');
        return in_array($name, self::SAFE_COLUMNS, true) ? $name : $fallback;
    }

    public static function direction(string $dir, string $fallback = 'ASC'): string
    {
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : strtoupper($dir);
        return in_array($dir, ['ASC', 'DESC'], true) ? $dir : $fallback;
    }

    public static function table(string $name): string
    {
        $clean = preg_replace('/[^a-z0-9_]/', '', $name) ?? '';
        if ($clean === '') {
            throw new RuntimeException('Invalid table name.');
        }
        return $clean;
    }

    /**
     * Build a safe IN() fragment.
     *
     * @param  list<mixed> $values
     * @return array{0:string,1:array<string,mixed>}  [sql-fragment, bound-params]
     */
    public static function inClause(array $values, string $prefix = 'in'): array
    {
        $values = array_values(array_filter($values, static fn($v) => $v !== null && $v !== ''));
        if ($values === []) {
            return ['(NULL)', []];
        }
        $placeholders = [];
        $params       = [];
        foreach ($values as $i => $value) {
            $key             = "{$prefix}_{$i}";
            $placeholders[]  = ':' . $key;
            $params[$key]    = $value;
        }
        return ['(' . implode(', ', $placeholders) . ')', $params];
    }

    public function stats(): string
    {
        return sprintf('%d queries in %.1f ms', $this->queryCount, $this->queryTime * 1000);
    }
}
