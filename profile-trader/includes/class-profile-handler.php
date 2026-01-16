<?php
/**
 * Profile Handler Class
 * Handles user profile updates
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

class PT_Profile_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_post_pt_update_profile', [$this, 'handle_profile_update']);
        add_action('admin_post_nopriv_pt_update_profile', [$this, 'redirect_to_login']);
    }
    
    /**
     * Handle profile update form submission
     */
    public function handle_profile_update() {
        // Verify nonce
        if (!isset($_POST['pt_profile_nonce']) || !wp_verify_nonce($_POST['pt_profile_nonce'], 'pt_update_profile')) {
            wp_die(__('Security check failed', 'profile-trader'));
        }
        
        // Get current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            $this->redirect_to_login();
            return;
        }
        
        $errors = [];
        
        // Validate and update user data
        $userdata = [
            'ID' => $user_id,
        ];
        
        // First name
        if (isset($_POST['first_name'])) {
            $userdata['first_name'] = sanitize_text_field($_POST['first_name']);
        }
        
        // Last name
        if (isset($_POST['last_name'])) {
            $userdata['last_name'] = sanitize_text_field($_POST['last_name']);
        }
        
        // Display name
        if (isset($_POST['display_name']) && !empty($_POST['display_name'])) {
            $userdata['display_name'] = sanitize_text_field($_POST['display_name']);
        }
        
        // Email
        if (isset($_POST['user_email']) && !empty($_POST['user_email'])) {
            $email = sanitize_email($_POST['user_email']);
            
            if (!is_email($email)) {
                $errors[] = __('البريد الإلكتروني غير صالح', 'profile-trader');
            } else {
                // Check if email is already used by another user
                $existing_user = get_user_by('email', $email);
                if ($existing_user && $existing_user->ID !== $user_id) {
                    $errors[] = __('هذا البريد الإلكتروني مستخدم بالفعل', 'profile-trader');
                } else {
                    $userdata['user_email'] = $email;
                }
            }
        }
        
        // Description
        if (isset($_POST['description'])) {
            $userdata['description'] = sanitize_textarea_field($_POST['description']);
        }
        
        // Password change
        if (!empty($_POST['new_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Verify current password
            $user = get_user_by('id', $user_id);
            if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
                $errors[] = __('كلمة المرور الحالية غير صحيحة', 'profile-trader');
            } elseif ($new_password !== $confirm_password) {
                $errors[] = __('كلمة المرور الجديدة غير متطابقة', 'profile-trader');
            } elseif (strlen($new_password) < 8) {
                $errors[] = __('كلمة المرور يجب أن تكون 8 أحرف على الأقل', 'profile-trader');
            } else {
                $userdata['user_pass'] = $new_password;
            }
        }
        
        // If there are errors, redirect back with error message
        if (!empty($errors)) {
            $redirect_url = add_query_arg([
                'tab' => 'profile',
                'error' => urlencode(implode('|', $errors)),
            ], $this->get_dashboard_url());
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // Update user
        $result = wp_update_user($userdata);
        
        // Log redirect URL for debugging
        $dashboard_url = $this->get_dashboard_url();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Profile Trader] Dashboard URL resolved to: ' . $dashboard_url);
        }

        if (is_wp_error($result)) {
            $redirect_url = add_query_arg([
                'tab' => 'profile',
                'error' => urlencode($result->get_error_message()),
            ], $dashboard_url);
        } else {
            $redirect_url = add_query_arg([
                'tab' => 'profile',
                'profile_updated' => '1',
            ], $dashboard_url);
        }

        // Log final redirect URL
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Profile Trader] Redirecting to: ' . $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Redirect to login page
     */
    public function redirect_to_login() {
        $dashboard_url = $this->get_dashboard_url();
        $login_url = PT_Trader_Connection::get_custom_login_url($dashboard_url);
        wp_redirect($login_url);
        exit;
    }
    
    /**
     * Get dashboard URL
     */
    private function get_dashboard_url() {
        // First, try to get from referer to preserve the current page
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer = esc_url_raw($_SERVER['HTTP_REFERER']);
            // Remove existing query params to get clean base URL
            $base_url = strtok($referer, '?');

            // Verify it's a valid URL on our site
            if (strpos($base_url, home_url()) === 0) {
                return $base_url;
            }
        }

        // Fallback: Try to find the page by slug
        $page = get_page_by_path('trader-dashboard');
        if ($page) {
            return get_permalink($page);
        }

        // Last resort: Find any page with the [trader_dashboard] shortcode
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_content LIKE '%[trader_dashboard]%'
             AND post_status = 'publish'
             AND post_type = 'page'
             LIMIT 1"
        );

        if ($page_id) {
            return get_permalink($page_id);
        }

        // Final fallback
        return home_url();
    }
}

// Initialize
PT_Profile_Handler::get_instance();

