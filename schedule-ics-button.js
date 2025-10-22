// Handle ICS and CSV export button clicks
document.addEventListener('DOMContentLoaded', function() {
  // Handle clicks on ICS export buttons
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('dust-ics-export-btn')) {
      const btn = e.target;
      const originalText = btn.textContent;
      const eventName = btn.dataset.event || '';

      btn.disabled = true;
      btn.textContent = 'Generating...';

      const formData = new FormData();
      formData.append('action', 'export_schedule_ics');
      formData.append('nonce', dust_events_ajax.nonce);
      formData.append('event_name', eventName);

      fetch(dust_events_ajax.ajax_url, {
        method: 'POST',
        body: formData
      })
      .then(response => response.blob())
      .then(data => {
        const url = window.URL.createObjectURL(data);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'schedule.ics';
        a.click();
        window.URL.revokeObjectURL(url);
      })
      .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
      });
    }

    // Handle clicks on CSV export buttons
    if (e.target.classList.contains('dust-csv-export-btn')) {
      const btn = e.target;
      const originalText = btn.textContent;
      const eventName = btn.dataset.event || '';

      btn.disabled = true;
      btn.textContent = 'Generating...';

      const formData = new FormData();
      formData.append('action', 'export_schedule_csv');
      formData.append('nonce', dust_events_ajax.nonce);
      formData.append('event_name', eventName);

      fetch(dust_events_ajax.ajax_url, {
        method: 'POST',
        body: formData
      })
      .then(response => response.blob())
      .then(data => {
        const url = window.URL.createObjectURL(data);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'schedule.csv';
        a.click();
        window.URL.revokeObjectURL(url);
      })
      .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
      });
    }
  });
});
