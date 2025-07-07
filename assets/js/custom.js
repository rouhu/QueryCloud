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

// --- Reusable function to open and populate VQB Modal ---
function openVisualQueryBuilderModal(visualParamsObj, queryId, queryName, isEditingSaved) {
    var $modal = $('#modal-visual-query');
    var $selectedFieldsContainer = $modal.find('#selectedFieldsContainer');
    var $noSelectedMsg = $modal.find('#noSelectedFieldsMsg');
    
    // Clear/Reset VQB form elements (important before populating)
    // Non-aggregated fields - source select and the container for selected fields with aliases
    $modal.find('#fields_multiselect').val(null).trigger('change.select2'); // Clear the source selector
    $selectedFieldsContainer.empty(); // Clear previously added field/alias rows
    $noSelectedMsg.show(); // Show 'no fields' message initially

    // Aggregated fields
    $('#aggregateFieldsContainer').empty();
    // WHERE conditions
    $('#modal-visual-query form .parent').not('#fieldClone, #fieldCloneTable, #fieldCloneAggregate, #fieldCloneHaving, #orderby, #group, #limit').remove(); // remove only cloned condition rows
    // ORDER BY
    $modal.find('select.orderfields').val(null).trigger('change.select2');
    $modal.find('input[name="chkDescending"]').prop('checked', false);
    $('#orderby').hide(); // Hide the section if it was shown
    // GROUP BY
    $modal.find('select.groupfields').val(null).trigger('change.select2');
    $('#group').hide();
    // LIMIT
    $modal.find('input[name="limitStart"]').val('');
    $modal.find('input[name="limitNumRows"]').val('');
    $('#limit').hide();
    // HAVING Conditions
    $('#havingConditionsContainer').empty();
    // Clear existing join rows
    $modal.find('.cloned-join-row').remove();


    // Set primary table context
    __table = visualParamsObj.primaryTable || '';
    $modal.find('.vqb-table-name').text('Table: ' + (__table ? __table.toUpperCase() : 'UNKNOWN'));
    // TODO: Update modal title if queryName is provided: $modal.find('.modal-title').text('Editing: ' + queryName);

    // Store queryId if editing a saved query
    if (isEditingSaved && queryId) {
        $modal.find('#visual_query_id_edit').val(queryId);
    } else {
        $modal.find('#visual_query_id_edit').val(''); // Clear if not editing a saved one
    }

    // Set VQB form action
    if (__table) {
        var formActionUrl = base + '/table/' + __table;
        $modal.find('form').attr('action', formActionUrl);
        console.log("VQB form action set to:", formActionUrl);
    } else {
        console.error("VQB: __table is not defined, cannot set form action. Submitting to current page.");
        $modal.find('form').attr('action', '');
    }

    // Determine currentTableOptions for join dropdowns
    var currentTableOptions = '';
    // Helper function (defined within .btn-edit-saved-query previously, needs to be accessible or redefined)
    // For simplicity, assuming hasValidTableOptions is globally available or we inline a simplified check
    function _hasValidTableOptions(htmlString) { // Simplified local version
        return htmlString && typeof htmlString === 'string' && htmlString.indexOf('<option') !== -1 && $(htmlString).length > 1;
    }

    if (_hasValidTableOptions(allTablesOptionsHTML)) {
        currentTableOptions = allTablesOptionsHTML;
    } else {
        var templateTableOptions = $('#fieldCloneTable select.jointable').html();
        if (_hasValidTableOptions(templateTableOptions)) {
            currentTableOptions = templateTableOptions;
        } else {
            $.jGrowl('Error: Could not find a valid table list for VQB.', { header: 'Error', theme: 'error' });
            // Do not show modal if table options are missing
            return;
        }
    }
    if (allTablesOptionsHTML !== currentTableOptions && _hasValidTableOptions(currentTableOptions)) {
        allTablesOptionsHTML = currentTableOptions; // Ensure global one is updated if template was better
    }

    // Populate Joins
    if (visualParamsObj.jointype && Array.isArray(visualParamsObj.jointype)) {
        var $joinTemplate = $('#fieldCloneTable');
        visualParamsObj.jointype.forEach(function(type, idx) {
            var $clone = $joinTemplate.clone().removeAttr('id').addClass('cloned-join-row');
            $clone.find('select.jointype').val(type);
            var $tableSelect = $clone.find('select.jointable');
            $tableSelect.html(currentTableOptions).val(visualParamsObj.jointable[idx]);

            $('#btnJoinTable').before($clone); // Insert before the button
            
            $clone.find('select').each(function() {
                if ($(this).data('select2')) $(this).select2('destroy');
                $(this).select2({ placeholder: 'Choose', allowClear: true });
            });
            $clone.show(); // Ensure it's visible

            populateJoinFieldDropdown(
                $clone.find('select.joinfieldselected'),
                visualParamsObj.jointable[idx],
                visualParamsObj.joinfield[idx],
                function(success) {
                    // if (success) {
                    //     // Value for joinfieldmain will be set after addTablesToDropdown completes for all fields
                    // }
                    // Still trigger change for the joinfieldselected that was just populated
                    $clone.find('select.joinfieldselected').trigger('change.select2');
                }
            );
            // Store the target value for joinfieldp (primary table's field) on the element itself
            // It will be applied after addTablesToDropdown finishes loading all options.
            if (visualParamsObj.joinfieldp && visualParamsObj.joinfieldp[idx]) {
                $clone.find('select.joinfieldmain').data('saved-value', visualParamsObj.joinfieldp[idx]);
            }
        });
    }

    // Load options for all field dropdowns and then populate other VQB elements
    addTablesToDropdown(function(success) {
        if (success) {
            // Non-aggregated fields: Populate the list of selected fields with aliases
            // This replaces the direct setting of $modal.find('select[name="fields[]"]')
            if (visualParamsObj.fields && Array.isArray(visualParamsObj.fields)) {
                var $fieldAliasTemplate = $('#fieldAliasRowTemplate');
                if (visualParamsObj.fields.length > 0) {
                    $noSelectedMsg.hide();
                }
                visualParamsObj.fields.forEach(function(fieldObj) {
                    // Ensure fieldObj is an object and has 'field' property
                    if (fieldObj && typeof fieldObj.field === 'string') {
                        var $clone = $fieldAliasTemplate.clone().removeAttr('id').show();
                        // Attempt to find the display text from the source multiselect options
                        // Note: #fields_multiselect options are populated by addTablesToDropdown itself.
                        var fieldDisplayText = $modal.find('#fields_multiselect option[value="' + fieldObj.field + '"]').text();
                        $clone.find('.selected-field-name-display').text(fieldDisplayText || fieldObj.field);
                        $clone.find('.selected-field-name-hidden').val(fieldObj.field);
                        $clone.find('.field-alias-input').val(fieldObj.alias || '');
                        $selectedFieldsContainer.append($clone);
                    } else {
                        // Handle legacy string array for fields if necessary, or log warning
                        console.warn("Legacy or malformed 'fields' data found in visualParamsObj:", fieldObj);
                         // If it's a string (legacy format), create a row without an alias
                        if (typeof fieldObj === 'string') {
                            var $clone = $fieldAliasTemplate.clone().removeAttr('id').show();
                            var fieldDisplayText = $modal.find('#fields_multiselect option[value="' + fieldObj + '"]').text();
                            $clone.find('.selected-field-name-display').text(fieldDisplayText || fieldObj);
                            $clone.find('.selected-field-name-hidden').val(fieldObj);
                            $clone.find('.field-alias-input').val(''); // No alias for legacy string format
                            $selectedFieldsContainer.append($clone);
                            if(visualParamsObj.fields.length > 0) $noSelectedMsg.hide(); // Hide msg if we add anything
                        }
                    }
                });
            }


            // After all options are loaded by addTablesToDropdown,
            // now set the saved values for joinfieldmain in each cloned join row.
            $modal.find('.cloned-join-row').each(function() {
                var $clonedJoinRow = $(this);
                var $joinfieldmainSelect = $clonedJoinRow.find('select.joinfieldmain');
                var savedValue = $joinfieldmainSelect.data('saved-value');
                if (savedValue) {
                    $joinfieldmainSelect.val(savedValue).trigger('change.select2');
                }
            });

            // TODO: Populate WHERE conditions (dynamic rows)
            // Iterate visualParamsObj.fname, fvalue, ftype. For each, clone #fieldClone, set values, append.
            if (visualParamsObj.fname && Array.isArray(visualParamsObj.fname)) {
                visualParamsObj.fname.forEach(function(name, idx){
                    if(name && visualParamsObj.fvalue[idx]){ // Ensure field and value exist
                        var $c = $('#fieldClone').clone().removeAttr('id').show();
                        $c.find('select.fname').val(name);
                        $c.find('input[name="fvalue[]"]').val(visualParamsObj.fvalue[idx]);
                        if(idx > 0 && visualParamsObj.ftype[idx]) { // ftype links current to previous
                             $c.find('select[name="ftype[]"]').val(visualParamsObj.ftype[idx]);
                        } else if (idx === 0 && visualParamsObj.ftype && visualParamsObj.ftype[0]) {
                            // If ftype[0] is set (e.g. from older save), handle it for the first actual condition
                            // This might be redundant if ftype is always for linking *between* conditions
                        }
                        $('#btnAddWhere').after($c); // Add new condition row
                        $c.find('select').select2(); // Initialize select2 on new row
                    }
                });
            }


            // TODO: Populate Aggregated Fields (dynamic rows)
             if (visualParamsObj.agg_field && Array.isArray(visualParamsObj.agg_field)) {
                visualParamsObj.agg_field.forEach(function(field, idx) {
                    if (field && visualParamsObj.agg_func[idx]) {
                        var $aggClone = $('#fieldCloneAggregate').clone().removeAttr('id').show();
                        $aggClone.find('.agg_field').val(field);
                        $aggClone.find('.agg_func').val(visualParamsObj.agg_func[idx]);
                        $aggClone.find('.agg_alias').val(visualParamsObj.agg_alias[idx] || '');
                        $('#aggregateFieldsContainer').append($aggClone);
                        $aggClone.find('select').select2();
                    }
                });
            }


            // TODO: Populate GROUP BY fields
            if (visualParamsObj.groupfields && Array.isArray(visualParamsObj.groupfields) && visualParamsObj.groupfields.length > 0) {
                $modal.find('select.groupfields').val(visualParamsObj.groupfields).trigger('change.select2');
                $('#group').show();
            }

            // TODO: Populate ORDER BY fields
             if (visualParamsObj.orderfields && Array.isArray(visualParamsObj.orderfields) && visualParamsObj.orderfields.length > 0) {
                $modal.find('select.orderfields').val(visualParamsObj.orderfields).trigger('change.select2');
                if (visualParamsObj.chkDescending === 'on' || visualParamsObj.chkDescending === true) {
                    $modal.find('input[name="chkDescending"]').prop('checked', true);
                } else {
                    $modal.find('input[name="chkDescending"]').prop('checked', false);
                }
                $('#orderby').show();
            }

            // TODO: Populate LIMIT
            if (visualParamsObj.limitStart || visualParamsObj.limitNumRows) {
                 $modal.find('input[name="limitStart"]').val(visualParamsObj.limitStart || '');
                 $modal.find('input[name="limitNumRows"]').val(visualParamsObj.limitNumRows || '');
                 $('#limit').show();
            }

            // TODO: Populate HAVING conditions (dynamic rows)
            if (visualParamsObj.hfname && Array.isArray(visualParamsObj.hfname)) {
                visualParamsObj.hfname.forEach(function(name, idx){
                     if(name && visualParamsObj.hfvalue[idx]){
                        var $hClone = $('#fieldCloneHaving').clone().removeAttr('id').show();
                        $hClone.find('select.hfname').val(name); // Options for hfname are updated by updateHavingFieldNameOptions
                        $hClone.find('input[name="hfvalue[]"]').val(visualParamsObj.hfvalue[idx]);
                         if(idx > 0 && visualParamsObj.htype[idx]) {
                             $hClone.find('select[name="htype[]"]').val(visualParamsObj.htype[idx]);
                         }
                        $('#havingConditionsContainer').append($hClone);
                        $hClone.find('select.hfname').select2(); //Initialize select2 for hfname
                    }
                });
            }
            updateHavingFieldNameOptions(); // Call this after aggregates and groups are potentially populated

        } else {
            $.jGrowl('Warning: VQB fields may not be fully loaded.', { header: 'Warning' });
        }
        $modal.modal('show');
    });
}

// --- Non-Aggregated Field Alias UI Handlers ---
// Add selected fields from multiselect to the aliasing list
$('body').on('click', '#btnAddFieldsToQueryList', function() {
    var $modal = $('#modal-visual-query');
    var $sourceSelect = $modal.find('#fields_multiselect');
    var $container = $modal.find('#selectedFieldsContainer');
    var $noSelectedMsg = $modal.find('#noSelectedFieldsMsg');
    var $template = $('#fieldAliasRowTemplate');

    var selectedOptions = $sourceSelect.val();

    if (selectedOptions && selectedOptions.length > 0) {
        selectedOptions.forEach(function(fieldValue) {
            // Check if this field is already added to avoid duplicates
            var isAlreadyAdded = false;
            $container.find('.selected-field-name-hidden').each(function() {
                if ($(this).val() === fieldValue) {
                    isAlreadyAdded = true;
                    return false; // break loop
                }
            });

            if (!isAlreadyAdded) {
                var $clone = $template.clone().removeAttr('id').show();
                var fieldText = $sourceSelect.find('option[value="' + fieldValue + '"]').text(); // Get text for display
                $clone.find('.selected-field-name-display').text(fieldText || fieldValue);
                $clone.find('.selected-field-name-hidden').val(fieldValue);
                $clone.find('.field-alias-input').val(''); // Clear any previous alias from template
                $container.append($clone);
            }
        });
        // $sourceSelect.val(null).trigger('change.select2'); // Optionally clear selection from source
        $noSelectedMsg.hide();
    } else {
        $.jGrowl('No fields selected from the list to add.', { header: 'Info' });
    }
});

// Remove a field/alias row
$('body').on('click', '.remove-field-alias-row', function() {
    $(this).closest('.parent').remove();
    var $modal = $('#modal-visual-query');
    var $container = $modal.find('#selectedFieldsContainer');
    var $noSelectedMsg = $modal.find('#noSelectedFieldsMsg');
    if ($container.find('.parent:visible').length === 0) {
        $noSelectedMsg.show();
    }
});


// --- Edit Saved Query Functionality ---
$('body').on('click', '.btn-edit-saved-query', function() {
    var queryId = $(this).data('query-id');
    var queryName = $(this).data('query-name');

    var queryData = savedQueriesCache?.find(q => q.id == queryId);
    if (!queryData) {
        $.jGrowl('Could not retrieve query details. Please refresh.', { header: 'Error' });
        return;
    }

    if (queryData.is_visual_query == '1' || queryData.is_visual_query === true) {
        if (queryData.visual_params) {
            try {
                var parsedParams = JSON.parse(queryData.visual_params);
                openVisualQueryBuilderModal(parsedParams, queryId, queryName, true);
            } catch (e) {
                console.error("Error parsing visual_params for saved query:", e);
                $.jGrowl('Error loading visual query data. Opening as SQL.', { header: 'Error' });
                $('#sql').val(queryData.sql_query); // Fallback to SQL editor
                $('#modal-custom-query').modal('show');
            }
        } else {
            $.jGrowl('Saved visual query has no parameters. Opening as SQL.', { header: 'Warning' });
            $('#sql').val(queryData.sql_query); // Fallback to SQL editor
            $('#modal-custom-query').modal('show');
        }
    } else { // Not a visual query, open in SQL editor
        $('#sql').val(queryData.sql_query);
        // Populate fields for custom query modal if needed (e.g. queryId for update)
        $('#custom_query_id_edit').val(queryId);
        // Potentially clear name edit field if it exists: $('#custom_query_name_edit').val(queryName);
        $('#modal-custom-query').modal('show');
    }
});


// --- Edit Executed Query Button ---
$('body').on('click', '#btnEditExecutedQuery', function() {
    var visualParamsJsonString = $('#current_visual_params').val();
    var executedQueryId = $('#executed_query_id').val(); // Might be empty if not a saved query
    var executedQueryName = $('#executed_query_name').val(); // Might be empty
    var wasSavedVisual = $('#executed_query_was_saved_visual').val() === 'true';

    if (visualParamsJsonString && visualParamsJsonString !== '{}' && visualParamsJsonString !== '[]') {
        try {
            var parsedParams = JSON.parse(visualParamsJsonString);
            // If it was a saved visual query, its ID and name are used.
            // Otherwise, queryId and queryName will be empty, VQB opens for a new/adhoc query state.
            openVisualQueryBuilderModal(parsedParams, executedQueryId, executedQueryName, wasSavedVisual);
        } catch (e) {
            console.error("Error parsing #current_visual_params for editing:", e);
            $.jGrowl('Could not parse visual parameters to edit this query.', { header: 'Error' });
        }
    } else {
        $.jGrowl('This query was not run from the VQB or is not visually editable.', { header: 'Info' });
    }
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
    // Note: Non-aggregated fields are NOT collected via serializeArray anymore due to new UI.
    var traditionalFormData = {}; // For fields other than non-aggregated `fields`
    formData.forEach(function(item) {
        // The `fields[]` from the main multi-select (now fields_multiselect) should not be part of visualParamsData directly.
        // It's only a source list. The actual selected fields are in selectedFieldsContainer.
        if (item.name === 'fields[]') { // This name is from the original <select name="fields[]"> which is now #fields_multiselect
            console.warn("Skipping fields_multiselect (name='fields[]') during VQB update data collection as it's a source list.");
            return;
        }

        if (item.name.endsWith('[]')) {
            var name = item.name.substring(0, item.name.length - 2);
            if (!traditionalFormData[name]) {
                traditionalFormData[name] = [];
            }
            if (item.value) {
                traditionalFormData[name].push(item.value);
            }
        } else {
            if (item.value || item.name === 'chkDescending' || item.name === 'limitStart' || item.name === 'limitNumRows') {
                if(item.name === 'chkDescending' && !$modal.find('input[name="chkDescending"]').is(':checked')){
                    // Skip if chkDescending is not checked
                } else {
                    traditionalFormData[item.name] = item.value;
                }
            }
        }
    });

    // Collect Non-Aggregated Fields with Aliases from the new UI
    visualParamsData.fields = []; // Initialize as array of objects
    $modal.find('#selectedFieldsContainer .parent:visible').each(function() { // Ensure we only process visible rows if any are hidden by other means
        var $row = $(this);
        var fieldName = $row.find('.selected-field-name-hidden').val();
        var fieldAlias = $row.find('.field-alias-input').val().trim();
        if (fieldName) { // Only add if a field name is present
            visualParamsData.fields.push({ field: fieldName, alias: fieldAlias });
        }
    });

    // Merge traditional form data (excluding 'fields') into visualParamsData
    for (var key in traditionalFormData) {
        if (traditionalFormData.hasOwnProperty(key)) {
            visualParamsData[key] = traditionalFormData[key];
        }
    }

    // Ensure array fields that might be empty are at least empty arrays
    // 'fields' is already handled and is an array of objects.
    var arrayFields = ['agg_field', 'agg_func', 'agg_alias', 'jointype', 'jointable', 'joinfield', 'joinfieldp', 'ftype', 'fname', 'fvalue', 'groupfields', 'orderfields', 'htype', 'hfname', 'hfvalue'];
    arrayFields.forEach(function(fieldName) {
        if (!visualParamsData[fieldName]) { // Check if it wasn't populated from traditionalFormData
            visualParamsData[fieldName] = [];
        }
    });

    // Handle checkbox for chkDescending (already part of traditionalFormData if checked)
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
