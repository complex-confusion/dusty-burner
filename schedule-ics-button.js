// Add ICS export button to schedule displays
jQuery(document).ready(function ($) {
  // Add export button after schedule containers
  $(".dust-schedule-container").each(function () {
    var $container = $(this);
    var $button = $('<button class="dust-ics-export-btn">📅 Export to Calendar</button>');

    $container.before($button);

    $button.on("click", function () {
      var $btn = $(this);
      $btn.prop("disabled", true).text("Generating...");

      $.ajax({
        url: dust_events_ajax.ajax_url,
        type: "POST",
        data: {
          action: "export_schedule_ics",
          nonce: dust_events_ajax.nonce,
          event_name: "",
        },
        xhrFields: {
          responseType: "blob",
        },
        success: function (data) {
          var blob = new Blob([data], { type: "text/calendar" });
          var url = window.URL.createObjectURL(blob);
          var a = document.createElement("a");
          a.href = url;
          a.download = "schedule.ics";
          a.click();
          window.URL.revokeObjectURL(url);
        },
        complete: function () {
          $btn.prop("disabled", false).text("📅 Export to Calendar");
        },
      });
    });
  });
});
