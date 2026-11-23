
<?php
session_start();

require_once '../db.php';

// Admin login check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Add New Customer
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_customer'])) {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $date = $_POST['date'];
    $password = $_POST['password'];

    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === "" || $password === "") {
        die("All required fields must be filled.");
    }

    if (strlen($password) < 8) {
        die("Password must be at least 8 characters.");
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($conn,
        "INSERT INTO users (name, email, phone, password, role, created_at)
         VALUES (?, ?, ?, ?, 'customer', ?)"
    );

    mysqli_stmt_bind_param(
        $stmt, "sssss",
        $name, $email, $phone, $hashedPassword, $date
    );

    if (mysqli_stmt_execute($stmt)) {
        header("Location: manage-customers.php?added=1");
        exit();
    } else {
        die("Customer save failed: " . mysqli_error($conn));
    }
}

// Edit an existing customer without changing the database schema.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['customer_action'] ?? '') === 'update') {
    header('Content-Type: application/json; charset=utf-8');
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($customerId <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Enter a name and valid email address.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'customer'");
    $stmt->bind_param('sssi', $name, $email, $phone, $customerId);
    try {
        $success = $stmt->execute();
    } catch (mysqli_sql_exception $exception) {
        $success = false;
    }
    $stmt->close();
    if (!$success) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Could not update customer. The email may already be in use.']);
        exit();
    }
    echo json_encode(['success' => true, 'message' => 'Customer updated successfully.']);
    exit();
}

// Delete only customers without retained appointment or review history.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['customer_action'] ?? '') === 'delete') {
    header('Content-Type: application/json; charset=utf-8');
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $usage = $conn->prepare('SELECT (SELECT COUNT(*) FROM appointments WHERE user_id = ?) AS appointments, (SELECT COUNT(*) FROM reviews WHERE user_id = ?) AS reviews, (SELECT COUNT(*) FROM notifications WHERE user_id = ?) AS notifications');
    $usage->bind_param('iii', $customerId, $customerId, $customerId);
    $usage->execute();
    $references = $usage->get_result()->fetch_assoc();
    $usage->close();

    if ($customerId <= 0 || (int) $references['appointments'] > 0 || (int) $references['reviews'] > 0 || (int) $references['notifications'] > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This customer has appointment or review history and cannot be deleted.']);
        exit();
    }

    $delete = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
    $delete->bind_param('i', $customerId);
    $success = $delete->execute() && $delete->affected_rows === 1;
    $delete->close();
    echo json_encode(['success' => $success, 'message' => $success ? 'Customer deleted.' : 'Customer could not be deleted.']);
    exit();
}
$sql = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.phone,
        u.created_at,
        COUNT(a.id) AS bookings
    FROM users u
    LEFT JOIN appointments a ON u.id = a.user_id
    WHERE u.role = 'customer'
    GROUP BY u.id, u.name, u.email, u.phone, u.created_at
    ORDER BY u.id DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Customer Query Failed: " . mysqli_error($conn));
}

$customers = [];

while ($row = mysqli_fetch_assoc($result)) {
    $customers[] = $row;
}

$totalCustomers = count($customers);

$customersWithBookings = 0;
$customersWithoutBookings = 0;

foreach ($customers as $customer) {
    if ((int)$customer['bookings'] > 0) {
        $customersWithBookings++;
    } else {
        $customersWithoutBookings++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Customers | Beauty Salon</title>

<style>
:root {
    --pink: #d94f87;
    --light-pink: #fff0f6;
    --dark: #352631;
    --muted: #887681;
    --border: #f0e3eb;
    --white: #ffffff;
    --green: #21865b;
    --red: #d6455d;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #fff8fb;
    color: var(--dark);
}

button, input, select {
    font: inherit;
}

button {
    cursor: pointer;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 245px;
    background: white;
    border-right: 1px solid var(--border);
    padding: 25px 16px;
    flex-shrink: 0;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 8px 28px;
}

.brand img {
    width: 43px;
    height: 43px;
    border-radius: 50%;
    object-fit: cover;
}

.brand-name {
    font-size: 17px;
    font-weight: bold;
    color: var(--pink);
}

.brand small {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 11px;
}

.nav-title {
    color: #ad9aa5;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: 1px;
    padding: 12px 12px;
}

.nav-link {
    display: block;
    text-decoration: none;
    color: #74616e;
    padding: 13px 12px;
    margin: 4px 0;
    border-radius: 10px;
    font-size: 14px;
}

.nav-link:hover,
.nav-link.active {
    background: var(--light-pink);
    color: var(--pink);
    font-weight: bold;
}

.nav-link.logout {
    margin-top: 25px;
    color: var(--red);
}

/* Main content */
.main {
    flex: 1;
    min-width: 0;
}

.topbar {
    min-height: 80px;
    background: white;
    border-bottom: 1px solid var(--border);
    padding: 18px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.topbar h1 {
    font-size: 23px;
}

.topbar p {
    color: var(--muted);
    font-size: 13px;
    margin-top: 5px;
}

.admin {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: bold;
}

.avatar {
    background: var(--light-pink);
    color: var(--pink);
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
}

.content {
    padding: 28px 30px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 23px;
}

.page-heading h2 {
    font-size: 21px;
    margin-bottom: 7px;
}

.page-heading p {
    color: var(--muted);
    font-size: 13px;
}

.primary-btn {
    border: none;
    background: var(--pink);
    color: white;
    padding: 12px 17px;
    border-radius: 9px;
    font-weight: bold;
}

.primary-btn:hover {
    background: #bd3971;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 22px;
    border: 1px solid var(--border);
    border-radius: 14px;
}

.stat-card .label {
    font-size: 13px;
    color: var(--muted);
}

.stat-card h3 {
    font-size: 27px;
    margin: 12px 0 5px;
}

.stat-card small {
    color: var(--pink);
    font-size: 12px;
}

.panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
}

.panel-heading {
    padding: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.panel-heading h3 {
    font-size: 17px;
}

.filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filters input,
.filters select {
    padding: 11px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
    background: white;
    color: var(--dark);
}

.filters input {
    min-width: 220px;
}

.filters input:focus,
.filters select:focus,
.form-group input:focus,
.form-group select:focus {
    border-color: var(--pink);
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th {
    background: #fff8fb;
    color: var(--muted);
    font-size: 12px;
    text-align: left;
    padding: 15px 20px;
    white-space: nowrap;
}

td {
    padding: 16px 20px;
    border-top: 1px solid #f5edf2;
    font-size: 13px;
}

.customer-info {
    display: flex;
    align-items: center;
    gap: 11px;
}

.customer-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #bd3971;
    background: var(--light-pink);
    font-weight: bold;
    flex-shrink: 0;
}

.customer-info strong {
    display: block;
    font-size: 13px;
    margin-bottom: 4px;
}

.customer-info small {
    color: var(--muted);
    font-size: 11px;
}

.status {
    display: inline-block;
    border-radius: 20px;
    padding: 6px 10px;
    font-size: 11px;
    font-weight: bold;
}

.status.active {
    color: var(--green);
    background: #e8f8ef;
}

.status.inactive {
    color: #997017;
    background: #fff5d9;
}

.actions {
    display: flex;
    gap: 7px;
}

.action-btn {
    border: 1px solid var(--border);
    border-radius: 7px;
    background: white;
    padding: 7px 10px;
    font-size: 12px;
    color: var(--dark);
}

.action-btn:hover {
    background: var(--light-pink);
    color: var(--pink);
}

.action-btn.delete:hover {
    background: #fff0f0;
    color: var(--red);
}

.empty {
    text-align: center;
    color: var(--muted);
    padding: 35px;
    display: none;
}

.footer-note {
    text-align: center;
    color: var(--muted);
    font-size: 12px;
    margin-top: 22px;
}

/* Modal */
.modal {
    position: fixed;
    inset: 0;
    background: rgba(40, 20, 33, 0.45);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    z-index: 10;
}

.modal.show {
    display: flex;
}

.modal-box {
    background: white;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 25px;
    border-radius: 16px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}

.modal-header h2 {
    font-size: 20px;
}

.close-btn {
    border: none;
    background: #fff0f6;
    color: var(--pink);
    border-radius: 8px;
    width: 34px;
    height: 34px;
    font-size: 22px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 8px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 22px;
}

.cancel-btn {
    padding: 11px 16px;
    border: 1px solid var(--border);
    background: white;
    border-radius: 8px;
}

@media (max-width: 900px) {
    .sidebar {
        width: 200px;
    }

    .content {
        padding: 22px 18px;
    }

    .topbar {
        padding: 18px;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
        padding: 12px;
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .brand {
        padding-bottom: 10px;
    }

    .nav-title {
        display: none;
    }

    .sidebar nav {
        display: flex;
        gap: 5px;
        overflow-x: auto;
        padding-bottom: 4px;
    }

    .nav-link {
        white-space: nowrap;
        margin: 0;
        padding: 10px;
        font-size: 12px;
    }

    .topbar h1 {
        font-size: 19px;
    }

    .topbar p {
        font-size: 11px;
    }

    .stats {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .panel-heading {
        padding: 16px;
    }

    .filters,
    .filters input,
    .filters select {
        width: 100%;
    }

    .admin span:last-child {
        display: none;
    }
}
</style>
</head>

<body>

<div class="layout">

   
<aside class="sidebar">

    <div class="brand">
        <img src="../salon-logo.png/LOGO.png" alt="Salon Logo"
             onerror="this.style.display='none'">
        <div>
            <div class="brand-name">Beauty Salon</div>
            <small>Admin Panel</small>
        </div>
    </div>

    <div class="nav-title">MAIN MENU</div>

    <nav>
        <a href="dashboard.php" class="nav-link">Dashboard</a>

        <a href="manage-appointments.php" class="nav-link">
            Appointments
        </a>

        <a href="manage-services.php" class="nav-link">
            Manage Services
        </a>

        <a href="manage-customers.php" class="nav-link active">
            Customers
        </a>

        <a href="manage-staff.php" class="nav-link">
            Staff Members
        </a>

        <a href="payments.php" class="nav-link">
            Payments
        </a>

        <a href="../index.html" class="nav-link">
            View Website
        </a>

        <a href="../logout.php" class="nav-link logout">
            Logout
        </a>
    </nav>

</aside>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Manage Customers</h1>
                <p>View and manage salon customers</p>
            </div>

            <div class="admin">
                <div class="avatar">A</div>
                <span>Admin</span>
            </div>
        </header>

        <section class="content">

            <div class="page-heading">
                <div>
                    <h2>Customer Management</h2>
                <p>Manage customer information and booking history.</p>
                </div>

                <button class="primary-btn" onclick="openAddModal()">
                    + Add Customer
                </button>
            </div>

            <div class="stats">

    <div class="stat-card">
        <div class="label">Total Customers</div>
        <h3><?php echo $totalCustomers; ?></h3>
        <small>All registered customers</small>
    </div>

    <div class="stat-card">
        <div class="label">Customers With Bookings</div>
        <h3><?php echo $customersWithBookings; ?></h3>
        <small>Customers who booked services</small>
    </div>

    <div class="stat-card">
        <div class="label">Customers Without Bookings</div>
        <h3><?php echo $customersWithoutBookings; ?></h3>
        <small>Customers with no bookings</small>
    </div>

</div>

            <div class="panel">
                <div class="panel-heading">
                    <h3>All Customers</h3>

                    <div class="filters">
                        <input
                            type="search"
                            id="searchInput"
                            placeholder="Search name, email, phone..."
                            oninput="renderCustomers()"
                        >

                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>CUSTOMER</th>
                                <th>PHONE</th>
                                <th>JOIN DATE</th>
                                <th>BOOKINGS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>

                        <tbody id="customerTable"></tbody>
                    </table>
                </div>

                <div class="empty" id="emptyMessage">
                    No customers found. Try another search.
                </div>
            </div>

            <div class="footer-note">
                Beauty Salon Appointment and Management System
            </div>

        </section>
    </main>
</div>

<!-- Add/Edit Customer Modal -->
<div class="modal" id="customerModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Customer</h2>
            <button class="close-btn" onclick="closeModal()"
                    aria-label="Close">&times;</button>
        </div>

         <form id="customerForm" method="POST" action="manage-customers.php">
    <input type="hidden" name="add_customer" value="1">

            <input type="hidden" id="customerId">

            <div class="form-group">
                <label for="customerName">Full Name *</label>
                <input type="text" id="customerName" name="name"
       placeholder="Enter full name"
       required maxlength="80">

            </div>

            <div class="form-group">
                <label for="customerEmail">Email Address *</label>
                <input type="email" id="customerEmail" name="email"
       placeholder="example@email.com"
       required maxlength="120">
            </div>

            <div class="form-group">
                <label for="customerPhone">Phone Number *</label>
                <input type="tel" id="customerPhone" name="phone"
       placeholder="01XXXXXXXXX"
       required maxlength="20">
            </div>

            <div class="form-group" id="passwordGroup">
    <label for="customerPassword">Password *</label>
    <input type="password" id="customerPassword" name="password"
       placeholder="Enter customer password"
       minlength="8">
</div>

            <div class="form-group">
                <label for="customerDate">Join Date *</label>
                <input type="date" id="customerDate" name="date" required>
            </div>


            <div class="form-actions">
                <button type="button" class="cancel-btn"
                        onclick="closeModal()">Cancel</button>
                <button type="submit" class="primary-btn"
                        id="saveButton">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
 let customers = <?php
echo json_encode(
    array_map(function ($customer) {
        return [
            'id' => (int)$customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'] ?? '',
            'date' => date('Y-m-d', strtotime($customer['created_at'])),
            'bookings' => (int)$customer['bookings'],
        ];
    }, $customers),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
?>;

function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        }[char];
    });
}

function formatDate(dateString) {
    if (!dateString) return "-";

    const parts = dateString.split("-");
    return parts.length === 3
        ? parts[2] + "-" + parts[1] + "-" + parts[0]
        : dateString;
}

function renderCustomers() {
    const search = document.getElementById("searchInput")
        .value.toLowerCase().trim();

    const filtered = customers.filter(function (customer) {
        const matchesSearch =
            customer.name.toLowerCase().includes(search) ||
            customer.email.toLowerCase().includes(search) ||
            String(customer.phone || '').toLowerCase().includes(search);

        return matchesSearch;
    });

    

    const table = document.getElementById("customerTable");

    table.innerHTML = filtered.map(function (customer) {
        const initial = escapeHTML(customer.name.charAt(0).toUpperCase());
        const safeName = escapeHTML(customer.name);
        const safeEmail = escapeHTML(customer.email);
        const safePhone = escapeHTML(customer.phone);
        const safeDate = escapeHTML(formatDate(customer.date));

        return `
            <tr>
                <td>
                    <div class="customer-info">
                        <div class="customer-avatar">${initial}</div>
                        <div>
                            <strong>${safeName}</strong>
                            <small>${safeEmail}</small>
                        </div>
                    </div>
                </td>
                <td>${safePhone}</td>
                <td>${safeDate}</td>
                <td>${customer.bookings}</td>
                <td>
                    <div class="actions">
                        <button class="action-btn"
                            onclick="editCustomer(${customer.id})">
                            Edit
                        </button>
                        <button class="action-btn delete"
                            onclick="deleteCustomer(${customer.id})">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");

    document.getElementById("emptyMessage").style.display =
        filtered.length === 0 ? "block" : "none";
}

function openAddModal() {
    document.getElementById("customerForm").reset();
    document.getElementById("customerId").value = "";
    document.getElementById("modalTitle").textContent =
        "Add New Customer";
    document.getElementById("saveButton").textContent =
        "Save Customer";
    document.getElementById("customerDate").value =
        new Date().toISOString().slice(0, 10);

    document.getElementById("customerModal").classList.add("show");
}

function editCustomer(id) {
    const customer = customers.find(c => c.id === id);
    if (!customer) return;

    document.getElementById("customerId").value = customer.id;
    document.getElementById("customerName").value = customer.name;
    document.getElementById("customerEmail").value = customer.email;
    document.getElementById("customerPhone").value = customer.phone;
    document.getElementById("customerDate").value = customer.date;

    document.getElementById("modalTitle").textContent =
        "Edit Customer";
    document.getElementById("saveButton").textContent =
        "Update Customer";
    document.getElementById("customerModal").classList.add("show");
}

function closeModal() {
    document.getElementById("customerModal").classList.remove("show");
}



    document.getElementById("customerForm").addEventListener(
    "submit",
    function (event) {
        const idValue = document.getElementById("customerId").value;

        if (idValue) {
            event.preventDefault();
            const formData = new FormData(event.currentTarget);
            formData.set("customer_action", "update");
            formData.set("customer_id", idValue);
            fetch("manage-customers.php", { method: "POST", body: formData })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    if (result.success) window.location.reload();
                })
                .catch(() => alert("Customer update failed. Please try again."));
            return;
        }

        const password = document.getElementById("customerPassword").value;

        if (password.length < 8) {
            event.preventDefault();
            alert("Password must be at least 8 characters.");
            return;
        }

        // Allow the form to submit to PHP
    }
    );

function deleteCustomer(id) {
    const customer = customers.find(c => c.id === id);
    if (!customer) return;

    const confirmed = confirm(
        "Delete customer " + customer.name + "?"
    );

    if (!confirmed) return;

    const formData = new FormData();
    formData.set("customer_action", "delete");
    formData.set("customer_id", id);
    fetch("manage-customers.php", { method: "POST", body: formData })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                customers = customers.filter(c => c.id !== id);
                renderCustomers();
            }
        })
        .catch(() => alert("Customer could not be deleted."));
}

document.getElementById("customerModal").addEventListener(
    "click",
    function (event) {
        if (event.target === this) closeModal();
    }
);

renderCustomers();
</script>

</body>
</html>
