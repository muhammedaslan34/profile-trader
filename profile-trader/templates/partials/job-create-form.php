<?php
/**
 * Job Create Form Partial Template
 *
 * @package Profile_Trader
 */

if (!defined('ABSPATH')) {
    exit;
}

$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$job = $job_id ? get_post($job_id) : null;
$is_edit = $job !== null;

// Get current values
$values = [];
if ($is_edit) {
    $values['position'] = get_post_meta($job_id, 'position', true);
    $values['salary_range'] = get_post_meta($job_id, 'salary_range', true);
    $values['expirence'] = get_post_meta($job_id, 'expirence', true);
    $values['job_type'] = get_post_meta($job_id, 'job_type', true);
    $values['requirements'] = get_post_meta($job_id, 'requirements', true);
    $values['advantages'] = get_post_meta($job_id, 'advantages', true);
    $values['contact_number'] = get_post_meta($job_id, 'contact_number', true);
}

// Get taxonomy terms
$job_categories = get_terms(['taxonomy' => 'job_category', 'hide_empty' => false]);

// Get selected job category term
$selected_job_category = $is_edit ? wp_get_post_terms($job_id, 'job_category', ['fields' => 'ids']) : [];
$selected_job_category_id = !empty($selected_job_category) ? $selected_job_category[0] : '';

// Job type options - use plugin helper function
$plugin = Profile_Trader::get_instance();
$job_type_options = $plugin->get_job_type_labels();
?>

<form id="pt-job-form" class="pt-form" enctype="multipart/form-data">
    <input type="hidden" name="post_id" value="<?php echo $job_id; ?>">
    <input type="hidden" name="post_type" value="job">
    <?php wp_nonce_field('pt_job_nonce', 'pt_job_form_nonce'); ?>

    <!-- Job Information -->
    <div class="pt-form-section">
        <div class="pt-section-header">
            <h2 class="pt-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
                معلومات الوظيفة
            </h2>
        </div>

        <div class="pt-form-grid">
            <!-- Job Title -->
            <div class="pt-form-group pt-col-full">
                <label for="post_title" class="pt-label">
                    عنوان الوظيفة
                    <span class="pt-required">*</span>
                </label>
                <input type="text"
                       id="post_title"
                       name="post_title"
                       class="pt-input"
                       value="<?php echo $is_edit ? esc_attr($job->post_title) : ''; ?>"
                       placeholder="مثال: مطور واجهات أمامية"
                       required>
            </div>

            <!-- Position -->
            <div class="pt-form-group pt-col-third">
                <label for="position" class="pt-label">
                    المسمى الوظيفي
                    <span class="pt-required">*</span>
                </label>
                <input type="text"
                       id="position"
                       name="position"
                       class="pt-input"
                       value="<?php echo esc_attr($values['position'] ?? ''); ?>"
                       placeholder="مثال: Senior Developer"
                       required>
            </div>

            <!-- Salary Range -->
            <div class="pt-form-group pt-col-third">
                <label for="salary_range" class="pt-label">
                    الراتب
                </label>
                <input type="text"
                       id="salary_range"
                       name="salary_range"
                       class="pt-input"
                       value="<?php echo esc_attr($values['salary_range'] ?? ''); ?>"
                       placeholder="مثال: 300000 - 500000 ليرة سوري">
            </div>

            <!-- Experience -->
            <div class="pt-form-group pt-col-third">
                <label for="expirence" class="pt-label">
                    الخبرة
                </label>
                <input type="text"
                       id="expirence"
                       name="expirence"
                       class="pt-input"
                       value="<?php echo esc_attr($values['expirence'] ?? ''); ?>"
                       placeholder="مثال: 2-5 سنوات">
            </div>

            <!-- Job Category -->
            <div class="pt-form-group pt-col-half">
                <label for="tax_job_category" class="pt-label">
                    فئة الوظيفة
                </label>
                <div class="pt-select-wrapper">
                    <span class="pt-select-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <select id="tax_job_category" 
                            name="tax_job_category" 
                            class="pt-select">
                        <option value="">اختر فئة الوظيفة</option>
                        <?php if (!is_wp_error($job_categories)): foreach ($job_categories as $term): ?>
                        <option value="<?php echo $term->term_id; ?>" 
                                <?php echo $selected_job_category_id == $term->term_id ? 'selected' : ''; ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>

            <!-- Job Type -->
            <div class="pt-form-group pt-col-half">
                <label class="pt-label">
                    نوع الوظيفة
                    <span class="pt-required">*</span>
                </label>
                <div class="pt-radio-group pt-radio-inline">
                    <?php foreach ($job_type_options as $key => $label): ?>
                    <label class="pt-radio-item">
                        <input type="radio"
                               name="job_type"
                               value="<?php echo esc_attr($key); ?>"
                               <?php
                               // For edit mode: check saved value; For new job: default to 'دوام كامل' (full time)
                               $is_checked = $is_edit
                                   ? ($values['job_type'] === $key)
                                   : ($key === 'دوام كامل');
                               checked($is_checked, true);
                               ?>
                               required>
                        <span class="pt-radio-label"><?php echo esc_html($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Job Description -->
            <div class="pt-form-group pt-col-full">
                <label for="post_content" class="pt-label">
                    وصف الوظيفة
                    <span class="pt-required">*</span>
                </label>
                <textarea id="post_content"
                          name="post_content"
                          class="pt-textarea pt-editor"
                          rows="6"
                          required><?php echo $is_edit ? esc_textarea($job->post_content) : ''; ?></textarea>
                <span class="pt-hint">قدم وصفاً تفصيلياً عن الوظيفة والمهام المطلوبة</span>
            </div>

            <!-- Requirements -->
            <div class="pt-form-group pt-col-half">
                <label for="requirements" class="pt-label">
                    المتطلبات
                </label>
                <textarea id="requirements"
                          name="requirements"
                          class="pt-textarea"
                          rows="8"><?php echo esc_textarea($values['requirements'] ?? ''); ?></textarea>
                <span class="pt-hint">المؤهلات والخبرات المطلوبة للوظيفة</span>
            </div>

            <!-- Advantages -->
            <div class="pt-form-group pt-col-half">
                <label for="advantages" class="pt-label">
                    المزايا
                </label>
                <textarea id="advantages"
                          name="advantages"
                          class="pt-textarea"
                          rows="8"><?php echo esc_textarea($values['advantages'] ?? ''); ?></textarea>
                <span class="pt-hint">المزايا والحوافز التي تقدمها الشركة</span>
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
            <!-- Contact Number -->
            <div class="pt-form-group pt-col-full">
                <label for="contact_number" class="pt-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    رقم التواصل
                    <span class="pt-required">*</span>
                </label>
                <input type="tel"
                       id="contact_number"
                       name="contact_number"
                       class="pt-input pt-phone-input"
                       pattern="[0-9]*"
                       inputmode="numeric"
                       value="<?php echo esc_attr($values['contact_number'] ?? ''); ?>"
                       placeholder="05xxxxxxxx"
                       required>
                <span class="pt-hint">رقم الهاتف للتواصل بخصوص الوظيفة</span>
            </div>
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
            <?php echo $is_edit ? 'حفظ التغييرات' : 'نشر الوظيفة'; ?>
        </button>

        <a href="<?php echo esc_url(add_query_arg('tab', 'jobs', get_permalink())); ?>" class="pt-btn pt-btn-outline pt-btn-lg">
            إلغاء
        </a>
    </div>

    <div class="pt-form-message" id="pt-job-form-message"></div>
</form>

<style>
/* Grid system for three columns */
@media (max-width: 767px) {
    .pt-col-third {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

@media (min-width: 768px) {
    .pt-form-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .pt-col-third {
        flex: 0 0 calc(33.333% - 1rem);
        max-width: calc(33.333% - 1rem);
    }

    .pt-col-half {
        flex: 0 0 calc(50% - 0.75rem);
        max-width: calc(50% - 0.75rem);
    }

    .pt-col-full {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

/* Success Modal */
.pt-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    animation: fadeIn 0.3s ease;
}

.pt-modal {
    background: #fff;
    border-radius: 16px;
    padding: 2.5rem;
    text-align: center;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
}

.pt-modal-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #4CAF50, #45a049);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: #fff;
}

.pt-modal-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #333;
    margin: 0 0 0.75rem;
    font-family: 'Cairo', sans-serif;
}

.pt-modal-message {
    font-size: 1rem;
    color: #666;
    margin: 0 0 1.5rem;
    line-height: 1.6;
    font-family: 'Cairo', sans-serif;
}

.pt-modal .pt-btn {
    min-width: 150px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { 
        opacity: 0;
        transform: translateY(20px);
    }
    to { 
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Visual editors disabled - using plain textareas for job form
    // If you want visual editors (with HTML formatting), uncomment the code below:
    /*
    if (typeof wp !== 'undefined' && wp.editor) {
        if ($('#requirements').length) {
            wp.editor.initialize('requirements', {
                tinymce: {
                    wpautop: true,
                    plugins: 'lists,paste,tabfocus,wplink,wordpress',
                    toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink'
                },
                quicktags: true
            });
        }

        if ($('#advantages').length) {
            wp.editor.initialize('advantages', {
                tinymce: {
                    wpautop: true,
                    plugins: 'lists,paste,tabfocus,wplink,wordpress',
                    toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink'
                },
                quicktags: true
            });
        }

        if ($('#post_content').length) {
            wp.editor.initialize('post_content', {
                tinymce: {
                    wpautop: true,
                    plugins: 'lists,paste,tabfocus,wplink,wordpress',
                    toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink'
                },
                quicktags: true
            });
        }
    }
    */

    // Handle form submission
    $('#pt-job-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var $message = $('#pt-job-form-message');

        // Clear previous messages
        $message.html('').hide();

        // CLIENT-SIDE VALIDATION: Validate job_type is selected
        if ($form.find('input[name="job_type"]:checked').length === 0) {
            $message.html('<div class="pt-alert pt-alert-error">يرجى اختيار نوع الوظيفة</div>').show();
            return false;
        }

        // Check if ptAjax is available
        if (typeof ptAjax === 'undefined') {
            $message.html('<div class="pt-alert pt-alert-error">خطأ: لم يتم تحميل البرنامج النصي بشكل صحيح</div>');
            return;
        }

        // Disable submit button
        $submitBtn.prop('disabled', true).addClass('pt-loading');

        // Get form data
        var formData = new FormData(this);
        formData.append('action', 'pt_save_job');
        formData.append('nonce', ptAjax.nonce);

        // DEBUG: Log form submission
        console.log('=== Job Form Debug ===');
        console.log('Selected job_type:', $form.find('input[name="job_type"]:checked').val());
        console.log('FormData entries:');
        for (var pair of formData.entries()) {
            console.log('  ' + pair[0] + ':', pair[1]);
        }

        // Get editor content - DISABLED since visual editors are not initialized
        // If you re-enable visual editors above, uncomment this code:
        /*
        if (typeof wp !== 'undefined' && wp.editor) {
            if (wp.editor.getContent('requirements')) {
                formData.set('requirements', wp.editor.getContent('requirements'));
            }
            if (wp.editor.getContent('advantages')) {
                formData.set('advantages', wp.editor.getContent('advantages'));
            }
            if (wp.editor.getContent('post_content')) {
                formData.set('post_content', wp.editor.getContent('post_content'));
            }
        }
        */

        // CRITICAL FIX: Explicitly ensure job_type is in FormData
        var $jobTypeRadio = $form.find('input[name="job_type"]:checked');
        if ($jobTypeRadio.length > 0) {
            var jobTypeValue = $jobTypeRadio.val();
            formData.set('job_type', jobTypeValue);
            console.log('✓ job_type set:', jobTypeValue);
        } else {
            console.error('✗ No job_type selected!');
            $message.html('<div class="pt-alert pt-alert-error">يرجى اختيار نوع الوظيفة</div>').show();
            $submitBtn.prop('disabled', false).removeClass('pt-loading');
            return false;
        }

        $.ajax({
            url: ptAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('✓ AJAX Success:', response);
                if (response.success) {
                    // Show success modal
                    var modalHtml = '<div class="pt-modal-overlay" id="pt-success-modal">' +
                        '<div class="pt-modal">' +
                            '<div class="pt-modal-icon">✓</div>' +
                            '<h3 class="pt-modal-title">تم نشر الوظيفة بنجاح!</h3>' +
                            '<p class="pt-modal-message">سيتم مراجعة الوظيفة من قبل الإدارة للموافقة عليها.</p>' +
                            '<button type="button" class="pt-btn pt-btn-primary" id="pt-modal-close-btn">حسناً</button>' +
                        '</div>' +
                    '</div>';
                    $('body').append(modalHtml);

                    // Close modal and reset form when clicking OK
                    $('#pt-modal-close-btn').on('click', function() {
                        $('#pt-success-modal').fadeOut(200, function() {
                            $(this).remove();
                        });
                        // Reset the form for a new job
                        $form[0].reset();
                    });

                    // Also close on overlay click
                    $('#pt-success-modal').on('click', function(e) {
                        if (e.target === this) {
                            $(this).fadeOut(200, function() {
                                $(this).remove();
                            });
                            $form[0].reset();
                        }
                    });

                    $submitBtn.prop('disabled', false).removeClass('pt-loading');
                } else {
                    console.error('✗ Save failed:', response.data.message);
                    $message.html('<div class="pt-alert pt-alert-error">' + response.data.message + '</div>').show();
                    $submitBtn.prop('disabled', false).removeClass('pt-loading');
                }
            },
            error: function(xhr, status, error) {
                console.error('✗ AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                $message.html('<div class="pt-alert pt-alert-error">حدث خطأ أثناء حفظ الوظيفة. يرجى المحاولة مرة أخرى.</div>').show();
                $submitBtn.prop('disabled', false).removeClass('pt-loading');
            }
        });
    });
});
</script>
