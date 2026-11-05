import {useEffect, useRef} from 'react';
import {DiscoverEventItem} from '../../../../api/discover.client.ts';
import classes from './Discover.module.scss';

interface DiscoverMapProps {
    events: DiscoverEventItem[];
    userCoords?: { lat: number; lng: number } | null;
    radiusKm?: number;
    onSelectEvent?: (eventId: number) => void;
}

declare global {
    interface Window {
        L: any;
    }
}

export function DiscoverMap({events, userCoords, radiusKm}: DiscoverMapProps) {
    const mapContainerRef = useRef<HTMLDivElement | null>(null);
    const mapInstanceRef = useRef<any>(null);
    const markersLayerRef = useRef<any>(null);

    useEffect(() => {
        if (typeof window === 'undefined') return;

        let isCancelled = false;

        const initLeaflet = () => {
            if (!document.getElementById('leaflet-css')) {
                const link = document.createElement('link');
                link.id = 'leaflet-css';
                link.rel = 'stylesheet';
                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(link);
            }

            if (!window.L) {
                const script = document.createElement('script');
                script.id = 'leaflet-js';
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.async = true;
                script.onload = () => {
                    if (!isCancelled) setupMap();
                };
                document.head.appendChild(script);
            } else {
                setupMap();
            }
        };

        const setupMap = () => {
            if (!mapContainerRef.current || !window.L) return;

            if (mapInstanceRef.current) {
                mapInstanceRef.current.remove();
                mapInstanceRef.current = null;
            }

            const defaultLat = userCoords?.lat ?? 19.0596;
            const defaultLng = userCoords?.lng ?? 72.8295;
            const defaultZoom = userCoords ? 12 : 11;

            const map = window.L.map(mapContainerRef.current).setView([defaultLat, defaultLng], defaultZoom);
            mapInstanceRef.current = map;

            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 18,
            }).addTo(map);

            const markersLayer = window.L.layerGroup().addTo(map);
            markersLayerRef.current = markersLayer;

            if (userCoords) {
                const userIcon = window.L.divIcon({
                    className: 'user-location-marker',
                    html: '<div style="background:#4f46e5;width:16px;height:16px;border-radius:50%;border:3px solid #ffffff;box-shadow:0 0 10px rgba(79,70,229,0.7);"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8],
                });

                window.L.marker([userCoords.lat, userCoords.lng], {icon: userIcon})
                    .bindPopup('<b>📍 Your Location</b>')
                    .addTo(markersLayer);

                if (radiusKm) {
                    window.L.circle([userCoords.lat, userCoords.lng], {
                        radius: radiusKm * 1000,
                        color: '#6366f1',
                        fillColor: '#818cf8',
                        fillOpacity: 0.1,
                        weight: 1.5,
                    }).addTo(markersLayer);
                }
            }

            const bounds = window.L.latLngBounds([]);
            if (userCoords) {
                bounds.extend([userCoords.lat, userCoords.lng]);
            }

            let validCoordinatesCount = 0;

            events.forEach((event) => {
                const lat = event.location?.latitude;
                const lng = event.location?.longitude;

                if (lat != null && lng != null) {
                    validCoordinatesCount++;
                    bounds.extend([lat, lng]);

                    const priceText = event.is_free ? 'Free' : (event.min_price != null ? `₹${event.min_price}` : '');
                    const distText = event.distance_km != null ? `📍 ${event.distance_km} km away` : '';

                    const popupContent = `
                        <div style="font-family:inherit;min-width:180px;padding:2px;">
                            <div style="font-size:11px;font-weight:700;color:#4f46e5;text-transform:uppercase;margin-bottom:2px;">${event.category || 'EVENT'}</div>
                            <h4 style="margin:0 0 6px 0;font-size:14px;line-height:1.25;"><a href="/event/${event.id}/${event.slug}" style="color:#0f172a;text-decoration:none;">${event.title}</a></h4>
                            <div style="font-size:12px;color:#64748b;margin-bottom:4px;">${event.location?.venue_name || ''}</div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;font-size:12px;">
                                <span style="font-weight:700;color:${event.is_free ? '#059669' : '#0f172a'};">${priceText}</span>
                                <span style="color:#6366f1;font-weight:600;">${distText}</span>
                            </div>
                            <div style="margin-top:8px;">
                                <a href="/event/${event.id}/${event.slug}" style="display:inline-block;background:#4f46e5;color:#fff;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;">View Event</a>
                            </div>
                        </div>
                    `;

                    window.L.marker([lat, lng])
                        .bindPopup(popupContent)
                        .addTo(markersLayer);
                }
            });

            if (validCoordinatesCount > 0 && bounds.isValid()) {
                map.fitBounds(bounds, {padding: [40, 40], maxZoom: 14});
            }
        };

        initLeaflet();

        return () => {
            isCancelled = true;
            if (mapInstanceRef.current) {
                mapInstanceRef.current.remove();
                mapInstanceRef.current = null;
            }
        };
    }, [events, userCoords?.lat, userCoords?.lng, radiusKm]);

    return (
        <div className={classes.mapContainer} ref={mapContainerRef} />
    );
}
