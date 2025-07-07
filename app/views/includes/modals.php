<?php
// Initialize fields if not set
$fields = $fields ?? [];
?>
<!-- delete confirm modal start -->
<div class="modal fade" id="modal-delete-confirm">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header label-danger">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-check-square-o"></i> <span
                       class="text-white bold">Delete</span></h4>
            </div>
            <div class="modal-body">
                <p class="pull-left" style="margin-right: 10px;"><i
                       class="glyphicon-4x glyphicon glyphicon-question-sign"></i></p>

                <p>You are about to delete, this procedure is irreversible.</p>

                <p>Do you want to proceed?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i>
                    Close
                </button>
                <button type="button" class="btn btnDelete btn-danger"><i class="fa fa-trash-o"></i> Delete</button>
            </div>

        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>
<!-- delete confirm modal end -->

<div class="modal fade" id="modal-detail">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header label-success">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-info-sign"></i> <span
                       class="text-white bold">Todo Details</span></h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-dismiss="modal"><i class="fa fa-times"></i>
                    Close
                </button>
            </div>

        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

<div class="modal fade" id="modal-custom-query">
    <div class="modal-dialog">
        <form action="" method="post">

            <div class="modal-content">
                <div class="modal-header label-success">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="text-white fa fa-pencil-square-o"></i> <span
                           class="text-white bold">Custom Query</span></h4>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="custom_query_id_edit" name="custom_query_id_edit">
                    <div class="form-group">
                        <label>SQL Query:</label>
                        <div id="ace" style="height: 250px; width: 100%;"></div>
                    </div>
                    <div id="updateCustomQueryMsg" class="alert" style="display:none;"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" id="btnUpdateSavedQuery" class="btn btn-primary"><i class="fa fa-save"></i>
                        Update Saved Query
                    </button>
                    <button type="button" id="btnCustomQuery" class="btn btn-success"><i class="fa fa-play"></i>
                        Run Query (from editor)
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i>
                        Close
                    </button>
                </div>

            </div>

            <input type="hidden" id="cquery" name="cquery"/>
            <!-- This hidden input 'cquery' is still used by the #btnCustomQuery (Run Query) button -->

        </form>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

<div class="modal fade" id="modal-visual-query">
    <div class="modal-dialog">
        <form action="" method="post" class="form-horizontal" role="form">

            <div class="modal-content">
                <div class="modal-header label-success">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="text-white fa fa-database"></i> <span
                           class="text-white bold">Visual Query</span></h4>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="visual_query_id_edit" name="visual_query_id_edit">
                     <hr>

                    <div class="form-group">
                    <h4 class="vqb-table-name"> Table: <?php echo strtoupper(Flight::get('lastSegment')); ?></h4>
                        <button style="margin-bottom: 10px !important;" type="button" id="btnJoinTable" class="btn btn-primary" rel="hover_popover" data-content="Join a table">
                            <i class="glyphicon glyphicon-plus-sign"></i> Join Table
                        </button>
                        <br/>
<a href="#" id="addjoinedtablefields" onclick="addTablesToDropdown(); return false;"><i class="fa fa-refresh"></i> Add Table Fields to Below Pulldowns </a>
                    </div>

                    <div class="form-group">
    <label class="control-label" for="fields">Select Non-Aggregated Fields</label>
    <div class="controls">
        <select name="fields[]" multiple="multiple" class="form-control fields" style="width: 100%;">
            <?php echo $fields; ?>
        </select>
    </div>
</div>

                    <div class="form-group">
                        <hr/>
                        <label class="control-label">Aggregated Fields</label>
                        <button style="margin-bottom: 10px !important;" type="button" id="btnAddAggregateField" class="btn btn-info" rel="hover_popover" data-content="Add SUM, COUNT, AVG etc.">
                            <i class="glyphicon glyphicon-plus-sign"></i> Add Aggregate Field
                        </button>
                        <div id="aggregateFieldsContainer">
                            <!-- Cloned aggregate fields will be inserted here -->
                        </div>
                        <hr/>
                    </div>

                    <div class="form-group">
                        <button style="margin-bottom: 10px !important;" type="button" id="btnAddWhere" class="btn btn-primary" rel="hover_popover" data-content="Add WHERE clause conditions">
                            <i class="glyphicon glyphicon-plus-sign"></i> Add Condition
                        </button>
                    </div>

                    <div class="form-group">
                        <button type="button" id="btnOrderby" class="btn btn-primary" rel="hover_popover" data-content="Add ORDER BY clause fields">
                            <i class="glyphicon glyphicon-plus-sign"></i> Add Order
                        </button>
                    </div>

                    <div class="form-group parent" style="display: none;" id="orderby">
                        <div class="pull-left">
                            <a href="#" class="remove"><i class="glyphicon glyphicon-trash glyphicon-2x" style="margin-top: 5px;"></i></a>
                        </div>
                        <div class="pull-left" style="margin: 3px;">
                            &nbsp;
                        </div>
                        <div class="controls pull-left">
                            <select name="orderfields[]" id="orderfields" multiple class="orderfields form-control" style="width: 400px;">
                                <?php echo $fields; ?>
                            </select>
                        </div>
                        <div class="pull-left">
                            &nbsp;&nbsp;
                        </div>
                        <div class="controls pull-left">
                            <input type="checkbox" id="chkDescending" name="chkDescending"/>
                            <label class="control-label" for="chkDescending" class="form-control">Descending</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="button" id="btnGroup" class="btn btn-primary" rel="hover_popover" data-content="Add GROUP BY clause fields">
                            <i class="glyphicon glyphicon-plus-sign"></i> Add Group Field
                        </button>
                    </div>

                    <div class="form-group parent" style="display: none;" id="group">
                        <div class="pull-left">
                            <a href="#" class="remove"><i class="glyphicon glyphicon-trash glyphicon-2x" style="margin-top: 5px;"></i></a>
                        </div>
                        <div class="pull-left" style="margin: 3px;">
                            &nbsp;
                        </div>
                        <div class="controls pull-left">
                            <select name="groupfields[]" id="groupfields" multiple class="groupfields form-control fields" style="width: 400px;">
                                <?php echo $fields; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <hr/>
                        <label class="control-label">Having Conditions</label>
                        <button style="margin-bottom: 10px !important;" type="button" id="btnAddHavingCondition" class="btn btn-warning" rel="hover_popover" data-content="Add HAVING clause conditions (filters on aggregate values)">
                            <i class="glyphicon glyphicon-filter"></i> Add Having Condition
                        </button>
                        <div id="havingConditionsContainer">
                            <!-- Cloned having conditions will be inserted here -->
                        </div>
                        <hr/>
                    </div>

                    <div class="form-group">
                        <button type="button" id="btnLimit" class="btn btn-primary" rel="hover_popover" data-content="Add LIMIT clause details">
                            <i class="glyphicon glyphicon-plus-sign"></i> Add Limit
                        </button>
                    </div>

                    <div class="form-group parent" style="display: none;" id="limit">
                        <div class="pull-left">
                            <a href="#" class="remove"><i class="glyphicon glyphicon-trash glyphicon-2x" style="margin-top: 5px;"></i></a>
                        </div>
                        <div class="pull-left" style="margin: 3px;">
                            &nbsp;
                        </div>
                        <div class="controls pull-left">
                            <input type="number" id="limitStart" name="limitStart" class="form-control" placeholder="Starting Row ID"/>
                        </div>
                        <div class="pull-left">
                            &nbsp;&nbsp;
                        </div>
                        <div class="controls pull-left">
                            <input type="number" id="limitNumRows" name="limitNumRows" class="form-control" placeholder="Number of Rows"/>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <input type="checkbox" id="printArray" name="printArray"/>
                    <label for="printArray">Print POST Array</label>
                    &nbsp;&nbsp;

                    <button type="button" id="btnUpdateVisualQuery" class="btn btn-primary"><i class="fa fa-save"></i>
                        Update Visual Query
                    </button>
                    <button type="submit" id="btnVisualQuery" class="btn btn-success"><i class="fa fa-play"></i>
                        Run Query (from editor)
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i>
                        Close
                    </button>
                </div>

            </div>

            <input type="hidden" name="vquery"/>
            <input type="hidden" name="visual_query_id_edit_submit" id="visual_query_id_edit_submit_field">
            <!-- The above field is not strictly needed if JS handles ID for update, but good for form context if ever used -->

        </form>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>

<script type="text/javascript">
    // Store the globally generated table options HTML (from Flight::get('masterTableOptionsHTML')) in a JS variable
    var allTablesOptionsHTML = <?php echo json_encode(Flight::get('masterTableOptionsHTML') ?? '<option value=\"\">No tables available (Global Fallback)</option>'); ?>;
    if (typeof allTablesOptionsHTML !== 'string' || allTablesOptionsHTML.trim() === '' || allTablesOptionsHTML.indexOf('<option') === -1) {
        // This JS fallback should ideally not be hit if masterTableOptionsHTML is always populated in index.php
        allTablesOptionsHTML = '<option value=\"\">No tables available (JS Fallback)</option>';
        console.warn("masterTableOptionsHTML was empty or invalid from Flight::get. Using JS fallback.");
    }
</script>

<div id="fieldClone" class="parent" style="display: none; margin: 3px;">
    <div class="pull-left">
        <a href="#" class="remove"><i class="glyphicon glyphicon-trash glyphicon-2x" style="margin-top: 5px;"></i></a>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="ftype[]" class="form-control" style="width: 70px;">
            <option value="AND">AND</option>
            <option value="OR">OR</option>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="fname[]" placeholder="Field Name" class="fname form-control" style="width: 250px;">
            <?php echo $fields; ?>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <input type="text" name="fvalue[]" placeholder="Operator + Value eg = 5 or != 10" class="form-control" style="height: 28px; width: 250px;">
    </div>
    <div class="clearfix"></div>
</div>

<!-- Rename Query Modal -->
<div class="modal fade" id="modal-rename-query">
    <div class="modal-dialog modal-sm"> <!-- Using modal-sm for a smaller modal -->
        <div class="modal-content">
            <div class="modal-header label-info"> <!-- Changed color for distinction -->
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-pencil"></i> Rename Query</h4>
            </div>
            <div class="modal-body">
                <div id="renameQueryMsg" class="alert" style="display:none;"></div>
                <input type="hidden" id="rename_query_id" name="rename_query_id">
                <div class="form-group">
                    <label for="rename_query_name">New Query Name:</label>
                    <input type="text" class="form-control" id="rename_query_name" name="rename_query_name" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveRenameQuery"><i class="fa fa-save"></i> Save Rename</button>
            </div>
        </div>
    </div>
</div>
<!-- Save Query Modal -->
<div class="modal fade" id="modal-save-query">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header label-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-save"></i> Save Query</h4>
            </div>
            <div class="modal-body">
                <div id="saveQueryMsg" class="alert" style="display:none;"></div>
                <div class="form-group">
                    <label for="query_name_save">Query Name:</label>
                    <input type="text" class="form-control" id="query_name_save" name="query_name_save" required>
                </div>
                <input type="hidden" id="sql_query_save" name="sql_query_save">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Close</button>
                <button type="button" class="btn btn-primary" id="btnSaveQueryConfirm"><i class="fa fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<div id="fieldCloneHaving" class="parent" style="display: none; margin: 3px;">
    <div class="pull-left">
        <a href="#" class="remove"><i class="glyphicon glyphicon-trash glyphicon-2x" style="margin-top: 5px;"></i></a>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="htype[]" class="form-control" style="width: 70px;">
            <option value="AND">AND</option>
            <option value="OR">OR</option>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="hfname[]" placeholder="Field Name / Alias" class="hfname form-control fields" style="width: 250px;">
            <?php echo $fields; ?>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <input type="text" name="hfvalue[]" placeholder="Operator + Value eg > 100" class="form-control" style="height: 28px; width: 250px;">
    </div>
    <div class="clearfix"></div>
</div>

<div id="fieldCloneTable" class="parent" style="display: none; margin: 3px;">
    <div class="pull-left">
        <a href="#" class="remove removeme"><i class="glyphicon glyphicon-trash glyphicon-2x" style="margin-top: 5px;"></i></a>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="jointype[]" class="form-control" style="width: 150px;">
            <option value="INNER JOIN">INNER JOIN</option>
            <option value="LEFT JOIN">LEFT JOIN</option>
            <option value="RIGHT JOIN">RIGHT JOIN</option>
            <option value="FULL JOIN">FULL JOIN</option>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="jointable[]" class="jointable form-control" style="width: 160px;">
            <?php echo Flight::get('masterTableOptionsHTML') ?? '<option value="">No tables available</option>'; ?>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="joinfield[]" class="joinfieldselected form-control" style="width: 160px;">
            <option value="">Joining Field</option>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="joinfieldp[]" class="joinfieldmain form-control fields" style="width: 160px;">
            <option value="">Joining with Field</option>
            <?php echo $fields ?? '' ?>
        </select>
    </div>
    <div class="clearfix"></div>
</div>

<div id="fieldCloneAggregate" class="parent" style="display: none; margin: 10px 0;">
    <div class="pull-left">
        <a href="#" class="remove"><i class="glyphicon glyphicon-trash glyphicon-2x" style="margin-top: 5px;"></i></a>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="agg_field[]" class="form-control fields agg_field" style="width: 200px;" data-placeholder="Select Field">
            <?php echo $fields; ?>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <select name="agg_func[]" class="form-control agg_func" style="width: 120px;">
            <option value="">None</option>
            <option value="COUNT">COUNT</option>
            <option value="SUM">SUM</option>
            <option value="AVG">AVG</option>
            <option value="MIN">MIN</option>
            <option value="MAX">MAX</option>
        </select>
    </div>
    <div class="pull-left" style="margin: 3px;">
        &nbsp;
    </div>
    <div class="pull-left">
        <input type="text" name="agg_alias[]" placeholder="Alias (optional)" class="form-control agg_alias" style="height: 28px; width: 180px;">
    </div>
    <div class="clearfix"></div>
</div>
