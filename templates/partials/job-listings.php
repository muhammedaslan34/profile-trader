<?php
/**
 * Job Listings Partial Template
 * 
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = Profile_Trader::get_instance();
$jobs = $plugin->get_user_jobs();
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

// Filter jobs
if ($filter_status !== 'all') {
    $jobs = array_filter($jobs, function($job) use ($filter_status) {
        return $job->post_status === $filter_status;
    });
}

// Job type labels - use plugin helper function
$job_type_labels = $plugin->get_job_type_labels();
?>

<!-- Filters -->
<div class="pt-filters">
    <div class="pt-filter-tabs">
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'jobs', 'status' => 'all'], get_permalink())); ?>" 
           class="pt-filter-tab <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
            الكل
        </a>
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'jobs', 'status' => 'publish'], get_permalink())); ?>" 
           class="pt-filter-tab <?php echo $filter_status === 'publish' ? 'active' : ''; ?>">
            منشور
        </a>
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'jobs', 'status' => 'pending'], get_permalink())); ?>" 
           class="pt-filter-tab <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
            قيد المراجعة
        </a>
        <a href="<?php echo esc_url(add_query_arg(['tab' => 'jobs', 'status' => 'draft'], get_permalink())); ?>" 
           class="pt-filter-tab <?php echo $filter_status === 'draft' ? 'active' : ''; ?>">
            مسودة
        </a>
    </div>
</div>

<?php if (empty($jobs)): ?>
<div class="pt-empty-state">
    <div class="pt-empty-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
        </svg>
    </div>
    <h3>لا توجد وظائف</h3>
    <p>
        <?php if ($filter_status !== 'all'): ?>
            لا توجد وظائف بهذه الحالة
        <?php else: ?>
            ابدأ بإضافة وظيفتك الأولى
        <?php endif; ?>
    </p>
    <a href="<?php echo esc_url(add_query_arg('tab', 'add-job', get_permalink())); ?>" class="pt-btn pt-btn-primary">
        <svg class="pt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        إضافة وظيفة جديدة
    </a>
</div>
<?php else: ?>

<!-- Jobs Grid -->
<div class="pt-listings-grid">
    <?php foreach ($jobs as $job): 
        $position = get_post_meta($job->ID, 'position', true);
        $salary_range = get_post_meta($job->ID, 'salary_range', true);
        $expirence = get_post_meta($job->ID, 'expirence', true);
        $job_type = get_post_meta($job->ID, 'job_type', true);
        $contact_number = get_post_meta($job->ID, 'contact_number', true);
    ?>
    <div class="pt-job-item">

        <div class="pt-job-item-header">
            <div class="pt-job-item-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>

            <div class="pt-job-item-status">
                <?php
                $status_class = '';
                $status_text = '';
                switch ($job->post_status) {
                    case 'publish':
                        $status_class = 'pt-status-published';
                        $status_text = 'منشور';
                        break;
                    case 'pending':
                        $status_class = 'pt-status-pending';
                        $status_text = 'قيد المراجعة';
                        break;
                    case 'draft':
                        $status_class = 'pt-status-draft';
                        $status_text = 'مسودة';
                        break;
                }
                ?>
                <span class="pt-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
        </div>

        <div class="pt-job-item-content">
            <h3 class="pt-job-item-title"><?php echo esc_html($job->post_title); ?></h3>

            <?php if ($position): ?>
            <p class="pt-job-item-position"><?php echo esc_html($position); ?></p>
            <?php endif; ?>

            <?php if ($job_type && isset($job_type_labels[$job_type])): ?>
            <div class="pt-job-item-info">
                <span class="pt-job-item-type"><?php echo esc_html($job_type_labels[$job_type]); ?></span>
            </div>
            <?php endif; ?>

            <div class="pt-job-item-info">
                <?php if ($salary_range): 
                    // Clean and format salary range - remove duplicate dollar signs
                    $salary_clean = trim($salary_range);
                    // Remove all dollar signs (including escaped ones)
                    $salary_clean = preg_replace('/\$+/u', '', $salary_clean);
                    // Remove dollar signs with any whitespace around them
                    $salary_clean = preg_replace('/\s*\$+\s*/u', ' ', $salary_clean);
                    // Clean up multiple spaces
                    $salary_clean = preg_replace('/\s+/u', ' ', $salary_clean);
                    $salary_clean = trim($salary_clean);
                    // Remove any trailing dollar signs that might remain
                    $salary_clean = preg_replace('/\$+\s*$/u', '', $salary_clean);
                    $salary_clean = trim($salary_clean);
                    // Add a single dollar sign at the end if there are numbers
                    if (preg_match('/\d/u', $salary_clean) && !empty($salary_clean)) {
                        $salary_clean = $salary_clean . '$';
                    }
                ?>
                <span class="pt-job-item-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <?php echo esc_html($salary_clean); ?>
                </span>
                <?php endif; ?>

                <?php if ($expirence): ?>
                <span class="pt-job-item-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <?php echo esc_html($expirence); ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if ($contact_number): ?>
            <div class="pt-job-item-contact">
                <span class="pt-job-item-phone">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <?php echo esc_html($contact_number); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <div class="pt-job-item-footer">
            <span class="pt-job-item-date">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <?php echo get_the_date('Y/m/d', $job); ?>
            </span>

            <div class="pt-job-item-actions">
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'edit-job', 'job_id' => $job->ID], get_permalink())); ?>"
                   class="pt-btn pt-btn-sm pt-btn-outline">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    تعديل
                </a>

                <?php if ($job->post_status === 'publish'): ?>
                <a href="<?php echo get_permalink($job->ID); ?>"
                   class="pt-btn pt-btn-sm pt-btn-primary" target="_blank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    عرض
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

