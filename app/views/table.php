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

    <input type="hidden" id="current_visual_params" value="<?php echo isset($visual_params_json) ? htmlspecialchars($visual_params_json, ENT_QUOTES, 'UTF-8') : ''; ?>">

    <script>
        var __table = '<?php echo Flight::get('lastSegment');?>';
    </script>

<?php require_once 'includes/footer.php'; ?>