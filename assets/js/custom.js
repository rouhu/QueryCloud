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
    e.preventDefault(); // Prevent default anchor action if it were an <a> tag
    var queryId = $(this).data('query-id');
    var queryToRun = null;

    // Find the query in the cache
    for (var i = 0; i < savedQueriesCache.length; i++) {
        if (savedQueriesCache[i].id == queryId) { // Note: queryId from data attribute might be string
            queryToRun = savedQueriesCache[i];
            break;
        }
    }

    if (queryToRun && queryToRun.sql_query) {
        // Check if ACE editor instance 'editor' is available
        if (typeof editor !== 'undefined' && editor !== null) {
            editor.setValue(queryToRun.sql_query, -1); // -1 moves cursor to the start
        } else {
            // Fallback or alternative if ACE editor is not on the current page context or not used for custom query
            // This might happen if the custom query modal isn't initialized or visible.
            // For now, we assume custom query modal is the primary way to run these.
            console.warn('ACE editor instance not found. Cannot set query value directly for custom query modal.');
            // As a direct fallback, try to set #cquery if it exists, though this is less ideal without ACE sync
            $('#cquery').val(queryToRun.sql_query);
        }

        // Ensure the hidden #cquery input (used by the form that submits custom queries) is updated.
        // This is critical if #btnCustomQuery relies on this hidden field rather than exclusively on editor.getValue() at the moment of click.
        // The existing #btnCustomQuery handler does `var query = editor.getValue(); $input.val(query);`
        // So, setting the editor value should be sufficient if the custom query modal is open.
        // If we want to run it without opening the custom query modal first, we'd need a more direct submission.

        // Close the list modal (if it's open - it won't be if running from dashboard)
        if ($('#modal-list-queries').is(':visible')) {
            $('#modal-list-queries').modal('hide');
        }

        // Determine if on dashboard or a table page
        // The `lastSegment` variable is already available globally from PHP.
        // It contains the last part of the URL. Empty for home, 'home' for /home, or table name for /table/tablename
        var onDashboard = (!lastSegment || lastSegment === 'home' || lastSegment === 'dashboard');
        var $customQueryForm = $('#modal-custom-query form');

        if (onDashboard) {
            var firstTableLink = $('.sidebar-nav a[href*="/table/"]').first();
            if (firstTableLink.length > 0) {
                var hrefParts = firstTableLink.attr('href').split('/');
                var firstTableName = hrefParts[hrefParts.length -1]; // Get last part
                if (firstTableName) {
                    $customQueryForm.attr('action', base + '/table/' + firstTableName);
                } else {
                    $.jGrowl('Could not determine a default table to run the query. Please select a table first.', { header: 'Error', theme: 'error' });
                    return;
                }
            } else {
                $.jGrowl('No tables available to run the query. Please ensure tables are loaded.', { header: 'Error', theme: 'error' });
                return;
            }
        } else {
            // On a table page, ensure action is relative to current table or correctly set
            // If `action` is empty, it will submit to current page (e.g., /table/current_table_name)
            // If `action` was previously changed by dashboard run, reset it.
             $customQueryForm.attr('action', ''); // Reset to submit to current page context
        }

        // Open the custom query modal and then click its run button
        $('#modal-custom-query').modal('show');

        // Dynamically create and submit a hidden form
        var $form = $('<form>', {
            'action': formAction,
            'method': 'POST',
            'style': 'display:none;'
        }).append($('<input>', {
            'type': 'hidden',
            'name': 'cquery',
            'value': queryToRun.sql_query
        }));

        // If the original custom query form has other specific hidden inputs that Table::runquery might expect,
        // they would need to be cloned here. For example, if 'vquery' or 'printArray' were relevant for non-visual runs.
        // var $originalCustomQueryForm = $('#modal-custom-query form');
        // $form.append($originalCustomQueryForm.find('input[name="vquery"]').clone());
        // $form.append($originalCustomQueryForm.find('input[name="printArray"]').clone()); // This one is a checkbox, cloning needs care.
        // For now, assuming only 'cquery' is essential for this direct run.

        $('body').append($form);
        $form.submit();
        $form.remove();

    } else {
        $.jGrowl('Could not find the SQL for the selected query.', { sticky: false, header: 'Error', theme: 'error' });
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

    // Populate ACE editor (if available)
    if (typeof editor !== 'undefined' && editor !== null) {
        editor.setValue(sqlQuery, -1); // -1 moves cursor to start
    } else {
        // Fallback if ACE editor isn't on the page or available
        $('#cquery').val(sqlQuery); // Assuming #cquery is the hidden input for custom SQL
        console.warn('ACE editor not found. SQL set in hidden input for custom query.');
    }

    // Store editing state on the save query modal
    $('#modal-save-query').data('editing-query-id', queryId);
    $('#modal-save-query').data('editing-query-name', queryName); // Will be used to prefill name

    // Set form action for custom query modal (similar to btn-run-saved-query)
    var onDashboard = (!lastSegment || lastSegment === 'home' || lastSegment === 'dashboard');
    var $customQueryForm = $('#modal-custom-query form');
    if (onDashboard) {
        var firstTableLink = $('.sidebar-nav a[href*="/table/"]').first();
        if (firstTableLink.length > 0) {
            var hrefParts = firstTableLink.attr('href').split('/');
            var firstTableName = hrefParts[hrefParts.length -1];
            if (firstTableName) {
                $customQueryForm.attr('action', base + '/table/' + firstTableName);
            } else {
                 // This state should ideally not be reached if tables exist
                $.jGrowl('Could not determine a default table context for editing.', { header: 'Error', theme: 'error' });
                return;
            }
        } else {
            $.jGrowl('No tables available for query context.', { header: 'Error', theme: 'error' });
            return;
        }
    } else {
        // On a table page, action should be current page context
        $customQueryForm.attr('action', '');
    }

    // Open the custom query modal for editing SQL
    $('#modal-custom-query').modal('show');
    // The user will edit the SQL, then click "Run Query" in custom query modal.
    // After running, they will be on table.php, then they can click "Save Current Query"
    // which will trigger #btnShowSaveQueryModal, which needs to be aware of the edit state.
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

// --- Save Query Functionality ---
// Show Save Query Modal
$('body').on('click', '#btnShowSaveQueryModal', function() {
    var sqlQueryText = $('#generatedQueryDisplay pre').text();
    if (!sqlQueryText || $.trim(sqlQueryText) === '') {
        $.jGrowl('No query generated yet to save!', { sticky: false, header: 'Error', theme: 'error' });
        return;
    }

    $('#sql_query_save').val(sqlQueryText);

    // Check if we are in an "edit" workflow
    var editingQueryId = $('#modal-save-query').data('editing-query-id');
    var editingQueryName = $('#modal-save-query').data('editing-query-name');

    if (editingQueryId) {
        $('#query_name_save').val(editingQueryName); // Pre-fill name if editing
    } else {
        $('#query_name_save').val(''); // Clear name for new save
    }

    $('#saveQueryMsg').hide().removeClass('alert-success alert-danger').text('');
    $('#modal-save-query').modal('show');
});

// Confirm and Save/Update Query (AJAX)
$('body').on('click', '#btnSaveQueryConfirm', function() {
    var queryName = $('#query_name_save').val();
    var sqlQuery = $('#sql_query_save').val();
    var $saveQueryMsg = $('#saveQueryMsg');
    var editingQueryId = $('#modal-save-query').data('editing-query-id'); // Get editing ID

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
        sql_query: sqlQuery
    };

    if (editingQueryId) {
        ajaxData.query_id = editingQueryId; // Add query_id if we are editing
    }

    $.ajax({
        url: base + '/ajax/saveQuery',
        type: 'POST',
        data: ajaxData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $saveQueryMsg.removeClass('alert-danger').addClass('alert-success').text(response.message).show();

                if (!editingQueryId) { // Only clear name if it was a new save
                    $('#query_name_save').val('');
                }
                // If it was an update, and successful, we might want to update the name on the dashboard if it changed.
                // This requires refreshing the dashboard list or finding and updating the specific item.
                // For now, a full refresh of dashboard or re-opening saved queries list from dashboard will show changes.
                // Or, update the cache and re-render the specific item if possible.
                if (editingQueryId && typeof initialSavedQueries !== 'undefined') { // If on dashboard
                     // Simple refresh of dashboard content to show updated name/query
                     // This is a bit heavy-handed, a more targeted update would be better for UX.
                     // Consider just updating the name in savedQueriesCache and on the specific list item.
                    var itemInCache = savedQueriesCache.find(function(q) { return q.id == editingQueryId; });
                    if(itemInCache) {
                        itemInCache.query_name = queryName;
                        itemInCache.sql_query = sqlQuery; // also update sql in cache
                        // Update the name in the dashboard list
                        $('li[data-query-list-id="' + editingQueryId + '"]').contents().filter(function() {
                            return this.nodeType === 3; // Text node
                        }).first().replaceWith(escapeHtml(queryName));
                         // Update the data-query-name attribute on the edit/delete buttons for this item
                        $('li[data-query-list-id="' + editingQueryId + '"]').find('.btn-edit-saved-query, .btn-delete-saved-query').data('query-name', queryName);

                    }
                }


                setTimeout(function() {
                    $saveQueryMsg.fadeOut();
                    if (editingQueryId) { // Optionally close modal after successful update
                         $('#modal-save-query').modal('hide');
                    }
                }, editingQueryId ? 1500 : 3000); // Shorter timeout for updates

            } else {
                $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text(response.message || 'An unknown error occurred.').show();
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        },
        complete: function() {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            if (editingQueryId && $('#modal-save-query').is(':hidden')) { // If closed by timeout
                 $('#modal-save-query').removeData('editing-query-id');
                 $('#modal-save-query').removeData('editing-query-name');
            }
            // If not closed by timeout, it will be cleared on next 'show' or if closed manually.
        }
    });
});

// Clear editing state if the save modal is closed manually
$('#modal-save-query').on('hidden.bs.modal', function () {
    $(this).removeData('editing-query-id');
    $(this).removeData('editing-query-name');
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
