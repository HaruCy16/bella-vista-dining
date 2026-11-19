<?php
declare(strict_types=1);

/*
 * Admin / kitchen JSON API. Every action checks the role first.
 *   GET  ?action=dashboard                 both roles: board + kitchen queue (+ report for managers)
 *   GET  ?action=report&date=Y-m-d          Manager only
 *   GET  ?action=reservations&date=&status=&block=&type=&payment=&q=   both roles
 *   GET  ?action=reservation&id=           both roles: one booking in full
 *   GET  ?action=customers | customer&id=  Manager only (guest personal data)
 *   POST {action: "table-status", table_id, status}        both roles
 *   POST {action: "check-in" | "finish", reservation_id}   both roles
 *   POST {action: "order-status", order_id, status}        both roles (transition rules apply)
 *   POST {action: "staff-book", ...booking fields}          both roles: phone / advance booking, pay at the restaurant
 *   POST {action: "menu-availability", item_id, available}  Manager only
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
        if ($action === 'reservations') {
            Auth::requireRole(Auth::MANAGER, Auth::KITCHEN);
            $v = new Validator($_GET);
            $filters = ['date' => isset($_GET['date']) && $_GET['date'] !== '' ? $v->date('date') : $now->format('Y-m-d')];
            $optional = [
                'status' => ['status', ['Pending', 'Confirmed', 'Checked-in', 'Completed', 'Cancelled', 'No-show']],
                'block' => ['time block', Config::reservationSlots()],
                'type' => ['table category', ['Standard', 'Family', 'VIP']],
                'payment' => ['payment', ['Unpaid', 'Downpayment', 'Paid', 'On-site']],
            ];
            foreach ($optional as $key => [$label, $allowed]) {
                if (isset($_GET[$key]) && $_GET[$key] !== '') {
                    $filters[$key] = $v->in($key, $label, $allowed);
                }
            }
            if (isset($_GET['q']) && $_GET['q'] !== '') {
                $filters['q'] = $v->string('q', 'Search', 60);
            }
            $v->validate();
            (new HousekeepingService(db()))->run($now);
            return $ops->reservationList($filters);
        }
        if ($action === 'reservation') {
            Auth::requireRole(Auth::MANAGER, Auth::KITCHEN);
            $v = new Validator($_GET);
            $id = $v->int('id', 'Booking', 1, PHP_INT_MAX);
            $v->validate();
            return $ops->reservationDetail($id, $now);
        }
        if ($action === 'customers') {
            Auth::requireRole(Auth::MANAGER);
            return $ops->customerDirectory($now);
        }
        if ($action === 'customer') {
            Auth::requireRole(Auth::MANAGER);
            $v = new Validator($_GET);
            $id = $v->int('id', 'Customer', 1, PHP_INT_MAX);
            $v->validate();
            return $ops->customerRecord($id);
        }
        if ($action !== 'dashboard') {
            throw new ValidationException('Unknown action.');
        }
        $admin = Auth::requireRole(Auth::MANAGER, Auth::KITCHEN);
        $housekeeping = (new HousekeepingService(db()))->run($now);
        $menu = new MenuRepository(db());
        return [
            'now' => $now->format('Y-m-d H:i:s'),
            'housekeeping' => $housekeeping,
            'board' => $ops->board($now),
            'queue' => $ops->kitchenQueue($now),
            'report' => Auth::isManager($admin) ? $ops->dailyReport($now->format('Y-m-d')) : null,
            'menu' => Auth::isManager($admin) ? $menu->allItems() : [],
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
        case 'staff-book':
            // Phone / advance bookings only: payment is at the restaurant, and the booking rules (slot not
            // started, table fits, minimum order) are the same ones guests get. The staff member confirms the
            // policies with the guest, which is what terms_accepted records.
            $booking = ReservationService::make(db())->book([
                'first_name' => $input['first_name'] ?? null, 'last_name' => $input['last_name'] ?? null,
                'email' => $input['email'] ?? null, 'phone_number' => $input['phone_number'] ?? null,
                'reservation_date' => $input['reservation_date'] ?? null, 'start_time' => $input['start_time'] ?? null,
                'number_of_guests' => $input['number_of_guests'] ?? null, 'table_id' => $input['table_id'] ?? null,
                'cart' => [], 'payment_option' => 'onsite', 'terms_accepted' => $input['terms_accepted'] ?? null,
            ], $now);
            return ['ok' => true, 'reservation_id' => $booking['reservation_id'], 'reference_code' => $booking['reference_code']];
        case 'menu-availability':
            Auth::requireRole(Auth::MANAGER);
            $id = $v->int('item_id', 'Menu item', 1, PHP_INT_MAX);
            $available = filter_var($input['available'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($available === null) {
                throw ValidationException::field('available', 'Availability must be true or false.');
            }
            $v->validate();
            if (!(new MenuRepository(db()))->setAvailability($id, $available)) {
                throw new NotFoundException('Menu item not found.');
            }
            break;
        default:
            throw new ValidationException('Unknown action.');
    }
    return ['ok' => true];
});
