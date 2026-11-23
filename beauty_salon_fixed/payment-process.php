<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Customer login check
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'customer'
) {
    respond([
        'success' => false,
        'message' => 'Please login as a customer first.'
    ], 401);
}

$userId = (int) $_SESSION['user_id'];

// Create CSRF token
if (empty($_SESSION['payment_csrf'])) {
    $_SESSION['payment_csrf'] = bin2hex(random_bytes(32));
}

// GET: Load a specific payment receipt
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['action'] ?? '') === 'receipt'
) {

    $paymentId = (int) ($_GET['id'] ?? 0);

    if ($paymentId <= 0) {
        respond([
            'success' => false,
            'message' => 'Invalid payment ID.'
        ], 400);
    }

    $sql = "
        SELECT
            p.id,
            u.name AS customer,
            u.email,
            u.phone,
            s.service_name AS service,
            a.appointment_date AS date,
            a.appointment_time AS time,
            p.payment_method AS method,
            p.transaction_id,
            p.amount,
            p.payment_status AS status,
            p.payment_date
        FROM payments p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN services s ON a.service_id = s.id
        JOIN users u ON a.user_id = u.id
        WHERE p.id = ?
          AND a.user_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $paymentId, $userId);
    $stmt->execute();

    $receipt = $stmt->get_result()->fetch_assoc();

    if (!$receipt) {
        respond([
            'success' => false,
            'message' => 'Receipt not found.'
        ], 404);
    }

    respond([
        'success' => true,
        'receipt' => $receipt
    ]);
}

// GET: Load customer's payment history
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['action'] ?? '') === 'history'
) {

    $sql = "
        SELECT
            p.id,
            u.name AS customer,
            s.service_name AS service,
            a.appointment_date AS date,
            p.payment_method AS method,
            p.amount,
            p.payment_status AS status
        FROM payments p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN services s ON a.service_id = s.id
        JOIN users u ON a.user_id = u.id
        WHERE a.user_id = ?
        ORDER BY p.payment_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $payments = [];

    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }

    respond([
        'success' => true,
        'payments' => $payments
    ]);
}

// GET: Load customer's unpaid appointments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $sql = "
        SELECT
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            s.service_name,
            s.price
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.user_id = ?
          AND a.status <> 'Cancelled'
          AND NOT EXISTS (
              SELECT 1
              FROM payments p
              WHERE p.appointment_id = a.id
                AND p.payment_status IN ('Pending', 'Paid')
          )
        ORDER BY a.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $appointments = [];

    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    respond([
        'success' => true,
        'csrf_token' => $_SESSION['payment_csrf'],
        'appointments' => $appointments
    ]);
}

// POST: Save payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $token = $data['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['payment_csrf'], $token)
    ) {
        respond([
            'success' => false,
            'message' => 'Invalid request. Please refresh the page.'
        ], 403);
    }

    $appointmentId = (int) ($data['appointment_id'] ?? 0);
    $method = $data['method'] ?? '';
    $transaction = trim($data['transaction'] ?? '');

    $allowedMethods = ['bKash', 'Nagad', 'Card', 'Cash'];

    if ($appointmentId <= 0 || !in_array($method, $allowedMethods, true)) {
        respond([
            'success' => false,
            'message' => 'Please provide valid payment information.'
        ], 400);
    }

    if (strlen($transaction) > 100) {
        respond([
            'success' => false,
            'message' => 'Transaction reference is too long.'
        ], 400);
    }

    // Verify appointment belongs to this customer
    $stmt = $conn->prepare("
        SELECT a.id, a.status, s.price
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.id = ?
          AND a.user_id = ?
          AND a.status <> 'Cancelled'
        LIMIT 1
    ");

    $stmt->bind_param("ii", $appointmentId, $userId);
    $stmt->execute();

    $appointment = $stmt->get_result()->fetch_assoc();

    if (!$appointment) {
        respond([
            'success' => false,
            'message' => 'Appointment not found.'
        ], 404);
    }

    // Prevent duplicate payment
    $stmt = $conn->prepare("
        SELECT id FROM payments
        WHERE appointment_id = ?
          AND payment_status IN ('Pending', 'Paid')
        LIMIT 1
    ");

    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        respond([
            'success' => false,
            'message' => 'Payment already exists for this appointment.'
        ], 409);
    }

    $amount = (float) $appointment['price'];
    $transactionId = ($transaction !== '') ? $transaction : null;

    // Demo payment: admin confirmation is required
    $paymentStatus = 'Pending';

    $stmt = $conn->prepare("
        INSERT INTO payments
        (appointment_id, amount, payment_method, transaction_id, payment_status)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "idsss",
        $appointmentId,
        $amount,
        $method,
        $transactionId,
        $paymentStatus
    );

    if (!$stmt->execute()) {
        respond([
            'success' => false,
            'message' => 'Could not save payment.'
        ], 500);
    }

    respond([
        'success' => true,
        'message' => 'Payment record saved successfully.',
        'payment_id' => $conn->insert_id,
        'amount' => $amount
    ]);
}

respond([
    'success' => false,
    'message' => 'Invalid request method.'
], 405);
?>
