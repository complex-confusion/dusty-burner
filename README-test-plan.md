# Test Plan: LunaCode Display Dust Data

This plan covers the behavior documented in `README.md` and implemented by the
plugin. Tests should use fixed fixtures and mocks rather than making live Dust
API requests, so they remain deterministic and do not depend on network access.

## Test setup

The initial PHPUnit harness is in place. It loads the plugin inside the
WordPress test suite and includes a smoke test using the WordPress HTTP API
filter; the broader test coverage below remains to be implemented.

The bootstrap defaults to the WordPress test library in the sibling
`../wordpress-develop/tests/phpunit/` directory. From the plugin repository
root, create the test configuration at the root of that checkout:

```sh
cp ../wordpress-develop/wp-tests-config-sample.php ../wordpress-develop/wp-tests-config.php
```

Edit `../wordpress-develop/wp-tests-config.php`: the sample's `ABSPATH` value
(`dirname( __FILE__ ) . '/src/'`) is correct for this checkout. Set `DB_NAME`,
`DB_USER`, `DB_PASSWORD`, and `DB_HOST` to a disposable MySQL test database.
The test suite drops tables with its configured prefix, so never point it at a
production or shared database. This test configuration is separate from
WordPress's regular `wp-config.php`; no regular `wp-config.php` is needed.

Run the suite from the plugin repository root:

```sh
vendor/bin/phpunit -c phpunit.xml.dist
```

If your WordPress development checkout is elsewhere, set `WP_TESTS_DIR` to its
`tests/phpunit/` directory before running PHPUnit. The PHPUnit executable may
also be supplied globally as `phpunit`. If the test library or database is
unavailable, the test command cannot bootstrap WordPress and will report that
setup requirement.

- Add a QUnit browser test page for the plugin JavaScript, with jQuery and DOM
  fixtures matching the plugin markup.
- Keep API response fixtures for camps, art, schedule, and music in test data.
- Clear plugin transients between tests and restore WordPress options, hooks, and
  globals during teardown.

The tests below describe required behavior; they are not claims that the tests
or test runners already exist.

## 1. PHP and WordPress tests (PHPUnit)

### API retrieval: `DisplayDustData::get_data()`

- For each supported type (`camps`, `art`, `schedule`, and `music`), return
  decoded fixture data after a mocked 200 response.
- Construct the expected endpoint from the event name and type. Verify that an
  explicit event name takes precedence over the configured option.
- Return `WP_Error` for unsupported types, missing event names, HTTP errors
  (including 404 and 500), transport/network errors, invalid JSON, and valid JSON
  that is not an object or array.
- Verify valid results are cached for one hour, a cache hit avoids a second HTTP
  request, and different event names/types do not share cache entries.
- Verify camps and art sort by name; schedule and music sort by camp (including
  `hosted_by_camp`) and then title.
- Verify only schedule and music are filtered by configured start/end dates,
  including boundary dates, missing bounds, repeat-day behavior, and configured
  timezone. Camps and art should not be date-filtered.

### Shortcodes and rendered markup

- Exercise `[dust_camps]`, `[dust_art]`, `[dust_schedule]`, and `[dust_music]`
  with fixture data, empty results, API errors, and no configured event name.
- Check default and explicit shortcode attributes: event name, grid/list layout,
  `show_coordinates`, `show_images`, and `per_page`. In particular, verify the
  documented default of showing coordinates and that `per_page` limits output.
- Verify camp/art descriptions, names, image URLs, coordinates, and links are
  escaped or sanitized for their output context; malformed optional pin data
  must not produce warnings or break rendering.
- Verify schedule export-button options (`all`/`true`, `none`/`false`, `csv`,
  `ics`, and comma-separated values), legacy `show_export_button`, and
  `display="tabs"`.
- Verify the two export-button shortcodes honor their `event_name` and `text`
  attributes and escape generated HTML.
- Test the public theme functions
  `lunacode_display_dust_data_get()` and
  `lunacode_display_dust_data_render()` against success, empty data, and errors.

### Plugin settings and script setup

- Verify the settings page registers the event name, start date, end date, and
  timezone options and renders their current values safely.
- Verify the plugin enqueues its frontend assets and localizes the expected
  `dust_events_ajax.ajax_url` and nonce for the frontend scripts.
- Verify missing settings use documented fallbacks, including the WordPress
  timezone when no event timezone is configured.

### Schedule tabs and event grouping

- Cover empty and populated tab data, recurring/repeating events, multi-day
  events, missing day/title fields, and duplicate day labels.
- Verify rendered tabs and panels have unique IDs and matching ARIA references,
  exactly one initially selected tab, and matching selected/visible panel state.
- Verify each tab's events are ordered all-day first, then start time, then title.
- Verify repeat-day helpers exclude the current day, return distinct sorted
  days, and honor configured date bounds.

### AJAX handlers

- For `get_dust_data`, verify valid nonce behavior for logged-in and public
  requests, rejection of invalid/missing nonces, and handling of missing or
  invalid `type`.
- Verify optional `event_name` is sanitized and passed through, and response
  shape is `{ success: true, data: [...] }` with a JSON list for each supported
  type; API errors must produce an error response.
- Verify the registered action names match the frontend request names.
- Do not set an arbitrary response-time limit in unit tests. If latency is a
  requirement, define an explicit budget and measure it in a separate benchmark
  with controlled network conditions.

### Schedule exports

- Verify CSV headers, row values, image URL handling, descriptions, and valid CSV
  quoting for commas, quotes, and newlines.
- Verify ICS calendar/event structure, line endings, timezone/VTIMEZONE output,
  start/end-time handling (including missing end-time fallback), and escaping
  of backslashes, commas, semicolons, and newlines.
- Verify both export handlers check the nonce, use the requested/configured
  event, and report API failures without returning a success-shaped download.

### Template behavior

- Render `page-lunacode-display-dust-data.php` with WordPress template helpers
  stubbed or in a WordPress integration test.
- Verify the featured-camp section uses `lunacode_display_dust_data_get('camps')`
  and displays at most the first six camps; verify empty/error data uses the
  fallback message.
- Verify the dynamic loader uses the plugin's localized `dust_events_ajax`
  configuration and `get_dust_data` action with `type=camps`.

## 2. JavaScript browser tests (QUnit)

### `lunacode-display-dust-data.js`

- Verify search filtering for camps, art, schedule, and music using name,
  description, and camp fields; check clearing search and result counts.
- Verify `dustItemClicked` fires with the expected UID/name/type for click,
  Enter, and Space, but not for other keys or clicks on links.
- Verify image-error placeholders and lazy-loading behavior with and without
  `IntersectionObserver`.
- Verify schedule tab click behavior updates active classes, `aria-selected`,
  `tabindex`, panel visibility, and the screen-reader announcement.
- Verify arrow keys wrap correctly and Home/End move focus to first/last tabs;
  Enter/Space activate the focused tab.
- Verify the public refresh, lookup, highlight, display-toggle, and tab-switch
  methods handle valid items/containers and missing ones without exceptions.

### Camps page dynamic loader

- Verify loading text/button disabled state, success with data, empty results,
  failed requests, and re-enabling/restoring the button in every completion path.
- Verify refresh repeats the request, and grid/list selection applies the chosen
  layout to loaded results.
- Verify camps render using the same `.dust-camps-*` class names as the plugin,
  so search, result counts, styling, and item events work on dynamically loaded
  content.
- Verify names, descriptions, UIDs, image URLs, and map values containing HTML
  or quote characters are rendered as text/escaped attributes, not executable
  markup. Malformed/missing pins must not throw.
- Verify numeric latitude/longitude are represented correctly in Google Maps
  links and links opened in a new tab include safe `rel` attributes.

### `schedule-ics-button.js`

- Verify ICS and CSV requests send the configured URL, action, nonce, and event
  name; buttons disable while pending and restore their original text afterward.
- Verify successful responses trigger a download with the expected MIME type and
  a failed request does not initiate a download.

## 3. Cross-file integration tests

- With mocked Dust fixtures, exercise API fetch -> cache/date/sort processing ->
  shortcode or template rendering -> browser filtering.
- Assert representative camp names, descriptions, image URLs, and coordinates
  match the fixture after output escaping/formatting. Assert schedule/music date
  filtering and schedule tabs match the same fixture.
- Exercise the browser AJAX request against the WordPress handler and confirm
  the action name, nonce, response shape, error handling, and UI state agree.
- Keep live Dust API checks out of required CI tests; if desired, run them
  separately as optional smoke tests.

## Coverage boundaries

Do not spend plugin test coverage on WordPress core/theme behavior such as
whether `get_header()` works. Test only this template's use of WordPress hooks,
plugin functions, markup, and failure states. Do not test undocumented
performance thresholds until the acceptable budget is specified.
