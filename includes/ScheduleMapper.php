<?php
declare(strict_types=1);

namespace LunaCode\DisplayDustData;

use Closure;

/**
 * Class ScheduleMapper
 * 
 * Provides headers, or methods to map data to CSV columns, specific to a given type of Dust data. 
 * 
 * @phpstan-import-type EventShape from Types
 */
class ScheduleMapper implements CsvMapperInterface {
    private Closure $image_url_getter;

    public function __construct(Closure $image_url_getter) {
        $this->image_url_getter = $image_url_getter;
    }

    /**
     * @return string[]
     */
    public function get_headers(): array {
        return [
            'Title', 
            'Camp', 
            'Location', 
            'Day', 
            'Start Time', 
            'End Time', 
            'Short Time', 
            'Long Time', 
            'Brief Time', 
            'Image URL', 
            'Categories', 
            'Description'
        ];
    }

    /**
     * @param EventShape $event
     * @return string[]
     */
    public function map(mixed $event): array {
        return [
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
        ];
    }
}