<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use Carbon\Carbon;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Helper\StringHelper;
use HiEvents\Helper\Url;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Event;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\DiscoverEventItemDTO;
use HiEvents\Services\Application\Handlers\Event\DTO\DiscoverEventsDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends BaseRepository<EventDomainObject>
 */
class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    private const SITEMAP_ENDED_EVENT_RETENTION_DAYS = 120;

    protected function getModel(): string
    {
        return Event::class;
    }

    public function getDomainObject(): string
    {
        return EventDomainObject::class;
    }

    public function findEventsForOrganizer(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where[] = static function (Builder $builder) use ($accountId, $organizerId) {
            $builder
                ->whereIn(EventDomainObjectAbstract::STATUS, [
                    EventStatus::LIVE->name,
                    EventStatus::DRAFT->name,
                    EventStatus::PENDING_MANUAL_REVIEW->name,
                ])
                ->where(EventDomainObjectAbstract::ORGANIZER_ID, $organizerId)
                ->where(EventDomainObjectAbstract::ACCOUNT_ID, $accountId);
        };

        return $this->findEvents($where, $params);
    }

    public function findEvents(
        array $where,
        QueryParamsDTO $params,
        ?array $restrictToOrganizerIds = null,
        ?array $restrictToEventIds = null,
    ): LengthAwarePaginator {
        if ($restrictToOrganizerIds !== null || $restrictToEventIds !== null) {
            $where[] = static function (Builder $builder) use ($restrictToOrganizerIds, $restrictToEventIds) {
                $builder
                    ->whereIn(EventDomainObjectAbstract::ORGANIZER_ID, $restrictToOrganizerIds ?? [])
                    ->orWhereIn(EventDomainObjectAbstract::ID, $restrictToEventIds ?? []);
            };
        }

        if (! empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(EventDomainObjectAbstract::TITLE, 'ilike', '%'.$params->query.'%');
            };
        }

        $upcomingEventsFilter = $params->query_params->get('eventsStatus') === 'upcoming';
        $endedEventsFilter = $params->query_params->get('eventsStatus') === 'ended';

        if (! empty($params->filter_fields)) {
            $this->applyFilterFields($params, EventDomainObject::getAllowedFilterFields());
        }

        if ($upcomingEventsFilter) {
            $where[] = static function (Builder $builder) {
                $builder
                    ->where(EventDomainObjectAbstract::STATUS, '!=', EventStatus::ARCHIVED->getName())
                    ->where(function (Builder $eventQuery) {
                        $eventQuery
                            ->whereNotExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('event_occurrences')
                                    ->whereColumn('event_occurrences.event_id', 'events.id')
                                    ->whereNull('event_occurrences.deleted_at');
                            })
                            ->orWhereExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('event_occurrences')
                                    ->whereColumn('event_occurrences.event_id', 'events.id')
                                    ->whereNull('event_occurrences.deleted_at')
                                    ->whereRaw('COALESCE(event_occurrences.end_date, event_occurrences.start_date) >= ?', [now()]);
                            });
                    });
            };
        }

        if ($endedEventsFilter) {
            $where[] = static function (Builder $builder) {
                $builder
                    ->where(EventDomainObjectAbstract::STATUS, '!=', EventStatus::ARCHIVED->getName())
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('event_occurrences')
                            ->whereColumn('event_occurrences.event_id', 'events.id')
                            ->whereNull('event_occurrences.deleted_at');
                    })
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('event_occurrences')
                            ->whereColumn('event_occurrences.event_id', 'events.id')
                            ->whereNull('event_occurrences.deleted_at')
                            ->whereRaw('COALESCE(event_occurrences.end_date, event_occurrences.start_date) >= ?', [now()]);
                    });
            };
        }

        $sortColumn = $this->validateSortColumn($params->sort_by, EventDomainObject::class);
        $sortDirection = $this->validateSortDirection($params->sort_direction, EventDomainObject::class);

        if ($sortColumn === EventDomainObjectAbstract::START_DATE) {
            $this->applyOccurrenceStartDateSort($sortDirection, $upcomingEventsFilter, $endedEventsFilter);
        } else {
            $this->model = $this->model->orderBy($sortColumn, $sortDirection);
        }

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    private function applyOccurrenceStartDateSort(string $direction, bool $upcomingOnly, bool $endedOnly): void
    {
        $liveOccurrences = 'FROM event_occurrences eo WHERE eo.event_id = events.id AND eo.deleted_at IS NULL';
        $bindings = [];

        if ($upcomingOnly) {
            $sortDateSql = "SELECT MIN(eo.start_date) {$liveOccurrences} AND COALESCE(eo.end_date, eo.start_date) >= ?";
            $bindings[] = now();
        } elseif ($endedOnly) {
            $sortDateSql = "SELECT MAX(eo.start_date) {$liveOccurrences}";
        } else {
            $sortDateSql = "SELECT MIN(eo.start_date) {$liveOccurrences}";
        }

        $this->model = $this->model
            ->orderByRaw("({$sortDateSql}) {$direction} NULLS LAST", $bindings)
            ->orderBy(EventDomainObjectAbstract::ID);
    }

    public function getAllEventsForAdmin(
        ?string $search = null,
        int $perPage = 20,
        ?string $sortBy = 'created_at',
        ?string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        $this->model = $this->model
            ->select('events.*')
            ->withCount('attendees');

        if ($search) {
            $this->model = $this->model->where(function ($q) use ($search) {
                $q->where(EventDomainObjectAbstract::TITLE, 'ilike', '%'.$search.'%')
                    ->orWhereHas('organizer', function ($orgQuery) use ($search) {
                        $orgQuery->where('name', 'ilike', '%'.$search.'%');
                    });
            });
        }

        $allowedSortColumns = ['title', 'created_at', 'updated_at', 'start_date', 'end_date'];
        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'created_at';
        $sortDir = in_array(strtolower((string) $sortDirection), ['asc', 'desc'], true) ? strtolower($sortDirection) : 'desc';

        if ($sortColumn === 'start_date') {
            $this->model = $this->model->orderByRaw(
                "(SELECT MIN(eo.start_date) FROM event_occurrences eo WHERE eo.event_id = events.id AND eo.deleted_at IS NULL) {$sortDir} NULLS LAST"
            );
        } elseif ($sortColumn === 'end_date') {
            $this->model = $this->model->orderByRaw(
                "(SELECT MAX(COALESCE(eo.end_date, eo.start_date)) FROM event_occurrences eo WHERE eo.event_id = events.id AND eo.deleted_at IS NULL) {$sortDir} NULLS LAST"
            );
        } else {
            $this->model = $this->model->orderBy($sortColumn, $sortDir);
        }

        $this->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'));
        $this->loadRelation(new Relationship(AccountDomainObject::class, name: 'account'));
        $this->loadRelation(new Relationship(EventStatisticDomainObject::class, name: 'event_statistics'));
        $this->loadRelation(new Relationship(EventOccurrenceDomainObject::class));

        return $this->paginate($perPage);
    }

    public function getSitemapEvents(int $page, int $perPage): LengthAwarePaginator
    {
        return $this->handleResults($this->sitemapEventsQuery()
            ->select([
                'events.'.EventDomainObjectAbstract::ID,
                'events.'.EventDomainObjectAbstract::TITLE,
                'events.'.EventDomainObjectAbstract::UPDATED_AT,
            ])
            ->orderBy('events.'.EventDomainObjectAbstract::ID)
            ->paginate($perPage, ['*'], 'page', $page));
    }

    public function findByIdLocked(int $id): EventDomainObject
    {
        $model = Event::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->firstOrFail();

        return $this->handleSingleResult($model);
    }

    public function getSitemapEventCount(): int
    {
        return $this->sitemapEventsQuery()->count();
    }

    private function sitemapEventsQuery(): Builder
    {
        return $this->model
            ->newQuery()
            ->join('event_settings', 'events.id', '=', 'event_settings.event_id')
            ->where('events.'.EventDomainObjectAbstract::STATUS, EventStatus::LIVE->name)
            ->where('event_settings.'.EventSettingDomainObjectAbstract::ALLOW_SEARCH_ENGINE_INDEXING, true)
            ->whereNull('events.'.EventDomainObjectAbstract::DELETED_AT)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('event_occurrences')
                    ->whereColumn('event_occurrences.event_id', 'events.id')
                    ->whereNull('event_occurrences.deleted_at')
                    ->where('event_occurrences.status', '!=', EventOccurrenceStatus::CANCELLED->name)
                    ->whereRaw(
                        'COALESCE(event_occurrences.end_date, event_occurrences.start_date) >= ?',
                        [now()->subDays(self::SITEMAP_ENDED_EVENT_RETENTION_DAYS)]
                    );
            });
    }

    public function getUpcomingEventsWithCompletedOrders(int $accountId): Collection
    {
        return $this->runQuery(function () use ($accountId) {
            $events = $this->model
                ->where('events.'.EventDomainObjectAbstract::ACCOUNT_ID, $accountId)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('event_occurrences')
                        ->whereColumn('event_occurrences.event_id', 'events.id')
                        ->whereNull('event_occurrences.deleted_at')
                        ->whereRaw('COALESCE(event_occurrences.end_date, event_occurrences.start_date) >= ?', [now()]);
                })
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('orders')
                        ->whereColumn('orders.event_id', 'events.id')
                        ->whereNull('orders.deleted_at')
                        ->where('orders.status', OrderStatus::COMPLETED->name);
                })
                ->orderBy('events.id')
                ->get();

            return $this->handleResults($events);
        });
    }

    public function discoverEvents(DiscoverEventsDTO $dto): LengthAwarePaginator
    {
        return $this->runQuery(function () use ($dto) {
            $dateFrom = $dto->date_from;
            $dateTo = $dto->date_to;
            $tz = $dto->tz ?: 'UTC';

            if ($dto->when !== null) {
                $nowLocal = Carbon::now($tz);
                if ($dto->when === 'today') {
                    $dateFrom = $nowLocal->copy()->setTimezone('UTC')->toIso8601String();
                    $dateTo = $nowLocal->copy()->endOfDay()->setTimezone('UTC')->toIso8601String();
                } elseif ($dto->when === 'tonight') {
                    $tonightStart = $nowLocal->copy()->setTime(17, 0, 0);
                    $start = $nowLocal->greaterThan($tonightStart) ? $nowLocal->copy() : $tonightStart;
                    $dateFrom = $start->setTimezone('UTC')->toIso8601String();
                    $dateTo = $nowLocal->copy()->endOfDay()->setTimezone('UTC')->toIso8601String();
                } elseif ($dto->when === 'weekend') {
                    if ($nowLocal->isSaturday() || $nowLocal->isSunday()) {
                        $dateFrom = $nowLocal->copy()->setTimezone('UTC')->toIso8601String();
                        $dateTo = $nowLocal->copy()->endOfWeek(Carbon::SUNDAY)->setTimezone('UTC')->toIso8601String();
                    } else {
                        $saturday = $nowLocal->copy()->next(Carbon::SATURDAY)->startOfDay();
                        $sunday = $saturday->copy()->endOfWeek(Carbon::SUNDAY);
                        $dateFrom = $saturday->setTimezone('UTC')->toIso8601String();
                        $dateTo = $sunday->setTimezone('UTC')->toIso8601String();
                    }
                }
            } elseif ($dateFrom !== null || $dateTo !== null) {
                if ($dateFrom !== null) {
                    $dateFrom = strlen($dateFrom) <= 10
                        ? Carbon::parse($dateFrom, $tz)->startOfDay()->setTimezone('UTC')->toIso8601String()
                        : Carbon::parse($dateFrom)->setTimezone('UTC')->toIso8601String();
                }
                if ($dateTo !== null) {
                    $dateTo = strlen($dateTo) <= 10
                        ? Carbon::parse($dateTo, $tz)->endOfDay()->setTimezone('UTC')->toIso8601String()
                        : Carbon::parse($dateTo)->setTimezone('UTC')->toIso8601String();
                }
            }

            $hasCoordinates = $dto->lat !== null && $dto->lng !== null;
            $distanceSelect = 'NULL::numeric AS distance_km';
            $bindings = [];

            if ($hasCoordinates) {
                $distanceSelect = 'CASE 
                    WHEN locations.latitude IS NOT NULL AND locations.longitude IS NOT NULL THEN
                        ROUND((6371 * acos(
                            LEAST(1.0, GREATEST(-1.0,
                                cos(radians(?)) * cos(radians(locations.latitude)) * cos(radians(locations.longitude) - radians(?))
                                + sin(radians(?)) * sin(radians(locations.latitude))
                            ))
                        ))::numeric, 1)
                    ELSE NULL
                END AS distance_km';
                $bindings[] = $dto->lat;
                $bindings[] = $dto->lng;
                $bindings[] = $dto->lat;
            }

            $relevanceSelect = '0.0::float AS relevance';
            if ($dto->q !== null && $dto->q !== '') {
                $relevanceSelect = 'GREATEST(
                    similarity(events.title, ?),
                    similarity(COALESCE(locations.name, \'\'), ?),
                    similarity(organizers.name, ?)
                )::float AS relevance';
                $bindings[] = $dto->q;
                $bindings[] = $dto->q;
                $bindings[] = $dto->q;
            }

            $sql = "
                SELECT 
                    events.id,
                    events.short_id,
                    events.title,
                    events.category,
                    events.description,
                    events.type,
                    events.timezone,
                    events.currency,
                    organizers.id AS organizer_id,
                    organizers.name AS organizer_name,
                    locations.name AS venue_name,
                    locations.latitude,
                    locations.longitude,
                    locations.structured_address,
                    (
                        CASE 
                            WHEN event_locations.type = 'ONLINE' OR event_settings.is_online_event = true OR locations.latitude IS NULL OR locations.longitude IS NULL THEN true
                            ELSE false
                        END
                    ) AS is_online,
                    (
                        SELECT MIN(eo_s.start_date)
                        FROM event_occurrences eo_s
                        WHERE eo_s.event_id = events.id
                          AND eo_s.deleted_at IS NULL
                    ) AS first_start_date,
                    (
                        SELECT MAX(COALESCE(eo_e.end_date, eo_e.start_date))
                        FROM event_occurrences eo_e
                        WHERE eo_e.event_id = events.id
                          AND eo_e.deleted_at IS NULL
                    ) AS last_end_date,
                    (
                        SELECT MIN(eo_next.start_date)
                        FROM event_occurrences eo_next
                        WHERE eo_next.event_id = events.id
                          AND eo_next.deleted_at IS NULL
                          AND eo_next.status = 'ACTIVE'
                          AND COALESCE(eo_next.end_date, eo_next.start_date) >= NOW()
                    ) AS next_start_date,
                    (
                        CASE 
                            WHEN events.type = 'RECURRING' THEN (
                                SELECT MIN(eo_r.start_date)
                                FROM event_occurrences eo_r
                                WHERE eo_r.event_id = events.id
                                  AND eo_r.deleted_at IS NULL
                                  AND eo_r.status = 'ACTIVE'
                                  AND COALESCE(eo_r.end_date, eo_r.start_date) >= NOW()
                            )
                            ELSE (
                                SELECT MIN(eo_s2.start_date)
                                FROM event_occurrences eo_s2
                                WHERE eo_s2.event_id = events.id
                                  AND eo_s2.deleted_at IS NULL
                            )
                        END
                    ) AS effective_start_date,
                    (
                        SELECT MIN(pp.price)
                        FROM products p
                        JOIN product_prices pp ON pp.product_id = p.id AND pp.deleted_at IS NULL
                        WHERE p.event_id = events.id
                          AND p.deleted_at IS NULL
                          AND p.is_hidden = false
                          AND p.is_hidden_without_promo_code = false
                          AND p.is_addon_only = false
                    ) AS min_price,
                    (
                        SELECT i.path
                        FROM images i
                        WHERE i.entity_id = events.id
                          AND i.entity_type = 'HiEvents\\\\Models\\\\Event'
                          AND i.type = 'EVENT_COVER'
                          AND i.deleted_at IS NULL
                        ORDER BY i.id DESC
                        LIMIT 1
                    ) AS cover_image_path,
                    {$distanceSelect},
                    {$relevanceSelect}
                FROM events
                JOIN organizers ON organizers.id = events.organizer_id AND organizers.deleted_at IS NULL
                LEFT JOIN event_locations ON event_locations.id = events.event_location_id AND event_locations.deleted_at IS NULL
                LEFT JOIN locations ON locations.id = event_locations.location_id AND locations.deleted_at IS NULL
                LEFT JOIN event_settings ON event_settings.event_id = events.id
                WHERE events.status = 'LIVE'
                  AND events.deleted_at IS NULL
                  AND EXISTS (
                      SELECT 1 FROM event_occurrences eo_elig
                      WHERE eo_elig.event_id = events.id
                        AND eo_elig.deleted_at IS NULL
                        AND eo_elig.status = 'ACTIVE'
                        AND COALESCE(eo_elig.end_date, eo_elig.start_date) >= NOW()
                  )
            ";

            if (! empty($dto->categories)) {
                $categoryPlaceholders = implode(',', array_fill(0, count($dto->categories), '?'));
                $sql .= " AND events.category IN ({$categoryPlaceholders})";
                foreach ($dto->categories as $category) {
                    $bindings[] = $category;
                }
            }

            if ($dto->q !== null && $dto->q !== '') {
                $qLike = '%'.addcslashes($dto->q, '%_\\').'%';
                $sql .= " AND (
                    events.title ILIKE ?
                    OR events.description ILIKE ?
                    OR organizers.name ILIKE ?
                    OR locations.name ILIKE ?
                    OR similarity(events.title, ?) > 0.15
                    OR similarity(COALESCE(locations.name, ''), ?) > 0.15
                    OR similarity(organizers.name, ?) > 0.15
                )";
                $bindings[] = $qLike;
                $bindings[] = $qLike;
                $bindings[] = $qLike;
                $bindings[] = $qLike;
                $bindings[] = $dto->q;
                $bindings[] = $dto->q;
                $bindings[] = $dto->q;
            }

            $outerConditions = [];
            $outerBindings = [];

            if ($hasCoordinates) {
                if ($dto->include_online) {
                    $outerConditions[] = '(is_online = true OR (distance_km IS NOT NULL AND distance_km <= ?))';
                    $outerBindings[] = $dto->radius_km;
                } else {
                    $outerConditions[] = '(is_online = false AND distance_km IS NOT NULL AND distance_km <= ?)';
                    $outerBindings[] = $dto->radius_km;
                }
            }

            if ($dateFrom !== null) {
                $outerConditions[] = 'effective_start_date >= ?';
                $outerBindings[] = $dateFrom;
            }

            if ($dateTo !== null) {
                $outerConditions[] = 'effective_start_date <= ?';
                $outerBindings[] = $dateTo;
            }

            if ($dto->free === true) {
                $outerConditions[] = 'COALESCE(min_price, 0) = 0';
            } elseif ($dto->free === false) {
                $outerConditions[] = 'min_price > 0';
            }

            $outerWhere = '';
            if (! empty($outerConditions)) {
                $outerWhere = 'WHERE '.implode(' AND ', $outerConditions);
            }

            $countSql = "
                WITH discover_events AS ({$sql})
                SELECT COUNT(*) as total FROM discover_events {$outerWhere}
            ";
            $allBindings = array_merge($bindings, $outerBindings);
            $countResult = DB::selectOne($countSql, $allBindings);
            $total = (int) ($countResult->total ?? 0);

            $sort = $dto->sort;
            if ($sort === null) {
                $sort = $hasCoordinates ? 'distance' : 'date';
            }

            $orderBy = match ($sort) {
                'distance' => 'ORDER BY distance_km ASC NULLS LAST, effective_start_date ASC',
                'relevance' => ($dto->q !== null && $dto->q !== '')
                    ? 'ORDER BY relevance DESC, effective_start_date ASC'
                    : 'ORDER BY effective_start_date ASC',
                default => 'ORDER BY effective_start_date ASC',
            };

            $offset = ($dto->page - 1) * $dto->per_page;
            $dataSql = "
                WITH discover_events AS ({$sql})
                SELECT * FROM discover_events {$outerWhere} {$orderBy}
                LIMIT ? OFFSET ?
            ";
            $dataBindings = array_merge($allBindings, [$dto->per_page, $offset]);
            $rows = DB::select($dataSql, $dataBindings);

            $items = collect($rows)->map(function ($row) {
                $structuredAddress = null;
                if (! empty($row->structured_address)) {
                    $structuredAddress = is_string($row->structured_address)
                        ? json_decode($row->structured_address, true)
                        : (array) $row->structured_address;
                }

                $city = $structuredAddress['city'] ?? null;
                $location = null;
                if (! $row->is_online && ($row->venue_name !== null || $row->latitude !== null)) {
                    $location = [
                        'venue_name' => $row->venue_name,
                        'city' => $city,
                        'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
                        'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
                    ];
                }

                $minPrice = $row->min_price !== null ? (float) $row->min_price : 0.0;
                $isFree = $minPrice === 0.0;
                $slug = Str::slug($row->title);
                $coverImageUrl = $row->cover_image_path ? Url::getCdnUrl($row->cover_image_path) : null;
                $frontendBaseUrl = rtrim((string) config('app.frontend_url'), '/');

                return new DiscoverEventItemDTO(
                    id: (int) $row->id,
                    short_id: $row->short_id,
                    slug: $slug,
                    title: $row->title,
                    category: $row->category,
                    description_preview: StringHelper::previewFromHtml($row->description ?? '', 160),
                    type: $row->type,
                    start_date: Carbon::parse($row->first_start_date)->setTimezone('UTC')->toIso8601String(),
                    end_date: $row->last_end_date ? Carbon::parse($row->last_end_date)->setTimezone('UTC')->toIso8601String() : null,
                    next_start_date: ($row->type === 'RECURRING' && $row->next_start_date)
                        ? Carbon::parse($row->next_start_date)->setTimezone('UTC')->toIso8601String()
                        : null,
                    timezone: $row->timezone ?: 'UTC',
                    is_free: $isFree,
                    min_price: $minPrice,
                    currency: $row->currency ?: 'INR',
                    cover_image_url: $coverImageUrl,
                    organizer: [
                        'id' => (int) $row->organizer_id,
                        'name' => (string) $row->organizer_name,
                    ],
                    is_online: (bool) $row->is_online,
                    location: $location,
                    distance_km: $row->distance_km !== null ? (float) $row->distance_km : null,
                    url: sprintf('%s/event/%d/%s', $frontendBaseUrl, $row->id, $slug),
                );
            });

            return new LengthAwarePaginator(
                items: $items,
                total: $total,
                perPage: $dto->per_page,
                currentPage: $dto->page,
                options: [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => 'page',
                ]
            );
        });
    }
}
