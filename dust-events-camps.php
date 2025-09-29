<?php
/**
 * Plugin Name: Dust Events Camps Display
 * Description: Display camps from Dust Events API with name, description, image, and coordinates
 * Version: 1.0.0
 * Author: Complex Confusion
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DustEventsCamps {

    private $api_base_url = 'https://data.dust.events/';
    private $image_base_url = 'https://data.dust.events/';

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('dust_camps', array($this, 'display_camps_shortcode'));

        // Add admin menu for settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));

        // AJAX handlers
        add_action('wp_ajax_get_camps_data', array($this, 'ajax_get_camps_data'));
        add_action('wp_ajax_nopriv_get_camps_data', array($this, 'ajax_get_camps_data'));
    }

    public function init() {
        // Register custom post type for caching camps (optional)
        register_post_type('dust_camp', array(
            'labels' => array(
                'name' => 'Dust Camps',
                'singular_name' => 'Dust Camp'
            ),
            'public' => false,
            'show_in_admin' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields')
        ));
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('dust-camps-js', plugin_dir_url(__FILE__) . 'camps.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('dust-camps-css', plugin_dir_url(__FILE__) . 'camps.css', array(), '1.0.0');

        // Localize script for AJAX
        wp_localize_script('dust-camps-js', 'dust_camps_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dust_camps_nonce')
        ));
    }

    public function add_admin_menu() {
        add_options_page(
            'Dust Events Camps Settings',
            'Dust Events Camps',
            'manage_options',
            'dust-events-camps',
            array($this, 'options_page')
        );
    }

    public function settings_init() {
        register_setting('dust_camps', 'dust_camps_event_name');

        add_settings_section(
            'dust_camps_section',
            'API Configuration',
            array($this, 'settings_section_callback'),
            'dust_camps'
        );

        add_settings_field(
            'dust_camps_event_name',
            'Event Name (your-unique-name)',
            array($this, 'event_name_render'),
            'dust_camps',
            'dust_camps_section'
        );
    }

    public function settings_section_callback() {
        echo 'Enter your unique event name from Dust Events:';
    }

    public function event_name_render() {
        $event_name = get_option('dust_camps_event_name');
        echo '<input type="text" name="dust_camps_event_name" value="' . esc_attr($event_name) . '" />';
        echo '<p class="description">This is the unique name shown when you edit the event in Dust Events.</p>';
    }

    public function options_page() {
        ?>
        <form action='options.php' method='post'>
            <h2>Dust Events Camps Settings</h2>
            <?php
            settings_fields('dust_camps');
            do_settings_sections('dust_camps');
            submit_button();
            ?>
        </form>
        <?php
    }

    /**
     * Fetch camps data from API
     */
    public function get_camps_data($event_name = null) {
        if (!$event_name) {
            $event_name = get_option('dust_camps_event_name');
        }

        if (!$event_name) {
            return new WP_Error('no_event_name', 'No event name configured');
        }

        $api_url = $this->api_base_url . $event_name . '/camps.json';

        // Check for cached data (cache for 1 hour)
        $cache_key = 'dust_camps_' . md5($event_name);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Fetch data from API
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'user-agent' => 'WordPress Dust Camps Plugin'
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'API returned status code: ' . $response_code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        uasort($data, [DustEventsCamps::class, 'sort_data']);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response');
        }

        // Cache the data
        set_transient($cache_key, $data, HOUR_IN_SECONDS);

        return $data;
    }

    private function sort_data($a, $b) {
        return strcmp($a['name'], $b['name']);
    }

    /**
     * Parse pin coordinates
     */
    private function parse_pin_coordinates($pin_string) {
        if (empty($pin_string)) {
            return null;
        }

        $pin_data = json_decode($pin_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $pin_data;
    }

    /**
     * Get complete image URL
     */
    private function get_image_url($image_path) {
        if (empty($image_path)) {
            return null;
        }

        return $this->image_base_url . $image_path;
    }

    /**
     * AJAX handler for getting camps data
     */
    public function ajax_get_camps_data() {
        check_ajax_referer('dust_camps_nonce', 'nonce');

        $camps_data = $this->get_camps_data();

        if (is_wp_error($camps_data)) {
            wp_send_json_error($camps_data->get_error_message());
        }

        wp_send_json_success($camps_data);
    }

    /**
     * Shortcode to display camps
     * Usage: [dust_camps event_name="your-event-name" layout="grid|list" show_coordinates="true|false"]
     */
    public function display_camps_shortcode($atts) {
        $atts = shortcode_atts(array(
            'event_name' => '',
            'layout' => 'grid',
            'show_coordinates' => 'true',
            'per_page' => -1,
            'show_images' => 'true'
        ), $atts, 'dust_camps');

        $event_name = !empty($atts['event_name']) ? $atts['event_name'] : get_option('dust_camps_event_name');

        if (empty($event_name)) {
            return '<p>Please configure the event name in the plugin settings.</p>';
        }

        $camps_data = $this->get_camps_data($event_name);

        if (is_wp_error($camps_data)) {
            return '<p>Error loading camps data: ' . esc_html($camps_data->get_error_message()) . '</p>';
        }

        if (empty($camps_data)) {
            return '<p>No camps found.</p>';
        }

        // Limit results if specified
        if ($atts['per_page'] > 0) {
            $camps_data = array_slice($camps_data, 0, intval($atts['per_page']));
        }

        ob_start();
        $this->render_camps($camps_data, $atts);
        return ob_get_clean();
    }

    /**
     * Render camps HTML
     */
    private function render_camps($camps_data, $options = array()) {
        $layout = isset($options['layout']) ? $options['layout'] : 'grid';
        $show_coordinates = isset($options['show_coordinates']) && $options['show_coordinates'] === 'true';
        $show_images = isset($options['show_images']) && $options['show_images'] === 'true';

        echo '<div class="dust-camps-container dust-camps-' . esc_attr($layout) . '">';

        foreach ($camps_data as $camp) {
            $this->render_single_camp($camp, $show_coordinates, $show_images);
        }

        echo '</div>';
    }

    /**
     * Render single camp
     */
    private function render_single_camp($camp, $show_coordinates = true, $show_images = true) {
        $name = esc_html($camp['name']);
        $description = wp_kses_post($camp['description']);
        $uid = esc_attr($camp['uid']);
        $image_url = $show_images ? $this->get_image_url($camp['imageUrl']) : null;
        $pin_data = $this->parse_pin_coordinates($camp['pin']);

        echo '<div class="dust-camp-item" data-uid="' . $uid . '">';

        // Image
        if ($image_url && $show_images) {
            echo '<div class="dust-camp-image">';
            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($name) . '" loading="lazy" />';
            echo '</div>';
        }

        // Content
        echo '<div class="dust-camp-content">';

        // Name
        echo '<h3 class="dust-camp-name">' . $name . '</h3>';

        // Description
        if (!empty($description)) {
            echo '<div class="dust-camp-description">' . $description . '</div>';
        }

        // Coordinates
        if ($show_coordinates && $pin_data) {
            echo '<div class="dust-camp-coordinates">';
            echo '<strong>Coordinates:</strong> ';

            if (isset($pin_data['lat'], $pin_data['lng'])) {
                echo 'GPS - Lat: ' . esc_html($pin_data['lat']) . ', Lng: ' . esc_html($pin_data['lng']);
                echo '<div class="dust-camp-map-link">';
                echo '<a href="https://www.google.com/maps?q=' . esc_attr($pin_data['lat']) . ',' . esc_attr($pin_data['lng']) . '" target="_blank">View on Google Maps</a>';
                echo '</div>';
            } elseif (isset($pin_data['x'], $pin_data['y'])) {
                echo 'Map Position - X: ' . esc_html($pin_data['x']) . ', Y: ' . esc_html($pin_data['y']);
            }

            echo '</div>';
        }

        echo '</div>'; // .dust-camp-content
        echo '</div>'; // .dust-camp-item
    }

    /**
     * Get camps for use in themes/other plugins
     */
    public static function get_camps($event_name = null) {
        $instance = new self();
        return $instance->get_camps_data($event_name);
    }
}

// Initialize the plugin
new DustEventsCamps();

// Template function for theme developers
function dust_get_camps($event_name = null) {
    return DustEventsCamps::get_camps($event_name);
}

function dust_display_camps($event_name = null, $options = array()) {
    $camps = dust_get_camps($event_name);
    if (!is_wp_error($camps) && !empty($camps)) {
        $instance = new DustEventsCamps();
        $instance->render_camps($camps, $options);
    }
}
?>