<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="<?php echo Flight::get('base'); ?>/favicon.ico" type="image/x-icon">
    <link rel="icon" href="<?php echo Flight::get('base'); ?>/favicon.ico" type="image/x-icon">

    <title>
        <?php
        $page_title = Flight::get('appname'); // Default
        if (!empty($title)) { // $title is usually the table name from Flight::get('lastSegment')
            $page_title = "Table: " . htmlspecialchars($title) . " | " . Flight::get('appname');
        }
        // $executed_query_name is passed from Table::runQueryWithView
        if (!empty($executed_query_name)) {
            $page_title = "Query: " . htmlspecialchars($executed_query_name) . " - " . $page_title;
        }
        echo $page_title;
        ?>
    </title>
    <link href="<?php echo Flight::get('base'); ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo Flight::get('base'); ?>/assets/css/font-awesome.css" rel="stylesheet">
    <link href="<?php echo Flight::get(
       'base'
    ); ?>/assets/plugins/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet">
    <link href="<?php echo Flight::get('base'); ?>/assets/plugins/jGrowl/jquery.jgrowl.css" rel="stylesheet">
    <link href="<?php echo Flight::get('base'); ?>/assets/plugins/select2/select2.css" rel="stylesheet">
    <link href="<?php echo Flight::get('base'); ?>/assets/plugins/select2/select2-bootstrap.css"
          rel="stylesheet">
    <link href="<?php echo Flight::get('base'); ?>/assets/plugins/dataTables/dataTables.bootstrap.css"
          rel="stylesheet">

    <!--<link href="<?php /*echo Flight::get('base'); */ ?>/assets/plugins/summernote/summernote.css" rel="stylesheet">-->

    <link href="<?php echo Flight::get('base'); ?>/assets/css/custom.css?v=<?php echo time(); ?>"
          rel="stylesheet">
</head>

<body>
<div id="wrapper">

    <!-- Sidebar -->
    <div id="sidebar-wrapper">
        <a class="sidebar-brand" href="<?php echo Flight::get(
           'base'
        ); ?>/home"><i class="fa fa-database"></i> <?php echo Flight::get('appname'); ?></a>

        <ul class="sidebar-nav">
            <li><a href="<?php echo Flight::get('base'); ?>/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="<?php echo Flight::get('base'); ?>/datasources"><i class="fa fa-database"></i> Manage Data Sources</a></li>
            <li><a href="<?php echo Flight::get('base'); ?>/destinations"><i class="fa fa-rocket"></i> Manage Destinations</a></li>
            <li><a href="<?php echo Flight::get('base'); ?>/etllog"><i class="fa fa-history"></i> ETL Log</a></li>

            <li class="nav-heading" style="padding: 10px 15px; font-weight: bold; color: #000;">Create New Query</li>
            <li style="padding: 0 1px 10px 15px;">
                <label for="datasource" style="color: #fff; font-weight: bold;">Select Data Source</label>
                <select name="datasource" id="datasource" class="form-control" style="width: 100%;">
                    <option value="">-- Choose a Data Source --</option>
                    <?php echo Flight::get('dataSourceOptions'); ?>
                </select>
            </li>

            <li style="padding: 0 1px 10px 15px;">
                <label for="table_select" style="color: #fff; font-weight: bold;">Select Table</label>
                <select id="table_select" name="table_select" class="form-control" style="width: 100%;">
                    <option value="">-- Choose a Table --</option>
                    <?php echo Flight::get('table_options'); ?>
                </select>
            </li>
        </ul>
    </div>
    <!-- End Sidebar -->

    <!-- Page content -->
    <div id="page-content-wrapper">

        <div class="content-header">
            <div class="pull-left">
                <?php if (!empty($executed_query_name)): ?>
                    <h1><i class="fa fa-play-circle-o"></i> Query: <?php echo htmlspecialchars($executed_query_name); ?> <small>(on table: <?php echo htmlspecialchars($title); ?>)</small></h1>
                <?php else: ?>
                    <h1><i class="glyphicon <?php echo $icon; ?>"></i> <?php echo $title; ?></h1>
                <?php endif; ?>
            </div>

            <div class="pull-right" id="addbuttoncontainer">
                <?php if (false !== strpos($_SERVER['REQUEST_URI'], '/table')) { ?>
                    <button rel="hover_popover" data-content="Build Visual Query" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#modal-visual-query">
                        <i
                           class="fa fa-database"></i> Visual Query
                    </button>

                <?php } ?>

                <a rel="hover_popover" data-content="Log Out" href="<?php echo Flight::get(
                   'base'
                ); ?>/login/logout" class="btn btn-danger btn-lg"><i class="fa fa-sign-out"></i> Logout</a>
            </div>

            <div class="clearfix"></div>
        </div>

        <div id="header_stips" class="progress">
            <div class="progress-bar progress-bar-primary" style="width: 25%;"></div>
            <div class="progress-bar progress-bar-success" style="width: 25%;"></div>
            <div class="progress-bar progress-bar-warning" style="width: 25%;"></div>
            <div class="progress-bar progress-bar-danger" style="width: 25%;"></div>
        </div>

        <?php
        if (getFlashMessage()) {
            $class = (false !== stripos(getFlashMessage(), 'error')) ? 'danger' : 'success';
            $icon = ($class === 'danger') ? 'warning' : 'check-circle';
            ?>
            <div class="bold alert alert-<?php echo $class; ?>">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-<?php echo $icon; ?>"></i> <?php echo getFlashMessage();
                clearFlashMessage(); ?>
            </div>
        <?php } ?>
