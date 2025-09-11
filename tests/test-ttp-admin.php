<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-data.php';
require_once __DIR__ . '/../includes/class-ttp-airbase.php';

if ( ! function_exists( 'get_option' ) ) {
    $GLOBALS['wp_options'] = [];
    function get_option( $name, $default = false ) {
        return isset( $GLOBALS['wp_options'][ $name ] ) ? $GLOBALS['wp_options'][ $name ] : $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) {
        $GLOBALS['wp_options'][ $name ] = $value;
        return true;
    }
}

if ( ! function_exists( 'run_api_test' ) ) {
    function run_api_test() {
        TTP_Data::refresh_vendor_cache();
        return isset( $GLOBALS['__ttp_saved_vendors'] ) ? $GLOBALS['__ttp_saved_vendors'] : [];
    }
}

class TTP_Admin_Test extends TestCase {
    protected $schema_map;

    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when( 'is_wp_error' )->alias( function ( $thing ) {
            return $thing instanceof WP_Error;
        } );
        when( 'sanitize_text_field' )->returnArg();

        $this->schema_map = [
            'Product Name'    => 'fld_name',
            'Linked Vendor'   => 'fld_vendor',
            'Product Website' => 'fld_website',
            'Product Video'   => 'fld_video',
            'Logo URL'        => 'fld_logo',
            'Status'          => 'fld_status',
            'Hosted Type'     => 'fld_hosted',
            'Domain'          => 'fld_domain',
            'Regions'         => 'fld_regions',
            'Category'        => 'fld_category',
            'Sub Categories'  => 'fld_sub',
            'Capabilities'    => 'fld_caps',
            'HQ Location'     => 'fld_hq',
            'Founded Year'    => 'fld_year',
            'Founders'        => 'fld_founders',
        ];

        $schema =& $this->schema_map;
        \Patchwork\replace( 'TTP_Airbase::get_table_schema', function () use ( &$schema ) {
            return $schema;
        } );

        \Patchwork\replace( 'TTP_Data::save_vendors', function ( $vendors ) {
            $GLOBALS['__ttp_saved_vendors'] = $vendors;
        } );
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
        \Brain\Monkey\tearDown();
        unset( $GLOBALS['__ttp_saved_vendors'] );
    }

    private function id_fields( array $fields ) {
        $mapped = [];
        foreach ( $this->schema_map as $name => $id ) {
            $mapped[ $id ] = isset( $fields[ $name ] ) ? $fields[ $name ] : '';
        }
        return $mapped;
    }

    public function test_run_api_test_replaces_ids_with_names() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields( [
                'Product Name'  => 'Sample Product',
                'Linked Vendor' => [ 'recven1' ],
                'Regions'       => [ 'recreg1' ],
                'Category'      => [ 'reccat1' ],
            ] ),
        ];

        \Patchwork\replace( 'TTP_Airbase::get_vendors', function ( $fields = array(), $return_fields_by_id = false ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name' ) {
            $maps = [
                'Regions'  => [ 'recreg1' => 'North America' ],
                'Vendors'  => [ 'recven1' => 'Acme Corp' ],
                'Category' => [ 'reccat1' => 'Cash' ],
            ];
            $out = [];
            foreach ( (array) $ids as $id ) {
                if ( isset( $maps[ $table_id ][ $id ] ) ) {
                    $out[] = $maps[ $table_id ][ $id ];
                }
            }
            return $out;
        } );

        $vendors = run_api_test();

        $this->assertSame( 'Acme Corp', $vendors[0]['vendor'] );
        $this->assertSame( [ 'North America' ], $vendors[0]['regions'] );
        $this->assertSame( [ 'Cash' ], $vendors[0]['categories'] );
    }

    public function test_run_api_test_logs_and_drops_unresolved_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields( [
                'Product Name'  => 'Sample Product',
                'Linked Vendor' => [ 'recven1' ],
                'Regions'       => [ 'recreg1', 'recreg2' ],
            ] ),
        ];

        \Patchwork\replace( 'TTP_Airbase::get_vendors', function ( $fields = array(), $return_fields_by_id = false ) use ( $record ) {
            return [ 'records' => [ $record ] ];
        } );

        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table_id, $ids, $primary_field = 'Name' ) {
            if ( 'Regions' === $table_id ) {
                return [ 'North America' ];
            }
            if ( 'Vendors' === $table_id ) {
                return new WP_Error( 'err', 'fail' );
            }
            return array();
        } );

        $logged = [];
        \Patchwork\replace( 'error_log', function ( $msg ) use ( &$logged ) {
            $logged[] = $msg;
        } );

        $vendors = run_api_test();

        $this->assertSame( [ 'North America' ], $vendors[0]['regions'] );
        $this->assertSame( '', $vendors[0]['vendor'] );
        $this->assertNotEmpty( $logged );
        $this->assertStringContainsString( 'recreg2', implode( ' ', $logged ) );
        $this->assertStringContainsString( 'recven1', implode( ' ', $logged ) );

        $unresolved = isset( $GLOBALS['wp_options']['ttp_unresolved_fields'] ) ? $GLOBALS['wp_options']['ttp_unresolved_fields'] : [];
        $this->assertNotEmpty( $unresolved );
        $this->assertStringContainsString( 'recreg2', implode( ' ', $unresolved ) );
        $this->assertStringContainsString( 'recven1', implode( ' ', $unresolved ) );
    }
}
