
<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$sql = "SELECT
            r.id,
            r.rating,
            r.comment,
            DATE_FORMAT(r.created_at, '%d %b %Y') AS created_at,
            u.name,
            s.service_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN appointments a ON r.appointment_id = a.id
        JOIN services s ON a.service_id = s.id
        ORDER BY r.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Could not load reviews.',
        'data' => []
    ]);
    exit;
}

$reviews = [];

while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

echo json_encode([
    'success' => true,
    'message' => 'Reviews loaded successfully.',
    'data' => $reviews
]);
?>
