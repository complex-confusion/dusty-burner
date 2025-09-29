# Dust Events Plugin for WordPress

This plugin displays [Dust](https://dust.events/)'s complete data including camps, art, schedule, and music from regional Burning Man events or from Burning Man itself.
It fetches data from all four Dust Events API endpoints and renders it on your WordPress site with options for layout, styling, and interactivity.

Many thanks to Dust's creator Damian for providing the app and the API this depends on. Also, much gratitude for the quick support during our regional event's implementation of Dust.

## Quick Start

1. Go to the Settings for this plugin, and enter the short name for your event. This is the same short event name you see in your Dust admin panel: "https://edit.dust.events/**myBurn**";
2. Use any of the following shortcodes on your WordPress pages:
   ```
   [dust_camps layout="grid" show_coordinates="false" show_images="true"]
   [dust_art layout="grid" show_images="true"]
   [dust_schedule layout="list"]
   [dust_music layout="list"]
   ```

## Available Shortcodes

### [dust_camps] - Display Camps
Shows camp information with names, descriptions, images, and coordinates.

### [dust_art] - Display Art
Shows art installations and mutant vehicles with artist information.

### [dust_schedule] - Display Schedule
Shows scheduled events sorted by camp, then by title.

### [dust_music] - Display Music/Parties
Shows music events and parties sorted by camp, then by title.

## Configuration Options

### Shortcode Parameters (All Shortcodes)

- `event_name`: Your unique event name from Dust Events
- `layout`: `"grid"` or `"list"` (default: grid)
- `show_coordinates`: `"true"` or `"false"` (default: true) - For camps/art only
- `show_images`: `"true"` or `"false"` (default: true)
- `per_page`: Number of items to show, `-1` for all (default: -1)

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

**Camps:**
- `.dust-camps-container`: Main container
- `.dust-camps-item`: Individual camp card
- `.dust-camps-image`: Image container
- `.dust-camps-content`: Content area
- `.dust-camps-name`: Camp name
- `.dust-camps-description`: Description text
- `.dust-camps-coordinates`: Coordinates display

**Art:**
- `.dust-art-container`: Main container
- `.dust-art-item`: Individual art card
- `.dust-art-image`: Image container
- `.dust-art-content`: Content area
- `.dust-art-name`: Art name
- `.dust-art-artist`: Artist name
- `.dust-art-description`: Description text

**Schedule:**
- `.dust-schedule-container`: Main container
- `.dust-schedule-item`: Individual event card
- `.dust-schedule-content`: Content area
- `.dust-schedule-title`: Event title
- `.dust-schedule-camp`: Camp name
- `.dust-schedule-time`: Event time
- `.dust-schedule-description`: Event description

**Music:**
- `.dust-music-container`: Main container
- `.dust-music-item`: Individual music event card
- `.dust-music-content`: Content area
- `.dust-music-title`: Event title
- `.dust-music-camp`: Camp name
- `.dust-music-who`: Artist/DJ name
- `.dust-music-time`: Event time

## JavaScript Integration

### Available Methods

```javascript
// Refresh data for any type
DustEvents.refreshData("camps", "event-name");
DustEvents.refreshData("art", "event-name");
DustEvents.refreshData("schedule", "event-name");
DustEvents.refreshData("music", "event-name");

// Get specific item data
var item = DustEvents.getItemByUid("u-123", "camps");

// Highlight an item
DustEvents.highlightItem("u-123", "camps");

// Filter items
DustEvents.filterItems("search-term", "camps");
```

### Custom Events

```javascript
// Listen for item clicks
$(document).on("dustItemClicked", function (event, data) {
  console.log("Item clicked:", data.name || data.title, data.uid, data.type);
});
```

## API Response Handling

### Data Structure Examples

```php
// Get any type of data
$camps = dust_get_data('camps', 'your-event-name');
$art = dust_get_data('art', 'your-event-name');
$schedule = dust_get_data('schedule', 'your-event-name');
$music = dust_get_data('music', 'your-event-name');

// Parse coordinates (camps/art)
$pin_data = json_decode($item['pin'], true);
if (isset($pin_data['lat'], $pin_data['lng'])) {
    $latitude = $pin_data['lat'];
    $longitude = $pin_data['lng'];
}

// Handle different image formats
// Camps: $item['imageUrl'] (needs base URL prefix)
// Art: $item['images'][0]['thumbnail_url'] (complete URL)
// Schedule: $item['imageUrl'] (needs base URL prefix)
```

## Performance Optimization

### Caching

The plugin automatically caches API responses for 1 hour using WordPress transients:

```php
// Cache key format for each endpoint
$cache_key = 'dust_' . $type . '_' . md5($event_name);
// Examples: dust_camps_abc123, dust_art_abc123, dust_schedule_abc123, dust_music_abc123
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
- Ensure the endpoint exists (some events may not have all four data types)

#### Images not loading

- **Camps/Schedule**: Check that imageUrl field is present and add base URL
- **Art**: Check that images array exists and contains thumbnail_url
- Verify image URLs are accessible

#### Coordinates not displaying

- Only applies to camps and art
- Check that pin field contains valid JSON
- Verify coordinate format (lat/lng or x/y)

#### Schedule/Music not sorting correctly

- Data is sorted by camp name, then by title
- Check that camp/hosted_by_camp fields are present

## Advanced Customization

### Custom Data Processing

Add filters for any data type:

```php
// In your theme or plugin
function custom_data_processing($data, $type) {
    // Add custom fields or modify existing data
    if ($type === 'camps') {
        // Custom camp processing
    } elseif ($type === 'schedule') {
        // Custom schedule processing
    }
    return $data;
}
add_filter('dust_events_process_data', 'custom_data_processing', 10, 2);
```

### Custom Templates

Create custom display templates by overriding the render functions or creating your own shortcodes for specific data types.

### Template Functions

```php
// Display any data type programmatically
dust_display_data('camps', 'your-event', array('layout' => 'grid'));
dust_display_data('art', 'your-event', array('show_images' => 'true'));
dust_display_data('schedule', 'your-event', array('layout' => 'list'));
dust_display_data('music', 'your-event', array('per_page' => 10));

// Backward compatibility
dust_display_camps('your-event', array('layout' => 'grid'));
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

## Data Sorting

- **Camps & Art**: Sorted alphabetically by name
- **Schedule & Music**: Sorted by camp name (using `camp` or `hosted_by_camp` fields), then by title

## API Endpoints Used

- `https://data.dust.events/[event-name]/camps.json`
- `https://data.dust.events/[event-name]/art.json`
- `https://data.dust.events/[event-name]/schedule.json`
- `https://data.dust.events/[event-name]/music.json`
