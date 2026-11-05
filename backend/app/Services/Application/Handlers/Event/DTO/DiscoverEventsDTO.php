<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class DiscoverEventsDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly float $radius_km = 10.0,
        public readonly bool $include_online = false,
        public readonly ?array $categories = null,
        public readonly ?string $when = null,
        public readonly ?string $date_from = null,
        public readonly ?string $date_to = null,
        public readonly string $tz = 'UTC',
        public readonly ?bool $free = null,
        public readonly ?string $q = null,
        public readonly ?string $sort = null,
        public readonly int $page = 1,
        public readonly int $per_page = 20,
    ) {}
}
