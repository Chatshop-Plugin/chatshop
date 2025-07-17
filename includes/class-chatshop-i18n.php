<?php

/**
 * Define the internationalization functionality
 *
 * @package ChatShop
 * @subpackage ChatShop/includes
 * @since 1.0.0
 */

namespace ChatShop;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since 1.0.0
 */
class ChatShop_i18n
{
    /**
     * Load the plugin text domain for translation
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'chatshop',
            false,
            dirname(CHATSHOP_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Get available languages
     *
     * @since 1.0.0
     * @return array
     */
    public function get_available_languages()
    {
        $languages = array();
        $language_dir = CHATSHOP_PLUGIN_DIR . 'languages/';

        if (is_dir($language_dir)) {
            $files = glob($language_dir . '*.mo');

            foreach ($files as $file) {
                $locale = basename($file, '.mo');
                if (strpos($locale, 'chatshop-') === 0) {
                    $locale = str_replace('chatshop-', '', $locale);
                    $languages[] = $locale;
                }
            }
        }

        return $languages;
    }

    /**
     * Get current language
     *
     * @since 1.0.0
     * @return string
     */
    public function get_current_language()
    {
        return get_locale();
    }

    /**
     * Check if language file exists
     *
     * @since 1.0.0
     * @param string $locale Language locale
     * @return bool
     */
    public function language_file_exists($locale)
    {
        $file = CHATSHOP_PLUGIN_DIR . "languages/chatshop-{$locale}.mo";
        return file_exists($file);
    }
}
