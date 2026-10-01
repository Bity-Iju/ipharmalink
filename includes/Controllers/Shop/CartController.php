<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Controller;
use App\Request;
use App\Response;
use App\Services\CartService;
use App\Services\OrderService;
use App\Session;

/**
 * Shopping cart. The same actions are reachable by plain form POST and by
 * fetch() from the product grid, so both return JSON and let the caller
 * decide whether to reload.
 */
final class CartController extends Controller
{
    private CartService $cart;
    private OrderService $orders;

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $this->cart   = new CartService();
        $this->orders = new OrderService();
    }

    // -----------------------------------------------------------------------
    //  GET /cart
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $contents = $this->cart->contents();
        $quote    = $this->orders->calculate('delivery');

        $this->view('shop/cart', [
            'title'        => 'Your shopping cart',
            'contents'     => $contents,
            'quote'        => $quote,
            'couponCode'   => (string) Session::get('coupon_code', ''),
            'heading'      => 'Your cart',
        ]);
    }

    // -----------------------------------------------------------------------
    //  POST /cart/add
    // -----------------------------------------------------------------------
    public function add(Request $request): void
    {
        $this->guard(function () use ($request): array {
            $this->cart->add($request->postInt('product_id'), max(1, $request->postInt('quantity', 1)));
            return ['message' => 'Added to your cart.'];
        });
    }

    // -----------------------------------------------------------------------
    //  POST /cart/update
    // -----------------------------------------------------------------------
    public function update(Request $request): void
    {
        $this->guard(function () use ($request): array {
            $this->cart->updateQuantity($request->postInt('product_id'), $request->postInt('quantity', 1));
            return ['message' => 'Cart updated.'];
        });
    }

    // -----------------------------------------------------------------------
    //  POST /cart/remove
    // -----------------------------------------------------------------------
    public function remove(Request $request): void
    {
        $this->guard(function () use ($request): array {
            $this->cart->remove($request->postInt('product_id'));
            return ['message' => 'Removed from your cart.'];
        });
    }

    // -----------------------------------------------------------------------
    //  POST /cart/clear
    // -----------------------------------------------------------------------
    public function clear(Request $request): void
    {
        $this->cart->clear();
        Session::info('Your cart is now empty.');
        Response::redirect('/cart');
    }

    // -----------------------------------------------------------------------
    //  POST /cart/coupon
    // -----------------------------------------------------------------------
    public function coupon(Request $request): void
    {
        $code = strtoupper(trim((string) $request->input('coupon', '')));

        if ($code === '') {
            $this->fail('Please enter a promo code.');
        }

        $quote = $this->orders->calculate('delivery');

        // A rejected code produces a warning rather than an error so the
        // customer keeps their cart exactly as it was.
        if ($quote['discount_total'] <= 0.0) {
            Session::error('That promo code could not be applied. Please check the code and the minimum order value.');
            Response::redirect('/cart');
        }

        Session::set('coupon_code', $code);
        Session::success(sprintf('Promo code %s applied — you saved %s.', $code, money((float) $quote['discount_total'])));
        Response::redirect('/cart');
    }

    // -----------------------------------------------------------------------
    //  POST /cart/coupon/remove
    // -----------------------------------------------------------------------
    public function removeCoupon(Request $request): void
    {
        Session::forget('coupon_code');
        Session::info('Promo code removed.');
        Response::redirect('/cart');
    }

    // -----------------------------------------------------------------------
    //  Shared JSON responder
    // -----------------------------------------------------------------------

    /**
     * Run a cart mutation and answer with the fresh header state, so the UI
     * never has to guess what the cart now contains.
     *
     * @param callable():array<string,mixed> $action
     */
    private function guard(callable $action): void
    {
        try {
            $payload = $action();
        } catch (\App\ValidationException $e) {
            $messages = array_merge(...array_values($e->errors()));
            if ($this->request->wantsJson()) {
                Response::json(['ok' => false, 'message' => implode(' ', $messages)], 422);
            }
            Session::error(implode(' ', $messages));
            Response::back('/cart');
        }

        $contents = $this->cart->contents();
        $lineTotals = [];
        foreach ($contents['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $lineTotals[(string) $item['product_id']] = round((float) $item['line_total'], 2);
            }
        }

        if ($this->request->wantsJson()) {
            Response::json(array_merge($payload, [
                'ok'          => true,
                'count'       => $this->cart->rawCount(),
                'subtotal'    => round((float) $contents['subtotal'], 2),
                'line_totals' => $lineTotals,
                'redirect'    => \App\Auth::check() ? '/checkout' : '/cart',
            ]));
        }

        Session::success($payload['message'] ?? 'Cart updated.');
        Response::redirect('/cart');
    }

    private function fail(string $message): void
    {
        if ($this->request->wantsJson()) {
            Response::json(['ok' => false, 'message' => $message], 422);
        }
        Session::error($message);
        Response::back('/cart');
    }
}
