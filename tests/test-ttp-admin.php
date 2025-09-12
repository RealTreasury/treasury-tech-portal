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
            'Vendor'          => 'fld_vendor',
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
                'Vendor'          => ['recven1'],
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

    public function test_run_api_test_drops_unresolved_ids() {
        $record = [
            'id'     => 'rec1',
            'fields' => $this->id_fields([
                'Product Name'    => 'Sample Product',
                'Vendor'          => ['recven1'],
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
            if ( 'Regions' === $table ) {
                return new WP_Error( 'err', 'fail' );
            }
            $maps = array(
                'Vendors'        => array( 'recven1' => 'Acme Corp' ),
                'Hosted Type'    => array( 'rechost1' => 'Cloud' ),
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

        $logged = array();
        \Patchwork\replace( 'error_log', function ( $msg ) use ( &$logged ) {
            $logged[] = $msg;
        } );

        ob_start();
        run_api_test();
        $output = ob_get_clean();

        $this->assertStringNotContainsString( 'recreg1', $output );
        $this->assertStringNotContainsString( 'North America', $output );
        $this->assertNotEmpty( $logged );
        $this->assertStringContainsString( 'recreg1', implode( ' ', $logged ) );
    }

    public function test_save_domains_updates_option() {
        $updated = array();
        when( 'current_user_can' )->justReturn( true );
        when( 'check_admin_referer' )->justReturn( true );
        when( 'wp_redirect' )->alias( function () {
            throw new Exception( 'redirect' );
        } );
        when( 'update_option' )->alias( function ( $name, $value ) use ( &$updated ) {
            $updated[ $name ] = $value;
            return true;
        } );
        when( 'admin_url' )->alias( function ( $url = '' ) {
            return $url;
        } );
        when( 'add_query_arg' )->alias( function ( $key, $value, $url ) {
            return $url;
        } );

        $_POST['enabled_domains'] = array( 'Treasury', 'Payments' );

        try {
            TTP_Admin::save_domains();
        } catch ( Exception $e ) {
            // Expected redirect.
        }

        $this->assertSame( array( 'Treasury', 'Payments' ), $updated[ TTP_Admin::OPTION_ENABLED_DOMAINS ] );

        unset( $_POST['enabled_domains'] );
    }

    public function test_render_page_includes_filter_controls_for_all_columns() {
        when( 'current_user_can' )->justReturn( true );
        when( 'esc_html_e' )->alias( function( $text ) { echo $text; } );
        when( 'esc_attr_e' )->alias( function( $text ) { echo $text; } );
        when( 'esc_attr__' )->returnArg();
        when( 'esc_html' )->returnArg();
        when( 'esc_attr' )->returnArg();
        when( 'esc_url' )->returnArg();
        when( 'esc_url_raw' )->returnArg();
        when( 'wp_nonce_field' )->justReturn( '' );
        when( 'submit_button' )->justReturn( '' );
        when( 'checked' )->justReturn( '' );
        when( 'admin_url' )->alias( function( $url = '' ) { return $url; } );
        when( 'add_query_arg' )->alias( function( $key, $value, $url ) { return $url; } );
        when( 'get_option' )->alias( function ( $key, $default = array() ) {
            return $default;
        } );

        \Patchwork\replace( 'TTP_Data::get_all_vendors', function () {
            return array(
                array(
                    'name'           => 'Vendor One',
                    'vendor'         => 'Acme',
                    'website'        => 'https://example.com',
                    'video_url'      => 'https://example.com/video',
                    'status'         => 'Active',
                    'hosted_type'    => array( 'Cloud' ),
                    'domain'         => array( 'Payments' ),
                    'regions'        => array( 'US' ),
                    'sub_categories' => array( 'Sub1' ),
                    'category'       => 'Cash',
                    'category_names' => array( 'Cash' ),
                    'capabilities'   => array( 'API' ),
                    'logo_url'       => 'https://example.com/logo.png',
                    'hq_location'    => 'NY',
                    'founded_year'   => '2020',
                    'founders'       => 'Alice',
                ),
            );
        } );
        \Patchwork\replace( 'TTP_Data::get_categories', function () {
            return array();
        } );
        \Patchwork\replace( 'TTP_Data::get_domains', function () {
            return array();
        } );

        ob_start();
        TTP_Admin::render_page();
        $html = ob_get_clean();

        $dom   = new DOMDocument();
        @$dom->loadHTML( $html );
        $xpath = new DOMXPath( $dom );
        $headers = $xpath->query( '//table[contains(@class, "treasury-portal-admin-table")]//th[@data-sort-key]' );
        foreach ( $headers as $th ) {
            $key   = $th->getAttribute( 'data-sort-key' );
            $nodes = $xpath->query( "//tr[contains(@class,'tp-filter-row')]//*[@class='tp-filter-control' and @data-filter-key='$key']" );
            $this->assertGreaterThan( 0, $nodes->length, 'Missing filter control for ' . $key );
        }
    }
}

