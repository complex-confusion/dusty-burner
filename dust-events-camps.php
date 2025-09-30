<?php
/**
 * Plugin Name: Dust Events Display
 * Description: Display camps, art, schedule, and music from Dust Events API
 * Version: 2.0.0
 * Author: Complex Confusion
 * License: GPL2
 * Text Domain: dust-events-camps
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DustEvents {

    private $api_base_url = 'https://data.dust.events/';
    private $image_base_url = 'https://data.dust.events/';

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('dust_camps', array($this, 'display_camps_shortcode'));
        add_shortcode('dust_art', array($this, 'display_art_shortcode'));
        add_shortcode('dust_schedule', array($this, 'display_schedule_shortcode'));
        add_shortcode('dust_music', array($this, 'display_music_shortcode'));

        // Add admin menu for settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));

        // AJAX handlers - public read-only data
        add_action('wp_ajax_get_dust_data', array($this, 'ajax_get_data'));
        add_action('wp_ajax_nopriv_get_dust_data', array($this, 'ajax_get_data'));
    }

    public function init() {
        // Register custom post types for caching only if needed
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            $types = array('camp', 'art', 'schedule', 'music');
            foreach ($types as $type) {
                register_post_type('dust_' . $type, array(
                    'labels' => array(
                        'name' => 'Dust ' . ucfirst($type),
                        'singular_name' => 'Dust ' . ucfirst($type)
                    ),
                    'public' => false,
                    'show_in_admin' => true,
                    'supports' => array('title', 'editor', 'thumbnail', 'custom-fields')
                ));
            }
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('dust-events-js', plugin_dir_url(__FILE__) . 'camps.js', array('jquery'), '2.0.0', true);
        wp_enqueue_style('dust-events-css', plugin_dir_url(__FILE__) . 'camps.css', array(), '2.0.0');

        // Localize script for AJAX
        wp_localize_script('dust-events-js', 'dust_events_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dust_events_nonce')
        ));
    }

    public function add_admin_menu() {
        add_options_page(
            'Dust Events Settings',
            'Dust Events',
            'manage_options',
            'dust-events',
            array($this, 'options_page')
        );
    }

    public function settings_init() {
        register_setting('dust_events', 'dust_events_event_name');

        add_settings_section(
            'dust_events_section',
            'API Configuration',
            array($this, 'settings_section_callback'),
            'dust_events'
        );

        add_settings_field(
            'dust_events_event_name',
            'Event Name (your-unique-name)',
            array($this, 'event_name_render'),
            'dust_events',
            'dust_events_section'
        );
    }

    public function settings_section_callback() {
        echo 'Enter your unique event name from Dust Events:';
    }

    public function event_name_render() {
        $event_name = get_option('dust_events_event_name');
        echo '<input type="text" name="dust_events_event_name" value="' . esc_attr($event_name) . '" />';
        echo '<p class="description">This is the unique name shown when you edit the event in Dust Events.</p>';
    }

    public function options_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <form action='options.php' method='post'>
            <h2>Dust Events Settings</h2>
            <?php
            settings_fields('dust_events');
            do_settings_sections('dust_events');
            submit_button();
            ?>
        </form>
        <?php
    }

    /**
     * Fetch data from API
     *
     * @param string $type 'camps'|'art'|'schedule'|'music'
     * @param string|null $event_name
     * @return array
     */
    public function get_data($type, $event_name = null) {
        // Validate type parameter
        $allowed_types = array('camps', 'art', 'schedule', 'music');
        if (!in_array($type, $allowed_types, true)) {
            return new WP_Error('invalid_type', 'Invalid API endpoint specified');
        }

        if (!$event_name) {
            $event_name = get_option('dust_events_event_name');
        }

        if (!$event_name) {
            return new WP_Error('no_event_name', 'No event name configured');
        }

        $api_url = $this->api_base_url . $event_name . '/' . $type . '.json';

        // Check for cached data (cache for 1 hour)
        $cache_key = 'dust_' . $type . '_' . md5($event_name);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Fetch data from API
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'user-agent' => 'WordPress Dust Events Plugin'
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

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response');
        }

        // Sort data based on type
        $this->sort_data($data, $type);

        // Cache the data
        set_transient($cache_key, $data, HOUR_IN_SECONDS);

        return $data;
    }

    /**
     * Sort data according to its type:
     * camps and art get sorted by their name;
     * schedule (events) and music get sorted by their camp and then their name
     *
     * @param array $data
     * @param string $type 'camps'|'art'|'schedule'|'music'
     * @return void
     */
    private function sort_data(&$data, $type) {
        if ($type === 'camps' || $type === 'art') {
            // Sort by name
            uasort($data, function($a, $b) {
                $name_a = (string)($a['name'] ?? '');
                $name_b = (string)($b['name'] ?? '');
                return strcmp($name_a, $name_b);
            });
        } elseif ($type === 'schedule' || $type === 'music') {
            // Sort by camp, then by title
            uasort($data, function($a, $b) {
                $camp_a = (string)($a['camp'] ?? $a['hosted_by_camp'] ?? '');
                $camp_b = (string)($b['camp'] ?? $b['hosted_by_camp'] ?? '');
                $camp_cmp = strcmp($camp_a, $camp_b);
                if ($camp_cmp === 0) {
                    $title_a = (string)($a['title'] ?? '');
                    $title_b = (string)($b['title'] ?? '');
                    return strcmp($title_a, $title_b);
                }
                return $camp_cmp;
            });
        }
    }

    /**
     * Get location on the map as array of coordinates
     *
     * @param string $pin_string
     * @return array|null
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
     *
     * Converts relative image paths to absolute URLs by prepending the base URL.
     * Prevents duplicate base URLs from caching or multiple processing
     * by checking if the path is already a complete URL.
     *
     * @param string $image_path Relative path or complete URL
     * @return string|null Complete image URL or null if empty
     */
    private function get_image_url($image_path) {
        if (empty($image_path)) {
            return null;
        }

        if (strpos($image_path, '://') !== false) {
            return $image_path;
        }

        return $this->image_base_url . $image_path;
    }

    /**
     * Format description as paragraphs
     *
     * @param string $description
     * @return string
     */
    private function format_description($description) {
        if (empty($description)) {
            return '';
        }

        $paragraphs = explode("\n", trim($description));
        $formatted_paragraphs = array();

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                $formatted_paragraphs[] = '<p>' . wp_kses_post($paragraph) . '</p>';
            }
        }

        return implode('', $formatted_paragraphs);
    }

    /**
     * AJAX handler for getting data
     *
     * @return void
     */
    public function ajax_get_data() {
        check_ajax_referer('dust_events_nonce', 'nonce');

        $type = sanitize_text_field($_POST['type']);
        $data = $this->get_data($type);

        if (is_wp_error($data)) {
            wp_send_json_error($data->get_error_message());
        }

        wp_send_json_success($data);
    }

    /**
     * Shortcode handlers for each data type
     */
    public function display_camps_shortcode($atts, $content = null) {
        return $this->display_shortcode($atts, $content, 'dust_camps', 'camps');
    }

    public function display_art_shortcode($atts, $content = null) {
        return $this->display_shortcode($atts, $content, 'dust_art', 'art');
    }

    public function display_schedule_shortcode($atts, $content = null) {
        return $this->display_shortcode($atts, $content, 'dust_schedule', 'schedule');
    }

    public function display_music_shortcode($atts, $content = null) {
        return $this->display_shortcode($atts, $content, 'dust_music', 'music');
    }

    /**
     * Allow a shortcode to display data
     * Usage: [dust_camps], [dust_art], [dust_schedule], [dust_music]
     *
     * @param array $atts Shortcode attributes
     * @param string|null $content Shortcode content
     * @param string $tag Shortcode tag name
     * @return string HTML output
     */
    private function display_shortcode($atts, $content = null, $tag = '', $type = '') {
        $atts = shortcode_atts(array(
            'event_name' => '',
            'layout' => 'grid',
            'show_coordinates' => 'true',
            'per_page' => -1,
            'show_images' => 'true'
        ), $atts, $tag);

        $event_name = !empty($atts['event_name']) ? $atts['event_name'] : get_option('dust_events_event_name');

        if (empty($event_name)) {
            return '<p>Please configure the event name in the plugin settings.</p>';
        }

        $data = $this->get_data($type, $event_name);

        if (is_wp_error($data)) {
            return '<p>Error loading ' . $type . ' data: ' . esc_html($data->get_error_message()) . '</p>';
        }

        if (empty($data)) {
            return '<p>No ' . $type . ' found.</p>';
        }

        // Limit results if specified
        if ($atts['per_page'] > 0) {
            $data = array_slice($data, 0, intval($atts['per_page']));
        }

        ob_start();
        $this->render_data($data, $atts, $type);
        return ob_get_clean();
    }

    /**
     * Output an entire list of Burn items as HTML
     *
     * @param array[] $data Array of item arrays
     * @param array $options
     * @param string $type 'camps'|'art'|'schedule'|'music'
     * @return void
     */
    private function render_data($data, $options = array(), $type = 'camps') {
        $layout = isset($options['layout']) ? $options['layout'] : 'grid';
        $show_coordinates = isset($options['show_coordinates']) && $options['show_coordinates'] === 'true';
        $show_images = isset($options['show_images']) && $options['show_images'] === 'true';

        echo '<div class="dust-' . esc_attr($type) . '-container dust-' . esc_attr($type) . '-' . esc_attr($layout) . '">';

        foreach ($data as $item) {
            $this->render_single_item($item, $show_coordinates, $show_images, $type);
        }

        echo '</div>';
    }

    /**
     * Render single item
     *
     * @param array $item
     * @param boolean $show_coordinates
     * @param boolean $show_images
     * @param string $type
     * @return void
     */
    private function render_single_item($item, $show_coordinates = true, $show_images = true, $type = 'camps') {
        echo '<div class="dust-' . esc_attr($type) . '-item" data-uid="' . esc_attr($item['uid']) . '">';

        if ($type === 'camps' || $type === 'art') {
            $this->render_camps_art($item, $show_coordinates, $show_images, $type);
        } elseif ($type === 'schedule') {
            $this->render_schedule($item, $show_images);
        } elseif ($type === 'music') {
            $this->render_music($item);
        }

        echo '</div>';
    }

    /**
     * Output either camps or art as HTML
     *
     * @param array $item
     * @param bool $show_coordinates
     * @param bool $show_images
     * @param string $type 'camps'|'art'
     * @return void
     */
    private function render_camps_art($item, $show_coordinates, $show_images, $type) {
        $name = $item['name'];
        $description = $this->format_description($item['description']);

        // Handle images differently for art vs camps
        $image_url = null;
        if ($show_images) {
            if ($type === 'art' && isset($item['images']) && !empty($item['images'])) {
                $image_url = $item['images'][0]['thumbnail_url'];
            } elseif (isset($item['imageUrl'])) {
                $image_url = $this->get_image_url($item['imageUrl']);
            }
        }

        $pin_data = isset($item['pin']) ? $this->parse_pin_coordinates($item['pin']) : null;

        if ($image_url) {
            echo '<div class="dust-' . esc_attr($type) . '-image">';
            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($name) . '" loading="lazy" />';
            echo '</div>';
        }

        echo '<div class="dust-' . esc_attr($type) . '-content">';
        echo '<h3 class="dust-' . esc_attr($type) . '-name">' . esc_html($name) . '</h3>';

        if ($type === 'art' && isset($item['artist'])) {
            echo '<div class="dust-art-artist"><strong>Artist:</strong> ' . esc_html($item['artist']) . '</div>';
        }

        if (!empty($description)) {
            echo '<div class="dust-' . esc_attr($type) . '-description">' . $description . '</div>';
        }

        if ($show_coordinates && $pin_data) {
            echo '<div class="dust-' . esc_attr($type) . '-coordinates"><strong>Coordinates:</strong> ';
            if (isset($pin_data['lat'], $pin_data['lng'])) {
                echo 'GPS - Lat: ' . esc_html($pin_data['lat']) . ', Lng: ' . esc_html($pin_data['lng']);
            } elseif (isset($pin_data['x'], $pin_data['y'])) {
                echo 'Map Position - X: ' . esc_html($pin_data['x']) . ', Y: ' . esc_html($pin_data['y']);
            }
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Output schedule items (events) as HTML
     *
     * @param array $item
     * @param bool $show_images
     * @return void
     */
    private function render_schedule($item, $show_images) {
        $title = $item['title'];
        $description = $this->format_description($item['description']);
        $camp = isset($item['camp']) ? $item['camp'] : '';
        $location = isset($item['location']) ? $item['location'] : '';
        $day = isset($item['day']) ? $item['day'] : '';
        $occurrence = isset($item['occurrence']) ? $item['occurrence'] : array();

        $image_url = $show_images && isset($item['imageUrl']) ? $this->get_image_url($item['imageUrl']) : null;

        if ($image_url) {
            echo '<div class="dust-schedule-image">';
            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" loading="lazy" />';
            echo '</div>';
        }

        echo '<div class="dust-schedule-content">';
        echo '<h3 class="dust-schedule-title">' . esc_html($title) . '</h3>';

        if ($camp) echo '<div class="dust-schedule-camp"><strong>Camp:</strong> ' . esc_html($camp) . '</div>';
        if ($location) echo '<div class="dust-schedule-location"><strong>Location:</strong> ' . esc_html($location) . '</div>';
        if ($day) echo '<div class="dust-schedule-day"><strong>Day:</strong> ' . esc_html($day) . '</div>';

        if (isset($occurrence['long'])) {
            echo '<div class="dust-schedule-time"><strong>Time:</strong> ' . esc_html($occurrence['long']) . '</div>';
        }

        if (!empty($description)) {
            echo '<div class="dust-schedule-description">' . $description . '</div>';
        }
        echo '</div>';
    }

    /**
     * Output Music items (musical events or "parties") as HTML
     *
     * @param array $item
     * @return void
     */
    private function render_music($item) {
        $title = $item['title'];
        $camp = isset($item['camp']) ? $item['camp'] : '';
        $location = isset($item['location']) ? $item['location'] : '';
        $day = isset($item['day']) ? $item['day'] : '';
        $occurrence = isset($item['occurrence']) ? $item['occurrence'] : array();

        echo '<div class="dust-music-content">';
        echo '<h3 class="dust-music-title">' . esc_html($title) . '</h3>';

        if ($camp) echo '<div class="dust-music-camp"><strong>Camp:</strong> ' . esc_html($camp) . '</div>';
        if ($location) echo '<div class="dust-music-location"><strong>Location:</strong> ' . esc_html($location) . '</div>';
        if ($day) echo '<div class="dust-music-day"><strong>Day:</strong> ' . esc_html($day) . '</div>';

        if (isset($occurrence['who'])) {
            echo '<div class="dust-music-who"><strong>Artist:</strong> ' . esc_html($occurrence['who']) . '</div>';
        }

        if (isset($occurrence['long'])) {
            echo '<div class="dust-music-time"><strong>Time:</strong> ' . esc_html($occurrence['long']) . '</div>';
        }
        echo '</div>';
    }

    /**
     */
    /**
     * Get data for use in themes/other plugins
     *
     * @param string $type 'camps'|'art'|'schedule'|'music'
     * @param string|null $event_name
     * @return array
     */
    public static function get_dust_data($type, $event_name = null) {
        $instance = new self();
        return $instance->get_data($type, $event_name);
    }
}

// Initialize the plugin
new DustEvents();

// Template functions for theme developers

/**
 * For a theme: Get array of Burn items - camps, art, events, or music
 * @param string $type 'camps'|'art'|'schedule'|'music'
 * @param string|null $event_name
 * @return array
 */
function dust_get_data($type, $event_name = null) {
    return DustEvents::get_dust_data($type, $event_name);
}

/**
 * Output an entire list of Burn items as HTML
 *
 * @param array[] $data Array of item arrays
 * @param array $options
 * @param string $type 'camps'|'art'|'schedule'|'music'
 * @return void
 */
function dust_display_data($type, $event_name = null, $options = array()) {
    $data = dust_get_data($type, $event_name);
    if (!is_wp_error($data) && !empty($data)) {
        $instance = new DustEvents();
        $instance->render_data($data, $options, $type);
    }
}
?>