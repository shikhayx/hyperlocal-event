<?php

declare(strict_types=1);

namespace HiEvents\Resources\Event;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Event\DTO\DiscoverEventItemDTO;
use Illuminate\Http\Request;

/**
 * @mixin DiscoverEventItemDTO
 */
class DiscoverEventResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'short_id' => $this->short_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category,
            'description_preview' => $this->description_preview,
            'type' => $this->type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'next_start_date' => $this->next_start_date,
            'timezone' => $this->timezone,
            'is_free' => $this->is_free,
            'min_price' => $this->min_price,
            'currency' => $this->currency,
            'cover_image_url' => $this->cover_image_url,
            'organizer' => $this->organizer,
            'is_online' => $this->is_online,
            'location' => $this->location,
            'distance_km' => $this->distance_km,
            'url' => $this->url,
        ];
    }
}
