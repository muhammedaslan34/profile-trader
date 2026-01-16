/**
 * Profile Trader Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Auto connect by email
        $('#pt-auto-connect').on('click', function() {
            var $btn = $(this);
            var $result = $('#pt-auto-connect-result');
            var $logsContainer = $('#pt-auto-connect-logs');
            var $logsSummary = $('#pt-logs-summary');
            var $logsTbody = $('#pt-auto-connect-logs-tbody');

            $btn.prop('disabled', true);
            $result.removeClass('success error').addClass('loading').text('جاري الربط...').show();
            $logsContainer.hide();

            $.post(ptAdmin.ajaxurl, {
                action: 'pt_auto_connect_by_email',
                nonce: ptAdmin.nonce
            }, function(response) {
                $btn.prop('disabled', false);
                $result.removeClass('loading');

                if (response.success) {
                    $result.addClass('success').text(response.data.message);

                    // Display logs if available
                    if (response.data.logs && response.data.logs.length > 0) {
                        var logs = response.data.logs;
                        var stats = response.data.stats;

                        // Build summary
                        var summaryHtml = '<div class="pt-logs-stats">';
                        summaryHtml += '<span class="pt-log-stat pt-log-success">✓ نجح: ' + stats.connected + '</span>';
                        summaryHtml += '<span class="pt-log-stat pt-log-already">• مرتبط مسبقاً: ' + stats.already_connected + '</span>';
                        summaryHtml += '<span class="pt-log-stat pt-log-no-match">⚠ بدون تطابق: ' + stats.no_match + '</span>';
                        if (stats.errors && stats.errors.length > 0) {
                            summaryHtml += '<span class="pt-log-stat pt-log-error">✗ أخطاء: ' + stats.errors.length + '</span>';
                        }
                        summaryHtml += '</div>';
                        $logsSummary.html(summaryHtml);

                        // Build logs table
                        var logsHtml = '';
                        logs.forEach(function(log) {
                            var statusClass = 'pt-log-status-' + log.status;
                            var statusText = '';
                            var statusIcon = '';

                            switch(log.status) {
                                case 'success':
                                    statusText = 'نجح';
                                    statusIcon = '✓';
                                    break;
                                case 'already_connected':
                                    statusText = 'مرتبط مسبقاً';
                                    statusIcon = '•';
                                    break;
                                case 'no_match':
                                    statusText = 'بدون تطابق';
                                    statusIcon = '⚠';
                                    break;
                                case 'no_email':
                                    statusText = 'لا يوجد إيميل';
                                    statusIcon = '—';
                                    break;
                                case 'error':
                                    statusText = 'خطأ';
                                    statusIcon = '✗';
                                    break;
                            }

                            logsHtml += '<tr>';
                            logsHtml += '<td>' + log.title + '</td>';
                            logsHtml += '<td>' + (log.email || '—') + '</td>';
                            logsHtml += '<td><span class="' + statusClass + '">' + statusIcon + ' ' + statusText + '</span></td>';
                            logsHtml += '<td>' + log.message + '</td>';
                            logsHtml += '<td><a href="' + log.edit_link + '" class="button button-small">تعديل</a></td>';
                            logsHtml += '</tr>';
                        });

                        $logsTbody.html(logsHtml);
                        $logsContainer.show();
                    }

                    // Don't auto-reload, let user review logs
                    // setTimeout(function() {
                    //     location.reload();
                    // }, 2000);
                } else {
                    $result.addClass('error').text(response.data.message || 'حدث خطأ');
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $result.removeClass('loading').addClass('error').text('حدث خطأ في الاتصال');
            });
        });
        
        // Create users for traders - with batch support and progress tracking
        $('#pt-create-users').on('click', function() {
            var useDefaultPassword = $('#pt-use-default-password').is(':checked');
            var createAll = $('#pt-create-all').is(':checked');
            var batchSize = parseInt($('#pt-batch-size').val()) || 50;

            // Validate batch size
            if (!createAll && (batchSize < 1 || batchSize > 1000)) {
                alert('يرجى إدخال حجم دفعة صالح (1-1000)');
                return;
            }

            // Confirmation
            var confirmMsg = createAll
                ? 'هل أنت متأكد؟ سيتم إنشاء جميع الحسابات دفعة واحدة. قد يستغرق هذا وقتاً طويلاً.'
                : 'هل أنت متأكد؟ سيتم إنشاء ' + batchSize + ' حساب في كل دفعة.';

            if (!confirm(confirmMsg)) {
                return;
            }

            var $btn = $(this);
            var $cancelBtn = $('#pt-cancel-create');
            var $result = $('#pt-create-users-result');
            var $progressContainer = $('#pt-create-progress-container');
            var $progressFill = $('#pt-create-progress-fill');
            var $progressText = $('#pt-create-progress-text');
            var $progressStats = $('#pt-create-progress-stats');

            var cancelled = false;
            var totalCreated = 0;
            var totalSkipped = 0;
            var totalErrors = [];

            // Setup UI for batch creation
            $btn.prop('disabled', true);
            $cancelBtn.show();
            $result.hide();
            $progressContainer.show();
            $('#pt-batch-size, #pt-create-all, #pt-use-default-password').prop('disabled', true);

            // Cancel handler
            $cancelBtn.off('click').on('click', function() {
                cancelled = true;
                $(this).prop('disabled', true).text('جاري الإيقاف...');
            });

            // Recursive batch creation function
            function createBatch(offset) {
                if (cancelled) {
                    finishCreation('تم إيقاف العملية من قبل المستخدم', false);
                    return;
                }

                $.post(ptAdmin.ajaxurl, {
                    action: 'pt_bulk_create_users',
                    nonce: ptAdmin.nonce,
                    use_default_password: useDefaultPassword ? 1 : 0,
                    batch_size: createAll ? 0 : batchSize,
                    offset: offset
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        totalCreated += data.created;
                        totalSkipped += data.skipped;

                        // Collect errors
                        if (data.errors && data.errors.length > 0) {
                            totalErrors = totalErrors.concat(data.errors);
                        }

                        // Update progress bar
                        var percentage = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 100;
                        $progressFill.css('width', percentage + '%');
                        $progressText.text(percentage + '%');

                        // Update stats
                        $progressStats.html(
                            'تم إنشاء: <strong>' + totalCreated + '</strong> من <strong>' + data.total + '</strong><br>' +
                            'تم المعالجة: ' + data.processed + ' | المتبقي: ' + data.remaining +
                            (totalSkipped > 0 ? ' | تم التخطي: ' + totalSkipped : '')
                        );

                        // Continue or finish
                        if (!data.complete && !cancelled) {
                            // Recurse to next batch
                            createBatch(data.processed);
                        } else {
                            finishCreation(data.message, false);
                        }
                    } else {
                        finishCreation(response.data.message || 'حدث خطأ', true);
                    }
                }).fail(function() {
                    finishCreation('حدث خطأ في الاتصال', true);
                });
            }

            // Finish creation and cleanup
            function finishCreation(message, isError) {
                $btn.prop('disabled', false);
                $cancelBtn.hide().prop('disabled', false).text('إيقاف');
                $('#pt-batch-size, #pt-create-all, #pt-use-default-password').prop('disabled', false);

                var finalMessage = message;
                if (totalErrors.length > 0) {
                    finalMessage += '\n\nأخطاء (' + totalErrors.length + ')';
                    if (totalErrors.length <= 5) {
                        finalMessage += ':\n' + totalErrors.join('\n');
                    } else {
                        finalMessage += ':\n' + totalErrors.slice(0, 5).join('\n') + '\n... و ' + (totalErrors.length - 5) + ' أخطاء أخرى';
                    }
                }

                $result.removeClass('success error loading')
                    .addClass(isError ? 'error' : 'success')
                    .text(finalMessage)
                    .show();

                // Reload page on success
                if (!isError && totalCreated > 0) {
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                }
            }

            // Start batch creation from offset 0
            createBatch(0);
        });

        // Add database indexes
        $('#pt-add-indexes').on('click', function() {
            if (!confirm('هل تريد إضافة فهارس لقاعدة البيانات لتحسين الأداء؟')) {
                return;
            }

            var $btn = $(this);
            var $result = $('#pt-add-indexes-result');

            $btn.prop('disabled', true);
            $result.removeClass('success error').addClass('loading').text('جاري إضافة الفهارس...').show();

            $.post(ptAdmin.ajaxurl, {
                action: 'pt_add_indexes',
                nonce: ptAdmin.nonce
            }, function(response) {
                $btn.prop('disabled', false);
                $result.removeClass('loading');

                if (response.success) {
                    $result.addClass('success').html(response.data.message);
                } else {
                    $result.addClass('error').html(response.data.message || 'حدث خطأ');
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $result.removeClass('loading').addClass('error').text('حدث خطأ في الاتصال');
            });
        });

        // Manual connect
        $('#pt-manual-connect').on('click', function() {
            var traderId = $('#pt-trader-select').val();
            var userId = $('#pt-user-select').val();
            var $result = $('#pt-manual-connect-result');
            
            if (!traderId || !userId) {
                $result.removeClass('success loading').addClass('error').text('يرجى اختيار التاجر والمستخدم').show();
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true);
            $result.removeClass('success error').addClass('loading').text('جاري الربط...').show();
            
            $.post(ptAdmin.ajaxurl, {
                action: 'pt_connect_trader',
                trader_id: traderId,
                user_id: userId,
                nonce: ptAdmin.nonce
            }, function(response) {
                $btn.prop('disabled', false);
                $result.removeClass('loading');
                
                if (response.success) {
                    $result.addClass('success').text(response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.addClass('error').text(response.data.message || 'حدث خطأ');
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $result.removeClass('loading').addClass('error').text('حدث خطأ في الاتصال');
            });
        });
        
        // Quick connect buttons
        $(document).on('click', '.pt-quick-connect', function() {
            var $btn = $(this);
            var traderId = $btn.data('trader');
            var userId = $btn.data('user');
            
            $btn.prop('disabled', true).text('جاري...');
            
            $.post(ptAdmin.ajaxurl, {
                action: 'pt_connect_trader',
                trader_id: traderId,
                user_id: userId,
                nonce: ptAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    $btn.prop('disabled', false).text('ربط');
                    alert(response.data.message || 'حدث خطأ');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('ربط');
                alert('حدث خطأ في الاتصال');
            });
        });
        
        // Create user button
        $(document).on('click', '.pt-create-user-btn', function() {
            if (!confirm('هل تريد إنشاء حساب لهذا التاجر؟')) {
                return;
            }
            
            var $btn = $(this);
            var traderId = $btn.data('trader');
            
            $btn.prop('disabled', true).text('جاري...');
            
            $.post(ptAdmin.ajaxurl, {
                action: 'pt_create_user_for_trader',
                trader_id: traderId,
                nonce: ptAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                    alert(response.data.message);
                } else {
                    $btn.prop('disabled', false).text('إنشاء حساب');
                    alert(response.data.message || 'حدث خطأ');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('إنشاء حساب');
                alert('حدث خطأ في الاتصال');
            });
        });
        
        // Regenerate default password
        $('#pt-regenerate-default-password').on('click', function() {
            if (!confirm('هل تريد إنشاء كلمة مرور افتراضية جديدة؟ سيتم استخدامها للمستخدمين الجدد فقط.')) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('جاري...');
            
            $.post(ptAdmin.ajaxurl, {
                action: 'pt_regenerate_default_password',
                nonce: ptAdmin.nonce
            }, function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'حدث خطأ');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('إنشاء كلمة مرور جديدة');
                alert('حدث خطأ في الاتصال');
            });
        });

        // ========================================
        // Unconnected Traders List with AJAX Pagination
        // ========================================

        // Global state variables
        var ptTradersCurrentPage = 1;
        var ptTradersPerPage = 20;
        var ptTradersSearchTerm = '';
        var ptTradersSearchTimeout = null;
        var ptTradersIsLoading = false;

        // Main function to load traders list
        window.ptLoadUnconnectedTraders = function(page, search) {
            if (ptTradersIsLoading) return;

            page = page || ptTradersCurrentPage;
            search = (typeof search !== 'undefined') ? search : ptTradersSearchTerm;

            ptTradersIsLoading = true;
            ptTradersCurrentPage = page;
            ptTradersSearchTerm = search;

            var $tbody = $('#pt-traders-tbody');
            var $pagination = $('#pt-pagination-container');
            var $searchStatus = $('#pt-search-status');

            // Show loading state
            $tbody.html(
                '<tr><td colspan="4" style="text-align: center; padding: 20px;">' +
                '<span class="spinner is-active" style="float: none;"></span> جاري التحميل...' +
                '</td></tr>'
            );
            $pagination.hide();

            // AJAX request
            $.post(ptAdmin.ajaxurl, {
                action: 'pt_get_unconnected_traders',
                nonce: ptAdmin.nonce,
                page: page,
                per_page: ptTradersPerPage,
                search: search
            }, function(response) {
                ptTradersIsLoading = false;

                if (response.success) {
                    var data = response.data;

                    // Update table body
                    $tbody.html(data.html);

                    // Update pagination
                    if (data.total > 0) {
                        ptRenderPagination(data.current_page, data.total_pages, data.total);
                        $pagination.show();
                    } else {
                        $pagination.hide();
                    }

                    // Update search status
                    if (search) {
                        $searchStatus.html('نتائج البحث: <strong>' + data.total + '</strong> تاجر').show();
                    } else {
                        $searchStatus.hide();
                    }
                } else {
                    $tbody.html(
                        '<tr><td colspan="4" style="text-align: center; color: #991b1b;">' +
                        (response.data.message || 'حدث خطأ') + '</td></tr>'
                    );
                }
            }).fail(function() {
                ptTradersIsLoading = false;
                $tbody.html(
                    '<tr><td colspan="4" style="text-align: center; color: #991b1b;">' +
                    'حدث خطأ في الاتصال</td></tr>'
                );
            });
        };

        // Render pagination controls
        function ptRenderPagination(currentPage, totalPages, totalItems) {
            var $info = $('#pt-pagination-info');
            var $controls = $('#pt-pagination-controls');

            // Pagination info (e.g., "Showing 1-20 of 150")
            var startItem = ((currentPage - 1) * ptTradersPerPage) + 1;
            var endItem = Math.min(currentPage * ptTradersPerPage, totalItems);
            $info.html(
                'عرض <strong>' + startItem + '</strong> - <strong>' + endItem +
                '</strong> من أصل <strong>' + totalItems + '</strong> تاجر'
            );

            // Pagination controls
            var html = '<div class="tablenav-pages">';

            // Previous button
            if (currentPage > 1) {
                html += '<button type="button" class="button pt-page-nav" data-page="' + (currentPage - 1) + '">السابق</button> ';
            }

            // Page numbers (show current +/- 2 pages)
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, currentPage + 2);

            // First page (if not in range)
            if (startPage > 1) {
                html += '<button type="button" class="button pt-page-number" data-page="1">1</button> ';
                if (startPage > 2) {
                    html += '<span class="pt-page-dots">...</span> ';
                }
            }

            // Page numbers around current
            for (var i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    html += '<button type="button" class="button button-primary pt-page-number" data-page="' + i + '">' + i + '</button> ';
                } else {
                    html += '<button type="button" class="button pt-page-number" data-page="' + i + '">' + i + '</button> ';
                }
            }

            // Last page (if not in range)
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += '<span class="pt-page-dots">...</span> ';
                }
                html += '<button type="button" class="button pt-page-number" data-page="' + totalPages + '">' + totalPages + '</button> ';
            }

            // Next button
            if (currentPage < totalPages) {
                html += '<button type="button" class="button pt-page-nav" data-page="' + (currentPage + 1) + '">التالي</button>';
            }

            html += '</div>';
            $controls.html(html);
        }

        // Search input handler (with 500ms debounce)
        $(document).on('input', '#pt-traders-search', function() {
            var searchTerm = $(this).val().trim();
            var $clearBtn = $('#pt-search-clear');

            // Show/hide clear button
            $clearBtn.toggle(searchTerm.length > 0);

            // Debounce search (wait 500ms after last keystroke)
            clearTimeout(ptTradersSearchTimeout);
            ptTradersSearchTimeout = setTimeout(function() {
                ptLoadUnconnectedTraders(1, searchTerm);
            }, 500);
        });

        // Clear search button
        $(document).on('click', '#pt-search-clear', function() {
            $('#pt-traders-search').val('');
            $('#pt-search-clear').hide();
            ptLoadUnconnectedTraders(1, '');
        });

        // Pagination click handlers (Previous, Next, page numbers)
        $(document).on('click', '.pt-page-nav, .pt-page-number', function() {
            var page = parseInt($(this).data('page'));
            if (page && !ptTradersIsLoading) {
                ptLoadUnconnectedTraders(page);

                // Smooth scroll to table
                $('html, body').animate({
                    scrollTop: $('#pt-traders-table-container').offset().top - 50
                }, 300);
            }
        });

        // Initialize unconnected traders list on page load
        if ($('#pt-traders-tbody').length) {
            window.ptTradersPerPage = 20;
            ptLoadUnconnectedTraders(1);
        }

        // ========================================
        // Email Settings Save/Reset Handlers
        // ========================================

        // Save email settings
        $('#pt-save-email-settings').on('click', function() {
            var $btn = $(this);
            var $result = $('#pt-email-settings-result');

            // Get values
            var fromName = $('#pt-email-from-name').val().trim();
            var fromEmail = $('#pt-email-from-email').val().trim();
            var replyTo = $('#pt-email-reply-to').val().trim();
            var cc = $('#pt-email-cc').val().trim();
            var bcc = $('#pt-email-bcc').val().trim();

            // Basic client-side validation
            if (!fromName) {
                $result.removeClass('success loading').addClass('error')
                    .text('اسم المرسل مطلوب').show();
                $('#pt-email-from-name').focus();
                return;
            }

            if (!fromEmail) {
                $result.removeClass('success loading').addClass('error')
                    .text('إيميل المرسل مطلوب').show();
                $('#pt-email-from-email').focus();
                return;
            }

            // Email format validation (basic)
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(fromEmail)) {
                $result.removeClass('success loading').addClass('error')
                    .text('إيميل المرسل غير صالح').show();
                $('#pt-email-from-email').focus();
                return;
            }

            // Validate reply-to if provided
            if (replyTo && !emailRegex.test(replyTo)) {
                $result.removeClass('success loading').addClass('error')
                    .text('عنوان الرد غير صالح').show();
                $('#pt-email-reply-to').focus();
                return;
            }

            // Disable button and show loading
            $btn.prop('disabled', true).text('جاري الحفظ...');
            $result.removeClass('success error').addClass('loading')
                .text('جاري حفظ الإعدادات...').show();

            // AJAX request
            $.post(ptAdmin.ajaxurl, {
                action: 'pt_save_email_settings',
                nonce: ptAdmin.nonce,
                from_name: fromName,
                from_email: fromEmail,
                reply_to: replyTo,
                cc: cc,
                bcc: bcc
            }, function(response) {
                $btn.prop('disabled', false).text('حفظ الإعدادات');
                $result.removeClass('loading');

                if (response.success) {
                    $result.addClass('success').text(response.data.message);

                    // Hide success message after 3 seconds
                    setTimeout(function() {
                        $result.fadeOut();
                    }, 3000);
                } else {
                    $result.addClass('error').text(response.data.message || 'حدث خطأ');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('حفظ الإعدادات');
                $result.removeClass('loading').addClass('error')
                    .text('حدث خطأ في الاتصال');
            });
        });

        // Reset email settings to defaults
        $('#pt-reset-email-settings').on('click', function() {
            if (!confirm('هل تريد إعادة تعيين جميع إعدادات البريد الإلكتروني للقيم الافتراضية؟')) {
                return;
            }

            var $btn = $(this);
            var $result = $('#pt-email-settings-result');

            $btn.prop('disabled', true).text('جاري الإعادة...');
            $result.removeClass('success error').addClass('loading')
                .text('جاري إعادة التعيين...').show();

            $.post(ptAdmin.ajaxurl, {
                action: 'pt_reset_email_settings',
                nonce: ptAdmin.nonce
            }, function(response) {
                $btn.prop('disabled', false).text('إعادة تعيين للافتراضي');
                $result.removeClass('loading');

                if (response.success) {
                    $result.addClass('success').text(response.data.message);

                    // Update form fields with defaults
                    if (response.data.defaults) {
                        $('#pt-email-from-name').val(response.data.defaults.from_name);
                        $('#pt-email-from-email').val(response.data.defaults.from_email);
                        $('#pt-email-reply-to').val(response.data.defaults.reply_to);
                        $('#pt-email-cc').val(response.data.defaults.cc);
                        $('#pt-email-bcc').val(response.data.defaults.bcc);
                    }

                    // Hide success message after 3 seconds
                    setTimeout(function() {
                        $result.fadeOut();
                    }, 3000);
                } else {
                    $result.addClass('error').text(response.data.message || 'حدث خطأ');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('إعادة تعيين للافتراضي');
                $result.removeClass('loading').addClass('error')
                    .text('حدث خطأ في الاتصال');
            });
        });

        // ========================================
        // Trader Search for Manual Connection
        // ========================================

        var traderSearchTimeout = null;

        // Search input handler with debounce
        $(document).on('input', '#pt-trader-search', function() {
            var searchTerm = $(this).val().trim();
            var $suggestions = $('#pt-trader-suggestions');

            // Clear hidden value when user types
            $('#pt-trader-select').val('');

            if (searchTerm.length < 2) {
                $suggestions.hide().empty();
                return;
            }

            // Show loading
            $suggestions.html('<div class="pt-suggestion-item pt-suggestion-loading">جاري البحث...</div>').show();

            clearTimeout(traderSearchTimeout);
            traderSearchTimeout = setTimeout(function() {
                $.post(ptAdmin.ajaxurl, {
                    action: 'pt_search_traders',
                    search: searchTerm,
                    limit: 20,
                    nonce: ptAdmin.nonce
                }, function(response) {
                    if (response.success && response.data.results.length > 0) {
                        var html = '';
                        response.data.results.forEach(function(trader) {
                            html += '<div class="pt-suggestion-item" data-id="' + trader.id + '" data-text="' + escapeHtml(trader.text) + '">';
                            html += '<strong>' + escapeHtml(trader.name) + '</strong>';
                            if (trader.email) {
                                html += '<span class="pt-suggestion-email">' + escapeHtml(trader.email) + '</span>';
                            }
                            if (trader.commercial_register) {
                                html += '<span class="pt-suggestion-cr">السجل: ' + escapeHtml(trader.commercial_register) + '</span>';
                            }
                            html += '</div>';
                        });
                        $suggestions.html(html).show();
                    } else {
                        $suggestions.html('<div class="pt-suggestion-item pt-suggestion-empty">لا توجد نتائج</div>').show();
                    }
                }).fail(function() {
                    $suggestions.html('<div class="pt-suggestion-item pt-suggestion-error">حدث خطأ في البحث</div>').show();
                });
            }, 300);
        });

        // Click on suggestion to select trader
        $(document).on('click', '#pt-trader-suggestions .pt-suggestion-item[data-id]', function() {
            var $item = $(this);
            var traderId = $item.data('id');
            var text = $item.data('text');

            $('#pt-trader-select').val(traderId);
            $('#pt-trader-search').val(text);
            $('#pt-trader-suggestions').hide().empty();
        });

        // Close suggestions on Escape key
        $(document).on('keydown', '#pt-trader-search', function(e) {
            if (e.keyCode === 27) { // Escape
                $('#pt-trader-suggestions').hide().empty();
            }
        });

        // Close suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.pt-trader-search-container').length) {
                $('#pt-trader-suggestions').hide().empty();
            }
            if (!$(e.target).closest('.pt-user-search-container').length) {
                $('#pt-user-suggestions').hide().empty();
            }
        });

        // ========================================
        // User Search for Manual Connection
        // ========================================

        var userSearchTimeout = null;

        // User search input handler with debounce
        $(document).on('input', '#pt-user-search', function() {
            var searchTerm = $(this).val().trim();
            var $suggestions = $('#pt-user-suggestions');

            // Clear hidden value when user types
            $('#pt-user-select').val('');

            if (searchTerm.length < 2) {
                $suggestions.hide().empty();
                return;
            }

            // Show loading
            $suggestions.html('<div class="pt-suggestion-item pt-suggestion-loading">جاري البحث...</div>').show();

            clearTimeout(userSearchTimeout);
            userSearchTimeout = setTimeout(function() {
                $.post(ptAdmin.ajaxurl, {
                    action: 'pt_search_users',
                    search: searchTerm,
                    limit: 20,
                    nonce: ptAdmin.nonce
                }, function(response) {
                    if (response.success && response.data.results.length > 0) {
                        var html = '';
                        response.data.results.forEach(function(user) {
                            html += '<div class="pt-suggestion-item" data-id="' + user.id + '" data-text="' + escapeHtml(user.text) + '">';
                            html += '<strong>' + escapeHtml(user.name) + '</strong>';
                            if (user.email) {
                                html += '<span class="pt-suggestion-email">' + escapeHtml(user.email) + '</span>';
                            }
                            html += '</div>';
                        });
                        $suggestions.html(html).show();
                    } else {
                        $suggestions.html('<div class="pt-suggestion-item pt-suggestion-empty">لا توجد نتائج</div>').show();
                    }
                }).fail(function() {
                    $suggestions.html('<div class="pt-suggestion-item pt-suggestion-error">حدث خطأ في البحث</div>').show();
                });
            }, 300);
        });

        // Click on user suggestion to select
        $(document).on('click', '#pt-user-suggestions .pt-suggestion-item[data-id]', function() {
            var $item = $(this);
            var userId = $item.data('id');
            var text = $item.data('text');

            $('#pt-user-select').val(userId);
            $('#pt-user-search').val(text);
            $('#pt-user-suggestions').hide().empty();
        });

        // Close user suggestions on Escape key
        $(document).on('keydown', '#pt-user-search', function(e) {
            if (e.keyCode === 27) { // Escape
                $('#pt-user-suggestions').hide().empty();
            }
        });

        // Helper function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        // ========================================
        // Analytics Dashboard
        // ========================================

        var ptViewsChart = null;

        // Initialize analytics if on analytics page
        if ($('#pt-views-chart').length) {
            ptInitAnalytics();
        }

        function ptInitAnalytics() {
            // Wait for Chart.js to load
            if (typeof Chart === 'undefined') {
                setTimeout(ptInitAnalytics, 100);
                return;
            }

            // Load chart data
            ptLoadChartData();

            // Load top posts
            ptLoadTopPosts();
        }

        function ptLoadChartData() {
            var period = $('#pt-analytics-period').val() || '30';
            var postType = $('#pt-analytics-post-type').val() || 'all';

            $.post(ptAdmin.ajaxurl, {
                action: 'pt_get_ad_views_chart',
                nonce: ptAdmin.nonce,
                period: period,
                post_type: postType
            }, function(response) {
                if (response.success) {
                    ptRenderChart(response.data);
                }
            });
        }

        function ptRenderChart(data) {
            var ctx = document.getElementById('pt-views-chart');
            if (!ctx) return;

            // Destroy existing chart
            if (ptViewsChart) {
                ptViewsChart.destroy();
            }

            ptViewsChart = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'المشاهدات',
                        data: data.values,
                        borderColor: '#0A4E45',
                        backgroundColor: 'rgba(10, 78, 69, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0A4E45',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#0A4E45',
                            padding: 12,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        }

        function ptLoadTopPosts() {
            var $tbody = $('#pt-top-posts-tbody');
            var period = $('#pt-analytics-period').val() || '30';
            var postType = $('#pt-analytics-post-type').val() || 'all';

            $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float: none;"></span></td></tr>');

            $.post(ptAdmin.ajaxurl, {
                action: 'pt_get_top_posts',
                nonce: ptAdmin.nonce,
                days: period,
                post_type: postType
            }, function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '';
                    response.data.forEach(function(post) {
                        var typeClass = 'pt-type-' + post.type_raw;
                        html += '<tr>';
                        html += '<td class="pt-post-title"><a href="' + post.url + '" target="_blank">' + escapeHtml(post.title) + '</a></td>';
                        html += '<td><span class="pt-post-type ' + typeClass + '">' + post.type + '</span></td>';
                        html += '<td class="pt-views-count">' + post.views.toLocaleString() + '</td>';
                        html += '<td class="pt-views-today">+' + post.views_today + '</td>';
                        html += '<td><a href="' + post.edit_url + '" class="button button-small">تعديل</a></td>';
                        html += '</tr>';
                    });
                    $tbody.html(html);
                } else {
                    $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 30px; color: #6b7280;">لا توجد مشاهدات في هذه الفترة</td></tr>');
                }
            }).fail(function() {
                $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 30px; color: #dc2626;">حدث خطأ في تحميل البيانات</td></tr>');
            });
        }

        // Period/type filter change
        $(document).on('change', '#pt-analytics-period, #pt-analytics-post-type', function() {
            ptLoadChartData();
            ptLoadTopPosts();
        });

        // Refresh top posts button
        $(document).on('click', '#pt-refresh-top-posts', function() {
            ptLoadTopPosts();
        });

    });

})(jQuery);

