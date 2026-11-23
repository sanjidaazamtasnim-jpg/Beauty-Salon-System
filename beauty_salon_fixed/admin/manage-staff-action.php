
<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

/* ADMIN AUTHENTICATION */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'admin'
) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit();
}

/* REQUEST METHOD */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit();
}

/* RESPONSE FUNCTION */
function response($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

/* PHOTO UPLOAD FUNCTION */
function uploadStaffPhoto() {

    if (
        !isset($_FILES['photo']) ||
        $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        response(false, 'Photo upload failed.');
    }

    $file = $_FILES['photo'];

    if ($file['size'] > 2 * 1024 * 1024) {
        response(false, 'Photo size must be 2 MB or less.');
    }

    $imageInfo = getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        response(false, 'Please upload a valid image.');
    }

    $mime = $imageInfo['mime'];

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        response(false, 'Only JPG, PNG and WEBP images are allowed.');
    }

    $uploadDir = __DIR__ . '/../uploads/staff/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            response(false, 'Could not create upload folder.');
        }
    }

    $fileName = 'staff_' . bin2hex(random_bytes(12))
              . '.' . $allowed[$mime];

    $destination = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        response(false, 'Could not save staff photo.');
    }

    return 'uploads/staff/' . $fileName;
}

/* DELETE OLD PHOTO FILE */
function removeStaffPhoto($photoPath) {

    if (!$photoPath) {
        return;
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/staff');

    if (!$uploadDir) {
        return;
    }

    $fullPath = realpath(__DIR__ . '/../' . $photoPath);

    if (
        $fullPath &&
        strpos($fullPath, $uploadDir . DIRECTORY_SEPARATOR) === 0 &&
        is_file($fullPath)
    ) {
        unlink($fullPath);
    }
}

$action = $_POST['action'] ?? '';

/* FETCH STAFF */
if ($action === 'fetch') {

    $sql = "SELECT id, staff_name, email, phone, salary,
                   joining_date, specialty, photo, status
            FROM staff
            ORDER BY id DESC";

    $result = $conn->query($sql);

    if (!$result) {
        response(false, 'Could not fetch staff members.');
    }

    $staff = [];

    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }

    response(true, 'Staff loaded successfully.', $staff);
}

/* ADD OR UPDATE STAFF */
if ($action === 'add' || $action === 'update') {

    $id = (int)($_POST['id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $salary = $_POST['salary'] ?? '';
    $joiningDate = $_POST['joining_date'] ?? '';
    $specialty = trim($_POST['specialty'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    if (
        $name === '' || $email === '' || $phone === '' ||
        $salary === '' || $joiningDate === '' || $specialty === ''
    ) {
        response(false, 'Please fill in all required fields.');
    }

    if (strlen($name) > 100) {
        response(false, 'Staff name is too long.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        response(false, 'Please enter a valid email address.');
    }

    if (strlen($email) > 120 || strlen($phone) > 20) {
        response(false, 'Email or phone number is too long.');
    }

    if (!is_numeric($salary) || (float)$salary < 0) {
        response(false, 'Please enter a valid salary.');
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        response(false, 'Invalid staff status.');
    }

    $date = DateTime::createFromFormat('Y-m-d', $joiningDate);

    if (!$date || $date->format('Y-m-d') !== $joiningDate) {
        response(false, 'Please enter a valid joining date.');
    }

    /* CHECK DUPLICATE EMAIL */
    if ($action === 'add') {

        $check = $conn->prepare(
            "SELECT id FROM staff WHERE email = ?"
        );

        $check->bind_param("s", $email);

    } else {

        if ($id <= 0) {
            response(false, 'Invalid staff ID.');
        }

        $check = $conn->prepare(
            "SELECT id FROM staff WHERE email = ? AND id != ?"
        );

        $check->bind_param("si", $email, $id);
    }

    $check->execute();

    $duplicate = $check->get_result()->num_rows > 0;

    $check->close();

    if ($duplicate) {
        response(false, 'This email is already registered.');
    }

    /* HANDLE PHOTO */
    $newPhoto = uploadStaffPhoto();

    $salary = (float)$salary;

    /* ADD STAFF */
    if ($action === 'add') {

        $stmt = $conn->prepare(
            "INSERT INTO staff
            (staff_name, email, phone, salary, joining_date,
             specialty, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "sssdssss",
            $name,
            $email,
            $phone,
            $salary,
            $joiningDate,
            $specialty,
            $newPhoto,
            $status
        );

        if ($stmt->execute()) {
            response(true, 'Staff added successfully.');
        }

        if ($newPhoto) {
            removeStaffPhoto($newPhoto);
        }

        response(false, 'Could not add staff member.');
    }

    /* UPDATE STAFF */
    if ($action === 'update') {

        $oldQuery = $conn->prepare(
            "SELECT photo FROM staff WHERE id = ?"
        );

        $oldQuery->bind_param("i", $id);
        $oldQuery->execute();

        $oldResult = $oldQuery->get_result();

        if ($oldResult->num_rows === 0) {
            response(false, 'Staff member not found.');
        }

        $oldStaff = $oldResult->fetch_assoc();
        $oldPhoto = $oldStaff['photo'];

        $oldQuery->close();

        // Keep old photo if no new photo is uploaded
        $photo = $newPhoto ?: $oldPhoto;

        $stmt = $conn->prepare(
            "UPDATE staff SET
                staff_name = ?,
                email = ?,
                phone = ?,
                salary = ?,
                joining_date = ?,
                specialty = ?,
                photo = ?,
                status = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "sssdssssi",
            $name,
            $email,
            $phone,
            $salary,
            $joiningDate,
            $specialty,
            $photo,
            $status,
            $id
        );

        if ($stmt->execute()) {

            if ($newPhoto && $oldPhoto) {
                removeStaffPhoto($oldPhoto);
            }

            response(true, 'Staff updated successfully.');
        }

        if ($newPhoto) {
            removeStaffPhoto($newPhoto);
        }

        response(false, 'Could not update staff member.');
    }
}

/* DELETE STAFF */
if ($action === 'delete') {

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        response(false, 'Invalid staff ID.');
    }

    $query = $conn->prepare(
        "SELECT photo FROM staff WHERE id = ?"
    );

    $query->bind_param("i", $id);
    $query->execute();

    $result = $query->get_result();

    if ($result->num_rows === 0) {
        response(false, 'Staff member not found.');
    }

    $staff = $result->fetch_assoc();
    $photo = $staff['photo'];

    $query->close();

    $linked = $conn->prepare('SELECT COUNT(*) AS total FROM appointments WHERE staff_id = ?');
    $linked->bind_param('i', $id);
    $linked->execute();
    $hasAppointments = (int) $linked->get_result()->fetch_assoc()['total'] > 0;
    $linked->close();

    if ($hasAppointments) {
        response(false, 'This staff member has appointment history and cannot be deleted. Set the status to Inactive instead.');
    }

    $stmt = $conn->prepare(
        "DELETE FROM staff WHERE id = ?"
    );

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {

        removeStaffPhoto($photo);

        response(true, 'Staff deleted successfully.');
    }

    response(
        false,
        'Could not delete staff. This staff member may be linked to an appointment.'
    );
}

response(false, 'Invalid action.');

