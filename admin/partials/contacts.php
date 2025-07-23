<?php

/**
 * Admin Contacts Management Page
 *
 * File: admin/partials/contacts.php
 * 
 * Provides comprehensive contact management interface with import/export,
 * bulk operations, and freemium feature toggles.
 *
 * @package ChatShop
 * @subpackage Admin\Partials
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get contact manager instance
$contact_manager = chatshop_get_component('contact_manager');
if (!$contact_manager) {
    echo '<div class="wrap"><h1>' . __('Contact Management', 'chatshop') . '</h1>';
    echo '<div class="notice notice-error"><p>' . __('Contact manager component is not available.', 'chatshop') . '</p></div></div>';
    return;
}

// Get contact statistics
$stats = $contact_manager->get_contact_stats();

// Check premium status
$is_premium = chatshop_is_premium_feature_available('unlimited_contacts');
$can_import_export = chatshop_is_premium_feature_available('contact_import_export');

// Get contacts for display
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($current_page - 1) * $per_page;

$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$opt_in_filter = isset($_GET['opt_in']) ? sanitize_text_field($_GET['opt_in']) : '';

$contacts_data = $contact_manager->get_contacts(array(
    'limit' => $per_page,
    'offset' => $offset,
    'search' => $search,
    'status' => $status_filter,
    'opt_in_status' => $opt_in_filter,
    'orderby' => 'created_at',
    'order' => 'DESC'
));

$total_pages = $contacts_data['pages'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Contact Management', 'chatshop'); ?></h1>

    <?php if (!$is_premium): ?>
        <a href="#" class="page-title-action chatshop-premium-feature" data-feature="unlimited_contacts">
            <?php _e('Upgrade to Premium', 'chatshop'); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Contact Statistics -->
    <div class="chatshop-contact-stats">
        <div class="chatshop-stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($stats['total']); ?></div>
                <div class="stat-label"><?php _e('Total Contacts', 'chatshop'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($stats['active']); ?></div>
                <div class="stat-label"><?php _e('Active Contacts', 'chatshop'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($stats['opted_in']); ?></div>
                <div class="stat-label"><?php _e('Opted In', 'chatshop'); ?></div>
            </div>
            <div class="stat-card <?php echo !$is_premium ? 'stat-warning' : ''; ?>">
                <div class="stat-number">
                    <?php if ($is_premium): ?>
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e('Unlimited', 'chatshop'); ?>
                    <?php else: ?>
                        <?php echo esc_html($stats['monthly_usage']); ?>/<?php echo esc_html($stats['monthly_limit']); ?>
                    <?php endif; ?>
                </div>
                <div class="stat-label"><?php _e('Monthly Limit', 'chatshop'); ?></div>
                <?php if (!$is_premium && $stats['monthly_usage'] >= $stats['monthly_limit'] * 0.8): ?>
                    <div class="stat-warning-text"><?php _e('Approaching limit', 'chatshop'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="chatshop-contact-actions">
        <button type="button" class="button button-primary" id="chatshop-add-contact">
            <span class="dashicons dashicons-plus"></span> <?php _e('Add Contact', 'chatshop'); ?>
        </button>

        <?php if ($can_import_export): ?>
            <button type="button" class="button" id="chatshop-import-contacts">
                <span class="dashicons dashicons-upload"></span> <?php _e('Import Contacts', 'chatshop'); ?>
            </button>
            <button type="button" class="button" id="chatshop-export-contacts">
                <span class="dashicons dashicons-download"></span> <?php _e('Export Contacts', 'chatshop'); ?>
            </button>
            <a href="#" class="button" id="chatshop-download-template">
                <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e('Download Template', 'chatshop'); ?>
            </a>
        <?php else: ?>
            <button type="button" class="button chatshop-premium-feature" data-feature="contact_import_export">
                <span class="dashicons dashicons-upload"></span> <?php _e('Import Contacts', 'chatshop'); ?> <span class="dashicons dashicons-lock"></span>
            </button>
            <button type="button" class="button chatshop-premium-feature" data-feature="contact_import_export">
                <span class="dashicons dashicons-download"></span> <?php _e('Export Contacts', 'chatshop'); ?> <span class="dashicons dashicons-lock"></span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="chatshop-contact-filters">
        <form method="get" class="chatshop-filter-form">
            <input type="hidden" name="page" value="chatshop-contacts">

            <input type="search"
                name="search"
                value="<?php echo esc_attr($search); ?>"
                placeholder="<?php esc_attr_e('Search contacts...', 'chatshop'); ?>"
                class="chatshop-search-input">

            <select name="status" class="chatshop-filter-select">
                <option value=""><?php _e('All Statuses', 'chatshop'); ?></option>
                <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('Active', 'chatshop'); ?></option>
                <option value="inactive" <?php selected($status_filter, 'inactive'); ?>><?php _e('Inactive', 'chatshop'); ?></option>
                <option value="blocked" <?php selected($status_filter, 'blocked'); ?>><?php _e('Blocked', 'chatshop'); ?></option>
            </select>

            <select name="opt_in" class="chatshop-filter-select">
                <option value=""><?php _e('All Opt-in Status', 'chatshop'); ?></option>
                <option value="opted_in" <?php selected($opt_in_filter, 'opted_in'); ?>><?php _e('Opted In', 'chatshop'); ?></option>
                <option value="opted_out" <?php selected($opt_in_filter, 'opted_out'); ?>><?php _e('Opted Out', 'chatshop'); ?></option>
                <option value="pending" <?php selected($opt_in_filter, 'pending'); ?>><?php _e('Pending', 'chatshop'); ?></option>
            </select>

            <button type="submit" class="button"><?php _e('Filter', 'chatshop'); ?></button>

            <?php if ($search || $status_filter || $opt_in_filter): ?>
                <a href="<?php echo admin_url('admin.php?page=chatshop-contacts'); ?>" class="button">
                    <?php _e('Clear Filters', 'chatshop'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Contacts Table -->
    <div class="chatshop-contacts-table-container">
        <?php if (!empty($contacts_data['contacts'])): ?>
            <form id="chatshop-contacts-form">
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="chatshop-bulk-action">
                            <option value=""><?php _e('Bulk Actions', 'chatshop'); ?></option>
                            <option value="delete"><?php _e('Delete', 'chatshop'); ?></option>
                            <option value="activate"><?php _e('Activate', 'chatshop'); ?></option>
                            <option value="deactivate"><?php _e('Deactivate', 'chatshop'); ?></option>
                        </select>
                        <button type="button" class="button" id="chatshop-apply-bulk-action">
                            <?php _e('Apply', 'chatshop'); ?>
                        </button>
                    </div>

                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(__('%s items', 'chatshop'), number_format_i18n($contacts_data['total'])); ?>
                        </span>

                        <?php if ($total_pages > 1): ?>
                            <span class="pagination-links">
                                <?php
                                $page_links = paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => __('&laquo; Previous'),
                                    'next_text' => __('Next &raquo;'),
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'type' => 'plain'
                                ));
                                echo $page_links;
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped contacts">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="chatshop-select-all-contacts">
                            </td>
                            <th class="manage-column column-name"><?php _e('Name', 'chatshop'); ?></th>
                            <th class="manage-column column-phone"><?php _e('Phone', 'chatshop'); ?></th>
                            <th class="manage-column column-email"><?php _e('Email', 'chatshop'); ?></th>
                            <th class="manage-column column-status"><?php _e('Status', 'chatshop'); ?></th>
                            <th class="manage-column column-opt-in"><?php _e('Opt-in Status', 'chatshop'); ?></th>
                            <th class="manage-column column-tags"><?php _e('Tags', 'chatshop'); ?></th>
                            <th class="manage-column column-created"><?php _e('Created', 'chatshop'); ?></th>
                            <th class="manage-column column-actions"><?php _e('Actions', 'chatshop'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts_data['contacts'] as $contact): ?>
                            <tr data-contact-id="<?php echo esc_attr($contact['id']); ?>">
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="contact_ids[]" value="<?php echo esc_attr($contact['id']); ?>" class="chatshop-contact-checkbox">
                                </th>
                                <td class="column-name">
                                    <strong><?php echo esc_html($contact['name']); ?></strong>
                                    <?php if (!empty($contact['notes'])): ?>
                                        <br><small class="description"><?php echo esc_html(wp_trim_words($contact['notes'], 10)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-phone">
                                    <code><?php echo esc_html($contact['phone']); ?></code>
                                    <?php if ($contact['contact_count'] > 0): ?>
                                        <br><small class="description">
                                            <?php printf(__('Contacted %d times', 'chatshop'), $contact['contact_count']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-email">
                                    <?php if (!empty($contact['email'])): ?>
                                        <a href="mailto:<?php echo esc_attr($contact['email']); ?>">
                                            <?php echo esc_html($contact['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="description"><?php _e('No email', 'chatshop'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <span class="chatshop-status-badge status-<?php echo esc_attr($contact['status']); ?>">
                                        <?php echo esc_html(ucfirst($contact['status'])); ?>
                                    </span>
                                </td>
                                <td class="column-opt-in">
                                    <span class="chatshop-opt-in-badge opt-in-<?php echo esc_attr($contact['opt_in_status']); ?>">
                                        <?php
                                        switch ($contact['opt_in_status']) {
                                            case 'opted_in':
                                                echo '<span class="dashicons dashicons-yes"></span> ' . __('Opted In', 'chatshop');
                                                break;
                                            case 'opted_out':
                                                echo '<span class="dashicons dashicons-no"></span> ' . __('Opted Out', 'chatshop');
                                                break;
                                            default:
                                                echo '<span class="dashicons dashicons-clock"></span> ' . __('Pending', 'chatshop');
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="column-tags">
                                    <?php if (!empty($contact['tags'])): ?>
                                        <?php
                                        $tags = explode(',', $contact['tags']);
                                        foreach ($tags as $tag) {
                                            $tag = trim($tag);
                                            if (!empty($tag)) {
                                                echo '<span class="chatshop-tag">' . esc_html($tag) . '</span> ';
                                            }
                                        }
                                        ?>
                                    <?php else: ?>
                                        <span class="description"><?php _e('No tags', 'chatshop'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-created">
                                    <abbr title="<?php echo esc_attr($contact['created_at']); ?>">
                                        <?php echo esc_html(human_time_diff(strtotime($contact['created_at']), current_time('timestamp')) . ' ago'); ?>
                                    </abbr>
                                    <?php if (!empty($contact['last_contacted'])): ?>
                                        <br><small class="description">
                                            <?php _e('Last contacted:', 'chatshop'); ?>
                                            <?php echo esc_html(human_time_diff(strtotime($contact['last_contacted']), current_time('timestamp')) . ' ago'); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="#" class="chatshop-edit-contact" data-contact-id="<?php echo esc_attr($contact['id']); ?>">
                                                <?php _e('Edit', 'chatshop'); ?>
                                            </a>
                                        </span>
                                        <span class="delete">
                                            | <a href="#" class="chatshop-delete-contact" data-contact-id="<?php echo esc_attr($contact['id']); ?>">
                                                <?php _e('Delete', 'chatshop'); ?>
                                            </a>
                                        </span>
                                        <?php if ($contact['opt_in_status'] === 'opted_in'): ?>
                                            <span class="message">
                                                | <a href="#" class="chatshop-message-contact" data-contact-id="<?php echo esc_attr($contact['id']); ?>">
                                                    <?php _e('Message', 'chatshop'); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php if ($total_pages > 1): ?>
                            <span class="pagination-links">
                                <?php echo $page_links; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="chatshop-no-contacts">
                <div class="no-contacts-message">
                    <span class="dashicons dashicons-groups"></span>
                    <h3><?php _e('No contacts found', 'chatshop'); ?></h3>
                    <?php if ($search || $status_filter || $opt_in_filter): ?>
                        <p><?php _e('No contacts match your current filters.', 'chatshop'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=chatshop-contacts'); ?>" class="button">
                            <?php _e('Clear Filters', 'chatshop'); ?>
                        </a>
                    <?php else: ?>
                        <p><?php _e('Start building your contact list by adding contacts manually or importing from a file.', 'chatshop'); ?></p>
                        <button type="button" class="button button-primary" id="chatshop-add-first-contact">
                            <?php _e('Add Your First Contact', 'chatshop'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Contact Modal -->
<div id="chatshop-contact-modal" class="chatshop-modal" style="display: none;">
    <div class="chatshop-modal-content">
        <div class="chatshop-modal-header">
            <h2 id="chatshop-contact-modal-title"><?php _e('Add Contact', 'chatshop'); ?></h2>
            <button type="button" class="chatshop-modal-close">&times;</button>
        </div>
        <div class="chatshop-modal-body">
            <form id="chatshop-contact-form">
                <input type="hidden" id="contact-id" name="contact_id" value="">

                <div class="chatshop-form-row">
                    <div class="chatshop-form-field">
                        <label for="contact-phone"><?php _e('Phone Number', 'chatshop'); ?> <span class="required">*</span></label>
                        <input type="tel" id="contact-phone" name="phone" required
                            placeholder="<?php esc_attr_e('+1234567890', 'chatshop'); ?>"
                            pattern="^\+?[1-9]\d{1,14}$">
                        <p class="description"><?php _e('Include country code (e.g., +1234567890)', 'chatshop'); ?></p>
                    </div>
                    <div class="chatshop-form-field">
                        <label for="contact-name"><?php _e('Name', 'chatshop'); ?> <span class="required">*</span></label>
                        <input type="text" id="contact-name" name="name" required>
                    </div>
                </div>

                <div class="chatshop-form-row">
                    <div class="chatshop-form-field">
                        <label for="contact-email"><?php _e('Email', 'chatshop'); ?></label>
                        <input type="email" id="contact-email" name="email">
                    </div>
                    <div class="chatshop-form-field">
                        <label for="contact-status"><?php _e('Status', 'chatshop'); ?></label>
                        <select id="contact-status" name="status">
                            <option value="active"><?php _e('Active', 'chatshop'); ?></option>
                            <option value="inactive"><?php _e('Inactive', 'chatshop'); ?></option>
                            <option value="blocked"><?php _e('Blocked', 'chatshop'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="chatshop-form-field">
                    <label for="contact-tags"><?php _e('Tags', 'chatshop'); ?></label>
                    <input type="text" id="contact-tags" name="tags"
                        placeholder="<?php esc_attr_e('customer, vip, prospect (comma-separated)', 'chatshop'); ?>">
                    <p class="description"><?php _e('Separate multiple tags with commas', 'chatshop'); ?></p>
                </div>

                <div class="chatshop-form-field">
                    <label for="contact-notes"><?php _e('Notes', 'chatshop'); ?></label>
                    <textarea id="contact-notes" name="notes" rows="3"
                        placeholder="<?php esc_attr_e('Additional notes about this contact...', 'chatshop'); ?>"></textarea>
                </div>
            </form>
        </div>
        <div class="chatshop-modal-footer">
            <button type="button" class="button" id="chatshop-cancel-contact"><?php _e('Cancel', 'chatshop'); ?></button>
            <button type="button" class="button button-primary" id="chatshop-save-contact">
                <?php _e('Save Contact', 'chatshop'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Import Contacts Modal -->
<?php if ($can_import_export): ?>
    <div id="chatshop-import-modal" class="chatshop-modal" style="display: none;">
        <div class="chatshop-modal-content">
            <div class="chatshop-modal-header">
                <h2><?php _e('Import Contacts', 'chatshop'); ?></h2>
                <button type="button" class="chatshop-modal-close">&times;</button>
            </div>
            <div class="chatshop-modal-body">
                <div class="chatshop-import-instructions">
                    <h4><?php _e('Import Instructions', 'chatshop'); ?></h4>
                    <ol>
                        <li><?php _e('Download the CSV template below', 'chatshop'); ?></li>
                        <li><?php _e('Add your contacts to the template file', 'chatshop'); ?></li>
                        <li><?php _e('Upload the completed file using the form below', 'chatshop'); ?></li>
                    </ol>

                    <p><strong><?php _e('Required columns:', 'chatshop'); ?></strong> phone, name</p>
                    <p><strong><?php _e('Optional columns:', 'chatshop'); ?></strong> email, tags, notes, status</p>

                    <a href="#" id="chatshop-download-template-link" class="button">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php _e('Download CSV Template', 'chatshop'); ?>
                    </a>
                </div>

                <form id="chatshop-import-form" enctype="multipart/form-data">
                    <div class="chatshop-form-field">
                        <label for="import-file"><?php _e('Select File', 'chatshop'); ?></label>
                        <input type="file" id="import-file" name="import_file" accept=".csv,.xlsx,.xls" required>
                        <p class="description"><?php _e('Supported formats: CSV, Excel (.xlsx, .xls). Maximum file size: 5MB', 'chatshop'); ?></p>
                    </div>
                </form>

                <div id="chatshop-import-progress" style="display: none;">
                    <div class="chatshop-progress-bar">
                        <div class="chatshop-progress-fill"></div>
                    </div>
                    <p class="chatshop-progress-text"><?php _e('Processing import...', 'chatshop'); ?></p>
                </div>

                <div id="chatshop-import-results" style="display: none;">
                    <!-- Import results will be displayed here -->
                </div>
            </div>
            <div class="chatshop-modal-footer">
                <button type="button" class="button" id="chatshop-cancel-import"><?php _e('Cancel', 'chatshop'); ?></button>
                <button type="button" class="button button-primary" id="chatshop-start-import">
                    <?php _e('Import Contacts', 'chatshop'); ?>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Premium Feature Modal -->
<div id="chatshop-premium-modal" class="chatshop-modal" style="display: none;">
    <div class="chatshop-modal-content">
        <div class="chatshop-modal-header">
            <h2><?php _e('Premium Feature', 'chatshop'); ?></h2>
            <button type="button" class="chatshop-modal-close">&times;</button>
        </div>
        <div class="chatshop-modal-body">
            <div class="chatshop-premium-feature-info">
                <span class="dashicons dashicons-lock chatshop-premium-icon"></span>
                <h3 id="chatshop-premium-feature-title"><?php _e('Unlock Premium Features', 'chatshop'); ?></h3>
                <p id="chatshop-premium-feature-description">
                    <?php _e('This feature is available in the premium version of ChatShop.', 'chatshop'); ?>
                </p>
                <div class="chatshop-premium-benefits">
                    <h4><?php _e('Premium Benefits:', 'chatshop'); ?></h4>
                    <ul>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Unlimited contacts', 'chatshop'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Import/Export contacts', 'chatshop'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Bulk messaging', 'chatshop'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Advanced analytics', 'chatshop'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php _e('Priority support', 'chatshop'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="chatshop-modal-footer">
            <button type="button" class="button" id="chatshop-close-premium-modal"><?php _e('Close', 'chatshop'); ?></button>
            <a href="#" class="button button-primary" id="chatshop-upgrade-to-premium">
                <?php _e('Upgrade to Premium', 'chatshop'); ?>
            </a>
        </div>
    </div>
</div>

<style>
    /* Contact Management Styles */
    .chatshop-contact-stats {
        margin: 20px 0;
    }

    .chatshop-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        text-align: center;
        transition: box-shadow 0.2s;
    }

    .stat-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .stat-card.stat-warning {
        border-color: #f56565;
        background: #fff5f5;
    }

    .stat-number {
        font-size: 32px;
        font-weight: bold;
        margin-bottom: 8px;
        color: #2c3e50;
    }

    .stat-warning .stat-number {
        color: #e53e3e;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-warning-text {
        font-size: 12px;
        color: #e53e3e;
        margin-top: 4px;
    }

    .chatshop-contact-actions {
        margin: 20px 0;
    }

    .chatshop-contact-actions .button {
        margin-right: 10px;
    }

    .chatshop-premium-feature {
        position: relative;
        opacity: 0.7;
    }

    .chatshop-premium-feature .dashicons-lock {
        font-size: 12px;
        vertical-align: text-top;
    }

    .chatshop-contact-filters {
        background: #f9f9f9;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .chatshop-filter-form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .chatshop-search-input {
        min-width: 200px;
    }

    .chatshop-filter-select {
        min-width: 150px;
    }

    .chatshop-status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .status-active {
        background: #d4edda;
        color: #155724;
    }

    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .status-blocked {
        background: #f5c6cb;
        color: #856404;
    }

    .chatshop-opt-in-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
    }

    .opt-in-opted_in {
        color: #155724;
    }

    .opt-in-opted_out {
        color: #721c24;
    }

    .opt-in-pending {
        color: #856404;
    }

    .chatshop-tag {
        display: inline-block;
        background: #e3f2fd;
        color: #1565c0;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        margin-right: 4px;
        margin-bottom: 2px;
    }

    .chatshop-no-contacts {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }

    .no-contacts-message .dashicons {
        font-size: 48px;
        color: #ddd;
        margin-bottom: 20px;
    }

    /* Modal Styles */
    .chatshop-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chatshop-modal-content {
        background: #fff;
        border-radius: 4px;
        max-width: 600px;
        width: 90%;
        max-height: 90%;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .chatshop-modal-header {
        padding: 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chatshop-modal-header h2 {
        margin: 0;
    }

    .chatshop-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chatshop-modal-body {
        padding: 20px;
    }

    .chatshop-modal-footer {
        padding: 20px;
        border-top: 1px solid #ddd;
        text-align: right;
    }

    .chatshop-modal-footer .button {
        margin-left: 10px;
    }

    .chatshop-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .chatshop-form-field {
        margin-bottom: 20px;
    }

    .chatshop-form-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .chatshop-form-field .required {
        color: #e53e3e;
    }

    .chatshop-form-field input,
    .chatshop-form-field select,
    .chatshop-form-field textarea {
        width: 100%;
        max-width: 100%;
    }

    .chatshop-import-instructions {
        background: #f0f8ff;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .chatshop-import-instructions h4 {
        margin-top: 0;
    }

    .chatshop-progress-bar {
        width: 100%;
        height: 20px;
        background: #f0f0f0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    .chatshop-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #4CAF50, #45a049);
        width: 0%;
        transition: width 0.3s ease;
        animation: progress-pulse 2s infinite;
    }

    @keyframes progress-pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.7;
        }
    }

    .chatshop-premium-feature-info {
        text-align: center;
        padding: 20px;
    }

    .chatshop-premium-icon {
        font-size: 48px;
        color: #f39c12;
        margin-bottom: 20px;
    }

    .chatshop-premium-benefits ul {
        text-align: left;
        max-width: 300px;
        margin: 0 auto;
    }

    .chatshop-premium-benefits li {
        margin-bottom: 8px;
    }

    .chatshop-premium-benefits .dashicons-yes {
        color: #27ae60;
    }

    @media (max-width: 768px) {
        .chatshop-stats-grid {
            grid-template-columns: 1fr;
        }

        .chatshop-form-row {
            grid-template-columns: 1fr;
        }

        .chatshop-filter-form {
            flex-direction: column;
            align-items: stretch;
        }

        .chatshop-search-input,
        .chatshop-filter-select {
            min-width: auto;
            width: 100%;
        }
    }
</style>