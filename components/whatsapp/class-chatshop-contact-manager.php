<?php

/**
 * ChatShop Contact Manager Component
 *
 * File: components/whatsapp/class-chatshop-contact-manager.php
 * 
 * Manages WhatsApp contacts with import/export capabilities,
 * segmentation, and integration with WhatsApp Business API.
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
 * Handles contact management functionality including CRUD operations,
 * import/export, segmentation, and WhatsApp integration.
 *
 * @since 1.0.0
 */
class ChatShop_Contact_Manager extends ChatShop_Abstract_Component
{
    /**
     * Component identifier
     *
     * @var string
     * @since 1.0.0
     */
    protected $id = 'contact_manager';

    /**
     * Component name
     *
     * @var string
     * @since 1.0.0
     */
    protected $name = 'Contact Management';

    /**
     * Component description
     *
     * @var string
     * @since 1.0.0
     */
    protected $description = 'Manage WhatsApp contacts with import/export capabilities';

    /**
     * Database table names
     *
     * @var array
     * @since 1.0.0
     */
    private $tables = array();

    /**
     * Contact statuses
     *
     * @var array
     * @since 1.0.0
     */
    private $contact_statuses = array(
        'active' => 'Active',
        'inactive' => 'Inactive',
        'blocked' => 'Blocked',
        'unsubscribed' => 'Unsubscribed'
    );

    /**
     * Maximum contacts for free version
     *
     * @var int
     * @since 1.0.0
     */
    private $free_contact_limit = 100;

    /**
     * Initialize component
     *
     * @since 1.0.0
     */
    protected function init()
    {
        $this->setup_database_tables();
        $this->init_hooks();

        chatshop_log('Contact Manager component initialized', 'info');
    }

    /**
     * Setup database table names
     *
     * @since 1.0.0
     */
    private function setup_database_tables()
    {
        global $wpdb;

        $this->tables = array(
            'contacts' => $wpdb->prefix . 'chatshop_contacts',
            'contact_groups' => $wpdb->prefix . 'chatshop_contact_groups',
            'contact_group_relations' => $wpdb->prefix . 'chatshop_contact_group_relations',
            'contact_interactions' => $wpdb->prefix . 'chatshop_contact_interactions'
        );
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // AJAX handlers
        add_action('wp_ajax_chatshop_add_contact', array($this, 'ajax_add_contact'));
        add_action('wp_ajax_chatshop_update_contact', array($this, 'ajax_update_contact'));
        add_action('wp_ajax_chatshop_delete_contact', array($this, 'ajax_delete_contact'));
        add_action('wp_ajax_chatshop_bulk_delete_contacts', array($this, 'ajax_bulk_delete_contacts'));
        add_action('wp_ajax_chatshop_import_contacts', array($this, 'ajax_import_contacts'));
        add_action('wp_ajax_chatshop_export_contacts', array($this, 'ajax_export_contacts'));
        add_action('wp_ajax_chatshop_get_contact_stats', array($this, 'ajax_get_contact_stats'));
        add_action('wp_ajax_chatshop_search_contacts', array($this, 'ajax_search_contacts'));

        // WhatsApp integration hooks
        add_action('chatshop_whatsapp_message_received', array($this, 'handle_incoming_message'), 10, 2);
        add_action('chatshop_whatsapp_status_update', array($this, 'handle_status_update'), 10, 2);

        // Scheduled tasks
        add_action('chatshop_daily_contact_cleanup', array($this, 'cleanup_inactive_contacts'));

        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('chatshop_daily_contact_cleanup')) {
            wp_schedule_event(time(), 'daily', 'chatshop_daily_contact_cleanup');
        }
    }

    /**
     * Component activation handler
     *
     * @since 1.0.0
     * @return bool True on successful activation
     */
    protected function do_activation()
    {
        return $this->create_database_tables();
    }

    /**
     * Component deactivation handler
     *
     * @since 1.0.0
     * @return bool True on successful deactivation
     */
    protected function do_deactivation()
    {
        // Clear scheduled events
        wp_clear_scheduled_hook('chatshop_daily_contact_cleanup');

        return true;
    }

    /**
     * Create database tables for contact management
     *
     * @since 1.0.0
     * @return bool True if tables created successfully
     */
    private function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Contacts table
        $contacts_table = "CREATE TABLE {$this->tables['contacts']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            name varchar(100) NOT NULL,
            email varchar(100),
            tags text,
            notes text,
            status varchar(20) DEFAULT 'active',
            last_contacted datetime,
            last_seen datetime,
            opt_in_status tinyint(1) DEFAULT 1,
            source varchar(50),
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_phone (phone),
            KEY status (status),
            KEY last_contacted (last_contacted),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Contact groups table
        $groups_table = "CREATE TABLE {$this->tables['contact_groups']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            color varchar(7) DEFAULT '#007cba',
            contact_count int(11) DEFAULT 0,
            created_by bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Contact group relations table
        $relations_table = "CREATE TABLE {$this->tables['contact_group_relations']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) NOT NULL,
            group_id bigint(20) NOT NULL,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_relation (contact_id, group_id),
            KEY contact_id (contact_id),
            KEY group_id (group_id),
            FOREIGN KEY (contact_id) REFERENCES {$this->tables['contacts']}(id) ON DELETE CASCADE,
            FOREIGN KEY (group_id) REFERENCES {$this->tables['contact_groups']}(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Contact interactions table
        $interactions_table = "CREATE TABLE {$this->tables['contact_interactions']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) NOT NULL,
            interaction_type varchar(50) NOT NULL,
            message_id varchar(100),
            content text,
            direction varchar(10) DEFAULT 'outbound',
            status varchar(20),
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_id (contact_id),
            KEY interaction_type (interaction_type),
            KEY direction (direction),
            KEY created_at (created_at),
            FOREIGN KEY (contact_id) REFERENCES {$this->tables['contacts']}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $results = array(
            dbDelta($contacts_table),
            dbDelta($groups_table),
            dbDelta($relations_table),
            dbDelta($interactions_table)
        );

        // Create default groups
        $this->create_default_groups();

        chatshop_log('Contact Manager database tables created', 'info', array('results' => $results));

        return true;
    }

    /**
     * Create default contact groups
     *
     * @since 1.0.0
     */
    private function create_default_groups()
    {
        $default_groups = array(
            array(
                'name' => __('All Contacts', 'chatshop'),
                'description' => __('Default group for all contacts', 'chatshop'),
                'color' => '#007cba'
            ),
            array(
                'name' => __('Customers', 'chatshop'),
                'description' => __('Contacts who have made purchases', 'chatshop'),
                'color' => '#46b450'
            ),
            array(
                'name' => __('Leads', 'chatshop'),
                'description' => __('Potential customers', 'chatshop'),
                'color' => '#ffb900'
            ),
            array(
                'name' => __('VIP', 'chatshop'),
                'description' => __('High value customers', 'chatshop'),
                'color' => '#dc3232'
            )
        );

        foreach ($default_groups as $group) {
            $this->create_group($group);
        }
    }

    /**
     * Add a new contact
     *
     * @since 1.0.0
     * @param array $contact_data Contact data
     * @return int|false Contact ID or false on failure
     */
    public function add_contact($contact_data)
    {
        global $wpdb;

        // Check contact limit for free version
        if (!chatshop_is_premium() && $this->get_contact_count() >= $this->free_contact_limit) {
            return new \WP_Error(
                'contact_limit_exceeded',
                __('Contact limit exceeded. Upgrade to premium for unlimited contacts.', 'chatshop')
            );
        }

        // Validate required fields
        if (empty($contact_data['phone']) || empty($contact_data['name'])) {
            return new \WP_Error(
                'missing_required_fields',
                __('Phone number and name are required.', 'chatshop')
            );
        }

        // Sanitize phone number
        $phone = chatshop_sanitize_phone($contact_data['phone']);
        if (!chatshop_validate_phone($phone)) {
            return new \WP_Error(
                'invalid_phone',
                __('Invalid phone number format.', 'chatshop')
            );
        }

        // Check for duplicates
        if ($this->contact_exists($phone)) {
            return new \WP_Error(
                'contact_exists',
                __('A contact with this phone number already exists.', 'chatshop')
            );
        }

        $contact_data = wp_parse_args($contact_data, array(
            'phone' => $phone,
            'name' => '',
            'email' => '',
            'tags' => '',
            'notes' => '',
            'status' => 'active',
            'opt_in_status' => 1,
            'source' => 'manual',
            'user_id' => get_current_user_id()
        ));

        // Sanitize data
        $sanitized_data = array(
            'phone' => $phone,
            'name' => sanitize_text_field($contact_data['name']),
            'email' => sanitize_email($contact_data['email']),
            'tags' => sanitize_textarea_field($contact_data['tags']),
            'notes' => sanitize_textarea_field($contact_data['notes']),
            'status' => sanitize_key($contact_data['status']),
            'opt_in_status' => intval($contact_data['opt_in_status']),
            'source' => sanitize_key($contact_data['source']),
            'user_id' => intval($contact_data['user_id']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($this->tables['contacts'], $sanitized_data);

        if ($result) {
            $contact_id = $wpdb->insert_id;

            // Add to default group
            $this->add_contact_to_group($contact_id, 1); // Assuming group ID 1 is "All Contacts"

            // Track the event
            do_action('chatshop_contact_added', $contact_id, $sanitized_data);

            chatshop_log("Contact added: {$sanitized_data['name']} ({$phone})", 'info', array(
                'contact_id' => $contact_id
            ));

            return $contact_id;
        }

        chatshop_log("Failed to add contact: {$contact_data['name']}", 'error', array(
            'error' => $wpdb->last_error
        ));

        return false;
    }

    /**
     * Update an existing contact
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @param array $contact_data Updated contact data
     * @return bool True on success, false on failure
     */
    public function update_contact($contact_id, $contact_data)
    {
        global $wpdb;

        $contact_id = intval($contact_id);

        if (!$this->contact_exists_by_id($contact_id)) {
            return false;
        }

        // Sanitize phone if provided
        if (!empty($contact_data['phone'])) {
            $phone = chatshop_sanitize_phone($contact_data['phone']);
            if (!chatshop_validate_phone($phone)) {
                return new \WP_Error(
                    'invalid_phone',
                    __('Invalid phone number format.', 'chatshop')
                );
            }
            $contact_data['phone'] = $phone;
        }

        // Prepare update data
        $update_data = array();
        $allowed_fields = array('phone', 'name', 'email', 'tags', 'notes', 'status', 'opt_in_status', 'last_seen');

        foreach ($allowed_fields as $field) {
            if (isset($contact_data[$field])) {
                switch ($field) {
                    case 'name':
                        $update_data[$field] = sanitize_text_field($contact_data[$field]);
                        break;
                    case 'email':
                        $update_data[$field] = sanitize_email($contact_data[$field]);
                        break;
                    case 'tags':
                    case 'notes':
                        $update_data[$field] = sanitize_textarea_field($contact_data[$field]);
                        break;
                    case 'status':
                        $update_data[$field] = sanitize_key($contact_data[$field]);
                        break;
                    case 'opt_in_status':
                        $update_data[$field] = intval($contact_data[$field]);
                        break;
                    case 'last_seen':
                        $update_data[$field] = $contact_data[$field];
                        break;
                    default:
                        $update_data[$field] = sanitize_text_field($contact_data[$field]);
                }
            }
        }

        $update_data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->tables['contacts'],
            $update_data,
            array('id' => $contact_id),
            null,
            array('%d')
        );

        if ($result !== false) {
            do_action('chatshop_contact_updated', $contact_id, $update_data);

            chatshop_log("Contact updated: ID {$contact_id}", 'info');
            return true;
        }

        chatshop_log("Failed to update contact: ID {$contact_id}", 'error', array(
            'error' => $wpdb->last_error
        ));

        return false;
    }

    /**
     * Delete a contact
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @return bool True on success, false on failure
     */
    public function delete_contact($contact_id)
    {
        global $wpdb;

        $contact_id = intval($contact_id);

        if (!$this->contact_exists_by_id($contact_id)) {
            return false;
        }

        // Get contact data before deletion for logging
        $contact = $this->get_contact($contact_id);

        $result = $wpdb->delete(
            $this->tables['contacts'],
            array('id' => $contact_id),
            array('%d')
        );

        if ($result) {
            do_action('chatshop_contact_deleted', $contact_id, $contact);

            chatshop_log("Contact deleted: {$contact->name} ({$contact->phone})", 'info', array(
                'contact_id' => $contact_id
            ));

            return true;
        }

        chatshop_log("Failed to delete contact: ID {$contact_id}", 'error', array(
            'error' => $wpdb->last_error
        ));

        return false;
    }

    /**
     * Get a contact by ID
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @return object|null Contact object or null if not found
     */
    public function get_contact($contact_id)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['contacts']} WHERE id = %d",
            $contact_id
        ));
    }

    /**
     * Get a contact by phone number
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return object|null Contact object or null if not found
     */
    public function get_contact_by_phone($phone)
    {
        global $wpdb;

        $phone = chatshop_sanitize_phone($phone);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['contacts']} WHERE phone = %s",
            $phone
        ));
    }

    /**
     * Get contacts with pagination and filtering
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Contacts and pagination info
     */
    public function get_contacts($args = array())
    {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'search' => '',
            'status' => '',
            'group_id' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(name LIKE %s OR phone LIKE %s OR email LIKE %s)";
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }

        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        // Handle group filtering
        $join_clause = '';
        if (!empty($args['group_id'])) {
            $join_clause = "INNER JOIN {$this->tables['contact_group_relations']} cgr ON c.id = cgr.contact_id";
            $where_conditions[] = "cgr.group_id = %d";
            $where_values[] = intval($args['group_id']);

            if (!empty($where_conditions)) {
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            }
        }

        // Validate order by
        $allowed_order_by = array('id', 'name', 'phone', 'status', 'created_at', 'updated_at', 'last_contacted');
        if (!in_array($args['order_by'], $allowed_order_by)) {
            $args['order_by'] = 'created_at';
        }

        $order_by = sanitize_sql_orderby($args['order_by'] . ' ' . $args['order']);

        // Get total count
        $count_query = "SELECT COUNT(DISTINCT c.id) FROM {$this->tables['contacts']} c {$join_clause} {$where_clause}";
        $total_contacts = $wpdb->get_var($wpdb->prepare($count_query, $where_values));

        // Get contacts
        $offset = ($args['page'] - 1) * $args['per_page'];
        $limit = intval($args['per_page']);

        $contacts_query = "
            SELECT DISTINCT c.* 
            FROM {$this->tables['contacts']} c 
            {$join_clause} 
            {$where_clause} 
            ORDER BY {$order_by} 
            LIMIT {$offset}, {$limit}
        ";

        $contacts = $wpdb->get_results($wpdb->prepare($contacts_query, $where_values));

        // Calculate pagination
        $total_pages = ceil($total_contacts / $args['per_page']);

        return array(
            'contacts' => $contacts,
            'total' => intval($total_contacts),
            'pages' => intval($total_pages),
            'current_page' => intval($args['page']),
            'per_page' => intval($args['per_page'])
        );
    }

    /**
     * Check if contact exists by phone
     *
     * @since 1.0.0
     * @param string $phone Phone number
     * @return bool True if exists, false otherwise
     */
    public function contact_exists($phone)
    {
        global $wpdb;

        $phone = chatshop_sanitize_phone($phone);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['contacts']} WHERE phone = %s",
            $phone
        ));

        return intval($count) > 0;
    }

    /**
     * Check if contact exists by ID
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @return bool True if exists, false otherwise
     */
    public function contact_exists_by_id($contact_id)
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['contacts']} WHERE id = %d",
            $contact_id
        ));

        return intval($count) > 0;
    }

    /**
     * Get total contact count
     *
     * @since 1.0.0
     * @return int Total contact count
     */
    public function get_contact_count()
    {
        global $wpdb;

        return intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->tables['contacts']}"));
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

        $stats = array(
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'blocked' => 0,
            'unsubscribed' => 0,
            'recent' => 0, // Added in last 7 days
            'limit_reached' => false
        );

        // Get counts by status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->tables['contacts']} GROUP BY status",
            ARRAY_A
        );

        foreach ($status_counts as $status_count) {
            $status = $status_count['status'];
            $count = intval($status_count['count']);

            if (isset($stats[$status])) {
                $stats[$status] = $count;
            }

            $stats['total'] += $count;
        }

        // Get recent contacts (last 7 days)
        $stats['recent'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tables['contacts']} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ));

        // Check if limit reached for free version
        if (!chatshop_is_premium()) {
            $stats['limit_reached'] = $stats['total'] >= $this->free_contact_limit;
        }

        return $stats;
    }

    /**
     * Create a contact group
     *
     * @since 1.0.0
     * @param array $group_data Group data
     * @return int|false Group ID or false on failure
     */
    public function create_group($group_data)
    {
        global $wpdb;

        $group_data = wp_parse_args($group_data, array(
            'name' => '',
            'description' => '',
            'color' => '#007cba',
            'created_by' => get_current_user_id()
        ));

        if (empty($group_data['name'])) {
            return false;
        }

        $sanitized_data = array(
            'name' => sanitize_text_field($group_data['name']),
            'description' => sanitize_textarea_field($group_data['description']),
            'color' => sanitize_hex_color($group_data['color']),
            'created_by' => intval($group_data['created_by']),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($this->tables['contact_groups'], $sanitized_data);

        if ($result) {
            $group_id = $wpdb->insert_id;
            chatshop_log("Contact group created: {$sanitized_data['name']}", 'info', array(
                'group_id' => $group_id
            ));
            return $group_id;
        }

        return false;
    }

    /**
     * Add contact to group
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @param int $group_id Group ID
     * @return bool True on success, false on failure
     */
    public function add_contact_to_group($contact_id, $group_id)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->tables['contact_group_relations'],
            array(
                'contact_id' => intval($contact_id),
                'group_id' => intval($group_id),
                'added_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );

        if ($result) {
            // Update group contact count
            $this->update_group_contact_count($group_id);
            return true;
        }

        return false;
    }

    /**
     * Update group contact count
     *
     * @since 1.0.0
     * @param int $group_id Group ID
     */
    private function update_group_contact_count($group_id)
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['contact_group_relations']} WHERE group_id = %d",
            $group_id
        ));

        $wpdb->update(
            $this->tables['contact_groups'],
            array('contact_count' => intval($count)),
            array('id' => $group_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Record contact interaction
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @param string $type Interaction type
     * @param array $data Interaction data
     * @return int|false Interaction ID or false on failure
     */
    public function record_interaction($contact_id, $type, $data = array())
    {
        global $wpdb;

        $interaction_data = wp_parse_args($data, array(
            'message_id' => '',
            'content' => '',
            'direction' => 'outbound',
            'status' => 'sent',
            'metadata' => array()
        ));

        $result = $wpdb->insert(
            $this->tables['contact_interactions'],
            array(
                'contact_id' => intval($contact_id),
                'interaction_type' => sanitize_key($type),
                'message_id' => sanitize_text_field($interaction_data['message_id']),
                'content' => sanitize_textarea_field($interaction_data['content']),
                'direction' => sanitize_key($interaction_data['direction']),
                'status' => sanitize_key($interaction_data['status']),
                'metadata' => wp_json_encode($interaction_data['metadata']),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            // Update contact's last_contacted timestamp if outbound
            if ($interaction_data['direction'] === 'outbound') {
                $this->update_contact($contact_id, array('last_contacted' => current_time('mysql')));
            }

            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Handle incoming WhatsApp message
     *
     * @since 1.0.0
     * @param array $message_data Message data
     * @param string $phone Sender phone number
     */
    public function handle_incoming_message($message_data, $phone)
    {
        $phone = chatshop_sanitize_phone($phone);

        // Get or create contact
        $contact = $this->get_contact_by_phone($phone);

        if (!$contact) {
            // Create new contact from incoming message
            $contact_id = $this->add_contact(array(
                'phone' => $phone,
                'name' => $message_data['sender_name'] ?? $phone,
                'source' => 'whatsapp_incoming'
            ));
        } else {
            $contact_id = $contact->id;

            // Update last seen
            $this->update_contact($contact_id, array('last_seen' => current_time('mysql')));
        }

        if ($contact_id && !is_wp_error($contact_id)) {
            // Record the interaction
            $this->record_interaction($contact_id, 'message_received', array(
                'message_id' => $message_data['id'] ?? '',
                'content' => $message_data['body'] ?? '',
                'direction' => 'inbound',
                'status' => 'received',
                'metadata' => $message_data
            ));
        }
    }

    /**
     * Handle WhatsApp status update
     *
     * @since 1.0.0
     * @param array $status_data Status data
     * @param string $phone Contact phone number
     */
    public function handle_status_update($status_data, $phone)
    {
        $contact = $this->get_contact_by_phone($phone);

        if ($contact) {
            $this->record_interaction($contact->id, 'status_update', array(
                'message_id' => $status_data['message_id'] ?? '',
                'status' => $status_data['status'] ?? '',
                'direction' => 'system',
                'metadata' => $status_data
            ));
        }
    }

    /**
     * Import contacts from CSV data
     *
     * @since 1.0.0
     * @param array $csv_data CSV data
     * @return array Import results
     */
    public function import_contacts($csv_data)
    {
        $results = array(
            'total' => count($csv_data),
            'imported' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        foreach ($csv_data as $row_index => $row) {
            try {
                // Validate required fields
                if (empty($row['phone']) || empty($row['name'])) {
                    $results['errors'][] = "Row " . ($row_index + 1) . ": Missing required fields (phone, name)";
                    continue;
                }

                // Check if contact already exists
                if ($this->contact_exists($row['phone'])) {
                    $results['skipped']++;
                    continue;
                }

                // Add the contact
                $contact_id = $this->add_contact(array(
                    'phone' => $row['phone'],
                    'name' => $row['name'],
                    'email' => $row['email'] ?? '',
                    'tags' => $row['tags'] ?? '',
                    'notes' => $row['notes'] ?? '',
                    'source' => 'csv_import'
                ));

                if (is_wp_error($contact_id)) {
                    $results['errors'][] = "Row " . ($row_index + 1) . ": " . $contact_id->get_error_message();
                } elseif ($contact_id) {
                    $results['imported']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Row " . ($row_index + 1) . ": " . $e->getMessage();
            }
        }

        chatshop_log("Contact import completed", 'info', $results);

        return $results;
    }

    /**
     * Export contacts to CSV format
     *
     * @since 1.0.0
     * @param array $args Export arguments
     * @return string|false CSV content or false on failure
     */
    public function export_contacts($args = array())
    {
        $contacts_data = $this->get_contacts(array(
            'per_page' => -1, // Get all contacts
            'status' => $args['status'] ?? '',
            'group_id' => $args['group_id'] ?? 0
        ));

        if (empty($contacts_data['contacts'])) {
            return false;
        }

        // Create CSV content
        $csv_content = '';
        $headers = array('ID', 'Phone', 'Name', 'Email', 'Tags', 'Status', 'Created At');

        // Add headers
        $csv_content .= implode(',', $headers) . "\n";

        // Add contact data
        foreach ($contacts_data['contacts'] as $contact) {
            $row = array(
                $contact->id,
                $contact->phone,
                '"' . str_replace('"', '""', $contact->name) . '"',
                $contact->email,
                '"' . str_replace('"', '""', $contact->tags) . '"',
                $contact->status,
                $contact->created_at
            );

            $csv_content .= implode(',', $row) . "\n";
        }

        return $csv_content;
    }

    /**
     * Get contact groups
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Contact groups
     */
    public function get_groups($args = array())
    {
        global $wpdb;

        $defaults = array(
            'order_by' => 'name',
            'order' => 'ASC',
            'include_counts' => true
        );

        $args = wp_parse_args($args, $defaults);

        $order_by = sanitize_sql_orderby($args['order_by'] . ' ' . $args['order']);

        $query = "SELECT * FROM {$this->tables['contact_groups']} ORDER BY {$order_by}";
        $groups = $wpdb->get_results($query);

        // Update contact counts if requested
        if ($args['include_counts']) {
            foreach ($groups as $group) {
                $this->update_group_contact_count($group->id);
            }

            // Re-fetch with updated counts
            $groups = $wpdb->get_results($query);
        }

        return $groups;
    }

    /**
     * Get contact interactions
     *
     * @since 1.0.0
     * @param int $contact_id Contact ID
     * @param array $args Query arguments
     * @return array Contact interactions
     */
    public function get_contact_interactions($contact_id, $args = array())
    {
        global $wpdb;

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'type' => '',
            'direction' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array('contact_id = %d');
        $where_values = array($contact_id);

        if (!empty($args['type'])) {
            $where_conditions[] = 'interaction_type = %s';
            $where_values[] = $args['type'];
        }

        if (!empty($args['direction'])) {
            $where_conditions[] = 'direction = %s';
            $where_values[] = $args['direction'];
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);

        $query = "
            SELECT * FROM {$this->tables['contact_interactions']} 
            {$where_clause} 
            ORDER BY created_at DESC 
            LIMIT {$offset}, {$limit}
        ";

        $interactions = $wpdb->get_results($wpdb->prepare($query, $where_values));

        // Decode metadata JSON
        foreach ($interactions as $interaction) {
            $interaction->metadata = json_decode($interaction->metadata, true);
        }

        return $interactions;
    }

    /**
     * Search contacts
     *
     * @since 1.0.0
     * @param string $search_term Search term
     * @param array $args Additional arguments
     * @return array Search results
     */
    public function search_contacts($search_term, $args = array())
    {
        $args['search'] = $search_term;
        return $this->get_contacts($args);
    }

    /**
     * Bulk update contacts
     *
     * @since 1.0.0
     * @param array $contact_ids Contact IDs
     * @param array $update_data Data to update
     * @return int Number of updated contacts
     */
    public function bulk_update_contacts($contact_ids, $update_data)
    {
        if (empty($contact_ids) || empty($update_data)) {
            return 0;
        }

        $updated_count = 0;
        foreach ($contact_ids as $contact_id) {
            if ($this->update_contact($contact_id, $update_data)) {
                $updated_count++;
            }
        }

        chatshop_log("Bulk contact update completed", 'info', array(
            'updated_count' => $updated_count,
            'total_requested' => count($contact_ids)
        ));

        return $updated_count;
    }

    /**
     * Cleanup inactive contacts
     *
     * @since 1.0.0
     */
    public function cleanup_inactive_contacts()
    {
        global $wpdb;

        $cleanup_days = chatshop_get_option('contacts', 'cleanup_inactive_days', 365);
        $cutoff_date = date('Y-m-d', strtotime("-{$cleanup_days} days"));

        // Only cleanup contacts that are inactive and haven't been contacted recently
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['contacts']} 
             WHERE status = 'inactive' 
             AND (last_contacted IS NULL OR last_contacted < %s)
             AND created_at < %s",
            $cutoff_date,
            $cutoff_date
        ));

        if ($deleted > 0) {
            chatshop_log("Cleaned up {$deleted} inactive contacts", 'info');
        }
    }

    /**
     * Get component status for admin display
     *
     * @since 1.0.0
     * @return array Component status
     */
    public function get_status()
    {
        global $wpdb;

        $status = array(
            'active' => true,
            'tables_exist' => true,
            'total_contacts' => 0,
            'recent_contacts' => 0,
            'groups_count' => 0,
            'interactions_count' => 0,
            'errors' => array()
        );

        // Check if tables exist
        foreach ($this->tables as $table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                $status['tables_exist'] = false;
                $status['errors'][] = "Table {$table_name} does not exist";
            }
        }

        if ($status['tables_exist']) {
            // Get contact statistics
            $stats = $this->get_contact_stats();
            $status['total_contacts'] = $stats['total'];
            $status['recent_contacts'] = $stats['recent'];

            // Get groups count
            $status['groups_count'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['contact_groups']}"
            ));

            // Get interactions count
            $status['interactions_count'] = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['contact_interactions']}"
            ));
        }

        return $status;
    }

    // AJAX Handlers

    /**
     * AJAX handler for adding contact
     *
     * @since 1.0.0
     */
    public function ajax_add_contact()
    {
        if (!chatshop_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!chatshop_check_permissions('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $contact_data = array(
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'tags' => sanitize_textarea_field($_POST['tags'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );

        $result = $this->add_contact($contact_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } elseif ($result) {
            wp_send_json_success(array(
                'contact_id' => $result,
                'message' => __('Contact added successfully', 'chatshop')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to add contact', 'chatshop')));
        }
    }

    /**
     * AJAX handler for updating contact
     *
     * @since 1.0.0
     */
    public function ajax_update_contact()
    {
        if (!chatshop_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!chatshop_check_permissions('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $contact_id = intval($_POST['contact_id'] ?? 0);
        $contact_data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'tags' => sanitize_textarea_field($_POST['tags'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'status' => sanitize_key($_POST['status'] ?? '')
        );

        $result = $this->update_contact($contact_id, $contact_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } elseif ($result) {
            wp_send_json_success(array('message' => __('Contact updated successfully', 'chatshop')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update contact', 'chatshop')));
        }
    }

    /**
     * AJAX handler for deleting contact
     *
     * @since 1.0.0
     */
    public function ajax_delete_contact()
    {
        if (!chatshop_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!chatshop_check_permissions('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $contact_id = intval($_POST['contact_id'] ?? 0);
        $result = $this->delete_contact($contact_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Contact deleted successfully', 'chatshop')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete contact', 'chatshop')));
        }
    }

    /**
     * AJAX handler for bulk deleting contacts
     *
     * @since 1.0.0
     */
    public function ajax_bulk_delete_contacts()
    {
        if (!chatshop_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!chatshop_check_permissions('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $contact_ids = array_map('intval', $_POST['contact_ids'] ?? array());

        if (empty($contact_ids)) {
            wp_send_json_error(array('message' => __('No contacts selected', 'chatshop')));
            return;
        }

        $deleted_count = 0;
        foreach ($contact_ids as $contact_id) {
            if ($this->delete_contact($contact_id)) {
                $deleted_count++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d contacts deleted successfully', 'chatshop'), $deleted_count),
            'deleted_count' => $deleted_count
        ));
    }

    /**
     * AJAX handler for importing contacts
     *
     * @since 1.0.0
     */
    public function ajax_import_contacts()
    {
        if (!chatshop_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!chatshop_check_permissions('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        if (!chatshop_is_premium_feature_available('unlimited_contacts')) {
            wp_send_json_error(array('message' => __('Contact import requires premium access', 'chatshop')));
            return;
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('No file uploaded or upload error occurred', 'chatshop')));
            return;
        }

        $file = $_FILES['import_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, array('csv'))) {
            wp_send_json_error(array('message' => __('Only CSV files are supported', 'chatshop')));
            return;
        }

        // Parse CSV file
        $csv_data = array();
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            $headers = fgetcsv($handle);

            // Normalize headers
            $headers = array_map(function ($header) {
                return strtolower(trim($header));
            }, $headers);

            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) === count($headers)) {
                    $csv_data[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }

        if (empty($csv_data)) {
            wp_send_json_error(array('message' => __('No data found in CSV file', 'chatshop')));
            return;
        }

        $results = $this->import_contacts($csv_data);
        wp_send_json_success($results);
    }

    /**
     * AJAX handler for exporting contacts
     *
     * @since 1.0.0
     */
    public function ajax_export_contacts()
    {
        if (!chatshop_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!chatshop_check_permissions('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        if (!chatshop_is_premium_feature_available('unlimited_contacts')) {
            wp_send_json_error(array('message' => __('Contact export requires premium access', 'chatshop')));
            return;
        }

        $export_args = array(
            'status' => sanitize_key($_POST['status'] ?? ''),
            'group_id' => intval($_POST['group_id'] ?? 0)
        );

        $csv_content = $this->export_contacts($export_args);

        if (!$csv_content) {
            wp_send_json_error(array('message' => __('No contacts found to export', 'chatshop')));
            return;
        }

        // Save to uploads directory
        $upload_dir = wp_upload_dir();
        $filename = 'chatshop-contacts-export-' . date('Y-m-d-H-i-s') . '.csv';
        $file_path = $upload_dir['path'] . '/' . $filename;

        if (file_put_contents($file_path, $csv_content)) {
            wp_send_json_success(array(
                'download_url' => $upload_dir['url'] . '/' . $filename,
                'message' => __('Contacts exported successfully', 'chatshop')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create export file', 'chatshop')));
        }
    }

    /**
     * AJAX handler for getting contact statistics
     *
     * @since 1.0.0
     */
    public function ajax_get_contact_stats()
    {
        if (!chatshop_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!chatshop_check_permissions('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $stats = $this->get_contact_stats();
        wp_send_json_success($stats);
    }

    /**
     * AJAX handler for searching contacts
     *
     * @since 1.0.0
     */
    public function ajax_search_contacts()
    {
        if (!chatshop_verify_nonce($_POST['nonce'] ?? '', 'chatshop_admin_nonce')) {
            wp_die(__('Security check failed', 'chatshop'));
        }

        if (!chatshop_check_permissions('manage_options')) {
            wp_die(__('Insufficient permissions', 'chatshop'));
        }

        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);

        $results = $this->search_contacts($search_term, array(
            'page' => $page,
            'per_page' => $per_page
        ));

        wp_send_json_success($results);
    }
}
