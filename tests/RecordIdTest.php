<?php
use PHPUnit\Framework\TestCase;

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field( $str ) {
        return is_scalar( $str ) ? (string) $str : '';
    }
}

require_once __DIR__ . '/../includes/class-ttp-data.php';

class RecordIdTest extends TestCase {
    public function test_detects_rec_and_res_ids() {
        $this->assertTrue( TTP_Data::contains_record_ids( 'recABCDEFGHIJKLMN' ) );
        $this->assertTrue( TTP_Data::contains_record_ids( 'res1234567890ABCD' ) );
    }

    public function test_ignores_regular_strings() {
        $this->assertFalse( TTP_Data::contains_record_ids( 'Resources' ) );
        $this->assertFalse( TTP_Data::contains_record_ids( 'Rest of World' ) );
    }
}
