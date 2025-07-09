<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <link href="<?php echo Flight::get('base') ? rtrim(Flight::get('base'), '/') . '/' : ''; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 40px; padding-bottom: 40px; background-color: #f9f9f9; }
        .container { max-width: 600px; margin: auto; background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); text-align: center; }
        .alert-danger { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>An Error Occurred</h2>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($message ?? 'An unspecified error occurred.', ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <p><a href="<?php echo Flight::get('base') ? rtrim(Flight::get('base'), '/') . '/' : './'; ?>">Return to Homepage</a></p>
    </div>
</body>
</html>
