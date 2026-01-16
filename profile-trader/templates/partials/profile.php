<?php
/**
 * Profile Partial Template
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$profile_updated = isset($_GET['profile_updated']) && $_GET['profile_updated'] === '1';
?>

<?php if ($profile_updated): ?>
<div class="pt-alert pt-alert-success">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>
    تم تحديث الملف الشخصي بنجاح
</div>
<?php endif; ?>

<div class="pt-profile-section">
    <div class="pt-profile-header">
        <div class="pt-profile-avatar">
            <div class="pt-avatar-container" id="pt-avatar-container">
                <?php 
                $avatar_id = get_user_meta($current_user->ID, 'pt_user_avatar', true);
                if ($avatar_id) {
                    $avatar_url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
                    echo '<img src="' . esc_url($avatar_url) . '" alt="' . esc_attr($current_user->display_name) . '" class="pt-avatar-image">';
                } else {
                    echo get_avatar($current_user->ID, 120);
                }
                ?>
                <div class="pt-avatar-overlay">
                    <button type="button" class="pt-change-avatar" id="pt-change-avatar-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            <input type="file" id="pt-avatar-upload" accept="image/png,image/jpeg,image/jpg" style="display:none;">
            <div class="pt-avatar-upload-progress" id="pt-avatar-upload-progress" style="display:none;">
                <div class="pt-progress-bar">
                    <div class="pt-progress-fill" style="width:0%"></div>
                </div>
                <span class="pt-progress-text">0%</span>
            </div>
            <div class="pt-avatar-error" id="pt-avatar-error" style="display:none;"></div>
        </div>
        <div class="pt-profile-info">
            <h2><?php echo esc_html($current_user->display_name); ?></h2>
            <p><?php echo esc_html($current_user->user_email); ?></p>
            <span class="pt-member-since">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                عضو منذ: <?php echo date_i18n('F Y', strtotime($current_user->user_registered)); ?>
            </span>
        </div>
    </div>
</div>

<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="pt-form pt-profile-form">
    <input type="hidden" name="action" value="pt_update_profile">
    <?php wp_nonce_field('pt_update_profile', 'pt_profile_nonce'); ?>
    
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                المعلومات الشخصية
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <div class="pt-form-group pt-col-half">
                <label for="first_name" class="pt-label">الاسم الأول</label>
                <input type="text" 
                       id="first_name" 
                       name="first_name" 
                       class="pt-input" 
                       value="<?php echo esc_attr($current_user->first_name); ?>">
            </div>
            
            <div class="pt-form-group pt-col-half">
                <label for="last_name" class="pt-label">الاسم الأخير</label>
                <input type="text" 
                       id="last_name" 
                       name="last_name" 
                       class="pt-input" 
                       value="<?php echo esc_attr($current_user->last_name); ?>">
            </div>
            
            <div class="pt-form-group pt-col-half">
                <label for="display_name" class="pt-label">الاسم المعروض</label>
                <input type="text" 
                       id="display_name" 
                       name="display_name" 
                       class="pt-input" 
                       value="<?php echo esc_attr($current_user->display_name); ?>">
            </div>
            
            <div class="pt-form-group pt-col-half">
                <label for="user_email" class="pt-label">البريد الإلكتروني</label>
                <input type="email" 
                       id="user_email" 
                       name="user_email" 
                       class="pt-input" 
                       value="<?php echo esc_attr($current_user->user_email); ?>"
                       dir="ltr">
            </div>
            
            
        </div>
    </div>
    
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                تغيير كلمة المرور
            </h2>
        </div>
        
        <div class="pt-form-grid">
            <div class="pt-form-group pt-col-full">
                <label for="current_password" class="pt-label">كلمة المرور الحالية</label>
                <div class="pt-password-field">
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="pt-input" 
                           dir="ltr">
                    <button type="button" class="pt-toggle-password">
                        <svg class="pt-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="pt-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="pt-form-group pt-col-half">
                <label for="new_password" class="pt-label">كلمة المرور الجديدة</label>
                <div class="pt-password-field">
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="pt-input" 
                           dir="ltr">
                    <button type="button" class="pt-toggle-password">
                        <svg class="pt-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="pt-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="pt-form-group pt-col-half">
                <label for="confirm_password" class="pt-label">تأكيد كلمة المرور</label>
                <div class="pt-password-field">
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="pt-input" 
                           dir="ltr">
                    <button type="button" class="pt-toggle-password">
                        <svg class="pt-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="pt-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <p class="pt-password-hint">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            اترك حقول كلمة المرور فارغة إذا كنت لا تريد تغييرها
        </p>
    </div>
    
    <div class="pt-form-actions">
        <button type="submit" class="pt-btn pt-btn-primary pt-btn-lg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            حفظ التغييرات
        </button>
    </div>
</form>

