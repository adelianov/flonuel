
<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/* DATA ADMIN */
$nama_admin = 'Admin';

$stmtAdmin = mysqli_prepare($conn, "
    SELECT Nama FROM user WHERE User_id = ? LIMIT 1
");

if ($stmtAdmin) {
    mysqli_stmt_bind_param($stmtAdmin, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmtAdmin);
    $resultAdmin = mysqli_stmt_get_result($stmtAdmin);

    if ($resultAdmin && mysqli_num_rows($resultAdmin) > 0) {
        $admin = mysqli_fetch_assoc($resultAdmin);
        $nama_admin = $admin['Nama'] ?: 'Admin';
    }

    mysqli_stmt_close($stmtAdmin);
}

/* STATISTIK PRODUK */
$total_produk = 0;
$total_aktif = 0;
$total_nonaktif = 0;
$total_kategori = 0;

$q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM produk");
if ($q) {
    $total_produk = (int) mysqli_fetch_assoc($q)['total'];
}

$q = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM produk
    WHERE Status = 'aktif'
");
if ($q) {
    $total_aktif = (int) mysqli_fetch_assoc($q)['total'];
}

$q = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM produk
    WHERE Status = 'tidak_aktif'
");
if ($q) {
    $total_nonaktif = (int) mysqli_fetch_assoc($q)['total'];
}

$q = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM kategori_produk
");
if ($q) {
    $total_kategori = (int) mysqli_fetch_assoc($q)['total'];
}

/* PENCARIAN DAN FILTER */
$keyword = trim($_GET['cari'] ?? '');
$kategori_id = (int) ($_GET['kategori'] ?? 0);

$sql = "
    SELECT
        p.Produk_id,
        p.Nama_produk,
        p.Deskripsi_produk,
        p.Harga,
        p.Jumlah_bunga,
        p.Foto_produk,
        p.Status,
        p.Kategori_id,
        k.Nama_kategori
    FROM produk p
    LEFT JOIN kategori_produk k
        ON p.Kategori_id = k.Kategori_id
    WHERE 1=1
";

if ($keyword !== '') {
    $sql .= "
        AND (
            p.Nama_produk LIKE ?
            OR p.Deskripsi_produk LIKE ?
        )
    ";
}

if ($kategori_id > 0) {
    $sql .= " AND p.Kategori_id = ?";
}

$sql .= " ORDER BY p.Produk_id DESC";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die('Gagal menyiapkan query produk: ' . e(mysqli_error($conn)));
}

if ($keyword !== '' && $kategori_id > 0) {
    $cari = '%' . $keyword . '%';
    mysqli_stmt_bind_param($stmt, 'ssi', $cari, $cari, $kategori_id);
} elseif ($keyword !== '') {
    $cari = '%' . $keyword . '%';
    mysqli_stmt_bind_param($stmt, 'ss', $cari, $cari);
} elseif ($kategori_id > 0) {
    mysqli_stmt_bind_param($stmt, 'i', $kategori_id);
}

mysqli_stmt_execute($stmt);
$produkQuery = mysqli_stmt_get_result($stmt);

/* DATA KATEGORI */
$kategoriQuery = mysqli_query(
    $conn,
    "SELECT Kategori_id, Nama_kategori
     FROM kategori_produk
     ORDER BY Nama_kategori ASC"
);

$jumlah_hasil = $produkQuery ? mysqli_num_rows($produkQuery) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Produk</title>

    <link rel="stylesheet" href="../../assets/css/admin.css?v=2">
<link rel="stylesheet" href="../../assets/css/admin-produk.css?v=2">

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

        <a href="dashboard.php">

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
        <a href="../pesanan/index.php">Data Pesanan</a>
        <a href="../pembayaran/index.php">Data Pembayaran</a>
        <a href="index.php" class="active">Produk</a>
        <a href="../addon/index.php.">Add On</a>
        <a href="../bunga/index.php.">Bunga</a>
        <a href="../kategori/index.php">Kategori</a>
        <a href="../wrapping/index.php">Wrapping</a>
        <a href="../laporan_keuangan.php">Laporan Keuangan</a>

    </nav>

    <div class="header-user">
        <div class="header-avatar">
            <i class="fa-solid fa-user"></i>
        </div>

        <div class="header-user-info">
            <strong><?= e($nama_admin) ?></strong>
            <span>Administrator</span>
        </div>

        <a href="../../logout.php"
           class="logout-button"
           title="Logout"
           onclick="return confirm('Apakah Anda yakin ingin logout?')">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>

</header>

<!-- MAIN -->
<main class="admin-container produk-page">
    <div class="produk-page-heading">
        <div>
            <h1>Data Produk</h1>
        </div>

        <a href="tambah.php" class="primary-button">
            <i class="fa-solid fa-plus"></i>
            Tambah Produk
        </a>
    </div>

    <!-- JUDUL STATISTIK -->
    <div class="section-title produk-section-title">
        <div>
        </div>

    </div>

    <!-- STATISTIK -->
    <section class="statistics produk-statistics">

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon">
                    <!-- <i class="fa-solid fa-box-open"></i> -->
                </div>
                <span class="stat-label">TOTAL PRODUK</span>
            </div>

            <div class="stat-content">
                <strong><?= $total_produk ?></strong>
                <span>Seluruh produk katalog</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon">
                </div>
                <span class="stat-label">PRODUK AKTIF</span>
            </div>

            <div class="stat-content">
                <strong><?= $total_aktif ?></strong>
                <span>Produk tersedia di katalog</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon">
                </div>
                <span class="stat-label">TIDAK AKTIF</span>
            </div>

            <div class="stat-content">
                <strong><?= $total_nonaktif ?></strong>
                <span>Produk nonaktif</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon">
                </div>
                <span class="stat-label">KATEGORI</span>
            </div>

            <div class="stat-content">
                <strong><?= $total_kategori ?></strong>
                <span>Kategori produk</span>
            </div>
        </div>

    </section>

    <!-- NOTIFIKASI -->
    <?php if (isset($_GET['pesan'])): ?>
        <div class="produk-alert">
            <?php
            $pesan = $_GET['pesan'];

            if ($pesan === 'tambah') {
                echo 'Produk berhasil ditambahkan.';
            } elseif ($pesan === 'edit') {
                echo 'Produk berhasil diperbarui.';
            } elseif ($pesan === 'hapus') {
                echo 'Produk berhasil dihapus.';
            } elseif ($pesan === 'gagal') {
                echo 'Proses gagal. Silakan periksa kembali.';
            } else {
                echo 'Informasi produk diperbarui.';
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- TABEL PRODUK -->
    <section class="produk-table-card">

        <div class="produk-table-header">
            <div>
                <h2>Daftar Produk</h2>
                <p>Ditemukan <?= $jumlah_hasil ?> produk.</p>
            </div>
        </div>

        <!-- PENCARIAN DAN FILTER -->
        <form method="GET" class="produk-filter">

            <div class="produk-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                    type="text"
                    name="cari"
                    placeholder="Cari nama produk..."
                    value="<?= e($keyword) ?>">
            </div>

            <select name="kategori">
                <option value="0">Semua Kategori</option>

                <?php if ($kategoriQuery): ?>
                    <?php while ($kat = mysqli_fetch_assoc($kategoriQuery)): ?>
                        <option
                            value="<?= (int) $kat['Kategori_id'] ?>"
                            <?= $kategori_id === (int) $kat['Kategori_id'] ? 'selected' : '' ?>>
                            <?= e($kat['Nama_kategori']) ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>

            <button type="submit" class="produk-btn-filter">
                <i class="fa-solid fa-filter"></i>
                Filter
            </button>

            <a href="index.php" class="produk-btn-reset">Reset</a>
        </form>

        <!-- TABEL -->
        <div class="produk-table-wrap">
            <table class="produk-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Informasi Produk</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Jumlah Bunga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($jumlah_hasil > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($produk = mysqli_fetch_assoc($produkQuery)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <div class="produk-info">
                                    <?php
                                    $foto = trim((string)($produk['Foto_produk'] ?? ''));
                                    $namaFoto = basename($foto);
                                    $fotoPath = '../../uploads/produk/' . rawurlencode($namaFoto);
                                    ?>

                                    <div class="produk-image">
                                        <?php if ($foto !== ''): ?>
                                            <img
                                                src="<?= e($fotoPath) ?>"
                                                alt="<?= e($produk['Nama_produk']) ?>"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">

                                            <div class="produk-image-placeholder" style="display:none">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="produk-image-placeholder">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="produk-info-text">
                                        <strong><?= e($produk['Nama_produk']) ?></strong>
                                        <span>
                                            <?= e(function_exists('mb_strimwidth')
                                                ? mb_strimwidth(
                                                    (string)($produk['Deskripsi_produk'] ?? ''),
                                                    0,
                                                    65,
                                                    '...'
                                                )
                                                : substr(
                                                    (string)($produk['Deskripsi_produk'] ?? ''),
                                                    0,
                                                    65
                                                )
                                            ) ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="produk-category">
                                    <?= e($produk['Nama_kategori'] ?? 'Tanpa kategori') ?>
                                </span>
                            </td>

                            <td class="produk-price">
                                <?= rupiah($produk['Harga']) ?>
                            </td>

                            <td><?= (int) $produk['Jumlah_bunga'] ?> bunga</td>

                            <td>
                                <?php if ($produk['Status'] === 'aktif'): ?>
                                    <span class="produk-status aktif">Aktif</span>
                                <?php else: ?>
                                    <span class="produk-status nonaktif">Tidak Aktif</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="produk-actions">
                                    <a
                                        href="edit.php?id=<?= (int) $produk['Produk_id'] ?>"
                                        class="produk-action edit"
                                        title="Edit produk">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>

                                    <a
                                        href="hapus.php?id=<?= (int) $produk['Produk_id'] ?>"
                                        class="produk-action delete"
                                        title="Hapus produk"
                                        onclick="return confirm('Yakin ingin menghapus produk ini?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="7" class="produk-empty">
                            <i class="fa-solid fa-box-open"></i>
                            <strong>Produk belum ditemukan</strong>
                            <span>Coba ubah kata kunci atau kategori pencarian.</span>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

    <footer class="produk-footer">
        &copy; <?= date('Y') ?> FlowerShop Admin Panel
    </footer>

</main>

</body>
</html>
