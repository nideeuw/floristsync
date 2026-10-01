<?php

return [
    // A product is "low stock" when stock <= this value.
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    // Used to estimate parcel weight (the ERD has no weight column).
    'default_item_weight_gram' => (int) env('DEFAULT_ITEM_WEIGHT_GRAM', 1000),

    'whatsapp_number' => env('WHATSAPP_NUMBER', '6281217775489'),
];
