import {Badge} from '@mantine/core';
import {IconCalendar, IconMapPin, IconRepeat, IconWorld} from '@tabler/icons-react';
import {Link} from 'react-router';
import {DiscoverEventItem} from '../../../../api/discover.client.ts';
import {formatDate} from '../../../../utilites/dates.ts';
import classes from './Discover.module.scss';

const categoryGradients: Record<string, string> = {
    TECH: 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)',
    MUSIC: 'linear-gradient(135deg, #ec4899 0%, #be185d 100%)',
    FOOD_DRINK: 'linear-gradient(135deg, #f97316 0%, #c2410c 100%)',
    WELLNESS: 'linear-gradient(135deg, #10b981 0%, #047857 100%)',
    ART: 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)',
    WORKSHOP: 'linear-gradient(135deg, #eab308 0%, #a16207 100%)',
    SPORTS: 'linear-gradient(135deg, #06b6d4 0%, #0e7490 100%)',
    DEFAULT: 'linear-gradient(135deg, #6366f1 0%, #4338ca 100%)',
};

const categoryEmojis: Record<string, string> = {
    TECH: '💻',
    MUSIC: '🎵',
    FOOD_DRINK: '🍽️',
    WELLNESS: '🧘',
    ART: '🎨',
    WORKSHOP: '🛠️',
    SPORTS: '⚽',
    NIGHTLIFE: '🪩',
    CHARITY: '🎗️',
    DANCE: '💃',
    FILM: '🎬',
    THEATER: '🎭',
    COMEDY: '😂',
    HOBBIES: '🧩',
    SOCIAL: '🤝',
    FAMILY: '👨‍👩‍👧‍👦',
    EDUCATION: '📚',
    BUSINESS: '💼',
    OUTDOORS: '🏞️',
    DEFAULT: '✨',
};

export function DiscoverCard({event}: { event: DiscoverEventItem }) {
    const category = event.category || 'DEFAULT';
    const gradient = categoryGradients[category] || categoryGradients.DEFAULT;
    const emoji = categoryEmojis[category] || categoryEmojis.DEFAULT;

    const eventDate = event.type === 'RECURRING' && event.next_start_date
        ? event.next_start_date
        : event.start_date;

    const formattedDate = formatDate(eventDate, 'ddd, MMM D · h:mm A', event.timezone || 'UTC');

    const venueText = event.is_online
        ? 'Online Event'
        : (event.location?.venue_name
            ? `${event.location.venue_name}${event.location.city ? ` · ${event.location.city}` : ''}`
            : event.location?.city || 'Local Venue');

    return (
        <Link to={`/event/${event.id}/${event.slug}`} className={classes.eventCard}>
            {event.cover_image_url ? (
                <div
                    className={classes.cardCover}
                    style={{backgroundImage: `url(${event.cover_image_url})`}}
                >
                    <div className={classes.badgeOverlay}>
                        <Badge size="sm" variant="filled" color="indigo" radius="sm">
                            {emoji} {event.category || 'Event'}
                        </Badge>
                        {event.type === 'RECURRING' && (
                            <Badge size="sm" variant="filled" color="grape" radius="sm" leftSection={<IconRepeat size={11} />}>
                                Series
                            </Badge>
                        )}
                    </div>
                    {event.distance_km != null && (
                        <div className={classes.distanceBadge}>
                            📍 {event.distance_km} km
                        </div>
                    )}
                </div>
            ) : (
                <div className={classes.cardCoverPlaceholder} style={{background: gradient}}>
                    <span>{emoji}</span>
                    <div className={classes.badgeOverlay}>
                        <Badge size="sm" variant="filled" color="dark" radius="sm">
                            {event.category || 'Event'}
                        </Badge>
                        {event.type === 'RECURRING' && (
                            <Badge size="sm" variant="filled" color="grape" radius="sm" leftSection={<IconRepeat size={11} />}>
                                Series
                            </Badge>
                        )}
                    </div>
                    {event.distance_km != null && (
                        <div className={classes.distanceBadge}>
                            📍 {event.distance_km} km
                        </div>
                    )}
                </div>
            )}

            <div className={classes.cardBody}>
                <div className={classes.eventDate}>
                    <IconCalendar size={14} />
                    <span>{formattedDate}</span>
                </div>

                <div className={classes.eventTitle} title={event.title}>
                    {event.title}
                </div>

                <div className={classes.eventLocation} title={venueText}>
                    {event.is_online ? <IconWorld size={14} /> : <IconMapPin size={14} />}
                    <span>{venueText}</span>
                </div>

                {event.description_preview && (
                    <div className={classes.eventExcerpt}>
                        {event.description_preview}
                    </div>
                )}

                <div className={classes.cardFooter}>
                    <div className={classes.organizerName} title={event.organizer.name}>
                        {event.organizer.name}
                    </div>
                    <div className={`${classes.priceTag} ${event.is_free ? classes.free : ''}`}>
                        {event.is_free ? 'Free' : `₹${event.min_price || 0}`}
                    </div>
                </div>
            </div>
        </Link>
    );
}
