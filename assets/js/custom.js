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
    $('#aggregateFieldsContainer').append($clone);
    $clone.find('.agg_field').select2({ placeholder: 'Select Field', allowClear: true }); // Initialize select2 for the field selection
    // No need to initialize select2 for agg_func unless specific styling/features are needed for it.
    $clone.slideDown('fast');
    updateHavingFieldNameOptions(); // Update HAVING field options when a new aggregate is added
});

// add Having condition for visual query
$('#btnAddHavingCondition').click(function () {
    var $clone = $('#fieldCloneHaving').clone().removeAttr('id');
    $('#havingConditionsContainer').append($clone);
    // Initialize select2 for the hfname dropdown, it will be populated by updateHavingFieldNameOptions
    $clone.find('.hfname').select2({ placeholder: 'Select Field/Alias', allowClear: true });
    $clone.slideDown('fast');
    updateHavingFieldNameOptions(); // Ensure new HAVING rows get the correct options
});

// When aggregate alias or group by fields change, update HAVING field name options
$('body').on('change', '.agg_alias, .groupfields', function() {
    updateHavingFieldNameOptions();
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
        $select.html(newHtml);
        if (currentValue && $select.find('option[value="' + currentValue + '"]').length > 0) {
            $select.val(currentValue);
        }
        $select.trigger('change.select2'); // Notify select2 of update
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
