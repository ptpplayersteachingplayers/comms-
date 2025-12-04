(function($) {
    'use strict';
    
    const PTPCommsAdmin = {
        config: {
            autoRefreshInterval: 60000,  // Reduced from 30s to 60s
            messagePollingInterval: 8000, // Reduced from 5s to 8s for less server load
            notificationDuration: 5000,
            tooltipDelay: 200
        },
        
        pollingInterval: null,
        currentConversationId: null,
        lastMessageId: null,
        
        init: function() {
            this.bindEvents();
            this.initializeComponents();
            this.setupInboxAutoRefresh();
            this.initializeRealtimeMessaging();
        },
        
        bindEvents: function() {
            $(document).on('submit', '#contacts-form', this.handleBulkActions);
            $(document).on('submit', 'form[data-validate="true"]', this.validateForm);
            $(document).on('submit', 'form[data-ajax="true"]', this.handleAjaxForm);
            $(document).on('submit', '#send-message-form', this.handleSendMessage.bind(this));
            $(document).on('blur', 'input[type="tel"]', this.formatPhoneNumber);
            $(document).on('click', '.ptp-copy-button', this.copyToClipboard);
            $(document).on('click', '.ptp-comms-table .row-action', this.handleRowAction);
            $(document).on('mouseenter', '[data-tooltip]', this.showTooltip);
            $(document).on('mouseleave', '[data-tooltip]', this.hideTooltip);
            $(document).on('click', '.ptp-comms-tabs .nav-tab', this.handleTabClick);
            $(document).on('click', '[data-modal-trigger]', this.showModal);
            $(document).on('click', '.ptp-modal-close, .ptp-modal-overlay', this.hideModal);
            $(document).on('click', '.ptp-comms-alert-dismiss', this.dismissAlert);
            $(document).on('click', 'a[href^="#"]:not([href="#"])', this.smoothScroll);
            $(document).on('change', '.select-all-checkbox', this.handleSelectAll);
            $(document).on('keydown', '#send-message-form textarea[name="message"]', this.handleMessageKeydown.bind(this));
        },
        
        initializeComponents: function() {
            $('.ptp-comms-alert.success').delay(this.config.notificationDuration).fadeOut();
            this.initCharacterCounter();
            this.scrollConversationThread();
            this.initializeDatePickers();
            this.initializeSelect2();
        },
        
        initializeRealtimeMessaging: function() {
            const conversationId = this.getConversationId();
            if (conversationId) {
                this.currentConversationId = conversationId;
                this.lastMessageId = this.getLastMessageId();
                this.startMessagePolling();
            }
        },
        
        setupInboxAutoRefresh: function() {
            if ($('body').hasClass('ptp-comms_page_ptp-comms-inbox')) {
                setInterval(() => {
                    this.checkForNewMessages();
                }, this.config.autoRefreshInterval);
            }
        },
        
        handleSendMessage: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $button = $form.find('button[type="submit"]');
            const $textarea = $form.find('textarea[name="message"]');
            const message = $textarea.val().trim();
            
            if (!message) {
                this.showNotification('Type a message to this parent before sending.', 'error');
                return;
            }
            
            $button.prop('disabled', true);
            $textarea.prop('disabled', true);
            
            const originalButtonHtml = $button.html();
            $button.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> Sending...');
            
            $.ajax({
                url: ptpCommsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ptp_comms_send_message',
                    nonce: ptpCommsData.nonce,
                    conversation_id: $form.find('input[name="conversation_id"]').val(),
                    contact_id: $form.find('input[name="contact_id"]').val(),
                    message_type: $form.find('select[name="message_type"]').val(),
                    message: message
                },
                success: (response) => {
                    if (response.success) {
                        $textarea.val('');
                        this.addMessageToThread(response.data.message);
                        this.showNotification('Message sent to parent.', 'success');
                        this.lastMessageId = response.data.message.id;
                    } else {
                        this.showNotification(response.data.message || 'We could not send this message.', 'error');
                    }
                },
                error: (xhr) => {
                    let errorMsg = 'We could not send this message. Please try again in a moment.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    this.showNotification(errorMsg, 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).html(originalButtonHtml);
                    $textarea.prop('disabled', false).focus();
                }
            });
        },
        
        handleMessageKeydown: function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $('#send-message-form').trigger('submit');
            }
        },
        
        startMessagePolling: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
            }
            
            this.pollingInterval = setInterval(() => {
                this.pollForNewMessages();
            }, this.config.messagePollingInterval);
        },
        
        stopMessagePolling: function() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        },
        
        pollForNewMessages: function() {
            if (!this.currentConversationId) return;
            
            $.ajax({
                url: ptpCommsData.ajax_url,
                type: 'GET',
                data: {
                    action: 'ptp_comms_get_new_messages',
                    nonce: ptpCommsData.nonce,
                    conversation_id: this.currentConversationId,
                    after_id: this.lastMessageId || 0
                },
                success: (response) => {
                    if (response.success && response.data.messages && response.data.messages.length > 0) {
                        response.data.messages.forEach((message) => {
                            this.addMessageToThread(message, false);
                            this.lastMessageId = message.id;
                        });
                        
                        const hasIncoming = response.data.messages.some(m => m.direction === 'inbound');
                        if (hasIncoming) {
                            this.playNotificationSound();
                        }
                    }
                }
            });
        },
        
        addMessageToThread: function(message, animate = true) {
            const $thread = $('.ptp-conversation-thread');
            if (!$thread.length) return;

            // Check if message already exists (prevent duplicates)
            if (document.querySelector(`[data-message-id="${message.id}"]`)) {
                return;
            }

            const isInbound = message.direction === 'inbound';
            const messageClass = isInbound ? 'inbound' : 'outbound';
            const statusBadge = this.getStatusBadge(message.status);

            const $messageDiv = $(`
                <div class="ptp-message ${messageClass}" data-message-id="${message.id}" style="${animate ? 'opacity: 0; transform: translateY(10px);' : ''}">
                    <div class="ptp-message-content">
                        ${this.escapeHtml(message.message_body)}
                    </div>
                    <div class="ptp-message-meta">
                        ${message.message_type.charAt(0).toUpperCase() + message.message_type.slice(1)}
                        ${statusBadge}
                        · ${this.formatMessageTime(message.created_at)}
                    </div>
                </div>
            `);

            $thread.append($messageDiv);
            
            if (animate) {
                setTimeout(() => {
                    $messageDiv.css({
                        'opacity': '1',
                        'transform': 'translateY(0)',
                        'transition': 'all 0.3s ease'
                    });
                }, 10);
            }
            
            this.scrollConversationThread(true);
        },
        
        getStatusBadge: function(status) {
            if (!status || status === 'sent') return '';
            
            const statusMap = {
                'delivered': 'Delivered',
                'failed': 'Failed to send',
                'queued': 'Scheduled',
                'sending': 'Sending...'
            };
            
            return ' · ' + (statusMap[status] || status);
        },
        
        formatMessageTime: function(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Just now';
            if (diff < 3600000) {
                const minutes = Math.floor(diff / 60000);
                return `${minutes}m ago`;
            }
            if (diff < 86400000) {
                const hours = Math.floor(diff / 3600000);
                return `${hours}h ago`;
            }
            
            const month = date.toLocaleString('default', { month: 'short' });
            const day = date.getDate();
            const time = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            
            return `${month} ${day} at ${time}`;
        },
        
        getConversationId: function() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('conversation') || null;
        },
        
        getLastMessageId: function() {
            const $messages = $('.ptp-conversation-thread .ptp-message');
            if ($messages.length === 0) return 0;
            
            const $lastMessage = $messages.last();
            return parseInt($lastMessage.data('message-id')) || 0;
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        handleBulkActions: function(e) {
            const action = $('select[name="bulk_action_type"]').val();
            const selected = $('.contact-checkbox:checked').length;
            
            if (action && selected > 0) {
                let message = 'Are you sure you want to ';
                switch(action) {
                    case 'delete': message += `delete ${selected} contact(s)?`; break;
                    case 'opt_in': message += `mark ${selected} contact(s) as opted in?`; break;
                    case 'opt_out': message += `mark ${selected} contact(s) as opted out?`; break;
                    default: message += `perform this action on ${selected} contact(s)?`;
                }
                
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            }
        },
        
        formatPhoneNumber: function() {
            const $input = $(this);
            const phone = $input.val().replace(/\D/g, '');
            
            if (phone.length === 10) {
                $input.val(`(${phone.substr(0,3)}) ${phone.substr(3,3)}-${phone.substr(6,4)}`);
            } else if (phone.length === 11 && phone[0] === '1') {
                $input.val(`+1 (${phone.substr(1,3)}) ${phone.substr(4,3)}-${phone.substr(7,4)}`);
            }
        },
        
        validateForm: function(e) {
            const $form = $(this);
            let isValid = true;
            let errorMessage = '';
            
            $form.find('.ptp-comms-form-group').removeClass('error');
            
            $form.find('[required]').each(function() {
                const $field = $(this);
                const $group = $field.closest('.ptp-comms-form-group');
                
                if (!$field.val() || $field.val().trim() === '') {
                    isValid = false;
                    $group.addClass('error');
                    const fieldName = $field.attr('name') || $field.attr('id') || 'This field';
                    errorMessage += `• ${fieldName} is required\n`;
                }
            });
            
            if (!isValid) {
                PTPCommsAdmin.showNotification('Please correct the errors', 'error');
                e.preventDefault();
                return false;
            }
        },
        
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            const originalHtml = $button.html();
            
            $button.prop('disabled', true).html('<span class="ptp-comms-spinner small white"></span> Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        PTPCommsAdmin.showNotification(response.data.message || 'Success!', 'success');
                        
                        if (response.data.redirect) {
                            setTimeout(() => window.location.href = response.data.redirect, 1000);
                        } else if (response.data.reload) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    } else {
                        PTPCommsAdmin.showNotification(response.data.message || 'An error occurred', 'error');
                    }
                },
                error: function() {
                    PTPCommsAdmin.showNotification('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        },
        
        copyToClipboard: function(e) {
            e.preventDefault();
            const $button = $(this);
            const text = $button.data('copy');
            
            if (!text) return;
            
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            
            const originalHtml = $button.html();
            $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
            setTimeout(() => $button.html(originalHtml), 2000);
        },
        
        handleRowAction: function(e) {
            e.preventDefault();
            const action = $(this).data('action');
            const itemId = $(this).data('id');
            
            if (action === 'delete' && !confirm('Are you sure you want to delete this item?')) {
                return;
            }
        },
        
        handleTabClick: function(e) {
            e.preventDefault();
            
            const target = $(this).attr('href');
            
            if (!target || !target.startsWith('#') || target === '#') {
                return;
            }
            
            // Update tab active state
            $('.ptp-comms-tabs .nav-tab, .nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Hide all tab content
            $('.ptp-comms-tab-content').hide().addClass('ptp-hidden');
            
            // Show target tab
            const $targetTab = $(target);
            if ($targetTab.length) {
                $targetTab.show().removeClass('ptp-hidden');
            }
            
            // Update hidden field for form submission
            const tabName = target.replace('#', '');
            $('#ptp_active_tab').val(tabName);
            
            // Update URL hash without jumping
            if (history.pushState) {
                history.pushState(null, null, target);
            }
        },
        
        showTooltip: function(e) {
            const $element = $(this);
            const text = $element.data('tooltip');
            if (!text) return;
            
            const $tooltip = $(`<div class="ptp-tooltip">${text}</div>`);
            $('body').append($tooltip);
            
            const offset = $element.offset();
            const elementHeight = $element.outerHeight();
            
            $tooltip.css({
                top: offset.top + elementHeight + 5,
                left: offset.left
            }).fadeIn(200);
            
            $element.data('tooltip-element', $tooltip);
        },
        
        hideTooltip: function() {
            const $element = $(this);
            const $tooltip = $element.data('tooltip-element');
            
            if ($tooltip) {
                $tooltip.fadeOut(200, function() {
                    $(this).remove();
                });
            }
        },
        

        
        showModal: function(e) {
            e.preventDefault();
            
            const modalId = $(this).data('modal-trigger');
            const $modal = $(modalId);
            
            if ($modal.length) {
                const $overlay = $('<div class="ptp-modal-overlay"></div>');
                $overlay.append($modal.show());
                $('body').append($overlay);
                $('body').css('overflow', 'hidden');
            }
        },
        
        hideModal: function(e) {
            if ($(e.target).hasClass('ptp-modal-overlay') || $(e.target).hasClass('ptp-modal-close')) {
                $('.ptp-modal-overlay').fadeOut(200, function() {
                    $(this).remove();
                    $('body').css('overflow', '');
                });
            }
        },
        
        dismissAlert: function() {
            $(this).closest('.ptp-comms-alert').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        smoothScroll: function(e) {
            const $target = $($(this).attr('href'));
            
            if ($target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: $target.offset().top - 100
                }, 500);
            }
        },
        
        handleSelectAll: function() {
            const $checkbox = $(this);
            const $table = $checkbox.closest('table');
            const checked = $checkbox.prop('checked');
            
            $table.find('tbody input[type="checkbox"]').prop('checked', checked);
        },
        
        initCharacterCounter: function() {
            $('textarea[data-counter="true"]').each(function() {
                const $textarea = $(this);
                const maxLength = $textarea.attr('maxlength');
                
                const $counter = $('<div class="char-counter" style="margin-top: 5px; font-size: 12px; color: var(--ptp-gray-600);"></div>');
                $textarea.after($counter);
                
                const updateCounter = function() {
                    const length = $textarea.val().length;
                    let text = `${length} characters`;
                    
                    if (maxLength) {
                        text += ` / ${maxLength}`;
                        if (length > maxLength * 0.9) {
                            $counter.css('color', 'var(--ptp-danger)');
                        } else {
                            $counter.css('color', 'var(--ptp-gray-600)');
                        }
                    }
                    
                    $counter.text(text);
                };
                
                $textarea.on('input', updateCounter);
                updateCounter();
            });
        },
        
        scrollConversationThread: function(smooth = false) {
            const $thread = $('.ptp-conversation-thread');
            if ($thread.length) {
                if (smooth) {
                    $thread.animate({
                        scrollTop: $thread[0].scrollHeight
                    }, 300);
                } else {
                    $thread.scrollTop($thread[0].scrollHeight);
                }
            }
        },
        
        initializeDatePickers: function() {
            if ($.fn.datepicker) {
                $('input[type="date"].use-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },
        
        initializeSelect2: function() {
            if ($.fn.select2) {
                $('.ptp-select2').select2({
                    width: '100%',
                    theme: 'default'
                });
            }
        },
        
        checkForNewMessages: function() {
            const $unreadBadge = $('.ptp-unread-badge');
            if (!$unreadBadge.length) return;
            
            $.get(ptpCommsData.ajax_url, {
                action: 'ptp_comms_check_unread',
                nonce: ptpCommsData.nonce
            }, function(response) {
                if (response.success && response.data.count > 0) {
                    $unreadBadge.text(response.data.count).show();
                    
                    if (response.data.newMessages) {
                        PTPCommsAdmin.playNotificationSound();
                    }
                } else {
                    $unreadBadge.hide();
                }
            });
        },
        
        showNotification: function(message, type) {
            type = type || 'info';
            
            const alertClass = 'ptp-comms-alert ' + (type === 'error' ? 'error' : type);
            const icon = PTPCommsAdmin.getNotificationIcon(type);
            
            const $alert = $(`
                <div class="${alertClass}" style="position: fixed; top: 80px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); animation: slideInRight 0.3s ease;">
                    <span class="dashicons ${icon}"></span>
                    <div class="ptp-comms-alert-content">
                        <p style="margin: 0;">${message}</p>
                    </div>
                    <button class="ptp-comms-alert-dismiss dashicons dashicons-no-alt"></button>
                </div>
            `);
            
            $('body').append($alert);
            
            setTimeout(function() {
                $alert.fadeOut(300, function() {
                    $(this).remove();
                });
            }, PTPCommsAdmin.config.notificationDuration);
        },
        
        getNotificationIcon: function(type) {
            const icons = {
                success: 'dashicons-yes-alt',
                error: 'dashicons-warning',
                warning: 'dashicons-warning',
                info: 'dashicons-info'
            };
            
            return icons[type] || icons.info;
        },
        
        playNotificationSound: function() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.1);
            } catch (e) {
                console.log('Could not play notification sound');
            }
        },
        
        isValidEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
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
    
    $(document).ready(function() {
        PTPCommsAdmin.init();
    });
    
    $(window).on('beforeunload', function() {
        PTPCommsAdmin.stopMessagePolling();
    });
    
    window.ptpCommsAdmin = PTPCommsAdmin;
    
})(jQuery);
