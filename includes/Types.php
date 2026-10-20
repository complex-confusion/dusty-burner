<?php
declare(strict_types=1);

namespace LunaCode\DisplayDustData;

/**
 * This class exists solely to house type definitions for static analysis.
 * 
 * Type EventShape
 *   "Event" items from the Dust API's Schedule endpoint
 * 
 * Descriptions of some fields:
 * - uid: ID of the event
 * - located_at_art: ID of the art installation at which the event occurs. E.g. "u-2699"
 * 
 * Possibly-deprecated fields:
 * - otherLocation: Empty string or "Open Camping / Other"
 * - other_location: Null or same as `location` field
 * - canView: `false` or not present
 * - moderation: always null
 * - status: always null
 * 
 * Not all fields given as nullable (`|null`) or possibly not included (`my_key?:`)
 * are ever returned as such by Dust, but I wanted to be prepared.
 *
 * @phpstan-type EventShape array{
 *     uid: string,
 *     title: string,
 *     description: string|null,
 *     imageUrl: string|null,
 *     event_type: array{
 *         label: string,
 *     }|null,
 *     camp: string|null, 
 *     location: string,
 *     day: string,
 *     occurrence: array{
 *         start_time: string,
 *         end_time: string,
 *         short: string,
 *         long: string,
 *         brief: string,
 *     },
 *     art?: string|null,
 *     canView?: bool, 
 *     otherLocation?: string|null,
 *     other_location?: string|null,
 *     status?: string|null,
 *     moderation?: string|null,
 * }
 */
class Types { /* This class intentionally left empty */ }

