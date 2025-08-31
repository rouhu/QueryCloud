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
                            <label for="destination_type">Destination Type</label>
                            <select class="form-control" id="destination_type" name="destination_type" required onchange="toggleDestinationFields()">
                                <option value="">-- Select Type --</option>
                                <option value="database">Database</option>
                                <option value="sftp">SFTP</option>
                                <option value="s3">S3 Compatible Storage</option>
                            </select>
                        </div>
                        <div class="form-group database-fields" id="database_type_group" style="display: none;">
                            <label for="db_type">Database Type</label>
                            <select class="form-control" id="db_type" name="db_type">
                                <option value="mysql">MySQL</option>
                                <option value="postgresql">PostgreSQL</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="db_host">Host</label>
                            <input type="text" class="form-control" id="db_host" name="db_host" placeholder="e.g., 127.0.0.1" required>
                        </div>
                        <div class="form-group database-fields" id="db_port_group" style="display: none;">
                            <label for="db_port">Port</label>
                            <input type="number" class="form-control" id="db_port" name="db_port" placeholder="e.g., 3306">
                        </div>
                        <div class="form-group sftp-fields" id="sftp_port_group" style="display: none;">
                            <label for="sftp_port">SFTP Port</label>
                            <input type="number" class="form-control" id="sftp_port" name="sftp_port" placeholder="e.g., 22" value="22">
                        </div>
                        <div class="form-group s3-fields" id="s3_bucket_group" style="display: none;">
                            <label for="s3_bucket">S3 Bucket Name</label>
                            <input type="text" class="form-control" id="s3_bucket" name="s3_bucket" placeholder="e.g., my-etl-bucket">
                        </div>
                        <div class="form-group s3-fields" id="s3_region_group" style="display: none;">
                            <label for="s3_region">S3 Region/Endpoint</label>
                            <input type="text" class="form-control" id="s3_region" name="s3_region" placeholder="e.g., us-east-1, eu-central-1, or custom endpoint region">
                            <p class="help-block">For AWS S3: use region code (e.g., us-east-1). For other providers: use their specified region/endpoint identifier.</p>
                        </div>
                        <div class="form-group database-fields" id="db_name_group" style="display: none;">
                            <label for="db_name">Database Name</label>
                            <input type="text" class="form-control" id="db_name" name="db_name">
                        </div>
                        <div class="form-group">
                            <label for="db_user"><span class="database-fields sftp-fields">Username</span><span class="s3-fields" style="display: none;">AWS Access Key ID</span></label>
                            <input type="text" class="form-control" id="db_user" name="db_user" required>
                        </div>
                        <div class="form-group">
                            <label for="db_password"><span class="database-fields sftp-fields">Password</span><span class="s3-fields" style="display: none;">AWS Secret Access Key</span></label>
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
                                <th>Destination Type</th>
                                <th>Host</th>
                                <th>Database/Port</th>
                                <th>User</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($destinations) && count($destinations) > 0): ?>
                                <?php foreach ($destinations as $dest): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dest->connection_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(isset($dest->destination_type) ? ucfirst($dest->destination_type) : ucfirst($dest->db_type), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($dest->db_host, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if (isset($dest->destination_type) && $dest->destination_type == 'sftp'): ?>
                                                Port: <?php echo htmlspecialchars($dest->db_port ?: '22', ENT_QUOTES, 'UTF-8'); ?>
                                            <?php elseif (isset($dest->destination_type) && $dest->destination_type == 's3'): ?>
                                                Region: <?php echo htmlspecialchars($dest->db_name, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($dest->db_name, ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </td>
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

<script>
function toggleDestinationFields() {
    var destinationType = document.getElementById('destination_type').value;
    var databaseFields = document.querySelectorAll('.database-fields');
    var sftpFields = document.querySelectorAll('.sftp-fields');
    var s3Fields = document.querySelectorAll('.s3-fields');
    
    // Hide all fields first
    databaseFields.forEach(function(field) {
        field.style.display = 'none';
        var inputs = field.querySelectorAll('input, select');
        inputs.forEach(function(input) {
            input.removeAttribute('required');
        });
    });
    
    sftpFields.forEach(function(field) {
        field.style.display = 'none';
        var inputs = field.querySelectorAll('input, select');
        inputs.forEach(function(input) {
            input.removeAttribute('required');
        });
    });
    
    s3Fields.forEach(function(field) {
        field.style.display = 'none';
        var inputs = field.querySelectorAll('input, select');
        inputs.forEach(function(input) {
            input.removeAttribute('required');
        });
    });
    
    // Show relevant fields based on selection
    if (destinationType === 'database') {
        databaseFields.forEach(function(field) {
            field.style.display = 'block';
        });
        // Make database-specific fields required
        document.getElementById('db_type').setAttribute('required', 'required');
        document.getElementById('db_port').setAttribute('required', 'required');
        document.getElementById('db_name').setAttribute('required', 'required');
    } else if (destinationType === 'sftp') {
        sftpFields.forEach(function(field) {
            field.style.display = 'block';
        });
    } else if (destinationType === 's3') {
        s3Fields.forEach(function(field) {
            field.style.display = 'block';
        });
        // Make S3-specific fields required
        document.getElementById('s3_bucket').setAttribute('required', 'required');
        document.getElementById('s3_region').setAttribute('required', 'required');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
