<?php

return [
    'dashboard' => 'Dashboard',
    'orders' => 'Orders',
    'tables' => 'Tables',
    'menu' => 'Menu',
    'staff' => 'Staff',
    'settings' => 'Settings',
    'restaurant' => 'Restaurant',
    'open' => 'Open',
    'close' => 'Close',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'language' => 'Language',

    'meal' => [
        'back_to_menu' => 'Back to menu',
        'chef_pick' => "Chef's pick",
        'prep_time' => ':minutes min from the grill',
        'sold_out_message' => 'This meal is currently sold out.',
        'contains' => 'Contains',
        'no_allergens' => 'No listed allergens',
        'allergens_nearby' => 'Cooked with allergens nearby',
        'choose_size' => 'Choose a size',
        'add_on' => 'Add on',
        'optional' => 'optional',
        'sold_out' => 'Sold out',
        'kitchen_note' => 'A note for the kitchen',
        'note_placeholder' => 'e.g. no onions, sauce on the side',
        'note_promise' => 'Notes reach the pass and the waiter, in saffron, so nobody misses them.',
        'add_to_order' => 'Add to order',
    ],
    'cart' => [
        'title' => 'Your order',
        'empty' => 'Your order is empty.',
        'back_to_menu' => 'Back to menu',
        'subtotal' => 'Subtotal',
        'quantity' => 'Quantity',
        'note' => 'Kitchen note',
    ],

    'errors' => [
        'back_home_staff' => 'Back to your screen',
        'back_home_guest' => 'Back to the start',
        'go_back' => 'Go back',
        '403' => [
            'title' => 'This isn’t open to you',
            'body' => 'Your account doesn’t have access to this screen. If you think it should, ask your manager.',
        ],
        '404' => [
            'title' => 'We couldn’t find that',
            'body' => 'The page may have moved, or the link is out of date.',
        ],
        '429' => [
            'title' => 'Slow down a moment',
            'body' => 'Too many requests in a short time. Try again in a minute.',
        ],
        '500' => [
            'title' => 'Something went wrong on our side',
            'body' => 'It isn’t anything you did. Try again in a moment.',
        ],
        '503' => [
            'title' => 'Back in a moment',
            'body' => 'QResto is being updated. This usually takes a minute.',
        ],
    ],
];
