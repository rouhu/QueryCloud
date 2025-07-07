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
        })).append($('<input>', { // Add query name as a hidden field
            'type': 'hidden',
            'name': 'running_saved_query_name',
            'value': queryToRun.query_name
        }));

        $('body').append($dynamicForm);
        $dynamicForm.submit();
        $dynamicForm.remove();

    } else {
        $.jGrowl('Could not find the SQL for the selected query. Please refresh.', { sticky: false, header: 'Error', theme: 'error' });
    }
});

// --- Edit Saved Query Functionality ---
// Find the .btn-edit-saved-query click handler (around line 180) and modify it:

$('body').on('click', '.btn-edit-saved-query', function() {
    var queryId = $(this).data('query-id');
    var queryName = $(this).data('query-name');
    
    // Find the query in the cache
    var queryData = savedQueriesCache?.find(q => q.id == queryId);
    if (!queryData) {
        $.jGrowl('Could not retrieve query details. Please refresh.', { header: 'Error' });
        return;
    }

    var sqlQuery = queryData.sql_query;
    var isVisual = queryData.is_visual_query == '1' || queryData.is_visual_query === true;
    var visualParams = queryData.visual_params;

    // Clear old data
    $('#modal-save-query').removeData('editing-query-id').removeData('editing-query-name');

    // Initialize visual query builder
    if (isVisual && visualParams) {
        try {
            var parsedParams = JSON.parse(visualParams);
            console.log("Preparing to open Visual Query Builder for query ID:", queryId);
            
            // Set the primary table
            __table = parsedParams.primaryTable || '';
            $('#modal-visual-query .vqb-table-name').text('Table: ' + (__table ? __table.toUpperCase() : 'UNKNOWN'));
            
            // Store the queryId in the hidden field within the VQB modal for the update operation
            $('#visual_query_id_edit').val(queryId);

            // Dynamically set the form action for the VQB modal
            if (__table) {
                var formActionUrl = base + '/table/' + __table;
                $('#modal-visual-query form').attr('action', formActionUrl);
                console.log("VQB form action set to:", formActionUrl);
            } else {
                // This case should ideally not happen if primaryTable is always saved and present
                console.error("VQB: __table is not defined, cannot set form action accurately. Form will submit to current page.");
                // Fallback to current page if __table is missing, which is the default for action=""
                $('#modal-visual-query form').attr('action', '');
            }

            // Clear existing joins
            $('#modal-visual-query .cloned-join-row').remove();
            
            // Helper function to check if HTML options are valid (more than just a placeholder)
            function hasValidTableOptions(htmlString) {
                if (!htmlString || typeof htmlString !== 'string' || htmlString.indexOf('<option') === -1) {
                    return false;
                }
                // Create a temporary select to count actual options vs placeholders
                var $tempSelect = $('<select>').html(htmlString);
                var totalOptions = $tempSelect.find('option').length;
                var placeholderOptions = $tempSelect.find('option[value=""]').length;
                // Valid if there's more than one option, or one option that isn't a placeholder
                return totalOptions > 1 || (totalOptions === 1 && placeholderOptions === 0);
            }

            var currentTableOptions = '';

            // 1. Try global allTablesOptionsHTML (populated from PHP $view_tables_options_html)
            if (hasValidTableOptions(allTablesOptionsHTML)) {
                currentTableOptions = allTablesOptionsHTML;
                console.log("Using allTablesOptionsHTML (from view variable)");
            } else {
                // 2. Fallback: Try to get from the #fieldCloneTable template itself
                console.log("allTablesOptionsHTML is invalid, trying #fieldCloneTable template.");
                var templateTableOptions = $('#fieldCloneTable select.jointable').html();
                if (hasValidTableOptions(templateTableOptions)) {
                    currentTableOptions = templateTableOptions;
                    console.log("Using #fieldCloneTable select.jointable HTML.");
                } else {
                    console.error("Fallback #fieldCloneTable also has invalid options:", templateTableOptions);
                    $.jGrowl('Error: Could not find a valid table list. Please refresh the page or ensure tables are loaded.', { header: 'Error', theme: 'error', life: 7000 });
                    return; // Critical error, cannot proceed
                }
            }

            // Ensure allTablesOptionsHTML is updated globally if we found a valid source from the template
            // This helps if btnJoinTable is clicked later without re-running this specific edit logic.
            if (allTablesOptionsHTML !== currentTableOptions && hasValidTableOptions(currentTableOptions)) {
                allTablesOptionsHTML = currentTableOptions;
            }


            // Initialize joins if present
            if (parsedParams.jointype && Array.isArray(parsedParams.jointype)) {
                var $template = $('#fieldCloneTable'); // This is the template div
                
                parsedParams.jointype.forEach((type, idx) => {
                    var $clone = $template.clone() // Clone the template
                                       .removeAttr('id')
                                       .addClass('cloned-join-row'); // Make it a live row
                    
                    // Set up join type
                    $clone.find('select.jointype').val(type);
                    
                    // Set up table selection using the determined currentTableOptions
                    var $tableSelect = $clone.find('select.jointable');
                    $tableSelect.html(currentTableOptions); // Populate with the valid options
                    $tableSelect.val(parsedParams.jointable[idx]);
                    
                    // Add to form (before #btnJoinTable is good for UI)
                    $('#btnJoinTable').before($clone); // Changed from .after() to .before() for better UI flow.
                                                      // Or append to a specific container for joins if one exists.
                                                      // For now, placing before the button is fine.
                    
                    // Initialize select2 on all selects within the new clone
                    $clone.find('select').each(function() {
                        if ($(this).data('select2')) {
                            $(this).select2('destroy');
                        }
                        var placeholder = $(this).find('option[value=""]').text() || $(this).data('placeholder') || 'Choose';
                        $(this).select2({
                            placeholder: placeholder,
                            allowClear: true
                        });
                    });
                    
                    $clone.slideDown('fast'); // Show the new row
                    
                    // Populate join fields for this specific join
                    populateJoinFieldDropdown(
                        $clone.find('select.joinfieldselected'), // The target dropdown for fields of the joined table
                        parsedParams.jointable[idx], // The table that was joined
                        parsedParams.joinfield[idx], // The field in the joined table to select
                        function(success) {
                            if (success) {
                                // Set the primary table's joining field after the other dropdown is populated
                                $clone.find('select.joinfieldmain').val(parsedParams.joinfieldp[idx]);
                                // Trigger change for select2 to update display
                                $clone.find('select').trigger('change.select2');
                            }
                        }
                    );
                });
                
                // After all joins are processed and added, update all general dropdowns in the VQB
                addTablesToDropdown(function(success) {
                    if (success) {
                        // Now that options are loaded by addTablesToDropdown, set the saved fields.
                        if (parsedParams.fields && Array.isArray(parsedParams.fields)) {
                            $('#modal-visual-query select.fields').val(parsedParams.fields).trigger('change.select2');
                            console.log("Applied saved fields:", parsedParams.fields);
                        } else {
                            console.log("No saved fields found in parsedParams or not an array:", parsedParams.fields);
                        }
                        // Also re-apply other field types if they depend on options from addTablesToDropdown
                        // (e.g., orderfields, groupfields if they were not fully set up before this callback)
                        // For now, focusing on select.fields as per the issue.
                        // TODO: Review if other VQB elements like order by, group by need similar delayed population if their options are from addTablesToDropdown
                    } else {
                        // If addTablesToDropdown failed, fields might be incomplete.
                        $.jGrowl('Warning: Some fields in the query builder may not be fully loaded due to an issue updating dropdowns.', { header: 'Warning', theme: 'warning', life: 6000 });
                    }
                    // Show the modal regardless of partial success of addTablesToDropdown, but after attempting to set values
                    $('#modal-visual-query').modal('show');
                });
            } else {
                // No joins to initialize, but still need to ensure field dropdowns are correct for the primary table.
                 addTablesToDropdown(function(success) { // Ensure fields for primary table are loaded
                    if (success) {
                        // Now that options are loaded by addTablesToDropdown, set the saved fields.
                        if (parsedParams.fields && Array.isArray(parsedParams.fields)) {
                            $('#modal-visual-query select.fields').val(parsedParams.fields).trigger('change.select2');
                             console.log("Applied saved fields (no joins case):", parsedParams.fields);
                        } else {
                            console.log("No saved fields found in parsedParams (no joins case) or not an array:", parsedParams.fields);
                        }
                    } else {
                         $.jGrowl('Warning: Fields for the primary table might not be fully loaded.', { header: 'Warning', theme: 'warning', life: 6000 });
                    }
                    // Show the modal regardless
                    $('#modal-visual-query').modal('show');
                });
            }
            
        } catch (e) {
            console.error("Error processing visual query parameters:", e);
            $.jGrowl('Error loading visual query. Opening as SQL instead.', { header: 'Warning', theme: 'warning', life: 5000 });
            $('#sql').val(sqlQuery);
            $('#modal-custom-query').modal('show');
        }
    } else {
        // Open as SQL query
        $('#sql').val(sqlQuery);
        $('#modal-custom-query').modal('show');
    }
});

// Add these helper functions after the click handler:

function initializeVisualQueryBuilder(parsedParams, sqlQuery, queryName) {
    // Clear existing query builder state
    $('#modal-visual-query .parent.cloned-join-row').remove();
    
    // Store the query being edited
    $('#modal-visual-query').data({
        'editing-query-name': queryName,
        'original-sql': sqlQuery
    });

    // Set up initial table context
    var inferredPrimaryTable = parsedParams.primaryTable || '';
    var tableContextToUse = inferredPrimaryTable || __table;
    
    if (!tableContextToUse) {
        $.jGrowl('Cannot determine table context for Visual Query Builder.', { header: 'Warning' });
    }

    // Update global table context
    __table = tableContextToUse;
    $('#modal-visual-query .vqb-table-name').text('Table: ' + (tableContextToUse ? tableContextToUse.toUpperCase() : 'UNKNOWN'));

    // Initialize the form with stored parameters
    initializeVisualQueryForm(parsedParams);

    // Show the modal after initialization
    $('#modal-visual-query').modal('show');
}

function initializeVisualQueryForm(params) {
    // Set form values from params
    if (params.fields) {
        $('#modal-visual-query select.fields').val(params.fields);
    }
    
    // Initialize joins after the base form is set up
    if (params.jointype && Array.isArray(params.jointype)) {
        var joinIndex = 0;
        var processNextJoin = function() {
            if (joinIndex >= params.jointype.length) {
                // Final update of all dropdowns
                addTablesToDropdown(function(finalAtdSuccess) {
                    if (finalAtdSuccess) {
                        // Update all select2 elements
                        $('#modal-visual-query select').trigger('change.select2');
                        $.jGrowl('Visual editor ready.', { header: 'Info', life: 3000 });
                    }
                });
                return;
            }

            var joinDefinition = {
                type: params.jointype[joinIndex],
                table: params.jointable[joinIndex],
                field: params.joinfield[joinIndex],
                primaryField: params.joinfieldp[joinIndex]
            };

            // Clone and setup join row
            setupJoinRow(joinDefinition, function() {
                joinIndex++;
                processNextJoin();
            });
        };

        // Start processing joins
        processNextJoin();
    }
}

function setupJoinRow(joinDef, callback) {
    var $clone = $('#fieldCloneTable').clone().removeAttr('id').addClass('cloned-join-row');
    
    // Set values for the new join row
    $clone.find('select.jointype').val(joinDef.type);
    $clone.find('select.jointable').html(allTablesOptionsHTML).val(joinDef.table);
    
    // Initialize select2 for the new elements
    $clone.find('select').select2();
    
    // Add the new row to the form
    $('#btnJoinTable').after($clone);
    $clone.slideDown('fast');

    // Populate the join fields
    populateJoinFieldDropdown(
        $clone.find('select.joinfieldselected'),
        joinDef.table,
        joinDef.field,
        function(success) {
            if (success) {
                $clone.find('select.joinfieldmain').val(joinDef.primaryField);
                // Update general dropdowns
                addTablesToDropdown(function() {
                    if (typeof callback === 'function') callback();
                });
            } else {
                console.warn("Failed to populate join field for table:", joinDef.table);
                if (typeof callback === 'function') callback();
            }
        }
    );
}

function openAsSQLQuery(sqlQuery, queryName) {
    // Open in SQL editor mode
    $('#sql').val(sqlQuery);
    $('#modal-custom-query').modal('show');
    $('#modal-save-query').data({
        'editing-query-name': queryName,
        'original-sql': sqlQuery
    });
}

// --- Update Visual Query (from VQB Modal) ---
$('body').on('click', '#btnUpdateVisualQuery', function() {
    var $modal = $('#modal-visual-query');
    var queryId = $modal.find('#visual_query_id_edit').val();

    if (!queryId) {
        $.jGrowl('Error: Query ID is missing. Cannot update.', { header: 'Error', theme: 'error' });
        return;
    }

    // Collect form data
    var formData = $modal.find('form').serializeArray();
    var visualParamsData = {};

    // Process serialized array into a structured object, handling multi-value fields
    formData.forEach(function(item) {
        // Check if item name ends with [], indicating an array
        if (item.name.endsWith('[]')) {
            var name = item.name.substring(0, item.name.length - 2);
            if (!visualParamsData[name]) {
                visualParamsData[name] = [];
            }
            if (item.value) { // Only push non-empty values for array fields
                visualParamsData[name].push(item.value);
            }
        } else {
            // For single value fields, only set if value is not empty,
            // or if it's a field that can legitimately be empty (like chkDescending if not checked - but serializeArray doesn't include unchecked boxes)
            // For simplicity here, we take all values. Specific handling for empty optional fields might be needed if they cause issues.
            if (item.value || item.name === 'chkDescending' || item.name === 'limitStart' || item.name === 'limitNumRows') {
                 // explicitly include chkDescending even if its value might be "on" or undefined
                if(item.name === 'chkDescending' && !$modal.find('input[name="chkDescending"]').is(':checked')){
                    // Ensure 'chkDescending' is not added if not checked, or set to a specific false value if required by backend
                    // For now, we'll let it be absent if not checked. If present, serializeArray gives its value.
                } else {
                    visualParamsData[item.name] = item.value;
                }
            }
        }
    });

    // Ensure array fields that might be empty are at least empty arrays
    var arrayFields = ['fields', 'agg_field', 'agg_func', 'agg_alias', 'jointype', 'jointable', 'joinfield', 'joinfieldp', 'ftype', 'fname', 'fvalue', 'groupfields', 'orderfields', 'htype', 'hfname', 'hfvalue'];
    arrayFields.forEach(function(fieldName) {
        if (!visualParamsData[fieldName]) {
            visualParamsData[fieldName] = [];
        }
    });

    // Handle checkbox for chkDescending specifically as serializeArray doesn't include it if unchecked
    if ($modal.find('input[name="chkDescending"]').is(':checked')) {
        visualParamsData.chkDescending = 'on'; // Or true, depending on what backend expects
    } else {
        delete visualParamsData.chkDescending; // Remove if not checked, or set to false/0
    }


    // Add primaryTable
    if (typeof __table !== 'undefined' && __table) {
        visualParamsData.primaryTable = __table;
    } else {
        console.warn('VQB Update: __table is not defined. primaryTable will not be included in visual_params.');
    }

    // Remove the hidden input visual_query_id_edit from params sent to server
    // as it's not part of the actual visual query structure
    if ('visual_query_id_edit' in visualParamsData){
        delete visualParamsData.visual_query_id_edit;
    }
    if ('visual_query_id_edit_submit' in visualParamsData){ // also remove the other one if present
        delete visualParamsData.visual_query_id_edit_submit;
    }


    var visualParamsJsonString = JSON.stringify(visualParamsData);

    console.log("Updating Visual Query ID:", queryId);
    console.log("Visual Params to save:", visualParamsJsonString);

    var $thisButton = $(this);
    $thisButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');

    // AJAX call to save/update the query
    $.ajax({
        url: base + '/ajax/saveQuery',
        type: 'POST',
        data: {
            query_id: queryId,
            visual_params: visualParamsJsonString,
            is_visual_query: true
            // We are not sending sql_query here. The backend should ideally handle
            // updating/clearing the SQL query if visual_params change, or the SQL could become stale.
            // query_name is also not updated here; that's handled by a separate modal.
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                $.jGrowl(response.message || 'Visual query updated successfully!', { header: 'Success', theme: 'success', life: 3000 });

                // Update cache if it exists
                if (typeof savedQueriesCache !== 'undefined' && savedQueriesCache.find) {
                    var itemInCache = savedQueriesCache.find(function(q) { return q.id == queryId; });
                    if (itemInCache) {
                        itemInCache.visual_params = visualParamsJsonString;
                        itemInCache.is_visual_query = true;
                        // If sql_query was also updated and returned by server, update it here too.
                        // itemInCache.sql_query = new_sql_query_from_server;
                    }
                }
                // Optionally close the modal
                // $modal.modal('hide');
            } else {
                $.jGrowl(response.message || 'An error occurred while updating the query.', { header: 'Error', theme: 'error', life: 5000 });
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $.jGrowl('AJAX Error: ' + textStatus + ' - ' + errorThrown, { header: 'AJAX Error', theme: 'error', life: 5000 });
        },
        complete: function() {
            $thisButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
        }
    });
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
    console.log("DEBUG: #btnJoinTable click - allTablesOptionsHTML content (first 200 chars):", typeof allTablesOptionsHTML !== 'undefined' ? allTablesOptionsHTML.substring(0,200) : 'NOT DEFINED');
    // Ensure the template's jointable select has fresh options before cloning
    if (typeof allTablesOptionsHTML !== 'undefined') {
        $('#fieldCloneTable').find('select.jointable').html(allTablesOptionsHTML);
    } else {
        console.error("allTablesOptionsHTML is not defined. Cannot populate join table template for new join.");
    }
    console.log("DEBUG: btnJoinTable Click - BEFORE CLONE - HTML of #fieldCloneTable select.jointable:", $('#fieldCloneTable').find('select.jointable').html());
    console.log("DEBUG: btnJoinTable Click - BEFORE CLONE - Options count for #fieldCloneTable select.jointable:", $('#fieldCloneTable').find('select.jointable option').length);

    var $clone = $('#fieldCloneTable').clone();
    console.log("DEBUG: btnJoinTable Click - AFTER CLONE - HTML of CLONED select.jointable:", $clone.find('select.jointable').html());
    console.log("DEBUG: btnJoinTable Click - AFTER CLONE - Options count in CLONED select.jointable:", $clone.find('select.jointable option').length);

    $(this).after($clone);
    $clone.slideDown('fast');

    $clone.find('.select2-container').remove();
    $clone.find('.joinfieldselected').empty();
    $clone.find('select').select2();

    console.log("DEBUG: btnJoinTable Click - AFTER CLONE & S2 INIT - Cloned select.jointable S2 data:", $clone.find('select.jointable').data('select2'));
    console.log("DEBUG: btnJoinTable Click - AFTER CLONE & S2 INIT - HTML of CLONED s.jointable post-S2:", $clone.find('select.jointable').html());

    $('#addjoinedtablefields').slideDown('fast');
});

// to get fields for selected table for visual query
$('body').on('change', 'select.jointable', function () {
    var value = this.value;

    if (value) {
        var $this = $(this);
        var $targetSelect = $this.closest('.parent').find('select.joinfieldselected');
       // console.log("Manual Join: Selected table:", value, "Target select:", $targetSelect);

        // Use the new populateJoinFieldDropdown function
        populateJoinFieldDropdown($targetSelect, value, null, function(success) {
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
$('body').on('click', '#btnShowSaveQueryModal', function() {
    var sqlQueryText = $('#generatedQueryDisplay pre').text();
    if (!sqlQueryText || $.trim(sqlQueryText) === '') {
        $.jGrowl('No query generated yet to save!', { sticky: false, header: 'Error', theme: 'error' });
        return;
    }

    $('#sql_query_save').val(sqlQueryText);
    $('#query_name_save').val(''); // Always clear name for a new save

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

$('#addjoinedtablefields').click(function(event) {
    event.preventDefault(); // Prevent default anchor action if it's a link
    addTablesToDropdown(); // Call with no callback for default behavior
});

// dynamically populate dropdowns for selected tables for visual query
// Takes an optional callback function to execute after dropdowns are populated and initialized
function addTablesToDropdown(callback) {
    // __table should be the primary table for the VQB context.
    // Ensure __table is defined and not empty. If not, we can't proceed.
    if (typeof __table === 'undefined' || !__table) {
        console.error("__table (primary table context) is not defined for addTablesToDropdown.");
        $.jGrowl('Primary table context not set. Cannot load fields.', {header: 'Error', theme: 'error'});
        if (typeof callback === 'function') {
            callback(false); // Indicate failure
        }
        return;
    }

    const selectedTables = [__table]; 
    // console.log('Initial Table for Dropdowns:', __table);

    // Collect any currently joined tables in the VQB
    $('#modal-visual-query .jointable').each(function() { // Scope to VQB modal
        const table = $(this).val();
        if (table && !selectedTables.includes(table)) {
            selectedTables.push(table);
        }
    });

    // console.log('Final Tables Sent for getselectfields:', selectedTables);

    if (selectedTables.length > 0) {
        const postData = {"tables": JSON.stringify(selectedTables)};

        $.post(base + '/ajax/getselectfields', postData, function(response) {
            // console.log('Server Response for getselectfields:', response);
            
            var selectorsToUpdate = [
                '#modal-visual-query select.fields', // VQB main fields
                '#modal-visual-query select.fname', // WHERE clause fields
                '#modal-visual-query select.orderfields', // ORDER BY fields
                '#modal-visual-query select.groupfields', // GROUP BY fields
                '#modal-visual-query select.joinfieldmain', // JOIN clause primary table fields
                '#modal-visual-query select.agg_field' // Aggregate function fields
                // Note: .hfname (HAVING) is handled by updateHavingFieldNameOptions separately,
                // but updateHavingFieldNameOptions itself relies on select.fields being populated.
            ];

            $(selectorsToUpdate.join(', ')).each(function() {
                var $select = $(this);
                var currentValues = $select.val();

                try {
                    $select.select2('destroy');
                } catch (e) {
                    // console.warn('Could not destroy select2 instance on an element:', $select, e);
                }

                $select.html(response);

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

                var placeholderText = $select.data('placeholder') || 'Choose';
                $select.select2({ placeholder: placeholderText, allowClear: true });
            });
            
            // After updating general field dropdowns, also update the HAVING clause options
            // as it depends on the main fields list.
            updateHavingFieldNameOptions();


            $.jGrowl('Fields updated for selected tables!');
            if (typeof callback === 'function') {
                callback(true); // Indicate success
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            // console.error('AJAX Error in addTablesToDropdown:', textStatus, errorThrown);
            $.jGrowl('Error loading fields: ' + textStatus, {header: 'Error', theme: 'error'});
            if (typeof callback === 'function') {
                callback(false); // Indicate failure
            }
        });
    } else {
        // Should not happen if __table is always present
        console.warn("No tables selected for addTablesToDropdown, including primary __table.");
        if (typeof callback === 'function') {
            callback(false); // Indicate failure or no action
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
function populateJoinFieldDropdown($selectElement, tableName, selectedValue, callback) {
    // console.log("populateJFD - Called. Table:", tableName, "SelectedVal:", selectedValue, "Element:", $selectElement.length ? $selectElement[0] : 'not found'); // DEBUG
    if (!tableName) {
        console.error("populateJoinFieldDropdown: tableName is required.");
        if (typeof callback === 'function') callback(false);
        return;
    }

    // console.log("Populating join field dropdown for table:", tableName, "Target select:", $selectElement);

    $.post(base + '/ajax/gettablefields', { "table": tableName }, function (response) {
        // console.log("populateJFD - AJAX Success. Table:", tableName, "Resp:", JSON.stringify(response)); // DEBUG
        if (response && response.status === 'success' && response.fields) {
            var optionsHtml = '<option value="">Choose Field</option>'; // Add a default empty option
            response.fields.forEach(function(field) {
                optionsHtml += '<option value="' + escapeHtml(field) + '">' + escapeHtml(field) + '</option>';
            });
            // console.log("populateJFD - Options HTML for " + tableName + ":", optionsHtml.substring(0, 200)); // DEBUG

            try {
                $selectElement.select2('destroy'); // Destroy existing Select2 if any
            } catch(e) { /* ignore if not initialized */ }

            $selectElement.html(optionsHtml);

            if (selectedValue) {
                // console.log("populateJFD - Attempting to select value for " + tableName + ":", selectedValue); // DEBUG
                $selectElement.val(selectedValue);
                // console.log("populateJFD - Value after setting for " + tableName + ":", $selectElement.val()); // DEBUG
            }

            $selectElement.select2({ placeholder: 'Choose Field', allowClear: true });
            if (typeof callback === 'function') callback(true);

        } else {
            console.error("Error fetching fields for table " + tableName + ":", response.message);
            $.jGrowl('Error loading fields for ' + tableName + ': ' + (response.message || 'Unknown error'), { sticky: false, header: 'Error' });
            if (typeof callback === 'function') callback(false);
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        // console.error("populateJFD - AJAX FAIL. Table: " + tableName, jqXHR, textStatus, errorThrown); // DEBUG
        $.jGrowl('AJAX Error: Could not load fields for ' + tableName + '.', { sticky: false, header: 'Error' });
        if (typeof callback === 'function') callback(false);
    });
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
