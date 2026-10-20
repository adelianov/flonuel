
<?php
session_start();
require_once '../../config/koneksi.php';

/* CEK LOGIN */
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../../login.php');
    exit;
}

if (!isset($conn) || !$conn) {
    die('Koneksi database gagal.');
}

/* TOKEN KEAMANAN FORM */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* FUNGSI */
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return number_format((float)$angka, 0, ',', '.');
}

function statusPembayaran($status)
{
    $daftar = [
        'menunggu'      => ['Menunggu Verifikasi', 'waiting'],
        'diverifikasi'  => ['Terverifikasi', 'success'],
        'ditolak'       => ['Ditolak', 'cancelled'],
        'pending'       => ['Menunggu Verifikasi', 'waiting'],
        'berhasil'      => ['Berhasil', 'success']
    ];

    return $daftar[$status] ?? [
        ucfirst(str_replace('_', ' ', (string)$status)),
        'waiting'
    ];
}

/* PENCARIAN DAN FILTER */
$keyword = trim($_GET['keyword'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$status_valid = ['', 'menunggu', 'diverifikasi', 'ditolak'];

if (!in_array($status_filter, $status_valid, true)) {
    $status_filter = '';
}

$where = [];
$params = [];
$types = '';

if ($keyword !== '') {
    $where[] = "(
        p.Kode_pesanan LIKE ?
        OR u.Nama LIKE ?
        OR u.Username LIKE ?
    )";

    $cari = '%' . $keyword . '%';
    $params[] = $cari;
    $params[] = $cari;
    $params[] = $cari;
    $types .= 'sss';
}

if ($status_filter !== '') {
    $where[] = 'pb.Status_pembayaran = ?';
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* STATISTIK PEMBAYARAN */
$statistik = [
    'total' => 0,
    'menunggu' => 0,
    'diverifikasi' => 0,
    'ditolak' => 0
];

$sql_statistik = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(
            CASE WHEN Status_pembayaran IN ('menunggu', 'pending')
            THEN 1 ELSE 0 END
        ), 0) AS menunggu,
        COALESCE(SUM(
            CASE WHEN Status_pembayaran IN ('diverifikasi', 'berhasil')
            THEN 1 ELSE 0 END
        ), 0) AS diverifikasi,
        COALESCE(SUM(
            CASE WHEN Status_pembayaran = 'ditolak'
            THEN 1 ELSE 0 END
        ), 0) AS ditolak
    FROM pembayaran
";

$result_statistik = mysqli_query($conn, $sql_statistik);

if ($result_statistik) {
    $statistik = mysqli_fetch_assoc($result_statistik);
}

/* AMBIL DATA PEMBAYARAN */
$sql = "
    SELECT
        pb.Pembayaran_id,
        pb.Pesanan_id,
        pb.Tanggal_pembayaran,
        pb.Jumlah_pembayaran,
        pb.Bukti_pembayaran,
        pb.Status_pembayaran,
        pb.Tanggal_Verifikasi,
        p.Kode_pesanan,
        p.Tanggal_pesanan,
        p.total_harga,
        p.status_pesanan,
        u.Nama AS nama_pelanggan,
        u.Username AS username
    FROM pembayaran pb
    LEFT JOIN pesanan p
        ON pb.Pesanan_id = p.Pesanan_id
    LEFT JOIN user u
        ON p.User_id = u.User_id
    $where_sql
    ORDER BY pb.Pembayaran_id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die('Gagal mengambil data pembayaran: ' . e(mysqli_error($conn)));
}

if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result_pembayaran = mysqli_stmt_get_result($stmt);

if (!$result_pembayaran) {
    die('Gagal membaca data pembayaran.');
}

/* FORMAT TANGGAL */
function tanggalPembayaran($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $time = strtotime($tanggal);
    return $time ? date('d/m/Y H:i', $time) : '-';
}

/* PATH BUKTI */
function pathBukti($file)
{
    $file = trim((string)$file);

    if ($file === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $file)) {
        return $file;
    }

    $file = str_replace('\\', '/', $file);
    $file = ltrim($file, '/');

    if (
        strpos($file, 'uploads/') === 0 ||
        strpos($file, 'assets/') === 0
    ) {
        return '../../' . $file;
    }

    return '../../uploads/pembayaran/' . basename($file);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pembayaran</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-pembayaran.css">
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

    <button class="mobile-menu-button" id="mobileMenuButton"
            type="button" aria-label="Buka navigasi">
        <i class="fa-solid fa-bars"></i>
    </button>

    <nav class="admin-navigation" id="adminNavigation">
        <a href="../dashboard.php">Dashboard</a>
        <a href="../pesanan/index.php">Data Pesanan</a>
        <a href="index.php" class="active">Data Pembayaran</a>
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
            <strong><?= e($_SESSION['nama'] ?? $_SESSION['Nama'] ?? 'Admin') ?></strong>
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

<main class="pembayaran-container">

    <section class="page-heading">
        <h2>DATA PEMBAYARAN</h2>
    </section>

    <?php if (isset($_GET['pesan'])): ?>
        <?php
        $pesan = $_GET['pesan'];
        $jenis = $_GET['jenis'] ?? 'success';

        $pesan_list = [
            'success' => 'Pembayaran berhasil diproses.',
            'error' => 'Terjadi kesalahan saat memproses pembayaran.',
            'invalid' => 'Data pembayaran tidak valid atau sudah diproses.',
            'notfound' => 'Data pembayaran tidak ditemukan.'
        ];
        ?>
        <div class="payment-alert <?= $jenis === 'error' ? 'error' : 'success' ?>">
            <i class="fa-solid <?= $jenis === 'error'
                ? 'fa-circle-exclamation'
                : 'fa-circle-check' ?>"></i>
            <?= e($pesan_list[$pesan] ?? 'Proses selesai.') ?>
        </div>
    <?php endif; ?>

    <section class="pembayaran-stats">

        <div class="pembayaran-stat-card">
            <div class="pembayaran-stat-content">
                <span>Total Pembayaran</span>
                <strong><?= (int)$statistik['total'] ?></strong>
            </div>

            </div>
        </div>

        <div class="pembayaran-stat-card">
            <div class="pembayaran-stat-content">
                <span>Menunggu Verifikasi</span>
                <strong><?= (int)$statistik['menunggu'] ?></strong>
            </div>
            
        </div>

        <div class="pembayaran-stat-card">
            <div class="pembayaran-stat-content">
                <span>Terverifikasi</span>
                <strong><?= (int)$statistik['diverifikasi'] ?></strong>
            </div>
            
        </div>

        <div class="pembayaran-stat-card">
            <div class="pembayaran-stat-content">
                <span>Ditolak</span>
                <strong><?= (int)$statistik['ditolak'] ?></strong>
            </div>
            
        </div>

    </section>

    <section class="pembayaran-section">

        <div class="pembayaran-section-header">
            <div class="section-heading-left">

                <div>
                    <span>DATA TRANSAKSI</span>
                    <h2>Semua Pembayaran</h2>
                </div>
            </div>

        
        </div>

        <form method="GET" class="payment-filter">
            <div class="payment-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari kode pesanan atau pelanggan..."
                    value="<?= e($keyword) ?>"
                >
            </div>

            <select name="status" aria-label="Filter status pembayaran">
                <option value="">Semua Status</option>
                <option value="menunggu"
                    <?= $status_filter === 'menunggu' ? 'selected' : '' ?>>
                    Menunggu Verifikasi
                </option>
                <option value="diverifikasi"
                    <?= $status_filter === 'diverifikasi' ? 'selected' : '' ?>>
                    Terverifikasi
                </option>
                <option value="ditolak"
                    <?= $status_filter === 'ditolak' ? 'selected' : '' ?>>
                    Ditolak
                </option>
            </select>

            <button type="submit" class="payment-filter-button">
                <i class="fa-solid fa-filter"></i>
                Filter
            </button>

            <a href="index.php" class="payment-reset-button">Reset</a>
        </form>

        <div class="table-wrapper">
            <?php if (mysqli_num_rows($result_pembayaran) === 0): ?>

                <div class="empty-pembayaran">
                    <div class="empty-icon">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                    <h3>Belum Ada Pembayaran</h3>
                    <p>Data pembayaran pelanggan akan muncul di sini.</p>
                </div>

            <?php else: ?>

                <table class="pembayaran-table">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>KODE PESANAN</th>
                            <th>PELANGGAN</th>
                            <th>TANGGAL BAYAR</th>
                            <th>JUMLAH</th>
                            <th>BUKTI</th>
                            <th>STATUS</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $no = 1; ?>

                    <?php while ($item = mysqli_fetch_assoc($result_pembayaran)): ?>
                        <?php
                        [$label_status, $class_status] =
                            statusPembayaran($item['Status_pembayaran']);

                        $nama = $item['nama_pelanggan'] ?: 'Pelanggan';
                        $bukti = pathBukti($item['Bukti_pembayaran'] ?? '');
                        ?>

                        <tr>
                            <td class="number-column"><?= $no++ ?></td>

                            <td>
                                <div class="payment-order-code">
                                    <strong><?= e($item['Kode_pesanan'] ?? '-') ?></strong>
                                    <span>
                                        ID Pembayaran #<?= (int)$item['Pembayaran_id'] ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                    <div>
                                        <strong><?= e($nama) ?></strong>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?= tanggalPembayaran($item['Tanggal_pembayaran']) ?>
                            </td>

                            <td>
                                <strong class="payment-amount">
                                    Rp <?= rupiah($item['Jumlah_pembayaran']) ?>
                                </strong>
                            </td>

                            <td>
                                <?php if ($bukti !== ''): ?>
                                    <button type="button"
                                            class="proof-button"
                                            data-image="<?= e($bukti) ?>"
                                            data-code="<?= e($item['Kode_pesanan'] ?? '-') ?>">
                                        <i class="fa-solid fa-image"></i>
                                        Lihat Bukti
                                    </button>
                                <?php else: ?>
                                    <span class="no-proof">Belum ada bukti</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="payment-status <?= e($class_status) ?>">
                                    <span class="status-dot"></span>
                                    <?= e($label_status) ?>
                                </span>

                                <?php if (!empty($item['Tanggal_Verifikasi'])): ?>
                                    <span class="payment-date-note">
                                        <?= tanggalPembayaran($item['Tanggal_Verifikasi']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (in_array(
                                    $item['Status_pembayaran'],
                                    ['menunggu', 'pending'],
                                    true
                                )): ?>

                                    <div class="payment-actions">
                                        <form action="verifikasi.php" method="POST"
                                              onsubmit="return confirm('Verifikasi pembayaran ini?')">
                                            <input type="hidden" name="pembayaran_id"
                                                   value="<?= (int)$item['Pembayaran_id'] ?>">
                                            <input type="hidden" name="aksi" value="verifikasi">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION['csrf_token']) ?>">

                                            <button type="submit" class="verify-button">
                                                <i class="fa-solid fa-check"></i>
                                                Verifikasi
                                            </button>
                                        </form>

                                        <form action="verifikasi.php" method="POST"
                                              onsubmit="return confirm('Tolak pembayaran ini?')">
                                            <input type="hidden" name="pembayaran_id"
                                                   value="<?= (int)$item['Pembayaran_id'] ?>">
                                            <input type="hidden" name="aksi" value="tolak">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION['csrf_token']) ?>">

                                            <button type="submit" class="reject-button">
                                                <i class="fa-solid fa-xmark"></i>
                                                Tolak
                                            </button>
                                        </form>
                                    </div>

                                <?php else: ?>
                                    <span class="payment-processed">
                                        <i class="fa-solid fa-check-double"></i>
                                        Diproses
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>
                    </tbody>
                </table>

            <?php endif; ?>
        </div>
    </section>

</main>

<!-- MODAL BUKTI PEMBAYARAN -->
<div class="proof-modal" id="proofModal">
    <div class="proof-modal-content">
        <div class="proof-modal-header">
            <div>
                <h3>Bukti Pembayaran</h3>
                <span id="proofCode"></span>
            </div>
            <button type="button" id="closeProof" class="close-proof"
                    aria-label="Tutup">&times;</button>
        </div>

        <div class="proof-image-wrap">
            <img src="" alt="Bukti pembayaran" id="proofImage">
            <p id="proofError" hidden>
                Gambar tidak dapat ditampilkan. Periksa lokasi file bukti pembayaran.
            </p>
        </div>

        <div class="proof-modal-footer">
            <a href="#" target="_blank" rel="noopener"
               id="openProof" class="open-proof-button">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                Buka Gambar
            </a>
        </div>
    </div>
</div>

<script>
const menuButton = document.getElementById('mobileMenuButton');
const navigation = document.getElementById('adminNavigation');

menuButton.addEventListener('click', function () {
    navigation.classList.toggle('show');

    const icon = menuButton.querySelector('i');
    icon.classList.toggle('fa-bars');
    icon.classList.toggle('fa-xmark');
});

const proofModal = document.getElementById('proofModal');
const proofImage = document.getElementById('proofImage');
const proofError = document.getElementById('proofError');
const proofCode = document.getElementById('proofCode');
const openProof = document.getElementById('openProof');

document.querySelectorAll('.proof-button').forEach(function (button) {
    button.addEventListener('click', function () {
        const imageUrl = this.dataset.image;

        proofImage.style.display = 'block';
        proofError.hidden = true;
        proofImage.src = imageUrl;
        proofCode.textContent = this.dataset.code;
        openProof.href = imageUrl;

        proofModal.classList.add('show');
    });
});

proofImage.addEventListener('error', function () {
    this.style.display = 'none';
    proofError.hidden = false;
});

function closeProofModal() {
    proofModal.classList.remove('show');
    proofImage.src = '';
}

document.getElementById('closeProof')
    .addEventListener('click', closeProofModal);

proofModal.addEventListener('click', function (event) {
    if (event.target === proofModal) {
        closeProofModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeProofModal();
    }
});
</script>

</body>
</html>
<?php
mysqli_stmt_close($stmt);
?>