<?php
/**
 * Simple utility to fetch vendors from Airbase and display them in a table.
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
    // Refresh vendor cache which also resolves linked record IDs.
    $result = TTP_Data::refresh_vendor_cache();
    if ( is_wp_error( $result ) ) {
        echo 'Error: ' . $result->get_error_message() . PHP_EOL;
        return;
    }

    $vendors = TTP_Data::get_all_vendors();
    if ( empty( $vendors ) ) {
        echo "No vendors retrieved." . PHP_EOL;
        return;
    }

    // Determine table columns from the first vendor record.
    $columns = array_keys( $vendors[0] );

    echo "<table>\n<tr>";
    foreach ( $columns as $col ) {
        $label = ucwords( str_replace( '_', ' ', $col ) );
        echo '<th>' . htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) . '</th>';
    }
    echo "</tr>\n";

    foreach ( $vendors as $vendor ) {
        echo '<tr>';
        foreach ( $columns as $col ) {
            $value = $vendor[ $col ] ?? '';
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
