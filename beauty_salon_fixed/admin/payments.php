

<?php
session_start();
require_once '../db.php';

// Admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'], $_POST['payment_status'])) {
    $paymentId = (int) $_POST['payment_id'];
    $paymentStatus = $_POST['payment_status'];
    $allowedStatuses = ['Pending', 'Paid', 'Failed', 'Refunded'];

    if ($paymentId > 0 && in_array($paymentStatus, $allowedStatuses, true)) {
        $update = $conn->prepare('UPDATE payments SET payment_status = ? WHERE id = ?');
        $update->bind_param('si', $paymentStatus, $paymentId);
        $update->execute();
        $update->close();
    }

    header('Location: payments.php?updated=1');
    exit();
}

// Fetch payment records
$payments = [];
$error = '';

$sql = "SELECT
            p.id AS payment_id,
            p.appointment_id,
            p.amount,
            p.payment_method,
            p.transaction_id,
            p.payment_status,
            p.payment_date,
            a.appointment_date,
            a.appointment_time,
            u.name AS customer_name,
            u.email,
            s.service_name
        FROM payments p
        LEFT JOIN appointments a ON p.appointment_id = a.id
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN services s ON a.service_id = s.id
        ORDER BY p.id DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
} else {
    $error = "Unable to load payment records. Please check your database tables.";
}

// Summary
$totalPayments = count($payments);
$totalAmount = 0;
$paidCount = 0;
$pendingCount = 0;
$failedCount = 0;

foreach ($payments as $payment) {
    $totalAmount += (float)$payment['amount'];

    if ($payment['payment_status'] === 'Paid') {
        $paidCount++;
    } elseif ($payment['payment_status'] === 'Pending') {
        $pendingCount++;
    } elseif ($payment['payment_status'] === 'Failed') {
        $failedCount++;
    }
}

$adminName = $_SESSION['user_name'] ?? 'Admin';
$paymentJson = json_encode(
    $payments,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments | Beauty Salon Admin</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --pink: #c95d83;
    --pink-dark: #a94067;
    --pink-light: #fff0f5;
    --rose: #f8dce6;
    --bg: #fff8fa;
    --text: #392832;
    --muted: #907e88;
    --border: #f0e4e9;
    --white: #ffffff;
    --green: #23845b;
    --yellow: #ad7414;
    --red: #c64b60;
    --shadow: 0 8px 28px rgba(107, 48, 76, .06);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
}

.sidebar {
    width: 250px;
    height: 100vh;
    background: white;
    position: fixed;
    top: 0;
    left: 0;
    padding: 25px 18px;
    border-right: 1px solid var(--border);
    z-index: 10;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 8px 30px;
}

.brand img {
    width: 43px;
    height: 43px;
    object-fit: contain;
}

.brand-icon {
    width: 43px;
    height: 43px;
    border-radius: 13px;
    background: var(--pink-light);
    color: var(--pink);
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 23px;
    flex-shrink: 0;
}


.brand h2 {
    font-family: 'Playfair Display', serif;
    font-size: 21px;
}

.brand small {
    color: var(--muted);
    font-size: 11px;
}

.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 7px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    text-decoration: none;
    color: #76636d;
    font-size: 14px;
    font-weight: 600;
    border-radius: 10px;
    transition: .2s;
}

.menu a:hover,
.menu a.active {
    background: var(--pink-light);
    color: var(--pink-dark);
}

.menu-icon {
    width: 22px;
    text-align: center;
    font-size: 17px;
}

.menu-bottom {
    position: absolute;
    bottom: 25px;
    left: 18px;
    right: 18px;
}

.main {
    margin-left: 250px;
    padding: 30px 35px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 35px;
}

.breadcrumb {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 7px;
}

.topbar h1 {
    font-family: 'Playfair Display', serif;
    font-size: 29px;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 600;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--pink-light);
    color: var(--pink-dark);
    display: grid;
    place-items: center;
    font-weight: 700;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 23px;
}

.page-heading h2 {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
}

.page-heading p {
    color: var(--muted);
    font-size: 13px;
    margin-top: 5px;
}

.export-btn {
    border: none;
    background: var(--pink);
    color: white;
    padding: 11px 17px;
    border-radius: 9px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}

.export-btn:hover {
    background: var(--pink-dark);
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 17px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
    box-shadow: var(--shadow);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 13px;
}

.stat-label {
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
}

.stat-icon {
    width: 37px;
    height: 37px;
    border-radius: 11px;
    background: var(--pink-light);
    display: grid;
    place-items: center;
    font-size: 18px;
}

.stat-card h3 {
    font-size: 25px;
    margin-bottom: 4px;
}

.stat-card small {
    color: var(--muted);
    font-size: 12px;
}

.content-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.filter-bar {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 20px;
    border-bottom: 1px solid var(--border);
}

.search-box {
    flex: 1;
    max-width: 390px;
}

.search-box input,
.filter-bar select {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 9px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    background: white;
    color: var(--text);
}

.search-box input:focus,
.filter-bar select:focus {
    border-color: var(--pink);
}

.filter-bar select {
    max-width: 180px;
    cursor: pointer;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}

thead {
    background: #fff9fb;
}

th {
    padding: 15px 17px;
    text-align: left;
    font-size: 12px;
    color: var(--muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}

td {
    padding: 16px 17px;
    border-top: 1px solid var(--border);
    font-size: 13px;
}

tbody tr:hover {
    background: #fffbfc;
}

.customer-name {
    font-weight: 700;
    margin-bottom: 4px;
}

.customer-email {
    color: var(--muted);
    font-size: 11px;
}

.amount {
    font-weight: 700;
    color: var(--pink-dark);
}

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.status-paid {
    background: #e8f7ef;
    color: var(--green);
}

.status-pending {
    background: #fff5df;
    color: var(--yellow);
}

.status-failed {
    background: #ffedf0;
    color: var(--red);
}

.status-refunded {
    background: #f0eafa;
    color: #7552a4;
}

.view-btn {
    border: 1px solid var(--rose);
    background: var(--pink-light);
    color: var(--pink-dark);
    padding: 7px 12px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: 600;
    font-family: inherit;
    font-size: 12px;
}

.view-btn:hover {
    background: var(--rose);
}

.empty-state {
    text-align: center;
    padding: 65px 20px;
}

.empty-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 17px;
    border-radius: 50%;
    background: var(--pink-light);
    display: grid;
    place-items: center;
    font-size: 28px;
}

.empty-state h3 {
    font-family: 'Playfair Display', serif;
    font-size: 21px;
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--muted);
    font-size: 13px;
}

.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(42, 24, 34, .45);
    z-index: 100;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    background: white;
    width: 100%;
    max-width: 490px;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 15px 50px rgba(0,0,0,.15);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
}

.close-btn {
    border: none;
    background: var(--pink-light);
    color: var(--pink-dark);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.detail-row span:first-child {
    color: var(--muted);
}

.detail-row strong {
    text-align: right;
    overflow-wrap: anywhere;
}

.error-message {
    background: #fff0f0;
    color: var(--red);
    padding: 14px;
    border-radius: 9px;
    margin: 15px 20px;
    font-size: 13px;
}

@media(max-width: 1100px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 800px) {
    .sidebar {
        width: 70px;
        padding: 20px 10px;
    }

    .brand {
        justify-content: center;
        padding: 5px 0 30px;
    }

    .brand div:last-child,
    .menu a span:last-child {
        display: none;
    }

    .menu a {
        justify-content: center;
        padding: 13px 5px;
    }

    .menu-bottom {
        left: 10px;
        right: 10px;
    }

    .main {
        margin-left: 70px;
        padding: 22px 15px;
    }
}

@media(max-width: 550px) {
    .stats {
        grid-template-columns: 1fr;
    }

    .filter-bar,
    .page-heading {
        flex-direction: column;
        align-items: stretch;
    }

    .search-box {
        max-width: none;
    }

    .filter-bar select {
        max-width: none;
    }

    .topbar h1 {
        font-size: 23px;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="brand">
    <img src="../salon-logo.png/LOGO.png" alt="Salon Logo"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">

    <span class="brand-icon">✿</span>

    <div>
        <h2>Beauty Salon</h2>
        <small>Admin Panel</small>
    </div>
</div>

    <ul class="menu">
        <li>
            <a href="dashboard.php">
                <span class="menu-icon">⌂</span>
                <span>Dashboard</span>
            </a>
        </li>

        <li>
            <a href="manage-appointments.php">
                <span class="menu-icon">▦</span>
                <span>Appointments</span>
            </a>
        </li>

        <li>
            <a href="manage-services.php">
                <span class="menu-icon">✿</span>
                <span>Manage Services</span>
            </a>
        </li>

        <li>
            <a href="manage-customers.php">
                <span class="menu-icon">♙</span>
                <span>Customers</span>
            </a>
        </li>

        <li>
            <a href="manage-staff.php">
                <span class="menu-icon">♧</span>
                <span>Staff</span>
            </a>
        </li>

        <li>
            <a href="payments.php" class="active">
                <span class="menu-icon">৳</span>
                <span>Payments</span>
            </a>
        </li>
    </ul>

    <div class="menu-bottom">
        <ul class="menu">
            <li>
                <a href="../index.html">
                    <span class="menu-icon">↗</span>
                    <span>Visit Website</span>
                </a>
            </li>

            <li>
                <a href="../logout.php">
                    <span class="menu-icon">⇥</span>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

</aside>

<main class="main">

    <div class="topbar">
        <div>
            <div class="breadcrumb">Admin / Payments</div>
            <h1>Payment Management</h1>
        </div>

        <div class="admin-profile">
            <div class="avatar">A</div>
            <span><?php echo htmlspecialchars($adminName); ?></span>
        </div>
    </div>

    <div class="page-heading">
        <div>
            <h2>All Payments</h2>
            <p>View and monitor customer payment records.</p>
        </div>

        <button class="export-btn" onclick="exportCSV()">
            ↓ Export CSV
        </button>
    </div>

    <section class="stats">

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Total Payments</span>
                <div class="stat-icon">▤</div>
            </div>
            <h3><?php echo $totalPayments; ?></h3>
            <small>Recorded transactions</small>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Total Amount</span>
                <div class="stat-icon">৳</div>
            </div>
            <h3>৳<?php echo number_format($totalAmount, 2); ?></h3>
            <small>All recorded payments</small>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Paid Payments</span>
                <div class="stat-icon">✓</div>
            </div>
            <h3><?php echo $paidCount; ?></h3>
            <small>Successfully marked as paid</small>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Pending Payments</span>
                <div class="stat-icon">◷</div>
            </div>
            <h3><?php echo $pendingCount; ?></h3>
            <small><?php echo $failedCount; ?> failed payments</small>
        </div>

    </section>

    <section class="content-card">

        <div class="filter-bar">

            <div class="search-box">
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search customer, service, transaction ID..."
                    oninput="filterPayments()"
                >
            </div>

            <select id="statusFilter" onchange="filterPayments()">
                <option value="All">All Status</option>
                <option value="Paid">Paid</option>
                <option value="Pending">Pending</option>
                <option value="Failed">Failed</option>
                <option value="Refunded">Refunded</option>
            </select>

        </div>

        <?php if ($error): ?>

            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Appointment</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="paymentTableBody">
                    </tbody>
                </table>
            </div>

            <div class="empty-state" id="emptyState" style="display:none;">
                <div class="empty-icon">৳</div>
                <h3>No payment records found</h3>
                <p>Payment records will appear here when customers make payments.</p>
            </div>

        <?php endif; ?>

    </section>

</main>

<!-- Payment Details Modal -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal">

        <div class="modal-header">
            <h3>Payment Details</h3>
            <button class="close-btn" onclick="closeModal()">×</button>
        </div>

        <div id="paymentDetails"></div>

    </div>
</div>

<script>
const payments = <?php echo $paymentJson ?: '[]'; ?>;

const tableBody = document.getElementById('paymentTableBody');
const emptyState = document.getElementById('emptyState');

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function getStatusClass(status) {
    switch (status) {
        case 'Paid':
            return 'status-paid';
        case 'Pending':
            return 'status-pending';
        case 'Failed':
            return 'status-failed';
        case 'Refunded':
            return 'status-refunded';
        default:
            return 'status-pending';
    }
}

function filterPayments() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;

    const filtered = payments.filter(payment => {
        const searchable = [
            payment.payment_id,
            payment.appointment_id,
            payment.customer_name,
            payment.email,
            payment.service_name,
            payment.transaction_id,
            payment.payment_method
        ].join(' ').toLowerCase();

        const matchesSearch = searchable.includes(search);
        const matchesStatus = status === 'All' || payment.payment_status === status;

        return matchesSearch && matchesStatus;
    });

    renderPayments(filtered);
}

function renderPayments(data) {
    if (!tableBody || !emptyState) return;

    tableBody.innerHTML = '';

    if (data.length === 0) {
        emptyState.style.display = 'block';
        return;
    }

    emptyState.style.display = 'none';

    data.forEach(payment => {
        const status = payment.payment_status || 'Pending';
        const appointment = payment.appointment_date
            ? payment.appointment_date + ' ' + (payment.appointment_time || '')
            : 'N/A';

        const row = document.createElement('tr');

        row.innerHTML = `
            <td>#PAY-${escapeHTML(payment.payment_id)}</td>

            <td>
                <div class="customer-name">
                    ${escapeHTML(payment.customer_name || 'Unknown Customer')}
                </div>
                <div class="customer-email">
                    ${escapeHTML(payment.email || '')}
                </div>
            </td>

            <td>${escapeHTML(payment.service_name || 'N/A')}</td>

            <td>${escapeHTML(appointment)}</td>

            <td class="amount">
                ৳${Number(payment.amount || 0).toLocaleString('en-BD', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}
            </td>

            <td>${escapeHTML(payment.payment_method || 'N/A')}</td>

            <td>
                <span class="status ${getStatusClass(status)}">
                    ${escapeHTML(status)}
                </span>
            </td>

            <td>
                <button class="view-btn" onclick="viewPayment(${Number(payment.payment_id)})">
                    View
                </button>
            </td>
        `;

        tableBody.appendChild(row);
    });
}

function viewPayment(id) {
    const payment = payments.find(item => Number(item.payment_id) === Number(id));

    if (!payment) return;

    const details = document.getElementById('paymentDetails');

    const appointment = payment.appointment_date
        ? payment.appointment_date + ' ' + (payment.appointment_time || '')
        : 'N/A';

    details.innerHTML = `
        <div class="detail-row">
            <span>Payment ID</span>
            <strong>#PAY-${escapeHTML(payment.payment_id)}</strong>
        </div>

        <div class="detail-row">
            <span>Appointment ID</span>
            <strong>#${escapeHTML(payment.appointment_id)}</strong>
        </div>

        <div class="detail-row">
            <span>Customer</span>
            <strong>${escapeHTML(payment.customer_name || 'Unknown')}</strong>
        </div>

        <div class="detail-row">
            <span>Email</span>
            <strong>${escapeHTML(payment.email || 'N/A')}</strong>
        </div>

        <div class="detail-row">
            <span>Service</span>
            <strong>${escapeHTML(payment.service_name || 'N/A')}</strong>
        </div>

        <div class="detail-row">
            <span>Appointment</span>
            <strong>${escapeHTML(appointment)}</strong>
        </div>

        <div class="detail-row">
            <span>Amount</span>
            <strong>৳${Number(payment.amount || 0).toFixed(2)}</strong>
        </div>

        <div class="detail-row">
            <span>Payment Method</span>
            <strong>${escapeHTML(payment.payment_method || 'N/A')}</strong>
        </div>

        <div class="detail-row">
            <span>Transaction ID</span>
            <strong>${escapeHTML(payment.transaction_id || 'N/A')}</strong>
        </div>

        <div class="detail-row">
            <span>Payment Status</span>
            <strong>${escapeHTML(payment.payment_status || 'Pending')}</strong>
        </div>

        <div class="detail-row">
            <span>Payment Date</span>
            <strong>${escapeHTML(payment.payment_date || 'N/A')}</strong>
        </div>

        <form method="post" action="payments.php" class="payment-status-form">
            <input type="hidden" name="payment_id" value="${escapeHTML(payment.payment_id)}">
            <label for="paymentStatus">Update status</label>
            <select id="paymentStatus" name="payment_status">
                ${['Pending', 'Paid', 'Failed', 'Refunded'].map(status =>
                    `<option value="${status}" ${payment.payment_status === status ? 'selected' : ''}>${status}</option>`
                ).join('')}
            </select>
            <button type="submit" class="view-btn">Save Status</button>
        </form>
    `;

    document.getElementById('paymentModal').classList.add('show');
}

function closeModal() {
    document.getElementById('paymentModal').classList.remove('show');
}

document.getElementById('paymentModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal();
    }
});

function exportCSV() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;

    const filtered = payments.filter(payment => {
        const searchable = [
            payment.payment_id,
            payment.appointment_id,
            payment.customer_name,
            payment.email,
            payment.service_name,
            payment.transaction_id,
            payment.payment_method
        ].join(' ').toLowerCase();

        return searchable.includes(search) &&
            (status === 'All' || payment.payment_status === status);
    });

    if (filtered.length === 0) {
        alert('No payment records available to export.');
        return;
    }

    const headers = [
        'Payment ID',
        'Appointment ID',
        'Customer',
        'Email',
        'Service',
        'Amount',
        'Payment Method',
        'Transaction ID',
        'Status',
        'Payment Date'
    ];

    const rows = filtered.map(p => [
        p.payment_id,
        p.appointment_id,
        p.customer_name,
        p.email,
        p.service_name,
        p.amount,
        p.payment_method,
        p.transaction_id,
        p.payment_status,
        p.payment_date
    ]);

    const csv = [headers, ...rows]
        .map(row => row.map(value =>
            '"' + String(value ?? '').replace(/"/g, '""') + '"'
        ).join(','))
        .join('\n');

    const blob = new Blob(['\uFEFF' + csv], {
        type: 'text/csv;charset=utf-8;'
    });

    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'beauty-salon-payments.csv';
    link.click();

    URL.revokeObjectURL(link.href);
}

// Initial display
renderPayments(payments);
</script>

</body>
</html>
