const DEFAULT_CENTER = [-2.5, 118];
const DEFAULT_ZOOM = 5;
const PIN_ZOOM = 17;
const SEARCH_DELAY = 700;
const REVERSE_DELAY = 600;

const PIN_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 40" width="32" height="40" aria-hidden="true">'
    + '<path d="M16 0C7.2 0 0 7.1 0 15.9 0 27.5 16 40 16 40s16-12.5 16-24.1C32 7.1 24.8 0 16 0z" fill="#7E8C69"/>'
    + '<circle cx="16" cy="16" r="6" fill="#ffffff"/></svg>';

export default function locationPicker(cfg) {
    // Leaflet objects stay outside Alpine's reactive state on purpose.
    let L = null;
    let map = null;
    let marker = null;

    return {
        query: '',
        results: [],
        notice: '',
        error: '',
        searching: false,
        locating: false,
        resolving: false,
        mapFailed: false,
        latitude: cfg.latitude ?? null,
        longitude: cfg.longitude ?? null,
        address: cfg.address || '',
        postalCode: cfg.postalCode || '',
        destZip: cfg.destinationZip || '',
        pinPostcode: '',
        suggested: '',
        addressTouched: Boolean((cfg.address || '').trim()),
        searchTimer: null,
        reverseTimer: null,
        searchId: 0,
        reverseId: 0,

        get hasPin() {
            return this.latitude !== null && this.longitude !== null;
        },

        get coordinates() {
            return this.hasPin ? this.latitude.toFixed(5) + ', ' + this.longitude.toFixed(5) : '';
        },

        get zipMismatch() {
            return cfg.areaLinked && this.hasPin && !!this.destZip && !!this.pinPostcode && this.destZip !== this.pinPostcode;
        },

        async init() {
            try {
                const [leaflet] = await Promise.all([
                    import('leaflet'),
                    import('leaflet/dist/leaflet.css'),
                ]);
                L = leaflet.default ?? leaflet;
            } catch (e) {
                this.mapFailed = true;
                return;
            }

            this.$nextTick(() => this.buildMap());
        },

        destroy() {
            if (map) {
                map.remove();
                map = null;
                marker = null;
            }
        },

        onDestination(detail) {
            const zip = detail && detail.zip ? String(detail.zip) : '';

            this.destZip = zip;

            if (zip) {
                this.postalCode = zip;
            }
        },

        buildMap() {
            const start = this.hasPin ? [this.latitude, this.longitude] : DEFAULT_CENTER;

            map = L.map(this.$refs.map, {
                center: start,
                zoom: this.hasPin ? PIN_ZOOM : DEFAULT_ZOOM,
                scrollWheelZoom: false,
            });

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
            }).addTo(map);

            if (this.hasPin) {
                this.createMarker(start);
            }

            // Wheel zoom only after the map is focused, so the page can still scroll.
            map.on('focus', () => map.scrollWheelZoom.enable());
            map.on('blur', () => map.scrollWheelZoom.disable());

            map.on('click', (e) => {
                const p = e.latlng.wrap();
                this.setPoint(p.lat, p.lng, { pan: false });
            });

            setTimeout(() => map && map.invalidateSize(), 250);
        },

        createMarker(latlng) {
            const icon = L.divIcon({
                className: 'location-pin',
                html: PIN_SVG,
                iconSize: [32, 40],
                iconAnchor: [16, 40],
            });

            marker = L.marker(latlng, { icon, draggable: true }).addTo(map);

            marker.on('dragend', () => {
                const p = marker.getLatLng().wrap();
                this.setPoint(p.lat, p.lng, { pan: false });
            });
        },

        setPoint(lat, lng, options = {}) {
            const { reverse = true, pan = true, zoom = null } = options;

            this.latitude = Number(Number(lat).toFixed(7));
            this.longitude = Number(Number(lng).toFixed(7));
            this.error = '';
            this.notice = '';

            if (map) {
                const latlng = [this.latitude, this.longitude];

                if (marker) {
                    marker.setLatLng(latlng);
                } else {
                    this.createMarker(latlng);
                }

                if (pan) {
                    map.setView(latlng, zoom ?? Math.max(map.getZoom(), 16));
                }
            }

            if (reverse) {
                clearTimeout(this.reverseTimer);
                this.reverseTimer = setTimeout(() => this.resolveAddress(), REVERSE_DELAY);
            }
        },

        clearPin() {
            if (marker) {
                marker.remove();
                marker = null;
            }

            this.latitude = null;
            this.longitude = null;
            this.suggested = '';
            this.pinPostcode = '';
            this.notice = '';
        },

        async request(url) {
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(body.message || 'Request failed. Please try again.');
            }

            return body;
        },

        onInput() {
            this.error = '';
            this.notice = '';
            clearTimeout(this.searchTimer);

            if (this.query.trim().length < 3) {
                this.results = [];
                return;
            }

            this.searchTimer = setTimeout(() => this.search(), SEARCH_DELAY);
        },

        searchNow() {
            clearTimeout(this.searchTimer);

            if (this.query.trim().length >= 3) {
                this.search();
            }
        },

        async search() {
            const id = ++this.searchId;
            this.searching = true;
            this.error = '';
            this.notice = '';

            try {
                const body = await this.request(cfg.searchUrl + '?q=' + encodeURIComponent(this.query.trim()));

                if (id !== this.searchId) return;

                this.results = body.data || [];

                if (!this.results.length) {
                    this.notice = body.message || 'No place found.';
                }
            } catch (e) {
                if (id !== this.searchId) return;
                this.results = [];
                this.error = e.message;
            } finally {
                if (id === this.searchId) this.searching = false;
            }
        },

        pick(place) {
            this.results = [];
            this.query = place.label;
            this.setPoint(place.lat, place.lng, { reverse: false, zoom: PIN_ZOOM });
            this.pinPostcode = place.postcode || '';
            this.applyPlace(place);
        },

        async resolveAddress() {
            if (!this.hasPin) return;

            const id = ++this.reverseId;
            this.resolving = true;
            this.error = '';

            try {
                const body = await this.request(
                    cfg.reverseUrl + '?lat=' + encodeURIComponent(this.latitude) + '&lng=' + encodeURIComponent(this.longitude)
                );

                if (id !== this.reverseId) return;

                if (Array.isArray(body.data) || !body.data) {
                    this.suggested = '';
                    this.pinPostcode = '';
                    this.notice = body.message || '';
                } else {
                    this.pinPostcode = body.data.postcode || '';
                    this.applyPlace(body.data);
                }
            } catch (e) {
                if (id !== this.reverseId) return;
                this.error = e.message;
            } finally {
                if (id === this.reverseId) this.resolving = false;
            }
        },

        applyPlace(place) {
            // Linked to a shipping area: the pin only suggests the street line.
            const text = cfg.areaLinked ? (place && place.street) : (place && place.label);

            if (!text) {
                this.suggested = '';
                return;
            }

            this.suggested = text;

            if (!this.addressTouched) {
                this.address = text;
            }

            if (!cfg.areaLinked && cfg.withPostal && place.postcode && (!this.addressTouched || !this.postalCode)) {
                this.postalCode = place.postcode;
            }
        },

        useSuggested() {
            this.address = this.suggested;
            this.addressTouched = false;
        },

        locate() {
            this.error = '';
            this.notice = '';

            if (!window.isSecureContext || !navigator.geolocation) {
                this.error = 'Location detection needs a secure (https) page and a supported browser. Tap the map to drop the pin instead.';
                return;
            }

            this.locating = true;

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.locating = false;
                    this.setPoint(pos.coords.latitude, pos.coords.longitude, { zoom: PIN_ZOOM });
                },
                (err) => {
                    this.locating = false;
                    this.error = this.geoMessage(err);
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
            );
        },

        geoMessage(err) {
            if (err.code === 1) {
                return 'Location permission was denied. Allow location access in your browser settings, or tap the map instead.';
            }

            if (err.code === 3) {
                return 'Finding your location took too long. Please try again.';
            }

            return 'We could not detect your location. Tap the map to drop the pin instead.';
        },
    };
}
