<?php

/**
 * ChatShop Message Sender
 *
 * Handles sending messages via WhatsApp Business API
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Message Sender class
 *
 * Manages WhatsApp message sending with rate limiting and error handling
 */
class ChatShop_Message_Sender
{

    /**
     * WhatsApp API instance
     *
     * @var ChatShop_WhatsApp_API
     */
    private $api;

    /**
     * Rate limiter instance
     *
     * @var ChatShop_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Media handler instance
     *
     * @var ChatShop_Media_Handler
     */
    private $media_handler;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api = new ChatShop_WhatsApp_API();
        $this->rate_limiter = new \ChatShop\Core\ChatShop_Rate_Limiter();
        $this->media_handler = new ChatShop_Media_Handler();
    }

    /**
     * Send text message
     *
     * @param string $phone_number Recipient phone number
     * @param string $message Message content
     * @param array  $options Additional options
     * @return array|WP_Error Response or error
     */
    public function send_text_message($phone_number, $message, $options = [])
    {
        if (!$this->validate_phone_number($phone_number)) {
            return new \WP_Error('invalid_phone', __('Invalid phone number format', 'chatshop'));
        }

        if (!$this->rate_limiter->can_send($phone_number)) {
            return new \WP_Error('rate_limited', __('Rate limit exceeded for this contact', 'chatshop'));
        }

        $sanitized_message = sanitize_textarea_field($message);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->format_phone_number($phone_number),
            'type' => 'text',
            'text' => [
                'body' => $sanitized_message
            ]
        ];

        $response = $this->api->send_message($payload);

        if (is_wp_error($response)) {
            $this->log_error('text_message_failed', $response->get_error_message(), [
                'phone' => $phone_number,
                'message_length' => strlen($sanitized_message)
            ]);
            return $response;
        }

        $this->rate_limiter->record_send($phone_number);
        $this->log_message_sent('text', $phone_number, $sanitized_message);

        return $response;
    }

    /**
     * Send template message
     *
     * @param string $phone_number Recipient phone number
     * @param string $template_name Template name
     * @param array  $parameters Template parameters
     * @param string $language Language code (default: 'en')
     * @return array|WP_Error Response or error
     */
    public function send_template_message($phone_number, $template_name, $parameters = [], $language = 'en')
    {
        if (!$this->validate_phone_number($phone_number)) {
            return new \WP_Error('invalid_phone', __('Invalid phone number format', 'chatshop'));
        }

        if (!$this->rate_limiter->can_send($phone_number)) {
            return new \WP_Error('rate_limited', __('Rate limit exceeded for this contact', 'chatshop'));
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->format_phone_number($phone_number),
            'type' => 'template',
            'template' => [
                'name' => sanitize_text_field($template_name),
                'language' => [
                    'code' => sanitize_text_field($language)
                ]
            ]
        ];

        if (!empty($parameters)) {
            $payload['template']['components'] = $this->build_template_components($parameters);
        }

        $response = $this->api->send_message($payload);

        if (is_wp_error($response)) {
            $this->log_error('template_message_failed', $response->get_error_message(), [
                'phone' => $phone_number,
                'template' => $template_name
            ]);
            return $response;
        }

        $this->rate_limiter->record_send($phone_number);
        $this->log_message_sent('template', $phone_number, $template_name);

        return $response;
    }

    /**
     * Send media message
     *
     * @param string $phone_number Recipient phone number
     * @param string $media_type Media type (image, document, video, audio)
     * @param string $media_url Media URL or file path
     * @param string $caption Optional caption
     * @return array|WP_Error Response or error
     */
    public function send_media_message($phone_number, $media_type, $media_url, $caption = '')
    {
        if (!$this->validate_phone_number($phone_number)) {
            return new \WP_Error('invalid_phone', __('Invalid phone number format', 'chatshop'));
        }

        if (!$this->rate_limiter->can_send($phone_number)) {
            return new \WP_Error('rate_limited', __('Rate limit exceeded for this contact', 'chatshop'));
        }

        $media_data = $this->media_handler->prepare_media($media_type, $media_url);

        if (is_wp_error($media_data)) {
            return $media_data;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->format_phone_number($phone_number),
            'type' => $media_type,
            $media_type => $media_data
        ];

        if (!empty($caption) && in_array($media_type, ['image', 'video', 'document'])) {
            $payload[$media_type]['caption'] = sanitize_textarea_field($caption);
        }

        $response = $this->api->send_message($payload);

        if (is_wp_error($response)) {
            $this->log_error('media_message_failed', $response->get_error_message(), [
                'phone' => $phone_number,
                'media_type' => $media_type
            ]);
            return $response;
        }

        $this->rate_limiter->record_send($phone_number);
        $this->log_message_sent('media', $phone_number, $media_type);

        return $response;
    }

    /**
     * Send bulk messages
     *
     * @param array $recipients Array of recipient data
     * @param array $message_data Message data
     * @return array Results array
     */
    public function send_bulk_messages($recipients, $message_data)
    {
        $results = [];
        $batch_size = 10; // Process in batches to avoid memory issues
        $batches = array_chunk($recipients, $batch_size);

        foreach ($batches as $batch) {
            foreach ($batch as $recipient) {
                $phone = $recipient['phone'];
                $personalized_data = $this->personalize_message($message_data, $recipient);

                switch ($message_data['type']) {
                    case 'text':
                        $result = $this->send_text_message($phone, $personalized_data['message']);
                        break;
                    case 'template':
                        $result = $this->send_template_message(
                            $phone,
                            $personalized_data['template'],
                            $personalized_data['parameters']
                        );
                        break;
                    default:
                        $result = new \WP_Error('invalid_type', 'Invalid message type');
                }

                $results[$phone] = [
                    'success' => !is_wp_error($result),
                    'response' => $result,
                    'timestamp' => current_time('mysql')
                ];

                // Add delay between messages to respect rate limits
                usleep(500000); // 0.5 second delay
            }
        }

        return $results;
    }

    /**
     * Validate phone number format
     *
     * @param string $phone_number Phone number to validate
     * @return bool True if valid
     */
    private function validate_phone_number($phone_number)
    {
        // Remove all non-digit characters
        $clean_phone = preg_replace('/\D/', '', $phone_number);

        // Check if it's a valid international format (8-15 digits)
        return preg_match('/^\d{8,15}$/', $clean_phone);
    }

    /**
     * Format phone number for WhatsApp API
     *
     * @param string $phone_number Raw phone number
     * @return string Formatted phone number
     */
    private function format_phone_number($phone_number)
    {
        // Remove all non-digit characters
        $clean_phone = preg_replace('/\D/', '', $phone_number);

        // Add country code if missing (assuming format without leading +)
        if (!str_starts_with($clean_phone, '234') && strlen($clean_phone) === 11) {
            $clean_phone = '234' . substr($clean_phone, 1);
        }

        return $clean_phone;
    }

    /**
     * Build template components from parameters
     *
     * @param array $parameters Template parameters
     * @return array Components array
     */
    private function build_template_components($parameters)
    {
        $components = [];

        if (!empty($parameters['header'])) {
            $components[] = [
                'type' => 'header',
                'parameters' => $this->build_parameters($parameters['header'])
            ];
        }

        if (!empty($parameters['body'])) {
            $components[] = [
                'type' => 'body',
                'parameters' => $this->build_parameters($parameters['body'])
            ];
        }

        if (!empty($parameters['button'])) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => $this->build_parameters($parameters['button'])
            ];
        }

        return $components;
    }

    /**
     * Build parameters array for template components
     *
     * @param array $params Raw parameters
     * @return array Formatted parameters
     */
    private function build_parameters($params)
    {
        $formatted = [];

        foreach ($params as $param) {
            if (is_string($param)) {
                $formatted[] = [
                    'type' => 'text',
                    'text' => sanitize_text_field($param)
                ];
            } elseif (is_array($param) && isset($param['type'])) {
                $formatted[] = $param;
            }
        }

        return $formatted;
    }

    /**
     * Personalize message with recipient data
     *
     * @param array $message_data Base message data
     * @param array $recipient Recipient data
     * @return array Personalized message data
     */
    private function personalize_message($message_data, $recipient)
    {
        $personalized = $message_data;

        if (isset($message_data['message'])) {
            $personalized['message'] = $this->replace_placeholders($message_data['message'], $recipient);
        }

        if (isset($message_data['parameters'])) {
            foreach ($message_data['parameters'] as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subkey => $subvalue) {
                        if (is_string($subvalue)) {
                            $personalized['parameters'][$key][$subkey] = $this->replace_placeholders($subvalue, $recipient);
                        }
                    }
                } elseif (is_string($value)) {
                    $personalized['parameters'][$key] = $this->replace_placeholders($value, $recipient);
                }
            }
        }

        return $personalized;
    }

    /**
     * Replace placeholders in message with recipient data
     *
     * @param string $message Message with placeholders
     * @param array  $recipient Recipient data
     * @return string Message with replaced placeholders
     */
    private function replace_placeholders($message, $recipient)
    {
        $placeholders = [
            '{first_name}' => $recipient['first_name'] ?? '',
            '{last_name}' => $recipient['last_name'] ?? '',
            '{name}' => $recipient['name'] ?? $recipient['first_name'] ?? '',
            '{phone}' => $recipient['phone'] ?? '',
            '{email}' => $recipient['email'] ?? ''
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $message);
    }

    /**
     * Log message sent
     *
     * @param string $type Message type
     * @param string $phone Phone number
     * @param string $content Message content/identifier
     */
    private function log_message_sent($type, $phone, $content)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'ChatShop: %s message sent to %s - %s',
                $type,
                $phone,
                substr($content, 0, 50)
            ));
        }

        do_action('chatshop_message_sent', $type, $phone, $content);
    }

    /**
     * Log error
     *
     * @param string $error_type Error type
     * @param string $message Error message
     * @param array  $context Additional context
     */
    private function log_error($error_type, $message, $context = [])
    {
        error_log(sprintf(
            'ChatShop Message Sender Error [%s]: %s - Context: %s',
            $error_type,
            $message,
            json_encode($context)
        ));

        do_action('chatshop_message_error', $error_type, $message, $context);
    }
}
