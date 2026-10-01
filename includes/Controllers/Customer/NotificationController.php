<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Auth;
use App\Controller;
use App\Database;
use App\Request;
use App\Response;
use App\Services\NotificationService;
use App\Session;

/**
 * In-app notification centre for the signed-in customer.
 */
final class NotificationController extends Controller
{
    // -----------------------------------------------------------------------
    //  GET /account/notifications
    // -----------------------------------------------------------------------
    public function index(Request $request): void
    {
        $userId = (int) Auth::id();
        $db     = Database::instance();

        $filter = (string) $request->query('filter', '');

        $paginator = \App\Paginator::build(
            static fn(Database $db): int => (int) $db->value(
                'SELECT COUNT(*) FROM notifications WHERE user_id = ?',
                ['user_id' => $userId]
            ),
            static function (Database $db, int $perPage, int $offset) use ($userId): array {
                return $db->all(
                    'SELECT * FROM notifications WHERE user_id = ?
                     ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
                    ['user_id' => $userId]
                );
            },
            $this->perPage(15),
            $this->page()
        );

        $this->view('customer/notifications', [
            'title'     => 'Notifications',
            'heading'   => 'Notifications',
            'paginator' => $paginator,
            'unread'    => NotificationService::unreadCount($userId),
            'filter'    => $filter,
        ], 'layouts/dashboard');
    }

    // -----------------------------------------------------------------------
    //  POST /account/notifications/{id}/read
    // -----------------------------------------------------------------------
    public function read(Request $request, array $params): void
    {
        Database::instance()->run(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = ? AND user_id = ? AND is_read = 0',
            ['id' => (int) $this->param('id', $params), 'user_id' => Auth::id()]
        );

        Response::back('/account/notifications');
    }

    // -----------------------------------------------------------------------
    //  POST /account/notifications/read-all
    // -----------------------------------------------------------------------
    public function readAll(Request $request): void
    {
        $affected = Database::instance()->run(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0',
            ['user_id' => Auth::id()]
        )->rowCount();

        $affected > 0
            ? Session::success(sprintf('%d notification%s marked as read.', $affected, $affected === 1 ? '' : 's'))
            : Session::info('You have no unread notifications.');

        Response::redirect('/account/notifications');
    }
}
