<?php

/**
 * WhatsApp Contacts Management Page
 *
 * @package ChatShop
 * @subpackage Admin\Partials
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap chatshop-whatsapp-contacts">
    <h1 class="wp-heading-inline"><?php _e('WhatsApp Contacts', 'chatshop'); ?></h1>

    <div class="page-title-action">
        <a href="#" class="page-title-action export-contacts">
            <?php _e('Export Contacts', 'chatshop'); ?>
        </a>
        <a href="#" class="page-title-action" id="import-contacts">
            <?php _e('Import Contacts', 'chatshop'); ?>
        </a>
    </div>

    <hr class="wp-header-end">

    <!-- Contact Stats -->
    <div class="chatshop-contacts-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="total-contacts-stat"><?php echo number_format($total_contacts); ?></div>
                <div class="stat-label"><?php _e('Total Contacts', 'chatshop'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="opted-in-contacts">-</div>
                <div class="stat-label"><?php _e('Opted In', 'chatshop'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="recent-contacts">-</div>
                <div class="stat-label"><?php _e('Added This Week', 'chatshop'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="active-contacts">-</div>
                <div class="stat-label"><?php _e('Active (30 days)', 'chatshop'); ?></div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="filter_opt_in" id="filter-opt-in">
                <option value=""><?php _e('All Contacts', 'chatshop'); ?></option>
                <option value="1"><?php _e('Opted In', 'chatshop'); ?></option>
                <option value="0"><?php _e('Not Opted In', 'chatshop'); ?></option>
            </select>

            <select name="filter_source" id="filter-source">
                <option value=""><?php _e('All Sources', 'chatshop'); ?></option>
                <option value="manual"><?php _e('Manual Entry', 'chatshop'); ?></option>
                <option value="checkout"><?php _e('Checkout', 'chatshop'); ?></option>
                <option value="campaign"><?php _e('Campaign', 'chatshop'); ?></option>
                <option value="webhook"><?php _e('WhatsApp Webhook', 'chatshop'); ?></option>
            </select>

            <input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e('Filter', 'chatshop'); ?>">
        </div>

        <div class="alignright actions">
            <input type="search" id="contact-search" placeholder="<?php esc_attr_e('Search contacts...', 'chatshop'); ?>" />
            <button type="button" class="button" id="search-contacts"><?php _e('Search', 'chatshop'); ?></button>
        </div>
    </div>

    <!-- Contacts Table -->
    <div class="contacts-table-container">
        <table class="wp-list-table widefat fixed striped chatshop-data-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all" />
                    </th>
                    <th scope="col" class="manage-column column-name"><?php _e('Name', 'chatshop'); ?></th>
                    <th scope="col" class="manage-column column-phone"><?php _e('Phone Number', 'chatshop'); ?></th>
                    <th scope="col" class="manage-column column-email"><?php _e('Email', 'chatshop'); ?></th>
                    <th scope="col" class="manage-column column-opt-in"><?php _e('Opt-in Status', 'chatshop'); ?></th>
                    <th scope="col" class="manage-column column-source"><?php _e('Source', 'chatshop'); ?></th>
                    <th scope="col" class="manage-column column-last-interaction"><?php _e('Last Interaction', 'chatshop'); ?></th>
                    <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'chatshop'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contacts)): ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="8">
                            <?php _e('No contacts found.', 'chatshop'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contacts as $contact): ?>
                        <tr data-contact-id="<?php echo esc_attr($contact['id']); ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="contact[]" value="<?php echo esc_attr($contact['id']); ?>" />
                            </th>
                            <td class="column-name">
                                <strong>
                                    <?php echo esc_html(trim($contact['first_name'] . ' ' . $contact['last_name']) ?: __('(No name)', 'chatshop')); ?>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="#" class="edit-contact" data-contact-id="<?php echo esc_attr($contact['id']); ?>">
                                            <?php _e('Edit', 'chatshop'); ?>
                                        </a>
                                    </span>
                                    <span class="trash"> |
                                        <a href="#" class="delete-contact" data-contact-id="<?php echo esc_attr($contact['id']); ?>">
                                            <?php _e('Delete', 'chatshop'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-phone">
                                <code><?php echo esc_html($contact['phone_number']); ?></code>
                                <button type="button" class="button-link copy-phone" data-phone="<?php echo esc_attr($contact['phone_number']); ?>" title="<?php esc_attr_e('Copy phone number', 'chatshop'); ?>">
                                    <span class="dashicons dashicons-admin-page"></span>
                                </button>
                            </td>
                            <td class="column-email">
                                <?php if (!empty($contact['email'])): ?>
                                    <a href="mailto:<?php echo esc_attr($contact['email']); ?>">
                                        <?php echo esc_html($contact['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="description"><?php _e('Not provided', 'chatshop'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-opt-in">
                                <?php if ($contact['opt_in']): ?>
                                    <span class="status-badge status-opted-in">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('Opted In', 'chatshop'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-opted-out">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <?php _e('Not Opted In', 'chatshop'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="column-source">
                                <span class="source-badge source-<?php echo esc_attr($contact['source'] ?? 'unknown'); ?>">
                                    <?php
                                    $sources = [
                                        'manual' => __('Manual Entry', 'chatshop'),
                                        'checkout' => __('Checkout', 'chatshop'),
                                        'campaign' => __('Campaign', 'chatshop'),
                                        'webhook' => __('WhatsApp', 'chatshop'),
                                        'import' => __('Import', 'chatshop')
                                    ];
                                    echo esc_html($sources[$contact['source'] ?? 'unknown'] ?? __('Unknown', 'chatshop'));
                                    ?>
                                </span>
                            </td>
                            <td class="column-last-interaction">
                                <?php if (!empty($contact['last_interaction'])): ?>
                                    <time datetime="<?php echo esc_attr($contact['last_interaction']); ?>" title="<?php echo esc_attr(date_i18n('Y-m-d H:i:s', strtotime($contact['last_interaction']))); ?>">
                                        <?php echo esc_html(human_time_diff(strtotime($contact['last_interaction']), current_time('timestamp')) . ' ago'); ?>
                                    </time>
                                <?php else: ?>
                                    <span class="description"><?php _e('Never', 'chatshop'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <div class="table-actions">
                                    <button type="button" class="button button-small send-message-btn"
                                        data-phone="<?php echo esc_attr($contact['phone_number']); ?>"
                                        data-name="<?php echo esc_attr(trim($contact['first_name'] . ' ' . $contact['last_name'])); ?>">
                                        <span class="dashicons dashicons-email-alt"></span>
                                        <?php _e('Message', 'chatshop'); ?>
                                    </button>

                                    <button type="button" class="button button-small view-history"
                                        data-contact-id="<?php echo esc_attr($contact['id']); ?>">
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php _e('History', 'chatshop'); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(
                        _n('%s item', '%s items', $total_contacts, 'chatshop'),
                        number_format_i18n($total_contacts)
                    ); ?>
                </span>

                <?php
                $page_links = paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $page,
                    'type' => 'array'
                ]);

                if ($page_links):
                    echo '<span class="pagination-links">';
                    foreach ($page_links as $link) {
                        echo $link;
                    }
                    echo '</span>';
                endif;
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bulk Actions (if implemented) -->
    <div class="tablenav bottom">
        <div class="alignleft actions bulkactions">
            <select name="bulk_action" id="bulk-action-selector-bottom">
                <option value="-1"><?php _e('Bulk Actions', 'chatshop'); ?></option>
                <option value="delete"><?php _e('Delete', 'chatshop'); ?></option>
                <option value="opt_in"><?php _e('Mark as Opted In', 'chatshop'); ?></option>
                <option value="opt_out"><?php _e('Mark as Opted Out', 'chatshop'); ?></option>
                <option value="send_message"><?php _e('Send Message', 'chatshop'); ?></option>
            </select>
            <input type="submit" id="doaction-bottom" class="button action" value="<?php esc_attr_e('Apply', 'chatshop'); ?>">
        </div>
    </div>
</div>

<!-- Add Contact Modal -->
<div id="add-contact-modal" class="chatshop-modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Add New Contact', 'chatshop'); ?></h3>
            <button class="modal-close" type="button">&times;</button>
        </div>
        <div class="modal-body">
            <form id="add-contact-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="contact-first-name"><?php _e('First Name', 'chatshop'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="contact-first-name" name="first_name" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="contact-last-name"><?php _e('Last Name', 'chatshop'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="contact-last-name" name="last_name" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="contact-phone"><?php _e('Phone Number', 'chatshop'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="tel" id="contact-phone" name="phone_number" class="regular-text" required
                                placeholder="+1234567890" />
                            <p class="description"><?php _e('Include country code (e.g., +1234567890)', 'chatshop'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="contact-email"><?php _e('Email Address', 'chatshop'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="contact-email" name="email" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="contact-opt-in"><?php _e('Opt-in Status', 'chatshop'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" id="contact-opt-in" name="opt_in" value="1" checked />
                                    <?php _e('Contact has opted in to receive WhatsApp messages', 'chatshop'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <div class="modal-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Add Contact', 'chatshop'); ?>
                    </button>
                    <button type="button" class="button modal-close">
                        <?php _e('Cancel', 'chatshop'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Load contact stats
        loadContactStats();

        // Initialize contact management
        initContactManagement();

        function loadContactStats() {
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                    action: 'chatshop_get_contact_stats',
                    nonce: chatshopWhatsAppAdmin.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        const stats = response.data;
                        $('#opted-in-contacts').text(stats.opted_in_contacts || 0);
                        $('#recent-contacts').text(stats.recent_contacts || 0);
                        $('#active-contacts').text(stats.active_contacts || 0);
                    }
                });
        }

        function initContactManagement() {
            // Copy phone number to clipboard
            $(document).on('click', '.copy-phone', function(e) {
                e.preventDefault();
                const phone = $(this).data('phone');

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(phone).then(function() {
                        showTempMessage('Phone number copied!');
                    });
                } else {
                    // Fallback
                    const tempInput = $('<input>');
                    $('body').append(tempInput);
                    tempInput.val(phone).select();
                    document.execCommand('copy');
                    tempInput.remove();
                    showTempMessage('Phone number copied!');
                }
            });

            // Edit contact
            $(document).on('click', '.edit-contact', function(e) {
                e.preventDefault();
                const contactId = $(this).data('contact-id');
                // TODO: Implement edit contact modal
                console.log('Edit contact:', contactId);
            });

            // View contact history
            $(document).on('click', '.view-history', function(e) {
                e.preventDefault();
                const contactId = $(this).data('contact-id');
                // TODO: Implement contact history modal
                console.log('View history for contact:', contactId);
            });

            // Select all checkbox
            $('#cb-select-all').on('change', function() {
                $('input[name="contact[]"]').prop('checked', this.checked);
            });

            // Individual checkbox handling
            $(document).on('change', 'input[name="contact[]"]', function() {
                const totalCheckboxes = $('input[name="contact[]"]').length;
                const checkedCheckboxes = $('input[name="contact[]"]:checked').length;

                $('#cb-select-all').prop('indeterminate', checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
                $('#cb-select-all').prop('checked', checkedCheckboxes === totalCheckboxes);
            });

            // Filter contacts
            $('#post-query-submit, #search-contacts').on('click', function(e) {
                e.preventDefault();
                filterContacts();
            });

            // Search on Enter key
            $('#contact-search').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    filterContacts();
                }
            });

            // Bulk actions
            $('#doaction-bottom').on('click', function(e) {
                e.preventDefault();
                const action = $('#bulk-action-selector-bottom').val();
                const selectedContacts = $('input[name="contact[]"]:checked').map(function() {
                    return this.value;
                }).get();

                if (action === '-1') {
                    alert('Please select a bulk action.');
                    return;
                }

                if (selectedContacts.length === 0) {
                    alert('Please select at least one contact.');
                    return;
                }

                if (action === 'delete' && !confirm('Are you sure you want to delete the selected contacts?')) {
                    return;
                }

                executeBulkAction(action, selectedContacts);
            });
        }

        function filterContacts() {
            const optInFilter = $('#filter-opt-in').val();
            const sourceFilter = $('#filter-source').val();
            const searchTerm = $('#contact-search').val();

            // Build query string
            const params = new URLSearchParams(window.location.search);

            if (optInFilter) {
                params.set('opt_in', optInFilter);
            } else {
                params.delete('opt_in');
            }

            if (sourceFilter) {
                params.set('source', sourceFilter);
            } else {
                params.delete('source');
            }

            if (searchTerm) {
                params.set('search', searchTerm);
            } else {
                params.delete('search');
            }

            // Reset to page 1
            params.delete('paged');

            // Reload page with new parameters
            window.location.search = params.toString();
        }

        function executeBulkAction(action, contactIds) {
            $.post(chatshopWhatsAppAdmin.ajax_url, {
                    action: 'chatshop_bulk_contact_action',
                    nonce: chatshopWhatsAppAdmin.nonce,
                    bulk_action: action,
                    contact_ids: contactIds
                })
                .done(function(response) {
                    if (response.success) {
                        showNotice('Bulk action completed successfully.', 'success');
                        location.reload();
                    } else {
                        showNotice(response.data || 'Bulk action failed.', 'error');
                    }
                })
                .fail(function() {
                    showNotice('Bulk action failed.', 'error');
                });
        }

        function showNotice(message, type) {
            const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);

            setTimeout(function() {
                notice.fadeOut();
            }, 5000);
        }

        function showTempMessage(message) {
            const tempMsg = $('<div class="temp-message">' + message + '</div>');
            $('body').append(tempMsg);

            setTimeout(function() {
                tempMsg.fadeOut(function() {
                    tempMsg.remove();
                });
            }, 2000);
        }
    });
</script>

<style>
    .chatshop-contacts-stats {
        margin: 20px 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        text-align: center;
    }

    .stat-card .stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #2271b1;
        line-height: 1;
        margin-bottom: 8px;
    }

    .stat-card .stat-label {
        font-size: 14px;
        color: #646970;
        font-weight: 500;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-opted-in {
        background: #d4edda;
        color: #155724;
    }

    .status-opted-out {
        background: #f8d7da;
        color: #721c24;
    }

    .source-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .source-manual {
        background: #e3f2fd;
        color: #1565c0;
    }

    .source-checkout {
        background: #e8f5e8;
        color: #2e7d32;
    }

    .source-campaign {
        background: #fff3e0;
        color: #ef6c00;
    }

    .source-webhook {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .source-import {
        background: #fafafa;
        color: #424242;
    }

    .copy-phone {
        margin-left: 8px;
        padding: 2px;
        border: none;
        background: none;
        color: #646970;
        cursor: pointer;
    }

    .copy-phone:hover {
        color: #2271b1;
    }

    .temp-message {
        position: fixed;
        top: 50px;
        right: 20px;
        background: #00a32a;
        color: white;
        padding: 10px 15px;
        border-radius: 4px;
        z-index: 9999;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .required {
        color: #d63638;
    }

    @media screen and (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .table-actions {
            flex-direction: column;
            gap: 4px;
        }

        .table-actions .button {
            font-size: 11px;
            padding: 4px 8px;
        }
    }
</style>