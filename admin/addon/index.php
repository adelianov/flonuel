
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

$stmtAdmin = mysqli_prepare(
    $conn,
    "SELECT Nama FROM user WHERE User_id = ? LIMIT 1"
);

if ($stmtAdmin) {
    mysqli_stmt_bind_param($stmtAdmin, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmtAdmin);
    $resultAdmin = mysqli_stmt_get_result($stmtAdmin);

    if ($resultAdmin && ($admin = mysqli_fetch_assoc($resultAdmin))) {
        $nama_admin = $admin['Nama'] ?: 'Admin';
    }

    mysqli_stmt_close($stmtAdmin);
}

/* HAPUS ADD-ON */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'hapus') {

    $id_hapus = filter_input(INPUT_POST, 'addon_id', FILTER_VALIDATE_INT);

    if (!$id_hapus || $id_hapus < 1) {
        header('Location: index.php?pesan=gagal');
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT Foto FROM add_on WHERE addon_id = ? LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, 'i', $id_hapus);
    mysqli_stmt_execute($stmt);
    $resultFoto = mysqli_stmt_get_result($stmt);
    $dataFoto = mysqli_fetch_assoc($resultFoto);
    mysqli_stmt_close($stmt);

    if (!$dataFoto) {
        header('Location: index.php?pesan=tidak_ditemukan');
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM add_on WHERE addon_id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'i', $id_hapus);
    $berhasil = mysqli_stmt_execute($stmt);
    $errorDb = mysqli_stmt_errno($stmt);
    mysqli_stmt_close($stmt);

    if ($berhasil) {
        if (!empty($dataFoto['Foto'])) {
            $pathFoto = __DIR__ . '/../../uploads/addon/' .
                basename($dataFoto['Foto']);

            if (is_file($pathFoto)) {
                @unlink($pathFoto);
            }
        }

        header('Location: index.php?pesan=hapus');
        exit;
    }

    header('Location: index.php?pesan=' .
        ($errorDb === 1451 ? 'terhubung' : 'gagal'));
    exit;
}

/* PENCARIAN */
$keyword = trim($_GET['cari'] ?? '');

if ($keyword !== '') {
    $sql = "SELECT addon_id, Nama_addon, Harga, Foto, Stok
            FROM add_on
            WHERE Nama_addon LIKE ?
            ORDER BY addon_id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    $cari = '%' . $keyword . '%';
    mysqli_stmt_bind_param($stmt, 's', $cari);
    mysqli_stmt_execute($stmt);
    $addonQuery = mysqli_stmt_get_result($stmt);
} else {
    $addonQuery = mysqli_query(
        $conn,
        "SELECT addon_id, Nama_addon, Harga, Foto, Stok
         FROM add_on
         ORDER BY addon_id DESC"
    );
}

if (!$addonQuery) {
    die('Gagal mengambil data add-on. Periksa tabel add_on di database.');
}

/* STATISTIK */
$total_addon = mysqli_num_rows($addonQuery);
$qTotal = mysqli_query($conn, "SELECT COUNT(*) AS total FROM add_on");
$total_semua = $qTotal ? (int)mysqli_fetch_assoc($qTotal)['total'] : 0;

$qStok = mysqli_query($conn, "SELECT COALESCE(SUM(Stok), 0) AS total FROM add_on");
$total_stok = $qStok ? (int)mysqli_fetch_assoc($qStok)['total'] : 0;

$qNilai = mysqli_query($conn, "SELECT COALESCE(SUM(Harga * Stok), 0) AS total FROM add_on");
$total_nilai = $qNilai ? (float)mysqli_fetch_assoc($qNilai)['total'] : 0;

/* NOTIFIKASI */
$notifikasi = [
    'tambah' => ['success', 'Add-on berhasil ditambahkan.'],
    'edit' => ['success', 'Add-on berhasil diperbarui.'],
    'hapus' => ['success', 'Add-on berhasil dihapus.'],
    'gagal' => ['error', 'Add-on gagal diproses.'],
    'terhubung' => ['error', 'Add-on tidak dapat dihapus karena masih digunakan oleh data lain.'],
    'tidak_ditemukan' => ['error', 'Data add-on tidak ditemukan.']
];

$pesan = $notifikasi[$_GET['pesan'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Add-on</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-addon.css">
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
        <a href="../pesanan/index.php">Data Pesanan</a>
        <a href="../pembayaran/index.php">Data Pembayaran</a>
        <a href="../produk/index.php">Produk</a>
        <a href="index.php" class="active">Add-on</a>
        <a href="../bunga/index.php">Bunga</a>
        <a href="../kategori/index.php">Kategori</a>
        <a href="../wrapping/index.php">Wrapping</a>
        <a href="../laporan_keuangan.php">Laporan Keuangan</a>
    </nav>

    <div class="header-user">
        <div class="header-avatar"><i class="fa-solid fa-user"></i></div>
        <div class="header-user-info">
            <strong><?= e($nama_admin) ?></strong>
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

<main class="admin-container addon-page">

    <section class="addon-heading">
        <div>

            <h1>Data Add-on</h1>
    
        </div>

        <a href="tambah.php" class="addon-primary">
            <i class="fa-solid fa-plus"></i> Tambah Add-on
        </a>
    </section>

    <?php if ($pesan): ?>
        <div class="addon-alert <?= e($pesan[0]) ?>">
            <i class="fa-solid <?= $pesan[0] === 'success'
                ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <?= e($pesan[1]) ?>
        </div>
    <?php endif; ?>

    <section class="addon-stats">
        <div class="addon-stat-card">
            <!-- <div class="addon-stat-icon pink"><i class="fa-solid fa-gift"></i></div> -->
            <div>
                <span>Total Jenis Add-on</span>
                <strong><?= $total_semua ?></strong>
            </div>
        </div>

        <div class="addon-stat-card">
            <div>
                <span>Total Stok</span>
                <strong><?= $total_stok ?></strong>
            </div>
        </div>

        
        </div>
    </section>

    <section class="addon-panel">
        <div class="addon-panel-heading">
            <div>
                <h2>Daftar Add-on</h2>
                <p><?= $total_addon ?> data ditemukan<?= $keyword !== '' ? ' untuk pencarian ini' : '' ?>.</p>
            </div>
        </div>

        <form method="GET" class="addon-search-form">
            <div class="addon-search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text"
                       name="cari"
                       placeholder="Cari nama add-on..."
                       value="<?= e($keyword) ?>">
            </div>
            <button type="submit" class="addon-primary">
                Cari
            </button>
            <?php if ($keyword !== ''): ?>
                <a href="index.php" class="addon-reset">Reset</a>
            <?php endif; ?>
        </form>

        <div class="addon-table-wrap">
            <table class="addon-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Foto & Nama Add-on</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($total_addon > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($addon = mysqli_fetch_assoc($addonQuery)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div class="addon-product">
                                    <div class="addon-photo">
                                        <?php if (!empty($addon['Foto'])): ?>
                                            <img
                                                src="../../uploads/addon/<?= e(rawurlencode(basename($addon['Foto']))) ?>"
                                                alt="<?= e($addon['Nama_addon']) ?>"
                                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            <div class="addon-photo-empty" style="display:none">
                                                <i class="fa-regular fa-image"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="addon-photo-empty">
                                                <i class="fa-regular fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?= e($addon['Nama_addon']) ?></strong>
                                        <small>ID Add-on: <?= (int)$addon['addon_id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="addon-price"><?= rupiah($addon['Harga']) ?></td>
                            <td>
                                <span class="addon-stock <?= (int)$addon['Stok'] < 1 ? 'empty' : '' ?>">
                                    <?= (int)$addon['Stok'] ?> unit
                                </span>
                            </td>
                            <td>
                                <?php if ((int)$addon['Stok'] > 0): ?>
                                    <span class="addon-status available">Tersedia</span>
                                <?php else: ?>
                                    <span class="addon-status unavailable">Stok Habis</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="addon-actions">
                                    <a href="edit.php?id=<?= (int)$addon['addon_id'] ?>"
                                       class="addon-action edit"
                                       title="Edit add-on">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>

                                    <form method="POST"
                                          onsubmit="return confirm('Yakin ingin menghapus add-on ini?')">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="addon_id"
                                               value="<?= (int)$addon['addon_id'] ?>">
                                        <button type="submit"
                                                class="addon-action delete"
                                                title="Hapus add-on">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="addon-empty">
                            <i class="fa-solid fa-gift"></i>
                            <strong>Data add-on belum tersedia</strong>
                            <span>Tambahkan add-on baru atau ubah kata pencarian.</span>
                            <a href="tambah.php" class="addon-primary">
                                <i class="fa-solid fa-plus"></i> Tambah Add-on
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    
</main>

</body>
</html>