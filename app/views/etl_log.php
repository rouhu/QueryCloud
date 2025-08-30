<?php require_once 'includes/header.php'; ?>

<div class="page-content inset">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="<?php echo $icon; ?>"></i> <?php echo $title; ?></h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Query Name</th>
                                <th>Execution Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($logs) && !empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log->query_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log->execution_time, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log->ended_at, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <span class="label <?php echo $log->status === 'success' ? 'label-success' : ($log->status === 'failed' ? 'label-danger' : 'label-warning'); ?>">
                                                <?php echo htmlspecialchars($log->status, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td><pre><?php echo htmlspecialchars($log->message, ENT_QUOTES, 'UTF-8'); ?></pre></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No ETL logs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
