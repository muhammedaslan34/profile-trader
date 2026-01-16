<?php
/**
 * Trader Connection Class
 * Handles the connection between WordPress users and trader listings
 * 
 * Supports multiple connection methods:
 * 1. Author-based (default WordPress behavior)
 * 2. Meta-based (using _trader_user_id meta field)
 * 3. Email-based (matching user email with trader email field)
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

class PT_Trader_Connection {
    
    private static $instance = null;
    
    /**
     * Meta key for storing user ID connection
     */
    const USER_META_KEY = '_trader_user_id';
    
    /**
     * User meta key for storing connected trader IDs
     */
    const TRADER_IDS_META_KEY = '_connected_trader_ids';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log errors for debugging
     *
     * @param string $message Error message
     * @param string $context Context (e.g., 'connect', 'disconnect', 'query')
     * @param mixed $data Additional data to log
     */
    private static function log_error($message, $context = 'general', $data = null) {
        // Log to WordPress debug.log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf('[Profile Trader - %s] %s', $context, $message);
            if ($data) {
                $log_message .= ' | Data: ' . print_r($data, true);
            }
            error_log($log_message);
        }

        // Store critical errors in transient for admin review
        $errors = get_transient('pt_recent_errors') ?: [];
        $errors[] = [
            'time' => current_time('mysql'),
            'context' => $context,
            'message' => $message,
            'data' => $data,
        ];

        // Keep only last 50 errors
        if (count($errors) > 50) {
            $errors = array_slice($errors, -50);
        }

        set_transient('pt_recent_errors', $errors, DAY_IN_SECONDS);
    }

    /**
     * Add database indexes for better performance
     * Should be called on plugin activation or manually from admin
     */
    public static function add_database_indexes() {
        global $wpdb;

        // Check if indexes already exist to avoid errors
        $indexes_to_add = [];

        // Index for _trader_user_id meta key
        $index_name_user = 'idx_pt_trader_user_id';
        $check_user = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(1)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = DATABASE()
            AND table_name = %s
            AND index_name = %s
        ", $wpdb->postmeta, $index_name_user));

        if (!$check_user) {
            $indexes_to_add[] = "ALTER TABLE {$wpdb->postmeta}
                ADD INDEX {$index_name_user} (meta_key(20), meta_value(20))";
        }

        // Index for email meta key
        $index_name_email = 'idx_pt_trader_email';
        $check_email = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(1)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = DATABASE()
            AND table_name = %s
            AND index_name = %s
        ", $wpdb->postmeta, $index_name_email));

        if (!$check_email) {
            $indexes_to_add[] = "ALTER TABLE {$wpdb->postmeta}
                ADD INDEX {$index_name_email} (meta_key(20), meta_value(100))";
        }

        // Execute index additions
        $results = [];
        foreach ($indexes_to_add as $sql) {
            $result = $wpdb->query($sql);
            $results[] = [
                'sql' => $sql,
                'success' => $result !== false,
                'error' => $wpdb->last_error,
            ];
        }

        return $results;
    }

    private function __construct() {
        // Add meta box in admin for connecting traders to users
        add_action('add_meta_boxes', [$this, 'add_connection_meta_box']);
        add_action('save_post_' . PT_POST_TYPE, [$this, 'save_connection_meta']);
        
        // Add user column in admin
        add_filter('manage_' . PT_POST_TYPE . '_posts_columns', [$this, 'add_user_column']);
        add_action('manage_' . PT_POST_TYPE . '_posts_custom_column', [$this, 'render_user_column'], 10, 2);
        
        // Add bulk action for connecting traders
        add_filter('bulk_actions-edit-' . PT_POST_TYPE, [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-' . PT_POST_TYPE, [$this, 'handle_bulk_actions'], 10, 3);
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // AJAX handlers
        add_action('wp_ajax_pt_connect_trader', [$this, 'ajax_connect_trader']);
        add_action('wp_ajax_pt_disconnect_trader', [$this, 'ajax_disconnect_trader']);
        add_action('wp_ajax_pt_auto_connect_by_email', [$this, 'ajax_auto_connect_by_email']);
    }
    
    /**
     * Get all traders connected to a user
     * 
     * @param int $user_id User ID (defaults to current user)
     * @return array Array of post objects
     */
    public function get_user_traders($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [];
        }

        // Check cache first
        $cache_key = 'user_traders_' . $user_id;
        $cached_traders = wp_cache_get($cache_key, 'profile_trader');

        if (false !== $cached_traders) {
            return $cached_traders;
        }

        // Get user email for email-based matching
        $user = get_user_by('id', $user_id);
        $user_email = $user ? $user->user_email : '';

        // Optimized single query - consolidates all 3 connection methods
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT DISTINCT p.*
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'email'
            WHERE p.post_type = %s
            AND p.post_status IN ('publish', 'pending', 'draft')
            AND (
                p.post_author = %d
                OR pm_user.meta_value = %s
                OR (pm_email.meta_value != '' AND LOWER(pm_email.meta_value) = LOWER(%s))
            )
            ORDER BY p.post_date DESC
        ", self::USER_META_KEY, PT_POST_TYPE, $user_id, $user_id, $user_email);

        $trader_ids = $wpdb->get_col($query);

        // Log database errors
        if ($wpdb->last_error) {
            self::log_error('Query failed in get_user_traders', 'query', [
                'user_id' => $user_id,
                'error' => $wpdb->last_error,
                'query' => $query,
            ]);
        }

        // Convert IDs to post objects
        $unique_traders = [];
        if (!empty($trader_ids)) {
            $unique_traders = get_posts([
                'post_type' => PT_POST_TYPE,
                'post__in' => $trader_ids,
                'posts_per_page' => -1,
                'orderby' => 'post__in',
                'post_status' => ['publish', 'pending', 'draft'],
            ]);
        }

        // Cache results for 5 minutes
        wp_cache_set($cache_key, $unique_traders, 'profile_trader', 300);

        return $unique_traders;
    }
    
    /**
     * Check if a user can access/edit a specific trader
     * 
     * @param int $trader_id Trader post ID
     * @param int $user_id User ID (defaults to current user)
     * @return bool
     */
    public function user_can_access_trader($trader_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id || !$trader_id) {
            return false;
        }
        
        // Admins can access all
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        $trader = get_post($trader_id);
        if (!$trader || $trader->post_type !== PT_POST_TYPE) {
            return false;
        }
        
        // Check 1: Is user the author?
        if ((int) $trader->post_author === (int) $user_id) {
            return true;
        }
        
        // Check 2: Is user connected via meta?
        $connected_user = get_post_meta($trader_id, self::USER_META_KEY, true);
        if ((int) $connected_user === (int) $user_id) {
            return true;
        }
        
        // Check 3: Does user email match trader email?
        $user = get_user_by('id', $user_id);
        $trader_email = get_post_meta($trader_id, 'email', true);
        if ($user && $trader_email && strtolower($user->user_email) === strtolower($trader_email)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Connect a trader to a user
     * 
     * @param int $trader_id Trader post ID
     * @param int $user_id User ID
     * @param string $method Connection method: 'author', 'meta', or 'both'
     * @return bool|WP_Error
     */
    public function connect_trader_to_user($trader_id, $user_id, $method = 'both') {
        $trader = get_post($trader_id);
        $user = get_user_by('id', $user_id);
        
        if (!$trader || $trader->post_type !== PT_POST_TYPE) {
            return new WP_Error('invalid_trader', __('التاجر غير موجود', 'profile-trader'));
        }
        
        if (!$user) {
            return new WP_Error('invalid_user', __('المستخدم غير موجود', 'profile-trader'));
        }
        
        // Update author if requested
        if ($method === 'author' || $method === 'both') {
            wp_update_post([
                'ID' => $trader_id,
                'post_author' => $user_id,
            ]);
        }
        
        // Update meta connection
        if ($method === 'meta' || $method === 'both') {
            update_post_meta($trader_id, self::USER_META_KEY, $user_id);
        }
        
        // Update user meta with connected trader IDs
        $connected_ids = get_user_meta($user_id, self::TRADER_IDS_META_KEY, true);
        if (!is_array($connected_ids)) {
            $connected_ids = [];
        }
        if (!in_array($trader_id, $connected_ids)) {
            $connected_ids[] = $trader_id;
            update_user_meta($user_id, self::TRADER_IDS_META_KEY, $connected_ids);
        }

        // Clear cache for this user
        wp_cache_delete('user_traders_' . $user_id, 'profile_trader');

        do_action('pt_trader_connected', $trader_id, $user_id, $method);

        return true;
    }
    
    /**
     * Disconnect a trader from a user
     * 
     * @param int $trader_id Trader post ID
     * @param int $user_id User ID (optional, disconnects from all if not specified)
     * @return bool
     */
    public function disconnect_trader($trader_id, $user_id = null) {
        // Remove meta connection
        if ($user_id) {
            $current_user = get_post_meta($trader_id, self::USER_META_KEY, true);
            if ((int) $current_user === (int) $user_id) {
                delete_post_meta($trader_id, self::USER_META_KEY);
            }
            
            // Remove from user's connected traders
            $connected_ids = get_user_meta($user_id, self::TRADER_IDS_META_KEY, true);
            if (is_array($connected_ids)) {
                $connected_ids = array_diff($connected_ids, [$trader_id]);
                update_user_meta($user_id, self::TRADER_IDS_META_KEY, $connected_ids);
            }

            // Clear cache for this user
            wp_cache_delete('user_traders_' . $user_id, 'profile_trader');
        } else {
            delete_post_meta($trader_id, self::USER_META_KEY);

            // Clear cache for the connected user if meta exists
            $connected_user_id = get_post_meta($trader_id, self::USER_META_KEY, true);
            if ($connected_user_id) {
                wp_cache_delete('user_traders_' . $connected_user_id, 'profile_trader');
            }
        }

        do_action('pt_trader_disconnected', $trader_id, $user_id);

        return true;
    }
    
    /**
     * Auto-connect traders to users by matching email addresses
     * 
     * @return array Stats about connections made
     */
    public function auto_connect_by_email() {
        $stats = [
            'connected' => 0,
            'already_connected' => 0,
            'no_match' => 0,
            'errors' => [],
        ];

        $logs = []; // Detailed logs for each trader

        // Get all traders with email field
        $traders = get_posts([
            'post_type' => PT_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => 'email',
                    'value' => '',
                    'compare' => '!=',
                ],
            ],
        ]);

        foreach ($traders as $trader) {
            $trader_email = get_post_meta($trader->ID, 'email', true);

            $log_entry = [
                'id' => $trader->ID,
                'title' => $trader->post_title,
                'email' => $trader_email,
                'edit_link' => get_edit_post_link($trader->ID),
                'status' => '',
                'message' => '',
            ];

            if (empty($trader_email)) {
                $stats['no_match']++;
                $log_entry['status'] = 'no_email';
                $log_entry['message'] = __('لا يوجد إيميل', 'profile-trader');
                $logs[] = $log_entry;
                continue;
            }

            // Check if already connected
            $existing_connection = get_post_meta($trader->ID, self::USER_META_KEY, true);
            if ($existing_connection) {
                $stats['already_connected']++;
                $log_entry['status'] = 'already_connected';
                $user = get_user_by('ID', $existing_connection);
                $log_entry['message'] = sprintf(__('مرتبط مسبقاً بـ: %s', 'profile-trader'), $user ? $user->display_name : 'Unknown');
                $logs[] = $log_entry;
                continue;
            }

            // Find user by email
            $user = get_user_by('email', $trader_email);

            if ($user) {
                $result = $this->connect_trader_to_user($trader->ID, $user->ID, 'meta');
                if (is_wp_error($result)) {
                    $stats['errors'][] = $result->get_error_message();
                    $log_entry['status'] = 'error';
                    $log_entry['message'] = $result->get_error_message();
                } else {
                    $stats['connected']++;
                    $log_entry['status'] = 'success';
                    $log_entry['message'] = sprintf(__('تم الربط بنجاح مع: %s', 'profile-trader'), $user->display_name);
                }
            } else {
                $stats['no_match']++;
                $log_entry['status'] = 'no_match';
                $log_entry['message'] = __('لا يوجد مستخدم بهذا الإيميل', 'profile-trader');
            }

            $logs[] = $log_entry;
        }

        $stats['logs'] = $logs;
        return $stats;
    }
    
    /**
     * Get custom login URL
     * 
     * @param string $redirect Optional redirect URL after login
     * @return string Login URL
     */
    public static function get_custom_login_url($redirect = '') {
        $login_url = home_url('/login/');
        
        if (!empty($redirect)) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }
        
        return $login_url;
    }
    
    /**
     * Get default password for new users
     * 
     * @return string Default password
     */
    private function get_default_password() {
        $default_password = get_option('pt_default_user_password', '');
        
        // If no default password is set, generate a secure one
        if (empty($default_password)) {
            // Use a secure default password
            $default_password = wp_generate_password(12, true, true);
            // Store it so all users get the same password
            update_option('pt_default_user_password', $default_password);
        }
        
        return $default_password;
    }
    
    /**
     * Create a user account for a trader and connect them
     * 
     * @param int $trader_id Trader post ID
     * @param bool $send_notification Whether to send email notification
     * @param bool $use_default_password Whether to use default password instead of random
     * @return int|WP_Error User ID or error
     */
    public function create_user_for_trader($trader_id, $send_notification = true, $use_default_password = false) {
        // Validate trader
        $trader = get_post($trader_id);
        
        if (!$trader || $trader->post_type !== PT_POST_TYPE) {
            return new WP_Error('invalid_trader', __('التاجر غير موجود', 'profile-trader'));
        }
        
        // Validate email
        $email = get_post_meta($trader_id, 'email', true);
        
        if (empty($email)) {
            return new WP_Error('no_email', sprintf(__('التاجر "%s" لا يملك بريد إلكتروني', 'profile-trader'), $trader->post_title));
        }
        
        if (!is_email($email)) {
            return new WP_Error('invalid_email', sprintf(__('البريد الإلكتروني "%s" غير صالح', 'profile-trader'), $email));
        }
        
        // Check if email is already registered
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            // Check if already connected
            $connected_trader_id = get_post_meta($trader_id, self::USER_META_KEY, true);
            if ($connected_trader_id == $existing_user->ID) {
                return new WP_Error('already_connected', __('التاجر مرتبط بالفعل بهذا المستخدم', 'profile-trader'));
            }
            // Just connect them
            $this->connect_trader_to_user($trader_id, $existing_user->ID, 'both');
            return $existing_user->ID;
        }
        
        // Generate unique username from email
        $username = sanitize_user(current(explode('@', $email)), true);
        
        // Ensure username is valid
        if (empty($username) || strlen($username) < 3) {
            $username = 'user_' . substr(md5($email), 0, 8);
        }
        
        $original_username = $username;
        $counter = 1;
        
        // Make sure username is unique
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
            
            // Prevent infinite loop
            if ($counter > 1000) {
                $username = 'user_' . time() . '_' . rand(1000, 9999);
                break;
            }
        }
        
        // Generate or use default password
        if ($use_default_password) {
            $password = $this->get_default_password();
        } else {
            $password = wp_generate_password(12, true, true);
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            return new WP_Error('weak_password', __('كلمة المرور ضعيفة جداً', 'profile-trader'));
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user info
        $update_result = wp_update_user([
            'ID' => $user_id,
            'display_name' => $trader->post_title,
            'role' => 'subscriber',
        ]);
        
        if (is_wp_error($update_result)) {
            // If update fails, delete the user and return error
            wp_delete_user($user_id);
            return $update_result;
        }
        
        // Mark that user needs to change password on first login
        update_user_meta($user_id, 'pt_force_password_change', true);
        
        // Connect trader to user
        $connect_result = $this->connect_trader_to_user($trader_id, $user_id, 'both');
        
        if (is_wp_error($connect_result)) {
            // Log error but don't fail - user is created
            self::log_error('Failed to connect trader to user', 'create_user', [
                'trader_id' => $trader_id,
                'user_id' => $user_id,
                'error' => $connect_result->get_error_message()
            ]);
        }
        
        // Send notification
        if ($send_notification) {
            $email_sent = $this->send_account_notification($user_id, $password, $trader_id, $use_default_password);
            if (!$email_sent) {
                // Log email failure but don't fail user creation
                self::log_error('Failed to send notification email', 'create_user', [
                    'trader_id' => $trader_id,
                    'user_id' => $user_id,
                    'email' => $email
                ]);
            }
        }
        
        return $user_id;
    }
    
    /**
     * Send account creation notification email
     */
    private function send_account_notification($user_id, $password, $trader_id, $is_default_password = false) {
        $user = get_user_by('id', $user_id);
        $trader = get_post($trader_id);
        
        if (!$user || !$trader) {
            return false;
        }
        
        $dashboard_url = get_permalink(get_page_by_path('trader-dashboard'));
        if (!$dashboard_url) {
            $dashboard_url = home_url();
        }
        $login_url = self::get_custom_login_url($dashboard_url);
        
        $subject = sprintf(__('تم إنشاء حسابك في %s', 'profile-trader'), get_bloginfo('name'));
        
        $password_note = $is_default_password 
            ? __('كلمة المرور الافتراضية: %s (يرجى تغييرها فور تسجيل الدخول)', 'profile-trader')
            : __('كلمة المرور: %s', 'profile-trader');
        
        $password_warning = $is_default_password 
            ? __('⚠️ يرجى تغيير كلمة المرور فور تسجيل الدخول لأول مرة.', 'profile-trader')
            : __('يرجى تغيير كلمة المرور بعد تسجيل الدخول.', 'profile-trader');
        
        // HTML Email Template with Brand Colors
        $message = '
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($subject) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: \'Cairo\', Arial, sans-serif; background-color: #f5f3ed; direction: rtl;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f3ed; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0A4E45 0%, #0d6358 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 800; font-family: \'Cairo\', Arial, sans-serif;">
                                ' . esc_html(get_bloginfo('name')) . '
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #0A4E45; font-size: 24px; font-weight: 700; font-family: \'Cairo\', Arial, sans-serif;">
                                مرحباً ' . esc_html($trader->post_title) . ' 👋
                            </h2>
                            
                            <p style="margin: 0 0 30px 0; color: #1a1a1a; font-size: 16px; line-height: 1.6; font-family: \'Cairo\', Arial, sans-serif;">
                                تم إنشاء حساب لك للوصول إلى لوحة تحكم التاجر.
                            </p>
                            
                            <!-- Login Credentials Box -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #EDEAE0; border-radius: 10px; padding: 25px; margin-bottom: 30px; border: 2px solid #B9A779;">
                                <tr>
                                    <td>
                                        <h3 style="margin: 0 0 20px 0; color: #0A4E45; font-size: 18px; font-weight: 700; font-family: \'Cairo\', Arial, sans-serif;">
                                            بيانات الدخول:
                                        </h3>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #ddd8c8;">
                                                    <strong style="color: #0A4E45; font-size: 14px; display: block; margin-bottom: 5px;">البريد الإلكتروني:</strong>
                                                    <span style="color: #1a1a1a; font-size: 15px; font-weight: 600; direction: ltr; display: inline-block;">' . esc_html($user->user_email) . '</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0;">
                                                    <strong style="color: #0A4E45; font-size: 14px; display: block; margin-bottom: 5px;">' . ($is_default_password ? 'كلمة المرور الافتراضية:' : 'كلمة المرور:') . '</strong>
                                                    <span style="color: #0A4E45; font-size: 18px; font-weight: 700; font-family: monospace; letter-spacing: 2px; background-color: #ffffff; padding: 8px 15px; border-radius: 6px; display: inline-block; border: 1px solid #B9A779;">' . esc_html($password) . '</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Login Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
                                <tr>
                                    <td align="center">
                                        <a href="' . esc_url($login_url) . '" style="display: inline-block; background: linear-gradient(135deg, #0A4E45 0%, #0d6358 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 10px; font-size: 16px; font-weight: 700; font-family: \'Cairo\', Arial, sans-serif; box-shadow: 0 4px 12px rgba(10, 78, 69, 0.3);">
                                            تسجيل الدخول الآن
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Warning/Info Box -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: ' . ($is_default_password ? '#fef3c7' : '#d1fae5') . '; border-right: 4px solid ' . ($is_default_password ? '#f59e0b' : '#10b981') . '; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; color: ' . ($is_default_password ? '#92400e' : '#065f46') . '; font-size: 14px; line-height: 1.6; font-family: \'Cairo\', Arial, sans-serif; font-weight: 600;">
                                            ' . esc_html($password_warning) . '
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Login URL (fallback) -->
                            <p style="margin: 0 0 20px 0; color: #5a5a5a; font-size: 14px; line-height: 1.6; font-family: \'Cairo\', Arial, sans-serif; text-align: center;">
                                أو انسخ الرابط التالي إلى المتصفح:<br>
                                <a href="' . esc_url($login_url) . '" style="color: #0A4E45; word-break: break-all; direction: ltr; display: inline-block; text-align: left;">' . esc_url($login_url) . '</a>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0A4E45; padding: 25px 30px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 14px; font-family: \'Cairo\', Arial, sans-serif;">
                                مع تحياتنا،<br>
                                <strong style="font-size: 16px;">' . esc_html(get_bloginfo('name')) . '</strong>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <!-- Plain Text Fallback -->
                <div style="display: none; max-height: 0; overflow: hidden; font-size: 1px; line-height: 1px; color: transparent;">
                    مرحباً ' . esc_html($trader->post_title) . '،

تم إنشاء حساب لك للوصول إلى لوحة تحكم التاجر.

بيانات الدخول:
البريد الإلكتروني: ' . esc_html($user->user_email) . '
' . sprintf($password_note, $password) . '

رابط تسجيل الدخول: ' . esc_url($login_url) . '

' . esc_html($password_warning) . '

مع تحياتنا،
' . esc_html(get_bloginfo('name')) . '
                </div>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        // Build headers array with custom email settings
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // From header with custom values or defaults
        $from_name = get_option('pt_email_from_name', get_bloginfo('name'));
        $from_email = get_option('pt_email_from_email', get_option('admin_email'));
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

        // Reply-To header (optional)
        $reply_to = get_option('pt_email_reply_to', '');
        if (!empty($reply_to) && is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        // CC header (optional, comma-separated)
        $cc = get_option('pt_email_cc', '');
        if (!empty($cc)) {
            $cc_emails = array_map('trim', explode(',', $cc));
            foreach ($cc_emails as $email) {
                if (!empty($email) && is_email($email)) {
                    $headers[] = 'Cc: ' . $email;
                }
            }
        }

        // BCC header (optional, comma-separated)
        $bcc = get_option('pt_email_bcc', '');
        if (!empty($bcc)) {
            $bcc_emails = array_map('trim', explode(',', $bcc));
            foreach ($bcc_emails as $email) {
                if (!empty($email) && is_email($email)) {
                    $headers[] = 'Bcc: ' . $email;
                }
            }
        }
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Add meta box for trader-user connection in admin
     */
    public function add_connection_meta_box() {
        add_meta_box(
            'pt_user_connection',
            __('ربط التاجر بمستخدم', 'profile-trader'),
            [$this, 'render_connection_meta_box'],
            PT_POST_TYPE,
            'side',
            'high'
        );
    }
    
    /**
     * Render the connection meta box
     */
    public function render_connection_meta_box($post) {
        wp_nonce_field('pt_connection_nonce', 'pt_connection_nonce');
        
        $connected_user_id = get_post_meta($post->ID, self::USER_META_KEY, true);
        $author_id = $post->post_author;
        $trader_email = get_post_meta($post->ID, 'email', true);
        
        // Get all users for dropdown
        $users = get_users(['orderby' => 'display_name']);
        ?>
        <div class="pt-connection-box">
            <p>
                <label for="pt_connected_user"><strong><?php _e('المستخدم المرتبط:', 'profile-trader'); ?></strong></label>
                <select name="pt_connected_user" id="pt_connected_user" style="width: 100%; margin-top: 5px;">
                    <option value=""><?php _e('-- اختر مستخدم --', 'profile-trader'); ?></option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user->ID; ?>" <?php selected($connected_user_id, $user->ID); ?>>
                            <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" name="pt_update_author" value="1">
                    <?php _e('تحديث الكاتب أيضاً', 'profile-trader'); ?>
                </label>
            </p>
            
            <hr>
            
            <p>
                <strong><?php _e('الكاتب الحالي:', 'profile-trader'); ?></strong><br>
                <?php 
                $author = get_user_by('id', $author_id);
                echo $author ? esc_html($author->display_name) : __('غير محدد', 'profile-trader');
                ?>
            </p>
            
            <?php if ($trader_email): ?>
            <p>
                <strong><?php _e('إيميل التاجر:', 'profile-trader'); ?></strong><br>
                <?php echo esc_html($trader_email); ?>
                
                <?php 
                $matching_user = get_user_by('email', $trader_email);
                if ($matching_user): 
                ?>
                    <br><span style="color: green;">
                        ✓ <?php printf(__('يوجد مستخدم بهذا الإيميل: %s', 'profile-trader'), $matching_user->display_name); ?>
                    </span>
                <?php else: ?>
                    <br><span style="color: orange;">
                        ⚠ <?php _e('لا يوجد مستخدم بهذا الإيميل', 'profile-trader'); ?>
                    </span>
                    <br>
                    <button type="button" class="button" onclick="ptCreateUserForTrader(<?php echo $post->ID; ?>)">
                        <?php _e('إنشاء حساب للتاجر', 'profile-trader'); ?>
                    </button>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
        
        <script>
        function ptCreateUserForTrader(traderId) {
            if (!confirm('<?php _e('هل تريد إنشاء حساب مستخدم لهذا التاجر؟', 'profile-trader'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'pt_create_user_for_trader',
                trader_id: traderId,
                nonce: '<?php echo wp_create_nonce('pt_create_user'); ?>'
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('حدث خطأ', 'profile-trader'); ?>');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save connection meta box data
     */
    public function save_connection_meta($post_id) {
        if (!isset($_POST['pt_connection_nonce']) || 
            !wp_verify_nonce($_POST['pt_connection_nonce'], 'pt_connection_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $user_id = isset($_POST['pt_connected_user']) ? intval($_POST['pt_connected_user']) : 0;
        $update_author = isset($_POST['pt_update_author']) && $_POST['pt_update_author'] === '1';
        
        if ($user_id) {
            $method = $update_author ? 'both' : 'meta';
            $this->connect_trader_to_user($post_id, $user_id, $method);
        } else {
            $this->disconnect_trader($post_id);
        }
    }
    
    /**
     * Add user column to traders list
     */
    public function add_user_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['connected_user'] = __('المستخدم المرتبط', 'profile-trader');
            }
        }
        return $new_columns;
    }
    
    /**
     * Render user column content
     */
    public function render_user_column($column, $post_id) {
        if ($column !== 'connected_user') {
            return;
        }
        
        $connected_user_id = get_post_meta($post_id, self::USER_META_KEY, true);
        $post = get_post($post_id);
        
        if ($connected_user_id) {
            $user = get_user_by('id', $connected_user_id);
            if ($user) {
                echo '<span style="color: green;">✓</span> ';
                echo esc_html($user->display_name);
                echo '<br><small>' . esc_html($user->user_email) . '</small>';
                return;
            }
        }
        
        // Check by author
        if ($post->post_author) {
            $author = get_user_by('id', $post->post_author);
            if ($author && $author->ID > 0) {
                echo '<span style="color: blue;">👤</span> ';
                echo esc_html($author->display_name);
                echo '<br><small>(الكاتب)</small>';
                return;
            }
        }
        
        // Check by email
        $trader_email = get_post_meta($post_id, 'email', true);
        if ($trader_email) {
            $user = get_user_by('email', $trader_email);
            if ($user) {
                echo '<span style="color: orange;">📧</span> ';
                echo esc_html($user->display_name);
                echo '<br><small>(تطابق الإيميل)</small>';
                return;
            }
        }
        
        echo '<span style="color: gray;">—</span>';
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions($actions) {
        $actions['pt_auto_connect'] = __('ربط تلقائي بالإيميل', 'profile-trader');
        $actions['pt_create_users'] = __('إنشاء حسابات للتجار', 'profile-trader');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_url, $action, $post_ids) {
        if ($action === 'pt_auto_connect') {
            $connected = 0;
            foreach ($post_ids as $post_id) {
                $email = get_post_meta($post_id, 'email', true);
                if ($email) {
                    $user = get_user_by('email', $email);
                    if ($user) {
                        $this->connect_trader_to_user($post_id, $user->ID, 'meta');
                        $connected++;
                    }
                }
            }
            $redirect_url = add_query_arg('pt_connected', $connected, $redirect_url);
        }
        
        if ($action === 'pt_create_users') {
            $created = 0;
            foreach ($post_ids as $post_id) {
                $result = $this->create_user_for_trader($post_id);
                if (!is_wp_error($result)) {
                    $created++;
                }
            }
            $redirect_url = add_query_arg('pt_users_created', $created, $redirect_url);
        }
        
        return $redirect_url;
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if (isset($_GET['pt_connected'])) {
            $count = intval($_GET['pt_connected']);
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(__('تم ربط %d تاجر بنجاح', 'profile-trader'), $count)
            );
        }
        
        if (isset($_GET['pt_users_created'])) {
            $count = intval($_GET['pt_users_created']);
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(__('تم إنشاء %d حساب مستخدم', 'profile-trader'), $count)
            );
        }
    }
    
    /**
     * AJAX: Connect trader to user
     */
    public function ajax_connect_trader() {
        check_ajax_referer('pt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
        }
        
        $trader_id = intval($_POST['trader_id'] ?? 0);
        $user_id = intval($_POST['user_id'] ?? 0);
        
        $result = $this->connect_trader_to_user($trader_id, $user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('تم الربط بنجاح', 'profile-trader')]);
    }
    
    /**
     * AJAX: Disconnect trader
     */
    public function ajax_disconnect_trader() {
        check_ajax_referer('pt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
        }
        
        $trader_id = intval($_POST['trader_id'] ?? 0);
        
        $this->disconnect_trader($trader_id);
        
        wp_send_json_success(['message' => __('تم إلغاء الربط', 'profile-trader')]);
    }
    
    /**
     * AJAX: Auto connect by email
     */
    public function ajax_auto_connect_by_email() {
        check_ajax_referer('pt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
        }

        $stats = $this->auto_connect_by_email();

        wp_send_json_success([
            'message' => sprintf(
                __('تم ربط %d تاجر، %d مرتبط مسبقاً، %d بدون تطابق', 'profile-trader'),
                $stats['connected'],
                $stats['already_connected'],
                $stats['no_match']
            ),
            'stats' => $stats,
            'logs' => $stats['logs'],
            'total_processed' => count($stats['logs']),
        ]);
    }
}

// Add AJAX handler for creating user
add_action('wp_ajax_pt_create_user_for_trader', function() {
    check_ajax_referer('pt_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }
    
    $trader_id = intval($_POST['trader_id'] ?? 0);
    $use_default_password = isset($_POST['use_default_password']) && intval($_POST['use_default_password']) === 1;
    
    if (!$trader_id) {
        wp_send_json_error(['message' => __('معرف التاجر غير صالح', 'profile-trader')]);
    }
    
    $connection = PT_Trader_Connection::get_instance();
    $result = $connection->create_user_for_trader($trader_id, true, $use_default_password);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    $message = __('تم إنشاء الحساب وإرسال بيانات الدخول للتاجر', 'profile-trader');
    if ($use_default_password) {
        $default_password = get_option('pt_default_user_password', '');
        if ($default_password) {
            $message .= sprintf(__('\nكلمة المرور الافتراضية: %s', 'profile-trader'), $default_password);
        }
    }
    
    wp_send_json_success([
        'message' => $message,
        'user_id' => $result,
    ]);
});

// Add AJAX handler for bulk creating users
add_action('wp_ajax_pt_bulk_create_users', function() {
    check_ajax_referer('pt_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }

    $use_default_password = isset($_POST['use_default_password']) && intval($_POST['use_default_password']) === 1;
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 0;
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    $connection = PT_Trader_Connection::get_instance();

    global $wpdb;

    // Step 1: Get total count (for progress calculation)
    $total_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'email'
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_trader_user_id'
        LEFT JOIN {$wpdb->users} u ON LOWER(pm.meta_value) = LOWER(u.user_email)
        WHERE p.post_type = %s
        AND p.post_status = 'publish'
        AND pm.meta_value != ''
        AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
        AND u.ID IS NULL
    ", PT_POST_TYPE));

    // Step 2: Get batch of traders (or all if batch_size = 0)
    $limit_clause = ($batch_size > 0) ? $wpdb->prepare("LIMIT %d OFFSET %d", $batch_size, $offset) : "";

    $traders = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, pm.meta_value as email
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'email'
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_trader_user_id'
        LEFT JOIN {$wpdb->users} u ON LOWER(pm.meta_value) = LOWER(u.user_email)
        WHERE p.post_type = %s
        AND p.post_status = 'publish'
        AND pm.meta_value != ''
        AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
        AND u.ID IS NULL
        ORDER BY p.post_title ASC
        {$limit_clause}
    ", PT_POST_TYPE));

    // Step 3: Handle empty batch
    if (empty($traders)) {
        wp_send_json_success([
            'message' => __('لا يوجد تجار يحتاجون إلى إنشاء حسابات', 'profile-trader'),
            'created' => 0,
            'total' => $total_count,
            'processed' => $offset,
            'remaining' => 0,
            'complete' => true,
            'errors' => [],
        ]);
        return;
    }

    // Step 4: Process batch
    $created = 0;
    $skipped = 0;
    $errors = [];

    foreach ($traders as $trader) {
        $result = $connection->create_user_for_trader($trader->ID, true, $use_default_password);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();

            // Skip if already connected or user exists
            if (in_array($error_code, ['already_connected'])) {
                $skipped++;
                continue;
            }

            $errors[] = sprintf(
                __('%s (ID: %d): %s', 'profile-trader'),
                esc_html($trader->post_title),
                $trader->ID,
                $result->get_error_message()
            );
        } else {
            $created++;
        }
    }

    // Step 5: Calculate progress
    $processed = $offset + count($traders);
    $remaining = max(0, $total_count - $processed);
    $complete = ($remaining === 0);

    // Step 6: Build message
    $message = sprintf(
        __('تم إنشاء %d حساب من %d', 'profile-trader'),
        $created,
        $total_count
    );

    if ($skipped > 0) {
        $message .= sprintf(__('، تم تخطي %d', 'profile-trader'), $skipped);
    }

    if ($use_default_password && $complete) {
        $default_password = get_option('pt_default_user_password', '');
        if ($default_password) {
            $message .= sprintf(
                __('\n\nكلمة المرور الافتراضية: %s', 'profile-trader'),
                $default_password
            );
        }
    }

    // Step 7: Return response with progress data
    wp_send_json_success([
        'message' => $message,
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => $total_count,
        'processed' => $processed,
        'remaining' => $remaining,
        'complete' => $complete,
    ]);
});

// Add AJAX handler for regenerating default password
add_action('wp_ajax_pt_regenerate_default_password', function() {
    check_ajax_referer('pt_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }
    
    // Generate new secure default password
    $new_password = wp_generate_password(12, true, true);
    update_option('pt_default_user_password', $new_password);
    
    wp_send_json_success([
        'message' => __('تم إنشاء كلمة مرور افتراضية جديدة', 'profile-trader'),
        'password' => $new_password,
    ]);
});

// Add AJAX handler for adding database indexes
add_action('wp_ajax_pt_add_indexes', function() {
    check_ajax_referer('pt_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }

    $results = PT_Trader_Connection::add_database_indexes();

    $success_count = 0;
    $messages = [];

    foreach ($results as $result) {
        if ($result['success']) {
            $success_count++;
            $messages[] = __('✓ تم إضافة الفهرس بنجاح', 'profile-trader');
        } else {
            $messages[] = sprintf(__('✗ خطأ: %s', 'profile-trader'), $result['error'] ?: __('فشل إضافة الفهرس', 'profile-trader'));
        }
    }

    if (empty($results)) {
        wp_send_json_success([
            'message' => __('✓ جميع الفهارس موجودة مسبقاً!', 'profile-trader'),
            'details' => $messages,
        ]);
    } elseif ($success_count === count($results)) {
        wp_send_json_success([
            'message' => sprintf(__('✓ تم إضافة %d فهرس بنجاح!', 'profile-trader'), $success_count),
            'details' => $messages,
        ]);
    } else {
        wp_send_json_error([
            'message' => sprintf(__('تم إضافة %d من %d فهرس', 'profile-trader'), $success_count, count($results)),
            'details' => $messages,
        ]);
    }
});

// AJAX handler for fetching unconnected traders with search and pagination
add_action('wp_ajax_pt_get_unconnected_traders', function() {
    check_ajax_referer('pt_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }

    global $wpdb;

    // Get parameters
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    // Calculate offset
    $offset = ($page - 1) * $per_page;

    // Build search condition for name AND email
    $search_condition = '';
    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $search_condition = $wpdb->prepare(
            " AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)",
            $search_like,
            $search_like
        );
    }

    // Get total count (for pagination)
    $total_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'email'
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_trader_user_id'
        WHERE p.post_type = %s
        AND p.post_status = 'publish'
        AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
        {$search_condition}
    ", PT_POST_TYPE));

    // Get paginated traders
    $traders = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, pm.meta_value as email
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'email'
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_trader_user_id'
        WHERE p.post_type = %s
        AND p.post_status = 'publish'
        AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
        {$search_condition}
        ORDER BY p.post_title ASC
        LIMIT %d OFFSET %d
    ", PT_POST_TYPE, $per_page, $offset));

    // Calculate pagination
    $total_pages = ceil($total_count / $per_page);

    // Build HTML for table rows
    if (empty($traders)) {
        $html = '<tr><td colspan="4" style="text-align: center; padding: 20px;">';
        if (!empty($search)) {
            $html .= __('لا توجد نتائج للبحث', 'profile-trader');
        } else {
            $html .= '<span class="pt-success">' . __('جميع التجار مرتبطون!', 'profile-trader') . '</span>';
        }
        $html .= '</td></tr>';

        wp_send_json_success([
            'html' => $html,
            'total' => 0,
            'total_pages' => 0,
            'current_page' => 1,
        ]);
        return;
    }

    // Build table rows
    $html = '';
    foreach ($traders as $trader) {
        $matching_user = $trader->email ? get_user_by('email', $trader->email) : null;
        $edit_link = get_edit_post_link($trader->ID);

        $html .= '<tr>';

        // Column 1: Trader name (clickable link)
        $html .= '<td><a href="' . esc_url($edit_link) . '">';
        $html .= esc_html($trader->post_title);
        $html .= '</a></td>';

        // Column 2: Email
        $html .= '<td>' . esc_html($trader->email ?: '—') . '</td>';

        // Column 3: Status
        $html .= '<td>';
        if ($matching_user) {
            $html .= '<span style="color: green;">✓ ';
            $html .= sprintf(__('يوجد مستخدم: %s', 'profile-trader'), $matching_user->display_name);
            $html .= '</span>';
        } elseif ($trader->email) {
            $html .= '<span style="color: orange;">⚠ ';
            $html .= __('لا يوجد مستخدم بهذا الإيميل', 'profile-trader');
            $html .= '</span>';
        } else {
            $html .= '<span style="color: gray;">— ';
            $html .= __('لا يوجد إيميل', 'profile-trader');
            $html .= '</span>';
        }
        $html .= '</td>';

        // Column 4: Actions (Connect/Create + Edit button)
        $html .= '<td>';
        if ($matching_user) {
            $html .= '<button type="button" class="button button-small pt-quick-connect" ';
            $html .= 'data-trader="' . $trader->ID . '" data-user="' . $matching_user->ID . '">';
            $html .= __('ربط', 'profile-trader') . '</button> ';
        } elseif ($trader->email) {
            $html .= '<button type="button" class="button button-small pt-create-user-btn" ';
            $html .= 'data-trader="' . $trader->ID . '">';
            $html .= __('إنشاء حساب', 'profile-trader') . '</button> ';
        }
        // NEW: Edit button (always shown)
        $html .= '<a href="' . esc_url($edit_link) . '" class="button button-small">';
        $html .= __('تعديل', 'profile-trader') . '</a>';
        $html .= '</td>';

        $html .= '</tr>';
    }

    wp_send_json_success([
        'html' => $html,
        'total' => $total_count,
        'total_pages' => $total_pages,
        'current_page' => $page,
    ]);
});

// AJAX handler for saving email settings
add_action('wp_ajax_pt_save_email_settings', function() {
    check_ajax_referer('pt_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }

    // Get and sanitize inputs
    $from_name = sanitize_text_field($_POST['from_name'] ?? '');
    $from_email = sanitize_email($_POST['from_email'] ?? '');
    $reply_to = sanitize_email($_POST['reply_to'] ?? '');
    $cc = sanitize_text_field($_POST['cc'] ?? '');
    $bcc = sanitize_text_field($_POST['bcc'] ?? '');

    // Validate required fields
    if (empty($from_name)) {
        wp_send_json_error(['message' => __('اسم المرسل مطلوب', 'profile-trader')]);
    }

    if (empty($from_email) || !is_email($from_email)) {
        wp_send_json_error(['message' => __('إيميل المرسل غير صالح', 'profile-trader')]);
    }

    // Validate optional email fields
    if (!empty($reply_to) && !is_email($reply_to)) {
        wp_send_json_error(['message' => __('عنوان الرد غير صالح', 'profile-trader')]);
    }

    // Validate CC addresses
    if (!empty($cc)) {
        $cc_emails = array_map('trim', explode(',', $cc));
        foreach ($cc_emails as $email) {
            if (!empty($email) && !is_email($email)) {
                wp_send_json_error([
                    'message' => sprintf(__('عنوان CC غير صالح: %s', 'profile-trader'), $email)
                ]);
            }
        }
    }

    // Validate BCC addresses
    if (!empty($bcc)) {
        $bcc_emails = array_map('trim', explode(',', $bcc));
        foreach ($bcc_emails as $email) {
            if (!empty($email) && !is_email($email)) {
                wp_send_json_error([
                    'message' => sprintf(__('عنوان BCC غير صالح: %s', 'profile-trader'), $email)
                ]);
            }
        }
    }

    // Save options
    update_option('pt_email_from_name', $from_name);
    update_option('pt_email_from_email', $from_email);
    update_option('pt_email_reply_to', $reply_to);
    update_option('pt_email_cc', $cc);
    update_option('pt_email_bcc', $bcc);

    wp_send_json_success([
        'message' => __('تم حفظ إعدادات البريد الإلكتروني بنجاح', 'profile-trader')
    ]);
});

// AJAX handler for resetting email settings
add_action('wp_ajax_pt_reset_email_settings', function() {
    check_ajax_referer('pt_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }

    // Delete all email options to revert to defaults
    delete_option('pt_email_from_name');
    delete_option('pt_email_from_email');
    delete_option('pt_email_reply_to');
    delete_option('pt_email_cc');
    delete_option('pt_email_bcc');

    wp_send_json_success([
        'message' => __('تم إعادة تعيين الإعدادات للافتراضي', 'profile-trader'),
        'defaults' => [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'reply_to' => '',
            'cc' => '',
            'bcc' => ''
        ]
    ]);
});

// AJAX handler for searching traders (for manual connection)
add_action('wp_ajax_pt_search_traders', function() {
    check_ajax_referer('pt_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }

    global $wpdb;

    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;

    if (strlen($search) < 2) {
        wp_send_json_success(['results' => []]);
    }

    $search_like = '%' . $wpdb->esc_like($search) . '%';

    // Search by post_title (name), email (meta), or commercial_register (meta)
    $traders = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT p.ID, p.post_title,
               pm_email.meta_value as email,
               pm_cr.meta_value as commercial_register
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'email'
        LEFT JOIN {$wpdb->postmeta} pm_cr ON p.ID = pm_cr.post_id AND pm_cr.meta_key = 'commercial_register'
        WHERE p.post_type = %s
        AND p.post_status IN ('publish', 'draft', 'pending')
        AND (
            p.post_title LIKE %s
            OR pm_email.meta_value LIKE %s
            OR pm_cr.meta_value LIKE %s
        )
        ORDER BY p.post_title ASC
        LIMIT %d
    ", PT_POST_TYPE, $search_like, $search_like, $search_like, $limit));

    $results = [];
    foreach ($traders as $trader) {
        $display_text = $trader->post_title;
        if ($trader->email) {
            $display_text .= ' (' . $trader->email . ')';
        }

        $results[] = [
            'id' => $trader->ID,
            'text' => $display_text,
            'name' => $trader->post_title,
            'email' => $trader->email ?: '',
            'commercial_register' => $trader->commercial_register ?: '',
        ];
    }

    wp_send_json_success(['results' => $results]);
});

// AJAX handler for searching users (for manual connection)
add_action('wp_ajax_pt_search_users', function() {
    check_ajax_referer('pt_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
    }

    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;

    if (strlen($search) < 2) {
        wp_send_json_success(['results' => []]);
    }

    // Search users by display_name, user_email, or user_login
    $users = get_users([
        'search' => '*' . $search . '*',
        'search_columns' => ['display_name', 'user_email', 'user_login'],
        'number' => $limit,
        'orderby' => 'display_name',
        'order' => 'ASC',
    ]);

    $results = [];
    foreach ($users as $user) {
        $results[] = [
            'id' => $user->ID,
            'text' => $user->display_name . ' (' . $user->user_email . ')',
            'name' => $user->display_name,
            'email' => $user->user_email,
        ];
    }

    wp_send_json_success(['results' => $results]);
});

// Initialize
PT_Trader_Connection::get_instance();

