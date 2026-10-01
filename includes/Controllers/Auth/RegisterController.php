<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Response;
use App\Services\AuditService;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Session;
use App\Setting;
use App\Upload;
use App\UploadException;

/**
 * Customer and pharmacy (vendor) registration.
 *
 * A pharmacy registration creates two rows atomically — a pharmacy_owner user
 * and a pharmacies record in `pending` state — because a storefront without an
 * owner is meaningless, and an owner without a storefront cannot sign in.
 */
final class RegisterController extends Controller
{
    // -----------------------------------------------------------------------
    //  /register
    // -----------------------------------------------------------------------
    public function handle(Request $request): void
    {
        if (!$request->isPost()) {
            $this->view('auth/register', [
                'title'   => 'Create your account',
                'heading' => 'Create your account',
            ]);
            return;
        }

        $data = $this->validate(
            [
                'full_name' => 'required|string|min:2|max:120',
                'email'     => 'required|email|max:190|unique_email',
                'phone'     => 'required|phone|max:32',
                'password'  => 'required|password|confirmed',
                'terms'     => 'required',
            ],
            $request->all(),
            'auth/register',
            '/register'
        );

        $db      = Database::instance();
        $roleId  = (int) $db->value("SELECT id FROM roles WHERE name = 'customer'");
        $token   = bin2hex(random_bytes(20));

        $userId = $db->transaction(static function () use ($data, $roleId, $token): int {
            $id = Database::instance()->insert('users', [
                'role_id'            => $roleId,
                'full_name'          => $data['full_name'],
                'email'              => strtolower((string) $data['email']),
                'phone'              => $data['phone'],
                'password_hash'      => Auth::hashPassword((string) $data['password']),
                'verification_token' => $token,
                'status'             => 'active',
            ]);

            Database::instance()->insert('user_roles', ['user_id' => $id, 'role_id' => $roleId]);

            // Everyone gets a wishlist up front so the toggle never has to
            // create one lazily.
            Database::instance()->insert('wishlists', ['user_id' => $id, 'name' => 'My Wishlist']);

            return $id;
        });

        (new MailService())->sendVerification(
            (string) $data['email'],
            (string) $data['full_name'],
            $token
        );

        (new AuditService())->log('customer.registered', 'user', $userId, 'Customer account created');

        // Sign them straight in — verification gates sensitive actions, not
        // shopping, so nobody is forced to leave a cart behind.
        Auth::login($db->first('SELECT * FROM users WHERE id = ?', ['id' => $userId]) ?? []);

        Session::success('Welcome to ' . Setting::getString('general.platform_name', 'iPharmaLink')
            . '! We have sent a verification link to your email.');
        Session::info('Add a delivery address before checkout so we know where to bring your order.');

        Response::redirect('/account');
    }

    public function supplierRegister(Request $request): void
    {
        if (!$request->isPost()) {
            $this->view('auth/supplier-register', [
                'title' => 'Register as a wholesale supplier',
                'heading' => 'Register as a wholesale supplier',
            ]);
            return;
        }

        $data = $this->validate(
            [
                'full_name' => 'required|string|min:2|max:120',
                'pharmacist_name' => 'required|string|min:3|max:150',
                'contact_person' => 'required|string|min:2|max:150',
                'email' => 'required|email|max:190|unique_email',
                'phone' => 'required|phone|max:32',
                'password' => 'required|password|confirmed',
                'terms' => 'required',
                'name' => 'required|string|min:3|max:150',
                'registered_name' => 'required|string|min:3|max:180',
                'registration_number' => 'required|string|min:3|max:80',
                'business_address' => 'required|string|min:5|max:255',
                'warehouse_address' => 'nullable|string|max:255',
                'state' => 'required|string|min:2|max:80',
                'city' => 'required|string|min:2|max:80',
                'delivery_areas' => 'nullable|string|max:2000',
                'opening_hours' => 'nullable|string|max:1000',
                'description' => 'required|string|min:30|max:2000',
                'bank_name' => 'required|string|min:2|max:120',
                'bank_account_name' => 'required|string|min:2|max:150',
                'bank_account_number' => 'required|string|min:8|max:32',
            ],
            $request->all(),
            'auth/supplier-register',
            '/supplier/register'
        );

        $license = $request->file('license_document');
        if ($license === null) {
            Session::flashInput($request->all());
            $this->view('auth/supplier-register', [
                'title' => 'Please attach a supplier licence',
                'heading' => 'Register as a wholesale supplier',
                'errors' => ['license_document' => ['Upload your supplier licence or registration certificate.']],
            ]);
            return;
        }

        $db = Database::instance();
        if ($db->value('SELECT id FROM suppliers WHERE registration_number = ?', [$data['registration_number']]) !== null) {
            Session::flashInput($request->all());
            $this->view('auth/supplier-register', [
                'title' => 'Registration number already exists',
                'heading' => 'Register as a wholesale supplier',
                'errors' => ['registration_number' => ['This registration number is already in use.']],
            ]);
            return;
        }

        try {
            $logo = $request->file('logo') !== null ? Upload::store($request->file('logo'), 'supplier') : null;
            $documents = [];
            foreach ([
                'license' => 'license_document',
                'incorporation' => 'incorporation_document',
                'tax_certificate' => 'tax_document',
            ] as $type => $field) {
                $file = $request->file($field);
                if ($file !== null) {
                    $stored = Upload::store($file, 'document');
                    if ($stored !== null) {
                        $documents[] = ['type' => $type, 'path' => $stored['path'], 'name' => $stored['name']];
                    }
                }
            }
        } catch (UploadException $e) {
            Session::flashInput($request->all());
            $this->view('auth/supplier-register', [
                'title' => 'Please check your uploaded files',
                'heading' => 'Register as a wholesale supplier',
                'errors' => ['license_document' => [$e->getMessage()]],
            ]);
            return;
        }

        $roleId = (int) $db->value("SELECT id FROM roles WHERE name = 'wholesale_supplier'");
        $slug = $this->uniqueSupplierSlug((string) $data['name']);
        $supplierId = $db->transaction(static function () use ($db, $data, $roleId, $logo, $documents, $slug): int {
            $ownerId = $db->insert('users', [
                'role_id' => $roleId,
                'full_name' => $data['full_name'],
                'email' => strtolower((string) $data['email']),
                'phone' => $data['phone'],
                'password_hash' => Auth::hashPassword((string) $data['password']),
                'status' => 'active',
            ]);
            $db->insert('user_roles', ['user_id' => $ownerId, 'role_id' => $roleId]);

            $supplierId = $db->insert('suppliers', [
                'owner_id' => $ownerId,
                'name' => $data['name'],
                'slug' => $slug,
                'registered_name' => $data['registered_name'],
                'registration_number' => $data['registration_number'],
                'pharmacist_name' => $data['pharmacist_name'],
                'contact_person' => $data['contact_person'],
                'email' => strtolower((string) $data['email']),
                'phone' => $data['phone'],
                'logo' => $logo['path'] ?? null,
                'business_address' => $data['business_address'],
                'warehouse_address' => $data['warehouse_address'] ?? null,
                'state' => $data['state'],
                'city' => $data['city'],
                'delivery_areas' => $data['delivery_areas'] ?? null,
                'opening_hours' => $data['opening_hours'] ?? null,
                'description' => $data['description'],
                'bank_name' => $data['bank_name'],
                'bank_account_name' => $data['bank_account_name'],
                'bank_account_number' => $data['bank_account_number'],
                'status' => 'pending',
            ]);

            foreach ($documents as $document) {
                $db->insert('supplier_documents', [
                    'supplier_id' => $supplierId,
                    'doc_type' => $document['type'],
                    'file_path' => $document['path'],
                    'original_name' => $document['name'],
                ]);
            }
            return $supplierId;
        });

        (new AuditService())->log('supplier.registered', 'supplier', $supplierId, 'Supplier submitted for verification');
        $notifications = new NotificationService();
        foreach ($notifications->adminIds() as $adminId) {
            $notifications->to(
                $adminId,
                'supplier.registered',
                'New supplier awaiting verification',
                $data['name'] . ' submitted registration documents for review.',
                '/admin/suppliers/pending',
                'supplier',
                $supplierId
            );
        }
        $notifications->send(false);

        Session::success('Your supplier application has been submitted for verification.');
        Response::redirect('/supplier/login');
    }

    // -----------------------------------------------------------------------
    //  /pharmacy/register
    // -----------------------------------------------------------------------
    public function pharmacyRegister(Request $request): void
    {
        if (!$request->isPost()) {
            $this->view('auth/pharmacy-register', [
                'title'   => 'Register your pharmacy',
                'heading' => 'Register your pharmacy',
                'states'  => $this->states(),
            ]);
            return;
        }

        $data = $this->validate(
            [
                // Owner
                'pharmacist_name'    => 'required|string|min:3|max:150',
                'email'             => 'required|email|max:190|unique_email',
                'phone'             => 'required|phone|max:32',
                'password'          => 'required|password|confirmed',
                'terms'             => 'required',
                // Business
                'name'              => 'required|string|min:3|max:150',
                'legal_name'        => 'required|string|min:3|max:180',
                'registration_number' => 'required|string|min:3|max:80',
                'regulatory_body'   => 'required|string|min:2|max:120',
                'state'             => 'required|string|min:2|max:80',
                'city'              => 'required|string|min:2|max:80',
                'address'           => 'required|string|min:5|max:255',
                'description'       => 'required|string|min:30|max:2000',
                'latitude'          => 'required|latitude',
                'longitude'         => 'required|longitude',
                'open_time'         => 'required|time',
                'close_time'        => 'required|time',
                'website'           => 'nullable|regex:#^https?://#i|max:190',
                'delivery_radius_km' => 'nullable|numeric|max:200',
                'estimated_delivery_minutes' => 'nullable|integer|between:10,1440',
                'bank_name'         => 'required|string|min:2|max:120',
                'bank_account_name' => 'required|string|min:2|max:150',
                'bank_account_number' => 'required|string|min:8|max:32',
            ],
            $request->all(),
            'auth/pharmacy-register',
            '/pharmacy/register'
        );

        if ($request->file('licence_document') === null) {
            Session::flashInput($request->all());
            $this->view('auth/pharmacy-register', [
                'title' => 'Please attach your pharmacy licence',
                'heading' => 'Register your pharmacy',
                'states' => $this->states(),
                'errors' => ['documents' => ['Upload your pharmacy licence or registration certificate.']],
            ]);
            return;
        }

        // ---- uploads: logo, cover, licence documents ---------------------
        $logo = null;
        if ($request->file('logo') !== null) {
            $logo = Upload::store($request->file('logo'), 'pharmacy')['path'] ?? null;
        }
        $cover = null;
        if ($request->file('cover_image') !== null) {
            $cover = Upload::store($request->file('cover_image'), 'pharmacy')['path'] ?? null;
        }

        $docTypes = [
            'licence' => 'licence_document',
            'incorporation' => 'incorporation_document',
            'tax_cert' => 'tax_document',
            'identification' => 'identification_document',
        ];
        $stored    = [];
        foreach ($docTypes as $type => $field) {
            $file = $request->file($field);
            if ($file !== null) {
                $saved = Upload::store($file, 'document');
                if ($saved !== null) {
                    $stored[] = ['type' => $type, 'path' => $saved['path'], 'name' => $saved['name']];
                }
            }
        }

        $db     = Database::instance();
        $roleId = (int) $db->value("SELECT id FROM roles WHERE name = 'pharmacy_owner'");
        $slug   = $this->uniqueSlug((string) $data['name']);
        $token  = bin2hex(random_bytes(20));

        $pharmacyId = $db->transaction(static function () use ($data, $roleId, $slug, $logo, $cover, $stored, $token): int {
            $db = Database::instance();

            $ownerId = $db->insert('users', [
                'role_id'            => $roleId,
                'full_name'          => $data['pharmacist_name'],
                'email'              => strtolower((string) $data['email']),
                'phone'              => $data['phone'],
                'password_hash'      => Auth::hashPassword((string) $data['password']),
                'verification_token' => $token,
                'status'             => 'active',
            ]);
            $db->insert('user_roles', ['user_id' => $ownerId, 'role_id' => $roleId]);

            $pharmacyId = $db->insert('pharmacies', [
                'owner_id'            => $ownerId,
                'name'                => $data['name'],
                'slug'                => $slug,
                'legal_name'          => $data['legal_name'],
                'registration_number' => $data['registration_number'],
                'regulatory_body'     => $data['regulatory_body'],
                'pharmacist_name'     => $data['pharmacist_name'],
                'phone'               => $data['phone'],
                'email'               => strtolower((string) $data['email']),
                'website'             => $data['website'] ?? null,
                'logo'                => $logo,
                'cover_image'         => $cover,
                'description'         => $data['description'],
                'state'               => $data['state'],
                'city'                => $data['city'],
                'address'             => $data['address'],
                'latitude'            => (float) $data['latitude'],
                'longitude'           => (float) $data['longitude'],
                'open_time'           => $data['open_time'] . ':00',
                'close_time'          => $data['close_time'] . ':00',
                'delivery_available'  => 1,
                'pickup_available'    => 1,
                'delivery_radius_km'  => (float) ($data['delivery_radius_km'] ?? 10),
                'estimated_delivery_minutes' => (int) ($data['estimated_delivery_minutes'] ?? 60),
                'bank_name'           => $data['bank_name'],
                'bank_account_name'   => $data['bank_account_name'],
                'bank_account_number' => $data['bank_account_number'],
                'status'              => 'pending',
            ]);

            foreach ($stored as $doc) {
                $db->insert('pharmacy_documents', [
                    'pharmacy_id'   => $pharmacyId,
                    'doc_type'      => $doc['type'],
                    'file_path'     => $doc['path'],
                    'original_name' => $doc['name'],
                    'review_status' => 'pending',
                ]);
            }

            // Seed a wallet so payouts have somewhere to land.
            $db->insert('pharmacy_wallets', ['pharmacy_id' => $pharmacyId]);

            return $pharmacyId;
        });

        (new AuditService())->log('pharmacy.registered', 'pharmacy', $pharmacyId, 'Pharmacy submitted for verification');
        (new MailService())->sendPharmacySubmitted((string) $data['email'], (string) $data['name']);

        // Notify the compliance team.
        $notifications = new NotificationService();
        foreach ($notifications->adminIds() as $adminId) {
            $notifications->to(
                (int) $adminId,
                'pharmacy.registered',
                'New pharmacy awaiting verification',
                $data['name'] . ' submitted documents for review.',
                '/admin/pharmacies/pending',
                'pharmacy',
                $pharmacyId
            );
        }
        $notifications->send();

        Auth::login($db->first('SELECT * FROM users WHERE id = (SELECT owner_id FROM pharmacies WHERE id = ?)', ['id' => $pharmacyId]) ?? []);

        Session::success('Your pharmacy has been submitted for verification.');
        Session::info('We will review your licence documents, usually within two business days. You will be able to sign in once approved.');

        Response::redirect('/login');
    }

    // -----------------------------------------------------------------------

    private function uniqueSlug(string $name): string
    {
        $db   = Database::instance();
        $base = slugify_text($name);
        $slug = $base;
        $n    = 2;
        while ($db->value('SELECT id FROM pharmacies WHERE slug = ?', ['slug' => $slug]) !== null) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    private function uniqueSupplierSlug(string $name): string
    {
        $db = Database::instance();
        $base = slugify_text($name);
        $slug = $base;
        $number = 2;
        while ($db->value('SELECT id FROM suppliers WHERE slug = ?', [$slug]) !== null) {
            $slug = $base . '-' . $number++;
        }
        return $slug;
    }

    /** @return list<string> */
    private function states(): array
    {
        return [
            'Abia',
            'Adamawa',
            'Akwa Ibom',
            'Anambra',
            'Bauchi',
            'Bayelsa',
            'Benue',
            'Borno',
            'Cross River',
            'Delta',
            'Ebonyi',
            'Edo',
            'Ekiti',
            'Enugu',
            'FCT — Abuja',
            'Gombe',
            'Imo',
            'Jigawa',
            'Kaduna',
            'Kano',
            'Katsina',
            'Kebbi',
            'Kogi',
            'Kwara',
            'Lagos',
            'Nasarawa',
            'Niger',
            'Ogun',
            'Ondo',
            'Osun',
            'Oyo',
            'Plateau',
            'Rivers',
            'Sokoto',
            'Taraba',
            'Yobe',
            'Zamfara'
        ];
    }
}
