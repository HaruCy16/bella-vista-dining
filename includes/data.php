<?php

declare(strict_types=1);

$brand = [
    'name' => 'Bella Vista Dining',
    'tagline' => 'Farm-to-Table Flavors, Unforgettable Moments.',
    'location' => 'Bella Vista Estate, Tagaytay, Philippines',
    'email' => 'hello@bellavistadining.ph',
    'phone' => '+63 917 246 0811',
];

$menuItems = [
    [
        'id' => 'crispy-pata',
        'name' => 'Crispy Pata',
        'category' => 'Main Courses',
        'category_key' => 'mains',
        'description' => 'Slow-braised pork knuckle finished crisp, with cane vinegar and garden herbs.',
        'price' => 1380,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-3.webp',
        'badge' => 'House favorite',
        'available' => true,
    ],
    [
        'id' => 'beef-kare-kare',
        'name' => 'Beef Kare-Kare',
        'category' => 'Main Courses',
        'category_key' => 'mains',
        'description' => 'Tender beef, young vegetables, and silky peanut sauce with house bagoong.',
        'price' => 1180,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-3.webp',
        'badge' => 'Estate classic',
        'available' => true,
    ],
    [
        'id' => 'grilled-salmon',
        'name' => 'Grilled Salmon',
        'category' => 'Main Courses',
        'category_key' => 'mains',
        'description' => 'Fire-grilled salmon, citrus beurre blanc, and greens from our kitchen garden.',
        'price' => 1280,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-5.webp',
        'badge' => 'Seasonal',
        'available' => true,
    ],
    [
        'id' => 'caesar-salad',
        'name' => 'Caesar Salad',
        'category' => 'Appetizers & Soups',
        'category_key' => 'starters',
        'description' => 'Crisp romaine, parmesan, toasted sourdough, and a bright anchovy dressing.',
        'price' => 420,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-5.webp',
        'badge' => 'Garden fresh',
        'available' => true,
    ],
    [
        'id' => 'sinigang-na-hipon',
        'name' => 'Sinigang na Hipon',
        'category' => 'Appetizers & Soups',
        'category_key' => 'starters',
        'description' => 'Tamarind broth, river prawns, radish, and greens from the estate garden.',
        'price' => 580,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-5.webp',
        'badge' => 'Comfort bowl',
        'available' => true,
    ],
    [
        'id' => 'creamy-mushroom-soup',
        'name' => 'Creamy Mushroom Soup',
        'category' => 'Appetizers & Soups',
        'category_key' => 'starters',
        'description' => 'Roasted local mushrooms, cultured cream, and a little cracked pepper.',
        'price' => 360,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp',
        'badge' => 'Slow afternoon',
        'available' => true,
    ],
    [
        'id' => 'halo-halo-cheesecake',
        'name' => 'Halo-Halo Cheesecake',
        'category' => 'Desserts & Drinks',
        'category_key' => 'desserts',
        'description' => 'Silky cheesecake, ube, toasted coconut, and a playful halo-halo finish.',
        'price' => 480,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp',
        'badge' => 'A sweet ending',
        'available' => true,
    ],
    [
        'id' => 'fresh-fruit-shakes',
        'name' => 'Fresh Fruit Shakes',
        'category' => 'Desserts & Drinks',
        'category_key' => 'desserts',
        'description' => 'Seasonal fruit blended to order; ask about today’s garden and orchard picks.',
        'price' => 280,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp',
        'badge' => 'Made to order',
        'available' => true,
    ],
    [
        'id' => 'iced-tea',
        'name' => 'House Iced Tea',
        'category' => 'Desserts & Drinks',
        'category_key' => 'desserts',
        'description' => 'Black tea steeped with calamansi, lemongrass, and a touch of muscovado.',
        'price' => 180,
        'image' => '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp',
        'badge' => 'Always refreshing',
        'available' => true,
    ],
];

$tableTypes = [
    [
        'id' => 'standard',
        'label' => 'Standard table',
        'details' => '2–4 guests · Tables S1–S4',
        'fee' => 0,
        'minimum' => 1000,
        'capacity' => '2–4 guests',
        'note' => 'A ₱1,000 minimum food order applies.',
    ],
    [
        'id' => 'family',
        'label' => 'Family table',
        'details' => '5–8 guests · Tables F1–F3',
        'fee' => 500,
        'minimum' => 0,
        'capacity' => '5–8 guests',
        'note' => 'The ₱500 reservation fee is fully consumable.',
    ],
    [
        'id' => 'vip',
        'label' => 'VIP function room',
        'details' => 'Up to 20 guests · VIP Room 1–2',
        'fee' => 2500,
        'minimum' => 0,
        'capacity' => 'Up to 20 guests',
        'note' => '₱300 per guest over 15, up to 20 guests.',
    ],
];

$hours = [
    ['day' => 'Monday', 'hours' => 'Closed for maintenance', 'closed' => true],
    ['day' => 'Tuesday – Sunday', 'hours' => '10:00 AM – 10:00 PM', 'closed' => false],
];

$timeSlots = [
    '10:00 AM – 12:00 PM',
    '12:00 PM – 2:00 PM',
    '2:00 PM – 4:00 PM',
    '4:00 PM – 6:00 PM',
    '6:00 PM – 8:00 PM',
    '8:00 PM – 10:00 PM',
];

function peso(int|float $amount): string
{
    return '₱' . number_format((float) $amount, 0);
}

function vipExtraFee(int $guests): int
{
    return max(0, $guests - 15) * 300;
}

function findMenuItem(string $id): ?array
{
    global $menuItems;

    foreach ($menuItems as $item) {
        if ($item['id'] === $id) {
            return $item;
        }
    }

    return null;
}
