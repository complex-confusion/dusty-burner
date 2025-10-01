<?php
/**
 * Example usage of schedule with ICS export
 */

// In your page template or shortcode:
?>

<div class="schedule-section">
    <h2>Event Schedule</h2>

    <!-- The export button will be automatically added by JavaScript -->
    <?php echo do_shortcode('[dust_schedule layout="list"]'); ?>
</div>

<!-- Or manually add the button: -->
<button id="manual-ics-export" class="dust-ics-export-btn">📅 Export to Calendar</button>

<script>
jQuery(document).ready(function($) {
    $('#manual-ics-export').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Generating...');

        // Create form and submit to trigger download
        var form = $('<form>', {
            method: 'POST',
            action: dust_events_ajax.ajax_url,
            target: '_blank'
        });

        form.append($('<input>', {type: 'hidden', name: 'action', value: 'export_schedule_ics'}));
        form.append($('<input>', {type: 'hidden', name: 'nonce', value: dust_events_ajax.nonce}));
        form.append($('<input>', {type: 'hidden', name: 'event_name', value: ''}));

        $('body').append(form);
        form.submit();
        form.remove();

        setTimeout(function() {
            $btn.prop('disabled', false).text('📅 Export to Calendar');
        }, 1000);
    });
});
</script>