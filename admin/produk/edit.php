
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

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php?pesan=gagal');
    exit;
}

/* AMBIL DATA PRODUK */
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        Produk_id,
        Kategori_id,
        Nama_produk,
        Deskripsi_produk,
        Harga,
        Jumlah_bunga,
        Foto_produk,
        Status
     FROM produk
     WHERE Produk_id = ?
     LIMIT 1"
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

/* DATA ADMIN */
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

    if ($resultAdmin && mysqli_num_rows($resultAdmin) > 0) {
        $admin = mysqli_fetch_assoc($resultAdmin);
        $nama_admin = $admin['Nama'] ?: 'Admin';
    }

    mysqli_stmt_close($stmtAdmin);
}

/* DATA FORM */
$nama_produk = $produk['Nama_produk'];
$kategori_id = (int)$produk['Kategori_id'];
$deskripsi = $produk['Deskripsi_produk'] ?? '';
$harga = $produk['Harga'];
$jumlah_bunga = $produk['Jumlah_bunga'];
$status = $produk['Status'];
$foto_lama = $produk['Foto_produk'] ?? '';
$error = '';

/* AMBIL KATEGORI */
$kategoriQuery = mysqli_query(
    $conn,
    "SELECT Kategori_id, Nama_kategori
     FROM kategori_produk
     ORDER BY Nama_kategori ASC"
);

if (!$kategoriQuery) {
    die('Gagal mengambil data kategori.');
}

/* PROSES UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $harga = trim($_POST['harga'] ?? '');
    $jumlah_bunga = trim($_POST['jumlah_bunga'] ?? '');
    $status = $_POST['status'] ?? '';

    if ($nama_produk === '') {
        $error = 'Nama produk wajib diisi.';
    } elseif ($kategori_id <= 0) {
        $error = 'Silakan pilih kategori produk.';
    } elseif (
        $harga === '' ||
        !is_numeric($harga) ||
        (float)$harga < 0
    ) {
        $error = 'Harga harus berupa angka dan tidak boleh negatif.';
    } elseif (
        filter_var($jumlah_bunga, FILTER_VALIDATE_INT) === false ||
        (int)$jumlah_bunga < 0
    ) {
        $error = 'Jumlah bunga harus berupa bilangan bulat minimal 0.';
    } elseif (!in_array($status, ['aktif', 'tidak_aktif'], true)) {
        $error = 'Status produk tidak valid.';
    } else {
        /* VALIDASI KATEGORI */
        $stmtKategori = mysqli_prepare(
            $conn,
            "SELECT Kategori_id
             FROM kategori_produk
             WHERE Kategori_id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmtKategori,
            'i',
            $kategori_id
        );

        mysqli_stmt_execute($stmtKategori);
        $hasilKategori = mysqli_stmt_get_result($stmtKategori);
        $kategoriValid = mysqli_num_rows($hasilKategori) > 0;
        mysqli_stmt_close($stmtKategori);

        if (!$kategoriValid) {
            $error = 'Kategori produk tidak ditemukan.';
        } else {
            $foto_baru = $foto_lama;
            $namaFileBaru = '';
            $uploadBerhasil = true;

            /* UPLOAD FOTO PENGGANTI */
            if (
                isset($_FILES['foto_produk']) &&
                $_FILES['foto_produk']['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                $file = $_FILES['foto_produk'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Foto gagal diunggah.';
                    $uploadBerhasil = false;
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $error = 'Ukuran foto maksimal 5 MB.';
                    $uploadBerhasil = false;
                } else {
                    $tmpFile = $file['tmp_name'];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($tmpFile);

                    $formatFoto = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    if (
                        !isset($formatFoto[$mime]) ||
                        @getimagesize($tmpFile) === false
                    ) {
                        $error = 'Foto harus berformat JPG, PNG, atau WEBP.';
                        $uploadBerhasil = false;
                    } else {
                        $folderUpload = __DIR__ . '/../../uploads/produk/';

                        if (
                            !is_dir($folderUpload) &&
                            !mkdir($folderUpload, 0755, true) &&
                            !is_dir($folderUpload)
                        ) {
                            $error = 'Folder upload foto tidak dapat dibuat.';
                            $uploadBerhasil = false;
                        } else {
                            $namaFileBaru = bin2hex(random_bytes(16))
                                . '.'
                                . $formatFoto[$mime];

                            if (move_uploaded_file(
                                $tmpFile,
                                $folderUpload . $namaFileBaru
                            )) {
                                $foto_baru = $namaFileBaru;
                            } else {
                                $error = 'Foto gagal disimpan.';
                                $uploadBerhasil = false;
                            }
                        }
                    }
                }
            }

            /* UPDATE DATABASE */
            if ($uploadBerhasil) {
                $hargaSimpan = (float)$harga;
                $jumlahSimpan = (int)$jumlah_bunga;

                $stmtUpdate = mysqli_prepare(
                    $conn,
                    "UPDATE produk
                     SET
                        Kategori_id = ?,
                        Nama_produk = ?,
                        Deskripsi_produk = ?,
                        Harga = ?,
                        Jumlah_bunga = ?,
                        Foto_produk = ?,
                        Status = ?
                     WHERE Produk_id = ?"
                );

                if (!$stmtUpdate) {
                    $error = 'Gagal menyiapkan query update.';
                } else {
                    mysqli_stmt_bind_param(
                        $stmtUpdate,
                        'issdissi',
                        $kategori_id,
                        $nama_produk,
                        $deskripsi,
                        $hargaSimpan,
                        $jumlahSimpan,
                        $foto_baru,
                        $status,
                        $id
                    );

                    if (mysqli_stmt_execute($stmtUpdate)) {
                        mysqli_stmt_close($stmtUpdate);

                        /* HAPUS FOTO LAMA JIKA DIGANTI */
                        if (
                            $namaFileBaru !== '' &&
                            $foto_lama !== ''
                        ) {
                            $folderUpload = __DIR__ . '/../../uploads/produk/';
                            $namaFotoLama = basename($foto_lama);
                            $pathFotoLama = $folderUpload . $namaFotoLama;

                            if (
                                is_file($pathFotoLama) &&
                                realpath($pathFotoLama) !==
                                realpath($folderUpload . $namaFileBaru)
                            ) {
                                @unlink($pathFotoLama);
                            }
                        }

                        header('Location: index.php?pesan=edit');
                        exit;
                    }

                    $error = 'Produk gagal diperbarui. Silakan coba kembali.';
                    mysqli_stmt_close($stmtUpdate);

                    /* HAPUS FOTO BARU JIKA UPDATE GAGAL */
                    if ($namaFileBaru !== '') {
                        @unlink(
                            __DIR__ . '/../../uploads/produk/' . $namaFileBaru
                        );
                    }
                }
            }
        }
    }
}

/* URL FOTO SAAT INI */
$fotoTampil = '';

if (trim((string)$foto_lama) !== '') {
    $fotoTampil = '../../uploads/produk/' .
        rawurlencode(basename($foto_lama));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Produk | FlowerShop</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-produk-form.css">

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
        <a href="index.php" class="active">Add-on</a>
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

<!-- KONTEN -->
<main class="admin-container produk-form-page">

    <section class="form-main-card">

        <div class="form-page-heading">
            <div>
                <span class="form-eyebrow">MANAJEMEN PRODUK</span>
                <h1>Edit Produk</h1>
                <p>Perbarui informasi produk bunga di katalog.</p>
            </div>

            <a href="index.php" class="form-back-button">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali
            </a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="form-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST"
              enctype="multipart/form-data"
              class="produk-form">

            <div class="form-layout">

                <!-- KOLOM KIRI -->
                <div class="form-left">

                    <div class="form-group">
                        <label for="nama_produk">
                            Nama Produk <span>*</span>
                        </label>

                        <input
                            type="text"
                            id="nama_produk"
                            name="nama_produk"
                            value="<?= e($nama_produk) ?>"
                            maxlength="255"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="kategori_id">
                            Kategori Produk <span>*</span>
                        </label>

                        <select id="kategori_id" name="kategori_id" required>
                            <option value="">Pilih kategori produk</option>

                            <?php while ($kat = mysqli_fetch_assoc($kategoriQuery)): ?>
                                <option
                                    value="<?= (int)$kat['Kategori_id'] ?>"
                                    <?= $kategori_id === (int)$kat['Kategori_id'] ? 'selected' : '' ?>>
                                    <?= e($kat['Nama_kategori']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="harga">
                            Harga (Rp) <span>*</span>
                        </label>

                        <div class="input-prefix">
                            <span>Rp</span>
                            <input
                                type="number"
                                id="harga"
                                name="harga"
                                min="0"
                                step="1"
                                value="<?= e($harga) ?>"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="jumlah_bunga">
                            Jumlah Bunga <span>*</span>
                        </label>

                        <input
                            type="number"
                            id="jumlah_bunga"
                            name="jumlah_bunga"
                            min="0"
                            step="1"
                            value="<?= e($jumlah_bunga) ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="deskripsi">Deskripsi Produk</label>

                        <textarea
                            id="deskripsi"
                            name="deskripsi"
                            rows="5"
                            placeholder="Deskripsi produk bunga..."><?= e($deskripsi) ?></textarea>
                    </div>

                </div>

                <!-- KOLOM KANAN -->
                <div class="form-right">

                    <div class="form-group">
                        <label>Foto Produk</label>

                        <div class="current-photo">
                            <?php if ($fotoTampil !== ''): ?>
                                <img
                                    id="previewImage"
                                    src="<?= e($fotoTampil) ?>"
                                    alt="<?= e($nama_produk) ?>"
                                    onerror="this.style.display='none';document.getElementById('noPhoto').hidden=false;">

                                <div id="noPhoto" class="no-photo" hidden>
                                    <i class="fa-regular fa-image"></i>
                                    <span>Foto tidak ditemukan</span>
                                </div>
                            <?php else: ?>
                                <div id="noPhoto" class="no-photo">
                                    <i class="fa-regular fa-image"></i>
                                    <span>Belum ada foto produk</span>
                                </div>

                                <img id="previewImage" src="" alt="Pratinjau foto" hidden>
                            <?php endif; ?>
                        </div>

                        <div class="upload-area edit-upload-area">
                            <input
                                type="file"
                                id="foto_produk"
                                name="foto_produk"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                            <label for="foto_produk" class="upload-label">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <strong>Pilih Foto Pengganti</strong>
                                <span>JPG, PNG, atau WEBP</span>
                                <span>Maksimal 5 MB</span>
                            </label>
                        </div>

                        <p class="photo-note">
                            Kosongkan jika ingin tetap menggunakan foto lama.
                        </p>
                    </div>

                    <div class="form-group status-group">
                        <label for="status">Status Produk <span>*</span></label>

                        <select id="status" name="status" required>
                            <option value="aktif"
                                <?= $status === 'aktif' ? 'selected' : '' ?>>
                                Aktif
                            </option>

                            <option value="tidak_aktif"
                                <?= $status === 'tidak_aktif' ? 'selected' : '' ?>>
                                Tidak Aktif
                            </option>
                        </select>
                    </div>

                </div>
            </div>

            <!-- TOMBOL -->
            <div class="form-actions">
                <a href="index.php" class="form-cancel-button">
                    Batal
                </a>

                <button type="submit" class="form-submit-button">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan Perubahan
                </button>
            </div>

        </form>
    </section>

    <footer class="form-footer">
        &copy; <?= date('Y') ?> FlowerShop Admin Panel
    </footer>

</main>

<script>
const inputFoto = document.getElementById('foto_produk');
const previewImage = document.getElementById('previewImage');
const noPhoto = document.getElementById('noPhoto');

let objectURL = null;

inputFoto.addEventListener('change', function () {
    const file = this.files[0];

    if (objectURL) {
        URL.revokeObjectURL(objectURL);
        objectURL = null;
    }

    if (!file) {
        return;
    }

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!allowedTypes.includes(file.type)) {
        alert('Foto harus berformat JPG, PNG, atau WEBP.');
        this.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran foto maksimal 5 MB.');
        this.value = '';
        return;
    }

    objectURL = URL.createObjectURL(file);
    previewImage.src = objectURL;
    previewImage.hidden = false;
    previewImage.style.display = 'block';

    if (noPhoto) {
        noPhoto.hidden = true;
    }
});
</script>

</body>
</html>
