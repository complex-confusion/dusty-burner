/**
 * @file Lunacode Display Dust Data plugin's export buttons for schedule lists
 */
// Handle ICS and CSV export button clicks
document.addEventListener("DOMContentLoaded", function () {
  // Only add listeners if export buttons exist
  if (!document.querySelector(".dust-ics-export-btn, .dust-csv-export-btn")) return;

  function handleExport(btn, action, filename) {
    const originalText = btn.textContent;
    const eventName = btn.dataset.event || "";

    btn.disabled = true;
    btn.textContent = "Generating...";

    const formData = new FormData();
    formData.append("action", action);
    formData.append("nonce", dust_events_ajax.nonce);
    formData.append("event_name", eventName);

    fetch(dust_events_ajax.ajax_url, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.blob())
      .then((data) => {
        const url = window.URL.createObjectURL(data);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        a.click();
        window.URL.revokeObjectURL(url);
      })
      .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
      });
  }

  const exportHandlers = {
    "dust-ics-export-btn": { action: "export_schedule_ics", filename: "schedule.ics" },
    "dust-csv-export-btn": { action: "export_schedule_csv", filename: "schedule.csv" }
  };

  document.addEventListener("click", function (e) {
    for (const [className, config] of Object.entries(exportHandlers)) {
      if (e.target.classList.contains(className)) {
        handleExport(e.target, config.action, config.filename);
        break;
      }
    }
  });
});
