
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php?pesan=data_tidak_ditemukan');
    exit;
}

try {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT Bunga_id, Nama_bunga, Foto_bunga
         FROM bunga
         WHERE Bunga_id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $bunga = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$bunga) {
        header('Location: index.php?pesan=data_tidak_ditemukan');
        exit;
    }

    $stmtCek = mysqli_prepare(
        $conn,
        "SELECT 1
         FROM detail_bunga
         WHERE Bunga_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmtCek, 'i', $id);
    mysqli_stmt_execute($stmtCek);

    $resultCek = mysqli_stmt_get_result($stmtCek);
    $dipakai = mysqli_num_rows($resultCek) > 0;

    mysqli_stmt_close($stmtCek);

    if ($dipakai) {
        header('Location: index.php?pesan=gagal_hapus');
        exit;
    }

    $delete = mysqli_prepare(
        $conn,
        "DELETE FROM bunga WHERE Bunga_id = ?"
    );

    mysqli_stmt_bind_param($delete, 'i', $id);
    mysqli_stmt_execute($delete);

    $berhasil = mysqli_stmt_affected_rows($delete) === 1;

    mysqli_stmt_close($delete);

    if (!$berhasil) {
        header('Location: index.php?pesan=gagal_hapus');
        exit;
    }

    $foto = basename(
        str_replace('\\', '/', $bunga['Foto_bunga'] ?? '')
    );

    if ($foto !== '') {
        $pathFoto = __DIR__ . '/../../uploads/bunga/' . $foto;

        if (is_file($pathFoto)) {
            @unlink($pathFoto);
        }
    }

    header('Location: index.php?pesan=hapus');
    exit;

} catch (mysqli_sql_exception $e) {

    // 1451 = data masih direferensikan oleh tabel lain
    if ((int)$e->getCode() === 1451) {
        header('Location: index.php?pesan=gagal_hapus');
        exit;
    }

    error_log('Kesalahan hapus bunga: ' . $e->getMessage());

    header('Location: index.php?pesan=gagal_hapus');
    exit;
}
