import {useState} from 'react';
import {Badge, Button, Group, Loader, Pagination, Select, Stack, Switch, TextInput} from '@mantine/core';
import {IconCompass, IconCurrentLocation, IconLayoutGrid, IconMap, IconSearch, IconX} from '@tabler/icons-react';
import {Link} from 'react-router';
import {t, Trans} from '@lingui/macro';
import {getEventCategories} from '../../../../constants/eventCategories.ts';
import {useDiscoverEvents} from '../../../../queries/useDiscoverEvents.ts';
import {DiscoverEventItem} from '../../../../api/discover.client.ts';
import {DiscoverCard} from './DiscoverCard.tsx';
import {DiscoverMap} from './DiscoverMap.tsx';
import classes from './Discover.module.scss';

const LOCATION_PRESETS = [
    {label: 'Bandra', lat: 19.0596, lng: 72.8295},
    {label: 'Andheri', lat: 19.1136, lng: 72.8697},
    {label: 'Colaba', lat: 18.9067, lng: 72.8147},
    {label: 'Powai', lat: 19.1176, lng: 72.9060},
    {label: 'Dadar', lat: 19.0178, lng: 72.8478},
];

export default function DiscoverPage() {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
    const [when, setWhen] = useState<'today' | 'tonight' | 'weekend' | null>(null);
    const [freeOnly, setFreeOnly] = useState<boolean | null>(null);
    const [userCoords, setUserCoords] = useState<{ lat: number; lng: number; label: string } | null>({
        lat: 19.0596,
        lng: 72.8295,
        label: 'Bandra (Default)',
    });
    const [radiusKm, setRadiusKm] = useState<number>(15);
    const [includeOnline, setIncludeOnline] = useState<boolean>(false);
    const [sort, setSort] = useState<'distance' | 'date' | 'relevance'>('distance');
    const [viewMode, setViewMode] = useState<'both' | 'grid' | 'map'>('both');
    const [page, setPage] = useState<number>(1);
    const [geoLoading, setGeoLoading] = useState(false);

    const categories = getEventCategories();

    const handleUseMyLocation = () => {
        if (typeof window === 'undefined' || !navigator.geolocation) {
            return;
        }

        setGeoLoading(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setUserCoords({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    label: 'Current Location',
                });
                setSort('distance');
                setPage(1);
                setGeoLoading(false);
            },
            () => {
                setGeoLoading(false);
            },
            {timeout: 10000}
        );
    };

    const handleClearLocation = () => {
        setUserCoords(null);
        setSort('date');
        setPage(1);
    };

    const handlePresetSelect = (preset: typeof LOCATION_PRESETS[0]) => {
        setUserCoords({
            lat: preset.lat,
            lng: preset.lng,
            label: preset.label,
        });
        setSort('distance');
        setPage(1);
    };

    const queryParams = {
        lat: userCoords?.lat ?? null,
        lng: userCoords?.lng ?? null,
        radius_km: userCoords ? radiusKm : null,
        include_online: userCoords ? includeOnline : null,
        categories: selectedCategory ? [selectedCategory] : null,
        when: when,
        free: freeOnly,
        q: searchQuery.trim() || null,
        sort: sort,
        page: page,
        per_page: 20,
    };

    const {data, isLoading} = useDiscoverEvents(queryParams);

    const events = data?.data || [];
    const meta = data?.meta;

    const resetFilters = () => {
        setSearchQuery('');
        setSelectedCategory(null);
        setWhen(null);
        setFreeOnly(null);
        setPage(1);
    };

    return (
        <div className={classes.pageWrapper}>
            <header className={classes.header}>
                <div className={classes.headerInner}>
                    <div className={classes.brandNav}>
                        <Link to="/discover" className={classes.brandLogo}>
                            <IconCompass size={28} />
                            <span><Trans>Hyperlocal Events</Trans></span>
                        </Link>
                        <Group gap="xs">
                            <Button
                                component={Link}
                                to="/auth/login"
                                variant="subtle"
                                color="gray"
                                c="white"
                                size="sm"
                            >
                                <Trans>Organizer Sign In</Trans>
                            </Button>
                        </Group>
                    </div>

                    <h1 className={classes.heroTitle}>
                        <Trans>Discover Local Events & Experiences Near You</Trans>
                    </h1>
                    <p className={classes.heroSubtitle}>
                        <Trans>
                            Find curated concerts, workshops, tech meetups, sports matches, and community flows happening across your city.
                        </Trans>
                    </p>

                    <div className={classes.searchBarWrapper}>
                        <Stack gap="sm">
                            <Group grow>
                                <TextInput
                                    placeholder={t`Search by title, artist, venue, or keyword...`}
                                    value={searchQuery}
                                    onChange={(e) => {
                                        setSearchQuery(e.target.value);
                                        setPage(1);
                                    }}
                                    leftSection={<IconSearch size={18} />}
                                    size="md"
                                    radius="md"
                                    variant="filled"
                                    styles={{
                                        input: {
                                            backgroundColor: '#ffffff',
                                            color: '#0f172a',
                                        },
                                    }}
                                />
                                <Group gap="xs" wrap="nowrap" style={{flex: '0 0 auto'}}>
                                    <Button
                                        onClick={handleUseMyLocation}
                                        loading={geoLoading}
                                        leftSection={<IconCurrentLocation size={18} />}
                                        variant="filled"
                                        color="indigo"
                                        size="md"
                                        radius="md"
                                    >
                                        <Trans>Near Me</Trans>
                                    </Button>
                                    <Select
                                        data={[
                                            {value: '5', label: t`5 km`},
                                            {value: '10', label: t`10 km`},
                                            {value: '15', label: t`15 km`},
                                            {value: '25', label: t`25 km`},
                                            {value: '50', label: t`50 km`},
                                        ]}
                                        value={String(radiusKm)}
                                        onChange={(val) => {
                                            if (val) setRadiusKm(Number(val));
                                        }}
                                        size="md"
                                        radius="md"
                                        w={110}
                                        styles={{
                                            input: {
                                                backgroundColor: '#ffffff',
                                                color: '#0f172a',
                                            },
                                        }}
                                    />
                                </Group>
                            </Group>

                            <Group justify="space-between" wrap="wrap" gap="xs">
                                <Group gap="xs" wrap="wrap">
                                    <span style={{fontSize: 12, color: '#94a3b8', fontWeight: 600}}>
                                        <Trans>AREA PRESETS:</Trans>
                                    </span>
                                    {LOCATION_PRESETS.map((preset) => (
                                        <Badge
                                            key={preset.label}
                                            variant={userCoords?.label === preset.label ? 'filled' : 'light'}
                                            color="indigo"
                                            size="sm"
                                            style={{cursor: 'pointer'}}
                                            onClick={() => handlePresetSelect(preset)}
                                        >
                                            {preset.label}
                                        </Badge>
                                    ))}
                                    {userCoords && (
                                        <Badge
                                            variant="outline"
                                            color="gray"
                                            size="sm"
                                            rightSection={<IconX size={10} />}
                                            style={{cursor: 'pointer'}}
                                            onClick={handleClearLocation}
                                        >
                                            <Trans>Worldwide / All</Trans>
                                        </Badge>
                                    )}
                                </Group>

                                <Group gap="md">
                                    {userCoords && (
                                        <Switch
                                            label={<span style={{color: '#ffffff', fontSize: 13}}><Trans>Include Online</Trans></span>}
                                            checked={includeOnline}
                                            onChange={(e) => setIncludeOnline(e.currentTarget.checked)}
                                            size="sm"
                                            color="indigo"
                                        />
                                    )}
                                </Group>
                            </Group>
                        </Stack>
                    </div>
                </div>
            </header>

            <main className={classes.mainContent}>
                <section className={classes.filterSection}>
                    <Stack gap="md">
                        <div className={classes.categoryPillList}>
                            <div
                                className={`${classes.categoryPill} ${selectedCategory === null ? classes.active : ''}`}
                                onClick={() => {
                                    setSelectedCategory(null);
                                    setPage(1);
                                }}
                            >
                                ✨ <Trans>All Categories</Trans>
                            </div>
                            {categories.map((cat: {id: string; name: string; emoji: string}) => (
                                <div
                                    key={cat.id}
                                    className={`${classes.categoryPill} ${selectedCategory === cat.id ? classes.active : ''}`}
                                    onClick={() => {
                                        setSelectedCategory(selectedCategory === cat.id ? null : cat.id);
                                        setPage(1);
                                    }}
                                >
                                    {cat.emoji} {cat.name}
                                </div>
                            ))}
                        </div>

                        <Group justify="space-between" wrap="wrap" gap="md">
                            <Group gap="xs" wrap="wrap">
                                <Button
                                    size="xs"
                                    variant={when === null ? 'filled' : 'default'}
                                    color="gray"
                                    radius="xl"
                                    onClick={() => {
                                        setWhen(null);
                                        setPage(1);
                                    }}
                                >
                                    <Trans>Any Time</Trans>
                                </Button>
                                <Button
                                    size="xs"
                                    variant={when === 'today' ? 'filled' : 'default'}
                                    color="indigo"
                                    radius="xl"
                                    onClick={() => {
                                        setWhen(when === 'today' ? null : 'today');
                                        setPage(1);
                                    }}
                                >
                                    <Trans>Today</Trans>
                                </Button>
                                <Button
                                    size="xs"
                                    variant={when === 'tonight' ? 'filled' : 'default'}
                                    color="indigo"
                                    radius="xl"
                                    onClick={() => {
                                        setWhen(when === 'tonight' ? null : 'tonight');
                                        setPage(1);
                                    }}
                                >
                                    <Trans>Tonight</Trans>
                                </Button>
                                <Button
                                    size="xs"
                                    variant={when === 'weekend' ? 'filled' : 'default'}
                                    color="indigo"
                                    radius="xl"
                                    onClick={() => {
                                        setWhen(when === 'weekend' ? null : 'weekend');
                                        setPage(1);
                                    }}
                                >
                                    <Trans>This Weekend</Trans>
                                </Button>

                                <div style={{width: 1, height: 20, backgroundColor: 'var(--mantine-color-gray-3)', margin: '0 4px'}} />

                                <Button
                                    size="xs"
                                    variant={freeOnly === null ? 'filled' : 'default'}
                                    color="gray"
                                    radius="xl"
                                    onClick={() => {
                                        setFreeOnly(null);
                                        setPage(1);
                                    }}
                                >
                                    <Trans>All Prices</Trans>
                                </Button>
                                <Button
                                    size="xs"
                                    variant={freeOnly === true ? 'filled' : 'default'}
                                    color="teal"
                                    radius="xl"
                                    onClick={() => {
                                        setFreeOnly(freeOnly === true ? null : true);
                                        setPage(1);
                                    }}
                                >
                                    <Trans>Free Only</Trans>
                                </Button>
                                <Button
                                    size="xs"
                                    variant={freeOnly === false ? 'filled' : 'default'}
                                    color="gray"
                                    radius="xl"
                                    onClick={() => {
                                        setFreeOnly(freeOnly === false ? null : false);
                                        setPage(1);
                                    }}
                                >
                                    <Trans>Paid Only</Trans>
                                </Button>
                            </Group>

                            <Group gap="sm">
                                <Select
                                    size="xs"
                                    w={150}
                                    value={sort}
                                    onChange={(val) => {
                                        if (val) setSort(val as any);
                                    }}
                                    data={[
                                        {value: 'distance', label: t`Closest First`, disabled: !userCoords},
                                        {value: 'date', label: t`Upcoming Date`},
                                        {value: 'relevance', label: t`Most Relevant`},
                                    ]}
                                />
                                <Group gap={4}>
                                    <Button
                                        size="xs"
                                        variant={viewMode === 'both' ? 'filled' : 'subtle'}
                                        color="indigo"
                                        p={6}
                                        onClick={() => setViewMode('both')}
                                        title={t`Map and Grid`}
                                    >
                                        <Trans>Split</Trans>
                                    </Button>
                                    <Button
                                        size="xs"
                                        variant={viewMode === 'grid' ? 'filled' : 'subtle'}
                                        color="indigo"
                                        p={6}
                                        onClick={() => setViewMode('grid')}
                                        title={t`Grid View`}
                                    >
                                        <IconLayoutGrid size={16} />
                                    </Button>
                                    <Button
                                        size="xs"
                                        variant={viewMode === 'map' ? 'filled' : 'subtle'}
                                        color="indigo"
                                        p={6}
                                        onClick={() => setViewMode('map')}
                                        title={t`Map View`}
                                    >
                                        <IconMap size={16} />
                                    </Button>
                                </Group>
                            </Group>
                        </Group>
                    </Stack>
                </section>

                <div className={classes.resultsHeader}>
                    <div className={classes.resultsCount}>
                        {isLoading ? (
                            <Group gap="xs">
                                <Loader size="xs" />
                                <span><Trans>Searching events near you...</Trans></span>
                            </Group>
                        ) : (
                            <span>
                                {meta?.total ?? events.length} <Trans>events found</Trans>
                                {userCoords && ` within ${radiusKm} km of ${userCoords.label}`}
                            </span>
                        )}
                    </div>
                </div>

                {(viewMode === 'both' || viewMode === 'map') && (
                    <DiscoverMap
                        events={events}
                        userCoords={userCoords ? {lat: userCoords.lat, lng: userCoords.lng} : null}
                        radiusKm={userCoords ? radiusKm : undefined}
                    />
                )}

                {(viewMode === 'both' || viewMode === 'grid') && (
                    <>
                        {events.length > 0 ? (
                            <div className={classes.cardGrid}>
                                {events.map((event: DiscoverEventItem) => (
                                    <DiscoverCard key={event.id} event={event} />
                                ))}
                            </div>
                        ) : !isLoading ? (
                            <div className={classes.emptyState}>
                                <Stack align="center" gap="sm">
                                    <IconCompass size={48} color="var(--mantine-color-gray-5)" />
                                    <h3><Trans>No events found</Trans></h3>
                                    <p style={{color: 'var(--mantine-color-gray-6)', maxWidth: 400}}>
                                        <Trans>
                                            We couldn't find any events matching your current location and filters. Try increasing the search radius or clearing some filters.
                                        </Trans>
                                    </p>
                                    <Button onClick={resetFilters} variant="light" color="indigo">
                                        <Trans>Reset Filters</Trans>
                                    </Button>
                                </Stack>
                            </div>
                        ) : null}
                    </>
                )}

                {meta && meta.last_page > 1 && (
                    <Group justify="center" mt="xl">
                        <Pagination
                            total={meta.last_page}
                            value={page}
                            onChange={(p) => {
                                setPage(p);
                                if (typeof window !== 'undefined') {
                                    window.scrollTo({top: 300, behavior: 'smooth'});
                                }
                            }}
                            color="indigo"
                        />
                    </Group>
                )}
            </main>

            <footer className={classes.footer}>
                <div>
                    Powered by <a href="https://hi.events" target="_blank" rel="noopener noreferrer" style={{color: 'inherit', fontWeight: 600}}>Hi.Events</a>
                </div>
            </footer>
        </div>
    );
}
