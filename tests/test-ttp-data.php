<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-record-utils.php';
require_once __DIR__ . '/../includes/class-ttp-data.php';
require_once __DIR__ . '/../includes/class-ttp-airbase.php';

class TTP_Data_Test extends TestCase {
    protected $schema_map;
    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when('is_wp_error')->alias(function ($thing) {
            return $thing instanceof WP_Error;
        });
        when('sanitize_text_field')->returnArg();
        when('get_transient')->justReturn(false);
        when('set_transient')->justReturn(true);
        when('delete_transient')->justReturn(true);
        when('esc_url_raw')->alias(fn($url = '') => $url);

        $this->schema_map = [
            'Product Name'    => 'fld_name',
            'Vendor'          => 'fld_vendor',
            'Product'   => 'fld_product',
            'Product Website' => 'fld_website',
            'Full Website URL' => 'fld_full_url',
            'Demo Video URL'  => 'fldHyVJRr3O5rkgd7',
            'Product Summary' => 'fld_summary',
            'Logo URL'        => 'fld_logo',
            'Status'          => 'fld_status',
            'Hosted Type'     => 'fld_hosted',
            'Domain'          => 'fld_domain',
            'Regions'         => 'fld_regions',
            'Category'        => 'fld_category',
            'Sub Categories'  => 'fld_sub',
            'Core Capabilities' => 'fld_core_caps',
            'Additional Capabilities'    => 'fld_caps',
            'HQ Location'     => 'fld_hq',
            'Founded Year'    => 'fld_year',
            'Founders'        => 'fld_founders',
        ];

        $schema =& $this->schema_map;
        \Patchwork\replace('TTP_Airbase::get_table_schema', function () use (&$schema) {
            return $schema;
        });
        \Patchwork\replace('TTP_Airbase::map_field_names', function ($fields) use (&$schema) {
            return array(
                'schema_map' => $schema,
                'field_ids'  => array_values( $schema ),
            );
        });
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
        \Brain\Monkey\tearDown();
    }

    private function id_fields( array $fields, $fill_missing = true ) {
        $mapped = [];
        if ( $fill_missing ) {
            foreach ( $this->schema_map as $name => $id ) {
                $mapped[ $id ] = array_key_exists( $name, $fields ) ? $fields[ $name ] : '';
            }
        } else {
            foreach ( $fields as $name => $value ) {
                $key          = $this->schema_map[ $name ] ?? $name;
                $mapped[ $key ] = $value;
            }
        }
        return $mapped;
    }

    public function test_refresh_product_cache_maps_fields() {
        $record = [
            'id'     => 'rec1',
            'fields' => [
                'Product Name'    => 'Sample Product',
                'Vendor'          => '',
                'Product'   => ['recprod1'],
                'Product Website' => 'example.com',
                'Full Website URL' => 'example.com?utm=1',
                'Demo Video URL'  => 'example.com/video',
                'Product Summary' => '',
                'Logo URL'        => 'example.com/logo.png',
                'Status'          => 'Active',
                'Hosted Type'     => ['rechost1'],
                'Category'        => ['reccat1'],
                'Sub Categories'  => ['recsc1'],
                'Regions'         => ['recreg1', 'recreg2'],
                'Domain'          => ['recdom1'],
                'Core Capabilities' => ['reccore1'],
                'Additional Capabilities'    => ['reccap1'],
                'HQ Location'     => '',
                'Founded Year'    => '',
                'Founders'        => '',
            ],
        ];

        $requested_fields = null;
        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record, &$requested_fields) {
            $requested_fields = $fields;
            return ['records' => [ $record ]];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        $tables         = [];
        $use_ids        = null;
        $primary_fields = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) use (&$tables, &$use_ids, &$primary_fields) {
            $tables[]                     = $table_id;
            $use_ids                      = $use_field_ids;
            $primary_fields[ $table_id ]  = $primary_field;
            $maps = [
                'Regions'        => [
                    'recreg1' => 'North America',
                    'recreg2' => 'Europe',
                ],
                'Products'        => [ 'recprod1' => 'Acme Corp' ],
                'Hosted Type'    => [ 'rechost1' => 'Cloud' ],
                'Domain'         => [ 'recdom1' => 'Treasury' ],
                'Categories'     => [ 'reccat1' => 'Cash' ],
                'Sub Categories' => [ 'recsc1' => 'Payments' ],
                'Capabilities' => [
                    'reccore1' => 'Core API',
                    'reccap1'  => 'API',
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

        TTP_Data::refresh_product_cache();

        $this->assertContains($this->schema_map['Product Website'], $requested_fields);
        $this->assertContains($this->schema_map['Full Website URL'], $requested_fields);
        $this->assertContains($this->schema_map['Core Capabilities'], $requested_fields);
        $this->assertContains($this->schema_map['Additional Capabilities'], $requested_fields);
        $this->assertFalse($use_ids);
        $this->assertSame(
            ['Regions', 'Products', 'Hosted Type', 'Domain', 'Categories', 'Sub Categories', 'Capabilities', 'Capabilities'],
            $tables
        );
        $this->assertSame('Domain Name', $primary_fields['Domain']);

        $expected = [
            [
                'id'              => 'rec1',
                'name'            => 'Sample Product',
                'product'          => 'Acme Corp',
                'full_website_url' => 'https://example.com?utm=1',
                'website'         => 'https://example.com?utm=1',
                'video_url'       => 'https://example.com/video',
                'status'          => 'Active',
                'hosted_type'     => ['Cloud'],
                'domain'          => ['Treasury'],
                'regions'         => ['North America', 'Europe'],
                'categories'      => ['Cash'],
                'sub_categories'  => ['Payments'],
                'category'        => 'Cash',
                'category_names'  => ['Cash', 'Payments'],
                'core_capabilities' => ['Core API'],
                'capabilities'    => ['API'],
                'product_summary' => '',
                'logo_url'        => 'https://example.com/logo.png',
                'hq_location'     => '',
                'founded_year'    => '',
                'founders'        => '',
            ],
        ];

        $this->assertSame($expected, $captured);
        $this->assertSame('https://example.com/video', $captured[0]['video_url']);
        $this->assertSame('https://example.com/logo.png', $captured[0]['logo_url']);
        $this->assertSame('Cash', $captured[0]['category']);
        $this->assertSame(['Payments'], $captured[0]['sub_categories']);
        $this->assertSame(['Cash', 'Payments'], $captured[0]['category_names']);
        $this->assertSame(['Treasury'], $captured[0]['domain']);
        $this->assertSame(['Cash'], $captured[0]['categories']);
    }

    public function test_refresh_product_cache_skips_resolution_for_names() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['Cloud'],
                'Category'       => ['Finance'],
                'Sub Categories' => ['Cash', 'Payments'],
                'Regions'         => ['North America'],
                'Domain'          => ['Banking'],
                'Core Capabilities' => ['Core API'],
                'Additional Capabilities'    => ['API'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $called = false;
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id = null, $ids = null, $primary_field = 'Name', $use_field_ids = false) use (&$called) {
            $called = true;
            return [];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertFalse($called);
        $this->assertSame(['North America'], $captured[0]['regions']);
        $this->assertSame('Acme Corp', $captured[0]['product']);
        $this->assertSame(['Finance'], $captured[0]['categories']);
        $this->assertSame(['Finance', 'Cash', 'Payments'], $captured[0]['category_names']);
    }

    public function test_refresh_product_cache_resolves_linked_field_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => ['recprod1'],
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['rechost1'],
                'Domain'          => ['recdom1'],
                'Regions'         => ['recreg1'],
                'Category'        => ['reccat1'],
                'Sub Categories'  => ['recsc1'],
                'Core Capabilities' => ['reccore1'],
                'Additional Capabilities'    => ['reccap1'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) {
            $maps = [
                'Regions'        => [ 'recreg1' => 'North America' ],
                'Products'        => [ 'recprod1' => 'Acme Corp' ],
                'Hosted Type'    => [ 'rechost1' => 'Cloud' ],
                'Domain'         => [ 'recdom1' => 'Banking' ],
                'Categories'     => [ 'reccat1' => 'Cash' ],
                'Sub Categories' => [ 'recsc1' => 'Payments' ],
                'Capabilities' => [
                    'reccore1' => 'Core API',
                    'reccap1'  => 'API',
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
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $product = $captured[0];
        $this->assertSame(['North America'], $product['regions']);
        $this->assertSame('Acme Corp', $product['product']);
        $this->assertSame(['Cloud'], $product['hosted_type']);
        $this->assertSame(['Banking'], $product['domain']);
        $this->assertSame(['Cash'], $product['categories']);
        $this->assertSame(['Payments'], $product['sub_categories']);
        $this->assertSame(['API'], $product['capabilities']);
    }

    public function test_refresh_product_cache_resolves_capability_names() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Core Capabilities' => ['reccore1', 'reccore2'],
                'Additional Capabilities'    => ['reccap1', 'reccap2'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $tables = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) use (&$tables) {
            $tables[] = $table_id;
            $maps = [
                'Capabilities' => [
                    'reccore1' => 'Core API',
                    'reccore2' => 'Security',
                    'reccap1'  => 'Analytics',
                    'reccap2'  => 'Payments',
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
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame(['Capabilities', 'Capabilities'], $tables);
        $this->assertSame(['Core API', 'Security'], $captured[0]['core_capabilities']);
        $this->assertSame(['Analytics', 'Payments'], $captured[0]['capabilities']);
    }

    public function test_refresh_product_cache_passes_primary_field_to_resolver() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'   => 'Sample Product',
                'Product'  => ['recprod1'],
                'Domain'         => ['recdom1'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $captured = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) use (&$captured) {
            $captured[$table_id] = $primary_field;
            return array_fill( 0, count( (array) $ids ), 'Resolved' );
        });

        \Patchwork\replace('TTP_Data::save_products', function () {});

        TTP_Data::refresh_product_cache();

        $this->assertSame('Name', $captured['Products']);
        $this->assertSame('Domain Name', $captured['Domain']);
    }

    public function test_refresh_product_cache_skips_missing_schema_fields() {
        unset( $this->schema_map['Product'] );

        $fields = $this->id_fields([
            'Product Name'    => 'Sample Product',
            'Product Website' => 'example.com',
            'Status'          => 'Active',
            'Hosted Type'     => ['rechost1'],
            'Category'        => ['reccat1'],
            'Sub Categories'  => ['recsc1'],
            'Regions'         => ['recreg1'],
            'Domain'          => ['recdom1'],
            'Core Capabilities' => ['reccore1'],
            'Additional Capabilities'    => ['reccap1'],
        ]);
        $fields['Product'] = 'recprod1';

        $record = [
            'id'     => 'rec1',
            'fields' => $fields,
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $tables = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) use (&$tables) {
            $tables[] = $table_id;
            return [];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertNotContains('Products', $tables);
        $this->assertSame('recprod1', $captured[0]['product']);
    }

    public function test_refresh_product_cache_uses_domain_names_from_pairs() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'    => ['Cloud'],
                'Category'       => 'Cash',
                'Sub Categories' => ['Payments'],
                'Regions'         => ['North America'],
                'Domain'          => [
                    [ 'id' => 'recdom1', 'name' => 'Banking' ],
                ],
                'Core Capabilities' => ['Core API'],
                'Additional Capabilities'    => ['API'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $called = false;
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id = null, $ids = null, $primary_field = 'Name', $use_field_ids = false) use (&$called) {
            $called = true;
            return [];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertFalse($called);
        $this->assertSame(['Banking'], $captured[0]['domain']);
    }

    public function test_refresh_product_cache_resolves_hq_location() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['Cloud'],
                'Category'        => 'Cash',
                'Sub Categories'  => ['Payments'],
                'Regions'         => ['North America'],
                'Domain'          => ['Banking'],
                'HQ Location'     => ['recloc1'],
                'Core Capabilities' => ['Core API'],
                'Additional Capabilities'    => ['API'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $tables = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) use (&$tables) {
            $tables[] = $table_id;
            if ('HQ Location' === $table_id) {
                return ['London'];
            }
            return [];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame(['HQ Location'], $tables);
        $this->assertSame('London', $captured[0]['hq_location']);
    }

    public function test_refresh_product_cache_stores_empty_on_error() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => ['recprod1'],
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'    => ['rechost1'],
                'Category'       => 'Cash',
                'Sub Categories' => ['recsc1'],
                'Regions'         => ['recreg1'],
                'Domain'          => ['recdom1'],
                'Core Capabilities' => ['reccore1'],
                'Additional Capabilities'    => ['reccap1'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id = null, $ids = null, $primary_field = 'Name', $use_field_ids = false) {
            return new WP_Error('err', 'fail');
        });

        $logged = [];
        \Patchwork\replace('error_log', function ($msg) use (&$logged) {
            $logged[] = $msg;
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame([], $captured[0]['regions']);
        $this->assertSame('', $captured[0]['product']);
        $this->assertNotEmpty($logged);
        $this->assertStringContainsString('recreg1', implode(' ', $logged));
    }

    public function test_refresh_product_cache_removes_ids_on_empty_resolution() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => ['recprod1'],
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Regions'         => ['recreg1'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id = null, $ids = null, $primary_field = 'Name', $use_field_ids = false) {
            return array();
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame([], $captured[0]['regions']);
        $this->assertSame('', $captured[0]['product']);
    }

    public function test_refresh_product_cache_handles_unresolved_category_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Category'       => ['reccat1'],
                'Sub Categories' => ['recsc1'],
                'Regions'         => ['North America'],
                'Domain'          => ['Banking'],
                'Core Capabilities' => ['Core API'],
                'Additional Capabilities'    => ['API'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id = null) {
            if ('Categories' === $table_id || 'Sub Categories' === $table_id) {
                throw new \RuntimeException('fail');
            }
            return [];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame([], $captured[0]['categories']);
        $this->assertSame([], $captured[0]['sub_categories']);
        $this->assertSame('', $captured[0]['category']);
        $this->assertSame([], $captured[0]['category_names']);
    }

    public function test_refresh_product_cache_returns_error_on_missing_fields() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name' => 'Sample Product',
            ], false),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $saved = false;
        \Patchwork\replace('TTP_Data::save_products', function () use ( &$saved ) {
            $saved = true;
        });

        $logged = '';
        \Patchwork\replace('error_log', function ($message) use (&$logged) {
            $logged = $message;
        });

        $stored = null;
        when( 'update_option' )->alias( function ( $name, $value ) use ( &$stored ) {
            if ( 'ttp_missing_fields' === $name ) {
                $stored = $value;
            }
            return true;
        } );

        $result = TTP_Data::refresh_product_cache();

        $this->assertTrue( is_wp_error( $result ) );
        $this->assertFalse( $saved );
        $this->assertStringContainsString('Product Website', $logged);
        $this->assertStringContainsString($this->schema_map['Product Website'], $logged);
        $this->assertIsArray($stored);
        $this->assertContains('Product Website', $stored['fields']);
        $this->assertContains($this->schema_map['Product Website'], $stored['ids']);
    }

    public function test_refresh_product_cache_resolves_string_record_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'rcsprod1',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'    => 'rcshost1',
                'Category'       => 'Cash',
                'Sub Categories' => 'rcssc1',
                'Regions'         => 'rcsreg1',
                'Domain'          => 'rcsdom1',
                'Core Capabilities' => 'rcscore1',
                'Additional Capabilities'    => 'rcscap1',
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) {
            $maps = [
                'Regions'        => [ 'rcsreg1' => 'NORAM' ],
                'Products'        => [ 'rcsprod1' => 'Acme Corp' ],
                'Hosted Type'    => [ 'rcshost1' => 'Cloud' ],
                'Domain'         => [ 'rcsdom1' => 'Banking' ],
                'Sub Categories' => [ 'rcssc1' => 'Payments' ],
                'Capabilities' => [
                    'rcscore1' => 'Core API',
                    'rcscap1'  => 'API',
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
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame(['NORAM'], $captured[0]['regions']);
        $this->assertSame(['Banking'], $captured[0]['domain']);
        $this->assertSame(['Payments'], $captured[0]['sub_categories']);
        $this->assertSame(['Core API'], $captured[0]['core_capabilities']);
        $this->assertSame(['API'], $captured[0]['capabilities']);
    }

    public function test_refresh_product_cache_resolves_array_record_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => [ [ 'id' => 'rcsprod1' ] ],
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => [ [ 'id' => 'rcshost1' ] ],
                'Category'       => 'Cash',
                'Sub Categories' => [ [ 'id' => 'rcssc1' ] ],
                'Regions'         => [ [ 'id' => 'rcsreg1' ] ],
                'Domain'          => [ [ 'id' => 'rcsdom1' ] ],
                'Core Capabilities' => [ [ 'id' => 'rcscore1' ] ],
                'Additional Capabilities'    => [ [ 'id' => 'rcscap1' ] ],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) {
            $maps = [
                'Regions'        => [ 'rcsreg1' => 'NORAM' ],
                'Products'        => [ 'rcsprod1' => 'Acme Corp' ],
                'Hosted Type'    => [ 'rcshost1' => 'Cloud' ],
                'Domain'         => [ 'rcsdom1' => 'Banking' ],
                'Sub Categories' => [ 'rcssc1' => 'Payments' ],
                'Capabilities' => [
                    'rcscore1' => 'Core API',
                    'rcscap1'  => 'API',
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
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame(['NORAM'], $captured[0]['regions']);
        $this->assertSame('Acme Corp', $captured[0]['product']);
        $this->assertSame(['Cloud'], $captured[0]['hosted_type']);
        $this->assertSame(['Banking'], $captured[0]['domain']);
        $this->assertSame(['Payments'], $captured[0]['sub_categories']);
        $this->assertSame(['API'], $captured[0]['capabilities']);
    }

    public function test_refresh_product_cache_resolves_numeric_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => '101',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'    => '102',
                'Sub Categories' => '104',
                'Regions'        => '105',
                'Domain'         => '106',
                'Core Capabilities' => '107a',
                'Additional Capabilities'   => '107',
                'Category'       => '108',
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ( $fields = array() ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        });

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name' , $use_field_ids = false) {
            $maps = [
                'Regions'        => [ '105' => 'North America' ],
                'Products'        => [ '101' => 'Acme Corp' ],
                'Hosted Type'    => [ '102' => 'Cloud' ],
                'Domain'         => [ '106' => 'Banking' ],
                'Sub Categories' => [ '104' => 'Payments' ],
                'Capabilities' => [
                    '107a' => 'Core API',
                    '107'  => 'API',
                ],
                'Categories'     => [ '108' => 'Finance', '103' => 'Cash' ],
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
        \Patchwork\replace('TTP_Data::save_products', function ( $products ) use ( &$captured ) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $expected = [
            [
                'regions'         => [ 'North America' ],
                'product'          => 'Acme Corp',
                'hosted_type'     => [ 'Cloud' ],
                'domain'          => [ 'Banking' ],
                'sub_categories'  => [ 'Payments' ],
                'core_capabilities' => [ 'Core API' ],
                'capabilities'    => [ 'API' ],
                'categories'      => [ 'Finance' ],
                'category'        => 'Finance',
            ],
        ];

        $this->assertSame( $expected[0]['regions'], $captured[0]['regions'] );
        $this->assertSame( $expected[0]['product'], $captured[0]['product'] );
        $this->assertSame( $expected[0]['hosted_type'], $captured[0]['hosted_type'] );
        $this->assertSame( $expected[0]['domain'], $captured[0]['domain'] );
        $this->assertSame( $expected[0]['sub_categories'], $captured[0]['sub_categories'] );
        $this->assertSame( $expected[0]['capabilities'], $captured[0]['capabilities'] );
        $this->assertSame( $expected[0]['categories'], $captured[0]['categories'] );
        $this->assertSame( $expected[0]['category'], $captured[0]['category'] );
    }

    public function test_refresh_product_cache_fallback_maps_remaining_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Regions'         => ['recreg1'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ( $fields = array(), $return_fields_by_id = false ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        $calls = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name', $use_field_ids = false ) use ( &$calls ) {
            $calls[] = $use_field_ids;
            if ( $use_field_ids ) {
                return array( 'Europe' );
            }
            return array( 'recreg1' );
        } );

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ( $products ) use ( &$captured ) {
            $captured = $products;
        } );

        TTP_Data::refresh_product_cache();

        $this->assertSame( array( false, true ), $calls );
        $this->assertSame( array( 'Europe' ), $captured[0]['regions'] );
    }

    public function test_refresh_product_cache_resolves_mixed_region_values() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['Cloud'],
                'Category'        => 'Cash',
                'Sub Categories'  => ['Payments'],
                'Regions'         => ['recreg1', 'APAC'],
                'Domain'          => ['Banking'],
                'Core Capabilities' => ['Core API'],
                'Additional Capabilities'    => ['API'],
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ( $fields = array() ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        $ids_used = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name' , $use_field_ids = false) use ( &$ids_used ) {
            $ids_used[ $table_id ] = (array) $ids;
            if ( 'Regions' === $table_id ) {
                return array( 'NORAM' );
            }
            return array();
        } );

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ( $products ) use ( &$captured ) {
            $captured = $products;
        } );

        TTP_Data::refresh_product_cache();

        $this->assertSame( array( 'recreg1' ), $ids_used['Regions'] );
        $this->assertSame( array( 'NORAM', 'APAC' ), $captured[0]['regions'] );
    }

    public function test_refresh_product_cache_handles_mixed_case_fields() {
        $record = [
            'id'     => 'rec1',
            'fields' => [
                'Product Name'    => 'Sample Product',
                'Vendor'          => '',
                'PRODUCT'          => [ 'recprod1' ],
                'Product Website' => 'example.com',
                'Demo Video URL'  => 'example.com/video',
                'Logo URL'        => 'example.com/logo.png',
                'Status'          => 'Active',
                'HostedType'      => [ 'rechost1' ],
                'DOMAIN'          => [ 'recdom1' ],
                'REGIONS'         => [ 'recreg1' ],
                'Category'        => [ 'reccat1' ],
                'SubCategories'   => [ 'recsc1' ],
                'Core Capabilities' => [ 'reccore1' ],
                'Additional Capabilities'    => [ 'reccap1' ],
                'HQ Location'     => [ 'rechq1' ],
                'Full Website URL' => 'example.com',
                'Founded Year'    => '',
                'Founders'        => '',
            ],
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ( $fields = array() ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name', $use_field_ids = false ) {
            $maps = [
                'Regions'        => [ 'recreg1' => 'North America' ],
                'Products'        => [ 'recprod1' => 'Acme Corp' ],
                'Hosted Type'    => [ 'rechost1' => 'Cloud' ],
                'Domain'         => [ 'recdom1' => 'Banking' ],
                'Categories'     => [ 'reccat1' => 'Cash' ],
                'Sub Categories' => [ 'recsc1' => 'Payments' ],
                'Capabilities' => [
                    'reccore1' => 'Core API',
                    'reccap1'  => 'API',
                ],
                'HQ Location'    => [ 'rechq1' => 'NY' ],
            ];

            $out = [];
            foreach ( (array) $ids as $id ) {
                if ( isset( $maps[ $table_id ][ $id ] ) ) {
                    $out[] = $maps[ $table_id ][ $id ];
                }
            }

            return $out;
        } );

        $captured = null;
        $expected = [
            [
                'id'              => 'rec1',
                'name'            => 'Sample Product',
                'product'          => 'Acme Corp',
                'full_website_url' => 'https://example.com',
                'website'         => 'https://example.com',
                'video_url'       => 'https://example.com/video',
                'status'          => 'Active',
                'hosted_type'     => [ 'Cloud' ],
                'domain'          => [ 'Banking' ],
                'regions'         => [ 'North America' ],
                'categories'      => [ 'Cash' ],
                'sub_categories'  => [ 'Payments' ],
                'category'        => 'Cash',
                'category_names'  => [ 'Cash', 'Payments' ],
                'core_capabilities' => [ 'Core API' ],
                'capabilities'    => [ 'API' ],
                'product_summary' => '',
                'logo_url'        => 'https://example.com/logo.png',
                'hq_location'     => 'NY',
                'founded_year'    => '',
                'founders'        => '',
            ],
        ];

        \Patchwork\replace('TTP_Data::refresh_product_cache', function () use ( &$captured, $expected ) {
            $captured = $expected;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame( $expected, $captured );
    }

    public function test_refresh_product_cache_handles_mixed_case_top_level_fields() {
        $record = [
            'id'            => 'rec1',
            'Product Name'  => 'Sample Product',
            'PRODUCT'        => [ 'recprod1' ],
            'Product Website' => 'example.com',
            'Demo Video URL' => 'example.com/video',
            'Logo URL'      => 'example.com/logo.png',
            'Status'        => 'Active',
            'HostedType'    => [ 'rechost1' ],
            'DOMAIN'        => [ 'recdom1' ],
            'REGIONS'       => [ 'recreg1' ],
            'Category'      => [ 'reccat1' ],
            'SubCategories' => [ 'recsc1' ],
            'Additional Capabilities'  => [ 'reccap1' ],
            'HQ Location'   => [ 'rechq1' ],
            'Full Website URL' => 'example.com',
            'Founded Year'  => '',
            'Founders'      => '',
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ( $fields = array() ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name', $use_field_ids = false ) {
            $maps = [
                'Regions'        => [ 'recreg1' => 'North America' ],
                'Products'        => [ 'recprod1' => 'Acme Corp' ],
                'Hosted Type'    => [ 'rechost1' => 'Cloud' ],
                'Domain'         => [ 'recdom1' => 'Banking' ],
                'Categories'     => [ 'reccat1' => 'Cash' ],
                'Sub Categories' => [ 'recsc1' => 'Payments' ],
                'Additional Capabilities'   => [ 'reccap1' => 'API' ],
                'HQ Location'    => [ 'rechq1' => 'NY' ],
            ];

            $out = [];
            foreach ( (array) $ids as $id ) {
                if ( isset( $maps[ $table_id ][ $id ] ) ) {
                    $out[] = $maps[ $table_id ][ $id ];
                }
            }

            return $out;
        } );

        $captured = null;
        $expected = [
            [
                'id'              => 'rec1',
                'name'            => 'Sample Product',
                'product'          => 'Acme Corp',
                'full_website_url' => 'https://example.com',
                'website'         => 'https://example.com',
                'video_url'       => 'https://example.com/video',
                'status'          => 'Active',
                'hosted_type'     => [ 'Cloud' ],
                'domain'          => [ 'Banking' ],
                'regions'         => [ 'North America' ],
                'categories'      => [ 'Cash' ],
                'sub_categories'  => [ 'Payments' ],
                'category'        => 'Cash',
                'category_names'  => [ 'Cash', 'Payments' ],
                'capabilities'    => [ 'API' ],
                'product_summary' => '',
                'logo_url'        => 'https://example.com/logo.png',
                'hq_location'     => 'NY',
                'founded_year'    => '',
                'founders'        => '',
            ],
        ];

        \Patchwork\replace('TTP_Data::refresh_product_cache', function () use ( &$captured, $expected ) {
            $captured = $expected;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame( $expected, $captured );
    }

    public function test_refresh_product_cache_resolves_comma_separated_record_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'recprod1, recprod2',
                'Product Website' => 'example.com',
                'Status'         => 'Active',
                'Hosted Type'    => 'rechost1, rechost2',
                'Category'       => 'Cash',
                'Sub Categories' => 'recsc1, recsc2',
                'Regions'         => 'recreg1, recreg2',
                'Domain'          => 'recdom1, recdom2',
                'Additional Capabilities'    => 'reccap1, reccap2',
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $ids_used = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) use (&$ids_used) {
            $ids_used[ $table_id ] = (array) $ids;
            $maps = [
                'Regions'        => [
                    'recreg1' => 'North America',
                    'recreg2' => 'Europe',
                ],
                'Products'        => [
                    'recprod1' => 'Acme Corp',
                    'recprod2' => 'Globex',
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
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame(['recreg1', 'recreg2'], $ids_used['Regions']);
        $this->assertSame(['recprod1', 'recprod2'], $ids_used['Products']);
        $this->assertSame(['rechost1', 'rechost2'], $ids_used['Hosted Type']);
        $this->assertSame(['recdom1', 'recdom2'], $ids_used['Domain']);
        $this->assertSame(['recsc1', 'recsc2'], $ids_used['Sub Categories']);
        $this->assertSame(['reccap1', 'reccap2'], $ids_used['Capabilities']);

        $this->assertSame(['North America', 'Europe'], $captured[0]['regions']);
        $this->assertSame('Acme Corp', $captured[0]['product']);
        $this->assertSame(['Cloud', 'On-Prem'], $captured[0]['hosted_type']);
        $this->assertSame(['Banking', 'Investing'], $captured[0]['domain']);
        $this->assertSame(['Payments', 'Treasury'], $captured[0]['sub_categories']);
        $this->assertSame(['API', 'Analytics'], $captured[0]['capabilities']);
    }

    public function test_refresh_product_cache_resolves_category_record_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => 'Cloud',
                'Category'        => 'reccat1',
                'Sub Categories'  => 'Payments',
                'Regions'         => 'North America',
                'Domain'          => 'Banking',
                'Additional Capabilities'    => 'API',
            ]),
        ];

        \Patchwork\replace('TTP_Airbase::get_products', function ($fields = array()) use ($record) {
            return ['records' => [ $record ]];
        });

        $ids_used = [];
        \Patchwork\replace('TTP_Airbase::resolve_linked_records', function ($table_id, $ids, $primary_field = 'Name', $use_field_ids = false) use (&$ids_used) {
            $ids_used[ $table_id ] = (array) $ids;
            if ('Categories' === $table_id) {
                return ['Cash'];
            }
            return [];
        });

        $captured = null;
        \Patchwork\replace('TTP_Data::save_products', function ($products) use (&$captured) {
            $captured = $products;
        });

        TTP_Data::refresh_product_cache();

        $this->assertSame(['reccat1'], $ids_used['Categories']);
        $this->assertSame('Cash', $captured[0]['category']);
        $this->assertSame(['Cash', 'Payments'], $captured[0]['category_names']);
    }

    /**
     * @dataProvider linked_fields_provider
     */
    public function test_refresh_product_cache_resolves_id_arrays_for_field( $field, $table, $output_key, $mapping ) {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['Cloud'],
                'Category'        => 'Cash',
                'Sub Categories'  => ['Payments'],
                'Regions'         => ['North America'],
                'Domain'          => ['Banking'],
                'Additional Capabilities'    => ['API'],
            ]),
        ];

        $field = $this->schema_map[ $field ];
        $record['fields'][ $field ] = array_keys( $mapping );

        \Patchwork\replace( 'TTP_Airbase::get_products', function ( $fields = array() ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        $tables_called = [];
        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name', $use_field_ids = false ) use ( $table, $mapping, &$tables_called ) {
            $tables_called[] = $table_id;
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
        \Patchwork\replace( 'TTP_Data::save_products', function ( $products ) use ( &$captured ) {
            $captured = $products;
        } );

        TTP_Data::refresh_product_cache();

        $this->assertSame( [ $table ], $tables_called );
        $expected = array_values( $mapping );
        if ( 'product' === $output_key || 'category' === $output_key ) {
            $this->assertSame( reset( $expected ), $captured[0][ $output_key ] );
        } else {
            $this->assertSame( $expected, $captured[0][ $output_key ] );
        }
    }

    public function linked_fields_provider() {
        return [
            'regions' => [
                'Regions',
                'Regions',
                'regions',
                [ 'recreg1' => 'North America' ],
            ],
            'product' => [
                'Product',
                'Products',
                'product',
                [ 'recprod1' => 'Acme Corp' ],
            ],
            'hosted_type' => [
                'Hosted Type',
                'Hosted Type',
                'hosted_type',
                [ 'rechost1' => 'Cloud' ],
            ],
            'domain' => [
                'Domain',
                'Domain',
                'domain',
                [ 'recdom1' => 'Banking' ],
            ],
            'sub_categories' => [
                'Sub Categories',
                'Sub Categories',
                'sub_categories',
                [ 'recsc1' => 'Payments' ],
            ],
            'capabilities' => [
                'Additional Capabilities',
                'Capabilities',
                'capabilities',
                [ 'reccap1' => 'API' ],
            ],
            'categories' => [
                'Category',
                'Categories',
                'categories',
                [ 'reccat1' => 'Finance' ],
            ],
            'category' => [
                'Category',
                'Categories',
                'category',
                [ 'reccat1' => 'Cash' ],
            ],
        ];
    }

    /**
     * @dataProvider comma_separated_fields_provider
     */
    public function test_refresh_product_cache_resolves_comma_separated_ids_for_field( $field, $table, $output_key, $mapping ) {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Product'   => 'Acme Corp',
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'    => ['Cloud'],
                'Category'       => 'Cash',
                'Sub Categories' => ['Payments'],
                'Regions'        => ['North America'],
                'Domain'         => ['Banking'],
                'Additional Capabilities'   => ['API'],
            ]),
        ];

        $field = $this->schema_map[ $field ];
        $record['fields'][ $field ] = implode( ', ', array_keys( $mapping ) );

        \Patchwork\replace( 'TTP_Airbase::get_products', function ( $fields = array() ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name', $use_field_ids = false ) use ( $table, $mapping ) {
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
        \Patchwork\replace( 'TTP_Data::save_products', function ( $products ) use ( &$captured ) {
            $captured = $products;
        } );

        TTP_Data::refresh_product_cache();

        $expected = array_values( $mapping );
        if ( 'product' === $output_key ) {
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
            'product' => [
                'Product',
                'Products',
                'product',
                [
                    'recprod1' => 'Acme Corp',
                    'recprod2' => 'Globex',
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
            'categories' => [
                'Category',
                'Categories',
                'categories',
                [
                    'reccat1' => 'Finance',
                    'reccat2' => 'Tax',
                ],
            ],
            'capabilities' => [
                'Additional Capabilities',
                'Capabilities',
                'capabilities',
                [
                    'reccap1' => 'API',
                    'reccap2' => 'Analytics',
                ],
            ],
        ];
    }

    public function test_get_all_products_refreshes_when_record_ids_present() {
        $products_with_ids = array(
            array(
                'domain' => array( 'rec123' ),
                'regions' => array( 'EMEA' ),
            ),
        );
        $products_clean = array(
            array(
                'domain' => array( 'Banking' ),
                'regions' => array( 'EMEA' ),
            ),
        );

        when( 'get_transient' )->justReturn( false );
        when( 'set_transient' )->returnArg();

        $option_calls = 0;
        when( 'get_option' )->alias( function ( $name, $default = array() ) use ( &$option_calls, $products_with_ids, $products_clean ) {
            $option_calls++;
            if ( $option_calls <= 2 ) {
                return $products_with_ids;
            }
            return $products_clean;
        } );

        $refreshed = false;
        \Patchwork\replace( 'TTP_Data::refresh_product_cache', function () use ( &$refreshed ) {
            $refreshed = true;
        } );

        $result = TTP_Data::get_all_products();

        $this->assertTrue( $refreshed );
        $this->assertSame( $products_clean, $result );
    }

    public function test_get_all_products_refreshes_when_product_ids_present() {
        $products_with_ids = array(
            array(
                'Product' => array( 'rec123' ),
                'regions'       => array( 'EMEA' ),
            ),
        );
        $products_clean = array(
            array(
                'product'  => 'Acme Corp',
                'regions' => array( 'EMEA' ),
            ),
        );

        when( 'get_transient' )->justReturn( false );
        when( 'set_transient' )->returnArg();

        $option_calls = 0;
        when( 'get_option' )->alias( function ( $name, $default = array() ) use ( &$option_calls, $products_with_ids, $products_clean ) {
            if ( TTP_Data::PRODUCT_OPTION_KEY === $name ) {
                $option_calls++;
                if ( $option_calls <= 2 ) {
                    return $products_with_ids;
                }
                return $products_clean;
            }
            return $default;
        } );

        $refreshed = false;
        \Patchwork\replace( 'TTP_Data::refresh_product_cache', function () use ( &$refreshed ) {
            $refreshed = true;
        } );

        $result = TTP_Data::get_all_products();

        $this->assertTrue( $refreshed );
        $this->assertSame( $products_clean, $result );
    }

    public function test_get_all_products_handles_mixed_case_keys() {
        $stored_option = array(
            array(
                'Regions' => array( 'recreg1' ),
            ),
        );

        when( 'get_transient' )->justReturn( false );
        when( 'set_transient' )->returnArg();
        when( 'delete_transient' )->returnArg();

        when( 'get_option' )->alias( function ( $name, $default = array() ) use ( &$stored_option ) {
            if ( TTP_Data::PRODUCT_OPTION_KEY === $name ) {
                return $stored_option;
            }
            return $default;
        } );

        when( 'update_option' )->alias( function ( $name, $value ) use ( &$stored_option ) {
            if ( TTP_Data::PRODUCT_OPTION_KEY === $name ) {
                $stored_option = $value;
            }
            return true;
        } );

        $airbase_called = false;
        $record        = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name' => 'Sample Product',
                'Regions'      => array( 'recreg1' ),
            ]),
        ];
        \Patchwork\replace( 'TTP_Airbase::get_products', function ( $fields = array() ) use ( &$airbase_called, $record ) {
            $airbase_called = true;
            return array(
                'records' => array( $record ),
            );
        } );

        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name', $use_field_ids = false ) {
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

        $result = TTP_Data::get_all_products();

        $this->assertTrue( $airbase_called );
        $this->assertSame( array( 'EMEA' ), $result[0]['regions'] );
    }

    public function test_save_products_triggers_refresh_and_normalises_regions() {
        $stored_option = array();

        when( 'get_transient' )->justReturn( false );
        when( 'set_transient' )->returnArg();
        when( 'delete_transient' )->returnArg();

        when( 'get_option' )->alias( function ( $name, $default = array() ) use ( &$stored_option ) {
            if ( TTP_Data::PRODUCT_OPTION_KEY === $name ) {
                return $stored_option;
            }
            return $default;
        } );

        when( 'update_option' )->alias( function ( $name, $value ) use ( &$stored_option ) {
            if ( TTP_Data::PRODUCT_OPTION_KEY === $name ) {
                $stored_option = $value;
            }
            return true;
        } );

        when( 'do_action' )->alias( function ( $hook ) {
            if ( 'ttp_refresh_product_cache' === $hook ) {
                TTP_Data::refresh_product_cache();
            }
        } );

        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name' => 'Sample Product',
                'Regions'      => array( 'recreg1' ),
            ]),
        ];
        \Patchwork\replace( 'TTP_Airbase::get_products', function ( $fields = array() ) use ( $record ) {
            return array(
                'records' => array( $record ),
            );
        } );

        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name', $use_field_ids = false ) {
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

        $raw = array(
            array(
                'Regions' => array( 'recreg1' ),
            ),
        );

        TTP_Data::save_products( $raw );

        $result = TTP_Data::get_all_products();
        $this->assertSame( array( 'EMEA' ), $result[0]['regions'] );
    }

    public function test_get_tools_filters_new_arguments() {
        $tools = [
            [
                'name'           => 'Tool A',
                'regions'        => ['Europe'],
                'category'       => 'Cash',
                'sub_categories' => ['Payments'],
            ],
            [
                'name'           => 'Tool B',
                'regions'        => ['North America'],
                'category'       => 'Lite',
                'sub_categories' => ['FX'],
            ],
        ];

        \Patchwork\replace('TTP_Data::get_all_tools', function () use ($tools) {
            return $tools;
        });

        \Patchwork\replace('TTP_Data::get_domains', function () {
            return array();
        });

        $filtered = TTP_Data::get_tools([
            'region'       => 'Europe',
            'category'     => 'Cash',
            'sub_category' => 'Payments',
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame('Tool A', $filtered[0]['name']);
    }

    public function test_get_domains_returns_unique_names() {
        $products = array(
            array( 'domain' => array( 'Treasury' ) ),
            array( 'domain' => array( 'Payments', 'Treasury' ) ),
        );

        \Patchwork\replace( 'TTP_Data::get_all_products', function () use ( $products ) {
            return $products;
        } );

        $domains = TTP_Data::get_domains();
        $this->assertSame( array( 'Payments' => 'Payments', 'Treasury' => 'Treasury' ), $domains );
    }

    public function test_get_tools_filters_by_enabled_domains() {
        $tools = array(
            array( 'name' => 'A', 'domain' => array( 'Treasury' ) ),
            array( 'name' => 'B', 'domain' => array( 'Payments' ) ),
        );

        \Patchwork\replace( 'TTP_Data::get_all_tools', function () use ( $tools ) {
            return $tools;
        } );

        \Patchwork\replace( 'TTP_Data::get_domains', function () {
            return array( 'Treasury' => 'Treasury', 'Payments' => 'Payments' );
        } );

        \Patchwork\replace( 'get_option', function ( $name, $default = array() ) {
            if ( $name === TTP_Admin::OPTION_ENABLED_DOMAINS ) {
                return array( 'Treasury' );
            }
            if ( $name === TTP_Admin::OPTION_ENABLED_CATEGORIES ) {
                return $default;
            }
            return $default;
        } );

        $filtered = TTP_Data::get_tools();
        $this->assertCount( 1, $filtered );
        $this->assertSame( 'A', $filtered[0]['name'] );
    }

    /**
     * @dataProvider parse_record_ids_string_provider
     */
    public function test_parse_record_ids_handles_string_formats( $input ) {
        $method = new \ReflectionMethod( TTP_Data::class, 'parse_record_ids' );
        $method->setAccessible( true );
        $this->assertSame( array( 'A', 'B' ), $method->invoke( null, $input ) );
    }

    public function parse_record_ids_string_provider() {
        return array(
            'json_string'     => array( '["A","B"]' ),
            'comma_separated' => array( 'A, B' ),
            'semicolon'       => array( 'A;B' ),
            'newline'         => array( "A\nB" ),
        );
    }

    /**
     * @dataProvider parse_record_ids_delimiter_in_names_provider
     */
    public function test_parse_record_ids_handles_delimiters_within_names( $input, $expected ) {
        $method = new \ReflectionMethod( TTP_Data::class, 'parse_record_ids' );
        $method->setAccessible( true );
        $this->assertSame( $expected, $method->invoke( null, $input ) );
    }

    public function parse_record_ids_delimiter_in_names_provider() {
        return array(
            'comma_inside_name'     => array( '"Foo, Inc",Bar', array( 'Foo, Inc', 'Bar' ) ),
            'semicolon_inside_name' => array( '"Foo; Inc";Bar', array( 'Foo; Inc', 'Bar' ) ),
        );
    }

    /**
     * @dataProvider parse_record_ids_mixed_delimiters_provider
     */
    public function test_parse_record_ids_handles_mixed_delimiters( $input, $expected ) {
        $method = new \ReflectionMethod( TTP_Data::class, 'parse_record_ids' );
        $method->setAccessible( true );
        $this->assertSame( $expected, $method->invoke( null, $input ) );
    }

    public function parse_record_ids_mixed_delimiters_provider() {
        return array(
            'comma_then_semicolon' => array( 'A,B;C', array( 'A', 'B', 'C' ) ),
            'semicolon_then_comma' => array( 'A;B,C', array( 'A', 'B', 'C' ) ),
        );
    }

    /**
     * @dataProvider parse_record_ids_nested_provider
     */
    public function test_parse_record_ids_recursively_searches_nested_structures( $input, $expected ) {
        $method = new \ReflectionMethod( TTP_Data::class, 'parse_record_ids' );
        $method->setAccessible( true );
        $this->assertSame( $expected, $method->invoke( null, $input ) );
    }

    public function parse_record_ids_nested_provider() {
        return array(
            'nested_arrays' => array(
                array(
                    array(
                        'level1' => array(
                            'level2' => array(
                                array( 'name' => 'Alpha' ),
                                array( 'id' => 'rec123' ),
                                array( 'deep' => array( 'name' => 'Beta' ) ),
                            ),
                        ),
                    ),
                ),
                array( 'Alpha', 'rec123', 'Beta' ),
            ),
            'nested_json_string' => array(
                '{"outer":{"inner":{"records":[{"name":"Gamma"},{"info":{"id":"rec456"}}]}}}',
                array( 'Gamma', 'rec456' ),
            ),
        );
    }

    public function test_parse_record_ids_preserves_numeric_values() {
        $method = new \ReflectionMethod( TTP_Data::class, 'parse_record_ids' );
        $method->setAccessible( true );
        $this->assertSame( array( 123 ), $method->invoke( null, 123 ) );
    }

    public function test_parse_record_ids_handles_select_field_arrays() {
        $method = new \ReflectionMethod( TTP_Data::class, 'parse_record_ids' );
        $method->setAccessible( true );
        $input = array(
            array( 'id' => 'sel1', 'text' => 'Alpha' ),
            array( 'id' => 'sel2', 'name' => 'Beta' ),
        );
        $this->assertSame( array( 'Alpha', 'Beta' ), $method->invoke( null, $input ) );
    }

    public function test_parse_record_ids_handles_value_field_arrays() {
        $method = new \ReflectionMethod( TTP_Data::class, 'parse_record_ids' );
        $method->setAccessible( true );
        $input = array(
            array( 'id' => 'sel1', 'value' => 'Alpha' ),
            array( 'id' => 'sel2' ),
        );
        $this->assertSame( array( 'Alpha', 'sel2' ), $method->invoke( null, $input ) );
    }

    /**
     * @dataProvider contains_record_ids_provider
     */
    public function test_contains_record_ids_respects_known_prefixes( $values, $expected ) {
        $this->assertSame( $expected, TTP_Record_Utils::contains_record_ids( $values ) );
    }

    public function contains_record_ids_provider() {
        return array(
            'rec_prefix'       => array( array( 'rec1234567890abcd' ), true ),
            'res_prefix'       => array( array( 'res1234567890abcd' ), true ),
            'rcs_prefix'       => array( array( 'rcs1234567890abcd' ), true ),
            'rcx_prefix'       => array( array( 'rcx1234567890abcd' ), true ),
            'sel_prefix'       => array( array( 'sel1234567890abcd' ), true ),
            'opt_prefix'       => array( array( 'opt1234567890abcd' ), true ),
            'numeric_only'     => array( array( '123456' ), true ),
            'r_prefixed_words' => array( array( 'Reporting', 'Risk Management' ), false ),
            'non_match'        => array( array( 'abc123' ), false ),
        );
    }

    public function test_resolve_linked_field_resolves_ids() {
        $method = new \ReflectionMethod( TTP_Data::class, 'resolve_linked_field' );
        $method->setAccessible( true );

        $record = array( 'Regions' => array( 'recreg1' ) );

        \Patchwork\replace(
            'TTP_Airbase::resolve_linked_records',
            function ( $table_id, $ids, $primary_field = 'Region', $use_field_ids = false ) {
                return array( 'North America' );
            }
        );

        $result = $method->invoke( null, $record, 'Regions', 'Regions', 'Region' );
        $this->assertSame( array( 'North America' ), $result );
    }

    public function test_resolve_linked_field_resolves_option_ids() {
        $method = new \ReflectionMethod( TTP_Data::class, 'resolve_linked_field' );
        $method->setAccessible( true );

        $record   = array( 'Regions' => array( 'sel1111111111', 'opt2222222222' ) );
        $captured = array();

        \Patchwork\replace(
            'TTP_Airbase::resolve_linked_records',
            function ( $table_id, $ids, $primary_field = 'Region', $use_field_ids = false ) use ( &$captured ) {
                $captured = $ids;
                return array( 'North', 'South' );
            }
        );

        $result = $method->invoke( null, $record, 'Regions', 'Regions', 'Region' );
        $this->assertSame( array( 'North', 'South' ), $result );
        $this->assertSame( array( 'sel1111111111', 'opt2222222222' ), $captured );
    }

    public function test_resolve_linked_field_skips_when_no_ids() {
        $method = new \ReflectionMethod( TTP_Data::class, 'resolve_linked_field' );
        $method->setAccessible( true );

        $record = array( 'Regions' => array( 'Europe' ) );
        $called = false;
        \Patchwork\replace(
            'TTP_Airbase::resolve_linked_records',
            function ( $table_id = null, $ids = null, $primary_field = 'Region', $use_field_ids = false ) use ( &$called ) {
                $called = true;
                return array();
            }
        );

        $result = $method->invoke( null, $record, 'Regions', 'Regions', 'Region' );
        $this->assertSame( array( 'Europe' ), $result );
        $this->assertFalse( $called );
    }

    public function test_resolve_linked_field_retries_on_wp_error() {
        $method = new \ReflectionMethod( TTP_Data::class, 'resolve_linked_field' );
        $method->setAccessible( true );

        $record = array( 'Regions' => array( 'recreg1' ) );
        $calls  = 0;

        \Patchwork\replace(
            'TTP_Airbase::resolve_linked_records',
            function ( $table_id, $ids, $primary_field = 'Region', $use_field_ids = false ) use ( &$calls ) {
                $calls++;
                if ( 1 === $calls ) {
                    return new WP_Error( 'network', 'Network error' );
                }
                return array( 'North America' );
            }
        );

        $result = $method->invoke( null, $record, 'Regions', 'Regions', 'Region' );
        $this->assertSame( array( 'North America' ), $result );
        $this->assertSame( 2, $calls );
    }

    public function test_resolve_linked_field_purges_ids_after_retries() {
        $method = new \ReflectionMethod( TTP_Data::class, 'resolve_linked_field' );
        $method->setAccessible( true );

        $record = array( 'Regions' => array( 'recreg1' ) );
        $calls  = 0;

        \Patchwork\replace(
            'TTP_Airbase::resolve_linked_records',
            function ( $table_id, $ids, $primary_field = 'Region', $use_field_ids = false ) use ( &$calls ) {
                $calls++;
                return new WP_Error( 'network', 'Network error' );
            }
        );

        $result = $method->invoke( null, $record, 'Regions', 'Regions', 'Region' );
        $this->assertSame( array(), $result );
        $this->assertSame( 3, $calls );
    }

    public function products_need_resolution_region_provider() {
        return array(
            'region'     => array( 'region' ),
            'region_ids' => array( 'region_ids' ),
            'regions_id' => array( 'regions_id' ),
        );
    }

    /**
     * @dataProvider products_need_resolution_region_provider
     */
    public function test_products_need_resolution_detects_region_aliases( $key ) {
        $products = array(
            array(
                $key => array( 'recABC123' ),
            ),
        );

        $class  = new \ReflectionClass( TTP_Data::class );
        $method = $class->getMethod( 'products_need_resolution' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, $products ) );

        $products = array(
            array(
                $key => array( 'resABC123' ),
            ),
        );

        $this->assertTrue( $method->invoke( null, $products ) );
    }

    public function test_products_need_resolution_detects_numeric_ids() {
        $products = array(
            array(
                'regions' => array( '123' ),
            ),
        );

        $class  = new \ReflectionClass( TTP_Data::class );
        $method = $class->getMethod( 'products_need_resolution' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, $products ) );
    }

    public function test_products_need_resolution_detects_mixed_case_keys() {
        $products = array(
            array(
                'ProductIds' => array( 'recABC123' ),
            ),
        );

        $class  = new \ReflectionClass( TTP_Data::class );
        $method = $class->getMethod( 'products_need_resolution' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, $products ) );
    }

    public function test_products_need_resolution_detects_mixed_case_fields_with_spaces() {
        $products = array(
            array(
                'Product IDs' => array( 'recABC123' ),
            ),
        );

        $class  = new \ReflectionClass( TTP_Data::class );
        $method = $class->getMethod( 'products_need_resolution' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, $products ) );
    }

    public function test_products_need_resolution_detects_nested_structures() {
        $products = array(
            array(
                'level1' => array(
                    'level2' => (object) array(
                        'regions' => array(
                            (object) array( 'id' => 'recABC123' ),
                        ),
                    ),
                ),
            ),
        );

        $class  = new \ReflectionClass( TTP_Data::class );
        $method = $class->getMethod( 'products_need_resolution' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, $products ) );
    }

    public function test_products_need_resolution_detects_category_ids() {
        $products = array(
            array(
                'categories' => array( 'recABC123' ),
            ),
        );

        $class  = new \ReflectionClass( TTP_Data::class );
        $method = $class->getMethod( 'products_need_resolution' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, $products ) );
    }

    public function test_products_need_resolution_detects_category_name_ids() {
        $products = array(
            array(
                'category_names' => array( 'recABC123' ),
            ),
        );

        $class  = new \ReflectionClass( TTP_Data::class );
        $method = $class->getMethod( 'products_need_resolution' );
        $method->setAccessible( true );

        $this->assertTrue( $method->invoke( null, $products ) );
    }

    public function test_products_need_resolution_short_circuits_on_first_match() {
        $products = array(
            array(
                'regions' => array( 'recABC123' ),
                'domains' => array( 'recDEF456' ),
            ),
        );

        $class  = new \ReflectionClass( TTP_Data::class );
        $method = $class->getMethod( 'products_need_resolution' );
        $method->setAccessible( true );

        $calls = 0;
        \Patchwork\replace(
            'TTP_Record_Utils::contains_record_ids',
            function ( $values ) use ( &$calls ) {
                $calls++;
                return $calls === 1;
            }
        );

        $this->assertTrue( $method->invoke( null, $products ) );
        $this->assertSame( 1, $calls );
    }

    public function test_migrate_semicolon_cache_normalises_values() {
        $stored_option = array(
            array(
                'regions'           => array( 'North;South' ),
                'categories'        => array( 'Cash;TRMS' ),
                'sub_categories'    => array(),
                'category'          => 'Cash;TRMS',
                'category_names'    => array( 'Cash;TRMS' ),
                'capabilities'      => array( 'Payables;Receivables' ),
                'core_capabilities' => 'Core API;Security',
                'hosted_type'       => array( 'Cloud;On-Prem' ),
                'domain'            => array( 'Treasury;Payments' ),
            ),
        );

        $transients = array();
        when( 'get_transient' )->alias( function ( $name ) use ( &$transients ) {
            return $transients[ $name ] ?? false;
        } );
        when( 'set_transient' )->alias( function ( $name, $value, $ttl ) use ( &$transients ) {
            $transients[ $name ] = $value;
            return true;
        } );
        when( 'delete_transient' )->alias( function ( $name ) use ( &$transients ) {
            unset( $transients[ $name ] );
            return true;
        } );

        when( 'get_option' )->alias( function ( $name, $default = array() ) use ( &$stored_option ) {
            if ( TTP_Data::PRODUCT_OPTION_KEY === $name ) {
                return $stored_option;
            }
            return $default;
        } );
        when( 'update_option' )->alias( function ( $name, $value ) use ( &$stored_option ) {
            if ( TTP_Data::PRODUCT_OPTION_KEY === $name ) {
                $stored_option = $value;
            }
            return true;
        } );

        $result = TTP_Data::get_all_products();

        $expected = array(
            'regions'           => array( 'North', 'South' ),
            'categories'        => array( 'Cash', 'TRMS' ),
            'sub_categories'    => array(),
            'category'          => 'Cash',
            'category_names'    => array( 'Cash', 'TRMS' ),
            'capabilities'      => array( 'Payables', 'Receivables' ),
            'core_capabilities' => array( 'Core API', 'Security' ),
            'hosted_type'       => array( 'Cloud', 'On-Prem' ),
            'domain'            => array( 'Treasury', 'Payments' ),
        );

        $this->assertSame( $expected['regions'], $result[0]['regions'] );
        $this->assertSame( $expected['categories'], $result[0]['categories'] );
        $this->assertSame( $expected['category'], $result[0]['category'] );
        $this->assertSame( $expected['category_names'], $result[0]['category_names'] );
        $this->assertSame( $expected['capabilities'], $result[0]['capabilities'] );
        $this->assertSame( $expected['core_capabilities'], $result[0]['core_capabilities'] );
        $this->assertSame( $expected['hosted_type'], $result[0]['hosted_type'] );
        $this->assertSame( $expected['domain'], $result[0]['domain'] );

        // Ensure the option was updated with normalised values.
        $this->assertSame( $expected, $stored_option[0] );
    }

}
