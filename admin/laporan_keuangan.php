<?php

session_start();

require_once '../config/koneksi.php';

if (

    !isset($_SESSION['user_id']) ||

    ($_SESSION['role'] ?? '') !== 'admin'

) {

    header('Location: ../login.php');

    exit;

}

function e($value)

{

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}

function rupiah($angka)

{

    return 'Rp ' . number_format((float)$angka, 0, ',', '.');

}

function cariKolom($daftar, $pilihan)

{

    foreach ($pilihan as $nama) {

        foreach ($daftar as $kolom) {

            if (strtolower($kolom) === strtolower($nama)) {

                return $kolom;

            }

        }

    }

    return null;

}

// Deteksi kolom tabel PESANAN

$kolomTersedia = [];

$hasilKolom = mysqli_query($conn, "SHOW COLUMNS FROM PESANAN");

if ($hasilKolom) {

    while ($row = mysqli_fetch_assoc($hasilKolom)) {

        $kolomTersedia[] = $row['Field'];

    }

}

$kolomId = cariKolom($kolomTersedia, [

    'pesanan_id',

    'id_pesanan',

    'Pesanan_id',

    'ID_pesanan',

    'id'

]);

$kolomTanggal = cariKolom($kolomTersedia, [

    'tanggal_pesanan',

    'Tanggal_pesanan',

    'tanggal_order',

    'created_at',

    'tanggal',

    'tgl_pesanan'

]);

$kolomTotal = cariKolom($kolomTersedia, [

    'total_harga',

    'Total_harga'

]);

$kolomStatus = cariKolom($kolomTersedia, [

    'status_pesanan',

    'Status_pesanan'

]);

// Filter tanggal

$tanggalAwal = $_GET['tanggal_awal'] ?? date('Y-m-01');

$tanggalAkhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal)) {

    $tanggalAwal = date('Y-m-01');

}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir)) {

    $tanggalAkhir = date('Y-m-d');

}

$daftarPesanan = [];

$totalPemasukan = 0;

$totalTransaksi = 0;

$error = '';

if (!$kolomId || !$kolomTanggal || !$kolomTotal || !$kolomStatus) {

    $error = 'Kolom tabel PESANAN tidak lengkap. Periksa nama kolom database.';

} elseif ($tanggalAwal > $tanggalAkhir) {

    $error = 'Tanggal awal tidak boleh melebihi tanggal akhir.';

} else {

    // Amankan nama kolom yang digunakan dalam SQL

    $id = '`' . str_replace('`', '``', $kolomId) . '`';

    $tanggal = '`' . str_replace('`', '``', $kolomTanggal) . '`';

    $total = '`' . str_replace('`', '``', $kolomTotal) . '`';

    $status = '`' . str_replace('`', '``', $kolomStatus) . '`';

    $sql = "

        SELECT

            $id AS id_laporan,

            $tanggal AS tanggal_laporan,

            $total AS total_laporan

        FROM PESANAN

        WHERE LOWER(TRIM($status)) = 'selesai'

          AND $tanggal >= ?

          AND $tanggal < DATE_ADD(?, INTERVAL 1 DAY)

        ORDER BY $tanggal ASC

    ";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {

        mysqli_stmt_bind_param(

            $stmt,

            'ss',

            $tanggalAwal,

            $tanggalAkhir

        );

        mysqli_stmt_execute($stmt);

        $hasil = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($hasil)) {

            $daftarPesanan[] = $row;

            $totalPemasukan += (float)$row['total_laporan'];

            $totalTransaksi++;

        }

        mysqli_stmt_close($stmt);

    } else {

        $error = 'Gagal mengambil data laporan dari database.';

    }

}

// Format tanggal untuk laporan

$periodeAwal = date('d-m-Y', strtotime($tanggalAwal));

$periodeAkhir = date('d-m-Y', strtotime($tanggalAkhir));

$tanggalCetak = date('d-m-Y');

// Nama administrator

$namaAdmin = 'Administrator';

$stmtAdmin = mysqli_prepare(

    $conn,

    "SELECT Nama FROM user WHERE User_id = ? LIMIT 1"

);

if ($stmtAdmin) {

    mysqli_stmt_bind_param($stmtAdmin, 'i', $_SESSION['user_id']);

    mysqli_stmt_execute($stmtAdmin);

    $hasilAdmin = mysqli_stmt_get_result($stmtAdmin);

    if ($admin = mysqli_fetch_assoc($hasilAdmin)) {

        if (!empty($admin['Nama'])) {

            $namaAdmin = $admin['Nama'];

        }

    }

    mysqli_stmt_close($stmtAdmin);

}

// Unduh PDF

if (isset($_GET['unduh']) && $_GET['unduh'] === 'pdf') {

    if ($error !== '') {

        die(e($error));

    }

    $autoload = __DIR__ . '/../vendor/autoload.php';

    if (!file_exists($autoload)) {

        die(

            'Dompdf belum terpasang. Jalankan ' .

            'composer require dompdf/dompdf di folder proyek.'

        );

    }

    require_once $autoload;

    // Logo Flonuel.
    // Dompdf membutuhkan ekstensi GD untuk memproses gambar PNG.
    // Jika GD belum aktif, lewati logo PNG agar PDF tetap dapat dibuat.
    $logoPath = realpath(__DIR__ . '/../assets/img/logo flonuel.png');
    $logoData = '';

    if ($logoPath && is_file($logoPath)) {
        $tipeLogo = function_exists('mime_content_type')
            ? mime_content_type($logoPath)
            : '';

        // JPEG dapat digunakan tanpa GD; PNG hanya jika ekstensi GD aktif.
        if (
            $tipeLogo === 'image/jpeg' ||
            ($tipeLogo === 'image/png' && extension_loaded('gd'))
        ) {
            $isiLogo = file_get_contents($logoPath);

            if ($isiLogo !== false) {
                $logoData = 'data:' . $tipeLogo . ';base64,' .
                    base64_encode($isiLogo);
            }
        }
    }

    ob_start();

    ?>

    <!DOCTYPE html>

    <html lang="id">

    <head>

        <meta charset="UTF-8">

        <style>

            @page {

                margin: 35px 40px;

            }

            body {

                font-family: DejaVu Sans, sans-serif;

                font-size: 10px;

                color: #333;

            }

            .kop {

                width: 100%;

                border-bottom: 3px solid #b94f78;

                padding-bottom: 13px;

                margin-bottom: 20px;

            }

            .kop td {

                vertical-align: middle;

            }

            .logo {

                width: 65px;

                height: 65px;

                object-fit: contain;

            }

            .nama-toko {

                font-size: 20px;

                font-weight: bold;

                color: #b94f78;

                margin: 0 0 5px;

            }

            .subjudul {

                color: #666;

                font-size: 10px;

            }

            h2 {

                text-align: center;

                font-size: 16px;

                margin: 20px 0 5px;

            }

            .keterangan {

                text-align: center;

                color: #666;

                margin-bottom: 22px;

            }

            .ringkasan {

                width: 100%;

                border-collapse: separate;

                border-spacing: 8px 0;

                margin: 0 -8px 22px;

            }

            .ringkasan td {

                width: 50%;

                border: 1px solid #ead6df;

                background-color: #fff6f9;

                padding: 13px;

            }

            .label {

                font-size: 9px;

                color: #666;

                margin-bottom: 8px;

            }

            .nilai {

                color: #b94f78;

                font-size: 17px;

                font-weight: bold;

            }

            .judul-tabel {

                font-size: 12px;

                font-weight: bold;

                margin-bottom: 10px;

            }

            table.transaksi {

                width: 100%;

                border-collapse: collapse;

            }

            .transaksi th {

                background-color: #b94f78;

                color: white;

                padding: 10px 7px;

                text-align: left;

                border: 1px solid #b94f78;

            }

            .transaksi td {

                padding: 9px 7px;

                border: 1px solid #ddd;

            }

            .transaksi .angka {

                text-align: right;

            }

            .transaksi .total td {

                font-weight: bold;

                background-color: #fff0f5;

            }

            .kosong {

                text-align: center;

                padding: 20px !important;

                color: #777;

            }

            .catatan {

                margin-top: 14px;

                color: #777;

                font-size: 9px;

            }

            .ttd {

                width: 100%;

                margin-top: 35px;

            }

            .ttd td {

                width: 50%;

                vertical-align: top;

            }

            .kanan {

                text-align: center;

            }

            .ruang-ttd {

                height: 55px;

            }

            .nama-ttd {

                font-weight: bold;

                text-decoration: underline;

            }

            .footer {

                margin-top: 25px;

                padding-top: 8px;

                border-top: 1px solid #ddd;

                color: #888;

                text-align: center;

                font-size: 8px;

            }

        </style>

    </head>

    <body>

        <table class="kop">

            <tr>

                <td style="width:80px;">

                    <?php if ($logoData !== ''): ?>

                        <img class="logo" src="<?= $logoData ?>">

                    <?php endif; ?>

                </td>

                <td>

                    <div class="nama-toko">FLONUЕL</div>

                    <div class="subjudul">

                        Laporan Keuangan dan Rekapitulasi Transaksi

                    </div>

                </td>

            </tr>

        </table>

        <h2>LAPORAN KEUANGAN</h2>

        <div class="keterangan">

            Periode <?= e($periodeAwal) ?> sampai <?= e($periodeAkhir) ?>

        </div>

        <table class="ringkasan">

            <tr>

                <td>

                    <div class="label">TOTAL PEMASUKAN</div>

                    <div class="nilai"><?= e(rupiah($totalPemasukan)) ?></div>

                </td>

                <td>

                    <div class="label">TRANSAKSI SELESAI</div>

                    <div class="nilai">

                        <?= number_format($totalTransaksi, 0, ',', '.') ?>

                        transaksi

                    </div>

                </td>

            </tr>

        </table>

        <div class="judul-tabel">Rincian Transaksi Selesai</div>

        <table class="transaksi">

            <thead>

                <tr>

                    <th style="width:7%;">No.</th>

                    <th style="width:20%;">ID Pesanan</th>

                    <th style="width:28%;">Tanggal Pesanan</th>

                    <th style="width:17%;">Status</th>

                    <th style="width:28%;">Total</th>

                </tr>

            </thead>

            <tbody>

                <?php if (count($daftarPesanan) > 0): ?>

                    <?php foreach ($daftarPesanan as $i => $pesanan): ?>

                        <tr>

                            <td><?= $i + 1 ?></td>

                            <td><?= e($pesanan['id_laporan']) ?></td>

                            <td>

                                <?php

                                $waktu = strtotime(

                                    $pesanan['tanggal_laporan']

                                );

                                echo $waktu

                                    ? e(date('d-m-Y H:i', $waktu))

                                    : e($pesanan['tanggal_laporan']);

                                ?>

                            </td>

                            <td>Selesai</td>

                            <td class="angka">

                                <?= e(rupiah($pesanan['total_laporan'])) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <tr class="total">

                        <td colspan="4">TOTAL PEMASUKAN</td>

                        <td class="angka">

                            <?= e(rupiah($totalPemasukan)) ?>

                        </td>

                    </tr>

                <?php else: ?>

                    <tr>

                        <td colspan="5" class="kosong">

                            Tidak ada transaksi selesai pada periode ini.

                        </td>

                    </tr>

                    <tr class="total">

                        <td colspan="4">TOTAL PEMASUKAN</td>

                        <td class="angka"><?= e(rupiah(0)) ?></td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

        <div class="catatan">

            Catatan: laporan ini hanya mencakup pesanan dengan status selesai.

        </div>

        <table class="ttd">

            <tr>

                <td></td>

                <td class="kanan">

                    Bandung, <?= e($tanggalCetak) ?><br>

                    Administrator,

                    <div class="ruang-ttd"></div>

                    <div class="nama-ttd"><?= e($namaAdmin) ?></div>

                </td>

            </tr>

        </table>

        <div class="footer">

            Flonuel Flowershop - Laporan dibuat pada <?= e($tanggalCetak) ?>

        </div>

    </body>

    </html>

    <?php

    $htmlPdf = ob_get_clean();

    $options = new \Dompdf\Options();

    $options->set('isRemoteEnabled', false);

    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new \Dompdf\Dompdf($options);

    $dompdf->loadHtml($htmlPdf, 'UTF-8');

    $dompdf->setPaper('A4', 'portrait');

    $dompdf->render();

    $namaFile = 'Laporan-Keuangan-' .

        $tanggalAwal . '-sampai-' . $tanggalAkhir . '.pdf';

    $dompdf->stream($namaFile, ['Attachment' => true]);

    exit;

}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laporan Keuangan | FlowerShop</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <link rel="stylesheet"

          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        .laporan-card {

            background: white;

            padding: 24px;

            border-radius: 12px;

            margin-bottom: 20px;

            box-shadow: 0 3px 15px rgba(0,0,0,.05);

        }

        .filter {

            display: flex;

            gap: 12px;

            align-items: end;

            flex-wrap: wrap;

        }

        .filter div {

            display: flex;

            flex-direction: column;

            gap: 6px;

        }

        .filter input {

            padding: 10px;

            border: 1px solid #ddd;

            border-radius: 7px;

        }

        .btn {

            display: inline-flex;

            gap: 8px;

            align-items: center;

            padding: 11px 16px;

            background: #b94f78;

            color: white;

            text-decoration: none;

            border: none;

            border-radius: 7px;

            cursor: pointer;

        }

        .btn-pdf {

            background: #444;

        }

        .ringkasan {

            display: grid;

            grid-template-columns: repeat(2, minmax(0, 1fr));

            gap: 18px;

        }

        .angka {

            color: #b94f78;

            font-size: 25px;

            font-weight: bold;

            overflow-wrap: anywhere;

        }

        .tabel-wrapper {

            overflow-x: auto;

        }

        table {

            width: 100%;

            border-collapse: collapse;

        }

        th, td {

            padding: 12px;

            text-align: left;

            border-bottom: 1px solid #eee;

        }

        th {

            background: #fff0f5;

        }

        .error {

            background: #fff0f0;

            color: #a12626;

            padding: 15px;

            border-radius: 8px;

        }

        @media (max-width: 600px) {

            .ringkasan {

                grid-template-columns: 1fr;

            }

        }

    </style>

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

    <img src="../assets/img/logo flonuel.png" alt="Logo FlowerShop">

</div>

            <div class="brand-text">

                <span>Admin Panel</span>

            </div>

        </a>

    </div>

    <nav class="admin-navigation">

        <a href="dashboard.php">Dashboard</a>

        <a href="pesanan/index.php">Data Pesanan</a>

        <a href="pembayaran/index.php">Data Pembayaran</a>

        <a href="produk/index.php">Produk</a>

        <a href="addon/index.php">Add On</a>

        <a href="bunga/index.php">Bunga</a>
        <a href="kategori/index.php">Kategori</a>
        <a href="wrapping/index.php">Wrapping</a>
        <a href="laporan-keuangan.php" class="active">Laporan Keuangan</a>

    </nav>

    <div class="header-user">

        <div class="header-avatar">

            <i class="fa-solid fa-user"></i>

        </div>

        <div class="header-user-info">

            <strong><?= e($namaAdmin) ?></strong>

            <span>Administrator</span>

        </div>

        <a href="../logout.php"

           class="logout-button"

           onclick="return confirm('Yakin ingin logout?')">

            <i class="fa-solid fa-right-from-bracket"></i>

        </a>

    </div>

</header>

<main class="admin-container">

    <div class="laporan-card">

        <h1>Laporan Keuangan</h1>

        <p>Rekap pemasukan dari transaksi yang sudah selesai.</p>

        <form method="GET" class="filter">

            <div>

                <label for="tanggal_awal">Tanggal Awal</label>

                <input type="date" id="tanggal_awal"

                       name="tanggal_awal"

                       value="<?= e($tanggalAwal) ?>" required>

            </div>

            <div>

                <label for="tanggal_akhir">Tanggal Akhir</label>

                <input type="date" id="tanggal_akhir"

                       name="tanggal_akhir"

                       value="<?= e($tanggalAkhir) ?>" required>

            </div>

            <button class="btn" type="submit">

                <i class="fa-solid fa-filter"></i>

                Tampilkan

            </button>

            <button class="btn btn-pdf" type="submit" name="unduh" value="pdf">

                <i class="fa-solid fa-file-pdf"></i>

                Unduh PDF

            </button>

        </form>

    </div>

    <?php if ($error !== ''): ?>

        <div class="error"><?= e($error) ?></div>

    <?php else: ?>

        <div class="laporan-card">

            <h2>Ringkasan Keuangan</h2>

            <p>

                Periode <?= e($periodeAwal) ?>

                sampai <?= e($periodeAkhir) ?>

            </p>

            <div class="ringkasan">

                <div>

                    <p>Total Pemasukan</p>

                    <div class="angka"><?= rupiah($totalPemasukan) ?></div>

                </div>

                <div>

                    <p>Transaksi Selesai</p>

                    <div class="angka">

                        <?= number_format($totalTransaksi, 0, ',', '.') ?>

                    </div>

                </div>

            </div>

        </div>

        <div class="laporan-card">

            <h2>Rincian Transaksi</h2>

            <div class="tabel-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>No.</th>

                            <th>ID Pesanan</th>

                            <th>Tanggal Pesanan</th>

                            <th>Status</th>

                            <th>Total</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($daftarPesanan): ?>

                        <?php foreach ($daftarPesanan as $i => $pesanan): ?>

                            <tr>

                                <td><?= $i + 1 ?></td>

                                <td><?= e($pesanan['id_laporan']) ?></td>

                                <td>

                                    <?php

                                    $waktu = strtotime($pesanan['tanggal_laporan']);

                                    echo $waktu

                                        ? e(date('d-m-Y H:i', $waktu))

                                        : e($pesanan['tanggal_laporan']);

                                    ?>

                                </td>

                                <td>Selesai</td>

                                <td><?= rupiah($pesanan['total_laporan']) ?></td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="5" style="text-align:center">

                                Tidak ada transaksi selesai pada periode ini.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</main>

</body>

</html>