<?php
/**
 * Plugin Activation Class
 */

class Worknoon_Chat_Activator {

    public static function activate() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die('WorkNoon Chat requires PHP 7.4 or higher');
        }

        // Create plugin options
        $default_settings = array(
            'api_url' => 'http://localhost:5000',
            'enable_notifications' => true,
            'enable_file_uploads' => true,
            'max_upload_size' => 5242880, // 5MB
        );

        foreach ($default_settings as $key => $value) {
            if (!get_option('worknoon_chat_' . $key)) {
                add_option('worknoon_chat_' . $key, $value);
            }
        }

        // Track activation timestamp and site
        if (!get_option('worknoon_chat_activated_at')) {
            add_option('worknoon_chat_activated_at', current_time('mysql'));
        } else {
            update_option('worknoon_chat_activated_at', current_time('mysql'));
        }

        if (!get_option('worknoon_chat_activated_site')) {
            add_option('worknoon_chat_activated_site', site_url());
        } else {
            update_option('worknoon_chat_activated_site', site_url());
        }
        // Set a one-time redirect flag so admin can be sent to onboarding page
        if (!get_option('worknoon_chat_do_activation_redirect')) {
            add_option('worknoon_chat_do_activation_redirect', 1);
        } else {
            update_option('worknoon_chat_do_activation_redirect', 1);
        }
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
