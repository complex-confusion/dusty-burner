# Dust Events Camps Plugin for WordPress

This module displays [Dust](https://dust.events/)'s lists of events, camps, and art from regional Burning Man events or from Burning Man itself.
It fetches data from the Dust Events API and renders it on your WordPress site with options for layout, styling, and (in the near future) interactivity.

Many thanks to Dust's creator Damian for providing the app and the API this depends on. Also, much gratitudee for the quick support during our regional event's implementation of Dust.

## Quick Start

1. Go to the Settings for this plugin, and enter the short name for your event. This is the same short event name you see in your Dust admin panel: "https://edit.dust.events/**myBurn**";
1. Replace your event's short name in the following shortcode
1. Paste into your WordPress page
   ```
     [dust_camps layout="grid" show_coordinates="false" show_images="true"]
   ```

## Configuration Options

### Shortcode Parameters

- `event_name`: Your unique event name from Dust Events
- `layout`: `"grid"` or `"list"` (default: grid)
- `show_coordinates`: `"true"` or `"false"` (default: true)
- `show_images`: `"true"` or `"false"` (default: true)
- `per_page`: Number of camps to show, `-1` for all (default: -1)

### Display Layouts

**Grid Layout**

- Responsive card-based layout
- Images displayed above content
- Good for browsing multiple camps

**List Layout**

- Horizontal layout with image on left
- More detailed view
- Better for detailed information

## Styling Customization

### CSS Variables

```css
:root {
  --dust-camp-primary-color: #007cba;
  --dust-camp-border-color: #ddd;
  --dust-camp-background: #fff;
  --dust-camp-hover-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
```

### Custom CSS Classes

- `.dust-camps-container`: Main container
- `.dust-camp-item`: Individual camp card
- `.dust-camp-image`: Image container
- `.dust-camp-content`: Content area
- `.dust-camp-name`: Camp name
- `.dust-camp-description`: Description text
- `.dust-camp-coordinates`: Coordinates display

## JavaScript Integration

### Available Methods

```javascript
// Refresh camps data
DustCamps.refreshCamps("event-name");

// Get specific camp data
var camp = DustCamps.getCampByUid("u-123");

// Highlight a camp
DustCamps.highlightCamp("u-123");

// Filter camps
DustCamps.filterCamps("search-term");
```

### Custom Events

```javascript
// Listen for camp clicks
$(document).on("dustCampClicked", function (event, data) {
  console.log("Camp clicked:", data.name, data.uid);
});
```

## API Response Handling

### Coordinates Parsing

```php
// Parse pin coordinates
$pin_data = json_decode($camp['pin'], true);

if (isset($pin_data['lat'], $pin_data['lng'])) {
    // GPS coordinates
    $latitude = $pin_data['lat'];
    $longitude = $pin_data['lng'];
} elseif (isset($pin_data['x'], $pin_data['y'])) {
    // Map pixel coordinates
    $x_position = $pin_data['x'];
    $y_position = $pin_data['y'];
}
```

### Image URL Construction

```php
// Complete image URL
$full_image_url = 'https://data.dust.events/' . $camp['imageUrl'];
```

## Performance Optimization

### Caching

The plugin automatically caches API responses for 1 hour using WordPress transients:

```php
// Cache key format
$cache_key = 'dust_camps_' . md5($event_name);
```

### Image Loading

- Lazy loading support included
- Broken image handling with placeholder
- Responsive image sizing

## Troubleshooting

### Common Issues

#### "No event name configured"

- Configure event name in plugin settings
- Or pass event_name parameter to shortcode

#### "API returned status code: 404"

- Check that your event name is correct
- Verify the API endpoint is accessible

#### Images not loading

- Check that imageUrl field is present in API response
- Verify image URLs are accessible

#### Coordinates not displaying

- Check that pin field contains valid JSON
- Verify coordinate format (lat/lng or x/y)

## Advanced Customization

### Custom Fields

Add additional camp data processing:

```php
// In your theme or plugin
function custom_camp_processing($camp_data) {
    // Add custom fields or modify existing data
    return $camp_data;
}
add_filter('dust_camps_process_data', 'custom_camp_processing');
```

### Custom Templates

Create custom display templates by overriding the render functions or creating your own shortcode.

### Integration with Maps

Use the coordinate data to integrate with mapping services:

```javascript
// Example: Add to Google Maps
if (pinData.lat && pinData.lng) {
  var marker = new google.maps.Marker({
    position: { lat: pinData.lat, lng: pinData.lng },
    map: map,
    title: campName,
  });
}
```

## Security Considerations

- API responses are sanitized using WordPress functions
- User input is escaped and validated
- AJAX requests use nonces for security
- Remote API calls have timeout limits

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11+ for basic functionality
- Progressive enhancement for advanced features

## License

This implementation is provided as-is for integration with the Dust Events API. Follow WordPress coding standards and GPL licensing if distributing.

## Support

For issues with the [Dust Events API](https://dust.events/docs/Integrations/api) itself, contact the Dust Events team. For WordPress implementation questions, refer to WordPress documentation and community resources.
