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
        when('sanitize_text_field')->returnArg();
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
                'Linked Vendor'   => ['recven1'],
                'Product Website' => 'example.com',
                'Product Video'   => 'example.com/video',
                'Logo URL'        => 'example.com/logo.png',
                'Status'          => 'Active',
                'Hosted Type'     => ['rechost1'],
                'Parent Category' => 'Cash',
                'Sub Categories'  => ['recsc1'],
                'Regions'         => ['recreg1', 'recreg2'],
                'Domain'          => ['recdom1'],
                'Capabilities'    => ['reccap1'],
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

        $tables = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name') use (&$tables) {
            $tables[] = $table_id;
            $maps = [
                'Regions'        => [
                    'recreg1' => 'North America',
                    'recreg2' => 'Europe',
                ],
                'Vendors'        => [ 'recven1' => 'Acme Corp' ],
                'Hosted Type'    => [ 'rechost1' => 'Cloud' ],
                'Domain'         => [ 'recdom1' => 'Banking' ],
                'Sub Categories' => [ 'recsc1' => 'Payments' ],
                'Capabilities'   => [ 'reccap1' => 'API' ],
            ];

            $out = [];
            foreach ( (array) $ids as $id ) {
                if ( isset( $maps[ $table_id ][ $id ] ) ) {
                    $out[] = $maps[ $table_id ][ $id ];
                }
            }

            return $out;
        });

        TTP_Data::refresh_vendor_cache();

        $this->assertContains('fldznljEJpn4lv79r', $requested_fields);
        $this->assertSame(
            ['Regions', 'Vendors', 'Hosted Type', 'Domain', 'Sub Categories', 'Capabilities'],
            $tables
        );

        $expected = [
            [
                'id'              => 'rec1',
                'name'            => 'Sample Product',
                'vendor'          => 'Acme Corp',
                'website'         => 'https://example.com',
                'video_url'       => 'https://example.com/video',
                'status'          => 'Active',
                'hosted_type'     => ['Cloud'],
                'domain'          => ['Banking'],
                'regions'         => ['North America', 'Europe'],
                'sub_categories'  => ['Payments'],
                'parent_category' => 'Cash',
                'category_names'  => ['Cash', 'Payments'],
                'capabilities'    => ['API'],
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
        $this->assertSame(['Cash', 'Payments'], $captured[0]['category_names']);
        $this->assertSame(['Banking'], $captured[0]['domain']);
    }

    public function test_refresh_vendor_cache_skips_resolution_for_names() {
        $record = [
            'id' => 'rec1',
            'fields' => [
                'Product Name'    => 'Sample Product',
                'Linked Vendor'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['Cloud'],
                'Parent Category' => 'Cash',
                'Sub Categories'  => ['Payments'],
                'Regions'         => ['North America'],
                'Domain'          => ['Banking'],
                'Capabilities'    => ['API'],
            ],
        ];

        \Patchwork\replace('TTP_Airbase::get_vendors', function () use ($record) {
            return ['records' => [ $record ]];
        });

        $called = false;
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id = null, $ids = null, $primary_field = 'Name') use (&$called) {
            $called = true;
            return [];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();

        $this->assertFalse($called);
        $this->assertSame(['North America'], $captured[0]['regions']);
        $this->assertSame('Acme Corp', $captured[0]['vendor']);
    }

    public function test_refresh_vendor_cache_uses_domain_names_from_pairs() {
        $record = [
            'id' => 'rec1',
            'fields' => [
                'Product Name'    => 'Sample Product',
                'Linked Vendor'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['Cloud'],
                'Parent Category' => 'Cash',
                'Sub Categories'  => ['Payments'],
                'Regions'         => ['North America'],
                'Domain'          => [
                    [ 'id' => 'recdom1', 'name' => 'Banking' ],
                ],
                'Capabilities'    => ['API'],
            ],
        ];

        \Patchwork\replace('TTP_Airbase::get_vendors', function () use ($record) {
            return ['records' => [ $record ]];
        });

        $called = false;
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id = null, $ids = null, $primary_field = 'Name') use (&$called) {
            $called = true;
            return [];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();

        $this->assertFalse($called);
        $this->assertSame(['Banking'], $captured[0]['domain']);
    }

    public function test_refresh_vendor_cache_stores_empty_on_error() {
        $record = [
            'id' => 'rec1',
            'fields' => [
                'Product Name'    => 'Sample Product',
                'Linked Vendor'   => ['recven1'],
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['rechost1'],
                'Parent Category' => 'Cash',
                'Sub Categories'  => ['recsc1'],
                'Regions'         => ['recreg1'],
                'Domain'          => ['recdom1'],
                'Capabilities'    => ['reccap1'],
            ],
        ];

        \Patchwork\replace('TTP_Airbase::get_vendors', function () use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id = null, $ids = null, $primary_field = 'Name') {
            return new WP_Error('err', 'fail');
        });

        $logged = [];
        \Patchwork\replace('error_log', function ($msg) use (&$logged) {
            $logged[] = $msg;
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();

        $this->assertSame([], $captured[0]['regions']);
        $this->assertSame('', $captured[0]['vendor']);
        $this->assertNotEmpty($logged);
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

    public function test_refresh_vendor_cache_resolves_string_record_ids() {
        $record = [
            'id' => 'rec1',
            'fields' => [
                'Product Name'    => 'Sample Product',
                'Linked Vendor'   => 'recven1',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => 'rechost1',
                'Parent Category' => 'Cash',
                'Sub Categories'  => 'recsc1',
                'Regions'         => 'recreg1',
                'Domain'          => 'recdom1',
                'Capabilities'    => 'reccap1',
            ],
        ];

        \Patchwork\replace('TTP_Airbase::get_vendors', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name') {
            $maps = [
                'Regions'        => [ 'recreg1' => 'NORAM' ],
                'Vendors'        => [ 'recven1' => 'Acme Corp' ],
                'Hosted Type'    => [ 'rechost1' => 'Cloud' ],
                'Domain'         => [ 'recdom1' => 'Banking' ],
                'Sub Categories' => [ 'recsc1' => 'Payments' ],
                'Capabilities'   => [ 'reccap1' => 'API' ],
            ];

            $out = [];
            foreach ( (array) $ids as $id ) {
                if ( isset( $maps[ $table_id ][ $id ] ) ) {
                    $out[] = $maps[ $table_id ][ $id ];
                }
            }

            return $out;
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();

        $this->assertSame(['NORAM'], $captured[0]['regions']);
        $this->assertSame(['Banking'], $captured[0]['domain']);
        $this->assertSame(['Payments'], $captured[0]['sub_categories']);
        $this->assertSame(['API'], $captured[0]['capabilities']);
    }

    public function test_refresh_vendor_cache_resolves_comma_separated_record_ids() {
        $record = [
            'id' => 'rec1',
            'fields' => [
                'Product Name'    => 'Sample Product',
                'Linked Vendor'   => 'recven1, recven2',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => 'rechost1, rechost2',
                'Parent Category' => 'Cash',
                'Sub Categories'  => 'recsc1, recsc2',
                'Regions'         => 'recreg1, recreg2',
                'Domain'          => 'recdom1, recdom2',
                'Capabilities'    => 'reccap1, reccap2',
            ],
        ];

        \Patchwork\replace('TTP_Airbase::get_vendors', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name') {
            $maps = [
                'Regions'        => [
                    'recreg1' => 'North America',
                    'recreg2' => 'Europe',
                ],
                'Vendors'        => [
                    'recven1' => 'Acme Corp',
                    'recven2' => 'Globex',
                ],
                'Hosted Type'    => [
                    'rechost1' => 'Cloud',
                    'rechost2' => 'On-Prem',
                ],
                'Domain'         => [
                    'recdom1' => 'Banking',
                    'recdom2' => 'Investing',
                ],
                'Sub Categories' => [
                    'recsc1' => 'Payments',
                    'recsc2' => 'Treasury',
                ],
                'Capabilities'   => [
                    'reccap1' => 'API',
                    'reccap2' => 'Analytics',
                ],
            ];

            $out = [];
            foreach ( (array) $ids as $id ) {
                if ( isset( $maps[ $table_id ][ $id ] ) ) {
                    $out[] = $maps[ $table_id ][ $id ];
                }
            }

            return $out;
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_vendors', function ($vendors) use (&$captured) {
            $captured = $vendors;
        });

        TTP_Data::refresh_vendor_cache();

        $this->assertSame(['North America', 'Europe'], $captured[0]['regions']);
        $this->assertSame('Acme Corp', $captured[0]['vendor']);
        $this->assertSame(['Cloud', 'On-Prem'], $captured[0]['hosted_type']);
        $this->assertSame(['Banking', 'Investing'], $captured[0]['domain']);
        $this->assertSame(['Payments', 'Treasury'], $captured[0]['sub_categories']);
        $this->assertSame(['API', 'Analytics'], $captured[0]['capabilities']);
    }

    public function test_get_tools_filters_new_arguments() {
        $tools = [
            [
                'name'            => 'Tool A',
                'category'        => 'CASH',
                'regions'         => ['Europe'],
                'parent_category' => 'Cash',
                'sub_categories'  => ['Payments'],
            ],
            [
                'name'            => 'Tool B',
                'category'        => 'LITE',
                'regions'         => ['North America'],
                'parent_category' => 'Lite',
                'sub_categories'  => ['FX'],
            ],
        ];

        \Patchwork\replace('TTP_Data::get_all_tools', function () use ($tools) {
            return $tools;
        });

        $filtered = TTP_Data::get_tools([
            'region'          => 'Europe',
            'parent_category' => 'Cash',
            'sub_category'    => 'Payments',
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame('Tool A', $filtered[0]['name']);
    }
}
