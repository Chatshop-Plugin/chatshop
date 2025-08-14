/**
 * Public JavaScript for ChatShop
 *
 * @package ChatShop
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main ChatShop Public object
    window.ChatShopPublic = {
        
        init: function() {
            this.bindEvents();
            this.initForms();
            this.initPaymentButtons();
            this.initFloatingButton();
        },

        bindEvents: function() {
            // Contact form submission
            $(document).on('submit', '.chatshop-form', this.handleContactForm);
            
            // Payment button clicks
            $(document).on('click', '.chatshop-payment-button', this.handlePaymentButton);
            
            // WhatsApp button tracking
            $(document).on('click', '.chatshop-whatsapp-button', this.trackWhatsAppClick);
            
            // Notification close
            $(document).on('click', '.chatshop-notification-close', this.closeNotification);
        },

        initForms: function() {
            // Add form validation
            $('.chatshop-form input[required], .chatshop-form textarea[required]').on('blur', function() {
                ChatShopPublic.validateField($(this));
            });
        },

        initPaymentButtons: function() {
            // Track payment button impressions
            $('.chatshop-payment-button').each(function() {
                ChatShopPublic.trackEvent('payment_button_impression', {
                    amount: $(this).data('amount'),
                    currency: $(this).data('currency')
                });
            });
        },

        initFloatingButton: function() {
            var $floatingButton = $('.chatshop-whatsapp-button.chatshop-floating');
            
            if ($floatingButton.length === 0) return;
            
            // Show floating button after page load
            setTimeout(function() {
                $floatingButton.addClass('show');
            }, 1000);
            
            // Hide/show on scroll
            var lastScrollTop = 0;
            $(window).scroll(function() {
                var scrollTop = $(this).scrollTop();
                
                if (scrollTop > lastScrollTop && scrollTop > 200) {
                    // Scrolling down
                    $floatingButton.addClass('hidden');
                } else {
                    // Scrolling up
                    $floatingButton.removeClass('hidden');
                }
                
                lastScrollTop = scrollTop;
            });
        },

        handleContactForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('.chatshop-submit-button');
            
            // Validate form
            if (!ChatShopPublic.validateForm($form)) {
                return;
            }
            
            // Disable submit button and show loading
            var originalText = $submitButton.text();
            $submitButton.prop('disabled', true)
                         .html('<span class="chatshop-spinner"></span>' + chatshop_public.strings.loading);
            
            // Prepare form data
            var formData = {
                action: 'chatshop_public_action',
                chatshop_action: 'submit_contact_form',
                nonce: chatshop_public.nonce,
                name: $form.find('[name="name"]').val(),
                email: $form.find('[name="email"]').val(),
                phone: $form.find('[name="phone"]').val(),
                message: $form.find('[name="message"]').val(),
                redirect: $form.data('redirect') || 'whatsapp',
                chatshop_nonce: $form.find('[name="chatshop_nonce"]').val()
            };
            
            $.ajax({
                url: chatshop_public.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        ChatShopPublic.showNotification(response.data.message || chatshop_public.strings.success, 'success');
                        
                        // Redirect to WhatsApp if specified
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.open(response.data.redirect_url, '_blank');
                            }, 1000);
                        }
                        
                        // Reset form
                        $form[0].reset();
                        $form.find('.error').removeClass('error');
                        
                        // Track conversion
                        ChatShopPublic.trackEvent('contact_form_submission', {
                            redirect_type: formData.redirect
                        });
                    } else {
                        ChatShopPublic.showNotification(response.data.message || chatshop_public.strings.error, 'error');
                    }
                },
                error: function() {
                    ChatShopPublic.showNotification(chatshop_public.strings.error, 'error');
                },
                complete: function() {
                    $submitButton.prop('disabled', false).text(originalText);
                }
            });
        },

        handlePaymentButton: function(e) {
            var $button = $(this);
            var amount = $button.data('amount');
            var currency = $button.data('currency');
            
            // Track payment button click
            ChatShopPublic.trackEvent('payment_button_click', {
                amount: amount,
                currency: currency
            });
            
            // If it's a regular link, let it proceed normally
            if ($button.attr('href') && $button.attr('href') !== '#') {
                return true;
            }
            
            e.preventDefault();
            
            // Generate payment link via AJAX
            var originalText = $button.text();
            $button.prop('disabled', true)
                   .html('<span class="chatshop-spinner"></span>' + chatshop_public.strings.loading);
            
            $.ajax({
                url: chatshop_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_public_action',
                    chatshop_action: 'generate_payment_link',
                    amount: amount,
                    currency: currency,
                    nonce: chatshop_public.nonce
                },
                success: function(response) {
                    if (response.success && response.data.payment_url) {
                        window.location.href = response.data.payment_url;
                    } else {
                        ChatShopPublic.showNotification(response.data.message || 'Error generating payment link.', 'error');
                    }
                },
                error: function() {
                    ChatShopPublic.showNotification('Error generating payment link.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        trackWhatsAppClick: function() {
            var $button = $(this);
            var phone = $button.attr('href').match(/wa\.me\/(\d+)/);
            
            ChatShopPublic.trackEvent('whatsapp_button_click', {
                phone: phone ? phone[1] : 'unknown',
                type: $button.hasClass('chatshop-floating') ? 'floating' : 'inline'
            });
        },

        validateForm: function($form) {
            var isValid = true;
            var $firstError = null;
            
            // Clear previous errors
            $form.find('.error').removeClass('error');
            
            // Check required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                if (!ChatShopPublic.validateField($field)) {
                    isValid = false;
                    if (!$firstError) {
                        $firstError = $field;
                    }
                }
            });
            
            // Validate email format
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !ChatShopPublic.isValidEmail(value)) {
                    $field.addClass('error');
                    isValid = false;
                    if (!$firstError) {
                        $firstError = $field;
                    }
                }
            });
            
            // Validate phone format
            $form.find('input[type="tel"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !ChatShopPublic.isValidPhone(value)) {
                    $field.addClass('error');
                    isValid = false;
                    if (!$firstError) {
                        $firstError = $field;
                    }
                }
            });
            
            if (!isValid) {
                if ($firstError) {
                    $firstError.focus();
                }
                ChatShopPublic.showNotification('Please check the highlighted fields.', 'error');
            }
            
            return isValid;
        },

        validateField: function($field) {
            var value = $field.val().trim();
            var isValid = true;
            
            $field.removeClass('error');
            
            // Check if required field is empty
            if ($field.prop('required') && !value) {
                isValid = false;
            }
            
            // Check email format
            if ($field.attr('type') === 'email' && value && !ChatShopPublic.isValidEmail(value)) {
                isValid = false;
            }
            
            // Check phone format
            if ($field.attr('type') === 'tel' && value && !ChatShopPublic.isValidPhone(value)) {
                isValid = false;
            }
            
            if (!isValid) {
                $field.addClass('error');
            }
            
            return isValid;
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        isValidPhone: function(phone) {
            // Allow various phone formats
            var regex = /^[\+]?[1-9][\d]{0,15}$/;
            var cleanPhone = phone.replace(/[\s\-\(\)]/g, '');
            return regex.test(cleanPhone) && cleanPhone.length >= 10;
        },

        showNotification: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 5000;
            
            // Remove existing notifications
            $('.chatshop-notification').remove();
            
            var $notification = $('<div class="chatshop-notification ' + type + '">')
                .html('<div class="chatshop-notification-content">' +
                      '<p class="chatshop-notification-message">' + message + '</p>' +
                      '<button class="chatshop-notification-close">&times;</button>' +
                      '</div>');
            
            $('body').append($notification);
            
            // Show notification
            setTimeout(function() {
                $notification.addClass('show');
            }, 100);
            
            // Auto hide
            if (duration > 0) {
                setTimeout(function() {
                    ChatShopPublic.hideNotification($notification);
                }, duration);
            }
        },

        hideNotification: function($notification) {
            $notification = $notification || $('.chatshop-notification');
            $notification.removeClass('show');
            
            setTimeout(function() {
                $notification.remove();
            }, 300);
        },

        closeNotification: function() {
            ChatShopPublic.hideNotification($(this).closest('.chatshop-notification'));
        },

        trackEvent: function(eventName, properties) {
            properties = properties || {};
            
            // Add timestamp and page info
            properties.timestamp = new Date().toISOString();
            properties.page_url = window.location.href;
            properties.page_title = document.title;
            
            // Send to analytics endpoint
            $.ajax({
                url: chatshop_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_public_action',
                    chatshop_action: 'track_event',
                    event_name: eventName,
                    properties: properties,
                    nonce: chatshop_public.nonce
                },
                // Silent tracking - don't show errors to user
                error: function() {
                    // Log to console in debug mode
                    if (window.console && window.console.log) {
                        console.log('ChatShop: Failed to track event:', eventName);
                    }
                }
            });
            
            // Google Analytics integration if available
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, properties);
            }
            
            // Facebook Pixel integration if available
            if (typeof fbq !== 'undefined') {
                fbq('track', eventName, properties);
            }
        },

        // Utility functions
        formatPhone: function(phone) {
            // Remove all non-digit characters
            var cleaned = phone.replace(/\D/g, '');
            
            // Format based on length
            if (cleaned.length === 11 && cleaned.startsWith('0')) {
                // Nigerian number starting with 0
                return '+234' + cleaned.substring(1);
            } else if (cleaned.length === 10) {
                // Assume Nigerian number without country code
                return '+234' + cleaned;
            } else if (cleaned.length === 13 && cleaned.startsWith('234')) {
                // Nigerian number with country code but no +
                return '+' + cleaned;
            }
            
            return cleaned;
        },

        generateWhatsAppLink: function(phone, message) {
            var formattedPhone = this.formatPhone(phone);
            var url = 'https://wa.me/' + formattedPhone.replace('+', '');
            
            if (message) {
                url += '?text=' + encodeURIComponent(message);
            }
            
            return url;
        },

        // Initialize payment status checking
        initPaymentStatusCheck: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var paymentRef = urlParams.get('payment_ref');
            
            if (paymentRef) {
                this.checkPaymentStatus(paymentRef);
            }
        },

        checkPaymentStatus: function(paymentRef) {
            $.ajax({
                url: chatshop_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_public_action',
                    chatshop_action: 'check_payment_status',
                    payment_ref: paymentRef,
                    nonce: chatshop_public.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var status = response.data.status;
                        var message = response.data.message;
                        
                        if (status === 'success') {
                            ChatShopPublic.showNotification(message, 'success');
                            ChatShopPublic.trackEvent('payment_success', {
                                payment_ref: paymentRef,
                                amount: response.data.amount,
                                currency: response.data.currency
                            });
                        } else if (status === 'failed') {
                            ChatShopPublic.showNotification(message, 'error');
                            ChatShopPublic.trackEvent('payment_failed', {
                                payment_ref: paymentRef
                            });
                        }
                    }
                }
            });
        },

        // Lazy load images
        initLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(function(img) {
                    imageObserver.observe(img);
                });
            }
        },

        // Accessibility improvements
        initAccessibility: function() {
            // Add ARIA labels to buttons without text
            $('.chatshop-whatsapp-button.chatshop-floating').attr('aria-label', chatshop_public.strings.whatsapp_text);
            
            // Keyboard navigation for custom elements
            $('.chatshop-payment-button, .chatshop-whatsapp-button').attr('role', 'button').attr('tabindex', '0');
            
            // Handle Enter key on custom buttons
            $(document).on('keydown', '[role="button"]', function(e) {
                if (e.keyCode === 13 || e.keyCode === 32) { // Enter or Space
                    e.preventDefault();
                    $(this).click();
                }
            });
        },

        // Performance monitoring
        initPerformanceMonitoring: function() {
            // Track page load time
            window.addEventListener('load', function() {
                var loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
                ChatShopPublic.trackEvent('page_load_time', {
                    load_time: loadTime
                });
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ChatShopPublic.init();
        ChatShopPublic.initPaymentStatusCheck();
        ChatShopPublic.initLazyLoading();
        ChatShopPublic.initAccessibility();
        ChatShopPublic.initPerformanceMonitoring();
    });

    // Export for global access
    window.ChatShopPublic = ChatShopPublic;

})(jQuery);