

<?php
session_start();
include 'db.php';

$sql = "SELECT * FROM services WHERE status = 'Active'";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Our Services | Beauty Salon</title>

<style>
:root {
    --rose: #a64d70;
    --dark: #633448;
    --light: #fff8fa;
    --pink: #f8e5ed;
    --text: #49313d;
    --muted: #8d7882;
    --white: #fff;
    --border: #f0dfe7;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

html {
    scroll-behavior: smooth;
}

body {
    background: var(--light);
    color: var(--text);
}

a {
    text-decoration: none;
    color: inherit;
}

button, input, select {
    font: inherit;
}

button {
    cursor: pointer;
}

/* NAVBAR */

header {
    background: white;
    padding: 13px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
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
    width: 50px;
    height: 50px;
    object-fit: contain;
}

.logo h2 {
    color: var(--rose);
    font-size: 20px;
}

.logo small {
    display: block;
    color: var(--muted);
    font-size: 10px;
    margin-top: 4px;
}

nav {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 23px;
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
    padding: 11px 16px;
    border-radius: 8px;
}

nav .nav-btn:hover {
    background: var(--dark);
    color: white;
}

/* HERO */

.hero {
    min-height: 310px;
    padding: 65px 20px;
    text-align: center;
    background:
        radial-gradient(circle at 15% 30%, #ffffffaa, transparent 25%),
        linear-gradient(120deg, #f4d8e4, #fff1f5, #f8e2d8);
}

.eyebrow {
    color: var(--rose);
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 14px;
}

.hero h1 {
    color: var(--dark);
    font-size: clamp(32px, 4vw, 46px);
    margin-bottom: 17px;
}

.hero p {
    max-width: 650px;
    margin: auto;
    color: #765565;
    font-size: 14px;
    line-height: 1.9;
}

/* INTRO */

.container {
    max-width: 1200px;
    margin: auto;
    padding: 0 25px;
}

.intro {
    text-align: center;
    padding-top: 53px;
}

.intro h2 {
    color: var(--dark);
    font-size: 28px;
    margin-bottom: 13px;
}

.intro p {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.8;
}

/* SEARCH */

.toolbar {
    margin: 30px 0 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    flex: 1;
    max-width: 390px;
}

.search-box span {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 17px;
}

.search-box input {
    width: 100%;
    padding: 13px 14px 13px 42px;
    border: 1px solid var(--border);
    background: white;
    border-radius: 9px;
    outline: none;
    font-size: 13px;
    color: var(--text);
}

.search-box input:focus {
    border-color: var(--rose);
    box-shadow: 0 0 0 3px #a64d7012;
}

.results-count {
    color: var(--muted);
    font-size: 12px;
}

/* FILTERS */

.filters {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 32px;
}

.filter-btn {
    border: 1px solid #ead6e0;
    background: white;
    color: #765565;
    border-radius: 25px;
    padding: 10px 19px;
    font-size: 12px;
    transition: 0.2s;
}

.filter-btn:hover,
.filter-btn.active {
    background: var(--rose);
    color: white;
    border-color: var(--rose);
}

/* SERVICE GRID */

.service-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 25px;
    padding-bottom: 65px;
}

.service-card {
    min-width: 0;
    overflow: hidden;
    border-radius: 15px;
    background: white;
    border: 1px solid var(--border);
    box-shadow: 0 5px 20px #63344808;
    transition: transform 0.25s, box-shadow 0.25s;
    display: flex;
    flex-direction: column;
}

.service-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px #63344815;
}

.service-image {
    height: 220px;
    position: relative;
    overflow: hidden;
    background: var(--pink);
}

.service-image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s;
}

.service-card:hover .service-image img {
    transform: scale(1.05);
}

.service-badge {
    position: absolute;
    top: 13px;
    left: 13px;
    background: white;
    color: var(--rose);
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
    box-shadow: 0 2px 8px #0000000c;
}

.service-content {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.service-category {
    color: var(--rose);
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 1px;
    font-weight: bold;
    margin-bottom: 9px;
}

.service-content h3 {
    color: var(--dark);
    font-size: 19px;
    margin-bottom: 11px;
}

.service-description {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.8;
    min-height: 43px;
    margin-bottom: 18px;
}

.service-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 13px 0;
    border-top: 1px solid #f3e7ed;
    border-bottom: 1px solid #f3e7ed;
    margin-top: auto;
}

.meta-item {
    font-size: 11px;
    color: var(--muted);
}

.service-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding-top: 17px;
}

.price-label {
    color: var(--muted);
    font-size: 10px;
    margin-bottom: 5px;
}

.service-price {
    color: var(--rose);
    font-size: 23px;
    font-weight: bold;
}

.book-btn {
    border: none;
    border-radius: 8px;
    padding: 12px 14px;
    background: var(--rose);
    color: white;
    font-size: 12px;
    font-weight: bold;
    transition: 0.2s;
}

.book-btn:hover {
    background: var(--dark);
}

/* NO RESULTS */

.no-results {
    display: none;
    text-align: center;
    padding: 45px 15px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 13px;
    margin-bottom: 60px;
}

.no-results h3 {
    color: var(--dark);
    margin-bottom: 10px;
}

.no-results p {
    color: var(--muted);
    font-size: 13px;
}

/* CALL TO ACTION */

.cta {
    margin-bottom: 65px;
    padding: 38px 25px;
    text-align: center;
    background: linear-gradient(120deg, #f4dce6, #fff0f5);
    border: 1px solid #f0d8e3;
    border-radius: 17px;
}

.cta h2 {
    color: var(--dark);
    font-size: 25px;
    margin-bottom: 12px;
}

.cta p {
    color: #765565;
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 20px;
}

.cta-btn {
    display: inline-block;
    padding: 13px 22px;
    background: var(--rose);
    color: white;
    border-radius: 8px;
    font-size: 13px;
    font-weight: bold;
}

.cta-btn:hover {
    background: var(--dark);
}

/* FOOTER */

footer {
    background: var(--dark);
    color: #f8e6ee;
    padding: 33px 20px;
    text-align: center;
    font-size: 12px;
    line-height: 2;
}

footer strong {
    font-size: 18px;
}

/* RESPONSIVE */

@media (max-width: 950px) {
    .service-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
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

@media (max-width: 580px) {
    header {
        padding: 13px 15px;
    }

    nav {
        gap: 12px;
    }

    nav a {
        font-size: 12px;
    }

    .hero {
        padding: 48px 18px;
    }

    .container {
        padding: 0 14px;
    }

    .service-grid {
        grid-template-columns: 1fr;
    }

    .service-image {
        height: 230px;
    }

    .intro h2 {
        font-size: 24px;
    }

    .toolbar {
        align-items: stretch;
    }

    .search-box {
        max-width: none;
    }

    .results-count {
        text-align: center;
    }
}
</style>
</head>

<body>

<!-- NAVIGATION BAR -->
<header>
    <a href="index.html" class="logo">
        <!-- Replace with your own logo filename -->
        <img src="salon-logo.png/LOGO.png" alt="Beauty Salon Logo">

        <div>
            <h2>Beauty Salon</h2>
            <small>Beauty Begins Here!</small>
        </div>
    </a>

    <nav>
        <a href="index.html">Home</a>
        <a href="services.php" class="active">Services</a>
        <a href="appointment.php">Book Appointment</a>
        <a href="my-appointments.php">My Appointments</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="logout.php" class="nav-btn">Logout</a>
        <?php else: ?>
            <a href="login.php" class="nav-btn">Login</a>
        <?php endif; ?>
    </nav>
</header>


<!-- HERO SECTION -->
<section class="hero">
    <div class="eyebrow">BEAUTY • CARE • CONFIDENCE</div>

    <h1>Discover Our Beauty Services</h1>

    <p>
        Every beauty journey is unique. Explore our salon services,
        find the treatment you love and book your next beauty session
        with ease.
    </p>
</section>


<main class="container">

    <!-- INTRODUCTION -->
    <section class="intro">
        <div class="eyebrow">OUR SERVICES</div>

        <h2>A Little Care, A Lot of Confidence</h2>

        <p>
            From everyday hair care to special occasion makeup,
            find the right service for your beauty needs.
        </p>
    </section>


    <!-- SEARCH AND SERVICE COUNT -->
    <section class="toolbar">

        <div class="search-box">
            <span>⌕</span>
            <input
                type="search"
                id="serviceSearch"
                placeholder="Search haircut, facial, makeup..."
                aria-label="Search services">
        </div>

        <div class="results-count" id="resultsCount">
            Showing 6 services
        </div>

    </section>


    <!-- CATEGORY FILTERS -->
    <section class="filters" aria-label="Filter services">

        <button type="button" class="filter-btn active"
                data-category="All">All Services</button>

        <button type="button" class="filter-btn"
                data-category="Hair">Hair Care</button>

        <button type="button" class="filter-btn"
                data-category="Skin">Skin Care</button>

        <button type="button" class="filter-btn"
                data-category="Makeup">Makeup</button>

        <button type="button" class="filter-btn"
                data-category="Nails">Nail Care</button>

    </section>


    <!-- SERVICE CARDS
         JavaScript currently loads demo service data.
         Later, PHP/MySQL can provide the same fields. -->
    <section class="service-grid" id="serviceGrid"
         aria-label="Available salon services">

<?php
$categoryMap = [
    'Haircut' => 'Hair',
    'Hair Coloring' => 'Hair',
    'Facial' => 'Skin',
    'Makeup' => 'Makeup',
    'Bridal Makeup' => 'Makeup',
    'Manicure' => 'Nails',
    'Pedicure' => 'Nails'
];

$imageMap = [
    'Haircut' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=700&q=80',
    'Hair Coloring' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=700&q=80',
    'Facial' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=700&q=80',
    'Makeup' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=700&q=80',
    'Bridal Makeup' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=700&q=80',
    'Manicure' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?auto=format&fit=crop&w=700&q=80',
    'Pedicure' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?auto=format&fit=crop&w=700&q=80'
];

if (mysqli_num_rows($result) > 0):

    while ($service = mysqli_fetch_assoc($result)):

        $name = $service['service_name'];
        $category = $categoryMap[$name] ?? 'Other';
        $image = $imageMap[$name] ?? '';
?>

    <article class="service-card"
             data-service-id="<?php echo $service['id']; ?>"
             data-category="<?php echo htmlspecialchars($category); ?>">

        <div class="service-image">
            <img src="<?php echo htmlspecialchars($image); ?>"
                 alt="<?php echo htmlspecialchars($name); ?>"
                 loading="lazy">

            <span class="service-badge">Available</span>
        </div>

        <div class="service-content">

            <div class="service-category">
                <?php echo htmlspecialchars($category); ?> Care
            </div>

            <h3><?php echo htmlspecialchars($name); ?></h3>

            <p class="service-description">
                <?php echo htmlspecialchars($service['description']); ?>
            </p>

            <div class="service-meta">
                <span class="meta-item">
                    ◷ <?php echo (int)$service['duration']; ?> minutes
                </span>
                <span class="meta-item">♡ Professional Care</span>
            </div>

            <div class="service-bottom">

                <div>
                    <div class="price-label">Starting from</div>

                    <div class="service-price">
                        ৳<?php echo number_format((float)$service['price'], 0); ?>
                    </div>
                </div>

                <button type="button"
                        class="book-btn"
                        data-book-service="<?php echo $service['id']; ?>">
                    Book Now &rarr;
                </button>

            </div>
        </div>
    </article>

<?php
    endwhile;

else:
?>

    <p>No services available right now.</p>

<?php endif; ?>

</section>


    <section class="no-results" id="noResults">
        <h3>No Services Found</h3>
        <p>Try a different service name or category.</p>
    </section>


    <!-- CALL TO ACTION -->
    <section class="cta">
        <div class="eyebrow">YOUR NEXT BEAUTY MOMENT</div>

        <h2>Ready for Some Self-Care?</h2>

        <p>
            Choose your favourite salon service and schedule
            a time that works for you.
        </p>

        <a href="appointment.php" class="cta-btn">
            Book Your Appointment &rarr;
        </a>
    </section>

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

// Get all service cards from PHP
const cards = document.querySelectorAll(".service-card");

const searchInput = document.getElementById("serviceSearch");
const resultsCount = document.getElementById("resultsCount");
const noResults = document.getElementById("noResults");
const filterButtons = document.querySelectorAll(".filter-btn");

let activeCategory = "All";

// Search and filter services
function filterServices() {

    const searchTerm = searchInput.value.trim().toLowerCase();

    let visibleCount = 0;

    cards.forEach(card => {

        const name = card.querySelector("h3").textContent.toLowerCase();

        const description = card.querySelector(".service-description").textContent.toLowerCase();

        const category = card.dataset.category.toLowerCase();

        const matchesSearch =
            name.includes(searchTerm) ||
            description.includes(searchTerm) ||
            category.includes(searchTerm);

        const matchesCategory =
            activeCategory === "All" ||
            card.dataset.category === activeCategory;

        if (matchesSearch && matchesCategory) {
            card.style.display = "flex";
            visibleCount++;
        } else {
            card.style.display = "none";
        }

    });

    resultsCount.textContent =
        `Showing ${visibleCount} service${visibleCount === 1 ? "" : "s"}`;

    noResults.style.display =
        visibleCount === 0 ? "block" : "none";
}

// Search event
searchInput.addEventListener("input", filterServices);

// Category filter event
filterButtons.forEach(button => {

    button.addEventListener("click", () => {

        activeCategory = button.dataset.category;

        filterButtons.forEach(item => {
            item.classList.toggle("active", item === button);
        });

        filterServices();

    });

});

// Book Now event
document.getElementById("serviceGrid").addEventListener("click", event => {

    const button = event.target.closest("[data-book-service]");

    if (!button) return;

    const serviceId = button.dataset.bookService;

    window.location.href =
        "appointment.php?service_id=" + encodeURIComponent(serviceId);

});

// Initial display
filterServices();

</script>

</body>
</html>
