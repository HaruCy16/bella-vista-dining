<?php
declare(strict_types=1);

/*
 * Admin / kitchen JSON API. Every action checks the role first.
 *   GET  ?action=dashboard                 both roles: board + kitchen queue (+ report for managers)
 *   GET  ?action=report&date=Y-m-d          Manager only
 *   POST {action: "table-status", table_id, status}        both roles
 *   POST {action: "check-in" | "finish", reservation_id}   both roles
 *   POST {action: "order-status", order_id, status}        both roles (transition rules apply)
 */
require_once __DIR__ . '/../includes/bootstrap.php';

Response::api(static function (): array {
    $now = Config::now();
    $ops = OperationsService::make(db());

    if (Request::method() === 'GET') {
        $action = (string) ($_GET['action'] ?? 'dashboard');
        if ($action === 'report') {
            Auth::requireRole(Auth::MANAGER);
            $v = new Validator($_GET);
            $date = $v->date('date');
            $v->validate();
            return $ops->dailyReport($date);
        }
        if ($action !== 'dashboard') {
            throw new ValidationException('Unknown action.');
        }
        $admin = Auth::requireRole(Auth::MANAGER, Auth::KITCHEN);
        $housekeeping = (new HousekeepingService(db()))->run($now);
        return [
            'now' => $now->format('Y-m-d H:i:s'),
            'housekeeping' => $housekeeping,
            'board' => $ops->board($now),
            'queue' => $ops->kitchenQueue($now),
            'report' => Auth::isManager($admin) ? $ops->dailyReport($now->format('Y-m-d')) : null,
        ];
    }

    Request::requireMethod('POST');
    Auth::requireRole(Auth::MANAGER, Auth::KITCHEN);
    Request::requireCsrf();
    $input = Request::json();
    $v = new Validator($input);

    switch ($input['action'] ?? '') {
        case 'table-status':
            $tableId = $v->string('table_id', 'Table', 10);
            $status = $v->in('status', 'status', OperationsService::TABLE_STATUSES);
            $v->validate();
            $ops->setTableStatus($tableId, $status);
            break;
        case 'check-in':
        case 'finish':
            $id = $v->int('reservation_id', 'Booking', 1, PHP_INT_MAX);
            $v->validate();
            $input['action'] === 'check-in' ? $ops->checkIn($id, $now) : $ops->finish($id);
            break;
        case 'order-status':
            $id = $v->int('order_id', 'Order', 1, PHP_INT_MAX);
            $status = $v->in('status', 'status', array_keys(OperationsService::ORDER_TRANSITIONS));
            $v->validate();
            $ops->setOrderStatus($id, $status, $now);
            break;
        default:
            throw new ValidationException('Unknown action.');
    }
    return ['ok' => true];
});
