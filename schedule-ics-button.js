// Handle ICS export button clicks
jQuery(document).ready(function ($) {
  // Handle clicks on ICS export buttons
  $(document).on("click", ".dust-ics-export-btn", function () {
    var $btn = $(this);
    var originalText = $btn.text();
    var eventName = $btn.data("event") || "";

    $btn.prop("disabled", true).text("Generating...");

    $.ajax({
      url: dust_events_ajax.ajax_url,
      type: "POST",
      data: {
        action: "export_schedule_ics",
        nonce: dust_events_ajax.nonce,
        event_name: eventName,
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
        $btn.prop("disabled", false).text(originalText);
      },
    });
  });
});
