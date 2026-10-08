<?php

class Test_DisplayDustData_Bootstrap extends WP_UnitTestCase {
    public function test_plugin_loads_and_uses_the_wordpress_http_api(): void {
        $event_name = 'phpunit-bootstrap-event';
        $cache_key = 'dust_camps_' . md5($event_name);
        $option_name = 'dust_events_event_name';
        $had_event_option = metadata_exists('option', $option_name);
        $previous_event_name = get_option($option_name);
        $requests = array();
        $response_filter = static function($preempt, $args, $url) use (&$requests) {
            $requests[] = $url;

            return array(
                'headers' => array(),
                'body' => wp_json_encode(array(array('name' => 'Integration Camp'))),
                'response' => array(
                    'code' => 200,
                    'message' => 'OK',
                ),
                'cookies' => array(),
            );
        };

        delete_transient($cache_key);
        add_filter('pre_http_request', $response_filter, 10, 3);

        try {
            update_option($option_name, $event_name);

            $data = LunaCode\DisplayDustData::get_data('camps');

            $this->assertSame(
                array('https://data.dust.events/' . $event_name . '/camps.json'),
                $requests
            );
            $this->assertSame(array(array('name' => 'Integration Camp')), $data);
        } finally {
            remove_filter('pre_http_request', $response_filter, 10);
            delete_transient($cache_key);

            if ($had_event_option) {
                update_option($option_name, $previous_event_name);
            } else {
                delete_option($option_name);
            }
        }
    }
}
