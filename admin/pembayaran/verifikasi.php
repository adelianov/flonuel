
<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['flash_error'] = 'Permintaan tidak valid. Silakan coba kembali.';
    header('Location: index.php');
    exit;
}

$pembayaran_id = filter_input(INPUT_POST, 'pembayaran_id', FILTER_VALIDATE_INT);
$aksi = $_POST['aksi'] ?? '';
$catatan = trim($_POST['catatan'] ?? '');

if (!$pembayaran_id || !in_array($aksi, ['verifikasi', 'tolak'], true)) {
    $_SESSION['flash_error'] = 'Data pembayaran atau aksi tidak valid.';
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT Pembayaran_id, Pesanan_id, Status_pembayaran
     FROM pembayaran
     WHERE Pembayaran_id = ?
     LIMIT 1"
);

if (!$stmt) {
    $_SESSION['flash_error'] = 'Gagal menyiapkan pemeriksaan pembayaran.';
    header('Location: index.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $pembayaran_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$pembayaran = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$pembayaran) {
    $_SESSION['flash_error'] = 'Data pembayaran tidak ditemukan.';
    header('Location: index.php');
    exit;
}

$statusSaatIni = strtolower(trim($pembayaran['Status_pembayaran'] ?? ''));

if (in_array($statusSaatIni, ['diverifikasi', 'berhasil'], true)) {
    $_SESSION['flash_error'] = 'Pembayaran ini sudah diverifikasi.';
    header('Location: index.php');
    exit;
}

if ($statusSaatIni === 'ditolak') {
    $_SESSION['flash_error'] = 'Pembayaran ini sudah ditolak.';
    header('Location: index.php');
    exit;
}

$pesanan_id = (int) $pembayaran['Pesanan_id'];

mysqli_begin_transaction($conn);

try {
    if ($aksi === 'verifikasi') {
        $statusPembayaranBaru = 'diverifikasi';
        $statusPesananBaru = 'diproses';
        $pesanSukses = 'Pembayaran berhasil diverifikasi dan pesanan diperbarui.';
    } else {
        $statusPembayaranBaru = 'ditolak';
        $statusPesananBaru = 'menunggu_pembayaran';
        $pesanSukses = 'Pembayaran berhasil ditolak.';
    }

    if ($aksi === 'verifikasi') {
        $updatePembayaran = mysqli_prepare(
            $conn,
            "UPDATE pembayaran
             SET Status_pembayaran = ?, Tanggal_Verifikasi = NOW()
             WHERE Pembayaran_id = ?"
        );

        if (!$updatePembayaran) {
            throw new Exception('Gagal menyiapkan pembaruan pembayaran.');
        }

        mysqli_stmt_bind_param(
            $updatePembayaran,
            'si',
            $statusPembayaranBaru,
            $pembayaran_id
        );
    } else {
        $updatePembayaran = mysqli_prepare(
            $conn,
            "UPDATE pembayaran
             SET Status_pembayaran = ?
             WHERE Pembayaran_id = ?"
        );

        if (!$updatePembayaran) {
            throw new Exception('Gagal menyiapkan penolakan pembayaran.');
        }

        mysqli_stmt_bind_param(
            $updatePembayaran,
            'si',
            $statusPembayaranBaru,
            $pembayaran_id
        );
    }

    if (!mysqli_stmt_execute($updatePembayaran)) {
        throw new Exception('Status pembayaran gagal diperbarui.');
    }

    mysqli_stmt_close($updatePembayaran);

    /*
     * Perbarui status pesanan.
     */
    $updatePesanan = mysqli_prepare(
        $conn,
        "UPDATE pesanan
         SET status_pesanan = ?
         WHERE Pesanan_id = ?"
    );

    if (!$updatePesanan) {
        throw new Exception('Gagal menyiapkan pembaruan pesanan.');
    }

    mysqli_stmt_bind_param(
        $updatePesanan,
        'si',
        $statusPesananBaru,
        $pesanan_id
    );

    if (!mysqli_stmt_execute($updatePesanan)) {
        throw new Exception('Status pesanan gagal diperbarui.');
    }

    mysqli_stmt_close($updatePesanan);

    mysqli_commit($conn);

    $_SESSION['flash_success'] = $pesanSukses;

} catch (Throwable $e) {
    mysqli_rollback($conn);

    // Detail kesalahan dicatat ke log server, bukan ditampilkan ke pengguna.
    error_log('Verifikasi pembayaran: ' . $e->getMessage());

    $_SESSION['flash_error'] =
        'Pembayaran gagal diproses. Periksa struktur tabel dan kolom database Anda.';
}

header('Location: index.php');
exit;

