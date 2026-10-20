
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

$nama_admin = 'Admin';

$stmtAdmin = mysqli_prepare(
    $conn,
    "SELECT Nama FROM user WHERE User_id = ? LIMIT 1"
);

if ($stmtAdmin) {
    mysqli_stmt_bind_param(
        $stmtAdmin,
        'i',
        $_SESSION['user_id']
    );

    mysqli_stmt_execute($stmtAdmin);
    $resultAdmin = mysqli_stmt_get_result($stmtAdmin);

    if ($resultAdmin && ($admin = mysqli_fetch_assoc($resultAdmin))) {
        $nama_admin = $admin['Nama'] ?: 'Admin';
    }

    mysqli_stmt_close($stmtAdmin);
}

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$keyword = trim($_GET['cari'] ?? '');

if ($keyword !== '') {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT Bunga_id, Nama_bunga, Foto_bunga, Stok
         FROM bunga
         WHERE Nama_bunga LIKE ?
         ORDER BY Bunga_id DESC"
    );

    $search = '%' . $keyword . '%';
    mysqli_stmt_bind_param($stmt, 's', $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query(
        $conn,
        "SELECT Bunga_id, Nama_bunga, Foto_bunga, Stok
         FROM bunga
         ORDER BY Bunga_id DESC"
    );
}

if (!$result) {
    die('Gagal mengambil data bunga: ' . e(mysqli_error($conn)));
}

$totalBunga = mysqli_num_rows($result);

$totalStok = 0;
$dataBunga = [];

while ($row = mysqli_fetch_assoc($result)) {
    $dataBunga[] = $row;
    $totalStok += (int)$row['Stok'];
}

$pesan = $_GET['pesan'] ?? '';

$notifikasi = [
    'tambah' => 'Data bunga berhasil ditambahkan.',
    'edit' => 'Data bunga berhasil diperbarui.',
    'hapus' => 'Data bunga berhasil dihapus.',
    'gagal_hapus' => 'Data bunga gagal dihapus. Bunga mungkin sudah digunakan dalam pesanan.',
    'data_tidak_ditemukan' => 'Data bunga tidak ditemukan.'
];

$pesanTampil = $notifikasi[$pesan] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Data Bunga | FlowerShop Admin</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-bunga.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >
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
                <img
                    src="../../assets/img/logo flonuel.png"
                    alt="Logo FlowerShop"
                >
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
        <a href="../addon/index.php" >Add-on</a>
        <a href="../bunga/index.php" class="active">Bunga</a>
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

        <a
            href="../../logout.php"
            class="logout-button"
            title="Logout"
            onclick="return confirm('Yakin ingin logout?')"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</header>

<main class="admin-container bunga-page">

    <section class="bunga-heading">
        <div>
            <h1>Data Bunga</h1>

        </div>

        <a href="tambah.php" class="btn-tambah-bunga">
            <i class="fa-solid fa-plus"></i>
            Tambah Bunga
        </a>
    </section>

    <!-- NOTIFIKASI -->
    <?php if ($pesanTampil !== ''): ?>
        <div class="bunga-alert">
            <i class="fa-solid fa-circle-info"></i>
            <?= e($pesanTampil) ?>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <section class="bunga-stats">

        <div class="bunga-stat-card">
            <div class="bunga-stat-icon">

            </div>

            <div>
                <span>Jenis Bunga</span>
                <h2><?= number_format(count($dataBunga), 0, ',', '.') ?></h2>
                <small>
                    <?= $keyword !== '' ? 'Hasil pencarian' : 'Data tersedia' ?>
                </small>
            </div>
        </div>

        <div class="bunga-stat-card">
            <div class="bunga-stat-icon">
            </div>

            <div>
                <span>Total Stok Hasil Tampil</span>
                <h2><?= number_format($totalStok, 0, ',', '.') ?></h2>
    
            </div>
        </div>

    </section>

    <!-- DAFTAR BUNGA -->
    <section class="bunga-card">

        <div class="bunga-card-header">
            <div>
                <h2>Daftar Bunga</h2>
                <p>Informasi jenis bunga yang tersedia.</p>
            </div>

            <form method="GET" action="index.php" class="bunga-search">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="search"
                    name="cari"
                    placeholder="Cari nama bunga..."
                    value="<?= e($keyword) ?>"
                >

                <button type="submit">Cari</button>

                <?php if ($keyword !== ''): ?>
                    <a href="index.php" class="btn-reset-search" title="Reset">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="bunga-table-wrapper">
            <table class="bunga-table">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Gambar</th>
                        <th>Nama Bunga</th>
                        <th>Stok</th>
                        <th>Status Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($dataBunga) > 0): ?>

                    <?php foreach ($dataBunga as $index => $bunga): ?>
                        <?php
                            $stok = (int)$bunga['Stok'];
                            $foto = trim((string)($bunga['Foto_bunga'] ?? ''));

                            // Mendukung nama file yang disimpan di database.
                            // Jika Foto_bunga berisi URL penuh, gunakan URL tersebut.
                            if (
                                $foto !== '' &&
                                preg_match('#^https?://#i', $foto)
                            ) {
                                $fotoUrl = $foto;
                            } elseif ($foto !== '') {
                                $namaFile = basename(str_replace('\\', '/', $foto));

                                $kandidat = [
                                    '../../uploads/bunga/' . $namaFile,
                                    '../../assets/img/bunga/' . $namaFile
                                ];

                                $fotoUrl = '../../assets/img/bunga/default.png';

                                foreach ($kandidat as $path) {
                                    if (is_file(__DIR__ . '/' . $path)) {
                                        $fotoUrl = $path;
                                        break;
                                    }
                                }
                            } else {
                                $fotoUrl = '../../assets/img/bunga/default.png';
                            }
                        ?>

                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td>
                                <div class="bunga-image">
                                    <img
                                        src="<?= e($fotoUrl) ?>"
                                        alt="<?= e($bunga['Nama_bunga']) ?>"
                                        loading="lazy"
                                        onerror="this.onerror=null;this.src='../../assets/img/bunga/default.png';"
                                    >
                                </div>
                            </td>

                            <td>
                                <div class="bunga-name">
                                    <strong><?= e($bunga['Nama_bunga']) ?></strong>
                                    <small>
                                        ID Bunga: #<?= (int)$bunga['Bunga_id'] ?>
                                    </small>
                                </div>
                            </td>

                            <td>
                                <strong class="bunga-stock-number">
                                    <?= number_format($stok, 0, ',', '.') ?>
                                </strong>
                            </td>

                            <td>
    <?php if ($stok > 0): ?>
        <span class="stock-badge tersedia">
            Tersedia
        </span>
    <?php else: ?>
        <span class="stock-badge habis">
            Tidak Tersedia
        </span>
    <?php endif; ?>
</td>

                            <td>
                                <div class="bunga-actions">

                                    <a
                                        href="edit.php?id=<?= (int)$bunga['Bunga_id'] ?>"
                                        class="action-btn edit"
                                        title="Edit bunga"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>

                                    <form
                                        method="POST"
                                        action="hapus.php"
                                        class="delete-form"
                                        onsubmit="return confirm('Yakin ingin menghapus bunga <?= e($bunga['Nama_bunga']) ?>? Data yang sudah digunakan dalam pesanan mungkin tidak dapat dihapus.');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$bunga['Bunga_id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action-btn delete"
                                            title="Hapus bunga"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="bunga-empty">
                            <i class="fa-solid fa-seedling"></i>

                            <h3>Data bunga belum tersedia</h3>

                            <p>
                                <?php if ($keyword !== ''): ?>
                                    Tidak ditemukan bunga dengan nama
                                    "<?= e($keyword) ?>".
                                <?php else: ?>
                                    Silakan tambahkan data bunga terlebih dahulu.
                                <?php endif; ?>
                            </p>

                            <?php if ($keyword === ''): ?>
                                <a href="tambah.php" class="btn-tambah-bunga">
                                    <i class="fa-solid fa-plus"></i>
                                    Tambah Bunga
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>
        </div>

        <div class="bunga-card-footer">
            Menampilkan <?= count($dataBunga) ?> data bunga
        </div>

    </section>

</main>

<script>
const menuToggle = document.getElementById('menuToggle');
const adminNavigation = document.getElementById('adminNavigation');

if (menuToggle && adminNavigation) {
    menuToggle.addEventListener('click', function () {
        const terbuka = adminNavigation.classList.toggle('show');

        menuToggle.setAttribute('aria-expanded', terbuka ? 'true' : 'false');

        menuToggle.innerHTML = terbuka
            ? '<i class="fa-solid fa-xmark"></i>'
            : '<i class="fa-solid fa-bars"></i>';
    });
}
</script>

</body>
</html>
