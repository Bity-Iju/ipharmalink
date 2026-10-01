<?php

declare(strict_types=1);

namespace App\Controllers\Pharmacy;

use App\Auth;
use App\Controller;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Session;
use App\Upload;
use App\View;

/**
 * Pharmacy profile and operational settings.
 *
 * Two things are deliberately not editable here: the pharmacy's status
 * (only an admin can approve or suspend) and its commission rate (admin only).
 */
final class SettingsController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET|POST /pharmacy/profile
    // -----------------------------------------------------------------------
    public function profile(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();

        $pharmacy = $db->first('SELECT * FROM pharmacies WHERE id = ?', ['id' => $pharmacyId]);
        if ($pharmacy === null) {
            throw HttpException::notFound('Your pharmacy profile could not be loaded.');
        }

        if ($request->isPost()) {
            $data = $this->validate(
                [
                    'name'            => 'required|string|min:3|max:150',
                    'legal_name'      => 'nullable|string|max:180',
                    'pharmacist_name' => 'required|string|min:3|max:150',
                    'phone'           => 'required|phone|max:32',
                    'email'           => 'required|email|max:190',
                    'website'         => 'nullable|regex:#^https?://#i|max:190',
                    'description'     => 'required|string|min:30|max:3000',
                    'state'           => 'required|string|min:2|max:80',
                    'city'            => 'required|string|min:2|max:80',
                    'address'         => 'required|string|min:5|max:255',
                    'latitude'        => 'required|latitude',
                    'longitude'       => 'required|longitude',
                    'open_time'       => 'required|time',
                    'close_time'      => 'required|time',
                    'bank_name'           => 'nullable|string|max:120',
                    'bank_account_name'   => 'nullable|string|max:150',
                    'bank_account_number' => 'nullable|string|max:32',
                ],
                $request->all(),
                'pharmacy/profile',
                '/pharmacy/profile'
            );

            $update = [
                'name'            => $data['name'],
                'legal_name'      => $data['legal_name'] ?? $pharmacy['legal_name'],
                'pharmacist_name' => $data['pharmacist_name'],
                'phone'           => $data['phone'],
                'email'           => strtolower((string) $data['email']),
                'website'         => $data['website'] ?? null,
                'description'     => $data['description'],
                'state'           => $data['state'],
                'city'            => $data['city'],
                'address'         => $data['address'],
                'latitude'        => (float) $data['latitude'],
                'longitude'       => (float) $data['longitude'],
                'open_time'       => $data['open_time'] . ':00',
                'close_time'      => $data['close_time'] . ':00',
            ];

            if ($request->file('logo') !== null) {
                $saved = Upload::store($request->file('logo'), 'pharmacy');
                if ($saved !== null) {
                    $update['logo'] = $saved['path'];
                }
            }
            if ($request->file('cover_image') !== null) {
                $saved = Upload::store($request->file('cover_image'), 'pharmacy');
                if ($saved !== null) {
                    $update['cover_image'] = $saved['path'];
                }
            }

            $db->update('pharmacies', $update, 'id = ?', ['id' => $pharmacyId]);

            (new AuditService())->log('pharmacy.profile_updated', 'pharmacy', $pharmacyId, 'Storefront profile updated');

            Session::success('Your pharmacy profile has been updated.');
            Response::redirect('/pharmacy/profile');
        }

        $documents = $db->all(
            'SELECT * FROM pharmacy_documents WHERE pharmacy_id = ? ORDER BY id DESC',
            ['pharmacy_id' => $pharmacyId]
        );

        $this->view('pharmacy/profile', [
            'title'     => 'Pharmacy profile',
            'heading'   => 'Storefront profile',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'pharmacy'  => $pharmacy,
            'documents' => $documents,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  GET|POST /pharmacy/settings
    // -----------------------------------------------------------------------
    public function settings(Request $request): void
    {
        $pharmacyId = (int) Auth::pharmacyId();
        $db         = Database::instance();

        $pharmacy = $db->first('SELECT * FROM pharmacies WHERE id = ?', ['id' => $pharmacyId]);
        if ($pharmacy === null) {
            throw HttpException::notFound('Your pharmacy could not be loaded.');
        }

        if ($request->isPost()) {
            $data = $this->validate(
                [
                    'delivery_available'  => 'nullable|bool',
                    'pickup_available'    => 'nullable|bool',
                    'delivery_radius_km'  => 'required|numeric|between:0,200',
                    'delivery_fee'        => 'required|decimal|min:0',
                    'free_delivery_threshold' => 'required|decimal|min:0',
                    'estimated_delivery_minutes' => 'required|integer|between:10,1440',
                    'preparation_minutes' => 'required|integer|between:5,1440',
                    'min_order_value'     => 'required|decimal|min:0',
                    'accept_orders_automatically' => 'nullable|bool',
                    'notify_new_order'    => 'nullable|bool',
                    'notify_low_stock'    => 'nullable|bool',
                    'notify_expiry'       => 'nullable|bool',
                    'email_new_order'     => 'nullable|bool',
                    'sms_new_order'       => 'nullable|bool',
                ],
                $request->all(),
                'pharmacy/settings',
                '/pharmacy/settings'
            );

            $update = [
                'delivery_available'  => $request->bool('delivery_available') ? 1 : 0,
                'pickup_available'    => $request->bool('pickup_available') ? 1 : 0,
                'delivery_radius_km'  => (float) $data['delivery_radius_km'],
                'delivery_fee'        => round((float) $data['delivery_fee'], 2),
                'free_delivery_threshold' => round((float) $data['free_delivery_threshold'], 2),
                'estimated_delivery_minutes' => (int) $data['estimated_delivery_minutes'],
                'preparation_minutes' => (int) $data['preparation_minutes'],
                'min_order_value'     => round((float) $data['min_order_value'], 2),
                'accept_orders_automatically' => $request->bool('accept_orders_automatically') ? 1 : 0,
            ];

            // Business rules that must agree, validated rather than assumed.
            if (
                (float) $update['free_delivery_threshold'] > 0
                && (float) $update['free_delivery_threshold'] < (float) $update['min_order_value']
            ) {
                Session::error('The free delivery threshold cannot be lower than your minimum order value.');
                Response::redirect('/pharmacy/settings');
            }
            if ((int) $update['delivery_available'] === 0 && (int) $update['delivery_fee'] < 0) {
                Session::error('Delivery fees cannot be negative.');
                Response::redirect('/pharmacy/settings');
            }

            $db->update('pharmacies', $update, 'id = ?', ['id' => $pharmacyId]);

            // Notification preferences live in pharmacy_settings.
            foreach (['notify_new_order', 'notify_low_stock', 'notify_expiry', 'email_new_order', 'sms_new_order'] as $key) {
                \App\Setting::setForPharmacy($pharmacyId, $key, $request->bool($key) ? '1' : '0');
            }
            \App\Setting::setForPharmacy($pharmacyId, 'payment_methods', implode(',', $request->array('payment_methods')));

            (new AuditService())->log('pharmacy.settings_updated', 'pharmacy', $pharmacyId, 'Delivery and order settings updated');

            Session::success('Settings saved.');
            Response::redirect('/pharmacy/settings');
        }

        $settings = [];
        foreach ($db->all('SELECT key_name, value FROM pharmacy_settings WHERE pharmacy_id = ?', ['pharmacy_id' => $pharmacyId]) as $row) {
            $settings[(string) $row['key_name']] = (string) $row['value'];
        }

        $this->view('pharmacy/settings', [
            'title'     => 'Settings',
            'heading'   => 'Pharmacy settings',
            'sidebar'   => View::capture('pharmacy/partials/sidebar'),
            'pharmacy'  => $pharmacy,
            'settings'  => $settings,
        ], 'layouts/dashboard');
    }
}
