<?php
/**
 * Simple test script to verify REST API endpoint
 * Access via: /wp-content/plugins/lunacode-display-dust-data/test-api.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Test the REST API endpoint
$rest_url = rest_url('dust-events/v1/data/camps?event_name=playa-del-fuego');

echo "<h1>Testing Dust Events REST API</h1>";
echo "<p><strong>REST URL:</strong> " . esc_html($rest_url) . "</p>";

// Make a request to our own REST API
$response = wp_remote_get($rest_url);

if (is_wp_error($response)) {
    echo "<p style='color: red;'><strong>Error:</strong> " . esc_html($response->get_error_message()) . "</p>";
} else {
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "<p><strong>Status Code:</strong> " . esc_html($status_code) . "</p>";
    
    if ($status_code === 200) {
        $data = json_decode($body, true);
        if (is_array($data)) {
            echo "<p style='color: green;'><strong>Success!</strong> Retrieved " . count($data) . " camps.</p>";
            if (!empty($data)) {
                echo "<h3>Sample Camp Data:</h3>";
                echo "<pre>" . esc_html(json_encode($data[0], JSON_PRETTY_PRINT)) . "</pre>";
            }
        } else {
            echo "<p style='color: orange;'><strong>Warning:</strong> Response is not an array.</p>";
            echo "<pre>" . esc_html($body) . "</pre>";
        }
    } else {
        echo "<p style='color: red;'><strong>HTTP Error:</strong> " . esc_html($status_code) . "</p>";
        echo "<pre>" . esc_html($body) . "</pre>";
    }
}

echo "<hr>";
echo "<h2>JavaScript Test</h2>";
echo "<p>Open browser console to see fetch test results.</p>";
?>

<script>
// Test the REST API from JavaScript
console.log('Testing REST API from JavaScript...');

fetch('<?php echo esc_js($rest_url); ?>')
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Success! Retrieved data:', data);
        console.log('Number of items:', Array.isArray(data) ? data.length : 'Not an array');
    })
    .catch(error => {
        console.error('Error:', error);
    });
</script>