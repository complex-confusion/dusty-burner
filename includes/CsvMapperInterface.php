<?php
declare(strict_types=1);

namespace LunaCode\DisplayDustData;

/**
 * Interface for mapping a data item to a CSV row.
 */
interface CsvMapperInterface {
    /**
     * Get the headers for the CSV file.
     * 
     * @return string[]
     */
    public function get_headers(): array;

    /**
     * Map a single data item (e.g., an event) to a flat array of values.
     * 
     * @param mixed $item The data item to map.
     * @return string[]
     */
    public function map(mixed $item): array;
}