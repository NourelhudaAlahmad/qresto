<?php

// Messages the server returns to people: validation errors, toasts and
// lockouts. Keep en and ar key-for-key; tests/Feature/ServerMessagesTest.php checks.

return [
    'cart' => [
        'empty' => 'Your cart is empty.',
        'item_invalid' => 'The selected menu item is invalid.',
        'item_unavailable' => 'This item is currently unavailable.',
        'variant_required' => 'Please select a variant.',
        'variant_invalid' => 'The selected variant is invalid.',
        'addons_invalid' => 'One or more selected add-ons are invalid.',
        'addons_unavailable' => 'One or more selected add-ons are unavailable.',
        'qty_max' => 'The total quantity may not be greater than 99.',
    ],

    'checkout' => [
        'tip_currency' => 'The tip currency does not match the cart currency.',
        'split_ways' => 'Split ways must be between 1 and 8.',
        'existing_order_no_payment' => 'The existing order does not have a payment.',
        'idempotency_key' => 'The idempotency key may not be greater than 100 characters.',
        'challenge_invalid' => 'This payment challenge is no longer valid.',
        'auth_not_completed' => 'The card authentication was not completed.',
        'auth_required' => 'Card authentication is still required.',
    ],

    'promo' => [
        'invalid' => 'This promo code is invalid.',
        'not_active' => 'This promo code is not active yet.',
        'expired' => 'This promo code has expired.',
        'limit_reached' => 'This promo code has reached its usage limit.',
    ],

    'refund' => [
        'only_paid' => 'Only paid payments can be refunded.',
        'currency' => 'The refund currency does not match the payment currency.',
        'positive' => 'The refund amount must be greater than zero.',
        'exceeds' => 'The refund amount exceeds the remaining payment amount.',
    ],

    'payment' => [
        'staff_required' => 'A staff member is required to settle this payment.',
        'no_intent' => 'This payment does not have a gateway intent.',
        'positive' => 'Payment amount must be greater than zero.',
        'already_paid_full' => 'This order has already been fully paid.',
        'exceeds_balance' => 'Payment amount exceeds the remaining balance.',
        'exceeds_total' => 'Payments exceed the order total.',
    ],

    'floor' => [
        'only_free_seated' => 'Only free tables can be seated.',
        'party_too_big' => 'Party size cannot exceed the table capacity.',
        'seat_unpaid' => 'Cannot seat a table with an unpaid order.',
        'free_unpaid' => 'Cannot free this table while an unpaid bill exists.',
        'order_not_found' => 'Order not found for this table.',
        'already_paid' => 'This order has already been paid.',
        'serve_first' => 'The order must be served before payment.',
        'exceeds_balance' => 'Payment exceeds the remaining balance.',
        'method_mismatch' => 'Payment method does not match the order.',
        'pending_as_recorded' => 'An existing pending payment must be settled as recorded.',
        'payment_failed' => 'Payment could not be completed.',
    ],

    'orders' => [
        'cannot_advance' => 'This order cannot be advanced.',
        'cannot_void' => 'This order cannot be voided.',
        'reassign_waiter' => 'Select an active waiter from this restaurant.',
        'reassign_active_only' => 'Only active orders can be reassigned.',
    ],

    'menu' => [
        'category_not_empty' => 'Move or delete its dishes first.',
        'photo_dimensions' => 'Use a 4:3 photo at least 1200px wide.',
        'photo_type' => 'Use a JPEG, PNG or WebP photo.',
        'price_format' => 'Enter a price like 18 or 18.50.',
        'dish_on_open_order' => ':dish is on an open order. Delete it once those orders are paid.',
    ],

    'reports' => [
        'range_max' => 'Pick a range of at most a year.',
        'end_after_start' => 'The end date must be on or after the start date.',
    ],

    'auth' => [
        'shift_qr_invalid' => 'Invalid or expired shift QR.',
        'shift_qr_used' => 'Invalid, expired, or already used shift QR.',
        'account_unavailable' => 'This account is unavailable.',
        'locked_after_attempts' => 'Account locked for 10 minutes after 3 failed sign-in attempts.',
        'shift_qr_not_authorized' => 'Not authorized to issue a shift QR for this staff member.',
        'locked_for' => 'Account locked. Try again in :minutes minute(s).',
    ],

    'tables' => [
        'number_taken' => 'There is already a table with that number.',
        'number_format' => 'Use letters, numbers, spaces or dashes.',
        'has_guests' => 'Table :table has guests right now. Free it first.',
        'unpaid_bill' => 'Table :table still has an unpaid bill.',
    ],

    'restaurant' => [
        'currency' => 'Pick a currency from the list.',
        'timezone' => 'Pick a timezone from the list.',
        'locale_unavailable' => 'That language is not available yet.',
        'default_locale' => 'The default language must be one of the languages you offer.',
        'service_charge_max' => 'A service charge above 30% is almost certainly a typo.',
    ],

    'staff' => [
        'name_required' => 'Enter the full name.',
        'email_required' => 'Enter a work email.',
        'email_format' => 'Enter a work email, like name@albustan.co.',
        'email_taken' => 'Someone already uses this email.',
        'role_allowed' => 'You can add waiters, runners, cashiers and kitchen staff only.',
        'table_not_on_floor' => 'One of those tables is not on this floor.',
    ],

    'locale' => [
        'unsupported' => 'The selected locale is not supported by this restaurant.',
    ],

    'settings' => [
        'profile_updated' => 'Profile updated.',
        'password_updated' => 'Password updated.',
    ],
];
