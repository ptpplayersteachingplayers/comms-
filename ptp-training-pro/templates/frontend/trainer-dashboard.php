<?php
/**
 * Trainer Dashboard - Polished UI
 * Bigger hero, mobile optimized
 */

if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();

if (!$user_id) {
    ?>
    <div class="ptp-dashboard">
        <div class="ptp-login-required">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <h2>Login Required</h2>
            <p>Please log in to access your trainer dashboard.</p>
            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="ptp-btn ptp-btn-primary">Log In</a>
        </div>
    </div>
    <?php
    return;
}

$trainer = PTP_Database::get_trainer_by_user($user_id);

if (!$trainer) {
    ?>
    <div class="ptp-dashboard">
        <div class="ptp-login-required">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <h2>Not a Trainer Yet?</h2>
            <p>Join our network of elite soccer coaches.</p>
            <a href="<?php echo home_url('/become-a-trainer/'); ?>" class="ptp-btn ptp-btn-primary">Apply Now</a>
        </div>
    </div>
    <?php
    return;
}

if ($trainer->status !== 'approved') {
    ?>
    <div class="ptp-dashboard">
        <div class="ptp-login-required">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#FCB900" stroke-width="1.5">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            <h2>Application Under Review</h2>
            <p>We're reviewing your application. You'll receive an email once approved (usually within 2-3 business days).</p>
            <a href="<?php echo home_url(); ?>" class="ptp-btn ptp-btn-outline">Back to Home</a>
        </div>
    </div>
    <?php
    return;
}

// Get stats
$stats = PTP_Database::get_trainer_stats($trainer->id);
$platform_fee = get_option('ptp_platform_fee_percent', 25);
$trainer_cut = (100 - $platform_fee) / 100;

// Get sessions
$upcoming_sessions = PTP_Database::get_trainer_sessions($trainer->id, 'scheduled', date('Y-m-d'));
$past_sessions = PTP_Database::get_trainer_sessions($trainer->id, 'completed');

// Check integrations
$stripe_connected = $trainer->stripe_onboarding_complete;
$calendar_connected = !empty($trainer->google_calendar_token);

// Parse data
$specialties = $trainer->specialties ? json_decode($trainer->specialties, true) : [];
$age_groups = $trainer->age_groups ? json_decode($trainer->age_groups, true) : [];
$locations = PTP_Database::get_trainer_locations($trainer->id);
?>

<div class="ptp-dashboard" id="trainerDashboard">
    
    <!-- BIG HERO Section -->
    <div class="ptp-dashboard-hero" style="background-image: linear-gradient(rgba(14, 15, 17, 0.7), rgba(14, 15, 17, 0.85)), url('https://ptpsummercamps.com/wp-content/uploads/2025/12/BG7A1886.jpg');">
        <div class="ptp-dashboard-hero-content">
            <div class="ptp-dashboard-welcome">
                <h1>Welcome back, <?php echo esc_html($trainer->display_name); ?>!</h1>
                <p>Here's what's happening with your training business</p>
            </div>
            
            <div class="ptp-dashboard-stats">
                <div class="ptp-dashboard-stat">
                    <span class="ptp-dashboard-stat-value"><?php echo $stats['upcoming_sessions'] ?? 0; ?></span>
                    <span class="ptp-dashboard-stat-label">Upcoming Sessions</span>
                </div>
                <div class="ptp-dashboard-stat">
                    <span class="ptp-dashboard-stat-value"><?php echo $stats['total_sessions'] ?? 0; ?></span>
                    <span class="ptp-dashboard-stat-label">Total Completed</span>
                </div>
                <div class="ptp-dashboard-stat">
                    <span class="ptp-dashboard-stat-value"><?php echo number_format($trainer->avg_rating ?: 5.0, 1); ?></span>
                    <span class="ptp-dashboard-stat-label">Avg Rating</span>
                </div>
                <div class="ptp-dashboard-stat">
                    <span class="ptp-dashboard-stat-value">$<?php echo number_format(($stats['total_earnings'] ?? 0) * $trainer_cut, 0); ?></span>
                    <span class="ptp-dashboard-stat-label">Total Earned</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ptp-dashboard-main">
        
        <!-- Alerts -->
        <?php if (!$stripe_connected): ?>
        <div class="ptp-dashboard-card" style="background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border-left: 4px solid #F59E0B; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div style="flex: 1;">
                    <strong style="color: #92400E; font-size: 16px;">Complete Your Payment Setup</strong>
                    <p style="margin: 4px 0 0; color: #92400E; opacity: 0.8;">Connect Stripe to start receiving payouts for completed sessions.</p>
                </div>
                <button type="button" class="ptp-btn ptp-btn-secondary" id="connectStripeBtn">Connect Stripe</button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="ptp-dashboard-card" style="margin-bottom: 24px;">
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                <a href="<?php echo home_url('/trainer/' . $trainer->slug); ?>" class="ptp-btn ptp-btn-outline" target="_blank">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    View Public Profile
                </a>
                <button type="button" class="ptp-btn ptp-btn-outline" data-tab-target="profile">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Profile
                </button>
                <?php if ($calendar_connected): ?>
                <span style="margin-left: auto; color: var(--ptp-green); font-size: 14px; display: flex; align-items: center; gap: 6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Calendar Connected
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="ptp-tabs ptp-dashboard-tabs">
            <button class="ptp-tab active" data-tab="sessions">Sessions</button>
            <button class="ptp-tab" data-tab="profile">Profile</button>
            <button class="ptp-tab" data-tab="availability">Availability</button>
            <button class="ptp-tab" data-tab="messages">Messages</button>
            <button class="ptp-tab" data-tab="earnings">Earnings</button>
            <button class="ptp-tab" data-tab="settings">Settings</button>
        </div>
        
        <!-- Sessions Tab -->
        <div class="ptp-tab-content active" data-content="sessions">
            <div class="ptp-dashboard-grid">
                <!-- Upcoming -->
                <div class="ptp-dashboard-card">
                    <div class="ptp-dashboard-card-header">
                        <h2>Upcoming Sessions</h2>
                    </div>
                    
                    <?php if (!empty($upcoming_sessions)): ?>
                    <div class="ptp-sessions-grid">
                        <?php foreach (array_slice($upcoming_sessions, 0, 5) as $session): ?>
                        <div class="ptp-session-card">
                            <div class="ptp-session-date">
                                <span class="ptp-session-date-day"><?php echo date('d', strtotime($session->session_date)); ?></span>
                                <span class="ptp-session-date-month"><?php echo date('M', strtotime($session->session_date)); ?></span>
                            </div>
                            <div class="ptp-session-info">
                                <h3><?php echo esc_html($session->athlete_name); ?></h3>
                                <div class="ptp-session-meta">
                                    <span class="ptp-session-meta-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        <?php echo date('g:i A', strtotime($session->start_time)); ?>
                                    </span>
                                    <?php if (!empty($session->location_name)): ?>
                                    <span class="ptp-session-meta-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        <?php echo esc_html($session->location_name); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ptp-session-actions">
                                <button type="button" class="ptp-btn ptp-btn-primary ptp-btn-sm complete-session-btn" data-id="<?php echo $session->id; ?>">Complete</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="ptp-empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <h3>No Upcoming Sessions</h3>
                        <p>When families book sessions with you, they'll appear here.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Activity -->
                <div class="ptp-dashboard-card">
                    <div class="ptp-dashboard-card-header">
                        <h2>Recent Completed</h2>
                    </div>
                    
                    <?php if (!empty($past_sessions)): ?>
                    <div class="ptp-sessions-grid">
                        <?php foreach (array_slice($past_sessions, 0, 5) as $session): ?>
                        <div class="ptp-session-card" style="opacity: 0.8;">
                            <div class="ptp-session-date" style="background: var(--ptp-green-light);">
                                <span class="ptp-session-date-day"><?php echo date('d', strtotime($session->session_date)); ?></span>
                                <span class="ptp-session-date-month" style="color: var(--ptp-green);"><?php echo date('M', strtotime($session->session_date)); ?></span>
                            </div>
                            <div class="ptp-session-info">
                                <h3><?php echo esc_html($session->athlete_name); ?></h3>
                                <div class="ptp-session-meta">
                                    <span class="ptp-session-meta-item" style="color: var(--ptp-green);">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        Completed
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="ptp-empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                        <h3>No Sessions Yet</h3>
                        <p>Your completed sessions will appear here.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Profile Tab -->
        <div class="ptp-tab-content" data-content="profile">
            <div class="ptp-dashboard-card">
                <div class="ptp-dashboard-card-header">
                    <h2>Edit Your Profile</h2>
                </div>
                
                <form id="trainerProfileForm" class="ptp-profile-form">
                    <?php wp_nonce_field('ptp_update_profile', 'ptp_nonce'); ?>
                    <input type="hidden" name="trainer_id" value="<?php echo $trainer->id; ?>" />
                    
                    <div class="ptp-form-row">
                        <div class="ptp-form-group">
                            <label>Display Name</label>
                            <input type="text" name="display_name" value="<?php echo esc_attr($trainer->display_name); ?>" required />
                        </div>
                        <div class="ptp-form-group">
                            <label>Tagline</label>
                            <input type="text" name="tagline" value="<?php echo esc_attr($trainer->tagline); ?>" placeholder="e.g., Former MLS Player | Speed & Agility Specialist" />
                        </div>
                    </div>
                    
                    <div class="ptp-form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="5" placeholder="Tell families about yourself..."><?php echo esc_textarea($trainer->bio); ?></textarea>
                    </div>
                    
                    <div class="ptp-form-row">
                        <div class="ptp-form-group">
                            <label>Hourly Rate ($)</label>
                            <input type="number" name="hourly_rate" value="<?php echo esc_attr($trainer->hourly_rate); ?>" min="25" max="500" required />
                        </div>
                        <div class="ptp-form-group">
                            <label>Experience (years)</label>
                            <input type="number" name="experience_years" value="<?php echo esc_attr($trainer->experience_years); ?>" min="0" max="50" />
                        </div>
                    </div>
                    
                    <div class="ptp-form-group">
                        <label>Intro Video URL</label>
                        <input type="url" name="video_url" value="<?php echo esc_attr($trainer->video_url); ?>" placeholder="YouTube or Vimeo link" />
                    </div>
                    
                    <div style="margin-top: 24px;">
                        <button type="submit" class="ptp-btn ptp-btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Availability Tab -->
        <div class="ptp-tab-content" data-content="availability">
            <div class="ptp-dashboard-card">
                <div class="ptp-dashboard-card-header">
                    <h2>Weekly Availability</h2>
                </div>
                
                <p style="margin-bottom: 24px; color: var(--ptp-gray-500);">Set your regular weekly availability. Families can only book during these times.</p>
                
                <form id="availabilityForm">
                    <?php 
                    $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
                    $availability = PTP_Database::get_trainer_availability($trainer->id);
                    $avail_by_day = array();
                    foreach ($availability as $slot) {
                        $avail_by_day[$slot->day_of_week][] = $slot;
                    }
                    
                    foreach ($days as $index => $day): 
                    ?>
                    <div class="ptp-availability-day" style="display: flex; gap: 16px; align-items: flex-start; padding: 16px 0; border-bottom: 1px solid var(--ptp-gray-100);">
                        <div style="width: 100px; font-weight: 600;"><?php echo $day; ?></div>
                        <div class="ptp-availability-slots" data-day="<?php echo $index; ?>">
                            <?php if (!empty($avail_by_day[$index])): ?>
                                <?php foreach ($avail_by_day[$index] as $slot): ?>
                                    <div class="ptp-availability-slot" style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px;">
                                        <input type="time" name="avail[<?php echo $index; ?>][start][]" value="<?php echo substr($slot->start_time, 0, 5); ?>" />
                                        <span>to</span>
                                        <input type="time" name="avail[<?php echo $index; ?>][end][]" value="<?php echo substr($slot->end_time, 0, 5); ?>" />
                                        <button type="button" class="ptp-remove-slot" onclick="this.parentElement.remove()" style="background: none; border: none; color: var(--ptp-red); cursor: pointer;">×</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: var(--ptp-gray-400);">Not available</span>
                            <?php endif; ?>
                            <button type="button" class="ptp-add-slot" onclick="addSlot(this, <?php echo $index; ?>)" style="background: none; border: none; color: var(--ptp-yellow-hover); font-size: 13px; cursor: pointer; padding: 4px 0;">+ Add time slot</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top: 24px;">
                        <button type="submit" class="ptp-btn ptp-btn-primary">Save Availability</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Messages Tab -->
        <div class="ptp-tab-content" data-content="messages">
            <div class="ptp-dashboard-card">
                <div class="ptp-dashboard-card-header">
                    <h2>Messages</h2>
                </div>
                
                <?php 
                global $wpdb;
                $trainer_user_id = $trainer->user_id;
                $messages = $wpdb->get_results($wpdb->prepare(
                    "SELECT m.*, 
                            u.display_name as customer_name,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}ptp_messages WHERE conversation_id = m.conversation_id AND receiver_id = %d AND is_read = 0) as unread_count
                     FROM {$wpdb->prefix}ptp_messages m
                     LEFT JOIN {$wpdb->users} u ON (
                         CASE 
                             WHEN m.sender_id = %d THEN m.receiver_id = u.ID
                             ELSE m.sender_id = u.ID
                         END
                     )
                     WHERE (m.sender_id = %d OR m.receiver_id = %d)
                     GROUP BY m.conversation_id
                     ORDER BY m.created_at DESC",
                    $trainer_user_id, $trainer_user_id, $trainer_user_id, $trainer_user_id
                ));
                ?>
                
                <?php if (empty($messages)): ?>
                    <div class="ptp-empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <h3>No Messages Yet</h3>
                        <p>When families reach out, their messages will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="ptp-conversations-list">
                        <?php foreach ($messages as $msg): ?>
                            <div class="ptp-conversation-item" data-conversation="<?php echo esc_attr($msg->conversation_id); ?>" style="display: flex; gap: 12px; padding: 16px; border-bottom: 1px solid var(--ptp-gray-100); cursor: pointer;">
                                <div style="width: 40px; height: 40px; background: var(--ptp-gray-200); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: var(--ptp-gray-600);">
                                    <?php echo strtoupper(substr($msg->customer_name ?: 'U', 0, 1)); ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <strong><?php echo esc_html($msg->customer_name ?: 'Customer'); ?></strong>
                                        <span style="font-size: 12px; color: var(--ptp-gray-400);"><?php echo human_time_diff(strtotime($msg->created_at), current_time('timestamp')); ?> ago</span>
                                    </div>
                                    <p style="margin: 4px 0 0; color: var(--ptp-gray-500); font-size: 14px;"><?php echo esc_html(wp_trim_words($msg->message_text, 10, '...')); ?></p>
                                </div>
                                <?php if ($msg->unread_count > 0): ?>
                                    <span style="background: var(--ptp-yellow); color: var(--ptp-ink); padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600;"><?php echo $msg->unread_count; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Earnings Tab -->
        <div class="ptp-tab-content" data-content="earnings">
            <div class="ptp-dashboard-card">
                <div class="ptp-dashboard-card-header">
                    <h2>Earnings Overview</h2>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div style="background: var(--ptp-gray-50); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 800; color: var(--ptp-green);">$<?php echo number_format(($stats['total_earnings'] ?? 0) * $trainer_cut, 2); ?></div>
                        <div style="font-size: 14px; color: var(--ptp-gray-500);">Total Earned (<?php echo 100 - $platform_fee; ?>%)</div>
                    </div>
                    <div style="background: var(--ptp-gray-50); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 800; color: var(--ptp-ink);"><?php echo $stats['total_sessions'] ?? 0; ?></div>
                        <div style="font-size: 14px; color: var(--ptp-gray-500);">Sessions Completed</div>
                    </div>
                    <div style="background: var(--ptp-gray-50); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 32px; font-weight: 800; color: var(--ptp-ink);">$<?php echo number_format($trainer->hourly_rate * $trainer_cut, 0); ?></div>
                        <div style="font-size: 14px; color: var(--ptp-gray-500);">Your Rate (after fee)</div>
                    </div>
                </div>
                
                <p style="color: var(--ptp-gray-500); font-size: 14px;">
                    💡 PTP takes a <?php echo $platform_fee; ?>% platform fee. You keep <?php echo 100 - $platform_fee; ?>% of each session. 
                    Payouts are processed weekly via Stripe.
                </p>
            </div>
            
            <!-- Referral Earnings -->
            <div class="ptp-dashboard-card" style="margin-top: 24px;">
                <div class="ptp-dashboard-card-header">
                    <h2>Camp Referral Program</h2>
                </div>
                
                <p style="margin-bottom: 16px;">Share your unique code with families. When they register for PTP camps or clinics, you earn <?php echo get_option('ptp_trainer_referral_commission', 10); ?>% commission!</p>
                
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <span style="font-size: 14px; color: var(--ptp-gray-500);">Your Code:</span>
                    <button type="button" class="ptp-referral-code" onclick="navigator.clipboard.writeText('TRAINER-<?php echo strtoupper($trainer->slug); ?>'); this.innerHTML='✓ Copied!';">
                        TRAINER-<?php echo strtoupper($trainer->slug); ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Settings Tab -->
        <div class="ptp-tab-content" data-content="settings">
            <div class="ptp-dashboard-grid">
                <!-- Stripe -->
                <div class="ptp-dashboard-card">
                    <div class="ptp-dashboard-card-header">
                        <h2>Payment Setup</h2>
                    </div>
                    
                    <?php if ($stripe_connected): ?>
                    <div style="display: flex; align-items: center; gap: 12px; color: var(--ptp-green);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span style="font-weight: 600;">Stripe Connected</span>
                    </div>
                    <p style="margin-top: 12px; color: var(--ptp-gray-500); font-size: 14px;">Your payouts will be deposited directly to your connected bank account.</p>
                    <?php else: ?>
                    <p style="margin-bottom: 16px;">Connect your Stripe account to receive payouts for completed sessions.</p>
                    <button type="button" class="ptp-btn ptp-btn-primary" id="connectStripeBtn2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Connect Stripe
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Google Calendar -->
                <div class="ptp-dashboard-card">
                    <div class="ptp-dashboard-card-header">
                        <h2>Calendar Sync</h2>
                    </div>
                    
                    <?php if ($calendar_connected): ?>
                    <div style="display: flex; align-items: center; gap: 12px; color: var(--ptp-green);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span style="font-weight: 600;">Google Calendar Connected</span>
                    </div>
                    <p style="margin-top: 12px; color: var(--ptp-gray-500); font-size: 14px;">Sessions automatically sync to your calendar.</p>
                    <?php else: ?>
                    <p style="margin-bottom: 16px;">Sync your sessions with Google Calendar for automatic scheduling.</p>
                    <button type="button" class="ptp-btn ptp-btn-outline" id="connectCalendarBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Connect Google Calendar
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
/* Dashboard-specific inline styles for form */
.ptp-profile-form .ptp-form-row { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
@media (min-width: 640px) { .ptp-profile-form .ptp-form-row { grid-template-columns: repeat(2, 1fr); } }
.ptp-profile-form .ptp-form-group { margin-bottom: 16px; }
.ptp-profile-form label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--ptp-gray-700); }
.ptp-profile-form input, .ptp-profile-form textarea, .ptp-profile-form select {
    width: 100%; padding: 12px 16px; border: 1px solid var(--ptp-gray-200); border-radius: 8px;
    font-size: 16px; font-family: var(--ptp-font); transition: border-color 0.15s;
}
.ptp-profile-form input:focus, .ptp-profile-form textarea:focus, .ptp-profile-form select:focus {
    outline: none; border-color: var(--ptp-yellow); box-shadow: 0 0 0 3px rgba(252,185,0,0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    document.querySelectorAll('.ptp-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.dataset.tab;
            
            document.querySelectorAll('.ptp-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.ptp-tab-content').forEach(c => c.classList.remove('active'));
            
            this.classList.add('active');
            document.querySelector(`[data-content="${target}"]`).classList.add('active');
        });
    });
    
    // Quick action tab switching
    document.querySelectorAll('[data-tab-target]').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.dataset.tabTarget;
            document.querySelector(`[data-tab="${target}"]`).click();
        });
    });
    
    // Profile form
    const profileForm = document.getElementById('trainerProfileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('<?php echo rest_url('ptp-training/v1/trainer/profile'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' },
                    body: JSON.stringify(data)
                });
                
                if (response.ok) {
                    showToast('Profile updated successfully!', 'success');
                } else {
                    throw new Error('Failed to save');
                }
            } catch (err) {
                showToast('Error saving profile', 'error');
            }
            
            btn.disabled = false;
            btn.textContent = 'Save Changes';
        });
    }
    
    // Complete session
    document.querySelectorAll('.complete-session-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const sessionId = this.dataset.id;
            if (!confirm('Mark this session as completed?')) return;
            
            this.disabled = true;
            this.textContent = 'Completing...';
            
            try {
                const response = await fetch('<?php echo rest_url('ptp-training/v1/sessions/'); ?>' + sessionId + '/complete', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                });
                
                if (response.ok) {
                    this.closest('.ptp-session-card').style.opacity = '0.5';
                    showToast('Session completed!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error('Failed');
                }
            } catch (err) {
                showToast('Error completing session', 'error');
                this.disabled = false;
                this.textContent = 'Complete';
            }
        });
    });
    
    // Stripe connect
    ['connectStripeBtn', 'connectStripeBtn2'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('click', async function() {
                this.disabled = true;
                this.textContent = 'Redirecting...';
                
                try {
                    const response = await fetch('<?php echo rest_url('ptp-training/v1/stripe/connect'); ?>', {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                    });
                    const data = await response.json();
                    if (data.url) {
                        window.location.href = data.url;
                    }
                } catch (err) {
                    showToast('Error connecting to Stripe', 'error');
                    this.disabled = false;
                    this.textContent = 'Connect Stripe';
                }
            });
        }
    });
    
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'ptp-toast ' + type;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Availability form
    const availForm = document.getElementById('availabilityForm');
    if (availForm) {
        availForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const availability = {};
            
            // Parse form data into availability object
            for (let i = 0; i < 7; i++) {
                const starts = formData.getAll(`avail[${i}][start][]`);
                const ends = formData.getAll(`avail[${i}][end][]`);
                
                if (starts.length > 0) {
                    availability[i] = starts.map((start, idx) => ({
                        start: start + ':00',
                        end: ends[idx] + ':00'
                    })).filter(s => s.start && s.end);
                }
            }
            
            try {
                const response = await fetch('<?php echo rest_url('ptp-training/v1/trainer/availability'); ?>', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' 
                    },
                    body: JSON.stringify({ availability })
                });
                
                if (response.ok) {
                    showToast('Availability saved!', 'success');
                } else {
                    throw new Error('Failed to save');
                }
            } catch (err) {
                showToast('Error saving availability', 'error');
            }
        });
    }
});

// Add time slot function
function addSlot(button, dayIndex) {
    const container = button.parentElement;
    const slot = document.createElement('div');
    slot.className = 'ptp-availability-slot';
    slot.style.cssText = 'display: flex; gap: 8px; align-items: center; margin-bottom: 8px;';
    slot.innerHTML = `
        <input type="time" name="avail[${dayIndex}][start][]" value="09:00" />
        <span>to</span>
        <input type="time" name="avail[${dayIndex}][end][]" value="17:00" />
        <button type="button" class="ptp-remove-slot" onclick="this.parentElement.remove()" style="background: none; border: none; color: var(--ptp-red); cursor: pointer; font-size: 20px;">×</button>
    `;
    container.insertBefore(slot, button);
    
    // Remove "Not available" text if present
    const notAvail = container.querySelector('span');
    if (notAvail && notAvail.textContent.includes('Not available')) {
        notAvail.remove();
    }
}
</script>
