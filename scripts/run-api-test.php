<?php
/**
 * Simple utility to fetch products from Airbase and display them in a table.
 *
 * Usage: php scripts/run-api-test.php
 */

require_once dirname(__DIR__) . '/includes/class-ttp-airbase.php';
require_once dirname(__DIR__) . '/includes/class-ttp-data.php';

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

function run_api_test() {
    // Refresh product cache and rebuild product-field ID\u2192name mappings.
    $result = TTP_Data::refresh_product_cache();
    if ( is_wp_error( $result ) ) {
        echo 'Error: ' . $result->get_error_message() . PHP_EOL;
        return;
    }

    $products = TTP_Data::get_all_products();
    if ( empty( $products ) ) {
        echo "No products retrieved." . PHP_EOL;
        return;
    }

    // Determine table columns from the first product record.
    $columns = array_keys( $products[0] );

    echo "<table>\n<tr>";
    foreach ( $columns as $col ) {
        $label = ucwords( str_replace( '_', ' ', $col ) );
        echo '<th>' . htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) . '</th>';
    }
    echo "</tr>\n";

    foreach ( $products as $product ) {
        echo '<tr>';
        foreach ( $columns as $col ) {
            $value = $product[ $col ] ?? '';
            if ( is_array( $value ) ) {
                $value = implode( ', ', $value );
            }
            echo '<td>' . htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ) . '</td>';
        }
        echo "</tr>\n";
    }
    echo "</table>\n";
}

run_api_test();
