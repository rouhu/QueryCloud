<?php require_once 'includes/header.php'; ?>

<div class="page-content inset">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">ETL Configuration for Query: "<?php echo htmlspecialchars($saved_query->query_name, ENT_QUOTES, 'UTF-8'); ?>"</h3>
                </div>
                <div class="panel-body">

                    <h4>Saved SQL Query:</h4>
                    <div class="well well-sm">
                        <pre><code><?php echo htmlspecialchars($saved_query->sql_query, ENT_QUOTES, 'UTF-8'); ?></code></pre>
                    </div>

                    <hr>

                    <h4>Destination Setup:</h4>
                    <form class="form-horizontal" action="<?php echo Flight::get('base'); ?>/etl/save" method="post" role="form">
                        <input type="hidden" name="query_id" value="<?php echo $saved_query->id; ?>">

                        <div class="form-group">
                            <label for="destination_id" class="col-sm-3 control-label">Destination Connection</label>
                            <div class="col-sm-6">
                                <select class="form-control" id="destination_id" name="destination_id" required>
                                    <option value="">-- Select a Destination --</option>
                                    <?php if (isset($destinations)): ?>
                                        <?php foreach ($destinations as $dest): ?>
                                            <option value="<?php echo $dest->id; ?>" <?php echo (isset($etl_config['destination_db_id']) && $etl_config['destination_db_id'] == $dest->id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dest->connection_name, ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($dest->db_host, ENT_QUOTES, 'UTF-8'); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="destination_table" class="col-sm-3 control-label">Destination Table Name</label>
                            <div class="col-sm-6">
                                <select class="form-control" id="destination_table" name="destination_table"
                                        data-saved-table="<?php echo isset($etl_config['destination_table_name']) ? htmlspecialchars($etl_config['destination_table_name'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                                        required disabled>
                                    <option value="">-- Select a Destination First --</option>
                                </select>
                                <p class="help-block">The table where the query results will be inserted. The table must exist.</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="etl_type" class="col-sm-3 control-label">ETL Type</label>
                            <div class="col-sm-6">
                                <select class="form-control" id="etl_type" name="etl_type">
                                    <option value="insert_only" <?php echo (isset($etl_config['etl_type']) && $etl_config['etl_type'] == 'insert_only') ? 'selected' : ''; ?>>
                                        Insert Only
                                    </option>
                                    <option value="update_or_insert" <?php echo (isset($etl_config['etl_type']) && $etl_config['etl_type'] == 'update_or_insert') ? 'selected' : ''; ?>>
                                        Update or Insert
                                    </option>
                                </select>
                                <p class="help-block">"Insert Only" adds new records. "Update or Insert" will update existing records based on a key or insert new ones.</p>
                            </div>
                        </div>

                        <hr>
                        <h4>Column Mapping:</h4>
                        <div id="column-mapping-container" class="col-sm-offset-1 col-sm-10">
                            <p class="text-muted">Select a destination table to map columns.</p>
                        </div>
                        <div class="clearfix"></div>
                        <hr>

                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-6">
                                <button type="submit" class="btn btn-primary" formaction="<?php echo Flight::get('base'); ?>/etl/save"><i class="fa fa-save"></i> Save Configuration</button>
                                <?php if (!empty($etl_config['destination_db_id']) && !empty($etl_config['destination_table_name'])): ?>
                                    <button type="submit" class="btn btn-success" formaction="<?php echo Flight::get('base'); ?>/etl/run"><i class="fa fa-play"></i> Run ETL Now</button>
                                <?php endif; ?>
                                <a href="<?php echo Flight::get('base'); ?>/dashboard" class="btn btn-default">Cancel</a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Pass the PHP etl_config to JavaScript
    var etlConfig = <?php echo json_encode($etl_config); ?>;
</script>

<?php require_once 'includes/footer.php'; ?>
