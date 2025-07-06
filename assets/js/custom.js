/**
 * Created by SARFRAZ on 7/27/14
 */

// select active navigation item
$('.sidebar-nav a').parents('li').removeClass('activelink');
$('.sidebar-nav a[href$="' + lastSegment + '"]').parents('li').addClass('activelink');

// style tables
if ($('table tr').length) {
    $('.page-content table').not('table.nodatatable').dataTable({
        sPaginationType: "full_numbers",
        bAutoWidth: false,
        autoWidth: false,
        bLengthChange: true,
        iDisplayLength: 10,
        scrollX: 400
    });
}

// replace selects with select2 - BEWARE: This global initializer might be problematic for hidden templates.
// It's generally better to initialize Select2 specifically when elements are shown or activated.
// For now, we'll make it slightly more specific to avoid direct init on known template contents.
var initialSelect2Selector = 'select:not(#fieldClone select, #fieldCloneTable select, #fieldCloneAggregate select, #fieldCloneHaving select)';
$(initialSelect2Selector).select2({ placeholder: 'Choose' });


// for tooltips
$(".tip").tooltip();

// inline bootstrap editable
$.fn.editable.defaults.mode = 'popup';

$('a.editable').editable({
    validate: function (value) {
        if ($.trim(value) == '') return 'Cannot be empty!';
    }
});

$('a.editable').editable();

// for popover
$('#modal-visual-query [rel=hover_popover]').popover({ "trigger": "hover", "placement": "right"});
$('[rel=hover_popover]').popover({ "trigger": "hover", "placement": "bottom"});

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
    $selectInClone.select2({ placeholder: 'Choose Field', allowClear: true });
    $clone.slideDown('fast');
});

// add aggregate field for visual query
$('#btnAddAggregateField').click(function () {
    var $clone = $('#fieldCloneAggregate').clone().removeAttr('id'); // clone and remove id to avoid duplicates
    var $aggFieldSelect = $clone.find('.agg_field');

    // Destroy existing Select2 instance if any, then re-initialize
    $aggFieldSelect.select2('destroy');
    $('#aggregateFieldsContainer').append($clone);
    $aggFieldSelect.select2({ placeholder: 'Select Field', allowClear: true });

    // No need to initialize select2 for agg_func unless specific styling/features are needed for it.
    $clone.slideDown('fast');
    updateHavingFieldNameOptions(); // Update HAVING field options when a new aggregate is added
});

// add Having condition for visual query
$('#btnAddHavingCondition').click(function () {
    var $clone = $('#fieldCloneHaving').clone().removeAttr('id');
    var $hfnameSelect = $clone.find('.hfname');

    // Destroy existing Select2 instance if any, then re-initialize
    $hfnameSelect.select2('destroy');
    $('#havingConditionsContainer').append($clone);
    $hfnameSelect.select2({ placeholder: 'Select Field/Alias', allowClear: true });

    $clone.slideDown('fast');
    updateHavingFieldNameOptions(); // Ensure new HAVING rows get the correct options
});

// When aggregate alias or group by fields change, update HAVING field name options
$('body').on('change', '.agg_alias, .groupfields', function() {
    updateHavingFieldNameOptions();
});

// Run Saved Query
$('body').on('click', '.btn-run-saved-query', function(e) {
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
        var onDashboard = (!lastSegment || lastSegment === 'home' || lastSegment === 'dashboard');

        if (onDashboard) {
            var firstTableLink = $('.sidebar-nav a[href*="/table/"]').first();
            if (firstTableLink.length > 0) {
                var hrefParts = firstTableLink.attr('href').split('/');
                var firstTableName = hrefParts[hrefParts.length - 1];
                if (firstTableName) {
                    formAction = base + '/table/' + firstTableName;
                } else {
                    $.jGrowl('Could not determine a default table context. Please select a table first.', { header: 'Error', theme: 'error' });
                    return;
                }
            } else {
                $.jGrowl('No tables available for query context. Please ensure tables are loaded.', { header: 'Error', theme: 'error' });
                return;
            }
        } else {
            // On a table page, the action is the current page's URL.
            // The custom query form in #modal-custom-query has action=""
            formAction = $('#modal-custom-query form').attr('action'); // This will be ""
            if(formAction === "" || typeof formAction === 'undefined') {
                 formAction = window.location.pathname + window.location.search + window.location.hash;
            }
        }

        var $dynamicForm = $('<form>', {
            'action': formAction,
            'method': 'POST',
            'style': 'display:none;'
        }).append($('<input>', {
            'type': 'hidden',
            'name': 'cquery',
            'value': queryToRun.sql_query
        }));

        $('body').append($dynamicForm);
        $dynamicForm.submit();
        $dynamicForm.remove();

    } else {
        $.jGrowl('Could not find the SQL for the selected query. Please refresh.', { sticky: false, header: 'Error', theme: 'error' });
    }
});

// --- Edit Saved Query Functionality ---
$('body').on('click', '.btn-edit-saved-query', function() {
    var queryId = $(this).data('query-id');
    var queryName = $(this).data('query-name');
    var sqlQuery = null;

    // Find the query in the cache to get its SQL
    if (typeof savedQueriesCache !== 'undefined') {
        for (var i = 0; i < savedQueriesCache.length; i++) {
            if (savedQueriesCache[i].id == queryId) { // Loose comparison for data attribute
                sqlQuery = savedQueriesCache[i].sql_query;
                break;
            }
        }
    }

    if (sqlQuery === null) {
        $.jGrowl('Could not retrieve SQL for editing. Please refresh.', { header: 'Error', theme: 'error' });
        return;
    }

    var queryData = null;
    if (typeof savedQueriesCache !== 'undefined') {
        queryData = savedQueriesCache.find(function(q) { return q.id == queryId; });
    }

    if (!queryData) {
        $.jGrowl('Could not retrieve query details for editing. Please refresh.', { header: 'Error', theme: 'error' });
        return;
    }

    sqlQuery = queryData.sql_query; // Already have queryName from data attribute
    var isVisual = queryData.is_visual_query == '1' || queryData.is_visual_query === true; // Ensure boolean check
    var visualParams = queryData.visual_params;

    // Clear any old data from the save modal (as it's not used in this new flow)
    $('#modal-save-query').removeData('editing-query-id').removeData('editing-query-name');

    if (isVisual && visualParams && visualParams !== '') {
        // Populate and show Visual Query Modal
        $('#visual_query_id_edit').val(queryId);
        // Name is no longer edited in this modal
        // TODO: Implement populateVisualQueryModal(JSON.parse(visualParams));
        // For now, just opening it with name and ID. User has to rebuild.
        console.log("Attempting to open visual editor for query ID:", queryId, "Params:", visualParams);
        $.jGrowl('Populating visual editor from saved params is not yet fully implemented. Opening with name/ID.', { header: 'Info', theme: 'info', life: 5000});

        // Set form action for the visual query modal's run button (if it submits directly)
        // The visual query modal's form also needs its action set correctly.
        var $visualQueryForm = $('#modal-visual-query form');
        var onDashboardCtx = (!lastSegment || lastSegment === 'home' || lastSegment === 'dashboard');
        if (onDashboardCtx) {
            var firstTableLinkCtx = $('.sidebar-nav a[href*="/table/"]').first();
            if (firstTableLinkCtx.length > 0) {
                var hrefPartsCtx = firstTableLinkCtx.attr('href').split('/');
                var firstTableNameCtx = hrefPartsCtx[hrefPartsCtx.length -1];
                if (firstTableNameCtx) {
                    $visualQueryForm.attr('action', base + '/table/' + firstTableNameCtx);
                } else {
                    $.jGrowl('Could not determine default table context for visual editor.', { header: 'Error', theme: 'error' }); return;
                }
            } else {
                $.jGrowl('No tables available for visual editor context.', { header: 'Error', theme: 'error' }); return;
            }
        } else {
            $visualQueryForm.attr('action', ''); // Current table context
        }
        $('#modal-visual-query').modal('show');

    } else {
        // Fallback to Custom SQL Editor
        if (typeof editor !== 'undefined' && editor !== null) {
            editor.setValue(sqlQuery, -1);
        } else {
            $('#cquery').val(sqlQuery);
            console.warn('ACE editor not found. SQL set in hidden input for custom query.');
        }
        $('#custom_query_id_edit').val(queryId);
        // Name is no longer edited in this modal
        $('#updateCustomQueryMsg').hide().removeClass('alert-success alert-danger').text('');

        var onDashboardCtxSql = (!lastSegment || lastSegment === 'home' || lastSegment === 'dashboard');
        var $customQueryFormSql = $('#modal-custom-query form');
        if (onDashboardCtxSql) {
            var firstTableLinkSql = $('.sidebar-nav a[href*="/table/"]').first();
            if (firstTableLinkSql.length > 0) {
                var hrefPartsSql = firstTableLinkSql.attr('href').split('/');
                var firstTableNameSql = hrefPartsSql[hrefPartsSql.length -1];
                if (firstTableNameSql) {
                    $customQueryFormSql.attr('action', base + '/table/' + firstTableNameSql);
                } else {
                    $.jGrowl('Could not determine default table context for SQL editor.', { header: 'Error', theme: 'error' }); return;
                }
            } else {
                $.jGrowl('No tables available for SQL editor context.', { header: 'Error', theme: 'error' }); return;
            }
        } else {
            $customQueryFormSql.attr('action', '');
        }
        $('#modal-custom-query').modal('show');
    }
});

// --- Update Saved Query (from Custom Query Modal) ---
// This button is now primarily for updating the SQL of an existing query.
// Name editing is handled by the new Rename modal.
$('body').on('click', '#btnUpdateSavedQuery', function() {
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
        success: function(response) {
            if (response.status === 'success') {
                $msgContainer.removeClass('alert-danger').addClass('alert-success').text(response.message).show();

                // Update cache for sql_query
                if (typeof savedQueriesCache !== 'undefined') {
                    var itemInCache = savedQueriesCache.find(function(q) { return q.id == queryId; });
                    if (itemInCache) {
                        // itemInCache.query_name = queryName; // Name is not updated here
                        itemInCache.sql_query = sqlQuery;
                    }
                }
                // Update dashboard list item - only SQL changes, name display is not affected by this action.
                // var $listItem = $('li[data-query-list-id="' + queryId + '"]');
                // if ($listItem.length) {
                //    $listItem.contents().filter(function() { return this.nodeType === 3; }).first().replaceWith(escapeHtml(queryName));
                //    $listItem.find('.btn-edit-saved-query, .btn-delete-saved-query').data('query-name', queryName);
                // }

                setTimeout(function() {
                    $msgContainer.fadeOut(function() { $(this).hide(); });
                    $('#modal-custom-query').modal('hide');
                }, 1500);
            } else {
                $msgContainer.removeClass('alert-success').addClass('alert-danger').text(response.message || 'An unknown error occurred.').show();
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $msgContainer.removeClass('alert-success').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        },
        complete: function() {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
        }
    });
});


// --- Delete Saved Query Functionality (for dashboard and potentially modals if reused) ---
// Delegated click handler for the delete button on a saved query item
$('body').on('click', '.btn-delete-saved-query', function() {
    var queryId = $(this).data('query-id');
    var queryName = $(this).data('query-name');

    $('#modal-delete-confirm').data('query-id-to-delete', queryId);
    // If you want to customize the confirmation modal's text:
    // $('#modal-delete-confirm .modal-body p:first').html('You are about to delete the query: <strong>' + escapeHtml(queryName) + '</strong>. This procedure is irreversible.');

    $('#modal-delete-confirm').modal('show');
});

// Click handler for the final delete confirmation button inside #modal-delete-confirm
$('#modal-delete-confirm').on('click', '.btnDelete', function() {
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
        success: function(response) {
            if (response.status === 'success') {
                $.jGrowl(response.message || 'Query deleted successfully!', { header: 'Success', theme: 'success' });

                // Remove the item from the list (works for both dashboard and previous modal list)
                $('li[data-query-list-id="' + queryIdToDelete + '"]').fadeOut(function() {
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
                savedQueriesCache = savedQueriesCache.filter(function(query) {
                    return query.id != queryIdToDelete; // Use loose equality as data attributes can be strings
                });

            } else {
                $.jGrowl(response.message || 'Could not delete the query.', { header: 'Error', theme: 'error' });
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $.jGrowl('AJAX Error: Could not delete query. ' + textStatus, { header: 'Error', theme: 'error' });
        },
        complete: function() {
            $('#modal-delete-confirm').modal('hide');
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash-o');
            $('#modal-delete-confirm').removeData('query-id-to-delete');
        }
    });
});

$(document).ready(function() {
    // Populate savedQueriesCache if initialSavedQueries is available (from dashboard.php)
    if (typeof initialSavedQueries !== 'undefined' && Array.isArray(initialSavedQueries)) {
        savedQueriesCache = initialSavedQueries;
    }
});

function updateHavingFieldNameOptions() {
    var options = [];
    var existingOptions = {}; // To avoid duplicate options

    // Get options from original fields (table.column)
    // This re-uses the logic from addTablesToDropdown by fetching the current content of a 'fields' class select
    // and adapting it. This is a bit of a workaround. A cleaner way might be to have a dedicated source for these options.
    var generalFieldsHtml = $('select.fields').first().html(); // Get HTML of options from a representative 'fields' select
    if (generalFieldsHtml) {
        $(generalFieldsHtml).filter('optgroup').each(function() {
            var optgroupLabel = $(this).attr('label');
            var groupOptions = [];
            $(this).find('option').each(function() {
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
         $(generalFieldsHtml).filter('option').each(function() {
            var val = $(this).val();
            var text = $(this).text();
            if (!existingOptions[val]) {
                options.push({ val: val, text: text }); // Add non-optgrouped options
                existingOptions[val] = true;
            }
        });
    }


    // Get aliases from aggregated fields
    $('#aggregateFieldsContainer .parent').each(function() {
        var alias = $(this).find('.agg_alias').val();
        var field = $(this).find('.agg_field').val();
        var func = $(this).find('.agg_func').val();

        if (func && field) { // Only consider if function and field are selected
            var val = alias;
            if (!val) { // Generate default alias if not provided by user
                val = func.toLowerCase() + '_' + (field.includes('.') ? field.split('.')[1] : field) ;
            }
            var text = alias ? alias + ' (Alias)' : val + ' (Auto-Alias)';
            if (val && !existingOptions[val]) {
                options.push({ val: val, text: text, isAlias: true });
                existingOptions[val] = true;
            }
        }
    });

    // Get fields from GROUP BY clause
    $('select.groupfields').find('option:selected').each(function() {
        var val = $(this).val();
        var text = $(this).text() + ' (Group By)';
        if (val && !existingOptions[val]) {
            options.push({ val: val, text: text, isGroupBy: true });
            existingOptions[val] = true;
        }
    });

    var $hfnameSelects = $('select.hfname');
    var newHtml = '<option value=""></option>'; // Add a blank option for placeholder

    // Build HTML for options, handling optgroups if present in the initial set
    options.forEach(function(opt) {
        if (opt.label) { // This is an optgroup
            newHtml += '<optgroup label="' + opt.label + '">';
            opt.options.forEach(function(innerOpt) {
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


    $hfnameSelects.each(function() {
        var $select = $(this);
        var currentValue = $select.val(); // Preserve selected value if possible

        $select.select2('destroy'); // Destroy before updating HTML
        $select.html(newHtml);

        if (currentValue && $select.find('option[value="' + currentValue + '"]').length > 0) {
            $select.val(currentValue);
        }
        // Re-initialize Select2 after updating options
        $select.select2({ placeholder: 'Select Field/Alias', allowClear: true });
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
    var $clone = $('#fieldCloneTable').clone();
    $(this).after($clone);
    $clone.slideDown('fast');

    $clone.find('.select2-container').remove();
    $clone.find('.joinfieldselected').empty();
    $clone.find('select').select2();

    $('#addjoinedtablefields').slideDown('fast');
});

// to get fields for selected table for visual query
$('body').on('change', 'select.jointable', function () {
    var value = this.value;

    if (value) {
        var $this = $(this);
       // console.log("Selected table:", value); // debug
        $.post(base + '/ajax/gettablefields', {"table": value}, function (response) {
            var $select = $this.closest('.parent').find('select.joinfieldselected');
         //   console.log("Fields:", response); // debug
            $select.html(response);
            $select.select2();
        }).fail(function() {
            $.jGrowl('Error loading fields!', { sticky: false, header: 'Error' });
        });
    }
});

// --- Save Query Functionality (Handles NEW saves only now) ---
// Show Save Query Modal
$('body').on('click', '#btnShowSaveQueryModal', function() {
    var sqlQueryText = $('#generatedQueryDisplay pre').text();
    if (!sqlQueryText || $.trim(sqlQueryText) === '') {
        $.jGrowl('No query generated yet to save!', { sticky: false, header: 'Error', theme: 'error' });
        return;
    }

    $('#sql_query_save').val(sqlQueryText);
    $('#query_name_save').val(''); // Always clear name for a new save

    // For saving visual query params
    var visualParamsJson = $('#current_visual_params').val();
    if (visualParamsJson && visualParamsJson !== '') {
        $('#modal-save-query').data('visual-params', visualParamsJson);
    } else {
        $('#modal-save-query').removeData('visual-params'); // Ensure it's clear if no params
    }

    $('#saveQueryMsg').hide().removeClass('alert-success alert-danger').text('');
    $('#modal-save-query').modal('show');
});

// Confirm and Save NEW Query (AJAX)
$('body').on('click', '#btnSaveQueryConfirm', function() {
    var queryName = $('#query_name_save').val();
    var sqlQuery = $('#sql_query_save').val();
    var $saveQueryMsg = $('#saveQueryMsg');
    var visualParams = $('#modal-save-query').data('visual-params');

    if ($.trim(queryName) === '') {
        $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('Query name cannot be empty.').show();
        return;
    }

    if ($.trim(sqlQuery) === '') {
        $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('SQL query is empty. Cannot save.').show();
        return;
    }

    var $thisButton = $(this);
    $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');

    var ajaxData = {
        query_name: queryName,
        sql_query: sqlQuery,
        is_visual_query: (visualParams && visualParams !== '') ? true : false,
        visual_params: (visualParams && visualParams !== '') ? visualParams : null
    };

    $.ajax({
        url: base + '/ajax/saveQuery',
        type: 'POST',
        data: ajaxData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $saveQueryMsg.removeClass('alert-danger').addClass('alert-success').text(response.message).show();
                $('#query_name_save').val('');

                // If a new query is saved and user is on dashboard, ideally refresh the dashboard list or add to it.
                // For now, a jGrowl message might be enough, or they can refresh.
                if (typeof initialSavedQueries !== 'undefined' && response.new_query_id) { // Assuming server sends back new_query_id
                    // To dynamically update dashboard:
                    // 1. Add new query to savedQueriesCache
                    // savedQueriesCache.unshift({id: response.new_query_id, query_name: queryName, sql_query: sqlQuery, is_visual_query: ajaxData.is_visual_query, visual_params: ajaxData.visual_params, created_at: new Date().toISOString()});
                    // 2. Re-render the list on dashboard or prepend the new item.
                    // This part can be complex, for now, we'll rely on user refreshing dashboard or a success message.
                     $.jGrowl('New query saved! Refresh dashboard to see it in the list.', { header: 'Info', theme: 'info', life: 5000 });
                }


                setTimeout(function() {
                    $saveQueryMsg.fadeOut();
                }, 3000);
            } else {
                $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text(response.message || 'An unknown error occurred.').show();
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        },
        complete: function() {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            $('#modal-save-query').removeData('visual-params'); // Clean up
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
$('body').on('click', '.btn-rename-query', function() {
    var queryId = $(this).data('query-id');
    var currentQueryName = $(this).data('query-name');

    $('#rename_query_id').val(queryId);
    $('#rename_query_name').val(currentQueryName); // Populate with current name
    $('#renameQueryMsg').hide().removeClass('alert-success alert-danger').text('');
    $('#modal-rename-query').modal('show');
});

// Handle saving the renamed query
$('body').on('click', '#btnSaveRenameQuery', function() {
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
        success: function(response) {
            if (response.status === 'success') {
                $msgContainer.removeClass('alert-danger').addClass('alert-success').text(response.message || 'Query renamed successfully!').show();

                // Update cache
                if (typeof savedQueriesCache !== 'undefined') {
                    var itemInCache = savedQueriesCache.find(function(q) { return q.id == queryId; });
                    if (itemInCache) {
                        itemInCache.query_name = newQueryName;
                    }
                }

                // Update dashboard list item text and data attribute
                var $listItem = $('li[data-query-list-id="' + queryId + '"]');
                if ($listItem.length) {
                    // Update the text node directly
                    $listItem.contents().filter(function() {
                        return this.nodeType === 3; // Node.TEXT_NODE
                    }).first().replaceWith(escapeHtml(newQueryName));

                    // Update data-query-name on relevant buttons within this list item
                    $listItem.find('.btn-edit-saved-query, .btn-delete-saved-query, .btn-rename-query').data('query-name', newQueryName);
                }

                setTimeout(function() {
                    $msgContainer.fadeOut(function() { $(this).hide().text(''); });
                    $('#modal-rename-query').modal('hide');
                }, 1500);
            } else {
                $msgContainer.removeClass('alert-success').addClass('alert-danger').text(response.message || 'An unknown error occurred while renaming.').show();
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $msgContainer.removeClass('alert-success').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        },
        complete: function() {
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

$('#addjoinedtablefields').click(addTablesToDropdown);

// dynamically populate dropdowns for selected tables for visual query
function addTablesToDropdown() {
    const selectedTables = [__table]; 
   // console.log('Selected Tables:', selectedTables);

    $('.jointable').each(function() {
        const table = $(this).val();
        if (table && !selectedTables.includes(table)) {
            selectedTables.push(table);
        }
    });

    //console.log('Final Tables Sent:', selectedTables);

    if (selectedTables.length > 0) {
        const postData = {"tables": JSON.stringify(selectedTables)};
        //console.log('POST Data:', postData);

        $.post(base + '/ajax/getselectfields', postData, function(response) {
            // console.log('Server Response for getselectfields:', response); // For debugging the raw response
            
            // More robust way to update Select2 elements
            var selectorsToUpdate = [
                'select.fields',          // For general field selection
                'select.fname',           // For WHERE clause field names
                'select.orderfields',     // For ORDER BY field names
                'select.groupfields',     // For GROUP BY field names
                'select.joinfieldmain',   // For JOIN clause primary table fields (part of fieldCloneTable)
                'select.agg_field'        // For Aggregate function field names (part of fieldCloneAggregate)
                                          // Note: .hfname is handled by updateHavingFieldNameOptions separately
            ];

            $(selectorsToUpdate.join(', ')).each(function() {
                var $select = $(this);
                var currentValues = $select.val(); // Store current value(s)

                // Try to destroy existing Select2 instance.
                // If it wasn't initialized, this might throw a benign error or do nothing, depending on Select2 version.
                // It's generally safer to try.
                try {
                    $select.select2('destroy');
                } catch (e) {
                    // console.warn('Could not destroy select2 instance on an element:', $select, e);
                }

                $select.html(response); // Populate with new options from AJAX

                // Attempt to re-select previous value(s)
                if (currentValues) {
                    if (Array.isArray(currentValues)) {
                        var newValues = [];
                        currentValues.forEach(function(val) {
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

                // Re-initialize Select2 with appropriate placeholder
                var placeholderText = $select.data('placeholder') || 'Choose'; // Use data-placeholder if available
                $select.select2({ placeholder: placeholderText, allowClear: true });
            });
            
            // The global $('.select2').select2(); is removed to avoid conflicts.
            // Specific initializations are handled above or in their respective cloning functions.

            $.jGrowl('Fields updated with joined tables!');
        }).fail(function(jqXHR, textStatus, errorThrown) {
           // console.error('AJAX Error:', textStatus, errorThrown);
            $.jGrowl('Error loading fields: ' + textStatus, {header: 'Error', theme: 'error'});
        });
    }
}

// change database
$('#database').change(function () {
    if (this.value) {
        $.post(base + '/ajax/setDatabase', {"db": this.value}, function (response) {
            if (response === 'ok') {
                window.location.href = base;
            }
        });
    }
});
