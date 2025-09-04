<?php require_once 'includes/header.php'; ?>

<?php
// Initialize fields if not set
$fields = $fields ?? [];
$currentTable = $currentTable ?? '';
$dataSourceId = $dataSourceId ?? '';
$queryId = $queryId ?? '';
$queryName = $queryName ?? '';
$isEditMode = !empty($queryId);
?>

<div class="page-content">
    <div class="page-header">
        <div class="page-title">
            <h3>
                <i class="fa fa-database"></i> Visual Query Builder
                <?php if ($currentTable): ?>
                - <?php echo strtoupper($currentTable); ?>
                <?php endif; ?>
            </h3>
        </div>
        <ul class="breadcrumb">
            <li><a href="<?php echo Flight::get('base'); ?>/">Home</a></li>
            <?php if ($currentTable): ?>
                <li><a href="<?php echo Flight::get('base'); ?>/table/<?php echo $currentTable; ?>"><?php echo $currentTable; ?></a></li>
            <?php endif; ?>
            <li class="active">Visual Query Builder</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-magic"></i> Build Your Query Visually
                    </h4>
                </div>
                
                <div class="panel-body">
                    <form action="<?php echo Flight::get('base'); ?>/vqb<?php echo $currentTable ? '/' . $currentTable : ''; ?>" method="post" class="form-horizontal" role="form" id="vqb-form" data-source-id="<?php echo htmlspecialchars($dataSourceId); ?>" data-query-name="<?php echo htmlspecialchars($queryName); ?>">
                        <input type="hidden" id="visual_query_id_edit" name="visual_query_id_edit" value="<?php echo htmlspecialchars($queryId); ?>">
                        <input type="hidden" name="vquery"/>
                        <input type="hidden" name="visual_query_id_edit_submit" id="visual_query_id_edit_submit_field">

                        <!-- Navigation Actions -->
                        <div class="form-group">
                            <div class="col-md-12">
                                <div class="btn-group">
                                    <?php if ($currentTable): ?>
                                        <a href="<?php echo Flight::get('base'); ?>/table/<?php echo $currentTable; ?>" class="btn btn-default">
                                            <i class="fa fa-arrow-left"></i> Back to <?php echo $currentTable; ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo Flight::get('base'); ?>/" class="btn btn-default">
                                            <i class="fa fa-arrow-left"></i> Back to Dashboard
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Join Tables Section -->
                        <div class="form-group">
                            <div class="col-md-12">
                                <h4 class="vqb-table-name">
                                    Table: <?php echo $currentTable ? strtoupper($currentTable) : 'UNKNOWN'; ?>
                                </h4>
                                <button style="margin-bottom: 10px !important;" type="button" id="btnJoinTable" class="btn btn-primary" rel="hover_popover" data-content="Join a table">
                                    <i class="glyphicon glyphicon-plus-sign"></i> Join Table
                                </button>
                                <br/>
                                <a href="#" id="addjoinedtablefields" onclick="addTablesToDropdown(); return false;">
                                    <i class="fa fa-refresh"></i> Add Joined Table Fields to Below Pulldowns
                                </a>
                            </div>
                        </div>

                        <!-- Select Fields Section -->
                        <div class="form-group">
                            <label class="col-md-2 control-label" for="fields">Select Non-Aggregated Fields</label>
                            <div class="col-md-10">
                                <select name="fields[]" multiple="multiple" class="form-control fields" style="width: 100%;">
                                    <?php echo $fields; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Aggregated Fields Section -->
                        <div class="form-group">
                            <div class="col-md-12">
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
                        </div>

                        <!-- WHERE Conditions Section -->
                        <div class="form-group">
                            <div class="col-md-12">
                                <button style="margin-bottom: 10px !important;" type="button" id="btnAddWhere" class="btn btn-primary" rel="hover_popover" data-content="Add WHERE clause conditions">
                                    <i class="glyphicon glyphicon-plus-sign"></i> Add Condition
                                </button>
                            </div>
                        </div>

                        <!-- ORDER BY Section -->
                        <div class="form-group">
                            <div class="col-md-12">
                                <button type="button" id="btnOrderby" class="btn btn-primary" rel="hover_popover" data-content="Add ORDER BY clause fields">
                                    <i class="glyphicon glyphicon-plus-sign"></i> Add Order
                                </button>
                            </div>
                        </div>

                        <div class="form-group parent" style="display: none;" id="orderby">
                            <div class="col-md-12">
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
                        </div>

                        <!-- GROUP BY Section -->
                        <div class="form-group">
                            <div class="col-md-12">
                                <button type="button" id="btnGroup" class="btn btn-primary" rel="hover_popover" data-content="Add GROUP BY clause fields">
                                    <i class="glyphicon glyphicon-plus-sign"></i> Add Group Field
                                </button>
                            </div>
                        </div>

                        <div class="form-group parent" style="display: none;" id="group">
                            <div class="col-md-12">
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
                        </div>

                        <!-- HAVING Conditions Section -->
                        <div class="form-group">
                            <div class="col-md-12">
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
                        </div>

                        <!-- LIMIT Section -->
                        <div class="form-group">
                            <div class="col-md-12">
                                <button type="button" id="btnLimit" class="btn btn-primary" rel="hover_popover" data-content="Add LIMIT clause details">
                                    <i class="glyphicon glyphicon-plus-sign"></i> Add Limit
                                </button>
                            </div>
                        </div>

                        <div class="form-group parent" style="display: none;" id="limit">
                            <div class="col-md-12">
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

                        <!-- Form Actions -->
                        <div class="form-group">
                            <div class="col-md-12">
                                <hr/>
                                <div class="btn-group">
                                    <input type="checkbox" id="printArray" name="printArray"/>
                                    <label for="printArray">Print POST Array</label>
                                    &nbsp;&nbsp;
                                    
                                    <?php if ($isEditMode): ?>
                                        <button type="button" id="btnUpdateVisualQuery" class="btn btn-primary">
                                            <i class="fa fa-save"></i> Update Visual Query
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="submit" id="btnVisualQuery" class="btn btn-success">
                                        <i class="fa fa-play"></i> Run Query
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Elements (Hidden) -->
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

<!-- Store data for JavaScript initialization -->
<script type="text/javascript">
    // Store the globally generated table options HTML
    var allTablesOptionsHTML = <?php echo json_encode(Flight::get('masterTableOptionsHTML') ?? '<option value="">No tables available (Global Fallback)</option>'); ?>;
    if (typeof allTablesOptionsHTML !== 'string' || allTablesOptionsHTML.trim() === '' || allTablesOptionsHTML.indexOf('<option') === -1) {
        allTablesOptionsHTML = '<option value="">No tables available (JS Fallback)</option>';
        console.warn("masterTableOptionsHTML was empty or invalid from Flight::get. Using JS fallback.");
    }
    
    // Set up VQB page context
    var __table = '<?php echo addslashes($currentTable); ?>';
    var vqbDataSourceId = '<?php echo addslashes($dataSourceId); ?>';
    var vqbQueryName = '<?php echo addslashes($queryName); ?>';
    var vqbIsEditMode = <?php echo $isEditMode ? 'true' : 'false'; ?>;
    var vqbSavedParams = <?php echo json_encode($visualParams ?? null); ?>;
</script>

<?php require_once 'includes/footer.php'; ?>
