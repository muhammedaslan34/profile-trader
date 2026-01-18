<?php
/**
 * Admin Settings Class
 * Provides admin interface for managing trader connections
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

class PT_Admin_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=' . PT_POST_TYPE,
            __('إعدادات لوحة التاجر', 'profile-trader'),
            __('إعدادات الربط', 'profile-trader'),
            'manage_options',
            'pt-connection-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'edit.php?post_type=' . PT_POST_TYPE,
            __('الأكواد المختصرة', 'profile-trader'),
            __('الأكواد المختصرة', 'profile-trader'),
            'manage_options',
            'pt-shortcodes',
            [$this, 'render_shortcodes_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'pt-connection-settings') === false) {
            return;
        }
        
        wp_enqueue_style('pt-admin', PT_PLUGIN_URL . 'assets/css/admin.css', [], PT_VERSION);
        wp_enqueue_script('pt-admin', PT_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], PT_VERSION, true);
        
        wp_localize_script('pt-admin', 'ptAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pt_nonce'),
        ]);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $connection = PT_Trader_Connection::get_instance();
        
        // Get stats
        $total_traders = wp_count_posts(PT_POST_TYPE);
        $total_published = $total_traders->publish ?? 0;
        
        // Count connected traders
        global $wpdb;
        $connected_by_meta = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
            PT_Trader_Connection::USER_META_KEY
        ));
        
        // Count traders with emails
        $traders_with_email = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = 'email' AND meta_value != ''"
        );
        
        // Count potential matches (traders with emails that match existing users)
        $potential_matches = $wpdb->get_var("
            SELECT COUNT(DISTINCT pm.post_id) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->users} u ON LOWER(pm.meta_value) = LOWER(u.user_email)
            WHERE pm.meta_key = 'email' AND pm.meta_value != ''
        ");
        
        ?>
        <div class="wrap pt-admin-wrap">
            <h1><?php _e('إعدادات ربط التجار', 'profile-trader'); ?></h1>
            
            <div class="pt-admin-grid">
                <!-- Stats Cards -->
                <div class="pt-admin-card pt-stats-card">
                    <h2><?php _e('إحصائيات', 'profile-trader'); ?></h2>
                    <div class="pt-stats-grid">
                        <div class="pt-stat">
                            <span class="pt-stat-number"><?php echo $total_published; ?></span>
                            <span class="pt-stat-label"><?php _e('إجمالي التجار', 'profile-trader'); ?></span>
                        </div>
                        <div class="pt-stat">
                            <span class="pt-stat-number"><?php echo $connected_by_meta; ?></span>
                            <span class="pt-stat-label"><?php _e('مرتبط بمستخدم', 'profile-trader'); ?></span>
                        </div>
                        <div class="pt-stat">
                            <span class="pt-stat-number"><?php echo $traders_with_email; ?></span>
                            <span class="pt-stat-label"><?php _e('لديه إيميل', 'profile-trader'); ?></span>
                        </div>
                        <div class="pt-stat">
                            <span class="pt-stat-number"><?php echo $potential_matches; ?></span>
                            <span class="pt-stat-label"><?php _e('تطابق محتمل', 'profile-trader'); ?></span>
                        </div>
                    </div>
                    
                    
                </div>
                
                <!-- Row: Auto Connect, Create Users, Database Optimization -->
                <div class="pt-admin-row">
                    <!-- Auto Connect Tool -->
                    <div class="pt-admin-card pt-connection-card">
                        <h2><?php _e('الربط التلقائي بالإيميل', 'profile-trader'); ?></h2>
                        <p><?php _e('يقوم بربط التجار الذين لديهم إيميل يطابق إيميل مستخدم موجود.', 'profile-trader'); ?></p>
                        <button type="button" class="button button-primary" id="pt-auto-connect">
                            <?php _e('ربط تلقائي', 'profile-trader'); ?>
                        </button>
                        <div id="pt-auto-connect-result" class="pt-result"></div>

                        

                        <!-- Manual Connection Section -->
                        <div style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #f3f4f6;">
                            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; font-weight: 700; color: #0A4E45;"><?php _e('ربط يدوي', 'profile-trader'); ?></h3>
                            <p style="margin-bottom: 15px;"><?php _e('اختر تاجر ومستخدم لربطهما يدوياً.', 'profile-trader'); ?></p>
                            
                            <div class="pt-manual-connect-form">
                                <div class="pt-form-row">
                                    <label for="pt-trader-search"><?php _e('التاجر:', 'profile-trader'); ?></label>
                                    <div class="pt-trader-search-container">
                                        <input type="hidden" id="pt-trader-select" value="" />
                                        <input type="text"
                                               id="pt-trader-search"
                                               class="regular-text"
                                               placeholder="<?php _e('ابحث بالاسم أو الإيميل أو السجل التجاري...', 'profile-trader'); ?>"
                                               autocomplete="off" />
                                        <div id="pt-trader-suggestions" class="pt-search-suggestions"></div>
                                    </div>
                                </div>
                                
                                <div class="pt-form-row">
                                    <label for="pt-user-search"><?php _e('المستخدم:', 'profile-trader'); ?></label>
                                    <div class="pt-user-search-container">
                                        <input type="hidden" id="pt-user-select" value="" />
                                        <input type="text"
                                               id="pt-user-search"
                                               class="regular-text"
                                               placeholder="<?php _e('ابحث بالاسم أو الإيميل...', 'profile-trader'); ?>"
                                               autocomplete="off" />
                                        <div id="pt-user-suggestions" class="pt-search-suggestions"></div>
                                    </div>
                                </div>
                                
                                <div class="pt-form-row">
                                    <button type="button" class="button button-primary" id="pt-manual-connect">
                                        <?php _e('ربط', 'profile-trader'); ?>
                                    </button>
                                </div>
                                
                                <div id="pt-manual-connect-result" class="pt-result"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Create Users Tool -->
                    <div class="pt-admin-card">
                        <h2><?php _e('إنشاء حسابات للتجار', 'profile-trader'); ?></h2>
                        <p><?php _e('يقوم بإنشاء حسابات مستخدمين للتجار الذين لديهم إيميل ولا يوجد حساب بنفس الإيميل.', 'profile-trader'); ?></p>
                        <p class="description"><?php _e('سيتم إرسال بيانات الدخول لكل تاجر على إيميله.', 'profile-trader'); ?></p>

                    <!-- Batch Size Input -->
                    <div class="pt-form-row" style="margin: 15px 0;">
                        <label for="pt-batch-size">
                            <strong><?php _e('عدد المستخدمين في كل دفعة:', 'profile-trader'); ?></strong>
                        </label>
                        <input type="number" id="pt-batch-size" min="1" max="1000" value="50" class="regular-text" />
                        <p class="description" style="margin-top: 5px;">
                            <?php _e('يُنصح بـ 50-100 مستخدم لكل دفعة. أقصى حد: 1000', 'profile-trader'); ?>
                        </p>
                    </div>

                    <!-- Create All Checkbox -->
                    <div class="pt-form-row" style="margin: 15px 0;">
                        <label>
                            <input type="checkbox" id="pt-create-all" value="1">
                            <?php _e('إنشاء جميع الحسابات دفعة واحدة', 'profile-trader'); ?>
                        </label>
                        <p class="description" style="margin-top: 5px;">
                            <?php _e('تحذير: قد يستغرق وقتاً طويلاً ويمكن أن يتسبب في timeout للكميات الكبيرة جداً.', 'profile-trader'); ?>
                        </p>
                    </div>

                    <!-- Default Password Checkbox -->
                    <div class="pt-form-row" style="margin: 15px 0;">
                        <label>
                            <input type="checkbox" id="pt-use-default-password" value="1">
                            <?php _e('استخدام كلمة مرور افتراضية موحدة لجميع المستخدمين', 'profile-trader'); ?>
                        </label>
                        <p class="description" style="margin-top: 5px;">
                            <?php _e('إذا تم تفعيل هذا الخيار، سيتم استخدام كلمة مرور واحدة لجميع المستخدمين الجدد. يُنصح بتفعيله لتسهيل إدارة الحسابات.', 'profile-trader'); ?>
                        </p>
                    </div>

                    <?php
                    $default_password = get_option('pt_default_user_password', '');
                    if ($default_password):
                    ?>
                    <div class="pt-form-row" style="margin: 15px 0; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                        <strong><?php _e('كلمة المرور الافتراضية الحالية:', 'profile-trader'); ?></strong>
                        <code style="display: block; margin-top: 5px; padding: 5px; background: white; border: 1px solid #ddd;"><?php echo esc_html($default_password); ?></code>
                        <button type="button" class="button button-small" id="pt-regenerate-default-password" style="margin-top: 10px;">
                            <?php _e('إنشاء كلمة مرور جديدة', 'profile-trader'); ?>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Progress Bar Container -->
                    <div id="pt-create-progress-container" class="pt-progress-container" style="display: none;">
                        <div class="pt-progress-bar-wrapper">
                            <div class="pt-progress-bar">
                                <div class="pt-progress-fill" id="pt-create-progress-fill"></div>
                            </div>
                            <div class="pt-progress-text" id="pt-create-progress-text">0%</div>
                        </div>
                        <div class="pt-progress-stats" id="pt-create-progress-stats"></div>
                    </div>

                    <!-- Action Buttons -->
                    <button type="button" class="button button-primary" id="pt-create-users">
                        <?php _e('إنشاء حسابات', 'profile-trader'); ?>
                    </button>
                    <button type="button" class="button" id="pt-cancel-create" style="display: none;">
                        <?php _e('إيقاف', 'profile-trader'); ?>
                    </button>

                    <div id="pt-create-users-result" class="pt-result"></div>
                    </div>

                    <!-- Database Optimization -->
                    <div class="pt-admin-card">
                        <h2><?php _e('تحسين قاعدة البيانات', 'profile-trader'); ?></h2>
                        <p><?php _e('إضافة فهارس لقاعدة البيانات لتسريع الاستعلامات وتحسين الأداء.', 'profile-trader'); ?></p>
                        <p class="description"><?php _e('يُنصح بتشغيل هذا عند التثبيت الأولي أو بعد استيراد كمية كبيرة من البيانات.', 'profile-trader'); ?></p>
                        <button type="button" class="button button-secondary" id="pt-add-indexes">
                            <?php _e('إضافة فهارس', 'profile-trader'); ?>
                        </button>
                        <div id="pt-add-indexes-result" class="pt-result"></div>
                    </div>
                </div>

                <!-- Auto Connect Logs -->
                <div id="pt-auto-connect-logs" class="pt-logs-container pt-admin-card pt-full-width" style="display: none;">
                    <h3><?php _e('سجل العمليات:', 'profile-trader'); ?></h3>
                    <div class="pt-logs-summary" id="pt-logs-summary"></div>
                    <div class="pt-logs-table-wrapper">
                        <table class="wp-list-table widefat fixed striped pt-logs-table">
                            <thead>
                                <tr>
                                    <th style="width: 30%;"><?php _e('التاجر', 'profile-trader'); ?></th>
                                    <th style="width: 25%;"><?php _e('الإيميل', 'profile-trader'); ?></th>
                                    <th style="width: 15%;"><?php _e('الحالة', 'profile-trader'); ?></th>
                                    <th style="width: 20%;"><?php _e('التفاصيل', 'profile-trader'); ?></th>
                                    <th style="width: 10%;"><?php _e('إجراء', 'profile-trader'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="pt-auto-connect-logs-tbody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Unconnected Traders List -->
                <div class="pt-admin-card pt-full-width">
                    <h2><?php _e('التجار غير المرتبطين', 'profile-trader'); ?></h2>

                    <!-- Search Bar -->
                    <div class="pt-search-container">
                        <div class="pt-search-wrapper">
                            <input
                                type="text"
                                id="pt-traders-search"
                                class="regular-text pt-search-input"
                                placeholder="<?php _e('البحث بالاسم أو الإيميل...', 'profile-trader'); ?>"
                                autocomplete="off"
                            />
                            <button type="button" id="pt-search-clear" class="button" style="display: none;">
                                <?php _e('مسح', 'profile-trader'); ?>
                            </button>
                        </div>
                        <div id="pt-search-status" class="pt-search-status" style="display: none;"></div>
                    </div>

                    <!-- Table Container -->
                    <div id="pt-traders-table-container">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 30%;"><?php _e('التاجر', 'profile-trader'); ?></th>
                                    <th style="width: 25%;"><?php _e('الإيميل', 'profile-trader'); ?></th>
                                    <th style="width: 25%;"><?php _e('الحالة', 'profile-trader'); ?></th>
                                    <th style="width: 20%;"><?php _e('إجراء', 'profile-trader'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="pt-traders-tbody">
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px;">
                                        <span class="spinner is-active" style="float: none;"></span>
                                        <p><?php _e('جاري التحميل...', 'profile-trader'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="pt-pagination-container" id="pt-pagination-container" style="display: none;">
                        <div class="pt-pagination-info" id="pt-pagination-info"></div>
                        <div class="pt-pagination-controls" id="pt-pagination-controls"></div>
                    </div>
                </div>
            </div>

            <!-- Email Settings Card -->
            <div class="pt-admin-card pt-full-width pt-email-settings-card">
                <h2><?php _e('إعدادات البريد الإلكتروني', 'profile-trader'); ?></h2>
                <p class="description">
                    <?php _e('تخصيص إعدادات الإيميل المرسل للتجار عند إنشاء الحسابات.', 'profile-trader'); ?>
                </p>

                <div class="pt-email-settings-form">
                    <!-- From Name -->
                    <div class="pt-form-row">
                        <label for="pt-email-from-name">
                            <strong><?php _e('اسم المرسل:', 'profile-trader'); ?></strong>
                            <span class="required">*</span>
                        </label>
                        <input
                            type="text"
                            id="pt-email-from-name"
                            class="regular-text"
                            value="<?php echo esc_attr(get_option('pt_email_from_name', get_bloginfo('name'))); ?>"
                            placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>"
                        />
                        <p class="description">
                            <?php _e('الاسم الذي سيظهر كمرسل في الإيميل. الافتراضي: اسم الموقع', 'profile-trader'); ?>
                        </p>
                    </div>

                    <!-- From Email -->
                    <div class="pt-form-row">
                        <label for="pt-email-from-email">
                            <strong><?php _e('إيميل المرسل:', 'profile-trader'); ?></strong>
                            <span class="required">*</span>
                        </label>
                        <input
                            type="email"
                            id="pt-email-from-email"
                            class="regular-text"
                            value="<?php echo esc_attr(get_option('pt_email_from_email', get_option('admin_email'))); ?>"
                            placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
                            dir="ltr"
                            style="text-align: left;"
                        />
                        <p class="description">
                            <?php _e('عنوان البريد الإلكتروني للمرسل. الافتراضي: إيميل المدير', 'profile-trader'); ?>
                        </p>
                    </div>

                    <!-- Reply-To -->
                    <div class="pt-form-row">
                        <label for="pt-email-reply-to">
                            <strong><?php _e('الرد على (Reply-To):', 'profile-trader'); ?></strong>
                        </label>
                        <input
                            type="email"
                            id="pt-email-reply-to"
                            class="regular-text"
                            value="<?php echo esc_attr(get_option('pt_email_reply_to', '')); ?>"
                            placeholder="support@example.com"
                            dir="ltr"
                            style="text-align: left;"
                        />
                        <p class="description">
                            <?php _e('عنوان الرد الافتراضي عندما يقوم المستلم بالرد على الإيميل (اختياري)', 'profile-trader'); ?>
                        </p>
                    </div>

                    <!-- CC -->
                    <div class="pt-form-row">
                        <label for="pt-email-cc">
                            <strong><?php _e('نسخة كربونية (CC):', 'profile-trader'); ?></strong>
                        </label>
                        <input
                            type="text"
                            id="pt-email-cc"
                            class="regular-text"
                            value="<?php echo esc_attr(get_option('pt_email_cc', '')); ?>"
                            placeholder="email1@example.com, email2@example.com"
                            dir="ltr"
                            style="text-align: left;"
                        />
                        <p class="description">
                            <?php _e('عناوين البريد الإلكتروني لإرسال نسخة من الرسالة (مفصولة بفواصل، اختياري)', 'profile-trader'); ?>
                        </p>
                    </div>

                    <!-- BCC -->
                    <div class="pt-form-row">
                        <label for="pt-email-bcc">
                            <strong><?php _e('نسخة مخفية (BCC):', 'profile-trader'); ?></strong>
                        </label>
                        <input
                            type="text"
                            id="pt-email-bcc"
                            class="regular-text"
                            value="<?php echo esc_attr(get_option('pt_email_bcc', '')); ?>"
                            placeholder="admin@example.com, archive@example.com"
                            dir="ltr"
                            style="text-align: left;"
                        />
                        <p class="description">
                            <?php _e('عناوين البريد الإلكتروني لإرسال نسخة مخفية (مفصولة بفواصل، اختياري)', 'profile-trader'); ?>
                        </p>
                    </div>

                    <!-- Save Button -->
                    <div class="pt-form-row">
                        <button type="button" class="button button-primary" id="pt-save-email-settings">
                            <?php _e('حفظ الإعدادات', 'profile-trader'); ?>
                        </button>
                        <button type="button" class="button" id="pt-reset-email-settings">
                            <?php _e('إعادة تعيين للافتراضي', 'profile-trader'); ?>
                        </button>
                    </div>

                    <!-- Result Message -->
                    <div id="pt-email-settings-result" class="pt-result"></div>
                </div>
            </div>

                        <!-- Auto Connect Logs -->

            <!-- How it works -->
            <div class="pt-admin-card pt-info-card">
                <h2><?php _e('كيف يعمل الربط؟', 'profile-trader'); ?></h2>
                <p><?php _e('يتم ربط التاجر بالمستخدم بثلاث طرق:', 'profile-trader'); ?></p>
                <ol>
                    <li>
                        <strong><?php _e('الكاتب (Author):', 'profile-trader'); ?></strong>
                        <?php _e('الطريقة الافتراضية في ووردبريس - حقل post_author', 'profile-trader'); ?>
                    </li>
                    <li>
                        <strong><?php _e('الربط المباشر (Meta):', 'profile-trader'); ?></strong>
                        <?php _e('حقل meta مخصص يخزن معرف المستخدم المرتبط', 'profile-trader'); ?>
                    </li>
                    <li>
                        <strong><?php _e('تطابق الإيميل:', 'profile-trader'); ?></strong>
                        <?php _e('مطابقة إيميل التاجر مع إيميل المستخدم', 'profile-trader'); ?>
                    </li>
                </ol>
                <p><?php _e('عندما يسجل المستخدم الدخول، يرى جميع التجار المرتبطين به بأي من هذه الطرق.', 'profile-trader'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render shortcodes documentation page
     */
    public function render_shortcodes_page() {
        ?>
        <div class="wrap pt-admin-wrap">
            <h1><?php _e('الأكواد المختصرة المتاحة', 'profile-trader'); ?></h1>
            <p class="description"><?php _e('يمكنك استخدام هذه الأكواد المختصرة في أي صفحة أو مقال لعرض الوظائف والإعلانات والتجار', 'profile-trader'); ?></p>

            <div class="pt-shortcodes-grid" style="display: grid; gap: 20px; margin-top: 30px;">

                <!-- Job Listings Shortcode -->
                <div class="pt-shortcode-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h2 style="margin-top: 0; color: #1d4ed8; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">💼</span>
                        <?php _e('عرض الوظائف', 'profile-trader'); ?>
                    </h2>

                    <div class="shortcode-copy" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; margin: 15px 0; font-family: monospace; position: relative;">
                        <code style="font-size: 14px;">[job_listings]</code>
                        <button onclick="navigator.clipboard.writeText('[job_listings]')" style="position: absolute; left: 10px; top: 10px; padding: 5px 10px; background: #1d4ed8; color: white; border: none; border-radius: 4px; cursor: pointer;">نسخ</button>
                    </div>

                    <h3><?php _e('المعاملات المتاحة:', 'profile-trader'); ?></h3>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th><?php _e('المعامل', 'profile-trader'); ?></th>
                                <th><?php _e('القيمة الافتراضية', 'profile-trader'); ?></th>
                                <th><?php _e('الخيارات', 'profile-trader'); ?></th>
                                <th><?php _e('الوصف', 'profile-trader'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>limit</code></td>
                                <td>-1 (الكل)</td>
                                <td>أي رقم</td>
                                <td>عدد الوظائف المعروضة</td>
                            </tr>
                            <tr>
                                <td><code>status</code></td>
                                <td>publish</td>
                                <td>publish, pending, draft, all</td>
                                <td>حالة الوظائف</td>
                            </tr>
                            <tr>
                                <td><code>show_filters</code></td>
                                <td>no</td>
                                <td>yes, no</td>
                                <td>إظهار فلاتر الحالة</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="margin-top: 20px;"><?php _e('أمثلة:', 'profile-trader'); ?></h3>
                    <div style="background: #f8fafc; border-right: 4px solid #1d4ed8; padding: 15px; margin: 10px 0;">
                        <p style="margin: 5px 0;"><code>[job_listings limit="6"]</code> - عرض 6 وظائف</p>
                        <p style="margin: 5px 0;"><code>[job_listings limit="9" status="publish"]</code> - عرض 9 وظائف منشورة</p>
                        <p style="margin: 5px 0;"><code>[job_listings limit="12" show_filters="yes"]</code> - عرض 12 وظيفة مع الفلاتر</p>
                    </div>
                </div>

                <!-- Ads Listings Shortcode -->
                <div class="pt-shortcode-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h2 style="margin-top: 0; color: #059669; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">📢</span>
                        <?php _e('عرض الإعلانات', 'profile-trader'); ?>
                    </h2>

                    <div class="shortcode-copy" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; margin: 15px 0; font-family: monospace; position: relative;">
                        <code style="font-size: 14px;">[ads_listings]</code>
                        <button onclick="navigator.clipboard.writeText('[ads_listings]')" style="position: absolute; left: 10px; top: 10px; padding: 5px 10px; background: #059669; color: white; border: none; border-radius: 4px; cursor: pointer;">نسخ</button>
                    </div>

                    <h3><?php _e('المعاملات المتاحة:', 'profile-trader'); ?></h3>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th><?php _e('المعامل', 'profile-trader'); ?></th>
                                <th><?php _e('القيمة الافتراضية', 'profile-trader'); ?></th>
                                <th><?php _e('الخيارات', 'profile-trader'); ?></th>
                                <th><?php _e('الوصف', 'profile-trader'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>limit</code></td>
                                <td>-1 (الكل)</td>
                                <td>أي رقم</td>
                                <td>عدد الإعلانات المعروضة</td>
                            </tr>
                            <tr>
                                <td><code>status</code></td>
                                <td>publish</td>
                                <td>publish, pending, draft</td>
                                <td>حالة الإعلانات</td>
                            </tr>
                            <tr>
                                <td><code>category</code></td>
                                <td>فارغ (الكل)</td>
                                <td>slug التصنيف</td>
                                <td>تصنيف الإعلانات</td>
                            </tr>
                            <tr>
                                <td><code>featured</code></td>
                                <td>no</td>
                                <td>yes, no</td>
                                <td>إظهار المميزة فقط</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="margin-top: 20px;"><?php _e('أمثلة:', 'profile-trader'); ?></h3>
                    <div style="background: #f8fafc; border-right: 4px solid #059669; padding: 15px; margin: 10px 0;">
                        <p style="margin: 5px 0;"><code>[ads_listings limit="6"]</code> - عرض 6 إعلانات</p>
                        <p style="margin: 5px 0;"><code>[ads_listings limit="9" featured="yes"]</code> - عرض 9 إعلانات مميزة</p>
                        <p style="margin: 5px 0;"><code>[ads_listings limit="8" category="electronics"]</code> - عرض 8 إعلانات من تصنيف معين</p>
                    </div>
                </div>

                <!-- Trader Listings Shortcode -->
                <div class="pt-shortcode-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h2 style="margin-top: 0; color: #dc2626; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">👥</span>
                        <?php _e('عرض التجار', 'profile-trader'); ?>
                    </h2>

                    <div class="shortcode-copy" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; margin: 15px 0; font-family: monospace; position: relative;">
                        <code style="font-size: 14px;">[trader_listings_public]</code>
                        <button onclick="navigator.clipboard.writeText('[trader_listings_public]')" style="position: absolute; left: 10px; top: 10px; padding: 5px 10px; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer;">نسخ</button>
                    </div>

                    <h3><?php _e('المعاملات المتاحة:', 'profile-trader'); ?></h3>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th><?php _e('المعامل', 'profile-trader'); ?></th>
                                <th><?php _e('القيمة الافتراضية', 'profile-trader'); ?></th>
                                <th><?php _e('الخيارات', 'profile-trader'); ?></th>
                                <th><?php _e('الوصف', 'profile-trader'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>limit</code></td>
                                <td>-1 (الكل)</td>
                                <td>أي رقم</td>
                                <td>عدد التجار المعروضين</td>
                            </tr>
                            <tr>
                                <td><code>status</code></td>
                                <td>publish</td>
                                <td>publish, pending, draft</td>
                                <td>حالة التجار</td>
                            </tr>
                            <tr>
                                <td><code>featured</code></td>
                                <td>no</td>
                                <td>yes, no</td>
                                <td>إظهار المميزين فقط</td>
                            </tr>
                            <tr>
                                <td><code>company_type</code></td>
                                <td>فارغ (الكل)</td>
                                <td>نوع الشركة</td>
                                <td>نوع الشركة</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="margin-top: 20px;"><?php _e('أمثلة:', 'profile-trader'); ?></h3>
                    <div style="background: #f8fafc; border-right: 4px solid #dc2626; padding: 15px; margin: 10px 0;">
                        <p style="margin: 5px 0;"><code>[trader_listings_public limit="6"]</code> - عرض 6 تجار</p>
                        <p style="margin: 5px 0;"><code>[trader_listings_public limit="9" featured="yes"]</code> - عرض 9 تجار مميزين</p>
                        <p style="margin: 5px 0;"><code>[trader_listings_public limit="8" company_type="شركة"]</code> - عرض 8 تجار من نوع معين</p>
                    </div>
                </div>

                <!-- Usage Tips -->
                <div class="pt-shortcode-card" style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <h2 style="margin-top: 0; color: #92400e; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">💡</span>
                        <?php _e('نصائح الاستخدام', 'profile-trader'); ?>
                    </h2>

                    <ul style="line-height: 1.8;">
                        <li>يمكنك إضافة هذه الأكواد في أي صفحة أو مقال من محرر WordPress</li>
                        <li>لعرض محتوى في الصفحة الرئيسية، أضف الكود المختصر في محرر الصفحة الرئيسية</li>
                        <li>يمكنك الجمع بين عدة أكواد في نفس الصفحة</li>
                        <li>جميع الأكواد تدعم اللغة العربية والعرض من اليمين لليسار (RTL)</li>
                        <li>التصميم متجاوب ويعمل على جميع الأجهزة (موبايل، تابلت، كمبيوتر)</li>
                    </ul>

                    <h3 style="margin-top: 20px;"><?php _e('مثال على صفحة رئيسية كاملة:', 'profile-trader'); ?></h3>
                    <div style="background: #fff; border: 1px solid #d97706; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: monospace; direction: ltr; text-align: left;">
                        <pre style="margin: 0; white-space: pre-wrap;">&lt;h2&gt;الوظائف المتاحة&lt;/h2&gt;
[job_listings limit="6"]

&lt;h2&gt;الإعلانات المميزة&lt;/h2&gt;
[ads_listings limit="6" featured="yes"]

&lt;h2&gt;التجار المميزون&lt;/h2&gt;
[trader_listings_public limit="6" featured="yes"]</pre>
                    </div>
                </div>

            </div>
        </div>

        <style>
            .pt-shortcodes-grid .pt-shortcode-card h3 {
                font-size: 16px;
                font-weight: 600;
                margin-top: 15px;
                margin-bottom: 10px;
            }
            .pt-shortcodes-grid .widefat th {
                font-weight: 600;
                background: #f9fafb;
            }
            .pt-shortcodes-grid code {
                background: #f1f5f9;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 13px;
                color: #1e40af;
            }
        </style>
        <?php
    }
}

// Initialize
PT_Admin_Settings::get_instance();

