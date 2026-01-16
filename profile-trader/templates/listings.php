<?php
/**
 * Standalone Listings Template (for shortcode use)
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = Profile_Trader::get_instance();
$listings = $plugin->get_user_listings();
?>

<div class="pt-standalone-listings" dir="rtl">
    <div class="pt-listings-header">
        <h2>جميع الدلائل</h2>
        
    </div>
    
    <?php include PT_PLUGIN_DIR . 'templates/partials/listings-list.php'; ?>
</div>

