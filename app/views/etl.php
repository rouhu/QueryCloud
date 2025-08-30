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

                        <h4>Scheduling:</h4>
                        <div class="form-group">
                            <label for="schedule_type" class="col-sm-3 control-label">Schedule</label>
                            <div class="col-sm-6">
                                <select class="form-control" id="schedule_type" name="schedule_type">
                                    <option value="inactive" <?php echo (!isset($etl_config['schedule_type']) || $etl_config['schedule_type'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="minutely" <?php echo (isset($etl_config['schedule_type']) && $etl_config['schedule_type'] == 'minutely') ? 'selected' : ''; ?>>Minutely</option>
                                    <option value="hourly" <?php echo (isset($etl_config['schedule_type']) && $etl_config['schedule_type'] == 'hourly') ? 'selected' : ''; ?>>Hourly</option>
                                    <option value="daily" <?php echo (isset($etl_config['schedule_type']) && $etl_config['schedule_type'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo (isset($etl_config['schedule_type']) && $etl_config['schedule_type'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                </select>
                            </div>
                        </div>

                        <!-- Minutely Options -->
                        <div class="form-group schedule-options" id="schedule_minutely_options" style="display:none;">
                            <label for="schedule_interval" class="col-sm-3 control-label">Run Every</label>
                            <div class="col-sm-6">
                                <select class="form-control" id="schedule_interval" name="schedule_interval">
                                    <?php $intervals = [1, 5, 10, 15, 20, 30, 45]; ?>
                                    <?php foreach ($intervals as $interval): ?>
                                        <option value="<?php echo $interval; ?>" <?php echo (isset($etl_config['schedule_interval']) && $etl_config['schedule_interval'] == $interval) ? 'selected' : ''; ?>>
                                            <?php echo $interval; ?> minute(s)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Hourly Options -->
                        <div class="form-group schedule-options" id="schedule_hourly_options" style="display:none;">
                            <label for="schedule_hours" class="col-sm-3 control-label">Run On The Hour(s)</label>
                            <div class="col-sm-6">
                                <select class="form-control select2-multiple" id="schedule_hours" name="schedule_hours[]" multiple>
                                    <?php for ($i = 0; $i < 24; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo (isset($etl_config['schedule_hours']) && in_array($i, (array)$etl_config['schedule_hours'])) ? 'selected' : ''; ?>>
                                            <?php echo str_pad($i, 2, '0', STR_PAD_LEFT) . ':00'; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <p class="help-block">Select one or more hours. The job will run once within each selected hour.</p>
                            </div>
                        </div>

                        <!-- Daily Options -->
                        <div class="form-group schedule-options" id="schedule_daily_options" style="display:none;">
                            <label for="schedule_days" class="col-sm-3 control-label">Run On Day(s) of Month</label>
                            <div class="col-sm-6">
                                <select class="form-control select2-multiple" id="schedule_days" name="schedule_days[]" multiple>
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo (isset($etl_config['schedule_days']) && in_array($i, (array)$etl_config['schedule_days'])) ? 'selected' : ''; ?>>
                                            Day <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <p class="help-block">Select one or more days of the month. The job will run at midnight on these days.</p>
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

    $(document).ready(function() {
        // Initialize select2 for the new multi-select boxes
        $('.select2-multiple').select2({
            placeholder: 'Click to select options'
        });

        function toggleScheduleOptions() {
            // Hide all schedule option groups
            $('.schedule-options').hide();

            var selectedType = $('#schedule_type').val();
            if (selectedType === 'minutely') {
                $('#schedule_minutely_options').show();
            } else if (selectedType === 'hourly') {
                $('#schedule_hourly_options').show();
            } else if (selectedType === 'daily') {
                $('#schedule_daily_options').show();
            }
        }

        $('#schedule_type').on('change', toggleScheduleOptions);

        // Initial check on page load
        toggleScheduleOptions();
    });
</script>

<?php require_once 'includes/footer.php'; ?>
