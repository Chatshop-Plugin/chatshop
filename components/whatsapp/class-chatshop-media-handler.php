<?php

/**
 * ChatShop Media Handler
 *
 * Handles media processing for WhatsApp messages
 *
 * @package ChatShop
 * @subpackage Media
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Media Handler class
 *
 * Processes and validates media files for WhatsApp messages
 */
class ChatShop_Media_Handler
{

    /**
     * WhatsApp API instance
     *
     * @var ChatShop_WhatsApp_API
     */
    private $api;

    /**
     * Supported media types and their constraints
     *
     * @var array
     */
    private $media_constraints = [
        'image' => [
            'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size' => 5242880, // 5MB
            'extensions' => ['jpg', 'jpeg', 'png', 'webp']
        ],
        'video' => [
            'allowed_types' => ['video/mp4', 'video/3gpp'],
            'max_size' => 16777216, // 16MB
            'extensions' => ['mp4', '3gp']
        ],
        'audio' => [
            'allowed_types' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'],
            'max_size' => 16777216, // 16MB
            'extensions' => ['aac', 'mp3', 'm4a', 'amr', 'ogg']
        ],
        'document' => [
            'allowed_types' => [
                'application/pdf',
                'application/vnd.ms-powerpoint',
                'application/msword',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain'
            ],
            'max_size' => 104857600, // 100MB
            'extensions' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt']
        ]
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api = new ChatShop_WhatsApp_API();
    }

    /**
     * Prepare media for WhatsApp message
     *
     * @param string $media_type Media type (image, video, audio, document)
     * @param string $media_source Media source (URL or file path)
     * @param array  $options Additional options
     * @return array|WP_Error Media data or error
     */
    public function prepare_media($media_type, $media_source, $options = [])
    {
        // Validate media type
        if (!$this->is_supported_media_type($media_type)) {
            return new \WP_Error('unsupported_type', __('Unsupported media type', 'chatshop'));
        }

        // Check if source is URL or local file
        if (filter_var($media_source, FILTER_VALIDATE_URL)) {
            return $this->prepare_media_from_url($media_type, $media_source, $options);
        } else {
            return $this->prepare_media_from_file($media_type, $media_source, $options);
        }
    }

    /**
     * Prepare media from URL
     *
     * @param string $media_type Media type
     * @param string $url Media URL
     * @param array  $options Additional options
     * @return array|WP_Error Media data or error
     */
    private function prepare_media_from_url($media_type, $url, $options = [])
    {
        // Validate URL
        $validation = $this->validate_media_url($url, $media_type);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // For external URLs, return link data
        return [
            'link' => esc_url_raw($url)
        ];
    }

    /**
     * Prepare media from local file
     *
     * @param string $media_type Media type
     * @param string $file_path File path
     * @param array  $options Additional options
     * @return array|WP_Error Media data or error
     */
    private function prepare_media_from_file($media_type, $file_path, $options = [])
    {
        // Validate file exists
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', __('Media file not found', 'chatshop'));
        }

        // Validate file
        $validation = $this->validate_media_file($file_path, $media_type);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Upload to WhatsApp Media API
        $upload_result = $this->upload_media_to_whatsapp($file_path, $media_type);
        if (is_wp_error($upload_result)) {
            return $upload_result;
        }

        return [
            'id' => $upload_result['id']
        ];
    }

    /**
     * Download and process incoming media
     *
     * @param string $media_id WhatsApp media ID
     * @param string $media_type Media type
     * @return array|WP_Error Downloaded media info or error
     */
    public function download_incoming_media($media_id, $media_type)
    {
        // Get media URL from WhatsApp API
        $media_url_response = $this->api->get_media_url($media_id);
        if (is_wp_error($media_url_response)) {
            return $media_url_response;
        }

        $media_url = $media_url_response['url'];

        // Download media file
        $download_result = $this->download_media_file($media_url, $media_id, $media_type);
        if (is_wp_error($download_result)) {
            return $download_result;
        }

        // Process and store in WordPress media library
        return $this->store_in_media_library($download_result['file_path'], $media_type, $media_id);
    }

    /**
     * Validate media file
     *
     * @param string $file_path File path
     * @param string $media_type Expected media type
     * @return true|WP_Error True if valid, error if not
     */
    private function validate_media_file($file_path, $media_type)
    {
        if (!isset($this->media_constraints[$media_type])) {
            return new \WP_Error('invalid_type', __('Invalid media type', 'chatshop'));
        }

        $constraints = $this->media_constraints[$media_type];

        // Check file size
        $file_size = filesize($file_path);
        if ($file_size > $constraints['max_size']) {
            return new \WP_Error('file_too_large', sprintf(
                __('File size exceeds maximum allowed size of %s', 'chatshop'),
                $this->format_file_size($constraints['max_size'])
            ));
        }

        // Check file extension
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $constraints['extensions'])) {
            return new \WP_Error('invalid_extension', sprintf(
                __('File extension %s is not allowed for %s files', 'chatshop'),
                $file_extension,
                $media_type
            ));
        }

        // Check MIME type
        $mime_type = $this->get_file_mime_type($file_path);
        if (!in_array($mime_type, $constraints['allowed_types'])) {
            return new \WP_Error('invalid_mime_type', sprintf(
                __('MIME type %s is not allowed for %s files', 'chatshop'),
                $mime_type,
                $media_type
            ));
        }

        return true;
    }

    /**
     * Validate media URL
     *
     * @param string $url Media URL
     * @param string $media_type Media type
     * @return true|WP_Error True if valid, error if not
     */
    private function validate_media_url($url, $media_type)
    {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', __('Invalid media URL', 'chatshop'));
        }

        // Check URL scheme
        $parsed_url = parse_url($url);
        if (!in_array($parsed_url['scheme'], ['http', 'https'])) {
            return new \WP_Error('invalid_scheme', __('Only HTTP and HTTPS URLs are allowed', 'chatshop'));
        }

        // Check file extension from URL
        $file_extension = strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION));
        if (!empty($file_extension)) {
            $constraints = $this->media_constraints[$media_type];
            if (!in_array($file_extension, $constraints['extensions'])) {
                return new \WP_Error('invalid_url_extension', sprintf(
                    __('URL file extension %s is not allowed for %s files', 'chatshop'),
                    $file_extension,
                    $media_type
                ));
            }
        }

        return true;
    }

    /**
     * Upload media to WhatsApp
     *
     * @param string $file_path File path
     * @param string $media_type Media type
     * @return array|WP_Error Upload response or error
     */
    private function upload_media_to_whatsapp($file_path, $media_type)
    {
        $mime_type = $this->get_file_mime_type($file_path);

        return $this->api->upload_media($file_path, $mime_type);
    }

    /**
     * Download media file from URL
     *
     * @param string $url Media URL
     * @param string $media_id Media ID
     * @param string $media_type Media type
     * @return array|WP_Error Download result or error
     */
    private function download_media_file($url, $media_id, $media_type)
    {
        $upload_dir = wp_upload_dir();
        $chatshop_dir = $upload_dir['basedir'] . '/chatshop-media';

        // Create directory if it doesn't exist
        if (!file_exists($chatshop_dir)) {
            wp_mkdir_p($chatshop_dir);
        }

        // Generate filename
        $file_extension = $this->get_extension_from_media_type($media_type);
        $filename = sanitize_file_name($media_id . '.' . $file_extension);
        $file_path = $chatshop_dir . '/' . $filename;

        // Download file
        $response = wp_remote_get($url, [
            'timeout' => 60,
            'headers' => $this->api->get_auth_headers()
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200) {
            return new \WP_Error('download_failed', __('Failed to download media file', 'chatshop'));
        }

        // Save file
        $saved = file_put_contents($file_path, $body);
        if ($saved === false) {
            return new \WP_Error('save_failed', __('Failed to save media file', 'chatshop'));
        }

        return [
            'file_path' => $file_path,
            'filename' => $filename,
            'size' => filesize($file_path)
        ];
    }

    /**
     * Store media in WordPress media library
     *
     * @param string $file_path File path
     * @param string $media_type Media type
     * @param string $media_id WhatsApp media ID
     * @return array|WP_Error Media library data or error
     */
    private function store_in_media_library($file_path, $media_type, $media_id)
    {
        $filename = basename($file_path);
        $upload_dir = wp_upload_dir();

        // Move file to uploads directory
        $target_path = $upload_dir['path'] . '/' . $filename;
        if (!rename($file_path, $target_path)) {
            return new \WP_Error('move_failed', __('Failed to move file to uploads directory', 'chatshop'));
        }

        // Prepare file data for WordPress
        $file_data = [
            'name' => $filename,
            'type' => $this->get_file_mime_type($target_path),
            'tmp_name' => $target_path,
            'error' => 0,
            'size' => filesize($target_path)
        ];

        // Handle the upload
        $upload_result = wp_handle_sideload($file_data, [
            'test_form' => false,
            'mimes' => $this->get_allowed_mime_types()
        ]);

        if (isset($upload_result['error'])) {
            return new \WP_Error('upload_error', $upload_result['error']);
        }

        // Insert attachment
        $attachment_data = [
            'post_mime_type' => $upload_result['type'],
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attachment_id = wp_insert_attachment($attachment_data, $upload_result['file']);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload_result['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        // Store WhatsApp media ID as meta
        update_post_meta($attachment_id, '_chatshop_whatsapp_media_id', $media_id);

        return [
            'attachment_id' => $attachment_id,
            'url' => $upload_result['url'],
            'file_path' => $upload_result['file'],
            'mime_type' => $upload_result['type']
        ];
    }

    /**
     * Get media thumbnail
     *
     * @param int    $attachment_id Attachment ID
     * @param string $size Thumbnail size
     * @return string|false Thumbnail URL or false
     */
    public function get_media_thumbnail($attachment_id, $size = 'thumbnail')
    {
        if (wp_attachment_is_image($attachment_id)) {
            $thumbnail = wp_get_attachment_image_src($attachment_id, $size);
            return $thumbnail ? $thumbnail[0] : false;
        }

        // For non-images, return a default icon or generate a preview
        return $this->get_media_icon($attachment_id);
    }

    /**
     * Get media icon for non-image files
     *
     * @param int $attachment_id Attachment ID
     * @return string Icon URL
     */
    private function get_media_icon($attachment_id)
    {
        $mime_type = get_post_mime_type($attachment_id);
        $icon_dir = includes_url('images/media/');

        switch ($mime_type) {
            case 'application/pdf':
                return $icon_dir . 'document.png';
            case 'video/mp4':
            case 'video/3gpp':
                return $icon_dir . 'video.png';
            case 'audio/aac':
            case 'audio/mp4':
            case 'audio/mpeg':
            case 'audio/amr':
            case 'audio/ogg':
                return $icon_dir . 'audio.png';
            default:
                return $icon_dir . 'default.png';
        }
    }

    /**
     * Compress image if needed
     *
     * @param string $file_path Image file path
     * @param int    $max_width Maximum width
     * @param int    $max_height Maximum height
     * @param int    $quality JPEG quality (1-100)
     * @return string|WP_Error Compressed file path or error
     */
    public function compress_image($file_path, $max_width = 1024, $max_height = 1024, $quality = 85)
    {
        if (!$this->is_image_file($file_path)) {
            return new \WP_Error('not_image', __('File is not an image', 'chatshop'));
        }

        $image_editor = wp_get_image_editor($file_path);
        if (is_wp_error($image_editor)) {
            return $image_editor;
        }

        $current_size = $image_editor->get_size();

        // Only resize if image is larger than max dimensions
        if ($current_size['width'] > $max_width || $current_size['height'] > $max_height) {
            $resize_result = $image_editor->resize($max_width, $max_height, false);
            if (is_wp_error($resize_result)) {
                return $resize_result;
            }
        }

        // Set quality for JPEG images
        if ($this->get_file_mime_type($file_path) === 'image/jpeg') {
            $image_editor->set_quality($quality);
        }

        // Generate new filename
        $path_info = pathinfo($file_path);
        $compressed_path = $path_info['dirname'] . '/' . $path_info['filename'] . '_compressed.' . $path_info['extension'];

        $save_result = $image_editor->save($compressed_path);
        if (is_wp_error($save_result)) {
            return $save_result;
        }

        return $compressed_path;
    }

    /**
     * Generate media preview for messages
     *
     * @param int $attachment_id Attachment ID
     * @return array Preview data
     */
    public function generate_media_preview($attachment_id)
    {
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return [];
        }

        $mime_type = get_post_mime_type($attachment_id);
        $file_url = wp_get_attachment_url($attachment_id);

        $preview = [
            'id' => $attachment_id,
            'title' => get_the_title($attachment_id),
            'mime_type' => $mime_type,
            'url' => $file_url,
            'size' => $this->get_attachment_file_size($attachment_id)
        ];

        if (wp_attachment_is_image($attachment_id)) {
            $image_meta = wp_get_attachment_metadata($attachment_id);
            $preview['width'] = $image_meta['width'] ?? 0;
            $preview['height'] = $image_meta['height'] ?? 0;
            $preview['thumbnail'] = $this->get_media_thumbnail($attachment_id, 'medium');
        } else {
            $preview['icon'] = $this->get_media_icon($attachment_id);
        }

        return $preview;
    }

    /**
     * Clean up temporary media files
     *
     * @param int $days_old Delete files older than this many days
     * @return int Number of files deleted
     */
    public function cleanup_temp_media($days_old = 7)
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/chatshop-media';

        if (!is_dir($temp_dir)) {
            return 0;
        }

        $cutoff_time = time() - ($days_old * DAY_IN_SECONDS);
        $deleted_count = 0;

        $files = glob($temp_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }

        return $deleted_count;
    }

    /**
     * Get attachment file size
     *
     * @param int $attachment_id Attachment ID
     * @return string Formatted file size
     */
    private function get_attachment_file_size($attachment_id)
    {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return '';
        }

        return $this->format_file_size(filesize($file_path));
    }

    /**
     * Format file size in human readable format
     *
     * @param int $size Size in bytes
     * @return string Formatted size
     */
    private function format_file_size($size)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit_index = 0;

        while ($size >= 1024 && $unit_index < count($units) - 1) {
            $size /= 1024;
            $unit_index++;
        }

        return round($size, 2) . ' ' . $units[$unit_index];
    }

    /**
     * Get file MIME type
     *
     * @param string $file_path File path
     * @return string MIME type
     */
    private function get_file_mime_type($file_path)
    {
        $mime_type = wp_check_filetype($file_path);
        return $mime_type['type'] ?: 'application/octet-stream';
    }

    /**
     * Check if file is an image
     *
     * @param string $file_path File path
     * @return bool True if image
     */
    private function is_image_file($file_path)
    {
        $mime_type = $this->get_file_mime_type($file_path);
        return strpos($mime_type, 'image/') === 0;
    }

    /**
     * Check if media type is supported
     *
     * @param string $media_type Media type
     * @return bool True if supported
     */
    private function is_supported_media_type($media_type)
    {
        return isset($this->media_constraints[$media_type]);
    }

    /**
     * Get file extension from media type
     *
     * @param string $media_type Media type
     * @return string Default file extension
     */
    private function get_extension_from_media_type($media_type)
    {
        $extensions = [
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'mp3',
            'document' => 'pdf'
        ];

        return $extensions[$media_type] ?? 'bin';
    }

    /**
     * Get allowed MIME types for WordPress
     *
     * @return array Allowed MIME types
     */
    private function get_allowed_mime_types()
    {
        $mime_types = [];

        foreach ($this->media_constraints as $type => $constraints) {
            foreach ($constraints['allowed_types'] as $mime_type) {
                $extension = '';

                // Map MIME types to extensions
                switch ($mime_type) {
                    case 'image/jpeg':
                        $extension = 'jpg|jpeg';
                        break;
                    case 'image/png':
                        $extension = 'png';
                        break;
                    case 'image/webp':
                        $extension = 'webp';
                        break;
                    case 'video/mp4':
                        $extension = 'mp4';
                        break;
                    case 'video/3gpp':
                        $extension = '3gp';
                        break;
                    case 'audio/mpeg':
                        $extension = 'mp3';
                        break;
                    case 'audio/mp4':
                        $extension = 'm4a';
                        break;
                    case 'audio/aac':
                        $extension = 'aac';
                        break;
                    case 'audio/amr':
                        $extension = 'amr';
                        break;
                    case 'audio/ogg':
                        $extension = 'ogg';
                        break;
                    case 'application/pdf':
                        $extension = 'pdf';
                        break;
                    case 'application/msword':
                        $extension = 'doc';
                        break;
                    case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                        $extension = 'docx';
                        break;
                    case 'application/vnd.ms-excel':
                        $extension = 'xls';
                        break;
                    case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                        $extension = 'xlsx';
                        break;
                    case 'application/vnd.ms-powerpoint':
                        $extension = 'ppt';
                        break;
                    case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                        $extension = 'pptx';
                        break;
                    case 'text/plain':
                        $extension = 'txt';
                        break;
                }

                if ($extension) {
                    $mime_types[$extension] = $mime_type;
                }
            }
        }

        return $mime_types;
    }

    /**
     * Get media constraints for a specific type
     *
     * @param string $media_type Media type
     * @return array|null Constraints or null if not supported
     */
    public function get_media_constraints($media_type)
    {
        return $this->media_constraints[$media_type] ?? null;
    }

    /**
     * Get all supported media types
     *
     * @return array Supported media types
     */
    public function get_supported_media_types()
    {
        return array_keys($this->media_constraints);
    }
}
