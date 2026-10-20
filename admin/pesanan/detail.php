
<?php
session_start();
require_once '../../config/koneksi.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../../login.php');
    exit;
}

function rupiahDetail($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function tanggalDetail($tanggal, $format = 'd/m/Y')
{
    if (!$tanggal) {
        return '-';
    }

    $waktu = strtotime($tanggal);
    return $waktu ? date($format, $waktu) : '-';
}

$pesanan_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$pesanan_id || $pesanan_id < 1) {
    header('Location: index.php');
    exit;
}

$sql = "
    SELECT
        p.Pesanan_id,
        p.Kode_pesanan,
        p.User_id,
        p.Produk_id,
        p.Wrapping_id,
        p.Pickup_id,
        p.Tanggal_pesanan,
        p.total_harga,
        p.Catatan_pesanan,
        p.status_pesanan,

        u.Nama AS nama_pelanggan,
        u.Username AS username_pelanggan,
        u.Email AS email_pelanggan,
        u.No_Hp AS telepon_pelanggan,

        pr.Nama_produk AS nama_produk,
        pr.Harga AS harga_produk,
        pr.Foto_produk AS foto_produk,

        w.Warna_wrapping AS warna_wrapping,
        jp.Tanggal_pickup AS tanggal_pickup,
        jp.Jam_pickup AS jam_pickup

    FROM pesanan p

    LEFT JOIN user u
        ON p.User_id = u.User_id

    LEFT JOIN produk pr
        ON p.Produk_id = pr.Produk_id

    LEFT JOIN wrapping w
        ON p.Wrapping_id = w.Wrapping_id

    LEFT JOIN jadwal_pickup jp
        ON p.Pickup_id = jp.Pickup_id

    WHERE p.Pesanan_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die('Query pesanan gagal disiapkan: ' .
        htmlspecialchars(mysqli_error($conn)));
}

mysqli_stmt_bind_param($stmt, 'i', $pesanan_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$pesanan = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$pesanan) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan. <a href="index.php">Kembali</a>');
}

$status_list = [
    'menunggu_pembayaran'  => 'Menunggu Pembayaran',
    'menunggu_verifikasi'  => 'Menunggu Verifikasi',
    'diproses'             => 'Diproses',
    'dibuat'               => 'Sedang Dibuat',
    'siap_diambil'         => 'Siap Diambil',
    'siap_dikirim'         => 'Siap Dikirim',
    'dikirim'              => 'Dikirim',
    'selesai'              => 'Selesai',
    'dibatalkan'           => 'Dibatalkan'
];

$status = $pesanan['status_pesanan'] ?? '';
$label_status = $status_list[$status]
    ?? ucfirst(str_replace('_', ' ', $status));

$bunga_list = [];

$sql_bunga = "
    SELECT
        db.Detail_bunga_id,
        db.Bunga_id,
        b.Nama_bunga AS nama_bunga,
        db.Jumlah AS jumlah
    FROM detail_bunga db
    INNER JOIN bunga b
        ON db.Bunga_id = b.Bunga_id
    WHERE db.Pesanan_id = ?
    ORDER BY db.Detail_bunga_id ASC
";

$stmt_bunga = mysqli_prepare($conn, $sql_bunga);

if (!$stmt_bunga) {
    die('Query detail bunga gagal: ' .
        htmlspecialchars(mysqli_error($conn)));
}

mysqli_stmt_bind_param($stmt_bunga, 'i', $pesanan_id);
mysqli_stmt_execute($stmt_bunga);

$result_bunga = mysqli_stmt_get_result($stmt_bunga);

while ($row = mysqli_fetch_assoc($result_bunga)) {
    $bunga_list[] = $row;
}

mysqli_stmt_close($stmt_bunga);

$addon_list = [];
$total_addon = 0;

$sql_addon = "
    SELECT
        da.Detail_addon_id,
        da.addon_id,
        a.Nama_addon AS nama_addon,
        da.Jumlah AS jumlah,
        da.Harga AS harga,
        (da.Jumlah * da.Harga) AS subtotal
    FROM detail_add_on da
    INNER JOIN add_on a
        ON da.addon_id = a.addon_id
    WHERE da.Pesanan_id = ?
    ORDER BY da.Detail_addon_id ASC
";

$stmt_addon = mysqli_prepare($conn, $sql_addon);

if (!$stmt_addon) {
    die('Query detail Add-On gagal: ' .
        htmlspecialchars(mysqli_error($conn)));
}

mysqli_stmt_bind_param($stmt_addon, 'i', $pesanan_id);
mysqli_stmt_execute($stmt_addon);

$result_addon = mysqli_stmt_get_result($stmt_addon);

while ($row = mysqli_fetch_assoc($result_addon)) {
    $addon_list[] = $row;
    $total_addon += (float)$row['subtotal'];
}

mysqli_stmt_close($stmt_addon);

$foto = trim($pesanan['foto_produk'] ?? '');
$foto_url = '';

if ($foto !== '') {
    if (preg_match('~^https?://~i', $foto)) {
        $foto_url = $foto;
    } else {
        $foto = ltrim(str_replace('\\', '/', $foto), '/');

        if (
            strpos($foto, 'uploads/') === 0 ||
            strpos($foto, 'assets/') === 0
        ) {
            $foto_url = '../../' . $foto;
        } else {
            $foto_url = '../../uploads/produk/' . basename($foto);
        }
    }
}

$nama_admin = $_SESSION['nama']
    ?? $_SESSION['Nama']
    ?? 'Admin';

$harga_produk = (float)($pesanan['harga_produk'] ?? 0);
$total_pesanan = (float)($pesanan['total_harga'] ?? 0);
$catatan = trim($pesanan['Catatan_pesanan'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Pesanan | FlowerShop</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-detail-pesanan.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<style>
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

<!-- HEADER -->
<header class="admin-header">
    <div class="brand">
        <a href="../dashboard.php">
            <div class="brand-logo">
    <img src="../../assets/img/logo flonuel.png" alt="Logo FlowerShop">
</div>

            <div class="brand-text">
                <span>Admin Panel</span>
            </div>
        </a>
    </div>

    <nav class="admin-navigation">
        <a href="../dashboard.php">Dashboard</a>
        <a href="index.php" class="active">Data Pesanan</a>
        <a href="../pembayaran/index.php">Data Pembayaran</a>
        <a href="../produk/index.php">Produk</a>
        <a href="../addon/index.php">Add On</a>
        <a href="../bunga/index.php">Bunga</a>
        <a href="../kategori/index.php">Kategori</a>
        <a href="../wrapping/index.php">Wrapping</a>
        <a href="../laporan_keuangan.php">Laporan Keuangan</a>
    </nav>

    <div class="header-user">
        <div class="header-avatar">
            <i class="fa-solid fa-user"></i>
        </div>

        <div class="header-user-info">
            <strong><?= htmlspecialchars($nama_admin) ?></strong>
            <span>Administrator</span>
        </div>

        <a href="../../logout.php"
           class="logout-button"
           title="Logout"
           onclick="return confirm('Yakin ingin logout?')">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</header>

<main class="detail-admin-container">

    <div class="detail-page-heading">
        <a href="index.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Data Pesanan
        </a>

        <div class="detail-heading-row">
            <div>
                <span class="detail-kicker">MANAJEMEN TRANSAKSI</span>
                <h1>Detail Pesanan</h1>
                <p>Informasi lengkap pesanan pelanggan FlowerShop.</p>
            </div>

            <div class="detail-order-code">
                <span>KODE PESANAN</span>
                <strong><?= htmlspecialchars($pesanan['Kode_pesanan'] ?? '-') ?></strong>
            </div>
        </div>
    </div>

    <!-- STATUS PESANAN -->
    <section class="detail-status-card">
        <div class="status-card-left">
            <div class="status-card-icon">
                <i class="fa-solid fa-receipt"></i>
            </div>

            <div>
                <span class="status-caption">STATUS PESANAN</span>
                <h2><?= htmlspecialchars($label_status) ?></h2>
                <p>
                    Dipesan pada
                    <?= tanggalDetail($pesanan['Tanggal_pesanan'] ?? null) ?>
                </p>
            </div>
        </div>

        <div class="detail-status-badge <?= htmlspecialchars($status) ?>">
            <span></span>
            <?= htmlspecialchars($label_status) ?>
        </div>
    </section>

    <div class="detail-admin-grid">

        <!-- KOLOM KIRI -->
        <div class="detail-main-column">

            <!-- INFORMASI PRODUK -->
            <section class="detail-admin-card">
                <div class="detail-card-heading">
                    <div class="detail-card-icon">
                        <i class="fa-solid fa-gift"></i>
                    </div>

                    <div>
                        <h2>Informasi Produk</h2>
                        <p>Produk utama yang dipesan</p>
                    </div>
                </div>

                <div class="admin-product-info">
                    <div class="admin-product-image">
                        <?php if ($foto_url !== ''): ?>
                            <img
                                src="<?= htmlspecialchars($foto_url) ?>"
                                alt="<?= htmlspecialchars($pesanan['nama_produk'] ?? 'Produk') ?>"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>

                        <div class="product-image-fallback"
                             style="<?= $foto_url !== '' ? 'display:none;' : 'display:flex;' ?>">
                            <i class="fa-solid fa-spa"></i>
                        </div>
                    </div>

                    <div class="admin-product-description">
                        <span class="product-type-label">PRODUK FLOWERSHOP</span>

                        <h3>
                            <?= htmlspecialchars($pesanan['nama_produk'] ?? 'Produk tidak ditemukan') ?>
                        </h3>

                        <div class="product-price">
                            <?= rupiahDetail($harga_produk) ?>
                        </div>

                        <p>
                            Kode pesanan:
                            <strong>
                                <?= htmlspecialchars($pesanan['Kode_pesanan'] ?? '-') ?>
                            </strong>
                        </p>
                    </div>
                </div>

                <div class="detail-divider"></div>

                <div class="detail-information-row">
                    <span>Tanggal pemesanan</span>
                    <strong>
                        <?= tanggalDetail($pesanan['Tanggal_pesanan'] ?? null, 'd/m/Y H:i') ?>
                    </strong>
                </div>
            </section>

            <!-- DETAIL BUNGA DAN WRAPPING -->
            <section class="detail-admin-card">
                <div class="detail-card-heading">
                    <div class="detail-card-icon">
                        <i class="fa-solid fa-seedling"></i>
                    </div>

                    <div>
                        <h2>Detail Bunga</h2>
                        <p>Rincian bunga dan wrapping pesanan</p>
                    </div>
                </div>

                <div class="detail-information-row custom-row">
                    <span>Pilihan bunga</span>

                    <div>
                        <?php if (!empty($bunga_list)): ?>
                            <?php foreach ($bunga_list as $bunga): ?>
                                <p>
                                    <?= htmlspecialchars($bunga['nama_bunga']) ?>
                                    (<?= (int)$bunga['jumlah'] ?> tangkai)
                                </p>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <strong>Belum ada detail bunga</strong>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-information-row custom-row">
                    <span>Wrapping</span>
                    <strong>
                        <?= htmlspecialchars($pesanan['warna_wrapping'] ?? 'Tidak ditentukan') ?>
                    </strong>
                </div>

                <div class="detail-notes-box">
                    <span>
                        <i class="fa-regular fa-clipboard"></i>
                        Catatan pesanan
                    </span>

                    <p>
                        <?= nl2br(htmlspecialchars(
                            $catatan !== '' ? $catatan : 'Tidak ada catatan pesanan.'
                        )) ?>
                    </p>
                </div>
            </section>

            <!-- ADD-ON -->
            <section class="detail-admin-card">
                <div class="detail-card-heading">
                    <div class="detail-card-icon">
                        <i class="fa-solid fa-box-open"></i>
                    </div>

                    <div>
                        <h2>Add-On Pesanan</h2>
                        <p>Tambahan yang dipilih pelanggan</p>
                    </div>
                </div>

                <?php if (!empty($addon_list)): ?>
                    <div class="addon-list">
                        <?php foreach ($addon_list as $addon): ?>
                            <div class="addon-item">
                                <div class="addon-item-info">
                                    <div class="addon-item-icon">
                                        <i class="fa-solid fa-gift"></i>
                                    </div>

                                    <div>
                                        <h3>
                                            <?= htmlspecialchars($addon['nama_addon']) ?>
                                        </h3>

                                        <span>
                                            <?= (int)$addon['jumlah'] ?> item
                                            × <?= rupiahDetail($addon['harga']) ?>
                                        </span>
                                    </div>
                                </div>

                                <strong class="addon-subtotal">
                                    <?= rupiahDetail($addon['subtotal']) ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="detail-divider"></div>

                    <div class="detail-information-row">
                        <span>Total Add-On</span>
                        <strong><?= rupiahDetail($total_addon) ?></strong>
                    </div>
                <?php else: ?>
                    <div class="addon-empty">
                        <i class="fa-regular fa-circle-check"></i>
                        <p>Tidak ada Add-On yang tercatat untuk pesanan ini.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- JADWAL PICKUP -->
            <section class="detail-admin-card">
                <div class="detail-card-heading">
                    <div class="detail-card-icon">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>

                    <div>
                        <h2>Jadwal Pengambilan</h2>
                        <p>Jadwal pickup pesanan pelanggan</p>
                    </div>
                </div>

                <div class="pickup-information">
                    <div class="pickup-information-item">
                        <div class="pickup-icon">
                            <i class="fa-regular fa-calendar"></i>
                        </div>

                        <div>
                            <span>Tanggal pickup</span>
                            <strong>
                                <?= tanggalDetail($pesanan['tanggal_pickup'] ?? null) ?>
                            </strong>
                        </div>
                    </div>

                    <div class="pickup-information-item">
                        <div class="pickup-icon">
                            <i class="fa-regular fa-clock"></i>
                        </div>

                        <div>
                            <span>Jam pickup</span>
                            <strong>
                                <?= tanggalDetail($pesanan['jam_pickup'] ?? null, 'H:i') ?>
                                <?= !empty($pesanan['jam_pickup']) ? ' WIB' : '' ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </section>

        </div>

        <!-- KOLOM KANAN -->
        <aside class="detail-side-column">

            <!-- INFORMASI PELANGGAN -->
            <section class="detail-admin-card">
                <div class="detail-card-heading">
                    <div class="detail-card-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <div>
                        <h2>Informasi Pelanggan</h2>
                        <p>Data pemesan</p>
                    </div>
                </div>

                <div class="admin-customer-profile">
                    <div class="admin-customer-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <h3>
                        <?= htmlspecialchars($pesanan['nama_pelanggan'] ?? 'Pelanggan') ?>
                    </h3>

                    <span>
                        Username:
                        <?= htmlspecialchars($pesanan['username_pelanggan'] ?? '-') ?>
                    </span>
                </div>

                <div class="customer-contact-row">
                    <i class="fa-regular fa-envelope"></i>
                    <div>
                        <span>Email</span>
                        <strong>
                            <?= htmlspecialchars($pesanan['email_pelanggan'] ?? '-') ?>
                        </strong>
                    </div>
                </div>

                <div class="customer-contact-row">
                    <i class="fa-solid fa-phone"></i>
                    <div>
                        <span>Nomor telepon</span>
                        <strong>
                            <?= htmlspecialchars($pesanan['telepon_pelanggan'] ?? '-') ?>
                        </strong>
                    </div>
                </div>
            </section>

            <!-- RINGKASAN HARGA -->
            <section class="detail-admin-card order-total-card">
                <div class="detail-card-heading">
                    <div class="detail-card-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </div>

                    <div>
                        <h2>Ringkasan Harga</h2>
                        <p>Total transaksi pesanan</p>
                    </div>
                </div>

                <div class="price-detail-row">
                    <span>Harga produk</span>
                    <strong><?= rupiahDetail($harga_produk) ?></strong>
                </div>

                <div class="price-detail-row">
                    <span>Total Add-On</span>
                    <strong><?= rupiahDetail($total_addon) ?></strong>
                </div>

                <div class="detail-divider"></div>

                <div class="price-total-row">
                    <span>Total pesanan</span>
                    <strong><?= rupiahDetail($total_pesanan) ?></strong>
                </div>

                <p class="price-disclaimer">
                    Total mengikuti transaksi yang tersimpan di database.
                </p>
            </section>
            <!-- AKSI INVOICE -->
<div class="admin-invoice-actions">
    <a
        href="cetak_invoice.php?id=<?= (int)$pesanan['Pesanan_id'] ?>&aksi=lihat"
        class="admin-action-button invoice-view"
        target="_blank"
        rel="noopener"
    >
        <i class="fa-solid fa-eye"></i>
        Lihat Invoice PDF
    </a>

    <a
        href="cetak_invoice.php?id=<?= (int)$pesanan['Pesanan_id'] ?>&aksi=download"
        class="admin-action-button invoice-download"
    >
        <i class="fa-solid fa-download"></i>
        Download Invoice PDF
    </a>

                <?php if (
                    $status === 'menunggu_pembayaran' ||
                    $status === 'menunggu_verifikasi'
                ): ?>
                    <a href="../pembayaran/index.php"
                       class="admin-action-button primary">
                        <i class="fa-solid fa-credit-card"></i>
                        Periksa Pembayaran
                    </a>
                <?php endif; ?>
                <a href="index.php" class="admin-action-button secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                    Kembali ke Daftar
                </a>
            </section>

        </aside>
    </div>
</main>

</body>
</html>
