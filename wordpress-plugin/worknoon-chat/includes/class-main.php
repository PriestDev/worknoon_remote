<?php
/**
 * Main Plugin Class
 */

class Worknoon_Chat_Main {

    public function __construct() {
        add_action('init', array($this, 'register_chat_session_type'));

        if (is_admin()) {
            add_action('admin_init', array($this, 'maybe_redirect_on_activation'));
            add_action('add_meta_boxes', array($this, 'register_session_meta_boxes'));
        }

        if (class_exists('WooCommerce')) {
            add_action('woocommerce_new_order', array($this, 'handle_woocommerce_new_order'), 10, 1);
        }
    }

    public function maybe_redirect_on_activation() {
        // Only redirect once, and only for users with manage_options
        $flag = get_option('worknoon_chat_do_activation_redirect', false);
        if (!$flag) {
            return;
        }

        // remove the flag so we only redirect once
        delete_option('worknoon_chat_do_activation_redirect');

        if (!current_user_can('manage_options')) {
            return;
        }

        // Avoid redirect during bulk plugin activation
        if (isset($_GET['activate-multi'])) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=worknoon-chat-onboarding'));
        exit;
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'worknoon-chat-styles',
            WORKNOON_CHAT_URL . 'assets/css/chat.css',
            array(),
            WORKNOON_CHAT_VERSION
        );

        wp_enqueue_script(
            'socket-io',
            'https://cdn.socket.io/4.5.4/socket.io.js',
            array(),
            '4.5.4',
            true
        );

        wp_enqueue_script(
            'worknoon-chat-app',
            WORKNOON_CHAT_URL . 'assets/js/chat-app.js',
            array('socket-io'),
            WORKNOON_CHAT_VERSION,
            true
        );

        $api_url = get_option('worknoon_chat_api_url', 'http://localhost:5000');
        $current_user = wp_get_current_user();
        $context = $this->get_page_context();

        wp_localize_script('worknoon-chat-app', 'WorknoonChat', array(
            'apiUrl' => $api_url,
            'userId' => $current_user->ID,
            'userEmail' => $current_user->user_email,
            'userName' => $current_user->user_login,
            'isLoggedIn' => is_user_logged_in(),
            'nonce' => wp_create_nonce('worknoon_chat_nonce'),
            'pageContext' => $context,
        ));
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'worknoon-chat') === false) {
            return;
        }

        wp_enqueue_style(
            'worknoon-chat-admin',
            WORKNOON_CHAT_URL . 'assets/css/admin.css',
            array(),
            WORKNOON_CHAT_VERSION
        );

        wp_enqueue_script(
            'worknoon-chat-admin',
            WORKNOON_CHAT_URL . 'assets/js/admin.js',
            array('jquery'),
            WORKNOON_CHAT_VERSION,
            true
        );
    }

    public function output_chat_widget() {
        if (!is_user_logged_in()) {
            return;
        }

        echo '<div id="worknoon-chat-widget"></div>';
    }

    public function render_chat_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to use the chat feature.</p>';
        }

        return '<div id="worknoon-chat-shortcode" style="width: 100%; height: 600px;"></div>';
    }

    public function register_chat_session_type() {
        $labels = array(
            'name' => __('Chat Sessions', 'worknoon-chat'),
            'singular_name' => __('Chat Session', 'worknoon-chat'),
            'menu_name' => __('Chat Sessions', 'worknoon-chat'),
            'name_admin_bar' => __('Chat Session', 'worknoon-chat'),
            'add_new' => __('Add New', 'worknoon-chat'),
            'add_new_item' => __('Add New Chat Session', 'worknoon-chat'),
            'new_item' => __('New Chat Session', 'worknoon-chat'),
            'edit_item' => __('Edit Chat Session', 'worknoon-chat'),
            'view_item' => __('View Chat Session', 'worknoon-chat'),
            'all_items' => __('All Chat Sessions', 'worknoon-chat'),
            'search_items' => __('Search Chat Sessions', 'worknoon-chat'),
            'not_found' => __('No chat sessions found.', 'worknoon-chat'),
            'not_found_in_trash' => __('No chat sessions found in Trash.', 'worknoon-chat'),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'custom-fields'),
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-format-chat',
        );

        register_post_type('chat_session', $args);
    }

    public function register_session_meta_boxes() {
        add_meta_box(
            'worknoon_chat_session_meta',
            __('Chat Session Details', 'worknoon-chat'),
            array($this, 'render_session_meta_box'),
            'chat_session',
            'side',
            'default'
        );
    }

    public function render_session_meta_box($post) {
        $order_id = get_post_meta($post->ID, '_worknoon_chat_order_id', true);
        $product_ids = get_post_meta($post->ID, '_worknoon_chat_product_ids', true);
        $context = get_post_meta($post->ID, '_worknoon_chat_context', true);
        $backend_conversation = get_post_meta($post->ID, '_worknoon_chat_backend_conversation_id', true);

        echo '<p><strong>' . __('Context', 'worknoon-chat') . ':</strong> ' . esc_html($context ?: __('General', 'worknoon-chat')) . '</p>';
        echo '<p><strong>' . __('Order ID', 'worknoon-chat') . ':</strong> ' . esc_html($order_id ?: __('None', 'worknoon-chat')) . '</p>';
        echo '<p><strong>' . __('Product IDs', 'worknoon-chat') . ':</strong> ' . esc_html(is_array($product_ids) ? implode(', ', $product_ids) : $product_ids) . '</p>';
        echo '<p><strong>' . __('Backend Conversation', 'worknoon-chat') . ':</strong> ' . esc_html($backend_conversation ?: __('Not created', 'worknoon-chat')) . '</p>';
    }

    public function handle_woocommerce_new_order($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $product_ids = array();
        $product_names = array();

        foreach ($order->get_items() as $item) {
            if ($item->get_product_id()) {
                $product_ids[] = $item->get_product_id();
                $product_names[] = $item->get_name();
            }
        }

        $session_id = $this->create_chat_session(array(
            'title' => sprintf(__('Order #%d Support Chat', 'worknoon-chat'), $order_id),
            'user_id' => $order->get_user_id(),
            'order_id' => $order_id,
            'product_ids' => $product_ids,
            'product_names' => $product_names,
            'context' => 'order',
        ));

        if ($session_id) {
            update_post_meta($order_id, '_worknoon_chat_session_id', $session_id);
        }
    }

    protected function create_chat_session($args = array()) {
        $defaults = array(
            'title' => __('Chat Session', 'worknoon-chat'),
            'user_id' => get_current_user_id(),
            'order_id' => '',
            'product_ids' => array(),
            'product_names' => array(),
            'context' => 'general',
            'context_text' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $post_id = wp_insert_post(array(
            'post_title' => sanitize_text_field($args['title']),
            'post_type' => 'chat_session',
            'post_status' => 'publish',
            'post_author' => intval($args['user_id']),
            'post_content' => sanitize_textarea_field($args['context_text']),
        ));

        if (is_wp_error($post_id) || !$post_id) {
            return false;
        }

        update_post_meta($post_id, '_worknoon_chat_user_id', intval($args['user_id']));
        update_post_meta($post_id, '_worknoon_chat_order_id', sanitize_text_field($args['order_id']));
        update_post_meta($post_id, '_worknoon_chat_product_ids', array_map('intval', (array) $args['product_ids']));
        update_post_meta($post_id, '_worknoon_chat_product_names', array_map('sanitize_text_field', (array) $args['product_names']));
        update_post_meta($post_id, '_worknoon_chat_context', sanitize_text_field($args['context']));
        update_post_meta($post_id, '_worknoon_chat_context_text', sanitize_textarea_field($args['context_text']));

        return $post_id;
    }

    private function get_page_context() {
        $context = array(
            'type' => 'general',
            'title' => '',
            'data' => array(),
        );

        if (function_exists('is_product') && is_product()) {
            global $product;
            if ($product) {
                $context['type'] = 'product';
                $context['title'] = $product->get_name();
                $context['data'] = array(
                    'productId' => $product->get_id(),
                    'productName' => $product->get_name(),
                    'productSku' => $product->get_sku(),
                    'productUrl' => get_permalink($product->get_id()),
                );
            }
        }

        if (function_exists('is_order_received_page') && is_order_received_page()) {
            $order_id = absint(get_query_var('order-received'));
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $context['type'] = 'order';
                    $context['title'] = sprintf(__('Order #%d Chat', 'worknoon-chat'), $order_id);
                    $context['data'] = array(
                        'orderId' => $order_id,
                        'orderTotal' => $order->get_total(),
                        'productIds' => wp_list_pluck($order->get_items(), 'product_id'),
                    );
                }
            }
        }

        return $context;
    }
}
