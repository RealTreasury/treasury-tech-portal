<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-airbase.php';
require_once __DIR__ . '/../includes/class-ttp-data.php';

if ( ! function_exists( 'run_api_test' ) ) {
    $script = file_get_contents( __DIR__ . '/../scripts/run-api-test.php' );
    $script = preg_replace( '/run_api_test\(\);\s*$/', '', $script );
    eval( '?>' . $script );
}

class TTP_Admin_Test extends TestCase {
    protected $schema_map;

    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when( 'is_wp_error' )->alias( function ( $thing ) {
            return $thing instanceof WP_Error;
        } );
        when( 'sanitize_text_field' )->returnArg();
        when( 'get_transient' )->justReturn( false );
        when( 'set_transient' )->justReturn( true );
        when( 'delete_transient' )->justReturn( true );
        when( 'update_option' )->justReturn( true );
        when( 'do_action' )->justReturn( null );

        $this->schema_map = [
            'Product Name'    => 'fld_name',
            'Linked Vendor'   => 'fld_vendor',
            'Product Website' => 'fld_website',
            'Full Website URL' => 'fld_full_url',
            'Demo Video URL'  => 'fldHyVJRr3O5rkgd7',
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
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
        \Brain\Monkey\tearDown();
    }

    private function id_fields( array $fields ) {
        $mapped = [];
        foreach ( $this->schema_map as $name => $id ) {
            $mapped[ $id ] = array_key_exists( $name, $fields ) ? $fields[ $name ] : '';
        }
        return $mapped;
    }

    public function test_run_api_test_outputs_names_not_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Linked Vendor'   => ['recven1'],
                'Product Website' => 'example.com',
                'Status'          => 'Active',
                'Hosted Type'     => ['rechost1'],
                'Domain'          => ['recdom1'],
                'Regions'         => ['recreg1'],
                'Category'        => ['reccat1'],
                'Sub Categories'  => ['recsc1'],
                'Capabilities'    => ['reccap1'],
            ]),
        ];

        \Patchwork\replace( 'TTP_Airbase::get_vendors', function( $fields = array() ) use ( $record ) {
            return array( 'records' => array( $record ) );
        } );

        \Patchwork\replace( 'TTP_Airbase::resolve_linked_records', function ( $table, $ids, $primary = 'Name' ) {
            $maps = array(
                'Regions'        => array( 'recreg1' => 'North America' ),
                'Vendors'        => array( 'recven1' => 'Acme Corp' ),
                'Hosted Type'    => array( 'rechost1' => 'Cloud' ),
                'Domain'         => array( 'recdom1' => 'Banking' ),
                'Category'       => array( 'reccat1' => 'Cash' ),
                'Sub Categories' => array( 'recsc1' => 'Payments' ),
                'Capabilities'   => array( 'reccap1' => 'API' ),
            );
            $out = array();
            foreach ( (array) $ids as $id ) {
                if ( isset( $maps[ $table ][ $id ] ) ) {
                    $out[] = $maps[ $table ][ $id ];
                }
            }
            return $out;
        } );

        $stored = array();
        \Patchwork\replace( 'TTP_Data::save_vendors', function ( $vendors ) use ( &$stored ) {
            $stored = $vendors;
        } );

        when( 'get_option' )->alias( function ( $key, $default = null ) use ( &$stored ) {
            if ( TTP_Data::VENDOR_OPTION_KEY === $key ) {
                return $stored;
            }
            return $default;
        } );

        ob_start();
        run_api_test();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Acme Corp', $output );
        $this->assertStringContainsString( 'North America', $output );
        $this->assertStringNotContainsString( 'recven1', $output );
        $this->assertStringNotContainsString( 'recreg1', $output );
    }

}

