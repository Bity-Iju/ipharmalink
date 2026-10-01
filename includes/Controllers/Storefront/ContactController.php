<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Controller;
use App\Database;
use App\Request;
use App\Services\NotificationService;
use App\Setting;

/**
 * /contact — public enquiry form. Rate limited and stored for the admin inbox.
 */
final class ContactController extends Controller
{
    public function handle(Request $request): void
    {
        if (!$request->isPost()) {
            $this->view('storefront/contact', [
                'title'           => 'Contact us',
                'metaDescription' => 'Get in touch with the ' . Setting::getString('general.platform_name', 'iPharmaLink') . ' support team.',
                'heading'         => 'Contact us',
            ]);
            return;
        }

        $data = $this->validate(
            [
                'name'    => 'required|string|min:2|max:120',
                'email'   => 'required|email|max:190',
                'phone'   => 'nullable|phone|max:32',
                'subject' => 'required|string|min:3|max:190',
                'message' => 'required|string|min:10|max:4000',
            ],
            $request->all(),
            'storefront/contact',
            '/contact',
            ''
        );

        Database::instance()->insert('contact_messages', [
            'name'       => $data['name'],
            'email'      => strtolower((string) $data['email']),
            'phone'      => $data['phone'] ?? null,
            'subject'    => $data['subject'],
            'message'    => $data['message'],
            'ip_address' => $request->ip(),
            'status'     => 'new',
        ]);

        // Alert the admins so an enquiry is never left sitting in the table.
        $notifications = new NotificationService();
        foreach ($notifications->adminIds() as $adminId) {
            $notifications->to(
                (int) $adminId,
                'contact.message',
                'New contact message',
                $data['subject'],
                '/admin/contact-messages'
            );
        }
        $notifications->send(false, false);

        \App\Session::success('Thanks for reaching out. Our support team will respond within one business day.');
        \App\Response::redirect('/contact');
    }
}
