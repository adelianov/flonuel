
<?php
session_start();

require_once '../../config/koneksi.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header('Location: ../../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$pesanan_id = (int) ($_GET['id'] ?? 0);

if ($pesanan_id <= 0) {
    exit('ID pesanan tidak valid.');
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function tanggalID($tanggal, $jam = false)
{
    if (!$tanggal || strtotime($tanggal) === false) {
        return '-';
    }

    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];

    $waktu = strtotime($tanggal);

    return ($hari[date('l', $waktu)] ?? date('l', $waktu))
        . ', ' . date($jam ? 'd/m/Y H:i' : 'd/m/Y', $waktu);
}

/* DATA PESANAN DAN CUSTOMER */
$sql = "
    SELECT
        p.Pesanan_id,
        p.Kode_pesanan,
        p.User_id,
        p.Tanggal_pesanan,
        p.total_harga,
        p.status_pesanan,
        p.pilihan_bunga,
        p.wrapping,
        p.catatan_custom,
        u.Nama,
        u.No_Hp,
        u.Email,
        pr.Nama_produk,
        pr.Harga AS harga_produk,
        w.Warna_wrapping,
        jp.Tanggal_pickup,
        jp.Jam_pickup
    FROM pesanan p
    INNER JOIN user u ON p.User_id = u.User_id
    LEFT JOIN produk pr ON p.Produk_id = pr.Produk_id
    LEFT JOIN wrapping w ON p.Wrapping_id = w.Wrapping_id
    LEFT JOIN jadwal_pickup jp ON p.Pickup_id = jp.Pickup_id
    WHERE p.Pesanan_id = ?
      AND p.User_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    exit('Gagal menyiapkan data invoice.');
}

mysqli_stmt_bind_param($stmt, 'ii', $pesanan_id, $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$p = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$p) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

/* DATA ADD-ON */
$addons = [];
$totalAddon = 0;

$sqlAddon = "
    SELECT
        da.Jumlah,
        da.Harga,
        COALESCE(a.Nama_addon, 'Add-On') AS Nama_addon
    FROM detail_add_on da
    LEFT JOIN add_on a ON da.addon_id = a.addon_id
    WHERE da.Pesanan_id = ?
    ORDER BY da.Detail_addon_id ASC
";

$stmtAddon = mysqli_prepare($conn, $sqlAddon);

if (!$stmtAddon) {
    exit('Gagal mengambil rincian add-on.');
}

mysqli_stmt_bind_param($stmtAddon, 'i', $pesanan_id);
mysqli_stmt_execute($stmtAddon);

$resultAddon = mysqli_stmt_get_result($stmtAddon);

while ($row = mysqli_fetch_assoc($resultAddon)) {
    $jumlah = max(1, (int) $row['Jumlah']);
    $harga = (float) $row['Harga'];
    $subtotal = $jumlah * $harga;

    $addons[] = [
        'nama' => $row['Nama_addon'],
        'jumlah' => $jumlah,
        'harga' => $harga,
        'subtotal' => $subtotal
    ];

    $totalAddon += $subtotal;
}

mysqli_stmt_close($stmtAddon);

$hargaProduk = (float) ($p['harga_produk'] ?? 0);
$totalPesanan = (float) $p['total_harga'];

$statusLabel = [
    'menunggu_pembayaran' => 'Menunggu Pembayaran',
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'diproses' => 'Diproses',
    'dibuat' => 'Sedang Dibuat',
    'siap_diambil' => 'Siap Diambil',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan'
];

$status = $statusLabel[$p['status_pesanan']]
    ?? ucwords(str_replace('_', ' ', $p['status_pesanan'] ?? ''));

$wrapping = $p['Warna_wrapping']
    ?: ($p['wrapping'] ?? '-');

ob_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 35px;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        color: #44323b;
        font-size: 11px;
        line-height: 1.6;
    }

    .header {
        border-bottom: 3px solid #c05282;
        padding-bottom: 18px;
        margin-bottom: 25px;
    }

    .brand {
        color: #c05282;
        font-size: 27px;
        font-weight: bold;
    }

    .subtitle {
        color: #806875;
        font-size: 10px;
    }

    .invoice-title {
        color: #c05282;
        font-size: 23px;
        font-weight: bold;
        text-align: right;
    }

    .top-table,
    .items,
    .summary-table {
        width: 100%;
        border-collapse: collapse;
    }

    .top-table td {
        vertical-align: top;
        padding: 5px 0;
    }

    h3 {
        color: #c05282;
        font-size: 12px;
        margin-bottom: 8px;
    }

    .items {
        margin-top: 12px;
    }

    .items th {
        background: #c05282;
        color: white;
        padding: 10px 7px;
        text-align: left;
    }

    .items td {
        border-bottom: 1px solid #eadde4;
        padding: 9px 7px;
    }

    .right {
        text-align: right;
    }

    .center {
        text-align: center;
    }

    .summary-table {
        width: 55%;
        margin: 20px 0 0 auto;
    }

    .summary-table td {
        padding: 6px;
    }

    .grand-total td {
        border-top: 2px solid #c05282;
        color: #c05282;
        font-size: 14px;
        font-weight: bold;
        padding-top: 12px;
    }

    .note {
        margin-top: 25px;
        padding: 12px;
        background: #fff4f8;
        border: 1px solid #f1d8e3;
    }

    .footer {
        margin-top: 35px;
        padding-top: 12px;
        text-align: center;
        border-top: 1px solid #eadde4;
        color: #806875;
        font-size: 10px;
    }
</style>
</head>
<body>

<table class="top-table header">
    <tr>
        <td width="55%">
            <div class="brand">FLONUEL</div>
            <div class="subtitle">
                FlowerShop - Buket dan Rangkaian Bunga
            </div>
        </td>
        <td width="45%" class="right">
            <div class="invoice-title">INVOICE</div>
            <strong>INV-<?= e($p['Kode_pesanan']) ?></strong><br>
            Tanggal: <?= e(tanggalID($p['Tanggal_pesanan'], true)) ?><br>
            Status: <?= e($status) ?>
        </td>
    </tr>
</table>

<h3>INFORMASI CUSTOMER</h3>

<table class="top-table">
    <tr>
        <td width="50%">
            <strong>Nama:</strong> <?= e($p['Nama']) ?><br>
            <strong>Email:</strong> <?= e($p['Email'] ?: '-') ?><br>
            <strong>No. HP:</strong> <?= e($p['No_Hp'] ?: '-') ?>
        </td>
        <td width="50%">
            <strong>Kode Pesanan:</strong>
            <?= e($p['Kode_pesanan']) ?><br>
            <strong>Tanggal Pickup:</strong>
            <?= e(tanggalID($p['Tanggal_pickup'] ?? '')) ?><br>
            <strong>Jam Pickup:</strong>
            <?= !empty($p['Jam_pickup'])
                ? e(date('H:i', strtotime($p['Jam_pickup'])))
                : '-' ?>
        </td>
    </tr>
</table>

<h3>RINCIAN PESANAN</h3>

<table class="items">
    <thead>
        <tr>
            <th>Deskripsi</th>
            <th class="center">Jumlah</th>
            <th class="right">Harga</th>
            <th class="right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?= e($p['Nama_produk'] ?? 'Produk') ?></td>
            <td class="center">1</td>
            <td class="right"><?= rupiah($hargaProduk) ?></td>
            <td class="right"><?= rupiah($hargaProduk) ?></td>
        </tr>

        <?php foreach ($addons as $a): ?>
        <tr>
            <td><?= e($a['nama']) ?></td>
            <td class="center"><?= $a['jumlah'] ?></td>
            <td class="right"><?= rupiah($a['harga']) ?></td>
            <td class="right"><?= rupiah($a['subtotal']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h3>DETAIL CUSTOM</h3>

<strong>Pilihan Bunga:</strong>
<?= e($p['pilihan_bunga'] ?: '-') ?><br>

<strong>Wrapping:</strong>
<?= e($wrapping ?: '-') ?><br>

<?php if (!empty($p['catatan_custom'])): ?>
<strong>Catatan:</strong>
<?= nl2br(e($p['catatan_custom'])) ?>
<?php endif; ?>

<table class="summary-table">
    <tr>
        <td>Subtotal Produk</td>
        <td class="right"><?= rupiah($hargaProduk) ?></td>
    </tr>
    <tr>
        <td>Total Add-On</td>
        <td class="right"><?= rupiah($totalAddon) ?></td>
    </tr>
    <tr class="grand-total">
        <td>Total Tagihan</td>
        <td class="right"><?= rupiah($totalPesanan) ?></td>
    </tr>
</table>

<div class="note">
    <strong>Informasi:</strong><br>
    Invoice ini merupakan rincian tagihan pesanan dan bukan bukti
    bahwa pembayaran telah diterima. Silakan periksa status pembayaran
    pada halaman pembayaran.
</div>

<div class="footer">
    Terima kasih telah memesan di Flonuel.<br>
    Simpan invoice ini sebagai referensi pesanan Anda.
</div>

</body>
</html>

<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$namaFile = 'Invoice-' .
    preg_replace('/[^A-Za-z0-9_-]/', '-', $p['Kode_pesanan']) .
    '.pdf';

$aksi = $_GET['aksi'] ?? 'lihat';

$attachment = ($aksi === 'download');

$dompdf->stream($namaFile, [
    'Attachment' => $attachment
]);

exit;
