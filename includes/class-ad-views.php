<?php
/**
 * Ad Views Tracking Class
 * Handles view counting and analytics for ads and traders
 *
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

class PT_Ad_Views {

    private static $instance = null;

    const TABLE_NAME = 'pt_ad_views';
    const META_TOTAL_VIEWS = '_pt_total_views';

    // Bot patterns to filter out
    private $bot_patterns = [
        'bot', 'crawl', 'spider', 'slurp', 'googlebot',
        'bingbot', 'yahoo', 'baidu', 'yandex', 'duckduck',
        'facebot', 'ia_archiver', 'semrush', 'ahrefs',
        'mj12bot', 'dotbot', 'petalbot', 'bytespider'
    ];

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Check and create table if needed
        add_action('admin_init', [$this, 'maybe_create_table']);

        // AJAX handlers for tracking
        add_action('wp_ajax_pt_track_ad_view', [$this, 'ajax_track_view']);
        add_action('wp_ajax_nopriv_pt_track_ad_view', [$this, 'ajax_track_view']);

        // Admin hooks
        add_action('admin_menu', [$this, 'add_analytics_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_analytics_scripts']);

        // AJAX for analytics data
        add_action('wp_ajax_pt_get_view_analytics', [$this, 'ajax_get_analytics']);
        add_action('wp_ajax_pt_get_ad_views_chart', [$this, 'ajax_get_chart_data']);
        add_action('wp_ajax_pt_get_top_posts', [$this, 'ajax_get_top_posts']);

        // Register shortcode
        add_shortcode('pt_view_count', [$this, 'view_count_shortcode']);

        // Note: Tracking script is now added directly in templates (single-ad.php, trader-profile.php)
    }

    /**
     * Check and create table if it doesn't exist
     */
    public function maybe_create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if (!$table_exists) {
            self::create_table();
        }
    }

    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            post_type VARCHAR(50) NOT NULL DEFAULT 'ads',
            view_date DATE NOT NULL,
            view_time TIME NOT NULL,
            ip_hash VARCHAR(64),
            user_agent_hash VARCHAR(64),
            referrer VARCHAR(255),
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            INDEX idx_post_id (post_id),
            INDEX idx_view_date (view_date),
            INDEX idx_post_date (post_id, view_date),
            INDEX idx_post_type (post_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check if request is from a bot
     */
    private function is_bot() {
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (empty($user_agent)) {
            return true;
        }

        foreach ($this->bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get hashed client IP for privacy
     */
    private function get_client_ip_hash() {
        $ip = '';

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return hash('sha256', trim($ip) . wp_salt('auth'));
    }

    /**
     * Check if view should be counted
     */
    private function should_count_view($post_id, $post_type = 'ads') {
        // Skip bots
        if ($this->is_bot()) {
            return false;
        }

        // Skip admin users
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return false;
        }

        // Check for recent view from same IP (30 minute window)
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $ip_hash = $this->get_client_ip_hash();

        $recent_view = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name
             WHERE post_id = %d
             AND ip_hash = %s
             AND CONCAT(view_date, ' ', view_time) > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
             LIMIT 1",
            $post_id,
            $ip_hash
        ));

        return empty($recent_view);
    }

    /**
     * Track a view
     */
    public function track_view($post_id, $post_type = 'ads') {
        if (!$this->should_count_view($post_id, $post_type)) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->insert(
            $table_name,
            [
                'post_id' => $post_id,
                'post_type' => $post_type,
                'view_date' => current_time('Y-m-d'),
                'view_time' => current_time('H:i:s'),
                'ip_hash' => $this->get_client_ip_hash(),
                'user_agent_hash' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
                'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(substr($_SERVER['HTTP_REFERER'], 0, 255)) : '',
                'user_id' => get_current_user_id()
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        // Update total views meta
        if ($result) {
            $total = $this->get_total_views($post_id);
            update_post_meta($post_id, self::META_TOTAL_VIEWS, $total);
        }

        return $result;
    }

    /**
     * AJAX handler for tracking views
     */
    public function ajax_track_view() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pt_view_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'ads';

        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }

        // Verify post exists and is valid type
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, ['ads', 'trader'])) {
            wp_send_json_error(['message' => 'Invalid post']);
        }

        $result = $this->track_view($post_id, $post->post_type);

        if ($result) {
            wp_send_json_success(['message' => 'View tracked', 'views' => $this->get_total_views($post_id)]);
        } else {
            wp_send_json_success(['message' => 'View not counted (duplicate or filtered)']);
        }
    }

    /**
     * Get total views for a post
     */
    public function get_total_views($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d",
            $post_id
        ));
    }

    /**
     * Get views for a date range
     */
    public function get_views_by_date_range($start_date, $end_date, $post_type = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $sql = "SELECT view_date, COUNT(*) as views
                FROM $table_name
                WHERE view_date BETWEEN %s AND %s";

        $params = [$start_date, $end_date];

        if ($post_type) {
            $sql .= " AND post_type = %s";
            $params[] = $post_type;
        }

        $sql .= " GROUP BY view_date ORDER BY view_date ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get top viewed posts
     */
    public function get_top_posts($limit = 10, $days = 30, $post_type = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $sql = "SELECT v.post_id, v.post_type, COUNT(*) as views,
                       SUM(CASE WHEN v.view_date = CURDATE() THEN 1 ELSE 0 END) as views_today
                FROM $table_name v
                INNER JOIN {$wpdb->posts} p ON v.post_id = p.ID
                WHERE v.view_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                AND p.post_status = 'publish'";

        $params = [$days];

        if ($post_type) {
            $sql .= " AND v.post_type = %s";
            $params[] = $post_type;
        }

        $sql .= " GROUP BY v.post_id, v.post_type
                  ORDER BY views DESC
                  LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get stats summary
     */
    public function get_stats_summary() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('-7 days'));
        $month_start = date('Y-m-d', strtotime('-30 days'));

        return [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'today' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE view_date = %s",
                $today
            )),
            'week' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE view_date >= %s",
                $week_start
            )),
            'month' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE view_date >= %s",
                $month_start
            ))
        ];
    }

    /**
     * Shortcode for displaying view count
     * Usage: [pt_view_count] or [pt_view_count id="123"] or [pt_view_count icon="true"]
     */
    public function view_count_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'icon' => 'true'
        ], $atts);

        $post_id = intval($atts['id']);
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (!$post_id) {
            return '';
        }

        $views = $this->get_total_views($post_id);
        $show_icon = $atts['icon'] === 'true';

        $icon_svg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 5px;">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </svg>';

        $output = '<span class="pt-view-count">';
        if ($show_icon) {
            $output .= $icon_svg;
        }
        $output .= '<span class="pt-view-number">' . number_format_i18n($views) . '</span>';
        $output .= '</span>';

        return $output;
    }

    /**
     * Enqueue tracking script on frontend
     */
    public function enqueue_tracking_script() {
        // Only on single ads or trader pages
        if (!is_singular(['ads', 'trader'])) {
            // Check if we're on a page with the shortcode
            global $post;
            if (!$post) {
                return;
            }

            // Check for single_ad or trader_profile shortcode
            if (!has_shortcode($post->post_content, 'single_ad') &&
                !has_shortcode($post->post_content, 'trader_profile')) {
                return;
            }
        }

        $post_id = 0;
        $post_type = 'ads';

        // Try to get post ID from shortcode context
        if (is_singular('ads')) {
            $post_id = get_the_ID();
            $post_type = 'ads';
        } elseif (is_singular('trader')) {
            $post_id = get_the_ID();
            $post_type = 'trader';
        } elseif (isset($_GET['ad_id'])) {
            $post_id = intval($_GET['ad_id']);
            $post_type = 'ads';
        } elseif (isset($_GET['trader_id'])) {
            $post_id = intval($_GET['trader_id']);
            $post_type = 'trader';
        }

        if (!$post_id) {
            return;
        }

        $nonce = wp_create_nonce('pt_view_nonce');
        ?>
        <script>
        (function() {
            var tracked = false;
            function trackView() {
                if (tracked) return;
                tracked = true;

                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('action=pt_track_ad_view&post_id=<?php echo $post_id; ?>&post_type=<?php echo $post_type; ?>&nonce=<?php echo $nonce; ?>');
            }

            // Track after 1 second delay to ensure real page view
            setTimeout(trackView, 1000);
        })();
        </script>
        <?php
    }

    /**
     * Add analytics menu
     */
    public function add_analytics_menu() {
        add_submenu_page(
            'edit.php?post_type=ads',
            __('إحصائيات المشاهدات', 'profile-trader'),
            __('الإحصائيات', 'profile-trader'),
            'manage_options',
            'pt-ad-analytics',
            [$this, 'render_analytics_page']
        );
    }

    /**
     * Enqueue analytics scripts
     */
    public function enqueue_analytics_scripts($hook) {
        if (strpos($hook, 'pt-ad-analytics') === false) {
            return;
        }

        wp_enqueue_style('pt-admin', PT_PLUGIN_URL . 'profile-trader/assets/css/admin.css', [], PT_VERSION);
        wp_enqueue_script('pt-admin', PT_PLUGIN_URL . 'profile-trader/assets/js/admin.js', ['jquery'], PT_VERSION, true);

        // Chart.js
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], '4.4.1', true);

        wp_localize_script('pt-admin', 'ptAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pt_nonce'),
        ]);
    }

    /**
     * AJAX handler for chart data
     */
    public function ajax_get_chart_data() {
        check_ajax_referer('pt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
        }

        $period = isset($_POST['period']) ? intval($_POST['period']) : 30;
        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : null;

        if ($post_type === 'all') {
            $post_type = null;
        }

        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$period} days"));

        $data = $this->get_views_by_date_range($start_date, $end_date, $post_type);

        // Fill in missing dates
        $labels = [];
        $values = [];
        $date_views = [];

        foreach ($data as $row) {
            $date_views[$row->view_date] = (int) $row->views;
        }

        for ($i = $period; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date_i18n('j M', strtotime($date));
            $values[] = isset($date_views[$date]) ? $date_views[$date] : 0;
        }

        wp_send_json_success([
            'labels' => $labels,
            'values' => $values
        ]);
    }

    /**
     * AJAX handler for top posts
     */
    public function ajax_get_top_posts() {
        check_ajax_referer('pt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
        }

        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : null;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;

        if ($post_type === 'all') {
            $post_type = null;
        }

        $posts = $this->get_top_posts($per_page, $days, $post_type);

        $results = [];
        foreach ($posts as $row) {
            $post = get_post($row->post_id);
            if (!$post) continue;

            $results[] = [
                'id' => $row->post_id,
                'title' => $post->post_title,
                'type' => $row->post_type === 'trader' ? __('تاجر', 'profile-trader') : __('إعلان', 'profile-trader'),
                'type_raw' => $row->post_type,
                'views' => (int) $row->views,
                'views_today' => (int) $row->views_today,
                'url' => get_permalink($row->post_id),
                'edit_url' => get_edit_post_link($row->post_id, 'raw')
            ];
        }

        wp_send_json_success($results);
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        $stats = $this->get_stats_summary();
        ?>
        <div class="wrap pt-admin-wrap pt-analytics-wrap">
            <h1><?php _e('إحصائيات المشاهدات', 'profile-trader'); ?></h1>

            <div class="pt-admin-grid">
                <!-- Stats Cards -->
                <div class="pt-admin-card pt-stats-card pt-full-width">
                    <h2><?php _e('ملخص الإحصائيات', 'profile-trader'); ?></h2>
                    <div class="pt-stats-grid pt-stats-4">
                        <div class="pt-stat">
                            <span class="pt-stat-number"><?php echo number_format_i18n($stats['total']); ?></span>
                            <span class="pt-stat-label"><?php _e('إجمالي المشاهدات', 'profile-trader'); ?></span>
                        </div>
                        <div class="pt-stat">
                            <span class="pt-stat-number"><?php echo number_format_i18n($stats['today']); ?></span>
                            <span class="pt-stat-label"><?php _e('مشاهدات اليوم', 'profile-trader'); ?></span>
                        </div>
                        <div class="pt-stat">
                            <span class="pt-stat-number"><?php echo number_format_i18n($stats['week']); ?></span>
                            <span class="pt-stat-label"><?php _e('هذا الأسبوع', 'profile-trader'); ?></span>
                        </div>
                        <div class="pt-stat">
                            <span class="pt-stat-number"><?php echo number_format_i18n($stats['month']); ?></span>
                            <span class="pt-stat-label"><?php _e('هذا الشهر', 'profile-trader'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Chart Section -->
                <div class="pt-admin-card pt-full-width">
                    <div class="pt-analytics-header">
                        <h2><?php _e('المشاهدات خلال الفترة', 'profile-trader'); ?></h2>
                        <div class="pt-analytics-filters">
                            <select id="pt-analytics-post-type" class="pt-select">
                                <option value="all"><?php _e('الكل', 'profile-trader'); ?></option>
                                <option value="ads"><?php _e('الإعلانات', 'profile-trader'); ?></option>
                                <option value="trader"><?php _e('التجار', 'profile-trader'); ?></option>
                            </select>
                            <select id="pt-analytics-period" class="pt-select">
                                <option value="7"><?php _e('7 أيام', 'profile-trader'); ?></option>
                                <option value="14"><?php _e('14 يوم', 'profile-trader'); ?></option>
                                <option value="30" selected><?php _e('30 يوم', 'profile-trader'); ?></option>
                                <option value="90"><?php _e('90 يوم', 'profile-trader'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="pt-chart-container">
                        <canvas id="pt-views-chart"></canvas>
                    </div>
                </div>

                <!-- Top Posts Table -->
                <div class="pt-admin-card pt-full-width">
                    <div class="pt-analytics-header">
                        <h2><?php _e('الأكثر مشاهدة', 'profile-trader'); ?></h2>
                        <button type="button" class="button" id="pt-refresh-top-posts">
                            <?php _e('تحديث', 'profile-trader'); ?>
                        </button>
                    </div>
                    <div id="pt-top-posts-container">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 40%;"><?php _e('العنوان', 'profile-trader'); ?></th>
                                    <th style="width: 15%;"><?php _e('النوع', 'profile-trader'); ?></th>
                                    <th style="width: 15%;"><?php _e('المشاهدات', 'profile-trader'); ?></th>
                                    <th style="width: 15%;"><?php _e('اليوم', 'profile-trader'); ?></th>
                                    <th style="width: 15%;"><?php _e('إجراء', 'profile-trader'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="pt-top-posts-tbody">
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px;">
                                        <span class="spinner is-active" style="float: none;"></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- How it works -->
                <div class="pt-admin-card pt-info-card">
                    <h2><?php _e('كيف يعمل عداد المشاهدات؟', 'profile-trader'); ?></h2>
                    <ul>
                        <li>
                            <strong><?php _e('تتبع ذكي:', 'profile-trader'); ?></strong>
                            <?php _e('يتم احتساب الزائر الفريد مرة واحدة كل 30 دقيقة', 'profile-trader'); ?>
                        </li>
                        <li>
                            <strong><?php _e('فلترة الروبوتات:', 'profile-trader'); ?></strong>
                            <?php _e('يتم استبعاد محركات البحث والروبوتات تلقائياً', 'profile-trader'); ?>
                        </li>
                        <li>
                            <strong><?php _e('خصوصية:', 'profile-trader'); ?></strong>
                            <?php _e('يتم تشفير عناوين IP للحفاظ على خصوصية الزوار', 'profile-trader'); ?>
                        </li>
                        <li>
                            <strong><?php _e('الشورت كود:', 'profile-trader'); ?></strong>
                            <?php _e('استخدم [pt_view_count] لعرض عدد المشاهدات في أي صفحة', 'profile-trader'); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize
PT_Ad_Views::get_instance();
