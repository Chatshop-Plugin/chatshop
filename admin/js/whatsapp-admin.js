/**
 * ChatShop WhatsApp Admin JavaScript
 *
 * Handles admin interface interactions for WhatsApp settings
 *
 * @package ChatShop
 * @subpackage Admin
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const ChatShopWhatsAppAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initDataTables();
            this.initTooltips();
            this.initCharts();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Settings form handling
            $(document).on('submit', '.chatshop-settings-form', this.handleSettingsSubmit);
            $(document).on('click', '.test-connection-btn', this.testConnection);
            $(document).on('click', '.sync-templates-btn', this.syncTemplates);
            
            // Contact management
            $(document).on('click', '.delete-contact', this.deleteContact);
            $(document).on('click', '.export-contacts', this.exportContacts);
            $(document).on('click', '.send-message-btn', this.sendMessage);
            
            // Campaign management
            $(document).on('click', '.create-campaign-btn', this.createCampaign);
            $(document).on('click', '.edit-campaign', this.editCampaign);
            $(document).on('click', '.delete-campaign', this.deleteCampaign);
            $(document).on('click', '.execute-campaign', this.executeCampaign);
            
            // Template management
            $(document).on('click', '.create-template-btn', this.createTemplate);
            $(document).on('click', '.preview-template', this.previewTemplate);
            $(document).on('click', '.submit-template', this.submitTemplate);
            
            // Real-time updates
            $(document).on('change', '.auto-save', this.autoSave);
            
            // Modal handling
            $(document).on('click', '.modal-close', this.closeModal);
            $(document).on('click', '.modal-backdrop', this.closeModal);
            
            // Tab switching
            $(document).on('click', '.nav-tab', this.switchTab);
            
            // Form validation
            $(document).on('blur', '.required', this.validateField);
        },

        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('[type="submit"]');
            const originalText = submitBtn.text();
            
            // Validate required fields
            let isValid = true;
            form.find('.required').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('error');
                    isValid = false;
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                ChatShopWhatsAppAdmin.showNotice(
                    chatshopWhatsAppAdmin.strings.settings_save_failed, 
                    'error'
                );
                return;
            }
            
            submitBtn.text(chatshopWhatsAppAdmin.strings.saving + '...').prop('disabled', true);
            
            const formData = new FormData(form[0]);
            formData.append('action', 'chatshop_save_whatsapp_settings');
            formData.append('nonce', chatshopWhatsAppAdmin.nonce);
            
            $.ajax({
                url: chatshopWhatsAppAdmin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        ChatShopWhatsAppAdmin.showNotice(
                            chatshopWhatsAppAdmin.strings.settings_saved, 
                            'success'
                        );
                    } else {
                        ChatShopWhatsAppAdmin.showNotice(
                            response.data || chatshopWhatsAppAdmin.strings.settings_save_failed, 
                            'error'
                        );
                    }
                },
                error: function() {
                    ChatShopWhatsAppAdmin.showNotice(
                        chatshopWhatsAppAdmin.strings.settings_save_failed, 
                        'error'
                    );
                },
                complete: function() {
                    submitBtn.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Test WhatsApp connection
         */
        testConnection: function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const originalText = btn.text();
            
            btn.text(chatshopWhatsAppAdmin.strings.testing_connection)
               .prop('disabled', true)
               .addClass('updating-message');
            
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                action: 'chatshop_test_whatsapp_connection',
                nonce: chatshopWhatsAppAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    ChatShopWhatsAppAdmin.showNotice(
                        chatshopWhatsAppAdmin.strings.connection_successful, 
                        'success'
                    );
                    btn.removeClass('button-secondary').addClass('button-primary');
                } else {
                    ChatShopWhatsAppAdmin.showNotice(
                        response.data || chatshopWhatsAppAdmin.strings.connection_failed, 
                        'error'
                    );
                }
            })
            .fail(function() {
                ChatShopWhatsAppAdmin.showNotice(
                    chatshopWhatsAppAdmin.strings.connection_failed, 
                    'error'
                );
            })
            .always(function() {
                btn.text(originalText)
                   .prop('disabled', false)
                   .removeClass('updating-message');
            });
        },

        /**
         * Sync message templates
         */
        syncTemplates: function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const originalText = btn.text();
            
            btn.text(chatshopWhatsAppAdmin.strings.syncing_templates)
               .prop('disabled', true);
            
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                action: 'chatshop_sync_templates',
                nonce: chatshopWhatsAppAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    const result = response.data;
                    let message = chatshopWhatsAppAdmin.strings.templates_synced;
                    
                    if (result.updated > 0 || result.created > 0) {
                        message += ` (${result.created} created, ${result.updated} updated)`;
                    }
                    
                    ChatShopWhatsAppAdmin.showNotice(message, 'success');
                    
                    // Refresh templates list
                    if ($('.templates-table').length) {
                        location.reload();
                    }
                } else {
                    ChatShopWhatsAppAdmin.showNotice(
                        response.data || chatshopWhatsAppAdmin.strings.sync_failed, 
                        'error'
                    );
                }
            })
            .fail(function() {
                ChatShopWhatsAppAdmin.showNotice(
                    chatshopWhatsAppAdmin.strings.sync_failed, 
                    'error'
                );
            })
            .always(function() {
                btn.text(originalText).prop('disabled', false);
            });
        },

        /**
         * Delete contact
         */
        deleteContact: function(e) {
            e.preventDefault();
            
            if (!confirm(chatshopWhatsAppAdmin.strings.confirm_delete)) {
                return;
            }
            
            const btn = $(this);
            const contactId = btn.data('contact-id');
            const row = btn.closest('tr');
            
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                action: 'chatshop_delete_contact',
                nonce: chatshopWhatsAppAdmin.nonce,
                contact_id: contactId
            })
            .done(function(response) {
                if (response.success) {
                    row.fadeOut(function() {
                        row.remove();
                    });
                    ChatShopWhatsAppAdmin.showNotice(
                        'Contact deleted successfully', 
                        'success'
                    );
                } else {
                    ChatShopWhatsAppAdmin.showNotice(
                        response.data || 'Failed to delete contact', 
                        'error'
                    );
                }
            })
            .fail(function() {
                ChatShopWhatsAppAdmin.showNotice(
                    'Failed to delete contact', 
                    'error'
                );
            });
        },

        /**
         * Export contacts
         */
        exportContacts: function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const originalText = btn.text();
            
            btn.text('Exporting...').prop('disabled', true);
            
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                action: 'chatshop_export_contacts',
                nonce: chatshopWhatsAppAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    // Create and download CSV file
                    const blob = new Blob([response.data.csv_data], { type: 'text/csv' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    ChatShopWhatsAppAdmin.showNotice(
                        'Contacts exported successfully', 
                        'success'
                    );
                } else {
                    ChatShopWhatsAppAdmin.showNotice(
                        response.data || 'Export failed', 
                        'error'
                    );
                }
            })
            .fail(function() {
                ChatShopWhatsAppAdmin.showNotice(
                    'Export failed', 
                    'error'
                );
            })
            .always(function() {
                btn.text(originalText).prop('disabled', false);
            });
        },

        /**
         * Send message to contact
         */
        sendMessage: function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const phoneNumber = btn.data('phone');
            
            // Show message modal
            ChatShopWhatsAppAdmin.showMessageModal(phoneNumber);
        },

        /**
         * Show message modal
         */
        showMessageModal: function(phoneNumber) {
            const modal = `
                <div class="chatshop-modal" id="message-modal">
                    <div class="modal-backdrop"></div>
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Send Message to ${phoneNumber}</h3>
                            <button class="modal-close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="send-message-form">
                                <table class="form-table">
                                    <tr>
                                        <th><label for="message-type">Message Type</label></th>
                                        <td>
                                            <select id="message-type" name="message_type">
                                                <option value="text">Text Message</option>
                                                <option value="template">Template Message</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="text-message-row">
                                        <th><label for="message-text">Message</label></th>
                                        <td>
                                            <textarea id="message-text" name="message" rows="5" class="large-text" required></textarea>
                                        </td>
                                    </tr>
                                    <tr id="template-select-row" style="display: none;">
                                        <th><label for="template-select">Template</label></th>
                                        <td>
                                            <select id="template-select" name="template_name">
                                                <option value="">Select a template...</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                                
                                <input type="hidden" name="phone_number" value="${phoneNumber}">
                                
                                <div class="modal-actions">
                                    <button type="submit" class="button button-primary">Send Message</button>
                                    <button type="button" class="button modal-close">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modal);
            
            // Handle message type change
            $('#message-type').on('change', function() {
                if ($(this).val() === 'template') {
                    $('#text-message-row').hide();
                    $('#template-select-row').show();
                    $('#message-text').prop('required', false);
                    ChatShopWhatsAppAdmin.loadTemplates();
                } else {
                    $('#text-message-row').show();
                    $('#template-select-row').hide();
                    $('#message-text').prop('required', true);
                }
            });
            
            // Handle form submission
            $('#send-message-form').on('submit', function(e) {
                e.preventDefault();
                ChatShopWhatsAppAdmin.submitMessage($(this));
            });
        },

        /**
         * Load templates for modal
         */
        loadTemplates: function() {
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                action: 'chatshop_get_templates',
                nonce: chatshopWhatsAppAdmin.nonce
            })
            .done(function(response) {
                if (response.success) {
                    const select = $('#template-select');
                    select.empty().append('<option value="">Select a template...</option>');
                    
                    response.data.forEach(function(template) {
                        select.append(`<option value="${template.name}">${template.name}</option>`);
                    });
                }
            });
        },

        /**
         * Submit message form
         */
        submitMessage: function(form) {
            const submitBtn = form.find('[type="submit"]');
            const originalText = submitBtn.text();
            
            submitBtn.text(chatshopWhatsAppAdmin.strings.sending_test_message)
                     .prop('disabled', true);
            
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                action: 'chatshop_send_message',
                nonce: chatshopWhatsAppAdmin.nonce,
                ...ChatShopWhatsAppAdmin.serializeForm(form)
            })
            .done(function(response) {
                if (response.success) {
                    ChatShopWhatsAppAdmin.showNotice(
                        chatshopWhatsAppAdmin.strings.test_message_sent, 
                        'success'
                    );
                    ChatShopWhatsAppAdmin.closeModal();
                } else {
                    ChatShopWhatsAppAdmin.showNotice(
                        response.data || chatshopWhatsAppAdmin.strings.test_message_failed, 
                        'error'
                    );
                }
            })
            .fail(function() {
                ChatShopWhatsAppAdmin.showNotice(
                    chatshopWhatsAppAdmin.strings.test_message_failed, 
                    'error'
                );
            })
            .always(function() {
                submitBtn.text(originalText).prop('disabled', false);
            });
        },

        /**
         * Initialize data tables
         */
        initDataTables: function() {
            if ($.fn.DataTable && $('.chatshop-data-table').length) {
                $('.chatshop-data-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });
            }
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                $(this).attr('title', $(this).data('tooltip'));
            });
        },

        /**
         * Initialize charts
         */
        initCharts: function() {
            // Initialize Chart.js charts if present
            if (typeof Chart !== 'undefined') {
                $('.chatshop-chart').each(function() {
                    const chartType = $(this).data('chart-type') || 'line';
                    const chartData = $(this).data('chart-data');
                    
                    if (chartData) {
                        new Chart(this.getContext('2d'), {
                            type: chartType,
                            data: chartData,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    }
                });
            }
        },

        /**
         * Switch tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            const tab = $(this);
            const target = tab.attr('href');
            
            // Update active tab
            tab.siblings().removeClass('nav-tab-active');
            tab.addClass('nav-tab-active');
            
            // Show target content
            $('.tab-content').hide();
            $(target).show();
            
            // Update URL hash
            if (history.replaceState) {
                history.replaceState(null, null, target);
            }
        },

        /**
         * Validate field
         */
        validateField: function() {
            const field = $(this);
            const value = field.val().trim();
            
            if (field.hasClass('required') && !value) {
                field.addClass('error');
                return false;
            } else {
                field.removeClass('error');
                return true;
            }
        },

        /**
         * Auto save functionality
         */
        autoSave: function() {
            const field = $(this);
            const form = field.closest('form');
            
            clearTimeout(ChatShopWhatsAppAdmin.autoSaveTimeout);
            
            ChatShopWhatsAppAdmin.autoSaveTimeout = setTimeout(function() {
                const formData = ChatShopWhatsAppAdmin.serializeForm(form);
                formData.action = 'chatshop_auto_save_settings';
                formData.nonce = chatshopWhatsAppAdmin.nonce;
                
                $.post(chatshopWhatsAppAdmin.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        ChatShopWhatsAppAdmin.showTempMessage('Settings auto-saved');
                    }
                });
            }, 2000);
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            $('.chatshop-modal').fadeOut(function() {
                $(this).remove();
            });
        },

        /**
         * Show notification
         */
        showNotice: function(message, type) {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wrap h1').after(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
            
            // Manual dismiss
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            });
        },

        /**
         * Show temporary message
         */
        showTempMessage: function(message) {
            const tempMsg = $(`
                <div class="chatshop-temp-message">
                    <span class="dashicons dashicons-yes-alt"></span>
                    ${message}
                </div>
            `);
            
            $('body').append(tempMsg);
            
            setTimeout(function() {
                tempMsg.addClass('show');
            }, 100);
            
            setTimeout(function() {
                tempMsg.removeClass('show');
                setTimeout(function() {
                    tempMsg.remove();
                }, 300);
            }, 2500);
        },

        /**
         * Serialize form data
         */
        serializeForm: function(form) {
            const formData = {};
            const serialized = form.serializeArray();
            
            serialized.forEach(function(field) {
                if (formData[field.name]) {
                    if (!Array.isArray(formData[field.name])) {
                        formData[field.name] = [formData[field.name]];
                    }
                    formData[field.name].push(field.value);
                } else {
                    formData[field.name] = field.value;
                }
            });
            
            return formData;
        },

        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },

        /**
         * Refresh page content
         */
        refreshContent: function(container) {
            if (container) {
                $(container).addClass('loading');
                
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        },

        /**
         * Handle AJAX errors globally
         */
        handleAjaxError: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            
            let message = 'An error occurred. Please try again.';
            
            if (xhr.responseJSON && xhr.responseJSON.data) {
                message = xhr.responseJSON.data;
            } else if (xhr.status === 403) {
                message = 'You do not have permission to perform this action.';
            } else if (xhr.status === 500) {
                message = 'Server error. Please contact support if the problem persists.';
            }
            
            ChatShopWhatsAppAdmin.showNotice(message, 'error');
        },

        /**
         * Load content via AJAX
         */
        loadContent: function(action, data, callback) {
            const requestData = {
                action: action,
                nonce: chatshopWhatsAppAdmin.nonce,
                ...data
            };
            
            return $.post(chatshopWhatsAppAdmin.ajax_url, requestData)
                .done(function(response) {
                    if (response.success) {
                        if (callback) callback(response.data);
                    } else {
                        ChatShopWhatsAppAdmin.showNotice(
                            response.data || 'Failed to load content', 
                            'error'
                        );
                    }
                })
                .fail(ChatShopWhatsAppAdmin.handleAjaxError);
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function() {
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

    // Auto-save timeout reference
    ChatShopWhatsAppAdmin.autoSaveTimeout = null;

    // Initialize when document is ready
    $(document).ready(function() {
        ChatShopWhatsAppAdmin.init();
        
        // Set up global AJAX error handling
        $(document).ajaxError(ChatShopWhatsAppAdmin.handleAjaxError);
        
        // Handle hash navigation on page load
        if (window.location.hash) {
            const hash = window.location.hash;
            if ($(hash).length && $('.nav-tab[href="' + hash + '"]').length) {
                $('.nav-tab[href="' + hash + '"]').trigger('click');
            }
        }
        
        // Auto-refresh stats every 30 seconds on dashboard
        if ($('.chatshop-stats-widget').length) {
            setInterval(function() {
                ChatShopWhatsAppAdmin.loadContent('chatshop_get_contact_stats', {}, function(stats) {
                    $('#total-contacts').text(ChatShopWhatsAppAdmin.formatNumber(stats.total_contacts || 0));
                    $('#messages-today').text(ChatShopWhatsAppAdmin.formatNumber(
                        (stats.messages_sent_today || 0) + (stats.messages_received_today || 0)
                    ));
                    $('#active-campaigns').text(ChatShopWhatsAppAdmin.formatNumber(stats.active_campaigns || 0));
                    $('#response-rate').text((stats.response_rate || 0) + '%');
                });
            }, 30000);
        }
    });

    // Expose to global scope for external access
    window.ChatShopWhatsAppAdmin = ChatShopWhatsAppAdmin;

})(jQuery);