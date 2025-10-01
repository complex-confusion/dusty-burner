<?php
/**
 * Plugin Name: LunaCode Display Dust Data
 * Description: Display camps, art, schedule, and music from the Dust API
 * Version: 0.2.0
 * Author: Complex Confusion
 * License: GPL2
 * Text Domain: lunacode-display-dust-data
 */

namespace LunaCode;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DisplayDustData {
    const PLUGIN_VERSION = '0.2.0';
    const IMAGE_BASE_URL = 'https://data.dust.events/';
    const API_BASE_URL = 'https://data.dust.events/';

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('dust_camps', array($this, 'display_camps_shortcode'));
        add_shortcode('dust_art', array($this, 'display_art_shortcode'));
        add_shortcode('dust_schedule', array($this, 'display_schedule_shortcode'));
        add_shortcode('dust_music', array($this, 'display_music_shortcode'));
        add_shortcode('dust_schedule_ics_button', array($this, 'display_ics_button_shortcode'));

        // Add admin menu for settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));

        // AJAX handlers - public read-only data
        add_action('wp_ajax_get_dust_data', array($this, 'ajax_get_data'));
        add_action('wp_ajax_nopriv_get_dust_data', array($this, 'ajax_get_data'));
        add_action('wp_ajax_export_schedule_ics', array($this, 'export_schedule_ics'));
        add_action('wp_ajax_nopriv_export_schedule_ics', array($this, 'export_schedule_ics'));
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
        wp_enqueue_script('dust-events-js', plugin_dir_url(__FILE__) . 'lunacode-display-dust-data.js', array('jquery'), self::PLUGIN_VERSION, true);
        wp_enqueue_script('dust-ics-export', plugin_dir_url(__FILE__) . 'schedule-ics-button.js', array('jquery'), self::PLUGIN_VERSION, true);
        wp_enqueue_style('dust-events-css', plugin_dir_url(__FILE__) . 'lunacode-display-dust-data.css', array(), self::PLUGIN_VERSION);

        wp_localize_script('dust-events-js', 'dust_events_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dust_events_nonce')
        ));
    }

    public function add_admin_menu() {
        add_options_page(
            'LunaCode Display Dust Data Settings',
            'LunaCode Display Dust Data',
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
        echo 'Enter Dust\'s unique name for your regional burn:';
    }

    public function event_name_render() {
        $event_name = get_option('dust_events_event_name');
        echo '<input type="text" name="dust_events_event_name" value="' . esc_attr($event_name) . '" />';
        echo '<p class="description">This is the unique name used to register your regional burn in Dust.</p>';
    }

    public function options_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <form action='options.php' method='post'>
            <h2>LunaCode Display Dust Data Settings</h2>
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
    public static function get_data($type, $event_name = null) {
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

        $api_url = self::API_BASE_URL . $event_name . '/' . $type . '.json';

        // Check for cached data (cache for 1 hour)
        $cache_key = 'dust_' . $type . '_' . md5($event_name);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // Fetch data from API
        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'user-agent' => 'LunaCode Display Dust Data WordPress Plugin'
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
        self::sort_data($data, $type);

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
    private static function sort_data(&$data, $type) {
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
    private static function parse_pin_coordinates($pin_string) {
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
    private static function get_image_url($image_path) {
        if (empty($image_path)) {
            return null;
        }

        if (strpos($image_path, '://') !== false) {
            return $image_path;
        }

        return self::IMAGE_BASE_URL . $image_path;
    }

    /**
     * Format description as paragraphs
     *
     * @param string $description
     * @return string
     */
    private static function format_description($description) {
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
        $data = self::get_data($type);

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
     * Shortcode for ICS export button
     */
    public function display_ics_button_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'text' => '',
            'event_name' => ''
        ), $atts, 'dust_schedule_ics_button');

        return self::render_ics_button($atts['event_name'], $atts['text']);
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
            'show_images' => 'true',
            'show_export_button' => 'false'
        ), $atts, $tag);

        $event_name = !empty($atts['event_name']) ? $atts['event_name'] : get_option('dust_events_event_name');

        if (empty($event_name)) {
            return '<p>Please configure the event name in the plugin settings.</p>';
        }

        $data = self::get_data($type, $event_name);

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

        // Show export button for schedule if requested
        if ($type === 'schedule' && $atts['show_export_button'] === 'true') {
            echo self::render_ics_button();
        }

        self::render_data($data, $atts, $type);
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
    private static function render_data($data, $options = array(), $type = 'camps') {
        $layout = isset($options['layout']) ? $options['layout'] : 'grid';
        $show_coordinates = isset($options['show_coordinates']) && $options['show_coordinates'] === 'true';
        $show_images = isset($options['show_images']) && $options['show_images'] === 'true';

        echo '<div class="dust-' . esc_attr($type) . '-container dust-' . esc_attr($type) . '-' . esc_attr($layout) . '">';

        foreach ($data as $item) {
            self::render_single_item($item, $show_coordinates, $show_images, $type);
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
    private static function render_single_item($item, $show_coordinates = true, $show_images = true, $type = 'camps') {
        echo '<div class="dust-' . esc_attr($type) . '-item" data-uid="' . esc_attr($item['uid']) . '">';

        if ($type === 'camps' || $type === 'art') {
            self::render_camps_art($item, $show_coordinates, $show_images, $type);
        } elseif ($type === 'schedule') {
            self::render_schedule($item, $show_images);
        } elseif ($type === 'music') {
            self::render_music($item);
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
    private static function render_camps_art($item, $show_coordinates, $show_images, $type) {
        $name = $item['name'];
        $description = self::format_description($item['description']);

        // Handle images differently for art vs camps
        $image_url = null;
        if ($show_images) {
            if ($type === 'art' && isset($item['images']) && !empty($item['images'])) {
                $image_url = $item['images'][0]['thumbnail_url'];
            } elseif (isset($item['imageUrl'])) {
                $image_url = self::get_image_url($item['imageUrl']);
            }
        }

        $pin_data = isset($item['pin']) ? self::parse_pin_coordinates($item['pin']) : null;

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
    private static function render_schedule($item, $show_images) {
        $title = $item['title'];
        $description = self::format_description($item['description']);
        $camp = isset($item['camp']) ? $item['camp'] : '';
        $location = isset($item['location']) ? $item['location'] : '';
        $day = isset($item['day']) ? $item['day'] : '';
        $occurrence = isset($item['occurrence']) ? $item['occurrence'] : array();

        $image_url = $show_images && isset($item['imageUrl']) ? self::get_image_url($item['imageUrl']) : null;

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
    private static function render_music($item) {
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
     * Export schedule as ICS file
     */
    public function export_schedule_ics() {
        check_ajax_referer('dust_events_nonce', 'nonce');

        $event_name = sanitize_text_field($_POST['event_name'] ?? get_option('dust_events_event_name'));
        $data = self::get_data('schedule', $event_name);

        if (is_wp_error($data)) {
            wp_die('Error loading schedule data');
        }

        $ics_content = $this->generate_ics($data, $event_name);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $event_name . '-schedule.ics"');
        echo $ics_content;
        wp_die();
    }

    private function generate_ics($schedule_data, $event_name) {
        $timezone_info = $this->get_event_timezone($event_name);

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//LunaCode Display Dust Data//Schedule Export//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";

        // Add timezone definition
        $ics .= implode("\r\n", $timezone_info['vtimezone']) . "\r\n";

        foreach ($schedule_data as $event) {
            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:" . ($event['uid'] ?? uniqid()) . "@dust.events\r\n";
            $ics .= "SUMMARY:" . $this->escape_ics($event['title'] ?? '') . "\r\n";

            if (!empty($event['description'])) {
                $ics .= "DESCRIPTION:" . $this->escape_ics($event['description']) . "\r\n";
            }

            if (!empty($event['location'])) {
                $ics .= "LOCATION:" . $this->escape_ics($event['location']) . "\r\n";
            }

            // Handle time format from Dust API
            $start_time = $this->parse_dust_time($event, $event_name);
            $end_time = $this->parse_dust_end_time($event, $event_name);

            if ($start_time) {
                $ics .= "DTSTART;TZID=" . $timezone_info['id'] . ":" . $start_time . "\r\n";
                if ($end_time) {
                    $ics .= "DTEND;TZID=" . $timezone_info['id'] . ":" . $end_time . "\r\n";
                } else {
                    // Default 1 hour duration if no end time
                    $dt = \DateTime::createFromFormat('Ymd\THis', $start_time);
                    $dt->add(new DateInterval('PT1H'));
                    $ics .= "DTEND;TZID=" . $timezone_info['id'] . ":" . $dt->format('Ymd\THis') . "\r\n";
                }
            }

            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }

    /**
     * Return the event's timezone details
     * Returns a static EDT timezone for now - will be dynamic based on event in future.
     * // TODO make dynamic
     *
     * @param string|null $event_name
     * @return array
     */
    private function get_event_timezone($event_name = null) {
        return array(
            'id' => 'America/New_York',
            'vtimezone' => array(
                "BEGIN:VTIMEZONE",
                "TZID:America/New_York",
                "BEGIN:DAYLIGHT",
                "TZOFFSETFROM:-0500",
                "TZOFFSETTO:-0400",
                "TZNAME:EDT",
                "DTSTART:20070311T020000",
                "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU",
                "END:DAYLIGHT",
                "BEGIN:STANDARD",
                "TZOFFSETFROM:-0400",
                "TZOFFSETTO:-0500",
                "TZNAME:EST",
                "DTSTART:20071104T020000",
                "RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU",
                "END:STANDARD",
                "END:VTIMEZONE"
            )
        );
    }

    private function parse_dust_time($event, $event_name = null) {
        if (isset($event['occurrence']['start_time'])) {
            $timezone_info = $this->get_event_timezone($event_name);
            $dt = new \DateTime($event['occurrence']['start_time'], new \DateTimeZone($timezone_info['id']));
            return $dt->format('Ymd\THis');
        }
        return null;
    }

    private function parse_dust_end_time($event, $event_name = null) {
        if (isset($event['occurrence']['end_time'])) {
            $timezone_info = $this->get_event_timezone($event_name);
            $dt = new \DateTime($event['occurrence']['end_time'], new \DateTimeZone($timezone_info['id']));
            return $dt->format('Ymd\THis');
        }
        return null;
    }

    private function escape_ics($text) {
        return str_replace(["\n", "\r", ",", ";"], ["\\n", "", "\\,", "\\;"], strip_tags($text));
    }

    /**
     * Render ICS export button
     *
     * @param string $event_name Event name
     * @param string|null $text Button text
     * @return string HTML button
     */
    public static function render_ics_button($event_name = '', $text = '' ) {
        $event_name = $event_name ?: get_option('dust_events_event_name');
        $text = $text ?: '📅 Export Schedule to Calendar';

        $escaped = array(
            'event_name' => esc_attr($event_name),
            'text' => \esc_html($text),
        );
        return "<button class=\"dust-ics-export-btn\" data-event=\"{$escaped['event_name']}\">{$escaped['text']}</button>";
    }

    /**
     * Get data for use in themes/other plugins
     *
     * @param string $type 'camps'|'art'|'schedule'|'music'
     * @param string|null $event_name
     * @return array
     */
    public static function get_dust_data($type, $event_name = null) {
        return self::get_data($type, $event_name);
    }
}

// Initialize the plugin
new DisplayDustData();

// Template functions for theme developers (global namespace)

/**
 * For a theme: Get array of Burn items - camps, art, events, or music
 * @param string $type 'camps'|'art'|'schedule'|'music'
 * @param string|null $event_name
 * @return array
 */
function lunacode_display_dust_data_get($type, $event_name = null) {
    return \LunaCode\DisplayDustData::get_dust_data($type, $event_name);
}

/**
 * Output an entire list of Burn items as HTML
 *
 * @param array[] $data Array of item arrays
 * @param array $options
 * @param string $type 'camps'|'art'|'schedule'|'music'
 * @return void
 */
function lunacode_display_dust_data_render($type, $event_name = null, $options = array()) {
    $data = lunacode_display_dust_data_get($type, $event_name);
    if (!is_wp_error($data) && !empty($data)) {
        \LunaCode\DisplayDustData::render_data($data, $options, $type);
    }
}

/**
 * Render ICS export button
 *
 * @param string $event_name Event name
 * @param string $text Button text
 * @return string HTML button
 */
function dust_schedule_ics_button($event_name = '', $text = '') {
    return \LunaCode\DisplayDustData::render_ics_button($event_name, $text);
}
