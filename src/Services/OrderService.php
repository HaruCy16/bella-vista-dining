<?php
declare(strict_types=1);

/**
 * Prices carts from the database and stores pre-orders. The server is the only
 * source of prices and totals: anything the client sends besides item ids and
 * quantities is ignored.
 */
final class OrderService
{
    public function __construct(
        private readonly MenuRepository $menu,
        private readonly OrderRepository $orders,
    ) {
    }

    /**
     * @param array<int, int> $cart item_id => quantity (already validated as positive ints)
     * @return array{items: list<array{item_id: int, item_name: string, unit_price_centavos: int, quantity: int, subtotal_centavos: int}>, total_centavos: int}
     */
    public function price(array $cart): array
    {
        if ($cart === []) {
            return ['items' => [], 'total_centavos' => 0];
        }
        $menuItems = $this->menu->availableItemsByIds(array_keys($cart));
        $missing = array_diff(array_keys($cart), array_keys($menuItems));
        if ($missing !== []) {
            throw ValidationException::field('cart', 'One of the dishes in your pre-order is no longer available. Please review your order.');
        }

        $items = [];
        $total = 0;
        foreach ($cart as $itemId => $quantity) {
            $unit = Money::fromPesos((string) $menuItems[$itemId]['price']);
            $subtotal = $unit * $quantity;
            $total += $subtotal;
            $items[] = [
                'item_id' => $itemId,
                'item_name' => (string) $menuItems[$itemId]['item_name'],
                'unit_price_centavos' => $unit,
                'quantity' => $quantity,
                'subtotal_centavos' => $subtotal,
            ];
        }
        return ['items' => $items, 'total_centavos' => $total];
    }

    /**
     * Minimum-order rule (client spec III): Standard tables have a ₱1,000 minimum
     * "upon seating". Pre-ordering is optional; when a guest does pre-order, the
     * pre-order must reach the minimum. Otherwise the minimum applies to the final bill.
     */
    public function assertMinimumMet(int $orderTotal, int $minimumOrder): void
    {
        if ($orderTotal > 0 && $minimumOrder > 0 && $orderTotal < $minimumOrder) {
            throw ValidationException::field('cart', sprintf(
                'Pre-orders for this table must be at least %s. Add %s more, or remove the pre-order and order on arrival.',
                Money::format($minimumOrder),
                Money::format($minimumOrder - $orderTotal)
            ));
        }
    }

    /**
     * Stores a priced pre-order for a reservation. Must run inside the caller's
     * transaction. Returns the order id and the stored total.
     *
     * @param array{items: list<array<string, int|string>>, total_centavos: int} $priced
     * @return array{order_id: int, total_centavos: int}
     */
    public function createPreOrder(int $reservationId, string $orderDate, array $priced): array
    {
        $orderId = $this->orders->insertPreOrder($reservationId, $orderDate);
        $this->orders->insertItems($orderId, $priced['items']);
        $stored = $this->orders->refreshTotal($orderId);
        if ($stored !== $priced['total_centavos']) {
            throw new RuntimeException("Order {$orderId} total mismatch: stored {$stored}, priced {$priced['total_centavos']}.");
        }
        return ['order_id' => $orderId, 'total_centavos' => $stored];
    }
}
