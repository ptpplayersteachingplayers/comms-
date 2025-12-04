/**
 * PTP Training Pro - Frontend JavaScript
 * Handles marketplace interactions, booking, modals
 */

(function() {
    'use strict';
    
    // Utility functions
    const PTP = {
        
        // Format currency
        formatCurrency: function(amount) {
            return (window.ptpTraining?.currency || '$') + parseFloat(amount).toFixed(0);
        },
        
        // Format date
        formatDate: function(dateStr, format = 'short') {
            const date = new Date(dateStr + 'T00:00:00');
            if (format === 'short') {
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
            return date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        },
        
        // Format time
        formatTime: function(timeStr) {
            const [hours, minutes] = timeStr.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        },
        
        // API request helper
        api: async function(endpoint, method = 'GET', data = null) {
            const url = (window.ptpTraining?.rest_url || '/wp-json/ptp-training/v1/') + endpoint;
            
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            
            if (window.ptpTraining?.nonce) {
                options.headers['X-WP-Nonce'] = window.ptpTraining.nonce;
            }
            
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            
            const response = await fetch(url, options);
            return response.json();
        },
        
        // Show loading state
        showLoading: function(element) {
            element.classList.add('ptp-loading');
            element.setAttribute('disabled', 'disabled');
        },
        
        // Hide loading state
        hideLoading: function(element) {
            element.classList.remove('ptp-loading');
            element.removeAttribute('disabled');
        },
        
        // Show toast notification
        toast: function(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `ptp-toast ptp-toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },
        
        // Modal helpers
        openModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        },
        
        closeModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        },
        
        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        
        // Close modals on overlay click
        document.querySelectorAll('.ptp-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function() {
                this.closest('.ptp-modal').style.display = 'none';
                document.body.style.overflow = '';
            });
        });
        
        // Close modals on X button click
        document.querySelectorAll('.ptp-modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.ptp-modal').style.display = 'none';
                document.body.style.overflow = '';
            });
        });
        
        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.ptp-modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = '';
            }
        });
        
        // Star rating inputs
        document.querySelectorAll('.ptp-star-rating').forEach(container => {
            const stars = container.querySelectorAll('.ptp-star');
            const input = container.querySelector('input[type="hidden"]');
            
            stars.forEach((star, index) => {
                star.addEventListener('click', function() {
                    const rating = index + 1;
                    input.value = rating;
                    
                    stars.forEach((s, i) => {
                        s.classList.toggle('active', i < rating);
                    });
                });
                
                star.addEventListener('mouseenter', function() {
                    stars.forEach((s, i) => {
                        s.classList.toggle('hover', i <= index);
                    });
                });
            });
            
            container.addEventListener('mouseleave', function() {
                stars.forEach(s => s.classList.remove('hover'));
            });
        });
        
        // Auto-resize textareas
        document.querySelectorAll('textarea.ptp-auto-resize').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
        
        // Lazy load images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        observer.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    });
    
    // Expose PTP utilities globally
    window.PTP = PTP;
    
})();
