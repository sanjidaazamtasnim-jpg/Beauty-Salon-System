
<?php
session_start();
require_once '../db.php';

// Admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    // Add service
    if ($action === 'add' || $action === 'update') {

        $name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $duration = (int) ($_POST['duration'] ?? 0);
        $status = $_POST['status'] ?? 'Inactive';

        if (
            $name === '' ||
            $description === '' ||
            $price <= 0 ||
            $duration < 5 ||
            !in_array($status, ['Active', 'Inactive'], true)
        ) {
            echo json_encode([
                'success' => false,
                'message' => 'Please enter valid service information.'
            ]);
            exit();
        }

        if ($action === 'add') {

            $stmt = $conn->prepare(
                "INSERT INTO services
                (service_name, description, price, duration, status)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssdis",
                $name,
                $description,
                $price,
                $duration,
                $status
            );

        } else {

            $id = (int) ($_POST['id'] ?? 0);

            $stmt = $conn->prepare(
                "UPDATE services
                SET service_name = ?, description = ?,
                    price = ?, duration = ?, status = ?
                WHERE id = ?"
            );

            $stmt->bind_param(
                "ssdisi",
                $name,
                $description,
                $price,
                $duration,
                $status,
                $id
            );
        }

        if ($stmt && $stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => $action === 'add'
                    ? 'Service added successfully.'
                    : 'Service updated successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Database operation failed.'
            ]);
        }

        if ($stmt) {
            $stmt->close();
        }

        exit();
    }

    // Activate / Deactivate service
    if ($action === 'toggle') {

        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (
            $id <= 0 ||
            !in_array($status, ['Active', 'Inactive'], true)
        ) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid service information.'
            ]);
            exit();
        }

        $stmt = $conn->prepare(
            "UPDATE services SET status = ? WHERE id = ?"
        );

        $stmt->bind_param("si", $status, $id);
        $success = $stmt->execute();

        echo json_encode([
            'success' => $success,
            'message' => $success
                ? 'Service status updated.'
                : 'Could not update service status.'
        ]);

        $stmt->close();
        exit();
    }

    // Delete service
    if ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        $linked = $conn->prepare('SELECT COUNT(*) AS total FROM appointments WHERE service_id = ?');
        $linked->bind_param('i', $id);
        $linked->execute();
        $hasAppointments = (int) $linked->get_result()->fetch_assoc()['total'] > 0;
        $linked->close();

        if ($hasAppointments) {
            echo json_encode([
                'success' => false,
                'message' => 'This service has appointment history and cannot be deleted. Set it to Inactive instead.'
            ]);
            exit();
        }

        $stmt = $conn->prepare(
            "DELETE FROM services WHERE id = ?"
        );

        $stmt->bind_param("i", $id);
        $success = $stmt->execute();

        echo json_encode([
            'success' => $success,
            'message' => $success
                ? 'Service deleted successfully.'
                : 'Could not delete this service. It may be linked to an appointment.'
        ]);

        $stmt->close();
        exit();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Invalid action.'
    ]);
    exit();
}

// Fetch all services
$sql = "SELECT * FROM services ORDER BY id DESC";
$result = $conn->query($sql);

$services = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Services | Beauty Salon</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root {
    --pink: #c95d83;
    --pink-dark: #a94067;
    --pink-light: #fff0f5;
    --bg: #fff8fa;
    --text: #392832;
    --muted: #907e88;
    --border: #f0e4e9;
    --white: #ffffff;
    --green: #23845b;
    --red: #c64b60;
    --shadow: 0 8px 28px rgba(107,48,76,.06);
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

button, input, select, textarea {
    font: inherit;
}

button {
    cursor: pointer;
}

a {
    color: inherit;
    text-decoration: none;
}

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 250px;
    background: white;
    border-right: 1px solid var(--border);
    padding: 25px 17px;
    display: flex;
    flex-direction: column;
    z-index: 30;
    transition: transform .25s ease;
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 0 9px 27px;
    border-bottom: 1px solid var(--border);
}

.brand img, .brand-icon {
    width: 46px;
    height: 46px;
    object-fit: contain;
    border-radius: 50%;
}

.brand-icon {
    display: none;
    align-items: center;
    justify-content: center;
    background: var(--pink-light);
    color: var(--pink);
}

.brand h2 {
    font-family: 'Playfair Display', serif;
    font-size: 19px;
}

.brand p {
    color: var(--muted);
    font-size: 11px;
    margin-top: 4px;
}

.nav-label {
    color: #b2a0a9;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.6px;
    padding: 27px 12px 12px;
}

.nav-menu {
    display: grid;
    gap: 5px;
}

.nav-menu a {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    border-radius: 10px;
    color: #85717d;
    font-size: 13px;
    font-weight: 600;
    transition: .2s;
}

.nav-menu a i {
    width: 19px;
    text-align: center;
}

.nav-menu a:hover,
.nav-menu a.active {
    background: var(--pink-light);
    color: var(--pink-dark);
}

.nav-menu a.active {
    box-shadow: inset 3px 0 var(--pink);
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 20px;
}

.help-card {
    background: linear-gradient(140deg, #fff0f5, #fff8fa);
    border: 1px solid #f5dbe5;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 15px;
}

.help-card i {
    color: var(--pink);
    font-size: 19px;
    margin-bottom: 9px;
}

.help-card h4 {
    font-size: 13px;
    margin-bottom: 5px;
}

.help-card p {
    font-size: 11px;
    color: var(--muted);
    line-height: 1.6;
}

.logout-link {
    display: flex;
    gap: 12px;
    align-items: center;
    color: #a34e67;
    font-size: 13px;
    font-weight: 600;
    padding: 12px 14px;
    border-radius: 10px;
}

.logout-link:hover {
    background: #fff0f2;
}

/* Main layout */
.main {
    margin-left: 250px;
    min-width: 0;
}

.topbar {
    height: 78px;
    background: rgba(255,255,255,.94);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 35px;
    position: sticky;
    top: 0;
    z-index: 15;
    backdrop-filter: blur(12px);
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.menu-toggle {
    display: none;
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 10px;
    background: var(--pink-light);
    color: var(--pink-dark);
}

.breadcrumb {
    color: var(--muted);
    font-size: 11px;
    margin-bottom: 4px;
}

.breadcrumb span {
    color: var(--pink);
}

.topbar h3 {
    font-size: 16px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 17px;
}

.top-date {
    color: var(--muted);
    font-size: 12px;
}

.top-date i {
    color: var(--pink);
    margin-right: 5px;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-left: 17px;
    border-left: 1px solid var(--border);
}

.admin-avatar {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: linear-gradient(135deg, #f6d3e0, #d986a7);
    color: white;
}

.admin-profile strong {
    display: block;
    font-size: 12px;
}

.admin-profile small {
    color: var(--muted);
    font-size: 10px;
}

.content {
    padding: 31px 35px 40px;
    max-width: 1700px;
    margin: auto;
}

.page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 27px;
}

.eyebrow {
    color: var(--pink);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.page-heading h1 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(26px, 3vw, 33px);
    font-weight: 600;
    margin-bottom: 8px;
}

.page-heading p {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 700;
    transition: .2s;
    white-space: nowrap;
}

.btn-primary {
    background: var(--pink);
    color: white;
    box-shadow: 0 5px 14px rgba(201,93,131,.18);
}

.btn-primary:hover {
    background: var(--pink-dark);
    transform: translateY(-1px);
}

.btn-outline {
    background: white;
    border: 1px solid var(--border);
    color: #765d6a;
}

.btn-outline:hover {
    background: var(--pink-light);
    border-color: #efc7d7;
}

/* Statistics */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 20px;
    box-shadow: var(--shadow);
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 17px;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 16px;
}

.icon-pink { background: #fff0f5; color: #c95d83; }
.icon-green { background: #e8f7ef; color: #27875d; }
.icon-blue { background: #edf3ff; color: #5578c8; }
.icon-yellow { background: #fff6df; color: #bb851d; }

.stat-tag {
    font-size: 10px;
    color: #9b8a93;
}

.stat-card h2 {
    font-size: 29px;
    margin-bottom: 6px;
}

.stat-card p {
    color: var(--muted);
    font-size: 11px;
}

/* Toolbar */
.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 17px;
}

.section-heading h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
}

.section-heading p {
    font-size: 11px;
    color: var(--muted);
    margin-top: 5px;
}

.toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 11px;
    margin-bottom: 22px;
}

.search-box {
    position: relative;
    flex: 1 1 240px;
}

.search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #ae96a2;
    font-size: 13px;
}

.search-box input {
    padding-left: 39px;
}

.toolbar input,
.toolbar select {
    height: 43px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0 13px;
    color: var(--text);
    outline: none;
    font-size: 11px;
    min-width: 0;
}

.search-box input {
    width: 100%;
}

.toolbar select {
    flex: 0 1 175px;
}

.toolbar input:focus,
.toolbar select:focus,
.form-control:focus {
    border-color: #d98eaa;
    box-shadow: 0 0 0 3px #fff0f5;
}

/* Service cards */
.services-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 19px;
}

.service-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: transform .2s, box-shadow .2s;
    min-width: 0;
}

.service-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(107,48,76,.1);
}

.service-image {
    height: 165px;
    position: relative;
    background: linear-gradient(135deg, #fbe2eb, #f7c9dc);
    overflow: hidden;
}

.service-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .35s;
}

.service-card:hover .service-image img {
    transform: scale(1.04);
}

.image-placeholder {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    color: #c66b8e;
    font-size: 38px;
    background: linear-gradient(135deg, #fff0f5, #f6d5e2);
}

.category-badge {
    position: absolute;
    left: 13px;
    top: 13px;
    background: rgba(255,255,255,.94);
    color: var(--pink-dark);
    padding: 7px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

.service-status {
    position: absolute;
    right: 13px;
    top: 13px;
    padding: 7px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

.active-status {
    background: #e9f7ef;
    color: #218357;
}

.inactive-status {
    background: #fff0f1;
    color: #c44d60;
}

.service-body {
    padding: 18px;
}

.service-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 9px;
}

.service-title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.4;
}

.service-price {
    color: var(--pink-dark);
    font-weight: 700;
    font-size: 18px;
    white-space: nowrap;
}

.service-price small {
    display: block;
    text-align: right;
    font-size: 9px;
    color: var(--muted);
    font-weight: 500;
    margin-top: 2px;
}

.service-description {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.7;
    min-height: 38px;
    margin-bottom: 15px;
    overflow-wrap: anywhere;
}

.service-meta {
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 12px 0;
    margin-bottom: 15px;
}

.service-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #826c78;
    font-size: 10px;
}

.service-meta i {
    color: var(--pink);
}

.service-actions {
    display: flex;
    gap: 8px;
}

.service-actions button {
    flex: 1;
    min-width: 0;
    padding: 10px 8px;
    border-radius: 9px;
    font-size: 10px;
    font-weight: 700;
    border: 1px solid var(--border);
    background: white;
    color: #765d6a;
    transition: .2s;
}

.service-actions button:hover {
    background: var(--pink-light);
    border-color: #efc7d7;
    color: var(--pink-dark);
}

.service-actions .edit-btn {
    background: var(--pink-light);
    color: var(--pink-dark);
    border-color: #f5dbe5;
}

.service-actions .delete-btn {
    flex: 0 0 38px;
    color: var(--red);
}

.service-actions .delete-btn:hover {
    background: #fff0f1;
}

.service-card.inactive {
    opacity: .75;
}

.no-results {
    grid-column: 1 / -1;
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 55px 20px;
    text-align: center;
    color: var(--muted);
}

.no-results i {
    display: block;
    font-size: 32px;
    color: #dca3b9;
    margin-bottom: 12px;
}

.no-results h3 {
    color: var(--text);
    margin-bottom: 7px;
    font-size: 16px;
}

.no-results p {
    font-size: 11px;
}

/* Form modal */
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 100;
    background: rgba(45,24,36,.45);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    overflow-y: auto;
}

.modal-backdrop.show {
    display: flex;
}

.modal {
    width: 100%;
    max-width: 610px;
    max-height: 92vh;
    overflow-y: auto;
    background: white;
    border-radius: 18px;
    box-shadow: 0 20px 65px rgba(40,20,30,.2);
    animation: appear .2s ease;
}

@keyframes appear {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 23px 25px;
    background: linear-gradient(135deg,#fff0f5,#fffafd);
    border-bottom: 1px solid var(--border);
}

.modal-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 23px;
    margin-bottom: 5px;
}

.modal-header p {
    color: var(--muted);
    font-size: 11px;
}

.modal-close {
    width: 35px;
    height: 35px;
    flex-shrink: 0;
    border: 0;
    border-radius: 10px;
    background: white;
    color: #8c6b7a;
}

.modal-body {
    padding: 24px 25px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 17px;
}

.form-group {
    min-width: 0;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 8px;
}

.required {
    color: var(--red);
}

.form-control {
    width: 100%;
    min-width: 0;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: white;
    color: var(--text);
    padding: 11px 12px;
    outline: none;
    font-size: 12px;
}

textarea.form-control {
    resize: vertical;
    min-height: 90px;
    line-height: 1.6;
}

.form-help {
    color: var(--muted);
    font-size: 10px;
    line-height: 1.5;
    margin-top: 6px;
}

.checkbox-row {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 12px;
    color: #765d6a;
}

.checkbox-row input {
    accent-color: var(--pink);
    width: 16px;
    height: 16px;
}

.modal-footer {
    padding: 16px 25px 23px;
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    border-top: 1px solid var(--border);
}

/* Toast */
.toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 200;
    max-width: calc(100vw - 40px);
    background: #35232d;
    color: white;
    padding: 14px 18px;
    border-radius: 11px;
    font-size: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,.15);
    display: none;
}

.toast.show {
    display: block;
    animation: appear .2s ease;
}

@media (max-width: 1250px) {
    .stats-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }
    .services-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }
    .content {
        padding-left: 25px;
        padding-right: 25px;
    }
    .topbar {
        padding: 0 25px;
    }
}

@media (max-width: 900px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.open {
        transform: translateX(0);
        box-shadow: 10px 0 35px rgba(60,30,45,.12);
    }
    .main {
        margin-left: 0;
    }
    .menu-toggle {
        display: grid;
        place-items: center;
    }
    .topbar {
        padding: 0 20px;
    }
    .content {
        padding: 25px 20px 35px;
    }
}

@media (max-width: 600px) {
    .topbar {
        height: 69px;
        padding: 0 13px;
    }
    .topbar h3 {
        font-size: 13px;
    }
    .top-date {
        display: none;
    }
    .admin-profile {
        padding-left: 9px;
        gap: 6px;
    }
    .admin-profile strong {
        font-size: 10px;
    }
    .admin-profile small {
        font-size: 9px;
    }
    .admin-avatar {
        width: 33px;
        height: 33px;
    }
    .content {
        padding: 22px 13px 30px;
    }
    .page-heading {
        flex-direction: column;
        align-items: stretch;
    }
    .page-heading .btn {
        align-self: flex-start;
    }
    .stats-grid {
        gap: 10px;
    }
    .stat-card {
        padding: 15px 13px;
    }
    .stat-card h2 {
        font-size: 25px;
    }
    .stat-top {
        margin-bottom: 12px;
    }
    .stat-icon {
        width: 34px;
        height: 34px;
    }
    .section-heading {
        align-items: flex-start;
        flex-direction: column;
    }
    .toolbar {
        gap: 9px;
    }
    .search-box {
        flex-basis: 100%;
    }
    .toolbar select {
        flex: 1 1 40%;
    }
    .services-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    .service-image {
        height: 190px;
    }
    .form-grid {
        grid-template-columns: 1fr;
    }
    .form-group.full {
        grid-column: auto;
    }
    .modal-header,
    .modal-body {
        padding-left: 18px;
        padding-right: 18px;
    }
    .modal-footer {
        padding: 15px 18px 19px;
    }
}
</style>
</head>

<body>

<aside class="sidebar" id="sidebar">

    <div class="brand">
        <img src="../salon-logo.png/LOGO.png" alt="Salon Logo"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <span class="brand-icon"><i class="fa-solid fa-spa"></i></span>
        <div>
            <h2>Beauty Salon</h2>
            <p>Appointment Management</p>
        </div>
    </div>

    <div class="nav-label">MAIN MENU</div>

    <nav class="nav-menu">
        <a href="dashboard.php">
            <i class="fa-solid fa-border-all"></i> Dashboard
        </a>

        <a href="manage-appointments.php">
            <i class="fa-regular fa-calendar-check"></i> Appointments
        </a>

        <a href="manage-services.php" class="active">
            <i class="fa-solid fa-spa"></i> Manage Services
        </a>

        <a href="manage-customers.php">
            <i class="fa-solid fa-users"></i> Customers
        </a>

        <a href="manage-staff.php">
            <i class="fa-solid fa-user-tie"></i> Staff
        </a>

        <a href="payments.php">
            <i class="fa-solid fa-credit-card"></i> Payments
        </a>
    </nav>

    <div class="nav-label">WEBSITE</div>

    <nav class="nav-menu">
        <a href="../index.html">
            <i class="fa-solid fa-house"></i> View Website
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="help-card">
            <i class="fa-regular fa-circle-question"></i>
            <h4>Service management</h4>
            <p>Keep your salon services, prices and durations organized.</p>
        </div>

        <a href="../logout.php" class="logout-link">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </div>

</aside>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" id="menuToggle" aria-label="Open menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <div class="breadcrumb">Admin / <span>Manage Services</span></div>
                <h3>Manage Services</h3>
            </div>
        </div>

        <div class="topbar-right">
            <div class="top-date">
                <i class="fa-regular fa-calendar"></i>
                <span id="todayDate"></span>
            </div>
            <div class="admin-profile">
                <div class="admin-avatar"><i class="fa-solid fa-user-tie"></i></div>
                <div>
                    <strong>Admin</strong>
                    <small>Administrator</small>
                </div>
            </div>
        </div>
    </header>

    <section class="content">
        <div class="page-heading">
            <div>
                <div class="eyebrow">Salon service catalog</div>
                <h1>Manage Services</h1>
                <p>Create and update your salon services, prices and duration.</p>
            </div>
            <button class="btn btn-primary" id="addServiceBtn">
                <i class="fa-solid fa-plus"></i> Add New Service
            </button>
        </div>

        <!-- Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon icon-pink"><i class="fa-solid fa-spa"></i></div>
                    <span class="stat-tag">Catalog</span>
                </div>
                <h2 id="totalServices">0</h2>
                <p>Total Services</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon icon-green"><i class="fa-solid fa-circle-check"></i></div>
                    <span class="stat-tag">Available</span>
                </div>
                <h2 id="activeServices">0</h2>
                <p>Active Services</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon icon-blue"><i class="fa-solid fa-layer-group"></i></div>
                    <span class="stat-tag">Variety</span>
                </div>
                <h2>—</h2>
<p>Service Information</p>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon icon-yellow"><i class="fa-solid fa-tags"></i></div>
                    <span class="stat-tag">Pricing</span>
                </div>
                <h2 id="averagePrice">৳0</h2>
                <p>Average Service Price</p>
            </div>
        </div>

        <!-- Services -->
        <div class="section-heading">
            <div>
                <h2>Our Salon Services</h2>
                <p id="serviceResultText">Browse and manage all available services.</p>
            </div>
            <button class="btn btn-outline" id="resetFiltersBtn">
                <i class="fa-solid fa-rotate-left"></i> Reset Filters
            </button>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="searchInput"
                       placeholder="Search service name or description...">
            </div>

            <select id="statusFilter" aria-label="Filter by service status">
                <option value="All">All Services</option>
                <option value="Active">Active Only</option>
                <option value="Inactive">Inactive Only</option>
            </select>
        </div>

        <div class="services-grid" id="servicesGrid"></div>
    </section>
</main>

<!-- ADD / EDIT SERVICE MODAL -->
<div class="modal-backdrop" id="serviceModal"
     role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal">
        <div class="modal-header">
            <div>
                <h2 id="modalTitle">Add New Service</h2>
                <p>Enter the service information below.</p>
            </div>
            <button class="modal-close" id="closeModal" aria-label="Close form">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="serviceForm">
            <div class="modal-body">
                <input type="hidden" id="serviceId">

                <div class="form-grid">
                    <div class="form-group full">
                        <label for="serviceName">Service Name <span class="required">*</span></label>
                        <input class="form-control" type="text" id="serviceName"
                               placeholder="e.g. Haircut & Styling" maxlength="80" required>
                    </div>

                

                    <div class="form-group">
                        <label for="servicePrice">Price (৳) <span class="required">*</span></label>
                        <input class="form-control" type="number" id="servicePrice"
                               min="1" max="1000000" step="1" placeholder="e.g. 1200" required>
                    </div>

                    <div class="form-group">
                        <label for="serviceDuration">Duration (minutes) <span class="required">*</span></label>
                        <input class="form-control" type="number" id="serviceDuration"
                               min="5" max="1440" step="5" placeholder="e.g. 60" required>
                    </div>


                    <div class="form-group full">
                        <label for="serviceDescription">Description <span class="required">*</span></label>
                        <textarea class="form-control" id="serviceDescription"
                                  maxlength="350" placeholder="Describe this salon service..."
                                  required></textarea>
                    </div>

                    <div class="form-group full">
                        <label class="checkbox-row">
                            <input type="checkbox" id="serviceActive" checked>
                            Make this service active and available
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelFormBtn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveServiceBtn">
                    <i class="fa-solid fa-floppy-disk"></i> Save Service
                </button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>
// Load services from PHP / MySQL
let services = <?php echo json_encode(
    $services,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;

const servicesGrid = document.getElementById("servicesGrid");
const serviceModal = document.getElementById("serviceModal");
const serviceForm = document.getElementById("serviceForm");
const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");

function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, function(char) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        }[char];
    });
}

function money(value) {
    return "৳" + Number(value).toLocaleString("en-BD");
}

function updateStats() {
    document.getElementById("totalServices").textContent = services.length;

    document.getElementById("activeServices").textContent =
        services.filter(service => service.status === "Active").length;


    const average = services.length
        ? services.reduce((sum, service) => sum + Number(service.price), 0) / services.length
        : 0;

    document.getElementById("averagePrice").textContent =
        money(Math.round(average));
}

function getFilteredServices() {
    const query = searchInput.value.trim().toLowerCase();
    const status = statusFilter.value;

    return services.filter(service => {
        const searchable = [
            service.service_name,
            service.description
        ].join(" ").toLowerCase();

        const matchesSearch = searchable.includes(query);

        const matchesStatus =
            status === "All" || service.status === status;

        return matchesSearch && matchesStatus;
    });
}

function renderServices() {
    const filtered = getFilteredServices();

    document.getElementById("serviceResultText").textContent =
        `Showing ${filtered.length} of ${services.length} services`;

    if (!filtered.length) {
        servicesGrid.innerHTML = `
            <div class="no-results">
                <i class="fa-solid fa-spa"></i>
                <h3>No services found</h3>
                <p>Try a different search or add a new salon service.</p>
            </div>`;

        updateStats();
        return;
    }

    servicesGrid.innerHTML = filtered.map(service => {
        const isActive = service.status === "Active";

        return `
            <article class="service-card ${isActive ? "" : "inactive"}">

                <div class="service-image">
                    <div class="image-placeholder">
                        <i class="fa-solid fa-spa"></i>
                    </div>

                    <span class="category-badge">Salon Service</span>

                    <span class="service-status ${isActive ? "active-status" : "inactive-status"}">
                        ${escapeHTML(service.status)}
                    </span>
                </div>

                <div class="service-body">

                    <div class="service-title-row">
                        <h3 class="service-title">
                            ${escapeHTML(service.service_name)}
                        </h3>

                        <div class="service-price">
                            ${money(service.price)}
                            <small>per service</small>
                        </div>
                    </div>

                    <p class="service-description">
                        ${escapeHTML(service.description)}
                    </p>

                    <div class="service-meta">
                        <span>
                            <i class="fa-regular fa-clock"></i>
                            ${Number(service.duration)} minutes
                        </span>

                        <span>
                            <i class="fa-solid fa-spa"></i>
                            Beauty Service
                        </span>
                    </div>

                    <div class="service-actions">

                        <button class="edit-btn"
                                data-action="edit"
                                data-id="${service.id}">
                            <i class="fa-regular fa-pen-to-square"></i>
                            Edit
                        </button>

                        <button data-action="toggle"
                                data-id="${service.id}">
                            <i class="fa-solid ${isActive ? "fa-eye-slash" : "fa-eye"}"></i>
                            ${isActive ? "Deactivate" : "Activate"}
                        </button>

                        <button class="delete-btn"
                                data-action="delete"
                                data-id="${service.id}"
                                aria-label="Delete service">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>

                    </div>
                </div>
            </article>`;
    }).join("");

    updateStats();
}

// Add Service Modal
function openAddModal() {
    serviceForm.reset();

    document.getElementById("serviceId").value = "";

    document.getElementById("serviceActive").checked = true;

    document.getElementById("modalTitle").textContent =
        "Add New Service";

    document.getElementById("saveServiceBtn").innerHTML =
        '<i class="fa-solid fa-floppy-disk"></i> Save Service';

    serviceModal.classList.add("show");

    document.getElementById("serviceName").focus();
}

// Edit Service Modal
function openEditModal(id) {
    const service = services.find(
        item => Number(item.id) === Number(id)
    );

    if (!service) return;

    document.getElementById("serviceId").value = service.id;
    document.getElementById("serviceName").value = service.service_name;
    document.getElementById("servicePrice").value = service.price;
    document.getElementById("serviceDuration").value = service.duration;
    document.getElementById("serviceDescription").value = service.description;

    document.getElementById("serviceActive").checked =
        service.status === "Active";

    document.getElementById("modalTitle").textContent =
        "Edit Service";

    document.getElementById("saveServiceBtn").innerHTML =
        '<i class="fa-solid fa-floppy-disk"></i> Update Service';

    serviceModal.classList.add("show");

    document.getElementById("serviceName").focus();
}

function closeModal() {
    serviceModal.classList.remove("show");
}

function showToast(message) {
    const toast = document.getElementById("toast");

    toast.textContent = message;
    toast.classList.add("show");

    clearTimeout(showToast.timeout);

    showToast.timeout = setTimeout(() => {
        toast.classList.remove("show");
    }, 2600);
}

// Send AJAX request
function sendServiceRequest(formData) {
    fetch("manage-services.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.message || "Request completed.");

        if (data.success) {
            setTimeout(() => {
                location.reload();
            }, 500);
        }
    })
    .catch(error => {
        console.error(error);
        showToast("Server error. Please try again.");
    });
}

// Add / Update Service
serviceForm.addEventListener("submit", function(event) {
    event.preventDefault();

    const id = document.getElementById("serviceId").value;
    const name = document.getElementById("serviceName").value.trim();
    const description = document.getElementById("serviceDescription").value.trim();
    const price = document.getElementById("servicePrice").value;
    const duration = document.getElementById("serviceDuration").value;

    const status = document.getElementById("serviceActive").checked
        ? "Active"
        : "Inactive";

    const formData = new FormData();

    formData.append("action", id ? "update" : "add");
    formData.append("id", id);
    formData.append("service_name", name);
    formData.append("description", description);
    formData.append("price", price);
    formData.append("duration", duration);
    formData.append("status", status);

    sendServiceRequest(formData);
});

// Edit / Toggle / Delete
servicesGrid.addEventListener("click", function(event) {
    const button = event.target.closest("button[data-action]");

    if (!button) return;

    const action = button.dataset.action;
    const id = button.dataset.id;

    const service = services.find(
        item => Number(item.id) === Number(id)
    );

    if (!service) return;

    if (action === "edit") {
        openEditModal(id);
        return;
    }

    if (action === "delete") {
        if (!confirm(
            `Are you sure you want to delete "${service.service_name}"?`
        )) {
            return;
        }
    }

    const formData = new FormData();

    formData.append("action", action);
    formData.append("id", id);

    if (action === "toggle") {
        formData.append(
            "status",
            service.status === "Active" ? "Inactive" : "Active"
        );
    } else if (action !== "delete") {
        return;
    }

    sendServiceRequest(formData);
});

// Search and Status Filter
searchInput.addEventListener("input", renderServices);
statusFilter.addEventListener("change", renderServices);

// Reset Filters
document.getElementById("resetFiltersBtn").addEventListener("click", function() {
    searchInput.value = "";
    statusFilter.value = "All";
    renderServices();
});

// Modal Controls
document.getElementById("addServiceBtn").addEventListener(
    "click",
    openAddModal
);

document.getElementById("closeModal").addEventListener(
    "click",
    closeModal
);

document.getElementById("cancelFormBtn").addEventListener(
    "click",
    closeModal
);

serviceModal.addEventListener("click", function(event) {
    if (event.target === serviceModal) {
        closeModal();
    }
});

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeModal();
    }
});

// Mobile Sidebar
document.getElementById("menuToggle").addEventListener("click", function() {
    document.getElementById("sidebar").classList.toggle("open");
});

document.addEventListener("click", function(event) {
    const sidebar = document.getElementById("sidebar");
    const toggle = document.getElementById("menuToggle");

    if (
        window.innerWidth <= 900 &&
        sidebar.classList.contains("open") &&
        !sidebar.contains(event.target) &&
        !toggle.contains(event.target)
    ) {
        sidebar.classList.remove("open");
    }
});

// Header Date
document.getElementById("todayDate").textContent =
    new Date().toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    });

// Load Services
renderServices();

</script>
</body>
</html>


