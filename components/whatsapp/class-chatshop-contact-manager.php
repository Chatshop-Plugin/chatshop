<?php

/**
 * Contact Manager Component
 *
 * Handles contact management with freemium features including import/export,
 * contact limits, and comprehensive contact operations.
 *
 * @package ChatShop
 * @subpackage Components\WhatsApp
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Contact Manager Class
 *
 * Manages contacts with freemium limitations, import/export functionality,
 * and integration with WhatsApp messaging system.
 *
 * @since 1.0.0
 */
class ChatShop_Contact_Manager extends ChatShop_Abstract_Component
{
    /**
     * Component ID
     *
     * @var string
     * @since 1.0.0
     */
    protected $id = 'contact_manager';

    /**
     * Free plan contact limit per month
     *
     * @var int
     * @since 1.0.0
     */
    private $free_contact_limit = 20;

    /**
     * Database table name
     *
     * @var string
     * @since 1.0.0
     */
    private $table_name;

    /**
     * Import/Export handler
     *
     * @var ChatShop_Contact_Import_Export
     * @since 1.0.0
     */
    private $import_export;

    /**
     * Initialize component
     *
     * @since 1.0.0
     */
    protected function init()
    {
        global $wpdb;

        $this->name = __('Contact Manager', 'chatshop');
        $this->description = __('Manage WhatsApp contacts with import/export capabilities', 'chatshop');
        $this->version = '1.0.0';
        $this->table_name = $wpdb->prefix . 'chatshop_contacts';

        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        add_action('wp_ajax_chatshop_add_contact', array($this, 'ajax_add_contact'));
        add_action('wp_ajax_chatshop_update_contact', array($this, 'ajax_update_contact'));
        add_action('wp_ajax_chatshop_delete_contact', array($this, 'ajax_delete_contact'));
        add_action('wp_ajax_chatshop_bulk_delete_contacts', array($this, 'ajax_bulk_delete_contacts'));
        add_action('wp_ajax_chatshop_import_contacts', array($this, 'ajax_import_contacts'));
        add_action('wp_ajax_chatshop_export_contacts', array($this, 'ajax_export_contacts'));
        add_action('wp_ajax_chatshop_get_contact_stats', array($this, 'ajax_get_contact_stats'));
    }

    /**
     * Load component dependencies
     *
     * @since 1.0.0
     */
    private function load_dependencies()
    {
        require_once CHATSHOP_PLUGIN_DIR . 'components/whatsapp/class-chatshop-contact-import-export.php';

        if (class_exists('ChatShop\ChatShop_Contact_Import_Export')) {
            $this->import_export = new ChatShop_Contact_Import_Export($this);
        }
    }

    /**
     * Component activation
     *
     * @since 1.0.0
     * @return bool Activation result
     */
    protected function do_activation()
    {
        return $this->create_database_table();
    }

    /**
     * Component deactivation
     *
     * @since 1.0.0
     * @return bool Deactivation result
     */
    protected function do_deactivation()
    {
        // Keep data on deactivation, only remove on uninstall
        return true;
    }

    /**
     * Create database table
     *
     * @since 1.0.0
     * @return bool Creation result
     */
    private function create_database_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            name varchar(100) NOT NULL,
            email varchar(100) DEFAULT '',
            status enum('active','inactive','blocked') DEFAULT 'active',
            opt_in_status enum('opted_in','opted_out','pending') DEFAULT 'pending',
            tags text DEFAULT '',
            notes text DEFAULT '',
            last_contacted datetime DEFAULT NULL,
            contact_count int(11) DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY phone (phone),
            KEY email (email),
            KEY status (status),
            KEY opt_in_status (opt_in_status),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $result = dbDelta($sql);

        if ($result) {
            chatshop_log('Contact manager database table created successfully', 'info');
            return true;
        } else {
            chatshop_log('Failed to create contact manager database table', 'error');
            return false;
        }
    }

    /**
     * Add new contact
     *
     * @since 1.0.0
     * @param array $contact_data Contact information
     * @return array Result with contact ID or error
     */
    public function add_contact($contact_data)
    {
        // Validate required fields
        if (empty($contact_data['phone']) || empty($contact_data['name'])) {
            return array(
                'success' => false,
                'message' => __('Phone number and name are required.', 'chatshop')
            );
        }

        // Check free plan limits
        if (!$this->can_add_contact()) {
            return array(
                'success' => false,
                'message' => __('Contact limit reached. Upgrade to premium for unlimited contacts.', 'chatshop')
            );
        }

        // Sanitize data
        $phone = $this->sanitize_phone($contact_data['phone']);
        $name = sanitize_text_field($contact_data['name']);
        $email = sanitize_email($contact_data['email'] ?? '');
        $tags = sanitize_text_field($contact_data['tags'] ?? '');
        $notes = sanitize_textarea_field($contact_data['notes'] ?? '');
        $status = in_array($contact_data['status'] ?? 'active', array('active', 'inactive', 'blocked'))
            ? $contact_data['status'] : 'active';

        // Check if contact already exists
        if ($this->contact_exists($phone)) {
            return array(
                'success' => false,
                'message' => __('Contact with this phone number already exists.', 'chatshop')
            );
        }

        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'phone' => $phone,
                'name' => $name,
                'email' => $email,
                'status' => $status,
                'tags' => $tags,
                'notes' => $notes,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        if ($result) {
            $contact_id = $wpdb->insert_id;

            chatshop_log("Contact added successfully: {$name} ({$phone})", 'info', array(
                'contact_id' => $contact_id,
                'user_id' => get_current_user_id()
            ));

            return array(
                'success' => true,
                'contact_id' => $contact_id,
                'message' => __('Contact added successfully.', 'chatshop')
            );
        } else {
            chatshop_log("Failed to add contact: {$name} ({$phone})", 'error', array(
                'db_error' => $wpdb->last_error
            ));

            return array(
                'success' => false,
                'message' => __('Failed to add contact. Please try again.', 'chatshop')
            );
        }
    }

    /**
     * Update existing contact
     *
     * @since 1.0.0
     * @param int   $contact_id Contact ID
     * @param array $contact_data Updated contact information
     * @return array Result with success status
     */
    public function update_contact($contact_id, $contact_data)
    {
        $contact_id = absint($contact_id);

        if (!$contact_id || !$this->contact_exists_by_id($contact_id)) {
            return array(
                'success' => false,
                'message' => __('Contact not found.', 'chatshop')
            );
        }

        // Prepare update data
        $update_data = array();
        $format = array();

        if (!empty($contact_data['name'])) {
            $update_data['name'] = sanitize_text_field($contact_data['name']);
            $format[] = '%s';
        }

        if (isset($contact_data['email'])) {
            $update_data['email'] = sanitize_email($contact_data['email']);
            $format[] = '%s';
        }

        if (isset($contact_data['status']) && in_array($contact_data['status'], array('active', 'inactive', 'blocked'))) {
            $update_data['status'] = $contact_data['status'];
            $format[] = '%s';
        }

        if (isset($contact_data['opt_in_status']) && in_array($contact_data['opt_in_status'], array('opted_in', 'opted_out', 'pending'))) {
            $update_data['opt_in_status'] = $contact_data['opt_in_status'];
            $format[] = '%s';
        }

        if (isset($contact_data['tags'])) {
            $update_data['tags'] = sanitize_text_field($contact_data['tags']);
            $format[] = '%s';
        }

        if (isset($contact_data['notes'])) {
            $update_data['notes'] = sanitize_textarea_field($contact_data['notes']);
            $format[] = '%s';
        }

        if (empty($update_data)) {
            return array(
                'success' => false,
                'message' => __('No valid data provided for update.', 'chatshop')
            );
        }

        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $contact_id),
            $format,
            array('%d')
        );

        if ($result !== false) {
            chatshop_log("Contact updated successfully: ID {$contact_id}", 'info', array(
                'contact_id' => $contact_id,
                'updated_fields' => array_keys($update_data)
            ));

            return array(
                'success' => true,
                'message' => __('Contact updated successfully.', 'chatshop')
            );
        } else {
            chatshop_log("Failed to update contact: ID {$contact_id}", 'error', array(
                'contact_id' => $contact_id,
                'db_error' => $wpdb->last_error
            ));

            return array(
                'success' => false,
                'message' => __('Failed to update contact. Please try again.', 'chatshop')
            );
        }
    }

    /**
     * Delete contact
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @return array Result with success status
     */
    public function delete_contact($contact_id)
    {
        $contact_id = absint($contact_id);

        if (!$contact_id || !$this->contact_exists_by_id($contact_id)) {
            return array(
                'success' => false,
                'message' => __('Contact not found.', 'chatshop')
            );
        }

        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $contact_id),
            array('%d')
        );

        if ($result) {
            chatshop_log("Contact deleted successfully: ID {$contact_id}", 'info', array(
                'contact_id' => $contact_id,
                'user_id' => get_current_user_id()
            ));

            return array(
                'success' => true,
                'message' => __('Contact deleted successfully.', 'chatshop')
            );
        } else {
            chatshop_log("Failed to delete contact: ID {$contact_id}", 'error', array(
                'contact_id' => $contact_id,
                'db_error' => $wpdb->last_error
            ));

            return array(
                'success' => false,
                'message' => __('Failed to delete contact. Please try again.', 'chatshop')
            );
        }
    }

    /**
     * Get contact by ID
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @return array|null Contact data or null if not found
     */
    public function get_contact($contact_id)
    {
        global $wpdb;

        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            absint($contact_id)
        ), ARRAY_A);

        return $contact ?: null;
    }

    /**
     * Get contact by phone number
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return array|null Contact data or null if not found
     */
    public function get_contact_by_phone($phone)
    {
        global $wpdb;

        $phone = $this->sanitize_phone($phone);

        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE phone = %s",
            $phone
        ), ARRAY_A);

        return $contact ?: null;
    }

    /**
     * Get contacts with pagination and filtering
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Contacts data with pagination info
     */
    public function get_contacts($args = array())
    {
        global $wpdb;

        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'status' => '',
            'opt_in_status' => '',
            'search' => '',
            'tags' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where_conditions = array('1=1');
        $where_values = array();

        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }

        if (!empty($args['opt_in_status'])) {
            $where_conditions[] = "opt_in_status = %s";
            $where_values[] = $args['opt_in_status'];
        }

        if (!empty($args['search'])) {
            $where_conditions[] = "(name LIKE %s OR phone LIKE %s OR email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        if (!empty($args['tags'])) {
            $where_conditions[] = "tags LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($args['tags']) . '%';
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Build ORDER BY clause
        $allowed_orderby = array('id', 'name', 'phone', 'status', 'created_at', 'updated_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        $total_contacts = $wpdb->get_var($wpdb->prepare($count_sql, $where_values));

        // Get contacts
        $contacts_sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} 
                        ORDER BY {$orderby} {$order} 
                        LIMIT %d OFFSET %d";

        $where_values[] = absint($args['limit']);
        $where_values[] = absint($args['offset']);

        $contacts = $wpdb->get_results($wpdb->prepare($contacts_sql, $where_values), ARRAY_A);

        return array(
            'contacts' => $contacts ?: array(),
            'total' => (int) $total_contacts,
            'limit' => absint($args['limit']),
            'offset' => absint($args['offset']),
            'pages' => ceil($total_contacts / $args['limit'])
        );
    }

    /**
     * Get contact statistics
     *
     * @since 1.0.0
     * @return array Contact statistics
     */
    public function get_contact_stats()
    {
        global $wpdb;

        $stats = array();

        // Total contacts
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        // Active contacts
        $stats['active'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
            'active'
        ));

        // Opted in contacts
        $stats['opted_in'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE opt_in_status = %s",
            'opted_in'
        ));

        // Contacts added this month
        $stats['this_month'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d",
            date('Y'),
            date('n')
        ));

        // Free plan usage
        $stats['monthly_limit'] = $this->free_contact_limit;
        $stats['monthly_usage'] = $stats['this_month'];
        $stats['can_add_more'] = $this->can_add_contact();
        $stats['is_premium'] = chatshop_is_premium_feature_available('unlimited_contacts');

        return $stats;
    }

    /**
     * Update opt-in status for contact
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @param bool   $opted_in Opt-in status
     * @return bool Update result
     */
    public function update_opt_in_status($phone, $opted_in)
    {
        global $wpdb;

        $phone = $this->sanitize_phone($phone);
        $opt_in_status = $opted_in ? 'opted_in' : 'opted_out';

        $result = $wpdb->update(
            $this->table_name,
            array(
                'opt_in_status' => $opt_in_status,
                'updated_at' => current_time('mysql')
            ),
            array('phone' => $phone),
            array('%s', '%s'),
            array('%s')
        );

        if ($result !== false) {
            chatshop_log("Contact opt-in status updated: {$phone} -> {$opt_in_status}", 'info');
            return true;
        }

        return false;
    }

    /**
     * Update last contacted timestamp
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return bool Update result
     */
    public function update_last_contacted($phone)
    {
        global $wpdb;

        $phone = $this->sanitize_phone($phone);

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET last_contacted = %s, 
                 contact_count = contact_count + 1,
                 updated_at = %s 
             WHERE phone = %s",
            current_time('mysql'),
            current_time('mysql'),
            $phone
        ));

        return $result !== false;
    }

    /**
     * Check if contact can be added (free plan limit)
     *
     * @since 1.0.0
     * @return bool Whether contact can be added
     */
    public function can_add_contact()
    {
        // Premium users have unlimited contacts
        if (chatshop_is_premium_feature_available('unlimited_contacts')) {
            return true;
        }

        // Check monthly limit for free users
        $monthly_count = $this->get_monthly_contact_count();

        return $monthly_count < $this->free_contact_limit;
    }

    /**
     * Get monthly contact count
     *
     * @since 1.0.0
     * @return int Number of contacts added this month
     */
    public function get_monthly_contact_count()
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d",
            date('Y'),
            date('n')
        ));

        return (int) $count;
    }

    /**
     * Check if contact exists by phone
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return bool Whether contact exists
     */
    private function contact_exists($phone)
    {
        global $wpdb;

        $phone = $this->sanitize_phone($phone);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE phone = %s",
            $phone
        ));

        return (int) $exists > 0;
    }

    /**
     * Check if contact exists by ID
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @return bool Whether contact exists
     */
    private function contact_exists_by_id($contact_id)
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d",
            absint($contact_id)
        ));

        return (int) $exists > 0;
    }

    /**
     * Sanitize phone number
     *
     * @since 1.0.0
     * @param string $phone Raw phone number
     * @return string Sanitized phone number
     */
    private function sanitize_phone($phone)
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure phone starts with + for international format
        if (!empty($phone) && substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * AJAX handler for adding contact
     *
     * @since 1.0.0
     */
    public function ajax_add_contact()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        $contact_data = array(
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'tags' => sanitize_text_field($_POST['tags'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? 'active')
        );

        $result = $this->add_contact($contact_data);

        wp_send_json($result);
    }

    /**
     * AJAX handler for updating contact
     *
     * @since 1.0.0
     */
    public function ajax_update_contact()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        $contact_id = absint($_POST['contact_id'] ?? 0);
        $contact_data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'tags' => sanitize_text_field($_POST['tags'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? '')
        );

        $result = $this->update_contact($contact_id, $contact_data);

        wp_send_json($result);
    }

    /**
     * AJAX handler for deleting contact
     *
     * @since 1.0.0
     */
    public function ajax_delete_contact()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        $contact_id = absint($_POST['contact_id'] ?? 0);
        $result = $this->delete_contact($contact_id);

        wp_send_json($result);
    }

    /**
     * AJAX handler for bulk delete contacts
     *
     * @since 1.0.0
     */
    public function ajax_bulk_delete_contacts()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        $contact_ids = array_map('absint', $_POST['contact_ids'] ?? array());

        if (empty($contact_ids)) {
            wp_send_json(array(
                'success' => false,
                'message' => __('No contacts selected.', 'chatshop')
            ));
        }

        $deleted_count = 0;
        $errors = array();

        foreach ($contact_ids as $contact_id) {
            $result = $this->delete_contact($contact_id);
            if ($result['success']) {
                $deleted_count++;
            } else {
                $errors[] = $result['message'];
            }
        }

        wp_send_json(array(
            'success' => true,
            'message' => sprintf(
                __('%d contact(s) deleted successfully.', 'chatshop'),
                $deleted_count
            ),
            'deleted_count' => $deleted_count,
            'errors' => $errors
        ));
    }

    /**
     * AJAX handler for importing contacts
     *
     * @since 1.0.0
     */
    public function ajax_import_contacts()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        if (!chatshop_is_premium_feature_available('contact_import_export')) {
            wp_send_json(array(
                'success' => false,
                'message' => __('Contact import/export is a premium feature.', 'chatshop')
            ));
        }

        if (!$this->import_export) {
            wp_send_json(array(
                'success' => false,
                'message' => __('Import/export functionality not available.', 'chatshop')
            ));
        }

        $result = $this->import_export->handle_import();
        wp_send_json($result);
    }

    /**
     * AJAX handler for exporting contacts
     *
     * @since 1.0.0
     */
    public function ajax_export_contacts()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        if (!chatshop_is_premium_feature_available('contact_import_export')) {
            wp_send_json(array(
                'success' => false,
                'message' => __('Contact import/export is a premium feature.', 'chatshop')
            ));
        }

        if (!$this->import_export) {
            wp_send_json(array(
                'success' => false,
                'message' => __('Import/export functionality not available.', 'chatshop')
            ));
        }

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $result = $this->import_export->handle_export($format);
        wp_send_json($result);
    }

    /**
     * AJAX handler for getting contact statistics
     *
     * @since 1.0.0
     */
    public function ajax_get_contact_stats()
    {
        check_ajax_referer('chatshop_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'chatshop'));
        }

        $stats = $this->get_contact_stats();
        wp_send_json(array(
            'success' => true,
            'stats' => $stats
        ));
    }

    /**
     * Get table name
     *
     * @since 1.0.0
     * @return string Table name
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Get free contact limit
     *
     * @since 1.0.0
     * @return int Free contact limit
     */
    public function get_free_contact_limit()
    {
        return $this->free_contact_limit;
    }
}
