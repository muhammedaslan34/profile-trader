<?php
/**
 * Company Create Form Partial Template
 * Form for registering a new company (saves to company-create post type)
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();

// Get taxonomy terms
$cities = get_terms(['taxonomy' => 'city', 'hide_empty' => false]);
$activities = get_terms(['taxonomy' => 'activity', 'hide_empty' => false]);
$sectors = get_terms(['taxonomy' => 'sector', 'hide_empty' => false]);
$economic_activities = get_terms(['taxonomy' => 'economic_activity', 'hide_empty' => false]);
?>

<form id="pt-company-form" class="pt-form" enctype="multipart/form-data">
    <?php wp_nonce_field('pt_nonce', 'pt_form_nonce'); ?>
    
    <!-- Basic Information -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                معلومات الشركة
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <!-- Company Name -->
            <div class="pt-form-group pt-col-half">
                <label for="company_name" class="pt-label">
                    اسم الشركة التجاري
                    <span class="pt-required">*</span>
                </label>
                <input type="text" 
                       id="company_name" 
                       name="company_name" 
                       class="pt-input" 
                       required>
            </div>
            
            <!-- Company Type -->
            <div class="pt-form-group pt-col-half">
                <label for="company_type" class="pt-label">
                    نوع الشركة
                    <span class="pt-required">*</span>
                </label>
                <input type="text" 
                       id="company_type" 
                       name="company_type" 
                       class="pt-input" 
                       placeholder="مثال: شركة ذات مسؤولية محدودة"
                       required>
            </div>
            
            <!-- Owner Name -->
            <div class="pt-form-group pt-col-half">
                <label for="owner_name" class="pt-label">
                    اسم صاحب الشركة / المدير المفوض
                    <span class="pt-required">*</span>
                </label>
                <input type="text" 
                       id="owner_name" 
                       name="owner_name" 
                       class="pt-input" 
                       required>
            </div>
            
            <!-- National Number -->
            <div class="pt-form-group pt-col-half">
                <label for="national_number" class="pt-label">
                    الرقم الوطني لصاحب الشركة
                    <span class="pt-required">*</span>
                </label>
                <input type="text" 
                       id="national_number" 
                       name="national_number" 
                       class="pt-input" 
                       dir="ltr"
                       required>
            </div>
            
            <!-- About Company -->
            <div class="pt-form-group pt-col-full">
                <label for="about_company" class="pt-label">
                    وصف النشاط التجاري بشكل واضح
                    <span class="pt-required">*</span>
                </label>
                <textarea id="about_company"
                          name="about_company"
                          class="pt-textarea pt-editor"
                          rows="5"
                          required></textarea>
            </div>

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
                          rows="3"></textarea>
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
                          rows="3"></textarea>
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
        <div class="pt-repeater pt-stats-repeater" id="key_statistics-repeater"></div>
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
        <div class="pt-repeater pt-highlights-repeater" id="about_highlights-repeater"></div>
    </div>

    <!-- Classification Section -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
                التصنيف والموقع
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <!-- Directorate (City Dropdown) -->
            <div class="pt-form-group pt-col-half">
                <label for="directorate" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    المدينة
                    <span class="pt-required">*</span>
                </label>
                <div class="pt-select-wrapper">
                    <span class="pt-select-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <select id="directorate" 
                            name="directorate" 
                            class="pt-select"
                            required>
                        <option value="">اختر المدينة</option>
                        <?php if (!is_wp_error($cities) && !empty($cities)): foreach ($cities as $term): ?>
                        <option value="<?php echo esc_html($term->name); ?>">
                            <?php echo esc_html($term->name); ?>
                        </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            
            <!-- Sector (Dropdown) -->
            <div class="pt-form-group pt-col-half">
                <label for="tax_sector" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    القطاع
                    <span class="pt-required">*</span>
                </label>
                <div class="pt-select-wrapper">
                    <span class="pt-select-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <select id="tax_sector" 
                            name="tax_sector" 
                            class="pt-select"
                            required>
                        <option value="">اختر القطاع</option>
                        <?php if (!is_wp_error($sectors) && !empty($sectors)): foreach ($sectors as $term): ?>
                        <option value="<?php echo $term->term_id; ?>">
                            <?php echo esc_html($term->name); ?>
                        </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            
            <!-- Economic Activity (Dropdown) -->
            <div class="pt-form-group pt-col-half">
                <label for="tax_economic_activity" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    النشاط الاقتصادي
                    <span class="pt-required">*</span>
                </label>
                <div class="pt-select-wrapper">
                    <span class="pt-select-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <select id="tax_economic_activity" 
                            name="tax_economic_activity" 
                            class="pt-select"
                            required>
                        <option value="">اختر النشاط الاقتصادي</option>
                        <?php if (!is_wp_error($economic_activities) && !empty($economic_activities)): foreach ($economic_activities as $term): ?>
                        <option value="<?php echo $term->term_id; ?>">
                            <?php echo esc_html($term->name); ?>
                        </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>
            
            <!-- Activity (Checkbox List) -->
            <div class="pt-form-group pt-col-full">
                <label class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"></polyline>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                    </svg>
                    الأنشطة 
                    <span class="pt-hint">(يمكنك اختيار أكثر من نشاط)</span>
                    <span class="pt-required">*</span>
                </label>
                <div class="pt-checkbox-list">
                    <?php if (!is_wp_error($activities) && !empty($activities)): foreach ($activities as $term): ?>
                    <label class="pt-checkbox-item">
                        <input type="checkbox" 
                               name="tax_activity[]" 
                               value="<?php echo $term->term_id; ?>">
                        <span class="pt-checkbox-mark"></span>
                        <span class="pt-checkbox-label"><?php echo esc_html($term->name); ?></span>
                    </label>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            
            <!-- Full Address -->
            <div class="pt-form-group pt-col-full">
                <label for="full_address" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    العنوان الكامل
                    <span class="pt-required">*</span>
                </label>
                <input type="text" 
                       id="full_address" 
                       name="full_address" 
                       class="pt-input" 
                       required>
            </div>
        </div>
    </div>
    
    <!-- Contact Information Section -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"></path>
                </svg>
                معلومات التواصل
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <!-- Phone Number -->
            <div class="pt-form-group pt-col-half">
                <label for="phone_number" class="pt-label">
                    رقم الهاتف
                    <span class="pt-required">*</span>
                </label>
                <input type="tel" 
                       id="phone_number" 
                       name="phone_number" 
                       class="pt-input pt-phone-input" 
                       pattern="[0-9]*"
                       inputmode="numeric"
                       required>
            </div>
            
            <!-- Email -->
            <div class="pt-form-group pt-col-half">
                <label for="email" class="pt-label">
                    البريد الإلكتروني
                    <span class="pt-required">*</span>
                </label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="pt-input" 
                       dir="ltr"
                       value="<?php echo esc_attr($current_user->user_email); ?>"
                       required>
            </div>
            
            <!-- Website -->
            <div class="pt-form-group pt-col-full">
                <label for="website" class="pt-label">
                    الموقع الإلكتروني للشركة
                </label>
                <input type="url" 
                       id="website" 
                       name="website" 
                       class="pt-input" 
                       dir="ltr"
                       placeholder="https://www.example.com">
            </div>
        </div>
    </div>
    
    <!-- Documents Section -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                المستندات المطلوبة
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <!-- Profile Photo -->
            <div class="pt-form-group pt-col-half">
                <label class="pt-label">
                    صورة شخصية
                    <span class="pt-required">*</span>
                </label>
                <div class="pt-media-upload" data-field="profile_photo">
                    <input type="hidden" name="profile_photo" value="">
                    
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
                </div>
            </div>
            
            <!-- ID Photo -->
            <div class="pt-form-group pt-col-half">
                <label class="pt-label">
                    صورة الهوية
                    <span class="pt-required">*</span>
                </label>
                <div class="pt-media-upload" data-field="id_photo">
                    <input type="hidden" name="id_photo" value="">
                    
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
                </div>
            </div>
        </div>
    </div>
    
    <!-- Submit Button -->
    <div class="pt-form-actions">
        <button type="submit" class="pt-btn pt-btn-primary pt-btn-lg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            تقديم طلب التسجيل
        </button>
    </div>
</form>

