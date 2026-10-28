<?php
/**
 * StudentHub - Faculty dashboard (faculty only)
 * Admins are sent to the admin dashboard by php/guard.php.
 */

declare(strict_types=1);

require __DIR__ . '/../php/guard.php';
require __DIR__ . '/../php/views/admin-layout.php';

$user = require_role(['faculty']);

admin_page_start('Dashboard', 'faculty', $user);
?>
            <h2 class="mb-1"><i class="fas fa-chalkboard-teacher text-primary me-2"></i> Faculty Dashboard</h2>
            <p class="text-muted mb-4">Welcome, <?= e($user['name']) ?>.</p>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <a href="admin-students.php" class="card p-4 text-decoration-none h-100">
                        <i class="fas fa-users fs-2 text-primary mb-2"></i>
                        <h5 class="fw-bold">Students</h5>
                        <p class="text-muted mb-0">Search and view student records.</p>
                    </a>
                </div>
            </div>
<?php admin_page_end();
