<?php
/**
 * Optimized Minimal Trader Page Template
 * Shortcode: [trader_page id="123"] or [trader_page] (uses URL parameter)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get trader ID
$trader_id = isset($trader_id) ? intval($trader_id) : 0;
if (!$trader_id && isset($_GET['trader_id'])) {
    $trader_id = intval($_GET['trader_id']);
}
if (!$trader_id) {
    $trader_id = get_the_ID();
}

// Get the trader post
$trader = get_post($trader_id);
if (!$trader || $trader->post_type !== 'trader') {
    echo '<div class="pt-error" style="padding: 20px; text-align: center; color: #dc2626;">التاجر غير موجود</div>';
    return;
}

// OPTIMIZATION: Prime meta cache to avoid N+1 queries
update_meta_cache('post', [$trader_id]);

// Helper function to format date in Arabic
function pt_format_arabic_date($date_string) {
    if (empty($date_string)) return '';
    $months_arabic = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $timestamp = strtotime($date_string);
    if (!$timestamp) return $date_string;
    return $months_arabic[date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}

// Helper to get meta with fallback
function pt_get_meta($post_id, $keys, $default = '') {
    foreach ((array)$keys as $key) {
        $value = get_post_meta($post_id, $key, true);
        if (!empty($value)) return $value;
    }
    return $default;
}

// Core fields
$trader_name = $trader->post_title;
$trader_content = $trader->post_content;
$trader_date = $trader->post_date;

// OPTIMIZATION: Get all meta fields efficiently
$meta_keys = [
    'phone' => ['je_trader_phone', 'phone'],
    'whatsapp' => ['je_trader_whatsapp', 'whatsapp'],
    'email' => ['je_trader_email', 'email'],
    'website' => ['je_trader_website', 'website'],
    'company_type' => ['je_trader_company_type', 'company_type'],
    'score' => ['je_trader_score', 'score'],
    'commercial_register' => ['je_trader_commercial_register', 'commercial_register'],
    'commercial_industry' => ['je_trader_commercial_industry', 'commercial_industry'],
    'date_of_grant' => ['je_trader_date_of_grant_of_record', 'date_of_grant_of_record'],
    'map_location' => ['je_trader_map_location', 'map_location'],
    'profile_pdf' => ['je_trader_profile'],
    'facebook' => ['je_trader_facebook_page', 'facebook_page'],
    'instagram' => ['je_trader_instagram_page', 'instagram_page'],
    'short_desc' => ['short_desc'],
    'type_of_industry' => ['type_of_industry'],
    'is_featured' => ['is_featured'],
    'gallery' => ['je_trader_gallary', 'gallary', 'gallery'],
    'logo' => ['logo'],
];

$meta = [];
foreach ($meta_keys as $key => $keys) {
    $meta[$key] = pt_get_meta($trader_id, $keys);
}

// Logo
$trader_logo_id = $meta['logo'] ?: get_post_thumbnail_id($trader_id);
$trader_logo_url = $trader_logo_id ? wp_get_attachment_image_url($trader_logo_id, 'medium') : '';

// OPTIMIZATION: Get all taxonomies in one call
$taxonomies = ['sector', 'activity', 'economic_activity'];
$all_terms = [];
foreach ($taxonomies as $tax) {
    $terms = wp_get_post_terms($trader_id, $tax, ['fields' => 'names']);
    if (!is_wp_error($terms)) {
        $all_terms = array_merge($all_terms, $terms);
    }
}

// Format data
$trader_score = $meta['score'] ?: '9.8';
$member_since = pt_format_arabic_date($trader_date);
$formatted_grant_date = '';
if (!empty($meta['date_of_grant'])) {
    $timestamp = strtotime($meta['date_of_grant']);
    $formatted_grant_date = $timestamp ? date('d/m/Y', $timestamp) : $meta['date_of_grant'];
}

// WhatsApp link
$whatsapp_link = '';
if (!empty($meta['whatsapp'])) {
    $whatsapp_clean = preg_replace('/[^0-9]/', '', $meta['whatsapp']);
    $whatsapp_link = 'https://wa.me/' . $whatsapp_clean;
}

// Google Maps link
$google_maps_link = !empty($meta['map_location']) ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($meta['map_location']) : '';

// Star rating
$star_count = min(5, max(1, round(floatval($trader_score) / 2)));

// OPTIMIZATION: Process gallery images efficiently
$gallery_images = [];
if (!empty($meta['gallery'])) {
    $gallery_ids = array_filter(array_map('intval', explode(',', $meta['gallery'])));
    if (!empty($gallery_ids)) {
        // Prime attachment cache
        _prime_post_caches($gallery_ids, false, true);
        foreach ($gallery_ids as $img_id) {
            $img_url = wp_get_attachment_image_url($img_id, 'medium');
            if ($img_url) {
                $gallery_images[] = [
                    'id' => $img_id,
                    'url' => $img_url,
                    'full_url' => wp_get_attachment_image_url($img_id, 'full'),
                    'alt' => get_post_meta($img_id, '_wp_attachment_image_alt', true) ?: $trader_name
                ];
            }
        }
    }
}

// OPTIMIZATION: Get services efficiently
$services = [];
$services_meta = get_post_meta($trader_id, 'je_trader_services_services_name', true) ?: get_post_meta($trader_id, 'services', true);
if (!empty($services_meta)) {
    if (is_array($services_meta)) {
        $services = $services_meta;
    } elseif (is_string($services_meta)) {
        $decoded = json_decode($services_meta, true);
        $services = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $services_meta)));
    }
}

// OPTIMIZATION: Get branches efficiently
$branches = [];
$branches_meta = get_post_meta($trader_id, 'bracnches', true) ?: get_post_meta($trader_id, 'branches', true);
if (!empty($branches_meta) && is_array($branches_meta)) {
    foreach ($branches_meta as $branch_data) {
        if (is_array($branch_data)) {
            $branches[] = [
                'name' => $branch_data['name'] ?? $branch_data['اسم_الفرع'] ?? '',
                'phone' => $branch_data['phone'] ?? $branch_data['الهاتف'] ?? '',
                'address' => $branch_data['address'] ?? $branch_data['العنوان'] ?? '',
                'products' => $branch_data['products'] ?? $branch_data['المنتجات'] ?? '',
            ];
        }
    }
}

// If no branches from repeater, try CPT (only if needed)
if (empty($branches)) {
    global $wpdb;
    $branch_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'branch' AND p.post_status = 'publish'
         AND pm.meta_key = '_trader_id' AND pm.meta_value = %d
         ORDER BY p.menu_order, p.post_date ASC LIMIT 10",
        $trader_id
    ));
    if (!empty($branch_ids)) {
        update_meta_cache('post', $branch_ids);
        foreach ($branch_ids as $branch_id) {
            $branch = get_post($branch_id);
            if ($branch) {
                $branches[] = [
                    'name' => $branch->post_title,
                    'phone' => get_post_meta($branch_id, 'phone', true),
                    'address' => get_post_meta($branch_id, 'address', true),
                    'products' => get_post_meta($branch_id, 'products', true),
                ];
            }
        }
    }
}

?>

<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"></noscript>

<div class="pt-trader-page" dir="rtl" lang="ar">

<!-- Hero Section (Full Width) -->
<div class="pt-hero-section">
    <div class="pt-container">
        <div class="pt-header-card">
        <div class="pt-header-content">
            <div class="pt-header-main">
                <div class="pt-logo-wrapper">
                    <?php if ($trader_logo_url): ?>
                        <img src="<?php echo esc_url($trader_logo_url); ?>" alt="<?php echo esc_attr($trader_name); ?>" class="pt-logo" loading="eager" width="128" height="128"/>
                    <?php else: ?>
                        <div class="pt-logo-placeholder"><?php echo esc_html(mb_substr($trader_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="pt-verified-badge">
                        <span class="material-symbols-outlined">verified</span>
                    </div>
                </div>
                <div class="pt-header-info">
                    <h1 class="pt-title"><?php echo esc_html($trader_name); ?></h1>
                    <?php if (!empty($all_terms)): ?>
                        <div class="pt-categories">
                            <?php foreach (array_slice($all_terms, 0, 5) as $term): ?>
                                <span class="pt-category"><?php echo esc_html($term); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($member_since): ?>
                        <div class="pt-member-since">
                            <span class="material-symbols-outlined" style="font-size:18px">calendar_today</span>
                            عضو منذ <?php echo esc_html($member_since); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="pt-actions">
                <?php if ($meta['website']): ?>
                    <a href="<?php echo esc_url($meta['website']); ?>" target="_blank" rel="noopener" class="pt-btn pt-btn-sec">
                        <span class="material-symbols-outlined">language</span>
                        <span>الموقع الإلكتروني</span>
                    </a>
                <?php endif; ?>
                <?php if ($meta['phone']): ?>
                    <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $meta['phone'])); ?>" class="pt-btn pt-btn-prim">
                        <span class="material-symbols-outlined">call</span>
                        <span>اتصل الآن</span>
                    </a>
                <?php endif; ?>
                <?php if ($whatsapp_link): ?>
                    <a href="<?php echo esc_url($whatsapp_link); ?>" target="_blank" rel="noopener" class="pt-btn pt-btn-wa">
                        <span class="material-symbols-outlined">chat</span>
                        <span>واتساب</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Main Content -->
<div class="pt-container">
    <!-- Content Grid -->
    <div class="pt-grid">
        <!-- Main Content -->
        <div>
            <!-- About -->
            <div class="pt-card">
                <div class="pt-card-header">
                    <h2 class="pt-card-title">
                        <span class="material-symbols-outlined">info</span>
                        نبذة عن التاجر
                    </h2>
                </div>
                <div class="pt-card-body">
                    <?php if ($trader_content): ?>
                        <?php echo wp_kses_post(wpautop($trader_content)); ?>
                    <?php else: ?>
                        <div class="pt-empty">لا توجد معلومات متاحة</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Services -->
            <div class="pt-card">
                <div class="pt-card-header">
                    <h2 class="pt-card-title">
                        <span class="material-symbols-outlined">inventory_2</span>
                        الخدمات والمنتجات
                    </h2>
                </div>
                <div class="pt-card-body">
                    <?php if (!empty($services)): ?>
                        <div class="pt-services-grid">
                            <?php foreach ($services as $service): 
                                $service_name = is_array($service) ? ($service['name'] ?? $service['services_name'] ?? '') : $service;
                                if (empty($service_name)) continue;
                            ?>
                                <div class="pt-service">
                                    <div class="pt-service-icon-check">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M16.6667 5L7.50004 14.1667L3.33337 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="pt-service-content">
                                        <h4><?php echo esc_html($service_name); ?></h4>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pt-empty">لا توجد خدمات متاحة</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gallery -->
            <div class="pt-card">
                <div class="pt-card-header">
                    <h2 class="pt-card-title">
                        <span class="material-symbols-outlined">collections</span>
                        معرض الصور
                    </h2>
                </div>
                <div class="pt-card-body">
                    <?php if (!empty($gallery_images)): ?>
                        <div class="pt-gallery-grid">
                            <?php foreach (array_slice($gallery_images, 0, 9) as $img): ?>
                                <div class="pt-gallery-item" onclick="window.open('<?php echo esc_url($img['full_url']); ?>', '_blank')">
                                    <img src="<?php echo esc_url($img['url']); ?>" alt="<?php echo esc_attr($img['alt']); ?>" loading="lazy" width="300" height="300"/>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pt-empty">لا توجد صور متاحة</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Branches -->
            <div class="pt-card">
                <div class="pt-card-header">
                    <h2 class="pt-card-title">
                        <span class="material-symbols-outlined">location_on</span>
                        الفروع والمواقع
                    </h2>
                </div>
                <div class="pt-card-body">
                    <?php if (!empty($branches)): ?>
                        <?php foreach ($branches as $branch): ?>
                            <div class="pt-branch">
                                <div class="pt-branch-name"><?php echo esc_html($branch['name'] ?: 'فرع'); ?></div>
                                <?php if ($branch['address']): ?>
                                    <div class="pt-branch-info">
                                        <span class="material-symbols-outlined">location_on</span>
                                        <?php echo esc_html($branch['address']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($branch['phone']): ?>
                                    <div class="pt-branch-info">
                                        <span class="material-symbols-outlined">call</span>
                                        <?php echo esc_html($branch['phone']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($branch['products']): ?>
                                    <div class="pt-branch-info">
                                        <span class="material-symbols-outlined">inventory</span>
                                        <?php echo esc_html($branch['products']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($google_maps_link): ?>
                            <a href="<?php echo esc_url($google_maps_link); ?>" target="_blank" rel="noopener" class="pt-btn pt-btn-prim" style="margin-top:16px;display:inline-flex">
                                <span class="material-symbols-outlined">map</span>
                                <span>عرض على الخريطة</span>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="pt-empty">لا توجد فروع مسجلة</div>
                        <?php if ($meta['map_location']): ?>
                            <div style="margin-top:16px;padding:16px;background:var(--bg);border-radius:8px">
                                <strong>📍 الموقع:</strong> <?php echo esc_html($meta['map_location']); ?>
                                <?php if ($google_maps_link): ?>
                                    <a href="<?php echo esc_url($google_maps_link); ?>" target="_blank" rel="noopener" class="pt-btn pt-btn-prim" style="margin-top:12px;display:inline-flex">
                                        <span class="material-symbols-outlined">map</span>
                                        <span>عرض على الخريطة</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Business Info -->
            <div class="pt-sidebar-card">
                <div class="pt-card-header">
                    <h3 class="pt-card-title">
                        <span class="material-symbols-outlined">verified_user</span>
                        معلومات الأعمال الموثقة
                    </h3>
                </div>
                <div class="pt-card-body" style="padding:0">
                    <div class="pt-info-item">
                        <span class="pt-info-label">
                            <span class="material-symbols-outlined">description</span>
                            السجل التجاري
                        </span>
                        <span class="pt-info-value"><?php echo esc_html($meta['commercial_register'] ?: 'غير متوفر'); ?></span>
                    </div>
                    <?php if ($meta['score']): ?>
                        <div class="pt-info-item">
                            <span class="pt-info-label">
                                <span class="material-symbols-outlined">star</span>
                                درجة السجل
                            </span>
                            <span class="pt-info-value"><?php echo esc_html($meta['score']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="pt-info-item">
                        <span class="pt-info-label">
                            <span class="material-symbols-outlined">factory</span>
                            السجل الصناعي
                        </span>
                        <span class="pt-info-value"><?php echo esc_html($meta['commercial_industry'] ?: 'غير متوفر'); ?></span>
                    </div>
                    <div class="pt-info-item">
                        <span class="pt-info-label">
                            <span class="material-symbols-outlined">business_center</span>
                            نوع الشركة
                        </span>
                        <span class="pt-info-value"><?php echo esc_html($meta['company_type'] ?: 'غير متوفر'); ?></span>
                    </div>
                    <?php if ($formatted_grant_date): ?>
                        <div class="pt-info-item">
                            <span class="pt-info-label">
                                <span class="material-symbols-outlined">event_available</span>
                                تاريخ منح السجل                            </span>
                            <span class="pt-info-value"><?php echo esc_html($formatted_grant_date); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact -->
            <div class="pt-sidebar-card">
                <div class="pt-card-header">
                    <h3 class="pt-card-title">
                        <span class="material-symbols-outlined">contact_mail</span>
                        معلومات الاتصال
                    </h3>
                </div>
                <div class="pt-card-body" style="padding:0">
                    <?php if ($meta['phone']): ?>
                        <div class="pt-contact-item">
                            <div class="pt-contact-icon">
                                <span class="material-symbols-outlined">call</span>
                            </div>
                            <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $meta['phone'])); ?>" class="pt-contact-link"><?php echo esc_html($meta['phone']); ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if ($meta['email']): ?>
                        <div class="pt-contact-item">
                            <div class="pt-contact-icon">
                                <span class="material-symbols-outlined">email</span>
                            </div>
                            <a href="mailto:<?php echo esc_attr($meta['email']); ?>" class="pt-contact-link"><?php echo esc_html($meta['email']); ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if ($meta['website']): ?>
                        <div class="pt-contact-item">
                            <div class="pt-contact-icon">
                                <span class="material-symbols-outlined">language</span>
                            </div>
                            <a href="<?php echo esc_url($meta['website']); ?>" target="_blank" rel="noopener" class="pt-contact-link"><?php echo esc_html($meta['website']); ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if ($meta['facebook'] || $meta['instagram']): ?>
                        <div class="pt-social-links">
                            <?php if ($meta['facebook']): ?>
                                <a href="<?php echo esc_url($meta['facebook']); ?>" target="_blank" rel="noopener" class="pt-social-link" title="Facebook">
                                    <span class="material-symbols-outlined">social_leaderboard</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($meta['instagram']): ?>
                                <a href="<?php echo esc_url($meta['instagram']); ?>" target="_blank" rel="noopener" class="pt-social-link" title="Instagram">
                                    <span class="material-symbols-outlined">photo_camera</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                      <!-- Download PDF -->
            <?php if (shortcode_exists('trader_download')): ?>
            <div class="">
                <div class="pt-card-body" style="text-align:center;">
                    <?php echo do_shortcode('[trader_download id="' . $trader_id . '" text="تحميل الملف الشخصي للتاجر"]'); ?>
                </div>
            </div>
            <?php elseif ($meta['profile_pdf']): ?>
            <div class="pt-sidebar-card">
                <div class="pt-card-body" style="text-align:center;">
                    <a href="<?php echo esc_url($meta['profile_pdf']); ?>" target="_blank" class="pt-btn pt-btn-prim" style="width:100%;justify-content:center;display:inline-flex;text-decoration:none">
                        <span class="material-symbols-outlined">download</span>
                        <span>تحميل الملف الشخصي للتاجر</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
                </div>
            </div>

          

            <!-- QR Code -->
            <?php if (shortcode_exists('trader_qr')): ?>
            <div class="pt-sidebar-card">
                <div class="pt-card-header">
                    <h3 class="pt-card-title" style="justify-content:center">
                        <span class="material-symbols-outlined">qr_code</span>
                        مسح الرمز السريع
                    </h3>
                </div>
                <div class="pt-card-body" style="text-align:center;padding:24px">
                    <?php echo do_shortcode('[trader_qr id="' . $trader_id . '" size="250" download="true" download_text="تحميل ال QR"]'); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reviews Section (Full Width) -->
    <div class="pt-container">
        <?php if (shortcode_exists('trader_reviews')): ?>
            <div class="">
                <?php echo do_shortcode('[trader_reviews id="' . $trader_id . '"]'); ?>
            </div>
        <?php else: ?>
            <div class="pt-card">
                <div class="pt-card-header">
                    <h2 class="pt-card-title">التقييمات</h2>
                </div>
                <div class="pt-card-body">
                    <?php
                    // Get reviews data (if available from meta or custom table)
                    $reviews = [];
                    $total_reviews = 0;
                    $avg_rating = 0;
                    $rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                    
                    // Try to get reviews from meta or custom implementation
                    $reviews_meta = get_post_meta($trader_id, 'trader_reviews', true);
                    if (!empty($reviews_meta) && is_array($reviews_meta)) {
                        $reviews = $reviews_meta;
                        $total_reviews = count($reviews);
                        $total_rating = 0;
                        foreach ($reviews as $review) {
                            $rating = intval($review['rating'] ?? 0);
                            if ($rating >= 1 && $rating <= 5) {
                                $rating_counts[$rating]++;
                                $total_rating += $rating;
                            }
                        }
                        $avg_rating = $total_reviews > 0 ? round($total_rating / $total_reviews, 1) : 0;
                    }
                    ?>
                    
                    <!-- Rating Summary -->
                    <div style="display:flex;flex-direction:column;gap:20px;margin-bottom:24px">
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                            <div style="font-size:32px;font-weight:900;color:var(--t);line-height:1"><?php echo esc_html($total_reviews); ?></div>
                            <div style="font-size:14px;color:var(--tg)">(<?php echo esc_html($total_reviews); ?> تقييم)</div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <?php for ($star = 5; $star >= 1; $star--): 
                                $count = $rating_counts[$star] ?? 0;
                            ?>
                                <div style="display:flex;align-items:center;gap:12px">
                                    <div style="font-size:14px;font-weight:600;color:var(--t);min-width:60px"><?php echo esc_html($star); ?> نجمة</div>
                                    <div style="font-size:14px;font-weight:700;color:var(--t);min-width:30px;text-align:right"><?php echo esc_html($count); ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Login Required / Empty State -->
                    <?php if (!is_user_logged_in()): ?>
                        <div style="background:var(--bg);border-radius:8px;padding:24px;text-align:center;margin-bottom:24px">
                            <div style="font-size:48px;margin-bottom:16px">🔒</div>
                            <h4 style="font-size:18px;font-weight:700;margin:0 0 8px;color:var(--t)">تسجيل الدخول مطلوب</h4>
                            <p style="font-size:14px;color:var(--tg);margin:0 0 16px">يجب عليك تسجيل الدخول لإضافة تقييم</p>
                            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="pt-btn pt-btn-prim" style="min-width:auto;padding:10px 20px">تسجيل الدخول</a>
                                <a href="<?php echo esc_url(wp_registration_url()); ?>" class="pt-btn pt-btn-sec" style="min-width:auto;padding:10px 20px">إنشاء حساب</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($reviews)): ?>
                        <div style="text-align:center;padding:24px 20px">
                            <h4 style="font-size:18px;font-weight:700;margin:0 0 8px;color:var(--t)">لا توجد تقييمات بعد</h4>
                            <p style="font-size:14px;color:var(--tg);margin:0">كن أول من يقيم هذا التاجر!</p>
                        </div>
                    <?php else: ?>
                        <div style="display:flex;flex-direction:column;gap:24px">
                            <?php foreach (array_slice($reviews, 0, 5) as $review): 
                                $review_rating = intval($review['rating'] ?? 0);
                                $review_author = $review['author'] ?? $review['name'] ?? 'مستخدم';
                                $review_date = $review['date'] ?? $review['created_at'] ?? '';
                                $review_comment = $review['comment'] ?? $review['text'] ?? '';
                            ?>
                                <div style="border-bottom:1px solid var(--b);padding-bottom:24px">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;flex-wrap:wrap;gap:12px">
                                        <div style="display:flex;align-items:center;gap:12px">
                                            <div style="width:40px;height:40px;border-radius:50%;background:var(--bg);display:flex;align-items:center;justify-content:center;color:var(--p);font-weight:700;flex-shrink:0">
                                                <?php echo esc_html(mb_substr($review_author, 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:700;font-size:14px;color:var(--t)"><?php echo esc_html($review_author); ?></div>
                                                <?php if ($review_date): ?>
                                                    <div style="font-size:12px;color:var(--tm);margin-top:2px"><?php echo esc_html($review_date); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:2px">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="material-symbols-outlined" style="font-size:18px;color:<?php echo $i <= $review_rating ? 'var(--a)' : 'var(--b)'; ?>">star</span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php if ($review_comment): ?>
                                        <p style="font-size:14px;color:var(--tg);line-height:1.6;margin:0"><?php echo esc_html($review_comment); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($reviews) > 5): ?>
                                <div style="text-align:center;margin-top:16px">
                                    <button class="pt-btn pt-btn-sec" style="font-size:13px;padding:8px 16px;min-width:auto">
                                        عرض المزيد من التقييمات (<?php echo esc_html(count($reviews) - 5); ?>)
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
