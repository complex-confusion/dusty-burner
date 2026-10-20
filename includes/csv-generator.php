<?php
declare(strict_types=1);

namespace LunaCode\DisplayDustData;

// Classes

use Closure;
use InvalidArgumentException, RuntimeException;

class CsvGenerator {
    private Closure $image_url_getter;

    public function __construct(?Closure $custom_image_url_getter = null) {
        if (!empty($custom_image_url_getter) && ($custom_image_url_getter instanceof \Closure)) {
            $this->image_url_getter = $custom_image_url_getter;
        } else {
            $this->image_url_getter = fn($image_path) => (!empty($image_path) ? $image_path : '');
        }
    }
    
    /**
     * @param list<LunaCode\DisplayDustData\DisplayDustData::EventShape> $list
     * @param 'schedule' $export_type Other types not yet used: `|'camps'|'art'|'music'`
     * @return string CSV
     */
    public function generate_csv(array $list, string $export_type = 'schedule'): string {
        $csv = match ($export_type) {
            // TODO in future, enable other export types of Dust content.
            'schedule' => $this->generate_schedule_csv($list),
            // 'schedule', 'camps', 'art', 'music' => $this->{"export_{$export_type}_csv"}($event_name, $list),
            default => throw new InvalidArgumentException("Unsupported export type: {$export_type}"),
        };
        return $csv;
    }

    /**
     * generate_schedule_csv
     * Turn JSON schedule (list of the Burn's events) into CSV.
     * 
     * @param list<LunaCode\DisplayDustData\DisplayDustData::EventShape> $event_list
     * @return string CSV
     */
    private function generate_schedule_csv(array $event_list): string {
        $output = fopen('php://temp', 'r+');
        if (false === $output) {
            throw new RuntimeException('Error creating CSV file');
        }

        // CSV headers
        fputcsv($output, array('Title', 'Camp', 'Location', 'Day', 'Start Time', 'End Time', 'Short Time', 'Long Time', 'Brief Time', 'Image URL', 'Categories', 'Description'));

        foreach ($event_list as $event) {
            $row = array(
                $event['title'] ?? '',
                $event['camp'] ?? '',
                $event['location'] ?? '',
                $event['day'] ?? '',
                $event['occurrence']['start_time'] ?? '',
                $event['occurrence']['end_time'] ?? '',
                $event['occurrence']['short'] ?? '',
                $event['occurrence']['long'] ?? '',
                $event['occurrence']['brief'] ?? '',
                ($this->image_url_getter)($event['imageUrl']) ?? '',
                $event['event_type']['label'] ?? '',
                strip_tags($event['description'] ?? '')
            );
            fputcsv($output, $row);
        }

        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return $csv_content;
    }
}