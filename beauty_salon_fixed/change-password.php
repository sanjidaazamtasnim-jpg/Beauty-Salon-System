
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $user_id = $_SESSION['user_id'];

    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user || !password_verify($current_password, $user['password'])) {
        $message = "Current password is incorrect.";
        $message_type = "error";

    } elseif (strlen($new_password) < 8) {
        $message = "New password must be at least 8 characters.";
        $message_type = "error";

    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $message_type = "error";

    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $message = "Password changed successfully!";
            $message_type = "success";
        } else {
            $message = "Something went wrong. Please try again.";
            $message_type = "error";
        }

        mysqli_stmt_close($update_stmt);
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Beauty Salon</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fff9f8;
            color: #38252d;
            margin: 0;
            padding: 40px 15px;
        }

        .password-box {
            max-width: 450px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px #eadde1;
        }

        h2 {
            text-align: center;
            color: #b85c78;
        }

        label {
            display: block;
            margin-top: 18px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #eadde1;
            border-radius: 6px;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 22px;
            background: #c66b89;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .success {
            color: green;
            text-align: center;
        }

        .error {
            color: red;
            text-align: center;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #b85c78;
        }
    </style>
</head>

<body>

<div class="password-box">

    <h2>Change Password</h2>

    <?php if ($message != ""): ?>
        <p class="<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="change-password.php">

        <label>Current Password</label>
        <input type="password" name="current_password" required>

        <label>New Password</label>
        <input type="password" name="new_password" required minlength="8">

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required minlength="8">

        <button type="submit">Update Password</button>

    </form>

    <a href="profile.php">Back to Profile</a>

</div>

</body>
</html>