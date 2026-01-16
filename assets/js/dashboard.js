/**
 * Profile Trader Dashboard JavaScript
 * Handles form submissions, media uploads, phone inputs, and dynamic UI
 */

(function($) {
    'use strict';

    // Main Dashboard Object
    const PTDashboard = {
        
        phoneInputs: [],
        
        /**
         * Initialize all functionality
         */
        init: function() {
            this.bindEvents();
            this.initCharCounter();
            this.initRepeaters();
            this.initMediaUploads();
            this.initBranchToggle();
            this.initPasswordToggle();
            this.initPhoneInputs();
            this.initNumberInputs();
            this.initMobileDrawer();
            this.initSidebarToggle();
            this.initAvatarUpload();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            // Form submission
            $(document).on('submit', '#pt-listing-form', this.handleFormSubmit.bind(this));
            $(document).on('submit', '#pt-company-form', this.handleCompanyFormSubmit.bind(this));
            
            // Character counter for textarea
            $(document).on('input', '#short_desc', this.updateCharCount);
            
            // Repeater add/remove
            $(document).on('click', '.pt-add-repeater', this.addRepeaterItem.bind(this));
            $(document).on('click', '.pt-remove-repeater', this.removeRepeaterItem.bind(this));
            
            // Branch toggle
            $(document).on('click', '.pt-branch-header', this.toggleBranch);
            $(document).on('input', '.pt-branch-name', this.updateBranchTitle);
            
            // Media uploads
            $(document).on('click', '.pt-upload-btn', this.openMediaUploader.bind(this));
            $(document).on('click', '.pt-gallery-btn', this.openGalleryUploader.bind(this));
            $(document).on('click', '.pt-remove-media', this.removeMedia);
            $(document).on('click', '.pt-remove-gallery-item', this.removeGalleryItem);
            $(document).on('click', '.pt-edit-media', this.editLogo.bind(this));

            // File input change events
            $(document).on('change', '.pt-file-input', this.handleFileSelect.bind(this));

            // Drag and drop events
            this.setupDragAndDrop();
            
            // Password toggle
            $(document).on('click', '.pt-toggle-password', this.togglePassword);

            // Mobile drawer toggle
            $(document).on('click', '.pt-hamburger-btn', this.openMobileDrawer.bind(this));
            $(document).on('click', '.pt-drawer-close', this.closeMobileDrawer.bind(this));
            $(document).on('click', '.pt-drawer-overlay', this.closeMobileDrawer.bind(this));

            // Desktop/laptop sidebar toggle
            $(document).on('click', '.pt-sidebar-toggle', this.toggleSidebar.bind(this));

            // Crop modal events
            $(document).on('click', '.pt-crop-modal-close', this.closeCropModal.bind(this));
            $(document).on('click', '.pt-crop-modal-overlay', this.closeCropModal.bind(this));
            $(document).on('click', '#pt-crop-apply', this.applyCrop.bind(this));
            $(document).on('click', '.pt-crop-apply', this.applyCrop.bind(this)); // Alternative selector
            $(document).on('click', '#pt-crop-cancel', this.closeCropModal.bind(this));
            $(document).on('click', '.pt-crop-cancel', this.closeCropModal.bind(this)); // Alternative selector
            $(document).on('input', '#pt-zoom-slider', this.handleZoomSlider.bind(this));
            $(document).on('click', '.pt-zoom-btn[data-zoom="-0.1"]', this.handleZoomOut.bind(this));
            $(document).on('click', '.pt-zoom-btn[data-zoom="0.1"]', this.handleZoomIn.bind(this));
        },

        /**
         * Apply number-only validation to phone input
         */
        applyPhoneNumberOnly: function(input) {
            const $input = $(input);
            const inputName = $input.attr('name') || '';
            const isPhoneOrWhatsapp = inputName === 'phone' || inputName === 'whatsapp';
            
            // Remove existing handlers to avoid duplicates
            $input.off('input.phoneNumberOnly paste.phoneNumberOnly keypress.phoneNumberOnly');
            
            // Add number-only validation on input
            $input.on('input.phoneNumberOnly', function(e) {
                // Get current value
                let value = $(this).val();
                // Remove all non-numeric characters (keep only digits)
                value = value.replace(/\D/g, '');
                
                // Prevent "0" for phone and whatsapp fields
                if (isPhoneOrWhatsapp && value === '0') {
                    value = '';
                }
                
                // Update the value
                $(this).val(value);
            });
            
            // Prevent paste of non-numeric content
            $input.on('paste.phoneNumberOnly', function(e) {
                const paste = (e.originalEvent || e).clipboardData.getData('text');
                const numbersOnly = paste.replace(/\D/g, '');
                if (numbersOnly !== paste) {
                    e.preventDefault();
                    const currentValue = $(this).val();
                    const cursorPos = this.selectionStart;
                    let newValue = currentValue.substring(0, cursorPos) + numbersOnly + currentValue.substring(this.selectionEnd);
                    newValue = newValue.replace(/\D/g, '');
                    
                    // Prevent "0" for phone and whatsapp fields
                    if (isPhoneOrWhatsapp && newValue === '0') {
                        newValue = '';
                    }
                    
                    $(this).val(newValue);
                    // Set cursor position
                    setTimeout(() => {
                        this.setSelectionRange(cursorPos + (newValue.length - currentValue.length), cursorPos + (newValue.length - currentValue.length));
                    }, 0);
                }
            });
            
            // Prevent non-numeric keypress and prevent "0" for phone/whatsapp
            $input.on('keypress.phoneNumberOnly', function(e) {
                // Allow: backspace, delete, tab, escape, enter
                if ([8, 9, 27, 13].indexOf(e.which) !== -1 ||
                    // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                    (e.which === 65 && e.ctrlKey === true) ||
                    (e.which === 67 && e.ctrlKey === true) ||
                    (e.which === 86 && e.ctrlKey === true) ||
                    (e.which === 88 && e.ctrlKey === true) ||
                    // Allow: home, end, left, right
                    (e.which >= 35 && e.which <= 39)) {
                    return;
                }
                
                // Prevent "0" keypress for phone and whatsapp fields
                if (isPhoneOrWhatsapp && (e.which === 48 || e.which === 96)) {
                    e.preventDefault();
                    return;
                }
                
                // Ensure that it is a number and stop the keypress
                if ((e.shiftKey || (e.which < 48 || e.which > 57)) && (e.which < 96 || e.which > 105)) {
                    e.preventDefault();
                }
            });
        },

        /**
         * Initialize number-only inputs (commercial register, etc.)
         */
        initNumberInputs: function() {
            // Apply number-only validation to commercial register and other number fields
            $('.pt-number-input').each(function() {
                PTDashboard.applyPhoneNumberOnly(this);
            });
        },

        /**
         * Initialize international phone inputs
         */
        initPhoneInputs: function() {
            if (typeof intlTelInput === 'undefined') {
                console.log('intlTelInput not loaded, skipping phone init');
                return;
            }
            
            console.log('Initializing phone inputs...');
            
            // Initialize phone/whatsapp fields and company phone field
            $('input[name="phone"], input[name="whatsapp"], input[name="phone_number"]').each(function() {
                const input = this;
                const $input = $(input);
                const name = $input.attr('name');
                
                console.log('Found phone input:', name);
                
                // Create a hidden input to store the full number
                const $hidden = $('<input type="hidden" name="' + name + '_full" />');
                $input.after($hidden);
                
                // Apply number-only validation
                PTDashboard.applyPhoneNumberOnly(input);
                
                const iti = intlTelInput(input, {
                    initialCountry: 'sy',
                    countryOrder: ['sy', 'lb', 'jo', 'iq', 'ae', 'sa'],
                    separateDialCode: true,
                    loadUtilsOnInit: 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.7.4/build/js/utils.js',
                    i18n: {
                        searchPlaceholder: 'بحث...'
                    }
                });
                
                PTDashboard.phoneInputs.push({
                    input: input,
                    iti: iti,
                    name: name,
                    $hidden: $hidden
                });
                
                // Update hidden input when phone changes
                $input.on('change blur countrychange', function() {
                    PTDashboard.updatePhoneHiddenInput(input);
                });
                
                console.log('Phone input initialized:', name, 'ITI instance:', !!iti);
            });
            
            // Initialize branch phone inputs (without country code)
            $('.pt-repeater-item .pt-phone-input').each(function() {
                PTDashboard.applyPhoneNumberOnly(this);
            });
            
            console.log('Total phone inputs tracked:', PTDashboard.phoneInputs.length);
        },
        
        /**
         * Update the hidden input with full phone number
         */
        updatePhoneHiddenInput: function(input) {
            const phoneData = this.phoneInputs.find(p => p.input === input);
            if (!phoneData) return;
            
            let fullNumber = '';
            
            if (phoneData.iti) {
                try {
                    fullNumber = phoneData.iti.getNumber();
                } catch(e) {}
                
                // Fallback
                if (!fullNumber) {
                    const rawValue = $(input).val();
                    if (rawValue) {
                        try {
                            const countryData = phoneData.iti.getSelectedCountryData();
                            if (countryData && countryData.dialCode) {
                                const cleanNumber = rawValue.replace(/\D/g, '');
                                if (cleanNumber) {
                                    fullNumber = '+' + countryData.dialCode + cleanNumber;
                                }
                            }
                        } catch(e) {
                            fullNumber = rawValue;
                        }
                    }
                }
            }
            
            if (phoneData.$hidden) {
                phoneData.$hidden.val(fullNumber);
            }
            
            console.log('Updated hidden phone for', phoneData.name, ':', fullNumber);
        },

        /**
         * Get full phone number with country code
         */
        getPhoneNumber: function(input) {
            const phoneData = this.phoneInputs.find(p => p.input === input);
            if (phoneData && phoneData.iti) {
                return phoneData.iti.getNumber();
            }
            return $(input).val();
        },

        /**
         * Handle form submission via AJAX
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            const $message = $('#pt-form-message');
            
            // Disable submit button
            $submitBtn.prop('disabled', true).addClass('pt-loading');
            $message.removeClass('success error').hide();
            
            // Update phone input values BEFORE creating FormData
            const self = this;
            this.phoneInputs.forEach(function(phoneData) {
                if (!phoneData.iti || !phoneData.input) return;
                
                const $input = $(phoneData.input);
                let fullNumber = '';
                
                // Try to get full number from intl-tel-input
                try {
                    fullNumber = phoneData.iti.getNumber();
                } catch(e) {
                    console.log('ITI getNumber error:', e);
                }
                
                // Fallback to manual construction
                if (!fullNumber || fullNumber.trim() === '') {
                    const rawValue = $input.val();
                    if (rawValue && rawValue.trim() !== '') {
                        try {
                            const countryData = phoneData.iti.getSelectedCountryData();
                            if (countryData && countryData.dialCode) {
                                const cleanNumber = rawValue.replace(/\D/g, '');
                                if (cleanNumber) {
                                    fullNumber = '+' + countryData.dialCode + cleanNumber;
                                }
                            }
                        } catch(e) {
                            fullNumber = rawValue;
                        }
                    }
                }
                
                console.log('Phone ' + phoneData.name + ' value:', fullNumber);
                
                // Store in data attribute for later use
                $input.data('fullNumber', fullNumber);
            });
            
            // Collect form data
            const formData = new FormData($form[0]);
            formData.append('action', 'pt_save_listing');
            formData.append('nonce', ptAjax.nonce);
            
            // Override phone/whatsapp with full numbers
            this.phoneInputs.forEach(function(phoneData) {
                const $input = $(phoneData.input);
                const fullNumber = $input.data('fullNumber');
                const name = phoneData.name;
                
                if (name && fullNumber) {
                    formData.set(name, fullNumber);
                    console.log('FormData set ' + name + ':', fullNumber);
                } else if (name) {
                    // Even if empty, ensure the field exists
                    const rawVal = $input.val() || '';
                    formData.set(name, rawVal);
                    console.log('FormData set ' + name + ' (raw):', rawVal);
                }
            });
            
            // Remove any _full hidden fields
            formData.delete('phone_full');
            formData.delete('whatsapp_full');
            
            // Debug: Log all form data
            console.log('=== Form Data ===');
            for (let [key, value] of formData.entries()) {
                if (key === 'phone' || key === 'whatsapp' || key === 'post_title') {
                    console.log(key + ':', value);
                }
            }
            
            // Process repeater fields
            this.processRepeaterData(formData, 'services');
            this.processRepeaterData(formData, 'bracnches');
            
            // Process gallery
            this.processGalleryData(formData);
            
            $.ajax({
                url: ptAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success modal
                        self.showSuccessModal(response.data.message || ptAjax.strings.saved, function() {
                        // Redirect if new post
                        if (response.data.redirect && !$form.find('input[name="post_id"]').val()) {
                                window.location.href = response.data.redirect;
                        }
                        });
                    } else {
                        $message.addClass('error').text(response.data.message || ptAjax.strings.error).show();
                    }
                },
                error: function() {
                    $message.addClass('error').text(ptAjax.strings.error).show();
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).removeClass('pt-loading');
                }
            });
        },

        /**
         * Show success modal with modern UX
         */
        showSuccessModal: function(message, callback) {
            // Remove existing modal if any
            $('#pt-success-modal').remove();
            
            // Escape HTML in message for security
            const safeMessage = $('<div>').text(message).html();
            
            // Create modal HTML with better structure
            const modalHtml = `
                <div class="pt-modal-overlay" id="pt-success-modal">
                    <div class="pt-modal pt-success-modal">
                        <div class="pt-modal-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <h3 class="pt-modal-title">تم الحفظ بنجاح</h3>
                        <p class="pt-modal-message">${safeMessage}</p>
                        <button type="button" class="pt-btn pt-btn-primary pt-modal-close-btn">
                            <span>حسناً</span>
                        </button>
                    </div>
                </div>
            `;
            
            // Append to body
            $('body').append(modalHtml);
            
            // Show modal
            const $modal = $('#pt-success-modal');
            $modal.css('display', 'flex');
            
            // Prevent body scroll when modal is open
            $('body').css('overflow', 'hidden');
            
            // Close function
            const closeModal = function() {
                $modal.fadeOut(250, function() {
                    $(this).remove();
                    $('body').css('overflow', '');
                    if (callback) callback();
                });
            };
            
            // Close handlers
            $modal.find('.pt-modal-close-btn').on('click', closeModal);
            
            // Close on overlay click
            $modal.on('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Close on Escape key
            $(document).on('keydown.pt-success-modal', function(e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    closeModal();
                    $(document).off('keydown.pt-success-modal');
                }
            });
            
            // Auto close after 4 seconds (increased for better UX)
            const autoCloseTimer = setTimeout(function() {
                if ($modal.length && $modal.is(':visible')) {
                    closeModal();
                }
            }, 4000);
            
            // Clear timer if user closes manually
            $modal.on('click', '.pt-modal-close-btn, .pt-modal-overlay', function() {
                clearTimeout(autoCloseTimer);
                $(document).off('keydown.pt-success-modal');
            });
        },

        /**
         * Handle company registration form submission via AJAX
         */
        handleCompanyFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Disable submit button
            $submitBtn.prop('disabled', true).addClass('pt-loading');
            
            // Collect form data
            const formData = new FormData($form[0]);
            formData.append('action', 'pt_save_company');
            formData.append('nonce', ptAjax.nonce);
            
            $.ajax({
                url: ptAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success modal
                        self.showSuccessModal(response.data.message || ptAjax.strings.saved, function() {
                        // Redirect
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                        });
                    } else {
                        alert(response.data.message || ptAjax.strings.error);
                    }
                },
                error: function() {
                    alert(ptAjax.strings.error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).removeClass('pt-loading');
                }
            });
        },

        /**
         * Process repeater data for form submission
         */
        processRepeaterData: function(formData, fieldName) {
            const $repeater = $(`#${fieldName}-repeater`);
            const items = [];
            
            $repeater.find('.pt-repeater-item').each(function(index) {
                const item = {};
                $(this).find('input, textarea').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        // Extract field name from array notation
                        const match = name.match(/\[([^\]]+)\]$/);
                        if (match) {
                            item[match[1]] = $(this).val();
                        }
                    }
                });
                if (Object.keys(item).length > 0) {
                    items.push(item);
                }
            });
            
            // Remove old entries and add new
            for (let key of formData.keys()) {
                if (key.startsWith(fieldName + '[')) {
                    formData.delete(key);
                }
            }
            
            items.forEach((item, index) => {
                Object.keys(item).forEach(key => {
                    formData.append(`${fieldName}[${index}][${key}]`, item[key]);
                });
            });
        },

        /**
         * Process gallery data
         */
        processGalleryData: function(formData) {
            const $gallery = $('.pt-gallery-upload');
            const ids = [];
            
            // Find all gallery items across all gallery containers
            $gallery.find('.pt-gallery-item').each(function() {
                const id = $(this).data('id');
                if (id && !isNaN(id)) {
                    ids.push(parseInt(id));
                }
            });
            
            // Always set the gallery field, even if empty
            const galleryValue = ids.length > 0 ? ids.join(',') : '';
            formData.set('gallary', galleryValue);
            
            // Also update the hidden input field to keep it in sync
            $gallery.find('input[type="hidden"][name="gallary"]').val(galleryValue);
            
            console.log('Gallery processed:', galleryValue, 'IDs:', ids);
        },

        /**
         * Initialize character counter
         */
        initCharCounter: function() {
            const $textarea = $('#short_desc');
            if ($textarea.length) {
                this.updateCharCount.call($textarea[0]);
            }
        },

        /**
         * Update character count display
         */
        updateCharCount: function() {
            const $counter = $('#short_desc_count');
            const length = $(this).val().length;
            $counter.text(length);
            
            if (length >= 70) {
                $counter.css('color', 'var(--pt-error)');
            } else if (length >= 50) {
                $counter.css('color', 'var(--pt-warning)');
            } else {
                $counter.css('color', '');
            }
        },

        /**
         * Initialize repeater fields
         */
        initRepeaters: function() {
            // Reindex repeater items on load
            this.reindexRepeater('services');
            this.reindexRepeater('bracnches');
        },

        /**
         * Add new repeater item
         */
        addRepeaterItem: function(e) {
            e.preventDefault();
            
            const repeaterType = $(e.currentTarget).data('repeater');
            const $repeater = $(`#${repeaterType}-repeater`);
            const templateId = repeaterType === 'bracnches' ? 'branch-template' : 'service-template';
            const template = document.getElementById(templateId);
            
            if (!template) return;
            
            const newIndex = $repeater.find('.pt-repeater-item').length;
            let html = template.innerHTML.replace(/__INDEX__/g, newIndex);
            
            const $newItem = $(html);
            $repeater.append($newItem);
            
            // Animate entry
            $newItem.hide().slideDown(200);
            
            // Initialize phone inputs in new item
            $newItem.find('.pt-phone-input').each(function() {
                // Apply number-only validation
                PTDashboard.applyPhoneNumberOnly(this);
                
                if (typeof intlTelInput !== 'undefined') {
                    const iti = intlTelInput(this, {
                        initialCountry: 'sy',
                        preferredCountries: ['sy', 'lb', 'jo', 'iq', 'ae', 'sa'],
                        separateDialCode: true
                    });
                    PTDashboard.phoneInputs.push({
                        input: this,
                        iti: iti
                    });
                }
            });
            
            // Focus first input
            setTimeout(function() {
                $newItem.find('input:first').focus();
            }, 250);
        },

        /**
         * Remove repeater item
         */
        removeRepeaterItem: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(e.currentTarget).closest('.pt-repeater-item');
            const $repeater = $item.closest('.pt-repeater');
            
            // Don't remove if it's the last item for services
            if ($repeater.attr('id') === 'services-repeater' && $repeater.find('.pt-repeater-item').length <= 1) {
                return;
            }
            
            if (confirm(ptAjax.strings.confirm_delete)) {
                // Remove phone inputs from tracking
                $item.find('.pt-phone-input').each(function() {
                    const input = this;
                    PTDashboard.phoneInputs = PTDashboard.phoneInputs.filter(p => p.input !== input);
                });
                
                $item.slideUp(200, function() {
                    $(this).remove();
                    // Reindex remaining items
                    const repeaterType = $repeater.attr('id').replace('-repeater', '');
                    PTDashboard.reindexRepeater(repeaterType);
                });
            }
        },

        /**
         * Reindex repeater items
         */
        reindexRepeater: function(repeaterType) {
            const $repeater = $(`#${repeaterType}-repeater`);
            
            $repeater.find('.pt-repeater-item').each(function(index) {
                $(this).attr('data-index', index);
                
                $(this).find('input, textarea').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        const newName = name.replace(/\[\d+\]/, `[${index}]`);
                        $(this).attr('name', newName);
                    }
                });
            });
        },

        /**
         * Initialize branch toggle
         */
        initBranchToggle: function() {
            // Collapse all branches except first on load
            $('.pt-branch-item').each(function(index) {
                if (index > 0) {
                    $(this).addClass('collapsed');
                }
            });
        },

        /**
         * Toggle branch visibility
         */
        toggleBranch: function(e) {
            if ($(e.target).closest('.pt-toggle-branch').length || $(e.target).is('.pt-toggle-branch')) {
                e.preventDefault();
                const $branch = $(this).closest('.pt-branch-item');
                $branch.toggleClass('collapsed');
            }
        },

        /**
         * Update branch title from input
         */
        updateBranchTitle: function() {
            const value = $(this).val() || 'فرع جديد';
            $(this).closest('.pt-branch-item').find('.pt-branch-title').text(value);
        },

        /**
         * Initialize media upload functionality
         */
        initMediaUploads: function() {
            // Pre-populate media previews if needed
        },

        /**
         * Open WordPress media uploader for single image
         */
        openMediaUploader: function(e) {
            e.preventDefault();

            const $container = $(e.currentTarget).closest('.pt-media-upload');
            const $fileInput = $container.find('.pt-file-input');

            // Trigger file input click
            $fileInput.click();
        },

        /**
         * Open file browser for gallery upload
         */
        openGalleryUploader: function(e) {
            e.preventDefault();

            const $container = $(e.currentTarget).closest('.pt-gallery-upload');
            const $fileInput = $container.find('.pt-file-input');

            // Trigger file input click
            $fileInput.click();
        },

        /**
         * Setup drag and drop event handlers
         */
        setupDragAndDrop: function() {
            const self = this;

            $(document).on('dragenter dragover', '.pt-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('pt-drag-over');
            });

            $(document).on('dragleave', '.pt-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('pt-drag-over');
            });

            $(document).on('drop', '.pt-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('pt-drag-over');

                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    const $container = $(this).closest('.pt-media-upload, .pt-gallery-upload');
                    const isGallery = $container.hasClass('pt-gallery-upload');

                    if (isGallery) {
                        // Handle multiple files for gallery
                        self.handleMultipleFiles(files, $container);
                    } else {
                        // Handle single file for logo
                        self.handleSingleFile(files[0], $container);
                    }
                }
            });
        },

        /**
         * Handle file selection from input
         */
        handleFileSelect: function(e) {
            const files = e.target.files;
            if (files.length === 0) return;

            const $fileInput = $(e.target);
            const $container = $fileInput.closest('.pt-media-upload, .pt-gallery-upload');
            const isGallery = $container.hasClass('pt-gallery-upload');

            if (isGallery) {
                this.handleMultipleFiles(files, $container);
            } else {
                this.handleSingleFile(files[0], $container);
            }

            // Reset file input
            $fileInput.val('');
        },

        /**
         * Handle single file upload (logo)
         */
        handleSingleFile: function(file, $container) {
            const uploadType = $container.data('upload-type');
            const maxSize = $container.data('max-size');

            // Validate file
            const validationError = this.validateFile(file, maxSize);
            if (validationError) {
                this.showError($container, validationError);
                return;
            }

            this.hideError($container);

            // If logo, open crop modal; otherwise upload directly
            if (uploadType === 'logo') {
                this.openCropModal(file, $container);
            } else {
            this.uploadFile(file, $container, uploadType);
            }
        },

        /**
         * Handle multiple file upload (gallery)
         */
        handleMultipleFiles: function(files, $container) {
            const uploadType = $container.data('upload-type');
            const maxSize = $container.data('max-size');
            const self = this;

            // Convert FileList to Array
            const filesArray = Array.from(files);

            // Upload files sequentially
            let index = 0;
            function uploadNext() {
                if (index >= filesArray.length) {
                    return;
                }

                const file = filesArray[index];

                // Validate file
                const validationError = self.validateFile(file, maxSize);
                if (validationError) {
                    // Show error but continue with next file
                    self.showError($container, `${file.name}: ${validationError}`, true);
                    index++;
                    uploadNext();
                    return;
                }

                self.hideError($container);
                self.uploadFile(file, $container, uploadType, function() {
                    index++;
                    uploadNext();
                });
            }

            uploadNext();
        },

        /**
         * Validate file size and type
         */
        validateFile: function(file, maxSize) {
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!allowedTypes.includes(file.type)) {
                return 'نوع الملف غير مدعوم. يرجى رفع صور بصيغة JPG أو PNG فقط';
            }

            // Check file size
            if (file.size > maxSize) {
                const maxMB = (maxSize / 1048576).toFixed(0);
                return `حجم الملف كبير جداً. الحد الأقصى: ${maxMB} ميجابايت`;
            }

            return null; // No error
        },

        /**
         * Upload file via AJAX with progress tracking
         */
        uploadFile: function(file, $container, uploadType, callback) {
            const self = this;
            const formData = new FormData();
            const postId = $('#pt-listing-form input[name="post_id"]').val() || $('#pt-company-form input[name="post_id"]').val() || 0;

            formData.append('file', file);
            formData.append('action', 'pt_upload_media');
            formData.append('nonce', ptAjax.nonce);
            formData.append('upload_type', uploadType);
            formData.append('post_id', postId);

            // Show progress
            $container.addClass('pt-uploading');
            this.showProgress($container);

            // Create XHR for progress tracking
            const xhr = new XMLHttpRequest();

            // Progress handler
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    self.updateProgress($container, percentComplete);
                }
            });

            // Load handler (completion)
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.data.files && response.data.files.length > 0) {
                            const uploadedFile = response.data.files[0];

                            if (uploadType === 'logo') {
                                self.displayLogoPreview($container, uploadedFile);
                            } else {
                                self.addGalleryItem($container, uploadedFile);
                            }

                            self.hideProgress($container);
                            $container.removeClass('pt-uploading');

                            if (callback) callback();
                        } else {
                            self.showError($container, response.data.message || 'فشل في رفع الملف');
                            self.hideProgress($container);
                            $container.removeClass('pt-uploading');
                        }
                    } catch (e) {
                        self.showError($container, 'حدث خطأ غير متوقع');
                        self.hideProgress($container);
                        $container.removeClass('pt-uploading');
                    }
                } else {
                    self.showError($container, 'فشل في الاتصال بالخادم');
                    self.hideProgress($container);
                    $container.removeClass('pt-uploading');
                }
            });

            // Error handler
            xhr.addEventListener('error', function() {
                self.showError($container, 'حدث خطأ أثناء رفع الملف');
                self.hideProgress($container);
                $container.removeClass('pt-uploading');
            });

            // Send request
            xhr.open('POST', ptAjax.ajaxurl);
            xhr.send(formData);
        },

        /**
         * Display logo preview after upload
         */
        displayLogoPreview: function($container, file) {
            const $hiddenInput = $container.find('input[type="hidden"]');
            const $dropzone = $container.find('.pt-dropzone');
            const self = this;

            // Set attachment ID
            $hiddenInput.val(file.id);

            // Replace dropzone with preview
            $dropzone.replaceWith(`
                <div class="pt-media-preview has-image">
                    <img src="${file.medium || file.url}" alt="">
                    <div class="pt-media-actions">
                        <button type="button" class="pt-edit-media" data-attachment-id="${file.id}" title="تعديل">
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
            `);
        },

        /**
         * Add item to gallery grid
         */
        addGalleryItem: function($container, file) {
            // Validate file object
            if (!file || !file.id) {
                console.error('Invalid file object in addGalleryItem:', file);
                return;
            }

            const $grid = $container.find('.pt-gallery-grid');
            const fileId = parseInt(file.id);

            // Check if already exists
            if ($grid.find(`.pt-gallery-item[data-id="${fileId}"]`).length > 0) {
                console.log('Gallery item already exists:', fileId);
                return; // Already exists
            }

            // Append new item
            $grid.append(`
                <div class="pt-gallery-item" data-id="${fileId}">
                    <img src="${file.thumb || file.url}" alt="">
                    <button type="button" class="pt-remove-gallery-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            `);

            // Update hidden input
            this.updateGalleryInput($container);
            console.log('Gallery item added:', fileId);
        },

        /**
         * Show upload progress
         */
        showProgress: function($container) {
            $container.find('.pt-upload-progress').show();
            $container.find('.pt-upload-error').hide();
            this.updateProgress($container, 0);
        },

        /**
         * Hide upload progress
         */
        hideProgress: function($container) {
            $container.find('.pt-upload-progress').hide();
        },

        /**
         * Update progress bar
         */
        updateProgress: function($container, percent) {
            const $progress = $container.find('.pt-upload-progress');
            $progress.find('.pt-progress-fill').css('width', percent + '%');
            $progress.find('.pt-progress-text').text(Math.round(percent) + '%');
        },

        /**
         * Show error message
         */
        showError: function($container, message, append) {
            const $error = $container.find('.pt-upload-error');

            if (append) {
                // Append to existing errors
                const currentText = $error.text();
                $error.text(currentText + (currentText ? '\n' : '') + message);
            } else {
                // Replace error message
                $error.text(message);
            }

            $error.show();

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $error.fadeOut();
            }, 5000);
        },

        /**
         * Hide error message
         */
        hideError: function($container) {
            $container.find('.pt-upload-error').hide().text('');
        },

        /**
         * Update gallery hidden input value
         */
        updateGalleryInput: function($container) {
            const ids = [];
            $container.find('.pt-gallery-item').each(function() {
                const id = $(this).data('id');
                if (id && !isNaN(id)) {
                    ids.push(parseInt(id));
                }
            });
            const value = ids.length > 0 ? ids.join(',') : '';
            $container.find('input[type="hidden"][name="gallary"]').val(value);
            console.log('Gallery input updated:', value, 'IDs:', ids);
        },

        /**
         * Remove single media image
         */
        removeMedia: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $container = $(this).closest('.pt-media-upload');
            $container.find('input[type="hidden"]').val('');
            
            // Replace preview with dropzone
            $container.find('.pt-media-preview').replaceWith(`
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
            `);
        },

        /**
         * Logo Cropper Functions
         */
        cropperInstance: null,
        currentCropContainer: null,
        currentCropFile: null,
        currentAttachmentId: null,
        currentAttachmentId: null,

        /**
         * Open crop modal with image
         */
        openCropModal: function(file, $container, imageUrl) {
            const self = this;
            this.currentCropFile = file;
            this.currentCropContainer = $container;

            console.log('Opening crop modal:', { 
                hasFile: !!file, 
                fileSize: file ? file.size : 0,
                imageUrl: imageUrl,
                hasContainer: !!$container 
            });

            const $modal = $('#pt-crop-modal');
            
            if ($modal.length === 0) {
                console.error('Crop modal not found in DOM');
                this.showError($container, 'خطأ: لم يتم العثور على نافذة التحرير');
                return;
            }
            
            const $container_div = $modal.find('.pt-crop-container');
            const $img = $container_div.find('#pt-crop-image');
            
            if ($img.length === 0) {
                console.error('Crop image element not found');
                this.showError($container, 'خطأ: لم يتم العثور على عنصر الصورة');
                return;
            }
            
            // Clear any previous error state
            $img.removeClass('cropper-hidden');
            
            // Priority: Always use file to create blob URL if available
            let finalImageUrl = null;
            let isBlobUrl = false;
            
            if (file && file.size > 0) {
                // Create fresh blob URL from file - this is the most reliable method
                try {
                    finalImageUrl = URL.createObjectURL(file);
                    isBlobUrl = true;
                    console.log('Created blob URL from file:', finalImageUrl);
                } catch (error) {
                    console.error('Failed to create blob URL:', error);
                }
            }
            
            // Fallback: Use provided URL if no file or blob creation failed
            if (!finalImageUrl && imageUrl) {
                // Ensure it's absolute
                if (!imageUrl.startsWith('blob:') && !imageUrl.startsWith('data:') && !imageUrl.startsWith('http')) {
                    const baseUrl = window.location.origin;
                    if (imageUrl.startsWith('/')) {
                        finalImageUrl = baseUrl + imageUrl;
                    } else {
                        finalImageUrl = baseUrl + '/' + imageUrl;
                    }
                } else {
                    finalImageUrl = imageUrl;
                }
                console.log('Using provided image URL:', finalImageUrl);
            }
            
            if (!finalImageUrl) {
                console.error('No valid image URL or file available');
                this.showError($container, 'خطأ: لا يوجد رابط للصورة');
                return;
            }
            
            // Validate URL format
            if (!finalImageUrl.startsWith('blob:') && !finalImageUrl.startsWith('data:') && !finalImageUrl.startsWith('http')) {
                console.error('Invalid image URL format:', finalImageUrl);
                this.showError($container, 'خطأ: رابط الصورة غير صحيح');
                return;
            }
            
            console.log('Setting image source:', finalImageUrl);
            
            // Store blob URL for cleanup
            const blobUrlToCleanup = isBlobUrl ? finalImageUrl : null;
            
            // Set image source
            $img.attr('src', '');
            $img.removeAttr('crossOrigin');
            
            // Only add crossOrigin for non-blob, non-data URLs
            if (!isBlobUrl && !finalImageUrl.startsWith('data:')) {
                $img.attr('crossOrigin', 'anonymous');
            }
            
            // Show modal first
            $modal.fadeIn(200);
            
            // Set image source after a small delay to ensure modal is visible
            setTimeout(function() {
                $img.attr('src', finalImageUrl);
            }, 50);
            
            let retryCount = 0;
            const maxRetries = 2;
            
            // Initialize cropper when image loads
            $img.off('load.cropper error.cropper').on('load.cropper', function() {
                console.log('Image loaded successfully, initializing cropper');
                retryCount = 0; // Reset retry count on success
                // Remove error class if it was added
                $img.removeClass('cropper-hidden');
                self.initLogoCropper($img[0]);
            }).on('error.cropper', function(e) {
                console.error('Image failed to load:', e);
                console.error('Image URL was:', finalImageUrl);
                console.error('Is blob URL:', isBlobUrl);
                console.error('Has file:', !!self.currentCropFile);
                
                retryCount++;
                
                // If blob URL failed and we have the file, recreate blob URL
                if (isBlobUrl && retryCount <= maxRetries && self.currentCropFile && self.currentCropFile.size > 0) {
                    console.log('Blob URL failed, recreating from file (attempt ' + retryCount + ')...');
                    // Revoke old blob URL
                    if (blobUrlToCleanup) {
                        try {
                            URL.revokeObjectURL(blobUrlToCleanup);
                        } catch (e) {
                            console.warn('Failed to revoke blob URL:', e);
                        }
                    }
                    // Create new blob URL
                    try {
                        const newBlobUrl = URL.createObjectURL(self.currentCropFile);
                        $img.attr('src', newBlobUrl);
                        finalImageUrl = newBlobUrl;
                        return; // Let it try loading again
                    } catch (error) {
                        console.error('Failed to recreate blob URL:', error);
                    }
                }
                
                // For non-blob URLs, try without crossOrigin
                if (!isBlobUrl && retryCount <= maxRetries && $img.attr('crossOrigin')) {
                    console.log('Retrying without crossOrigin (attempt ' + retryCount + ')...');
                    $img.removeAttr('crossOrigin');
                    // Add cache buster for non-blob URLs
                    const separator = finalImageUrl.includes('?') ? '&' : '?';
                    $img.attr('src', finalImageUrl + separator + 't=' + Date.now());
                    return; // Let it try loading again
                }
                
                // All retries failed
                console.error('All retry attempts failed after', retryCount, 'tries');
                self.showError($container, 'فشل في تحميل الصورة. يرجى المحاولة مرة أخرى أو رفع صورة جديدة.');
                $modal.fadeOut(200);
                
                // Clean up blob URL if it was created
                if (blobUrlToCleanup) {
                    try {
                        URL.revokeObjectURL(blobUrlToCleanup);
                    } catch (e) {
                        console.warn('Failed to revoke blob URL on error:', e);
                    }
                }
            });
            
            // Check if image is already loaded after a short delay
            setTimeout(function() {
                if ($img[0].complete) {
                    if ($img[0].naturalHeight !== 0) {
                        console.log('Image already loaded, initializing immediately');
                        self.initLogoCropper($img[0]);
                    } else {
                        console.warn('Image completed loading but has 0 height - may have failed');
                    }
                }
            }, 200);
        },

        /**
         * Edit existing logo
         */
        editLogo: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const self = this;
            const $button = $(e.currentTarget);
            const attachmentId = $button.data('attachment-id');
            const $container = $button.closest('.pt-media-upload');
            
            if (!attachmentId) {
                console.error('No attachment ID found');
                return;
            }
            
            // Get image URL from the preview
            const $preview = $container.find('.pt-media-preview img');
            let imageUrl = $preview.attr('src');
            
            if (!imageUrl) {
                console.error('No image URL found');
                this.showError($container, 'لم يتم العثور على الصورة');
                return;
            }
            
            // Convert relative URL to absolute if needed
            if (imageUrl.startsWith('/') || !imageUrl.startsWith('http')) {
                const baseUrl = window.location.origin;
                if (imageUrl.startsWith('/')) {
                    imageUrl = baseUrl + imageUrl;
                } else {
                    imageUrl = baseUrl + '/' + imageUrl;
                }
            }
            
            console.log('Editing logo:', { attachmentId, imageUrl });
            
            // Show loading state
            $container.addClass('pt-uploading');
            
            // Try to fetch the image as blob to create a File object
            // If fetch fails (CORS issue), we'll use the image URL directly
            fetch(imageUrl, {
                mode: 'cors',
                credentials: 'same-origin',
                cache: 'no-cache'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch image: ' + response.status + ' ' + response.statusText);
                    }
                    if (!response.headers.get('content-type') || !response.headers.get('content-type').startsWith('image/')) {
                        throw new Error('Response is not an image');
                    }
                    return response.blob();
                })
                .then(blob => {
                    // Validate blob
                    if (!blob || blob.size === 0) {
                        throw new Error('Blob is empty or invalid');
                    }
                    
                    // Create a File object from the blob
                    const file = new File([blob], 'logo.png', {
                        type: blob.type || 'image/png',
                        lastModified: Date.now()
                    });
                    
                    console.log('File created successfully:', { 
                        name: file.name, 
                        size: file.size, 
                        type: file.type 
                    });
                    
                    // Store container reference
                    self.currentCropContainer = $container;
                    self.currentCropFile = file;
                    self.currentAttachmentId = attachmentId;
                    
                    // Remove loading state
                    $container.removeClass('pt-uploading');
                    
                    // Open crop modal with the file (will create fresh blob URL)
                    // Don't pass imageUrl - let it create blob URL from file
                    self.openCropModal(file, $container, null);
                })
                .catch(error => {
                    console.warn('Fetch failed, using image URL directly:', error);
                    console.warn('Image URL:', imageUrl);
                    
                    // Fallback: Use image URL directly without creating a File object
                    // We'll create a minimal file object for compatibility, but use the URL for display
                    const dummyFile = new File([''], 'logo.png', {
                        type: 'image/png',
                        lastModified: Date.now()
                    });
                    
                    // Store container reference
                    self.currentCropContainer = $container;
                    self.currentCropFile = dummyFile;
                    self.currentAttachmentId = attachmentId;
                    
                    // Remove loading state
                    $container.removeClass('pt-uploading');
                    
                    // Open crop modal with existing image URL directly
                    // This will use the URL instead of creating a blob
                    self.openCropModal(dummyFile, $container, imageUrl);
                });
        },

        /**
         * Initialize Cropper.js instance
         */
        initLogoCropper: function(imageElement) {
            const self = this;
            
            // Destroy existing cropper if any
            if (this.cropperInstance) {
                this.cropperInstance.destroy();
                this.cropperInstance = null;
            }

            // Initialize cropper with 1:1 aspect ratio
            this.cropperInstance = new Cropper(imageElement, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.8,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleable: false,
                minCropBoxWidth: 100,
                minCropBoxHeight: 100,
                ready: function() {
                    // Set max dimensions to 512x512
                    const cropper = self.cropperInstance;
                    const canvasData = cropper.getCanvasData();
                    const maxDimension = 512;
                    
                    // If image is larger than 512x512, scale it down
                    if (canvasData.naturalWidth > maxDimension || canvasData.naturalHeight > maxDimension) {
                        const scale = maxDimension / Math.max(canvasData.naturalWidth, canvasData.naturalHeight);
                        cropper.zoomTo(scale);
                    }
                    
                    // Update zoom display after initialization
                    self.updateZoomDisplay();
                },
                zoom: function() {
                    // Update zoom display when zoom changes
                    self.updateZoomDisplay();
                }
            });

            // Update zoom display
            this.updateZoomDisplay();
        },

        /**
         * Handle zoom in
         */
        handleZoomIn: function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (this.cropperInstance) {
                const canvasData = this.cropperInstance.getCanvasData();
                const currentZoom = canvasData.scaleX || 1;
                const newZoom = Math.min(currentZoom + 0.1, 3); // Max zoom 3x
                this.cropperInstance.zoomTo(newZoom);
                this.updateZoomDisplay();
            }
        },

        /**
         * Handle zoom out
         */
        handleZoomOut: function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (this.cropperInstance) {
                const canvasData = this.cropperInstance.getCanvasData();
                const currentZoom = canvasData.scaleX || 1;
                const newZoom = Math.max(currentZoom - 0.1, 0.1); // Min zoom 0.1x
                this.cropperInstance.zoomTo(newZoom);
                this.updateZoomDisplay();
            }
        },

        /**
         * Update zoom display
         */
        updateZoomDisplay: function() {
            if (this.cropperInstance) {
                const canvasData = this.cropperInstance.getCanvasData();
                const zoom = canvasData.scaleX;
                $('#pt-zoom-slider').val(zoom);
            }
        },

        /**
         * Handle zoom slider
         */
        handleZoomSlider: function(e) {
            if (this.cropperInstance) {
                const zoomValue = parseFloat($(e.target).val());
                this.cropperInstance.zoomTo(zoomValue);
            }
        },

        /**
         * Apply crop and upload
         */
        applyCrop: function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            const self = this;
            
            if (!this.cropperInstance) {
                console.error('Cropper instance not found');
                this.showError(this.currentCropContainer, 'خطأ: لم يتم تهيئة المحرر');
                return;
            }
            
            if (!this.currentCropFile) {
                console.error('Current crop file not found');
                this.showError(this.currentCropContainer, 'خطأ: لم يتم العثور على الملف');
                return;
            }
            
            if (!this.currentCropContainer) {
                console.error('Current crop container not found');
                return;
            }

            // Show loading state
            const $container = this.currentCropContainer;
            $container.addClass('pt-uploading');
            this.showProgress($container);

            try {
                // Get cropped canvas
                const canvas = this.cropperInstance.getCroppedCanvas({
                    width: 512,
                    height: 512,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high'
                });

                if (!canvas) {
                    console.error('Failed to get cropped canvas');
                    this.hideProgress($container);
                    $container.removeClass('pt-uploading');
                    this.showError($container, 'فشل في قص الصورة');
                    return;
                }

                // Convert canvas to blob
                canvas.toBlob(function(blob) {
                    if (!blob) {
                        console.error('Failed to convert canvas to blob');
                        self.hideProgress($container);
                        $container.removeClass('pt-uploading');
                        self.showError($container, 'فشل في تحويل الصورة');
                        return;
                    }

                    // Create a File object from blob
                    const croppedFile = new File([blob], self.currentCropFile.name, {
                        type: self.currentCropFile.type,
                        lastModified: Date.now()
                    });

                    // Store container reference before closing modal
                    const containerRef = $container;

                    // Close modal visually but keep references for upload
                    const $modal = $('#pt-crop-modal');
            
                    // Destroy cropper instance
                    if (self.cropperInstance) {
                        self.cropperInstance.destroy();
                        self.cropperInstance = null;
                    }

                    // Clear object URLs
                    const $img = $modal.find('#pt-crop-image');
                    const src = $img.attr('src');
                    if (src && src.startsWith('blob:')) {
                        URL.revokeObjectURL(src);
                    }
                    $img.attr('src', '');
            
                    // Hide modal
                    $modal.fadeOut(200);

                    // Upload cropped file (container reference is preserved)
                    self.uploadFile(croppedFile, containerRef, 'logo', function() {
                        // Clear state after successful upload
                        self.currentCropFile = null;
                        self.currentCropContainer = null;
                    });
                }, this.currentCropFile.type, 0.95);
            } catch (error) {
                console.error('Error in applyCrop:', error);
                this.hideProgress($container);
                $container.removeClass('pt-uploading');
                this.showError($container, 'حدث خطأ أثناء معالجة الصورة: ' + error.message);
            }
        },

        /**
         * Close crop modal
         */
        closeCropModal: function() {
            const $modal = $('#pt-crop-modal');
            
            // Destroy cropper instance
            if (this.cropperInstance) {
                this.cropperInstance.destroy();
                this.cropperInstance = null;
            }

            // Clear object URLs
            const $img = $modal.find('#pt-crop-image');
            const src = $img.attr('src');
            if (src && src.startsWith('blob:')) {
                URL.revokeObjectURL(src);
            }
            $img.attr('src', '');
            
            // Hide modal
            $modal.fadeOut(200);
            
            // Reset state
            this.currentCropFile = null;
            this.currentCropContainer = null;
        },

        /**
         * Remove gallery item
         */
        removeGalleryItem: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this).closest('.pt-gallery-item');
            const $container = $item.closest('.pt-gallery-upload');
            
            $item.fadeOut(200, function() {
                $(this).remove();
                PTDashboard.updateGalleryInput($container);
            });
        },

        /**
         * Initialize password toggle
         */
        initPasswordToggle: function() {
            // Initialize all password fields - ensure correct icon visibility
            $('.pt-password-field').each(function() {
                const $field = $(this);
                const $input = $field.find('input[type="password"]');
                const $btn = $field.find('.pt-toggle-password');
                
                if ($input.length && $btn.length) {
                    // Set initial state using class-based approach
                    if ($input.attr('type') === 'password') {
                        $btn.removeClass('is-visible');
                    } else {
                        $btn.addClass('is-visible');
                    }
                }
            });
        },

        /**
         * Toggle password visibility
         */
        togglePassword: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const $input = $btn.siblings('input');

            if ($input.length === 0) {
                console.warn('Password input not found');
                return;
            }

            if ($input.attr('type') === 'password') {
                // Show password - add is-visible class
                $input.attr('type', 'text');
                $btn.addClass('is-visible');
            } else {
                // Hide password - remove is-visible class
                $input.attr('type', 'password');
                $btn.removeClass('is-visible');
            }
        },

        /**
         * Format phone number for display
         * Converts raw phone number to formatted display
         * @param {string} phone - Raw phone number
         * @return {object} - Object with formatted parts
         */
        formatPhoneNumber: function(phone) {
            if (!phone) return null;

            // Remove all non-digits
            const cleaned = phone.replace(/\D/g, '');

            // Syrian mobile format: 0999 333 963
            if (cleaned.startsWith('09') && cleaned.length === 10) {
                return {
                    countryCode: '',
                    number: cleaned.replace(/(\d{4})(\d{3})(\d{3})/, '$1 $2 $3'),
                    dialNumber: '+963' + cleaned.substring(1),
                    formatted: cleaned.replace(/(\d{4})(\d{3})(\d{3})/, '$1 $2 $3')
                };
            }

            // International format starting with country code
            if (cleaned.startsWith('963') && cleaned.length === 12) {
                return {
                    countryCode: '+963',
                    number: cleaned.substring(3).replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3'),
                    dialNumber: '+' + cleaned,
                    formatted: '+' + cleaned.replace(/(\d{3})(\d{3})(\d{3})(\d{3})/, '$1 $2 $3 $4')
                };
            }

            // Default: just clean up and return
            return {
                countryCode: '',
                number: cleaned,
                dialNumber: cleaned,
                formatted: cleaned
            };
        },

        /**
         * Initialize mobile drawer functionality
         */
        initMobileDrawer: function() {
            // Nothing to initialize on load, just ensure drawer is closed
            this.closeMobileDrawer();
        },

        /**
         * Open mobile navigation drawer
         */
        openMobileDrawer: function() {
            const $sidebar = $('.pt-sidebar');
            const $overlay = $('.pt-drawer-overlay');
            const $hamburger = $('.pt-hamburger-btn');

            // Add is-open class
            $sidebar.addClass('is-open');
            $overlay.addClass('is-open');

            // Update ARIA attribute
            $hamburger.attr('aria-expanded', 'true');

            // Prevent body scroll when drawer is open
            $('body').css('overflow', 'hidden');
        },

        /**
         * Close mobile navigation drawer
         */
        closeMobileDrawer: function() {
            const $sidebar = $('.pt-sidebar');
            const $overlay = $('.pt-drawer-overlay');
            const $hamburger = $('.pt-hamburger-btn');

            // Remove is-open class
            $sidebar.removeClass('is-open');
            $overlay.removeClass('is-open');

            // Update ARIA attribute
            $hamburger.attr('aria-expanded', 'false');

            // Re-enable body scroll
            $('body').css('overflow', '');
        },

        /**
         * Initialize sidebar toggle functionality
         * Restores saved sidebar state from localStorage
         */
        initSidebarToggle: function() {
            // Only on desktop/laptop screens (width > 1005px)
            if (window.innerWidth <= 1005) {
                return;
            }

            // Check localStorage for saved state
            const isCollapsed = localStorage.getItem('pt-sidebar-collapsed') === 'true';

            if (isCollapsed) {
                $('.pt-sidebar').addClass('is-collapsed');
            }
        },

        /**
         * Toggle sidebar collapsed/expanded state
         * Saves preference to localStorage
         */
        toggleSidebar: function() {
            const $sidebar = $('.pt-sidebar');
            const isCollapsed = $sidebar.hasClass('is-collapsed');

            if (isCollapsed) {
                // Expand sidebar
                $sidebar.removeClass('is-collapsed');
                localStorage.setItem('pt-sidebar-collapsed', 'false');
            } else {
                // Collapse sidebar
                $sidebar.addClass('is-collapsed');
                localStorage.setItem('pt-sidebar-collapsed', 'true');
            }
        },

        /**
         * Initialize avatar upload
         */
        initAvatarUpload: function() {
            const self = this;
            
            // Open file picker when button is clicked
            $(document).on('click', '#pt-change-avatar-btn', function(e) {
                e.preventDefault();
                $('#pt-avatar-upload').click();
            });
            
            // Handle file selection
            $(document).on('change', '#pt-avatar-upload', function() {
                const file = this.files[0];
                if (!file) return;
                
                // Validate file
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                const maxSize = 2097152; // 2MB
                
                if (!allowedTypes.includes(file.type)) {
                    self.showAvatarError('نوع الملف غير مدعوم. يرجى رفع صور بصيغة JPG أو PNG فقط');
                    return;
                }
                
                if (file.size > maxSize) {
                    self.showAvatarError('حجم الملف كبير جداً. الحد الأقصى: 2 ميجابايت');
                    return;
                }
                
                // Upload file
                self.uploadAvatar(file);
            });
        },

        /**
         * Upload avatar via AJAX
         */
        uploadAvatar: function(file) {
            const self = this;
            const formData = new FormData();
            
            formData.append('file', file);
            formData.append('action', 'pt_upload_avatar');
            formData.append('nonce', ptAjax.nonce);
            
            // Show progress
            $('#pt-avatar-upload-progress').show();
            $('#pt-avatar-error').hide();
            this.updateAvatarProgress(0);
            
            // Create XHR for progress tracking
            const xhr = new XMLHttpRequest();
            
            // Progress handler
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    self.updateAvatarProgress(percentComplete);
                }
            });
            
            // Load handler (completion)
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.data.avatar_url) {
                            self.updateAvatarDisplay(response.data.avatar_url);
                            self.hideAvatarProgress();
                            $('#pt-avatar-upload').val(''); // Reset file input
                        } else {
                            self.showAvatarError(response.data.message || 'فشل في رفع الصورة');
                            self.hideAvatarProgress();
                        }
                    } catch (e) {
                        self.showAvatarError('حدث خطأ غير متوقع');
                        self.hideAvatarProgress();
                    }
                } else {
                    self.showAvatarError('فشل في الاتصال بالخادم');
                    self.hideAvatarProgress();
                }
            });
            
            // Error handler
            xhr.addEventListener('error', function() {
                self.showAvatarError('حدث خطأ أثناء رفع الصورة');
                self.hideAvatarProgress();
            });
            
            // Send request
            xhr.open('POST', ptAjax.ajaxurl);
            xhr.send(formData);
        },

        /**
         * Update avatar display
         */
        updateAvatarDisplay: function(avatarUrl) {
            const $container = $('#pt-avatar-container');
            $container.find('img, .avatar').remove();
            $container.prepend('<img src="' + avatarUrl + '" alt="Avatar" class="pt-avatar-image">');
        },

        /**
         * Update avatar upload progress
         */
        updateAvatarProgress: function(percent) {
            $('#pt-avatar-upload-progress .pt-progress-fill').css('width', percent + '%');
            $('#pt-avatar-upload-progress .pt-progress-text').text(Math.round(percent) + '%');
        },

        /**
         * Hide avatar upload progress
         */
        hideAvatarProgress: function() {
            $('#pt-avatar-upload-progress').hide();
        },

        /**
         * Show avatar error
         */
        showAvatarError: function(message) {
            const $error = $('#pt-avatar-error');
            $error.text(message).show();
            setTimeout(function() {
                $error.fadeOut();
            }, 5000);
        },

        /**
         * Initialize statistics and highlights repeaters
         */
        initRepeaters: function() {
            const self = this;

            // Statistics repeater - Add button
            $(document).on('click', '.pt-add-stat-btn', function() {
                const $repeater = $('#key_statistics-repeater');
                const index = $repeater.find('.pt-repeater-item').length;

                const template = `
                <div class="pt-repeater-item pt-stat-item-row" data-index="${index}">
                    <div class="pt-form-grid pt-form-grid-stats">
                        <div class="pt-form-group">
                            <label class="pt-label">الرقم</label>
                            <input type="text"
                                   name="key_statistics[${index}][stat_number]"
                                   class="pt-input"
                                   placeholder="مثال: +500">
                        </div>
                        <div class="pt-form-group">
                            <label class="pt-label">الوصف</label>
                            <input type="text"
                                   name="key_statistics[${index}][stat_label]"
                                   class="pt-input"
                                   placeholder="مثال: عميل سعيد">
                        </div>
                        <div class="pt-form-group">
                            <label class="pt-label">الأيقونة</label>
                            <select name="key_statistics[${index}][stat_icon]" class="pt-select">
                                <option value="calendar_month">سنوات الخبرة</option>
                                <option value="groups">العملاء</option>
                                <option value="handshake">الشركاء</option>
                                <option value="workspace_premium">الجوائز</option>
                                <option value="verified">المشاريع</option>
                                <option value="star">نجمة</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="pt-remove-repeater-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>`;

                $repeater.append(template);
            });

            // Highlights repeater - Add button
            $(document).on('click', '.pt-add-highlight-btn', function() {
                const $repeater = $('#about_highlights-repeater');
                const index = $repeater.find('.pt-repeater-item').length;

                const template = `
                <div class="pt-repeater-item pt-highlight-item-row" data-index="${index}">
                    <div class="pt-repeater-content">
                        <input type="text"
                               name="about_highlights[${index}][highlight_text]"
                               class="pt-input"
                               placeholder="نقطة مميزة عن شركتكم">
                    </div>
                    <button type="button" class="pt-remove-repeater-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>`;

                $repeater.append(template);
            });

            // Remove repeater item
            $(document).on('click', '.pt-remove-repeater-item', function() {
                $(this).closest('.pt-repeater-item').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        PTDashboard.init();
        PTDashboard.initRepeaters();
    });

    // Make available globally if needed
    window.PTDashboard = PTDashboard;

})(jQuery);
