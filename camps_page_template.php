<?php
/**
 * Template Name: Dust Events Camps Page
 *
 * This template demonstrates how to display camps using the Dust Events API
 * You can copy this to your theme as page-camps.php or create a custom page template
 */

get_header(); ?>

<div class="camps-page-container">
    <div class="container">

        <?php while (have_posts()) : the_post(); ?>

            <header class="page-header">
                <h1 class="page-title"><?php the_title(); ?></h1>
                <?php if (get_the_content()) : ?>
                    <div class="page-intro">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>
            </header>

            <!-- Search functionality -->
            <div class="dust-camps-search-container">
                <input type="text" id="dust-camps-search" placeholder="Search camps by name or description...">
                <div class="dust-camps-count"></div>
            </div>

            <!-- Method 1: Using Shortcode (Easiest) -->
            <div class="camps-section">
                <h2>All Camps</h2>

                <?php
                // Display camps using shortcode - this is the easiest method
                echo do_shortcode('[dust_camps layout="grid" show_coordinates="true" show_images="true"]');
                ?>
            </div>

            <!-- Method 2: Using PHP Functions (For theme developers) -->
            <?php if (function_exists('dust_get_camps')) : ?>
                <div class="camps-section-advanced">
                    <h2>Featured Camps (Custom Layout)</h2>

                    <?php
                    $camps = dust_get_camps(); // Get camps data

                    if (!is_wp_error($camps) && !empty($camps)) :
                        // Show only first 6 camps in a custom layout
                        $featured_camps = array_slice($camps, 0, 6);
                    ?>

                        <div class="featured-camps-grid">
                            <?php foreach ($featured_camps as $camp) :
                                $name = esc_html($camp['name']);
                                $description = wp_trim_words(wp_kses_post($camp['description']), 20);
                                $image_url = !empty($camp['imageUrl']) ? 'https://data.dust.events/' . $camp['imageUrl'] : null;
                                $pin_data = json_decode($camp['pin'], true);
                            ?>

                                <div class="featured-camp-card">
                                    <?php if ($image_url) : ?>
                                        <div class="featured-camp-image">
                                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($name); ?>" />
                                        </div>
                                    <?php endif; ?>

                                    <div class="featured-camp-content">
                                        <h3><?php echo $name; ?></h3>
                                        <p><?php echo $description; ?></p>

                                        <?php if ($pin_data && isset($pin_data['lat'], $pin_data['lng'])) : ?>
                                            <div class="camp-location">
                                                <small>📍 Lat: <?php echo esc_html($pin_data['lat']); ?>, Lng: <?php echo esc_html($pin_data['lng']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        </div>

                    <?php else : ?>
                        <p>No featured camps available at this time.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Method 3: AJAX Loading with Custom Controls -->
            <div class="camps-ajax-section">
                <h2>Dynamic Camp Loading</h2>
                <div class="camp-controls">
                    <button id="load-camps-btn" class="btn btn-primary">Load Camps</button>
                    <button id="refresh-camps-btn" class="btn btn-secondary">Refresh</button>
                    <select id="camp-layout-selector">
                        <option value="grid">Grid Layout</option>
                        <option value="list">List Layout</option>
                    </select>
                </div>
                <div id="dynamic-camps-container"></div>
            </div>

        <?php endwhile; ?>

    </div>
</div>

<style>
  /* Custom styles for the camps page template */
  .camps-page-container {
      padding: 40px 0;
  }

  .page-header {
      text-align: center;
      margin-bottom: 40px;
  }

  .page-intro {
      max-width: 700px;
      margin: 20px auto;
      font-size: 1.1em;
      line-height: 1.6;
      color: #666;
  }

  .camps-section {
      margin: 60px 0;
  }

  .camps-section h2 {
      margin-bottom: 30px;
      padding-bottom: 10px;
      border-bottom: 2px solid #007cba;
  }

  /* Featured camps custom layout */
  .featured-camps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin: 30px 0;
  }

  .featured-camp-card {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .featured-camp-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
  }

  .featured-camp-image {
      height: 160px;
      overflow: hidden;
  }

  .featured-camp-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
  }

  .featured-camp-content {
      padding: 20px;
  }

  .featured-camp-content h3 {
      margin: 0 0 10px 0;
      color: #333;
      font-size: 1.2em;
  }

  .featured-camp-content p {
      color: #666;
      margin: 0 0 15px 0;
      line-height: 1.5;
  }

  .camp-location small {
      color: #888;
      font-size: 0.85em;
  }

  /* AJAX Controls */
  .camp-controls {
      margin: 20px 0;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
      display: flex;
      gap: 15px;
      align-items: center;
      flex-wrap: wrap;
  }

  .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      transition: background-color 0.3s ease;
  }

  .btn-primary {
      background: #007cba;
      color: white;
  }

  .btn-primary:hover {
      background: #005a87;
  }

  .btn-secondary {
      background: #6c757d;
      color: white;
  }

  .btn-secondary:hover {
      background: #545b62;
  }

  #camp-layout-selector {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 5px;
      background: white;
  }

  #dynamic-camps-container {
      min-height: 200px;
      margin-top: 20px;
  }

  /* Responsive */
  @media (max-width: 768px) {
      .camps-page-container {
          padding: 20px 0;
      }

      .featured-camps-grid {
          grid-template-columns: 1fr;
      }

      .camp-controls {
          flex-direction: column;
          align-items: stretch;
      }

      .camp-controls > * {
          width: 100%;
          text-align: center;
      }
  }
</style>

<script>
jQuery(document).ready(function($) {

    // Handle dynamic loading
    $('#load-camps-btn').on('click', function() {
        var $btn = $(this);
        var $container = $('#dynamic-camps-container');

        $btn.prop('disabled', true).text('Loading...');
        $container.html('<div class="dust-camps-loading">Loading camps...</div>');

        $.ajax({
            url: dust_camps_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_camps_data',
                nonce: dust_camps_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // Build HTML from camp data
                    var html = buildCampsHTML(response.data, 'grid');
                    $container.html('<div class="dust-camps-container dust-camps-grid">' + html + '</div>');
                } else {
                    $container.html('<div class="dust-camps-no-results">No camps found.</div>');
                }
            },
            error: function() {
                $container.html('<div class="dust-camps-error">Failed to load camps.</div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Load Camps');
            }
        });
    });

    // Handle layout changes
    $('#camp-layout-selector').on('change', function() {
        var layout = $(this).val();
        var $container = $('#dynamic-camps-container .dust-camps-container');

        if ($container.length) {
            $container.removeClass('dust-camps-grid dust-camps-list').addClass('dust-camps-' + layout);
        }
    });

    // Handle refresh
    $('#refresh-camps-btn').on('click', function() {
        $('#load-camps-btn').click();
    });

    // Function to build camps HTML
    function buildCampsHTML(camps, layout) {
        var html = '';

        camps.forEach(function(camp) {
            var imageUrl = camp.imageUrl ? 'https://data.dust.events/' + camp.imageUrl : null;
            var pinData = camp.pin ? JSON.parse(camp.pin) : null;

            html += '<div class="dust-camp-item" data-uid="' + camp.uid + '">';

            if (imageUrl) {
                html += '<div class="dust-camp-image">';
                html += '<img src="' + imageUrl + '" alt="' + camp.name + '" loading="lazy" />';
                html += '</div>';
            }

            html += '<div class="dust-camp-content">';
            html += '<h3 class="dust-camp-name">' + camp.name + '</h3>';

            if (camp.description) {
                html += '<div class="dust-camp-description">' + camp.description + '</div>';
            }

            if (pinData) {
                html += '<div class="dust-camp-coordinates">';
                html += '<strong>Coordinates:</strong> ';

                if (pinData.lat && pinData.lng) {
                    html += 'GPS - Lat: ' + pinData.lat + ', Lng: ' + pinData.lng;
                    html += '<div class="dust-camp-map-link">';
                    html += '<a href="https://www.google.com/maps?q=' + pinData.lat + ',' + pinData.lng + '" target="_blank">View on Google Maps</a>';
                    html += '</div>';
                } else if (pinData.x && pinData.y) {
                    html += 'Map Position - X: ' + pinData.x + ', Y: ' + pinData.y;
                }

                html += '</div>';
            }

            html += '</div>'; // .dust-camp-content
            html += '</div>'; // .dust-camp-item
        });

        return html;
    }

    // Initialize search functionality
    DustCamps.updateResultsCount();
});
</script>

<?php get_footer(); ?>