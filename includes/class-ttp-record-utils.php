<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TTP_Record_Utils {
    /**
     * Check if an array contains Airtable record IDs.
     *
     * Strips non-alphanumeric characters and performs a case-insensitive search
     * for the `rec` prefix anywhere in the value so IDs wrapped in extra text
     * or mixed casing are detected. Numeric-only strings are also treated as
     * unresolved record IDs as some linked records may appear as plain numbers
     * in the source data.
     *
     * @param array $values Values to inspect.
     * @return bool
     */
    public static function contains_record_ids( $values ) {
        foreach ( (array) $values as $value ) {
            if ( is_array( $value ) || is_object( $value ) ) {
                if ( self::contains_record_ids( (array) $value ) ) {
                    return true;
                }
                continue;
            }

            $candidate = preg_replace( '/[^A-Za-z0-9]/', '', (string) $value );

            if ( $candidate === '' ) {
                continue;
            }

            if ( ctype_digit( $candidate ) ) {
                return true;
            }

            if (
                preg_match(
                    '/^(?:r(?:ec|es|cs|cx)|sel|opt)[0-9a-z]*\\d[0-9a-z]*$/i',
                    $candidate
                )
            ) {
                return true;
            }
        }
        return false;
    }
}
