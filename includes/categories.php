<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Available categories for the Treasury Tech Portal.
if ( ! defined( 'TTP_CATEGORIES' ) ) {
    define(
        'TTP_CATEGORIES',
        array(
            'CASH' => __( 'Cash Tools', 'treasury-tech-portal' ),
            'LITE' => __( 'Treasury Management System Lite (TMS-Lite)', 'treasury-tech-portal' ),
            'TRMS' => __( 'Treasury & Risk Management Systems (TRMS)', 'treasury-tech-portal' ),
        )
    );
}
