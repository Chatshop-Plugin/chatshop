/**
 * Admin Contacts Management JavaScript
 *
 * File: admin/js/chatshop-contacts.js
 * 
 * Handles all contact management functionality including AJAX operations,
 * modal interactions, import/export features, and freemium limitations.
 *
 * @package ChatShop
 * @subpackage Admin\JS
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // ChatShop Contacts Management Object
    const ChatShopContacts = {
        
        // Initialize the contact management system
        init: function() {
            this.bindEvents();
            this.initModals();
            this.setupTableFeatures();
        },

        // Bind all event handlers
        bindEvents: function() {
            // Contact CRUD operations
            $(document).on('click', '#chatshop-add-contact, #chatshop-add-first-contact', this.showAddContactModal);
            $(document).on('click', '.chatshop-edit-contact', this.showEditContactModal);
            $(document).on('click', '.chatshop-delete-contact', this.deleteContact);
            $(document).on('click', '#chatshop-save-contact', this.saveContact);
            $(document).on('click', '#chatshop-cancel-contact', this.hideContactModal);

            // Bulk operations
            $(document).on('click', '#chatshop-select-all-contacts', this.toggleSelectAll);
            $(document).on('click', '#chatshop-apply-bulk-action', this.applyBulkAction);

            // Import/Export
            $(document).on('click', '#chatshop-import-contacts', this.showImportModal);
            $(document).on('click', '#chatshop-export-contacts', this.exportContacts);
            $(document).on('click', '#chatshop-download-template, #chatshop-download-template-link', this.downloadTemplate);
            $(document).on('click', '#chatshop-start-import', this.startImport);
            $(document).on('click', '#chatshop-cancel-import', this.hideImportModal);

            // Premium features
            $(document).on('click', '.chatshop-premium-feature', this.showPremiumModal);
            $(document).on('click', '#chatshop-close-premium-modal', this.hidePremiumModal);

            // Modal close handlers
            $(document).on('click', '.chatshop-modal-close', this.closeModal);
            $(document).on('click', '.chatshop-modal', this.closeModalOnBackdrop);

            // Form validation
            $(document).on('input', '#contact-phone', this.validatePhoneNumber);
            $(document).on('submit', '#chatshop-contact-form', this.preventFormSubmit);

            // Auto-refresh stats
            this.setupStatsRefresh();
        },

        // Initialize modal functionality
        initModals: function() {
            // Prevent modal close on content click
            $('.chatshop-modal-content').on('click', function(e) {
                e.stopPropagation();
            });
        },

        // Setup table features
        setupTableFeatures: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Setup responsive table
            this.makeTableResponsive();
        },

        // Show add contact modal
        showAddContactModal: function(e) {
            e.preventDefault();
            
            // Check contact limit for free users
            if (!ChatShopContacts.canAddContact()) {
                ChatShopContacts.showPremiumModal('unlimited_contacts');
                return;
            }

            $('#chatshop-contact-modal-title').text(chatshopContactsL10n.addContact);
            $('#chatshop-contact-form')[0].reset();
            $('#contact-id').val('');
            $('#contact-phone').prop('readonly', false);
            $('#chatshop-save-contact').text(chatshopContactsL10n.addContact);
            
            ChatShopContacts.showModal('#chatshop-contact-modal');
        },

        // Show edit contact modal
        showEditContactModal: function(e) {
            e.preventDefault();
            
            const contactId = $(this).data('contact-id');
            const row = $(`tr[data-contact-id="${contactId}"]`);
            
            // Extract contact data from table row
            const contactData = {
                id: contactId,
                name: row.find('.column-name strong').text().trim(),
                phone: row.find('.column-phone code').text().trim(),
                email: row.find('.column-email a').text().trim() || '',
                status: row.find('.column-status .chatshop-status-badge').text().toLowerCase().trim(),
                tags: '',
                notes: ''
            };

            // Extract tags
            const tagElements = row.find('.column-tags .chatshop-tag');
            const tags = [];
            tagElements.each(function() {
                tags.push($(this).text().trim());
            });
            contactData.tags = tags.join(', ');

            // Extract notes from description
            const notesElement = row.find('.column-name .description');
            if (notesElement.length) {
                contactData.notes = notesElement.text().trim();
            }

            // Populate form
            $('#chatshop-contact-modal-title').text(chatshopContactsL10n.editContact);
            $('#contact-id').val(contactData.id);
            $('#contact-phone').val(contactData.phone).prop('readonly', true);
            $('#contact-name').val(contactData.name);
            $('#contact-email').val(contactData.email);
            $('#contact-status').val(contactData.status);
            $('#contact-tags').val(contactData.tags);
            $('#contact-notes').val(contactData.notes);
            $('#chatshop-save-contact').text(chatshopContactsL10n.updateContact);
            
            ChatShopContacts.showModal('#chatshop-contact-modal');
        },

        // Delete single contact
        deleteContact: function(e) {
            e.preventDefault();
            
            const contactId = $(this).data('contact-id');
            const contactName = $(this).closest('tr').find('.column-name strong').text().trim();
            
            if (!confirm(chatshopContactsL10n.confirmDelete.replace('%s', contactName))) {
                return;
            }

            ChatShopContacts.showSpinner();

            $.ajax({
                url: chatshopContactsL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_delete_contact',
                    contact_id: contactId,
                    nonce: chatshopContactsL10n.nonce
                },
                success: function(response) {
                    ChatShopContacts.hideSpinner();
                    
                    if (response.success) {
                        ChatShopContacts.showNotice(response.message, 'success');
                        $(`tr[data-contact-id="${contactId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            ChatShopContacts.updateContactStats();
                        });
                    } else {
                        ChatShopContacts.showNotice(response.message, 'error');
                    }
                },
                error: function() {
                    ChatShopContacts.hideSpinner();
                    ChatShopContacts.showNotice(chatshopContactsL10n.errorGeneric, 'error');
                }
            });
        },

        // Save contact (add or update)
        saveContact: function(e) {
            e.preventDefault();
            
            const form = $('#chatshop-contact-form');
            const formData = new FormData(form[0]);
            const contactId = $('#contact-id').val();
            const isEdit = contactId !== '';

            // Validate form
            if (!ChatShopContacts.validateContactForm()) {
                return;
            }

            // Check contact limit for new contacts
            if (!isEdit && !ChatShopContacts.canAddContact()) {
                ChatShopContacts.showPremiumModal('unlimited_contacts');
                return;
            }

            const actionName = isEdit ? 'chatshop_update_contact' : 'chatshop_add_contact';
            formData.append('action', actionName);
            formData.append('nonce', chatshopContactsL10n.nonce);

            $('#chatshop-save-contact').prop('disabled', true).text(chatshopContactsL10n.saving);

            $.ajax({
                url: chatshopContactsL10n.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#chatshop-save-contact').prop('disabled', false).text(
                        isEdit ? chatshopContactsL10n.updateContact : chatshopContactsL10n.addContact
                    );
                    
                    if (response.success) {
                        ChatShopContacts.hideContactModal();
                        ChatShopContacts.showNotice(response.message, 'success');
                        
                        // Reload page to show updated contact list
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        ChatShopContacts.showNotice(response.message, 'error');
                    }
                },
                error: function() {
                    $('#chatshop-save-contact').prop('disabled', false).text(
                        isEdit ? chatshopContactsL10n.updateContact : chatshopContactsL10n.addContact
                    );
                    ChatShopContacts.showNotice(chatshopContactsL10n.errorGeneric, 'error');
                }
            });
        },

        // Toggle select all checkboxes
        toggleSelectAll: function() {
            const isChecked = $(this).prop('checked');
            $('.chatshop-contact-checkbox').prop('checked', isChecked);
        },

        // Apply bulk action
        applyBulkAction: function(e) {
            e.preventDefault();
            
            const action = $('#chatshop-bulk-action').val();
            const selectedContacts = $('.chatshop-contact-checkbox:checked');
            
            if (!action) {
                ChatShopContacts.showNotice(chatshopContactsL10n.selectBulkAction, 'warning');
                return;
            }
            
            if (selectedContacts.length === 0) {
                ChatShopContacts.showNotice(chatshopContactsL10n.selectContacts, 'warning');
                return;
            }

            const contactIds = [];
            selectedContacts.each(function() {
                contactIds.push($(this).val());
            });

            let confirmMessage = '';
            switch (action) {
                case 'delete':
                    confirmMessage = chatshopContactsL10n.confirmBulkDelete.replace('%d', contactIds.length);
                    break;
                case 'activate':
                    confirmMessage = chatshopContactsL10n.confirmBulkActivate.replace('%d', contactIds.length);
                    break;
                case 'deactivate':
                    confirmMessage = chatshopContactsL10n.confirmBulkDeactivate.replace('%d', contactIds.length);
                    break;
            }

            if (!confirm(confirmMessage)) {
                return;
            }

            ChatShopContacts.showSpinner();

            $.ajax({
                url: chatshopContactsL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_bulk_' + action + '_contacts',
                    contact_ids: contactIds,
                    nonce: chatshopContactsL10n.nonce
                },
                success: function(response) {
                    ChatShopContacts.hideSpinner();
                    
                    if (response.success) {
                        ChatShopContacts.showNotice(response.message, 'success');
                        
                        // Reload page to show changes
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        ChatShopContacts.showNotice(response.message, 'error');
                    }
                },
                error: function() {
                    ChatShopContacts.hideSpinner();
                    ChatShopContacts.showNotice(chatshopContactsL10n.errorGeneric, 'error');
                }
            });
        },

        // Show import modal
        showImportModal: function(e) {
            e.preventDefault();
            
            if (!ChatShopContacts.isPremiumFeatureAvailable('contact_import_export')) {
                ChatShopContacts.showPremiumModal('contact_import_export');
                return;
            }

            $('#chatshop-import-form')[0].reset();
            $('#chatshop-import-progress').hide();
            $('#chatshop-import-results').hide();
            
            ChatShopContacts.showModal('#chatshop-import-modal');
        },

        // Start contact import
        startImport: function(e) {
            e.preventDefault();
            
            const fileInput = $('#import-file')[0];
            if (!fileInput.files.length) {
                ChatShopContacts.showNotice(chatshopContactsL10n.selectFile, 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'chatshop_import_contacts');
            formData.append('import_file', fileInput.files[0]);
            formData.append('nonce', chatshopContactsL10n.nonce);

            $('#chatshop-start-import').prop('disabled', true);
            $('#chatshop-import-progress').show();
            $('.chatshop-progress-fill').css('width', '50%');

            $.ajax({
                url: chatshopContactsL10n.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('.chatshop-progress-fill').css('width', '100%');
                    
                    setTimeout(() => {
                        $('#chatshop-import-progress').hide();
                        ChatShopContacts.showImportResults(response);
                        $('#chatshop-start-import').prop('disabled', false);
                    }, 500);
                },
                error: function() {
                    $('#chatshop-import-progress').hide();
                    $('#chatshop-start-import').prop('disabled', false);
                    ChatShopContacts.showNotice(chatshopContactsL10n.errorGeneric, 'error');
                }
            });
        },

        // Show import results
        showImportResults: function(response) {
            const resultsDiv = $('#chatshop-import-results');
            let html = '<div class="chatshop-import-summary">';
            
            if (response.success) {
                html += '<div class="notice notice-success"><p>' + response.message + '</p></div>';
                
                if (response.imported_count > 0) {
                    html += '<p><strong>' + chatshopContactsL10n.importedCount.replace('%d', response.imported_count) + '</strong></p>';
                }
                
                if (response.skipped_count > 0) {
                    html += '<p>' + chatshopContactsL10n.skippedCount.replace('%d', response.skipped_count) + '</p>';
                }
                
                if (response.failed_count > 0) {
                    html += '<p>' + chatshopContactsL10n.failedCount.replace('%d', response.failed_count) + '</p>';
                }
            } else {
                html += '<div class="notice notice-error"><p>' + response.message + '</p></div>';
            }
            
            if (response.errors && response.errors.length > 0) {
                html += '<details><summary>' + chatshopContactsL10n.showErrors + '</summary>';
                html += '<ul class="chatshop-import-errors">';
                response.errors.forEach(function(error) {
                    html += '<li>' + error + '</li>';
                });
                html += '</ul></details>';
            }
            
            html += '</div>';
            html += '<button type="button" class="button button-primary" onclick="window.location.reload()">' + chatshopContactsL10n.refreshPage + '</button>';
            
            resultsDiv.html(html).show();
        },

        // Export contacts
        exportContacts: function(e) {
            e.preventDefault();
            
            if (!ChatShopContacts.isPremiumFeatureAvailable('contact_import_export')) {
                ChatShopContacts.showPremiumModal('contact_import_export');
                return;
            }

            ChatShopContacts.showSpinner();

            $.ajax({
                url: chatshopContactsL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_export_contacts',
                    format: 'csv',
                    nonce: chatshopContactsL10n.nonce
                },
                success: function(response) {
                    ChatShopContacts.hideSpinner();
                    
                    if (response.success) {
                        ChatShopContacts.showNotice(response.message, 'success');
                        
                        // Trigger download
                        const link = document.createElement('a');
                        link.href = response.download_url;
                        link.download = response.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        ChatShopContacts.showNotice(response.message, 'error');
                    }
                },
                error: function() {
                    ChatShopContacts.hideSpinner();
                    ChatShopContacts.showNotice(chatshopContactsL10n.errorGeneric, 'error');
                }
            });
        },

        // Download CSV template
        downloadTemplate: function(e) {
            e.preventDefault();
            
            // Create and download template
            const csvContent = "phone,name,email,tags,notes,status\n+1234567890,John Doe,john@example.com,\"customer,vip\",Important customer,active\n+0987654321,Jane Smith,jane@example.com,prospect,Potential lead,active";
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = 'chatshop-contacts-template.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            window.URL.revokeObjectURL(url);
        },

        // Show premium feature modal
        showPremiumModal: function(feature) {
            const featureInfo = ChatShopContacts.getPremiumFeatureInfo(feature);
            $('#chatshop-premium-feature-title').text(featureInfo.title);
            $('#chatshop-premium-feature-description').text(featureInfo.description);
            
            ChatShopContacts.showModal('#chatshop-premium-modal');
        },

        // Get premium feature information
        getPremiumFeatureInfo: function(feature) {
            const features = {
                unlimited_contacts: {
                    title: chatshopContactsL10n.unlimitedContactsTitle,
                    description: chatshopContactsL10n.unlimitedContactsDesc
                },
                contact_import_export: {
                    title: chatshopContactsL10n.importExportTitle,
                    description: chatshopContactsL10n.importExportDesc
                }
            };
            
            return features[feature] || {
                title: chatshopContactsL10n.premiumFeatureTitle,
                description: chatshopContactsL10n.premiumFeatureDesc
            };
        },

        // Modal management functions
        showModal: function(selector) {
            $(selector).fadeIn(200);
            $('body').addClass('chatshop-modal-open');
        },

        hideContactModal: function() {
            $('#chatshop-contact-modal').fadeOut(200);
            $('body').removeClass('chatshop-modal-open');
        },

        hideImportModal: function() {
            $('#chatshop-import-modal').fadeOut(200);
            $('body').removeClass('chatshop-modal-open');
        },

        hidePremiumModal: function() {
            $('#chatshop-premium-modal').fadeOut(200);
            $('body').removeClass('chatshop-modal-open');
        },

        closeModal: function(e) {
            e.preventDefault();
            $(this).closest('.chatshop-modal').fadeOut(200);
            $('body').removeClass('chatshop-modal-open');
        },

        closeModalOnBackdrop: function(e) {
            if (e.target === this) {
                $(this).fadeOut(200);
                $('body').removeClass('chatshop-modal-open');
            }
        },

        // Validation functions
        validateContactForm: function() {
            const phone = $('#contact-phone').val().trim();
            const name = $('#contact-name').val().trim();
            
            if (!phone) {
                ChatShopContacts.showNotice(chatshopContactsL10n.phoneRequired, 'error');
                $('#contact-phone').focus();
                return false;
            }
            
            if (!name) {
                ChatShopContacts.showNotice(chatshopContactsL10n.nameRequired, 'error');
                $('#contact-name').focus();
                return false;
            }
            
            if (!ChatShopContacts.isValidPhone(phone)) {
                ChatShopContacts.showNotice(chatshopContactsL10n.invalidPhone, 'error');
                $('#contact-phone').focus();
                return false;
            }
            
            const email = $('#contact-email').val().trim();
            if (email && !ChatShopContacts.isValidEmail(email)) {
                ChatShopContacts.showNotice(chatshopContactsL10n.invalidEmail, 'error');
                $('#contact-email').focus();
                return false;
            }
            
            return true;
        },

        validatePhoneNumber: function() {
            const phone = $(this).val().trim();
            const isValid = ChatShopContacts.isValidPhone(phone);
            
            $(this).toggleClass('invalid', phone !== '' && !isValid);
        },

        isValidPhone: function(phone) {
            // Basic phone validation - should start with + and have at least 10 digits
            const phoneRegex = /^\+?[1-9]\d{9,14}$/;
            return phoneRegex.test(phone.replace(/\s/g, ''));
        },

        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        // Utility functions
        canAddContact: function() {
            return ChatShopContacts.isPremiumFeatureAvailable('unlimited_contacts') || 
                   (chatshopContactsL10n.monthlyUsage < chatshopContactsL10n.monthlyLimit);
        },

        isPremiumFeatureAvailable: function(feature) {
            return chatshopContactsL10n.premiumFeatures && 
                   chatshopContactsL10n.premiumFeatures[feature];
        },

        showSpinner: function() {
            if ($('#chatshop-spinner').length === 0) {
                $('body').append('<div id="chatshop-spinner" class="chatshop-spinner"><div class="spinner"></div></div>');
            }
            $('#chatshop-spinner').show();
        },

        hideSpinner: function() {
            $('#chatshop-spinner').hide();
        },

        showNotice: function(message, type) {
            const noticeClass = 'notice-' + (type || 'info');
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Scroll to top to show notice
            $('html, body').animate({ scrollTop: 0 }, 300);
        },

        updateContactStats: function() {
            $.ajax({
                url: chatshopContactsL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_get_contact_stats',
                    nonce: chatshopContactsL10n.nonce
                },
                success: function(response) {
                    if (response.success && response.stats) {
                        // Update stat cards
                        $('.stat-card:eq(0) .stat-number').text(response.stats.total);
                        $('.stat-card:eq(1) .stat-number').text(response.stats.active);
                        $('.stat-card:eq(2) .stat-number').text(response.stats.opted_in);
                        
                        // Update monthly usage
                        if (!response.stats.is_premium) {
                            $('.stat-card:eq(3) .stat-number').text(
                                response.stats.monthly_usage + '/' + response.stats.monthly_limit
                            );
                        }
                    }
                }
            });
        },

        setupStatsRefresh: function() {
            // Refresh stats every 30 seconds
            setInterval(() => {
                ChatShopContacts.updateContactStats();
            }, 30000);
        },

        initTooltips: function() {
            // Add tooltips to action buttons
            $('[title]').each(function() {
                $(this).tooltip();
            });
        },

        makeTableResponsive: function() {
            // Add responsive wrapper if needed
            if ($(window).width() < 768) {
                $('.wp-list-table').wrap('<div class="table-responsive"></div>');
            }
        },

        preventFormSubmit: function(e) {
            e.preventDefault();
            return false;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ChatShopContacts.init();
    });

    // Add some basic styles for functionality
    $('<style>').text(`
        .chatshop-spinner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chatshop-spinner .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0073aa;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .invalid {
            border-color: #e53e3e !important;
            box-shadow: 0 0 0 1px #e53e3e !important;
        }
        
        body.chatshop-modal-open {
            overflow: hidden;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .chatshop-import-errors {
            max-height: 200px;
            overflow-y: auto;
            margin: 10px 0;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .chatshop-import-errors li {
            color: #721c24;
            margin-bottom: 5px;
        }
    `).appendTo('head');

})(jQuery);