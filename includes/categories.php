<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define shared categories for the Treasury Tech Portal.
if ( ! defined( 'TTP_CATEGORIES' ) ) {
    define(
        'TTP_CATEGORIES',
        [
            'CASH' => 'Cash Tools',
            'LITE' => 'TMS Lite',
            'TRMS' => 'Enterprise TRMS',
        ]
    );
}

