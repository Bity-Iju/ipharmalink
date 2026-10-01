<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Response;
use App\Services\OrderService;
use App\Session;
use App\Upload;

/**
 * Multi-step checkout.
 *
 * The steps (contact → address → method → summary → payment) are one screen
 * per submission so the customer can go back without losing state, and the
 * review state lives in the session. The order itself is only ever created by
 * OrderService::place(), which re-prices everything inside a transaction.
 */
final class CheckoutController extends Controller
{
    private OrderService $orders;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->orders = new OrderService();
    }

    // -----------------------------------------------------------------------
    //  GET|POST /checkout
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $customerId = (int) Auth::id();
        $db         = Database::instance();

        $contents = (new \App\Services\CartService())->contents();
        if ($contents['groups'] === []) {
            Session::info('Your cart is empty — add something before checking out.');
            Response::redirect('/products');
        }

        $method  = Session::get('checkout.method', 'delivery') === 'pickup' ? 'pickup' : 'delivery';
        $quote   = $this->orders->calculate($method);
        $step    = (int) Session::get('checkout.step', 1);
        $step    = max(1, min(5, $step));

        if ($request->isPost()) {
            $this->advance($request, $step, $customerId);
        }

        // Recalculate after any transition so the summary always matches.
        $quote = $this->orders->calculate($method);

        $addresses = $db->all(
            'SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC',
            ['user_id' => $customerId]
        );

        $this->view('shop/checkout', [
            'title'        => 'Checkout',
            'heading'      => 'Checkout',
            'step'         => $step,
            'method'       => $method,
            'quote'        => $quote,
            'contents'     => $contents,
            'addresses'    => $addresses,
            'contact'      => Session::get('checkout.contact', [
                'full_name' => (string) (Auth::user()['full_name'] ?? ''),
                'phone'     => (string) (Auth::user()['phone'] ?? ''),
                'email'     => (string) (Auth::user()['email'] ?? ''),
            ]),
            'note'         => (string) Session::get('checkout.note', ''),
            'prescriptions' => $quote['requires_prescription'],
        ]);
    }

    // -----------------------------------------------------------------------
    //  GET /checkout/success/{orderNumber}
    // -----------------------------------------------------------------------
    public function success(Request $request, array $params): void
    {
        $order = Database::instance()->first(
            'SELECT * FROM orders WHERE order_number = ? AND customer_id = ? LIMIT 1',
            ['order_number' => $this->param('orderNumber', $params), 'customer_id' => Auth::id()]
        );

        if ($order === null) {
            throw \App\HttpException::notFound('We could not find that order.');
        }

        $service  = new OrderService();
        $full     = $service->find((int) $order['id']);
        $payment  = (new \App\Services\Payments\PaymentManager())->paymentForOrder((int) $order['id']);

        $this->view('shop/checkout-success', [
            'title'        => 'Order placed — ' . (string) $order['order_number'],
            'heading'      => 'Thank you for your order',
            'order'        => $full,
            'payment'      => $payment,
            'needsPayment' => $order['payment_status'] !== 'successful',
        ]);
    }

    // -----------------------------------------------------------------------
    //  Step machine
    // -----------------------------------------------------------------------

    /**
     * Validate the submitted step, persist it, and move forward or back.
     */
    private function advance(Request $request, int $step, int $customerId): void
    {
        $action = (string) $request->input('action', 'next');

        if ($action === 'back') {
            Session::set('checkout.step', max(1, $step - 1));
            Response::redirect('/checkout');
        }

        if ($action === 'restart') {
            Session::forget('checkout.step');
            Session::forget('checkout.contact');
            Session::forget('checkout.note');
            Session::forget('checkout.method');
            Session::forget('checkout.address_id');
            Response::redirect('/checkout');
        }

        // Step 1 contact details are kept in the session as a snapshot: the
        // customer may edit their profile later without rewriting past orders.
        if ($step === 1) {
            $this->validate(
                [
                    'full_name' => 'required|string|min:2|max:120',
                    'phone'     => 'required|phone|max:32',
                    'email'     => 'required|email|max:190',
                ],
                $request->all(),
                'shop/checkout',
                '/checkout'
            );

            Session::set('checkout.contact', [
                'full_name' => (string) $request->input('full_name', ''),
                'phone'     => (string) $request->input('phone', ''),
                'email'     => strtolower((string) $request->input('email', '')),
            ]);
        } elseif ($step === 2) {
            $this->chooseAddress($request, $customerId);
        } elseif ($step === 3) {
            $this->chooseMethod($request);
        }

        // Steps 4 and 5 both submit "place" (step 4) or go to payment (step 5).
        if ($step === 4) {
            $this->place($request, $customerId);
        }

        if ($step === 5) {
            Session::set('checkout.step', 4);
            Response::redirect('/payment');
        }

        // The customer is named on the order for the pharmacy's fulfilment
        // screens, so keep the checkout snapshot in step with the account.
        $contact = Session::get('checkout.contact', []);
        if (is_array($contact) && ($contact['phone'] ?? '') !== (string) (Auth::user()['phone'] ?? '')) {
            Database::instance()->update('users', [
                'full_name' => $contact['full_name'] ?? Auth::user()['full_name'],
                'phone'     => $contact['phone'] ?? Auth::user()['phone'],
            ], 'id = ?', ['id' => $customerId]);
            \App\Auth::resetCache();
        }

        Session::set('checkout.step', min(5, $step + 1));
        Response::redirect('/checkout');
    }

    private function chooseAddress(Request $request, int $customerId): void
    {
        // "Add a new address" is handled by the address book controller; the
        // checkout only records which existing address to use.
        $addressId = $request->postInt('address_id');

        if ($addressId > 0) {
            $owned = Database::instance()->value(
                'SELECT id FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1',
                ['id' => $addressId, 'user_id' => $customerId]
            );
            if ($owned === null) {
                $this->fail('Please choose one of your saved delivery addresses.');
            }
            Session::set('checkout.address_id', $addressId);
        } elseif (Session::get('checkout.address_id') === null) {
            $this->fail('Please choose a delivery address, or add one first.');
        }
    }

    private function chooseMethod(Request $request): void
    {
        $method = (string) $request->input('fulfilment_method', 'delivery');
        if (!in_array($method, ['delivery', 'pickup'], true)) {
            $this->fail('Please choose home delivery or pharmacy pickup.');
        }

        $quote = $this->orders->calculate($method);

        // Surface blocking problems as errors rather than letting the order fail.
        foreach ($quote['warnings'] as $warning) {
            if (
                str_contains($warning, 'does not offer delivery')
                || str_contains($warning, 'does not offer pickup')
                || str_contains($warning, 'minimum order')
            ) {
                $this->fail($warning);
            }
        }

        Session::set('checkout.method', $method);
        Session::set('checkout.note', trim((string) $request->input('note', '')));
    }

    /**
     * Final step: create the order atomically and hand off to payment.
     */
    private function place(Request $request, int $customerId): void
    {
        $method = (string) Session::get('checkout.method', 'delivery');

        $input = [
            'fulfilment_method' => $method,
            'address_id'        => (int) Session::get('checkout.address_id', 0) ?: null,
            'note'              => (string) Session::get('checkout.note', ''),
        ];

        // Prescription-required medicines must arrive with a prescription.
        $quote = $this->orders->calculate($method);
        if ($quote['requires_prescription'] && $request->file('prescription') !== null) {
            $saved = Upload::store($request->file('prescription'), 'prescription');
            if ($saved !== null) {
                $input['prescription_path'] = (string) $saved['path'];
                $input['prescription_note'] = trim((string) $request->input('prescription_note', ''));
            }
        }

        try {
            $result = $this->orders->place($input);
        } catch (\App\ValidationException $e) {
            $messages = array_merge(...array_values($e->errors()));
            $this->fail(implode(' ', $messages));
        }

        // Checkout state is single-use.
        foreach (['checkout.step', 'checkout.contact', 'checkout.note', 'checkout.method', 'checkout.address_id'] as $key) {
            Session::forget($key);
        }

        $orderId = (int) $result['order']['id'];
        Session::set('pending_order_id', $orderId);

        Response::redirect('/payment?order=' . $orderId);
    }

    private function fail(string $message): void
    {
        Session::error($message);
        Response::redirect('/checkout');
    }
}
