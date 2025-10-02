/**
 * LunaCode Display Dust Data JavaScript
 */
(function ($) {
  "use strict";

  // Initialize when document is ready
  $(document).ready(function () {
    LunaCode.DisplayDustData.init();
  });

  var LunaCode = {
    DisplayDustData: {
      init: function () {
        this.bindEvents();
        this.handleImageErrors();
        this.setupLazyLoading();
        this.setupSearch();
        this.setupScheduleTabs();
      },

      bindEvents: function () {
        // Handle coordinate clicks for better UX
        $(document).on("click", ".dust-camps-coordinates, .dust-art-coordinates", function (e) {
          var coordinates = $(this).text();
          console.log("Coordinates clicked:", coordinates);
        });

        // Handle item clicks for all types
        $(document).on(
          "click",
          ".dust-camps-item, .dust-art-item, .dust-schedule-item, .dust-music-item",
          function (e) {
            // Don't trigger if clicking on links
            if ($(e.target).is("a") || $(e.target).closest("a").length) {
              return;
            }

            var $item = $(this);
            var uid = $item.data("uid");
            var type = LunaCode.DisplayDustData.getItemType($item);
            var name = $item.find(".dust-" + type + "-name, .dust-" + type + "-title").text();

            // Emit custom event for other scripts to listen to
            $(document).trigger("dustItemClicked", {
              uid: uid,
              name: name,
              type: type,
              element: this,
            });

            console.log("Item clicked:", name, uid, type);
          }
        );
      },

      getItemType: function ($item) {
        if ($item.hasClass("dust-camps-item")) return "camps";
        if ($item.hasClass("dust-art-item")) return "art";
        if ($item.hasClass("dust-schedule-item")) return "schedule";
        if ($item.hasClass("dust-music-item")) return "music";
        return "unknown";
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
          var type = $(this).attr("id").replace("dust-", "").replace("-search", "");

          searchTimeout = setTimeout(function () {
            LunaCode.DisplayDustData.filterItems(searchTerm, type);
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
          var camp = $item
            .find(".dust-" + type + "-camp")
            .text()
            .toLowerCase();

          if (
            name.includes(searchTerm) ||
            description.includes(searchTerm) ||
            camp.includes(searchTerm) ||
            searchTerm === ""
          ) {
            $item.show().removeClass("filtered-out");
          } else {
            $item.hide().addClass("filtered-out");
          }
        });

        // Update results count
        this.updateResultsCount(type);
      },

      updateResultsCount: function (type) {
        type = type || "camps";
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
        $container.html('<div class="dust-' + type + '-loading">Loading ' + type + "...</div>");

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
            $container.html('<div class="dust-' + type + '-error">Failed to load ' + type + " data.</div>");
          },
        });
      },

      // Method to get item data for external use
      getItemByUid: function (uid, type) {
        type = type || "camps";
        var $item = $(".dust-" + type + '-item[data-uid="' + uid + '"]');
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
        type = type || "camps";
        $(".dust-" + type + "-item").removeClass("highlighted");
        var $item = $(".dust-" + type + '-item[data-uid="' + uid + '"]');
        if ($item.length) {
          $item.addClass("highlighted");
          $item[0].scrollIntoView({ behavior: "smooth", block: "center" });
        }
      },

      // Setup schedule tabs functionality
      setupScheduleTabs: function () {
        // Handle display toggle (tabs vs all events)
        $(document).on('click', '.dust-schedule-toggle-btn', function(e) {
          e.preventDefault();
          var $btn = $(this);
          var target = $btn.data('target');
          var $container = $btn.closest('.dust-schedule-tabs-container');
          
          // Update button states
          $btn.siblings('.dust-schedule-toggle-btn').removeClass('active');
          $btn.addClass('active');
          
          if (target === 'tabs') {
            $container.find('.dust-schedule-tab-nav').show();
            $container.find('.dust-schedule-tab-content').show();
            $container.find('.dust-schedule-all-events').hide();
          } else {
            $container.find('.dust-schedule-tab-nav').hide();
            $container.find('.dust-schedule-tab-content').hide();
            $container.find('.dust-schedule-all-events').show();
          }
        });
        
        // Handle tab switching with accessibility
        $(document).on('click', '.dust-schedule-tab-btn', function(e) {
          e.preventDefault();
          LunaCode.DisplayDustData.activateTab($(this));
        });
        
        // Handle keyboard navigation for tabs
        $(document).on('keydown', '.dust-schedule-tab-btn', function(e) {
          var $tabs = $(this).closest('.dust-schedule-tab-nav').find('.dust-schedule-tab-btn');
          var currentIndex = $tabs.index(this);
          var $target = null;
          
          switch(e.which) {
            case 37: // Left arrow
            case 38: // Up arrow
              e.preventDefault();
              $target = currentIndex > 0 ? $tabs.eq(currentIndex - 1) : $tabs.last();
              break;
            case 39: // Right arrow
            case 40: // Down arrow
              e.preventDefault();
              $target = currentIndex < $tabs.length - 1 ? $tabs.eq(currentIndex + 1) : $tabs.first();
              break;
            case 36: // Home
              e.preventDefault();
              $target = $tabs.first();
              break;
            case 35: // End
              e.preventDefault();
              $target = $tabs.last();
              break;
            case 13: // Enter
            case 32: // Space
              e.preventDefault();
              LunaCode.DisplayDustData.activateTab($(this));
              return;
          }
          
          if ($target) {
            // Update tabindex and focus
            $tabs.attr('tabindex', '-1');
            $target.attr('tabindex', '0').focus();
          }
        });
      },
      
      // Activate a tab with proper accessibility handling
      activateTab: function($btn) {
        var tabId = $btn.data('tab');
        var $container = $btn.closest('.dust-schedule-tabs-container');
        var $tabNav = $btn.closest('.dust-schedule-tab-nav');
        
        // Update ARIA attributes and visual states
        $tabNav.find('.dust-schedule-tab-btn')
          .removeClass('active')
          .attr('aria-selected', 'false')
          .attr('tabindex', '-1');
        
        $btn.addClass('active')
          .attr('aria-selected', 'true')
          .attr('tabindex', '0');
        
        // Show corresponding tab content and focus it
        var $panels = $container.find('.dust-schedule-tab-pane');
        $panels.removeClass('active').hide();
        
        var $activePanel = $container.find('.dust-schedule-tab-pane[data-tab="' + tabId + '"]');
        $activePanel.addClass('active').show();
        
        // Move focus to the start of the new content for screen readers
        $activePanel.focus();
      },

      // Public method to toggle schedule display
      toggleScheduleDisplay: function(containerId, displayType) {
        var $container = $('#' + containerId);
        if ($container.length) {
          var $btn = $container.find('.dust-schedule-toggle-btn[data-target="' + displayType + '"]');
          if ($btn.length) {
            $btn.click();
          }
        }
      },

      // Public method to switch to specific tab
      switchScheduleTab: function(containerId, tabId) {
        var $container = $('#' + containerId);
        if ($container.length) {
          var $btn = $container.find('.dust-schedule-tab-btn[data-tab="' + tabId + '"]');
          if ($btn.length) {
            LunaCode.DisplayDustData.activateTab($btn);
          }
        }
      },
    },
  };

  // Make LunaCode available globally with safe property assignment
  window.LunaCode = Object.assign({}, window.LunaCode, {
    DisplayDustData: LunaCode.DisplayDustData,
  });

  // Global convenience functions for schedule tabs
  window.dustToggleScheduleDisplay = function(containerId, displayType) {
    LunaCode.DisplayDustData.toggleScheduleDisplay(containerId, displayType);
  };

  window.dustSwitchScheduleTab = function(containerId, tabId) {
    LunaCode.DisplayDustData.switchScheduleTab(containerId, tabId);
  };

  // Custom events that other developers can listen to
  $(document).on("dustItemClicked", function (event, data) {
    // Example: console.log('An item was clicked:', data.name, data.type);
  });
})(jQuery);
