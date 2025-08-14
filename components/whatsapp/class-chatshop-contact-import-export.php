<?php

/**
 * Contact Import/Export Handler
 *
 * File: components/whatsapp/class-chatshop-contact-import-export.php
 * 
 * Handles CSV and Excel import/export functionality for contacts.
 * Premium feature with comprehensive validation and error handling.
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
 * ChatShop Contact Import/Export Class
 *
 * Handles importing and exporting contacts in CSV and Excel formats.
 * Includes data validation, duplicate detection, and error reporting.
 *
 * @since 1.0.0
 */
class ChatShop_Contact_Import_Export
{
    /**
     * Contact manager instance
     *
     * @var ChatShop_Contact_Manager
     * @since 1.0.0
     */
    private $contact_manager;

    /**
     * Maximum file size for uploads (5MB)
     *
     * @var int
     * @since 1.0.0
     */
    private $max_file_size = 5242880;

    /**
     * Allowed file types
     *
     * @var array
     * @since 1.0.0
     */
    private $allowed_types = array('csv', 'xlsx', 'xls');

    /**
     * Required CSV columns
     *
     * @var array
     * @since 1.0.0
     */
    private $required_columns = array('phone', 'name');

    /**
     * Optional CSV columns
     *
     * @var array
     * @since 1.0.0
     */
    private $optional_columns = array('email', 'tags', 'notes', 'status');

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param ChatShop_Contact_Manager $contact_manager Contact manager instance
     */
    public function __construct($contact_manager)
    {
        $this->contact_manager = $contact_manager;
    }

    /**
     * Handle contact import
     *
     * @since 1.0.0
     * @return array Import result
     */
    public function handle_import()
    {
        // Validate file upload
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => __('No file uploaded or upload error occurred.', 'chatshop')
            );
        }

        $file = $_FILES['import_file'];

        // Validate file size
        if ($file['size'] > $this->max_file_size) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('File size exceeds maximum allowed size of %s.', 'chatshop'),
                    size_format($this->max_file_size)
                )
            );
        }

        // Validate file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_types)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Invalid file type. Allowed types: %s', 'chatshop'),
                    implode(', ', $this->allowed_types)
                )
            );
        }

        // Process file based on type
        switch ($file_extension) {
            case 'csv':
                return $this->import_csv($file['tmp_name']);
            case 'xlsx':
            case 'xls':
                return $this->import_excel($file['tmp_name'], $file_extension);
            default:
                return array(
                    'success' => false,
                    'message' => __('Unsupported file format.', 'chatshop')
                );
        }
    }

    /**
     * Import contacts from CSV file
     *
     * @since 1.0.0
     * @param string $file_path Path to CSV file
     * @return array Import result
     */
    private function import_csv($file_path)
    {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return array(
                'success' => false,
                'message' => __('Cannot read uploaded file.', 'chatshop')
            );
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return array(
                'success' => false,
                'message' => __('Cannot open CSV file.', 'chatshop')
            );
        }

        $contacts = array();
        $headers = array();
        $row_number = 0;
        $errors = array();

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $row_number++;

            // First row should be headers
            if ($row_number === 1) {
                $headers = array_map('trim', array_map('strtolower', $row));

                // Validate required headers
                $missing_headers = array_diff($this->required_columns, $headers);
                if (!empty($missing_headers)) {
                    fclose($handle);
                    return array(
                        'success' => false,
                        'message' => sprintf(
                            __('Missing required columns: %s', 'chatshop'),
                            implode(', ', $missing_headers)
                        )
                    );
                }
                continue;
            }

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Parse row data
            $contact_data = $this->parse_csv_row($headers, $row, $row_number);

            if (isset($contact_data['error'])) {
                $errors[] = $contact_data['error'];
                continue;
            }

            $contacts[] = $contact_data;
        }

        fclose($handle);

        if (empty($contacts)) {
            return array(
                'success' => false,
                'message' => __('No valid contacts found in the file.', 'chatshop'),
                'errors' => $errors
            );
        }

        // Import contacts
        return $this->process_contact_import($contacts, $errors);
    }

    /**
     * Import contacts from Excel file
     *
     * @since 1.0.0
     * @param string $file_path Path to Excel file
     * @param string $extension File extension
     * @return array Import result
     */
    private function import_excel($file_path, $extension)
    {
        // For now, return an error message as Excel import requires additional libraries
        // This can be implemented with libraries like PhpSpreadsheet if needed
        return array(
            'success' => false,
            'message' => __('Excel import is not yet implemented. Please use CSV format.', 'chatshop')
        );
    }

    /**
     * Parse CSV row into contact data
     *
     * @since 1.0.0
     * @param array $headers CSV headers
     * @param array $row CSV row data
     * @param int   $row_number Row number for error reporting
     * @return array Contact data or error
     */
    private function parse_csv_row($headers, $row, $row_number)
    {
        $contact_data = array();

        // Map row values to headers
        foreach ($headers as $index => $header) {
            $value = isset($row[$index]) ? trim($row[$index]) : '';
            $contact_data[$header] = $value;
        }

        // Validate required fields
        if (empty($contact_data['phone'])) {
            return array(
                'error' => sprintf(
                    __('Row %d: Phone number is required.', 'chatshop'),
                    $row_number
                )
            );
        }

        if (empty($contact_data['name'])) {
            return array(
                'error' => sprintf(
                    __('Row %d: Name is required.', 'chatshop'),
                    $row_number
                )
            );
        }

        // Validate phone number format
        if (!$this->is_valid_phone($contact_data['phone'])) {
            return array(
                'error' => sprintf(
                    __('Row %d: Invalid phone number format: %s', 'chatshop'),
                    $row_number,
                    $contact_data['phone']
                )
            );
        }

        // Validate email if provided
        if (!empty($contact_data['email']) && !is_email($contact_data['email'])) {
            return array(
                'error' => sprintf(
                    __('Row %d: Invalid email format: %s', 'chatshop'),
                    $row_number,
                    $contact_data['email']
                )
            );
        }

        // Validate status if provided
        if (!empty($contact_data['status']) && !in_array($contact_data['status'], array('active', 'inactive', 'blocked'))) {
            $contact_data['status'] = 'active'; // Default to active
        }

        return $contact_data;
    }

    /**
     * Process contact import batch
     *
     * @since 1.0.0
     * @param array $contacts Contacts to import
     * @param array $errors Existing errors
     * @return array Import result
     */
    private function process_contact_import($contacts, $errors = array())
    {
        $imported_count = 0;
        $skipped_count = 0;
        $failed_count = 0;

        foreach ($contacts as $contact_data) {
            // Check if contact already exists
            $existing_contact = $this->contact_manager->get_contact_by_phone($contact_data['phone']);

            if ($existing_contact) {
                $skipped_count++;
                $errors[] = sprintf(
                    __('Contact with phone %s already exists and was skipped.', 'chatshop'),
                    $contact_data['phone']
                );
                continue;
            }

            // Check if we can add more contacts (free plan limit)
            if (!$this->contact_manager->can_add_contact()) {
                $errors[] = __('Contact limit reached. Remaining contacts were not imported.', 'chatshop');
                break;
            }

            // Add contact
            $result = $this->contact_manager->add_contact($contact_data);

            if ($result['success']) {
                $imported_count++;
            } else {
                $failed_count++;
                $errors[] = sprintf(
                    __('Failed to import contact %s: %s', 'chatshop'),
                    $contact_data['phone'],
                    $result['message']
                );
            }
        }

        chatshop_log("Contact import completed", 'info', array(
            'imported' => $imported_count,
            'skipped' => $skipped_count,
            'failed' => $failed_count,
            'total_contacts' => count($contacts)
        ));

        return array(
            'success' => $imported_count > 0,
            'message' => sprintf(
                __('Import completed. %d imported, %d skipped, %d failed.', 'chatshop'),
                $imported_count,
                $skipped_count,
                $failed_count
            ),
            'imported_count' => $imported_count,
            'skipped_count' => $skipped_count,
            'failed_count' => $failed_count,
            'errors' => $errors
        );
    }

    /**
     * Handle contact export
     *
     * @since 1.0.0
     * @param string $format Export format (csv or xlsx)
     * @return array Export result
     */
    public function handle_export($format = 'csv')
    {
        $format = strtolower($format);

        if (!in_array($format, array('csv', 'xlsx'))) {
            return array(
                'success' => false,
                'message' => __('Invalid export format.', 'chatshop')
            );
        }

        // Get all contacts
        $contacts_data = $this->contact_manager->get_contacts(array(
            'limit' => 10000, // Large limit to get all contacts
            'offset' => 0
        ));

        if (empty($contacts_data['contacts'])) {
            return array(
                'success' => false,
                'message' => __('No contacts to export.', 'chatshop')
            );
        }

        switch ($format) {
            case 'csv':
                return $this->export_csv($contacts_data['contacts']);
            case 'xlsx':
                return $this->export_excel($contacts_data['contacts']);
            default:
                return array(
                    'success' => false,
                    'message' => __('Unsupported export format.', 'chatshop')
                );
        }
    }

    /**
     * Export contacts to CSV
     *
     * @since 1.0.0
     * @param array $contacts Contacts to export
     * @return array Export result
     */
    private function export_csv($contacts)
    {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/chatshop/exports/';

        // Create directory if it doesn't exist
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $filename = 'chatshop-contacts-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = $export_dir . $filename;

        $handle = fopen($filepath, 'w');
        if (!$handle) {
            return array(
                'success' => false,
                'message' => __('Cannot create export file.', 'chatshop')
            );
        }

        // Write CSV headers
        $headers = array('phone', 'name', 'email', 'status', 'opt_in_status', 'tags', 'notes', 'created_at');
        fputcsv($handle, $headers);

        // Write contact data
        foreach ($contacts as $contact) {
            $row = array(
                $contact['phone'],
                $contact['name'],
                $contact['email'],
                $contact['status'],
                $contact['opt_in_status'],
                $contact['tags'],
                $contact['notes'],
                $contact['created_at']
            );
            fputcsv($handle, $row);
        }

        fclose($handle);

        $download_url = $upload_dir['baseurl'] . '/chatshop/exports/' . $filename;

        chatshop_log("Contacts exported to CSV", 'info', array(
            'filename' => $filename,
            'contact_count' => count($contacts)
        ));

        return array(
            'success' => true,
            'message' => sprintf(
                __('Successfully exported %d contacts.', 'chatshop'),
                count($contacts)
            ),
            'download_url' => $download_url,
            'filename' => $filename,
            'contact_count' => count($contacts)
        );
    }

    /**
     * Export contacts to Excel
     *
     * @since 1.0.0
     * @param array $contacts Contacts to export
     * @return array Export result
     */
    private function export_excel($contacts)
    {
        // For now, return an error message as Excel export requires additional libraries
        // This can be implemented with libraries like PhpSpreadsheet if needed
        return array(
            'success' => false,
            'message' => __('Excel export is not yet implemented. Please use CSV format.', 'chatshop')
        );
    }

    /**
     * Validate phone number format
     *
     * @since 1.0.0
     * @param string $phone Phone number to validate
     * @return bool Whether phone number is valid
     */
    private function is_valid_phone($phone)
    {
        // Remove all non-digit characters except +
        $cleaned_phone = preg_replace('/[^0-9+]/', '', $phone);

        // Must have at least 10 digits and start with + for international format
        return !empty($cleaned_phone) &&
            strlen($cleaned_phone) >= 10 &&
            (substr($cleaned_phone, 0, 1) === '+' || ctype_digit($cleaned_phone));
    }

    /**
     * Get sample CSV template
     *
     * @since 1.0.0
     * @return array Template data
     */
    public function get_csv_template()
    {
        $upload_dir = wp_upload_dir();
        $template_dir = $upload_dir['basedir'] . '/chatshop/templates/';

        // Create directory if it doesn't exist
        if (!file_exists($template_dir)) {
            wp_mkdir_p($template_dir);
        }

        $filename = 'chatshop-contacts-template.csv';
        $filepath = $template_dir . $filename;

        // Create template file if it doesn't exist
        if (!file_exists($filepath)) {
            $handle = fopen($filepath, 'w');
            if ($handle) {
                // Write headers
                $headers = array('phone', 'name', 'email', 'tags', 'notes', 'status');
                fputcsv($handle, $headers);

                // Write sample data
                $sample_data = array(
                    array('+1234567890', 'John Doe', 'john@example.com', 'customer,vip', 'Important customer', 'active'),
                    array('+0987654321', 'Jane Smith', 'jane@example.com', 'prospect', 'Potential lead', 'active')
                );

                foreach ($sample_data as $row) {
                    fputcsv($handle, $row);
                }

                fclose($handle);
            }
        }

        $download_url = $upload_dir['baseurl'] . '/chatshop/templates/' . $filename;

        return array(
            'success' => true,
            'download_url' => $download_url,
            'filename' => $filename
        );
    }

    /**
     * Cleanup old export files
     *
     * @since 1.0.0
     */
    public function cleanup_old_exports()
    {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/chatshop/exports/';

        if (!is_dir($export_dir)) {
            return;
        }

        $files = glob($export_dir . '*.csv');
        $cutoff_time = time() - (7 * DAY_IN_SECONDS); // 7 days

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }

        chatshop_log('Old export files cleaned up', 'info');
    }
}
