

<?php
session_start();

include 'db.php';

// Check customer login
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    header("Location: login.php");
    exit();
}

// Get logged-in customer information
$user_id = $_SESSION['user_id'];
$selected_service_id = (int) ($_GET['service_id'] ?? 0);

$sql = "SELECT name, email, phone FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    die("Customer information not found.");
}
$service_sql = "SELECT id, service_name, description, price, duration
                 FROM services
                 WHERE status = 'Active'
                 ORDER BY id ASC";

$service_result = mysqli_query($conn, $service_sql);
if (!$service_result) {
    die("Service Query Failed: " . mysqli_error($conn));
}
// Handle appointment booking
$booking_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $service_id = intval($_POST['service'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if ($service_id <= 0 || empty($appointment_date) || empty($appointment_time)) {

        $booking_message = "Please complete all required fields.";

    } elseif ($appointment_date < date('Y-m-d')) {

        $booking_message = "Please select today or a future date.";

    } else {

        // Check whether selected service is active
        $check_sql = "SELECT id FROM services
                      WHERE id = ? AND status = 'Active'";

        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $service_id);
        mysqli_stmt_execute($check_stmt);

        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) == 1) {

            $insert_sql = "INSERT INTO appointments
                (user_id, service_id, appointment_date,
                 appointment_time, status, notes)
                VALUES (?, ?, ?, ?, 'Pending', ?)";

            $insert_stmt = mysqli_prepare($conn, $insert_sql);

            mysqli_stmt_bind_param(
                $insert_stmt,
                "iisss",
                $user_id,
                $service_id,
                $appointment_date,
                $appointment_time,
                $notes
            );

            if (mysqli_stmt_execute($insert_stmt)) {

                header("Location: appointment.php?booking=success");
                exit();

            } else {

                $booking_message = "Booking failed. Please try again.";

            }

            mysqli_stmt_close($insert_stmt);

        } else {

            $booking_message = "Selected service is not available.";

        }

        mysqli_stmt_close($check_stmt);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book an Appointment | Beauty Salon</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

:root {
    --rose: #a64d70;
    --dark: #633448;
    --pink: #f8e5ed;
    --light: #fff8fa;
    --text: #49313d;
    --muted: #8d7882;
    --white: #ffffff;
}

body {
    background: var(--light);
    color: var(--text);
}

a {
    text-decoration: none;
    color: inherit;
}

button, input, select, textarea {
    font: inherit;
}

/* NAVIGATION */
header {
    background: white;
    padding: 13px 7%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    box-shadow: 0 3px 15px #6334480d;
    position: sticky;
    top: 0;
    z-index: 10;
}

.logo {
    display: flex;
    align-items: center;
    gap: 11px;
}

.logo img {
    width: 49px;
    height: 49px;
    object-fit: contain;
}

.logo h2 {
    font-size: 20px;
    color: var(--rose);
}

.logo small {
    display: block;
    color: var(--muted);
    font-size: 10px;
    margin-top: 4px;
}

nav {
    display: flex;
    flex-wrap: wrap;
    gap: 22px;
}

nav a {
    color: var(--text);
    font-size: 13px;
    transition: 0.2s;
}

nav a:hover,
nav a.active {
    color: var(--rose);
}

nav .nav-btn {
    background: var(--rose);
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
}

/* HERO */
.hero {
    background: linear-gradient(120deg, #f4d9e5, #fff1f5, #f9e4d9);
    padding: 43px 20px;
    text-align: center;
}

.hero .tag {
    color: var(--rose);
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 12px;
}

.hero h1 {
    color: var(--dark);
    font-size: clamp(29px, 4vw, 42px);
    margin-bottom: 13px;
}

.hero p {
    max-width: 600px;
    margin: auto;
    color: #765565;
    font-size: 14px;
    line-height: 1.8;
}

/* MAIN LAYOUT */
.container {
    max-width: 1160px;
    margin: 38px auto;
    padding: 0 22px;
}

.booking-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(270px, 0.85fr);
    gap: 26px;
    align-items: start;
}

.card {
    background: white;
    border: 1px solid #f0dfe7;
    border-radius: 17px;
    box-shadow: 0 5px 22px #63344808;
    overflow: hidden;
}

.form-card {
    padding: 28px;
}

.section-heading {
    margin-bottom: 23px;
}

.section-heading h2 {
    color: var(--dark);
    font-size: 22px;
    margin-bottom: 8px;
}

.section-heading p {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.7;
}

/* SERVICE CARDS */
.service-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 27px;
}

.service-option {
    position: relative;
    border: 2px solid #f0e3e9;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: 0.2s;
    background: white;
}

.service-option:hover {
    border-color: #dca0b8;
    transform: translateY(-2px);
}

.service-option.selected {
    border-color: var(--rose);
    background: #fff6f9;
    box-shadow: 0 3px 12px #a64d7015;
}

.service-option input {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 18px;
    height: 18px;
    accent-color: var(--rose);
    z-index: 1;
}

.service-option img {
    width: 100%;
    height: 135px;
    display: block;
    object-fit: cover;
}

.service-info {
    padding: 13px;
}

.service-info h3 {
    font-size: 15px;
    color: var(--dark);
    margin-bottom: 6px;
}

.service-info p {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
}

.service-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;
    margin-top: 12px;
}

.price {
    color: var(--rose);
    font-weight: bold;
    font-size: 15px;
}

.duration {
    color: var(--muted);
    font-size: 10px;
}

/* FORM */
.divider {
    border: none;
    border-top: 1px solid #f0e3e9;
    margin: 25px 0;
}

.form-title {
    font-size: 17px;
    color: var(--dark);
    margin-bottom: 19px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.field {
    min-width: 0;
}

.field.full {
    grid-column: 1 / -1;
}

.field label {
    display: block;
    color: #5b3d4b;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 9px;
}

.required {
    color: #c14d73;
}

.field input,
.field select,
.field textarea {
    width: 100%;
    min-height: 45px;
    padding: 12px;
    border: 1px solid #e8d9e1;
    border-radius: 8px;
    outline: none;
    background: white;
    color: var(--text);
    font-size: 13px;
    transition: 0.2s;
}

.field textarea {
    resize: vertical;
    min-height: 85px;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: var(--rose);
    box-shadow: 0 0 0 3px #a64d7012;
}

.field input[type="date"] {
    color-scheme: light;
}

.hint {
    font-size: 11px;
    color: var(--muted);
    margin-top: 7px;
    line-height: 1.5;
}

/* BOOKING SUMMARY */
.summary-card {
    position: sticky;
    top: 100px;
}

.summary-header {
    background: linear-gradient(130deg, #f5dce6, #fff0f5);
    padding: 23px;
}

.summary-header h2 {
    color: var(--dark);
    font-size: 20px;
    margin-bottom: 8px;
}

.summary-header p {
    color: #765565;
    font-size: 12px;
    line-height: 1.7;
}

.summary-body {
    padding: 23px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    padding: 14px 0;
    border-bottom: 1px solid #f1e6eb;
    font-size: 12px;
}

.summary-row .label {
    color: var(--muted);
}

.summary-row .value {
    color: var(--dark);
    text-align: right;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.total-row {
    margin-top: 8px;
    padding: 18px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.total-row span:first-child {
    font-size: 14px;
    font-weight: bold;
    color: var(--dark);
}

.total-price {
    font-size: 25px;
    color: var(--rose);
    font-weight: bold;
}

.currency {
    font-size: 12px;
}

.submit-btn {
    width: 100%;
    border: none;
    border-radius: 9px;
    padding: 15px;
    background: var(--rose);
    color: white;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.25s;
}

.submit-btn:hover {
    background: var(--dark);
    transform: translateY(-2px);
}

.privacy-note {
    margin-top: 15px;
    color: var(--muted);
    text-align: center;
    font-size: 10px;
    line-height: 1.7;
}

.info-box {
    background: #fff6f9;
    border: 1px solid #f3dce6;
    border-radius: 10px;
    padding: 15px;
    margin-top: 20px;
}

.info-box h3 {
    color: var(--dark);
    font-size: 13px;
    margin-bottom: 9px;
}

.info-box p {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.8;
}

/* FRONTEND DEMO MESSAGE */
.message {
    display: none;
    border-radius: 9px;
    padding: 14px;
    margin-top: 16px;
    font-size: 12px;
    line-height: 1.7;
}

.message.show {
    display: block;
}

.message.success {
    background: #e9f7ee;
    border: 1px solid #b8e3c7;
    color: #246c43;
}

.message.error {
    background: #fff0f2;
    border: 1px solid #f2c5ce;
    color: #983d50;
}

/* FOOTER */
footer {
    background: var(--dark);
    color: #f8e6ee;
    text-align: center;
    padding: 25px 15px;
    font-size: 12px;
    line-height: 2;
    margin-top: 45px;
}

footer strong {
    font-size: 16px;
}

/* RESPONSIVE */
@media (max-width: 850px) {
    .booking-layout {
        grid-template-columns: 1fr;
    }

    .summary-card {
        position: static;
    }

    header {
        flex-wrap: wrap;
        justify-content: center;
    }

    nav {
        justify-content: center;
        gap: 16px;
    }
}

@media (max-width: 500px) {
    .container {
        padding: 0 12px;
        margin: 22px auto;
    }

    .form-card {
        padding: 17px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .service-grid {
        gap: 9px;
    }

    .service-option img {
        height: 105px;
    }

    .service-info {
        padding: 10px;
    }

    .service-info h3 {
        font-size: 13px;
    }

    .price {
        font-size: 13px;
    }

    .duration {
        font-size: 9px;
    }

    .hero {
        padding: 32px 17px;
    }
}
</style>
</head>

<body>

<!-- NAVIGATION -->
<header>
    <a href="index.html" class="logo">
        <!-- Add your own logo file in this location -->
        <img src="salon-logo.png/LOGO.png" alt="Beauty Salon Logo">
        <div>
            <h2>Beauty Salon</h2>
            <small>Beauty Begins Here!</small>
        </div>
    </a>

    <nav>
        <a href="index.html">Home</a>
        <a href="index.html#services">Services</a>
        <a href="appointment.php" class="active">Book Appointment</a>
        <a href="my-appointments.php">My Appointments</a>
        <a href="logout.php" class="nav-btn">Logout</a>
        <a href="payment.html">Proceed to Payment</a>
    </nav>
</header>


<!-- HERO -->
<section class="hero">
    <div class="tag">Your beauty, our priority</div>
    <h1>Book Your Beauty Session</h1>
    <p>
        Take a little time for yourself. Choose your favourite service,
        select a convenient date and time, and plan your salon visit.
    </p>
</section>


<main class="container">

    <div class="booking-layout">

        <!-- BOOKING FORM -->
        <section class="card form-card">

            <div class="section-heading">
                <h2>Make an Appointment 🌸</h2>
                <p>Choose a service and fill in your booking details.</p>
            </div>

            <form id="bookingForm" method="POST" action="">

                <!-- SERVICE SELECTION -->
                <h3 class="form-title">01. Choose Your Service</h3>

                <div class="service-grid">

    <?php while ($service = mysqli_fetch_assoc($service_result)): ?>

        <label class="service-option">

            <input type="radio"
                   name="service"
                   value="<?php echo $service['id']; ?>"
                   <?php echo $selected_service_id === (int) $service['id'] ? 'checked' : ''; ?>
                   data-price="<?php echo $service['price']; ?>"
                   data-duration="<?php echo $service['duration']; ?> minutes"
                   required>

            <div class="service-info">

                <h3>
                    <?php echo htmlspecialchars($service['service_name']); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($service['description']); ?>
                </p>

                <div class="service-bottom">

                    <span class="price">
                        ৳<?php echo number_format($service['price']); ?>
                    </span>

                    <span class="duration">
                        <?php echo $service['duration']; ?> min
                    </span>

                </div>

            </div>

        </label>

    <?php endwhile; ?>


                <hr class="divider">


                <!-- CUSTOMER INFORMATION -->
                <h3 class="form-title">02. Your Information</h3>

                <div class="form-grid">

                    <div class="field">
                        <label for="customerName">
                            Full Name <span class="required">*</span>
                        </label>
                        <input type="text" id="customerName"
       name="customerName"
       value="<?php echo htmlspecialchars($customer['name']); ?>"
       readonly required>
                    </div>

                    <div class="field">
                        <label for="email">
                            Email Address <span class="required">*</span>
                        </label>
                        <input type="email" id="email"
       name="email"
       value="<?php echo htmlspecialchars($customer['email']); ?>"
       readonly required>
                    </div>

                    <div class="field full">
                        <label for="phone">
                            Phone Number <span class="required">*</span>
                        </label>
                        <input type="tel" id="phone"
       name="phone"
       value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
       readonly required>
                    </div>

                </div>


                <hr class="divider">


                <!-- DATE AND TIME -->
                <h3 class="form-title">03. Date & Time</h3>

                <div class="form-grid">

                    <div class="field">
                        <label for="date">
                            Appointment Date <span class="required">*</span>
                        </label>
                        <input type="date" id="date" name="appointment_date" required>
                    </div>

                    <div class="field">
                        <label for="time">
                            Preferred Time <span class="required">*</span>
                        </label>
                       <select id="time" name="appointment_time" required>
    <option value="">Select a time</option>
    <option value="10:00">10:00 AM</option>
    <option value="11:00">11:00 AM</option>
    <option value="12:00">12:00 PM</option>
    <option value="13:00">01:00 PM</option>
    <option value="14:00">02:00 PM</option>
    <option value="15:00">03:00 PM</option>
    <option value="16:00">04:00 PM</option>
    <option value="17:00">05:00 PM</option>
</select>
                        <p class="hint">
                            Demo time slots. Actual availability will be
                            checked after backend integration.
                        </p>
                    </div>

                    <div class="field full">
                        <label for="notes">Special Requests (Optional)</label>
                        <textarea id="notes" name="notes"
          placeholder="Any preferences or additional information..."></textarea>
                    </div>

                </div>

                <div id="formMessage"
     class="message <?php echo (isset($_GET['booking']) && $_GET['booking'] == 'success') || !empty($booking_message) ? 'show' : ''; ?>">

    <?php
    if (isset($_GET['booking']) && $_GET['booking'] == 'success') {
        echo "Your appointment has been booked successfully!";
    } elseif (!empty($booking_message)) {
        echo htmlspecialchars($booking_message);
    }
    ?>

</div>

                <p class="hint" style="margin-top:18px;">
                    * All required fields must be completed.
                </p>

                <button type="submit" class="submit-btn"
                        style="margin-top:16px;">
                    Confirm Booking &rarr;
                </button>

            </form>
        </section>


        <!-- BOOKING SUMMARY -->
        <aside class="card summary-card">

            <div class="summary-header">
                <h2>Your Booking ✨</h2>
                <p>Review your selected service and appointment details.</p>
            </div>

            <div class="summary-body">

                <div class="summary-row">
                    <span class="label">Selected Service</span>
                    <span class="value" id="summaryService">Haircut</span>
                </div>

                <div class="summary-row">
                    <span class="label">Duration</span>
                    <span class="value" id="summaryDuration">30 minutes</span>
                </div>

                <div class="summary-row">
                    <span class="label">Appointment Date</span>
                    <span class="value" id="summaryDate">Not selected</span>
                </div>

                <div class="summary-row">
                    <span class="label">Preferred Time</span>
                    <span class="value" id="summaryTime">Not selected</span>
                </div>

                <div class="summary-row">
                    <span class="label">Customer</span>
                    <span class="value" id="summaryCustomer">Not entered</span>
                </div>

                <div class="total-row">
                    <span>Estimated Total</span>
                    <span class="total-price">
                        <span class="currency">৳</span><span id="summaryPrice">500</span>
                    </span>
                </div>

                <p class="hint" style="margin-bottom:16px;">
                    Prices are sample prices and can be changed
                    according to your salon's actual price list.
                </p>

                <button type="button" class="submit-btn"
                        id="reviewBtn">
                    Review My Details
                </button>

                <div class="info-box">
                    <h3>💗 Before Your Visit</h3>
                    <p>
                        • Please provide accurate contact information.<br>
                        • Arrive a little before your selected time.<br>
                        • Contact the salon if you need to change your booking.
                    </p>
                </div>

                <div class="info-box" id="help">
                    <h3>📞 Need Help?</h3>
                    <p>
                        Contact the salon for assistance with services
                        or appointment details.
                    </p>
                </div>

            </div>
        </aside>

    </div>
</main>


<!-- FOOTER -->
<footer>
    <strong>Beauty Salon</strong>
    <br>
    Beauty Begins Here!
    <br>
    Beauty Salon Appointment and Management System
    <br>
    &copy; 2026 Beauty Salon. All rights reserved.
</footer>

<script>
const form = document.getElementById("bookingForm");
const serviceInputs = document.querySelectorAll('input[name="service"]');
const serviceCards = document.querySelectorAll(".service-option");

const dateInput = document.getElementById("date");
const timeInput = document.getElementById("time");
const nameInput = document.getElementById("customerName");

const message = document.getElementById("formMessage");

const summaryService = document.getElementById("summaryService");
const summaryDuration = document.getElementById("summaryDuration");
const summaryDate = document.getElementById("summaryDate");
const summaryTime = document.getElementById("summaryTime");
const summaryCustomer = document.getElementById("summaryCustomer");
const summaryPrice = document.getElementById("summaryPrice");


/* Set today's date as the earliest selectable date */
function localDateString(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
}

dateInput.min = localDateString(new Date());


/* Update selected service */
function updateService() {
    serviceInputs.forEach((input, index) => {
        const card = serviceCards[index];

        if (card) {
            card.classList.toggle("selected", input.checked);
        }

        if (input.checked) {
            summaryService.textContent =
                input.closest(".service-option").querySelector("h3").textContent.trim();

            summaryDuration.textContent = input.dataset.duration;

            summaryPrice.textContent =
                Number(input.dataset.price).toLocaleString("en-US");
        }
    });
}


/* Update booking summary */
function updateSummary() {
    summaryDate.textContent = dateInput.value || "Not selected";

    summaryTime.textContent =
        timeInput.options[timeInput.selectedIndex]?.text || "Not selected";

    summaryCustomer.textContent =
        nameInput.value.trim() || "Not entered";
}


/* Event listeners */
serviceInputs.forEach(input => {
    input.addEventListener("change", updateService);
});

dateInput.addEventListener("change", updateSummary);
timeInput.addEventListener("change", updateSummary);
nameInput.addEventListener("input", updateSummary);


/* Review button */
document.getElementById("reviewBtn").addEventListener("click", function() {

    updateService();
    updateSummary();

    document.querySelector(".summary-card").scrollIntoView({
        behavior: "smooth",
        block: "start"
    });

    message.className = "message show success";
    message.textContent =
        "Review your service, date, time and customer information before confirming.";
});


/* Form validation */
form.addEventListener("submit", function(event) {

    if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
        return;
    }

    if (dateInput.value < localDateString(new Date())) {
        event.preventDefault();

        message.className = "message show error";
        message.textContent =
            "Please choose today or a future date.";

        dateInput.focus();
        return;
    }

});


/* Initial update */
updateService();
updateSummary();

</script>

</body>
</html>
