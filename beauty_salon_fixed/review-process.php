
<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

function response($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Customer login check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    response(false, 'Please login as a customer first.');
}

$user_id = (int) $_SESSION['user_id'];

// Load completed appointments available for review
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $sql = "SELECT a.id, a.appointment_date, a.appointment_time,
                   s.service_name
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            LEFT JOIN reviews r
                ON r.appointment_id = a.id
                AND r.user_id = a.user_id
            WHERE a.user_id = ?
              AND a.status = 'Completed'
              AND r.id IS NULL
            ORDER BY a.appointment_date DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        response(false, 'Could not load appointments.');
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $appointments = [];

    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    response(true, 'Appointments loaded.', $appointments);
}

// Submit review
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($appointment_id <= 0) {
        response(false, 'Please select an appointment.');
    }

    if ($rating < 1 || $rating > 5) {
        response(false, 'Please select a rating between 1 and 5.');
    }

    if ($comment === '') {
        response(false, 'Please write a comment.');
    }

    // Verify that this completed appointment belongs to the customer
    $check = $conn->prepare(
        "SELECT id FROM appointments
         WHERE id = ? AND user_id = ? AND status = 'Completed'"
    );

    $check->bind_param("ii", $appointment_id, $user_id);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        response(false, 'Invalid or incomplete appointment.');
    }

    // Prevent duplicate reviews
    $duplicate = $conn->prepare(
        "SELECT id FROM reviews
         WHERE appointment_id = ? AND user_id = ?"
    );

    $duplicate->bind_param("ii", $appointment_id, $user_id);
    $duplicate->execute();

    if ($duplicate->get_result()->num_rows > 0) {
        response(false, 'You have already reviewed this appointment.');
    }

    $stmt = $conn->prepare(
        "INSERT INTO reviews (user_id, appointment_id, rating, comment)
         VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        response(false, 'Could not prepare review.');
    }

    $stmt->bind_param(
        "iiis",
        $user_id,
        $appointment_id,
        $rating,
        $comment
    );

    if ($stmt->execute()) {
        response(true, 'Your review was submitted successfully.');
    }

    response(false, 'Could not submit your review.');
}

response(false, 'Invalid request.');
?>
