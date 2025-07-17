/**
 * Admin JavaScript for ChatShop
 *
 * @package ChatShop
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main ChatShop Admin object
    window.ChatShopAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initColorPicker();
            this.initTabs();
            this.initTooltips();
            this.checkConnectionStatus();
        },

        bindEvents: function() {
            // Test connection buttons
            $(document).on('click', '.chatshop-test-connection', this.testConnection);
            
            // Settings form submission
            $(document).on('submit', '.chatshop-settings-form', this.saveSettings);
            
            // Modal triggers
            $(document).on('click', '[data-chatshop-modal]', this.openModal);
            $(document).on('click', '.chatshop-modal-close, .chatshop-modal-overlay', this.closeModal);
            
            // Tab navigation
            $(document).on('click', '.chatshop-tab', this.switchTab);
            
            // AJAX form submissions
            $(document).on('submit', '.chatshop-ajax-form', this.handleAjaxForm);
            
            // Copy to clipboard
            $(document).on('click', '.chatshop-copy-btn', this.copyToClipboard);
            
            // Refresh data
            $(document).on('click', '.chatshop-refresh-data', this.refreshData);
        },

        initColorPicker: function() {
            if ($.fn.wpColorPicker) {
                $('.chatshop-color-picker').wpColorPicker();
            }
        },

        initTabs: function() {
            // Set first tab as active if none selected
            if ($('.chatshop-tabs .nav-tab-active').length === 0) {
                $('.chatshop-tabs .nav-tab:first').addClass('nav-tab-active');
                $('.chatshop-tab-content:first').addClass('active');
            }
        },

        initTooltips: function() {
            // Initialize WordPress tooltips
            $('.chatshop-tooltip').each(function() {
                $(this).attr('title', $(this).data('tooltip'));
            });
        },

        checkConnectionStatus: function() {
            // Check WhatsApp connection status
            this.checkWhatsAppStatus();
            
            // Check payment gateway status
            this.checkPaymentStatus();
        },

        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var connectionType = $button.data('connection-type');
            var originalText = $button.text();
            
            $button.prop('disabled', true)
                   .html('<span class="chatshop-spinner"></span>' + chatshop_admin.strings.loading);
            
            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_admin_action',
                    chatshop_action: 'test_' + connectionType + '_connection',
                    nonce: chatshop_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ChatShopAdmin.showNotice(response.data.message, 'success');
                        ChatShopAdmin.updateConnectionStatus(connectionType, 'connected');
                    } else {
                        ChatShopAdmin.showNotice(response.data.message || chatshop_admin.strings.save_error, 'error');
                        ChatShopAdmin.updateConnectionStatus(connectionType, 'disconnected');
                    }
                },
                error: function() {
                    ChatShopAdmin.showNotice(chatshop_admin.strings.save_error, 'error');
                    ChatShopAdmin.updateConnectionStatus(connectionType, 'disconnected');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        checkWhatsAppStatus: function() {
            var $statusIndicator = $('.whatsapp-status-indicator');
            if ($statusIndicator.length === 0) return;
            
            $statusIndicator.removeClass('connected disconnected').addClass('testing');
            
            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_admin_action',
                    chatshop_action: 'check_whatsapp_status',
                    nonce: chatshop_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.connected) {
                        $statusIndicator.removeClass('testing disconnected').addClass('connected');
                    } else {
                        $statusIndicator.removeClass('testing connected').addClass('disconnected');
                    }
                },
                error: function() {
                    $statusIndicator.removeClass('testing connected').addClass('disconnected');
                }
            });
        },

        checkPaymentStatus: function() {
            var $statusIndicator = $('.payment-status-indicator');
            if ($statusIndicator.length === 0) return;
            
            $statusIndicator.removeClass('connected disconnected').addClass('testing');
            
            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_admin_action',
                    chatshop_action: 'check_payment_status',
                    nonce: chatshop_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.connected) {
                        $statusIndicator.removeClass('testing disconnected').addClass('connected');
                    } else {
                        $statusIndicator.removeClass('testing connected').addClass('disconnected');
                    }
                },
                error: function() {
                    $statusIndicator.removeClass('testing connected').addClass('disconnected');
                }
            });
        },

        updateConnectionStatus: function(type, status) {
            var $indicator = $('.' + type + '-status-indicator');
            $indicator.removeClass('connected disconnected testing').addClass(status);
            
            var $statusText = $('.' + type + '-status-text');
            var statusTexts = {
                connected: 'Connected',
                disconnected: 'Disconnected',
                testing: 'Testing...'
            };
            
            if ($statusText.length) {
                $statusText.text(statusTexts[status] || 'Unknown');
            }
        },

        saveSettings: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('[type="submit"]');
            var originalText = $submitButton.val();
            
            $submitButton.prop('disabled', true).val(chatshop_admin.strings.loading);
            
            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=chatshop_admin_action&chatshop_action=save_settings&nonce=' + chatshop_admin.nonce,
                success: function(response) {
                    if (response.success) {
                        ChatShopAdmin.showNotice(chatshop_admin.strings.save_success, 'success');
                    } else {
                        ChatShopAdmin.showNotice(response.data.message || chatshop_admin.strings.save_error, 'error');
                    }
                },
                error: function() {
                    ChatShopAdmin.showNotice(chatshop_admin.strings.save_error, 'error');
                },
                complete: function() {
                    $submitButton.prop('disabled', false).val(originalText);
                }
            });
        },

        openModal: function(e) {
            e.preventDefault();
            
            var modalId = $(this).data('chatshop-modal');
            var $modal = $('#' + modalId);
            
            if ($modal.length) {
                $modal.fadeIn(200);
                $('body').addClass('chatshop-modal-open');
            }
        },

        closeModal: function(e) {
            if (e.target === this || $(e.target).hasClass('chatshop-modal-close')) {
                $(this).closest('.chatshop-modal-overlay').fadeOut(200);
                $('body').removeClass('chatshop-modal-open');
            }
        },

        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var targetTab = $tab.attr('href').substring(1);
            
            // Remove active class from all tabs and content
            $('.chatshop-tabs .nav-tab').removeClass('nav-tab-active');
            $('.chatshop-tab-content').removeClass('active');
            
            // Add active class to clicked tab and corresponding content
            $tab.addClass('nav-tab-active');
            $('#' + targetTab).addClass('active');
        },

        handleAjaxForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitButton = $form.find('[type="submit"]');
            var originalText = $submitButton.val() || $submitButton.text();
            
            $submitButton.prop('disabled', true);
            if ($submitButton.is('input')) {
                $submitButton.val(chatshop_admin.strings.loading);
            } else {
                $submitButton.html('<span class="chatshop-spinner"></span>' + chatshop_admin.strings.loading);
            }
            
            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&nonce=' + chatshop_admin.nonce,
                success: function(response) {
                    if (response.success) {
                        ChatShopAdmin.showNotice(response.data.message || chatshop_admin.strings.success, 'success');
                        
                        // Reset form if requested
                        if (response.data.reset_form) {
                            $form[0].reset();
                        }
                        
                        // Reload page if requested
                        if (response.data.reload) {
                            location.reload();
                        }
                        
                        // Redirect if requested
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        ChatShopAdmin.showNotice(response.data.message || chatshop_admin.strings.error, 'error');
                    }
                },
                error: function() {
                    ChatShopAdmin.showNotice(chatshop_admin.strings.error, 'error');
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                    if ($submitButton.is('input')) {
                        $submitButton.val(originalText);
                    } else {
                        $submitButton.text(originalText);
                    }
                }
            });
        },

        copyToClipboard: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var textToCopy = $button.data('copy-text') || $button.prev('input').val();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    ChatShopAdmin.showNotice('Copied to clipboard!', 'success', 2000);
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(textToCopy).select();
                document.execCommand('copy');
                $temp.remove();
                ChatShopAdmin.showNotice('Copied to clipboard!', 'success', 2000);
            }
        },

        refreshData: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var dataType = $button.data('refresh-type');
            var originalText = $button.text();
            
            $button.prop('disabled', true)
                   .html('<span class="chatshop-spinner"></span>' + chatshop_admin.strings.loading);
            
            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_admin_action',
                    chatshop_action: 'refresh_' + dataType,
                    nonce: chatshop_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ChatShopAdmin.showNotice('Data refreshed successfully!', 'success');
                        
                        // Update specific data sections
                        if (response.data.html) {
                            var $target = $button.data('target');
                            if ($target) {
                                $($target).html(response.data.html);
                            }
                        }
                        
                        // Reload page if no specific target
                        if (!response.data.html) {
                            location.reload();
                        }
                    } else {
                        ChatShopAdmin.showNotice(response.data.message || 'Error refreshing data.', 'error');
                    }
                },
                error: function() {
                    ChatShopAdmin.showNotice('Error refreshing data.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        showNotice: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 5000;
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible chatshop-notice">')
                .html('<p>' + message + '</p>')
                .hide();
            
            // Add dismiss button
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            // Insert notice
            if ($('.wrap > h1').length) {
                $notice.insertAfter('.wrap > h1');
            } else {
                $notice.prependTo('.wrap');
            }
            
            $notice.slideDown(200);
            
            // Auto dismiss
            if (duration > 0) {
                setTimeout(function() {
                    $notice.slideUp(200, function() {
                        $(this).remove();
                    });
                }, duration);
            }
            
            // Manual dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.slideUp(200, function() {
                    $(this).remove();
                });
            });
        },

        hideNotice: function() {
            $('.chatshop-notice').slideUp(200, function() {
                $(this).remove();
            });
        },

        // Utility functions
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        formatCurrency: function(amount, currency) {
            currency = currency || 'NGN';
            return new Intl.NumberFormat('en-NG', {
                style: 'currency',
                currency: currency
            }).format(amount);
        },

        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        // Analytics functions
        loadAnalyticsData: function(period) {
            period = period || '30days';
            
            var $container = $('.chatshop-analytics-container');
            if ($container.length === 0) return;
            
            $container.addClass('chatshop-loading');
            
            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_admin_action',
                    chatshop_action: 'get_analytics_data',
                    period: period,
                    nonce: chatshop_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ChatShopAdmin.renderAnalytics(response.data);
                    } else {
                        ChatShopAdmin.showNotice('Error loading analytics data.', 'error');
                    }
                },
                error: function() {
                    ChatShopAdmin.showNotice('Error loading analytics data.', 'error');
                },
                complete: function() {
                    $container.removeClass('chatshop-loading');
                }
            });
        },

        renderAnalytics: function(data) {
            // Update stats cards
            $('.stat-total-contacts').text(this.formatNumber(data.total_contacts || 0));
            $('.stat-total-payments').text(this.formatNumber(data.total_payments || 0));
            $('.stat-total-revenue').text(this.formatCurrency(data.total_revenue || 0));
            $('.stat-conversion-rate').text((data.conversion_rate || 0) + '%');
            
            // Update charts if chart library is available
            if (typeof Chart !== 'undefined') {
                this.updateCharts(data);
            }
        },

        updateCharts: function(data) {
            // Implementation would depend on chart library used
            // This is a placeholder for chart updates
            console.log('Updating charts with data:', data);
        },

        // Form validation
        validateForm: function($form) {
            var isValid = true;
            var $firstInvalid = null;
            
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                $field.removeClass('error');
                
                if (!value) {
                    $field.addClass('error');
                    isValid = false;
                    
                    if (!$firstInvalid) {
                        $firstInvalid = $field;
                    }
                }
            });
            
            // Validate email fields
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !ChatShopAdmin.isValidEmail(value)) {
                    $field.addClass('error');
                    isValid = false;
                    
                    if (!$firstInvalid) {
                        $firstInvalid = $field;
                    }
                }
            });
            
            if (!isValid && $firstInvalid) {
                $firstInvalid.focus();
                ChatShopAdmin.showNotice('Please check the highlighted fields.', 'error');
            }
            
            return isValid;
        },

        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        // Initialize dashboard widgets
        initDashboard: function() {
            // Auto-refresh dashboard data every 5 minutes
            setInterval(function() {
                ChatShopAdmin.refreshDashboardStats();
            }, 300000);
            
            // Load initial analytics data
            this.loadAnalyticsData();
        },

        refreshDashboardStats: function() {
            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_admin_action',
                    chatshop_action: 'get_dashboard_stats',
                    nonce: chatshop_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ChatShopAdmin.updateDashboardStats(response.data);
                    }
                }
            });
        },

        updateDashboardStats: function(stats) {
            $('.stat-total-contacts h3').text(this.formatNumber(stats.total_contacts || 0));
            $('.stat-total-payments h3').text(this.formatNumber(stats.total_payments || 0));
            $('.stat-total-revenue h3').text(this.formatCurrency(stats.total_revenue || 0));
            
            // Update connection status
            if (stats.whatsapp_connected !== undefined) {
                this.updateConnectionStatus('whatsapp', stats.whatsapp_connected ? 'connected' : 'disconnected');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ChatShopAdmin.init();
        
        // Initialize dashboard if on dashboard page
        if ($('.chatshop-dashboard-header').length) {
            ChatShopAdmin.initDashboard();
        }
    });

    // Export for global access
    window.ChatShopAdmin = ChatShopAdmin;

})(jQuery);