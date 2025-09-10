<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-data.php';
require_once __DIR__ . '/../includes/class-ttp-airbase.php';

class TTP_Data_Test extends TestCase {
    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when('is_wp_error')->alias(function ($thing) {
            return $thing instanceof WP_Error;
        });
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
        \Brain\Monkey\tearDown();
    }

    public function test_refresh_vendor_cache_handles_records_response() {
        \Patchwork\replace('TTP_Airbase::get_vendors', function () {
            return ['records' => [ ['id' => 1, 'name' => 'Vendor R'] ]];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();
        $this->assertSame([ ['id' => 1, 'name' => 'Vendor R'] ], $captured);
    }

    public function test_refresh_vendor_cache_handles_products_response() {
        \Patchwork\replace('TTP_Airbase::get_vendors', function () {
            return ['products' => [ ['id' => 2, 'name' => 'Vendor P'] ]];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();
        $this->assertSame([ ['id' => 2, 'name' => 'Vendor P'] ], $captured);
    }

    public function test_refresh_vendor_cache_handles_vendors_response() {
        \Patchwork\replace('TTP_Airbase::get_vendors', function () {
            return ['vendors' => [ ['id' => 3, 'name' => 'Vendor V'] ]];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();
        $this->assertSame([ ['id' => 3, 'name' => 'Vendor V'] ], $captured);
    }
}
