<?php
session_start();
require_once '../db.php';

// Admin Authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Dashboard Statistics
$appointmentQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments");
$totalAppointments = mysqli_fetch_assoc($appointmentQuery)['total'];

$customerQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='customer'");
$totalCustomers = mysqli_fetch_assoc($customerQuery)['total'];

$staffQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM staff");
$totalStaff = mysqli_fetch_assoc($staffQuery)['total'];

$serviceQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM services");
$totalServices = mysqli_fetch_assoc($serviceQuery)['total'];

// Recent Appointments
$recentQuery = mysqli_query($conn, "
    SELECT a.appointment_date, a.status,
           u.name AS customer_name,
           s.service_name
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN services s ON a.service_id = s.id
    ORDER BY a.id DESC
    LIMIT 4
");

// Popular Services
$popularQuery = mysqli_query($conn, "
    SELECT s.service_name, COUNT(a.id) AS total_bookings
    FROM services s
    LEFT JOIN appointments a ON s.id = a.service_id
    GROUP BY s.id, s.service_name
    ORDER BY total_bookings DESC
    LIMIT 4
");

if (!$popularQuery) {
    die("Database Error: " . mysqli_error($conn));
}

if (!$recentQuery) {
    die("Database Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beauty Salon | Admin Dashboard</title>

<style>
:root{
    --rose:#a64d70;
    --dark:#633448;
    --light:#fff7fa;
    --pink:#f8e4ed;
    --text:#49313d;
    --muted:#907986;
    --white:#fff;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif}
body{background:var(--light);color:var(--text)}
a{text-decoration:none;color:inherit}
button{font:inherit;cursor:pointer}
.dashboard{display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{
    width:245px;flex-shrink:0;
    background:linear-gradient(180deg,#633448,#492538);
    color:white;padding:24px 14px
}
.brand{
    display:flex;align-items:center;gap:10px;
    padding:0 8px 22px;border-bottom:1px solid #ffffff30;
    margin-bottom:22px
}
.brand img{
    width:48px;height:48px;border-radius:50%;
    object-fit:contain;background:white
}
.brand h2{color:#ffe6ef;font-size:18px}
.brand small{display:block;color:#eacbd8;margin-top:5px;font-size:11px}
.menu{list-style:none}
.menu li{margin:6px 0}
.menu a{
    display:flex;align-items:center;gap:12px;padding:12px;
    border-radius:9px;font-size:14px;color:#f9eaf0;
    transition:.2s
}
.menu a:hover,.menu a.active{background:var(--rose);color:white}
.menu-icon{width:23px;text-align:center;font-size:19px}
.logout{margin-top:22px!important;padding-top:12px;border-top:1px solid #ffffff30}

/* MAIN */
.main-content{flex:1;min-width:0}
.topbar{
    min-height:85px;background:white;padding:16px 30px;
    display:flex;justify-content:space-between;align-items:center;
    gap:15px;box-shadow:0 3px 15px #6334480b
}
.topbar h1{font-size:24px;color:var(--dark)}
.topbar p{color:var(--muted);font-size:13px;margin-top:6px}
.profile{display:flex;align-items:center;gap:10px}
.avatar{
    width:44px;height:44px;border-radius:50%;background:var(--pink);
    display:grid;place-items:center;font-size:23px
}
.profile strong{display:block;font-size:14px;color:var(--dark)}
.profile small{display:block;margin-top:4px;color:var(--muted)}

/* CONTENT */
.content{padding:27px}
.eyebrow{
    color:var(--rose);font-size:12px;font-weight:bold;
    text-transform:uppercase;letter-spacing:1.5px;margin-bottom:9px
}
.page-title{color:var(--dark);font-size:25px;margin-bottom:8px}
.subtext{color:var(--muted);font-size:14px;line-height:1.7}

/* WELCOME */
.welcome-banner{
    background:linear-gradient(120deg,#f4d8e4,#fff0f5 65%,#f9e3d8);
    border:1px solid #f0d6e1;border-radius:17px;padding:29px;
    margin-bottom:27px;display:flex;justify-content:space-between;
    align-items:center;gap:20px
}
.welcome-banner h2{color:#783852;font-size:25px;margin-bottom:10px}
.welcome-banner p{color:#765565;font-size:14px;line-height:1.8;max-width:520px}
.banner-decoration{font-size:65px;opacity:.8;padding:10px 20px}
.primary-btn{
    display:inline-block;border:none;background:var(--rose);color:white;
    padding:12px 19px;border-radius:9px;margin-top:17px;
    font-size:13px;font-weight:bold;transition:.2s
}
.primary-btn:hover{background:var(--dark)}

/* SUMMARY CARDS */
.section-heading{color:var(--dark);font-size:19px;margin-bottom:17px}
.stats-grid{
    display:grid;grid-template-columns:repeat(4,minmax(0,1fr));
    gap:17px;margin-bottom:30px
}
.stat-card{
    background:white;border:1px solid #f0e0e7;border-radius:14px;
    padding:19px;box-shadow:0 4px 15px #63344808;transition:.2s
}
.stat-card:hover{transform:translateY(-3px)}
.stat-top{
    display:flex;justify-content:space-between;align-items:center;
    gap:8px;margin-bottom:19px
}
.stat-icon{
    width:46px;height:46px;border-radius:12px;
    display:grid;place-items:center;font-size:23px
}
.pink-bg{background:#fbe5ee}
.purple-bg{background:#eee7ff}
.peach-bg{background:#fff0df}
.green-bg{background:#e1f5e9}
.stat-label{color:var(--muted);font-size:12px;line-height:1.5}
.stat-card h3{color:var(--dark);font-size:27px;margin-bottom:8px}
.stat-note{font-size:11px;color:#658c73}

/* PANELS */
.panels{
    display:grid;grid-template-columns:minmax(0,1.65fr) minmax(250px,1fr);
    gap:22px;margin-bottom:25px
}
.panel{
    min-width:0;background:white;border:1px solid #f0e0e7;
    border-radius:14px;padding:22px;box-shadow:0 4px 15px #63344807
}
.panel-heading{
    display:flex;justify-content:space-between;align-items:center;
    gap:10px;margin-bottom:20px
}
.panel-heading h2{color:var(--dark);font-size:18px}
.view-link{color:var(--rose);font-size:12px;font-weight:bold}
.view-link:hover{text-decoration:underline}

/* TABLE */
.table-wrap{width:100%;overflow-x:auto}
table{width:100%;min-width:540px;border-collapse:collapse;text-align:left}
thead{background:#fcf0f5}
th,td{padding:14px 10px;border-bottom:1px solid #f2e6eb;font-size:12px}
th{color:var(--dark);font-weight:bold}
td{color:#765e6a}
tbody tr:hover{background:#fff9fb}
.customer-name{color:var(--dark);font-weight:bold}
.customer-email{display:block;font-size:10px;color:#a08b96;margin-top:5px}
.status{
    display:inline-block;padding:6px 9px;border-radius:20px;
    font-size:10px;font-weight:bold;white-space:nowrap
}
.confirmed{color:#24764b;background:#e2f5e9}
.pending{color:#996614;background:#fff0d5}
.cancelled{color:#a23e54;background:#fce6eb}

/* POPULAR SERVICES */
.service-item{margin-bottom:23px}
.service-details{
    display:flex;justify-content:space-between;align-items:center;
    gap:10px;margin-bottom:10px
}
.service-name{font-size:13px;color:var(--dark);font-weight:bold}
.service-count{color:var(--muted);font-size:11px}
.progress-track{
    width:100%;height:8px;background:#f6eaf0;border-radius:20px;overflow:hidden
}
.progress-fill{
    height:100%;border-radius:20px;
    background:linear-gradient(90deg,#a64d70,#df92b1)
}
.service-footer{
    border-top:1px solid #f2e6eb;padding-top:15px;
    color:var(--muted);font-size:12px;line-height:1.7
}

/* QUICK ACTIONS */
.quick-actions{margin-bottom:25px}
.action-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}
.action-card{
    display:flex;align-items:center;gap:13px;padding:17px;
    background:white;border:1px solid #f0e0e7;border-radius:12px;
    transition:.2s;min-width:0
}
.action-card:hover{border-color:#dca0b8;background:#fff0f5;transform:translateY(-2px)}
.action-icon{
    flex-shrink:0;width:43px;height:43px;border-radius:11px;
    background:#fbe5ee;display:grid;place-items:center;font-size:22px
}
.action-card strong{display:block;color:var(--dark);font-size:13px}
.action-card small{display:block;color:var(--muted);font-size:11px;line-height:1.5;margin-top:5px}

/* FOOTER */
footer{text-align:center;padding:20px;color:var(--muted);font-size:12px}

/* RESPONSIVE */
@media(max-width:1100px){
    .sidebar{width:215px}
    .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .action-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:800px){
    .dashboard{display:block}
    .sidebar{width:100%;padding:15px}
    .brand{padding-bottom:14px;margin-bottom:12px}
    .menu{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}
    .menu li{margin:0}
    .menu a{padding:10px;font-size:12px}
    .logout{margin-top:5px!important;padding-top:5px}
    .topbar{padding:17px 20px}
    .content{padding:20px}
    .panels{grid-template-columns:1fr}
}
@media(max-width:520px){
    .stats-grid,.action-grid{grid-template-columns:1fr}
    .welcome-banner{padding:20px}
    .welcome-banner h2{font-size:21px}
    .banner-decoration{font-size:40px;padding:0}
    .topbar h1{font-size:20px}
    .profile small{display:none}
    .content{padding:14px}
    .panel{padding:15px}
}
</style>
</head>

<body>
<div class="dashboard">

    <!-- SIDEBAR -->
   
<aside class="sidebar">

    <div class="brand">
        <img src="../salon-logo.png/LOGO.png" alt="Beauty Salon Logo"
             onerror="this.style.display='none'">

        <div>
            <h2>Beauty Salon</h2>
            <small>Admin Panel</small>
        </div>
    </div>

    <ul class="menu">

        <li>
            <a href="dashboard.php" class="active">
                <span class="menu-icon">▦</span> Dashboard
            </a>
        </li>

        <li>
            <a href="manage-appointments.php">
                <span class="menu-icon">📅</span> Appointments
            </a>
        </li>

        <li>
            <a href="manage-services.php">
                <span class="menu-icon">✂</span> Services
            </a>
        </li>

        <li>
            <a href="manage-customers.php">
                <span class="menu-icon">♧</span> Customers
            </a>
        </li>

        <li>
            <a href="manage-staff.php">
                <span class="menu-icon">♙</span> Staff
            </a>
        </li>

        <li>
            <a href="payments.php">
                <span class="menu-icon">৳</span> Payments
            </a>
        </li>

        <li class="logout">
            <a href="../logout.php">
                <span class="menu-icon">↪</span> Logout
            </a>
        </li>

    </ul>
</aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Admin Dashboard</h1>
                <p>Welcome back! Here's what's happening at your salon.</p>
            </div>
            <div class="profile">
                <div class="avatar">👩🏻</div>
                <div>
                    <strong>Administrator</strong>
                    <small>Salon Manager</small>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- WELCOME BANNER -->
            <section class="welcome-banner">
                <div>
                    <div class="eyebrow">WELCOME BACK</div>
                    <h2>Manage Your Beauty Salon</h2>
                    <p>
                        Manage appointments, services, customers and staff
                        from one place. Keep your salon organized and running smoothly.
                    </p>
                    <a href="manage-appointments.php" class="primary-btn">Manage Appointments →</a>
                </div>
                <div class="banner-decoration">🌸</div>
            </section>

            <!-- SUMMARY CARDS -->
            <section>
                <h2 class="section-heading">Salon Overview</h2>
                <div class="stats-grid">

                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-label">Total Appointments</span>
                            <div class="stat-icon pink-bg">📅</div>
                        </div>
                        <h3><?= $totalAppointments ?></h3>
                        <span class="stat-note">Salon booking overview</span>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-label">Total Customers</span>
                            <div class="stat-icon purple-bg">👥</div>
                        </div>
                        <h3><?= $totalCustomers ?></h3>
                        <span class="stat-note">Customer records</span>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-label">Salon Staff</span>
                            <div class="stat-icon peach-bg">👩‍💼</div>
                        </div>
                        <h3><?= $totalStaff ?></h3>
                        <span class="stat-note">Staff members</span>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-label">Total Services</span>
                            <div class="stat-icon green-bg">✂️</div>
                        </div>
                        <h3><?= $totalServices ?></h3>
                        <span class="stat-note">Available services</span>
                    </div>

                </div>
            </section>

            <!-- APPOINTMENTS AND POPULAR SERVICES -->
            <section class="panels">

                <div class="panel">
                    <div class="panel-heading">
                        <h2>Recent Appointments</h2>
                        <a href="manage-appointments.php" class="view-link">View All →</a>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
    <?php if (mysqli_num_rows($recentQuery) > 0): ?>

        <?php while ($row = mysqli_fetch_assoc($recentQuery)): ?>

            <tr>
                <td>
                    <span class="customer-name">
                        <?= htmlspecialchars($row['customer_name'] ?? 'Unknown') ?>
                    </span>
                    <span class="customer-email">Customer</span>
                </td>

                <td>
                    <?= htmlspecialchars($row['service_name'] ?? 'Unavailable') ?>
                </td>

                <td>
                    <?= date('d M Y', strtotime($row['appointment_date'])) ?>
                </td>

                <td>
                    <span class="status <?= strtolower($row['status']) ?>">
                        <?= htmlspecialchars($row['status']) ?>
                    </span>
                </td>
            </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="4">No appointments found.</td>
        </tr>

    <?php endif; ?>
</tbody>

                        </table>
                    </div>
                </div>

         <div class="panel">
    <div class="panel-heading">
        <h2>Popular Services</h2>
        <a href="manage-services.php" class="view-link">View All →</a>
    </div>

    <?php
    $popularServices = [];
    $maxBookings = 0;

    while ($service = mysqli_fetch_assoc($popularQuery)) {
        $popularServices[] = $service;

        if ($service['total_bookings'] > $maxBookings) {
            $maxBookings = $service['total_bookings'];
        }
    }
    ?>

    <?php if (count($popularServices) > 0): ?>

        <?php foreach ($popularServices as $service): ?>

            <?php
            $percentage = ($maxBookings > 0)
                ? round(($service['total_bookings'] / $maxBookings) * 100)
                : 0;
            ?>

            <div class="service-item">
                <div class="service-details">
                    <span class="service-name">
                        <?= htmlspecialchars($service['service_name']) ?>
                    </span>

                    <span class="service-count">
                        <?= $service['total_bookings'] ?> Bookings
                    </span>
                </div>

                <div class="progress-track">
                    <div class="progress-fill"
                         style="width:<?= $percentage ?>%">
                    </div>
                </div>
            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p class="service-footer">No services found.</p>

    <?php endif; ?>

    <div class="service-footer">
        Popularity is calculated from actual appointment records.
    </div>
</div>
                

            </section>

            <!-- QUICK ACTIONS -->
            <section class="quick-actions">
                <h2 class="section-heading">Quick Actions</h2>

                <div class="action-grid">

                    <a href="manage-appointments.php" class="action-card">
                        <div class="action-icon">📅</div>
                        <div>
                            <strong>Manage Appointments</strong>
                            <small>View and manage bookings</small>
                        </div>
                    </a>

                    <a href="manage-services.php" class="action-card">
                        <div class="action-icon">✂️</div>
                        <div>
                            <strong>Manage Services</strong>
                            <small>Update salon services</small>
                        </div>
                    </a>

                    <a href="manage-customers.php" class="action-card">
                        <div class="action-icon">👥</div>
                        <div>
                            <strong>Manage Customers</strong>
                            <small>View customer information</small>
                        </div>
                    </a>

                    <a href="manage-staff.php" class="action-card">
                        <div class="action-icon">👩‍💼</div>
                        <div>
                            <strong>Manage Staff</strong>
                            <small>View and manage staff</small>
                        </div>
                    </a>

                    <a href="payments.php" class="action-card">
                        <div class="action-icon">💳</div>
                        <div>
                            <strong>Payment History</strong>
                            <small>View payment records</small>
                        </div>
                    </a>

                </div>
            </section>

        </div>

        <footer>
            &copy; 2026 Beauty Salon Appointment and Management System
        </footer>

    </main>
</div>
</body>
</html>
