<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-record-utils.php';
require_once __DIR__ . '/../includes/class-ttp-rest.php';
require_once __DIR__ . '/../includes/class-ttp-data.php';

class TTP_Rest_Test extends TestCase {
    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when('rest_ensure_response')->alias(function ($data) {
            return $data;
        });
        when('sanitize_text_field')->alias(function ($v) {
            return $v;
        });
        when('absint')->alias(function ($v) {
            return (int) $v;
        });
        when('get_transient')->justReturn(false);
        when('set_transient')->justReturn(true);
        when('delete_transient')->justReturn(true);
        \Patchwork\replace('TTP_Data::get_domains', function () {
            return array( 'Treasury' => 'Treasury' );
        } );
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
        \Brain\Monkey\tearDown();
    }

    public function test_registers_products_endpoint() {
        $routes = [];
        when('register_rest_route')->alias(function ($namespace, $route, $args) use (&$routes) {
            $routes[] = [$namespace, $route, $args];
        });

        TTP_Rest::register_routes();

        $this->assertNotEmpty($routes);
        $products_route = array_filter($routes, function ($r) {
            return $r[0] === 'ttp/v1' && $r[1] === '/products';
        });
        $this->assertNotEmpty($products_route);
        $route = array_values($products_route)[0];
        $this->assertSame([TTP_Rest::class, 'get_products'], $route[2]['callback']);
    }

    public function test_products_endpoint_returns_product_with_new_fields() {
        $product = [
            'name'           => 'Sample Product',
            'product'        => 'Sample Co',
            'video_url'      => 'https://example.com/video',
            'logo_url'       => 'https://example.com/logo.png',
            'categories'     => ['Finance'],
            'category'       => 'Cash',
            'sub_categories' => ['Payments'],
            'category_names' => ['Finance', 'Cash', 'Payments'],
            'regions'        => ['North America'],
        ];
        \Patchwork\replace('TTP_Data::get_categories', function () {
            return array( 'Cash' => 'Cash' );
        } );

        \Patchwork\replace('get_option', function ( $name, $default = array() ) {
            if ( $name === TTP_Admin::OPTION_ENABLED_CATEGORIES ) {
                return array( 'Finance' );
            }
            if ( $name === TTP_Admin::OPTION_ENABLED_DOMAINS ) {
                return array( 'Treasury' );
            }
            return $default;
        } );

        \Patchwork\replace('TTP_Data::get_all_products', function () use ($product) {
            return [ $product ];
        });

        $request = new class {
            public function get_param($key) {
                return null;
            }
        };

        $response = TTP_Rest::get_products($request);
        $this->assertIsArray($response['products']);
        $tool = $response['products'][0];
        $this->assertArrayHasKey('product', $tool);
        $this->assertArrayHasKey('video_url', $tool);
        $this->assertArrayHasKey('logo_url', $tool);
        $this->assertArrayHasKey('category', $tool);
        $this->assertArrayHasKey('sub_categories', $tool);
        $this->assertArrayHasKey('categories', $tool);
        $this->assertArrayHasKey('category_names', $tool);
        $this->assertArrayHasKey('regions', $tool);
        $this->assertSame('Sample Co', $tool['product']);
        $this->assertSame('https://example.com/video', $tool['video_url']);
        $this->assertSame('https://example.com/logo.png', $tool['logo_url']);
        $this->assertSame('Cash', $tool['category']);
        $this->assertSame(['Payments'], $tool['sub_categories']);
        $this->assertSame(['Finance'], $tool['categories']);
        $this->assertSame(['Finance', 'Cash', 'Payments'], $tool['category_names']);
        $this->assertSame(['North America'], $tool['regions']);
    }

    public function test_tools_endpoint_passes_filter_params() {
        $captured = null;
        \Patchwork\replace('TTP_Data::get_tools', function ($args = array()) use (&$captured) {
            $captured = $args;
            return [];
        });

        $request = new class {
            private $params;
            public function __construct() {
                $this->params = [
                    'region'       => 'North America',
                    'category'     => 'Cash',
                    'sub_category' => 'Payments',
                ];
            }
            public function get_param($key) {
                return $this->params[$key] ?? null;
            }
        };

        TTP_Rest::get_tools($request);

        $this->assertSame(['North America'], $captured['region']);
        $this->assertSame(['Cash'], $captured['category']);
        $this->assertSame(['Payments'], $captured['sub_category']);
    }

    public function test_tools_endpoint_limits_to_enabled_categories_by_default() {
        $captured = null;

        \Patchwork\replace('TTP_Data::get_categories', function () {
            return array( 'Cash' => 'Cash', 'Lite' => 'Lite' );
        } );

        \Patchwork\replace('get_option', function ( $name, $default = array() ) {
            if ( $name === TTP_Admin::OPTION_ENABLED_CATEGORIES ) {
                return array( 'Cash', 'Lite' );
            }
            if ( $name === TTP_Admin::OPTION_ENABLED_DOMAINS ) {
                return array( 'Treasury' );
            }
            return $default;
        } );

        \Patchwork\replace('TTP_Data::get_tools', function ( $args = array() ) use ( &$captured ) {
            $captured = $args;
            return array();
        } );

        $request = new class {
            public function get_param( $key ) {
                return null;
            }
        };

        TTP_Rest::get_tools( $request );

        $this->assertSame( array( 'Cash', 'Lite' ), $captured['category'] );
    }

    public function test_products_endpoint_returns_resolved_names() {
        $product = [
            'regions'    => ['North America'],
            'categories' => ['Finance'],
        ];
        \Patchwork\replace('TTP_Data::get_categories', function () {
            return array( 'Cash' => 'Cash' );
        } );

        \Patchwork\replace('get_option', function ( $name, $default = array() ) {
            if ( $name === TTP_Admin::OPTION_ENABLED_CATEGORIES ) {
                return array( 'Finance' );
            }
            if ( $name === TTP_Admin::OPTION_ENABLED_DOMAINS ) {
                return array( 'Treasury' );
            }
            return $default;
        } );

        \Patchwork\replace('TTP_Data::get_all_products', function () use ( $product ) {
            return [ $product ];
        });

        $request = new class {};
        $response = TTP_Rest::get_products( $request );

        $this->assertSame( ['North America'], $response['products'][0]['regions'] );
        $this->assertSame( ['Finance'], $response['products'][0]['categories'] );
    }

    public function test_products_endpoint_strips_unresolved_ids_and_refreshes_cache() {
        $product = [
            'regions'    => ['rec123', 'North America'],
            'categories' => ['rec456'],
        ];

        $refresh_called = false;

        \Patchwork\replace('TTP_Data::get_categories', function () {
            return array( 'Cash' => 'Cash' );
        } );

        \Patchwork\replace('get_option', function ( $name, $default = array() ) {
            if ( $name === TTP_Admin::OPTION_ENABLED_CATEGORIES ) {
                return array( 'rec456' );
            }
            if ( $name === TTP_Admin::OPTION_ENABLED_DOMAINS ) {
                return array( 'Treasury' );
            }
            return $default;
        } );

        \Patchwork\replace('TTP_Data::get_all_products', function () use ( $product ) {
            return [ $product ];
        });

        \Patchwork\replace('TTP_Data::refresh_product_cache', function () use ( &$refresh_called ) {
            $refresh_called = true;
        });

        $request  = new class {};
        $response = TTP_Rest::get_products( $request );

        $this->assertTrue( $refresh_called );
        $this->assertSame( [ 'North America' ], $response['products'][0]['regions'] );
        $this->assertArrayNotHasKey( 'categories', $response['products'][0] );
    }

    public function test_products_endpoint_marks_incomplete_products() {
        $product = [
            'regions'    => ['rec123', 'North America'],
            'categories' => ['Finance'],
        ];

        \Patchwork\replace('TTP_Data::get_categories', function () {
            return array( 'Cash' => 'Cash' );
        } );

        \Patchwork\replace('get_option', function ( $name, $default = array() ) {
            if ( $name === TTP_Admin::OPTION_ENABLED_CATEGORIES ) {
                return array( 'Finance' );
            }
            if ( $name === TTP_Admin::OPTION_ENABLED_DOMAINS ) {
                return array( 'Treasury' );
            }
            return $default;
        } );

        \Patchwork\replace('TTP_Data::get_all_products', function () use ( $product ) {
            return [ $product ];
        });

        \Patchwork\replace('TTP_Data::refresh_product_cache', function () {});

        $request  = new class {};
        $response = TTP_Rest::get_products( $request );

        $this->assertTrue( $response['products'][0]['incomplete'] );
    }

    public function test_refresh_endpoint_triggers_cache_refresh() {
        $called = false;
        \Patchwork\replace('TTP_Data::refresh_product_cache', function () use ( &$called ) {
            $called = true;
        });

        $request = new class {};
        $result  = TTP_Rest::refresh_data( $request );

        $this->assertTrue( $called );
        $this->assertSame( 'refreshed', $result['status'] );
    }
}
