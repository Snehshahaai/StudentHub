<?php
/**
 * StudentHub - Shared layout for the admin / faculty pages
 *   admin_page_start('Students', 'students', $user);
 *   ... page content ...
 *   admin_page_end();
 */

declare(strict_types=1);

function admin_page_start(string $title, string $active, array $user): void
{
    $flash = take_flash();
    $nav = [
        'dashboard' => ['admin-dashboard.php', 'fa-tachometer-alt', 'Admin Console'],
        'students'  => ['admin-students.php', 'fa-users', 'Students'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | StudentHub Admin</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/responsive.css">
</head>

<body>

    <!-- Dynamic Notification Toast Container -->
    <div id="shToastContainer" class="sh-toast-container"></div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="admin-dashboard.php">
                <i class="fas fa-user-shield"></i> StudentHub Admin
            </a>

            <div class="d-flex align-items-center gap-2 ms-auto me-2 me-lg-0 order-lg-last">
                <!-- Ends the PHP session (php/logout.php) -->
                <form action="../php/logout.php" method="post" class="m-0">
                    <button type="submit" class="btn btn-outline-primary btn-sm px-3" aria-label="Log out">
                        <i class="fas fa-sign-out-alt"></i><span class="d-none d-sm-inline ms-1">Logout</span>
                    </button>
                </form>
                <button type="button" class="theme-toggle-btn" aria-label="Toggle Light Dark Theme">
                    <i class="fas fa-moon text-primary"></i>
                    <span class="theme-text d-none d-sm-inline">Dark</span>
                </button>

                <button class="sh-hamburger-btn" data-nav-target="shNavMenu" aria-label="Toggle Mobile Menu" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>

            <div class="sh-nav-menu collapse navbar-collapse" id="shNavMenu">
                <ul class="navbar-nav ms-auto">
                    <?php foreach ($nav as $key => [$href, $icon, $label]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $key === $active ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                                <i class="fas <?= e($icon) ?> me-1"></i> <?= e($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item"><a class="nav-link" href="../index.html"><i class="fas fa-globe me-1"></i> Portal Main Site</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="py-5">
        <div class="container">
            <p class="small text-muted mb-3">
                <i class="fas fa-user-shield text-primary me-1"></i>
                Signed in as <strong><?= e($user['name']) ?></strong>
                <span class="badge bg-primary ms-1 text-uppercase"><?= e($user['role']) ?></span>
            </p>

            <?php if ($flash): ?>
                <!-- Result of the last add / edit / delete -->
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="status" aria-live="polite">
                    <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
    <?php
}

function admin_page_end(): void
{
    ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pt-4 pb-3">
        <div class="container text-center">
            <p class="mb-0">© 2026 StudentHub | Student Management</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Main JS Engine -->
    <script src="../js/main.js"></script>

    <!-- Session timeout warning (php/me.php) -->
    <script src="../js/account.js"></script>
</body>

</html>
    <?php
}

/** Hidden CSRF field for every form that changes data. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Bootstrap badge for a student status. */
function status_badge(string $status): string
{
    $colors = ['active' => 'success', 'pending' => 'warning text-dark', 'inactive' => 'secondary', 'suspended' => 'danger'];
    return '<span class="badge bg-' . ($colors[$status] ?? 'secondary') . '">' . e(ucfirst($status)) . '</span>';
}

/** Current list URL with some query values changed (keeps search, filters, sort). */
function list_url(array $filters, array $changes = []): string
{
    $query = array_filter(array_merge($filters, $changes), fn($v) => $v !== '' && $v !== 0 && $v !== null);
    unset($query['page'], $query['per_page'], $query['sort']);
    foreach (['page' => 1, 'per_page' => STUDENT_PER_PAGE[0], 'sort' => 'newest'] as $key => $default) {
        $value = $changes[$key] ?? $filters[$key];
        if ($value !== $default) {
            $query[$key] = $value;
        }
    }
    return 'admin-students.php' . ($query ? '?' . http_build_query($query) : '');
}
