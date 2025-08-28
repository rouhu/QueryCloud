<?php require_once 'includes/header.php'; ?>

<div class="page-content inset">
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">Add New Destination</div>
                <div class="panel-body">
                    <form action="<?php echo Flight::get('base'); ?>/destinations/add" method="post" role="form">
                        <div class="form-group">
                            <label for="connection_name">Connection Name</label>
                            <input type="text" class="form-control" id="connection_name" name="connection_name" placeholder="e.g., Production DWH" required>
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
                        <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add Destination</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">Existing Destinations</div>
                <div class="panel-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Connection Name</th>
                                <th>Host</th>
                                <th>Database</th>
                                <th>User</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($destinations) && count($destinations) > 0): ?>
                                <?php foreach ($destinations as $dest): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dest->connection_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($dest->db_host, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($dest->db_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($dest->db_user, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <form action="<?php echo Flight::get('base'); ?>/destinations/delete" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this destination?');">
                                                <input type="hidden" name="id" value="<?php echo $dest->id; ?>">
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
