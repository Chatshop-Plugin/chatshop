/**
 * ChatShop Admin JavaScript
 *
 * @package ChatShop
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * ChatShop Admin Object
     */
    const ChatShopAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initFormValidation();
        },

        /**
         * Bind admin events
         */
        bindEvents: function() {
            // Test mode toggle
            $(document).on('change', '#paystack_test_mode', this.toggleTestMode);
            
            // Copy webhook URL
            $(document).on('click', '#copy-webhook-url', this.copyWebhookUrl);
            
            // Test gateway connection
            $(document).on('click', '#test-paystack-connection', this.testGatewayConnection);
            
            // Save settings via AJAX
            $(document).on('click', '.chatshop-save-settings', this.saveSettings);
            
            // Reset settings
            $(document).on('click', '.chatshop-reset-settings', this.resetSettings);
            
            // Form changes tracking
            $(document).on('change', '.chatshop-setting-field', this.trackFormChanges);
            
            // API key validation
            $(document).on('blur', 'input[name*="_key"]', this.validateApiKey);
            
            // Dismiss notices
            $(document).on('click', '.notice-dismiss', this.dismissNotice);
        },

        /**
         * Toggle test mode sections
         */
        toggleTestMode: function() {
            const isTestMode = $(this).is(':checked');
            
            if (isTestMode) {
                $('#test-keys-section').slideDown(300);
                $('#live-keys-section').slideUp(300);
            } else {
                $('#test-keys-section').slideUp(300);
                $('#live-keys-section').slideDown(300);
            }
        },

        /**
         * Copy webhook URL to clipboard
         */
        copyWebhookUrl: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const webhookInput = $('#webhook_url');
            const originalText = button.text();
            
            // Select and copy text
            webhookInput.select();
            webhookInput[0].setSelectionRange(0, 99999); // For mobile devices
            
            try {
                const successful = document.execCommand('copy');
                
                if (successful) {
                    button.addClass('copied').text(chatshopAdmin.strings.copied);
                    
                    // Show success feedback
                    ChatShopAdmin.showNotice('success', chatshopAdmin.strings.copied);
                    
                    setTimeout(function() {
                        button.removeClass('copied').text(originalText);
                    }, 2000);
                } else {
                    throw new Error('Copy command failed');
                }
            } catch (err) {
                // Fallback for browsers that don't support execCommand
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(webhookInput.val()).then(function() {
                        button.addClass('copied').text(chatshopAdmin.strings.copied);
                        ChatShopAdmin.showNotice('success', chatshopAdmin.strings.copied);
                        
                        setTimeout(function() {
                            button.removeClass('copied').text(originalText);
                        }, 2000);
                    }).catch(function() {
                        ChatShopAdmin.showNotice('error', chatshopAdmin.strings.copyFailed);
                    });
                } else {
                    ChatShopAdmin.showNotice('error', chatshopAdmin.strings.copyFailed);
                }
            }
            
            // Deselect text
            window.getSelection().removeAllRanges();
        },

        /**
         * Test gateway connection
         */
        testGatewayConnection: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const resultDiv = $('#test-result');
            const originalText = button.text();
            
            // Set loading state
            button.prop('disabled', true)
                  .addClass('chatshop-loading')
                  .text(chatshopAdmin.strings.testing);
            
            resultDiv.hide();

            // Make AJAX request
            $.ajax({
                url: chatshopAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_test_gateway',
                    gateway_id: 'paystack',
                    nonce: chatshopAdmin.nonce
                },
                timeout: 30000, // 30 second timeout
                success: function(response) {
                    if (response.success) {
                        resultDiv.removeClass('error info')
                               .addClass('success')
                               .html('<strong>' + chatshopAdmin.strings.testSuccess + '</strong> ' + response.data.message)
                               .slideDown(300);
                        
                        ChatShopAdmin.showNotice('success', response.data.message);
                    } else {
                        resultDiv.removeClass('success info')
                               .addClass('error')
                               .html('<strong>' + chatshopAdmin.strings.testFailed + '</strong> ' + response.data.message)
                               .slideDown(300);
                        
                        ChatShopAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = chatshopAdmin.strings.error;
                    
                    if (status === 'timeout') {
                        errorMessage = 'Connection timeout. Please check your internet connection and try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    
                    resultDiv.removeClass('success info')
                           .addClass('error')
                           .html('<strong>' + chatshopAdmin.strings.testFailed + '</strong> ' + errorMessage)
                           .slideDown(300);
                    
                    ChatShopAdmin.showNotice('error', errorMessage);
                },
                complete: function() {
                    // Reset button state
                    button.prop('disabled', false)
                          .removeClass('chatshop-loading')
                          .text(originalText);
                }
            });
        },

        /**
         * Save settings via AJAX
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const form = button.closest('form');
            const group = button.data('group') || 'paystack';
            const originalText = button.text();
            
            // Set loading state
            button.prop('disabled', true)
                  .addClass('chatshop-loading')
                  .text(chatshopAdmin.strings.saving);
            
            // Serialize form data
            const formData = form.serializeArray();
            const settingsData = {};
            
            // Convert form data to object
            $.each(formData, function(i, field) {
                if (field.name.includes('[') && field.name.includes(']')) {
                    // Handle array notation (e.g., chatshop_paystack_options[enabled])
                    const matches = field.name.match(/\[([^\]]+)\]/);
                    if (matches) {
                        settingsData[matches[1]] = field.value;
                    }
                } else {
                    settingsData[field.name] = field.value;
                }
            });
            
            // Handle checkboxes (they don't appear in serializeArray if unchecked)
            form.find('input[type="checkbox"]').each(function() {
                const name = $(this).attr('name');
                if (name && name.includes('[') && name.includes(']')) {
                    const matches = name.match(/\[([^\]]+)\]/);
                    if (matches && !settingsData.hasOwnProperty(matches[1])) {
                        settingsData[matches[1]] = false;
                    }
                }
            });

            // Make AJAX request
            $.ajax({
                url: chatshopAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_save_settings',
                    group: group,
                    data: settingsData,
                    nonce: chatshopAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ChatShopAdmin.showNotice('success', response.data.message);
                        
                        // Mark form as saved
                        form.addClass('chatshop-form-saved').removeClass('chatshop-form-changed');
                    } else {
                        ChatShopAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ChatShopAdmin.showNotice('error', chatshopAdmin.strings.error);
                },
                complete: function() {
                    // Reset button state
                    button.prop('disabled', false)
                          .removeClass('chatshop-loading')
                          .text(originalText);
                }
            });
        },

        /**
         * Reset settings
         */
        resetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm(chatshopAdmin.strings.confirmReset)) {
                return;
            }
            
            const button = $(this);
            const group = button.data('group') || 'paystack';
            const originalText = button.text();
            
            // Set loading state
            button.prop('disabled', true)
                  .addClass('chatshop-loading')
                  .text(chatshopAdmin.strings.resetting);

            // Make AJAX request
            $.ajax({
                url: chatshopAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_reset_settings',
                    group: group,
                    nonce: chatshopAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ChatShopAdmin.showNotice('success', response.data.message);
                        
                        // Reload page to reflect reset settings
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        ChatShopAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ChatShopAdmin.showNotice('error', chatshopAdmin.strings.error);
                },
                complete: function() {
                    // Reset button state
                    button.prop('disabled', false)
                          .removeClass('chatshop-loading')
                          .text(originalText);
                }
            });
        },

        /**
         * Track form changes
         */
        trackFormChanges: function() {
            const form = $(this).closest('form');
            form.addClass('chatshop-form-changed').removeClass('chatshop-form-saved');
        },

        /**
         * Validate API key format
         */
        validateApiKey: function() {
            const input = $(this);
            const value = input.val().trim();
            const name = input.attr('name') || '';
            
            if (!value) return;
            
            let expectedPrefix = '';
            let keyType = '';
            
            // Determine expected prefix based on field name
            if (name.includes('test_public_key')) {
                expectedPrefix = 'pk_test_';
                keyType = 'Test Public Key';
            } else if (name.includes('test_secret_key')) {
                expectedPrefix = 'sk_test_';
                keyType = 'Test Secret Key';
            } else if (name.includes('live_public_key')) {
                expectedPrefix = 'pk_live_';
                keyType = 'Live Public Key';
            } else if (name.includes('live_secret_key')) {
                expectedPrefix = 'sk_live_';
                keyType = 'Live Secret Key';
            }
            
            if (expectedPrefix && !value.startsWith(expectedPrefix)) {
                input.addClass('chatshop-invalid');
                ChatShopAdmin.showFieldError(input, `${keyType} must start with "${expectedPrefix}"`);
            } else {
                input.removeClass('chatshop-invalid');
                ChatShopAdmin.clearFieldError(input);
            }
        },

        /**
         * Show field-specific error
         */
        showFieldError: function(field, message) {
            this.clearFieldError(field);
            
            const errorDiv = $('<div class="chatshop-field-error">' + message + '</div>');
            field.after(errorDiv);
        },

        /**
         * Clear field-specific error
         */
        clearFieldError: function(field) {
            field.siblings('.chatshop-field-error').remove();
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 
                              type === 'error' ? 'notice-error' : 'notice-info';
            
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible chatshop-notice">' +
                            '<p>' + message + '</p>' +
                            '<button type="button" class="notice-dismiss">' +
                            '<span class="screen-reader-text">Dismiss this notice.</span>' +
                            '</button>' +
                            '</div>');
            
            // Remove existing notices
            $('.chatshop-notice').fadeOut(300, function() {
                $(this).remove();
            });
            
            // Add new notice
            if ($('.wrap h1').length) {
                $('.wrap h1').after(notice);
            } else {
                $('.wrap').prepend(notice);
            }
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Dismiss notice
         */
        dismissNotice: function(e) {
            e.preventDefault();
            $(this).closest('.notice').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to elements with title attribute
            $('[title]').each(function() {
                $(this).hover(
                    function() {
                        const title = $(this).attr('title');
                        $(this).data('tipText', title).removeAttr('title');
                        $('<div class="chatshop-tooltip">' + title + '</div>')
                            .appendTo('body')
                            .fadeIn(200);
                    },
                    function() {
                        $(this).attr('title', $(this).data('tipText'));
                        $('.chatshop-tooltip').remove();
                    }
                ).mousemove(function(e) {
                    $('.chatshop-tooltip').css({
                        top: e.pageY + 10,
                        left: e.pageX + 10
                    });
                });
            });
        },

        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            // Prevent form submission if there are validation errors
            $('form').on('submit', function(e) {
                const form = $(this);
                const invalidFields = form.find('.chatshop-invalid');
                
                if (invalidFields.length > 0) {
                    e.preventDefault();
                    ChatShopAdmin.showNotice('error', 'Please fix the validation errors before saving.');
                    
                    // Focus on first invalid field
                    invalidFields.first().focus();
                }
            });
        },

        /**
         * Format currency
         */
        formatCurrency: function(amount, currency = 'NGN') {
            const formatter = new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 2
            });
            
            return formatter.format(amount / 100); // Convert from kobo to naira
        },

        /**
         * Validate email
         */
        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        /**
         * Validate phone number
         */
        validatePhone: function(phone) {
            const re = /^\+?[\d\s\-\(\)]+$/;
            return re.test(phone) && phone.replace(/\D/g, '').length >= 10;
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        ChatShopAdmin.init();
        
        // Warn user about unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if ($('.chatshop-form-changed').length > 0) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    });

    // Expose to global scope
    window.ChatShopAdmin = ChatShopAdmin;

})(jQuery);