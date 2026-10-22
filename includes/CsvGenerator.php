<?php
declare(strict_types=1);

namespace LunaCode\DisplayDustData;

// Classes

use Closure;
use InvalidArgumentException, RuntimeException;

/**
 * Class CsvGenerator
 * 
 * Handles the type-specific formatting of Dust data as CSV content.
 * 
 * @phpstan-import-type EventShape from Types
 */
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
     * @param list<EventShape> $list List of Dust data objects; eventually to include models/shapes other than Events.
     * @param 'schedule' $export_type Other types not yet used: `|'camps'|'art'|'music'`
     * @return string The contents of a CSV file representing the list of Dust data objects.
     * @throws RuntimeException if a temporary file cannot be opened for writing.
     */
    public function generate_csv(array $list, string $export_type = 'schedule'): string {
        $mapper = $this->get_mapper($export_type);
        $output = fopen('php://temp', 'r+');
        if (false === $output) {
            throw new RuntimeException('Error creating CSV file');
        }

        // Write headers via mapper
        fputcsv($output, $mapper->get_headers());

        // Write rows via mapper
        foreach ($list as $item) {
            fputcsv($output, $mapper->map($item));
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return $csv_content;
    }

    /**
     * Factory method to get the appropriate mapper based on export type.
     *
     * @param 'schedule' $type Type of Dust data. Ultimately will accept `'camps'|'art'|'schedule'|'music'`
     * @return CsvMapperInterface Interface for mapping a data item to a CSV row.
     * @throws InvalidArgumentException if called with an unsupported or unrecognized data type
     */
    private function get_mapper(string $type): CsvMapperInterface {
        return match ($type) {
            'schedule' => new ScheduleMapper($this->image_url_getter),
            // Future mappers can be added here:
            // 'camps' => new CampMapper(),
            default    => throw new InvalidArgumentException("Unsupported export type: {$type}"),
        };
    }
}
