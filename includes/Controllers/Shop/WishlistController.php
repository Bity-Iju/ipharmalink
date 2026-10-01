<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Response;
use App\Services\CartService;
use App\Session;

/**
 * Wishlist. A customer has exactly one list, created on first use.
 */
final class WishlistController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /account/wishlist
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $items = Database::instance()->all(
            'SELECT wi.id AS wishlist_item_id, p.id, p.slug, p.name, p.brand_name, p.strength,
                    p.price, p.discount_price, p.stock_qty, p.requires_prescription,
                    ph.name AS pharmacy_name,
                    (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM wishlist_items wi
             JOIN wishlists w ON w.id = wi.wishlist_id
             JOIN products p ON p.id = wi.product_id
             JOIN pharmacies ph ON ph.id = p.pharmacy_id
             WHERE w.user_id = ? AND p.deleted_at IS NULL AND p.is_active = 1
             ORDER BY wi.id DESC',
            ['user_id' => Auth::id()]
        );

        // Mark anything no longer purchasable so the UI can explain why.
        foreach ($items as &$item) {
            $item['available'] = (int) $item['stock_qty'] > 0;
        }
        unset($item);

        $this->view('customer/wishlist', [
            'title'   => 'My wishlist',
            'heading' => 'My wishlist',
            'items'   => $items,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /wishlist/toggle
    // -----------------------------------------------------------------------
    public function toggle(Request $request): void
    {
        $productId = $request->postInt('product_id');
        $db        = Database::instance();

        $exists = $db->value(
            'SELECT id FROM products WHERE id = ? AND deleted_at IS NULL',
            ['id' => $productId]
        );
        if ($exists === null) {
            Response::json(['ok' => false, 'message' => 'That product is no longer available.'], 404);
        }

        $wishlistId = $this->wishlistId();
        $itemId     = $db->value(
            'SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1',
            ['wishlist_id' => $wishlistId, 'product_id' => $productId]
        );

        if ($itemId !== null) {
            $db->delete('wishlist_items', 'id = ?', ['id' => $itemId]);
            $inWishlist = false;
            $message    = 'Removed from your wishlist.';
        } else {
            $db->insert('wishlist_items', ['wishlist_id' => $wishlistId, 'product_id' => $productId]);
            $inWishlist = true;
            $message    = 'Saved to your wishlist.';
        }

        if ($request->wantsJson()) {
            Response::json([
                'ok'         => true,
                'in_wishlist' => $inWishlist,
                'count'      => (int) $db->value('SELECT COUNT(*) FROM wishlist_items WHERE wishlist_id = ?', ['wishlist_id' => $wishlistId]),
                'message'    => $message,
            ]);
        }

        Session::success($message);
        Response::back('/account/wishlist');
    }

    // -----------------------------------------------------------------------
    //  GET /wishlist/move/{productId}
    // -----------------------------------------------------------------------
    public function moveToCart(Request $request, array $params): void
    {
        $productId  = (int) $this->param('productId', $params);
        $wishlistId = $this->wishlistId();
        $db         = Database::instance();

        try {
            (new CartService())->add($productId, 1);
        } catch (\App\ValidationException $e) {
            $messages = array_merge(...array_values($e->errors()));
            Session::error(implode(' ', $messages));
            Response::redirect('/account/wishlist');
        }

        $db->delete('wishlist_items', 'wishlist_id = ? AND product_id = ?', [
            'wishlist_id' => $wishlistId,
            'product_id' => $productId,
        ]);

        Session::success('Moved to your cart.');
        Response::redirect('/cart');
    }

    // -----------------------------------------------------------------------

    private function wishlistId(): int
    {
        $db     = Database::instance();
        $userId = (int) Auth::id();

        $id = $db->value('SELECT id FROM wishlists WHERE user_id = ? LIMIT 1', ['user_id' => $userId]);
        if ($id !== null) {
            return (int) $id;
        }
        return $db->insert('wishlists', ['user_id' => $userId, 'name' => 'My Wishlist']);
    }
}
