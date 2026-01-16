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
    'twitter' => ['je_trader_twitter', 'twitter', 'twitter_page'],
    'linkedin' => ['je_trader_linkedin', 'linkedin', 'linkedin_page'],
    'youtube' => ['je_trader_youtube', 'youtube', 'youtube_page'],
    'telegram' => ['je_trader_telegram', 'telegram', 'telegram_page'],
    'tiktok' => ['je_trader_tiktok', 'tiktok', 'tiktok_page'],
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

// Get taxonomies separately - activities will have its own section
$header_taxonomies = ['sector', 'economic_activity'];
$header_terms = [];
foreach ($header_taxonomies as $tax) {
    $terms = wp_get_post_terms($trader_id, $tax, ['fields' => 'names']);
    if (!is_wp_error($terms)) {
        $header_terms = array_merge($header_terms, $terms);
    }
}
// Get activities separately for dedicated section
$activity_terms = wp_get_post_terms($trader_id, 'activity', ['fields' => 'all']);
if (is_wp_error($activity_terms)) {
    $activity_terms = [];
}

// Get structured about section fields
$mission_statement = get_post_meta($trader_id, 'mission_statement', true);
$vision = get_post_meta($trader_id, 'vision', true);
$key_statistics = get_post_meta($trader_id, 'key_statistics', true);
$about_highlights = get_post_meta($trader_id, 'about_highlights', true);
$has_structured_about = !empty($mission_statement) || !empty($vision) || !empty($key_statistics) || !empty($about_highlights);

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
            $img_url = wp_get_attachment_image_url($img_id, 'large');
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
                        <?php 
                        // Use Website-icon.svg as fallback
                        $default_logo_url = defined('PT_PLUGIN_URL') ? PT_PLUGIN_URL . 'assets/Website-icon.svg' : plugin_dir_url(dirname(__FILE__)) . 'assets/Website-icon.svg';
                        ?>
                        <img src="<?php echo esc_url($default_logo_url); ?>" alt="<?php echo esc_attr($trader_name); ?>" class="pt-logo pt-logo-default" loading="eager" width="128" height="128"/>
                    <?php endif; ?>
                    <div class="pt-verified-badge">
                        <span class="material-symbols-outlined">verified</span>
                    </div>
                </div>
                <div class="pt-header-info">
                    <h1 class="pt-title"><?php echo esc_html($trader_name); ?></h1>
                    
                    <?php 
                    // Get Economic Activity term
                    $economic_activity_terms = wp_get_post_terms($trader_id, 'economic_activity', ['fields' => 'names']);
                    $economic_activity = !empty($economic_activity_terms) && !is_wp_error($economic_activity_terms) ? $economic_activity_terms[0] : '';
                    
                    // Format score - convert to Arabic ordinal if needed
                    $score_display = '';
                    if (!empty($meta['score'])) {
                        $score_value = $meta['score'];
                        // If score is numeric, try to convert to Arabic ordinal
                        if (is_numeric($score_value)) {
                            $score_num = intval($score_value);
                            $arabic_ordinals = ['', 'الأولى', 'الثانية', 'الثالثة', 'الرابعة', 'الخامسة', 'السادسة', 'السابعة', 'الثامنة', 'التاسعة', 'العاشرة'];
                            if ($score_num >= 1 && $score_num <= 10) {
                                $score_display = $arabic_ordinals[$score_num];
                            } else {
                                $score_display = $score_value;
                            }
                        } else {
                            $score_display = $score_value;
                        }
                    }
                    ?>
                    
                    <?php if ($economic_activity || $score_display): ?>
                    <div class="pt-info-badges">
                        <?php if ($economic_activity): ?>
                        <div class="pt-info-badge pt-badge-activity">
                            النشاط الاقتصادي: <?php echo esc_html($economic_activity); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($score_display): ?>
                        <div class="pt-info-badge pt-badge-score">
                            درجة السجل: <?php echo esc_html($score_display); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    // Get only sector taxonomy terms
                    $sector_terms = wp_get_post_terms($trader_id, 'sector', ['fields' => 'names']);
                    if (!empty($sector_terms) && !is_wp_error($sector_terms)): ?>
                        <div class="pt-categories">
                            <?php foreach (array_slice($sector_terms, 0, 5) as $term): ?>
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
            <!-- About Section - Enhanced -->
            <div class="pt-card pt-about-card">
                <div class="pt-card-header">
                    <h2 class="pt-card-title">
                        <span class="material-symbols-outlined">info</span>
                        نبذة عن التاجر
                    </h2>
                    <button class="pt-about-toggle" aria-expanded="true" onclick="this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'); this.closest('.pt-card').querySelector('.pt-card-body').classList.toggle('is-collapsed');">
                        <span class="material-symbols-outlined">expand_more</span>
                    </button>
                </div>
                <div class="pt-card-body">
                    <?php if ($has_structured_about): ?>

                        <?php // Key Statistics ?>
                        <?php if (!empty($key_statistics) && is_array($key_statistics)): ?>
                        <div class="pt-about-stats">
                            <?php foreach ($key_statistics as $stat):
                                if (empty($stat['stat_number'])) continue;
                                $icon = !empty($stat['stat_icon']) ? $stat['stat_icon'] : 'star';
                            ?>
                            <div class="pt-stat-item">
                                <div class="pt-stat-icon-wrap">
                                    <span class="material-symbols-outlined"><?php echo esc_attr($icon); ?></span>
                                </div>
                                <div class="pt-stat-value"><?php echo esc_html($stat['stat_number']); ?></div>
                                <div class="pt-stat-desc"><?php echo esc_html($stat['stat_label'] ?? ''); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php // Company Overview ?>
                        <?php if ($trader_content): ?>
                        <div class="pt-about-section">
                            <div class="pt-about-section-header">
                                <span class="material-symbols-outlined">apartment</span>
                                <h3>نظرة عامة</h3>
                            </div>
                            <div class="pt-about-section-content">
                                <?php echo wp_kses_post(wpautop($trader_content)); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php // Mission Statement ?>
                        <?php if (!empty($mission_statement)): ?>
                        <div class="pt-about-section pt-mission">
                            <div class="pt-about-section-header">
                                <span class="material-symbols-outlined">track_changes</span>
                                <h3>مهمتنا</h3>
                            </div>
                            <div class="pt-about-section-content pt-highlight-box">
                                <?php echo wp_kses_post(wpautop($mission_statement)); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php // Vision ?>
                        <?php if (!empty($vision)): ?>
                        <div class="pt-about-section pt-vision">
                            <div class="pt-about-section-header">
                                <span class="material-symbols-outlined">visibility</span>
                                <h3>رؤيتنا</h3>
                            </div>
                            <div class="pt-about-section-content pt-highlight-box pt-highlight-accent">
                                <?php echo wp_kses_post(wpautop($vision)); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php // Highlights List ?>
                        <?php if (!empty($about_highlights) && is_array($about_highlights)): ?>
                        <div class="pt-about-section pt-highlights">
                            <div class="pt-about-section-header">
                                <span class="material-symbols-outlined">stars</span>
                                <h3>ما يميزنا</h3>
                            </div>
                            <ul class="pt-highlight-list">
                                <?php foreach ($about_highlights as $item):
                                    if (empty($item['highlight_text'])) continue;
                                ?>
                                <li>
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <?php echo esc_html($item['highlight_text']); ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <?php // Fallback: Original post_content display for backward compatibility ?>
                        <?php if ($trader_content): ?>
                            <?php echo wp_kses_post(wpautop($trader_content)); ?>
                        <?php else: ?>
                            <div class="pt-empty">لا توجد معلومات متاحة</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php // Activities Section - Separate Display ?>
            <?php if (!empty($activity_terms)): ?>
            <div class="pt-card pt-activities-card">
                <div class="pt-card-header">
                    <h2 class="pt-card-title">
                        <span class="material-symbols-outlined">category</span>
                        مجالات النشاط
                    </h2>
                </div>
                <div class="pt-card-body">
                    <div class="pt-activities-grid">
                        <?php foreach ($activity_terms as $term): ?>
                        <div class="pt-activity-item">
                            <div class="pt-activity-icon">
                                <span class="material-symbols-outlined">check_circle</span>
                            </div>
                            <div class="pt-activity-content">
                                <span class="pt-activity-name"><?php echo esc_html($term->name); ?></span>
                                <?php if (!empty($term->description)): ?>
                                <span class="pt-activity-desc"><?php echo esc_html($term->description); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Services -->
            <div class="pt-card pt-services-card">
                <div class="pt-card-header">
                    <h2 class="pt-card-title">
                        <span class="material-symbols-outlined">inventory_2</span>
                        الخدمات والمنتجات
                    </h2>
                    <?php if (!empty($services) && count($services) > 0): ?>
                    <span class="pt-services-count"><?php echo count($services); ?> خدمة</span>
                    <?php endif; ?>
                </div>
                <div class="pt-card-body">
                    <?php if (!empty($services)): ?>
                        <div class="pt-services-grid-enhanced">
                            <?php
                            foreach ($services as $service):
                                $service_name = is_array($service) ? ($service['name'] ?? $service['services_name'] ?? '') : $service;
                                if (empty($service_name)) continue;
                            ?>
                                <div class="pt-service-card">
                                    <div class="pt-service-icon-wrap">
                                        <span class="material-symbols-outlined">package_2</span>
                                    </div>
                                    <div class="pt-service-info">
                                        <h4 class="pt-service-name"><?php echo esc_html($service_name); ?></h4>
                                    </div>
                                    <div class="pt-service-badge">
                                        <span class="material-symbols-outlined">verified</span>
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
                        <?php 
                        $gallery_images_sliced = array_slice($gallery_images, 0, 9);
                        $first_image = $gallery_images_sliced[0];
                        $remaining_images = array_slice($gallery_images_sliced, 1);
                        ?>
                        <div class="pt-gallery-main-wrapper">
                            <!-- Main Large Image -->
                            <div class="pt-gallery-main">
                                <div class="pt-gallery-main-item" data-gallery-full="<?php echo esc_url($first_image['full_url']); ?>">
                                    <img src="<?php echo esc_url($first_image['url']); ?>" alt="<?php echo esc_attr($first_image['alt']); ?>" id="pt-gallery-main-img" loading="eager"/>
                                    <div class="pt-gallery-overlay">
                                        <span class="material-symbols-outlined">zoom_in</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Thumbnails -->
                            <?php if (!empty($remaining_images)): ?>
                            <div class="pt-gallery-thumbnails">
                                <?php foreach ($remaining_images as $index => $img): ?>
                                    <div class="pt-gallery-thumb" data-gallery-index="<?php echo esc_attr($index + 1); ?>" data-gallery-url="<?php echo esc_url($img['url']); ?>" data-gallery-full="<?php echo esc_url($img['full_url']); ?>" data-gallery-alt="<?php echo esc_attr($img['alt']); ?>">
                                        <img src="<?php echo esc_url($img['url']); ?>" alt="<?php echo esc_attr($img['alt']); ?>" loading="lazy"/>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Lightbox Modal -->
                        <div class="pt-gallery-lightbox" id="pt-gallery-lightbox">
                            <div class="pt-gallery-lightbox-close">
                                <span class="material-symbols-outlined">close</span>
                            </div>
                            <div class="pt-gallery-lightbox-prev">
                                <span class="material-symbols-outlined">chevron_right</span>
                            </div>
                            <div class="pt-gallery-lightbox-next">
                                <span class="material-symbols-outlined">chevron_left</span>
                            </div>
                            <div class="pt-gallery-lightbox-content">
                                <img src="" alt="" id="pt-gallery-lightbox-img"/>
                            </div>
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
                        تفاصيل النشاط التجاري
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
                    <?php 
                    $has_social_sidebar = $meta['facebook'] || $meta['instagram'] || $meta['twitter'] || $meta['linkedin'] || $meta['youtube'] || $meta['telegram'] || $meta['tiktok'];
                    if ($has_social_sidebar): ?>
                        <div class="pt-social-links">
                            <?php 
                            $facebook_svg_url = defined('PT_PLUGIN_URL') ? PT_PLUGIN_URL . 'assets/facebook.svg' : plugin_dir_url(dirname(__FILE__)) . 'assets/facebook.svg';
                            $instagram_svg_url = defined('PT_PLUGIN_URL') ? PT_PLUGIN_URL . 'assets/instagram.svg' : plugin_dir_url(dirname(__FILE__)) . 'assets/instagram.svg';
                            ?>
                            <?php if ($meta['facebook']): ?>
                                <a href="<?php echo esc_url($meta['facebook']); ?>" target="_blank" rel="noopener" class="pt-social-link pt-social-facebook" title="Facebook">
                                    <img src="<?php echo esc_url($facebook_svg_url); ?>" alt="Facebook" width="20" height="20"/>
                                </a>
                            <?php endif; ?>
                            <?php if ($meta['instagram']): ?>
                                <a href="<?php echo esc_url($meta['instagram']); ?>" target="_blank" rel="noopener" class="pt-social-link pt-social-instagram" title="Instagram">
                                    <img src="<?php echo esc_url($instagram_svg_url); ?>" alt="Instagram" width="20" height="20"/>
                                </a>
                            <?php endif; ?>
                            <?php if ($meta['twitter']): ?>
                                <a href="<?php echo esc_url($meta['twitter']); ?>" target="_blank" rel="noopener" class="pt-social-link pt-social-twitter" title="Twitter">
                                    <span class="material-symbols-outlined">chat_bubble</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($meta['linkedin']): ?>
                                <a href="<?php echo esc_url($meta['linkedin']); ?>" target="_blank" rel="noopener" class="pt-social-link pt-social-linkedin" title="LinkedIn">
                                    <span class="material-symbols-outlined">business</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($meta['youtube']): ?>
                                <a href="<?php echo esc_url($meta['youtube']); ?>" target="_blank" rel="noopener" class="pt-social-link pt-social-youtube" title="YouTube">
                                    <span class="material-symbols-outlined">video_library</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($meta['telegram']): ?>
                                <a href="<?php echo esc_url($meta['telegram']); ?>" target="_blank" rel="noopener" class="pt-social-link pt-social-telegram" title="Telegram">
                                    <span class="material-symbols-outlined">forum</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($meta['tiktok']): ?>
                                <a href="<?php echo esc_url($meta['tiktok']); ?>" target="_blank" rel="noopener" class="pt-social-link pt-social-tiktok" title="TikTok">
                                    <span class="material-symbols-outlined">music_video</span>
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

<script>
(function() {
    // Gallery Main Image and Thumbnails
    const mainImg = document.getElementById('pt-gallery-main-img');
    const mainItem = document.querySelector('.pt-gallery-main-item');
    const thumbnails = document.querySelectorAll('.pt-gallery-thumb');
    const lightbox = document.getElementById('pt-gallery-lightbox');
    const lightboxImg = document.getElementById('pt-gallery-lightbox-img');
    const lightboxClose = document.querySelector('.pt-gallery-lightbox-close');
    const lightboxPrev = document.querySelector('.pt-gallery-lightbox-prev');
    const lightboxNext = document.querySelector('.pt-gallery-lightbox-next');
    
    let currentIndex = 0;
    let galleryImages = [];
    
    // Collect all gallery images (main + thumbnails)
    if (mainItem) {
        galleryImages.push({
            url: mainImg ? mainImg.src : '',
            full: mainItem.getAttribute('data-gallery-full'),
            alt: mainImg ? mainImg.alt : ''
        });
    }
    
    thumbnails.forEach(function(thumb) {
        galleryImages.push({
            url: thumb.getAttribute('data-gallery-url'),
            full: thumb.getAttribute('data-gallery-full'),
            alt: thumb.getAttribute('data-gallery-alt')
        });
    });
    
    // Thumbnail click - change main image
    thumbnails.forEach(function(thumb, index) {
        thumb.addEventListener('click', function() {
            // Remove active class from all thumbs
            thumbnails.forEach(function(t) {
                t.classList.remove('active');
            });
            
            // Add active class to clicked thumb
            thumb.classList.add('active');
            
            // Update main image
            const newUrl = thumb.getAttribute('data-gallery-url');
            const newFull = thumb.getAttribute('data-gallery-full');
            const newAlt = thumb.getAttribute('data-gallery-alt');
            
            if (mainImg) {
                mainImg.src = newUrl;
                mainImg.alt = newAlt;
            }
            
            if (mainItem) {
                mainItem.setAttribute('data-gallery-full', newFull);
            }
            
            currentIndex = index + 1;
        });
    });
    
    // Main image click - open lightbox
    if (mainItem) {
        mainItem.addEventListener('click', function() {
            currentIndex = 0;
            openLightbox();
        });
    }
    
    // Thumbnail double click or long press - open lightbox
    thumbnails.forEach(function(thumb, index) {
        thumb.addEventListener('dblclick', function() {
            currentIndex = index + 1;
            openLightbox();
        });
    });
    
    function openLightbox() {
        if (galleryImages.length === 0) return;
        lightboxImg.src = galleryImages[currentIndex].full;
        lightboxImg.alt = galleryImages[currentIndex].alt;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    function showNext() {
        currentIndex = (currentIndex + 1) % galleryImages.length;
        lightboxImg.src = galleryImages[currentIndex].full;
        lightboxImg.alt = galleryImages[currentIndex].alt;
    }
    
    function showPrev() {
        currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length;
        lightboxImg.src = galleryImages[currentIndex].full;
        lightboxImg.alt = galleryImages[currentIndex].alt;
    }
    
    // Event listeners
    if (lightboxClose) {
        lightboxClose.addEventListener('click', closeLightbox);
    }
    
    if (lightboxNext) {
        lightboxNext.addEventListener('click', showNext);
    }
    
    if (lightboxPrev) {
        lightboxPrev.addEventListener('click', showPrev);
    }
    
    // Close on background click
    if (lightbox) {
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (!lightbox.classList.contains('active')) return;
        
        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowRight') {
            showNext();
        } else if (e.key === 'ArrowLeft') {
            showPrev();
        }
    });
})();
</script>

<script>
(function() {
    const rotatingIcons = ['package_2', 'handyman', 'build', 'settings', 'engineering', 'construction', 'precision_manufacturing', 'home_repair_service'];
    const iconElements = document.querySelectorAll('.pt-rotating-icon');
    
    if (iconElements.length > 0) {
        let currentIndex = 0;
        
        setInterval(function() {
            iconElements.forEach(function(icon) {
                icon.textContent = rotatingIcons[currentIndex];
            });
            currentIndex = (currentIndex + 1) % rotatingIcons.length;
        }, 2000); // Rotate every 2 seconds
    }
})();
</script>
