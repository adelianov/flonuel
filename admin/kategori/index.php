
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

/* TOKEN KEAMANAN */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* FUNGSI */
function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* PROSES TAMBAH, EDIT, DAN HAPUS */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        header('Location: index.php?pesan=invalid&jenis=error');
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $nama = trim($_POST['nama_kategori'] ?? '');
        $id = (int) ($_POST['kategori_id'] ?? 0);

        if ($nama === '' || mb_strlen($nama) > 100) {
            header('Location: index.php?pesan=invalid&jenis=error');
            exit;
        }

        if ($aksi === 'tambah') {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO kategori_produk (Nama_kategori)
                 VALUES (?)"
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $nama);
                $berhasil = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                header(
                    'Location: index.php?pesan=' .
                    ($berhasil ? 'tambah' : 'error') .
                    '&jenis=' . ($berhasil ? 'success' : 'error')
                );
                exit;
            }
        }

        if ($aksi === 'edit' && $id > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE kategori_produk
                 SET Nama_kategori = ?
                 WHERE Kategori_id = ?"
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $nama, $id);
                $berhasil = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                header(
                    'Location: index.php?pesan=' .
                    ($berhasil ? 'edit' : 'error') .
                    '&jenis=' . ($berhasil ? 'success' : 'error')
                );
                exit;
            }
        }

        header('Location: index.php?pesan=error&jenis=error');
        exit;
    }

    if ($aksi === 'hapus') {
        $id = (int) ($_POST['kategori_id'] ?? 0);

        if ($id <= 0) {
            header('Location: index.php?pesan=invalid&jenis=error');
            exit;
        }

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM kategori_produk
             WHERE Kategori_id = ?"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            $berhasil = mysqli_stmt_execute($stmt);
            $kode_error = mysqli_errno($conn);
            mysqli_stmt_close($stmt);

            if ($berhasil) {
                header('Location: index.php?pesan=hapus&jenis=success');
                exit;
            }

            if ($kode_error === 1451) {
                header('Location: index.php?pesan=terpakai&jenis=error');
                exit;
            }
        }

        header('Location: index.php?pesan=error&jenis=error');
        exit;
    }
}

/* PESAN NOTIFIKASI */
$pesan = $_GET['pesan'] ?? '';
$jenis = ($_GET['jenis'] ?? 'success') === 'error'
    ? 'error'
    : 'success';

$daftar_pesan = [
    'tambah'   => 'Kategori berhasil ditambahkan.',
    'edit'     => 'Kategori berhasil diperbarui.',
    'hapus'    => 'Kategori berhasil dihapus.',
    'invalid'  => 'Data tidak valid. Periksa kembali input kamu.',
    'terpakai' => 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.',
    'error'    => 'Terjadi kesalahan saat memproses kategori.'
];

/* PENCARIAN */
$keyword = trim($_GET['keyword'] ?? '');

if (mb_strlen($keyword) > 100) {
    $keyword = mb_substr($keyword, 0, 100);
}

/* STATISTIK */
$statistik = [
    'total' => 0,
    'kategori_terpakai' => 0,
    'total_produk' => 0
];

$sql_statistik = "
    SELECT
        COUNT(*) AS total,
        COALESCE(
            SUM(
                CASE WHEN jumlah_produk > 0 THEN 1 ELSE 0 END
            ), 0
        ) AS kategori_terpakai,
        COALESCE(SUM(jumlah_produk), 0) AS total_produk
    FROM (
        SELECT
            k.Kategori_id,
            COUNT(p.Kategori_id) AS jumlah_produk
        FROM kategori_produk k
        LEFT JOIN produk p
            ON p.Kategori_id = k.Kategori_id
        GROUP BY k.Kategori_id
    ) AS data_kategori
";

$result_statistik = mysqli_query($conn, $sql_statistik);

if ($result_statistik) {
    $statistik = mysqli_fetch_assoc($result_statistik);
}

/* DATA KATEGORI */
$sql = "
    SELECT
        k.Kategori_id,
        k.Nama_kategori,
        COUNT(p.Kategori_id) AS jumlah_produk
    FROM kategori_produk k
    LEFT JOIN produk p
        ON p.Kategori_id = k.Kategori_id
";

if ($keyword !== '') {
    $sql .= " WHERE k.Nama_kategori LIKE ? ";
}

$sql .= "
    GROUP BY k.Kategori_id, k.Nama_kategori
    ORDER BY k.Kategori_id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die('Gagal menyiapkan data kategori.');
}

if ($keyword !== '') {
    $cari = '%' . $keyword . '%';
    mysqli_stmt_bind_param($stmt, 's', $cari);
}

mysqli_stmt_execute($stmt);
$result_kategori = mysqli_stmt_get_result($stmt);

if (!$result_kategori) {
    die('Gagal membaca data kategori.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Kategori</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-pembayaran.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

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

        .kategori-stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fce7f3;
            color: #c13f7b;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .kategori-stat-icon.green {
            background: #e1f7e9;
            color: #23834b;
        }

        .kategori-stat-icon.purple {
            background: #eee7ff;
            color: #7650c8;
        }

        .kategori-stat-card {
            padding: 22px;
        }

        .kategori-stat-card .pembayaran-stat-content {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .kategori-stat-card .pembayaran-stat-content strong {
            font-size: 26px;
        }

        .kategori-heading-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .kategori-add-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            border: none;
            border-radius: 9px;
            background: #d94682;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
        }

        .kategori-add-button:hover {
            background: #bd326c;
        }

        .kategori-search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .kategori-search-form .payment-search {
            flex: 1;
            min-width: 220px;
        }

        .kategori-search-form input {
            width: 100%;
            box-sizing: border-box;
        }

        .kategori-reset-button {
            padding: 11px 15px;
            border-radius: 8px;
            background: #f1f1f1;
            color: #444;
            text-decoration: none;
        }

        .kategori-table {
            width: 100%;
            border-collapse: collapse;
        }

        .kategori-table th,
        .kategori-table td {
            padding: 16px 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .kategori-table th {
            white-space: nowrap;
        }

        .kategori-table tbody tr:hover {
            background: #fffafd;
        }

        .kategori-name {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .kategori-name-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            color: #c13f7b;
            background: #fff0f6;
        }

        .kategori-product-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            border-radius: 20px;
            background: #f4eaff;
            color: #7650c8;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .kategori-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kategori-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            border-radius: 7px;
            padding: 9px 11px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }

        .kategori-edit-button {
            background: #fff0d6;
            color: #925900;
        }

        .kategori-delete-button {
            background: #ffe5e5;
            color: #c62828;
        }

        .kategori-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
        }

        .kategori-alert.success {
            background: #e7f8ed;
            color: #176534;
        }

        .kategori-alert.error {
            background: #ffeaea;
            color: #a32121;
        }

        .kategori-empty {
            text-align: center;
            padding: 45px 20px;
        }

        .kategori-empty i {
            font-size: 36px;
            color: #d8a2bc;
            margin-bottom: 15px;
        }

        .kategori-empty h3 {
            margin-bottom: 8px;
        }

        .kategori-empty p {
            color: #777;
        }

        .kategori-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            padding: 20px;
            align-items: center;
            justify-content: center;
            background: rgba(20, 10, 20, .48);
        }

        .kategori-modal.show {
            display: flex;
        }

        .kategori-modal-content {
            width: 100%;
            max-width: 460px;
            background: white;
            border-radius: 14px;
            padding: 25px;
            box-sizing: border-box;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .15);
        }

        .kategori-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .kategori-modal-header h2 {
            margin: 0;
            font-size: 21px;
        }

        .kategori-close {
            border: none;
            background: transparent;
            color: #777;
            font-size: 26px;
            cursor: pointer;
        }

        .kategori-form-group {
            margin-bottom: 20px;
        }

        .kategori-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .kategori-form-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 13px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        .kategori-form-group input:focus {
            border-color: #d94682;
        }

        .kategori-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .kategori-cancel-button {
            border: none;
            border-radius: 8px;
            padding: 11px 15px;
            background: #eee;
            color: #333;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .kategori-heading-actions {
                justify-content: flex-start;
                width: 100%;
            }

            .kategori-table {
                min-width: 700px;
            }

            .kategori-actions {
                flex-wrap: wrap;
            }
        }
        .kategori-stat-card {
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
}

.kategori-stat-card .pembayaran-stat-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    text-align: center;
}
    </style>
</head>

<body>

<!-- HEADER -->
<header class="admin-header">

    <div class="brand">
        <a href="../dashboard.php">
            <div class="brand-logo">
                <img src="../../assets/img/logo flonuel.png"
                     alt="Logo FlowerShop">
            </div>

            <div class="brand-text">
                <span>Admin Panel</span>
            </div>
        </a>
    </div>

    <button class="mobile-menu-button"
            id="mobileMenuButton"
            type="button"
            aria-label="Buka navigasi">
        <i class="fa-solid fa-bars"></i>
    </button>

    <nav class="admin-navigation" id="adminNavigation">
        <a href="../dashboard.php">Dashboard</a>
        <a href="../pesanan/index.php">Data Pesanan</a>
        <a href="../pembayaran/index.php">Data Pembayaran</a>
        <a href="../produk/index.php">Produk</a>
        <a href="../addon/index.php">Add On</a>
        <a href="../bunga/index.php">Bunga</a>
        <a href="index.php" class="active">Kategori</a>
        <a href="../wrapping/index.php">Wrapping</a>
        <a href="../laporan_keuangan.php">Laporan Keuangan</a>
    </nav>

    <div class="header-user">
        <div class="header-avatar">
            <i class="fa-solid fa-user"></i>
        </div>

        <div class="header-user-info">
            <strong>
                <?= e($_SESSION['nama'] ?? $_SESSION['Nama'] ?? 'Admin') ?>
            </strong>
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

    <!-- JUDUL HALAMAN -->
    <section class="page-heading">
        <h2>KELOLA KATEGORI</h2>
    </section>

    <!-- NOTIFIKASI -->
    <?php if (isset($daftar_pesan[$pesan])): ?>
        <div class="kategori-alert <?= e($jenis) ?>">
            <i class="fa-solid <?= $jenis === 'error'
                ? 'fa-circle-exclamation'
                : 'fa-circle-check' ?>"></i>

            <span><?= e($daftar_pesan[$pesan]) ?></span>
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <section class="pembayaran-stats">
    <div class="pembayaran-stat-card kategori-stat-card">
        <div class="pembayaran-stat-content">
            <span>Total Kategori</span>
            <strong><?= (int) $statistik['total'] ?></strong>
        </div>
    </div>
</section>

    <!-- TABEL KATEGORI -->
    <section class="pembayaran-section">

        <div class="pembayaran-section-header">
            <div class="section-heading-left">
                <div>
                    <span>DATA PRODUK</span>
                    <h2>Semua Kategori</h2>
                </div>
            </div>

            <div class="kategori-heading-actions">
                <button type="button"
                        class="kategori-add-button"
                        onclick="bukaTambah()">
                    <i class="fa-solid fa-plus"></i>
                    Tambah Kategori
                </button>
            </div>
        </div>

        <!-- PENCARIAN -->
        <form method="GET" class="kategori-search-form">

            <div class="payment-search">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input type="text"
                       name="keyword"
                       placeholder="Cari nama kategori..."
                       value="<?= e($keyword) ?>">
            </div>

            <button type="submit" class="payment-filter-button">
                <i class="fa-solid fa-magnifying-glass"></i>
                Cari
            </button>

            <a href="index.php" class="kategori-reset-button">
                Reset
            </a>

        </form>

        <div class="table-wrapper">

            <?php if (mysqli_num_rows($result_kategori) === 0): ?>

                <div class="kategori-empty">
                    <i class="fa-solid fa-layer-group"></i>
                    <h3>Belum Ada Kategori</h3>
                    <p>
                        <?php if ($keyword !== ''): ?>
                            Kategori yang kamu cari tidak ditemukan.
                        <?php else: ?>
                            Tambahkan kategori untuk mengelompokkan produk bunga.
                        <?php endif; ?>
                    </p>
                </div>

            <?php else: ?>

                <table class="pembayaran-table kategori-table">

                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>NAMA KATEGORI</th>
                            <th>JUMLAH PRODUK</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php $no = 1; ?>

                    <?php while ($item = mysqli_fetch_assoc($result_kategori)): ?>

                        <tr>
                            <td class="number-column">
                                <?= $no++ ?>
                            </td>

                            <td>
                                <div class="kategori-name">
                                    <!-- <div class="kategori-name-icon">
                                        <i class="fa-solid fa-leaf"></i> -->
                                    </div>

                                    <span>
                                        <?= e($item['Nama_kategori']) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <span class="kategori-product-count">
                                    <i class="fa-solid fa-box"></i>
                                    <?= (int) $item['jumlah_produk'] ?> produk
                                </span>
                            </td>

                            <td>
                                <div class="kategori-actions">

                                    <button type="button"
                                            class="kategori-action-button kategori-edit-button"
                                            onclick='bukaEdit(
                                                <?= (int) $item["Kategori_id"] ?>,
                                                <?= json_encode(
                                                    $item["Nama_kategori"],
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_QUOT |
                                                    JSON_HEX_TAG |
                                                    JSON_HEX_AMP
                                                ) ?>
                                            )'>
                                        <i class="fa-solid fa-pen"></i>
                                        Edit
                                    </button>

                                    <form method="POST"
                                          action="index.php"
                                          onsubmit="return confirm('Yakin ingin menghapus kategori ini? Kategori yang masih digunakan produk tidak dapat dihapus.')">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">

                                        <input type="hidden"
                                               name="aksi"
                                               value="hapus">

                                        <input type="hidden"
                                               name="kategori_id"
                                               value="<?= (int) $item['Kategori_id'] ?>">

                                        <button type="submit"
                                                class="kategori-action-button kategori-delete-button">
                                            <i class="fa-solid fa-trash"></i>
                                            Hapus
                                        </button>

                                    </form>

                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>
    </section>

</main>

<!-- MODAL TAMBAH / EDIT -->
<div class="kategori-modal" id="modalKategori">

    <div class="kategori-modal-content">

        <div class="kategori-modal-header">
            <h2 id="judulModal">Tambah Kategori</h2>

            <button type="button"
                    class="kategori-close"
                    onclick="tutupModal()"
                    aria-label="Tutup">
                &times;
            </button>
        </div>

        <form method="POST" action="index.php">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                   name="aksi"
                   id="aksi"
                   value="tambah">

            <input type="hidden"
                   name="kategori_id"
                   id="kategori_id">

            <div class="kategori-form-group">
                <label for="nama_kategori">Nama Kategori</label>

                <input type="text"
                       name="nama_kategori"
                       id="nama_kategori"
                       maxlength="100"
                       placeholder="Contoh: Bunga Mawar"
                       required>
            </div>

            <div class="kategori-modal-actions">

                <button type="button"
                        class="kategori-cancel-button"
                        onclick="tutupModal()">
                    Batal
                </button>

                <button type="submit" class="kategori-add-button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan
                </button>

            </div>

        </form>
    </div>
</div>

<script>
const menuButton = document.getElementById('mobileMenuButton');
const navigation = document.getElementById('adminNavigation');

if (menuButton && navigation) {
    menuButton.addEventListener('click', function () {
        navigation.classList.toggle('show');

        const icon = menuButton.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-xmark');
    });
}

const modalKategori = document.getElementById('modalKategori');

function bukaTambah() {
    document.getElementById('judulModal').textContent = 'Tambah Kategori';
    document.getElementById('aksi').value = 'tambah';
    document.getElementById('kategori_id').value = '';
    document.getElementById('nama_kategori').value = '';

    modalKategori.classList.add('show');
    document.getElementById('nama_kategori').focus();
}

function bukaEdit(id, nama) {
    document.getElementById('judulModal').textContent = 'Edit Kategori';
    document.getElementById('aksi').value = 'edit';
    document.getElementById('kategori_id').value = id;
    document.getElementById('nama_kategori').value = nama;

    modalKategori.classList.add('show');
    document.getElementById('nama_kategori').focus();
}

function tutupModal() {
    modalKategori.classList.remove('show');
}

modalKategori.addEventListener('click', function (event) {
    if (event.target === modalKategori) {
        tutupModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        tutupModal();
    }
});
</script>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>