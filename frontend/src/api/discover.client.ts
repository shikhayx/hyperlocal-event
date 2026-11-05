import {publicApi} from "./public-client.ts";

export interface DiscoverEventItem {
    id: number;
    short_id: string;
    slug: string;
    title: string;
    category: string | null;
    description_preview: string;
    type: 'SINGLE' | 'RECURRING';
    start_date: string;
    end_date: string | null;
    next_start_date: string | null;
    timezone: string;
    is_free: boolean;
    min_price: number | null;
    currency: string | null;
    cover_image_url: string | null;
    organizer: {
        id: number;
        name: string;
    };
    is_online: boolean;
    location: {
        venue_name: string | null;
        city: string | null;
        latitude: number | null;
        longitude: number | null;
    } | null;
    distance_km: number | null;
    url?: string;
}

export interface DiscoverEventsParams {
    lat?: number | null;
    lng?: number | null;
    radius_km?: number | null;
    include_online?: boolean | null;
    categories?: string[] | null;
    when?: 'today' | 'tonight' | 'weekend' | null;
    date_from?: string | null;
    date_to?: string | null;
    tz?: string | null;
    free?: boolean | null;
    q?: string | null;
    sort?: 'distance' | 'date' | 'relevance' | null;
    page?: number | null;
    per_page?: number | null;
}

export interface DiscoverEventsResponse {
    data: DiscoverEventItem[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
}

export const discoverEvents = async (params: DiscoverEventsParams): Promise<DiscoverEventsResponse> => {
    const cleanParams: Record<string, any> = {};

    if (params.lat != null) cleanParams.lat = params.lat;
    if (params.lng != null) cleanParams.lng = params.lng;
    if (params.radius_km != null) cleanParams.radius_km = params.radius_km;
    if (params.include_online != null) cleanParams.include_online = params.include_online;
    if (params.categories && params.categories.length > 0) cleanParams['categories[]'] = params.categories;
    if (params.when) cleanParams.when = params.when;
    if (params.date_from) cleanParams.date_from = params.date_from;
    if (params.date_to) cleanParams.date_to = params.date_to;
    if (params.tz) cleanParams.tz = params.tz;
    if (params.free != null) cleanParams.free = params.free;
    if (params.q) cleanParams.q = params.q;
    if (params.sort) cleanParams.sort = params.sort;
    if (params.page != null) cleanParams.page = params.page;
    if (params.per_page != null) cleanParams.per_page = params.per_page;

    const response = await publicApi.get<DiscoverEventsResponse>('/events/discover', {
        params: cleanParams,
        paramsSerializer: {
            indexes: null,
        },
    });

    return response.data;
};
