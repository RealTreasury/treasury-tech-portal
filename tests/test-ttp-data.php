<?php
class TTP_Data_Test extends WP_UnitTestCase {
    public function test_get_all_tools_returns_array() {
        $tools = \TreasuryTechPortal\TTP_Data::get_all_tools();
        $this->assertIsArray($tools);
    }

    public function test_save_tools_updates_option() {
        $test_tools = [
            ['name' => 'Test Tool', 'category' => 'CASH']
        ];

        \TreasuryTechPortal\TTP_Data::save_tools($test_tools);
        $saved_tools = get_option('ttp_tools');

        $this->assertEquals($test_tools, $saved_tools);
    }
}
