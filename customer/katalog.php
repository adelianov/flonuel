<?php

session_start();

require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$nama = $_SESSION['nama'] ?? 'Customer';

$stmt = mysqli_prepare(
    $conn,
    "SELECT User_id, Nama, No_Hp, Email, Username
     FROM `user`
     WHERE User_id = ?
     LIMIT 1"
);

if ($stmt) {

    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);

    $result_user = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result_user);

    mysqli_stmt_close($stmt);

    if ($user) {
        $nama = $user['Nama'];
    }
}

$kategori_id = isset($_GET['kategori'])
    ? (int) $_GET['kategori']
    : 0;

$categories = [];

$sql_kategori = "
    SELECT
        Kategori_id,
        Nama_kategori
    FROM KATEGORI_PRODUK
    ORDER BY Nama_kategori ASC
";

$result_kategori = mysqli_query($conn, $sql_kategori);

if ($result_kategori) {

    while ($row = mysqli_fetch_assoc($result_kategori)) {
        $categories[] = $row;
    }
}

$products = [];


if ($kategori_id > 0) {

    $sql = "
        SELECT
            p.Produk_id,
            p.Kategori_id,
            p.Nama_produk,
            p.Harga,
            p.Jumlah_bunga,
            p.Foto_produk,
            p.Deskripsi_produk,
            p.Status,
            k.Nama_kategori
        FROM PRODUK p
        LEFT JOIN KATEGORI_PRODUK k
            ON p.Kategori_id = k.Kategori_id
        WHERE p.Status = 'aktif'
        AND p.Kategori_id = ?
        ORDER BY p.Produk_id DESC
    ";

    $stmt_produk = mysqli_prepare($conn, $sql);

    if ($stmt_produk) {

        mysqli_stmt_bind_param(
            $stmt_produk,
            "i",
            $kategori_id
        );

        mysqli_stmt_execute($stmt_produk);

        $product_result = mysqli_stmt_get_result($stmt_produk);

        while ($row = mysqli_fetch_assoc($product_result)) {
            $products[] = $row;
        }

        mysqli_stmt_close($stmt_produk);
    }

} else {

    $sql = "
        SELECT
            p.Produk_id,
            p.Kategori_id,
            p.Nama_produk,
            p.Harga,
            p.Jumlah_bunga,
            p.Foto_produk,
            p.Deskripsi_produk,
            p.Status,
            k.Nama_kategori
        FROM PRODUK p
        LEFT JOIN KATEGORI_PRODUK k
            ON p.Kategori_id = k.Kategori_id
        WHERE p.Status = 'aktif'
        ORDER BY p.Produk_id DESC
    ";

    $stmt_produk = mysqli_prepare($conn, $sql);

    if ($stmt_produk) {

        mysqli_stmt_execute($stmt_produk);

        $product_result = mysqli_stmt_get_result($stmt_produk);

        while ($row = mysqli_fetch_assoc($product_result)) {
            $products[] = $row;
        }

        mysqli_stmt_close($stmt_produk);
    }
}

$kategori_aktif = 'Semua Produk';

if ($kategori_id > 0) {

    foreach ($categories as $category) {

        if ((int) $category['Kategori_id'] === $kategori_id) {

            $kategori_aktif = $category['Nama_kategori'];

            break;
        }
    }
}

function formatRupiah($nominal)
{
    return 'Rp ' . number_format(
        (float) $nominal,
        0,
        ',',
        '.'
    );
}

function fotoProduk($foto)
{
    $foto = trim($foto ?? '');

    if ($foto === '') {
        return null;
    }

    $server_path = __DIR__ . '/../uploads/produk/' . $foto;

    if (file_exists($server_path)) {
        return '../uploads/produk/' . $foto;
    }

    $server_path_2 = __DIR__ . '/../assets/img/produk/' . $foto;

    if (file_exists($server_path_2)) {
        return '../assets/img/produk/' . $foto;
    }


    return null;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Katalog</title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        :root {

            --pink: #e77d88;
            --pink-dark: #d86673;
            --pink-light: #fff1f3;
            --dark: #292525;
            --gray: #777070;

        }


        * {

            box-sizing: border-box;

        }


        body {

            margin: 0;

            background: #fffafb;

            color: var(--dark);

            font-family: Arial, Helvetica, sans-serif;

        }
        .dashboard-sidebar {

            width: 250px;

            min-height: 100vh;

            position: fixed;

            left: 0;

            top: 0;

            z-index: 1040;

            background: #fff;

            border-right: 1px solid #f1e5e6;

        }


        .brand {

            height: 85px;

            display: flex;

            align-items: center;

            padding: 0 28px;

            color: var(--pink-dark);

            font-family: Georgia, "Times New Roman", serif;

            font-size: 25px;

            font-weight: bold;

            text-decoration: none;

        }


        .brand:hover {

            color: var(--pink-dark);

        }


        .sidebar-menu {

            padding: 10px 16px;

        }


        .sidebar-label {

            padding: 12px 14px 8px;

            color: #aaa;

            font-size: 9px;

            font-weight: 700;

            letter-spacing: 1.5px;

        }


        .sidebar-menu a {

            display: flex;

            align-items: center;

            gap: 12px;

            margin: 4px 0;

            padding: 12px 14px;

            color: #777;

            border-radius: 9px;

            text-decoration: none;

            font-size: 12px;

            transition: .2s;

        }


        .sidebar-menu a:hover,
        .sidebar-menu a.active {

            color: var(--pink-dark);

            background: var(--pink-light);

        }


        .sidebar-menu a i {

            width: 18px;

            text-align: center;

        }


        .sidebar-bottom {

            position: absolute;

            bottom: 22px;

            left: 16px;

            right: 16px;

        }

        .main-content {

            margin-left: 250px;

            min-height: 100vh;

        }

        .topbar {

            height: 85px;

            padding: 0 42px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            background: #fff;

            border-bottom: 1px solid #f3e9ea;

        }


        .topbar-title {

            font-family: Georgia, "Times New Roman", serif;

            font-size: 25px;

            color: var(--dark);

        }


        .profile-mini {

            display: flex;

            align-items: center;

            gap: 11px;

        }


        .profile-avatar {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: var(--pink-light);

            color: var(--pink-dark);

            font-size: 13px;

            font-weight: bold;

        }


        .profile-name {

            color: var(--dark);

            font-size: 11px;

            font-weight: 700;

        }


        .profile-role {

            color: #aaa;

            font-size: 9px;

        }

        .content {

            padding: 38px 42px;

        }

        .catalog-header {

            margin-bottom: 30px;

        }


        .catalog-title {

            margin: 0 0 7px;

            font-family: Georgia, "Times New Roman", serif;

            font-size: 27px;

            color: var(--dark);

        }


        .catalog-subtitle {

            margin: 0;

            color: #999;

            font-size: 11px;

            line-height: 1.6;

        }

        .section-title {

            margin: 0 0 17px;

            display: flex;

            align-items: center;

            justify-content: space-between;

        }


        .section-title h2 {

            margin: 0;

            font-family: Georgia, "Times New Roman", serif;

            font-size: 21px;

        }


        .section-count {

            color: #aaa;

            font-size: 9px;

        }


        .product-grid {

            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 15px;

        }

        .product-card {

            height: 100%;

            overflow: hidden;

            background: white;

            border: 1px solid #f2e7e8;

            border-radius: 13px;

            transition:
                transform .2s ease,
                box-shadow .2s ease;

        }


        .product-card:hover {

            transform: translateY(-3px);

            box-shadow:
                0 8px 22px rgba(80, 40, 40, .07);

        }

        .product-image {

            width: 100%;

            height: 190px;

            object-fit: cover;

            background: #f8e9ea;

            display: block;

            transition: transform .3s ease;

        }


        .product-card:hover .product-image {

            transform: scale(1.02);

        }


        .product-no-image {

            width: 100%;

            height: 190px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #cfa9ad;

            background: #fdf1f2;

            font-size: 35px;

        }

        .product-body {

            padding: 16px;

        }


        .product-body h3 {

            margin: 0 0 6px;

            color: var(--dark);

            font-family: Georgia, "Times New Roman", serif;

            font-size: 17px;

            line-height: 1.35;

            min-height: 46px;

        }


        .product-price {

            color: var(--pink-dark);

            font-size: 11px;

            font-weight: bold;

        }


        .product-category {

            display: inline-block;

            margin-top: 8px;

            padding: 4px 7px;

            color: var(--pink-dark);

            background: var(--pink-light);

            border-radius: 5px;

            font-size: 8px;

            font-weight: bold;

        }


        .product-description {

            margin: 10px 0 14px;

            color: #999;

            font-size: 9px;

            line-height: 1.6;

            min-height: 30px;

        }


        .product-footer {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 8px;

        }


        .flower-count {

            color: #aaa;

            font-size: 8px;

        }


        .flower-count i {

            margin-right: 3px;

            color: var(--pink);

        }

        .btn-view {

            display: inline-block;

            padding: 8px 12px;

            color: var(--pink-dark);

            background: var(--pink-light);

            border-radius: 6px;

            font-size: 9px;

            font-weight: bold;

            text-decoration: none;

            border: 0;

            transition: .2s;

        }


        .btn-view:hover {

            color: white;

            background: var(--pink);

        }

        .empty-box {

            padding: 65px 20px;

            text-align: center;

            background: white;

            border: 1px solid #f2e7e8;

            border-radius: 13px;

        }


        .empty-icon {

            width: 55px;

            height: 55px;

            margin: 0 auto 15px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            color: var(--pink-dark);

            background: var(--pink-light);

            font-size: 21px;

        }


        .empty-title {

            margin-bottom: 6px;

            color: var(--dark);

            font-family: Georgia, "Times New Roman", serif;

            font-size: 19px;

        }


        .empty-text {

            margin: 0;

            color: #999;

            font-size: 10px;

        }

        .mobile-menu-btn {

            display: none;

            margin-right: 13px;

            padding: 0;

            border: 0;

            background: transparent;

            color: var(--dark);

            font-size: 19px;

        }


        .sidebar-overlay {

            display: none;

        }

        @media (max-width: 1100px) {

            .product-grid {

                grid-template-columns:
                    repeat(3, minmax(0, 1fr));

            }

        }


        @media (max-width: 991px) {

            .dashboard-sidebar {

                transform: translateX(-100%);

                transition: .25s;

            }


            .dashboard-sidebar.show {

                transform: translateX(0);

            }


            .main-content {

                margin-left: 0;

            }


            .mobile-menu-btn {

                display: inline-block;

            }


            .sidebar-overlay.show {

                display: block;

                position: fixed;

                inset: 0;

                z-index: 1030;

                background: rgba(0, 0, 0, .25);

            }


            .topbar {

                padding: 0 22px;

            }


            .content {

                padding: 28px 22px;

            }


            .product-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

            }

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 575px) {

            .topbar {

                height: 70px;

            }


            .topbar-title {

                font-size: 20px;

            }


            .profile-name,
            .profile-role {

                display: none;

            }


            .content {

                padding: 20px 15px;

            }


            .catalog-title {

                font-size: 24px;

            }


            .product-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

                gap: 10px;

            }


            .product-image,
            .product-no-image {

                height: 155px;

            }


            .product-body {

                padding: 12px;

            }


            .product-body h3 {

                font-size: 13px;

                min-height: 36px;

            }


            .product-description {

                font-size: 8px;

                min-height: 28px;

            }


            .product-footer {

                align-items: stretch;

                flex-direction: column;

            }


            .flower-count {

                font-size: 8px;

            }


            .btn-view {

                width: 100%;

                text-align: center;

            }

        }


        @media (max-width: 380px) {

            .product-grid {

                gap: 8px;

            }


            .product-image,
            .product-no-image {

                height: 130px;

            }


            .product-body {

                padding: 10px;

            }


            .product-body h3 {

                font-size: 12px;

            }

        }

    </style>

</head>


<body>

<aside
    class="dashboard-sidebar"
    id="sidebar"
>

<a
    href="dashboard.php"
    class="brand"
    style="
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        box-sizing: border-box;
        text-decoration: none;
        padding: 15px 10px;
        margin: 0;
    "
>
    <img
        src="/bunga/assets/img/logo%20flonuel.png?v=2"
        alt="Logo FlowerShop"
        style="
            display: block;
            width: 100%;
            max-width: 180px;
            height: 110px;
            object-fit: contain;
            object-position: center;
            flex-shrink: 0;
            margin: 0 auto;
        "
    >
</a>

<nav class="sidebar-menu">

    <div class="sidebar-label">
        MENU 
    </div>

    <a href="dashboard.php">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="pesanan/index.php">
        <i class="fa-solid fa-bag-shopping"></i>
        <span>Pesanan Saya</span>
    </a>

    <a href="../customer/katalog.php" class="active">
        <i class="fa-solid fa-book-open"></i>
        <span>Katalog</span>
    </a>

    <a href="pembayaran/index.php">
        <i class="fa-solid fa-credit-card"></i>
        <span>Pembayaran</span>
    </a>

    <a
        href="../logout.php"
        onclick="return confirm('Apakah Anda yakin ingin logout?')"
    >
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>

</nav>

</aside>




    </div>

</aside>


<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>



<main class="main-content">


    <!-- TOPBAR -->

    <header class="topbar">

        <div
            style="
                display:flex;
                align-items:center;
            "
        >

            <button
                class="mobile-menu-btn"
                id="menuButton"
                type="button"
                aria-label="Buka menu"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <div class="topbar-title">
                Katalog 
            </div>

        </div>


        <div class="profile-mini">

            <div>

                <div class="profile-name">

                    <?= htmlspecialchars($nama) ?>

                </div>


                <div class="profile-role">

                    Customer

                </div>

            </div>


            <div class="profile-avatar">

                <?= strtoupper(
                    substr($nama, 0, 1)
                ) ?>

            </div>

        </div>

    </header>

    <section class="content">


        <!-- HEADER -->

        <div class="catalog-header">

            <h1 class="catalog-title">

                Katalog buket

            </h1>


            <p class="catalog-subtitle">

                Temukan berbagai pilihan bunga terbaik
                untuk setiap momen spesial Anda.

            </p>

        </div>


        <!-- PRODUCT TITLE -->

        <div class="section-title">

            <h2>
                Produk Buket
            </h2>


            <div class="section-count">

                <?= count($products) ?> produk

            </div>

        </div>

        <?php if (!empty($products)): ?>

            <div class="product-grid">

                <?php foreach ($products as $product): ?>


                    <?php

                    $foto = fotoProduk(
                        $product['Foto_produk']
                    );


                    $deskripsi = trim(
                        $product['Deskripsi_produk'] ?? ''
                    );


                    if ($deskripsi === '') {

                        $deskripsi =
                            'Produk bunga pilihan Flounel.';

                    } else {

                        $deskripsi = mb_strimwidth(
                            $deskripsi,
                            0,
                            75,
                            '...'
                        );

                    }

                    ?>


                    <div class="product-card">


                        <!-- FOTO -->

                        <?php if ($foto): ?>

                            <img
                                src="<?= htmlspecialchars($foto) ?>"
                                class="product-image"
                                alt="<?= htmlspecialchars(
                                    $product['Nama_produk']
                                ) ?>"
                                onerror="
                                    this.style.display='none';
                                    this.nextElementSibling.style.display='flex';
                                "
                            >


                            <div
                                class="product-no-image"
                                style="display:none;"
                            >

                                <i class="fa-solid fa-spa"></i>

                            </div>


                        <?php else: ?>

                            <div class="product-no-image">

                                <i class="fa-solid fa-spa"></i>

                            </div>

                        <?php endif; ?>


                        <!-- BODY -->

                        <div class="product-body">


                            <h3>

                                <?= htmlspecialchars(
                                    $product['Nama_produk']
                                ) ?>

                            </h3>


                            <div class="product-price">

                                <?= formatRupiah(
                                    $product['Harga']
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $product['Nama_kategori']
                                )
                            ): ?>

                                <div class="product-category">

                                    <?= htmlspecialchars(
                                        $product['Nama_kategori']
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <div class="product-description">

                                <?= htmlspecialchars(
                                    $deskripsi
                                ) ?>

                            </div>


                            <div class="product-footer">


                                <?php if (
                                    !empty(
                                        $product['Jumlah_bunga']
                                    )
                                ): ?>

                                    <div class="flower-count">

                                        <i class="fa-solid fa-spa"></i>

                                        <?= htmlspecialchars(
                                            $product['Jumlah_bunga']
                                        ) ?>

                                        bunga

                                    </div>

                                <?php else: ?>

                                    <div></div>

                                <?php endif; ?>


                                <a
                                    href="pesanan/buat.php?produk_id=<?= (int) $product['Produk_id'] ?>"
                                    class="btn-view"
                                >

                                    <i class="fa-solid fa-cart-plus"></i>

                                    Pesan

                                </a>


                            </div>


                        </div>


                    </div>


                <?php endforeach; ?>

            </div>


        <?php else: ?>


            <div class="empty-box">

                <div class="empty-icon">

                    <i class="fa-solid fa-flower-tulip"></i>

                </div>


                <div class="empty-title">

                    Belum Ada Produk

                </div>


                <p class="empty-text">

                    Saat ini belum ada produk bunga
                    yang tersedia di katalog.

                </p>

            </div>


        <?php endif; ?>


    </section>


</main>

<script>
const mobileMenu = document.getElementById('mobileMenu');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (mobileMenu && sidebar && sidebarOverlay) {
    mobileMenu.addEventListener('click', function () {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
    });
}

if (sidebarOverlay && sidebar) {
    sidebarOverlay.addEventListener('click', function () {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    });
}

/* Tutup sidebar ketika menu diklik di mobile */
const sidebarLinks = document.querySelectorAll('.sidebar-menu a');

sidebarLinks.forEach(function (link) {
    link.addEventListener('click', function () {
        if (window.innerWidth <= 991 && sidebar && sidebarOverlay) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
    });
});
window.addEventListener('resize', function () {
    if (window.innerWidth > 991 && sidebar && sidebarOverlay) {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    }
});

</script>


</body>

</html>