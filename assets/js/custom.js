/**
 * Created by SARFRAZ on 7/27/14
 */

// Detect mobile devices and disable VQB functionality
$(document).ready(function () {
    // Function to detect mobile devices - only disable VQB on true mobile phones
    function isMobileDevice() {
        return /Android.*Mobile|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
            (window.innerWidth <= 480 && /Android|Mobile/i.test(navigator.userAgent));
    }

    // Fix Select2 search issue by ensuring search is always enabled
    // We'll handle this in individual Select2 initializations rather than a global override
    console.log('Mobile device detection and Select2 search fix initialized');

    // If mobile device detected, disable VQB functionality
    if (isMobileDevice()) {
        // Hide VQB-related buttons and show warning
        $('#btnVisualQueryBuilder, .btn-edit-saved-query').hide();

        // Show warning message for mobile users
        $('body').prepend('<div class="alert alert-warning" style="margin: 10px;"><i class="fa fa-mobile"></i> <strong>Notice:</strong> Visual Query Builder is not available on mobile devices. Please use a desktop or tablet for full VQB functionality.</div>');

        console.log('Mobile device detected - VQB functionality disabled');
    }
});

// select active navigation item
$('.sidebar-nav a').parents('li').removeClass('activelink');
$('.sidebar-nav a[href$="' + lastSegment + '"]').parents('li').addClass('activelink');

// style tables
if ($('table tr').length) {
    // General datatable initialization with horizontal scroll
    $('.page-content table').not('table.nodatatable, #etl_log_table').dataTable({
        sPaginationType: "full_numbers",
        bAutoWidth: false,
        autoWidth: false,
        bLengthChange: true,
        iDisplayLength: 10,
        scrollX: 400
    });

    // Specific initialization for ETL Log table without horizontal scroll
    if ($('#etl_log_table').length) {
        $('#etl_log_table').dataTable({
            sPaginationType: "full_numbers",
            bAutoWidth: false,
            autoWidth: false,
            bLengthChange: true,
            iDisplayLength: 10
            // No scrollX here
        });
    }
}

// replace selects with select2 - BEWARE: This global initializer might be problematic for hidden templates.
// It's generally better to initialize Select2 specifically when elements are shown or activated.
// For now, we'll make it slightly more specific to avoid direct init on known template contents.
var initialSelect2Selector = 'select:not(#fieldClone select, #fieldCloneTable select, #fieldCloneAggregate select, #fieldCloneHaving select)';
$(initialSelect2Selector).each(function () {
    var $this = $(this);
    var options = {
        placeholder: 'Choose',
        allowClear: true,
        minimumInputLength: 0,
        minimumResultsForSearch: 0 // Always enable search
    };
    if ($this.closest('.modal').length) {
        options.dropdownParent = $this.closest('.modal');
    }
    console.log('DEBUG: Global Select2 initialization for element:', $this[0], 'with options:', options);
    $this.select2(options);
});


// for tooltips
$(".tip").tooltip();

// inline bootstrap editable
$.fn.editable.defaults.mode = 'popup';

$('a.editable').editable({
    validate: function (value) {
        if ($.trim(value) == '') return 'Cannot be empty!';
    }
});

// --- Table Formatting Modal ---
$('body').on('click', '#btnShowTableFormatModal', function () {
    var queryId = $(this).data('query-id');
    if (!queryId) {
        $.jGrowl('Error: Query ID is missing. Cannot open formatting modal.', { header: 'Error', theme: 'error' });
        return;
    }

    $('#table_format_query_id').val(queryId);
    var $modal = $('#modal-table-format');
    var $fieldsContainer = $('#tableFormatFieldsContainer');
    var $msgContainer = $('#tableFormatMsg');
    $fieldsContainer.empty().append('<p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading columns...</p>');
    $msgContainer.hide().removeClass('alert-success alert-danger alert-info').text('');

    // Fetch current column headers from the displayed table, now with data-original-name
    var columns = [];
    var $tableInScrollHead = $('#tabledata .dataTables_scrollHead table.dataTable');
    var $thElements;

    if ($tableInScrollHead.length > 0) {
        $thElements = $tableInScrollHead.find('thead tr:first-child th');
    } else {
        var $mainTable = $('#tabledata table.dataTable:first');
        if ($mainTable.length > 0) {
            $thElements = $mainTable.find('thead tr:first-child th');
        } else {
            $thElements = $('#tabledata table:first').find('thead tr:first-child th');
        }
    }

    if ($thElements && $thElements.length > 0) {
        $thElements.each(function () {
            var $th = $(this);
            columns.push({
                originalName: $th.data('original-name'),
                displayName: $th.text()
            });
        });
    }

    if (columns.length === 0) {
        $fieldsContainer.empty().append('<p class="text-danger">Could not find table headers on the page.</p>');
        $modal.modal('show');
        return;
    }

    // Fetch existing formatting for this query_id
    $.ajax({
        url: base + '/ajax/getTableFormatting/' + queryId,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            $fieldsContainer.empty(); // Clear "Loading..."
            var existingColumnTitles = {};
            if (response.status === 'success' && response.table_formatting && response.table_formatting.column_titles) {
                existingColumnTitles = response.table_formatting.column_titles;
                $msgContainer.addClass('alert-info').text('Loaded existing formatting.').show().delay(2000).fadeOut();
            } else if (response.status !== 'success' && response.message) {
                $msgContainer.addClass('alert-warning').text(response.message).show();
            }

            columns.forEach(function (column, index) {
                if (!column.originalName) {
                    console.warn("Skipping a column in formatting modal because it's missing 'data-original-name' attribute.");
                    return; // a th without the attribute cannot be formatted
                }

                var inputId = 'th_format_' + index;
                // Look up the saved title using the *original* database column name
                var savedTitle = existingColumnTitles[column.originalName] || '';

                var fieldHtml = '<div class="form-group col-md-4">' +
                    // The label shows the *currently displayed* name for user context
                    '<label for="' + inputId + '">Current: ' + escapeHtml(column.displayName) + '</label>' +
                    // The input's name attribute is the *original* name, which is the key for saving
                    '<input type="text" class="form-control" id="' + inputId + '" name="header_titles[' + escapeHtml(column.originalName) + ']" value="' + escapeHtml(savedTitle) + '" placeholder="New Title (default: ' + escapeHtml(column.originalName) + ')">' +
                    '</div>';
                $fieldsContainer.append(fieldHtml);
            });
            $modal.modal('show');
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $fieldsContainer.empty();
            $msgContainer.addClass('alert-danger').text('AJAX Error fetching formatting: ' + textStatus + ' - ' + errorThrown).show();
            $modal.modal('show'); // Still show modal but with error
        }
    });
});

// --- Share Query Modal Functionality ---
$('body').on('click', '.btn-share-query', function () {
    var queryId = $(this).data('query-id');
    var queryName = $(this).data('query-name'); // Assuming query name is also available on the button

    var $modal = $('#modal-share-query');
    var $linkInput = $('#shareableLinkInput');
    var $queryNameDisplay = $('#shareQueryName');
    var $copyBtn = $('#btnCopyShareLink');
    var $shareLinkMsg = $('#shareLinkMsg');
    var $requireLoginCheckbox = $('#shareRequireLoginCheckbox');
    var $shareSettingsMsg = $('#shareSettingsMsg');
    var $generateLinkBtn = $('#btnGenerateShareLink');
    var $generateLinkMsg = $('#generateLinkMsg');

    $modal.data('current-query-id', queryId); // Store queryId on the modal for the checkbox handler
    $queryNameDisplay.text(queryName || 'Selected Query');
    $linkInput.val(''); // Clear the input initially
    $shareLinkMsg.hide();
    $shareSettingsMsg.hide().removeClass('text-success text-danger').text('');
    $generateLinkMsg.hide().removeClass('text-success text-danger').text('');
    $copyBtn.prop('disabled', true);
    $requireLoginCheckbox.prop('checked', false).prop('disabled', true); // Disable until loaded
    $generateLinkBtn.prop('disabled', true); // Disable until loaded

    $modal.modal('show');

    $.ajax({
        url: base + '/ajax/getShareToken/' + queryId,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                if (response.share_url) {
                    $linkInput.val(response.share_url);
                    $copyBtn.prop('disabled', false);
                    $generateLinkBtn.text('Regenerate Share Link').removeClass('btn-primary').addClass('btn-warning');
                } else {
                    // No token/URL yet.
                    $linkInput.attr('placeholder', 'Click "Generate Share Link" to create a shareable URL');
                    $copyBtn.prop('disabled', true); // Disable copy if no URL
                    $generateLinkBtn.text('Generate Share Link').removeClass('btn-warning').addClass('btn-primary');
                }
                $requireLoginCheckbox.prop('checked', response.requires_login || false);
            } else {
                $linkInput.attr('placeholder', 'Error loading share settings');
                $.jGrowl(response.message || 'Error fetching share link.', { header: 'Error', theme: 'error' });
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $linkInput.attr('placeholder', 'Error loading share settings');
            $.jGrowl('AJAX Error: ' + textStatus + ' - ' + errorThrown, { header: 'AJAX Error', theme: 'error' });
        },
        complete: function () {
            $requireLoginCheckbox.prop('disabled', false); // Enable checkbox after loading
            $generateLinkBtn.prop('disabled', false); // Enable generate button after loading
        }
    });
});

// --- Generate Share Link Button Handler ---
$('body').on('click', '#btnGenerateShareLink', function () {
    var queryId = $('#modal-share-query').data('current-query-id');
    var $thisButton = $(this);
    var $linkInput = $('#shareableLinkInput');
    var $copyBtn = $('#btnCopyShareLink');
    var $generateLinkMsg = $('#generateLinkMsg');

    $thisButton.prop('disabled', true).find('i').removeClass('fa-link').addClass('fa-spinner fa-spin');
    $generateLinkMsg.hide().removeClass('text-success text-danger').text('');

    $.ajax({
        url: base + '/ajax/generateShareToken',
        type: 'POST',
        data: { query_id: queryId },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                $linkInput.val(response.share_url);
                $copyBtn.prop('disabled', false);
                $generateLinkMsg.addClass('text-success').text(response.message).fadeIn();
                $thisButton.text('Regenerate Share Link').removeClass('btn-primary').addClass('btn-warning');

                // Auto-hide success message after 3 seconds
                setTimeout(function () {
                    $generateLinkMsg.fadeOut();
                }, 3000);
            } else {
                $generateLinkMsg.addClass('text-danger').text(response.message || 'Failed to generate share link').fadeIn();
                $.jGrowl(response.message || 'Error generating share link.', { header: 'Error', theme: 'error' });
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $generateLinkMsg.addClass('text-danger').text('AJAX Error: Could not generate share link').fadeIn();
            $.jGrowl('AJAX Error: ' + textStatus + ' - ' + errorThrown, { header: 'AJAX Error', theme: 'error' });
        },
        complete: function () {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-link');
        }
    });
});

// Handler for the "Require Login" checkbox change
$('body').on('change', '#shareRequireLoginCheckbox', function () {
    var queryId = $('#modal-share-query').data('current-query-id');
    var requireLogin = $(this).is(':checked');
    var $shareSettingsMsg = $('#shareSettingsMsg');
    var $linkInput = $('#shareableLinkInput');
    var $copyBtn = $('#btnCopyShareLink');

    $shareSettingsMsg.hide().removeClass('text-success text-danger').text('Saving...');

    $.ajax({
        url: base + '/ajax/updateShareSettings',
        type: 'POST',
        data: {
            query_id: queryId,
            require_login: requireLogin
        },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                $shareSettingsMsg.addClass('text-success').text(response.message || 'Settings saved!').fadeIn().delay(2000).fadeOut();
                // Update checkbox state based on server response for consistency, though it should match user's click
                $requireLoginCheckbox.prop('checked', response.requires_login || false);

                if (response.share_url) {
                    $linkInput.val(response.share_url);
                    $copyBtn.prop('disabled', false);
                } else if (!response.requires_login) {
                    // If requireLogin is false and no token/URL (e.g., token was cleared or never generated for public)
                    $linkInput.val('No public link generated (login not required and no active link).');
                    $copyBtn.prop('disabled', true);
                } else if (response.requires_login && !response.share_url) {
                    // This case implies require_login is true, but backend failed to return/generate a URL/token.
                    // Ajax::updateShareSettings should always generate a token if require_login is true and no token exists.
                    $linkInput.val('Error: Link should be active but was not provided.');
                    $copyBtn.prop('disabled', true);
                } else { // Fallback, e.g. if requires_login true but no token
                    $linkInput.val('Link status unclear. Refresh modal.');
                    $copyBtn.prop('disabled', true);
                }
            } else {
                $shareSettingsMsg.addClass('text-danger').text(response.message || 'Failed to save settings.').fadeIn();
                // Revert checkbox state on failure? Or leave as is and let user retry?
                // For now, leave as is. User can try again.
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $shareSettingsMsg.addClass('text-danger').text('AJAX Error: Could not save settings. ' + textStatus).fadeIn();
        }
    });
});


$('body').on('click', '#btnCopyShareLink', function () {
    var $linkInput = $('#shareableLinkInput');
    var $shareLinkMsg = $('#shareLinkMsg');

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText($linkInput.val()).then(function () {
            $shareLinkMsg.text('Link copied to clipboard!').fadeIn().delay(2000).fadeOut();
        }).catch(function (err) {
            // Fallback for older browsers or if permission denied
            try {
                $linkInput[0].select();
                document.execCommand('copy');
                $shareLinkMsg.text('Link copied to clipboard! (fallback method)').fadeIn().delay(2000).fadeOut();
            } catch (e) {
                $shareLinkMsg.text('Failed to copy. Please copy manually.').removeClass('alert-success').addClass('alert-danger').fadeIn().delay(3000).fadeOut(function () {
                    $(this).removeClass('alert-danger').addClass('alert-success'); // Reset class
                });
            }
        });
    } else { // Fallback for very old browsers
        try {
            $linkInput[0].select();
            document.execCommand('copy');
            $shareLinkMsg.text('Link copied to clipboard! (fallback method)').fadeIn().delay(2000).fadeOut();
        } catch (e) {
            $shareLinkMsg.text('Failed to copy. Please copy manually.').removeClass('alert-success').addClass('alert-danger').fadeIn().delay(3000).fadeOut(function () {
                $(this).removeClass('alert-danger').addClass('alert-success'); // Reset class
            });
        }
    }
});


$('body').on('click', '#btnSaveTableFormatting', function () {
    var queryId = $('#table_format_query_id').val();
    var $msgContainer = $('#tableFormatMsg');
    var $thisButton = $(this);

    if (!queryId) {
        $msgContainer.removeClass('alert-success alert-info').addClass('alert-danger').text('Error: Query ID is missing.').show();
        return;
    }

    var columnTitles = {};
    $('#tableFormatFieldsContainer .form-control').each(function () {
        var originalHeaderKey = $(this).attr('name').match(/header_titles\[(.*?)\]/)[1];
        var newTitle = $(this).val();
        if ($.trim(newTitle) !== '') { // Only save non-empty titles
            columnTitles[originalHeaderKey] = $.trim(newTitle);
        }
    });

    var tableFormattingJson = JSON.stringify({ column_titles: columnTitles });

    $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');
    $msgContainer.hide().removeClass('alert-success alert-danger alert-info').text('');

    $.ajax({
        url: base + '/ajax/saveTableFormatting',
        type: 'POST',
        data: {
            query_id: queryId,
            table_formatting: tableFormattingJson
        },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                $msgContainer.removeClass('alert-danger alert-info').addClass('alert-success').text(response.message).show();
                setTimeout(function () {
                    $('#modal-table-format').modal('hide');
                    // Optionally, inform user to re-run query to see changes or try to apply dynamically (more complex)
                    $.jGrowl('Formatting saved. Re-run the query to see changes.', { header: 'Info', theme: 'info', life: 4000 });
                }, 1500);
            } else {
                $msgContainer.removeClass('alert-success alert-info').addClass('alert-danger').text(response.message || 'An unknown error occurred.').show();
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $msgContainer.removeClass('alert-success alert-info').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        },
        complete: function () {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
        }
    });
});


$('a.editable').editable();

// for popover
$('#modal-visual-query [rel=hover_popover]').popover({ "trigger": "hover", "placement": "right" });
$('[rel=hover_popover]').popover({ "trigger": "hover", "placement": "bottom" });

// run custom query
$('#btnCustomQuery').click(function () {
    var query = editor.getValue();
    var $input = $(this).closest('form').find('#cquery');
    var $form = $(this).closest('form').get(0);

    if (!$.trim(query)) {
        $.jGrowl('Please specify query first!', { sticky: false, header: 'Error' });
        return false;
    }

    $input.val(query);
    $form.submit();
});

// add query condition for visual query
$('#btnAddWhere').click(function () {
    var $clone = $('#fieldClone').clone().removeAttr('id'); // Good practice to remove ID from the parent clone if it had one.
    var $selectInClone = $clone.find('select.fname');

    // Destroy potential Select2 instance from the template before appending and re-initializing
    $selectInClone.select2('destroy');

    // Removing .select2-container manually might still be useful if destroy isn't perfect with older Select2 versions or specific CSS.
    // However, with a proper destroy, it should ideally not be necessary. Let's keep it for now as it was there.
    $clone.find('.select2-container').remove();

    $(this).after($clone); // Append the clone to the DOM

    // Initialize Select2 on the select element within the newly appended clone
    $selectInClone.select2({
        placeholder: 'Choose Field',
        allowClear: true,
        dropdownParent: $selectInClone.closest('.modal'),
        minimumResultsForSearch: 0 // Always enable search
    });
    $clone.slideDown('fast');
});

// add aggregate field for visual query
$('#btnAddAggregateField').click(function () {
    var $clone = $('#fieldCloneAggregate').clone().removeAttr('id'); // clone and remove id to avoid duplicates
    var $aggFieldSelect = $clone.find('.agg_field');

    // Destroy existing Select2 instance if any, then re-initialize
    $aggFieldSelect.select2('destroy');
    $('#aggregateFieldsContainer').append($clone);
    $aggFieldSelect.select2({
        placeholder: 'Select Field',
        allowClear: true,
        dropdownParent: $aggFieldSelect.closest('.modal'),
        minimumResultsForSearch: 0 // Always enable search
    });

    // No need to initialize select2 for agg_func unless specific styling/features are needed for it.
    $clone.slideDown('fast');
    updateHavingFieldNameOptions(); // Update HAVING field options when a new aggregate is added
});

// add Having condition for visual query
$('#btnAddHavingCondition').click(function () {
    var $clone = $('#fieldCloneHaving').clone().removeAttr('id');
    var $hfnameSelect = $clone.find('.hfname');

    // Destroy existing Select2 instance from the template clone if it had one (it shouldn't due to initialSelect2Selector)
    // but good for safety if template structure changes.
    // Also remove any stray select2 container divs that might have been cloned.
    $clone.find('.select2-container').remove();
    $hfnameSelect.select2('destroy');

    $('#havingConditionsContainer').append($clone);
    // $clone.slideDown('fast'); // slideDown can happen after options are set.
    // For now, keep original behavior of sliding then updating.

    // updateHavingFieldNameOptions will find this new row and initialize Select2 on its .hfname
    // with the correct options. No need to initialize Select2 on $hfnameSelect here.
    updateHavingFieldNameOptions();
    $clone.slideDown('fast'); // Ensure it's visible and then options are updated & Select2 initialized by the call above.
    // Or, call slideDown after updateHavingFieldNameOptions if preferred.
    // The original order was slideDown then update.
});

// When aggregate alias or group by fields change, update HAVING field name options
$('body').on('change', '.agg_alias, .groupfields', function () {
    updateHavingFieldNameOptions();
});

// Run Saved Query
$('body').on('click', '.btn-run-saved-query', function (e) {
    e.preventDefault();
    var queryId = $(this).data('query-id');
    var queryToRun = null;

    if (typeof savedQueriesCache !== 'undefined') {
        for (var i = 0; i < savedQueriesCache.length; i++) {
            if (savedQueriesCache[i].id == queryId) {
                queryToRun = savedQueriesCache[i];
                break;
            }
        }
    }

    if (queryToRun && queryToRun.sql_query) {
        var formAction = '';
        var isVisualQuery = queryToRun.is_visual_query == '1' || queryToRun.is_visual_query === true;

        if (isVisualQuery && queryToRun.visual_params) {
            // For VQB queries, extract the primary table and route to VQB controller
            try {
                var visualParams = JSON.parse(queryToRun.visual_params);
                var primaryTable = visualParams.primaryTable || 'unknown';
                formAction = base + '/vqb/' + encodeURIComponent(primaryTable);
            } catch (e) {
                console.error('Error parsing visual_params for saved VQB query:', e);
                // Fallback to regular table route if parsing fails
                formAction = base + '/table/run_saved_query';
                isVisualQuery = false;
            }
        } else {
            formAction = base + '/table/run_saved_query';
        }

        var $dynamicForm = $('<form>', {
            'action': formAction,
            'method': 'POST',
            'style': 'display:none;'
        }).append($('<input>', {
            'type': 'hidden',
            'name': 'cquery',
            'value': queryToRun.sql_query
        })).append($('<input>', { // Add query name as a hidden field
            'type': 'hidden',
            'name': 'running_saved_query_name',
            'value': queryToRun.query_name
        })).append($('<input>', { // Add query ID for VQB queries
            'type': 'hidden',
            'name': 'query_id',
            'value': queryId
        }));

        // Add visual params for VQB queries
        if (isVisualQuery && queryToRun.visual_params) {
            $dynamicForm.append($('<input>', {
                'type': 'hidden',
                'name': 'visual_params',
                'value': queryToRun.visual_params
            })).append($('<input>', {
                'type': 'hidden',
                'name': 'data_source_id',
                'value': queryToRun.source_connection_id
            }));
        }

        $('body').append($dynamicForm);
        $dynamicForm.submit();
        $dynamicForm.remove();

    } else {
        $.jGrowl('Could not find the SQL for the selected query. Please refresh.', { sticky: false, header: 'Error', theme: 'error' });
    }
});

// --- Reusable function to open and populate VQB Modal ---
// --- Reusable function to open and populate VQB Modal ---
function openVisualQueryBuilderModal(visualParamsObj, queryId, queryName, isEditingSaved, dataSourceId) {
    var $modal = $('#modal-visual-query');

    // Store the data source ID on the modal for use by other functions like join table button
    $modal.data('current-data-source-id', dataSourceId);

    // Helper to check for valid <option> HTML
    function _hasValidTableOptions(htmlString) {
        if (!htmlString || typeof htmlString !== 'string') {
            return false;
        }
        // A valid list should have more than just a single placeholder/dummy option.
        const optionCount = (htmlString.match(/<option/g) || []).length;
        return optionCount > 1;
    }

    // Main logic to populate and show the modal, moved into a function
    function _populateAndShowModal(tablesOptionsHtml) {
        // Clear/Reset VQB form elements
        $modal.find('select.fields').val(null).trigger('change.select2');
        $('#aggregateFieldsContainer').empty();
        $('#modal-visual-query form .parent').not('#fieldClone, #fieldCloneTable, #fieldCloneAggregate, #fieldCloneHaving, #orderby, #group, #limit').remove();
        $modal.find('select.orderfields').val(null).trigger('change.select2');
        $modal.find('input[name="chkDescending"]').prop('checked', false);
        $('#orderby').hide();
        $modal.find('select.groupfields').val(null).trigger('change.select2');
        $('#group').hide();
        $modal.find('input[name="limitStart"]').val('');
        $modal.find('input[name="limitNumRows"]').val('');
        $('#limit').hide();
        $('#havingConditionsContainer').empty();
        $modal.find('.cloned-join-row').remove();

        // Set primary table context
        __table = visualParamsObj.primaryTable || '';
        $modal.find('.vqb-table-name').text('Table: ' + (__table ? __table.toUpperCase() : 'UNKNOWN'));
        if (isEditingSaved && queryId) {
            $modal.find('#visual_query_id_edit').val(queryId);
        } else {
            $modal.find('#visual_query_id_edit').val('');
        }

        if (__table) {
            $modal.find('form').attr('action', base + '/table/' + __table);
        } else {
            $modal.find('form').attr('action', '');
        }

        // Populate Joins
        if (visualParamsObj.jointype && Array.isArray(visualParamsObj.jointype)) {
            var $joinTemplate = $('#fieldCloneTable');
            visualParamsObj.jointype.forEach(function (type, idx) {
                var $clone = $joinTemplate.clone().removeAttr('id').addClass('cloned-join-row');
                $clone.find('select.jointype').val(type);
                var $tableSelect = $clone.find('select.jointable');
                $tableSelect.html(tablesOptionsHtml).val(visualParamsObj.jointable[idx]); // Use the passed in HTML

                $('#btnJoinTable').before($clone);

                $clone.find('select').each(function () {
                    if ($(this).data('select2')) $(this).select2('destroy');
                    $(this).select2({
                        placeholder: 'Choose',
                        allowClear: true,
                        dropdownParent: $(this).closest('.modal'),
                        minimumResultsForSearch: 0 // Always enable search
                    });
                });
                $clone.show();

                populateJoinFieldDropdown(
                    $clone.find('select.joinfieldselected'),
                    visualParamsObj.jointable[idx],
                    visualParamsObj.joinfield[idx],
                    dataSourceId
                );
                if (visualParamsObj.joinfieldp && visualParamsObj.joinfieldp[idx]) {
                    $clone.find('select.joinfieldmain').data('saved-value', visualParamsObj.joinfieldp[idx]);
                }
            });
        }

        // Load options for all field dropdowns and then populate other VQB elements
        addTablesToDropdown(dataSourceId, function (success, fieldOptionsHtml) {
            if (success && fieldOptionsHtml) {
                if (visualParamsObj.fields && Array.isArray(visualParamsObj.fields)) {
                    $modal.find('select[name="fields[]"]').val(visualParamsObj.fields).trigger('change.select2');
                }
                $modal.find('.cloned-join-row').each(function () {
                    var $clonedJoinRow = $(this);
                    var $joinfieldmainSelect = $clonedJoinRow.find('select.joinfieldmain');
                    var savedValue = $joinfieldmainSelect.data('saved-value');
                    if (savedValue) {
                        $joinfieldmainSelect.val(savedValue).trigger('change.select2');
                    }
                });

                if (visualParamsObj.fname && Array.isArray(visualParamsObj.fname)) {
                    visualParamsObj.fname.forEach(function (name, idx) {
                        if (name && visualParamsObj.fvalue[idx]) {
                            var $c = $('#fieldClone').clone().removeAttr('id').show();
                            var $fnameSelect = $c.find('select.fname');
                            var $ftypeSelect = $c.find('select[name="ftype[]"]');
                            if ($fnameSelect.data('select2')) $fnameSelect.select2('destroy');
                            if ($ftypeSelect.data('select2')) $ftypeSelect.select2('destroy');
                            $fnameSelect.html(fieldOptionsHtml);
                            $fnameSelect.val(name);
                            $c.find('input[name="fvalue[]"]').val(visualParamsObj.fvalue[idx]);
                            if (idx > 0 && visualParamsObj.ftype[idx]) {
                                $ftypeSelect.val(visualParamsObj.ftype[idx]);
                            }
                            $('#btnAddWhere').after($c);
                            $fnameSelect.select2({
                                placeholder: 'Choose Field',
                                allowClear: true,
                                dropdownParent: $fnameSelect.closest('.modal'),
                                minimumResultsForSearch: 0 // Always enable search
                            });
                            $ftypeSelect.select2({
                                dropdownParent: $ftypeSelect.closest('.modal'),
                                minimumResultsForSearch: 0 // Always enable search
                            });
                            $fnameSelect.trigger('change');
                            $ftypeSelect.trigger('change');
                        }
                    });
                }

                if (visualParamsObj.agg_field && Array.isArray(visualParamsObj.agg_field)) {
                    var $aggContainer = $('#aggregateFieldsContainer');
                    visualParamsObj.agg_field.forEach(function (fieldValue, idx) {
                        if (fieldValue && visualParamsObj.agg_func[idx]) {
                            var $aggClone = $('#fieldCloneAggregate').clone().removeAttr('id').show();
                            var $aggFieldSelect = $aggClone.find('.agg_field');
                            var $aggFuncSelect = $aggClone.find('.agg_func');
                            if ($aggFieldSelect.data('select2')) $aggFieldSelect.select2('destroy');
                            if ($aggFuncSelect.data('select2')) $aggFuncSelect.select2('destroy');
                            $aggFieldSelect.html(fieldOptionsHtml);
                            $aggFieldSelect.val(fieldValue);
                            $aggFuncSelect.val(visualParamsObj.agg_func[idx]);
                            $aggClone.find('.agg_alias').val(visualParamsObj.agg_alias[idx] || '');
                            $aggContainer.append($aggClone);
                            $aggFieldSelect.select2({
                                placeholder: 'Select Field',
                                allowClear: true,
                                dropdownParent: $aggFieldSelect.closest('.modal'),
                                minimumResultsForSearch: 0 // Always enable search
                            });
                            $aggFuncSelect.select2({
                                dropdownParent: $aggFuncSelect.closest('.modal'),
                                minimumResultsForSearch: 0 // Always enable search
                            });
                            $aggFieldSelect.trigger('change');
                            $aggFuncSelect.trigger('change');
                        }
                    });
                }

                if (visualParamsObj.groupfields && Array.isArray(visualParamsObj.groupfields) && visualParamsObj.groupfields.length > 0) {
                    $modal.find('select.groupfields').val(visualParamsObj.groupfields).trigger('change.select2');
                    $('#group').show();
                }

                if (visualParamsObj.orderfields && Array.isArray(visualParamsObj.orderfields) && visualParamsObj.orderfields.length > 0) {
                    $modal.find('select.orderfields').val(visualParamsObj.orderfields).trigger('change.select2');
                    if (visualParamsObj.chkDescending === 'on' || visualParamsObj.chkDescending === true) {
                        $modal.find('input[name="chkDescending"]').prop('checked', true);
                    } else {
                        $modal.find('input[name="chkDescending"]').prop('checked', false);
                    }
                    $('#orderby').show();
                }

                if (visualParamsObj.limitStart || visualParamsObj.limitNumRows) {
                    $modal.find('input[name="limitStart"]').val(visualParamsObj.limitStart || '');
                    $modal.find('input[name="limitNumRows"]').val(visualParamsObj.limitNumRows || '');
                    $('#limit').show();
                }

                if (visualParamsObj.hfname && Array.isArray(visualParamsObj.hfname)) {
                    visualParamsObj.hfname.forEach(function (name, idx) {
                        if (name && visualParamsObj.hfvalue[idx]) {
                            var $hClone = $('#fieldCloneHaving').clone().removeAttr('id').show();
                            $hClone.find('select.hfname').val(name);
                            $hClone.find('input[name="hfvalue[]"]').val(visualParamsObj.hfvalue[idx]);
                            if (idx > 0 && visualParamsObj.htype[idx]) {
                                $hClone.find('select[name="htype[]"]').val(visualParamsObj.htype[idx]);
                            }
                            $hClone.find('.select2-container').remove();
                            var $hfnameSelectInClone = $hClone.find('select.hfname');
                            if ($hfnameSelectInClone.data('select2')) {
                                $hfnameSelectInClone.select2('destroy');
                            }
                            $hfnameSelectInClone.data('intended-value', name);
                            $('#havingConditionsContainer').append($hClone);
                        }
                    });
                }
                updateHavingFieldNameOptions();
            } else {
                $.jGrowl('Warning: VQB fields may not be fully loaded.', { header: 'Warning' });
            }
            $modal.modal('show');
        });
    }

    // If we don't have the table options for joins, and we are on the dashboard (indicated by a valid dataSourceId), fetch them.
    if (!_hasValidTableOptions(allTablesOptionsHTML) && dataSourceId) {
        $.post(base + '/ajax/get_tables_for_data_source', { data_source_id: dataSourceId }, function (response) {
            if (response.status === 'success' && response.tables) {
                let optionsHtml = '<option value="">Choose Table</option>';
                response.tables.forEach(function (table) {
                    optionsHtml += '<option value="' + escapeHtml(table) + '">' + escapeHtml(table) + '</option>';
                });
                allTablesOptionsHTML = optionsHtml;
                _populateAndShowModal(optionsHtml);
            } else {
                $.jGrowl('Failed to load table list for VQB.', { header: 'Error', theme: 'error' });
            }
        }, 'json').fail(function () {
            $.jGrowl('AJAX error loading table list for VQB.', { header: 'Error', theme: 'error' });
        });
    } else {
        // If we already have the table options, or are not on the dashboard, just proceed
        _populateAndShowModal(allTablesOptionsHTML);
    }
}


// --- Edit Saved Query Functionality ---
$('body').on('click', '.btn-edit-saved-query', function () {
    var queryId = $(this).data('query-id');
    var queryName = $(this).data('query-name');

    var queryData = savedQueriesCache?.find(q => q.id == queryId);
    if (!queryData) {
        $.jGrowl('Could not retrieve query details. Please refresh.', { header: 'Error' });
        return;
    }

    if (queryData.is_visual_query == '1' || queryData.is_visual_query === true) {
        // For visual queries, redirect to the VQB edit page using query ID
        window.location.href = base + '/vqb/edit/' + queryId;
    } else {
        // Not a visual query, open in SQL editor
        if (typeof editor !== 'undefined' && editor !== null) {
            editor.setValue(queryData.sql_query, -1); // -1 moves cursor to the start
        } else {
            $('#sql').val(queryData.sql_query); // Fallback if ACE editor not ready
        }
        $('#custom_query_id_edit').val(queryId);
        $('#modal-custom-query').data('source', 'dashboard');
        $('#modal-custom-query').modal('show');
    }
});


// --- Edit Executed Query Button ---
$('body').on('click', '#btnEditExecutedQuery', function () {
    var visualParamsJsonString = $('#current_visual_params').val();
    var executedQueryId = $('#executed_query_id').val(); // Might be empty if not a saved query
    var executedQueryName = $('#executed_query_name').val(); // Might be empty
    var wasSavedVisual = $('#executed_query_was_saved_visual').val() === 'true';
    var dataSourceId = $('#executed_query_source_connection_id').val(); // Get the data source ID

    if (!dataSourceId) {
        $.jGrowl('Error: Could not determine the data source for this query.', { header: 'Error', theme: 'error' });
        return;
    }

    if (visualParamsJsonString && visualParamsJsonString !== '{}' && visualParamsJsonString !== '[]') {
        try {
            var parsedParams = JSON.parse(visualParamsJsonString);

            if (wasSavedVisual && executedQueryId) {
                // For saved visual queries, redirect directly to the edit page (GET route)
                var editUrl = base + '/vqb/edit/' + encodeURIComponent(executedQueryId);
                window.location.href = editUrl;
            } else {
                // For ad-hoc VQB queries, use POST form submission to table-based route
                var tableName = (typeof __table !== 'undefined' && __table) ? __table : '';
                if (!tableName) {
                    $.jGrowl('Error: Could not determine the table for editing.', { header: 'Error', theme: 'error' });
                    return;
                }

                // Create a form with the visual parameters and redirect to VQB page
                var $form = $('<form>', {
                    'method': 'POST',
                    'action': base + '/vqb/' + encodeURIComponent(tableName),
                    'style': 'display:none;'
                });

                // Add visual parameters as form data
                $form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'edit_mode',
                    'value': 'true'
                }));

                $form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'visual_params',
                    'value': visualParamsJsonString
                }));

                $form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'data_source_id',
                    'value': dataSourceId
                }));

                $('body').append($form);
                $form.submit();
                $form.remove();
            }

        } catch (e) {
            console.error("Error parsing #current_visual_params for editing:", e);
            $.jGrowl('Could not parse visual parameters to edit this query.', { header: 'Error' });
        }
    } else {
        $.jGrowl('This query was not run from the VQB or is not visually editable.', { header: 'Info' });
    }
});

// --- Edit Custom SQL Query Button (from query results page) ---
$('body').on('click', '#btnEditCustomSQL', function () {
    var executedQueryId = $('#executed_query_id').val();
    var sqlQueryText = $('#generatedQueryDisplay pre').text();

    if (!executedQueryId) {
        $.jGrowl('Error: Executed query ID not found.', { header: 'Error' });
        return;
    }

    // Populate the hidden input in the custom query modal
    $('#custom_query_id_edit').val(executedQueryId);

    // Set the ACE editor's content
    if (typeof editor !== 'undefined' && editor !== null) {
        editor.setValue(sqlQueryText, -1); // -1 moves cursor to the start
    } else {
        // Fallback if ACE editor is not ready, though it should be.
        // The modal might not be visible yet, but we can try to set a textarea if one existed.
        // For now, we rely on the editor being available when the modal is shown.
        console.warn('ACE editor instance not found when trying to set SQL for editing.');
    }

    // Show the modal
    $('#modal-custom-query').data('source', 'results'); // Set source for save handler
    $('#modal-custom-query').modal('show');
});


// --- Helper functions (initializeVisualQueryBuilder, initializeVisualQueryForm, setupJoinRow, openAsSQLQuery) ---
// These were part of the older structure and are now largely replaced or integrated into openVisualQueryBuilderModal
// For cleanup, these can be removed if openVisualQueryBuilderModal covers all their previous uses.
// For now, keeping them commented out or to be removed in a later cleanup step.
/*
function initializeVisualQueryBuilder(parsedParams, sqlQuery, queryName) {
//function initializeVisualQueryBuilder(parsedParams, sqlQuery, queryName) {
//    // ... old code ...
//}

//function initializeVisualQueryForm(params) {
//    // ... old code ...
//}

//function setupJoinRow(joinDef, callback) {
//    // ... old code ...
//}

//function openAsSQLQuery(sqlQuery, queryName) {
//    // ... old code ...
//}
*/


// --- Update Visual Query (works for both VQB Modal and VQB Page) ---
$('body').on('click', '#btnUpdateVisualQuery', function () {
    var $vqbForm = $('#vqb-form');
    var queryId = $vqbForm.find('#visual_query_id_edit').val();
    var $thisButton = $(this);

    // Collect form data into a structured object
    var formData = $vqbForm.serializeArray();
    var visualParamsData = {};
    formData.forEach(function (item) {
        if (item.name.endsWith('[]')) {
            var name = item.name.substring(0, item.name.length - 2);
            if (!visualParamsData[name]) {
                visualParamsData[name] = [];
            }
            if (item.value) {
                visualParamsData[name].push(item.value);
            }
        } else {
            if (item.value || item.name === 'chkDescending' || item.name === 'limitStart' || item.name === 'limitNumRows') {
                if (item.name === 'chkDescending' && !$vqbForm.find('input[name="chkDescending"]').is(':checked')) { } else {
                    visualParamsData[item.name] = item.value;
                }
            }
        }
    });
    var arrayFields = ['fields', 'agg_field', 'agg_func', 'agg_alias', 'jointype', 'jointable', 'joinfield', 'joinfieldp', 'ftype', 'fname', 'fvalue', 'groupfields', 'orderfields', 'htype', 'hfname', 'hfvalue'];
    arrayFields.forEach(function (fieldName) {
        if (!visualParamsData[fieldName]) {
            visualParamsData[fieldName] = [];
        }
    });
    if ($vqbForm.find('input[name="chkDescending"]').is(':checked')) {
        visualParamsData.chkDescending = 'on';
    } else {
        delete visualParamsData.chkDescending;
    }
    if (typeof __table !== 'undefined' && __table) {
        visualParamsData.primaryTable = __table;
    }
    if ('visual_query_id_edit' in visualParamsData) {
        delete visualParamsData.visual_query_id_edit;
    }
    if ('visual_query_id_edit_submit' in visualParamsData) {
        delete visualParamsData.visual_query_id_edit_submit;
    }
    var visualParamsJsonString = JSON.stringify(visualParamsData);

    if (queryId) {
        // --- EXISTING QUERY: UPDATE IT ---
        var queryName = $vqbForm.data('query-name');
        var dataSourceId = $vqbForm.data('source-id');

        // Generate SQL first to populate the save modal
        $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');
        $.ajax({
            url: base + '/ajax/getSqlFromVisualParams',
            type: 'POST',
            data: {
                visual_params: JSON.stringify(visualParamsData),
                primary_table_name: visualParamsData.primaryTable
            },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success' && response.sql_query) {
                    // Open the Save Query modal with existing query data populated
                    $('#sql_query_save').val(response.sql_query);
                    $('#query_name_save').val(queryName); // Prepopulate existing name
                    $('#source_connection_id_save').val(dataSourceId); // Prepopulate existing connection
                    $('#modal-save-query').data('visual-params', visualParamsJsonString);
                    $('#modal-save-query').data('update-mode', true); // Flag for update mode
                    $('#modal-save-query').data('update-query-id', queryId); // Store query ID for update
                    $('#saveQueryMsg').hide().removeClass('alert-success alert-danger').text('');
                    $('#modal-save-query').modal('show');
                } else {
                    $.jGrowl(response.message || 'Could not generate SQL for this visual query.', { header: 'Error', theme: 'error' });
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $.jGrowl('AJAX Error generating SQL: ' + textStatus, { header: 'AJAX Error', theme: 'error' });
            },
            complete: function () {
                $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            }
        });
    } else {
        // --- NEW QUERY: GENERATE SQL AND OPEN SAVE MODAL ---
        $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');
        $.ajax({
            url: base + '/ajax/getSqlFromVisualParams',
            type: 'POST',
            data: {
                visual_params: JSON.stringify(visualParamsData),
                primary_table_name: visualParamsData.primaryTable
            },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success' && response.sql_query) {
                    // Open the Save Query modal for new query
                    $('#sql_query_save').val(response.sql_query);
                    $('#query_name_save').val(''); // Clear name for a new save
                    $('#source_connection_id_save').val($vqbForm.data('source-id') || ''); // Set current data source
                    $('#modal-save-query').data('visual-params', visualParamsJsonString);
                    $('#modal-save-query').removeData('update-mode');
                    $('#modal-save-query').removeData('update-query-id');
                    $('#saveQueryMsg').hide().removeClass('alert-success alert-danger').text('');
                    $('#modal-save-query').modal('show');
                } else {
                    $.jGrowl(response.message || 'Could not generate SQL for this visual query.', { header: 'Error', theme: 'error' });
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $.jGrowl('AJAX Error generating SQL: ' + textStatus, { header: 'AJAX Error', theme: 'error' });
            },
            complete: function () {
                $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            }
        });
    }
});

// --- Update Saved Query (from Custom Query Modal) ---
// This button is now primarily for updating the SQL of an existing query.
// Name editing is handled by the new Rename modal.
$('body').on('click', '#btnUpdateSavedQuery', function () {
    var queryId = $('#custom_query_id_edit').val();
    // var queryName = $('#custom_query_name_edit').val(); // Field removed from modal
    var sqlQuery = (typeof editor !== 'undefined' && editor !== null) ? editor.getValue() : $('#cquery').val(); // Get SQL from ACE or fallback
    var $msgContainer = $('#updateCustomQueryMsg');

    if (!queryId) {
        $msgContainer.removeClass('alert-success').addClass('alert-danger').text('Error: Query ID is missing. Cannot update SQL.').show();
        return;
    }
    // Query name validation removed as it's not edited here.
    // if ($.trim(queryName) === '') {
    //     $msgContainer.removeClass('alert-success').addClass('alert-danger').text('Query name cannot be empty.').show();
    //     return;
    // }
    if ($.trim(sqlQuery) === '') {
        $msgContainer.removeClass('alert-success').addClass('alert-danger').text('SQL query is empty. Cannot update.').show();
        return;
    }

    var $thisButton = $(this);
    $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');

    // query_name is removed from this request. The backend should only update the SQL.
    var ajaxData = {
        query_id: queryId,
        sql_query: sqlQuery
        // If visual_params were ever relevant here, they'd be included.
        // For now, this modal seems to be for raw SQL queries.
    };

    $.ajax({
        url: base + '/ajax/saveQuery', // Existing endpoint handles updates
        type: 'POST',
        data: ajaxData,
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                $msgContainer.removeClass('alert-danger').addClass('alert-success').text(response.message).show();
                var source = $('#modal-custom-query').data('source'); // Read source immediately

                // Update cache for sql_query
                var queryName = '';
                if (typeof savedQueriesCache !== 'undefined') {
                    var itemInCache = savedQueriesCache.find(function (q) { return q.id == queryId; });
                    if (itemInCache) {
                        itemInCache.sql_query = sqlQuery;
                        queryName = itemInCache.query_name;
                    }
                }

                setTimeout(function () {
                    if (source === 'dashboard') {
                        location.reload(); // Just reload the dashboard
                    } else {
                        // Instead of reloading, submit a form to run the updated query
                        var $dynamicForm = $('<form>', {
                            'action': base + '/table/run_saved_query',
                            'method': 'POST',
                            'style': 'display:none;'
                        }).append($('<input>', {
                            'type': 'hidden',
                            'name': 'cquery',
                            'value': sqlQuery
                        })).append($('<input>', {
                            'type': 'hidden',
                            'name': 'query_id',
                            'value': queryId
                        }));

                        $('body').append($dynamicForm);
                        $dynamicForm.submit();
                        $dynamicForm.remove();
                    }
                }, 1500);
            } else {
                $msgContainer.removeClass('alert-success').addClass('alert-danger').text(response.message || 'An unknown error occurred.').show();
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $msgContainer.removeClass('alert-success').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        },
        complete: function () {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            $('#modal-custom-query').removeData('source'); // Clean up the source flag
        }
    });
});


// --- Delete Saved Query Functionality (for dashboard and potentially modals if reused) ---
// Delegated click handler for the delete button on a saved query item
$('body').on('click', '.btn-delete-saved-query', function () {
    var queryId = $(this).data('query-id');
    var queryName = $(this).data('query-name');

    $('#modal-delete-confirm').data('query-id-to-delete', queryId);
    // If you want to customize the confirmation modal's text:
    // $('#modal-delete-confirm .modal-body p:first').html('You are about to delete the query: <strong>' + escapeHtml(queryName) + '</strong>. This procedure is irreversible.');

    $('#modal-delete-confirm').modal('show');
});

// Click handler for the final delete confirmation button inside #modal-delete-confirm
$('#modal-delete-confirm').on('click', '.btnDelete', function () {
    var queryIdToDelete = $('#modal-delete-confirm').data('query-id-to-delete');

    if (!queryIdToDelete) {
        $.jGrowl('Could not determine which query to delete. Please try again.', { header: 'Error', theme: 'error' });
        $('#modal-delete-confirm').modal('hide');
        return;
    }

    var $thisButton = $(this);
    $thisButton.prop('disabled', true).find('i').removeClass('fa-trash-o').addClass('fa-spinner fa-spin');

    $.ajax({
        url: base + '/ajax/deleteQuery',
        type: 'POST',
        data: { query_id: queryIdToDelete },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                $.jGrowl(response.message || 'Query deleted successfully!', { header: 'Success', theme: 'success' });

                // Remove the item from the list (works for both dashboard and previous modal list)
                $('li[data-query-list-id="' + queryIdToDelete + '"]').fadeOut(function () {
                    $(this).remove();
                    // Check if the list is empty on the dashboard specifically
                    if ($('#dashboardSavedQueriesList li').length === 0 && $('#dashboardSavedQueriesList').length > 0) {
                        $('#dashboardSavedQueriesList').replaceWith('<div class="alert alert-info"><p><span class="fa fa-info-circle"></span> You have no saved queries yet. You can save a query after running it from the table view.</p></div>');
                    }
                    // Check if the list is empty in the (now removed) modal list container
                    if ($('#savedQueriesListContainer li').length === 0 && $('#savedQueriesListContainer').length > 0) {
                        $('#savedQueriesListContainer').html('<p class="text-muted">No saved queries found.</p>');
                    }
                });

                // Remove from cache
                savedQueriesCache = savedQueriesCache.filter(function (query) {
                    return query.id != queryIdToDelete; // Use loose equality as data attributes can be strings
                });

            } else {
                $.jGrowl(response.message || 'Could not delete the query.', { header: 'Error', theme: 'error' });
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $.jGrowl('AJAX Error: Could not delete query. ' + textStatus, { header: 'Error', theme: 'error' });
        },
        complete: function () {
            $('#modal-delete-confirm').modal('hide');
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash-o');
            $('#modal-delete-confirm').removeData('query-id-to-delete');
        }
    });
});

$(document).ready(function () {
    // VQB Page-specific initialization (runs after jQuery loads)
    if (typeof vqbIsEditMode !== 'undefined' && typeof vqbDataSourceId !== 'undefined') {
        initializeVQBPage();
    }

    // Populate savedQueriesCache if initialSavedQueries is available (from dashboard.php)
    if (typeof initialSavedQueries !== 'undefined' && Array.isArray(initialSavedQueries)) {
        savedQueriesCache = initialSavedQueries;
    }

    // handle table select dropdown change
    $('#table_select').on('change', function () {
        var url = $(this).val();
        if (url) {
            window.location.href = url;
        }
    });

    // handle data source select dropdown change
    $('#datasource').on('change', function () {
        var dataSourceId = $(this).val();
        $.ajax({
            url: base + '/ajax/set_data_source',
            type: 'POST',
            data: { data_source_id: dataSourceId },
            success: function (response) {
                if (response.status === 'success') {
                    updateTableDropdown(dataSourceId);
                }
            }
        });
    });

    // ETL Page: Handle destination change to populate tables and toggle fields
    $('#destination_id').on('change', function () {
        var destinationId = $(this).val();
        var $tableSelect = $('#destination_table');
        var savedTableName = $tableSelect.data('saved-table');

        var $sftpFields = $('.sftp-etl-fields');
        var $s3Fields = $('.s3-etl-fields');
        var $databaseFields = $('.database-etl-fields');

        // Hide all fields and remove 'required' attributes if no destination is selected
        if (!destinationId) {
            $tableSelect.html('<option value="">-- Select a Destination First --</option>').prop('disabled', true);
            $sftpFields.hide();
            $s3Fields.hide();
            $databaseFields.hide();
            // Clear required attributes from all fields
            $('.sftp-etl-fields input, .sftp-etl-fields select, .s3-etl-fields input, .s3-etl-fields select, .database-etl-fields input, .database-etl-fields select').removeAttr('required');
            return;
        }

        // Get the destination type from the 'data-destination-type' attribute of the selected option
        var selectedOption = $(this).find('option:selected');
        var destType = selectedOption.data('destination-type') || 'database';

        if (destType === 'sftp') {
            // Show SFTP fields (including shared CSV separator) and hide others
            $sftpFields.show();
            // Hide only S3-specific fields (not shared fields like CSV separator)
            $('.s3-etl-fields:not(.sftp-etl-fields)').hide();
            $databaseFields.hide();

            // Manage 'required' attributes
            $('.database-etl-fields input, .database-etl-fields select, .s3-etl-fields:not(.sftp-etl-fields) input, .s3-etl-fields:not(.sftp-etl-fields) select').removeAttr('required');
            $('#csv_separator').prop('required', true);

            // Update the table select for SFTP
            $tableSelect.html('<option value="">-- SFTP destinations do not use tables --</option>').prop('disabled', true);

            return; // Stop here for SFTP
        }

        if (destType === 's3') {
            // Show S3 fields (including shared CSV separator) and hide others
            $s3Fields.show();
            // Hide only SFTP-specific fields (not shared fields like CSV separator)
            $('.sftp-etl-fields:not(.s3-etl-fields)').hide();
            $databaseFields.hide();

            // Manage 'required' attributes
            $('.database-etl-fields input, .database-etl-fields select, .sftp-etl-fields:not(.s3-etl-fields) input, .sftp-etl-fields:not(.s3-etl-fields) select').removeAttr('required');
            $('#csv_separator').prop('required', true);

            // Update the table select for S3
            $tableSelect.html('<option value="">-- S3 destinations do not use tables --</option>').prop('disabled', true);

            return; // Stop here for S3
        }

        // --- This part runs for 'database' destination types ---

        // Show database fields and hide SFTP fields
        $databaseFields.show();
        $sftpFields.hide();

        // Manage 'required' attributes
        $('.sftp-etl-fields input, .sftp-etl-fields select').removeAttr('required');
        $('#destination_table').prop('required', true);

        // Fetch tables for the selected database destination
        $tableSelect.html('<option value="">Loading tables...</option>').prop('disabled', false);

        $.ajax({
            url: base + '/ajax/get_destination_tables',
            type: 'POST',
            data: { destination_id: destinationId },
            dataType: 'json',
            success: function (response) {
                $tableSelect.empty();
                if (response.status === 'success' && response.tables) {
                    $tableSelect.append('<option value="">-- Select a Table --</option>');
                    $.each(response.tables, function (index, table) {
                        var isSelected = (table === savedTableName);
                        $tableSelect.append($('<option>', {
                            value: table,
                            text: table,
                            selected: isSelected
                        }));
                    });

                    // If a table was previously saved, trigger the change event to load column mappings
                    if (savedTableName && $tableSelect.val()) {
                        $tableSelect.trigger('change');
                    }
                } else {
                    $tableSelect.html('<option value="">Could not load tables</option>');
                    $.jGrowl(response.message || 'An error occurred.', { header: 'Error', theme: 'error' });
                }
            },
            error: function () {
                $tableSelect.html('<option value="">Error loading tables</option>');
                $.jGrowl('An AJAX error occurred while fetching tables.', { header: 'Error', theme: 'error' });
            }
        });
    });

    // Trigger change on page load if a destination is already selected
    if ($('#destination_id').val()) {
        $('#destination_id').trigger('change');
    }

    // ETL Page: Handle destination TABLE change to populate column mapping
    $('#destination_table').on('change', function () {
        var destinationTable = $(this).val();
        var destinationId = $('#destination_id').val();
        var queryId = $('input[name="query_id"]').val();
        var $mappingContainer = $('#column-mapping-container');

        if (!destinationTable) {
            $mappingContainer.html('<p class="text-muted">Select a destination table to map columns.</p>');
            return;
        }

        $mappingContainer.html('<p><i class="fa fa-spinner fa-spin"></i> Loading column mapping...</p>');

        $.ajax({
            url: base + '/ajax/get_etl_mapping_data',
            type: 'POST',
            data: {
                query_id: queryId,
                destination_table: destinationTable,
                destination_id: destinationId
            },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    var html = '<div class="row mapping-header">';
                    html += '<div class="col-sm-5"><strong>Source Column (from Query)</strong></div>';
                    html += '<div class="col-sm-5"><strong>Destination Column (from Table)</strong></div>';
                    html += '<div class="col-sm-2 key-column"><strong>Is Key?</strong></div>';
                    html += '</div><hr style="margin-top: 5px; margin-bottom: 10px;">';

                    response.source_columns.forEach(function (sourceCol) {
                        var savedMapping = (typeof etlConfig !== 'undefined' && etlConfig.column_mapping) ? etlConfig.column_mapping[sourceCol] : null;
                        var savedKeys = (typeof etlConfig !== 'undefined' && etlConfig.key_columns) ? etlConfig.key_columns : [];

                        html += '<div class="form-group row">';
                        html += '  <div class="col-sm-5">';
                        html += '    <input type="text" class="form-control" value="' + escapeHtml(sourceCol) + '" readonly>';
                        html += '  </div>';
                        html += '  <div class="col-sm-5">';
                        html += '    <select class="form-control" name="column_mapping[' + escapeHtml(sourceCol) + ']">';
                        html += '      <option value="">-- Do Not Map --</option>';
                        response.destination_columns.forEach(function (destCol) {
                            var selected = (destCol === savedMapping || destCol === sourceCol) ? ' selected' : '';
                            html += '<option value="' + escapeHtml(destCol) + '"' + selected + '>' + escapeHtml(destCol) + '</option>';
                        });
                        html += '    </select>';
                        html += '  </div>';
                        html += '  <div class="col-sm-2 key-column">';
                        var isChecked = savedKeys.includes(sourceCol) ? ' checked' : '';
                        html += '    <input type="checkbox" name="key_columns[]" value="' + escapeHtml(sourceCol) + '"' + isChecked + '>';
                        html += '  </div>';
                        html += '</div>';
                    });

                    $mappingContainer.html(html);
                    // Trigger etl_type change to set initial visibility of key column
                    $('#etl_type').trigger('change');
                } else {
                    $mappingContainer.html('<p class="text-danger">Error: ' + response.message + '</p>');
                }
            },
            error: function () {
                $mappingContainer.html('<p class="text-danger">An AJAX error occurred while fetching column data.</p>');
            }
        });
    });

    // ETL Page: Toggle key column visibility based on ETL type
    $('body').on('change', '#etl_type', function () {
        if ($(this).val() === 'update_or_insert') {
            $('.key-column').show();
        } else {
            $('.key-column').hide();
            // Uncheck all key columns when switching back to insert only
            $('.key-column input[type="checkbox"]').prop('checked', false);
        }
    });

    // ETL Page: Initialize select2 for scheduling options
    if ($('#schedule_hours').length) {
        $('#schedule_hours, #schedule_days').select2({
            placeholder: 'Click to select options',
            minimumResultsForSearch: 0 // Always enable search
        });
    }

    // ETL Page: Toggle visibility of detailed schedule options
    function toggleScheduleOptions() {
        if (!$('#schedule_type').length) {
            return; // Don't run if not on ETL page
        }
        // Hide all schedule option groups
        $('.schedule-options').hide();

        var selectedType = $('#schedule_type').val();
        if (selectedType === 'minutely') {
            $('#schedule_minutely_options').show();
        } else if (selectedType === 'hourly') {
            $('#schedule_hourly_options').show();
        } else if (selectedType === 'daily') {
            $('#schedule_daily_options').show();
        }
    }

    // Bind the event handler only if the element exists
    if ($('#schedule_type').length) {
        $('#schedule_type').on('change', toggleScheduleOptions);
        // Trigger on page load to set initial state
        toggleScheduleOptions();
    }
});

function updateTableDropdown(dataSourceId, callback) {
    var $tableSelect = $('#table_select');
    $tableSelect.empty().append('<option value="">Loading tables...</option>');

    if (!dataSourceId) {
        $tableSelect.empty().append('<option value="">-- Choose a Table --</option>');
        if (typeof callback === 'function') {
            callback();
        }
        return;
    }

    $.ajax({
        url: base + '/ajax/get_tables_for_data_source',
        type: 'POST',
        data: { data_source_id: dataSourceId },
        success: function (response) {
            $tableSelect.empty().append('<option value="">-- Choose a Table --</option>');
            if (response.status === 'success' && response.tables) {
                $.each(response.tables, function (index, table) {
                    var url = base + '/table/' + table;
                    $tableSelect.append('<option value="' + url + '">' + table + '</option>');
                });
            }
        },
        complete: function () {
            if (typeof callback === 'function') {
                callback();
            }
        }
    });
}

// Debounce function to prevent excessive calls
var debounceTimer = null;
function updateHavingFieldNameOptions() {
    // Clear existing timer
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    // Set new timer to delay execution
    debounceTimer = setTimeout(function () {
        _doUpdateHavingFieldNameOptions();
    }, 150); // 150ms delay to prevent rapid successive calls
}

function _doUpdateHavingFieldNameOptions() {
    var options = [];
    var existingOptions = {}; // To avoid duplicate options

    // Get options from original fields (table.column)
    // This re-uses the logic from addTablesToDropdown by fetching the current content of a 'fields' class select
    // and adapting it. This is a bit of a workaround. A cleaner way might be to have a dedicated source for these options.
    var generalFieldsHtml = $('select.fields').first().html(); // Get HTML of options from a representative 'fields' select
    if (generalFieldsHtml) {
        $(generalFieldsHtml).filter('optgroup').each(function () {
            var optgroupLabel = $(this).attr('label');
            var groupOptions = [];
            $(this).find('option').each(function () {
                var val = $(this).val();
                var text = $(this).text();
                if (!existingOptions[val]) {
                    groupOptions.push({ val: val, text: text });
                    existingOptions[val] = true;
                }
            });
            if (groupOptions.length > 0) {
                options.push({ label: optgroupLabel, options: groupOptions });
            }
        });
        $(generalFieldsHtml).filter('option').each(function () {
            var val = $(this).val();
            var text = $(this).text();
            if (!existingOptions[val]) {
                options.push({ val: val, text: text }); // Add non-optgrouped options
                existingOptions[val] = true;
            }
        });
    }


    // Get aliases from aggregated fields
    $('#aggregateFieldsContainer .parent').each(function () {
        var alias = $(this).find('.agg_alias').val();
        var field = $(this).find('.agg_field').val();
        var func = $(this).find('.agg_func').val();

        if (func && field) { // Only consider if function and field are selected
            var val = alias;
            if (!val) { // Generate default alias if not provided by user
                val = func.toLowerCase() + '_' + (field.includes('.') ? field.split('.')[1] : field);
            }
            var text = alias ? alias + ' (Alias)' : val + ' (Auto-Alias)';
            if (val && !existingOptions[val]) {
                options.push({ val: val, text: text, isAlias: true });
                existingOptions[val] = true;
            }
        }
    });

    // Get fields from GROUP BY clause
    $('select.groupfields').find('option:selected').each(function () {
        var val = $(this).val();
        var text = $(this).text() + ' (Group By)';
        if (val && !existingOptions[val]) {
            options.push({ val: val, text: text, isGroupBy: true });
            existingOptions[val] = true;
        }
    });

    var $hfnameSelects = $('#havingConditionsContainer').find('select.hfname'); // Scoped to active HAVING rows
    var newHtml = '<option value=""></option>'; // Add a blank option for placeholder

    // Build HTML for options, handling optgroups if present in the initial set
    options.forEach(function (opt) {
        if (opt.label) { // This is an optgroup
            newHtml += '<optgroup label="' + opt.label + '">';
            opt.options.forEach(function (innerOpt) {
                newHtml += '<option value="' + innerOpt.val + '">' + innerOpt.text + '</option>';
            });
            newHtml += '</optgroup>';
        } else if (opt.isAlias) {
            newHtml += '<option value="' + opt.val + '">' + opt.text + '</option>';
        } else if (opt.isGroupBy) {
            newHtml += '<option value="' + opt.val + '">' + opt.text + '</option>';
        } else { // These are general fields that might not be in an optgroup
            newHtml += '<option value="' + opt.val + '">' + opt.text + '</option>';
        }
    });


    $hfnameSelects.each(function () {
        var $select = $(this);
        var intendedValue = $select.data('intended-value'); // Get the stored intended value
        var currentHtml = $select.html();

        // Check if the select element is currently in focus or if its dropdown is open
        var isActive = $select.hasClass('select2-container--open') ||
            $select.parent().find('.select2-focused').length > 0 ||
            $select.parent().find('.select2-search input:focus').length > 0;

        // Skip updates if the field is currently being used (active/focused)
        if (isActive) {
            console.log('Skipping updateHavingFieldNameOptions for active select to prevent flickering');
            return; // Continue to next select element
        }

        // Only destroy if the options are actually different
        if (currentHtml !== newHtml) {
            try {
                $select.select2('destroy'); // Destroy before updating HTML
                $select.html(newHtml);      // Populate with all possible options

                if (intendedValue && $select.find('option[value="' + intendedValue + '"]').length > 0) {
                    $select.val(intendedValue); // Set to the stored intended value if option exists
                    $select.removeData('intended-value'); // Clean up data attribute
                } else {
                    // If no intended value, or if it's no longer a valid option,
                    // let Select2 default to placeholder or first option.
                    // No explicit .val('') needed here as the newHtml starts with an empty option.
                }

                // Re-initialize Select2 after updating options and value
                $select.select2({
                    placeholder: 'Select Field/Alias',
                    allowClear: true,
                    dropdownParent: $select.closest('.modal'),
                    minimumResultsForSearch: 0 // Always enable search
                });

                $select.trigger('change'); // Trigger change to ensure UI consistency
            } catch (e) {
                console.warn('Error updating select2 in updateHavingFieldNameOptions:', e);
            }
        }
    });
}


// add order by clause for visual query
$('#btnOrderby').click(function () {
    $('#orderby').slideDown('fast');
});

// add limit clause for visual query
$('#btnLimit').click(function () {
    $('#limit').slideDown('fast');
});

// add group by clause for visual query
$('#btnGroup').click(function () {
    $('#group').slideDown('fast');
});

// remove items for visual query
$('body').on('click', '.remove', function () {
    // update tables fields dropdowns when they are removed
    if ($(this).hasClass('removeme')) {
        $(this).closest('.parent').slideUp('fast').remove();
        addTablesToDropdown();
    }
    else {
        $(this).closest('.parent').slideUp('fast');
    }

    if (!$('.removeme:visible').length) {
        $('#addjoinedtablefields').slideUp('fast');
    }

    return false;
});

// join table for visual query
$('#btnJoinTable').click(function () {
    var $modal = $('#modal-visual-query');
    var dataSourceId = $modal.data('current-data-source-id'); // We'll store this when modal opens

    // Helper function to check for valid table options
    function _hasValidTableOptions(htmlString) {
        return htmlString && typeof htmlString === 'string' && htmlString.indexOf('<option') !== -1;
    }

    // Function to create and show the join row
    function _createJoinRow(tablesOptionsHtml) {
        console.log('DEBUG: _createJoinRow called with tablesOptionsHtml length:', tablesOptionsHtml ? tablesOptionsHtml.length : 'null');

        // Ensure the template's jointable select has fresh options before cloning
        $('#fieldCloneTable').find('select.jointable').html(tablesOptionsHtml);

        var $clone = $('#fieldCloneTable').clone();
        $('#btnJoinTable').after($clone);
        $clone.slideDown('fast');

        $clone.find('.select2-container').remove();
        $clone.find('.joinfieldselected').empty();

        console.log('DEBUG: About to initialize Select2 on join row selects. Found selects:', $clone.find('select').length);

        $clone.find('select').each(function () {
            var $select = $(this);
            $select.select2({
                placeholder: 'Choose',
                allowClear: true,
                dropdownParent: $clone.closest('.modal'),
                minimumResultsForSearch: 0 // Always enable search
            });
        });

        $('#addjoinedtablefields').slideDown('fast');
        console.log('DEBUG: _createJoinRow completed');
    }

    // If we have valid table options, use them directly
    if (_hasValidTableOptions(allTablesOptionsHTML)) {
        _createJoinRow(allTablesOptionsHTML);
    } else if (dataSourceId) {
        // Fetch table options from the server
        $.post(base + '/ajax/get_tables_for_data_source', { data_source_id: dataSourceId }, function (response) {
            if (response.status === 'success' && response.tables) {
                var optionsHtml = '<option value="">Choose Table</option>';
                response.tables.forEach(function (table) {
                    optionsHtml += '<option value="' + escapeHtml(table) + '">' + escapeHtml(table) + '</option>';
                });
                // Update the global variable for future use
                allTablesOptionsHTML = optionsHtml;
                _createJoinRow(optionsHtml);
            } else {
                $.jGrowl('Failed to load table list for join. Please try again.', { header: 'Error', theme: 'error' });
            }
        }, 'json').fail(function () {
            $.jGrowl('Error loading table list for join. Please check your connection.', { header: 'Error', theme: 'error' });
        });
    } else {
        // No data source ID available and no table options - show error
        $.jGrowl('Cannot add join: No data source context available. Please refresh and try again.', { header: 'Error', theme: 'error' });
    }
});

// to get fields for selected table for visual query
$('body').on('change', 'select.jointable', function () {
    var value = this.value;

    if (value) {
        var $this = $(this);
        var $targetSelect = $this.closest('.parent').find('select.joinfieldselected');
        var $modal = $('#modal-visual-query');
        var dataSourceId = $modal.data('current-data-source-id');

        // Use the new populateJoinFieldDropdown function
        populateJoinFieldDropdown($targetSelect, value, null, dataSourceId, function (success) {
            if (success) {
                // Optional: Trigger change on the populated select if other elements depend on its value
                // $targetSelect.trigger('change');

                // After a join table is manually selected and its fields loaded,
                // we need to update the general field dropdowns in VQB (like joinfieldmain in other rows)
                // to include fields from this newly selected table.
                addTablesToDropdown();
            }
            // Error handling is done within populateJoinFieldDropdown
        });
    }
});

// --- Save Query Functionality (Handles NEW saves only now) ---
// Show Save Query Modal
$('body').on('click', '#btnShowSaveQueryModal', function () {
    var sqlQueryText = $('#generatedQueryDisplay pre').text();
    if (!sqlQueryText || $.trim(sqlQueryText) === '') {
        $.jGrowl('No query generated yet to save!', { sticky: false, header: 'Error', theme: 'error' });
        return;
    }

    $('#sql_query_save').val(sqlQueryText);

    var executedQueryName = $('#executed_query_name').val();
    if (executedQueryName) {
        $('#query_name_save').val(executedQueryName);
    } else {
        $('#query_name_save').val(''); // Clear name for a new save
    }

    // Pre-select the data source in the modal if it's displayed on the page
    var displayedSourceId = $('#executed_query_source_connection_id').val();
    if (displayedSourceId) {
        $('#source_connection_id_save').val(displayedSourceId);
    } else {
        // If not on a saved query page, ensure the modal dropdown is reset to default
        $('#source_connection_id_save').val('');
    }

    // For saving visual query params
    var visualParamsJsonString = $('#current_visual_params').val();
    if (visualParamsJsonString && visualParamsJsonString !== '') {
        try {
            var visualParamsObject = JSON.parse(visualParamsJsonString);
            // Add the primaryTable information using the global __table variable
            // __table is set in app/views/table.php
            if (typeof __table !== 'undefined' && __table) {
                visualParamsObject.primaryTable = __table;
            } else {
                console.warn('__table variable is not defined. Cannot add primaryTable to visual_params for saving.');
                // Potentially, you could prevent saving here or notify the user,
                // but for now, we'll allow saving without it if __table is missing,
                // though editing might fail later as per the original issue.
            }
            // Store the modified object (as a string) in the modal's data
            $('#modal-save-query').data('visual-params', JSON.stringify(visualParamsObject));
            console.log('Visual params for save:', JSON.stringify(visualParamsObject));
        } catch (e) {
            console.error('Error parsing visual_params_json or adding primaryTable:', e);
            // If parsing fails, store the original string, though it's likely an issue.
            $('#modal-save-query').data('visual-params', visualParamsJsonString);
        }
    } else {
        $('#modal-save-query').removeData('visual-params'); // Ensure it's clear if no params
    }

    $('#saveQueryMsg').hide().removeClass('alert-success alert-danger').text('');
    $('#modal-save-query').modal('show');
});

// Confirm and Save/Update Query (AJAX)
$('body').on('click', '#btnSaveQueryConfirm', function () {
    var queryName = $('#query_name_save').val();
    var sqlQuery = $('#sql_query_save').val();
    var sourceConnectionId = $('#source_connection_id_save').val();
    var $saveQueryMsg = $('#saveQueryMsg');
    var visualParams = $('#modal-save-query').data('visual-params');
    var updateMode = $('#modal-save-query').data('update-mode');
    var updateQueryId = $('#modal-save-query').data('update-query-id');

    if ($.trim(queryName) === '') {
        $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('Query name cannot be empty.').show();
        return;
    }

    if ($.trim(sqlQuery) === '') {
        $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('SQL query is empty. Cannot save.').show();
        return;
    }

    if ($.trim(sourceConnectionId) === '') {
        $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('Please select a data source.').show();
        return;
    }

    var $thisButton = $(this);
    $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');

    var ajaxData = {
        query_name: queryName,
        sql_query: sqlQuery,
        source_connection_id: sourceConnectionId,
        is_visual_query: (visualParams && visualParams !== '') ? true : false,
        visual_params: (visualParams && visualParams !== '') ? visualParams : null
    };

    // If in update mode, add the query ID
    if (updateMode && updateQueryId) {
        ajaxData.query_id = updateQueryId;
    }

    $.ajax({
        url: base + '/ajax/saveQuery',
        type: 'POST',
        data: ajaxData,
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                var message = updateMode ? 'Visual query updated successfully!' : response.message;
                $saveQueryMsg.removeClass('alert-danger').addClass('alert-success').text(message).show();
                $('#query_name_save').val('');

                // Update cache if in update mode
                if (updateMode && typeof savedQueriesCache !== 'undefined' && savedQueriesCache.find) {
                    var itemInCache = savedQueriesCache.find(function (q) { return q.id == updateQueryId; });
                    if (itemInCache) {
                        itemInCache.query_name = queryName;
                        itemInCache.sql_query = sqlQuery;
                        itemInCache.source_connection_id = sourceConnectionId;
                        itemInCache.visual_params = ajaxData.visual_params;
                        itemInCache.is_visual_query = ajaxData.is_visual_query;
                    }
                }

                // If a new query is saved and user is on dashboard, ideally refresh the dashboard list or add to it.
                // For now, a jGrowl message might be enough, or they can refresh.
                if (!updateMode && typeof initialSavedQueries !== 'undefined' && response.new_query_id) { // Assuming server sends back new_query_id
                    // To dynamically update dashboard:
                    // 1. Add new query to savedQueriesCache
                    // savedQueriesCache.unshift({id: response.new_query_id, query_name: queryName, sql_query: sqlQuery, is_visual_query: ajaxData.is_visual_query, visual_params: ajaxData.visual_params, created_at: new Date().toISOString()});
                    // 2. Re-render the list on dashboard or prepend the new item.
                    // This part can be complex, for now, we'll rely on user refreshing dashboard or a success message.
                    $.jGrowl('New query saved! Refresh dashboard to see it in the list.', { header: 'Info', theme: 'info', life: 5000 });
                }

                setTimeout(function () {
                    location.reload();
                }, 1500);
            } else {
                $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text(response.message || 'An unknown error occurred.').show();
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        },
        complete: function () {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            $('#modal-save-query').removeData('visual-params').removeData('update-mode').removeData('update-query-id'); // Clean up
        }
    });
});

// Clear visual_params data if the save modal is closed manually
$('#modal-save-query').on('hidden.bs.modal', function () {
    $(this).removeData('visual-params');
});

// Basic HTML escaping function
function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') {
        return ''; // Or handle other types as needed
    }
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// --- Rename Saved Query ---
// Show Rename Query Modal
$('body').on('click', '.btn-rename-query', function () {
    var queryId = $(this).data('query-id');
    var currentQueryName = $(this).data('query-name');

    $('#rename_query_id').val(queryId);
    $('#rename_query_name').val(currentQueryName); // Populate with current name
    $('#renameQueryMsg').hide().removeClass('alert-success alert-danger').text('');
    $('#modal-rename-query').modal('show');
});

// Handle saving the renamed query
$('body').on('click', '#btnSaveRenameQuery', function () {
    var queryId = $('#rename_query_id').val();
    var newQueryName = $('#rename_query_name').val();
    var $msgContainer = $('#renameQueryMsg');
    var $thisButton = $(this);

    if ($.trim(newQueryName) === '') {
        $msgContainer.removeClass('alert-success').addClass('alert-danger').text('Query name cannot be empty.').show();
        return;
    }

    $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');

    var ajaxData = {
        query_id: queryId,
        query_name: newQueryName
        // No sql_query or visual_params are sent, as we are only renaming.
    };

    $.ajax({
        url: base + '/ajax/saveQuery', // Use the existing endpoint
        type: 'POST',
        data: ajaxData,
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                $msgContainer.removeClass('alert-danger').addClass('alert-success').text(response.message || 'Query renamed successfully!').show();

                // Update cache
                if (typeof savedQueriesCache !== 'undefined') {
                    var itemInCache = savedQueriesCache.find(function (q) { return q.id == queryId; });
                    if (itemInCache) {
                        itemInCache.query_name = newQueryName;
                    }
                }

                // Update dashboard list item text and data attribute
                var $listItem = $('li[data-query-list-id="' + queryId + '"]');
                if ($listItem.length) {
                    // Update the text node directly
                    $listItem.contents().filter(function () {
                        return this.nodeType === 3; // Node.TEXT_NODE
                    }).first().replaceWith(escapeHtml(newQueryName));

                    // Update data-query-name on relevant buttons within this list item
                    $listItem.find('.btn-edit-saved-query, .btn-delete-saved-query, .btn-rename-query').data('query-name', newQueryName);
                }

                setTimeout(function () {
                    $msgContainer.fadeOut(function () { $(this).hide().text(''); });
                    $('#modal-rename-query').modal('hide');
                }, 1500);
            } else {
                $msgContainer.removeClass('alert-success').addClass('alert-danger').text(response.message || 'An unknown error occurred while renaming.').show();
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            $msgContainer.removeClass('alert-success').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        },
        complete: function () {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
        }
    });
});


// --- List Saved Queries Functionality (Obsolete, kept for reference during cleanup, will be removed) ---
// var savedQueriesCache = []; // This is now initialized by initialSavedQueries if on dashboard

/* // Obsolete: fetchAndDisplaySavedQueries function
function fetchAndDisplaySavedQueries() {
    // ... content removed ...
}
*/

/* // Obsolete: escapeHtml function (if only used by fetchAndDisplaySavedQueries)
function escapeHtml(unsafe) {
    // ... content removed ...
}
*/

/* // Obsolete: Modal event listeners for list queries
$('#modal-list-queries').on('show.bs.modal', function () {
    // fetchAndDisplaySavedQueries(); // Removed
});

$('body').on('click', '#btnRefreshSavedQueries', function() {
    // fetchAndDisplaySavedQueries(); // Removed
});
*/

$('#addjoinedtablefields').click(function (event) {
    event.preventDefault(); // Prevent default anchor action if it's a link
    addTablesToDropdown(); // Call with no callback for default behavior
});

// dynamically populate dropdowns for selected tables for visual query
// Takes an optional callback function to execute after dropdowns are populated and initialized
function addTablesToDropdown(dataSourceId, callback) {
    // __table should be the primary table for the VQB context.
    // Ensure __table is defined and not empty. If not, we can't proceed.
    if (typeof __table === 'undefined' || !__table) {
        console.error("__table (primary table context) is not defined for addTablesToDropdown.");
        $.jGrowl('Primary table context not set. Cannot load fields.', { header: 'Error', theme: 'error' });
        if (typeof callback === 'function') {
            callback(false); // Indicate failure
        }
        return;
    }

    // If dataSourceId is not provided, try to get it from VQB contexts (modal or standalone page)
    if (!dataSourceId) {
        var $modal = $('#modal-visual-query');
        var $vqbForm = $('#vqb-form');

        if ($modal.length) {
            dataSourceId = $modal.data('current-data-source-id');
        } else if ($vqbForm.length) {
            dataSourceId = $vqbForm.data('current-data-source-id');
        }
    }

    const selectedTables = [__table];
    // console.log('Initial Table for Dropdowns:', __table);

    // Collect any currently joined tables in the VQB (works for both modal and standalone page)
    var $joinTableSelects = $('#modal-visual-query .jointable, #vqb-form .jointable');
    $joinTableSelects.each(function () {
        const table = $(this).val();
        if (table && !selectedTables.includes(table)) {
            selectedTables.push(table);
        }
    });

    // console.log('Final Tables Sent for getselectfields:', selectedTables);

    if (selectedTables.length > 0) {
        const postData = { "tables": JSON.stringify(selectedTables), "data_source_id": dataSourceId };

        $.post(base + '/ajax/getselectfields', postData, function (response) {
            // console.log('Server Response for getselectfields:', response);

            var selectorsToUpdate = [
                '#modal-visual-query select.fields, #vqb-form select.fields', // VQB main fields
                '#modal-visual-query select.fname, #vqb-form select.fname', // WHERE clause fields
                '#modal-visual-query select.orderfields, #vqb-form select.orderfields', // ORDER BY fields
                '#modal-visual-query select.groupfields, #vqb-form select.groupfields', // GROUP BY fields
                '#modal-visual-query select.joinfieldmain, #vqb-form select.joinfieldmain', // JOIN clause primary table fields
                '#modal-visual-query select.agg_field, #vqb-form select.agg_field' // Aggregate function fields
                // Note: .hfname (HAVING) is handled by updateHavingFieldNameOptions separately,
                // but updateHavingFieldNameOptions itself relies on select.fields being populated.
            ];

            $(selectorsToUpdate.join(', ')).each(function () {
                var $select = $(this);
                var currentValues = $select.val();

                // Only destroy if the options are actually different
                var currentOptionsHtml = $select.html();
                if (currentOptionsHtml !== response) {
                    try {
                        $select.select2('destroy');
                    } catch (e) {
                        // console.warn('Could not destroy select2 instance on an element:', $select, e);
                    }

                    $select.html(response);

                    if (currentValues) {
                        if (Array.isArray(currentValues)) {
                            var newValues = [];
                            currentValues.forEach(function (val) {
                                if ($select.find('option[value="' + val + '"]').length > 0) {
                                    newValues.push(val);
                                }
                            });
                            $select.val(newValues);
                        } else {
                            if ($select.find('option[value="' + currentValues + '"]').length > 0) {
                                $select.val(currentValues);
                            }
                        }
                    }

                    var placeholderText = $select.data('placeholder') || 'Choose';
                    var select2Options = {
                        placeholder: placeholderText,
                        allowClear: true,
                        minimumResultsForSearch: 0 // Always enable search
                    };

                    // Add dropdownParent for modal context only
                    if ($select.closest('.modal').length) {
                        select2Options.dropdownParent = $select.closest('.modal');
                    }

                    $select.select2(select2Options);
                }
            });

            // After updating general field dropdowns, also update the HAVING clause options
            // as it depends on the main fields list.
            updateHavingFieldNameOptions();

            $.jGrowl('Fields updated for selected tables!');
            if (typeof callback === 'function') {
                callback(true, response); // Indicate success and pass the HTML response
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            // console.error('AJAX Error in addTablesToDropdown:', textStatus, errorThrown);
            $.jGrowl('Error loading fields: ' + textStatus, { header: 'Error', theme: 'error' });
            if (typeof callback === 'function') {
                callback(false, null); // Indicate failure, pass null for response
            }
        });
    } else {
        // Should not happen if __table is always present
        console.warn("No tables selected for addTablesToDropdown, including primary __table.");
        if (typeof callback === 'function') {
            callback(false, null); // Indicate failure or no action, pass null for response
        }
    }
}

/**
 * Populates a given select dropdown with fields from a specified table.
 * @param {jQuery} $selectElement - The jQuery object for the select dropdown.
 * @param {string} tableName - The name of the table to fetch fields for.
 * @param {string} [selectedValue] - Optional. The value to pre-select in the dropdown.
 * @param {function} [callback] - Optional. Callback function executed after population (receives true for success, false for failure).
 */
function populateJoinFieldDropdown($selectElement, tableName, selectedValue, dataSourceId, callback) {
    if (!tableName) {
        if (typeof callback === 'function') callback(false);
        return;
    }

    $.post(base + '/ajax/gettablefields', { "table": tableName, "data_source_id": dataSourceId }, function (response) {
        if (response && response.status === 'success' && response.fields) {
            var optionsHtml = '<option value="">Choose Field</option>';
            response.fields.forEach(function (field) {
                optionsHtml += '<option value="' + escapeHtml(field) + '">' + escapeHtml(field) + '</option>';
            });

            try {
                $selectElement.select2('destroy');
            } catch (e) {
                // Ignore destruction errors
            }

            $selectElement.html(optionsHtml);

            if (selectedValue) {
                $selectElement.val(selectedValue);
            }

            $selectElement.select2({
                placeholder: 'Choose Field',
                allowClear: true,
                dropdownParent: $selectElement.closest('.modal'),
                minimumResultsForSearch: 0 // Always enable search
            });

            if (typeof callback === 'function') callback(true);
        } else {
            $.jGrowl('Error loading fields for ' + tableName + ': ' + (response && response.message ? response.message : 'Unknown error'), { sticky: false, header: 'Error' });
            if (typeof callback === 'function') callback(false);
        }
    }, 'json').fail(function (jqXHR, textStatus, errorThrown) {
        $.jGrowl('AJAX Error: Could not load fields for ' + tableName + '.', { sticky: false, header: 'Error' });
        if (typeof callback === 'function') callback(false);
    });
}

// VQB Page-specific initialization function
function initializeVQBPage() {
    console.log('Initializing VQB page with edit mode:', vqbIsEditMode, 'and saved params:', vqbSavedParams);

    // Store data source ID for VQB functions
    if (vqbDataSourceId) {
        $('#vqb-form').data('current-data-source-id', vqbDataSourceId);
    }

    // Initialize popovers for VQB page
    $('[rel=hover_popover]').popover({ "trigger": "hover", "placement": "bottom" });

    // Initialize Select2 on VQB form elements
    var vqbSelect2Selector = '#vqb-form select:not(#fieldClone select, #fieldCloneTable select, #fieldCloneAggregate select, #fieldCloneHaving select)';
    $(vqbSelect2Selector).each(function () {
        var $this = $(this);
        var options = {
            placeholder: 'Choose',
            allowClear: true,
            minimumInputLength: 0,
            minimumResultsForSearch: 0 // Always enable search
        };

        console.log('DEBUG: VQB Select2 initialization for element:', $this[0], 'with options:', options);
        $this.select2(options);
    });

    // If in edit mode and have saved parameters, populate the form
    if (vqbIsEditMode && vqbSavedParams) {
        populateVQBFormWithSavedData(vqbSavedParams);
    }
}

// Function to populate VQB form with saved query data
function populateVQBFormWithSavedData(savedParams) {
    console.log('Populating VQB form with saved data:', savedParams);

    // First, populate the join rows. This is necessary to know which tables' fields to load.
    if (savedParams.jointype && Array.isArray(savedParams.jointype)) {
        savedParams.jointype.forEach(function (type, idx) {
            if (type && savedParams.jointable && savedParams.jointable[idx]) {
                var $clone = $('#fieldCloneTable').clone().removeAttr('id').addClass('cloned-join-row');
                $clone.find('select[name="jointype[]"]').val(type);
                var $tableSelect = $clone.find('select[name="jointable[]"]');
                if (allTablesOptionsHTML) {
                    $tableSelect.html(allTablesOptionsHTML);
                }
                $tableSelect.val(savedParams.jointable[idx]);
                $('#btnJoinTable').after($clone);
                $clone.show();

                $clone.find('select').each(function () {
                    $(this).select2({ placeholder: 'Choose', allowClear: true, minimumResultsForSearch: 0 });
                });

                if (savedParams.joinfield && savedParams.joinfield[idx]) {
                    populateJoinFieldDropdown(
                        $clone.find('select[name="joinfield[]"]'),
                        savedParams.jointable[idx],
                        savedParams.joinfield[idx],
                        vqbDataSourceId
                    );
                }
            }
        });
    }

    // Now, call addTablesToDropdown to populate all field dropdowns with the correct options.
    // The rest of the form population logic will happen in the callback.
    addTablesToDropdown(vqbDataSourceId, function (success, fieldOptionsHtml) {
        if (!success) {
            $.jGrowl('Failed to load field options, form may not populate correctly.', { header: 'Error', theme: 'error' });
            return;
        }

        // Populate non-aggregated fields
        if (savedParams.fields && Array.isArray(savedParams.fields)) {
            $('select[name="fields[]"]').val(savedParams.fields).trigger('change');
        }

        // Re-set the primary table join fields now that their options are loaded
        $('.cloned-join-row').each(function (idx) {
            var $row = $(this);
            if (savedParams.joinfieldp && savedParams.joinfieldp[idx]) {
                $row.find('select[name="joinfieldp[]"]').val(savedParams.joinfieldp[idx]).trigger('change');
            }
        });

        // Populate WHERE conditions
        if (savedParams.fname && Array.isArray(savedParams.fname)) {
            savedParams.fname.forEach(function (name, idx) {
                if (name && savedParams.fvalue && savedParams.fvalue[idx]) {
                    var $clone = $('#fieldClone').clone().removeAttr('id');
                    $clone.find('select[name="fname[]"]').val(name).end()
                        .find('input[name="fvalue[]"]').val(savedParams.fvalue[idx]).end();
                    if (idx > 0 && savedParams.ftype && savedParams.ftype[idx]) {
                        $clone.find('select[name="ftype[]"]').val(savedParams.ftype[idx]);
                    }
                    $('#btnAddWhere').after($clone);
                    $clone.find('select').each(function () {
                        $(this).select2({ placeholder: 'Choose', allowClear: true, minimumResultsForSearch: 0 });
                    });
                    $clone.show();
                }
            });
        }

        // Populate aggregated fields
        if (savedParams.agg_field && Array.isArray(savedParams.agg_field)) {
            savedParams.agg_field.forEach(function (field, idx) {
                if (field && savedParams.agg_func && savedParams.agg_func[idx]) {
                    var $clone = $('#fieldCloneAggregate').clone().removeAttr('id');
                    $clone.find('select[name="agg_field[]"]').val(field).end()
                        .find('select[name="agg_func[]"]').val(savedParams.agg_func[idx]).end()
                        .find('input[name="agg_alias[]"]').val(savedParams.agg_alias[idx] || '').end();
                    $('#aggregateFieldsContainer').append($clone);
                    $clone.find('select').each(function () {
                        $(this).select2({ placeholder: 'Choose', allowClear: true, minimumResultsForSearch: 0 });
                    });
                    $clone.show();
                }
            });
        }

        // Populate ORDER BY
        if (savedParams.orderfields && Array.isArray(savedParams.orderfields) && savedParams.orderfields.length > 0) {
            $('select[name="orderfields[]"]').val(savedParams.orderfields).trigger('change');
            $('#orderby').show();
            if (savedParams.chkDescending === 'on' || savedParams.chkDescending === true) {
                $('input[name="chkDescending"]').prop('checked', true);
            }
        }

        // Populate GROUP BY
        if (savedParams.groupfields && Array.isArray(savedParams.groupfields) && savedParams.groupfields.length > 0) {
            $('select[name="groupfields[]"]').val(savedParams.groupfields).trigger('change');
            $('#group').show();
        }

        // Populate LIMIT
        if (savedParams.limitStart || savedParams.limitNumRows) {
            $('input[name="limitStart"]').val(savedParams.limitStart || '');
            $('input[name="limitNumRows"]').val(savedParams.limitNumRows || '');
            $('#limit').show();
        }

        // Populate HAVING conditions (after aggregates are populated)
        if (savedParams.hfname && Array.isArray(savedParams.hfname)) {
            updateHavingFieldNameOptions(); // Ensure options are fresh
            setTimeout(function () { // Use a timeout to allow dropdowns to render
                savedParams.hfname.forEach(function (name, idx) {
                    if (name && savedParams.hfvalue && savedParams.hfvalue[idx]) {
                        var $clone = $('#fieldCloneHaving').clone().removeAttr('id');
                        $clone.find('select[name="hfname[]"]').val(name).end()
                            .find('input[name="hfvalue[]"]').val(savedParams.hfvalue[idx]).end();
                        if (idx > 0 && savedParams.htype && savedParams.htype[idx]) {
                            $clone.find('select[name="htype[]"]').val(savedParams.htype[idx]);
                        }
                        $('#havingConditionsContainer').append($clone);
                        $clone.find('select').each(function () {
                            $(this).select2({ placeholder: 'Choose', allowClear: true, minimumResultsForSearch: 0 });
                        });
                        $clone.show();
                    }
                });
            }, 100);
        }

        console.log('VQB form population completed.');
    });
}
