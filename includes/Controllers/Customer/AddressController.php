<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\OrderService;
use App\Session;

/**
 * Delivery address book. The first address a customer saves becomes the
 * default automatically, so a new user can check out without extra clicks.
 */
final class AddressController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /account/addresses
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $customerId = (int) Auth::id();

        $addresses = Database::instance()->all(
            'SELECT a.*, (SELECT COUNT(*) FROM orders o WHERE o.delivery_address_id = a.id) AS order_count
             FROM user_addresses a WHERE a.user_id = ?
             ORDER BY a.is_default DESC, a.id ASC',
            ['user_id' => $customerId]
        );

        $this->view('customer/addresses', [
            'title'     => 'Delivery addresses',
            'heading'   => 'Delivery addresses',
            'addresses' => $addresses,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /account/addresses
    // -----------------------------------------------------------------------
    public function store(Request $request): void
    {
        $data = $this->validate(
            [
                'label'         => 'required|string|max:50',
                'recipient_name' => 'required|string|min:2|max:120',
                'phone'         => 'required|phone|max:32',
                'state'         => 'required|string|min:2|max:80',
                'city'          => 'required|string|min:2|max:80',
                'address_line'  => 'required|string|min:5|max:255',
                'landmark'      => 'nullable|string|max:160',
                'latitude'      => 'nullable|latitude',
                'longitude'     => 'nullable|longitude',
            ],
            $request->all(),
            'customer/addresses',
            '/account/addresses'
        );

        $db         = Database::instance();
        $customerId = (int) Auth::id();

        $isFirst = (int) $db->value('SELECT COUNT(*) FROM user_addresses WHERE user_id = ?', ['user_id' => $customerId]) === 0;

        $db->transaction(static function () use ($db, $data, $customerId, $isFirst, $request): void {
            $id = $db->insert('user_addresses', [
                'user_id'        => $customerId,
                'label'          => $data['label'],
                'recipient_name' => $data['recipient_name'],
                'phone'          => $data['phone'],
                'state'          => $data['state'],
                'city'           => $data['city'],
                'address_line'   => $data['address_line'],
                'landmark'       => $data['landmark'] ?? null,
                'latitude'       => isset($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude'      => isset($data['longitude']) ? (float) $data['longitude'] : null,
                'is_default'     => $isFirst ? 1 : ($request->bool('is_default') ? 1 : 0),
            ]);

            if ($isFirst || $request->bool('is_default')) {
                $db->update('user_addresses', ['is_default' => 0], 'user_id = ?', ['user_id' => $customerId]);
                $db->update('user_addresses', ['is_default' => 1], 'id = ?', ['id' => $id]);
            }
        });

        Session::success('Delivery address saved.');
        Response::redirect('/account/addresses');
    }

    // -----------------------------------------------------------------------
    //  POST /account/addresses/{id}
    // -----------------------------------------------------------------------
    public function update(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $owned = $this->ownedAddress($id);

        $data = $this->validate(
            [
                'label'         => 'required|string|max:50',
                'recipient_name' => 'required|string|min:2|max:120',
                'phone'         => 'required|phone|max:32',
                'state'         => 'required|string|min:2|max:80',
                'city'          => 'required|string|min:2|max:80',
                'address_line'  => 'required|string|min:5|max:255',
                'landmark'      => 'nullable|string|max:160',
                'latitude'      => 'nullable|latitude',
                'longitude'     => 'nullable|longitude',
            ],
            $request->all(),
            'customer/addresses',
            '/account/addresses'
        );

        $db = Database::instance();
        $db->update('user_addresses', [
            'label'          => $data['label'],
            'recipient_name' => $data['recipient_name'],
            'phone'          => $data['phone'],
            'state'          => $data['state'],
            'city'           => $data['city'],
            'address_line'   => $data['address_line'],
            'landmark'       => $data['landmark'] ?? null,
            'latitude'       => isset($data['latitude']) ? (float) $data['latitude'] : $owned['latitude'],
            'longitude'      => isset($data['longitude']) ? (float) $data['longitude'] : $owned['longitude'],
        ], 'id = ?', ['id' => $id]);

        if ($request->bool('is_default')) {
            $db->update('user_addresses', ['is_default' => 0], 'user_id = ?', ['user_id' => Auth::id()]);
            $db->update('user_addresses', ['is_default' => 1], 'id = ?', ['id' => $id]);
        }

        Session::success('Address updated.');
        Response::redirect('/account/addresses');
    }

    // -----------------------------------------------------------------------
    //  POST /account/addresses/{id}/delete
    // -----------------------------------------------------------------------
    public function destroy(Request $request, array $params): void
    {
        $id     = (int) $this->param('id', $params);
        $owned  = $this->ownedAddress($id);
        $db     = Database::instance();

        // Past orders keep their own address snapshot, so deleting is safe.
        $inUse = (int) $db->value(
            "SELECT COUNT(*) FROM orders WHERE delivery_address_id = ? AND status NOT IN ('cancelled','refunded')",
            ['delivery_address_id' => $id]
        );

        if ($inUse > 0) {
            Session::error('This address is attached to an order that is still in progress, so it cannot be deleted yet.');
            Response::redirect('/account/addresses');
        }

        $db->delete('user_addresses', 'id = ?', ['id' => $id]);

        // Promote another address so the customer always has a default.
        if ((int) $owned['is_default'] === 1) {
            $next = $db->value('SELECT id FROM user_addresses WHERE user_id = ? ORDER BY id ASC LIMIT 1', ['user_id' => Auth::id()]);
            if ($next !== null) {
                $db->update('user_addresses', ['is_default' => 1], 'id = ?', ['id' => $next]);
            }
        }

        Session::success('Address removed.');
        Response::redirect('/account/addresses');
    }

    // -----------------------------------------------------------------------
    //  POST /account/addresses/{id}/default
    // -----------------------------------------------------------------------
    public function makeDefault(Request $request, array $params): void
    {
        $id    = (int) $this->param('id', $params);
        $owned = $this->ownedAddress($id);
        $db    = Database::instance();

        $db->transaction(static function () use ($db, $id, $owned): void {
            $db->update('user_addresses', ['is_default' => 0], 'user_id = ?', ['user_id' => $owned['user_id']]);
            $db->update('user_addresses', ['is_default' => 1], 'id = ?', ['id' => $id]);
        });

        Session::success('Default delivery address updated.');
        Response::redirect('/account/addresses');
    }

    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function ownedAddress(int $id): array
    {
        $address = Database::instance()->first(
            'SELECT * FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1',
            ['id' => $id, 'user_id' => Auth::id()]
        );

        if ($address === null) {
            throw HttpException::notFound('That address was not found in your address book.');
        }
        return $address;
    }
}
