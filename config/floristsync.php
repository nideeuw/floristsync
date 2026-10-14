<?php

return [
    // A product is "low stock" when stock <= this value.
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    // Used to estimate parcel weight (the ERD has no weight column).
    'default_item_weight_gram' => (int) env('DEFAULT_ITEM_WEIGHT_GRAM', 1000),

    'whatsapp_number' => env('WHATSAPP_NUMBER', '6281217775489'),

    // Fallback opening hours, used until the owner saves store settings.
    'opening_hours' => array_fill_keys(
        ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ['closed' => false, 'open' => '08:00', 'close' => '20:00']
    ),
];
