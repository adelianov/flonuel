
<?php
session_start();

require_once '../../config/koneksi.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../../login.php');
    exit;
}

function invoiceRupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function invoiceTanggal($tanggal, $format = 'd/m/Y')
{
    if (empty($tanggal)) {
        return '-';
    }

    $waktu = strtotime($tanggal);
    return $waktu ? date($format, $waktu) : '-';
}

function invoiceEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$pesanan_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$pesanan_id || $pesanan_id < 1) {
    http_response_code(400);
    exit('ID pesanan tidak valid.');
}

/* DATA PESANAN DAN PELANGGAN */
$sql = "
    SELECT
        p.Pesanan_id,
        p.Kode_pesanan,
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

        w.Warna_wrapping AS warna_wrapping,
        jp.Tanggal_pickup AS tanggal_pickup,
        jp.Jam_pickup AS jam_pickup

    FROM pesanan p
    LEFT JOIN user u ON p.User_id = u.User_id
    LEFT JOIN produk pr ON p.Produk_id = pr.Produk_id
    LEFT JOIN wrapping w ON p.Wrapping_id = w.Wrapping_id
    LEFT JOIN jadwal_pickup jp ON p.Pickup_id = jp.Pickup_id
    WHERE p.Pesanan_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    exit('Gagal menyiapkan data invoice.');
}

mysqli_stmt_bind_param($stmt, 'i', $pesanan_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$pesanan = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$pesanan) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

/* DETAIL BUNGA */
$bunga_list = [];

$sqlBunga = "
    SELECT
        b.Nama_bunga AS nama_bunga,
        db.Jumlah AS jumlah
    FROM detail_bunga db
    INNER JOIN bunga b ON db.Bunga_id = b.Bunga_id
    WHERE db.Pesanan_id = ?
    ORDER BY db.Detail_bunga_id ASC
";

$stmt = mysqli_prepare($conn, $sqlBunga);
mysqli_stmt_bind_param($stmt, 'i', $pesanan_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $bunga_list[] = $row;
}

mysqli_stmt_close($stmt);

/* DETAIL ADD-ON */
$addon_list = [];
$total_addon = 0;

$sqlAddon = "
    SELECT
        a.Nama_addon AS nama_addon,
        da.Jumlah AS jumlah,
        da.Harga AS harga,
        (da.Jumlah * da.Harga) AS subtotal
    FROM detail_add_on da
    INNER JOIN add_on a ON da.addon_id = a.addon_id
    WHERE da.Pesanan_id = ?
    ORDER BY da.Detail_addon_id ASC
";

$stmt = mysqli_prepare($conn, $sqlAddon);
mysqli_stmt_bind_param($stmt, 'i', $pesanan_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $addon_list[] = $row;
    $total_addon += (float)$row['subtotal'];
}

mysqli_stmt_close($stmt);

/* STATUS PESANAN */
$status_list = [
    'menunggu_pembayaran' => 'Menunggu Pembayaran',
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'diproses' => 'Diproses',
    'dibuat' => 'Sedang Dibuat',
    'siap_diambil' => 'Siap Diambil',
    'siap_dikirim' => 'Siap Dikirim',
    'dikirim' => 'Dikirim',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan'
];

$status = $pesanan['status_pesanan'] ?? '';
$label_status = $status_list[$status]
    ?? ucfirst(str_replace('_', ' ', $status));

/* HTML INVOICE */
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #333;
            font-size: 11px;
            line-height: 1.6;
        }

        .header {
            border-bottom: 3px solid #c05282;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .brand {
            color: #a9406e;
            font-size: 24px;
            font-weight: bold;
        }

        .subtitle {
            color: #777;
            font-size: 10px;
        }

        .invoice-title {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #a9406e;
        }

        .row {
            width: 100%;
        }

        .column {
            width: 49%;
            vertical-align: top;
        }

        .section-title {
            background: #fff0f6;
            color: #a9406e;
            padding: 7px;
            font-weight: bold;
            margin-top: 16px;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th {
            background: #c05282;
            color: #fff;
            text-align: left;
        }

        th, td {
            border: 1px solid #e5d5dc;
            padding: 7px;
        }

        .right {
            text-align: right;
        }

        .total-box {
            margin-top: 18px;
            padding: 12px;
            background: #fff0f6;
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            color: #a9406e;
        }

        .note {
            margin-top: 20px;
            color: #777;
            font-size: 9px;
        }

        .status {
            display: inline-block;
            padding: 4px 8px;
            background: #fff0f6;
            color: #a9406e;
        }
    </style>
</head>
<body>

    <table class="header" style="border:0; margin-top:0">
        <tr>
            <td style="border:0; padding:0">
                <div class="brand">Flonuel</div>
                <div class="subtitle">FlowerShop</div>
            </td>
            <td style="border:0; padding:0; text-align:right">
                <div class="invoice-title">INVOICE</div>
                <strong>
                    <?= invoiceEscape($pesanan['Kode_pesanan']) ?>
                </strong><br>
                Tanggal:
                <?= invoiceTanggal($pesanan['Tanggal_pesanan'], 'd/m/Y H:i') ?>
            </td>
        </tr>
    </table>

    <table class="row" style="border:0">
        <tr>
            <td class="column" style="border:0; padding:0">
                <strong>INFORMASI PELANGGAN</strong><br>
                Nama:
                <?= invoiceEscape($pesanan['nama_pelanggan'] ?? '-') ?><br>
                Username:
                <?= invoiceEscape($pesanan['username_pelanggan'] ?? '-') ?><br>
                Email:
                <?= invoiceEscape($pesanan['email_pelanggan'] ?? '-') ?><br>
                Telepon:
                <?= invoiceEscape($pesanan['telepon_pelanggan'] ?? '-') ?>
            </td>
            <td class="column" style="border:0; padding:0">
                <strong>INFORMASI PESANAN</strong><br>
                Kode:
                <?= invoiceEscape($pesanan['Kode_pesanan']) ?><br>
                Status:
                <span class="status">
                    <?= invoiceEscape($label_status) ?>
                </span><br>
                Pickup:
                <?= invoiceTanggal($pesanan['tanggal_pickup'] ?? null) ?><br>
                Jam:
                <?= !empty($pesanan['jam_pickup'])
                    ? invoiceEscape(substr($pesanan['jam_pickup'], 0, 5) . ' WIB')
                    : '-' ?>
            </td>
        </tr>
    </table>

    <div class="section-title">Detail Produk</div>

    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th>Wrapping</th>
                <th class="right">Harga</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= invoiceEscape($pesanan['nama_produk'] ?? '-') ?></td>
                <td><?= invoiceEscape($pesanan['warna_wrapping'] ?? '-') ?></td>
                <td class="right">
                    <?= invoiceRupiah($pesanan['harga_produk'] ?? 0) ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Rincian Bunga</div>

    <table>
        <thead>
            <tr>
                <th>Nama Bunga</th>
                <th class="right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($bunga_list): ?>
                <?php foreach ($bunga_list as $bunga): ?>
                    <tr>
                        <td><?= invoiceEscape($bunga['nama_bunga']) ?></td>
                        <td class="right">
                            <?= (int)$bunga['jumlah'] ?> tangkai
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2">Tidak ada detail bunga.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">Rincian Add-On</div>

    <table>
        <thead>
            <tr>
                <th>Nama Add-On</th>
                <th class="right">Jumlah</th>
                <th class="right">Harga</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($addon_list): ?>
                <?php foreach ($addon_list as $addon): ?>
                    <tr>
                        <td><?= invoiceEscape($addon['nama_addon']) ?></td>
                        <td class="right"><?= (int)$addon['jumlah'] ?></td>
                        <td class="right">
                            <?= invoiceRupiah($addon['harga']) ?>
                        </td>
                        <td class="right">
                            <?= invoiceRupiah($addon['subtotal']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">Tidak ada add-on.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="total-box">
        TOTAL PESANAN:
        <?= invoiceRupiah($pesanan['total_harga']) ?>
    </div>

    <div class="section-title">Catatan Pesanan</div>
    <p>
        <?= nl2br(invoiceEscape(
            $pesanan['Catatan_pesanan'] ?: 'Tidak ada catatan.'
        )) ?>
    </p>

    <div class="note">
        Invoice ini dibuat secara otomatis oleh sistem Flonuel.
        Status di atas merupakan status pesanan, bukan konfirmasi
        bahwa pembayaran telah diterima.
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

/* GENERATE PDF */
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$kode = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '_',
    $pesanan['Kode_pesanan'] ?? 'pesanan'
);

$namaFile = 'Invoice-' . $kode . '.pdf';

$aksi = $_GET['aksi'] ?? 'lihat';
$attachment = ($aksi === 'download');

$dompdf->stream($namaFile, [
    'Attachment' => $attachment
]);

exit;