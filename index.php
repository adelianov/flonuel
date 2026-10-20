<?php
session_start();

$isLogin = isset($_SESSION['user_id']);
$role    = $_SESSION['role'] ?? '';
$nama    = $_SESSION['nama'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Flonuel</title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

</head>

<body>

<header class="navbar">

<div class="navbar-container">

    <a href="index.php" class="logo" style="display:flex; align-items:center; gap:10px; text-decoration:none; flex-shrink:0;">

        <img
            src="/bunga/assets/img/logo%20flonuel.png?v=2"
            alt="Logo FlowerShop"
            class="logo-image"
            style="display:block; width:px; height:75px; max-width:75px; object-fit:contain; object-position:center;"
        >
        </span>

    </a>

    <nav
        class="nav-menu"
        id="navMenu"
    >

        <a
            href="index.php"
            class="active"
        >
            BERANDA
        </a>

        <a href="#kategori">
    KATEGORI
</a>

        <a href="#tentang">
            TENTANG KAMI
        </a>

        <a href="#kontak">
            KONTAK
        </a>

        <?php if ($isLogin): ?>

            <?php if ($role === 'admin'): ?>

                <a
                    href="admin/dashboard.php"
                    class="login-button"
                >
                    DASHBOARD
                </a>

            <?php else: ?>

                <a
                    href="customer/dashboard.php"
                    class="login-button"
                >
                    DASHBOARD
                </a>

            <?php endif; ?>

        <?php else: ?>

            <a
                href="login.php"
                class="login-button"
            >
                LOGIN
            </a>

        <?php endif; ?>

    </nav>

    <button
        type="button"
        class="menu-toggle"
        id="menuToggle"
        aria-label="Menu"
    >

        <span></span>
        <span></span>
        <span></span>

    </button>

</div>

</header>




<main>

<section class="hero">

    <div class="hero-container">


        <div class="hero-content">

            <span class="hero-label">
                FLOWERS FOR EVERY MOMENT
            </span>


            <h1>

                <span class="black">
                    SO...
                </span>

                <br>

                <span class="black">
                    WHO ARE WE
                </span>

                <br>

                <span class="pink">
                    SENDING FLOWERS
                </span>

                <span class="black">
                    TO?
                </span>

            </h1>


            <p>
                Custom bouquets made for your favorite
                people &amp; moments.
            </p>


            <div class="hero-actions">

                <a
                    href="katalog.php"
                    class="hero-button"
                >
                    CREATE YOUR BOUQUET
                </a>

            </div>

        </div>


        <div class="hero-image">

            <div class="hero-bg"></div>

            <img
                src="assets/img/buket 1.png"
                alt="Flower Bouquet"
            >

        </div>

    </div>

</section>

<section
    class="story-section"
    id="tentang"
>

    <div class="story-container">


        <div class="story-visual">

            <div class="flower-shape flower-shape-one">

                <img
                    src="assets/img/buket 2.png"
                    alt="Flowers"
                >

            </div>

        </div>


        <div class="story-content">

            <h2>

                Every bouquet is made
                to hold a
                <span>
                    special meaning.
                </span>

            </h2>


            <p>
                Flonuel hadir untuk kamu yang ingin
                memberikan sesuatu yang lebih personal.
                Setiap buket dapat disesuaikan dengan
                keinginanmu, mulai dari pilihan bunga,
                warna, hingga wrapping.
            </p>


            <p>
                Kamu juga bisa memberikan referensi
                untuk membantu kami mewujudkan buket
                sesuai dengan bayanganmu.
            </p>


            <p>
                Pilih buketnya,pilih bunga sesuai yang kamu inginkan,
                dan biarkan Flonuel merangkainya
                menjadi hadiah yang spesial.
            </p>



        </div>

    </div>


    <div class="story-bar">

        <h2>Pick your favorite bouquet,
        place your order,
        and pick it up at our store <h2>

    </div>

</section>

<section class="featured-section">

    <div class="featured-container">


        <div class="featured-title">

            <h2>
                BEST<br>
                LOVED<br>
                BLOOMS
            </h2>

        </div>


        <div class="featured-products">


            <a
                href="katalog.php"
                class="featured-card"
            >

                <div class="featured-image">

                    <img
                        src="assets/img/best buket 1.png"
                        alt="Choco Mood"
                    >

                </div>

                <div class="featured-name">
                <h2>Choco Mood</h2>
                </div>

            </a>



            <a
                href="katalog.php"
                class="featured-card"
            >

                <div class="featured-image">

                    <img
                        src="assets/img/best buket 2.png"
                        alt="Grand Bloom"
                    >

                </div>

                <div class="featured-name">
                    <h2>Grand Bloom</h2>
                </div>

            </a>



            <a
                href="katalog.php"
                class="featured-card"
            >

                <div class="featured-image">

                    <img
                        src="assets/img/best buket 3.png"
                        alt="Fuzzy Blooms"
                    >

                </div>

                <div class="featured-name">
                   <h2>Fuzzy Blooms</h2>
                </div>

            </a>

        </div>

    </div>

</section>

<section
    class="category-section"
    id="kategori"
>

    <div class="category-container">


        <div class="category-heading">
            <h2>
                OUR KATEGORI
            </h2>

        </div>


        <div class="category-grid">
            <a
                href="katalog.php"
                class="category-card"
            >

                <div class="category-image">

                    <img
                        src="assets/img/kategori 1.jpe.jpeg"
                        alt="Handmade Flowers"
                    >

                </div>

                <h2>
                    HANDMADE
                    <br>
                    FLOWERS
                </h2>

            </a>



            <a
                href="katalog.php"
                class="category-card"
            >

                <div class="category-image">

                    <img
                        src="assets/img/kategori 2.png"
                        alt="Money Bouquet"
                    >

                </div>

                <h2>
                    MONEY
                    <br>
                    BOUQUET
                </h2>

            </a>



            <a
                href="katalog.php"
                class="category-card"
            >

                <div class="category-image">

                    <img
                        src="assets/img/kategori 3.jpe.jpeg"
                        alt="Artificial Bouquet"
                    >

                </div>

                <h2>
                    ARTIFICIAL
                    <br>
                    BOUQUET
                </h2>

            </a>



            <a
                href="katalog.php"
                class="category-card"
            >

                <div class="category-image">

                    <img
                        src="assets/img/kategori 4.png"
                        alt="Snack Bouquet"
                    >

                </div>

                <h2>
                    SNACK
                    <br>
                    BOUQUET
                </h2>

            </a>

        </div>

    </div>

</section>

<section class="collection-section">

    <div class="collection-container">

        <!-- KOLOM KIRI -->
        <div class="collection-column">

            <!-- FUZZY BOUQUET -->
            <div class="collection-group">

                <div class="collection-heading">
                    <h2>FUZZY BOUQUET</h2>
                    <p>(bunga handmade dari pipe cleaner)</p>
                </div>

                <div class="collection-products">

                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/fuzzy petite.jpe.jpeg"
                                alt="Fuzzy Petite"
                            >
                        </div>

                        <h3>Fuzzy Petite</h3>

                        <span>Rp. 25.000</span>

                    </a>


                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/fuzzy grand.jpg.jpeg"
                                alt="Fuzzy Grand"
                            >
                        </div>

                        <h3>Fuzzy Grand</h3>

                        <span>Rp. 150.000</span>

                    </a>


                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/fuzzy blooms.png"
                                alt="Fuzzy Blooms"
                            >
                        </div>

                        <h3>Fuzzy Blooms</h3>

                        <span>Rp. 70.000</span>

                    </a>

                </div>

            </div>


            <!-- GRADUATION BOUQUET -->
            <div class="collection-group">

                <div class="collection-heading">
                    <h2>GRADUATION BOUQUET</h2>
                </div>

                <div class="collection-products">

                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/gd1.png"
                                alt="Little Graduation"
                            >
                        </div>

                        <h3>Little Graduation</h3>

                        <span>Rp. 50.000</span>

                    </a>


                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/gd2.png"
                                alt="Grand Graduation"
                            >
                        </div>

                        <h3>Grand Graduation</h3>

                        <span>Rp. 200.000</span>

                    </a>


                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/gd3.png"
                                alt="Blooms Graduation"
                            >
                        </div>

                        <h3>Blooms Graduation</h3>

                        <span>Rp. 150.000</span>

                    </a>

                </div>

            </div>

        </div>


        <!-- KOLOM KANAN -->
        <div class="collection-column">

            <!-- MONEY BOUQUET -->
            <div class="collection-group">

                <div class="collection-heading">
                    <h2>MONEY BOUQUET</h2>

                    <p>
                        (Harga belum termasuk nominal uang)
                    </p>
                </div>

                <div class="collection-products">

                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/lucky blooms.png"
                                alt="Lucky Blooms"
                            >
                        </div>

                        <h3>Lucky Blooms</h3>

                        <span>Rp. 70.000</span>

                    </a>


                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/lucky grand.png"
                                alt="Lucky Grand"
                            >
                        </div>

                        <h3>Lucky Grand</h3>

                        <span>Rp. 150.000</span>

                    </a>


                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/money tower.png"
                                alt="Money Tower"
                            >
                        </div>

                        <h3>Money Tower</h3>

                        <span>Rp. 70.000</span>

                    </a>

                </div>

            </div>


            <!-- SNACK BOUQUET -->
            <div class="collection-group">

                <div class="collection-heading">
                    <h2>SNACK BOUQUET</h2>

                    <p>
                        (Harga belum termasuk harga snack)
                    </p>
                </div>

                <div class="collection-products">

                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/kategori 4.png"
                                alt="Chocoblooms Snack"
                            >
                        </div>

                        <h3>Chocoblooms Snack</h3>

                        <span>Rp. 70.000</span>

                    </a>


                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/snack treat.png"
                                alt="Snack Treat Blooms"
                            >
                        </div>

                        <h3>Snack Treat Blooms</h3>

                        <span>Rp. 80.000</span>

                    </a>


                    <a href="katalog.php" class="collection-card">

                        <div class="collection-image">
                            <img
                                src="assets/img/bucket snack.png"
                                alt="Bucket Snack"
                            >
                        </div>

                        <h3>Bucket Snack</h3>

                        <span>Rp. 70.000</span>

                    </a>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="product-section">

<div class="product-container">

    <div class="product-heading">

        <span>
            EVERLASTING BLOOMS
        </span>

        <h2>
            ARTIFICIAL BOUQUET
        </h2>

    </div>

    <div class="product-grid">

        <!-- PRODUK 1 -->
        <div class="product-card">

            <a href="katalog.php" class="product-image">

                <img
                    src="assets/img/korean.png"
                    alt="Korean Round Blooms"
                >

            </a>

            <h3>
                Korean Round Blooms
            </h3>

            <p>
                Rp. 150.000
            </p>

        </div>

        <!-- PRODUK 2 -->
        <div class="product-card">

            <a href="katalog.php" class="product-image">

                <img
                    src="assets/img/pipe grand.jpg"
                    alt="Fleura Loly"
                >

            </a>

            <h3>
                Fleura Loly
            </h3>

            <p>
                Rp. 70.000
            </p>

        </div>

        <!-- PRODUK 3 -->
        <div class="product-card">

            <a href="katalog.php" class="product-image">

                <img
                    src="assets/img/pearl grand.png"
                    alt="Pearl Grand"
                >

            </a>

            <h3>
                Pearl Grand
            </h3>

            <p>
                Rp. 300.000
            </p>

        </div>

    </div>

</div>

</section>




</section>

<section
    class="contact-section"
    id="kontak"
>

    <div class="contact-container">


        <!-- PICKUP -->

        <div class="contact-box pickup-box">

            <h2>
                Find us for pickup
            </h2>


            <div class="contact-item">

                <div class="contact-icon">
                    ●
                </div>

                <div>

                    <p>
                        <strong>
                            Flonuel House
                        </strong>
                    </p>

                    <p>
                        Jl. Margasari No.65,
                        RT.003/RW.10,
                        Bandung, Margasari,
                        Kec. Buahbatu,
                        Kota Bandung,
                        Jawa Barat 40286
                    </p>

                </div>

            </div>


            <div class="contact-item">

                <div class="contact-icon">
                    ●
                </div>

                <div>

                    <p>
                        <strong>
                            Opening Hours
                        </strong>
                    </p>

                    <p>
                        Mon - Fri, 9am - 7pm
                    </p>

                    <p>
                        Sun, 9am - 5pm
                    </p>

                </div>

            </div>

        </div>


        <!-- CONTACT -->

        <div class="contact-box">

            <h2>
                CONTACT US
            </h2>


            <div class="contact-item">

                <div class="contact-icon">
                    ☎
                </div>

                <p>
                    WhatsApp :
                    08212629669
                </p>

            </div>


            <div class="contact-item">

                <div class="contact-icon">
                    ◎
                </div>

                <p>
                    Instagram :
                    @flobdgnuel
                </p>

            </div>


            <div class="contact-item">

                <div class="contact-icon">
                    ✉
                </div>

                <p>
                    Email :
                    flonuelbdg@gmail.com
                </p>

            </div>

        </div>

    </div>

</section>



</main>

<script src="assets/js/script.js"></script>

</body>
</html>
