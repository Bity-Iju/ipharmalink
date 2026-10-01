<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Auth;
use App\Controller;
use App\Request;
use App\Response;
use App\Services\CartService;
use App\Session;

/**
 * Sign in / sign out.
 *
 * One controller serves three entry points because the credential check is
 * identical; only the redirect target and the surrounding chrome differ:
 *   /login          customers
 *   /pharmacy/login pharmacy owners and staff
 *   /admin/login    super administrators
 */
final class LoginController extends Controller
{
    // -----------------------------------------------------------------------
    //  Customer and delivery personnel share this door; each is sent to their
    //  own workspace afterwards.
    // -----------------------------------------------------------------------
    public function handle(Request $request): void
    {
        $this->authenticate($request, '/login', $this->customerHome(), 'Welcome back');
    }

    // -----------------------------------------------------------------------
    //  Pharmacy vendor
    // -----------------------------------------------------------------------
    public function pharmacyLogin(Request $request): void
    {
        $this->authenticate($request, '/pharmacy/login', '/pharmacy/dashboard', 'Pharmacy portal');
    }

    public function supplierLogin(Request $request): void
    {
        $this->authenticate($request, '/supplier/login', '/supplier/dashboard', 'Wholesale supplier portal');
    }

    // -----------------------------------------------------------------------
    //  Admin
    // -----------------------------------------------------------------------
    public function adminLogin(Request $request): void
    {
        $this->authenticate($request, '/admin/login', '/admin/dashboard', 'Administrator sign in');
    }

    // -----------------------------------------------------------------------
    //  /logout
    // -----------------------------------------------------------------------
    public function logout(Request $request): void
    {
        Auth::logout();
        Session::success('You have been signed out.');
        Response::redirect('/');
    }

    // -----------------------------------------------------------------------
    //  Shared
    // -----------------------------------------------------------------------

    /**
     * Render on GET, verify on POST.
     *
     * @param string $view   the form path, which also decides the view template
     * @param string $home   where a successful sign-in lands
     * @param string $heading page heading
     */
    private function authenticate(Request $request, string $view, string $home, string $heading): void
    {
        $allowedRoles = match ($view) {
            '/pharmacy/login' => ['pharmacy_owner', 'pharmacy_staff'],
            '/supplier/login' => ['wholesale_supplier'],
            '/admin/login'    => ['super_admin'],
            default           => ['customer', 'delivery_personnel'],
        };

        // /pharmacy/login -> auth/pharmacy-login
        $template = 'auth.' . str_replace('/', '-', ltrim($view, '/'));

        if (!$request->isPost()) {
            $this->view($template, [
                'title'   => $heading,
                'heading' => $heading,
                'home'    => $home,
            ]);
            return;
        }

        $identifier = trim((string) $request->input('identifier', ''));
        $password   = (string) $request->input('password', '');

        if ($identifier === '' || $password === '') {
            Session::flashInput($request->all());
            $this->view($template, [
                'title'   => $heading,
                'heading' => $heading,
                'home'    => $home,
                'errors'  => ['identifier' => ['Enter your email address or phone number.']],
            ]);
            return;
        }

        // Auth::attempt throws 403 for suspended/pending accounts and 429 when
        // the account is locked — both render as friendly pages.
        $user = Auth::attempt($identifier, $password);

        if ($user === null) {
            Session::flashInput($request->all());
            Session::error('Those details do not match our records. Please check and try again.');
            Response::redirect($view);
        }

        if (!in_array((string) $user['role'], $allowedRoles, true)) {
            // Signed-in but at the wrong door: sign them out again rather than
            // silently letting them into someone else's area.
            $roleName = (string) $user['role'];
            Auth::logout();
            Session::error(match ($roleName) {
                'super_admin'        => 'Administrator accounts must sign in from the admin portal.',
                'pharmacy_owner',
                'pharmacy_staff'     => 'Pharmacy accounts must sign in from the pharmacy portal.',
                'wholesale_supplier' => 'Supplier accounts must sign in from the supplier portal.',
                'delivery_personnel' => 'Delivery accounts must sign in from the delivery portal.',
                default              => 'Please sign in with the account you registered with.',
            });
            Response::redirect($view);
        }

        Auth::login($user, (bool) $request->bool('remember'));

        // Anything the visitor added before signing in comes with them.
        (new CartService())->mergeGuestCart();

        Session::success(match (true) {
            Auth::isSuperAdmin()    => 'Signed in to the administrator panel.',
            Auth::isPharmacy()      => 'Signed in to your pharmacy dashboard.',
            Auth::isSupplier()     => 'Signed in to your supplier workspace.',
            Auth::isDelivery()      => 'Welcome back, ' . strtok((string) $user['full_name'], ' ') . '! Your deliveries are ready.',
            default                 => 'Welcome back, ' . strtok((string) $user['full_name'], ' ') . '!',
        });

        Response::redirect(Session::pullIntendedUrl($home));
    }

    private function customerHome(): string
    {
        return \App\Auth::isDelivery() ? '/delivery/dashboard' : '/account';
    }
}
