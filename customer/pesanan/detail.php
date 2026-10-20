
<?php
session_start();
require_once '../../config/koneksi.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header('Location: ../../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$pesanan_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($pesanan_id <= 0) {
    header('Location: index.php');
    exit;
}
function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function hariIndonesia($hari)
{
    $hariList = [
        'Sunday'    => 'Minggu',
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu'
    ];

    return $hariList[$hari] ?? $hari;
}

$sqlUser = "
    SELECT User_id, Nama, No_Hp, Email
    FROM user
    WHERE User_id = ?
    LIMIT 1
";

$stmtUser = mysqli_prepare($conn, $sqlUser);

if (!$stmtUser) {
    die('Query user gagal.');
}

mysqli_stmt_bind_param($stmtUser, 'i', $user_id);
mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);

mysqli_stmt_close($stmtUser);

if (!$user) {
    session_destroy();
    header('Location: ../../login.php');
    exit;
}

$sqlPesanan = "
    SELECT
        p.Pesanan_id,
        p.Kode_pesanan,
        p.User_id,
        p.Produk_id,
        p.pilihan_bunga,
        p.wrapping,
        p.catatan_custom,
        p.Wrapping_id,
        p.Pickup_id,
        p.Tanggal_pesanan,
        p.total_harga,
        p.Catatan_pesanan,
        p.status_pesanan,

        pr.Nama_produk,
        pr.Harga AS harga_produk,
        pr.Foto_produk,
        pr.Deskripsi_produk,

        w.Warna_wrapping,

        jp.Tanggal_pickup,
        jp.Jam_pickup

    FROM pesanan p

    LEFT JOIN produk pr
        ON p.Produk_id = pr.Produk_id

    LEFT JOIN wrapping w
        ON p.Wrapping_id = w.Wrapping_id

    LEFT JOIN jadwal_pickup jp
        ON p.Pickup_id = jp.Pickup_id

    WHERE p.Pesanan_id = ?
      AND p.User_id = ?

    LIMIT 1
";

$stmtPesanan = mysqli_prepare($conn, $sqlPesanan);

if (!$stmtPesanan) {
    die('Query detail pesanan gagal. Periksa struktur tabel pesanan, produk, wrapping, dan jadwal_pickup.');
}

mysqli_stmt_bind_param(
    $stmtPesanan,
    'ii',
    $pesanan_id,
    $user_id
);

mysqli_stmt_execute($stmtPesanan);

$resultPesanan = mysqli_stmt_get_result($stmtPesanan);
$pesanan = mysqli_fetch_assoc($resultPesanan);

mysqli_stmt_close($stmtPesanan);

if (!$pesanan) {
    header('Location: index.php');
    exit;
}

$status = $pesanan['status_pesanan'] ?? '';

$statusLabel = [
    'menunggu_pembayaran' => 'Menunggu Pembayaran',
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'diproses'             => 'Diproses',
    'dibuat'               => 'Sedang Dibuat',
    'siap_diambil'         => 'Siap Diambil',
    'selesai'              => 'Selesai',
    'dibatalkan'           => 'Dibatalkan'
];

$statusClass = [
    'menunggu_pembayaran' => 'waiting',
    'menunggu_verifikasi' => 'verification',
    'diproses'             => 'processing',
    'dibuat'               => 'making',
    'siap_diambil'         => 'ready',
    'selesai'              => 'success',
    'dibatalkan'           => 'cancelled'
];

$statusText = $statusLabel[$status]
    ?? ucwords(str_replace('_', ' ', $status));

$statusBadge = $statusClass[$status] ?? 'waiting';

$tanggalPesanan = '-';

if (!empty($pesanan['Tanggal_pesanan'])) {
    $timestamp = strtotime($pesanan['Tanggal_pesanan']);

    if ($timestamp !== false) {
        $tanggalPesanan =
            hariIndonesia(date('l', $timestamp)) .
            ', ' . date('d/m/Y H:i', $timestamp);
    }
}

$tanggalPickup = '-';

if (!empty($pesanan['Tanggal_pickup'])) {
    $timestampPickup = strtotime($pesanan['Tanggal_pickup']);

    if ($timestampPickup !== false) {
        $tanggalPickup =
            hariIndonesia(date('l', $timestampPickup)) .
            ', ' . date('d/m/Y', $timestampPickup);
    }
}

$jamPickup = '-';

if (!empty($pesanan['Jam_pickup'])) {
    $timestampJam = strtotime($pesanan['Jam_pickup']);

    if ($timestampJam !== false) {
        $jamPickup = date('H:i', $timestampJam);
    }
}

$fotoProduk = trim((string) ($pesanan['Foto_produk'] ?? ''));
$fotoPath = '';

if ($fotoProduk !== '') {
    if (
        preg_match('~^https?://~i', $fotoProduk) &&
        filter_var($fotoProduk, FILTER_VALIDATE_URL)
    ) {
        $fotoPath = $fotoProduk;
    } else {
        $fotoProduk = str_replace('\\', '/', $fotoProduk);
        $fotoProduk = ltrim($fotoProduk, '/');

        $allowedPrefixes = [
            'uploads/produk/',
            'assets/img/produk/'
        ];

        $relativePaths = [];

        foreach ($allowedPrefixes as $prefix) {
            if (strpos($fotoProduk, $prefix) === 0) {
                $relativePaths[] = $fotoProduk;
            }
        }

        $namaFile = basename($fotoProduk);

        $relativePaths[] = 'uploads/produk/' . $namaFile;
        $relativePaths[] = 'assets/img/produk/' . $namaFile;

        foreach ($relativePaths as $relative) {
            $localPath = __DIR__ . '/../../' . $relative;

            if (is_file($localPath)) {
                $fotoPath = '../../' . $relative;
                break;
            }
        }
    }
}

$pilihanBunga = trim((string) ($pesanan['pilihan_bunga'] ?? ''));
$pilihanBunga = $pilihanBunga !== '' ? $pilihanBunga : '-';

$wrapping = trim(
    (string) (
        $pesanan['Warna_wrapping']
        ?? $pesanan['wrapping']
        ?? ''
    )
);

$wrapping = $wrapping !== '' ? $wrapping : '-';

$catatanCustom = trim((string) ($pesanan['catatan_custom'] ?? ''));
$catatanPesanan = trim((string) ($pesanan['Catatan_pesanan'] ?? ''));

$daftarAddon = [];
$totalAddon = 0;
$addonError = false;

$sqlAddon = "
    SELECT
        da.Detail_addon_id,
        da.addon_id,
        da.Jumlah AS jumlah,
        da.Harga AS harga,
        COALESCE(a.Nama_addon, 'Add-On') AS nama

    FROM detail_add_on da

    LEFT JOIN add_on a
        ON da.addon_id = a.addon_id

    WHERE da.Pesanan_id = ?

    ORDER BY da.Detail_addon_id ASC
";

$stmtAddon = mysqli_prepare($conn, $sqlAddon);

if ($stmtAddon) {
    mysqli_stmt_bind_param($stmtAddon, 'i', $pesanan_id);
    mysqli_stmt_execute($stmtAddon);

    $resultAddon = mysqli_stmt_get_result($stmtAddon);

    while ($rowAddon = mysqli_fetch_assoc($resultAddon)) {
        $jumlahAddon = max(1, (int) $rowAddon['jumlah']);
        $hargaAddon = (float) $rowAddon['harga'];

        $subtotalAddon = $hargaAddon * $jumlahAddon;

        $daftarAddon[] = [
            'addon_id' => (int) $rowAddon['addon_id'],
            'nama'     => $rowAddon['nama'],
            'jumlah'   => $jumlahAddon,
            'harga'    => $hargaAddon,
            'subtotal' => $subtotalAddon
        ];

        $totalAddon += $subtotalAddon;
    }

    mysqli_stmt_close($stmtAddon);
} else {

    $addonError = true;
}

$totalPesanan = (float) ($pesanan['total_harga'] ?? 0);
$hargaProduk = (float) ($pesanan['harga_produk'] ?? 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Detail Pesanan</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/detail.css"
    >

    <style>
        .addon-detail {
            padding-left: 12px;
            color: #8b7782;
            font-size: 12px;
        }

        .addon-detail strong {
            color: #55414b;
        }

        .addon-empty-message {
            padding: 13px 15px;
            margin-top: 12px;
            border-radius: 9px;
            background: #fff7fb;
            color: #8b7782;
            font-size: 12px;
            line-height: 1.6;
        }

        .addon-error-message {
            padding: 13px 15px;
            margin-top: 12px;
            border-radius: 9px;
            background: #fff1f4;
            color: #a61e45;
            font-size: 12px;
            line-height: 1.6;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .total-row span {
            overflow-wrap: anywhere;
        }

        .total-row strong {
            text-align: right;
        }

        @media (max-width: 480px) {
            .total-row {
                gap: 10px;
            }

            .addon-detail {
                padding-left: 8px;
            }
        }
        
/* CARD PEMBAYARAN */
.payment-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 22px;
    margin-top: 22px;
    background: #fff;
    border: 1px solid #f0dce6;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(85, 65, 75, 0.06);
}

.payment-card-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.payment-card-icon {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #fff0f6;
    color: #c05282;
    font-size: 22px;
}

.payment-card-info h3 {
    margin: 0 0 6px;
    color: #55414b;
    font-size: 16px;
}

.payment-card-info p {
    margin: 0;
    color: #8b7782;
    font-size: 13px;
    line-height: 1.6;
}

.payment-card .btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 18px;
    border-radius: 9px;
    background: #c05282;
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    white-space: nowrap;
    transition: 0.2s;
}

.payment-card .btn-primary:hover {
    background: #a9406e;
}

@media (max-width: 600px) {
    .payment-card {
        align-items: stretch;
        flex-direction: column;
        gap: 18px;
    }

    .payment-card .btn-primary {
        width: 100%;
        box-sizing: border-box;
    }
}

.invoice-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}

.btn-invoice {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 9px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: background 0.2s ease;
}

.btn-view {
    background: #fff0f6;
    color: #a9406e;
    border: 1px solid #e9bfd2;
}

.btn-view:hover {
    background: #f9dce9;
}

.btn-download {
    background: #c05282;
    color: #fff;
    border: 1px solid #c05282;
}

.btn-download:hover {
    background: #a9406e;
}

@media (max-width: 600px) {
    .invoice-actions {
        flex-direction: column;
    }

    .btn-invoice {
        width: 100%;
        box-sizing: border-box;
    }
}

    </style>
</head>

<body>

<!-- SIDEBAR -->

<aside class="dashboard-sidebar" id="sidebar">

<a
    href="../dashboard.php"
    class="brand"
    style="display:flex; align-items:center; gap:10px; text-decoration:none; padding:15px;"
>

    <div style="
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
">
    <img
        src="/bunga/assets/img/logo%20flonuel.png?v=2"
        alt="Logo FlowerShop"
        style="
            display: block;
            width: 100%;
            max-width: 200px;
            height: 100px;
            object-fit: contain;
            object-position: center;
        "
    >
</div>

</a>

<nav class="sidebar-menu">

    <div class="sidebar-label">MENU</div>

    <a href="../dashboard.php">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="index.php" class="active">
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


</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MAIN -->
<main class="main-content">

    <!-- TOPBAR -->
    <header class="topbar">

        <div class="topbar-left">

            <button
                type="button"
                class="mobile-menu"
                id="mobileMenu"
                aria-label="Buka menu"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">
                Detail Pesanan
            </div>

        </div>

        <div class="profile-mini">

            <div class="profile-info">
                <div class="profile-name">
                    <?= e($user['Nama']); ?>
                </div>

                <div class="profile-role">
                    Customer
                </div>
            </div>

            <div class="profile-avatar">
                <i class="fa-solid fa-user"></i>
            </div>

        </div>

    </header>

    <!-- CONTENT -->
    <section class="content">

        <a href="index.php" class="back-button">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Pesanan Saya
        </a>

        <!-- HEADER PESANAN -->
        <div class="detail-header">

            <div>
                <div class="detail-title">
                    <i class="fa-solid fa-receipt"></i>
                    Detail Pesanan
                </div>

                <div class="order-code">
                    <?= e($pesanan['Kode_pesanan']); ?>
                </div>
            </div>

            <div class="status-badge <?= e($statusBadge); ?>">
                <?= e($statusText); ?>
            </div>

        </div>

        <!-- INFORMASI PESANAN -->
        <div class="order-info-grid">

            <div class="info-box">
                <span class="info-label">Tanggal Pesanan</span>
                <strong><?= e($tanggalPesanan); ?></strong>
            </div>

            <div class="info-box">
                <span class="info-label">Nomor Pesanan</span>
                <strong><?= e($pesanan['Kode_pesanan']); ?></strong>
            </div>

            <div class="info-box">
                <span class="info-label">Status</span>
                <strong><?= e($statusText); ?></strong>
            </div>

        </div>

        <!-- PRODUK -->
        <div class="detail-card">

            <div class="card-title">
                <i class="fa-solid fa-bag-shopping"></i>
                Produk Pesanan
            </div>

            <div class="product-detail">

                <div class="product-image">

                    <?php if ($fotoPath !== ''): ?>

                        <img
                            src="<?= e($fotoPath); ?>"
                            alt="<?= e($pesanan['Nama_produk'] ?? 'Produk'); ?>"
                        >

                    <?php else: ?>

                        <div class="image-placeholder">
                            <i class="fa-solid fa-image"></i>
                            <span>Foto tidak tersedia</span>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="product-info">

                    <div class="product-category">
                        PRODUK
                    </div>

                    <h2>
                        <?= e($pesanan['Nama_produk'] ?? 'Produk'); ?>
                    </h2>

                    <div class="product-price">
                        <?= rupiah($hargaProduk); ?>
                    </div>

                    <?php if (!empty($pesanan['Deskripsi_produk'])): ?>

                        <p class="product-description">
                            <?= nl2br(e($pesanan['Deskripsi_produk'])); ?>
                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- DETAIL CUSTOM -->
        <div class="detail-card">

            <div class="card-title">
                <i class="fa-solid fa-sliders"></i>
                Detail Custom
            </div>

            <div class="custom-grid">

                <div class="custom-item">

                    <div class="custom-icon">
                        <i class="fa-solid fa-leaf"></i>
                    </div>

                    <div>
                        <span>Pilihan Bunga</span>
                        <strong><?= e($pilihanBunga); ?></strong>
                    </div>

                </div>

                <div class="custom-item">

                    <div class="custom-icon">
                        <i class="fa-solid fa-gift"></i>
                    </div>

                    <div>
                        <span>Wrapping</span>
                        <strong><?= e($wrapping); ?></strong>
                    </div>

                </div>

            </div>

        </div>

        <!-- ADD-ON -->
        <div class="detail-card">

            <div class="card-title">
                <i class="fa-solid fa-gift"></i>
                Add-On Pesanan
            </div>

            <?php if ($addonError): ?>

                <div class="addon-error-message">
                    Data Add-On belum dapat dimuat. Periksa tabel
                    <strong>detail_add_on</strong> dan struktur kolomnya.
                </div>

            <?php elseif (!empty($daftarAddon)): ?>

                <?php foreach ($daftarAddon as $addon): ?>

                    <div class="total-row addon-detail">

                        <span>
                            <?= e($addon['nama']); ?>
                            × <?= (int) $addon['jumlah']; ?>
                            <br>
                            <small>
                                <?= rupiah($addon['harga']); ?> per item
                            </small>
                        </span>

                        <strong>
                            <?= rupiah($addon['subtotal']); ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

                <div class="total-line"></div>

                <div class="total-row">
                    <span>Total Add-On</span>
                    <strong><?= rupiah($totalAddon); ?></strong>
                </div>

            <?php else: ?>

                <div class="addon-empty-message">
                    Pesanan ini tidak memiliki Add-On yang tersimpan.
                </div>

            <?php endif; ?>

        </div>

        <!-- PICKUP -->
        <div class="detail-card">

            <div class="card-title">
                <i class="fa-solid fa-calendar-check"></i>
                Jadwal Pengambilan
            </div>

            <div class="pickup-detail">

                <div class="pickup-item">

                    <div class="pickup-icon">
                        <i class="fa-regular fa-calendar-days"></i>
                    </div>

                    <div>
                        <span>Tanggal Pickup</span>
                        <strong><?= e($tanggalPickup); ?></strong>
                    </div>

                </div>

                <div class="pickup-item">

                    <div class="pickup-icon">
                        <i class="fa-regular fa-clock"></i>
                    </div>

                    <div>
                        <span>Jam Pickup</span>
                        <strong><?= e($jamPickup); ?></strong>
                    </div>

                </div>

            </div>

            <div class="pickup-note">
                <i class="fa-solid fa-circle-info"></i>
                <span>
                    Pesanan diambil langsung di toko sesuai tanggal dan
                    jam pickup yang telah dipilih.
                </span>
            </div>

        </div>

        <!-- CATATAN -->
        <?php if ($catatanCustom !== '' || $catatanPesanan !== ''): ?>

            <div class="detail-card">

                <div class="card-title">
                    <i class="fa-solid fa-note-sticky"></i>
                    Catatan Pesanan
                </div>

                <?php if ($catatanCustom !== ''): ?>

                    <div class="note-block">
                        <span>Catatan Custom</span>
                        <p><?= nl2br(e($catatanCustom)); ?></p>
                    </div>

                <?php endif; ?>

                <?php if ($catatanPesanan !== ''): ?>

                    <div class="note-block">
                        <span>Catatan Pesanan</span>
                        <p><?= nl2br(e($catatanPesanan)); ?></p>
                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <!-- TOTAL PEMBAYARAN -->
        <div class="total-card">

            <div class="total-header">
                <span>Ringkasan Pembayaran</span>
                <i class="fa-solid fa-wallet"></i>
            </div>

            <div class="total-row">
                <span>Produk</span>
                <strong><?= rupiah($hargaProduk); ?></strong>
            </div>

            <div class="total-row">
                <span>Add-On</span>

                <strong>
                    <?= !empty($daftarAddon)
                        ? rupiah($totalAddon)
                        : rupiah(0); ?>
                </strong>
            </div>

            <div class="total-line"></div>

            <div class="total-final">
                <span>Total Pesanan</span>
                <strong><?= rupiah($totalPesanan); ?></strong>
            </div>

        </div>

        

<!-- CARD PEMBAYARAN -->
<div class="payment-card">

    <div class="payment-card-info">
        <div class="payment-card-icon">
            <i class="fa-solid fa-credit-card"></i>
        </div>

        <div>
            <h3>Pembayaran Pesanan</h3>

            <?php if ($status === 'menunggu_pembayaran'): ?>
                <p>Pesanan belum dibayar. Silakan lanjutkan pembayaran.</p>

            <?php elseif ($status === 'menunggu_verifikasi'): ?>
                <p>Bukti pembayaran sedang menunggu verifikasi.</p>

            <?php elseif ($status === 'dibatalkan'): ?>
                <p>Pesanan dibatalkan. Kamu tetap dapat membuka halaman pembayaran.</p>

            <?php else: ?>
                <p>Lihat informasi pembayaran untuk pesanan ini.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOMBOL SELALU TAMPIL -->
    <a
        href="../pembayaran/index.php?pesanan_id=<?= (int) $pesanan['Pesanan_id']; ?>"
        class="btn-primary"
    >
        <?php if ($status === 'menunggu_pembayaran'): ?>
            <i class="fa-solid fa-wallet"></i>
            Bayar Sekarang
        <?php else: ?>
           
            Lihat Pembayaran
        <?php endif; ?>
    </a>

<div class="invoice-actions">
    <a
        href="cetak_invoice.php?id=<?= (int) $pesanan['Pesanan_id']; ?>&aksi=lihat"
        class="btn-invoice btn-view"
        target="_blank"
        rel="noopener"
    >
        <i class="fa-solid fa-eye"></i>
        Lihat Invoice PDF
    </a>

    <a
        href="cetak_invoice.php?id=<?= (int) $pesanan['Pesanan_id']; ?>&aksi=download"
        class="btn-invoice btn-download"
    >
        <i class="fa-solid fa-download"></i>
        Download Invoice PDF
    </a>
</div>

</div>


    

</div>

<!-- TOMBOL KEMBALI -->
<div class="detail-actions">
    <a href="index.php" class="btn-secondary">
        <i class="fa-solid fa-arrow-left"></i>
        Kembali ke Pesanan Saya
    </a>
</div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mobileMenu = document.getElementById('mobileMenu');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (mobileMenu && sidebar && overlay) {
        mobileMenu.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
});
</script>

</body>
</html>
