<?php
/**
 * Plugin Name: Profile Trader Dashboard
 * Plugin URI: https://muhammadaslan.com/profile-trader
 * Description: Frontend dashboard for traders to manage their listings (دليل التجار)
 * Version: 2.0.0
 * Author: Muhammed Aslan
 * Text Domain: profile-trader
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PT_VERSION', '2.0.0');
define('PT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PT_POST_TYPE', 'trader');

// Include additional files (only if they exist) - Load early so classes are available
// Check both root and nested includes directories
$includes_files = [
    'class-profile-handler.php',
    'class-trader-connection.php',
    'class-admin-settings.php',
    'class-ad-views.php'
];

foreach ($includes_files as $file) {
    // Check root includes directory first
    $root_path = PT_PLUGIN_DIR . 'includes/' . $file;
    // Check nested profile-trader/includes directory
    $nested_path = PT_PLUGIN_DIR . 'profile-trader/includes/' . $file;
    
    if (file_exists($root_path)) {
        require_once $root_path;
    } elseif (file_exists($nested_path)) {
        require_once $nested_path;
    }
}

/**
 * Helper function to get asset file path (checks multiple locations)
 */
function pt_get_asset_path($relative_path) {
    // Check root assets directory first
    $root_path = PT_PLUGIN_DIR . $relative_path;
    if (file_exists($root_path)) {
        return $root_path;
    }
    // Check nested profile-trader/assets directory
    $nested_path = PT_PLUGIN_DIR . 'profile-trader/' . $relative_path;
    if (file_exists($nested_path)) {
        return $nested_path;
    }
    // Return root path as fallback (will be used for URL even if file doesn't exist)
    return $root_path;
}

/**
 * Helper function to get asset file URL (checks multiple locations)
 */
function pt_get_asset_url($relative_path) {
    // Check root assets directory first
    $root_path = PT_PLUGIN_DIR . $relative_path;
    if (file_exists($root_path)) {
        return PT_PLUGIN_URL . $relative_path;
    }
    // Check nested profile-trader/assets directory
    $nested_path = PT_PLUGIN_DIR . 'profile-trader/' . $relative_path;
    if (file_exists($nested_path)) {
        return PT_PLUGIN_URL . 'profile-trader/' . $relative_path;
    }
    // Return root URL as fallback
    return PT_PLUGIN_URL . $relative_path;
}

/**
 * Helper function to get template file path (checks multiple locations)
 */
function pt_get_template_path($relative_path) {
    // Check root templates directory first
    $root_path = PT_PLUGIN_DIR . $relative_path;
    if (file_exists($root_path)) {
        return $root_path;
    }
    // Check nested profile-trader/templates directory
    $nested_path = PT_PLUGIN_DIR . 'profile-trader/' . $relative_path;
    if (file_exists($nested_path)) {
        return $nested_path;
    }
    // Return root path as fallback (will show error if file doesn't exist)
    return $root_path;
}

/**
 * Main Plugin Class
 */
class Profile_Trader {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 999); // Load after Bricks Builder
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('pre_get_posts', [$this, 'modify_job_query']);
        add_action('wp_ajax_pt_save_listing', [$this, 'ajax_save_listing']);
        add_action('wp_ajax_pt_save_company', [$this, 'ajax_save_company']);
        add_action('wp_ajax_pt_save_job', [$this, 'ajax_save_job']);
        add_action('wp_ajax_pt_upload_media', [$this, 'ajax_upload_media']);
        add_action('wp_ajax_pt_upload_avatar', [$this, 'ajax_upload_avatar']);
        add_action('wp_ajax_pt_delete_media', [$this, 'ajax_delete_media']);
        add_action('wp_ajax_pt_migrate_gallery_meta', [$this, 'ajax_migrate_gallery_meta']);
        add_action('wp_ajax_pt_load_ads_archive', [$this, 'ajax_load_ads_archive']);
        add_action('wp_ajax_nopriv_pt_load_ads_archive', [$this, 'ajax_load_ads_archive']);

        // Admin: Ads-Trader relationship meta box
        add_action('add_meta_boxes', [$this, 'add_ads_trader_meta_box']);
        add_action('save_post_ads', [$this, 'save_ads_trader_meta']);
        // Backup hook in case save_post_ads doesn't fire
        add_action('save_post', [$this, 'save_ads_trader_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Filter avatar to use custom uploaded avatar
        add_filter('get_avatar_data', [$this, 'filter_avatar_data'], 10, 2);

        // Normalize gallery meta field to always return string format for JetEngine/Bricks compatibility
        add_filter('get_post_metadata', [$this, 'normalize_gallery_meta'], 10, 4);
        
        // Run migration on admin init (one-time fix for existing data)
        add_action('admin_init', [$this, 'maybe_migrate_gallery_meta']);

        // Shortcodes
        add_shortcode('trader_dashboard', [$this, 'render_dashboard']);
        add_shortcode('trader_listings', [$this, 'render_listings']);
        add_shortcode('trader_edit_form', [$this, 'render_edit_form']);
        add_shortcode('job_type_filter', [$this, 'job_type_filter_shortcode']);
        add_shortcode('single_ad', [$this, 'render_single_ad']);
        add_shortcode('trader_profile', [$this, 'render_trader_profile']);
        add_shortcode('trader_page', [$this, 'render_trader_page']);
        add_shortcode('trader_download', [$this, 'render_trader_download']);
        add_shortcode('trader_qr', [$this, 'render_trader_qr']);
        add_shortcode('ads_archive', [$this, 'render_ads_archive']);

        // Add dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);

        // Create dashboard page on activation
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('profile-trader', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_assets() {
        // Load assets on all frontend pages for better compatibility with page builders
        // The CSS is scoped to .pt-dashboard so it won't affect other pages
        if (!is_admin()) {
            // Google Fonts - Cairo
            wp_enqueue_style(
                'google-fonts-cairo',
                'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap',
                [],
                null
            );
            
            // intl-tel-input CSS
            wp_enqueue_style(
                'intl-tel-input',
                'https://cdn.jsdelivr.net/npm/intl-tel-input@23.7.4/build/css/intlTelInput.css',
                [],
                '23.7.4'
            );

            // Cropper.js CSS for logo cropping
            wp_enqueue_style(
                'cropper-css',
                'https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css',
                [],
                '1.6.1'
            );

            // Dashboard Styles (load after theme/builders)
            $dashboard_css = pt_get_asset_path('assets/css/dashboard.css');
            wp_enqueue_style(
                'pt-dashboard',
                pt_get_asset_url('assets/css/dashboard.css'),
                ['intl-tel-input'],
                file_exists($dashboard_css) ? filemtime($dashboard_css) : PT_VERSION
            );
            
            // Add inline styles for Bricks Builder compatibility
            $bricks_compat = '
                .brxe-shortcode .pt-dashboard,
                .brxe-code .pt-dashboard,
                .bricks-element .pt-dashboard {
                    width: 100% !important;
                    max-width: none !important;
                }
            ';
            wp_add_inline_style('pt-dashboard', $bricks_compat);
            
            // intl-tel-input JS
            wp_enqueue_script(
                'intl-tel-input',
                'https://cdn.jsdelivr.net/npm/intl-tel-input@23.7.4/build/js/intlTelInput.min.js',
                [],
                '23.7.4',
                true
            );

            // Cropper.js for logo cropping
            wp_enqueue_script(
                'cropper-js',
                'https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js',
                [],
                '1.6.1',
                true
            );

            // Scripts
            wp_enqueue_media();
            $dashboard_js = pt_get_asset_path('assets/js/dashboard.js');
            wp_enqueue_script(
                'pt-dashboard',
                pt_get_asset_url('assets/js/dashboard.js'),
                ['jquery', 'intl-tel-input', 'cropper-js'],
                file_exists($dashboard_js) ? filemtime($dashboard_js) : PT_VERSION,
                true
            );
            
            wp_localize_script('pt-dashboard', 'ptAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pt_nonce'),
                'strings' => [
                    'saving' => __('جاري الحفظ...', 'profile-trader'),
                    'saved' => __('تم الحفظ بنجاح', 'profile-trader'),
                    'error' => __('حدث خطأ، يرجى المحاولة مرة أخرى', 'profile-trader'),
                    'confirm_delete' => __('هل أنت متأكد من الحذف؟', 'profile-trader'),
                    'uploading' => __('جاري الرفع...', 'profile-trader'),
                    'add_branch' => __('إضافة فرع', 'profile-trader'),
                    'add_service' => __('إضافة خدمة', 'profile-trader'),
                ]
            ]);
        }
    }
    
    private function is_dashboard_page() {
        global $post;
        
        // Always load on frontend if any of our shortcodes might be present
        // This ensures compatibility with page builders like Bricks
        if (is_singular()) {
            // Check regular post content
            if ($post && (
                has_shortcode($post->post_content, 'trader_dashboard') ||
                has_shortcode($post->post_content, 'trader_listings') ||
                has_shortcode($post->post_content, 'trader_edit_form')
            )) {
                return true;
            }
            
            // Check Bricks Builder content (stored in post meta)
            if ($post) {
                $bricks_data = get_post_meta($post->ID, '_bricks_page_content_2', true);
                if ($bricks_data && is_array($bricks_data)) {
                    $bricks_json = json_encode($bricks_data);
                    if (strpos($bricks_json, 'trader_dashboard') !== false ||
                        strpos($bricks_json, 'trader_listings') !== false ||
                        strpos($bricks_json, 'trader_edit_form') !== false) {
                        return true;
                    }
                }
                
                // Also check _bricks_page_content (older format)
                $bricks_data_old = get_post_meta($post->ID, '_bricks_page_content', true);
                if ($bricks_data_old && is_array($bricks_data_old)) {
                    $bricks_json = json_encode($bricks_data_old);
                    if (strpos($bricks_json, 'trader_dashboard') !== false ||
                        strpos($bricks_json, 'trader_listings') !== false ||
                        strpos($bricks_json, 'trader_edit_form') !== false) {
                        return true;
                    }
                }
            }
        }
        
        // Check if we're on a specific dashboard page by slug
        if ($post && in_array($post->post_name, ['trader-dashboard', 'dashboard', 'my-account'])) {
            return true;
        }
        
        return false;
    }
    
    public function activate() {
        // Create dashboard page if not exists
        $page = get_page_by_path('trader-dashboard');
        if (!$page) {
            wp_insert_post([
                'post_title' => 'لوحة تحكم التاجر',
                'post_name' => 'trader-dashboard',
                'post_content' => '[trader_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
            ]);
        }

        // Create ad views table
        if (class_exists('PT_Ad_Views')) {
            PT_Ad_Views::create_table();
        }

        flush_rewrite_rules();
    }
    
    /**
     * Get trader meta fields configuration
     */
    public function get_meta_fields() {
        return [
            'short_desc' => [
                'label' => 'وصف قصير',
                'type' => 'textarea',
                'max_length' => 70,
                'width' => '100%',
            ],
            'website' => [
                'label' => 'الموقع الالكتروني',
                'type' => 'url',
                'icon' => 'globe',
            ],
            'email' => [
                'label' => 'الايميل',
                'type' => 'email',
                'icon' => 'envelope',
            ],
            'phone' => [
                'label' => 'رقم الهاتف',
                'type' => 'tel',
                'icon' => 'phone',
            ],
            'whatsapp' => [
                'label' => 'واتساب',
                'type' => 'tel',
                'icon' => 'whatsapp',
            ],
            'facebook_page' => [
                'label' => 'صفحة الفيس',
                'type' => 'url',
                'icon' => 'facebook',
            ],
            'instagram_page' => [
                'label' => 'صفحة الانستغرام',
                'type' => 'url',
                'icon' => 'instagram',
            ],
            'date_of_grant_of_record' => [
                'label' => 'تاريخ منح السجل',
                'type' => 'date',
            ],
            'map_location' => [
                'label' => 'العنوان الفرع الرئيسي',
                'type' => 'text',
                'icon' => 'map-marker',
            ],
            'commercial_register' => [
                'label' => ' رقم السجل التجاري ',
                'type' => 'text',
            ],
            'score' => [
                'label' => 'درجة السجل',
                'type' => 'radio',
                'options' => [
                    'الممتازة' => 'الممتازة',
                    'الأولى' => 'الأولى',
                    'الثانية' => 'الثانية',
                    'الثالثة' => 'الثالثة',
                    'الرابعة' => 'الرابعة',
                ],
            ],
            'company_type' => [
                'label' => 'نوع الشركة',
                'type' => 'radio',
                'options' => [
                    'شركة فردية' => 'شركة فردية',
                    'شركة تضامن' => 'شركة تضامن',
                    'شركة محدودة المسؤولية' => 'شركة محدودة المسؤولية',
                    'شركة مساهمة مغفلة' => 'شركة مساهمة مغفلة',
                    'شركة مساهمة مفتوحة' => 'شركة مساهمة مفتوحة',
                    'شركة توصية' => 'شركة توصية',
                ],
            ],
            'services' => [
                'label' => 'تصنيف المنتجات',
                'type' => 'repeater',
                'fields' => [
                    'services_name' => [
                        'label' => 'اسم الخدمة',
                        'type' => 'text',
                    ],
                ],
            ],
            'bracnches' => [
                'label' => 'الفروع',
                'type' => 'repeater',
                'fields' => [
                    'اسم_الفرع' => [
                        'label' => 'اسم الفرع',
                        'type' => 'text',
                    ],
                    'العنوان' => [
                        'label' => 'العنوان',
                        'type' => 'text',
                    ],
                    'الهاتف' => [
                        'label' => 'الهاتف',
                        'type' => 'tel',
                    ],
                    'المنتجات' => [
                        'label' => 'المنتجات',
                        'type' => 'textarea',
                    ],
                ],
            ],
            'is_featured' => [
                'label' => 'عضو مميز',
                'type' => 'checkbox',
                'admin_only' => true,
            ],
            'status_editing' => [
                'label' => 'حالة التعديل',
                'type' => 'radio',
                'options' => [
                    'تم التعديل' => 'تم التعديل',
                    'غير مكتمل' => 'غير مكتمل',
                ],
                'admin_only' => true,
            ],
            'gallary' => [
                'label' => 'معرض الصور',
                'type' => 'gallery',
            ],
            'logo' => [
                'label' => 'لوجو',
                'type' => 'media',
            ],
            'profile' => [
                'label' => 'الملف الشخصي',
                'type' => 'text',
            ],
            // About Section Structured Fields
            'mission_statement' => [
                'label' => 'بيان المهمة',
                'type' => 'textarea',
            ],
            'vision' => [
                'label' => 'الرؤية',
                'type' => 'textarea',
            ],
            'key_statistics' => [
                'label' => 'الإحصائيات الرئيسية',
                'type' => 'repeater',
                'fields' => [
                    'stat_number' => [
                        'label' => 'الرقم',
                        'type' => 'text',
                    ],
                    'stat_label' => [
                        'label' => 'الوصف',
                        'type' => 'text',
                    ],
                    'stat_icon' => [
                        'label' => 'الأيقونة',
                        'type' => 'text',
                    ],
                ],
            ],
            'about_highlights' => [
                'label' => 'النقاط المميزة',
                'type' => 'repeater',
                'fields' => [
                    'highlight_text' => [
                        'label' => 'نقطة مميزة',
                        'type' => 'text',
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Get company registration meta fields configuration
     */
    public function get_company_meta_fields() {
        return [
            'company_name' => [
                'label' => 'اسم الشركة التجاري',
                'type' => 'text',
                'required' => true,
            ],
            'phone_number' => [
                'label' => 'رقم الهاتف',
                'type' => 'tel',
                'required' => true,
            ],
            'full_address' => [
                'label' => 'العنوان الكامل',
                'type' => 'text',
                'required' => true,
            ],
            'directorate' => [
                'label' => 'المدينة',
                'type' => 'text',
                'required' => true,
            ],
            'national_number' => [
                'label' => 'الرقم الوطني لصاحب الشركة',
                'type' => 'text',
                'required' => true,
            ],
            'company_type' => [
                'label' => 'نوع الشركة',
                'type' => 'text',
                'required' => true,
            ],
            'owner_name' => [
                'label' => 'اسم صاحب الشركة/المدير المفوض',
                'type' => 'text',
                'required' => true,
            ],
            'email' => [
                'label' => 'البريد الالكتروني',
                'type' => 'email',
                'required' => true,
            ],
            'website' => [
                'label' => 'الموقع الإلكتروني للشركة',
                'type' => 'url',
                'required' => false,
            ],
            'about_company' => [
                'label' => 'وصف النشاط التجاري بشكل واضح',
                'type' => 'textarea',
                'required' => true,
            ],
            'profile_photo' => [
                'label' => 'صورة شخصية',
                'type' => 'media',
                'required' => true,
            ],
            'id_photo' => [
                'label' => 'صورة الهوية',
                'type' => 'media',
                'required' => true,
            ],
        ];
    }
    
    /**
     * Get current user's trader listings
     * Uses the connection class to get all traders connected to the user
     * via author, meta, or email match
     */
    public function get_user_listings($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return [];
        }
        
        // Use the connection class for comprehensive lookup
        if (class_exists('PT_Trader_Connection')) {
            $connection = PT_Trader_Connection::get_instance();
            return $connection->get_user_traders($user_id);
        }
        // Fallback if class not available
        return [];
    }

    /**
     * Get current user's job listings
     *
     * @param int|null $user_id User ID (default: current user)
     * @return array Array of job posts
     */
    public function get_user_jobs($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return [];
        }

        $args = [
            'post_type' => 'job',
            'post_status' => ['publish', 'pending', 'draft'],
            'author' => $user_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $jobs = get_posts($args);
        return $jobs;
    }

    /**
     * Get job type labels (Arabic)
     *
     * @return array Array of job type keys => Arabic labels
     * Note: Using Arabic values to match JetEngine meta field configuration
     */
    public function get_job_type_labels() {
        return [
            'دوام كامل' => 'دوام كامل',
            'دوام جزئي' => 'دوام جزئي',
            'عن بعد' => 'عن بعد',
            'تدريب' => 'تدريب'
        ];
    }
    
    /**
     * Get job type label in Arabic
     *
     * @param string $job_type_key Job type key (e.g., 'full_time', 'part_time')
     * @return string Arabic label or the key if not found
     */
    public function get_job_type_label($job_type_key) {
        $labels = $this->get_job_type_labels();
        return isset($labels[$job_type_key]) ? $labels[$job_type_key] : $job_type_key;
    }

    /**
     * Register REST API endpoints
     */
    public function register_rest_routes() {
        register_rest_route('profile-trader/v1', '/job-types', [
            'methods' => 'GET',
            'callback' => [$this, 'get_job_types_for_filter'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Get job types formatted for filters
     * Returns array of objects with 'value' (English key) and 'label' (Arabic)
     *
     * @return WP_REST_Response Response object with job types data
     */
    public function get_job_types_for_filter() {
        $job_types = $this->get_job_type_labels();
        $formatted = [];

        foreach ($job_types as $key => $label) {
            $formatted[] = [
                'value' => $key,      // English key for querying
                'label' => $label     // Arabic label for display
            ];
        }

        return rest_ensure_response($formatted);
    }

    /**
     * Get job type options as simple array for dropdowns
     * Returns: ['value' => 'label'] format with ONLY Arabic labels visible
     *
     * @return array Associative array of job type key => Arabic label
     */
    public static function get_job_type_options_for_select() {
        $instance = self::get_instance();
        return $instance->get_job_type_labels();
    }

    /**
     * Get job type label by key (static helper)
     *
     * @param string $key The job type key
     * @return string The Arabic label
     */
    public static function get_job_type_label_by_key($key) {
        $instance = self::get_instance();
        return $instance->get_job_type_label($key);
    }

    /**
     * Modify job queries to support filtering by job_type
     *
     * @param WP_Query $query The WordPress query object
     */
    public function modify_job_query($query) {
        // Only on frontend, main query, and job post type
        if (is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'job') {
            return;
        }

        // Check if job_type filter is set
        if (isset($_GET['job_type']) && !empty($_GET['job_type'])) {
            $job_type = sanitize_text_field($_GET['job_type']);

            // Validate it's a valid job type
            $valid_types = array_keys($this->get_job_type_labels());
            if (in_array($job_type, $valid_types)) {
                // Add meta query for job_type
                $meta_query = $query->get('meta_query') ?: [];
                $meta_query[] = [
                    'key' => 'job_type',
                    'value' => $job_type,
                    'compare' => '='
                ];
                $query->set('meta_query', $meta_query);
            }
        }
    }

    /**
     * Job type filter shortcode
     * Usage: [job_type_filter]
     *
     * @return string HTML output for the filter form
     */
    public function job_type_filter_shortcode() {
        $job_types = $this->get_job_type_labels();
        $selected = isset($_GET['job_type']) ? sanitize_text_field($_GET['job_type']) : '';

        ob_start();
        ?>
        <form method="get" class="job-type-filter">
            <label for="job_type_filter">نوع الوظيفة:</label>
            <select name="job_type" id="job_type_filter" onchange="this.form.submit()">
                <option value="">الكل</option>
                <?php foreach ($job_types as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Strip Gutenberg block comments from content
     * 
     * @param string $content Content that may contain block comments
     * @return string Cleaned content without block comments
     */
    private function strip_block_comments($content) {
        if (empty($content)) {
            return $content;
        }
        
        // Remove Gutenberg block comments (<!-- wp:paragraph -->, <!-- /wp:paragraph -->, etc.)
        $content = preg_replace('/<!--\s*wp:[^>]+-->/', '', $content);
        
        // Also handle block attributes if present
        $content = preg_replace('/<!--\s*\/wp:[^>]+-->/', '', $content);
        
        // Clean up any extra whitespace
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        
        return trim($content);
    }

    /**
     * Check if user can access a specific trader listing
     */
    public function user_can_access($trader_id, $user_id = null) {
        if (class_exists('PT_Trader_Connection')) {
            $connection = PT_Trader_Connection::get_instance();
            return $connection->user_can_access_trader($trader_id, $user_id);
        }
        // Fallback: check if user is the author
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $trader = get_post($trader_id);
        return $trader && $trader->post_author == $user_id;
    }
    
    /**
     * Render Dashboard Shortcode
     */
    public function render_dashboard($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }
        
        ob_start();
        include PT_PLUGIN_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * Render Listings Shortcode
     */
    public function render_listings($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }
        
        ob_start();
        include PT_PLUGIN_DIR . 'templates/listings.php';
        return ob_get_clean();
    }
    
    /**
     * Render Edit Form Shortcode
     */
    public function render_edit_form($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }
        
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);
        
        $post_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : intval($atts['id']);
        
        // Verify ownership using connection class
        if ($post_id) {
            if (!$this->user_can_access($post_id)) {
                return '<div class="pt-error">' . __('ليس لديك صلاحية لتعديل هذا الإعلان', 'profile-trader') . '</div>';
            }
        }
        
        ob_start();
        include PT_PLUGIN_DIR . 'templates/edit-form.php';
        return ob_get_clean();
    }
    
    /**
     * Render Single Ad Shortcode
     * Usage: [single_ad id="123"] or [single_ad] (uses current post ID)
     */
    public function render_single_ad($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $ad_id = intval($atts['id']);

        // Check URL parameter if no ID provided
        if (!$ad_id && isset($_GET['ad_id'])) {
            $ad_id = intval($_GET['ad_id']);
        }

        // Fallback to current post
        if (!$ad_id) {
            $ad_id = get_the_ID();
        }

        ob_start();
        include PT_PLUGIN_DIR . 'templates/single-ad.php';
        return ob_get_clean();
    }

    /**
     * Render Trader Profile Shortcode
     * Usage: [trader_profile id="123"] or [trader_profile] (uses URL parameter or current post)
     */
    public function render_trader_profile($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $trader_id = intval($atts['id']);

        // Check URL parameter if no ID provided
        if (!$trader_id && isset($_GET['trader_id'])) {
            $trader_id = intval($_GET['trader_id']);
        }

        // Fallback to current post
        if (!$trader_id) {
            $trader_id = get_the_ID();
        }

        ob_start();
        include PT_PLUGIN_DIR . 'templates/trader-profile.php';
        return ob_get_clean();
    }

    /**
     * Render Trader Page Shortcode (Minimal UI)
     * Usage: [trader_page id="123"] or [trader_page] (uses URL parameter or current post)
     */
    public function render_trader_page($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $trader_id = intval($atts['id']);

        // Check URL parameter if no ID provided
        if (!$trader_id && isset($_GET['trader_id'])) {
            $trader_id = intval($_GET['trader_id']);
        }

        // Fallback to current post
        if (!$trader_id) {
            $trader_id = get_the_ID();
        }

        ob_start();
        // Pass trader_id to template
        include PT_PLUGIN_DIR . 'templates/trader-page-minimal.php';
        return ob_get_clean();
    }

    /**
     * Render Trader Download Shortcode
     * Usage: [trader_download id="123" text="تحميل الملف الشخصي للتاجر"]
     */
    public function render_trader_download($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'text' => 'تحميل الملف التعريفي (PDF)',
        ], $atts);

        $trader_id = intval($atts['id']);
        if (!$trader_id) {
            $trader_id = get_the_ID();
        }

        // Get PDF URL - check multiple meta keys
        $pdf_url = get_post_meta($trader_id, 'je_trader_profile', true);
        if (empty($pdf_url)) {
            $pdf_url = get_post_meta($trader_id, 'profile_pdf', true);
        }
        if (empty($pdf_url)) {
            $pdf_url = get_post_meta($trader_id, 'trader_profile', true);
        }
        
        $download_text = esc_html($atts['text']);
        
        // Always show button, even if no PDF (button will be disabled)
        if (empty($pdf_url)) {
            return sprintf(
                '<button class="pt-btn pt-btn-prim" style="width:100%%;justify-content:center;display:inline-flex;text-decoration:none;opacity:0.6;cursor:not-allowed" disabled>
                    <span class="material-symbols-outlined">download</span>
                    <span>%s</span>
                </button>
                <p style="font-size:12px;color:#94a3b8;margin-top:8px;text-align:center">الملف غير متوفر حالياً</p>',
                $download_text
            );
        }
        
        return sprintf(
            '<a href="%s" target="_blank" class="pt-btn pt-btn-prim" style="width:100%%;justify-content:center;display:inline-flex;text-decoration:none">
                <span class="material-symbols-outlined">download</span>
                <span>%s</span>
            </a>',
            esc_url($pdf_url),
            $download_text
        );
    }

    /**
     * Render Trader QR Code Shortcode
     * Usage: [trader_qr id="123" size="250" download="true" download_text="تحميل ال QR"]
     */
    public function render_trader_qr($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'size' => 200,
            'download' => 'false',
            'download_text' => 'تحميل QR Code',
        ], $atts);

        $trader_id = intval($atts['id']);
        if (!$trader_id) {
            $trader_id = get_the_ID();
        }

        // Get trader URL
        $trader_url = get_permalink($trader_id);
        if (!$trader_url) {
            $trader_url = home_url('/trader/' . $trader_id . '/');
        }

        // Generate QR code using a service (you can use any QR code API)
        $size = intval($atts['size']);
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($trader_url);
        
        $output = '<div style="text-align:center">';
        $output .= '<img src="' . esc_url($qr_url) . '" alt="QR Code" style="max-width:100%;height:auto;border:1px solid #e2e8f0;border-radius:8px;padding:8px;background:#fff"/>';
        
        if ($atts['download'] === 'true') {
            $download_text = esc_html($atts['download_text']);
            $output .= '<div style="margin-top:12px">';
            $output .= '<a href="' . esc_url($qr_url) . '" download="trader-qr-' . $trader_id . '.png" class="pt-btn pt-btn-sec" style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;padding:8px 16px;font-size:14px">';
            $output .= '<span class="material-symbols-outlined" style="font-size:18px">download</span>';
            $output .= '<span>' . $download_text . '</span>';
            $output .= '</a>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render Ads Archive Shortcode
     * Usage: [ads_archive]
     */
    public function render_ads_archive($atts) {
        ob_start();
        include PT_PLUGIN_DIR . 'templates/archive-ads.php';
        return ob_get_clean();
    }

    /**
     * Render login required message
     */
    private function render_login_required() {
        // Get current page URL for redirect after login
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $login_url = add_query_arg('redirect_to', urlencode($current_url), home_url('/login/'));

        return sprintf(
            '<div class="pt-login-required" style="font-family: \'Cairo\', sans-serif;">
                <div class="pt-login-icon">🔐</div>
                <h3>%s</h3>
                <p>%s</p>
                <a href="%s" class="pt-btn pt-btn-primary">%s</a>
            </div>',
            __('تسجيل الدخول مطلوب', 'profile-trader'),
            __('يجب عليك تسجيل الدخول للوصول إلى لوحة التحكم', 'profile-trader'),
            esc_url($login_url),
            __('تسجيل الدخول', 'profile-trader')
        );
    }
    
    /**
     * AJAX: Save Listing
     */
    public function ajax_save_listing() {
        check_ajax_referer('pt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول', 'profile-trader')]);
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $user_id = get_current_user_id();
        
        // Verify ownership for existing posts using connection class
        if ($post_id) {
            if (!$this->user_can_access($post_id, $user_id)) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
            }
        }
        
        // Determine whether this listing was already submitted (locked fields)
        $is_new_post = !isset($_POST['post_id']) || !$_POST['post_id'];
        $was_submitted = isset($_POST['is_submitted']) && $_POST['is_submitted'] === '1';

        // Determine post status: keep existing status if post is already published
        $post_status = 'pending'; // Default for new posts
        if ($post_id) {
            // Clear cache to get fresh post data
            clean_post_cache($post_id);
            $existing_post = get_post($post_id);
            if ($existing_post && $existing_post->post_status === 'publish') {
                // Keep published status for existing published posts
                $post_status = 'publish';
            } elseif ($existing_post) {
                // Keep existing status for pending/draft posts
                $post_status = $existing_post->post_status;
            }
        }

        // Prepare post data
        $post_data = [
            'post_title' => sanitize_text_field($_POST['post_title'] ?? ''),
            'post_content' => wp_kses_post($_POST['post_content'] ?? ''),
            'post_type' => PT_POST_TYPE,
            'post_status' => $post_status,
            'post_author' => $user_id,
        ];

        // Lock company name after first submission (server-side enforcement)
        if (!$is_new_post && $was_submitted && $post_id) {
            $existing_post = get_post($post_id);
            if ($existing_post) {
                $post_data['post_title'] = $existing_post->post_title;
            }
        }
        
        if ($post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $post_id = $result;
        
        // Save meta fields
        $meta_fields = $this->get_meta_fields();
        
        // Debug: Log phone values
        error_log('PT Debug - phone in POST: ' . (isset($_POST['phone']) ? $_POST['phone'] : 'NOT SET'));
        error_log('PT Debug - whatsapp in POST: ' . (isset($_POST['whatsapp']) ? $_POST['whatsapp'] : 'NOT SET'));
        
        foreach ($meta_fields as $key => $field) {
            // Skip admin-only fields
            if (!empty($field['admin_only']) && !current_user_can('manage_options')) {
                continue;
            }
            
            // For gallery fields, always process (even if empty) to allow clearing
            if (isset($_POST[$key]) || $field['type'] === 'gallery') {
                $value = isset($_POST[$key]) ? $_POST[$key] : '';
                
                // Sanitize based on type
                if ($field['type'] === 'repeater') {
                    $value = $this->sanitize_repeater($value, $field['fields']);
                } elseif ($field['type'] === 'gallery') {
                    // Process gallery IDs: validate, filter, and convert to comma-separated string
                    // JetEngine/Bricks expects gallery fields as comma-separated string, not array
                    $gallery_ids = [];
                    
                    if (is_string($value) && !empty(trim($value))) {
                        $gallery_ids = explode(',', $value);
                    } elseif (is_array($value)) {
                        $gallery_ids = $value;
                    }
                    
                    // Filter and convert to integers, remove empty/invalid values
                    $gallery_ids = array_filter(array_map('intval', (array) $gallery_ids), function($v) {
                        return $v > 0;
                    });
                    
                    // Re-index array and convert back to comma-separated string for storage
                    // JetEngine/Bricks requires string format: "123,456,789"
                    $value = !empty($gallery_ids) ? implode(',', array_values($gallery_ids)) : '';
                } elseif ($field['type'] === 'media') {
                    $value = intval($value);
                } elseif ($field['type'] === 'email') {
                    $value = sanitize_email($value);
                } elseif ($field['type'] === 'url') {
                    $value = esc_url_raw($value);
                } elseif ($field['type'] === 'textarea') {
                    $value = sanitize_textarea_field($value);
                } elseif ($field['type'] === 'tel') {
                    // Phone numbers - keep only digits and + sign
                    $value = preg_replace('/[^0-9+]/', '', $value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                // Debug: Log phone fields
                if ($key === 'phone' || $key === 'whatsapp') {
                    error_log('PT Debug - Saving ' . $key . ': ' . $value);
                }
                
                // Debug: Log gallery field
                if ($key === 'gallary') {
                    error_log('PT Debug - Saving gallery: ' . print_r($value, true));
                }
                
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Update editing status
        update_post_meta($post_id, 'status_editing', 'تم التعديل');
        
        // Save taxonomies only if new post or not previously submitted
        if ($is_new_post || !$was_submitted) {
            // City taxonomy (single select)
            if (isset($_POST['tax_city']) && !empty($_POST['tax_city'])) {
                $city_term = intval($_POST['tax_city']);
                wp_set_post_terms($post_id, [$city_term], 'city');
            }
            
            // Activity taxonomy (multi-select)
            if (isset($_POST['tax_activity'])) {
                $activity_terms = array_map('intval', (array) $_POST['tax_activity']);
                $activity_terms = array_filter($activity_terms);
                wp_set_post_terms($post_id, $activity_terms, 'activity');
            }
            
            // Sector taxonomy (single select)
            if (isset($_POST['tax_sector']) && !empty($_POST['tax_sector'])) {
                $sector_term = intval($_POST['tax_sector']);
                wp_set_post_terms($post_id, [$sector_term], 'sector');
            }

            // Economic Activity taxonomy (single select)
            if (isset($_POST['tax_economic_activity']) && !empty($_POST['tax_economic_activity'])) {
                $economic_activity_term = intval($_POST['tax_economic_activity']);
                wp_set_post_terms($post_id, [$economic_activity_term], 'economic_activity');
            }
        }
        
        // Ensure trader is connected to user (for new posts)
        if ($is_new_post && class_exists('PT_Trader_Connection')) {
            $connection = PT_Trader_Connection::get_instance();
            $connection->connect_trader_to_user($post_id, $user_id, 'both');
        }
        
        wp_send_json_success([
            'message' => __('تم حفظ البيانات بنجاح', 'profile-trader'),
            'post_id' => $post_id,
            'redirect' => add_query_arg(['tab' => 'listings'], get_permalink(get_page_by_path('trader-dashboard'))),
        ]);
    }
    
    /**
     * AJAX: Save Company Registration
     */
    public function ajax_save_company() {
        check_ajax_referer('pt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول', 'profile-trader')]);
        }
        
        $user_id = get_current_user_id();
        $meta_fields = $this->get_company_meta_fields();
        
        // Validate required fields
        $errors = [];
        foreach ($meta_fields as $key => $field) {
            if (!empty($field['required']) && empty($_POST[$key])) {
                $errors[] = sprintf(__('حقل %s مطلوب', 'profile-trader'), $field['label']);
            }
        }
        
        if (!empty($errors)) {
            wp_send_json_error(['message' => implode('<br>', $errors)]);
        }
        
        // Prepare post data
        $company_name = sanitize_text_field($_POST['company_name'] ?? '');
        $post_data = [
            'post_title' => $company_name,
            'post_content' => wp_kses_post($_POST['about_company'] ?? ''),
            'post_type' => 'company-create',
            'post_status' => 'pending',
            'post_author' => $user_id,
        ];
        
        $result = wp_insert_post($post_data, true);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $post_id = $result;
        
        // Save meta fields
        foreach ($meta_fields as $key => $field) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                
                // Sanitize based on type
                if ($field['type'] === 'media') {
                    $value = intval($value);
                } elseif ($field['type'] === 'email') {
                    $value = sanitize_email($value);
                } elseif ($field['type'] === 'url') {
                    $value = esc_url_raw($value);
                } elseif ($field['type'] === 'textarea') {
                    $value = sanitize_textarea_field($value);
                } elseif ($field['type'] === 'tel') {
                    $value = preg_replace('/[^0-9+]/', '', $value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Save taxonomies
        // Sector taxonomy (single select)
        if (isset($_POST['tax_sector']) && !empty($_POST['tax_sector'])) {
            $sector_term = intval($_POST['tax_sector']);
            wp_set_post_terms($post_id, [$sector_term], 'sector');
        }
        
        // Economic Activity taxonomy (single select)
        if (isset($_POST['tax_economic_activity']) && !empty($_POST['tax_economic_activity'])) {
            $economic_activity_term = intval($_POST['tax_economic_activity']);
            wp_set_post_terms($post_id, [$economic_activity_term], 'economic_activity');
        }
        
        // Activity taxonomy (multi-select)
        if (isset($_POST['tax_activity'])) {
            $activity_terms = array_map('intval', (array) $_POST['tax_activity']);
            $activity_terms = array_filter($activity_terms);
            wp_set_post_terms($post_id, $activity_terms, 'activity');
        }
        
        wp_send_json_success([
            'message' => __('تم تقديم طلب التسجيل بنجاح. سيتم مراجعته من قبل الإدارة.', 'profile-trader'),
            'post_id' => $post_id,
            'redirect' => add_query_arg(['tab' => 'overview'], get_permalink(get_page_by_path('trader-dashboard'))),
        ]);
    }
    
    /**
     * AJAX: Save Job
     */
    public function ajax_save_job() {
        check_ajax_referer('pt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول', 'profile-trader')]);
        }
        
        $user_id = get_current_user_id();
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        // Verify ownership for existing posts
        if ($post_id) {
            $existing_post = get_post($post_id);
            if (!$existing_post || $existing_post->post_author != $user_id) {
                wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
            }
        }
        
        // Validate required fields
        $errors = [];
        if (empty($_POST['post_title'])) {
            $errors[] = __('عنوان الوظيفة مطلوب', 'profile-trader');
        }
        if (empty($_POST['position'])) {
            $errors[] = __('المسمى الوظيفي مطلوب', 'profile-trader');
        }
        if (empty($_POST['post_content'])) {
            $errors[] = __('وصف الوظيفة مطلوب', 'profile-trader');
        }
        if (empty($_POST['contact_number'])) {
            $errors[] = __('رقم التواصل مطلوب', 'profile-trader');
        }
        
        if (!empty($errors)) {
            wp_send_json_error(['message' => implode('<br>', $errors)]);
        }
        
        // Clean post content - remove Gutenberg block comments
        $post_content = isset($_POST['post_content']) ? $_POST['post_content'] : '';
        $post_content = $this->strip_block_comments($post_content);

        // Determine post status: keep existing status if post is already published
        $post_status = 'pending'; // Default for new posts
        if ($post_id) {
            // Clear cache to get fresh post data
            clean_post_cache($post_id);
            $existing_post = get_post($post_id);
            if ($existing_post && $existing_post->post_status === 'publish') {
                // Keep published status for existing published posts
                $post_status = 'publish';
            } elseif ($existing_post) {
                // Keep existing status for pending/draft posts
                $post_status = $existing_post->post_status;
            }
        }

        // Prepare post data
        $post_data = [
            'post_title' => sanitize_text_field($_POST['post_title']),
            'post_content' => wp_kses_post($post_content),
            'post_type' => 'job',
            'post_status' => $post_status,
            'post_author' => $user_id,
        ];
        
        if ($post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $post_id = $result;
        
        // Save meta fields
        $meta_fields = ['position', 'salary_range', 'expirence', 'job_type', 'requirements', 'advantages', 'contact_number'];
        
        foreach ($meta_fields as $key) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                
                if ($key === 'contact_number') {
                    $value = preg_replace('/[^0-9+]/', '', $value);
                } elseif (in_array($key, ['requirements', 'advantages'])) {
                    // Clean block comments from requirements and advantages
                    $value = $this->strip_block_comments($value);
                    $value = wp_kses_post($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Save job_category taxonomy (single select)
        if (isset($_POST['tax_job_category']) && !empty($_POST['tax_job_category'])) {
            $job_category_term = intval($_POST['tax_job_category']);
            wp_set_post_terms($post_id, [$job_category_term], 'job_category');
        } else {
            // Remove taxonomy if empty
            wp_set_post_terms($post_id, [], 'job_category');
        }
        
        // Get the dashboard page URL
        $dashboard_page = get_page_by_path('trader-dashboard');
        $redirect_url = $dashboard_page 
            ? add_query_arg(['tab' => 'jobs'], get_permalink($dashboard_page))
            : home_url('/trader-dashboard/?tab=jobs');
        
        wp_send_json_success([
            'message' => __('تم نشر الوظيفة بنجاح! سيتم مراجعتها من قبل الإدارة للموافقة عليها.', 'profile-trader'),
            'post_id' => $post_id,
            'redirect' => $redirect_url,
        ]);
    }
    
    /**
     * Sanitize repeater field data
     */
    private function sanitize_repeater($data, $fields) {
        if (!is_array($data)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($data as $index => $row) {
            $sanitized_row = [];
            foreach ($fields as $field_key => $field_config) {
                if (isset($row[$field_key])) {
                    if ($field_config['type'] === 'textarea') {
                        $sanitized_row[$field_key] = sanitize_textarea_field($row[$field_key]);
                    } else {
                        $sanitized_row[$field_key] = sanitize_text_field($row[$field_key]);
                    }
                }
            }
            if (!empty($sanitized_row)) {
                $sanitized[] = $sanitized_row;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX: Upload Media
     */
    public function ajax_upload_media() {
        check_ajax_referer('pt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول', 'profile-trader')]);
        }

        // Verify ownership if post_id provided
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($post_id && !$this->user_can_access($post_id)) {
            wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
        }

        // Get upload type and set size limit
        $upload_type = isset($_POST['upload_type']) ? sanitize_text_field($_POST['upload_type']) : 'gallery';
        $max_size = ($upload_type === 'logo') ? 2097152 : 10485760; // 2MB for logo, 10MB for gallery

        // Allowed file types
        $allowed_types = ['image/jpeg', 'image/png'];
        $allowed_extensions = ['jpg', 'jpeg', 'png'];

        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('media_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        $uploaded_files = [];

        // Handle single file upload (file field)
        if (!empty($_FILES['file'])) {
            $file = $_FILES['file'];

            // Validate file
            $validation_error = $this->validate_upload_file($file, $max_size, $allowed_types, $allowed_extensions);
            if ($validation_error) {
                wp_send_json_error(['message' => $validation_error]);
            }

            // Process upload
            $attachment_id = media_handle_upload('file', 0);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(['message' => $attachment_id->get_error_message()]);
            }

            // Set attachment author to current user
            wp_update_post([
                'ID' => $attachment_id,
                'post_author' => get_current_user_id()
            ]);

            // Resize logo to max 512x512 if it's a logo upload
            if ($upload_type === 'logo') {
                try {
                    $this->resize_logo_image($attachment_id, 512, 512);
                } catch (Exception $e) {
                    // Log error but don't fail the upload
                    error_log('Profile Trader: Error resizing logo - ' . $e->getMessage());
                }
            }

            // Convert to WebP format
            try {
                $this->convert_image_to_webp($attachment_id);
            } catch (Exception $e) {
                // Log error but don't fail the upload - keep original format
                error_log('Profile Trader: Error converting to WebP - ' . $e->getMessage());
            }

            $uploaded_files[] = [
                'id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'thumb' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
                'medium' => wp_get_attachment_image_url($attachment_id, 'medium'),
            ];
        }
        // Handle multiple file upload (files field)
        elseif (!empty($_FILES['files'])) {
            $files = $_FILES['files'];

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $temp_file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ];

                    // Validate file
                    $validation_error = $this->validate_upload_file($temp_file, $max_size, $allowed_types, $allowed_extensions);
                    if ($validation_error) {
                        continue; // Skip invalid files
                    }

                    $_FILES['upload'] = $temp_file;

                    $attachment_id = media_handle_upload('upload', 0);

                    if (!is_wp_error($attachment_id)) {
                        // Set attachment author to current user
                        wp_update_post([
                            'ID' => $attachment_id,
                            'post_author' => get_current_user_id()
                        ]);

                        // Convert to WebP format
                        try {
                            $this->convert_image_to_webp($attachment_id);
                        } catch (Exception $e) {
                            // Log error but don't fail the upload - keep original format
                            error_log('Profile Trader: Error converting to WebP - ' . $e->getMessage());
                        }

                        $uploaded_files[] = [
                            'id' => $attachment_id,
                            'url' => wp_get_attachment_url($attachment_id),
                            'thumb' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
                            'medium' => wp_get_attachment_image_url($attachment_id, 'medium'),
                        ];
                    }
                }
            }
        }

        if (empty($uploaded_files)) {
            wp_send_json_error(['message' => __('فشل في رفع الملفات', 'profile-trader')]);
        }

        wp_send_json_success(['files' => $uploaded_files]);
    }

    /**
     * Validate uploaded file
     */
    private function validate_upload_file($file, $max_size, $allowed_types, $allowed_extensions) {
        // Check file size
        if ($file['size'] > $max_size) {
            $max_mb = $max_size / 1048576;
            return sprintf(__('حجم الملف كبير جداً. الحد الأقصى: %s ميجابايت', 'profile-trader'), $max_mb);
        }

        // Check MIME type
        if (!in_array($file['type'], $allowed_types)) {
            return __('نوع الملف غير مدعوم. يرجى رفع صور بصيغة JPG أو PNG فقط', 'profile-trader');
        }

        // Check file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_extensions)) {
            return __('امتداد الملف غير مدعوم. يرجى رفع صور بصيغة JPG أو PNG فقط', 'profile-trader');
        }

        // Verify it's actually an image
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return __('الملف ليس صورة صالحة', 'profile-trader');
        }

        return false; // No error
    }
    
    /**
     * Resize logo image to maximum dimensions
     * 
     * @param int $attachment_id Attachment ID
     * @param int $max_width Maximum width in pixels
     * @param int $max_height Maximum height in pixels
     */
    private function resize_logo_image($attachment_id, $max_width, $max_height) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return;
        }
        
        // Get image editor
        $image_editor = wp_get_image_editor($file_path);
        
        if (is_wp_error($image_editor)) {
            return;
        }
        
        // Get current image size
        $size = $image_editor->get_size();
        if (is_wp_error($size)) {
            return;
        }
        
        $current_width = $size['width'];
        $current_height = $size['height'];
        
        // Check if resizing is needed
        if ($current_width <= $max_width && $current_height <= $max_height) {
            return; // No resize needed
        }
        
        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($max_width / $current_width, $max_height / $current_height);
        $new_width = (int) round($current_width * $ratio);
        $new_height = (int) round($current_height * $ratio);
        
        // Resize image
        $resized = $image_editor->resize($new_width, $new_height, false);
        
        if (is_wp_error($resized)) {
            return;
        }
        
        // Save resized image
        $saved = $image_editor->save($file_path);
        
        if (is_wp_error($saved)) {
            return;
        }
        
        // Regenerate attachment metadata
        wp_generate_attachment_metadata($attachment_id, $file_path);
    }
    
    /**
     * Convert image to WebP format
     * 
     * @param int $attachment_id Attachment ID
     * @return bool True on success, false on failure
     */
    private function convert_image_to_webp($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }
        
        // Check if WebP conversion is supported
        if (!function_exists('imagewebp') && !class_exists('Imagick')) {
            return false; // No WebP support available
        }
        
        // Get image editor
        $image_editor = wp_get_image_editor($file_path);
        
        if (is_wp_error($image_editor)) {
            return false;
        }
        
        // Check if image editor supports WebP
        $mime_type = wp_check_filetype($file_path)['type'];
        
        // Only convert JPEG and PNG images
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return false;
        }
        
        // Get file info
        $file_info = pathinfo($file_path);
        $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
        
        // Check if WebP already exists
        if (file_exists($webp_path)) {
            return true; // Already converted
        }
        
        // Try to save as WebP using WordPress image editor (if supported)
        $saved = $image_editor->save($webp_path, 'image/webp');
        
        if (is_wp_error($saved)) {
            // If direct WebP save fails, try using GD or Imagick fallback
            $converted = $this->convert_to_webp_fallback($file_path, $webp_path, $mime_type);
            
            if (!$converted) {
                return false; // Conversion failed, keep original
            }
        } else {
            // WordPress editor saved successfully
            if (isset($saved['path']) && file_exists($saved['path'])) {
                $webp_path = $saved['path'];
            } else {
                return false;
            }
        }
        
        // If WebP conversion succeeded, replace original file
        if (file_exists($webp_path)) {
            // Update attachment to use WebP file
            update_attached_file($attachment_id, $webp_path);
            
            // Delete old file
            if ($webp_path !== $file_path && file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // Update attachment metadata
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $webp_path));
            
            // Update MIME type
            wp_update_post([
                'ID' => $attachment_id,
                'post_mime_type' => 'image/webp'
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Fallback WebP conversion using GD or Imagick
     * 
     * @param string $source_path Source image path
     * @param string $webp_path Destination WebP path
     * @param string $mime_type Image MIME type
     * @return bool True on success, false on failure
     */
    private function convert_to_webp_fallback($source_path, $webp_path, $mime_type) {
        // Try Imagick first
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick($source_path);
                $imagick->setImageFormat('webp');
                $imagick->setImageCompressionQuality(85); // Good quality, smaller file
                $imagick->writeImage($webp_path);
                $imagick->clear();
                $imagick->destroy();
                
                if (file_exists($webp_path)) {
                    return true;
                }
            } catch (Exception $e) {
                error_log('Profile Trader: Imagick WebP conversion failed - ' . $e->getMessage());
            }
        }
        
        // Try GD library
        if (function_exists('imagewebp')) {
            try {
                // Load source image based on type
                if ($mime_type === 'image/jpeg') {
                    $image = imagecreatefromjpeg($source_path);
                } elseif ($mime_type === 'image/png') {
                    $image = imagecreatefrompng($source_path);
                    
                    // Preserve transparency for PNG
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                } else {
                    return false;
                }
                
                if (!$image) {
                    return false;
                }
                
                // Convert to WebP with quality 85
                $result = imagewebp($image, $webp_path, 85);
                imagedestroy($image);
                
                return $result && file_exists($webp_path);
            } catch (Exception $e) {
                error_log('Profile Trader: GD WebP conversion failed - ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * AJAX: Upload Avatar
     */
    public function ajax_upload_avatar() {
        check_ajax_referer('pt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول', 'profile-trader')]);
        }

        $user_id = get_current_user_id();
        $max_size = 2097152; // 2MB
        $allowed_types = ['image/jpeg', 'image/png'];
        $allowed_extensions = ['jpg', 'jpeg', 'png'];

        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('media_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => __('لم يتم اختيار ملف', 'profile-trader')]);
        }

        $file = $_FILES['file'];

        // Validate file
        $validation_error = $this->validate_upload_file($file, $max_size, $allowed_types, $allowed_extensions);
        if ($validation_error) {
            wp_send_json_error(['message' => $validation_error]);
        }

        // Process upload
        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => $attachment_id->get_error_message()]);
        }

        // Set attachment author to current user
        wp_update_post([
            'ID' => $attachment_id,
            'post_author' => $user_id
        ]);

        // Convert to WebP format
        try {
            $this->convert_image_to_webp($attachment_id);
        } catch (Exception $e) {
            // Log error but don't fail the upload - keep original format
            error_log('Profile Trader: Error converting avatar to WebP - ' . $e->getMessage());
        }

        // Delete old avatar if exists
        $old_avatar_id = get_user_meta($user_id, 'pt_user_avatar', true);
        if ($old_avatar_id && $old_avatar_id != $attachment_id) {
            wp_delete_attachment($old_avatar_id, true);
        }

        // Save new avatar to user meta
        update_user_meta($user_id, 'pt_user_avatar', $attachment_id);

        // Get avatar URLs
        $avatar_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        $avatar_full = wp_get_attachment_image_url($attachment_id, 'full');

        wp_send_json_success([
            'message' => __('تم رفع الصورة بنجاح', 'profile-trader'),
            'avatar_url' => $avatar_url,
            'avatar_full' => $avatar_full,
            'attachment_id' => $attachment_id,
        ]);
    }
    
    /**
     * AJAX: Delete Media
     */
    public function ajax_delete_media() {
        check_ajax_referer('pt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('يجب تسجيل الدخول', 'profile-trader')]);
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => __('معرف الملف غير صحيح', 'profile-trader')]);
        }
        
        // Verify the user owns this attachment
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => __('ليس لديك صلاحية لحذف هذا الملف', 'profile-trader')]);
        }
        
        wp_delete_attachment($attachment_id, true);
        
        wp_send_json_success(['message' => __('تم الحذف بنجاح', 'profile-trader')]);
    }
    
    /**
     * Filter avatar data to use custom uploaded avatar
     */
    public function filter_avatar_data($args, $id_or_email) {
        // Get user ID
        $user_id = false;
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        } elseif ($id_or_email instanceof WP_User) {
            $user_id = $id_or_email->ID;
        }
        
        if (!$user_id) {
            return $args;
        }
        
        // Get custom avatar
        $avatar_id = get_user_meta($user_id, 'pt_user_avatar', true);
        if ($avatar_id) {
            $size = isset($args['size']) ? (int) $args['size'] : 96;
            $avatar_url = wp_get_attachment_image_url($avatar_id, [$size, $size]);
            
            if ($avatar_url) {
                $args['url'] = $avatar_url;
                $args['found_avatar'] = true;
            }
        }
        
        return $args;
    }
    
    /**
     * Normalize gallery meta field to always return string format
     * This ensures JetEngine/Bricks compatibility (they expect comma-separated string, not array)
     * 
     * @param mixed $value The meta value (null if not yet retrieved)
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param bool $single Whether to return a single value
     * @return mixed Normalized value
     */
    public function normalize_gallery_meta($value, $post_id, $meta_key, $single) {
        // Only process gallery field
        if ($meta_key !== 'gallary') {
            return $value;
        }
        
        // Static flag to prevent infinite recursion
        static $processing = [];
        $cache_key = $post_id . '_' . $meta_key;
        
        if (isset($processing[$cache_key])) {
            return $value;
        }
        
        $processing[$cache_key] = true;
        
        // Get raw meta value from database (bypass filters to avoid recursion)
        global $wpdb;
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
            $post_id,
            $meta_key
        ));
        
        // If no value found, return null to let WordPress handle it
        if ($meta_value === null) {
            unset($processing[$cache_key]);
            return $value;
        }
        
        // Try to unserialize (WordPress stores arrays as serialized)
        $unserialized = maybe_unserialize($meta_value);
        
        // If already a string or numeric, return as-is
        if (is_string($unserialized) && !empty(trim($unserialized))) {
            unset($processing[$cache_key]);
            return $single ? $unserialized : [$unserialized];
        }
        
        // If it's an array, convert to comma-separated string
        if (is_array($unserialized)) {
            // Filter and convert to integers
            $gallery_ids = array_filter(array_map('intval', $unserialized), function($v) {
                return $v > 0;
            });
            
            // Convert to string
            $normalized_value = !empty($gallery_ids) ? implode(',', array_values($gallery_ids)) : '';
            
            // Update the meta value in database immediately to fix it permanently
            update_post_meta($post_id, $meta_key, $normalized_value);
            
            unset($processing[$cache_key]);
            return $single ? $normalized_value : [$normalized_value];
        }
        
        // If empty string, return empty
        if (empty($unserialized)) {
            unset($processing[$cache_key]);
            return $single ? '' : [''];
        }
        
        unset($processing[$cache_key]);
        return $value;
    }
    
    /**
     * Migrate all gallery meta fields from array to string format
     * Run this once to fix all existing data
     */
    public function migrate_gallery_meta_to_string() {
        global $wpdb;
        
        // Get all posts with gallery meta
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND post_id IN (
                 SELECT ID FROM {$wpdb->posts} WHERE post_type = %s
             )",
            'gallary',
            PT_POST_TYPE
        ));
        
        $updated = 0;
        
        foreach ($posts as $post) {
            $meta_value = maybe_unserialize($post->meta_value);
            
            // Skip if already a string
            if (is_string($meta_value) && !is_serialized($meta_value)) {
                continue;
            }
            
            // Convert array to string
            if (is_array($meta_value)) {
                $gallery_ids = array_filter(array_map('intval', $meta_value), function($v) {
                    return $v > 0;
                });
                
                $normalized_value = !empty($gallery_ids) ? implode(',', array_values($gallery_ids)) : '';
                
                update_post_meta($post->post_id, 'gallary', $normalized_value);
                $updated++;
            }
        }
        
        return $updated;
    }
    
    /**
     * Check and run gallery meta migration if needed (one-time)
     */
    public function maybe_migrate_gallery_meta() {
        // Only run once, check if migration flag exists
        $migration_done = get_option('pt_gallery_meta_migrated', false);
        
        if (!$migration_done && current_user_can('manage_options')) {
            $updated = $this->migrate_gallery_meta_to_string();
            if ($updated > 0) {
                update_option('pt_gallery_meta_migrated', true);
                // Show admin notice
                add_action('admin_notices', function() use ($updated) {
                    echo '<div class="notice notice-success is-dismissible"><p>';
                    printf(__('تم تحويل %d من حقول معرض الصور من صيغة المصفوفة إلى صيغة النص.', 'profile-trader'), $updated);
                    echo '</p></div>';
                });
            } else {
                // Mark as done even if nothing to update
                update_option('pt_gallery_meta_migrated', true);
            }
        }
    }
    
    /**
     * AJAX handler to manually trigger gallery meta migration
     */
    public function ajax_migrate_gallery_meta() {
        check_ajax_referer('pt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('ليس لديك صلاحية', 'profile-trader')]);
        }
        
        // Reset migration flag to allow re-running
        delete_option('pt_gallery_meta_migrated');
        
        $updated = $this->migrate_gallery_meta_to_string();
        
        update_option('pt_gallery_meta_migrated', true);
        
        wp_send_json_success([
            'message' => sprintf(__('تم تحويل %d من حقول معرض الصور.', 'profile-trader'), $updated),
            'updated' => $updated
        ]);
    }
    
    /**
     * Add custom dashboard widgets
     */
    public function add_dashboard_widgets() {
        // Stats Overview Widget
        wp_add_dashboard_widget(
            'pt_stats_overview',
            __('إحصائيات دليل التجار', 'profile-trader'),
            [$this, 'render_stats_widget']
        );
        
        // Pending Approvals Widget
        wp_add_dashboard_widget(
            'pt_pending_approvals',
            __('قيد المراجعة', 'profile-trader'),
            [$this, 'render_pending_widget']
        );
        
        // Recent Activity Widget
        wp_add_dashboard_widget(
            'pt_recent_activity',
            __('النشاط الأخير', 'profile-trader'),
            [$this, 'render_recent_activity_widget']
        );
        
        // Connection Status Widget
        wp_add_dashboard_widget(
            'pt_connection_status',
            __('حالة الربط', 'profile-trader'),
            [$this, 'render_connection_widget']
        );
    }
    
    /**
     * Render stats overview widget
     */
    public function render_stats_widget() {
        $traders = wp_count_posts(PT_POST_TYPE);
        $jobs = wp_count_posts('job');
        
        $total_traders = $traders->publish ?? 0;
        $pending_traders = $traders->pending ?? 0;
        $draft_traders = $traders->draft ?? 0;
        $total_jobs = $jobs->publish ?? 0;
        
        // Count featured traders
        global $wpdb;
        $featured_traders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value = '1'",
            'is_featured'
        ));
        
        // Count connected traders
        $connected = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value != ''",
            '_trader_user_id'
        ));
        
        ?>
        <div class="pt-dashboard-stats" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 10px; font-family: 'Cairo', Arial, sans-serif;">
            <div style="padding: 15px; background: #f0fdf4; border-right: 4px solid #10b981; border-radius: 4px;">
                <div style="font-size: 28px; font-weight: 700; color: #10b981;"><?php echo $total_traders; ?></div>
                <div style="color: #666; margin-top: 5px;"><?php _e('تجار منشورين', 'profile-trader'); ?></div>
            </div>
            
            <div style="padding: 15px; background: #fef3c7; border-right: 4px solid #f59e0b; border-radius: 4px;">
                <div style="font-size: 28px; font-weight: 700; color: #f59e0b;"><?php echo $pending_traders; ?></div>
                <div style="color: #666; margin-top: 5px;"><?php _e('قيد المراجعة', 'profile-trader'); ?></div>
            </div>
            
            <div style="padding: 15px; background: #f3f4f6; border-right: 4px solid #6b7280; border-radius: 4px;">
                <div style="font-size: 28px; font-weight: 700; color: #6b7280;"><?php echo $draft_traders; ?></div>
                <div style="color: #666; margin-top: 5px;"><?php _e('مسودات', 'profile-trader'); ?></div>
            </div>
            
            <div style="padding: 15px; background: #dbeafe; border-right: 4px solid #3b82f6; border-radius: 4px;">
                <div style="font-size: 28px; font-weight: 700; color: #3b82f6;"><?php echo $total_jobs; ?></div>
                <div style="color: #666; margin-top: 5px;"><?php _e('وظائف', 'profile-trader'); ?></div>
            </div>
        </div>
        
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            <div style="padding: 10px; background: #f9fafb; border-radius: 4px;">
                <div style="font-size: 18px; font-weight: 600; color: #0A4E45;"><?php echo $featured_traders; ?></div>
                <div style="font-size: 12px; color: #666; margin-top: 3px;"><?php _e('مميز', 'profile-trader'); ?></div>
            </div>
            <div style="padding: 10px; background: #f9fafb; border-radius: 4px;">
                <div style="font-size: 18px; font-weight: 600; color: #0A4E45;"><?php echo $connected; ?></div>
                <div style="font-size: 12px; color: #666; margin-top: 3px;"><?php _e('مرتبط', 'profile-trader'); ?></div>
            </div>
        </div>
        
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
            <a href="<?php echo admin_url('edit.php?post_type=' . PT_POST_TYPE); ?>" 
               style="text-decoration: none; color: #0A4E45; font-weight: 600;">
                <?php _e('عرض جميع التجار →', 'profile-trader'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Render pending approvals widget
     */
    public function render_pending_widget() {
        $pending = get_posts([
            'post_type' => PT_POST_TYPE,
            'post_status' => 'pending',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        if (empty($pending)) {
            echo '<p style="color: #666; font-family: \'Cairo\', Arial, sans-serif;">' . __('لا توجد إعلانات قيد المراجعة', 'profile-trader') . '</p>';
            return;
        }
        
        echo '<ul style="list-style: none; padding: 0; margin: 0; font-family: \'Cairo\', Arial, sans-serif;">';
        foreach ($pending as $post) {
            $edit_link = admin_url('post.php?action=edit&post=' . $post->ID);
            $date = date_i18n(get_option('date_format'), strtotime($post->post_date));
            ?>
            <li style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
                <a href="<?php echo $edit_link; ?>" style="text-decoration: none; color: #0A4E45; font-weight: 600;">
                    <?php echo esc_html($post->post_title); ?>
                </a>
                <div style="font-size: 12px; color: #999; margin-top: 3px;">
                    <?php echo $date; ?>
                </div>
            </li>
            <?php
        }
        echo '</ul>';
        
        $all_pending = wp_count_posts(PT_POST_TYPE)->pending ?? 0;
        if ($all_pending > 5) {
            ?>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                <a href="<?php echo admin_url('edit.php?post_type=' . PT_POST_TYPE . '&post_status=pending'); ?>" 
                   style="text-decoration: none; color: #0A4E45; font-weight: 600;">
                    <?php printf(__('عرض جميع (%d) →', 'profile-trader'), $all_pending); ?>
                </a>
            </div>
            <?php
        }
    }
    
    /**
     * Render recent activity widget
     */
    public function render_recent_activity_widget() {
        $recent = get_posts([
            'post_type' => PT_POST_TYPE,
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => 5,
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);
        
        if (empty($recent)) {
            echo '<p style="color: #666; font-family: \'Cairo\', Arial, sans-serif;">' . __('لا يوجد نشاط حديث', 'profile-trader') . '</p>';
            return;
        }
        
        echo '<ul style="list-style: none; padding: 0; margin: 0; font-family: \'Cairo\', Arial, sans-serif;">';
        foreach ($recent as $post) {
            $edit_link = admin_url('post.php?action=edit&post=' . $post->ID);
            $modified = human_time_diff(strtotime($post->post_modified), current_time('timestamp'));
            $status_label = $post->post_status === 'pending' ? 
                '<span style="background: #fef3c7; color: #f59e0b; padding: 2px 8px; border-radius: 3px; font-size: 11px;">قيد المراجعة</span>' : 
                '<span style="background: #d1fae5; color: #10b981; padding: 2px 8px; border-radius: 3px; font-size: 11px;">منشور</span>';
            ?>
            <li style="padding: 10px 0; border-bottom: 1px solid #e5e7eb;">
                <a href="<?php echo $edit_link; ?>" style="text-decoration: none; color: #0A4E45; font-weight: 600;">
                    <?php echo esc_html($post->post_title); ?>
                </a>
                <div style="font-size: 12px; color: #999; margin-top: 5px; display: flex; align-items: center; gap: 8px;">
                    <?php echo $status_label; ?>
                    <span>منذ <?php echo $modified; ?></span>
                </div>
            </li>
            <?php
        }
        echo '</ul>';
    }
    
    /**
     * Render connection status widget
     */
    public function render_connection_widget() {
        global $wpdb;
        
        $total_traders = wp_count_posts(PT_POST_TYPE)->publish ?? 0;
        $connected = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value != ''",
            '_trader_user_id'
        ));
        $unconnected = $total_traders - $connected;
        $percentage = $total_traders > 0 ? round(($connected / $total_traders) * 100) : 0;
        
        ?>
        <div style="margin-top: 10px; font-family: 'Cairo', Arial, sans-serif;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span style="color: #666;"><?php _e('مرتبط', 'profile-trader'); ?></span>
                <strong style="color: #10b981;"><?php echo $connected; ?> / <?php echo $total_traders; ?></strong>
            </div>
            
            <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="background: #10b981; height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s;"></div>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: #f3f4f6; border-radius: 4px;">
                <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                    <?php _e('غير مرتبط', 'profile-trader'); ?>
                </div>
                <div style="font-size: 20px; font-weight: 700; color: #f59e0b;">
                    <?php echo $unconnected; ?>
                </div>
            </div>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                <a href="<?php echo admin_url('edit.php?post_type=' . PT_POST_TYPE . '&page=pt-connection-settings'); ?>" 
                   style="text-decoration: none; color: #0A4E45; font-weight: 600;">
                    <?php _e('إدارة الربط →', 'profile-trader'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Load ads archive page
     */
    public function ajax_load_ads_archive() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pt_archive_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 12;
        // Validate per_page value (only allow 12, 24, or 32)
        if (!in_array($per_page, [12, 24, 32])) {
            $per_page = 12;
        }
        $posts_per_page = $per_page;
        
        // Get sort parameter
        $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc';
        $valid_sorts = ['date_desc', 'date_asc', 'price_asc', 'price_desc', 'title_asc'];
        if (!in_array($sort, $valid_sorts)) {
            $sort = 'date_desc';
        }
        
        // Parse sort parameter
        $orderby = 'date';
        $order = 'DESC';
        $meta_key = '';
        
        switch ($sort) {
            case 'date_asc':
                $orderby = 'date';
                $order = 'ASC';
                break;
            case 'price_asc':
                $orderby = 'meta_value_num';
                $order = 'ASC';
                $meta_key = 'price_ads';
                break;
            case 'price_desc':
                $orderby = 'meta_value_num';
                $order = 'DESC';
                $meta_key = 'price_ads';
                break;
            case 'title_asc':
                $orderby = 'title';
                $order = 'ASC';
                break;
            default: // date_desc
                $orderby = 'date';
                $order = 'DESC';
        }
        
        // Build query args
        $query_args = [
            'post_type' => 'ads',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $page,
            'orderby' => $orderby,
            'order' => $order
        ];
        
        // Add meta_key for price sorting
        if ($meta_key) {
            $query_args['meta_key'] = $meta_key;
            // Only include posts that have price_ads meta
            $query_args['meta_query'] = [
                [
                    'key' => 'price_ads',
                    'compare' => 'EXISTS'
                ]
            ];
        }
        
        // Query ads
        $ads_query = new WP_Query($query_args);
        
        // Generate HTML
        ob_start();
        if ($ads_query->have_posts()):
            while ($ads_query->have_posts()): $ads_query->the_post();
                $ad_id = get_the_ID();
                $ad_price = get_post_meta($ad_id, 'price_ads', true);
                $ad_location = get_post_meta($ad_id, 'ad_location', true);
                $contact_number = get_post_meta($ad_id, 'contact_number', true);
                $whatsapp = get_post_meta($ad_id, 'whatsapp', true);
                $ad_thumb = get_the_post_thumbnail_url($ad_id, 'medium');
                $short_desc = get_post_meta($ad_id, 'short_desc', true);
                
                // Calculate days since posted
                $post_date = strtotime(get_the_date('c'));
                $days_ago = floor((time() - $post_date) / 86400);
                if ($days_ago == 0) {
                    $date_label = 'اليوم';
                } elseif ($days_ago == 1) {
                    $date_label = 'أمس';
                } else {
                    $date_label = sprintf('منذ %d يوم', $days_ago);
                }
                ?>
                <div class="pt-ad-card">
                    <div class="pt-ad-card-image-wrapper">
                        <a href="<?php the_permalink(); ?>" class="pt-ad-card-image-link">
                            <?php if ($ad_thumb): ?>
                            <img src="<?php echo esc_url($ad_thumb); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="pt-ad-card-image">
                            <?php else: ?>
                            <div class="pt-ad-card-image pt-no-img-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                            </div>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="pt-ad-card-content">
                        <a href="<?php the_permalink(); ?>" class="pt-ad-card-link">
                            <h4 class="pt-ad-card-title"><?php the_title(); ?></h4>
                            <?php if ($ad_price): ?>
                            <div class="pt-ad-card-price"><?php echo esc_html($ad_price); ?></div>
                            <?php endif; ?>
                        </a>
                        
                        <?php if ($short_desc): ?>
                        <div class="pt-ad-specs-grid">
                            <div class="pt-spec-item">
                                <svg class="pt-spec-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                </svg>
                                <span class="pt-spec-text"><?php echo esc_html(wp_trim_words($short_desc, 3)); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="pt-ad-card-footer">
                            <div class="pt-ad-card-meta">
                                <?php if ($ad_location): ?>
                                <span class="pt-ad-card-location">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <span><?php echo esc_html($ad_location); ?></span>
                                </span>
                                <?php endif; ?>
                                <span class="pt-ad-card-date"><?php echo esc_html($date_label); ?></span>
                            </div>
                            
                            <div class="pt-ad-card-actions">
                                <?php if ($contact_number): ?>
                                <a href="tel:<?php echo esc_attr($contact_number); ?>" class="pt-action-btn pt-action-btn-call" onclick="event.stopPropagation();">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <span>اتصال</span>
                                </a>
                                <?php endif; ?>
                                <?php if ($whatsapp): ?>
                                <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $whatsapp)); ?>?text=<?php echo urlencode('مرحباً، أنا مهتم بالإعلان: ' . get_the_title()); ?>" target="_blank" class="pt-action-btn pt-action-btn-message" onclick="event.stopPropagation();">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <span>رسالة</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            endwhile;
            wp_reset_postdata();
        else:
            ?>
            <div class="pt-empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="9" y1="3" x2="9" y2="21"></line>
                </svg>
                <p>لا توجد إعلانات منشورة</p>
            </div>
            <?php
        endif;
        $html = ob_get_clean();
        
        // Generate pagination
        ob_start();
        if ($ads_query->max_num_pages > 1) {
            // Add per_page and sort to pagination links
            $pagination_base = add_query_arg([
                'per_page' => $per_page,
                'sort' => $sort
            ], get_pagenum_link(1, false));
            $pagination_base = remove_query_arg('paged', $pagination_base);
            
            echo paginate_links([
                'total' => $ads_query->max_num_pages,
                'current' => $page,
                'prev_text' => '&laquo; السابق',
                'next_text' => 'التالي &raquo;',
                'type' => 'list',
                'end_size' => 2,
                'mid_size' => 2,
                'base' => $pagination_base . '%_%',
                'format' => '&paged=%#%',
            ]);
        }
        $pagination = ob_get_clean();
        
        // Generate count text
        $count_text = sprintf('عرض %d من %d إعلان', $ads_query->post_count, $ads_query->found_posts);
        
        wp_send_json_success([
            'html' => $html,
            'pagination' => $pagination,
            'count' => $count_text
        ]);
    }

    /**
     * Enqueue admin assets for ads-trader meta box
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;

        // Only load on ads post type edit screens
        if (!in_array($hook, ['post.php', 'post-new.php']) || $post_type !== 'ads') {
            return;
        }

        // Select2 CSS & JS
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );

        // Inline styles for RTL and custom styling
        wp_add_inline_style('select2', '
            .pt-trader-select-wrap { margin-top: 10px; }
            .pt-trader-select-wrap .select2-container { width: 100% !important; }
            .pt-trader-select-wrap .select2-selection--single { height: 38px; border-color: #8c8f94; }
            .pt-trader-select-wrap .select2-selection__rendered { line-height: 36px; padding-right: 12px; }
            .pt-trader-select-wrap .select2-selection__arrow { height: 36px; }
            .pt-trader-option { display: flex; align-items: center; gap: 10px; }
            .pt-trader-option img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }
            .pt-trader-option .pt-no-logo { width: 30px; height: 30px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #6b7280; }
            .pt-current-trader { margin-top: 12px; padding: 10px; background: #f0f6fc; border-radius: 6px; display: flex; align-items: center; gap: 10px; }
            .pt-current-trader img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
            .pt-current-trader .pt-no-logo { width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #6b7280; }
            .pt-current-trader-info { flex: 1; }
            .pt-current-trader-name { font-weight: 600; color: #1e3a5f; }
            .pt-current-trader-label { font-size: 11px; color: #6b7280; }
        ');

        // Inline script for Select2 initialization (simple, no AJAX)
        $select2_script = <<<'JS'
jQuery(document).ready(function($) {
    var $select = $("#pt_trader_link_select");
    var $backup = $("#pt_trader_link_backup");
    
    // Get initial value from select or backup field
    var initialValue = $select.val() || $backup.val() || "";
    
    // Initialize Select2 with the current value
    $select.select2({
        placeholder: "ابحث عن تاجر...",
        allowClear: true,
        dir: "rtl",
        language: {
            noResults: function() { return "لا توجد نتائج"; }
        }
    });
    
    // Ensure Select2 displays the current value - wait a bit for Select2 to fully initialize
    setTimeout(function() {
        if (initialValue) {
            $select.val(initialValue).trigger('change.select2');
            $backup.val(initialValue);
            console.log("Select2 initialized with trader ID:", initialValue);
        }
    }, 100);
    
    // Sync Select2 value to actual select and backup field on change
    $select.on("change", function() {
        var val = $(this).val() || "";
        $backup.val(val);
        $(this).val(val);
        console.log("Trader selected:", val);
    });
    
    // CRITICAL: Before form submission, ensure values are synced
    $(document).on("submit", "#post", function(e) {
        var selectedValue = $select.val() || "";
        $select.val(selectedValue);
        $backup.val(selectedValue);
        if ($select[0]) {
            $select[0].value = selectedValue;
        }
        console.log("Form submitting with trader_id:", selectedValue);
        var selectVal = $select[0] ? $select[0].value : "N/A";
        console.log("Select element value:", selectVal);
        console.log("Backup field value:", $backup.val());
        if (selectedValue) {
            alert("Saving trader ID: " + selectedValue + "\nCheck console and debug.log for details");
        }
    });
    
    // Also hook into WordPress save button clicks
    $(document).on("click", "#publish, #save-post, input[name='save']", function() {
        var selectedValue = $select.val() || "";
        $select.val(selectedValue);
        if ($select[0]) {
            $select[0].value = selectedValue;
        }
        $backup.val(selectedValue);
        console.log("Save button clicked - trader_id:", selectedValue);
    });
});
JS;
        wp_add_inline_script('select2', $select2_script);
    }

    /**
     * Add meta box for ads-trader relationship
     */
    public function add_ads_trader_meta_box() {
        add_meta_box(
            'pt_ads_trader_link',
            __('ربط التاجر', 'profile-trader'),
            [$this, 'render_ads_trader_meta_box'],
            'ads',
            'side',
            'high'
        );
    }

    /**
     * Render the ads-trader meta box
     */
    public function render_ads_trader_meta_box($post) {
        wp_nonce_field('pt_ads_trader_link', 'pt_trader_link_nonce');

        // Get trader ID from our custom meta key
        $current_trader_id = intval(get_post_meta($post->ID, '_pt_trader_id', true));

        // Fallback to trader_link for existing ads (migration support)
        if (!$current_trader_id) {
            $trader_link = get_post_meta($post->ID, 'trader_link', true);
            if (is_numeric($trader_link)) {
                $current_trader_id = intval($trader_link);
            }
        }

        // Get all traders
        $all_traders = get_posts([
            'post_type' => 'trader',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        ?>
        <div class="pt-trader-select-wrap">
            <select id="pt_trader_link_select" name="pt_trader_link" style="width: 100%;">
                <option value="">-- اختر تاجر --</option>
                <?php foreach ($all_traders as $trader): ?>
                    <option value="<?php echo esc_attr($trader->ID); ?>" <?php selected($current_trader_id, $trader->ID); ?>>
                        <?php echo esc_html($trader->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <!-- Hidden backup field to ensure value is submitted -->
            <input type="hidden" name="pt_trader_link_backup" id="pt_trader_link_backup" value="<?php echo esc_attr($current_trader_id); ?>" />
            
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <!-- Debug: Current trader ID being displayed: <?php echo $current_trader_id; ?> -->
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save ads-trader relationship
     */
    public function save_ads_trader_meta($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check post type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'ads') {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Debug: Log all POST data related to trader (temporary - remove after fixing)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Save Trader Meta - POST data: " . print_r([
                'pt_trader_link' => isset($_POST['pt_trader_link']) ? $_POST['pt_trader_link'] : 'NOT SET',
                'pt_trader_link_backup' => isset($_POST['pt_trader_link_backup']) ? $_POST['pt_trader_link_backup'] : 'NOT SET',
                'nonce' => isset($_POST['pt_trader_link_nonce']) ? 'SET' : 'NOT SET',
                'post_id' => $post_id,
                'post_type' => $post->post_type
            ], true));
        }

        // Save or delete trader link - check both main field and backup
        // Don't require nonce if we have data (allows programmatic saves)
        $trader_id = 0;
        
        // Priority 1: Check main select field
        if (isset($_POST['pt_trader_link']) && $_POST['pt_trader_link'] !== '' && $_POST['pt_trader_link'] !== '0' && $_POST['pt_trader_link'] !== null) {
            $trader_id = intval($_POST['pt_trader_link']);
        }
        // Priority 2: Check backup hidden field
        if ($trader_id === 0 && isset($_POST['pt_trader_link_backup']) && $_POST['pt_trader_link_backup'] !== '' && $_POST['pt_trader_link_backup'] !== '0' && $_POST['pt_trader_link_backup'] !== null) {
            $trader_id = intval($_POST['pt_trader_link_backup']);
        }
        
        // Also check for any variations of the field name
        if ($trader_id === 0) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'trader_link') !== false && !empty($value) && is_numeric($value)) {
                    $trader_id = intval($value);
                    break;
                }
            }
        }
        
        // Debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Trader ID extracted: $trader_id");
        }
        
        if ($trader_id > 0) {
            // Verify trader exists
            $trader_post = get_post($trader_id);
            if ($trader_post && $trader_post->post_type === 'trader') {
                // Save to our own meta key (not affected by JetEngine)
                update_post_meta($post_id, '_pt_trader_id', $trader_id);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("SUCCESS: Trader saved - Ad ID $post_id -> Trader ID $trader_id");
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("ERROR: Trader ID $trader_id does not exist or is not a trader post type");
                }
            }
        } else {
            // If trader_id is 0, check if user explicitly cleared it
            $field_in_post = isset($_POST['pt_trader_link']);
            $backup_in_post = isset($_POST['pt_trader_link_backup']);

            if ($field_in_post || $backup_in_post) {
                $main_empty = !$field_in_post || $_POST['pt_trader_link'] === '' || $_POST['pt_trader_link'] === '0';
                $backup_empty = !$backup_in_post || $_POST['pt_trader_link_backup'] === '' || $_POST['pt_trader_link_backup'] === '0';

                if ($main_empty && $backup_empty) {
                    delete_post_meta($post_id, '_pt_trader_id');
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Trader cleared for Ad ID $post_id");
                    }
                }
            }
        }
    }
}

// Initialize plugin
Profile_Trader::get_instance();

