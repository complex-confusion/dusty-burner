<?php
// Add to dust-events-camps.php constructor:
// add_action('wp_ajax_export_schedule_ics', array($this, 'export_schedule_ics'));
// add_action('wp_ajax_nopriv_export_schedule_ics', array($this, 'export_schedule_ics'));

public function export_schedule_ics() {
    check_ajax_referer('dust_events_nonce', 'nonce');
    
    $event_name = sanitize_text_field($_POST['event_name'] ?? get_option('dust_events_event_name'));
    $data = self::get_data('schedule', $event_name);
    
    if (is_wp_error($data)) {
        wp_die('Error loading schedule data');
    }
    
    $ics_content = $this->generate_ics($data, $event_name);
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $event_name . '-schedule.ics"');
    echo $ics_content;
    wp_die();
}

private function generate_ics($schedule_data, $event_name) {
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Dust Events//Schedule Export//EN\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    
    foreach ($schedule_data as $event) {
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . ($event['uid'] ?? uniqid()) . "@dust.events\r\n";
        $ics .= "SUMMARY:" . $this->escape_ics($event['title'] ?? '') . "\r\n";
        
        if (!empty($event['description'])) {
            $ics .= "DESCRIPTION:" . $this->escape_ics($event['description']) . "\r\n";
        }
        
        if (!empty($event['location'])) {
            $ics .= "LOCATION:" . $this->escape_ics($event['location']) . "\r\n";
        }
        
        // Handle occurrence time
        if (isset($event['occurrence']['start'], $event['occurrence']['end'])) {
            $ics .= "DTSTART:" . date('Ymd\THis\Z', strtotime($event['occurrence']['start'])) . "\r\n";
            $ics .= "DTEND:" . date('Ymd\THis\Z', strtotime($event['occurrence']['end'])) . "\r\n";
        }
        
        $ics .= "END:VEVENT\r\n";
    }
    
    $ics .= "END:VCALENDAR\r\n";
    return $ics;
}

private function escape_ics($text) {
    return str_replace(["\n", "\r", ",", ";"], ["\\n", "", "\\,", "\\;"], strip_tags($text));
}