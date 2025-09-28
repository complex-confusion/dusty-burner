/**
 * Dust Events Camps JavaScript
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    DustCamps.init();
  });

  var DustCamps = {
    init: function () {
      this.bindEvents();
      this.handleImageErrors();
      this.setupLazyLoading();
      this.setupSearch();
    },

    bindEvents: function () {
      // Handle coordinate clicks for better UX
      $(document).on("click", ".dust-camp-coordinates", function (e) {
        var coordinates = $(this).text();
        console.log("Camp coordinates clicked:", coordinates);
      });

      // Handle camp item clicks
      $(document).on("click", ".dust-camp-item", function (e) {
        // Don't trigger if clicking on links
        if ($(e.target).is("a") || $(e.target).closest("a").length) {
          return;
        }

        var uid = $(this).data("uid");
        var name = $(this).find(".dust-camp-name").text();

        // Emit custom event for other scripts to listen to
        $(document).trigger("dustCampClicked", {
          uid: uid,
          name: name,
          element: this,
        });

        console.log("Camp clicked:", name, uid);
      });
    },

    handleImageErrors: function () {
      // Handle broken images
      $(".dust-camp-image img").on("error", function () {
        var $img = $(this);
        var $container = $img.closest(".dust-camp-image");

        // Create placeholder
        var placeholder =
          '<div class="dust-camp-image-placeholder">' +
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

        $(".dust-camp-image img.lazy").each(function () {
          imageObserver.observe(this);
        });
      }
    },

    setupSearch: function () {
      // Add search functionality if search input exists
      var $searchInput = $("#dust-camps-search");
      if ($searchInput.length === 0) return;

      var searchTimeout;

      $searchInput.on("input", function () {
        clearTimeout(searchTimeout);
        var searchTerm = $(this).val().toLowerCase();

        searchTimeout = setTimeout(function () {
          DustCamps.filterCamps(searchTerm);
        }, 300);
      });
    },

    filterCamps: function (searchTerm) {
      $(".dust-camp-item").each(function () {
        var $camp = $(this);
        var name = $camp.find(".dust-camp-name").text().toLowerCase();
        var description = $camp.find(".dust-camp-description").text().toLowerCase();

        if (name.includes(searchTerm) || description.includes(searchTerm) || searchTerm === "") {
          $camp.show().removeClass("filtered-out");
        } else {
          $camp.hide().addClass("filtered-out");
        }
      });

      // Update results count
      this.updateResultsCount();
    },

    updateResultsCount: function () {
      var total = $(".dust-camp-item").length;
      var visible = $(".dust-camp-item:visible").length;

      var $counter = $(".dust-camps-count");
      if ($counter.length) {
        if (visible === total) {
          $counter.html("Showing all " + total + " camps");
        } else {
          $counter.html("Showing " + visible + " of " + total + " camps");
        }
      }
    },

    // Public method to refresh camps data
    refreshCamps: function (eventName) {
      var $container = $(".dust-camps-container");
      $container.html('<div class="dust-camps-loading">Loading camps...</div>');

      $.ajax({
        url: dust_camps_ajax.ajax_url,
        type: "POST",
        data: {
          action: "get_camps_data",
          event_name: eventName || "",
          nonce: dust_camps_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            // You would need to implement server-side rendering here
            // or build the HTML in JavaScript
            location.reload(); // Simple solution
          } else {
            $container.html('<div class="dust-camps-error">Error: ' + response.data + "</div>");
          }
        },
        error: function () {
          $container.html('<div class="dust-camps-error">Failed to load camps data.</div>');
        },
      });
    },

    // Method to get camp data for external use
    getCampByUid: function (uid) {
      var $camp = $('.dust-camp-item[data-uid="' + uid + '"]');
      if ($camp.length) {
        return {
          uid: uid,
          name: $camp.find(".dust-camp-name").text(),
          description: $camp.find(".dust-camp-description").text(),
          element: $camp[0],
        };
      }
      return null;
    },

    // Method to highlight a specific camp
    highlightCamp: function (uid) {
      $(".dust-camp-item").removeClass("highlighted");
      var $camp = $('.dust-camp-item[data-uid="' + uid + '"]');
      if ($camp.length) {
        $camp.addClass("highlighted");
        $camp[0].scrollIntoView({ behavior: "smooth", block: "center" });
      }
    },
  };

  // Make DustCamps available globally
  window.DustCamps = DustCamps;

  // Custom events that other developers can listen to
  $(document).on("dustCampClicked", function (event, data) {
    // Example: console.log('A camp was clicked:', data.name);
  });
})(jQuery);

// CSS for additional interactive features
var additionalStyles = `
<style>
.dust-camp-image-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    background: #f5f5f5;
    color: #999;
}

.dust-camp-image-placeholder p {
    margin: 8px 0 0 0;
    font-size: 12px;
}

.dust-camp-item.highlighted {
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.3);
}

.dust-camp-item.filtered-out {
    display: none;
}

.dust-camps-search-container {
    margin-bottom: 20px;
}

.dust-camps-search-container input {
    width: 100%;
    max-width: 400px;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.dust-camps-search-container input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.dust-camp-item {
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
