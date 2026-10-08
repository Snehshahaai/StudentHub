<?php
// Faculty and admins only: students are sent to their own dashboard (php/guard.php)
require __DIR__ . '/../php/guard.php';
$user = require_role(['faculty', 'admin']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | StudentHub</title>

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
            <a class="navbar-brand" href="../index.html">
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
                    <li class="nav-item"><a class="nav-link active" href="admin-dashboard.php"><i class="fas fa-tachometer-alt me-1"></i> Admin Console</a></li>
                    <li class="nav-item"><a class="nav-link" href="../index.html"><i class="fas fa-globe me-1"></i> Portal Main Site</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Admin Dashboard Section -->
    <section class="py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2><i class="fas fa-tools text-primary me-2"></i> Faculty & Administrative Controls</h2>
                    <p class="text-muted mb-0">Manage student registrations, post campus circulars, and monitor attendance metrics.</p>
                    <p class="small mb-0 mt-2">
                        <i class="fas fa-user-shield text-primary me-1"></i>
                        Signed in as <strong><?= e($user['name']) ?></strong>
                        <span class="badge bg-primary ms-1 text-uppercase"><?= e($user['role']) ?></span>
                    </p>
                </div>
                <div>
                    <button class="btn btn-primary me-2" data-modal-target="createNoticeModal">
                        <i class="fas fa-bullhorn me-1"></i> Post New Notice
                    </button>
                    <button class="btn btn-outline-primary" data-trigger-toast data-toast-msg="Generating system health report..." data-toast-type="info">
                        <i class="fas fa-chart-bar me-1"></i> Audit Logs
                    </button>
                </div>
            </div>

            <!-- Admin Quick Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card p-3 text-center border-start border-4 border-primary">
                        <small class="text-muted fw-bold">TOTAL STUDENTS</small>
                        <h2 class="fw-bold mb-0 text-primary" data-me="total_students">1,248</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 text-center border-start border-4 border-success">
                        <small class="text-muted fw-bold">ACTIVE FACULTY</small>
                        <h2 class="fw-bold mb-0 text-success" data-me="active_faculty">84</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 text-center border-start border-4 border-warning">
                        <small class="text-muted fw-bold">SUBMISSIONS TODAY</small>
                        <h2 class="fw-bold mb-0 text-warning" data-me="submissions_today">312</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 text-center border-start border-4 border-info">
                        <small class="text-muted fw-bold">OPEN TICKETS</small>
                        <h2 class="fw-bold mb-0 text-info" data-me="open_tickets">0</h2>
                    </div>
                </div>
            </div>

            <!-- Student Management Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-surface py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-users text-primary me-2"></i> Recent Student Enrollments</h5>
                    <button class="btn btn-sm btn-outline-success" data-trigger-toast data-toast-msg="Approved 4 pending student accounts!" data-toast-type="success">
                        <i class="fas fa-user-check me-1"></i> Batch Approve
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>2026CS108</td>
                                <td class="fw-bold">Sneh Shah</td>
                                <td>Computer Engg</td>
                                <td>snehshah@university.edu</td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-trigger-toast data-toast-msg="Editing profile for 2026CS108" data-toast-type="info"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>2026CS109</td>
                                <td class="fw-bold">Rohan Verma</td>
                                <td>Computer Engg</td>
                                <td>rohan.v@university.edu</td>
                                <td><span class="badge bg-warning text-dark">Pending</span></td>
                                <td>
                                    <button class="btn btn-sm btn-success" data-trigger-toast data-toast-msg="Approved Rohan Verma!" data-toast-type="success"><i class="fas fa-check"></i> Approve</button>
                                </td>
                            </tr>
                            <tr>
                                <td>2026IT204</td>
                                <td class="fw-bold">Priya Patel</td>
                                <td>Information Tech</td>
                                <td>priya.p@university.edu</td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-trigger-toast data-toast-msg="Editing profile for 2026IT204" data-toast-type="info"><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Create Notice Modal -->
    <div class="sh-modal-overlay" id="createNoticeModal">
        <div class="sh-modal-container">
            <div class="sh-modal-header">
                <h4><i class="fas fa-bullhorn text-primary me-2"></i> Post Campus Notice</h4>
                <button class="sh-modal-close" aria-label="Close modal">&times;</button>
            </div>
            <div class="sh-modal-body">
                <form onsubmit="event.preventDefault(); StudentHub.closeModal('createNoticeModal'); StudentHub.showNotification('Notice broadcasted to all students!', 'success');">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notice Headline</label>
                        <input type="text" class="form-control" placeholder="e.g. Schedule for Sports Week" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notice Category</label>
                        <select class="form-select">
                            <option>Academic Announcement</option>
                            <option>Exam Circular</option>
                            <option>Event / Placement</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notice Content</label>
                        <textarea class="form-control" rows="4" placeholder="Enter details..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Broadcast Notice</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="pt-4 pb-3">
        <div class="container text-center">
            <p class="mb-0">© 2026 StudentHub | Admin Console</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Main JS Engine -->
    <script src="../js/main.js"></script>

    <!-- Loads live stats from php/me.php and handles the session timeout -->
    <script src="../js/account.js"></script>
</body>

</html>