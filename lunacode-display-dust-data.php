<?php
/**
 * LunaCode Display Dust Data
 *
 * WordPress plugin for displaying Dust API data including camps, art, schedule, and music
 * from regional Burning Man events. Provides shortcodes and customizable layouts.
 *
 *
 * @wordpress-plugin
 * Plugin Name:       LunaCode Display Dust Data
 * Description:       Display camps, art, schedule, and music served by the Dust API with customizable shortcodes and layouts.
 * Version:           0.5.0
 * @version           0.5.0
 * Requires at least: 4.6
 * Requires PHP:      7.0
 * Author:            Complex Confusion
 * @author            Complex Confusion
 * Author URI:        https://lunacode.com
 * @link              https://lunacode.com
 * @package           LunaCode\DisplayDustData
 * @copyright         2025 LunaCode
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * @license           GPL-2.0-or-later
 * Text Domain:       lunacode-display-dust-data
 */

namespace LunaCode;

// WordPress functions
use function add_action, add_options_page, add_settings_field, add_settings_section, add_shortcode, admin_url,
    check_ajax_referer, current_user_can, do_settings_sections, esc_attr, esc_html, esc_url, get_option, get_transient,
    is_admin, is_user_logged_in, is_wp_error, plugin_dir_url, register_post_type, register_setting, sanitize_text_field, set_transient,
    settings_fields, shortcode_atts, submit_button, wp_create_nonce, wp_die, wp_enqueue_script, wp_enqueue_style,
    wp_kses_post, wp_localize_script, wp_remote_get, wp_remote_retrieve_body, wp_remote_retrieve_response_code,
    wp_send_json_error, wp_send_json_success;

// PHP functions
use function array_keys, array_slice, defined, explode, header, implode, in_array, intval, json_decode, json_last_error, md5,
    ob_get_clean, ob_start, str_replace, strcmp, strip_tags, strpos, trim, uasort, ucfirst, uniqid;

// Classes
use WP_Error, DateTime, DateInterval, DateTimeZone;

// Constants
use const ABSPATH, DOING_AJAX, HOUR_IN_SECONDS, JSON_ERROR_NONE;

// Prevent direct access
if (!defined('ABSPATH')) {
    wp_die('Direct access is not allowed. Return through the website; stop with the hackery.');
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
        add_shortcode('dust_schedule_csv_button', array($this, 'display_csv_button_shortcode'));
        add_shortcode('dust_schedule_morse_button', array($this, 'display_morse_button_shortcode'));

        // Add admin menu for settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));

        // AJAX handlers - public read-only data from Dust API (no authentication required)
        // @security-ignore CWE-285: These endpoints access public APIs that do not require authentication
        add_action('wp_ajax_get_dust_data', array($this, 'ajax_get_data'));
        add_action('wp_ajax_nopriv_get_dust_data', array($this, 'ajax_get_data'));
        add_action('wp_ajax_export_schedule_ics', array($this, 'export_schedule_ics'));
        add_action('wp_ajax_nopriv_export_schedule_ics', array($this, 'export_schedule_ics'));
        add_action('wp_ajax_export_schedule_csv', array($this, 'export_schedule_csv'));
        add_action('wp_ajax_nopriv_export_schedule_csv', array($this, 'export_schedule_csv'));
        add_action('wp_ajax_export_schedule_morse', array($this, 'export_schedule_morse'));
        add_action('wp_ajax_nopriv_export_schedule_morse', array($this, 'export_schedule_morse'));
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
     * @security-note This accesses the public Dust Events API which requires no authentication.
     * @security-ignore CWE-285: No authentication required for public API endpoints
     *
     * @param string $type 'camps'|'art'|'schedule'|'music'
     * @param string|null $event_name
     * @return array|\WP_Error
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
     * Format categories as comma-separated list
     *
     * @param array $item
     * @param string $type
     * @return string
     */
    private static function format_categories($item, $type) {
        $categories = array();

        if ($type === 'schedule' && isset($item['event_type']['label'])) {
            $categories[] = $item['event_type']['label'];
        } elseif ($type === 'camps' && isset($item['camp_type'])) {
            $categories[] = $item['camp_type'];
        } elseif ($type === 'art' && isset($item['art_type'])) {
            $categories[] = $item['art_type'];
        }

        return !empty($categories) ? implode(', ', $categories) : '';
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
     * Shortcode for CSV export button
     */
    public function display_csv_button_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'text' => '',
            'event_name' => ''
        ), $atts, 'dust_schedule_csv_button');

        return self::render_csv_button($atts['event_name'], $atts['text']);
    }

    /**
     * Shortcode for Morse export button
     */
    public function display_morse_button_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'text' => '',
            'event_name' => ''
        ), $atts, 'dust_schedule_morse_button');

        return self::render_morse_button($atts['event_name'], $atts['text']);
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
            'show_export_buttons' => 'false',
            'show_export_button' => 'false', // backwards compatibility
            'display' => 'false'
        ), $atts, $tag);

        // Backwards compatibility: if old parameter is used, use it
        if ($atts['show_export_button'] === 'true' && $atts['show_export_buttons'] === 'false') {
            $atts['show_export_buttons'] = 'true';
        }

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

        if ($type === 'schedule' && $atts['display'] === 'tabs') {
            self::render_schedule_tabs($data, $atts, $event_name);
        } else {
            // Show export buttons for schedule if requested
            if ($type === 'schedule') {
                $export_buttons = self::get_export_buttons($atts['show_export_buttons'], $event_name);
                if (!empty($export_buttons)) {
                    echo '<div class="lunacode-export-buttons">' . $export_buttons . '</div>';
                }
            }
            self::render_data($data, $atts, $type);
        }
        return ob_get_clean();
    }

    /**
     * Output an entire list of Burn items as HTML
     *
     * @param array[] $data Array of item arrays
     * @param array $options
     * @param string $type 'camps'|'art'|'schedule'|'music'
     * @param string $tab_context 'repeating' or 'day' for schedule tabs
     * @return void
     */
    public static function render_data($data, $options = array(), $type = 'camps', $tab_context = 'day') {
        $layout = isset($options['layout']) ? $options['layout'] : 'grid';
        $show_coordinates = isset($options['show_coordinates']) && $options['show_coordinates'] === 'true';
        $show_images = isset($options['show_images']) && $options['show_images'] === 'true';

        echo '<div class="dust-' . esc_attr($type) . '-container dust-' . esc_attr($type) . '-' . esc_attr($layout) . '">';

        foreach ($data as $item) {
            self::render_single_item($item, $show_coordinates, $show_images, $type, $data, $tab_context);
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
     * @param array $all_data Optional: all data for repeat checking
     * @param string $tab_context 'repeating' or 'day' for schedule tabs
     * @return void
     */
    public static function render_single_item($item, $show_coordinates = true, $show_images = true, $type = 'camps', $all_data = null, $tab_context = 'day') {
        echo '<article class="dust-' . esc_attr($type) . '-item" data-uid="' . esc_attr($item['uid']) . '" role="article" tabindex="0">';

        if ($type === 'camps' || $type === 'art') {
            self::render_camps_art($item, $show_coordinates, $show_images, $type);
        } elseif ($type === 'schedule') {
            self::render_schedule($item, $show_images, $all_data, $tab_context);
        } elseif ($type === 'music') {
            self::render_music($item);
        }

        echo '</article>';
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
            echo '<img src="' . esc_url($image_url) . '" alt="" role="presentation" loading="lazy" />';
            echo '</div>';
        }

        echo '<div class="dust-' . esc_attr($type) . '-content">';
        echo '<h3 class="dust-' . esc_attr($type) . '-name">' . esc_html($name) . '</h3>';

        if ($type === 'art' && isset($item['artist'])) {
            echo '<div class="dust-item-field"><strong>Artist:</strong> ' . esc_html($item['artist']) . '</div>';
        }

        $categories = self::format_categories($item, $type);
        if (!empty($categories)) {
            echo '<div class="dust-item-field"><strong>Categories:</strong> ' . esc_html($categories) . '</div>';
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
     * Render schedule with tabs
     */
    private static function render_schedule_tabs($data, $options, $event_name) {
        $tabs = self::organize_schedule_by_days($data);
        $container_id = 'dust-schedule-tabs-' . uniqid();

        echo '<div class="dust-schedule-tabs-container" id="' . esc_attr($container_id) . '" data-event="' . esc_attr($event_name) . '">';

        // Export buttons if requested
        if (isset($options['show_export_buttons'])) {
            $export_buttons = self::get_export_buttons($options['show_export_buttons'], $event_name);
            if (!empty($export_buttons)) {
                echo '<div class="lunacode-export-buttons">' . $export_buttons . '</div>';
            }
        }

        // Tab navigation
        echo '<div class="dust-schedule-tab-nav" role="tablist" aria-label="Schedule by day">';
        $first = true;
        foreach ($tabs as $tab_key => $tab_data) {
            $tab_id = $container_id . '-tab-' . $tab_key;
            $panel_id = $container_id . '-panel-' . $tab_key;
            $selected = $first ? 'true' : 'false';
            echo '<button class="dust-schedule-tab-btn' . ($first ? ' active' : '') . '" '
                . 'role="tab" '
                . 'aria-selected="' . $selected . '" '
                . 'aria-controls="' . esc_attr($panel_id) . '" '
                . 'id="' . esc_attr($tab_id) . '" '
                . 'data-tab="' . esc_attr($tab_key) . '" '
                . 'tabindex="' . ($first ? '0' : '-1') . '">'
                . esc_html($tab_data['label']) . '</button>';
            $first = false;
        }
        echo '</div>';

        // Tab content
        echo '<div class="dust-schedule-tab-content">';
        $first = true;
        foreach ($tabs as $tab_key => $tab_data) {
            $tab_id = $container_id . '-tab-' . $tab_key;
            $panel_id = $container_id . '-panel-' . $tab_key;
            $context = ($tab_key === 'repeating') ? 'repeating' : 'day';
            echo '<div class="dust-schedule-tab-pane' . ($first ? ' active' : '') . '" '
                . 'role="tabpanel" '
                . 'aria-labelledby="' . esc_attr($tab_id) . '" '
                . 'id="' . esc_attr($panel_id) . '" '
                . 'data-tab="' . esc_attr($tab_key) . '" '
                . 'tabindex="0" '
                . 'style="display: ' . ($first ? 'block' : 'none') . ';">';
            self::render_data($tab_data['events'], $options, 'schedule', $context);
            echo '</div>';
            $first = false;
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Organize schedule data by days
     */
    private static function organize_schedule_by_days($data) {
        $tabs = array();
        $everyday_events = array();
        $day_events = array();
        $title_day_count = array();

        // First pass: count how many different days each event title appears on
        foreach ($data as $event) {
            $title = $event['title'] ?? '';
            $day = $event['day'] ?? '';

            if (!empty($title) && !empty($day)) {
                if (!isset($title_day_count[$title])) {
                    $title_day_count[$title] = array();
                }
                $title_day_count[$title][$day] = true;
            }
        }

        // Second pass: categorize events
        foreach ($data as $event) {
            $title = $event['title'] ?? '';
            $day = $event['day'] ?? '';

            // Check if it's an everyday event (appears on 3+ different days or has daily keywords)
            $is_everyday = false;
            if (!empty($title)) {
                $daily_keywords = array('daily', 'every day', 'everyday', 'all days');
                $title_lower = strtolower($title);
                $desc_lower = strtolower($event['description'] ?? '');

                foreach ($daily_keywords as $keyword) {
                    if (strpos($title_lower, $keyword) !== false || strpos($desc_lower, $keyword) !== false) {
                        $is_everyday = true;
                        break;
                    }
                }

                // Also check if this title appears on 2+ different days (multi-day events)
                if (!$is_everyday && isset($title_day_count[$title]) && count($title_day_count[$title]) >= 2) {
                    $is_everyday = true;
                }
            }

            if ($is_everyday) {
                $everyday_events[] = $event;
            }

            if (!empty($day)) {
                if (!isset($day_events[$day])) {
                    $day_events[$day] = array();
                }
                $day_events[$day][] = $event;
            }
        }

        // Add "Repeating" tab if there are everyday events
        if (!empty($everyday_events)) {
            $tabs['repeating'] = array(
                'label' => 'Repeating',
                'events' => self::sort_schedule_events($everyday_events)
            );
        }

        // Process day events and create tabs
        $day_counts = array();
        foreach ($day_events as $day => $events) {
            $day_counts[$day] = ($day_counts[$day] ?? 0) + 1;
        }

        foreach ($day_events as $day => $events) {
            $tab_key = strtolower(str_replace(' ', '_', $day));

            // Add date suffix if multiple occurrences of same day
            $label = $day;
            if ($day_counts[$day] > 1) {
                // Extract date from first event if available
                $first_event = reset($events);
                if (isset($first_event['occurrence']['start_time'])) {
                    $date = new DateTime($first_event['occurrence']['start_time']);
                    $label .= ' ' . $date->format('n/j');
                }
            }

            $tabs[$tab_key] = array(
                'label' => $label,
                'events' => self::sort_schedule_events($events)
            );
        }

        return $tabs;
    }

    /**
     * Sort schedule events: all-day first, then by start time, then by name
     */
    private static function sort_schedule_events($events) {
        uasort($events, function($a, $b) {
            $a_occurrence = $a['occurrence'] ?? array();
            $b_occurrence = $b['occurrence'] ?? array();

            // Check if all-day events
            $a_all_day = isset($a_occurrence['all_day']) && $a_occurrence['all_day'];
            $b_all_day = isset($b_occurrence['all_day']) && $b_occurrence['all_day'];

            if ($a_all_day && !$b_all_day) return -1;
            if (!$a_all_day && $b_all_day) return 1;

            // Sort by start time
            $a_time = $a_occurrence['start_time'] ?? '';
            $b_time = $b_occurrence['start_time'] ?? '';

            if ($a_time && $b_time) {
                $time_cmp = strcmp($a_time, $b_time);
                if ($time_cmp !== 0) return $time_cmp;
            }

            // Sort by title
            return strcmp($a['title'] ?? '', $b['title'] ?? '');
        });

        return $events;
    }

    /**
     * Get the days an event repeats on (excluding the current day)
     *
     * @param string $title Event title
     * @param string $current_day Current day
     * @param array $all_data All schedule data
     * @return array Array of days the event repeats on
     */
    public static function get_repeat_days($title, $current_day, $all_data) {
        static $repeat_cache = array();
        $cache_key = md5($title . '|' . $current_day);

        if (isset($repeat_cache[$cache_key])) {
            return $repeat_cache[$cache_key];
        }

        $repeat_days = array();
        foreach ($all_data as $event) {
            if ($event['title'] === $title && !empty($event['day']) && $event['day'] !== $current_day) {
                $repeat_days[$event['day']] = true;
            }
        }

        $result = array_keys($repeat_days);
        sort($result);
        $repeat_cache[$cache_key] = $result;
        return $result;
    }

    /**
     * Get all days an event repeats on (including all instances)
     *
     * @param string $title Event title
     * @param array $all_data All schedule data
     * @return array Array of all days the event appears on
     */
    public static function get_all_repeat_days($title, $all_data) {
        static $all_days_cache = array();

        if (isset($all_days_cache[$title])) {
            return $all_days_cache[$title];
        }

        $day_dates = array();
        foreach ($all_data as $event) {
            if ($event['title'] === $title && !empty($event['day'])) {
                $day_dates[$event['day']] = $event['occurrence']['start_time'] ?? '';
            }
        }

        $result = array_keys($day_dates);
        usort($result, function($a, $b) use ($day_dates) {
            return strcmp($day_dates[$a], $day_dates[$b]);
        });
        $all_days_cache[$title] = $result;
        return $result;
    }

    /**
     * Output schedule items (events) as HTML
     *
     * @param array $item
     * @param bool $show_images
     * @param array $all_data Optional: all schedule data to check for repeats
     * @param string $tab_context 'repeating' or 'day'
     * @return void
     */
    public static function render_schedule($item, $show_images, $all_data = null, $tab_context = 'day') {
        $title = $item['title'];
        $description = self::format_description($item['description']);
        $camp = isset($item['camp']) ? $item['camp'] : '';
        $location = isset($item['location']) ? $item['location'] : '';
        $day = isset($item['day']) ? $item['day'] : '';
        $occurrence = isset($item['occurrence']) ? $item['occurrence'] : array();

        $image_url = $show_images && isset($item['imageUrl']) ? self::get_image_url($item['imageUrl']) : null;

        if ($image_url) {
            echo '<div class="dust-schedule-image">';
            echo '<img src="' . esc_url($image_url) . '" alt="" role="presentation" loading="lazy" />';
            echo '</div>';
        }

        echo '<div class="dust-schedule-content">';
        echo '<h3 class="dust-schedule-title">' . esc_html($title) . '</h3>';

        if ($camp) echo '<div class="dust-item-field"><strong>Camp:</strong> ' . esc_html($camp) . '</div>';
        if ($location) echo '<div class="dust-item-field"><strong>Location:</strong> ' . esc_html($location) . '</div>';

        $categories = self::format_categories($item, 'schedule');
        if (!empty($categories)) {
            echo '<div class="dust-item-field"><strong>Categories:</strong> ' . esc_html($categories) . '</div>';
        }

        if ($day) {
            $day_text = esc_html($day);
            if ($all_data && !empty($title)) {
                if ($tab_context === 'repeating') {
                    // On Repeating tab: show "Repeats on [list of all days]"

                    $all_days = self::get_all_repeat_days($title, $all_data);
                    if (count($all_days) > 1) {
                        $day_text = 'Repeats on ' . esc_html(implode(', ', $all_days));
                    }
                } else {
                    // On day tabs: show "Thursday (repeats on Friday, Saturday, Sunday)"
                    $all_days = self::get_all_repeat_days($title, $all_data);
                    if (count($all_days) > 1) {
                        $other_days = array_diff($all_days, array($day));
                        if (!empty($other_days)) {
                            $day_text .= ' (repeats on ' . esc_html(implode(', ', $other_days)) . ')';
                        }
                    }
                }
            }
            echo '<div class="dust-item-field"><strong>Day:</strong> ' . $day_text . '</div>';
        }

        if (isset($occurrence['long'])) {
            echo '<div class="dust-item-field"><strong>Time:</strong> ' . esc_html($occurrence['long']) . '</div>';
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

        if ($camp) echo '<div class="dust-item-field"><strong>Camp:</strong> ' . esc_html($camp) . '</div>';
        if ($location) echo '<div class="dust-item-field"><strong>Location:</strong> ' . esc_html($location) . '</div>';
        if ($day) echo '<div class="dust-item-field"><strong>Day:</strong> ' . esc_html($day) . '</div>';

        if (isset($occurrence['who'])) {
            echo '<div class="dust-item-field"><strong>Artist:</strong> ' . esc_html($occurrence['who']) . '</div>';
        }

        if (isset($occurrence['long'])) {
            echo '<div class="dust-item-field"><strong>Time:</strong> ' . esc_html($occurrence['long']) . '</div>';
        }
        echo '</div>';
    }
    /**
     * Export schedule as CSV file
     */
    public function export_schedule_csv() {
        check_ajax_referer('dust_events_nonce', 'nonce');

        $event_name = sanitize_text_field($_POST['event_name'] ?? get_option('dust_events_event_name'));
        $data = self::get_data('schedule', $event_name);

        if (is_wp_error($data)) {
            wp_die('Error loading schedule data');
        }

        $csv_content = $this->generate_csv($data);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . esc_attr($event_name) . '-schedule.csv"');
        echo $csv_content;
        wp_die();
    }

    private function generate_csv($schedule_data) {
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            wp_die('Error creating CSV file');
        }

        // CSV headers
        fputcsv($output, array('Title', 'Camp', 'Location', 'Day', 'Start Time', 'End Time', 'Short Time', 'Long Time', 'Brief Time', 'Image URL', 'Categories', 'Description'));

        foreach ($schedule_data as $event) {
            $row = array(
                $event['title'] ?? '',
                $event['camp'] ?? '',
                $event['location'] ?? '',
                $event['day'] ?? '',
                isset($event['occurrence']['start_time']) ? $event['occurrence']['start_time'] : '',
                isset($event['occurrence']['end_time']) ? $event['occurrence']['end_time'] : '',
                isset($event['occurrence']['short']) ? $event['occurrence']['short'] : '',
                isset($event['occurrence']['long']) ? $event['occurrence']['long'] : '',
                isset($event['occurrence']['brief']) ? $event['occurrence']['brief'] : '',
                isset($event['imageUrl']) ? self::get_image_url($event['imageUrl']) : '',
                isset($event['event_type']['label']) ? $event['event_type']['label'] : '',
                strip_tags($event['description'] ?? '')
            );
            fputcsv($output, $row);
        }

        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return $csv_content;
    }

    /**
     * Export schedule as Morse code file
     */
    public function export_schedule_morse() {
        check_ajax_referer('dust_events_nonce', 'nonce');

        $event_name = sanitize_text_field($_POST['event_name'] ?? get_option('dust_events_event_name'));
        $data = self::get_data('schedule', $event_name);

        if (is_wp_error($data)) {
            wp_die('Error loading schedule data');
        }

        $morse_content = $this->generate_morse($data);

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . esc_attr($event_name) . '-schedule.txt"');
        echo $morse_content;
        wp_die();
    }

    private function generate_morse($schedule_data) {
        $morse_map = array(
            'A' => '.-', 'B' => '-...', 'C' => '-.-.', 'D' => '-..', 'E' => '.', 'F' => '..-.',
            'G' => '--.', 'H' => '....', 'I' => '..', 'J' => '.---', 'K' => '-.-', 'L' => '.-..',
            'M' => '--', 'N' => '-.', 'O' => '---', 'P' => '.--.', 'Q' => '--.-', 'R' => '.-.',
            'S' => '...', 'T' => '-', 'U' => '..-', 'V' => '...-', 'W' => '.--', 'X' => '-..-',
            'Y' => '-.--', 'Z' => '--..', '0' => '-----', '1' => '.----', '2' => '..---',
            '3' => '...--', '4' => '....-', '5' => '.....', '6' => '-....', '7' => '--...',
            '8' => '---..', '9' => '----.'
        );

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            wp_die('Error creating Morse file');
        }
        fwrite($output, "SCHEDULE MORSE CODE\n\n");

        foreach ($schedule_data as $event) {
            $text = strtoupper(strip_tags($event['title'] ?? ''));
            // This is safe b/c it's generated from a controlled character set, and unmapped characters were discarded.
            $morse_line_safe = '';
            for ($i = 0; $i < strlen($text); $i++) {
                $char = $text[$i];
                if ($char === ' ') {
                    $morse_line_safe .= '  ';
                } elseif (isset($morse_map[$char])) {
                    $morse_line_safe .= $morse_map[$char] . ' ';
                }
            }
            fwrite($output, trim($morse_line_safe) . "\n");
        }

        rewind($output);
        $morse_content = stream_get_contents($output);
        fclose($output);

        return $morse_content;
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
        $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $event_name);
        header('Content-Disposition: attachment; filename="' . $safe_filename . '-schedule.ics"');
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
            // amazonq-ignore-next-line This fallback ID does not require secure randomness.
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
                    $dt = DateTime::createFromFormat('Ymd\THis', $start_time);
                    if ($dt !== false) {
                        $dt->add(new DateInterval('PT1H'));
                        $ics .= "DTEND;TZID=" . $timezone_info['id'] . ":" . $dt->format('Ymd\THis') . "\r\n";
                    }
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
            try {
                $timezone_info = $this->get_event_timezone($event_name);
                $dt = new DateTime($event['occurrence']['start_time'], new DateTimeZone($timezone_info['id']));
                return $dt->format('Ymd\THis');
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    private function parse_dust_end_time($event, $event_name = null) {
        if (isset($event['occurrence']['end_time'])) {
            try {
                $timezone_info = $this->get_event_timezone($event_name);
                $dt = new DateTime($event['occurrence']['end_time'], new DateTimeZone($timezone_info['id']));
                return $dt->format('Ymd\THis');
            } catch (\Exception $e) {
                return null;
            }
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
        $text = $text ?: '📅 Export to Calendar';

        $escaped = array(
            'event_name' => esc_attr($event_name),
            'text' => esc_html($text),
        );
        return "<button class=\"dust-ics-export-btn\" data-event=\"{$escaped['event_name']}\" aria-label=\"Export schedule to calendar file\" type=\"button\">{$escaped['text']}</button>";
    }

    /**
     * Render CSV export button
     *
     * @param string $event_name Event name
     * @param string|null $text Button text
     * @return string HTML button
     */
    public static function render_csv_button($event_name = '', $text = '') {
        $event_name = $event_name ?: get_option('dust_events_event_name');
        $text = $text ?: '📊 Export to CSV';

        $escaped = array(
            'event_name' => esc_attr($event_name),
            'text' => esc_html($text),
        );
        return "<button class=\"dust-csv-export-btn\" data-event=\"{$escaped['event_name']}\" aria-label=\"Export schedule to CSV file\" type=\"button\">{$escaped['text']}</button>";
    }

    /**
     * Render Morse export button
     *
     * @param string $event_name Event name
     * @param string|null $text Button text
     * @return string HTML button
     */
    public static function render_morse_button($event_name = '', $text = '') {
        $event_name = $event_name ?: get_option('dust_events_event_name');
        $text = $text ?: '📡 Export to Morse';

        $escaped = array(
            'event_name' => esc_attr($event_name),
            'text' => esc_html($text),
        );
        return "<button class=\"dust-morse-export-btn\" data-event=\"{$escaped['event_name']}\" aria-label=\"Export schedule to Morse code file\" type=\"button\">{$escaped['text']}</button>";
    }

    /**
     * Parse export buttons parameter and return HTML for requested buttons
     *
     * @param string $show_export_buttons Parameter value
     * @param string $event_name Event name
     * @return string HTML for export buttons
     */
    private static function get_export_buttons($show_export_buttons, $event_name = '') {
        // Handle legacy boolean values
        if ($show_export_buttons === 'true' || $show_export_buttons === 'all') {
            return self::render_ics_button($event_name) . ' ' . self::render_csv_button($event_name) . ' ' . self::render_morse_button($event_name);
        }

        if ($show_export_buttons === 'false' || $show_export_buttons === 'none' || empty($show_export_buttons)) {
            return '';
        }

        // Parse comma-separated list
        $buttons = array();
        $requested = array_map('trim', explode(',', strtolower($show_export_buttons)));

        // If 'none' is present, return empty regardless of other values
        if (in_array('none', $requested)) {
            return '';
        }

        foreach ($requested as $type) {
            switch ($type) {
                case 'ics':
                    $buttons[] = self::render_ics_button($event_name);
                    break;
                case 'csv':
                    $buttons[] = self::render_csv_button($event_name);
                    break;
                case 'morse':
                    $buttons[] = self::render_morse_button($event_name);
                    break;
                case 'all':
                    return self::render_ics_button($event_name) . ' ' . self::render_csv_button($event_name) . ' ' . self::render_morse_button($event_name);
            }
        }

        return implode(' ', $buttons);
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

/**
 * Render CSV export button
 *
 * @param string $event_name Event name
 * @param string $text Button text
 * @return string HTML button
 */
function dust_schedule_csv_button($event_name = '', $text = '') {
    return \LunaCode\DisplayDustData::render_csv_button($event_name, $text);
}

/**
 * Render Morse export button
 *
 * @param string $event_name Event name
 * @param string $text Button text
 * @return string HTML button
 */
function dust_schedule_morse_button($event_name = '', $text = '') {
    return \LunaCode\DisplayDustData::render_morse_button($event_name, $text);
}
