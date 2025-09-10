<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-airbase.php';

class TTP_Airbase_Test extends TestCase {
    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when('wp_parse_url')->alias('parse_url');
        when('wp_http_validate_url')->alias(function ($url) {
            return filter_var($url, FILTER_VALIDATE_URL);
        });
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
    }

    public function test_request_includes_authorization_header() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return TTP_Airbase::DEFAULT_BASE_URL;
                case TTP_Airbase::OPTION_BASE_ID:
                    return 'base123';
                case TTP_Airbase::OPTION_API_PATH:
                    return 'tblXYZ';
                default:
                    return $default;
            }
        });

        when('is_wp_error')->alias(function ($thing) {
            return $thing instanceof WP_Error;
        });
        when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        when('wp_remote_retrieve_body')->alias(function ($response) {
            return $response['body'];
        });

        $self         = $this;
        $expected_url = TTP_Airbase::DEFAULT_BASE_URL . '/base123/tblXYZ';
        expect('wp_remote_get')->once()->andReturnUsing(function ($url, $args) use ($self, $expected_url) {
            $self->assertSame($expected_url, $url);
            $self->assertArrayHasKey('headers', $args);
            $self->assertSame('Bearer abc123', $args['headers']['Authorization']);
            return [
                'response' => ['code' => 200],
                'body'     => json_encode(['records' => []]),
            ];
        });

        $data = TTP_Airbase::get_vendors();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('records', $data);
    }

    public function test_returns_wp_error_on_request_failure() {
        when('get_option')->alias(function ($option, $default = false) {
            return TTP_Airbase::OPTION_TOKEN === $option ? 'abc123' : $default;
        });

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
        when('get_option')->alias(function ($option, $default = false) {
            return TTP_Airbase::OPTION_TOKEN === $option ? '' : $default;
        });

        $result = TTP_Airbase::get_vendors();
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_appends_v0_when_base_url_has_no_path() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return 'https://api.airtable.com';
                case TTP_Airbase::OPTION_BASE_ID:
                    return 'base123';
                case TTP_Airbase::OPTION_API_PATH:
                    return 'tblXYZ';
                default:
                    return $default;
            }
        });

        when('is_wp_error')->alias(function ($thing) {
            return $thing instanceof WP_Error;
        });
        when('wp_remote_retrieve_response_code')->alias(function ($response) {
            return $response['response']['code'];
        });
        when('wp_remote_retrieve_body')->alias(function ($response) {
            return $response['body'];
        });

        $expected_url = 'https://api.airtable.com/v0/base123/tblXYZ';
        expect('wp_remote_get')->once()->andReturnUsing(function ($url) use ($expected_url) {
            $this->assertSame($expected_url, $url);
            return [
                'response' => ['code' => 200],
                'body'     => json_encode(['records' => []]),
            ];
        });

        $data = TTP_Airbase::get_vendors();
        $this->assertIsArray($data);
    }
}
