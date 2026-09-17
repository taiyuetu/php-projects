<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex" id="wrapper">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <div id="page-content" class="flex-grow-1">
        <?php require __DIR__ . '/../partials/navbar.php'; ?>

        <main class="container-fluid py-4">
            <?php require __DIR__ . '/../partials/alerts.php'; ?>
            <?php echo $content; ?>
        </main>

        <footer class="text-center text-muted small py-3">
            <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
