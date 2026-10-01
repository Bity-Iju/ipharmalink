<?php
/**
 * iPharmaLink :: Wallet & payout service
 * ---------------------------------------------------------------------------
 * Money flow for vendors:
 *
 *   order delivered  ->  commission record 'credited'
 *                      ->  wallet credit transaction
 *   pharmacy requests ->  payout (minimum amount enforced)
 *   admin approves   ->  balance debited, payout marked paid
 *
 * Wallet movements are always written as an immutable ledger row with the
 * resulting balance, so the wallet table is a cache and the ledger is truth.
 */

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Database;
use App\HttpException;
use App\Setting;

final class WalletService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    // -----------------------------------------------------------------------
    //  Reads
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    public function balance(int $pharmacyId): array
    {
        $wallet = $this->db->first('SELECT * FROM pharmacy_wallets WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacyId]);
        if ($wallet === null) {
            $this->db->insert('pharmacy_wallets', ['pharmacy_id' => $pharmacyId]);
            $wallet = $this->db->first('SELECT * FROM pharmacy_wallets WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacyId]);
        }
        return [
            'balance'         => (float) ($wallet['balance'] ?? 0),
            'pending_balance' => (float) ($wallet['pending_balance'] ?? 0),
            'total_earned'    => (float) ($wallet['total_earned'] ?? 0),
            'total_paid'      => (float) ($wallet['total_paid'] ?? 0),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function transactions(int $pharmacyId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->all(
            "SELECT * FROM wallet_transactions WHERE pharmacy_id = ? ORDER BY id DESC LIMIT {$limit}",
            ['pharmacy_id' => $pharmacyId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function payouts(int $pharmacyId): array
    {
        return $this->db->all(
            'SELECT * FROM payouts WHERE pharmacy_id = ? ORDER BY id DESC',
            ['pharmacy_id' => $pharmacyId]
        );
    }

    // -----------------------------------------------------------------------
    //  Earnings
    // -----------------------------------------------------------------------

    /**
     * Release a slice's earnings into the pharmacy's spendable balance once
     * the order is delivered.
     */
    public function releaseEarningsForSlice(int $pharmacyOrderId): void
    {
        $this->db->transaction(function () use ($pharmacyOrderId): void {
            $commission = $this->db->first(
                'SELECT * FROM commissions WHERE pharmacy_order_id = ? FOR UPDATE',
                ['pharmacy_order_id' => $pharmacyOrderId]
            );
            if ($commission === null || $commission['status'] === 'credited') {
                return;   // idempotent
            }

            $slice = $this->db->first(
                'SELECT order_id, sub_order_number, pharmacy_id, pharmacy_earnings FROM pharmacy_orders WHERE id = ?',
                ['id' => $pharmacyOrderId]
            );
            if ($slice === null) {
                return;
            }

            $earnings = (float) $slice['pharmacy_earnings'];
            $this->credit((int) $slice['pharmacy_id'], $earnings,
                'Earnings from ' . $slice['sub_order_number'], 'pharmacy_order', $pharmacyOrderId);

            $this->db->update('commissions', ['status' => 'credited'], 'id = ?', ['id' => $commission['id']]);
        });
    }

    /**
     * Credit a pharmacy wallet and write the ledger row.
     * Must be called inside a transaction when part of a larger operation.
     */
    public function credit(int $pharmacyId, float $amount, string $description, ?string $referenceType = null, ?int $referenceId = null): void
    {
        if ($amount <= 0) {
            return;
        }
        $walletId = $this->walletId($pharmacyId);

        $this->db->run(
            'UPDATE pharmacy_wallets
             SET balance = balance + ?, total_earned = total_earned + ?
             WHERE id = ?',
            ['amount' => $amount, 'earned' => $amount, 'id' => $walletId]
        );

        $balanceAfter = (float) $this->db->value('SELECT balance FROM pharmacy_wallets WHERE id = ?', ['id' => $walletId]);

        $this->db->insert('wallet_transactions', [
            'wallet_id'      => $walletId,
            'pharmacy_id'    => $pharmacyId,
            'type'           => 'credit',
            'amount'         => $amount,
            'balance_after'  => $balanceAfter,
            'description'    => $description,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
        ]);
    }

    // -----------------------------------------------------------------------
    //  Payouts
    // -----------------------------------------------------------------------

    /** @return array{payout_id:int, reference:string} */
    public function requestPayout(int $pharmacyId): array
    {
        return $this->db->transaction(function () use ($pharmacyId): array {
            $wallet = $this->balance($pharmacyId);
            $minimum = Setting::getFloat('commission.payout_minimum', 20000.0);

            if ($wallet['balance'] < $minimum) {
                throw HttpException::badRequest(sprintf(
                    'The minimum payout is ₦%s. Your available balance is ₦%s.',
                    number_format($minimum, 2), number_format($wallet['balance'], 2)
                ));
            }

            $existing = $this->db->value(
                'SELECT id FROM payouts WHERE pharmacy_id = ? AND status IN (\'requested\', \'approved\', \'processing\') LIMIT 1',
                ['pharmacy_id' => $pharmacyId]
            );
            if ($existing !== null) {
                throw HttpException::badRequest('You already have a payout awaiting processing.');
            }

            $pharmacy = $this->db->first(
                'SELECT bank_name, bank_account_name, bank_account_number FROM pharmacies WHERE id = ?',
                ['id' => $pharmacyId]
            ) ?? [];

            $reference = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $payoutId = $this->db->insert('payouts', [
                'reference'      => $reference,
                'pharmacy_id'    => $pharmacyId,
                'amount'         => $wallet['balance'],
                'bank_name'      => $pharmacy['bank_name'] ?? null,
                'account_number' => $pharmacy['bank_account_number'] ?? null,
                'status'         => 'requested',
                'requested_by'   => Auth::id(),
            ]);

            return ['payout_id' => $payoutId, 'reference' => $reference];
        });
    }

    public function approvePayout(int $payoutId, int $adminId): void
    {
        $this->db->transaction(function () use ($payoutId, $adminId): void {
            $payout = $this->db->first('SELECT * FROM payouts WHERE id = ? FOR UPDATE', ['id' => $payoutId]);
            if ($payout === null) {
                throw HttpException::notFound('Payout not found.');
            }
            if ($payout['status'] !== 'requested') {
                throw HttpException::badRequest('Only a requested payout can be approved.');
            }

            $this->db->update('payouts', [
                'status'       => 'approved',
                'processed_by' => $adminId,
                'processed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', ['id' => $payoutId]);
        });
    }

    public function markPayoutPaid(int $payoutId, int $adminId, ?string $notes = null): void
    {
        $this->db->transaction(function () use ($payoutId, $adminId, $notes): void {
            $payout = $this->db->first('SELECT * FROM payouts WHERE id = ? FOR UPDATE', ['id' => $payoutId]);
            if ($payout === null) {
                throw HttpException::notFound('Payout not found.');
            }
            if (!in_array($payout['status'], ['approved', 'processing'], true)) {
                throw HttpException::badRequest('This payout is not ready to be marked as paid.');
            }

            $amount  = (float) $payout['amount'];
            $walletId = $this->walletId((int) $payout['pharmacy_id']);

            $available = (float) $this->db->value('SELECT balance FROM pharmacy_wallets WHERE id = ?', ['id' => $walletId]);
            if ($available < $amount) {
                throw HttpException::badRequest('The pharmacy no longer has enough available balance for this payout.');
            }

            $newBalance = round($available - $amount, 2);
            $this->db->run(
                'UPDATE pharmacy_wallets
                 SET balance = ?, total_paid = total_paid + ? WHERE id = ?',
                ['balance' => $newBalance, 'paid' => $amount, 'id' => $walletId]
            );

            $this->db->insert('wallet_transactions', [
                'wallet_id'      => $walletId,
                'pharmacy_id'    => (int) $payout['pharmacy_id'],
                'type'           => 'payout',
                'amount'         => $amount,
                'balance_after'  => $newBalance,
                'description'    => 'Payout ' . $payout['reference'] . ($notes !== null ? ' — ' . $notes : ''),
                'reference_type' => 'payout',
                'reference_id'   => $payoutId,
            ]);

            $this->db->update('payouts', [
                'status'       => 'paid',
                'processed_by' => $adminId,
                'processed_at' => date('Y-m-d H:i:s'),
                'paid_at'      => date('Y-m-d H:i:s'),
                'notes'        => $notes,
            ], 'id = ?', ['id' => $payoutId]);

            (new NotificationService())
                ->toPharmacy((int) $payout['pharmacy_id'], 'payout.processed', 'Payout processed',
                    'Your payout of ₦' . number_format($amount, 2) . ' (' . $payout['reference'] . ') has been sent.',
                    '/pharmacy/wallet')
                ->send();
        });
    }

    public function rejectPayout(int $payoutId, int $adminId, string $reason): void
    {
        $this->db->update('payouts', [
            'status'       => 'rejected',
            'processed_by' => $adminId,
            'processed_at' => date('Y-m-d H:i:s'),
            'notes'        => $reason,
        ], 'id = ? AND status = ?', ['id' => $payoutId, 'status' => 'requested']);

        $payout = $this->db->first('SELECT pharmacy_id, reference FROM payouts WHERE id = ?', ['id' => $payoutId]);
        if ($payout !== null) {
            (new NotificationService())
                ->toPharmacy((int) $payout['pharmacy_id'], 'payout.processed', 'Payout request declined',
                    "Your payout request {$payout['reference']} was declined: {$reason}", '/pharmacy/wallet')
                ->send();
        }
    }

    // -----------------------------------------------------------------------
    //  Platform reporting
    // -----------------------------------------------------------------------

    /** @return array<string,float|int> */
    public function platformTotals(): array
    {
        $row = $this->db->first(
            'SELECT
                COALESCE(SUM(CASE WHEN status = "credited" THEN commission_amount END), 0) AS commission_earned,
                COALESCE(SUM(CASE WHEN status = "pending"  THEN commission_amount END), 0) AS commission_pending,
                COALESCE(SUM(CASE WHEN status = "reversed" THEN commission_amount END), 0) AS commission_reversed
             FROM commissions'
        ) ?: [];

        $payouts = $this->db->first(
            'SELECT
                COALESCE(SUM(CASE WHEN status IN ("requested","approved","processing") THEN amount END), 0) AS pending_payouts,
                COALESCE(SUM(CASE WHEN status = "paid" THEN amount END), 0) AS paid_payouts
             FROM payouts'
        ) ?: [];

        return [
            'commission_earned'   => round((float) $row['commission_earned'], 2),
            'commission_pending'  => round((float) $row['commission_pending'], 2),
            'commission_reversed' => round((float) $row['commission_reversed'], 2),
            'pending_payouts'     => round((float) $payouts['pending_payouts'], 2),
            'paid_payouts'        => round((float) $payouts['paid_payouts'], 2),
        ];
    }

    /** Reverse a credited commission (e.g. a refunded order). */
    public function reverseForSlice(int $pharmacyOrderId): void
    {
        $this->db->transaction(function () use ($pharmacyOrderId): void {
            $commission = $this->db->first(
                'SELECT * FROM commissions WHERE pharmacy_order_id = ? FOR UPDATE',
                ['pharmacy_order_id' => $pharmacyOrderId]
            );
            if ($commission === null || $commission['status'] !== 'credited') {
                return;
            }

            $walletId = $this->walletId((int) $commission['pharmacy_id']);
            $earnings = (float) $commission['pharmacy_earnings'];
            $balance  = max(0.0, round((float) $this->db->value('SELECT balance FROM pharmacy_wallets WHERE id = ?', ['id' => $walletId]) - $earnings, 2));

            $this->db->run('UPDATE pharmacy_wallets SET balance = ?, total_earned = GREATEST(0, total_earned - ?) WHERE id = ?',
                ['balance' => $balance, 'earnings' => $earnings, 'id' => $walletId]);

            $this->db->insert('wallet_transactions', [
                'wallet_id'      => $walletId,
                'pharmacy_id'    => (int) $commission['pharmacy_id'],
                'type'           => 'refund_deduction',
                'amount'         => $earnings,
                'balance_after'  => $balance,
                'description'    => 'Reversal — order refunded',
                'reference_type' => 'pharmacy_order',
                'reference_id'   => $pharmacyOrderId,
            ]);

            $this->db->update('commissions', ['status' => 'reversed'], 'id = ?', ['id' => $commission['id']]);
        });
    }

    private function walletId(int $pharmacyId): int
    {
        $wallet = $this->db->first('SELECT id FROM pharmacy_wallets WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacyId]);
        if ($wallet !== null) {
            return (int) $wallet['id'];
        }
        return $this->db->insert('pharmacy_wallets', ['pharmacy_id' => $pharmacyId]);
    }
}
