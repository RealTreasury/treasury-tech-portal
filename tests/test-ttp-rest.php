<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-rest.php';
require_once __DIR__ . '/../includes/class-ttp-data.php';

class TTP_Rest_Test extends TestCase {
    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when('rest_ensure_response')->alias(function ($data) {
            return $data;
        });
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
        \Brain\Monkey\tearDown();
    }

    public function test_registers_tools_endpoint() {
        $routes = [];
        when('register_rest_route')->alias(function ($namespace, $route, $args) use (&$routes) {
            $routes[] = [$namespace, $route, $args];
        });

        TTP_Rest::register_routes();

        $this->assertNotEmpty($routes);
        $tools_route = array_filter($routes, function ($r) {
            return $r[0] === 'ttp/v1' && $r[1] === '/tools';
        });
        $this->assertNotEmpty($tools_route);
        $route = array_values($tools_route)[0];
        $this->assertSame([TTP_Rest::class, 'get_tools'], $route[2]['callback']);
    }

    public function test_vendors_endpoint_returns_vendor_with_new_fields() {
        $vendor = [
            'name'            => 'Sample Product',
            'video_url'       => 'https://example.com/video',
            'logo_url'        => 'https://example.com/logo.png',
            'parent_category' => 'Cash',
            'sub_categories'  => ['Payments'],
        ];

        \Patchwork\replace('TTP_Data::get_all_vendors', function () use ($vendor) {
            return [ $vendor ];
        });

        $response = TTP_Rest::get_vendors(null);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('video_url', $response[0]);
        $this->assertArrayHasKey('logo_url', $response[0]);
        $this->assertArrayHasKey('parent_category', $response[0]);
        $this->assertArrayHasKey('sub_categories', $response[0]);
        $this->assertSame('https://example.com/video', $response[0]['video_url']);
        $this->assertSame('https://example.com/logo.png', $response[0]['logo_url']);
        $this->assertSame('Cash', $response[0]['parent_category']);
        $this->assertSame(['Payments'], $response[0]['sub_categories']);
    }

    public function test_get_tools_passes_query_params() {
        $captured = null;
        \Patchwork\replace('TTP_Data::get_tools', function ($args = []) use (&$captured) {
            $captured = $args;
            return [];
        });

        $request = new class {
            public function get_param($key) {
                $params = [
                    'region' => 'US',
                    'parent_category' => 'Cash',
                    'sub_category' => 'Payments',
                ];
                return $params[$key] ?? null;
            }
        };

        $response = TTP_Rest::get_tools($request);

        $this->assertSame('US', $captured['region']);
        $this->assertSame('Cash', $captured['parent_category']);
        $this->assertSame('Payments', $captured['sub_category']);
        $this->assertSame([], $response);
    }
}
