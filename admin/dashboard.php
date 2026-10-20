<?php
session_start();

require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$nama_admin = 'Admin';

$stmt = mysqli_prepare($conn, "
    SELECT Nama
    FROM user
    WHERE user_id = ?
    LIMIT 1
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $data_admin = mysqli_fetch_assoc($result);

        if (!empty($data_admin['Nama'])) {
            $nama_admin = $data_admin['Nama'];
        }
    }

    mysqli_stmt_close($stmt);
}


$sqlNotifikasi = "
    SELECT COUNT(*) AS total
    FROM pembayaran
    WHERE Status_pembayaran = 'menunggu'
      AND Bukti_pembayaran IS NOT NULL
      AND Bukti_pembayaran <> ''
";

$resultNotifikasi = mysqli_query($conn, $sqlNotifikasi);
$dataNotifikasi = mysqli_fetch_assoc($resultNotifikasi);
$totalPembayaranMenunggu = (int) ($dataNotifikasi['total'] ?? 0);

$total_pesanan = 0;

$query = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM PESANAN
");

if ($query) {
    $data = mysqli_fetch_assoc($query);
    $total_pesanan = (int) $data['total'];
}

$penjualan_selesai = 0;

$query = mysqli_query($conn, "
    SELECT COALESCE(SUM(total_harga), 0) AS total
    FROM PESANAN
    WHERE status_pesanan = 'selesai'
");

if ($query) {
    $data = mysqli_fetch_assoc($query);
    $penjualan_selesai = (float) $data['total'];
}

$total_kategori = 0;

$query = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM KATEGORI_PRODUK
");

if ($query) {
    $data = mysqli_fetch_assoc($query);
    $total_kategori = (int) $data['total'];
}

$total_addon = 0;

$query = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM PESANAN
    WHERE
        (pilihan_bunga IS NOT NULL AND pilihan_bunga <> '')
        OR
        (wrapping IS NOT NULL AND wrapping <> '')
        OR
        (catatan_custom IS NOT NULL AND catatan_custom <> '')
");

if ($query) {
    $data = mysqli_fetch_assoc($query);
    $total_addon = (int) $data['total'];
}

$menunggu_verifikasi = 0;

$query = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM PESANAN
    WHERE status_pesanan = 'menunggu_verifikasi'
");

if ($query) {
    $data = mysqli_fetch_assoc($query);
    $menunggu_verifikasi = (int) $data['total'];
}
function rupiah($angka)
{
    return 'Rp ' . number_format(
        (float) $angka,
        0,
        ',',
        '.'
    );
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

    <title>Dashboard Admin | FlowerShop</title>

    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>
<style>
    :root {
    --pink: #f38c94;
    --pink-dark: #df6973;
    --pink-light: #fff0f1;
    --pink-soft: #fff8f8;

    --green: #75a986;
    --purple: #9b87b4;
    --orange: #dca16b;

    --dark: #242020;
    --gray: #777070;
    --light-gray: #aaa2a3;

    --border: #eee5e5;
    --white: #ffffff;
}


* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #fcfbfb;

    color: var(--dark);

    font-family: Arial, Helvetica, sans-serif;
}

a {
    color: inherit;
    text-decoration: none;
}

.admin-header {
    height: 68px;

    padding: 0 38px;

    background: #ffe9ea;

    border-bottom: 1px solid #f6d8da;

    display: flex;
    align-items: center;

    position: sticky;
    top: 0;

    z-index: 100;
}

.brand {
    margin-right: 55px;
}

.brand a {
    display: flex;
    align-items: center;

    gap: 9px;
}

.brand-logo {
    width: 38px;
    height: 38px;

    border-radius: 50%;

    background: #ffffff;

    color: var(--pink-dark);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 14px;

    box-shadow: 0 2px 6px rgba(0,0,0,.04);
}

.brand-text strong {
    display: block;

    font-family: Georgia, serif;

    font-size: 15px;

    color: #3c3031;
}

.brand-text span {
    display: block;

    margin-top: 2px;

    font-size: 7px;

    color: #a58e90;

    text-transform: uppercase;

    letter-spacing: 1px;
}

.admin-navigation {
    height: 100%;

    display: flex;
    align-items: center;

    gap: 30px;
}

.admin-navigation a {
    height: 100%;

    display: flex;
    align-items: center;

    position: relative;

    color: #4d4445;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: .3px;

    transition: color .2s ease;
}

.admin-navigation a:hover {
    color: var(--pink-dark);
}

.admin-navigation a.active {
    color: var(--pink-dark);

    font-weight: 600;
}

.admin-navigation a.active::after {
    content: "";

    position: absolute;

    left: 0;
    right: 0;
    bottom: 0;

    height: 2px;

    background: var(--pink-dark);
}

.header-user {
    margin-left: auto;

    display: flex;
    align-items: center;

    gap: 8px;
}

.header-avatar {
    width: 31px;
    height: 31px;

    border-radius: 50%;

    background: #ffffff;

    color: var(--pink-dark);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 10px;
}

.header-user-info strong {
    display: block;

    color: #41393a;

    font-size: 8px;
}

.header-user-info span {
    display: block;

    margin-top: 2px;

    color: #a39395;

    font-size: 7px;
}

.logout-button {
    width: 29px;
    height: 29px;

    margin-left: 6px;

    border-radius: 7px;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #776b6c;

    font-size: 10px;

    transition: .2s ease;
}

.logout-button:hover {
    background: #ffffff;

    color: var(--pink-dark);
}

.admin-container {
    width: min(1050px, calc(100% - 70px));

    margin: 0 auto;

    padding: 36px 0 60px;
}

.welcome-card {
    min-height: 205px;

    padding: 30px 35px;

    border-radius: 15px;

    background:
        linear(
            120deg,
            #fff0f1,
            #fff9f9 60%,
            #ffffff
        );

    border: 1px solid #f3dfe1;

    display: flex;
    align-items: center;
    justify-content: space-between;

    position: relative;

    overflow: hidden;

    margin-bottom: 32px;
}

.welcome-content {
    position: relative;

    z-index: 2;
}

.welcome-small {
    color: var(--pink-dark);

    font-size: 7px;

    font-weight: 700;

    letter-spacing: 1.5px;
}

.welcome-card h1 {
    margin-top: 7px;

    font-family: Georgia, serif;

    font-size: 23px;

    font-weight: 700;

    color: #292323;
}

.welcome-card h2 {
    margin-top: 5px;

    font-family: Georgia, serif;

    font-size: 18px;

    font-weight: 500;

    color: var(--pink-dark);
}

.welcome-card p {
    margin-top: 7px;

    max-width: 500px;

    color: #8a7d7f;

    font-size: 9px;

    line-height: 1.6;
}

.welcome-actions {
    display: flex;

    gap: 10px;

    margin-top: 18px;
}

.primary-button,
.secondary-button {
    height: 31px;

    padding: 0 15px;

    border-radius: 7px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    font-size: 8px;

    font-weight: 600;

    transition: .2s ease;
}

.primary-button {
    background: var(--pink);

    color: #ffffff;

    box-shadow: 0 3px 8px rgba(223,105,115,.20);
}

.primary-button:hover {
    background: var(--pink-dark);

    transform: translateY(-1px);
}

.secondary-button {
    background: #ffffff;

    color: #5b5051;

    border: 1px solid #eadcdd;
}

.secondary-button:hover {
    border-color: var(--pink);

    color: var(--pink-dark);

    transform: translateY(-1px);
}

.welcome-decoration {
    width: 190px;
    height: 190px;

    position: relative;

    margin-right: 15px;
}

.flower-circle {
    width: 110px;
    height: 110px;

    position: absolute;

    top: 35px;
    right: 25px;

    border-radius: 50%;

    background: #ffffff;

    color: var(--pink);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 35px;

    box-shadow:
        0 12px 35px rgba(200,130,135,.13);
}

.decoration-dot {
    width: 11px;
    height: 11px;

    position: absolute;

    border-radius: 50%;

    background: var(--pink);
}

.dot-one {
    top: 20px;
    right: 25px;
}

.dot-two {
    bottom: 28px;
    right: 5px;

    width: 7px;
    height: 7px;
}

.dot-three {
    bottom: 40px;
    left: 20px;

    width: 14px;
    height: 14px;

    opacity: .45;
}

.section-title {
    display: flex;

    align-items: end;
    justify-content: space-between;

    margin-bottom: 14px;
}

.section-title > div:first-child span {
    display: block;

    margin-bottom: 4px;

    color: var(--pink-dark);

    font-size: 7px;

    font-weight: 700;

    letter-spacing: 1.3px;
}

.section-title h2 {
    font-family: Georgia, serif;

    font-size: 17px;

    font-weight: 500;
}

.store-status {
    padding: 6px 9px;

    border-radius: 20px;

    background: #f1f8f3;

    color: #639370;

    font-size: 7px;

    display: flex;
    align-items: center;

    gap: 5px;
}

.store-status span {
    width: 5px;
    height: 5px;

    border-radius: 50%;

    background: #6da97b;
}

.statistics {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 13px;

    margin-bottom: 25px;
}

.statistics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 30px;
    margin-bottom: 25px;
}

.stat-card {
    min-width: 0;
    box-sizing: border-box;
}

.stat-icon.orange {
    background: #fff0df;
    color: #e58a26;
}

.stat-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 18px;
    color: #e58a26;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.stat-link:hover {
    text-decoration: underline;
}

/* Tablet */
@media (max-width: 1000px) {
    .statistics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* HP */
@media (max-width: 550px) {
    .statistics {
        grid-template-columns: 1fr;
    }
}

.stat-card {
    min-height: 140px;

    padding: 15px;

    background: #ffffff;

    border: 1px solid var(--border);

    border-radius: 11px;

    box-shadow:
        0 3px 12px rgba(50,35,35,.035);

    transition: .2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);

    box-shadow:
        0 8px 22px rgba(50,35,35,.06);
}

.stat-top {
    display: flex;

    align-items: center;
    justify-content: space-between;
}

.stat-icon {
    width: 31px;
    height: 31px;

    border-radius: 8px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 10px;
}

.stat-icon.pink {
    background: var(--pink-light);
    color: var(--pink-dark);
}

.stat-icon.green {
    background: #eef7f0;
    color: var(--green);
}

.stat-icon.purple {
    background: #f4f0f8;
    color: var(--purple);
}

.stat-icon.orange {
    background: #fff4e9;
    color: var(--orange);
}

.stat-label {
    color: #b1a5a7;

    font-size: 10px;

    font-weight: 700;

    letter-spacing: .8px;
}

.stat-content {
    margin-top: 35px;
}

.stat-content strong {
    display: block;

    color: #292323;

    font-family: Georgia, serif;

    font-size: 35px;
}

.stat-content strong.money {
    font-size: 30px;
}

.stat-content span {
    display: block;

    margin-top: 4px;

    color: #aaa0a1;

    font-size: 10px;
}

.bottom-grid {
    display: grid;

    grid-template-columns: 1fr 1.4fr;

    gap: 13px;
}

.info-card {
    min-height: 105px;

    padding: 18px;

    background: #ffffff;

    border: 1px solid var(--border);

    border-radius: 11px;

    display: flex;
    align-items: center;

    gap: 12px;
}

.info-icon {
    width: 39px;
    height: 39px;

    flex-shrink: 0;

    border-radius: 9px;

    background: var(--pink-light);

    color: var(--pink-dark);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 12px;
}

.info-content {
    flex: 1;
}

.info-content span {
    display: block;

    color: #a29899;

    font-size: 7px;
}

.info-content strong {
    display: block;

    margin-top: 3px;

    font-family: Georgia, serif;

    font-size: 19px;
}

.info-content p {
    margin-top: 3px;

    color: #aaa0a1;

    font-size: 7px;
}

.info-card > a {
    color: var(--pink-dark);

    font-size: 10px;
}

.quick-card {
    min-height: 105px;

    padding: 17px 20px;

    background: #ffffff;

    border: 1px solid var(--border);

    border-radius: 11px;
}

.quick-header span {
    color: var(--pink-dark);

    font-size: 6.5px;

    font-weight: 700;

    letter-spacing: 1px;
}

.quick-header h3 {
    margin-top: 4px;

    font-family: Georgia, serif;

    font-size: 14px;

    font-weight: 500;
}

.quick-links {
    display: flex;

    gap: 8px;

    margin-top: 13px;
}

.quick-links a {
    flex: 1;

    height: 30px;

    border-radius: 6px;

    background: #fff7f7;

    color: #675b5c;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 5px;

    font-size: 7.5px;

    border: 1px solid #f4e7e8;

    transition: .2s ease;
}

.quick-links a i {
    color: var(--pink-dark);
}

.quick-links a:hover {
    background: var(--pink-light);

    color: var(--pink-dark);
}

@media (max-width: 900px) {

    .admin-header {
        padding: 0 22px;
    }

    .brand {
        margin-right: 25px;
    }

    .admin-navigation {
        gap: 18px;
    }

    .admin-navigation a {
        font-size: 8px;
    }

    .header-user-info {
        display: none;
    }

    .admin-container {
        width: calc(100% - 40px);
    }

    .statistics {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 650px) {

    .admin-header {
        height: auto;

        min-height: 65px;

        padding: 10px 15px;

        flex-wrap: wrap;
    }

    .brand {
        margin-right: 0;
    }

    .brand-logo {
        width: 34px;
        height: 34px;
    }

    .brand-text strong {
        font-size: 13px;
    }

    .admin-navigation {
        width: 100%;

        height: 36px;

        order: 3;

        overflow-x: auto;

        gap: 20px;

        margin-top: 7px;

        border-top: 1px solid rgba(255,255,255,.8);
    }

    .admin-navigation a {
        flex-shrink: 0;

        height: 36px;

        font-size: 7px;
    }

    .header-user {
        margin-left: auto;
    }

    .admin-container {
        width: calc(100% - 30px);

        padding-top: 22px;
    }

    .welcome-card {
        padding: 24px;

        min-height: auto;
    }

    .welcome-card h1 {
        font-size: 20px;
    }

    .welcome-card h2 {
        font-size: 16px;

        line-height: 1.35;
    }

    .welcome-card p {
        font-size: 8px;

        max-width: 90%;
    }

    .welcome-decoration {
        display: none;
    }

    .welcome-actions {
        flex-wrap: wrap;
    }

    .primary-button,
    .secondary-button {
        font-size: 7.5px;

        height: 30px;
    }

    .statistics {
        grid-template-columns: 1fr;
    }

    .stat-card {
        min-height: 105px;
    }

    .bottom-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 400px) {

    .admin-navigation {
        gap: 15px;
    }

    .welcome-card {
        padding: 20px;
    }

    .welcome-card h1 {
        font-size: 18px;
    }

    .welcome-card h2 {
        font-size: 14px;
    }

    .welcome-actions {
        gap: 7px;
    }

    .primary-button,
    .secondary-button {
        padding: 0 10px;
    }

    .quick-links {
        flex-direction: column;
    }

}
    .admin-container {
        font-size: 13px;
    }

    .admin-container .laporan-card {
        background: white;
        padding: 22px 24px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 3px 15px rgba(0, 0, 0, .05);
    }

    /* Judul utama */
    .admin-container .laporan-card h1 {
        font-size: 25px;
        line-height: 1.3;
        margin: 0 0 3px;
        font-weight: 600;
    }

    /* Judul bagian */
    .admin-container .laporan-card h2 {
        font-size: 19px;
        line-height: 1.4;
        margin: 0 0 3px;
        font-weight: 600;
    }

    /* Deskripsi dan periode */
    .admin-container .laporan-card p {
        font-size: 13px;
        line-height: 1.5;
        margin: 0;
    }

    /* Form filter tanggal */
    .admin-container .filter {
        display: flex;
        gap: 12px;
        align-items: end;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .admin-container .filter div {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .admin-container .filter label {
        font-size: 12px;
    }

    .admin-container .filter input {
        padding: 9px 10px;
        border: 1px solid #ddd;
        border-radius: 7px;
        font-size: 12px;
    }

    /* Tombol */
    .admin-container .btn {
        display: inline-flex;
        gap: 7px;
        align-items: center;
        justify-content: center;
        padding: 10px 14px;
        background: #b94f78;
        color: white;
        text-decoration: none;
        border: none;
        border-radius: 7px;
        cursor: pointer;
        font-size: 12px;
    }

    .admin-container .btn-pdf {
        background: #444;
    }

    /* Ringkasan keuangan */
    .admin-container .ringkasan {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-top: 8px;
    }

    .admin-container .ringkasan p {
        font-size: 13px;
        margin-bottom: 1px;
    }

    .admin-container .angka {
        color: #b94f78;
        font-size: 23px;
        line-height: 1.4;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    /* Tabel transaksi */
    .admin-container .tabel-wrapper {
        overflow-x: auto;
        margin-top: 2px;
    }

    .admin-container table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .admin-container th,
    .admin-container td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .admin-container th {
        background: #fff0f5;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .admin-container td {
        font-size: 12px;
    }

    /* Pesan error */
    .admin-container .error {
        background: #fff0f0;
        color: #a12626;
        padding: 15px;
        border-radius: 8px;
        font-size: 13px;
    }

    @media (max-width: 600px) {
        .admin-container .laporan-card {
            padding: 16px;
        }

        .admin-container .laporan-card h1 {
            font-size: 22px;
        }

        .admin-container .laporan-card h2 {
            font-size: 17px;
        }

        .admin-container .ringkasan {
            grid-template-columns: 1fr;
            gap: 12px;
        }
    }

.brand-logo {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 10px;
}

.brand-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>

<body>

<header class="admin-header">

    <div class="brand">

        <a href="dashboard.php">

<div class="brand-logo">
    <img src="../assets/img/logo flonuel.png" alt="Logo FlowerShop">
</div>

<div class="brand-text">
    <span>Admin Panel</span>
</div>

        </a>

    </div>


<nav class="admin-navigation">

    <a href="dashboard.php" class="active">
        Dashboard
    </a>

    <a href="pesanan/index.php">
        Data Pesanan
    </a>

    <a href="pembayaran/index.php">
        Data Pembayaran
    </a>

    <a href="produk/index.php">
        Produk
    </a>

    <a href="addon/index.php">
        Add On
    </a>

    <a href="bunga/index.php">
        Bunga
    </a>
    <a href="kategori/index.php">
        Kategori
    </a>

    <a href="wrapping/index.php">
        Wrapping
    </a>

    <a href="laporan_keuangan.php">
        Laporan Keuangan
    </a>

</nav>


    <div class="header-user">

        <div class="header-avatar">
            <i class="fa-solid fa-user"></i>
        </div>

        <div class="header-user-info">

            <strong>
                <?= htmlspecialchars($nama_admin) ?>
            </strong>

            <span>
                Administrator
            </span>

        </div>

        <a
            href="../logout.php"
            class="logout-button"
            title="Logout"
            onclick="return confirm('Apakah Anda yakin ingin logout?')"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>

    </div>

</header>

<main class="admin-container">


    <section class="welcome-card">

        <div class="welcome-content">

            <span class="welcome-small">
                ADMIN DASHBOARD
            </span>

            <h1>
                Halo, <?= htmlspecialchars($nama_admin) ?>
            </h1>

            <h2>
                Ready to manage your bouquet orders?
            </h2>
            </p>
        
            <div class="welcome-actions">

                <a
                    href="pesanan/index.php"
                    class="primary-button"
                >
                    <!-- <i class="fa-solid fa-bag-shopping"></i> -->
                    Cek Data Pesanan
                </a>

                <a
                    href="produk/index.php"
                    class="secondary-button"
                >
                    Kelola Produk
                </a>

            </div>

        </div>

    </section>


<section class="statistics">

    <div class="stat-card">
        <div class="stat-top">
            <div class=>
                <!-- <i class="fa-solid fa-bag-shopping"></i> -->
            </div>
            <span class="stat-label">PESANAN</span>
        </div>

        <div class="stat-content">
            <strong><?= $total_pesanan ?></strong>
            <span>Total pesanan masuk</span>
        </div>
    </div>

    <!-- PENJUALAN -->
    <div class="stat-card">
        <div class="stat-top">
            <div class=>
                <!-- <i class="fa-solid fa-chart-line"></i> -->
            </div>
            <span class="stat-label">PENJUALAN</span>
        </div>

        <div class="stat-content">
            <strong class="money">
                <?= rupiah($penjualan_selesai) ?>
            </strong>
            <span>Pesanan yang selesai</span>
        </div>
    </div>

    <!-- KATEGORI -->
    <div class="stat-card">
        <div class="stat-top">
            <div class=>
                <!-- <i class="fa-solid fa-layer-group"></i> -->
            </div>
            <span class="stat-label">KATEGORI</span>
        </div>

        <div class="stat-content">
            <strong><?= $total_kategori ?></strong>
            <span>Kategori produk</span>
        </div>
    </div>

    <!-- PEMBAYARAN -->
    <div class="stat-card">
        <div class="stat-top">
            <div class=>
                <!-- <i class="fa-solid fa-credit-card"></i> -->
            </div>
            <span class="stat-label">PEMBAYARAN </span>
        </div>

        <div class="stat-content">
            <strong><?= $menunggu_verifikasi ?></strong>
            <span>Menunggu verifikasi</span>
        </div>

        <a href="pembayaran/index.php" class="stat-link">
            Lihat pembayaran
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

</section>


</body>
</html>