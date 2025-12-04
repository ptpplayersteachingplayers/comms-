<?php
/**
 * Trainer Profile Page
 * Full profile with video, reviews, availability calendar, and lesson packs
 */

if (!defined('ABSPATH')) exit;

// Get trainer from URL
$trainer_slug = get_query_var('trainer_slug') ?: (isset($_GET['trainer']) ? sanitize_text_field($_GET['trainer']) : '');

if (!$trainer_slug) {
    // Try to get from URL path
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    $trainer_slug = end($parts);
}

$trainer = PTP_Database::get_trainer_by_slug($trainer_slug);

if (!$trainer) {
    echo '<div class="ptp-error"><h2>Trainer Not Found</h2><p>This trainer profile could not be found.</p><a href="' . esc_url(home_url('/private-training/')) . '" class="ptp-btn">Browse All Trainers</a></div>';
    return;
}

// Get trainer user & email for contact
$trainer_email = '';
if (!empty($trainer->user_id)) {
    $trainer_user = get_userdata($trainer->user_id);
    if ($trainer_user && !empty($trainer_user->user_email)) {
        $trainer_email = $trainer_user->user_email;
    }
}

// Get reviews
$reviews = PTP_Database::get_trainer_reviews($trainer->id, 10);

// Get locations
global $wpdb;
$locations = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ptp_trainer_locations WHERE trainer_id = %d ORDER BY is_primary DESC",
    $trainer->id
));

// Parse JSON fields
$specialties = $trainer->specialties ? json_decode($trainer->specialties, true) : array();
$certifications = $trainer->certifications ? json_decode($trainer->certifications, true) : array();
$age_groups = $trainer->age_groups ? json_decode($trainer->age_groups, true) : array();

// Calculate pack pricing
$hourly = floatval($trainer->hourly_rate);
$pack_4_price = floatval($trainer->pack_4_rate) ?: $hourly * 4 * (1 - $trainer->pack_4_discount / 100);
$pack_8_price = floatval($trainer->pack_8_rate) ?: $hourly * 8 * (1 - $trainer->pack_8_discount / 100);
?>

<div class="ptp-profile" id="trainerProfile" data-trainer-id="<?php echo esc_attr($trainer->id); ?>">
    
    <!-- Breadcrumb -->
    <nav class="ptp-profile-breadcrumb">
        <a href="<?php echo home_url('/private-training/'); ?>">Find a Trainer</a>
        <span>/</span>
        <span><?php echo esc_html($trainer->primary_location_state); ?></span>
        <span>/</span>
        <span><?php echo esc_html($trainer->display_name); ?></span>
    </nav>
    
    <!-- Profile Header -->
    <header class="ptp-profile-header">
        <div class="ptp-profile-media">
            <?php if ($trainer->intro_video_url): ?>
                <div class="ptp-profile-video-container">
                    <video 
                        id="introVideo"
                        class="ptp-profile-video"
                        poster="<?php echo esc_url($trainer->intro_video_thumbnail ?: $trainer->profile_photo); ?>"
                        playsinline
                    >
                        <source src="<?php echo esc_url($trainer->intro_video_url); ?>" type="video/mp4">
                    </video>
                    <button type="button" class="ptp-video-play-btn" id="playVideoBtn">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="white">
                            <polygon points="5 3 19 12 5 21 5 3"/>
                        </svg>
                        <span>Watch Intro</span>
                    </button>
                </div>
            <?php else: ?>
                <img 
                    src="<?php echo esc_url($trainer->profile_photo ?: PTP_TRAINING_URL . 'assets/images/default-avatar.svg'); ?>" 
                    alt="<?php echo esc_attr($trainer->display_name); ?>"
                    class="ptp-profile-photo"
                />
            <?php endif; ?>
        </div>
        
        <div class="ptp-profile-info">
            <?php if ($trainer->is_featured): ?>
                <span class="ptp-profile-featured-badge">Featured Trainer</span>
            <?php endif; ?>
            
            <h1 class="ptp-profile-name"><?php echo esc_html($trainer->display_name); ?></h1>
            
            <?php if ($trainer->tagline): ?>
                <p class="ptp-profile-tagline"><?php echo esc_html($trainer->tagline); ?></p>
            <?php endif; ?>
            
            <div class="ptp-profile-meta">
                <div class="ptp-profile-rating">
                    <div class="ptp-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $i <= round($trainer->avg_rating) ? '#FCB900' : '#E5E7EB'; ?>">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        <?php endfor; ?>
                    </div>
                    <span class="ptp-profile-rating-value"><?php echo number_format($trainer->avg_rating, 1); ?></span>
                    <span class="ptp-profile-review-count">(<?php echo $trainer->total_reviews; ?> reviews)</span>
                </div>
                
                <div class="ptp-profile-location">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                    <?php echo esc_html($trainer->primary_location_city . ', ' . $trainer->primary_location_state); ?>
                </div>
                
                <div class="ptp-profile-sessions">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/>
                    </svg>
                    <?php echo number_format($trainer->total_sessions); ?> sessions completed
                </div>
                
                <?php if ($trainer->response_time_hours): ?>
                    <div class="ptp-profile-response">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Typically responds within <?php echo $trainer->response_time_hours; ?> hours
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($specialties)): ?>
                <div class="ptp-profile-specialties">
                    <?php foreach ($specialties as $specialty): ?>
                        <span class="ptp-specialty-tag"><?php echo esc_html($specialty); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="ptp-profile-main">
        
        <!-- Left Column - About, Reviews -->
        <div class="ptp-profile-content">
            
            <!-- About Section -->
            <section class="ptp-profile-section">
                <h2>About <?php echo esc_html(explode(' ', $trainer->display_name)[0]); ?></h2>
                <div class="ptp-profile-bio">
                    <?php echo wpautop(esc_html($trainer->bio)); ?>
                </div>
                
                <?php if ($trainer->credentials): ?>
                    <div class="ptp-profile-credentials">
                        <h3>Background</h3>
                        <p><?php echo esc_html($trainer->credentials); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="ptp-profile-details">
                    <?php if ($trainer->experience_years): ?>
                        <div class="ptp-profile-detail">
                            <span class="ptp-detail-label">Experience</span>
                            <span class="ptp-detail-value"><?php echo $trainer->experience_years; ?>+ years</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($age_groups)): ?>
                        <div class="ptp-profile-detail">
                            <span class="ptp-detail-label">Age Groups</span>
                            <span class="ptp-detail-value"><?php echo implode(', ', $age_groups); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($trainer->service_radius_miles): ?>
                        <div class="ptp-profile-detail">
                            <span class="ptp-detail-label">Service Area</span>
                            <span class="ptp-detail-value">Within <?php echo $trainer->service_radius_miles; ?> miles</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($certifications)): ?>
                    <div class="ptp-profile-certifications">
                        <h3>Certifications</h3>
                        <ul>
                            <?php foreach ($certifications as $cert): ?>
                                <li>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                    <?php echo esc_html($cert); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>
            
            <!-- Training Locations -->
            <?php if (!empty($locations)): ?>
                <section class="ptp-profile-section">
                    <h2>Training Locations</h2>
                    <div class="ptp-profile-locations">
                        <?php foreach ($locations as $location): ?>
                            <div class="ptp-location-card">
                                <div class="ptp-location-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                                    </svg>
                                </div>
                                <div class="ptp-location-info">
                                    <h4><?php echo esc_html($location->name); ?></h4>
                                    <p><?php echo esc_html($location->address); ?></p>
                                    <span class="ptp-location-type"><?php echo esc_html(ucfirst($location->location_type)); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Reviews Section -->
            <section class="ptp-profile-section ptp-profile-reviews">
                <div class="ptp-reviews-header">
                    <h2>Reviews</h2>
                    <div class="ptp-reviews-summary">
                        <span class="ptp-reviews-avg"><?php echo number_format($trainer->avg_rating, 1); ?></span>
                        <div class="ptp-reviews-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo $i <= round($trainer->avg_rating) ? '#FCB900' : '#E5E7EB'; ?>">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <span class="ptp-reviews-total"><?php echo $trainer->total_reviews; ?> reviews</span>
                    </div>
                </div>
                
                <?php if (!empty($reviews)): ?>
                    <div class="ptp-reviews-list">
                        <?php foreach ($reviews as $review): ?>
                            <article class="ptp-review-card">
                                <div class="ptp-review-header">
                                    <div class="ptp-review-author">
                                        <div class="ptp-review-avatar">
                                            <?php echo strtoupper(substr($review->reviewer_name, 0, 1)); ?>
                                        </div>
                                        <div class="ptp-review-author-info">
                                            <span class="ptp-review-name"><?php echo esc_html($review->reviewer_name); ?></span>
                                            <?php if ($review->reviewer_experience): ?>
                                                <span class="ptp-review-experience"><?php echo esc_html($review->reviewer_experience); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ptp-review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="<?php echo $i <= $review->rating ? '#FCB900' : '#E5E7EB'; ?>">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="ptp-review-text"><?php echo esc_html($review->review_text); ?></p>
                                <?php if ($review->skills_improved): ?>
                                    <div class="ptp-review-skills">
                                        <span class="ptp-review-skills-label">Skills improved:</span>
                                        <?php 
                                        $skills = json_decode($review->skills_improved, true) ?: array($review->skills_improved);
                                        foreach ($skills as $skill): 
                                        ?>
                                            <span class="ptp-review-skill-tag"><?php echo esc_html($skill); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <time class="ptp-review-date"><?php echo human_time_diff(strtotime($review->created_at), current_time('timestamp')); ?> ago</time>
                                
                                <?php if ($review->trainer_response): ?>
                                    <div class="ptp-review-response">
                                        <span class="ptp-review-response-label">Response from <?php echo esc_html(explode(' ', $trainer->display_name)[0]); ?>:</span>
                                        <p><?php echo esc_html($review->trainer_response); ?></p>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($trainer->total_reviews > 10): ?>
                        <button type="button" class="ptp-btn ptp-btn-outline ptp-load-more-reviews">
                            Load More Reviews
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="ptp-no-reviews">No reviews yet. Be the first to train with <?php echo esc_html(explode(' ', $trainer->display_name)[0]); ?>!</p>
                <?php endif; ?>
            </section>
            
            <!-- Camp & Clinic Referrals -->
            <?php 
            $summer_camp_url = get_option('ptp_camp_summer_url');
            $clinics_url = get_option('ptp_clinics_url');
            $trainer_referral_code = 'TRAINER-' . strtoupper(str_replace('-', '', $trainer->slug));
            
            if ($summer_camp_url || $clinics_url): 
            ?>
            <section class="ptp-trainer-camps">
                <h3>⚽ Also Train with <?php echo esc_html(explode(' ', $trainer->display_name)[0]); ?> at PTP Camps!</h3>
                <p><?php echo esc_html(explode(' ', $trainer->display_name)[0]); ?> recommends these camps for continued development:</p>
                
                <div class="ptp-trainer-camps-grid">
                    <?php if ($summer_camp_url): ?>
                    <a href="<?php echo esc_url($summer_camp_url); ?>?ref=<?php echo esc_attr($trainer_referral_code); ?>" class="ptp-trainer-camp-card" target="_blank">
                        <img src="https://ptpsummercamps.com/wp-content/uploads/2025/12/BG7A1595.jpg" alt="Summer Soccer Camp" />
                        <h4>Summer Camps 2026</h4>
                        <span>Register Now →</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($clinics_url): ?>
                    <a href="<?php echo esc_url($clinics_url); ?>?ref=<?php echo esc_attr($trainer_referral_code); ?>" class="ptp-trainer-camp-card" target="_blank">
                        <img src="https://ptpsummercamps.com/wp-content/uploads/2025/12/BG7A1563.jpg" alt="Soccer Clinics" />
                        <h4>Skills Clinics</h4>
                        <span>View Schedule →</span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo home_url('/private-training/'); ?>" class="ptp-trainer-camp-card">
                        <img src="https://ptpsummercamps.com/wp-content/uploads/2025/12/BG7A1539.jpg" alt="Private Training" />
                        <h4>More Trainers</h4>
                        <span>Browse All →</span>
                    </a>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <p style="font-size: 13px; margin-bottom: 8px;">Use <?php echo esc_html(explode(' ', $trainer->display_name)[0]); ?>'s code at checkout:</p>
                    <span class="ptp-referral-code" onclick="navigator.clipboard.writeText('<?php echo esc_js($trainer_referral_code); ?>'); this.textContent = '✓ Copied!';">
                        <?php echo esc_html($trainer_referral_code); ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </span>
                </div>
            </section>
            <?php endif; ?>
            
        </div>
        
        <!-- Right Column - Booking Widget -->
        <aside class="ptp-profile-sidebar">
            <div class="ptp-booking-widget" id="bookingWidget">
                <div class="ptp-booking-header">
                    <h3>Book Training</h3>
                </div>
                
                <!-- Pricing Options -->
                <div class="ptp-pricing-options">
                    <label class="ptp-pricing-option" data-pack="single">
                        <input type="radio" name="pack_type" value="single" checked />
                        <div class="ptp-pricing-option-content">
                            <div class="ptp-pricing-option-header">
                                <span class="ptp-pricing-option-title">Single Session</span>
                                <span class="ptp-pricing-option-price">$<?php echo number_format($hourly, 0); ?></span>
                            </div>
                            <span class="ptp-pricing-option-detail">1 session</span>
                        </div>
                    </label>
                    
                    <label class="ptp-pricing-option ptp-pricing-popular" data-pack="pack_4">
                        <input type="radio" name="pack_type" value="pack_4" />
                        <div class="ptp-pricing-option-content">
                            <div class="ptp-pricing-option-header">
                                <span class="ptp-pricing-option-title">4-Pack</span>
                                <span class="ptp-pricing-option-badge">Save <?php echo $trainer->pack_4_discount; ?>%</span>
                                <span class="ptp-pricing-option-price">$<?php echo number_format($pack_4_price, 0); ?></span>
                            </div>
                            <span class="ptp-pricing-option-detail">$<?php echo number_format($pack_4_price / 4, 0); ?>/session</span>
                        </div>
                    </label>
                    
                    <label class="ptp-pricing-option" data-pack="pack_8">
                        <input type="radio" name="pack_type" value="pack_8" />
                        <div class="ptp-pricing-option-content">
                            <div class="ptp-pricing-option-header">
                                <span class="ptp-pricing-option-title">8-Pack</span>
                                <span class="ptp-pricing-option-badge">Save <?php echo $trainer->pack_8_discount; ?>%</span>
                                <span class="ptp-pricing-option-price">$<?php echo number_format($pack_8_price, 0); ?></span>
                            </div>
                            <span class="ptp-pricing-option-detail">$<?php echo number_format($pack_8_price / 8, 0); ?>/session</span>
                        </div>
                    </label>
                </div>
                
                <!-- Availability Calendar -->
                <div class="ptp-availability-section">
                    <h4>Check Availability</h4>
                    <div class="ptp-calendar" id="availabilityCalendar">
                        <div class="ptp-calendar-header">
                            <button type="button" class="ptp-calendar-nav" id="prevMonth">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="15 18 9 12 15 6"/>
                                </svg>
                            </button>
                            <span class="ptp-calendar-month" id="currentMonth"></span>
                            <button type="button" class="ptp-calendar-nav" id="nextMonth">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </button>
                        </div>
                        <div class="ptp-calendar-weekdays">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="ptp-calendar-days" id="calendarDays"></div>
                    </div>
                    
                    <div class="ptp-time-slots" id="timeSlots" style="display: none;">
                        <h4>Available Times for <span id="selectedDateDisplay"></span></h4>
                        <div class="ptp-time-slots-grid" id="timeSlotsGrid"></div>
                    </div>
                </div>
                
                <!-- Book Button -->
                <button type="button" class="ptp-btn ptp-btn-primary ptp-btn-block" id="bookNowBtn">
                    Continue to Booking
                </button>
                
                <p class="ptp-booking-note">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Free cancellation up to 24 hours before
                </p>
            </div>
            
            <!-- Contact Trainer -->
            <div class="ptp-contact-widget">
                <h4>Questions?</h4>
                <p>Message <?php echo esc_html(explode(' ', $trainer->display_name)[0]); ?> directly</p>
                <button type="button" class="ptp-btn ptp-btn-outline ptp-btn-block" id="contactTrainerBtn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    Send Message
                </button>
            </div>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trainerId = <?php echo $trainer->id; ?>;
    const hourlyRate = <?php echo $hourly; ?>;
    const pack4Price = <?php echo $pack_4_price; ?>;
    const pack8Price = <?php echo $pack_8_price; ?>;
    
    let selectedDate = null;
    let selectedTime = null;
    let selectedPack = 'single';
    let currentMonth = new Date();
    
    // Video player
    const video = document.getElementById('introVideo');
    const playBtn = document.getElementById('playVideoBtn');
    
    if (video && playBtn) {
        playBtn.addEventListener('click', function() {
            video.play();
            playBtn.style.display = 'none';
        });
        
        video.addEventListener('pause', function() {
            playBtn.style.display = 'flex';
        });
        
        video.addEventListener('ended', function() {
            playBtn.style.display = 'flex';
        });
    }
    
    // Pack selection
    document.querySelectorAll('.ptp-pricing-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.ptp-pricing-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            selectedPack = this.dataset.pack;
            this.querySelector('input').checked = true;
        });
    });
    
    // Calendar
    function renderCalendar() {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        
        document.getElementById('currentMonth').textContent = 
            new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        let html = '';
        
        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            html += '<span class="ptp-calendar-day empty"></span>';
        }
        
        // Days
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const dateStr = date.toISOString().split('T')[0];
            const isPast = date < today;
            const isSelected = selectedDate === dateStr;
            
            let classes = 'ptp-calendar-day';
            if (isPast) classes += ' disabled';
            if (isSelected) classes += ' selected';
            
            html += `<span class="${classes}" data-date="${dateStr}">${day}</span>`;
        }
        
        document.getElementById('calendarDays').innerHTML = html;
        
        // Bind day clicks
        document.querySelectorAll('.ptp-calendar-day:not(.disabled):not(.empty)').forEach(day => {
            day.addEventListener('click', function() {
                document.querySelectorAll('.ptp-calendar-day').forEach(d => d.classList.remove('selected'));
                this.classList.add('selected');
                selectedDate = this.dataset.date;
                loadTimeSlots(selectedDate);
            });
        });
    }
    
    document.getElementById('prevMonth').addEventListener('click', function() {
        currentMonth.setMonth(currentMonth.getMonth() - 1);
        renderCalendar();
    });
    
    document.getElementById('nextMonth').addEventListener('click', function() {
        currentMonth.setMonth(currentMonth.getMonth() + 1);
        renderCalendar();
    });
    
    async function loadTimeSlots(date) {
        const container = document.getElementById('timeSlots');
        const grid = document.getElementById('timeSlotsGrid');
        
        container.style.display = 'block';
        document.getElementById('selectedDateDisplay').textContent = 
            new Date(date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
        
        grid.innerHTML = '<div class="ptp-loading-slots">Loading...</div>';
        
        try {
            const response = await fetch(`${ptpTraining.rest_url}trainers/${trainerId}/slots?date=${date}`);
            const slots = await response.json();
            
            if (slots.length === 0) {
                grid.innerHTML = '<p class="ptp-no-slots">No available times on this date</p>';
                return;
            }
            
            grid.innerHTML = slots.map(slot => `
                <button type="button" class="ptp-time-slot" data-time="${slot.start}">
                    ${slot.formatted}
                </button>
            `).join('');
            
            // Bind slot clicks
            document.querySelectorAll('.ptp-time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.ptp-time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedTime = this.dataset.time;
                });
            });
            
        } catch (error) {
            console.error('Error loading slots:', error);
            grid.innerHTML = '<p class="ptp-error-slots">Error loading times. Please try again.</p>';
        }
    }
    
    // Book Now
    document.getElementById('bookNowBtn').addEventListener('click', function() {
        if (!ptpTraining.user_logged_in) {
            window.location.href = '<?php echo wp_login_url(get_permalink()); ?>';
            return;
        }
        
        const params = new URLSearchParams({
            trainer: trainerId,
            pack: selectedPack
        });
        
        if (selectedDate) params.set('date', selectedDate);
        if (selectedTime) params.set('time', selectedTime);
        
        window.location.href = '<?php echo home_url('/book-training/'); ?>?' + params.toString();
    });
    
    // Contact trainer
    document.getElementById('contactTrainerBtn').addEventListener('click', function() {
        if (!ptpTraining.user_logged_in) {
            window.location.href = '<?php echo wp_login_url(get_permalink()); ?>';
            return;
        }
        // For now, open email to trainer so parents can reach out directly
        const trainerEmail = '<?php echo esc_js($trainer_email); ?>';
        if (trainerEmail) {
            window.location.href = 'mailto:' + trainerEmail + '?subject=' + encodeURIComponent('PTP Training with <?php echo esc_js($trainer->display_name); ?>');
        } else {
            alert('Please email PTP and we will connect you with this trainer.');
        }
    });
    
    // Initialize
    renderCalendar();
});
</script>
