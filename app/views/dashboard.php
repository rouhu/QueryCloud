<?php require_once 'includes/header.php'; ?>

    <div class="page-content inset">
        <div class="row">
            <h4><i class="fa fa-list-alt"></i> Saved Queries</h4>
            <hr>
            <?php if (!empty($saved_queries)): ?>
                <ul class="list-group" id="dashboardSavedQueriesList">
                    <?php foreach ($saved_queries as $query): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center" data-query-list-id="<?php echo htmlspecialchars($query['id']); ?>">
                            <?php echo htmlspecialchars($query['query_name']); ?>
                            <div style="float: right;"> <!-- Group buttons on the right -->
                                <button type="button" class="btn btn-warning btn-sm btn-rename-query" data-query-id="<?php echo htmlspecialchars($query['id']); ?>" data-query-name="<?php echo htmlspecialchars($query['query_name']); ?>" style="margin-left: 10px;">
                                    <i class="fa fa-i-cursor"></i> Rename
                                </button>
                                <button type="button" class="btn btn-info btn-sm btn-edit-saved-query" data-query-id="<?php echo htmlspecialchars($query['id']); ?>" data-query-name="<?php echo htmlspecialchars($query['query_name']); ?>" style="margin-left: 5px;">
                                    <i class="fa fa-pencil"></i> Edit SQL/Visual
                                </button>
                                <button type="button" class="btn btn-primary btn-sm btn-run-saved-query" data-query-id="<?php echo htmlspecialchars($query['id']); ?>" style="margin-left: 5px;">
                                    <i class="fa fa-play"></i> Run
                                </button>
                                <button type="button" class="btn btn-danger btn-sm btn-delete-saved-query" data-query-id="<?php echo htmlspecialchars($query['id']); ?>" data-query-name="<?php echo htmlspecialchars($query['query_name']); ?>" style="margin-left: 5px;">
                                    <i class="fa fa-trash-o"></i> Delete
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="alert alert-info">
                    <p><span class="fa fa-info-circle"></span> You have no saved queries yet. You can save a query after running it from the table view.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="row" style="margin-top: 40px;">
             <div class="alert alert-info">
                <h3 style="margin: 0;"><span class="fa fa-table"></span> To build or run new queries, please select a table from the sidebar.</h3>
            </div>
        </div>
    </div>

<script>
    // Make saved queries available to JavaScript (for run/delete handlers that might use savedQueriesCache)
    var initialSavedQueries = <?php echo json_encode($saved_queries ?? []); ?>;
</script>

<?php require_once 'includes/footer.php'; ?>