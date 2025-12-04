<?php
/**
 * Trainer Marketplace - Map-based Discovery
 * TeachMe.to-style UX with mobile-first design
 */

if (!defined('ABSPATH')) exit;

// Get filter params
$current_state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
$current_specialty = isset($_GET['specialty']) ? sanitize_text_field($_GET['specialty']) : '';
$user_lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$user_lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

// Get filters for dropdowns
global $wpdb;
$states = $wpdb->get_col(
    "SELECT DISTINCT primary_location_state FROM {$wpdb->prefix}ptp_trainers
     WHERE status = 'approved' AND primary_location_state != ''
     ORDER BY primary_location_state"
);

$specialties = array(
    'Ball Control', 'Finishing', '1v1 Moves', 'Defending', 
    'Goalkeeping', 'Speed & Agility', 'Game IQ', 'Passing & Vision'
);
?>

<div class="ptp-marketplace ptp-training-grid" id="ptpMarketplace">
    
    <!-- Hero Section -->
    <section class="ptp-mp-hero ptp-training-hero" style="background-image: linear-gradient(rgba(14, 15, 17, 0.7), rgba(14, 15, 17, 0.8)), url('https://ptpsummercamps.com/wp-content/uploads/2025/12/BG7A1899.jpg');">
        <div class="ptp-mp-hero-content">
            <h1 class="ptp-training-hero__headline">Don't just dream. Train.</h1>
            <p class="ptp-training-hero__subheadline">Find your perfect private soccer trainer. Train 1-on-1 with current MLS players and D1 college athletes in your area.</p>
            
            <div class="ptp-mp-search-bar ptp-training-search">
                <div class="ptp-mp-search-location ptp-training-search__chip">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <input type="text" id="locationSearch" placeholder="Enter your ZIP code or city" />
                    <button type="button" id="useMyLocation" class="ptp-mp-location-btn" title="Use my location">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <circle cx="12" cy="12" r="3"/>
                            <line x1="12" y1="2" x2="12" y2="6"/>
                            <line x1="12" y1="18" x2="12" y2="22"/>
                            <line x1="2" y1="12" x2="6" y2="12"/>
                            <line x1="18" y1="12" x2="22" y2="12"/>
                        </svg>
                    </button>
                </div>
                <button type="button" id="searchTrainers" class="ptp-btn ptp-btn-primary ptp-training-search__button">Find Trainers</button>
            </div>
        </div>
    </section>
    
    <!-- Main Content -->
    <div class="ptp-mp-main">
        
        <!-- Filters Sidebar (Desktop) / Top Bar (Mobile) -->
        <aside class="ptp-mp-filters ptp-training-filters">
            <div class="ptp-mp-filters-header">
                <h3>Filters</h3>
                <button type="button" id="clearFilters" class="ptp-mp-clear">Clear all</button>
            </div>
            
            <div class="ptp-mp-filter-group">
                <label>State</label>
                <select id="filterState">
                    <option value="">All States</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo esc_attr($state); ?>" <?php selected($current_state, $state); ?>>
                            <?php echo esc_html($state); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ptp-mp-filter-group">
                <label>Specialty</label>
                <select id="filterSpecialty">
                    <option value="">All Specialties</option>
                    <?php foreach ($specialties as $specialty): ?>
                        <option value="<?php echo esc_attr($specialty); ?>" <?php selected($current_specialty, $specialty); ?>>
                            <?php echo esc_html($specialty); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ptp-mp-filter-group">
                <label>Distance</label>
                <select id="filterDistance">
                    <option value="10">Within 10 miles</option>
                    <option value="25" selected>Within 25 miles</option>
                    <option value="50">Within 50 miles</option>
                    <option value="100">Within 100 miles</option>
                </select>
            </div>
            
            <div class="ptp-mp-filter-group">
                <label>Minimum Rating</label>
                <select id="filterRating">
                    <option value="0">Any Rating</option>
                    <option value="4">4+ Stars</option>
                    <option value="4.5">4.5+ Stars</option>
                </select>
            </div>
            
            <div class="ptp-mp-filter-group">
                <label>Sort By</label>
                <select id="filterSort">
                    <option value="distance">Closest</option>
                    <option value="rating">Highest Rated</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                    <option value="sessions">Most Experienced</option>
                </select>
            </div>
        </aside>
        
        <!-- Results Area -->
        <div class="ptp-mp-results">
            
            <!-- Map View Toggle -->
            <div class="ptp-mp-view-toggle">
                <button type="button" class="ptp-mp-view-btn active" data-view="list">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    List
                </button>
                <button type="button" class="ptp-mp-view-btn" data-view="map">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                        <line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>
                    </svg>
                    Map
                </button>
                <span class="ptp-mp-results-count" id="resultsCount">0 trainers</span>
            </div>
            
            <!-- Map Container -->
            <div class="ptp-mp-map-container" id="mapContainer" style="display: none;">
                <div id="trainersMap"></div>
            </div>
            
            <!-- Trainers Grid -->
            <div class="ptp-mp-trainers" id="trainersGrid">
                <div class="ptp-mp-loading">
                    <div class="ptp-spinner"></div>
                    <p>Finding trainers near you...</p>
                </div>
            </div>
            
            <!-- Load More -->
            <div class="ptp-mp-load-more" id="loadMore" style="display: none;">
                <button type="button" class="ptp-btn ptp-btn-outline">Load More Trainers</button>
            </div>
            
        </div>
    </div>
    
    <!-- CTA Section -->
    <section class="ptp-mp-cta">
        <div class="ptp-mp-cta-content">
            <h2>Are You a Coach or Player?</h2>
            <p>Join our network of trainers and start earning money doing what you love</p>
            <a href="<?php echo home_url('/become-a-trainer/'); ?>" class="ptp-btn ptp-btn-secondary">Apply to Become a Trainer</a>
        </div>
    </section>
    
</div>

<!-- Trainer Card Template -->
<template id="trainerCardTemplate">
    <article class="ptp-trainer-card">
        <a href="" class="ptp-trainer-card-link">
            <div class="ptp-trainer-card-media">
                <img src="" alt="" class="ptp-trainer-photo" loading="lazy" />
                <div class="ptp-trainer-video-badge" style="display: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                    Intro Video
                </div>
                <div class="ptp-trainer-featured-badge" style="display: none;">Featured</div>
            </div>
            <div class="ptp-trainer-card-body">
                <div class="ptp-trainer-card-header">
                    <h3 class="ptp-trainer-name"></h3>
                    <div class="ptp-trainer-rating">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#FCB900">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <span class="ptp-trainer-rating-value"></span>
                        <span class="ptp-trainer-review-count"></span>
                    </div>
                </div>
                <p class="ptp-trainer-tagline"></p>
                <div class="ptp-trainer-location">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                    <span class="ptp-trainer-location-text"></span>
                    <span class="ptp-trainer-distance"></span>
                </div>
                <div class="ptp-trainer-specialties"></div>
                <div class="ptp-trainer-card-footer">
                    <div class="ptp-trainer-price">
                        <span class="ptp-trainer-price-label">From</span>
                        <span class="ptp-trainer-price-value"></span>
                        <span class="ptp-trainer-price-unit">/ session</span>
                    </div>
                    <span class="ptp-trainer-sessions"></span>
                </div>
            </div>
        </a>
    </article>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const marketplace = {
        trainers: [],
        map: null,
        markers: [],
        userLocation: null,
        currentPage: 0,
        perPage: 24,
        hasMore: true,
        currentView: 'list',
        
        init: function() {
            this.bindEvents();
            this.checkUserLocation();
            this.loadTrainers();
        },
        
        bindEvents: function() {
            // Location search
            document.getElementById('useMyLocation').addEventListener('click', () => this.getUserLocation());
            document.getElementById('searchTrainers').addEventListener('click', () => this.searchLocation());
            document.getElementById('locationSearch').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.searchLocation();
            });
            
            // Filters
            document.getElementById('filterState').addEventListener('change', () => this.applyFilters());
            document.getElementById('filterSpecialty').addEventListener('change', () => this.applyFilters());
            document.getElementById('filterDistance').addEventListener('change', () => this.applyFilters());
            document.getElementById('filterRating').addEventListener('change', () => this.applyFilters());
            document.getElementById('filterSort').addEventListener('change', () => this.applyFilters());
            document.getElementById('clearFilters').addEventListener('click', () => this.clearFilters());
            
            // View toggle
            document.querySelectorAll('.ptp-mp-view-btn').forEach(btn => {
                btn.addEventListener('click', (e) => this.toggleView(e.currentTarget.dataset.view));
            });
            
            // Load more
            document.getElementById('loadMore').addEventListener('click', () => this.loadMore());
        },
        
        checkUserLocation: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const lat = urlParams.get('lat');
            const lng = urlParams.get('lng');
            
            if (lat && lng) {
                this.userLocation = { lat: parseFloat(lat), lng: parseFloat(lng) };
            }
        },
        
        getUserLocation: function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        this.applyFilters();
                        this.reverseGeocode(this.userLocation);
                    },
                    (error) => {
                        console.error('Geolocation error:', error);
                        alert('Unable to get your location. Please enter your ZIP code.');
                    }
                );
            }
        },
        
        reverseGeocode: function(location) {
            if (window.google && google.maps) {
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ location: location }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        document.getElementById('locationSearch').value = results[0].formatted_address;
                    }
                });
            }
        },
        
        searchLocation: function() {
            const query = document.getElementById('locationSearch').value;
            if (!query) return;
            
            if (window.google && google.maps) {
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ address: query }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        this.userLocation = {
                            lat: results[0].geometry.location.lat(),
                            lng: results[0].geometry.location.lng()
                        };
                        this.applyFilters();
                    } else {
                        alert('Location not found. Please try a different search.');
                    }
                });
            }
        },
        
        buildQueryParams: function() {
            const params = new URLSearchParams();
            
            if (this.userLocation) {
                params.set('lat', this.userLocation.lat);
                params.set('lng', this.userLocation.lng);
            }
            
            const state = document.getElementById('filterState').value;
            if (state) params.set('state', state);
            
            const specialty = document.getElementById('filterSpecialty').value;
            if (specialty) params.set('specialty', specialty);
            
            const distance = document.getElementById('filterDistance').value;
            if (distance) params.set('radius', distance);
            
            const rating = document.getElementById('filterRating').value;
            if (rating) params.set('min_rating', rating);
            
            params.set('limit', this.perPage);
            params.set('offset', this.currentPage * this.perPage);
            
            return params;
        },
        
        applyFilters: function() {
            this.currentPage = 0;
            this.trainers = [];
            this.loadTrainers();
        },
        
        clearFilters: function() {
            document.getElementById('filterState').value = '';
            document.getElementById('filterSpecialty').value = '';
            document.getElementById('filterDistance').value = '25';
            document.getElementById('filterRating').value = '0';
            document.getElementById('filterSort').value = 'distance';
            this.applyFilters();
        },
        
        loadTrainers: async function() {
            const grid = document.getElementById('trainersGrid');
            
            if (this.currentPage === 0) {
                grid.innerHTML = '<div class="ptp-mp-loading"><div class="ptp-spinner"></div><p>Finding trainers...</p></div>';
            }
            
            try {
                const params = this.buildQueryParams();
                const response = await fetch(ptpTraining.rest_url + 'trainers?' + params.toString());
                const data = await response.json();
                
                if (this.currentPage === 0) {
                    this.trainers = data.trainers;
                    grid.innerHTML = '';
                } else {
                    this.trainers = [...this.trainers, ...data.trainers];
                }
                
                this.hasMore = data.has_more;
                this.renderTrainers(data.trainers, this.currentPage === 0);
                this.updateResultsCount(data.total);
                
                document.getElementById('loadMore').style.display = this.hasMore ? 'block' : 'none';
                
                if (this.currentView === 'map') {
                    this.updateMapMarkers();
                }
                
            } catch (error) {
                console.error('Error loading trainers:', error);
                grid.innerHTML = '<div class="ptp-mp-error"><p>Error loading trainers. Please try again.</p></div>';
            }
        },
        
        loadMore: function() {
            this.currentPage++;
            this.loadTrainers();
        },
        
        renderTrainers: function(trainers, clear = false) {
            const grid = document.getElementById('trainersGrid');
            const template = document.getElementById('trainerCardTemplate');
            
            if (clear) grid.innerHTML = '';
            
            if (trainers.length === 0 && this.currentPage === 0) {
                grid.innerHTML = `
                    <div class="ptp-mp-no-results">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <h3>No trainers found</h3>
                        <p>Try adjusting your filters or search in a different area.</p>
                    </div>
                `;
                return;
            }
            
            trainers.forEach(trainer => {
                const card = template.content.cloneNode(true);
                
                card.querySelector('.ptp-trainer-card-link').href = '<?php echo home_url('/trainer/'); ?>' + trainer.slug;
                card.querySelector('.ptp-trainer-photo').src = trainer.profile_photo || '<?php echo PTP_TRAINING_URL; ?>assets/images/default-avatar.svg';
                card.querySelector('.ptp-trainer-photo').alt = trainer.display_name;
                card.querySelector('.ptp-trainer-name').textContent = trainer.display_name;
                card.querySelector('.ptp-trainer-rating-value').textContent = trainer.avg_rating.toFixed(1);
                card.querySelector('.ptp-trainer-review-count').textContent = `(${trainer.total_reviews})`;
                card.querySelector('.ptp-trainer-tagline').textContent = trainer.tagline || '';
                card.querySelector('.ptp-trainer-location-text').textContent = `${trainer.location.city}, ${trainer.location.state}`;
                card.querySelector('.ptp-trainer-price-value').textContent = ptpTraining.currency + trainer.hourly_rate;
                card.querySelector('.ptp-trainer-sessions').textContent = `${trainer.total_sessions} sessions`;
                
                if (trainer.distance) {
                    card.querySelector('.ptp-trainer-distance').textContent = `(${trainer.distance} mi)`;
                }
                
                if (trainer.intro_video_url) {
                    card.querySelector('.ptp-trainer-video-badge').style.display = 'flex';
                }
                
                if (trainer.is_featured) {
                    card.querySelector('.ptp-trainer-featured-badge').style.display = 'block';
                }
                
                // Specialties
                const specialtiesContainer = card.querySelector('.ptp-trainer-specialties');
                (trainer.specialties || []).slice(0, 3).forEach(specialty => {
                    const tag = document.createElement('span');
                    tag.className = 'ptp-trainer-specialty-tag';
                    tag.textContent = specialty;
                    specialtiesContainer.appendChild(tag);
                });
                
                grid.appendChild(card);
            });
        },
        
        updateResultsCount: function(total) {
            document.getElementById('resultsCount').textContent = `${total} trainer${total !== 1 ? 's' : ''}`;
        },
        
        toggleView: function(view) {
            this.currentView = view;
            
            document.querySelectorAll('.ptp-mp-view-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === view);
            });
            
            document.getElementById('trainersGrid').style.display = view === 'list' ? 'grid' : 'none';
            document.getElementById('mapContainer').style.display = view === 'map' ? 'block' : 'none';
            
            if (view === 'map' && !this.map) {
                this.initMap();
            }
        },
        
        initMap: function() {
            if (!window.google || !google.maps) {
                console.error('Google Maps not loaded');
                return;
            }
            
            const center = this.userLocation || { lat: 40.0, lng: -75.5 };
            
            this.map = new google.maps.Map(document.getElementById('trainersMap'), {
                center: center,
                zoom: 10,
                styles: [
                    { featureType: 'poi', stylers: [{ visibility: 'off' }] }
                ]
            });
            
            this.updateMapMarkers();
        },
        
        updateMapMarkers: function() {
            if (!this.map) return;
            
            // Clear existing markers
            this.markers.forEach(marker => marker.setMap(null));
            this.markers = [];
            
            const bounds = new google.maps.LatLngBounds();
            
            this.trainers.forEach(trainer => {
                if (!trainer.location.lat || !trainer.location.lng) return;
                
                const marker = new google.maps.Marker({
                    position: { lat: trainer.location.lat, lng: trainer.location.lng },
                    map: this.map,
                    title: trainer.display_name,
                    icon: {
                        url: '<?php echo PTP_TRAINING_URL; ?>assets/images/map-marker.svg',
                        scaledSize: new google.maps.Size(40, 40)
                    }
                });
                
                const infoWindow = new google.maps.InfoWindow({
                    content: this.createInfoWindowContent(trainer)
                });
                
                marker.addListener('click', () => {
                    infoWindow.open(this.map, marker);
                });
                
                this.markers.push(marker);
                bounds.extend(marker.getPosition());
            });
            
            if (this.markers.length > 0) {
                this.map.fitBounds(bounds);
                if (this.markers.length === 1) {
                    this.map.setZoom(14);
                }
            }
        },
        
        createInfoWindowContent: function(trainer) {
            return `
                <div class="ptp-map-info">
                    <img src="${trainer.profile_photo || ''}" alt="${trainer.display_name}" />
                    <div class="ptp-map-info-content">
                        <h4>${trainer.display_name}</h4>
                        <div class="ptp-map-info-rating">
                            <span class="ptp-star">★</span> ${trainer.avg_rating.toFixed(1)} (${trainer.total_reviews})
                        </div>
                        <p>${trainer.location.city}, ${trainer.location.state}</p>
                        <p class="ptp-map-info-price">${ptpTraining.currency}${trainer.hourly_rate}/session</p>
                        <a href="<?php echo home_url('/trainer/'); ?>${trainer.slug}" class="ptp-map-info-link">View Profile</a>
                    </div>
                </div>
            `;
        }
    };
    
    marketplace.init();
});
</script>
