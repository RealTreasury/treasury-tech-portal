<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

require_once __DIR__ . '/../includes/class-ttp-data.php';

class TTP_Admin_Notice_Test extends TestCase {
    protected function setUp(): void {
        \Brain\Monkey\setUp();
        when('esc_html_e')->alias(function ($text) { echo $text; });
        when('esc_attr_e')->alias(function ($text) { echo $text; });
        when('esc_html__')->alias(function ($text) { return $text; });
        when('esc_attr__')->alias(function ($text) { return $text; });
        when('esc_html')->returnArg();
        when('esc_attr')->returnArg();
        when('esc_url')->returnArg();
        when('wp_nonce_field')->alias(function () {});
        when('submit_button')->alias(function () {});
        when('admin_url')->alias(function ($path = '') { return $path; });
        when('sanitize_text_field')->returnArg();
    }

    protected function tearDown(): void {
        \Patchwork\restoreAll();
        \Brain\Monkey\tearDown();
    }

    public function test_warning_icon_displayed_for_unresolved_ids() {
        $vendors = [
            [
                'name'    => 'Vendor',
                'regions' => ['rec123abc']
            ]
        ];
        $unresolved_fields = [];

        ob_start();
        include __DIR__ . '/../templates/admin-page.php';
        $html = ob_get_clean();

        $this->assertStringContainsString('ttp-warning-icon', $html);
        $this->assertStringContainsString('Vendor data may be incomplete', $html);
    }
}
