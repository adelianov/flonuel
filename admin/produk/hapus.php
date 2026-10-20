
<?php
session_start();
require_once '../../config/koneksi.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php?pesan=gagal');
    exit;
}

/* Pastikan produk tersedia */
$stmt = mysqli_prepare(
    $conn,
    "SELECT Produk_id FROM produk WHERE Produk_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$produk = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$produk) {
    header('Location: index.php?pesan=gagal');
    exit;
}

/* Periksa apakah produk pernah digunakan dalam pesanan */
$stmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total FROM pesanan WHERE Produk_id = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ((int)$data['total'] > 0) {
    /* Produk tetap ada, tetapi tidak aktif */
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE produk SET Status = 'tidak_aktif' WHERE Produk_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $berhasil = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header('Location: index.php?pesan=' .
        ($berhasil ? 'nonaktif' : 'gagal'));
    exit;
}

/* Produk yang belum pernah dipesan boleh dihapus */
$stmt = mysqli_prepare(
    $conn,
    "SELECT Foto_produk FROM produk WHERE Produk_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dataFoto = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM produk WHERE Produk_id = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
$berhasil = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($berhasil && !empty($dataFoto['Foto_produk'])) {
    $path = __DIR__ . '/../../uploads/produk/' .
        basename($dataFoto['Foto_produk']);

    if (is_file($path)) {
        @unlink($path);
    }
}

header('Location: index.php?pesan=' .
    ($berhasil ? 'hapus' : 'gagal'));
exit;