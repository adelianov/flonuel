
<?php
session_start();

require_once '../../config/koneksi.php';

/* CEK LOGIN ADMIN */
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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* TOKEN KEAMANAN */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* FUNGSI */
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectWrapping($keyword = '')
{
    $url = 'index.php';

    if ($keyword !== '') {
        $url .= '?keyword=' . urlencode($keyword);
    }

    header('Location: ' . $url);
    exit;
}

$pesan = '';
$jenis_pesan = '';

/* SIMPAN KEYWORD UNTUK REDIRECT */
$keywordRedirect = trim($_POST['keyword_redirect'] ?? '');

/* PROSES TAMBAH, EDIT, HAPUS, DAN UBAH STATUS */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
        $jenis_pesan = 'error';
    } else {
        $aksi = $_POST['aksi'] ?? '';
        $nama = trim($_POST['Warna_wrapping'] ?? '');
        $id = (int)($_POST['Wrapping_id'] ?? 0);

        try {
            /* TAMBAH WRAPPING */
            if ($aksi === 'tambah') {
                if ($nama === '') {
                    throw new Exception('Nama wrapping wajib diisi.');
                }

                if (mb_strlen($nama) > 100) {
                    throw new Exception(
                        'Nama wrapping maksimal 100 karakter.'
                    );
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO wrapping
                        (Warna_wrapping, Status_wrapping)
                     VALUES (?, 'Ready')"
                );

                mysqli_stmt_bind_param($stmt, 's', $nama);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $_SESSION['flash_wrapping'] = [
                    'jenis' => 'success',
                    'pesan' => 'Wrapping berhasil ditambahkan.'
                ];

                redirectWrapping($keywordRedirect);
            }

            /* EDIT WRAPPING */
            elseif ($aksi === 'edit') {
                if ($id <= 0) {
                    throw new Exception('ID wrapping tidak valid.');
                }

                if ($nama === '') {
                    throw new Exception('Nama wrapping wajib diisi.');
                }

                if (mb_strlen($nama) > 100) {
                    throw new Exception(
                        'Nama wrapping maksimal 100 karakter.'
                    );
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE wrapping
                     SET Warna_wrapping = ?
                     WHERE Wrapping_id = ?"
                );

                mysqli_stmt_bind_param($stmt, 'si', $nama, $id);
                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) === 0) {
                    mysqli_stmt_close($stmt);

                    $cek = mysqli_prepare(
                        $conn,
                        "SELECT Wrapping_id
                         FROM wrapping
                         WHERE Wrapping_id = ?"
                    );

                    mysqli_stmt_bind_param($cek, 'i', $id);
                    mysqli_stmt_execute($cek);
                    $ada = mysqli_stmt_get_result($cek)->num_rows > 0;
                    mysqli_stmt_close($cek);

                    if (!$ada) {
                        throw new Exception('Wrapping tidak ditemukan.');
                    }
                } else {
                    mysqli_stmt_close($stmt);
                }

                $_SESSION['flash_wrapping'] = [
                    'jenis' => 'success',
                    'pesan' => 'Wrapping berhasil diperbarui.'
                ];

                redirectWrapping($keywordRedirect);
            }

            /* AKTIFKAN / NONAKTIFKAN WRAPPING */
            elseif ($aksi === 'toggle') {
                if ($id <= 0) {
                    throw new Exception('ID wrapping tidak valid.');
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE wrapping
                     SET Status_wrapping =
                         CASE
                             WHEN Status_wrapping = 'Ready'
                             THEN 'Tidak Ready'
                             ELSE 'Ready'
                         END
                     WHERE Wrapping_id = ?"
                );

                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) === 0) {
                    mysqli_stmt_close($stmt);

                    $cek = mysqli_prepare(
                        $conn,
                        "SELECT Wrapping_id
                         FROM wrapping
                         WHERE Wrapping_id = ?"
                    );

                    mysqli_stmt_bind_param($cek, 'i', $id);
                    mysqli_stmt_execute($cek);
                    $ada = mysqli_stmt_get_result($cek)->num_rows > 0;
                    mysqli_stmt_close($cek);

                    if (!$ada) {
                        throw new Exception('Wrapping tidak ditemukan.');
                    }
                } else {
                    mysqli_stmt_close($stmt);
                }

                $_SESSION['flash_wrapping'] = [
                    'jenis' => 'success',
                    'pesan' => 'Status wrapping berhasil diubah.'
                ];

                redirectWrapping($keywordRedirect);
            }

            /* HAPUS WRAPPING */
            elseif ($aksi === 'hapus') {
                if ($id <= 0) {
                    throw new Exception('ID wrapping tidak valid.');
                }

                // Periksa apakah wrapping digunakan dalam pesanan.
                $cek = mysqli_prepare(
                    $conn,
                    "SELECT COUNT(*) AS total
                     FROM pesanan
                     WHERE Wrapping_id = ?"
                );

                mysqli_stmt_bind_param($cek, 'i', $id);
                mysqli_stmt_execute($cek);

                $totalPesanan = (int)mysqli_fetch_assoc(
                    mysqli_stmt_get_result($cek)
                )['total'];

                mysqli_stmt_close($cek);

                if ($totalPesanan > 0) {
                    throw new Exception(
                        'Wrapping sudah digunakan dalam pesanan. ' .
                        'Nonaktifkan saja jika sudah tidak tersedia.'
                    );
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM wrapping
                     WHERE Wrapping_id = ?"
                );

                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) === 0) {
                    mysqli_stmt_close($stmt);
                    throw new Exception('Wrapping tidak ditemukan.');
                }

                mysqli_stmt_close($stmt);

                $_SESSION['flash_wrapping'] = [
                    'jenis' => 'success',
                    'pesan' => 'Wrapping berhasil dihapus.'
                ];

                redirectWrapping($keywordRedirect);
            } else {
                throw new Exception('Aksi tidak dikenal.');
            }
        } catch (Throwable $ex) {
            $pesan = $ex->getMessage();
            $jenis_pesan = 'error';
        }
    }
}

/* NOTIFIKASI SETELAH REDIRECT */
if (isset($_SESSION['flash_wrapping'])) {
    $pesan = $_SESSION['flash_wrapping']['pesan'];
    $jenis_pesan = $_SESSION['flash_wrapping']['jenis'];

    unset($_SESSION['flash_wrapping']);
}

/* PENCARIAN */
$keyword = trim($_GET['keyword'] ?? '');

/* STATISTIK */
$statistik = [
    'total' => 0,
    'digunakan' => 0,
    'pesanan' => 0,
    'ready' => 0,
    'tidak_ready' => 0
];

$sql_statistik = "
    SELECT
        (SELECT COUNT(*) FROM wrapping) AS total,

        (SELECT COUNT(DISTINCT w.Wrapping_id)
         FROM wrapping w
         INNER JOIN pesanan p
             ON p.Wrapping_id = w.Wrapping_id) AS digunakan,

        (SELECT COUNT(*) FROM pesanan) AS pesanan,

        (SELECT COUNT(*)
         FROM wrapping
         WHERE Status_wrapping = 'Ready') AS ready,

        (SELECT COUNT(*)
         FROM wrapping
         WHERE Status_wrapping = 'Tidak Ready') AS tidak_ready
";

$result_statistik = mysqli_query($conn, $sql_statistik);
$statistik = mysqli_fetch_assoc($result_statistik);

/* DATA WRAPPING */
$sql = "
    SELECT
        w.Wrapping_id,
        w.Warna_wrapping,
        w.Status_wrapping,
        COUNT(p.Pesanan_id) AS jumlah_pesanan
    FROM wrapping w
    LEFT JOIN pesanan p
        ON p.Wrapping_id = w.Wrapping_id
";

if ($keyword !== '') {
    $sql .= " WHERE w.Warna_wrapping LIKE ?";
}

$sql .= "
    GROUP BY
        w.Wrapping_id,
        w.Warna_wrapping,
        w.Status_wrapping
    ORDER BY w.Wrapping_id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if ($keyword !== '') {
    $cari = '%' . $keyword . '%';
    mysqli_stmt_bind_param($stmt, 's', $cari);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Wrapping</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-pembayaran.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .wrapping-container {
            padding-bottom: 40px;
        }

        .wrapping-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin: 24px 0;
        }

        .wrapping-stat-card {
            min-height: 115px;
            padding: 22px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #eee;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .04);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .wrapping-stat-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .wrapping-stat-content span {
            color: #666;
            font-size: 14px;
        }

        .wrapping-stat-content strong {
            font-size: 27px;
            color: #333;
        }

        .wrapping-section {
            background: #fff;
            padding: 24px;
            border-radius: 14px;
            border: 1px solid #eee;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .04);
            margin-bottom: 24px;
        }

        .wrapping-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .wrapping-section-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .wrapping-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .wrapping-search {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1;
        }

        .wrapping-search input,
        .wrapping-form input {
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
            box-sizing: border-box;
        }

        .wrapping-search input {
            width: 100%;
            max-width: 350px;
        }

        .wrapping-search input:focus,
        .wrapping-form input:focus {
            border-color: #b76e79;
            box-shadow: 0 0 0 3px rgba(183, 110, 121, .12);
        }

        .wrapping-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 13px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            transition: .2s;
        }

        .wrapping-btn-primary {
            background: #b76e79;
            color: #fff;
        }

        .wrapping-btn-primary:hover {
            background: #9f5964;
        }

        .wrapping-btn-secondary {
            background: #eee;
            color: #333;
        }

        .wrapping-btn-edit {
            background: #fff1d6;
            color: #946200;
        }

        .wrapping-btn-delete {
            background: #fde8e8;
            color: #b42318;
        }

        .wrapping-btn-toggle-off {
            background: #fde8e8;
            color: #b42318;
        }

        .wrapping-btn-toggle-on {
            background: #e1f7e8;
            color: #216e39;
        }

        .wrapping-actions {
            display: flex;
            gap: 7px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .wrapping-actions form {
            margin: 0;
        }

        .wrapping-table {
            width: 100%;
            border-collapse: collapse;
        }

        .wrapping-table th,
        .wrapping-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: middle;
        }

        .wrapping-table th {
            background: #faf7f7;
            color: #555;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .wrapping-table td {
            font-size: 14px;
        }

        .wrapping-table .text-center {
            text-align: center;
        }

        .wrapping-name {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #333;
        }

        .wrapping-name i {
            color: #b76e79;
            font-size: 18px;
        }

        .wrapping-count {
            display: inline-block;
            min-width: 30px;
            padding: 5px 10px;
            border-radius: 20px;
            background: #f7eeee;
            color: #92515b;
            text-align: center;
            font-weight: 600;
        }

        .status-ready,
        .status-not-ready {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-ready {
            background: #d1fae5;
            color: #065f46;
        }

        .status-not-ready {
            background: #fee2e2;
            color: #991b1b;
        }

        .wrapping-alert {
            padding: 13px 16px;
            margin: 20px 0;
            border-radius: 8px;
            font-size: 14px;
        }

        .wrapping-alert.success {
            background: #e8f7ed;
            color: #216e39;
            border: 1px solid #b8e5c5;
        }

        .wrapping-alert.error {
            background: #fdecec;
            color: #a12626;
            border: 1px solid #f3c1c1;
        }

        .wrapping-empty {
            text-align: center;
            padding: 40px 15px;
            color: #777;
        }

        .wrapping-empty i {
            display: block;
            font-size: 35px;
            margin-bottom: 12px;
            color: #c99ca2;
        }

        .wrapping-note {
            color: #777;
            font-size: 12px;
            margin-top: 15px;
            line-height: 1.6;
        }

        .wrapping-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            inset: 0;
            padding: 20px;
            background: rgba(0, 0, 0, .48);
            align-items: center;
            justify-content: center;
        }

        .wrapping-modal.show {
            display: flex;
        }

        .wrapping-modal-content {
            background: #fff;
            width: 100%;
            max-width: 460px;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .2);
        }

        .wrapping-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 22px;
        }

        .wrapping-modal-header h3 {
            margin: 0;
        }

        .wrapping-modal-close {
            background: transparent;
            border: none;
            font-size: 23px;
            cursor: pointer;
            color: #777;
        }

        .wrapping-form label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .wrapping-form input {
            width: 100%;
            margin-bottom: 20px;
        }

        .wrapping-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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

        .brand a {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
        }

        @media (max-width: 800px) {
            .wrapping-table {
                min-width: 850px;
            }

            .table-wrapper {
                overflow-x: auto;
            }
        }

        @media (max-width: 650px) {
            .wrapping-section {
                padding: 16px;
            }

            .wrapping-search {
                width: 100%;
            }

            .wrapping-search input {
                max-width: none;
                flex: 1;
                min-width: 0;
            }
        }
    </style>
</head>

<body>

<header class="admin-header">

    <div class="brand">
        <a href="../dashboard.php">
            <div class="brand-logo">
                <img
                    src="../../assets/img/logo flonuel.png"
                    alt="Logo Flonuel"
                >
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
        <a href="../kategori/index.php">Kategori</a>
        <a href="index.php" class="active">Wrapping</a>
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

<main class="pembayaran-container wrapping-container">

    <section class="page-heading">
        <h2>KELOLA WRAPPING</h2>
        <p>
            Kelola pilihan warna wrapping dan atur ketersediaannya
            untuk pesanan pelanggan.
        </p>
    </section>

    <?php if ($pesan !== ''): ?>
        <div class="wrapping-alert <?= e($jenis_pesan) ?>">
            <i class="fa-solid <?= $jenis_pesan === 'success'
                ? 'fa-circle-check'
                : 'fa-circle-exclamation' ?>"></i>
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <section class="wrapping-stats">

        <div class="wrapping-stat-card">
            <div class="wrapping-stat-content">
                <span>Total Wrapping</span>
                <strong><?= (int)$statistik['total'] ?></strong>
            </div>
        </div>

        <div class="wrapping-stat-card">
            <div class="wrapping-stat-content">
                <span>Wrapping Ready</span>
                <strong style="color:#059669">
                    <?= (int)$statistik['ready'] ?>
                </strong>
            </div>
        </div>

        <div class="wrapping-stat-card">
            <div class="wrapping-stat-content">
                <span>Wrapping Tidak Ready</span>
                <strong style="color:#dc2626">
                    <?= (int)$statistik['tidak_ready'] ?>
                </strong>
            </div>
        </div>

    </section>

    <!-- DAFTAR WRAPPING -->
    <section class="wrapping-section">

        <div class="wrapping-section-header">
            <h2>Daftar Wrapping</h2>

            <button type="button"
                    class="wrapping-btn wrapping-btn-primary"
                    onclick="bukaModalTambah()">
                <i class="fa-solid fa-plus"></i>
                Tambah Wrapping
            </button>
        </div>

        <div class="wrapping-toolbar">
            <form action="index.php"
                  method="GET"
                  class="wrapping-search">

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari nama wrapping..."
                    value="<?= e($keyword) ?>"
                >

                <button type="submit"
                        class="wrapping-btn wrapping-btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Cari
                </button>

                <?php if ($keyword !== ''): ?>
                    <a href="index.php"
                       class="wrapping-btn wrapping-btn-secondary">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </a>
                <?php endif; ?>

            </form>
        </div>

        <div class="table-wrapper">
            <table class="wrapping-table">

                <thead>
                    <tr>
                        <th class="text-center" style="width:60px">No.</th>
                        <th>Nama Wrapping</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Jumlah Pesanan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>

                            <td class="text-center">
                                <?= $no++ ?>
                            </td>

                            <td>
                                <span class="wrapping-name">
                                    <?= e($row['Warna_wrapping']) ?>
                                </span>
                            </td>

                            <!-- STATUS WRAPPING -->
                            <td class="text-center">
                                <?php if ($row['Status_wrapping'] === 'Ready'): ?>
                                    <span class="status-ready">
                                        Ready
                                    </span>
                                <?php else: ?>
                                    <span class="status-not-ready">
                                        <i class="fa-solid fa-circle-xmark"></i>
                                        Tidak Ready
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <span class="wrapping-count">
                                    <?= (int)$row['jumlah_pesanan'] ?>
                                </span>
                            </td>

                            <td>
                                <div class="wrapping-actions">

                                    <!-- EDIT -->
                                    <button
                                        type="button"
                                        class="wrapping-btn wrapping-btn-edit"
                                        onclick='bukaModalEdit(
                                            <?= (int)$row["Wrapping_id"] ?>,
                                            <?= json_encode(
                                                $row["Warna_wrapping"],
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ) ?>
                                        )'
                                        title="Edit wrapping">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Edit
                                    </button>

                                    <!-- AKTIFKAN / NONAKTIFKAN -->
                                    <form action="index.php"
                                          method="POST"
                                          onsubmit="return confirm(
                                            <?= json_encode(
                                                $row['Status_wrapping'] === 'Ready'
                                                    ? 'Nonaktifkan wrapping ini? Wrapping tidak akan tersedia untuk pesanan baru jika halaman pemesanan sudah menggunakan filter status.'
                                                    : 'Aktifkan kembali wrapping ini?'
                                            ) ?>
                                          )">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">

                                        <input type="hidden"
                                               name="aksi"
                                               value="toggle">

                                        <input type="hidden"
                                               name="Wrapping_id"
                                               value="<?= (int)$row['Wrapping_id'] ?>">

                                        <input type="hidden"
                                               name="keyword_redirect"
                                               value="<?= e($keyword) ?>">

                                        <?php if ($row['Status_wrapping'] === 'Ready'): ?>

                                            <button type="submit"
                                                    class="wrapping-btn wrapping-btn-toggle-off">
                                                <i class="fa-solid fa-ban"></i>
                                                Nonaktifkan
                                            </button>

                                        <?php else: ?>

                                            <button type="submit"
                                                    class="wrapping-btn wrapping-btn-toggle-on">
                                                <i class="fa-solid fa-check"></i>
                                                Aktifkan
                                            </button>

                                        <?php endif; ?>

                                    </form>

                                    <!-- HAPUS -->
                                    <form action="index.php"
                                          method="POST"
                                          onsubmit="return confirm(
                                              'Yakin ingin menghapus wrapping ini?'
                                          )">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">

                                        <input type="hidden"
                                               name="aksi"
                                               value="hapus">

                                        <input type="hidden"
                                               name="Wrapping_id"
                                               value="<?= (int)$row['Wrapping_id'] ?>">

                                        <input type="hidden"
                                               name="keyword_redirect"
                                               value="<?= e($keyword) ?>">

                                        <button type="submit"
                                                class="wrapping-btn wrapping-btn-delete">
                                            <i class="fa-solid fa-trash"></i>
                                            Hapus
                                        </button>

                                    </form>

                                </div>
                            </td>

                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5">
                            <div class="wrapping-empty">
                                <i class="fa-solid fa-box-open"></i>
                                <strong>Data wrapping tidak ditemukan.</strong>
                                <p>
                                    <?= $keyword !== ''
                                        ? 'Coba gunakan kata kunci lain.'
                                        : 'Silakan tambahkan wrapping baru.' ?>
                                </p>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>
        </div>

        <p class="wrapping-note">
            <i class="fa-solid fa-circle-info"></i>
            Wrapping yang dinonaktifkan tetap tersimpan di database.
            Aktifkan kembali jika wrapping sudah tersedia. Pesanan lama
            tidak dihapus ketika status wrapping diubah.
        </p>

    </section>

</main>

<!-- MODAL TAMBAH / EDIT -->
<div class="wrapping-modal" id="wrappingModal">

    <div class="wrapping-modal-content">

        <div class="wrapping-modal-header">
            <h3 id="modalTitle">Tambah Wrapping</h3>

            <button type="button"
                    class="wrapping-modal-close"
                    onclick="tutupModal()"
                    aria-label="Tutup modal">
                &times;
            </button>
        </div>

        <form action="index.php"
              method="POST"
              class="wrapping-form">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                   name="aksi"
                   id="aksiForm"
                   value="tambah">

            <input type="hidden"
                   name="Wrapping_id"
                   id="wrappingId">

            <input type="hidden"
                   name="keyword_redirect"
                   value="<?= e($keyword) ?>">

            <label for="namaWrapping">Nama Wrapping</label>

            <input
                type="text"
                name="Warna_wrapping"
                id="namaWrapping"
                maxlength="100"
                placeholder="Contoh: Ready Coklat & Putih"
                required
            >

            <div class="wrapping-form-actions">

                <button type="button"
                        class="wrapping-btn wrapping-btn-secondary"
                        onclick="tutupModal()">
                    Batal
                </button>

                <button type="submit"
                        class="wrapping-btn wrapping-btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan
                </button>

            </div>

        </form>

    </div>

</div>

<script>
    /* MENU MOBILE */
    const menuButton = document.getElementById('mobileMenuButton');
    const navigation = document.getElementById('adminNavigation');

    if (menuButton && navigation) {
        menuButton.addEventListener('click', function () {
            navigation.classList.toggle('active');
        });
    }

    /* MODAL TAMBAH */
    function bukaModalTambah() {
        document.getElementById('modalTitle').textContent = 'Tambah Wrapping';
        document.getElementById('aksiForm').value = 'tambah';
        document.getElementById('wrappingId').value = '';
        document.getElementById('namaWrapping').value = '';

        document.getElementById('wrappingModal').classList.add('show');
        document.getElementById('namaWrapping').focus();
    }

    /* MODAL EDIT */
    function bukaModalEdit(id, nama) {
        document.getElementById('modalTitle').textContent = 'Edit Wrapping';
        document.getElementById('aksiForm').value = 'edit';
        document.getElementById('wrappingId').value = id;
        document.getElementById('namaWrapping').value = nama;

        document.getElementById('wrappingModal').classList.add('show');
        document.getElementById('namaWrapping').focus();
    }

    /* TUTUP MODAL */
    function tutupModal() {
        document.getElementById('wrappingModal').classList.remove('show');
    }

    document.getElementById('wrappingModal').addEventListener(
        'click',
        function (event) {
            if (event.target === this) {
                tutupModal();
            }
        }
    );

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