<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Event\DiscoverEventsPublicRequest;
use HiEvents\Resources\Event\DiscoverEventResourcePublic;
use HiEvents\Services\Application\Handlers\Event\DiscoverEventsPublicHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\DiscoverEventsDTO;
use Illuminate\Http\JsonResponse;

class DiscoverEventsPublicAction extends BaseAction
{
    public function __construct(
        private readonly DiscoverEventsPublicHandler $handler,
    ) {}

    public function __invoke(DiscoverEventsPublicRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dto = new DiscoverEventsDTO(
            lat: isset($validated['lat']) ? (float) $validated['lat'] : null,
            lng: isset($validated['lng']) ? (float) $validated['lng'] : null,
            radius_km: isset($validated['radius_km']) ? (float) $validated['radius_km'] : 10.0,
            include_online: isset($validated['include_online']) ? (bool) $validated['include_online'] : false,
            categories: $validated['categories'] ?? null,
            when: $validated['when'] ?? null,
            date_from: $validated['date_from'] ?? null,
            date_to: $validated['date_to'] ?? null,
            tz: $validated['tz'] ?? 'UTC',
            free: isset($validated['free']) ? (bool) $validated['free'] : null,
            q: $validated['q'] ?? null,
            sort: $validated['sort'] ?? null,
            page: isset($validated['page']) ? (int) $validated['page'] : 1,
            per_page: isset($validated['per_page']) ? (int) $validated['per_page'] : 20,
        );

        $events = $this->handler->handle($dto);

        return $this->resourceResponse(
            resource: DiscoverEventResourcePublic::class,
            data: $events,
        );
    }
}
