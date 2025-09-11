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
                'Product Name'    => 'Sample Product',
                'Linked Vendor'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Product Video'   => 'example.com/video',
                'Logo URL'        => 'example.com/logo.png',
                'Status'          => 'Active',
                'Hosted Type'     => ['Cloud'],
                'Parent Category' => 'Cash',
                'Sub Categories'  => ['Payments'],
            ],
        ];

        $requested_fields = null;
        \Patchwork\replace('TTP_Airbase::get_vendors', function ($fields = array()) use ($record, &$requested_fields) {
            $requested_fields = $fields;
            return ['records' => [ $record ]];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();

        $this->assertContains('fldznljEJpn4lv79r', $requested_fields);

        $expected = [
            [
                'name'            => 'Sample Product',
                'vendor'          => 'Acme Corp',
                'website'         => 'https://example.com',
                'video_url'       => 'https://example.com/video',
                'status'          => 'Active',
                'hosted_type'     => ['Cloud'],
                'domain'          => [],
                'regions'         => [],
                'sub_categories'  => ['Payments'],
                'parent_category' => 'Cash',
                'capabilities'    => [],
                'logo_url'        => 'https://example.com/logo.png',
                'hq_location'     => '',
                'founded_year'    => '',
                'founders'        => '',
            ],
        ];

        $this->assertSame($expected, $captured);
        $this->assertSame('https://example.com/video', $captured[0]['video_url']);
        $this->assertSame('https://example.com/logo.png', $captured[0]['logo_url']);
        $this->assertSame('Cash', $captured[0]['parent_category']);
        $this->assertSame(['Payments'], $captured[0]['sub_categories']);
    }

    public function test_refresh_vendor_cache_logs_missing_fields() {
        $record = [
            'id' => 'rec1',
            'fields' => [
                'Product Name' => 'Sample Product',
            ],
        ];

        \Patchwork\replace('TTP_Airbase::get_vendors', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Data::save_vendors', function () {
        });

        $logged = '';
        \Patchwork\replace('error_log', function ($message) use (&$logged) {
            $logged = $message;
        });

        TTP_Data::refresh_vendor_cache();

        $this->assertStringContainsString('Product Website', $logged);
    }

    public function test_get_tools_filters_new_arguments() {
        $tools = [
            [
                'name'            => 'Tool A',
                'category'        => 'CASH',
                'regions'         => ['EMEA'],
                'parent_category' => 'Cash',
                'sub_categories'  => ['Payments'],
            ],
            [
                'name'            => 'Tool B',
                'category'        => 'LITE',
                'regions'         => ['NA'],
                'parent_category' => 'Lite',
                'sub_categories'  => ['FX'],
            ],
        ];

        \Patchwork\replace('TTP_Data::get_all_tools', function () use ($tools) {
            return $tools;
        });

        $filtered = TTP_Data::get_tools([
            'region'          => 'EMEA',
            'parent_category' => 'Cash',
            'sub_category'    => 'Payments',
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame('Tool A', $filtered[0]['name']);
    }
}
