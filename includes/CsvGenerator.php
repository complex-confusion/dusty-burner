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
     * @param string $type
     * @return CsvMapperInterface
     * @throws InvalidArgumentException
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
