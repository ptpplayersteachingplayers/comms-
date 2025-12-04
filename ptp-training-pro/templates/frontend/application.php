<?php
/**
 * Front-end Trainer Application Form
 * TeachMeTo-style design with PTP branding
 */
if (!defined('ABSPATH')) exit;
?>

<div class="ptp-application-page">
    
    <!-- Hero Section -->
    <section class="ptp-application-hero">
        <div class="ptp-application-hero__inner">
            <div class="ptp-application-hero__content">
                <span class="ptp-tag">Join Our Team</span>
                <h1 class="ptp-application-hero__headline">Become a PTP Trainer</h1>
                <p class="ptp-application-hero__subheadline">
                    Share your soccer expertise with the next generation. Train 1-on-1 with youth athletes, 
                    set your own schedule, and earn competitive rates.
                </p>
            </div>
            <div class="ptp-application-hero__image">
                <img src="https://ptpsummercamps.com/wp-content/uploads/2025/12/BG7A1915.jpg" 
                     alt="PTP trainer coaching young athletes" loading="lazy">
            </div>
        </div>
    </section>
    
    <!-- Benefits Section -->
    <section class="ptp-application-benefits">
        <div class="ptp-section-inner">
            <h2 class="ptp-section-title">Why Train With PTP?</h2>
            <div class="ptp-benefits-grid">
                <div class="ptp-benefit-card">
                    <div class="ptp-benefit-icon">💰</div>
                    <h3>Competitive Earnings</h3>
                    <p>Set your own hourly rate and keep 75-80% of every session. Get paid weekly via Stripe.</p>
                </div>
                <div class="ptp-benefit-card">
                    <div class="ptp-benefit-icon">📅</div>
                    <h3>Flexible Schedule</h3>
                    <p>Train when it works for you. Set your availability and accept bookings that fit your life.</p>
                </div>
                <div class="ptp-benefit-card">
                    <div class="ptp-benefit-icon">📍</div>
                    <h3>Train Locally</h3>
                    <p>Work with athletes in your area. We match you with families looking for training near you.</p>
                </div>
                <div class="ptp-benefit-card">
                    <div class="ptp-benefit-icon">⚽</div>
                    <h3>Make an Impact</h3>
                    <p>Help young athletes develop skills and confidence. Be the mentor you wish you had.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Application Form Section -->
    <section class="ptp-application-form-section">
        <div class="ptp-section-inner">
            <div class="ptp-application-form-wrapper">
                <div class="ptp-application-form-header">
                    <h2>Apply Now</h2>
                    <p>Fill out the form below and we'll review your application within 2-3 business days.</p>
                </div>
                
                <form id="ptp-application-form" class="ptp-application-form">
                    <?php wp_nonce_field('ptp_application_submit', 'ptp_application_nonce'); ?>
                    
                    <!-- Personal Info -->
                    <div class="ptp-form-section">
                        <h3 class="ptp-form-section-title">Personal Information</h3>
                        
                        <div class="ptp-form-row">
                            <div class="ptp-form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                            <div class="ptp-form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="ptp-form-row">
                            <div class="ptp-form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="ptp-form-group">
                                <label for="phone">Phone Number <span class="required">*</span></label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="ptp-form-section">
                        <h3 class="ptp-form-section-title">Location</h3>
                        
                        <div class="ptp-form-row ptp-form-row-3">
                            <div class="ptp-form-group">
                                <label for="location_city">City</label>
                                <input type="text" id="location_city" name="location_city" placeholder="Philadelphia">
                            </div>
                            <div class="ptp-form-group">
                                <label for="location_state">State</label>
                                <select id="location_state" name="location_state">
                                    <option value="">Select State</option>
                                    <option value="PA">Pennsylvania</option>
                                    <option value="NJ">New Jersey</option>
                                    <option value="DE">Delaware</option>
                                    <option value="MD">Maryland</option>
                                    <option value="NY">New York</option>
                                </select>
                            </div>
                            <div class="ptp-form-group">
                                <label for="location_zip">ZIP Code</label>
                                <input type="text" id="location_zip" name="location_zip" placeholder="19103">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Role & Background -->
                    <div class="ptp-form-section">
                        <h3 class="ptp-form-section-title">Your Background</h3>
                        
                        <div class="ptp-form-group">
                            <label for="role_type">What type of trainer are you? <span class="required">*</span></label>
                            <select id="role_type" name="role_type" required>
                                <option value="">Select Role</option>
                                <option value="current_college">Current College Player</option>
                                <option value="former_college">Former College Player</option>
                                <option value="current_pro">Current Pro Player</option>
                                <option value="former_pro">Former Pro Player</option>
                                <option value="coach">Licensed Coach</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="ptp-form-group">
                            <label for="playing_background">Playing Background</label>
                            <textarea id="playing_background" name="playing_background" rows="3" 
                                      placeholder="Tell us about your playing career (teams, positions, achievements)"></textarea>
                        </div>
                        
                        <div class="ptp-form-group">
                            <label for="coaching_experience">Coaching Experience</label>
                            <textarea id="coaching_experience" name="coaching_experience" rows="3" 
                                      placeholder="Describe any coaching or training experience you have"></textarea>
                        </div>
                        
                        <div class="ptp-form-group">
                            <label for="certifications">Certifications (Optional)</label>
                            <textarea id="certifications" name="certifications" rows="2" 
                                      placeholder="USSF, NSCAA, CPR, or other relevant certifications"></textarea>
                        </div>
                    </div>
                    
                    <!-- Additional Info -->
                    <div class="ptp-form-section">
                        <h3 class="ptp-form-section-title">Additional Information</h3>
                        
                        <div class="ptp-form-group">
                            <label for="experience_summary">Tell Us About Yourself</label>
                            <textarea id="experience_summary" name="experience_summary" rows="4" 
                                      placeholder="What makes you a great trainer? What's your training philosophy?"></textarea>
                        </div>
                        
                        <div class="ptp-form-row">
                            <div class="ptp-form-group">
                                <label for="intro_video_url">Intro Video (Optional)</label>
                                <input type="url" id="intro_video_url" name="intro_video_url" 
                                       placeholder="https://youtube.com/watch?v=...">
                                <span class="ptp-form-hint">YouTube or Vimeo link showcasing your skills</span>
                            </div>
                            <div class="ptp-form-group">
                                <label for="instagram_handle">Instagram Handle (Optional)</label>
                                <input type="text" id="instagram_handle" name="instagram_handle" placeholder="@yourhandle">
                            </div>
                        </div>
                        
                        <div class="ptp-form-group">
                            <label for="availability_notes">Availability Notes</label>
                            <textarea id="availability_notes" name="availability_notes" rows="2" 
                                      placeholder="When are you generally available to train? Weekends, evenings, etc."></textarea>
                        </div>
                        
                        <div class="ptp-form-group">
                            <label for="why_join">Why do you want to join PTP?</label>
                            <textarea id="why_join" name="why_join" rows="3" 
                                      placeholder="What motivates you to become a PTP trainer?"></textarea>
                        </div>
                        
                        <div class="ptp-form-group">
                            <label for="referral_source">How did you hear about us?</label>
                            <select id="referral_source" name="referral_source">
                                <option value="">Select One</option>
                                <option value="instagram">Instagram</option>
                                <option value="facebook">Facebook</option>
                                <option value="google">Google Search</option>
                                <option value="friend">Friend/Teammate</option>
                                <option value="ptp_camp">PTP Camp</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Submit -->
                    <div class="ptp-form-submit">
                        <button type="submit" class="ptp-btn ptp-btn-primary ptp-btn-lg">
                            <span class="ptp-btn-text">Submit Application</span>
                            <span class="ptp-btn-loading" style="display: none;">Submitting...</span>
                        </button>
                        <p class="ptp-form-disclaimer">
                            By submitting, you agree to be contacted about your application.
                        </p>
                    </div>
                </form>
                
                <!-- Success Message (hidden by default) -->
                <div id="ptp-application-success" class="ptp-application-success" style="display: none;">
                    <div class="ptp-success-icon">✓</div>
                    <h2>Application Submitted!</h2>
                    <p>Thank you for applying to become a PTP trainer. We'll review your application and get back to you within 2-3 business days.</p>
                    <a href="<?php echo home_url(); ?>" class="ptp-btn ptp-btn-outline">Return Home</a>
                </div>
            </div>
        </div>
    </section>
    
</div>

<script>
document.getElementById('ptp-application-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.ptp-btn-text');
    const btnLoading = submitBtn.querySelector('.ptp-btn-loading');
    
    // Show loading state
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';
    submitBtn.disabled = true;
    
    // Collect form data
    const formData = new FormData(form);
    formData.append('action', 'ptp_submit_application');
    
    // Submit via AJAX
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            form.style.display = 'none';
            document.querySelector('.ptp-application-form-header').style.display = 'none';
            document.getElementById('ptp-application-success').style.display = 'block';
            
            // Scroll to success message
            document.getElementById('ptp-application-success').scrollIntoView({ behavior: 'smooth' });
        } else {
            alert(data.data.message || 'There was an error submitting your application. Please try again.');
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('There was an error submitting your application. Please try again.');
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
        submitBtn.disabled = false;
    });
});
</script>
