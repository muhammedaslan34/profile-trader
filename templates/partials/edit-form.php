<?php
/**
 * Edit Form Partial Template
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = Profile_Trader::get_instance();
$meta_fields = $plugin->get_meta_fields();
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
$listing = $listing_id ? get_post($listing_id) : null;
$is_edit = $listing !== null;

// Check if this is already submitted (has been saved before)
$is_submitted = $is_edit && $listing->post_status !== 'auto-draft';

// Get current values
$values = [];
if ($is_edit) {
    foreach ($meta_fields as $key => $field) {
        $values[$key] = get_post_meta($listing_id, $key, true);
    }
}

// Get taxonomy terms
$cities = get_terms(['taxonomy' => 'city', 'hide_empty' => false]);
$activities = get_terms(['taxonomy' => 'activity', 'hide_empty' => false]);
$sectors = get_terms(['taxonomy' => 'sector', 'hide_empty' => false]);
$economic_activities = get_terms(['taxonomy' => 'economic_activity', 'hide_empty' => false]);

// Get selected terms
$selected_cities = $is_edit ? wp_get_post_terms($listing_id, 'city', ['fields' => 'ids']) : [];
$selected_activities = $is_edit ? wp_get_post_terms($listing_id, 'activity', ['fields' => 'ids']) : [];
$selected_sectors = $is_edit ? wp_get_post_terms($listing_id, 'sector', ['fields' => 'ids']) : [];
$selected_economic_activities = $is_edit ? wp_get_post_terms($listing_id, 'economic_activity', ['fields' => 'ids']) : [];

// Fields that become readonly after submission
$readonly_fields = ['company_type', 'score', 'commercial_register'];
$readonly_taxonomies = ['city', 'activity', 'sector', 'economic_activity'];
?>

<form id="pt-listing-form" class="pt-form" enctype="multipart/form-data">
    <input type="hidden" name="post_id" value="<?php echo $listing_id; ?>">
    <input type="hidden" name="is_submitted" value="<?php echo $is_submitted ? '1' : '0'; ?>">
    <?php wp_nonce_field('pt_nonce', 'pt_form_nonce'); ?>
    
    <!-- Basic Information -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                المعلومات الأساسية
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <!-- Title -->
            <div class="pt-form-group pt-col-full">
                <label for="post_title" class="pt-label">
                    اسم الشركة / المؤسسة
                    <span class="pt-required">*</span>
                </label>
                <input type="text" 
                       id="post_title" 
                       name="post_title" 
                       class="pt-input" 
                       placeholder="أدخل اسم الشركة أو المؤسسة"
                       value="<?php echo $is_edit ? esc_attr($listing->post_title) : ''; ?>"
                       <?php echo $is_submitted ? 'readonly disabled' : ''; ?>
                       required>
                <?php if ($is_submitted): ?>
                    <!-- Keep value submitted even when input is disabled -->
                    <input type="hidden" name="post_title" value="<?php echo $is_edit ? esc_attr($listing->post_title) : ''; ?>">
                    <span class="pt-hint">لا يمكن تعديل اسم الشركة بعد الحفظ</span>
                <?php endif; ?>
            </div>
            
            <!-- Short Description -->
            <div class="pt-form-group pt-col-full">
                <label for="short_desc" class="pt-label">
                    <?php echo esc_html($meta_fields['short_desc']['label']); ?>
                </label>
                <textarea id="short_desc" 
                          name="short_desc" 
                          class="pt-textarea" 
                          placeholder="أدخل وصفاً مختصراً للشركة (حد أقصى 70 حرف)"
                          maxlength="70"
                          rows="2"><?php echo esc_textarea($values['short_desc'] ?? ''); ?></textarea>
                <span class="pt-char-count">
                    <span id="short_desc_count"><?php echo strlen($values['short_desc'] ?? ''); ?></span>/70
                </span>
            </div>
            
            <!-- Full Description -->
            <div class="pt-form-group pt-col-full">
                <label for="post_content" class="pt-label">الوصف التفصيلي</label>
                <textarea id="post_content" 
                          name="post_content" 
                          class="pt-textarea pt-editor" 
                          placeholder="أدخل وصفاً تفصيلياً عن الشركة ونشاطاتها"
                          rows="6"><?php echo $is_edit ? esc_textarea($listing->post_content) : ''; ?></textarea>
            </div>
            
            <!-- Profile -->
            <div class="pt-form-group pt-col-full">
                <label for="profile" class="pt-label">
                    <?php echo esc_html($meta_fields['profile']['label']); ?>
                </label>
                <input type="text" 
                       id="profile" 
                       name="profile" 
                       class="pt-input" 
                       placeholder="ادخل رابط تحميل ملفك"
                       value="<?php echo esc_attr($values['profile'] ?? ''); ?>">
            </div>
            
            <!-- Logo -->
            <div class="pt-form-group pt-col-half">
                <label class="pt-label"><?php echo esc_html($meta_fields['logo']['label']); ?></label>
                <div class="pt-media-upload" data-field="logo" data-upload-type="logo" data-max-size="2097152">
                    <input type="hidden" name="logo" value="<?php echo esc_attr($values['logo'] ?? ''); ?>">
                    <input type="file" class="pt-file-input" accept="image/png,image/jpeg,image/jpg" style="display:none" data-field="logo">

                    <?php
                    $logo_url = !empty($values['logo']) ? wp_get_attachment_image_url($values['logo'], 'medium') : '';
                    if ($logo_url):
                    ?>
                    <!-- Logo Preview -->
                    <div class="pt-media-preview has-image">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="">
                        <div class="pt-media-actions">
                            <button type="button" class="pt-edit-media" data-attachment-id="<?php echo esc_attr($values['logo']); ?>" title="تعديل">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <button type="button" class="pt-remove-media" title="حذف">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Drop Zone -->
                    <div class="pt-dropzone pt-upload-btn">
                        <div class="pt-dropzone-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                        </div>
                        <div class="pt-dropzone-text">
                            <span class="pt-dropzone-title">انقر للتحميل <span class="pt-dropzone-hint">أو اسحب وأفلت</span></span>
                            <span class="pt-dropzone-formats">PNG, JPG, JPEG (الحد الأقصى: 2 ميجابايت)</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upload Progress -->
                    <div class="pt-upload-progress" style="display:none">
                        <div class="pt-progress-bar">
                            <div class="pt-progress-fill" style="width:0%"></div>
                        </div>
                        <span class="pt-progress-text">0%</span>
                    </div>

                    <!-- Error Message -->
                    <div class="pt-upload-error" style="display:none"></div>
                </div>
            </div>
            
            <!-- Gallery -->
            <div class="pt-form-group pt-col-half">
                <label class="pt-label"><?php echo esc_html($meta_fields['gallary']['label']); ?></label>
                <div class="pt-gallery-upload" data-field="gallary" data-upload-type="gallery" data-max-size="10485760">
                    <input type="hidden" name="gallary" value="<?php echo esc_attr(is_array($values['gallary'] ?? null) ? implode(',', $values['gallary']) : ($values['gallary'] ?? '')); ?>">
                    <input type="file" class="pt-file-input" multiple accept="image/png,image/jpeg,image/jpg" style="display:none" data-field="gallary">

                    <!-- Drop Zone -->
                    <div class="pt-dropzone pt-gallery-btn">
                        <div class="pt-dropzone-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="pt-dropzone-text">
                            <span class="pt-dropzone-title">انقر للتحميل <span class="pt-dropzone-hint">أو اسحب وأفلت</span></span>
                            <span class="pt-dropzone-formats">PNG, JPG, JPEG (الحد الأقصى: 10 ميجابايت لكل ملف)</span>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div class="pt-upload-progress" style="display:none">
                        <div class="pt-progress-bar">
                            <div class="pt-progress-fill" style="width:0%"></div>
                        </div>
                        <span class="pt-progress-text">0%</span>
                    </div>

                    <!-- Error Message -->
                    <div class="pt-upload-error" style="display:none"></div>

                    <!-- Gallery Grid -->
                    <div class="pt-gallery-grid">
                        <?php
                        $gallery_ids = $values['gallary'] ?? [];
                        if (!is_array($gallery_ids) && !empty($gallery_ids)) {
                            $gallery_ids = explode(',', $gallery_ids);
                        }
                        if (!empty($gallery_ids)):
                            foreach ($gallery_ids as $img_id):
                                $img_url = wp_get_attachment_image_url($img_id, 'thumbnail');
                                if ($img_url):
                        ?>
                        <div class="pt-gallery-item" data-id="<?php echo esc_attr($img_id); ?>">
                            <img src="<?php echo esc_url($img_url); ?>" alt="">
                            <button type="button" class="pt-remove-gallery-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Structured About Information -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                معلومات إضافية عن الشركة
            </h2>
        </div>

        <div class="pt-form-grid">
            <!-- Mission Statement -->
            <div class="pt-form-group pt-col-full">
                <label for="mission_statement" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-left:6px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <circle cx="12" cy="12" r="6"></circle>
                        <circle cx="12" cy="12" r="2"></circle>
                    </svg>
                    بيان المهمة
                </label>
                <textarea id="mission_statement"
                          name="mission_statement"
                          class="pt-textarea"
                          placeholder="ما هي مهمة شركتكم؟"
                          rows="3"><?php echo esc_textarea($values['mission_statement'] ?? ''); ?></textarea>
            </div>

            <!-- Vision -->
            <div class="pt-form-group pt-col-full">
                <label for="vision" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-left:6px;">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    الرؤية
                </label>
                <textarea id="vision"
                          name="vision"
                          class="pt-textarea"
                          placeholder="ما هي رؤية شركتكم للمستقبل؟"
                          rows="3"><?php echo esc_textarea($values['vision'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Key Statistics Section -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                الإحصائيات الرئيسية
            </h2>
            <button type="button" class="pt-btn pt-btn-sm pt-btn-outline pt-add-stat-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                إضافة إحصائية
            </button>
        </div>

        <div class="pt-repeater pt-stats-repeater" id="key_statistics-repeater">
            <?php
            $stats = $values['key_statistics'] ?? [];
            if (!is_array($stats)) $stats = [];

            if (!empty($stats)):
            foreach ($stats as $index => $stat):
            ?>
            <div class="pt-repeater-item pt-stat-item-row" data-index="<?php echo $index; ?>">
                <div class="pt-form-grid pt-form-grid-stats">
                    <div class="pt-form-group">
                        <label class="pt-label">الرقم</label>
                        <input type="text"
                               name="key_statistics[<?php echo $index; ?>][stat_number]"
                               class="pt-input"
                               placeholder="مثال: +500"
                               value="<?php echo esc_attr($stat['stat_number'] ?? ''); ?>">
                    </div>
                    <div class="pt-form-group">
                        <label class="pt-label">الوصف</label>
                        <input type="text"
                               name="key_statistics[<?php echo $index; ?>][stat_label]"
                               class="pt-input"
                               placeholder="مثال: عميل سعيد"
                               value="<?php echo esc_attr($stat['stat_label'] ?? ''); ?>">
                    </div>
                    <div class="pt-form-group">
                        <label class="pt-label">الأيقونة</label>
                        <select name="key_statistics[<?php echo $index; ?>][stat_icon]" class="pt-select">
                            <option value="calendar_month" <?php selected($stat['stat_icon'] ?? '', 'calendar_month'); ?>>سنوات الخبرة</option>
                            <option value="groups" <?php selected($stat['stat_icon'] ?? '', 'groups'); ?>>العملاء</option>
                            <option value="handshake" <?php selected($stat['stat_icon'] ?? '', 'handshake'); ?>>الشركاء</option>
                            <option value="workspace_premium" <?php selected($stat['stat_icon'] ?? '', 'workspace_premium'); ?>>الجوائز</option>
                            <option value="verified" <?php selected($stat['stat_icon'] ?? '', 'verified'); ?>>المشاريع</option>
                            <option value="star" <?php selected($stat['stat_icon'] ?? '', 'star'); ?>>نجمة</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="pt-remove-repeater-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            </div>
            <?php
            endforeach;
            endif;
            ?>
        </div>
    </div>

    <!-- Highlights Section -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                النقاط المميزة
            </h2>
            <button type="button" class="pt-btn pt-btn-sm pt-btn-outline pt-add-highlight-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                إضافة نقطة
            </button>
        </div>

        <div class="pt-repeater pt-highlights-repeater" id="about_highlights-repeater">
            <?php
            $highlights = $values['about_highlights'] ?? [];
            if (!is_array($highlights)) $highlights = [];

            if (!empty($highlights)):
            foreach ($highlights as $index => $item):
            ?>
            <div class="pt-repeater-item pt-highlight-item-row" data-index="<?php echo $index; ?>">
                <div class="pt-repeater-content">
                    <input type="text"
                           name="about_highlights[<?php echo $index; ?>][highlight_text]"
                           class="pt-input"
                           placeholder="نقطة مميزة عن شركتكم"
                           value="<?php echo esc_attr($item['highlight_text'] ?? ''); ?>">
                </div>
                <button type="button" class="pt-remove-repeater-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            </div>
            <?php
            endforeach;
            endif;
            ?>
        </div>
    </div>

    <!-- Classification Section (Taxonomies) -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
                التصنيف
            </h2>
            <?php if ($is_submitted): ?>
            <span class="pt-readonly-notice">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                لا يمكن تعديل هذه الحقول بعد الحفظ
            </span>
            <?php endif; ?>
        </div>
        
        <div class="pt-form-grid">
            <!-- City (Single Dropdown) -->
            <div class="pt-form-group pt-col-half">
                <label for="tax_city" class="pt-label">المدينة</label>
                <div class="pt-select-wrapper">
                    <span class="pt-select-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <select id="tax_city" 
                            name="tax_city" 
                            class="pt-select"
                            <?php echo $is_submitted ? 'disabled' : ''; ?>>
                        <option value="">اختر المدينة</option>
                        <?php if (!is_wp_error($cities)): foreach ($cities as $term): ?>
                        <option value="<?php echo $term->term_id; ?>" 
                                <?php echo in_array($term->term_id, $selected_cities) ? 'selected' : ''; ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                <?php if ($is_submitted && !empty($selected_cities)): ?>
                <input type="hidden" name="tax_city" value="<?php echo $selected_cities[0] ?? ''; ?>">
                <?php endif; ?>
            </div>
            
            <!-- Sector (Single Dropdown) -->
            <div class="pt-form-group pt-col-half">
                <label for="tax_sector" class="pt-label">القطاع</label>
                <div class="pt-select-wrapper">
                    <span class="pt-select-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <select id="tax_sector"
                            name="tax_sector"
                            class="pt-select"
                            <?php echo $is_submitted ? 'disabled' : ''; ?>>
                        <option value="">اختر القطاع</option>
                        <?php if (!is_wp_error($sectors)): foreach ($sectors as $term): ?>
                        <option value="<?php echo $term->term_id; ?>"
                                <?php echo in_array($term->term_id, $selected_sectors) ? 'selected' : ''; ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                <?php if ($is_submitted && !empty($selected_sectors)): ?>
                <input type="hidden" name="tax_sector" value="<?php echo $selected_sectors[0] ?? ''; ?>">
                <?php endif; ?>
            </div>

            <!-- Economic Activity (Single Dropdown) -->
            <div class="pt-form-group pt-col-half">
                <label for="tax_economic_activity" class="pt-label">النشاط الاقتصادي</label>
                <div class="pt-select-wrapper">
                    <span class="pt-select-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <select id="tax_economic_activity"
                            name="tax_economic_activity"
                            class="pt-select"
                            <?php echo $is_submitted ? 'disabled' : ''; ?>>
                        <option value="">اختر النشاط الاقتصادي</option>
                        <?php if (!is_wp_error($economic_activities)): foreach ($economic_activities as $term): ?>
                        <option value="<?php echo $term->term_id; ?>"
                                <?php echo in_array($term->term_id, $selected_economic_activities) ? 'selected' : ''; ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                <?php if ($is_submitted && !empty($selected_economic_activities)): ?>
                <input type="hidden" name="tax_economic_activity" value="<?php echo $selected_economic_activities[0] ?? ''; ?>">
                <?php endif; ?>
            </div>

            <!-- Activity (Checkbox List) -->
            <div class="pt-form-group pt-col-full">
                <label class="pt-label">النشاط <span class="pt-hint">(يمكنك اختيار أكثر من نشاط)</span></label>
                <div class="pt-checkbox-list <?php echo $is_submitted ? 'pt-disabled' : ''; ?>">
                    <?php if (!is_wp_error($activities)): foreach ($activities as $term): ?>
                    <label class="pt-checkbox-item">
                        <input type="checkbox" 
                               name="tax_activity[]" 
                               value="<?php echo $term->term_id; ?>"
                               <?php echo in_array($term->term_id, $selected_activities) ? 'checked' : ''; ?>
                               <?php echo $is_submitted ? 'disabled' : ''; ?>>
                        <span class="pt-checkbox-mark"></span>
                        <span class="pt-checkbox-label"><?php echo esc_html($term->name); ?></span>
                    </label>
                    <?php endforeach; endif; ?>
                </div>
                <?php if ($is_submitted && !empty($selected_activities)): ?>
                <?php foreach ($selected_activities as $act_id): ?>
                <input type="hidden" name="tax_activity[]" value="<?php echo $act_id; ?>">
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Company Information -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                معلومات الشركة
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <!-- Company Type -->
            <div class="pt-form-group pt-col-full <?php echo $is_submitted ? 'pt-field-locked' : ''; ?>">
                <label class="pt-label">
                    <?php echo esc_html($meta_fields['company_type']['label']); ?>
                    <span class="pt-tooltip">ℹ️
                        <span class="pt-tooltiptext">
                            <b>شركة فردية:</b> يملكها شخص واحد ويتحمل كامل المسؤولية.<br>
                            <b>شركة تضامن:</b> شركاء متعددون يتقاسمون المسؤولية.<br>
                            <b>شركة محدودة المسؤولية:</b> مسؤولية الشركاء محدودة برأس المال فقط.<br>
                            <b>شركة توصية:</b> تضم شركاء متضامنين وموصين.<br>
                            <b>شركة مساهمة مغفلة:</b> أسهمها غير قابلة للتداول العام.<br>
                            <b>شركة مساهمة مفتوحة:</b> أسهمها قابلة للتداول في البورصة.
                        </span>
                    </span>
                    <?php if ($is_submitted): ?>
                    <span class="pt-lock-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <?php endif; ?>
                </label>
                <div class="pt-radio-group pt-radio-inline">
                    <?php foreach ($meta_fields['company_type']['options'] as $key => $label): ?>
                    <label class="pt-radio-item <?php echo $is_submitted ? 'pt-disabled' : ''; ?>">
                        <input type="radio" 
                               name="company_type" 
                               value="<?php echo esc_attr($key); ?>"
                               <?php checked($values['company_type'] ?? '', $key); ?>
                               <?php echo $is_submitted ? 'disabled' : ''; ?>>
                        <span class="pt-radio-label"><?php echo esc_html($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($is_submitted && !empty($values['company_type'])): ?>
                <input type="hidden" name="company_type" value="<?php echo esc_attr($values['company_type']); ?>">
                <?php endif; ?>
            </div>
            
            <!-- Score -->
            <div class="pt-form-group pt-col-full <?php echo $is_submitted ? 'pt-field-locked' : ''; ?>">
                <label class="pt-label">
                    <?php echo esc_html($meta_fields['score']['label']); ?>
                    <?php if ($is_submitted): ?>
                    <span class="pt-lock-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <?php endif; ?>
                </label>
                <div class="pt-radio-group pt-radio-inline">
                    <?php foreach ($meta_fields['score']['options'] as $key => $label): ?>
                    <label class="pt-radio-item <?php echo $is_submitted ? 'pt-disabled' : ''; ?>">
                        <input type="radio" 
                               name="score" 
                               value="<?php echo esc_attr($key); ?>"
                               <?php checked($values['score'] ?? '', $key); ?>
                               <?php echo $is_submitted ? 'disabled' : ''; ?>>
                        <span class="pt-radio-label"><?php echo esc_html($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($is_submitted && !empty($values['score'])): ?>
                <input type="hidden" name="score" value="<?php echo esc_attr($values['score']); ?>">
                <?php endif; ?>
            </div>
            
            <!-- Commercial Register -->
            <div class="pt-form-group pt-col-full <?php echo $is_submitted ? 'pt-field-locked' : ''; ?>">
                <label for="commercial_register" class="pt-label">
                    <?php echo esc_html($meta_fields['commercial_register']['label']); ?>
                    <?php if ($is_submitted): ?>
                    <span class="pt-lock-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <?php endif; ?>
                </label>
                <input type="text" 
                       id="commercial_register" 
                       name="commercial_register" 
                       class="pt-input pt-number-input" 
                       placeholder="أدخل رقم السجل التجاري"
                       pattern="[0-9]*"
                       inputmode="numeric"
                       value="<?php echo esc_attr($values['commercial_register'] ?? ''); ?>"
                       <?php echo $is_submitted ? 'readonly disabled' : ''; ?>>
                <?php if ($is_submitted && !empty($values['commercial_register'])): ?>
                    <input type="hidden" name="commercial_register" value="<?php echo esc_attr($values['commercial_register']); ?>">
                    <span class="pt-hint">لا يمكن تعديل رقم السجل التجاري بعد الحفظ</span>
                <?php endif; ?>
            </div>
            
            <!-- Date of Grant -->
            <div class="pt-form-group pt-col-full">
                <label for="date_of_grant_of_record" class="pt-label">
                    <?php echo esc_html($meta_fields['date_of_grant_of_record']['label']); ?>
                </label>
                <input type="date" 
                       id="date_of_grant_of_record" 
                       name="date_of_grant_of_record" 
                       class="pt-input" 
                       value="<?php echo esc_attr($values['date_of_grant_of_record'] ?? ''); ?>">
            </div>
        </div>
    </div>
    
    <!-- Contact Information -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                معلومات التواصل
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <!-- Phone -->
            <div class="pt-form-group pt-col-half">
                <label for="phone" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <?php echo esc_html($meta_fields['phone']['label']); ?>
                </label>
                <input type="tel" 
                       id="phone" 
                       name="phone" 
                       class="pt-input pt-phone-input" 
                       placeholder="أدخل رقم الهاتف"
                       pattern="[0-9]*"
                       inputmode="numeric"
                       dir="ltr"
                       value="<?php echo esc_attr($values['phone'] ?? ''); ?>">
            </div>
            
            <!-- WhatsApp -->
            <div class="pt-form-group pt-col-half">
                <label for="whatsapp" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <?php echo esc_html($meta_fields['whatsapp']['label']); ?>
                </label>
                <input type="tel" 
                       id="whatsapp" 
                       name="whatsapp" 
                       class="pt-input pt-phone-input" 
                       placeholder="أدخل رقم الواتساب"
                       pattern="[0-9]*"
                       inputmode="numeric"
                       dir="ltr"
                       value="<?php echo esc_attr($values['whatsapp'] ?? ''); ?>">
            </div>
            
            <!-- Email -->
            <div class="pt-form-group pt-col-half">
                <label for="email" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <?php echo esc_html($meta_fields['email']['label']); ?>
                </label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="pt-input" 
                       placeholder="example@email.com"
                       value="<?php echo esc_attr($values['email'] ?? ''); ?>"
                       dir="ltr">
            </div>
            
            <!-- Website -->
            <div class="pt-form-group pt-col-half">
                <label for="website" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                    <?php echo esc_html($meta_fields['website']['label']); ?>
                </label>
                <input type="url" 
                       id="website" 
                       name="website" 
                       class="pt-input" 
                       value="<?php echo esc_attr($values['website'] ?? ''); ?>"
                       placeholder="https://"
                       dir="ltr">
            </div>
            
            <!-- Facebook -->
            <div class="pt-form-group pt-col-half">
                <label for="facebook_page" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <?php echo esc_html($meta_fields['facebook_page']['label']); ?>
                </label>
                <input type="url" 
                       id="facebook_page" 
                       name="facebook_page" 
                       class="pt-input" 
                       value="<?php echo esc_attr($values['facebook_page'] ?? ''); ?>"
                       placeholder="https://facebook.com/"
                       dir="ltr">
            </div>
            
            <!-- Instagram -->
            <div class="pt-form-group pt-col-half">
                <label for="instagram_page" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                    <?php echo esc_html($meta_fields['instagram_page']['label']); ?>
                </label>
                <input type="url" 
                       id="instagram_page" 
                       name="instagram_page" 
                       class="pt-input" 
                       value="<?php echo esc_attr($values['instagram_page'] ?? ''); ?>"
                       placeholder="https://instagram.com/"
                       dir="ltr">
            </div>
            
            <!-- Map Location -->
            <div class="pt-form-group pt-col-full">
                <label for="map_location" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <?php echo esc_html($meta_fields['map_location']['label']); ?>
                </label>
                <input type="text" 
                       id="map_location" 
                       name="map_location" 
                       class="pt-input" 
                       placeholder="أدخل العنوان أو رابط الموقع على الخريطة"
                       value="<?php echo esc_attr($values['map_location'] ?? ''); ?>">
            </div>
        </div>
    </div>
    
    <!-- Services/Products -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                <?php echo esc_html($meta_fields['services']['label']); ?>
            </h2>
            <button type="button" class="pt-btn pt-btn-sm pt-btn-outline pt-add-repeater" data-repeater="services">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                إضافة خدمة
            </button>
        </div>
        
        <div class="pt-repeater" id="services-repeater">
            <?php 
            $services = $values['services'] ?? [];
            if (!is_array($services)) $services = [];
            if (empty($services)) $services = [['services_name' => '']];
            
            foreach ($services as $index => $service): 
            ?>
            <div class="pt-repeater-item" data-index="<?php echo $index; ?>">
                <div class="pt-repeater-content">
                    <input type="text" 
                           name="services[<?php echo $index; ?>][services_name]" 
                           class="pt-input" 
                           placeholder="اسم الخدمة / المنتج"
                           value="<?php echo esc_attr($service['services_name'] ?? ''); ?>">
                </div>
                <button type="button" class="pt-remove-repeater">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Branches -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <?php echo esc_html($meta_fields['bracnches']['label']); ?>
            </h2>
            <button type="button" class="pt-btn pt-btn-sm pt-btn-outline pt-add-repeater" data-repeater="bracnches">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                إضافة فرع
            </button>
        </div>
        
        <div class="pt-repeater pt-repeater-complex" id="bracnches-repeater">
            <?php 
            $branches = $values['bracnches'] ?? [];
            if (!is_array($branches)) $branches = [];
            
            foreach ($branches as $index => $branch): 
            ?>
            <div class="pt-repeater-item pt-branch-item" data-index="<?php echo $index; ?>">
                <div class="pt-branch-header">
                    <span class="pt-branch-title"><?php echo esc_html($branch['اسم_الفرع'] ?? 'فرع جديد'); ?></span>
                    <button type="button" class="pt-toggle-branch">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </div>
                <div class="pt-branch-content">
                    <div class="pt-form-grid">
                        <div class="pt-form-group pt-col-half">
                            <label class="pt-label">اسم الفرع</label>
                            <input type="text" 
                                   name="bracnches[<?php echo $index; ?>][اسم_الفرع]" 
                                   class="pt-input pt-branch-name" 
                                   placeholder="أدخل اسم الفرع"
                                   value="<?php echo esc_attr($branch['اسم_الفرع'] ?? ''); ?>">
                        </div>
                        <div class="pt-form-group pt-col-half">
                            <label class="pt-label">الهاتف</label>
                            <input type="tel" 
                                   name="bracnches[<?php echo $index; ?>][الهاتف]" 
                                   class="pt-input pt-phone-input" 
                                   placeholder="أدخل رقم الهاتف"
                                   pattern="[0-9]*"
                                   inputmode="numeric"
                                   dir="ltr"
                                   value="<?php echo esc_attr($branch['الهاتف'] ?? ''); ?>">
                        </div>
                        <div class="pt-form-group pt-col-full">
                            <label class="pt-label">العنوان</label>
                            <input type="text" 
                                   name="bracnches[<?php echo $index; ?>][العنوان]" 
                                   class="pt-input" 
                                   placeholder="أدخل عنوان الفرع"
                                   value="<?php echo esc_attr($branch['العنوان'] ?? ''); ?>">
                        </div>
                        <div class="pt-form-group pt-col-full">
                            <label class="pt-label">المنتجات</label>
                            <textarea name="bracnches[<?php echo $index; ?>][المنتجات]" 
                                      class="pt-textarea" 
                                      placeholder="أدخل المنتجات أو الخدمات المتوفرة في هذا الفرع"
                                      rows="3"><?php echo esc_textarea($branch['المنتجات'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <button type="button" class="pt-remove-repeater pt-remove-branch">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    حذف الفرع
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Form Actions -->
    <div class="pt-form-actions">
        <button type="submit" class="pt-btn pt-btn-primary pt-btn-lg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            <?php echo $is_edit ? 'حفظ التغييرات' : 'نشر الإعلان'; ?>
        </button>
        
        <a href="<?php echo esc_url(add_query_arg('tab', 'listings', get_permalink())); ?>" class="pt-btn pt-btn-outline pt-btn-lg">
            إلغاء
        </a>
    </div>
    
    <div class="pt-form-message" id="pt-form-message"></div>
</form>

<!-- Branch Template -->
<template id="branch-template">
    <div class="pt-repeater-item pt-branch-item" data-index="__INDEX__">
        <div class="pt-branch-header">
            <span class="pt-branch-title">فرع جديد</span>
            <button type="button" class="pt-toggle-branch">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
        </div>
        <div class="pt-branch-content">
            <div class="pt-form-grid">
                <div class="pt-form-group pt-col-half">
                    <label class="pt-label">اسم الفرع</label>
                    <input type="text" name="bracnches[__INDEX__][اسم_الفرع]" class="pt-input pt-branch-name" placeholder="أدخل اسم الفرع">
                </div>
                <div class="pt-form-group pt-col-half">
                    <label class="pt-label">الهاتف</label>
                    <input type="tel" name="bracnches[__INDEX__][الهاتف]" class="pt-input pt-phone-input" placeholder="أدخل رقم الهاتف" pattern="[0-9]*" inputmode="numeric" dir="ltr">
                </div>
                <div class="pt-form-group pt-col-full">
                    <label class="pt-label">العنوان</label>
                    <input type="text" name="bracnches[__INDEX__][العنوان]" class="pt-input" placeholder="أدخل عنوان الفرع">
                </div>
                <div class="pt-form-group pt-col-full">
                    <label class="pt-label">المنتجات</label>
                    <textarea name="bracnches[__INDEX__][المنتجات]" class="pt-textarea" placeholder="أدخل المنتجات أو الخدمات المتوفرة في هذا الفرع" rows="3"></textarea>
                </div>
            </div>
        </div>
        <button type="button" class="pt-remove-repeater pt-remove-branch">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            حذف الفرع
        </button>
    </div>
</template>

<!-- Service Template -->
<template id="service-template">
    <div class="pt-repeater-item" data-index="__INDEX__">
        <div class="pt-repeater-content">
            <input type="text" name="services[__INDEX__][services_name]" class="pt-input" placeholder="اسم الخدمة / المنتج">
        </div>
        <button type="button" class="pt-remove-repeater">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
        </button>
    </div>
</template>

<!-- Logo Crop Modal -->
<div id="pt-crop-modal" class="pt-crop-modal" style="display: none;">
    <div class="pt-crop-modal-overlay"></div>
    <div class="pt-crop-modal-content">
        <div class="pt-crop-modal-header">
            <h3>تعديل الشعار</h3>
            <button type="button" class="pt-crop-modal-close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="pt-crop-modal-body">
            <div class="pt-crop-container">
                <img id="pt-crop-image" src="" alt="صورة للاقتصاص">
            </div>
            <div class="pt-crop-controls">
                <div class="pt-zoom-controls">
                    <button type="button" class="pt-zoom-btn" data-zoom="-0.1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            <line x1="8" y1="11" x2="14" y2="11"></line>
                        </svg>
                        تصغير
                    </button>
                    <input type="range" id="pt-zoom-slider" min="0.1" max="3" step="0.1" value="1">
                    <button type="button" class="pt-zoom-btn" data-zoom="0.1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            <line x1="11" y1="8" x2="11" y2="14"></line>
                            <line x1="8" y1="11" x2="14" y2="11"></line>
                        </svg>
                        تكبير
                    </button>
                </div>
                <div class="pt-crop-hint">
                    اسحب الصورة لتحريكها واستخدم أزرار التكبير والتصغير لضبط الحجم
                </div>
            </div>
        </div>
        <div class="pt-crop-modal-footer">
            <button type="button" class="pt-btn pt-btn-primary" id="pt-crop-apply">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                اقتصاص وحفظ
            </button>
            <button type="button" class="pt-btn pt-btn-outline" id="pt-crop-cancel">
                إلغاء
            </button>
        </div>
    </div>
</div>
