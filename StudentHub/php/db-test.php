<?php
/**
 * StudentHub - Database connection check
 * Open /php/db-test.php to confirm PHP can reach MySQL and see every table
 * with its row count. Development helper: remove it on a live server.
 */

declare(strict_types=1);

require __DIR__ . '/db.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = null;
$tables = [];
$views = [];
$info = [];

try {
    $conn = db();
    $info = [
        'Server'   => $conn->server_info,
        'Host'     => $conn->host_info,
        'Database' => db_value('SELECT DATABASE()'),
        'Charset'  => $conn->character_set_name(),
        'Time'     => db_value('SELECT NOW()'),
    ];

    $list = db_all(
        'SELECT table_name AS name, table_type AS type
           FROM information_schema.tables
          WHERE table_schema = DATABASE()
          ORDER BY table_type, table_name'
    );
    foreach ($list as $row) {
        if ($row['type'] === 'VIEW') {
            $views[] = $row['name'];
            continue;
        }
        // Table names come from information_schema, not from the user, so quoting them is safe
        $count = db_value('SELECT COUNT(*) FROM `' . str_replace('`', '``', $row['name']) . '`');
        $tables[] = ['name' => $row['name'], 'rows' => (int) $count];
    }

    $stats = db_one('SELECT * FROM v_admin_stats');
} catch (mysqli_sql_exception $ex) {
    $error = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Check | StudentHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<section class="py-5">
    <div class="container" style="max-width: 860px;">
        <h2 class="mb-4"><i class="fas fa-database text-primary me-2"></i> Database Connection</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <h5 class="fw-bold"><i class="fas fa-times-circle me-2"></i>Could not connect to MySQL</h5>
                <p class="mb-2"><code><?= e($error) ?></code></p>
                <ul class="mb-0 small">
                    <li>Start MySQL in the XAMPP control panel.</li>
                    <li>Import <code>database/schema.sql</code> then <code>database/seed.sql</code> (phpMyAdmin &gt; Import).</li>
                    <li>Check the username/password in <code>php/config.php</code>.</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>Connected to <strong><?= e($info['Database']) ?></strong> successfully.
            </div>

            <div class="card p-3 mb-4">
                <table class="table mb-0">
                    <?php foreach ($info as $label => $value): ?>
                        <tr><th style="width: 30%"><?= e($label) ?></th><td><?= e($value) ?></td></tr>
                    <?php endforeach; ?>
                    <tr>
                        <th>Dashboard stats</th>
                        <td>
                            <?= e($stats['total_students']) ?> active students ·
                            <?= e($stats['pending_approvals']) ?> pending ·
                            <?= e($stats['active_faculty']) ?> faculty ·
                            <?= e($stats['open_tickets']) ?> open tickets
                        </td>
                    </tr>
                </table>
            </div>

            <h5 class="mb-3"><?= count($tables) ?> tables</h5>
            <div class="card p-3 mb-4">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Table</th><th class="text-end">Rows</th></tr></thead>
                    <tbody>
                    <?php foreach ($tables as $table): ?>
                        <tr><td><code><?= e($table['name']) ?></code></td><td class="text-end"><?= e($table['rows']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h5 class="mb-3"><?= count($views) ?> views</h5>
            <p><?php foreach ($views as $view): ?><code class="me-3"><?= e($view) ?></code><?php endforeach; ?></p>
        <?php endif; ?>
    </div>
</section>
</body>
</html>
