/**
 * Dust Events JavaScript
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    DustEvents.init();
  });

  var DustEvents = {
    init: function () {
      this.bindEvents();
      this.handleImageErrors();
      this.setupLazyLoading();
      this.setupSearch();
    },

    bindEvents: function () {
      // Handle coordinate clicks for better UX
      $(document).on("click", ".dust-camps-coordinates, .dust-art-coordinates", function (e) {
        var coordinates = $(this).text();
        console.log("Coordinates clicked:", coordinates);
      });

      // Handle item clicks for all types
      $(document).on("click", ".dust-camps-item, .dust-art-item, .dust-schedule-item, .dust-music-item", function (e) {
        // Don't trigger if clicking on links
        if ($(e.target).is("a") || $(e.target).closest("a").length) {
          return;
        }

        var $item = $(this);
        var uid = $item.data("uid");
        var type = DustEvents.getItemType($item);
        var name = $item.find(".dust-" + type + "-name, .dust-" + type + "-title").text();

        // Emit custom event for other scripts to listen to
        $(document).trigger("dustItemClicked", {
          uid: uid,
          name: name,
          type: type,
          element: this,
        });

        console.log("Item clicked:", name, uid, type);
      });
    },

    getItemType: function ($item) {
      if ($item.hasClass('dust-camps-item')) return 'camps';
      if ($item.hasClass('dust-art-item')) return 'art';
      if ($item.hasClass('dust-schedule-item')) return 'schedule';
      if ($item.hasClass('dust-music-item')) return 'music';
      return 'unknown';
    },

    handleImageErrors: function () {
      // Handle broken images for all types
      $(".dust-camps-image img, .dust-art-image img, .dust-schedule-image img").on("error", function () {
        var $img = $(this);
        var $container = $img.closest(".dust-camps-image, .dust-art-image, .dust-schedule-image");

        // Create placeholder
        var placeholder =
          '<div class="dust-events-image-placeholder">' +
          '<svg width="60" height="60" viewBox="0 0 24 24" fill="#ccc">' +
          '<path d="M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19M19,19H5V5H19V19Z"/>' +
          "</svg>" +
          "<p>No Image</p>" +
          "</div>";

        $container.html(placeholder);
      });
    },

    setupLazyLoading: function () {
      // Simple lazy loading for images
      if ("IntersectionObserver" in window) {
        var imageObserver = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              var img = entry.target;
              if (img.dataset.src) {
                img.src = img.dataset.src;
                img.classList.remove("lazy");
                imageObserver.unobserve(img);
              }
            }
          });
        });

        $(".dust-camps-image img.lazy, .dust-art-image img.lazy, .dust-schedule-image img.lazy").each(function () {
          imageObserver.observe(this);
        });
      }
    },

    setupSearch: function () {
      // Add search functionality for all types
      var $searchInputs = $("#dust-camps-search, #dust-art-search, #dust-schedule-search, #dust-music-search");
      if ($searchInputs.length === 0) return;

      var searchTimeout;

      $searchInputs.on("input", function () {
        clearTimeout(searchTimeout);
        var searchTerm = $(this).val().toLowerCase();
        var type = $(this).attr('id').replace('dust-', '').replace('-search', '');

        searchTimeout = setTimeout(function () {
          DustEvents.filterItems(searchTerm, type);
        }, 300);
      });
    },

    filterItems: function (searchTerm, type) {
      var itemSelector = ".dust-" + type + "-item";
      var nameSelector = ".dust-" + type + "-name, .dust-" + type + "-title";
      var descSelector = ".dust-" + type + "-description";
      
      $(itemSelector).each(function () {
        var $item = $(this);
        var name = $item.find(nameSelector).text().toLowerCase();
        var description = $item.find(descSelector).text().toLowerCase();
        var camp = $item.find(".dust-" + type + "-camp").text().toLowerCase();

        if (name.includes(searchTerm) || description.includes(searchTerm) || camp.includes(searchTerm) || searchTerm === "") {
          $item.show().removeClass("filtered-out");
        } else {
          $item.hide().addClass("filtered-out");
        }
      });

      // Update results count
      this.updateResultsCount(type);
    },

    updateResultsCount: function (type) {
      type = type || 'camps';
      var itemSelector = ".dust-" + type + "-item";
      var counterSelector = ".dust-" + type + "-count";
      
      var total = $(itemSelector).length;
      var visible = $(itemSelector + ":visible").length;

      var $counter = $(counterSelector);
      if ($counter.length) {
        if (visible === total) {
          $counter.html("Showing all " + total + " " + type);
        } else {
          $counter.html("Showing " + visible + " of " + total + " " + type);
        }
      }
    },

    // Public method to refresh data for any type
    refreshData: function (type, eventName) {
      var $container = $(".dust-" + type + "-container");
      $container.html('<div class="dust-' + type + '-loading">Loading ' + type + '...</div>');

      $.ajax({
        url: dust_events_ajax.ajax_url,
        type: "POST",
        data: {
          action: "get_dust_data",
          type: type,
          event_name: eventName || "",
          nonce: dust_events_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            // You would need to implement server-side rendering here
            // or build the HTML in JavaScript
            location.reload(); // Simple solution
          } else {
            $container.html('<div class="dust-' + type + '-error">Error: ' + response.data + "</div>");
          }
        },
        error: function () {
          $container.html('<div class="dust-' + type + '-error">Failed to load ' + type + ' data.</div>');
        },
      });
    },

    // Method to get item data for external use
    getItemByUid: function (uid, type) {
      type = type || 'camps';
      var $item = $('.dust-' + type + '-item[data-uid="' + uid + '"]');
      if ($item.length) {
        var nameSelector = ".dust-" + type + "-name, .dust-" + type + "-title";
        var descSelector = ".dust-" + type + "-description";
        
        return {
          uid: uid,
          type: type,
          name: $item.find(nameSelector).text(),
          description: $item.find(descSelector).text(),
          element: $item[0],
        };
      }
      return null;
    },

    // Method to highlight a specific item
    highlightItem: function (uid, type) {
      type = type || 'camps';
      $(".dust-" + type + "-item").removeClass("highlighted");
      var $item = $('.dust-' + type + '-item[data-uid="' + uid + '"]');
      if ($item.length) {
        $item.addClass("highlighted");
        $item[0].scrollIntoView({ behavior: "smooth", block: "center" });
      }
    },
  };

  // Make DustEvents available globally
  window.DustEvents = DustEvents;
  
  // Backward compatibility
  window.DustCamps = {
    init: function() { return DustEvents.init(); },
    refreshCamps: function(eventName) { return DustEvents.refreshData('camps', eventName); },
    getCampByUid: function(uid) { return DustEvents.getItemByUid(uid, 'camps'); },
    highlightCamp: function(uid) { return DustEvents.highlightItem(uid, 'camps'); },
    filterCamps: function(searchTerm) { return DustEvents.filterItems(searchTerm, 'camps'); }
  };

  // Custom events that other developers can listen to
  $(document).on("dustItemClicked", function (event, data) {
    // Example: console.log('An item was clicked:', data.name, data.type);
  });
  
  // Backward compatibility event
  $(document).on("dustItemClicked", function (event, data) {
    if (data.type === 'camps') {
      $(document).trigger("dustCampClicked", data);
    }
  });
})(jQuery);

// CSS for additional interactive features
var additionalStyles = `
<style>
.dust-events-image-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    background: #f5f5f5;
    color: #999;
}

.dust-events-image-placeholder p {
    margin: 8px 0 0 0;
    font-size: 12px;
}

.dust-camps-item.highlighted,
.dust-art-item.highlighted,
.dust-schedule-item.highlighted,
.dust-music-item.highlighted {
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.3);
}

.dust-camps-item.filtered-out,
.dust-art-item.filtered-out,
.dust-schedule-item.filtered-out,
.dust-music-item.filtered-out {
    display: none;
}

.dust-camps-search-container,
.dust-art-search-container,
.dust-schedule-search-container,
.dust-music-search-container {
    margin-bottom: 20px;
}

.dust-camps-search-container input,
.dust-art-search-container input,
.dust-schedule-search-container input,
.dust-music-search-container input {
    width: 100%;
    max-width: 400px;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.dust-camps-search-container input:focus,
.dust-art-search-container input:focus,
.dust-schedule-search-container input:focus,
.dust-music-search-container input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.dust-camps-item,
.dust-art-item,
.dust-schedule-item,
.dust-music-item {
    animation: fadeIn 0.5s ease-out;
}
</style>
`;

// Add the additional styles to the page
if (typeof document !== "undefined") {
  document.addEventListener("DOMContentLoaded", function () {
    document.head.insertAdjacentHTML("beforeend", additionalStyles);
  });
}
