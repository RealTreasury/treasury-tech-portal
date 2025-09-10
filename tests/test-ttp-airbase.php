<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-airbase.php';

class TTP_Airbase_Test extends TestCase {
    protected function setUp(): void {
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
    }

    public function test_request_includes_authorization_header() {
        expect('get_option')
            ->once()
            ->with(TTP_Airbase::OPTION_TOKEN)
            ->andReturn('abc123');

        when('is_wp_error')->alias(function ($thing) {
            return $thing instanceof WP_Error;
        });
        when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        when('wp_remote_retrieve_body')->alias(function ($response) {
            return $response['body'];
        });

        $self = $this;
        expect('wp_remote_get')->once()->andReturnUsing(function ($url, $args) use ($self) {
            $self->assertSame(TTP_Airbase::API_URL, $url);
            $self->assertArrayHasKey('headers', $args);
            $self->assertSame('Bearer abc123', $args['headers']['Authorization']);
            return [
                'response' => ['code' => 200],
                'body'     => json_encode(['vendors' => []]),
            ];
        });

        $data = TTP_Airbase::get_vendors();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('vendors', $data);
    }

    public function test_returns_wp_error_on_request_failure() {
        expect('get_option')
            ->once()
            ->with(TTP_Airbase::OPTION_TOKEN)
            ->andReturn('abc123');

        expect('wp_remote_get')
            ->once()
            ->andReturn(new WP_Error('http_error', 'Request failed'));

        when('is_wp_error')->alias(function ($thing) {
            return $thing instanceof WP_Error;
        });

        $result = TTP_Airbase::get_vendors();
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_returns_wp_error_when_token_missing() {
        expect('get_option')
            ->once()
            ->with(TTP_Airbase::OPTION_TOKEN)
            ->andReturn('');

        $result = TTP_Airbase::get_vendors();
        $this->assertInstanceOf(WP_Error::class, $result);
    }
}
