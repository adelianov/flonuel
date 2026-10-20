
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
        $dataAdmin = mysqli_fetch_assoc($resultAdmin);
        $nama_admin = $dataAdmin['Nama'] ?: 'Admin';
    }

    mysqli_stmt_close($stmtAdmin);
}

$error = '';
$nama_produk = '';
$kategori_id = '';
$harga = '';
$jumlah_bunga = '0';
$deskripsi = '';
$status = 'aktif';

/* AMBIL KATEGORI */
$kategoriQuery = mysqli_query(
    $conn,
    "SELECT Kategori_id, Nama_kategori
     FROM kategori_produk
     ORDER BY Nama_kategori ASC"
);

if (!$kategoriQuery) {
    die('Gagal mengambil kategori produk.');
}

/* PROSES FORM */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $harga = trim($_POST['harga'] ?? '');
    $jumlah_bunga = trim($_POST['jumlah_bunga'] ?? '0');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'aktif';

    if ($nama_produk === '') {
        $error = 'Nama produk wajib diisi.';
    } elseif ($kategori_id <= 0) {
        $error = 'Silakan pilih kategori produk.';
    } elseif ($harga === '' || !is_numeric($harga) || (float)$harga < 0) {
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
            $foto_produk = '';
            $uploadBerhasil = true;

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
                            $namaFile = bin2hex(random_bytes(16))
                                . '.'
                                . $formatFoto[$mime];

                            if (move_uploaded_file(
                                $tmpFile,
                                $folderUpload . $namaFile
                            )) {
                                $foto_produk = $namaFile;
                            } else {
                                $error = 'Foto gagal disimpan.';
                                $uploadBerhasil = false;
                            }
                        }
                    }
                }
            }

            if ($uploadBerhasil) {
                $hargaSimpan = (float)$harga;
                $jumlahSimpan = (int)$jumlah_bunga;

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO produk
                    (
                        Kategori_id,
                        Nama_produk,
                        Deskripsi_produk,
                        Harga,
                        Jumlah_bunga,
                        Foto_produk,
                        Status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    if ($foto_produk !== '') {
                        @unlink(
                            __DIR__ . '/../../uploads/produk/' . $foto_produk
                        );
                    }

                    $error = 'Query penyimpanan produk gagal disiapkan.';
                } else {
                    mysqli_stmt_bind_param(
                        $stmt,
                        'issdiss',
                        $kategori_id,
                        $nama_produk,
                        $deskripsi,
                        $hargaSimpan,
                        $jumlahSimpan,
                        $foto_produk,
                        $status
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        header('Location: index.php?pesan=tambah');
                        exit;
                    }

                    if ($foto_produk !== '') {
                        @unlink(
                            __DIR__ . '/../../uploads/produk/' . $foto_produk
                        );
                    }

                    $error = 'Produk gagal disimpan. Periksa kembali data Anda.';
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Produk | FlowerShop</title>

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
                <h1>Tambah Produk</h1>
                <p>Masukkan informasi produk bunga baru ke katalog.</p>
            </div>

            <a href="index.php" class="form-back-button">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali
            </a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="form-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= e($error) ?>
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
                            placeholder="Contoh: Buket Mawar Merah"
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
                                    <?= (string)$kategori_id === (string)$kat['Kategori_id'] ? 'selected' : '' ?>>
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
                                placeholder="Contoh: 150000"
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
                            placeholder="Jelaskan jenis bunga, warna, isi buket, atau detail produk lainnya..."><?= e($deskripsi) ?></textarea>
                    </div>

                </div>

                <!-- KOLOM KANAN -->
                <div class="form-right">

                    <div class="form-group">
                        <label for="foto_produk">Foto Produk</label>

                        <div class="upload-area">
                            <input
                                type="file"
                                id="foto_produk"
                                name="foto_produk"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                            <label for="foto_produk" class="upload-label">
                                <i class="fa-regular fa-image"></i>
                                <strong>Pilih foto produk</strong>
                                <span>Format JPG, PNG, atau WEBP.</span>
                                <span>Maksimal 5 MB.</span>
                            </label>
                        </div>

                        <div class="image-preview" id="imagePreview" hidden>
                            <img id="previewImage" src="" alt="Pratinjau foto produk">

                            <div class="preview-info">
                                <strong id="previewName"></strong>
                                <span>Foto produk yang dipilih</span>
                            </div>

                            <button type="button"
                                    id="removeImage"
                                    class="remove-image"
                                    aria-label="Hapus foto">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group status-group">
                        <label for="status">Status Produk <span>*</span></label>

                        <select id="status" name="status" required>
                            <option value="aktif"
                                <?= $status === 'aktif' ? 'selected' : '' ?>>
                                Aktif — ditampilkan di katalog
                            </option>

                            <option value="tidak_aktif"
                                <?= $status === 'tidak_aktif' ? 'selected' : '' ?>>
                                Tidak Aktif — disembunyikan dari katalog
                            </option>
                        </select>
                    </div>

                </div>
            </div>

            <!-- TOMBOL FORM -->
            <div class="form-actions">
                <a href="index.php" class="form-cancel-button">Batal</a>

                <button type="submit" class="form-submit-button">
                    <i class="fa-regular fa-floppy-disk"></i>
                    Simpan Produk
                </button>
            </div>

        </form>
    </section>

</main>

<script>
const inputFoto = document.getElementById('foto_produk');
const imagePreview = document.getElementById('imagePreview');
const previewImage = document.getElementById('previewImage');
const previewName = document.getElementById('previewName');
const removeImage = document.getElementById('removeImage');

let objectURL = null;

inputFoto.addEventListener('change', function () {
    const file = this.files[0];

    if (objectURL) {
        URL.revokeObjectURL(objectURL);
        objectURL = null;
    }

    if (!file) {
        imagePreview.hidden = true;
        return;
    }

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!allowedTypes.includes(file.type)) {
        alert('Pilih foto JPG, PNG, atau WEBP.');
        this.value = '';
        imagePreview.hidden = true;
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran foto maksimal 5 MB.');
        this.value = '';
        imagePreview.hidden = true;
        return;
    }

    objectURL = URL.createObjectURL(file);
    previewImage.src = objectURL;
    previewName.textContent = file.name;
    imagePreview.hidden = false;
});

removeImage.addEventListener('click', function () {
    inputFoto.value = '';
    previewImage.src = '';
    previewName.textContent = '';
    imagePreview.hidden = true;

    if (objectURL) {
        URL.revokeObjectURL(objectURL);
        objectURL = null;
    }
});
</script>

</body>
</html>
```
