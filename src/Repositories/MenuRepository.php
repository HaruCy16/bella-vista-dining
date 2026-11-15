<?php
declare(strict_types=1);

final class MenuRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param list<int> $itemIds @return array<int, array<string, mixed>> */
    public function availableItemsByIds(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $statement = $this->pdo->prepare(
            "SELECT item_id, item_name, price
             FROM menu_items
             WHERE is_available = 1 AND item_id IN ({$placeholders})"
        );
        $statement->execute($itemIds);
        $items = [];
        foreach ($statement->fetchAll() as $item) {
            $items[(int) $item['item_id']] = $item;
        }
        return $items;
    }

    /** @return list<array{category_id: int, category_name: string, category_image: string|null, items: list<array<string, mixed>>}> */
    public function categoriesWithItems(): array
    {
        $statement = $this->pdo->query(
            'SELECT c.category_id, c.category_name, c.image_path AS category_image,
                    i.item_id, i.item_name, i.description, i.price, i.image_path,
                    i.is_available
             FROM menu_categories c
             LEFT JOIN menu_items i
               ON i.category_id = c.category_id AND i.is_available = 1
             ORDER BY c.sort_order, c.category_id, i.item_id'
        );
        $categories = [];
        foreach ($statement->fetchAll() as $row) {
            $categoryId = (int) $row['category_id'];
            if (!isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => (string) $row['category_name'],
                    'category_image' => $row['category_image'] !== null ? (string) $row['category_image'] : null,
                    'items' => [],
                ];
            }
            if ($row['item_id'] !== null) {
                $categories[$categoryId]['items'][] = [
                    'item_id' => (int) $row['item_id'],
                    'name' => (string) $row['item_name'],
                    'description' => (string) ($row['description'] ?? ''),
                    'price' => (string) $row['price'],
                    'image' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
                ];
            }
        }
        return array_values($categories);
    }

    /** @return list<array<string, mixed>> */
    public function allItems(): array
    {
        return $this->pdo->query(
            'SELECT i.item_id, i.item_name, i.description, i.price, i.is_available,
                    c.category_name
             FROM menu_items i INNER JOIN menu_categories c ON c.category_id = i.category_id
             ORDER BY c.sort_order, c.category_id, i.item_name'
        )->fetchAll();
    }

    public function setAvailability(int $itemId, bool $available): bool
    {
        $statement = $this->pdo->prepare('UPDATE menu_items SET is_available = :available WHERE item_id = :id');
        $statement->execute(['available' => (int) $available, 'id' => $itemId]);
        if ($statement->rowCount() === 1) {
            return true;
        }
        $exists = $this->pdo->prepare('SELECT 1 FROM menu_items WHERE item_id = :id');
        $exists->execute(['id' => $itemId]);
        return (bool) $exists->fetchColumn();
    }
}
