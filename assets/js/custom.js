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

// replace selects with select2
$('select').select2({ placeholder: 'Choose' });

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
    var $clone = $('#fieldClone').clone();
    $(this).after($clone);
    $clone.slideDown('fast');
    //$clone.find('select').select2({});
    $clone.find('.select2-container').remove();
    $clone.find('select').select2();
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
$('body').on('click', '.btn-run-saved-query', function() {
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

        // Close the list modal
        $('#modal-list-queries').modal('hide');

        // Open the custom query modal (if not already open) and then click its run button
        // This reuses the existing custom query infrastructure.
        $('#modal-custom-query').modal('show');

        // It's better to ensure the modal is fully shown before clicking, but a slight delay can often work.
        // Or, more robustly, trigger run from within 'shown.bs.modal' event of custom query modal if it was just opened.
        // For now, a direct click:
        $('#btnCustomQuery').click();

    } else {
        $.jGrowl('Could not find the SQL for the selected query.', { sticky: false, header: 'Error', theme: 'error' });
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
    // Get the displayed SQL query text
    // The SQL is inside a <pre> tag within #generatedQueryDisplay
    var sqlQueryText = $('#generatedQueryDisplay pre').text();
    if (!sqlQueryText || $.trim(sqlQueryText) === '') {
        $.jGrowl('No query generated yet to save!', { sticky: false, header: 'Error', theme: 'error' });
        return;
    }

    $('#sql_query_save').val(sqlQueryText);
    $('#query_name_save').val(''); // Clear previous name
    $('#saveQueryMsg').hide().removeClass('alert-success alert-danger').text('');
    $('#modal-save-query').modal('show');
});

// Confirm and Save Query (AJAX)
$('body').on('click', '#btnSaveQueryConfirm', function() {
    var queryName = $('#query_name_save').val();
    var sqlQuery = $('#sql_query_save').val();
    var $saveQueryMsg = $('#saveQueryMsg');

    if ($.trim(queryName) === '') {
        $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('Query name cannot be empty.').show();
        return;
    }

    if ($.trim(sqlQuery) === '') {
        // This case should ideally be prevented by the #btnShowSaveQueryModal logic
        $saveQueryMsg.removeClass('alert-success').addClass('alert-danger').text('SQL query is empty. Cannot save.').show();
        return;
    }

    var $thisButton = $(this);
    $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');


    $.ajax({
        url: base + '/ajax/saveQuery', // Ensure 'base' variable is globally available or adjust path
        type: 'POST',
        data: {
            query_name: queryName,
            sql_query: sqlQuery
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $saveQueryMsg.removeClass('alert-danger').addClass('alert-success').text(response.message).show();
                $('#query_name_save').val(''); // Clear name for next save
                setTimeout(function() {
                    //$('#modal-save-query').modal('hide'); // Optionally hide modal
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
        }
    });
});


// --- List Saved Queries Functionality ---
var savedQueriesCache = []; // Simple cache for query SQL

function fetchAndDisplaySavedQueries() {
    var $container = $('#savedQueriesListContainer');
    var $msgContainer = $('#listQueriesMsg');
    $container.html('<p><i class="fa fa-spinner fa-spin"></i> Loading saved queries...</p>');
    $msgContainer.hide();

    $.ajax({
        url: base + '/ajax/getSavedQueries',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            $container.empty(); // Clear loading message
            if (response.status === 'success' && response.queries && response.queries.length > 0) {
                savedQueriesCache = response.queries; // Cache the queries
                var listHtml = '<ul class="list-group">';
                $.each(response.queries, function(index, query) {
                    listHtml += '<li class="list-group-item d-flex justify-content-between align-items-center">';
                    listHtml += escapeHtml(query.query_name); // Display query name
                    listHtml += '<button type="button" class="btn btn-primary btn-xs btn-run-saved-query" data-query-id="' + query.id + '" style="margin-left: 10px;"><i class="fa fa-play"></i> Run</button>';
                    // Future: Add edit/delete buttons here, using query.id
                    // listHtml += '<span class="badge badge-primary badge-pill">' + query.id + '</span>'; // Example badge
                    listHtml += '</li>';
                });
                listHtml += '</ul>';
                $container.html(listHtml);
            } else if (response.status === 'success') {
                $container.html('<p class="text-muted">No saved queries found.</p>');
            }
            else {
                $msgContainer.removeClass('alert-success').addClass('alert-danger').text(response.message || 'Could not load saved queries.').show();
                $container.html('<p class="text-danger">Error loading queries.</p>');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $container.html('<p class="text-danger">Error loading queries.</p>');
            $msgContainer.removeClass('alert-success').addClass('alert-danger').text('AJAX Error: ' + textStatus + ' - ' + errorThrown).show();
        }
    });
}

// Using escapeHtml to prevent XSS from query names
function escapeHtml(unsafe) {
    if (unsafe === null || typeof unsafe === 'undefined') {
        return '';
    }
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
 }

// When the "List Saved Queries" modal is shown, fetch the list
$('#modal-list-queries').on('show.bs.modal', function () {
    fetchAndDisplaySavedQueries();
});

// Refresh button click
$('body').on('click', '#btnRefreshSavedQueries', function() {
    fetchAndDisplaySavedQueries();
});


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
          //  console.log('Server Response:', response);
            
            $('select.fields, select.fname, select.orderfields, select.groupfields').html(response).trigger('change');
            $('.select2').select2();
            
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
