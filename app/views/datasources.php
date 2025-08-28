<?php require_once 'includes/header.php'; ?>

<div class="page-content inset">
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">Add New Data Source</div>
                <div class="panel-body">
                    <form action="<?php echo Flight::get('base'); ?>/datasources/add" method="post" role="form">
                        <div class="form-group">
                            <label for="source_name">Source Name</label>
                            <input type="text" class="form-control" id="source_name" name="source_name" placeholder="e.g., Production OLTP" required>
                        </div>
                        <div class="form-group">
                            <label for="db_host">Host</label>
                            <input type="text" class="form-control" id="db_host" name="db_host" placeholder="e.g., 127.0.0.1" required>
                        </div>
                        <div class="form-group">
                            <label for="db_port">Port</label>
                            <input type="number" class="form-control" id="db_port" name="db_port" placeholder="e.g., 3306" required>
                        </div>
                        <div class="form-group">
                            <label for="db_name">Database Name</label>
                            <input type="text" class="form-control" id="db_name" name="db_name" required>
                        </div>
                        <div class="form-group">
                            <label for="db_user">Username</label>
                            <input type="text" class="form-control" id="db_user" name="db_user" required>
                        </div>
                        <div class="form-group">
                            <label for="db_password">Password</label>
                            <input type="password" class="form-control" id="db_password" name="db_password" required>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add Data Source</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">Existing Data Sources</div>
                <div class="panel-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Source Name</th>
                                <th>Host</th>
                                <th>Database</th>
                                <th>User</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($sources) && count($sources) > 0): ?>
                                <?php foreach ($sources as $source): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($source->source_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($source->db_host, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($source->db_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($source->db_user, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <form action="<?php echo Flight::get('base'); ?>/datasources/delete" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this data source?');">
                                                <input type="hidden" name="id" value="<?php echo $source->id; ?>">
                                                <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash-o"></i> Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
