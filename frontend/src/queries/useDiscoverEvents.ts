import {useQuery} from "@tanstack/react-query";
import {discoverEvents, DiscoverEventsParams, DiscoverEventsResponse} from "../api/discover.client.ts";

export const useDiscoverEvents = (params: DiscoverEventsParams) => {
    return useQuery<DiscoverEventsResponse>({
        queryKey: ['discoverEvents', params],
        queryFn: () => discoverEvents(params),
        staleTime: 60 * 1000,
    });
};
