<?php
/**
 * PDF Generator Class for Trader Profile
 * Uses mPDF library for PDF generation with Arabic/RTL support
 * 
 * @package ProfileTrader
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PT_PDF_Generator {
    
    private $mpdf = null;
    private $mpdf_available = false;
    private $last_error = '';
    
    public function __construct() {
        // Delay initialization to ensure WordPress is fully loaded
        if (did_action('plugins_loaded')) {
            $this->init_mpdf();
        } else {
            // If WordPress isn't fully loaded, try to initialize anyway
            // This handles cases where class is instantiated early
            $this->init_mpdf();
        }
    }
    
    /**
     * Initialize mPDF library
     */
    private function init_mpdf() {
        try {
            // Try to use constant from qr code generator plugin if available
            if (defined('TRADER_QR_PDF_PLUGIN_DIR')) {
                $qr_plugin_path = TRADER_QR_PDF_PLUGIN_DIR . 'vendor/autoload.php';
            } else {
                // Fallback: Try to load mPDF from qr code generator plugin
                // Use plugin_dir_path to get correct path (handles spaces properly)
                $qr_plugin_file = WP_PLUGIN_DIR . '/qr code generator for cpt/trader-qr-pdf.php';
                if (file_exists($qr_plugin_file)) {
                    $qr_plugin_dir = dirname($qr_plugin_file);
                } else {
                    // Fallback to direct path
                    $qr_plugin_dir = WP_PLUGIN_DIR . '/qr code generator for cpt';
                }
                $qr_plugin_path = $qr_plugin_dir . '/vendor/autoload.php';
            }
            
            // Check if autoloader exists
            if (!file_exists($qr_plugin_path)) {
                $this->last_error = 'Autoloader not found at ' . $qr_plugin_path;
                error_log('PT PDF Generator: ' . $this->last_error);
                $this->mpdf_available = false;
                return;
            }
            
            // Load autoloader if mPDF class doesn't exist
            if (!class_exists('Mpdf\Mpdf')) {
                require_once $qr_plugin_path;
            }
            
            // Check if mPDF class is available after loading autoloader
            if (!class_exists('Mpdf\Mpdf')) {
                $this->last_error = 'Mpdf\Mpdf class not found after loading autoloader from: ' . $qr_plugin_path;
                error_log('PT PDF Generator: ' . $this->last_error);
                error_log('PT PDF Generator: Autoloader file exists: ' . (file_exists($qr_plugin_path) ? 'Yes' : 'No'));
                $this->mpdf_available = false;
                return;
            }
            
            // Test if we can actually instantiate mPDF
            try {
                // Increase memory limit temporarily for PDF generation
                $original_memory_limit = ini_get('memory_limit');
                @ini_set('memory_limit', '256M');
                
                // Get a writable temp directory
                $upload_dir = wp_upload_dir();
                $temp_dir = $upload_dir['basedir'] . '/mpdf_temp';
                
                // Create temp directory if it doesn't exist
                if (!file_exists($temp_dir)) {
                    wp_mkdir_p($temp_dir);
                }
                
                // Fallback to system temp if WordPress uploads not writable
                if (!is_writable($temp_dir)) {
                    $temp_dir = sys_get_temp_dir();
                    if (!is_writable($temp_dir)) {
                        throw new Exception('No writable temporary directory available. Please check uploads directory permissions.');
                    }
                }
                
                // Initialize mPDF with Arabic support
                // Use minimal config first, then try to add font
                $mpdf_config = [
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'P',
                    'margin_left' => 15,
                    'margin_right' => 15,
                    'margin_top' => 16,
                    'margin_bottom' => 16,
                    'margin_header' => 9,
                    'margin_footer' => 9,
                    'tempDir' => $temp_dir,
                ];
                
                // Try without font first (most compatible)
                $this->mpdf = new \Mpdf\Mpdf($mpdf_config);
                
                // Try to set font, but don't fail if it's not available
                try {
                    $this->mpdf->SetFont('Cairo', '', 12);
                } catch (Exception $font_e) {
                    // Cairo font not available, use default
                    error_log('PT PDF Generator: Cairo font not available, using default font: ' . $font_e->getMessage());
                    try {
                        $this->mpdf->SetFont('DejaVuSans', '', 12);
                    } catch (Exception $font_e2) {
                        // Use system default
                        $this->mpdf->SetFont('', '', 12);
                    }
                }
                
                // Restore original memory limit
                @ini_set('memory_limit', $original_memory_limit);
            } catch (Exception $inst_e) {
                $error_msg = 'Failed to instantiate mPDF: ' . $inst_e->getMessage();
                $error_msg .= ' | File: ' . $inst_e->getFile() . ' | Line: ' . $inst_e->getLine();
                if ($inst_e->getPrevious()) {
                    $error_msg .= ' | Previous: ' . $inst_e->getPrevious()->getMessage();
                }
                $this->last_error = $error_msg;
                error_log('PT PDF Generator: ' . $error_msg);
                error_log('PT PDF Generator: Stack trace: ' . $inst_e->getTraceAsString());
                $this->mpdf_available = false;
                $this->mpdf = null;
                return;
            } catch (Error $inst_e) {
                $error_msg = 'Failed to instantiate mPDF (Error): ' . $inst_e->getMessage();
                $error_msg .= ' | File: ' . $inst_e->getFile() . ' | Line: ' . $inst_e->getLine();
                $this->last_error = $error_msg;
                error_log('PT PDF Generator: ' . $error_msg);
                error_log('PT PDF Generator: Stack trace: ' . $inst_e->getTraceAsString());
                $this->mpdf_available = false;
                $this->mpdf = null;
                return;
            }
            
            // Mark as available
            $this->mpdf_available = true;
            
        } catch (Exception $e) {
            error_log('PT PDF Generator: mPDF initialization failed - ' . $e->getMessage());
            error_log('PT PDF Generator: File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            $this->mpdf_available = false;
            $this->mpdf = null;
        } catch (Error $e) {
            error_log('PT PDF Generator: mPDF initialization error - ' . $e->getMessage());
            error_log('PT PDF Generator: File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            $this->mpdf_available = false;
            $this->mpdf = null;
        }
    }
    
    /**
     * Check if mPDF is available
     */
    public function is_available() {
        return $this->mpdf_available && $this->mpdf !== null;
    }
    
    /**
     * Get the last error message
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Static method to check if mPDF is available without instantiating
     */
    public static function check_availability() {
        // Try to use constant from qr code generator plugin if available
        if (defined('TRADER_QR_PDF_PLUGIN_DIR')) {
            $qr_plugin_path = TRADER_QR_PDF_PLUGIN_DIR . 'vendor/autoload.php';
        } else {
            $qr_plugin_file = WP_PLUGIN_DIR . '/qr code generator for cpt/trader-qr-pdf.php';
            if (file_exists($qr_plugin_file)) {
                $qr_plugin_dir = dirname($qr_plugin_file);
            } else {
                $qr_plugin_dir = WP_PLUGIN_DIR . '/qr code generator for cpt';
            }
            $qr_plugin_path = $qr_plugin_dir . '/vendor/autoload.php';
        }
        
        if (!file_exists($qr_plugin_path)) {
            return false;
        }
        
        if (!class_exists('Mpdf\Mpdf')) {
            require_once $qr_plugin_path;
        }
        
        return class_exists('Mpdf\Mpdf');
    }
    
    /**
     * Generate PDF for trader
     */
    public function generate_trader_pdf($trader_id) {
        if (!$this->is_available()) {
            wp_die('PDF generation library not available. Please ensure mPDF is installed.');
        }
        
        $trader_id = intval($trader_id);
        if (!$trader_id) {
            wp_die('Invalid trader ID');
        }
        
        $trader = get_post($trader_id);
        if (!$trader || $trader->post_type !== 'trader') {
            wp_die('Trader not found');
        }
        
        // Collect all required data
        $data = $this->collect_trader_data($trader_id);
        
        // Generate HTML content
        $html = $this->generate_html($data, $trader_id);
        
        // Write HTML to mPDF
        $this->mpdf->WriteHTML($html);
        
        // Generate filename
        $commercial_register = !empty($data['commercial_register']) ? sanitize_file_name($data['commercial_register']) : '';
        if ($commercial_register) {
            $filename = 'trader-' . $trader_id . '-' . $commercial_register . '.pdf';
        } else {
            $filename = 'trader-' . $trader_id . '-' . date('Y-m-d') . '.pdf';
        }
        
        // Output PDF for download
        $this->mpdf->Output($filename, 'D');
        exit;
    }
    
    /**
     * Collect trader data for PDF
     */
    private function collect_trader_data($trader_id) {
        // Prime meta cache
        update_meta_cache('post', [$trader_id]);
        
        // Helper to get meta with fallback
        $get_meta = function($keys, $default = '') use ($trader_id) {
            foreach ((array)$keys as $key) {
                $value = get_post_meta($trader_id, $key, true);
                if (!empty($value)) return $value;
            }
            return $default;
        };
        
        $trader = get_post($trader_id);
        
        // Core fields
        $data = [
            'name' => $trader->post_title,
            'content' => $trader->post_content,
        ];
        
        // Meta fields
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
            'facebook' => ['je_trader_facebook_page', 'facebook_page'],
            'instagram' => ['je_trader_instagram_page', 'instagram_page'],
            'twitter' => ['je_trader_twitter', 'twitter', 'twitter_page'],
            'linkedin' => ['je_trader_linkedin', 'linkedin', 'linkedin_page'],
            'youtube' => ['je_trader_youtube', 'youtube', 'youtube_page'],
            'telegram' => ['je_trader_telegram', 'telegram', 'telegram_page'],
            'tiktok' => ['je_trader_tiktok', 'tiktok', 'tiktok_page'],
            'logo' => ['logo'],
        ];
        
        foreach ($meta_keys as $key => $keys) {
            $data[$key] = $get_meta($keys);
        }
        
        // Logo
        $logo_id = $data['logo'] ?: get_post_thumbnail_id($trader_id);
        $data['logo_url'] = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
        $data['logo_base64'] = $this->image_to_base64($data['logo_url']);
        
        // Format date
        $data['member_since'] = $this->format_arabic_date($trader->post_date);
        
        // Format grant date
        if (!empty($data['date_of_grant'])) {
            $timestamp = strtotime($data['date_of_grant']);
            $data['formatted_grant_date'] = $timestamp ? date('d/m/Y', $timestamp) : $data['date_of_grant'];
        } else {
            $data['formatted_grant_date'] = '';
        }
        
        // Format score
        $data['score_display'] = '';
        if (!empty($data['score'])) {
            $score_value = $data['score'];
            if (is_numeric($score_value)) {
                $score_num = intval($score_value);
                $arabic_ordinals = ['', 'الأولى', 'الثانية', 'الثالثة', 'الرابعة', 'الخامسة', 'السادسة', 'السابعة', 'الثامنة', 'التاسعة', 'العاشرة'];
                if ($score_num >= 1 && $score_num <= 10) {
                    $data['score_display'] = $arabic_ordinals[$score_num];
                } else {
                    $data['score_display'] = $score_value;
                }
            } else {
                $data['score_display'] = $score_value;
            }
        }
        
        // Taxonomies
        $economic_activity_terms = wp_get_post_terms($trader_id, 'economic_activity', ['fields' => 'names']);
        $data['economic_activity'] = !empty($economic_activity_terms) && !is_wp_error($economic_activity_terms) ? $economic_activity_terms[0] : '';
        
        $sector_terms = wp_get_post_terms($trader_id, 'sector', ['fields' => 'names']);
        $data['sectors'] = !empty($sector_terms) && !is_wp_error($sector_terms) ? $sector_terms : [];
        
        // Activities
        $activity_terms = wp_get_post_terms($trader_id, 'activity', ['fields' => 'all']);
        $data['activities'] = !empty($activity_terms) && !is_wp_error($activity_terms) ? $activity_terms : [];
        
        // Services
        $services_meta = get_post_meta($trader_id, 'je_trader_services_services_name', true) ?: get_post_meta($trader_id, 'services', true);
        $services = [];
        if (!empty($services_meta)) {
            if (is_array($services_meta)) {
                $services = $services_meta;
            } elseif (is_string($services_meta)) {
                $decoded = json_decode($services_meta, true);
                $services = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $services_meta)));
            }
        }
        $data['services'] = $services;
        
        // QR Code
        $data['qr_code_base64'] = $this->get_qr_code_base64($trader_id);
        
        return $data;
    }
    
    /**
     * Convert image URL to base64
     */
    private function image_to_base64($image_url) {
        if (empty($image_url)) {
            return '';
        }
        
        try {
            // Check if it's an attachment ID (numeric)
            if (is_numeric($image_url)) {
                $attachment_id = intval($image_url);
                $image_path = get_attached_file($attachment_id);
                if ($image_path && file_exists($image_path)) {
                    $image_data = file_get_contents($image_path);
                    $image_info = getimagesize($image_path);
                    $mime_type = $image_info ? $image_info['mime'] : 'image/png';
                    return 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
                }
            }
            
            // Check if it's a URL
            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                // Check if it's from the same site (uploads directory)
                $upload_dir = wp_upload_dir();
                if (strpos($image_url, $upload_dir['baseurl']) !== false) {
                    // Local file
                    $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
                    if (file_exists($image_path)) {
                        $image_data = file_get_contents($image_path);
                        $image_info = getimagesize($image_path);
                        $mime_type = $image_info ? $image_info['mime'] : 'image/png';
                        return 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
                    }
                } else {
                    // External URL - try to fetch (may fail due to CORS/security)
                    $image_data = @file_get_contents($image_url);
                    if ($image_data !== false) {
                        $image_info = @getimagesizefromstring($image_data);
                        $mime_type = $image_info ? $image_info['mime'] : 'image/png';
                        return 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
                    }
                }
            } else {
                // Assume it's a file path
                if (file_exists($image_url)) {
                    $image_data = file_get_contents($image_url);
                    $image_info = getimagesize($image_url);
                    $mime_type = $image_info ? $image_info['mime'] : 'image/png';
                    return 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
                }
            }
        } catch (Exception $e) {
            error_log('PT PDF: Image conversion error - ' . $e->getMessage());
        }
        
        return '';
    }
    
    /**
     * Get QR code as base64
     */
    private function get_qr_code_base64($trader_id) {
        // Try to get QR code from meta
        $qr_attachment_id = get_post_meta($trader_id, '_trader_qr_attachment_id', true);
        if ($qr_attachment_id) {
            $qr_file_path = get_attached_file($qr_attachment_id);
            if ($qr_file_path && file_exists($qr_file_path)) {
                return $this->image_to_base64(wp_get_attachment_url($qr_attachment_id));
            }
        }
        
        return '';
    }
    
    /**
     * Format date in Arabic
     */
    private function format_arabic_date($date_string) {
        if (empty($date_string)) return '';
        $months_arabic = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $timestamp = strtotime($date_string);
        if (!$timestamp) return $date_string;
        return $months_arabic[date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
    }
    
    /**
     * Generate HTML content for PDF
     */
    private function generate_html($data, $trader_id) {
        $html = '<!DOCTYPE html>';
        $html .= '<html dir="rtl" lang="ar">';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>' . esc_html($data['name']) . '</title>';
        $html .= '<style>';
        $html .= '* { margin: 0; padding: 0; box-sizing: border-box; }';
        $html .= 'body { font-family: "Cairo", Arial, sans-serif; font-size: 11px; line-height: 1.6; color: #1a1a1a; direction: rtl; }';
        $html .= '.pdf-container { padding: 10px; }';
        $html .= '.pdf-header { border-bottom: 3px solid #0A4E45; padding-bottom: 15px; margin-bottom: 15px; }';
        $html .= '.header-top { display: flex; align-items: center; gap: 15px; margin-bottom: 10px; }';
        $html .= '.logo { width: 60px; height: 60px; object-fit: contain; border: 1px solid #ddd; border-radius: 4px; }';
        $html .= '.header-info { flex: 1; }';
        $html .= '.trader-name { font-size: 20px; font-weight: 700; color: #0A4E45; margin-bottom: 5px; }';
        $html .= '.badges { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }';
        $html .= '.badge { background: #EDEAE0; color: #0A4E45; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 600; }';
        $html .= '.sectors { margin-top: 5px; }';
        $html .= '.sector-tag { display: inline-block; background: #f0f0f0; padding: 2px 8px; border-radius: 3px; font-size: 9px; margin-left: 5px; }';
        $html .= '.member-since { color: #666; font-size: 10px; margin-top: 5px; }';
        $html .= '.section { margin-bottom: 15px; }';
        $html .= '.section-title { font-size: 14px; font-weight: 700; color: #0A4E45; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 2px solid #B9A779; }';
        $html .= '.activities-list { list-style: none; }';
        $html .= '.activity-item { margin-bottom: 6px; padding-right: 15px; position: relative; }';
        $html .= '.activity-item:before { content: "✓"; position: absolute; right: 0; color: #0A4E45; font-weight: bold; }';
        $html .= '.services-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }';
        $html .= '.service-item { background: #f9f9f9; padding: 6px 10px; border-radius: 4px; border-right: 3px solid #0A4E45; font-size: 10px; }';
        $html .= '.info-table { width: 100%; border-collapse: collapse; margin-top: 8px; }';
        $html .= '.info-table td { padding: 6px 10px; border-bottom: 1px solid #e0e0e0; }';
        $html .= '.info-label { font-weight: 700; color: #0A4E45; width: 40%; }';
        $html .= '.info-value { color: #333; }';
        $html .= '.contact-list { list-style: none; }';
        $html .= '.contact-item { margin-bottom: 6px; padding-right: 15px; }';
        $html .= '.qr-section { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #0A4E45; }';
        $html .= '.qr-code { max-width: 120px; height: auto; margin: 0 auto; }';
        $html .= '.qr-label { font-size: 10px; color: #666; margin-top: 5px; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="pdf-container">';
        
        // Header Section
        $html .= '<div class="pdf-header">';
        $html .= '<div class="header-top">';
        if (!empty($data['logo_base64'])) {
            $html .= '<img src="' . esc_attr($data['logo_base64']) . '" alt="Logo" class="logo" />';
        }
        $html .= '<div class="header-info">';
        $html .= '<div class="trader-name">' . esc_html($data['name']) . '</div>';
        
        if (!empty($data['economic_activity']) || !empty($data['score_display'])) {
            $html .= '<div class="badges">';
            if (!empty($data['economic_activity'])) {
                $html .= '<span class="badge">النشاط الاقتصادي: ' . esc_html($data['economic_activity']) . '</span>';
            }
            if (!empty($data['score_display'])) {
                $html .= '<span class="badge">درجة السجل: ' . esc_html($data['score_display']) . '</span>';
            }
            $html .= '</div>';
        }
        
        if (!empty($data['sectors'])) {
            $html .= '<div class="sectors">';
            foreach (array_slice($data['sectors'], 0, 5) as $sector) {
                $html .= '<span class="sector-tag">' . esc_html($sector) . '</span>';
            }
            $html .= '</div>';
        }
        
        if (!empty($data['member_since'])) {
            $html .= '<div class="member-since">عضو منذ ' . esc_html($data['member_since']) . '</div>';
        }
        
        $html .= '</div>'; // header-info
        $html .= '</div>'; // header-top
        $html .= '</div>'; // pdf-header
        
        // Activities Section
        if (!empty($data['activities'])) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">مجالات النشاط</div>';
            $html .= '<ul class="activities-list">';
            foreach ($data['activities'] as $activity) {
                $html .= '<li class="activity-item">';
                $html .= '<strong>' . esc_html($activity->name) . '</strong>';
                if (!empty($activity->description)) {
                    $html .= ' - ' . esc_html($activity->description);
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        // Services Section
        if (!empty($data['services'])) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">الخدمات والمنتجات</div>';
            $html .= '<div class="services-grid">';
            foreach ($data['services'] as $service) {
                $service_name = is_array($service) ? ($service['name'] ?? $service['services_name'] ?? '') : $service;
                if (!empty($service_name)) {
                    $html .= '<div class="service-item">' . esc_html($service_name) . '</div>';
                }
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Business Info Section
        $html .= '<div class="section">';
        $html .= '<div class="section-title">تفاصيل النشاط التجاري</div>';
        $html .= '<table class="info-table">';
        
        if (!empty($data['commercial_register'])) {
            $html .= '<tr><td class="info-label">السجل التجاري</td><td class="info-value">' . esc_html($data['commercial_register']) . '</td></tr>';
        }
        
        if (!empty($data['score'])) {
            $html .= '<tr><td class="info-label">درجة السجل</td><td class="info-value">' . esc_html($data['score']) . '</td></tr>';
        }
        
        if (!empty($data['commercial_industry'])) {
            $html .= '<tr><td class="info-label">السجل الصناعي</td><td class="info-value">' . esc_html($data['commercial_industry']) . '</td></tr>';
        }
        
        if (!empty($data['company_type'])) {
            $html .= '<tr><td class="info-label">نوع الشركة</td><td class="info-value">' . esc_html($data['company_type']) . '</td></tr>';
        }
        
        if (!empty($data['formatted_grant_date'])) {
            $html .= '<tr><td class="info-label">تاريخ منح السجل</td><td class="info-value">' . esc_html($data['formatted_grant_date']) . '</td></tr>';
        }
        
        $html .= '</table>';
        $html .= '</div>';
        
        // Contact Section
        $html .= '<div class="section">';
        $html .= '<div class="section-title">معلومات الاتصال</div>';
        $html .= '<ul class="contact-list">';
        
        if (!empty($data['phone'])) {
            $html .= '<li class="contact-item"><strong>الهاتف:</strong> ' . esc_html($data['phone']) . '</li>';
        }
        
        if (!empty($data['email'])) {
            $html .= '<li class="contact-item"><strong>البريد الإلكتروني:</strong> ' . esc_html($data['email']) . '</li>';
        }
        
        if (!empty($data['website'])) {
            $html .= '<li class="contact-item"><strong>الموقع الإلكتروني:</strong> ' . esc_html($data['website']) . '</li>';
        }
        
        if (!empty($data['whatsapp'])) {
            $html .= '<li class="contact-item"><strong>واتساب:</strong> ' . esc_html($data['whatsapp']) . '</li>';
        }
        
        // Social media
        $social_links = [];
        if (!empty($data['facebook'])) $social_links[] = 'فيسبوك';
        if (!empty($data['instagram'])) $social_links[] = 'إنستغرام';
        if (!empty($data['twitter'])) $social_links[] = 'تويتر';
        if (!empty($data['linkedin'])) $social_links[] = 'لينكد إن';
        if (!empty($data['youtube'])) $social_links[] = 'يوتيوب';
        if (!empty($data['telegram'])) $social_links[] = 'تيليجرام';
        if (!empty($data['tiktok'])) $social_links[] = 'تيك توك';
        
        if (!empty($social_links)) {
            $html .= '<li class="contact-item"><strong>وسائل التواصل الاجتماعي:</strong> ' . esc_html(implode('، ', $social_links)) . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        // QR Code Section
        if (!empty($data['qr_code_base64'])) {
            $html .= '<div class="qr-section">';
            $html .= '<img src="' . esc_attr($data['qr_code_base64']) . '" alt="QR Code" class="qr-code" />';
            $html .= '<div class="qr-label">مسح الرمز السريع للوصول إلى الملف الشخصي</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // pdf-container
        $html .= '</body>';
        $html .= '</html>';
        
        return $html;
    }
}
