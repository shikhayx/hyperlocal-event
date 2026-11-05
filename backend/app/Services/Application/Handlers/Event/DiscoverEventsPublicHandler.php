<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\DiscoverEventsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class DiscoverEventsPublicHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function handle(DiscoverEventsDTO $dto): LengthAwarePaginator
    {
        return $this->eventRepository->discoverEvents($dto);
    }
}
