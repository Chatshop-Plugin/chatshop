/**
 * Paystack Admin Settings JavaScript for ChatShop
 *
 * @package ChatShop
 * @subpackage Admin\Assets
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Paystack Admin Settings Object
     *
     * @since 1.0.0
     */
    var ChatShopPaystackAdmin = {
        
        /**
         * Initialize the admin interface
         *
         * @since 1.0.0
         */
        init: function() {
            this.bindEvents();
            this.initClipboard();
            this.toggleFields();
        },

        /**
         * Bind event handlers
         *
         * @since 1.0.0
         */
        bindEvents: function() {
            // Test connection button
            $(document).on('click', '#test-paystack-connection', this.testConnection);
            
            // Copy webhook URL button
            $(document).on('click', '.copy-webhook-url', this.copyWebhookUrl);
            
            // Toggle fields based on test mode
            $(document).on('change', 'input[name="chatshop_paystack_options[test_mode]"]', this.toggleFields);
            
            // Enable/disable gateway
            $(document).on('change', 'input[name="chatshop_paystack_options[enabled]"]', this.toggleGateway);
            
            // Form validation
            $('form').on('submit', this.validateForm);
        },

        /**
         * Test Paystack connection
         *
         * @since 1.0.0
         */
        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true)
                   .text(chatshop_paystack_admin.strings.testing);
            
            // Remove previous notices
            $('.chatshop-test-notice').remove();
            
            // Make AJAX request
            $.ajax({
                url: chatshop_paystack_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_test_gateway',
                    gateway_id: 'paystack',
                    nonce: chatshop_paystack_admin.nonce
                },
                success: function(response) {
                    ChatShopPaystackAdmin.showTestResult(response, $button);
                },
                error: function(xhr, status, error) {
                    ChatShopPaystackAdmin.showTestResult({
                        success: false,
                        data: { message: chatshop_paystack_admin.strings.error }
                    }, $button);
                },
                complete: function() {
                    // Restore button state
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Show test connection result
         *
         * @param {Object} response AJAX response
         * @param {jQuery} $button Test button element
         * @since 1.0.0
         */
        showTestResult: function(response, $button) {
            var noticeClass = response.success ? 'notice-success' : 'notice-error';
            var message = response.success ? 
                          chatshop_paystack_admin.strings.success : 
                          (response.data && response.data.message ? response.data.message : chatshop_paystack_admin.strings.error);
            
            var $notice = $('<div class="notice ' + noticeClass + ' chatshop-test-notice is-dismissible">' +
                           '<p>' + message + '</p>' +
                           '</div>');
            
            $button.closest('.notice').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Copy webhook URL to clipboard
         *
         * @since 1.0.0
         */
        copyWebhookUrl: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var textToCopy = $button.data('clipboard-text');
            
            // Create temporary textarea to copy text
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(textToCopy).select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    ChatShopPaystackAdmin.showCopySuccess($button);
                }
            } catch (err) {
                console.error('Copy failed:', err);
            }
            
            $temp.remove();
        },

        /**
         * Show copy success message
         *
         * @param {jQuery} $button Copy button element
         * @since 1.0.0
         */
        showCopySuccess: function($button) {
            var originalText = $button.text();
            
            $button.text(chatshop_paystack_admin.strings.copied)
                   .addClass('button-primary');
            
            setTimeout(function() {
                $button.text(originalText)
                       .removeClass('button-primary');
            }, 2000);
        },

        /**
         * Initialize clipboard functionality
         *
         * @since 1.0.0
         */
        initClipboard: function() {
            // Check if clipboard API is supported
            if (!navigator.clipboard) {
                return;
            }
            
            // Modern clipboard API implementation
            $(document).on('click', '.copy-webhook-url', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var textToCopy = $button.data('clipboard-text');
                
                navigator.clipboard.writeText(textToCopy).then(function() {
                    ChatShopPaystackAdmin.showCopySuccess($button);
                }).catch(function(err) {
                    console.error('Modern clipboard copy failed:', err);
                    // Fallback handled in copyWebhookUrl method
                });
            });
        },

        /**
         * Toggle fields based on test mode
         *
         * @since 1.0.0
         */
        toggleFields: function() {
            var isTestMode = $('input[name="chatshop_paystack_options[test_mode]"]').is(':checked');
            
            // Test mode fields
            var $testFields = $('input[name*="test_"]').closest('tr');
            var $liveFields = $('input[name*="live_"]').closest('tr');
            
            if (isTestMode) {
                $testFields.show().find('input').prop('disabled', false);
                $liveFields.hide().find('input').prop('disabled', true);
            } else {
                $testFields.hide().find('input').prop('disabled', true);
                $liveFields.show().find('input').prop('disabled', false);
            }
        },

        /**
         * Toggle gateway enabled state
         *
         * @since 1.0.0
         */
        toggleGateway: function() {
            var isEnabled = $(this).is(':checked');
            var $form = $(this).closest('form');
            
            if (isEnabled) {
                $form.find('input, select').not(this).prop('disabled', false);
                ChatShopPaystackAdmin.toggleFields(); // Respect test mode
            } else {
                $form.find('input, select').not(this).prop('disabled', true);
            }
        },

        /**
         * Validate form before submission
         *
         * @param {Event} e Form submit event
         * @since 1.0.0
         */
        validateForm: function(e) {
            var isEnabled = $('input[name="chatshop_paystack_options[enabled]"]').is(':checked');
            
            if (!isEnabled) {
                return true; // Allow saving if disabled
            }
            
            var isTestMode = $('input[name="chatshop_paystack_options[test_mode]"]').is(':checked');
            var errors = [];
            
            // Validate required fields based on mode
            if (isTestMode) {
                if (!$('input[name="chatshop_paystack_options[test_public_key]"]').val().trim()) {
                    errors.push('Test Public Key is required');
                }
                if (!$('input[name="chatshop_paystack_options[test_secret_key]"]').val().trim()) {
                    errors.push('Test Secret Key is required');
                }
            } else {
                if (!$('input[name="chatshop_paystack_options[live_public_key]"]').val().trim()) {
                    errors.push('Live Public Key is required');
                }
                if (!$('input[name="chatshop_paystack_options[live_secret_key]"]').val().trim()) {
                    errors.push('Live Secret Key is required');
                }
            }
            
            // Validate key formats
            var publicKey = isTestMode ? 
                          $('input[name="chatshop_paystack_options[test_public_key]"]').val() :
                          $('input[name="chatshop_paystack_options[live_public_key]"]').val();
                          
            var secretKey = isTestMode ? 
                          $('input[name="chatshop_paystack_options[test_secret_key]"]').val() :
                          $('input[name="chatshop_paystack_options[live_secret_key]"]').val();
            
            if (publicKey && !ChatShopPaystackAdmin.validatePublicKey(publicKey, isTestMode)) {
                errors.push('Invalid public key format');
            }
            
            if (secretKey && !ChatShopPaystackAdmin.validateSecretKey(secretKey, isTestMode)) {
                errors.push('Invalid secret key format');
            }
            
            // Show errors if any
            if (errors.length > 0) {
                e.preventDefault();
                ChatShopPaystackAdmin.showValidationErrors(errors);
                return false;
            }
            
            return true;
        },

        /**
         * Validate public key format
         *
         * @param {string} key Public key
         * @param {boolean} isTestMode Whether in test mode
         * @return {boolean} Whether key is valid
         * @since 1.0.0
         */
        validatePublicKey: function(key, isTestMode) {
            if (isTestMode) {
                return key.startsWith('pk_test_');
            } else {
                return key.startsWith('pk_live_');
            }
        },

        /**
         * Validate secret key format
         *
         * @param {string} key Secret key
         * @param {boolean} isTestMode Whether in test mode
         * @return {boolean} Whether key is valid
         * @since 1.0.0
         */
        validateSecretKey: function(key, isTestMode) {
            if (isTestMode) {
                return key.startsWith('sk_test_');
            } else {
                return key.startsWith('sk_live_');
            }
        },

        /**
         * Show validation errors
         *
         * @param {Array} errors Array of error messages
         * @since 1.0.0
         */
        showValidationErrors: function(errors) {
            // Remove existing error notices
            $('.chatshop-validation-errors').remove();
            
            var errorHtml = '<div class="notice notice-error chatshop-validation-errors">' +
                           '<p><strong>Please fix the following errors:</strong></p>' +
                           '<ul>';
            
            errors.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            
            errorHtml += '</ul></div>';
            
            $('.wrap h1').after(errorHtml);
            
            // Scroll to top to show errors
            $('html, body').animate({
                scrollTop: $('.wrap').offset().top - 32
            }, 500);
        },

        /**
         * Save settings via AJAX
         *
         * @since 1.0.0
         */
        saveSettings: function() {
            var $form = $('form');
            var formData = $form.serialize();
            
            $.ajax({
                url: chatshop_paystack_admin.ajax_url,
                type: 'POST',
                data: formData + '&action=chatshop_save_gateway_settings&gateway_id=paystack&nonce=' + chatshop_paystack_admin.nonce,
                success: function(response) {
                    if (response.success) {
                        ChatShopPaystackAdmin.showSaveSuccess();
                    } else {
                        ChatShopPaystackAdmin.showSaveError(response.data.message);
                    }
                },
                error: function() {
                    ChatShopPaystackAdmin.showSaveError('Network error occurred');
                }
            });
        },

        /**
         * Show save success message
         *
         * @since 1.0.0
         */
        showSaveSuccess: function() {
            var $notice = $('<div class="notice notice-success is-dismissible chatshop-save-notice">' +
                           '<p>Settings saved successfully!</p>' +
                           '</div>');
            
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut();
            }, 3000);
        },

        /**
         * Show save error message
         *
         * @param {string} message Error message
         * @since 1.0.0
         */
        showSaveError: function(message) {
            var $notice = $('<div class="notice notice-error is-dismissible chatshop-save-notice">' +
                           '<p>Error saving settings: ' + message + '</p>' +
                           '</div>');
            
            $('.wrap h1').after($notice);
        },

        /**
         * Handle premium feature tooltips
         *
         * @since 1.0.0
         */
        initPremiumTooltips: function() {
            $('.chatshop-premium-feature').each(function() {
                var $this = $(this);
                var message = $this.data('premium-message') || 'This is a premium feature';
                
                $this.addClass('chatshop-premium-tooltip')
                     .attr('title', message);
            });
        },

        /**
         * Auto-save draft settings
         *
         * @since 1.0.0
         */
        autoSaveDraft: function() {
            var $form = $('form');
            var autoSaveInterval = 30000; // 30 seconds
            
            setInterval(function() {
                var formData = $form.serialize();
                
                // Save as draft in localStorage
                localStorage.setItem('chatshop_paystack_draft', formData);
                
                // Show auto-save indicator
                if (!$('.chatshop-autosave-indicator').length) {
                    var $indicator = $('<span class="chatshop-autosave-indicator">Draft saved</span>');
                    $('.submit').append($indicator);
                    
                    setTimeout(function() {
                        $indicator.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 2000);
                }
            }, autoSaveInterval);
        },

        /**
         * Restore draft settings
         *
         * @since 1.0.0
         */
        restoreDraft: function() {
            var draft = localStorage.getItem('chatshop_paystack_draft');
            
            if (draft && confirm('Restore unsaved changes?')) {
                // Parse and restore form data
                var params = new URLSearchParams(draft);
                
                params.forEach(function(value, key) {
                    var $field = $('[name="' + key + '"]');
                    
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', value === '1');
                    } else {
                        $field.val(value);
                    }
                });
                
                // Clear draft
                localStorage.removeItem('chatshop_paystack_draft');
            }
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        ChatShopPaystackAdmin.init();
        ChatShopPaystackAdmin.initPremiumTooltips();
        ChatShopPaystackAdmin.restoreDraft();
        
        // Enable auto-save for premium users
        if (typeof chatshop_premium !== 'undefined' && chatshop_premium.auto_save) {
            ChatShopPaystackAdmin.autoSaveDraft();
        }
    });

    /**
     * Clear draft on successful form submission
     */
    $(window).on('beforeunload', function() {
        if ($('form').data('submitted')) {
            localStorage.removeItem('chatshop_paystack_draft');
        }
    });

})(jQuery);