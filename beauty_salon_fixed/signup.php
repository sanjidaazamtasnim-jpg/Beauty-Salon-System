


<?php
include 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Check empty fields
    if (
        $name == "" || $email == "" ||
        $phone == "" || $password == "" ||
        $confirmPassword == ""
    ) {
        $error = "Please fill in all fields.";
    }

    // Validate email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email.";
    }

    // Check password length
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    }

    // Confirm password
    elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    }

    else {

        // Check existing email
        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE email = ?"
        );

        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {

            $error = "Email already registered.";

        } else {

            // Hash password
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert customer data
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users
                (name, email, phone, password, role)
                VALUES (?, ?, ?, ?, 'customer')"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $name,
                $email,
                $phone,
                $hashedPassword
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);
                mysqli_stmt_close($check);

                header("Location: login.php?registered=success");
                exit();

            } else {

                $error = "Registration failed. Please try again.";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($check);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Beauty Salon | Sign Up</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 25px 15px;
            background: #fff5f8;
            color: #513344;
        }

        /* Main Sign Up Container */
        .signup-container {
            width: 100%;
            max-width: 480px;
            padding: 30px 28px;
            background: #ffffff;
            border: 1px solid #e7c6d4;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(130, 53, 82, 0.12);
        }

        /* Logo */
        .logo img {
            width: 125px;
            height: 125px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        /* Heading */
        h1 {
            font-size: 30px;
            color: #a34870;
            margin-bottom: 25px;
        }

        /* Input Fields */
        .input-box {
            width: 100%;
            height: 62px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 0 18px;
            margin-bottom: 15px;
            border: 1px solid #d8b6c5;
            border-radius: 13px;
            background: #fffafb;
            transition: 0.3s;
        }

        .input-box:focus-within {
            border-color: #a34870;
            box-shadow: 0 0 6px rgba(163, 72, 112, 0.18);
        }

        .input-icon {
            width: 25px;
            flex-shrink: 0;
            font-size: 21px;
        }

        .input-box input {
            width: 100%;
            min-width: 0;
            border: none;
            outline: none;
            background: transparent;
            color: #513344;
            font-size: 16px;
        }

        .input-box input::placeholder {
            color: #927b87;
        }

        /* Sign Up Button */
        .signup-btn {
            width: 80%;
            min-height: 57px;
            margin-top: 35px;
            border: none;
            border-radius: 13px;
            background: #a34870;
            color: white;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .signup-btn:hover {
            background: #803552;
            transform: translateY(-2px);
        }

        /* Login Link */
        .login-text {
            margin-top: 25px;
            font-size: 15px;
            line-height: 1.8;
        }

        .login-text a {
            color: #a34870;
            font-weight: bold;
            text-decoration: none;
            margin-left: 5px;
        }

        .login-text a:hover {
            text-decoration: underline;
        }

        /* Mobile Responsive */
        @media (max-width: 480px) {
            .signup-container {
                padding: 25px 18px;
            }

            .logo img {
                width: 110px;
                height: 110px;
            }

            h1 {
                font-size: 26px;
            }

            .input-box {
                height: 56px;
                padding: 0 12px;
            }

            .input-box input {
                font-size: 14px;
            }

            .signup-btn {
                width: 85%;
                margin-top: 25px;
            }
        }
    </style>
</head>

<body>

    <div class="signup-container">

        <!-- Your Logo -->
        <div class="logo">
            <img src="salon-logo.png/LOGO.png" alt="Beauty Salon Logo">
        </div>

        <!-- Page Heading -->
        <h1>Create an Account</h1>

        <!-- Sign Up Form -->
        <form id="signupForm" method="POST" action="">
     <?php if ($error != ""): ?>
    <p style="color: red; text-align: center;">
        <?php echo htmlspecialchars($error); ?>
    </p>
           <?php endif; ?>
 
           <!-- Full Name -->
            <div class="input-box">
                <span class="input-icon" aria-hidden="true">♙</span>

                <input
                    type="text"
                    name="fullname"
                    placeholder="Full Name"
                    autocomplete="name"
                    required>
            </div>

            <!-- Email -->
            <div class="input-box">
                <span class="input-icon" aria-hidden="true">✉</span>

                <input
                    type="email"
                    name="email"
                    placeholder="Email"
                    autocomplete="email"
                    required>
            </div>

            <!-- Phone -->
            <div class="input-box">
                <span class="input-icon" aria-hidden="true">♧</span>

                <input
                    type="tel"
                    name="phone"
                    placeholder="Phone"
                    autocomplete="tel"
                    required>
            </div>

            <!-- Password -->
            <div class="input-box">
                <span class="input-icon" aria-hidden="true">🔒</span>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Password"
                    autocomplete="new-password"
                    minlength="8"
                    required>
            </div>

            <!-- Confirm Password -->
            <div class="input-box">
                <span class="input-icon" aria-hidden="true">🔒</span>

                <input
                    type="password"
                    id="confirmPassword"
                    name="confirmPassword"
                    placeholder="Confirm Password"
                    autocomplete="new-password"
                    minlength="8"
                    required>
            </div>

            <!-- Sign Up Button -->
            <button type="submit" class="signup-btn">
                Sign Up
            </button>

        </form>

        <!-- Login Link -->
        <p class="login-text">
            Already have an account?
            <a href="login.php">Login</a>
        </p>

    </div>

    <script>
    const form = document.getElementById("signupForm");

    const password = document.getElementById("password");
    const confirmPassword =
        document.getElementById("confirmPassword");

    form.addEventListener("submit", function(event) {

        if (password.value !== confirmPassword.value) {

            event.preventDefault();

            alert("Passwords do not match!");

            confirmPassword.focus();
            return;
        }

        // Allow form submission to PHP
    });
</script>

</body>
</html>