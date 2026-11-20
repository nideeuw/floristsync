<?php

return [
    // A product is "low stock" when stock <= this value.
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    // Used to estimate parcel weight (the ERD has no weight column).
    'default_item_weight_gram' => (int) env('DEFAULT_ITEM_WEIGHT_GRAM', 1000),

    // Stock is held while an online order waits for payment. A member may have at most this many
    // unpaid (pending_payment) online orders at the same time. 0 turns the limit off.
    'max_pending_orders' => (int) env('MAX_PENDING_ORDERS', 3),

    'whatsapp_number' => env('WHATSAPP_NUMBER', '6281217775489'),

    'store_address' => env('STORE_ADDRESS', 'Jl. Merbabu No. 12, Malang'),
    'phone' => env('STORE_PHONE', '0812-3456-7890'),

    'pos_cancel_window_minutes' => (int) env('POS_CANCEL_WINDOW_MINUTES', 30),

    'auto_complete_shipped_days' => (int) env('AUTO_COMPLETE_SHIPPED_DAYS', 3),

    // Delivery date rules at checkout. Lead time and cut-off can be changed in store settings.
    'min_lead_hours' => (int) env('MIN_LEAD_HOURS', 3),
    'same_day_cutoff_hour' => (int) env('SAME_DAY_CUTOFF_HOUR', 16),
    'max_advance_days' => (int) env('MAX_ADVANCE_DAYS', 60),

    // Fallback opening hours, used until the owner saves store settings.
    'opening_hours' => array_fill_keys(
        ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ['closed' => false, 'open' => '08:00', 'close' => '20:00']
    ),
];
