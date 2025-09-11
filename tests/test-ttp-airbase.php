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
        when('sanitize_text_field')->returnArg();
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
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
        $expected_url = TTP_Airbase::DEFAULT_BASE_URL . '/base123/tblXYZ?cellFormat=json';
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
        $this->assertCount(0, $data);
    }

    public function test_returns_wp_error_on_request_failure() {
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

    public function test_returns_wp_error_when_base_id_missing() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return TTP_Airbase::DEFAULT_BASE_URL;
                case TTP_Airbase::OPTION_API_PATH:
                    return 'tblXYZ';
                default:
                    return $default;
            }
        });

        $result = TTP_Airbase::get_vendors();
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_returns_wp_error_when_api_path_missing() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return TTP_Airbase::DEFAULT_BASE_URL;
                case TTP_Airbase::OPTION_BASE_ID:
                    return 'base123';
                default:
                    return $default;
            }
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

        $expected_url = 'https://api.airtable.com/v0/base123/tblXYZ?cellFormat=json';
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

    public function test_paginates_until_all_records_retrieved() {
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

        $self       = $this;
        $base_url   = TTP_Airbase::DEFAULT_BASE_URL . '/base123/tblXYZ?cellFormat=json';
        $call_count = 0;
        expect('wp_remote_get')->twice()->andReturnUsing(function ($url) use ($self, $base_url, &$call_count) {
            $call_count++;
            if (1 === $call_count) {
                $self->assertSame($base_url, $url);
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'records' => [['id' => 'rec1']],
                        'offset'  => 'abc',
                    ]),
                ];
            }

            $self->assertSame($base_url . '&offset=abc', $url);
            return [
                'response' => ['code' => 200],
                'body'     => json_encode([
                    'records' => [['id' => 'rec2']],
                ]),
            ];
        });

        $records = TTP_Airbase::get_vendors();
        $this->assertIsArray($records);
        $this->assertCount(2, $records);
        $this->assertSame('rec2', $records[1]['id']);
    }

    public function test_adds_fields_query_parameters() {
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

        \Patchwork\replace('TTP_Airbase::get_table_schema', function () {
            return ['Name' => 'fldName', 'Email' => 'fldEmail'];
        });

        $expected_url = TTP_Airbase::DEFAULT_BASE_URL . '/base123/tblXYZ?cellFormat=json&fields[]=Name&fields[]=Email';
        expect('wp_remote_get')->once()->andReturnUsing(function ($url) use ($expected_url) {
            $this->assertSame($expected_url, $url);
            return [
                'response' => ['code' => 200],
                'body'     => json_encode([
                    'records' => [ [ 'fields' => [ 'Name' => 'Acme', 'Email' => 'a@b.com' ] ] ],
                ]),
            ];
        });

        $records = TTP_Airbase::get_vendors(['Name', 'Email']);
        $this->assertIsArray($records);
    }

    public function test_encodes_fields_with_spaces() {
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

        \Patchwork\replace('TTP_Airbase::get_table_schema', function () {
            return [
                'Product Name' => 'fldProd',
                'Category'     => 'fldCat',
            ];
        });

        $expected_url = TTP_Airbase::DEFAULT_BASE_URL . '/base123/tblXYZ?cellFormat=json&fields[]=Product%20Name&fields[]=Category';
        expect('wp_remote_get')->once()->andReturnUsing(function ($url) use ($expected_url) {
            $this->assertSame($expected_url, $url);
            return [
                'response' => ['code' => 200],
                'body'     => json_encode([
                    'records' => [ [ 'fields' => [ 'Product Name' => 'P', 'Category' => 'C' ] ] ],
                ]),
            ];
        });

        $records = TTP_Airbase::get_vendors(['Product Name', 'Category']);
        $this->assertIsArray($records);
    }

    public function test_adds_field_id_query_parameters() {
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

        \Patchwork\replace('TTP_Airbase::get_table_schema', function () {
            return ['Name' => 'fldName', 'Email' => 'fldEmail'];
        });

        $expected_url = TTP_Airbase::DEFAULT_BASE_URL . '/base123/tblXYZ?cellFormat=json&fields[]=fldName&fields[]=fldEmail&returnFieldsByFieldId=true';
        expect('wp_remote_get')->once()->andReturnUsing(function ($url) use ($expected_url) {
            $this->assertSame($expected_url, $url);
            return [
                'response' => ['code' => 200],
                'body'     => json_encode([
                    'records' => [ [ 'fields' => [ 'fldName' => 'Acme', 'fldEmail' => 'a@b.com' ] ] ],
                ]),
            ];
        });

        $records = TTP_Airbase::get_vendors(['Name', 'Email'], true);
        $this->assertIsArray($records);
    }

    public function test_get_table_schema_returns_cached_value() {
        when('get_transient')->alias(function ($key) {
            return 'ttp_airbase_schema' === $key ? [ 'tblXYZ' => [ 'Name' => 'fldName' ] ] : false;
        });

        expect('wp_remote_get')->never();
        expect('set_transient')->never();

        $schema = TTP_Airbase::get_table_schema('tblXYZ');
        $this->assertSame([ 'Name' => 'fldName' ], $schema);
    }

    public function test_get_table_schema_maps_multiple_fields() {
        when('get_transient')->alias(function ($key) {
            return false;
        });
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

        $body = json_encode([
            'tables' => [
                [
                    'id' => 'tblXYZ',
                    'name' => 'Products',
                    'fields' => [
                        [ 'name' => 'Name', 'id' => 'fldName' ],
                        [ 'name' => 'Status', 'id' => 'fldStatus' ],
                    ],
                ],
            ],
        ]);

        expect('wp_remote_get')->once()->andReturn([
            'response' => [ 'code' => 200 ],
            'body'     => $body,
        ]);

        $self = $this;
        expect('set_transient')->once()->andReturnUsing(function ($key, $value, $ttl) use ($self) {
            $self->assertSame('ttp_airbase_schema', $key);
            $self->assertSame(DAY_IN_SECONDS, $ttl);
            $self->assertSame([ 'Name' => 'fldName', 'Status' => 'fldStatus' ], $value['tblXYZ']);
            $self->assertSame($value['tblXYZ'], $value['Products']);
            return true;
        });

        $schema = TTP_Airbase::get_table_schema('tblXYZ');
        $this->assertSame([ 'Name' => 'fldName', 'Status' => 'fldStatus' ], $schema);
    }

    public function test_get_table_schema_returns_error_when_tables_missing() {
        when('get_transient')->alias(function ($key) {
            return false;
        });
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

        $body = json_encode([ 'foo' => 'bar' ]);

        expect('wp_remote_get')->once()->andReturn([
            'response' => [ 'code' => 200 ],
            'body'     => $body,
        ]);

        $result = TTP_Airbase::get_table_schema('tblXYZ');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_tables', $result->get_error_code());
    }

    public function test_get_table_schema_returns_error_on_invalid_json() {
        when('get_transient')->alias(function ($key) {
            return false;
        });
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

        expect('wp_remote_get')->once()->andReturn([
            'response' => [ 'code' => 200 ],
            'body'     => '{invalid',
        ]);

        $result = TTP_Airbase::get_table_schema('tblXYZ');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_json', $result->get_error_code());
    }

    public function test_resolve_linked_records_handles_multiple_ids() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return TTP_Airbase::DEFAULT_BASE_URL;
                case TTP_Airbase::OPTION_BASE_ID:
                    return 'base123';
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

        $body = json_encode([
            'records' => [
                [ 'id' => 'rec1', 'fields' => [ 'Name' => 'First' ] ],
                [ 'id' => 'rec2', 'fields' => [ 'Name' => 'Second' ] ],
            ],
        ]);

        expect('wp_remote_get')->once()->andReturn([
            'response' => [ 'code' => 200 ],
            'body'     => $body,
        ]);

        $values = TTP_Airbase::resolve_linked_records('Vendors1', ['rec1', 'rec2']);
        $this->assertSame(['First', 'Second'], $values);
    }

    public function test_resolve_linked_records_batches_requests_when_ids_exceed_limit() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return TTP_Airbase::DEFAULT_BASE_URL;
                case TTP_Airbase::OPTION_BASE_ID:
                    return 'base123';
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

        $ids = array();
        for ( $i = 1; $i <= TTP_Airbase::RECORD_BATCH_SIZE + 5; $i++ ) {
            $ids[] = 'rec' . $i;
        }

        $self  = $this;
        $call  = 0;
        expect('wp_remote_get')->twice()->andReturnUsing(function ($url, $args) use ($self, &$call) {
            $call++;
            if ( 1 === $call ) {
                $self->assertStringContainsString('rec1', $url);
                $self->assertStringContainsString('rec50', $url);
                $self->assertStringNotContainsString('rec51', $url);

                $records = array();
                for ( $i = 1; $i <= TTP_Airbase::RECORD_BATCH_SIZE; $i++ ) {
                    $records[] = array( 'id' => 'rec' . $i, 'fields' => array( 'Name' => 'Name' . $i ) );
                }
            } else {
                $self->assertStringContainsString('rec51', $url);

                $records = array();
                for ( $i = TTP_Airbase::RECORD_BATCH_SIZE + 1; $i <= TTP_Airbase::RECORD_BATCH_SIZE + 5; $i++ ) {
                    $records[] = array( 'id' => 'rec' . $i, 'fields' => array( 'Name' => 'Name' . $i ) );
                }
            }

            return array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( array( 'records' => $records ) ),
            );
        });

        $values   = TTP_Airbase::resolve_linked_records( 'Vendors2', $ids );
        $expected = array();
        for ( $i = 1; $i <= TTP_Airbase::RECORD_BATCH_SIZE + 5; $i++ ) {
            $expected[] = 'Name' . $i;
        }
        $this->assertSame( $expected, $values );
    }

    public function test_resolve_linked_records_returns_empty_array_when_ids_empty() {
        when('get_option')->alias(function ($option, $default = false) {
            return TTP_Airbase::OPTION_TOKEN === $option ? 'abc123' : $default;
        });

        $result = TTP_Airbase::resolve_linked_records('Vendors3', []);
        $this->assertSame([], $result);
    }

    public function test_resolve_linked_records_returns_wp_error_on_api_error() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return TTP_Airbase::DEFAULT_BASE_URL;
                case TTP_Airbase::OPTION_BASE_ID:
                    return 'base123';
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

        expect('wp_remote_get')->once()->andReturn([
            'response' => [ 'code' => 500 ],
            'body'     => '',
        ]);

        $result = TTP_Airbase::resolve_linked_records('Vendors4', ['rec1']);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('api_error', $result->get_error_code());
    }

    public function test_resolve_linked_records_uses_cache_on_subsequent_calls() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return TTP_Airbase::DEFAULT_BASE_URL;
                case TTP_Airbase::OPTION_BASE_ID:
                    return 'base123';
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

        $body = json_encode([
            'records' => [ [ 'id' => 'rec1', 'fields' => [ 'Name' => 'First' ] ] ],
        ]);

        expect('wp_remote_get')->once()->andReturn([
            'response' => [ 'code' => 200 ],
            'body'     => $body,
        ]);

        $values  = TTP_Airbase::resolve_linked_records('Vendors5', ['rec1']);
        $values2 = TTP_Airbase::resolve_linked_records('Vendors5', ['rec1']);

        $this->assertSame(['First'], $values);
        $this->assertSame(['First'], $values2);
    }

    public function test_resolve_linked_records_requests_only_uncached_ids() {
        when('get_option')->alias(function ($option, $default = false) {
            switch ($option) {
                case TTP_Airbase::OPTION_TOKEN:
                    return 'abc123';
                case TTP_Airbase::OPTION_BASE_URL:
                    return TTP_Airbase::DEFAULT_BASE_URL;
                case TTP_Airbase::OPTION_BASE_ID:
                    return 'base123';
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

        $call = 0;
        expect('wp_remote_get')->twice()->andReturnUsing(function ($url, $args) use (&$call) {
            $call++;
            if ( 1 === $call ) {
                return [
                    'response' => [ 'code' => 200 ],
                    'body'     => json_encode([
                        'records' => [ [ 'id' => 'rec1', 'fields' => [ 'Name' => 'First' ] ] ],
                    ]),
                ];
            }

            \PHPUnit\Framework\Assert::assertStringContainsString('rec2', $url);
            \PHPUnit\Framework\Assert::assertStringNotContainsString('rec1', $url);

            return [
                'response' => [ 'code' => 200 ],
                'body'     => json_encode([
                    'records' => [ [ 'id' => 'rec2', 'fields' => [ 'Name' => 'Second' ] ] ],
                ]),
            ];
        });

        $first  = TTP_Airbase::resolve_linked_records('Vendors6', ['rec1']);
        $second = TTP_Airbase::resolve_linked_records('Vendors6', ['rec1', 'rec2']);

        $this->assertSame(['First'], $first);
        $this->assertSame(['First', 'Second'], $second);
    }

    public function test_get_table_schema_fetches_and_caches_when_missing() {
        when('get_transient')->alias(function ($key) {
            return false;
        });
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

        $body = json_encode([
            'tables' => [
                [
                    'id' => 'tblXYZ',
                    'name' => 'Products',
                    'fields' => [ [ 'name' => 'Name', 'id' => 'fldName' ] ],
                ],
            ],
        ]);

        expect('wp_remote_get')->once()->andReturn([
            'response' => [ 'code' => 200 ],
            'body'     => $body,
        ]);

        $self = $this;
        expect('set_transient')->once()->andReturnUsing(function ($key, $value, $ttl) use ($self) {
            $self->assertSame('ttp_airbase_schema', $key);
            $self->assertSame(DAY_IN_SECONDS, $ttl);
            $self->assertSame([ 'Name' => 'fldName' ], $value['tblXYZ']);
            return true;
        });

        $schema = TTP_Airbase::get_table_schema('tblXYZ');
        $this->assertSame([ 'Name' => 'fldName' ], $schema);
    }
}
