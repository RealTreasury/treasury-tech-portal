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
        $this->assertStringContainsString('recreg1', implode(' ', $logged));
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

        $ids_used = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name') use (&$ids_used) {
            $ids_used[ $table_id ] = (array) $ids;
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

        $this->assertSame(['recreg1', 'recreg2'], $ids_used['Regions']);
        $this->assertSame(['recven1', 'recven2'], $ids_used['Vendors']);
        $this->assertSame(['rechost1', 'rechost2'], $ids_used['Hosted Type']);
        $this->assertSame(['recdom1', 'recdom2'], $ids_used['Domain']);
        $this->assertSame(['recsc1', 'recsc2'], $ids_used['Sub Categories']);
        $this->assertSame(['reccap1', 'reccap2'], $ids_used['Capabilities']);

        $this->assertSame(['North America', 'Europe'], $captured[0]['regions']);
        $this->assertSame('Acme Corp', $captured[0]['vendor']);
        $this->assertSame(['Cloud', 'On-Prem'], $captured[0]['hosted_type']);
        $this->assertSame(['Banking', 'Investing'], $captured[0]['domain']);
        $this->assertSame(['Payments', 'Treasury'], $captured[0]['sub_categories']);
        $this->assertSame(['API', 'Analytics'], $captured[0]['capabilities']);
    }

    /**
     * @dataProvider comma_separated_fields_provider
     */
    public function test_refresh_vendor_cache_resolves_comma_separated_ids_for_field( $field, $table, $output_key, $mapping ) {
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

        $record['fields'][ $field ] = implode( ', ', array_keys( $mapping ) );

        \Patchwork\replace( 'TTP_Airbase::get_vendors', function ( $fields = array() ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table_id, $ids ) use ( $table, $mapping ) {
            if ( $table_id !== $table ) {
                return array();
            }
            $out = array();
            foreach ( (array) $ids as $id ) {
                if ( isset( $mapping[ $id ] ) ) {
                    $out[] = $mapping[ $id ];
                }
            }
            return $out;
        } );

        $captured = null;
        \Patchwork\replace( 'TTP_Data::save_vendors', function ( $vendors ) use ( &$captured ) {
            $captured = $vendors;
        } );

        TTP_Data::refresh_vendor_cache();

        $expected = array_values( $mapping );
        if ( 'vendor' === $output_key ) {
            $this->assertSame( reset( $expected ), $captured[0][ $output_key ] );
        } else {
            $this->assertSame( $expected, $captured[0][ $output_key ] );
        }
    }

    public function comma_separated_fields_provider() {
        return [
            'regions' => [
                'Regions',
                'Regions',
                'regions',
                [
                    'recreg1' => 'North America',
                    'recreg2' => 'Europe',
                ],
            ],
            'vendor' => [
                'Linked Vendor',
                'Vendors',
                'vendor',
                [
                    'recven1' => 'Acme Corp',
                    'recven2' => 'Globex',
                ],
            ],
            'hosted_type' => [
                'Hosted Type',
                'Hosted Type',
                'hosted_type',
                [
                    'rechost1' => 'Cloud',
                    'rechost2' => 'On-Prem',
                ],
            ],
            'domain' => [
                'Domain',
                'Domain',
                'domain',
                [
                    'recdom1' => 'Banking',
                    'recdom2' => 'Investing',
                ],
            ],
            'sub_categories' => [
                'Sub Categories',
                'Sub Categories',
                'sub_categories',
                [
                    'recsc1' => 'Payments',
                    'recsc2' => 'Treasury',
                ],
            ],
            'capabilities' => [
                'Capabilities',
                'Capabilities',
                'capabilities',
                [
                    'reccap1' => 'API',
                    'reccap2' => 'Analytics',
                ],
            ],
        ];
    }

    public function test_get_all_vendors_refreshes_when_record_ids_present() {
        $vendors_with_ids = array(
            array(
                'domain' => array( 'rec123' ),
                'regions' => array( 'EMEA' ),
            ),
        );
        $vendors_clean = array(
            array(
                'domain' => array( 'Banking' ),
                'regions' => array( 'EMEA' ),
            ),
        );

        when( 'get_transient' )->justReturn( false );
        when( 'set_transient' )->returnArg();

        $option_calls = 0;
        when( 'get_option' )->alias( function ( $name, $default = array() ) use ( &$option_calls, $vendors_with_ids, $vendors_clean ) {
            $option_calls++;
            if ( 1 === $option_calls ) {
                return $vendors_with_ids;
            }
            return $vendors_clean;
        } );

        $refreshed = false;
        \Patchwork\replace( 'TTP_Data::refresh_vendor_cache', function () use ( &$refreshed ) {
            $refreshed = true;
        } );

        $result = TTP_Data::get_all_vendors();

        $this->assertTrue( $refreshed );
        $this->assertSame( $vendors_clean, $result );
    }

    public function test_get_all_vendors_handles_mixed_case_keys() {
        $stored_option = array(
            array(
                'Regions' => array( 'recreg1' ),
            ),
        );

        when( 'get_transient' )->justReturn( false );
        when( 'set_transient' )->returnArg();
        when( 'delete_transient' )->returnArg();

        when( 'get_option' )->alias( function ( $name, $default = array() ) use ( &$stored_option ) {
            if ( TTP_Data::VENDOR_OPTION_KEY === $name ) {
                return $stored_option;
            }
            return $default;
        } );

        when( 'update_option' )->alias( function ( $name, $value ) use ( &$stored_option ) {
            if ( TTP_Data::VENDOR_OPTION_KEY === $name ) {
                $stored_option = $value;
            }
            return true;
        } );

        $airbase_called = false;
        \Patchwork\replace( 'TTP_Airbase::get_vendors', function () use ( &$airbase_called ) {
            $airbase_called = true;
            return array(
                'records' => array(
                    array(
                        'id'     => 'rec1',
                        'fields' => array(
                            'Product Name' => 'Sample Product',
                            'Regions'      => array( 'recreg1' ),
                        ),
                    ),
                ),
            );
        } );

        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table_id, $ids ) {
            if ( 'Regions' === $table_id ) {
                $map = array( 'recreg1' => 'EMEA' );
                $out = array();
                foreach ( (array) $ids as $id ) {
                    if ( isset( $map[ $id ] ) ) {
                        $out[] = $map[ $id ];
                    }
                }
                return $out;
            }
            return array();
        } );

        $result = TTP_Data::get_all_vendors();

        $this->assertTrue( $airbase_called );
        $this->assertSame( array( 'EMEA' ), $result[0]['regions'] );
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
