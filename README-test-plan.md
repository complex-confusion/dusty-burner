# TEST COVERAGE PLAN FOR LUNACODE DISPLAY DUST DATA PLUGIN
This document outlines the comprehensive test strategy for ensuring the stability,
reliability, and data integrity of the plugin's API integration with Dust.io.
---------------------------------------------------------------------------------
I. PHP BACKEND TESTING (PHPUnit)
---------------------------------------------------------------------------------
We use PHPUnit as the primary framework because it is the standard for WordPress
core and plugin development, providing excellent integration with WP_UnitTestCase.
1. API Integration Tests (dust_get_camps() and similar)
   - Test Case: Valid API Response. Ensure that when Dust.io returns a 200 OK
     with valid JSON, the function returns an array of camps.
   - Test Case: Invalid API Response. Mock the HTTP request to enable a test for
     how the plugin handles 404 or 500 server errors from Dust.io.
   - Test Case: Timeout/Network Error. Ensure that if the network request fails, 
     the plugin handles the timeout gracefully without crashing the page load.
   - Test Case: Data Sanitization. Verify that data coming from the API (which is
     not under our control) is properly sanitized before being stored or displayed.
2. Shortcode Implementation ([dust_camps] and others)
   - Test Case: Rendering. Check if the shortcode produces expected HTML output
     consistent with the defined plugin templates.
   - Test Case: Attribute Handling. Verify that layout="grid" vs layout="list"
     actually changes the CSS classes in the output.
   - Test Case: Appendix/Empty State. Ensure a clear "No camps found" message
     is displayed when the API returns an empty array.
3. AJAX Handlers (get_camps_data)
   - Test Case: Nonce Verification. Verify that the request is rejected if
     the nonce is incorrect or invalid to prevent CSRF attacks.
   - Test Case: Response Format. Ensure the AJAX response is a valid JSON object
     containing the data requested.
   - Test Case: Response Time. Measure the performance impact of API requests
     to ensure they do not exceed acceptable latency thresholds for frontend users.
     (THIS IS A TEST OF BASIC WORDPRESS FUNCTIONALITY if it's an AJAX wrapper).
4. PHP Template/Page Logic (page-lunacode-display-dust-data.php)
   - Test Case: Page Header rendering. Verify that the WordPress theme properly 
     integrates with the custom page template hooks.
     THIS IS A TEST OF BASIC WORDPRESS FUNCTIONALITY.
   - Test case: Custom Layout Logic. Verify that the first 6 camps are sliced from the
     the array in the "Featured Camps" section.
---------------------------------------------------------------------------------
II. JAVASCRIPT FRONTEND TESTING (Jest / Cypress)
---------------------------------------------------------------------------------
While PHPUnit is for PHP logic, we use Jest for unit tests and Cypress for E2E tests
to ensure the "display" part of the plugin actually works in a browser.
1. LunaCode.DisplayDustData Logic (lunacode-display-dust-data.js)
   - Test Case: Tab Switching. Verify that `activateTab` and `activateFilter` 
     correctly update the active CSS classes and visibility of data containers.
   - Test Case: Search Filtering. Ensure that when a user types in the search box,
     the plugin's JS logic filters the current list of camps based on camp names and descriptions.
   - Test Case: Event Triggering. Verify that `$(document).on("dustItemClicked", ...)`
     is triggered when an item is actually clicked.
2. Dynamic Loading (AJAX in page-lunacode-display-dust-data.php)
   - Test Case: Loading State. Verify that the "Load More" button is disabled during
     the AJAX request to prevent duplicate simultaneous queries.
   - Test Case: HTML Builder. `buildCampsHTML` function logic: verify that it returns
     the expected HTML string when given a valid camp data object.
   - Test Case: Map Link Generation. Ensure the coordinates (lat/lng) are 
     correctly extracted and formatted for external map URLs.
   - Test case: Google Maps URL construction. Verify that the generated links 
     direct the user to the correct geographic coordinates of the specific camp.
---------------------------------------------------------------------------------
III. CROSS-FILE INTEGRATION TESTING
---------------------------------------------------------------------------------
1. Data Flow Path: API -> PHP Function -> Shortcode/Template -> JS Filtering.
   - Test Case: End-to-End flow. Trigger a request to the Dust.io API and verify 
     that the data is processed by PHP and correctly rendered in the browser DOM.
   - Test Case: Validation of data consistency. Ensure that the camp name, description,
     and coordinates displayed on the frontend exactly match the source data provided by the Dust.io API endpoint.
