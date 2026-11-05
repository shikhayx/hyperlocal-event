<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class DiscoverEventItemDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $short_id,
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $category,
        public readonly string $description_preview,
        public readonly string $type,
        public readonly string $start_date,
        public readonly ?string $end_date,
        public readonly ?string $next_start_date,
        public readonly string $timezone,
        public readonly bool $is_free,
        public readonly ?float $min_price,
        public readonly ?string $currency,
        public readonly ?string $cover_image_url,
        public readonly array $organizer,
        public readonly bool $is_online,
        public readonly ?array $location,
        public readonly ?float $distance_km,
        public readonly ?string $url = null,
    ) {}
}
