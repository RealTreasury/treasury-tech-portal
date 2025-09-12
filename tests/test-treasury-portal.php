<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-treasury-portal.php';

class Treasury_Portal_Shortcode_Test extends TestCase {
    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when( 'esc_html__' )->alias( fn( $text, $domain = 'default' ) => $text );
        when( 'plugin_dir_path' )->alias( fn( $file ) => __DIR__ . '/fixtures/' );
        when( 'error_log' )->alias( fn( $msg ) => null );
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
        \Brain\Monkey\tearDown();
    }

    public function test_shortcode_handler_skips_enqueue_on_include_error() {
        $enqueued = false;
        \Patchwork\replace( 'Treasury_Tech_Portal::enqueue_assets', function() use ( &$enqueued ) {
            $enqueued = true;
        } );

        $ref = new ReflectionClass( Treasury_Tech_Portal::class );
        $portal = $ref->newInstanceWithoutConstructor();

        $output = $portal->shortcode_handler();

        $this->assertFalse( $enqueued, 'Assets should not be enqueued when shortcode include fails.' );
        $this->assertStringContainsString( 'treasury-portal-error', $output );
    }

    public function test_shortcode_handler_skips_enqueue_when_portal_missing() {
        $enqueued = false;
        \Patchwork\replace( 'Treasury_Tech_Portal::enqueue_assets', function() use ( &$enqueued ) {
            $enqueued = true;
        } );

        // Point the shortcode include to a template without the portal element.
        when( 'plugin_dir_path' )->alias( fn( $file ) => __DIR__ . '/fixtures/no-portal/' );

        $ref    = new ReflectionClass( Treasury_Tech_Portal::class );
        $portal = $ref->newInstanceWithoutConstructor();

        $output = $portal->shortcode_handler();

        $this->assertFalse( $enqueued, 'Assets should not be enqueued when .treasury-portal is missing.' );
        $this->assertStringContainsString( 'No portal here', $output );
    }
}
