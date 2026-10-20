<?php
session_start();

require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmtUser = mysqli_prepare($conn, "
    SELECT User_id, Nama, No_Hp, Email, Username
    FROM user
    WHERE User_id = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmtUser, "i", $user_id);
mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);

mysqli_stmt_close($stmtUser);

if (!$user) {
    session_destroy();
    header("Location: ../../login.php");
    exit;
}

$nama = $user['Nama'] ?? 'Customer';

$sql = "
    SELECT
        p.Pesanan_id,
        p.Kode_pesanan,
        p.Tanggal_pesanan,
        p.total_harga,
        p.status_pesanan,
        p.Produk_id,
        pr.Nama_produk,
        pr.Foto_produk
    FROM PESANAN p
    LEFT JOIN PRODUK pr
        ON p.Produk_id = pr.Produk_id
    WHERE p.User_id = ?
    ORDER BY p.Pesanan_id DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$pesanan = [];

while ($row = mysqli_fetch_assoc($result)) {
    $pesanan[] = $row;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/
function formatRupiah($nominal)
{
    return 'Rp ' . number_format((float) $nominal, 0, ',', '.');
}

function statusLabel($status)
{
    $labels = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'diproses'             => 'Diproses',
        'dibuat'               => 'Sedang Dibuat',
        'siap_diambil'         => 'Siap Diambil',
        'selesai'              => 'Selesai',
        'dibatalkan'           => 'Dibatalkan'
    ];

    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function statusClass($status)
{
    $classes = [
        'menunggu_pembayaran' => 'warning',
        'menunggu_verifikasi' => 'info',
        'diproses'             => 'primary',
        'dibuat'               => 'secondary',
        'siap_diambil'         => 'primary',
        'selesai'              => 'success',
        'dibatalkan'           => 'danger'
    ];

    return $classes[$status] ?? 'secondary';
}

function fotoProduk($foto)
{
    if (empty($foto)) {
        return '';
    }

    $foto = basename($foto);

    $pathUpload = __DIR__ . '/../../uploads/produk/' . $foto;
    $pathAssets = __DIR__ . '/../../assets/img/produk/' . $foto;

    if (file_exists($pathUpload)) {
        return '../../uploads/produk/' . $foto;
    }

    if (file_exists($pathAssets)) {
        return '../../assets/img/produk/' . $foto;
    }

    return '';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pesanan Saya</title>

    <link
        rel="stylesheet"
        href="../../assets/css/customer.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body>


<aside class="dashboard-sidebar" id="sidebar">

<a href="dashboard.php" class="brand"
   style="
       display: flex;
       align-items: center;
       justify-content: center;
       width: 100%;
       box-sizing: border-box;
       text-decoration: none;
       padding: 15px 10px;
       margin: 0;
   ">

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

    <a href="../dashboard.php">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="../pesanan/index.php" class="active">
        <i class="fa-solid fa-bag-shopping"></i>
        <span>Pesanan Saya</span>
    </a>

    <a href="../katalog.php">
        <i class="fa-solid fa-book-open"></i>
        <span>Katalog</span>
    </a>

    <a href="../pembayaran/index.php">
        <i class="fa-solid fa-credit-card"></i>
        <span>Pembayaran</span>
    </a>

    <a
        href="../../logout.php"
        onclick="return confirm('Apakah Anda yakin ingin logout?')"
    >
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>

</nav>

</aside>


<div class="sidebar-overlay" id="sidebarOverlay"></div>


<div class="main-content">

    <!-- TOPBAR -->

    <header class="topbar">

        <div class="topbar-left">

            <button
                type="button"
                class="mobile-menu-btn"
                id="menuButton"
                aria-label="Buka menu"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">
                Pesanan Saya
            </div>

        </div>


        <div class="profile-mini">

            <div>

                <div class="profile-name">
                    <?= htmlspecialchars($nama); ?>
                </div>

                <div class="profile-role">
                    Customer
                </div>

            </div>

            <div class="profile-avatar">
                <?= strtoupper(substr($nama, 0, 1)); ?>
            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <main class="content">

        <!-- HEADER -->

        <section class="catalog-header">

            <h1 class="catalog-title">
                Pesanan Saya
            </h1>

            <p class="catalog-subtitle">
                Lihat dan pantau semua pesanan bunga yang telah Anda buat.
            </p>

        </section>


        <!-- JUMLAH PESANAN -->

        <div class="section-title">

            <h2>
                Daftar Pesanan
            </h2>

            <span class="section-count">
                <?= count($pesanan); ?> pesanan
            </span>

        </div>

        <?php if (!empty($pesanan)): ?>

            <div class="order-list">

                <?php foreach ($pesanan as $item): ?>

                    <?php

                    $foto = fotoProduk(
                        $item['Foto_produk'] ?? ''
                    );

                    $status = $item['status_pesanan'] ?? '';

                    $kode = $item['Kode_pesanan'] ?? '-';

                    ?>

                    <div class="order-card">

                        <!-- FOTO -->

                        <div class="order-image-wrapper">

                            <?php if ($foto): ?>

                                <img
                                    src="<?= htmlspecialchars($foto); ?>"
                                    alt="<?= htmlspecialchars($item['Nama_produk'] ?? 'Produk'); ?>"
                                    class="order-image"
                                >

                            <?php else: ?>

                                <div class="order-no-image">

                                    <i class="fa-solid fa-flower"></i>

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- INFORMASI -->

                        <div class="order-info">

                            <div class="order-top">

                                <div>

                                    <div class="order-code">
                                        <?= htmlspecialchars($kode); ?>
                                    </div>

                                    <h3 class="order-product">

                                        <?= htmlspecialchars(
                                            $item['Nama_produk'] ??
                                            'Produk tidak tersedia'
                                        ); ?>

                                    </h3>

                                </div>


                                <span
                                    class="order-status <?= statusClass($status); ?>"
                                >
                                    <?= htmlspecialchars(
                                        statusLabel($status)
                                    ); ?>
                                </span>

                            </div>


                            <!-- META -->

                            <div class="order-meta">

                                <div class="order-meta-item">

                                    <i class="fa-regular fa-calendar"></i>

                                    <span>

                                        <?= !empty($item['Tanggal_pesanan'])
                                            ? date(
                                                'd M Y',
                                                strtotime(
                                                    $item['Tanggal_pesanan']
                                                )
                                            )
                                            : '-';
                                        ?>

                                    </span>

                                </div>


                                <div class="order-meta-item">

                                    <i class="fa-solid fa-money-bill-wave"></i>

                                    <span>

                                        <?= formatRupiah(
                                            $item['total_harga']
                                        ); ?>

                                    </span>

                                </div>

                            </div>


                            <!-- BOTTOM -->

                            <div class="order-bottom">

                                <div class="order-total">

                                    <span>
                                        Total Pesanan
                                    </span>

                                    <strong>

                                        <?= formatRupiah(
                                            $item['total_harga']
                                        ); ?>

                                    </strong>

                                </div>


                                <a
                                    href="detail.php?id=<?= (int) $item['Pesanan_id']; ?>"
                                    class="btn-view"
                                >

                                    <i class="fa-solid fa-eye"></i>

                                    Lihat Detail

                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <!-- =================================================
                 EMPTY
            ================================================= -->

            <div class="empty-box">

                <div class="empty-icon">

                    <i class="fa-solid fa-bag-shopping"></i>

                </div>

                <div class="empty-title">

                    Belum Ada Pesanan

                </div>

                <p class="empty-text">

                    Anda belum memiliki pesanan.
                    Silakan pilih bunga dari katalog kami.

                </p>

                <a
                    href="../katalog.php"
                    class="btn-view empty-button"
                >

                    <i class="fa-solid fa-book-open"></i>

                    Lihat Katalog

                </a>

            </div>

        <?php endif; ?>

    </main>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

const sidebar = document.getElementById('sidebar');

const menuButton = document.getElementById('menuButton');

const sidebarOverlay =
    document.getElementById('sidebarOverlay');


if (menuButton) {

    menuButton.addEventListener('click', function () {

        sidebar.classList.toggle('show');

        sidebarOverlay.classList.toggle('show');

    });

}


if (sidebarOverlay) {

    sidebarOverlay.addEventListener('click', function () {

        sidebar.classList.remove('show');

        sidebarOverlay.classList.remove('show');

    });

}


document
    .querySelectorAll('.sidebar-menu a')
    .forEach(function(link) {

        link.addEventListener('click', function() {

            if (window.innerWidth <= 991) {

                sidebar.classList.remove('show');

                sidebarOverlay.classList.remove('show');

            }

        });

    });

</script>

</body>

</html>