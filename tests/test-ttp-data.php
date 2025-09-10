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

    public function test_refresh_vendor_cache_maps_fields() {
        $record = [
            'id' => 'rec1',
            'fields' => [
                'Product Name'   => 'Sample Product',
                'Linked Vendor'  => 'Acme Corp',
                'Product Website'=> 'https://example.com',
                'Status'         => 'Active',
                'Hosted Type'    => ['Cloud'],
            ],
        ];

        \Patchwork\replace('TTP_Airbase::get_vendors', function () use ($record) {
            return ['records' => [ $record ]];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();

        $expected = [
            [
                'name'        => 'Sample Product',
                'vendor'      => 'Acme Corp',
                'website'     => 'https://example.com',
                'status'      => 'Active',
                'hosted_type' => ['Cloud'],
                'domain'      => [],
                'regions'     => [],
                'sub_categories' => [],
                'parent_category' => '',
                'capabilities' => [],
                'logo_url'    => '',
                'hq_location' => '',
                'founded_year'=> '',
                'founders'    => '',
            ],
        ];

        $this->assertSame($expected, $captured);
    }
}
