<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Request;
use App\Response;
use App\Services\WalletService;
use App\Session;
use App\View;

/**
 * Pharmacy wallet, earnings and payout requests.
 */
final class PayoutController extends Controller
{
    private WalletService $wallet;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->wallet = new WalletService();
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/wallet
    // -----------------------------------------------------------------------
    public function wallet(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        $this->view('pharmacy/wallet', [
            'title'        => 'Wallet',
            'heading'      => 'Wallet &amp; earnings',
            'sidebar'      => View::capture('pharmacy/partials/sidebar'),
            'balance'      => $this->wallet->balance($pharmacyId),
            'transactions' => $this->wallet->transactions($pharmacyId, 40),
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET /pharmacy/payouts
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $balance    = $this->wallet->balance($pharmacyId);
        $minimum    = \App\Config::float('business.payout_minimum', 20000.0);

        $this->view('pharmacy/payouts', [
            'title'        => 'Payouts',
            'heading'      => 'Payout requests',
            'sidebar'      => View::capture('pharmacy/partials/sidebar'),
            'balance'      => $balance,
            'payouts'      => $this->wallet->payouts($pharmacyId),
            'minimum'      => $minimum,
            'canRequest'   => (float) $balance['balance'] >= $minimum,
            'bank'         => \App\Database::instance()->first(
                'SELECT bank_name, bank_account_name, bank_account_number FROM pharmacies WHERE id = ?',
                ['id' => $pharmacyId]
            ) ?? [],
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /pharmacy/payouts
    // -----------------------------------------------------------------------
    public function request(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();

        try {
            $result = $this->wallet->requestPayout($pharmacyId);
        } catch (\App\HttpException $e) {
            Session::error($e->getMessage());
            Response::redirect('/pharmacy/payouts');
        } catch (\Throwable $e) {
            Session::error($e->getMessage());
            Response::redirect('/pharmacy/payouts');
        }

        (new \App\Services\AuditService())->log(
            'finance.payout_requested',
            'payout',
            $result['payout_id'],
            'Payout requested: ' . $result['reference']
        );

        Session::success('Payout requested. Our finance team will process it shortly.');
        Response::redirect('/pharmacy/payouts');
    }
}
