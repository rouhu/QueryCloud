<?php require_once 'includes/header.php'; ?>

    <div class="page-content inset">
        <div class="row" style="margin-top: -20px;" id="tabledata">
            <a target="_blank" href="../export/csv" class="btn btn-primary" id="csv"><i class="fa fa-file-text-o"></i> Export CSV</a>
            <a target="_blank" href="../export/excel" class="btn btn-primary" id="excel"><i class="fa fa-file-excel-o"></i> Export Excel</a>
            <div class="clearfix">&nbsp;</div>

            <?php echo $table_data; ?>
        </div>
    </div>

    <hr/>
    <div style="margin-bottom: 10px;">
        <h3 style="display: inline-block; margin-right: 10px;">Generated Query</h3>
        <button type="button" class="btn btn-success" id="btnShowSaveQueryModal"><i class="fa fa-save"></i> Save Current Query</button>
        <?php if (isset($executed_query_id) && !empty($executed_query_id)): ?>
            <div style="display: inline-block; margin-left: 15px;">
                <strong style="font-weight: bold;">Data Source:</strong>
                <span style="margin-left: 5px;"><?php echo htmlspecialchars($executed_query_source_name ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <?php
            // Determine if the "Edit Query" button should be shown.
            // Option 1: Query was just built with VQB (visual_params_json is set from current VQB run)
            $can_edit_visually = !empty($visual_params_json);
            // Option 2: Executed query was a saved visual query
            // (controller needs to pass $executed_query_was_saved_visual and set $visual_params_json to its stored params)
            if (isset($executed_query_was_saved_visual) && $executed_query_was_saved_visual) {
                $can_edit_visually = true;
                // Ensure $visual_params_json for editing refers to the *saved* state if different from an ad-hoc run
                // This logic is mainly handled by how controller populates $visual_params_json and other related hidden fields
            }
        ?>
        <?php if ($can_edit_visually): ?>
            <button type="button" class="btn btn-info" id="btnEditExecutedQuery" style="margin-left: 10px;"><i class="fa fa-pencil"></i> Edit Query in VQB</button>
        <?php elseif (isset($executed_query_id) && !empty($executed_query_id)): ?>
            <button type="button" class="btn btn-info" id="btnEditCustomSQL" style="margin-left: 10px;"><i class="fa fa-pencil"></i> Edit Custom SQL</button>
        <?php endif; ?>
        <?php if (isset($executed_query_id) && !empty($executed_query_id)): ?>
            <button type="button" class="btn btn-warning" id="btnShowTableFormatModal" style="margin-left: 10px;" data-query-id="<?php echo htmlspecialchars($executed_query_id, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-paint-brush"></i> Format Table
            </button>
            <a href="<?php echo Flight::get('base'); ?>/etl/<?php echo htmlspecialchars($executed_query_id, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-info" style="margin-left: 10px;">
                <i class="fa fa-cogs"></i> ETL
            </a>
        <?php endif; ?>
    </div>
    <div class="footer" id="generatedQueryDisplay">
        <?php echo $query; ?>
    </div>
    <span class="alert-warning timetaken">Time Taken: <strong><?php echo $timetaken ? $timetaken : '0.00'; ?></strong> second(s)!</span>
    <br/>
    <br/>
    <div id="printArray">
        <?php echo $printArray; ?>
    </div>

    <!-- Holds visual params from an ad-hoc VQB run, or potentially the params of a saved visual query if it was just run -->
    <input type="hidden" id="current_visual_params" value="<?php echo isset($visual_params_json) ? htmlspecialchars($visual_params_json, ENT_QUOTES, 'UTF-8') : ''; ?>">

    <!-- Holds info about the executed query IF it was a saved query that was run -->
    <input type="hidden" id="executed_query_id" value="<?php echo isset($executed_query_id) ? htmlspecialchars($executed_query_id, ENT_QUOTES, 'UTF-8') : ''; ?>">
    <input type="hidden" id="executed_query_name" value="<?php echo isset($executed_query_name) ? htmlspecialchars($executed_query_name, ENT_QUOTES, 'UTF-8') : ''; ?>">
    <input type="hidden" id="executed_query_source_connection_id" value="<?php echo isset($executed_query_source_connection_id) ? htmlspecialchars($executed_query_source_connection_id, ENT_QUOTES, 'UTF-8') : ''; ?>">
    <!-- This flag helps JS determine if the #current_visual_params are from a saved visual query context vs an ad-hoc VQB run -->
    <input type="hidden" id="executed_query_was_saved_visual" value="<?php echo (isset($executed_query_was_saved_visual) && $executed_query_was_saved_visual) ? 'true' : 'false'; ?>">


    <script>
        var __table = '<?php echo Flight::get('lastSegment');?>';
    </script>

<?php require_once 'includes/footer.php'; ?>