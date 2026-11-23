<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Update profile
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['form_action'])
    && $_POST['form_action'] === 'update_profile') {

    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['date_of_birth'] ?: null;
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);

    // Check duplicate email
    $check = mysqli_prepare(
        $conn,
        "SELECT id FROM users WHERE email = ? AND id != ?"
    );

    mysqli_stmt_bind_param($check, "si", $email, $user_id);
    mysqli_stmt_execute($check);
    $check_result = mysqli_stmt_get_result($check);

    if (mysqli_num_rows($check_result) > 0) {
        $message = "This email is already used by another account.";
    } else {
        $update = mysqli_prepare(
            $conn,
            "UPDATE users SET
            name = ?, email = ?, phone = ?, gender = ?,
            date_of_birth = ?, address = ?, city = ?, postal_code = ?
            WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $update,
            "ssssssssi",
            $name,
            $email,
            $phone,
            $gender,
            $dob,
            $address,
            $city,
            $postal_code,
            $user_id
        );

        if (mysqli_stmt_execute($update)) {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;

            mysqli_stmt_close($update);
            mysqli_stmt_close($check);

            header("Location: profile.php?updated=1");
            exit();
        } else {
            $message = "Profile update failed.";
        }

        mysqli_stmt_close($update);
    }

    mysqli_stmt_close($check);
}

// Get customer information
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    die("Customer not found.");
}

if (isset($_GET['updated'])) {
    $message = "Profile updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Beauty Salon</title>

    <style>
        :root {
            --pink: #d94f87;
            --dark-pink: #b9366b;
            --light-pink: #fff0f5;
            --background: #fff9fb;
            --text: #382630;
            --muted: #81717b;
            --border: #f0dce5;
            --white: #ffffff;
            --green: #21864b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        /* Navigation */

        .navbar {
            background: var(--white);
            padding: 15px 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 3px 15px #e9cbd833;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 22px;
            font-weight: bold;
            color: var(--pink);
        }

        .brand-name span {
            color: var(--text);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 19px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #57434f;
            font-size: 14px;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--pink);
        }

        /* Page header */

        .page-header {
            padding: 40px 7%;
            background: linear-gradient(120deg, #ffe6ef, #fff4f8);
        }

        .eyebrow {
            color: var(--pink);
            font-weight: bold;
            font-size: 13px;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .page-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .page-header p:last-child {
            color: var(--muted);
        }

        /* Main layout */

        .container {
            width: min(1120px, 92%);
            margin: 35px auto 60px;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 270px minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 18px #e9cbd81c;
        }

        /* Sidebar */

        .profile-sidebar {
            text-align: center;
        }

        .avatar {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--light-pink);
            color: var(--pink);
            border: 3px solid #f8d3e2;
            font-size: 35px;
            font-weight: bold;
            overflow: hidden;
        }

        .profile-sidebar h2 {
            font-size: 20px;
            overflow-wrap: anywhere;
        }

        .profile-email {
            font-size: 13px;
            color: var(--muted);
            overflow-wrap: anywhere;
            margin: 5px 0 18px;
        }

        .customer-id {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            background: var(--light-pink);
            color: var(--dark-pink);
            font-size: 12px;
            margin-bottom: 22px;
        }

        .completion {
            text-align: left;
            margin: 8px 0 25px;
        }

        .completion-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .progress-track {
            height: 8px;
            border-radius: 10px;
            background: #f5e5ec;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0;
            background: var(--pink);
            border-radius: 10px;
            transition: width 0.2s;
        }

        .side-links {
            display: grid;
            gap: 8px;
            text-align: left;
        }

        .side-links a,
        .side-links button {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            border-radius: 8px;
            padding: 11px 12px;
            background: transparent;
            color: #57434f;
            cursor: pointer;
            font-size: 14px;
        }

        .side-links a:hover,
        .side-links a.active {
            background: var(--light-pink);
            color: var(--dark-pink);
        }

        .side-links .logout-link {
            color: #c13e3e;
        }

        /* Form sections */

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .section-heading h2 {
            font-size: 21px;
        }

        .section-description {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 23px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px;
        }

        .field {
            min-width: 0;
        }

        .field.full-width {
            grid-column: 1 / -1;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-width: 0;
            padding: 12px 13px;
            border: 1px solid #ead5df;
            border-radius: 9px;
            background: white;
            color: var(--text);
            outline: none;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--pink);
            box-shadow: 0 0 0 3px #d94f8718;
        }

        .field input:disabled,
        .field select:disabled,
        .field textarea:disabled {
            background: #faf6f8;
            color: #786b73;
            cursor: not-allowed;
        }

        .field textarea {
            resize: vertical;
            min-height: 85px;
        }

        .field small {
            display: block;
            color: var(--muted);
            font-size: 11px;
            margin-top: 5px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .btn {
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 11px 17px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: var(--pink);
            color: white;
        }

        .btn-primary:hover {
            background: var(--dark-pink);
        }

        .btn-outline {
            border-color: var(--border);
            background: white;
            color: var(--text);
        }

        .btn-outline:hover {
            background: var(--light-pink);
        }

        .form-status {
            display: none;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin: 15px 0;
        }

        .form-status.success {
            display: block;
            color: var(--green);
            background: #eaf8ef;
        }

        .form-status.error {
            display: block;
            color: #b42318;
            background: #fff0ef;
        }

        /* Password section */

        .password-card {
            margin-top: 24px;
        }

        .password-note {
            padding: 13px;
            background: #fff7e9;
            color: #7c5a1d;
            border-radius: 9px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .password-field {
            position: relative;
        }

        .password-field input {
            padding-right: 60px;
        }

        .toggle-password {
            position: absolute;
            right: 9px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--pink);
            font-size: 12px;
            cursor: pointer;
        }

        /* Footer */

        .footer {
            background: #382630;
            color: white;
            text-align: center;
            padding: 25px 15px;
            font-size: 13px;
            line-height: 1.8;
        }

        @media (max-width: 850px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-sidebar {
                text-align: center;
            }

            .side-links {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .navbar {
                padding: 15px 4%;
            }

            .nav-links {
                gap: 12px;
            }

            .page-header {
                padding: 30px 5%;
            }

            .page-header h1 {
                font-size: 27px;
            }

            .container {
                width: 94%;
                margin-top: 22px;
            }

            .card {
                padding: 18px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full-width {
                grid-column: auto;
            }

            .form-actions .btn {
                flex: 1;
            }

            .side-links {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<!-- Navigation -->
<nav class="navbar">
    <a href="index.html" class="brand" aria-label="Beauty Salon home">
        <img
            src="salon-logo.png/LOGO.png"
            alt="Beauty Salon logo"
            onerror="this.style.display='none'"
        >
        <span class="brand-name">Beauty<span>Salon.</span></span>
    </a>

    <div class="nav-links">
        <a href="index.html">Home</a>
        <a href="services.php">Services</a>
        <a href="appointment.php">Book Appointment</a>
        <a href="my-appointments.php">My Appointments</a>
        <a href="profile.php" class="active">My Profile</a>
    </div>
</nav>

<!-- Page Header -->
<header class="page-header">
    <p class="eyebrow">YOUR PERSONAL ACCOUNT</p>
    <h1>My Profile</h1>
    <p>Manage your personal information and account settings.</p>
</header>

<main class="container">

    <div class="profile-layout">

        <!-- Profile Sidebar -->
        <aside class="card profile-sidebar">

            <div class="avatar" id="profileAvatar">C</div>

            <h2 id="sidebarName">
    <?php echo htmlspecialchars($user['name']); ?>
           </h2>
            <p class="profile-email" id="sidebarEmail">
                <?php echo htmlspecialchars($user['email']); ?>
            </p>

            <div class="customer-id">
                Customer ID:
<span id="customerId">
    CUS-<?php echo str_pad($user['id'], 3, '0', STR_PAD_LEFT); ?>
</span>
            </div>

            <div class="completion">
                <div class="completion-row">
                    <span>Profile completion</span>
                    <strong id="completionText">0%</strong>
                </div>

                <div class="progress-track">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
            </div>

            <div class="side-links">
                <a href="profile.php" class="active">👤 Personal Information</a>
                <a href="my-appointments.php">📅 My Appointments</a>
                <a href="services.php">💗 Explore Services</a>
                <button type="button" id="logoutButton" class="logout-link">
                    ↪ Logout
                </button>
            </div>

        </aside>

        <!-- Main Content -->
        <div>

            <!-- Personal Information -->
            <section class="card">

                <div class="section-heading">
                    <h2>Personal Information</h2>
                    <button
                        type="button"
                        class="btn btn-outline"
                        id="editButton"
                    >
                        Edit Profile
                    </button>
                </div>

                <p class="section-description">
                    Update your name and contact information.
                </p>

                <div id="profileStatus" class="form-status" role="status"></div>
                <?php if (!empty($message)): ?>
    <div class="form-status <?php echo isset($_GET['updated']) ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

                <form id="profileForm" action="profile.php" method="post">

                    <!-- Backend can identify the customer through the
                         authenticated session. This ID is demo-only. -->
                    <input
                        type="hidden"
                        name="customer_id"
                        id="customer_id"
                        value="<?php echo (int)$user['id']; ?>"
                    >

                    <input
                        type="hidden"
                        name="form_action"
                        value="update_profile"
                    >

                    <div class="form-grid">

                        <div class="field">
                            <label for="full_name">Full Name *</label>
                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                autocomplete="name"
                                maxlength="100"
                                required 
                                value="<?php echo htmlspecialchars($user['name']); ?>"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="email">Email Address *</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                autocomplete="email"
                                maxlength="150"
                                required
                                value="<?php echo htmlspecialchars($user['email']); ?>"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="phone">Phone Number *</label>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                autocomplete="tel"
                                maxlength="20"
                                pattern="[0-9+() -]{7,20}"
                                title="Enter a valid phone number."
                                required
                                value="<?php echo htmlspecialchars($user['phone']); ?>"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="gender">Gender</label>
                            <select
                                id="gender"
                                name="gender"
                                disabled
                            >
                                <option value="">Prefer not to say</option>
<option value="Female" <?php if ($user['gender'] == 'Female') echo 'selected'; ?>>Female</option>
<option value="Male" <?php if ($user['gender'] == 'Male') echo 'selected'; ?>>Male</option>
<option value="Other" <?php if ($user['gender'] == 'Other') echo 'selected'; ?>>Other</option>

                            </select>
                        </div>

                        <div class="field">
                            <label for="date_of_birth">Date of Birth</label>
                            <input
                                type="date"
                                id="date_of_birth"
                                name="date_of_birth"
                                autocomplete="bday"
                                value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>"
                                disabled
                            >
                        </div>

                        <div class="field">
                            <label for="created_at">Member Since</label>
                            <input
                                type="text"
                                id="created_at"
                                name="created_at"
                                value="<?php echo htmlspecialchars($user['created_at']); ?>"
                                disabled
                                aria-describedby="memberNote"
                            >
                            <small id="memberNote">
                                Set automatically by the server when registered.
                            </small>
                        </div>

                        <div class="field full-width">
                            <label for="address">Street Address</label>
                            <textarea
    id="address"
    name="address"
    autocomplete="street-address"
    maxlength="500"
    disabled
><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="field">
                            <label for="city">City</label>
                            <input
    type="text"
    id="city"
    name="city"
    autocomplete="address-level2"
    maxlength="100"
    value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
    disabled
>
                        </div>

                        <div class="field">
                            <label for="postal_code">Postal Code</label>
                            <input
    type="text"
    id="postal_code"
    name="postal_code"
    autocomplete="postal-code"
    maxlength="20"
    value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>"
    disabled
>
                        </div>

                    </div>

                    <div class="form-actions" id="profileActions" hidden>
                        <button
                            type="button"
                            class="btn btn-outline"
                            id="cancelButton"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Save Changes
                        </button>
                    </div>

                </form>

            </section>

            <!-- Change Password -->
            <section class="card password-card">

                <div class="section-heading">
                    <h2>Change Password</h2>
                </div>

                <p class="section-description">
                    Keep your account secure by using a strong password.
                </p>

                <div class="password-note">
                    Password changes require server-side verification.
                    This section is prepared for future backend integration.
                </div>

                <div
                    id="passwordStatus"
                    class="form-status"
                    role="status"
                ></div>

                <form
                    id="passwordForm"
                    action="change-password.php"
                    method="post"
                >

                    <input
                        type="hidden"
                        name="form_action"
                        value="change_password"
                    >

                    <div class="form-grid">

                        <div class="field full-width">
                            <label for="current_password">
                                Current Password *
                            </label>

                            <div class="password-field">
                                <input
                                    type="password"
                                    id="current_password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    required
                                    minlength="8"
                                    disabled
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="current_password"
                                    disabled
                                >
                                    Show
                                </button>
                            </div>
                        </div>

                        <div class="field">
                            <label for="new_password">
                                New Password *
                            </label>

                            <div class="password-field">
                                <input
                                    type="password"
                                    id="new_password"
                                    name="new_password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    maxlength="72"
                                    required
                                    disabled
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="new_password"
                                    disabled
                                >
                                    Show
                                </button>
                            </div>

                            <small>Use at least 8 characters.</small>
                        </div>

                        <div class="field">
                            <label for="confirm_password">
                                Confirm New Password *
                            </label>

                            <div class="password-field">
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    maxlength="72"
                                    required
                                    disabled
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="confirm_password"
                                    disabled
                                >
                                    Show
                                </button>
                            </div>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button
                            type="button"
                            class="btn btn-outline"
                            id="enablePasswordButton"
                        >
                            Change Password
                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            id="savePasswordButton"
                            hidden
                        >
                            Update Password
                        </button>
                    </div>

                </form>

            </section>

        </div>
    </div>
</main>

<footer class="footer">
    <p>Beauty Salon Appointment and Management System</p>
    <p>© 2026 Beauty Salon. All rights reserved.</p>
</footer>

<script>
const profileForm = document.getElementById("profileForm");
const editButton = document.getElementById("editButton");
const profileActions = document.getElementById("profileActions");

const editableFields = [
    "full_name",
    "email",
    "phone",
    "gender",
    "date_of_birth",
    "address",
    "city",
    "postal_code"
];

editButton.addEventListener("click", function () {
    editableFields.forEach(function (id) {
        document.getElementById(id).disabled = false;
    });

    editButton.hidden = true;
    profileActions.hidden = false;
});

document.getElementById("cancelButton").addEventListener("click", function () {
    window.location.href = "profile.php";
});

document.getElementById("logoutButton").addEventListener("click", function () {
    window.location.href = "logout.php";
});
const enablePasswordButton = document.getElementById("enablePasswordButton");
const savePasswordButton = document.getElementById("savePasswordButton");

enablePasswordButton.addEventListener("click", function () {

    const passwordFields = [
        "current_password",
        "new_password",
        "confirm_password"
    ];

    passwordFields.forEach(function (id) {
        document.getElementById(id).disabled = false;
    });

    document.querySelectorAll(".password-card .toggle-password")
        .forEach(function (button) {
            button.disabled = false;
        });

    enablePasswordButton.hidden = true;
    savePasswordButton.hidden = false;
});
</script>

</body>
</html>