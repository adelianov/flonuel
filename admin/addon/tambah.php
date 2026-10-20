
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

    if ($resultAdmin && ($admin = mysqli_fetch_assoc($resultAdmin))) {
        $nama_admin = $admin['Nama'] ?: 'Admin';
    }

    mysqli_stmt_close($stmtAdmin);
}


function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$nama = '';
$harga = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama_addon'] ?? '');
    $harga = trim($_POST['harga'] ?? '');

    $fotoNama = null;
    $fotoPath = null;

    // Validasi nama
    if ($nama === '') {
        $error = 'Nama add-on wajib diisi.';
    }

    // Validasi harga
    elseif (
        $harga === '' ||
        !is_numeric($harga) ||
        (float)$harga < 0
    ) {
        $error = 'Harga harus berupa angka positif atau nol.';
    }

    // Validasi gambar
    elseif (
        !isset($_FILES['foto']) ||
        $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE
    ) {
        $error = 'Gambar add-on wajib diunggah.';
    }

    elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Terjadi kesalahan saat mengunggah gambar.';
    }

    elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
        $error = 'Ukuran gambar maksimal 2 MB.';
    }

    else {

        $tmpFile = $_FILES['foto']['tmp_name'];

        // Periksa gambar berdasarkan isi file
        $infoGambar = @getimagesize($tmpFile);
        $mime = $infoGambar['mime'] ?? '';

        $formatDiizinkan = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (
            $infoGambar === false ||
            !isset($formatDiizinkan[$mime])
        ) {
            $error = 'Format gambar harus JPG, PNG, atau WEBP.';
        } else {

            $folderUpload = '../../uploads/addon/';

            if (
                !is_dir($folderUpload) &&
                !mkdir($folderUpload, 0755, true)
            ) {
                $error = 'Folder upload gambar gagal dibuat.';
            } else {

                // Nama file unik
                $fotoNama = 'addon_' .
                    bin2hex(random_bytes(12)) .
                    '.' .
                    $formatDiizinkan[$mime];

                $fotoPath = $folderUpload . $fotoNama;

                // Pindahkan gambar ke folder upload
                if (!move_uploaded_file($tmpFile, $fotoPath)) {
                    $error = 'Gambar gagal disimpan ke folder upload.';
                }
            }
        }
    }

    if ($error === '') {

        $hargaSimpan = (float)$harga;

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO add_on
                (Nama_addon, Harga, Foto, Stok)
             VALUES (?, ?, ?, 0)"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                'sds',
                $nama,
                $hargaSimpan,
                $fotoNama
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                header('Location: index.php?pesan=tambah');
                exit;

            } else {

                $error = 'Add-on gagal disimpan: ' .
                    mysqli_stmt_error($stmt);

                mysqli_stmt_close($stmt);

                // Hapus gambar jika penyimpanan database gagal
                if ($fotoPath && file_exists($fotoPath)) {
                    unlink($fotoPath);
                }
            }

        } else {

            $error = 'Query database gagal disiapkan: ' .
                mysqli_error($conn);

            if ($fotoPath && file_exists($fotoPath)) {
                unlink($fotoPath);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Tambah Add-on | FlowerShop</title>

    <link
        rel="stylesheet"
        href="../../assets/css/admin.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/admin-addon.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        .addon-field input[type="file"] {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px dashed #d8a8bd;
            border-radius: 10px;
            background: #fff8fb;
            cursor: pointer;
            box-sizing: border-box;
        }

        .addon-field small {
            display: block;
            margin-top: 8px;
            color: #777;
            font-size: 12px;
        }

        .addon-preview {
            margin-top: 15px;
        }

        .addon-preview img {
            display: block;
            width: 180px;
            height: 180px;
            max-width: 100%;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #eadde3;
            background: #fff;
        }

        .addon-alert {
            margin-bottom: 20px;
            padding: 14px 16px;
            color: #842029;
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            border-radius: 10px;
        }

        .addon-alert i {
            margin-right: 8px;
        }

        .addon-field label span {
            color: #d63384;
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

<main class="admin-container addon-page">

    <section class="addon-card">

        <!-- JUDUL HALAMAN -->
        <div class="addon-heading">

            <div>
                <span class="addon-eyebrow">
                    MANAJEMEN ADD-ON
                </span>

                <h1>Tambah Add-on</h1>

                <p>
                    Tambahkan pilihan ekstra untuk pesanan buket pelanggan.
                </p>
            </div>

            <a href="index.php" class="addon-back">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali
            </a>

        </div>

        <!-- PESAN ERROR -->
        <?php if ($error !== ''): ?>
            <div class="addon-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="addon-form-grid">

                <!-- NAMA ADD-ON -->
                <div class="addon-field">

                    <label for="nama_addon">
                        Nama Add-on <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="nama_addon"
                        name="nama_addon"
                        maxlength="150"
                        placeholder="Contoh: Kartu ucapan"
                        value="<?= e($nama) ?>"
                        required
                    >

                </div>

                <!-- HARGA -->
                <div class="addon-field">

                    <label for="harga">
                        Harga Tambahan (Rp) <span>*</span>
                    </label>

                    <div class="addon-price-input">

                        <span>Rp</span>

                        <input
                            type="number"
                            id="harga"
                            name="harga"
                            min="0"
                            step="0.01"
                            placeholder="10000"
                            value="<?= e($harga) ?>"
                            required
                        >

                    </div>

                </div>

                <!-- FOTO ADD-ON -->
                <div class="addon-field addon-full">

                    <label for="foto">
                        Gambar Add-on <span>*</span>
                    </label>

                    <input
                        type="file"
                        id="foto"
                        name="foto"
                        accept=".jpg,.jpeg,.png,.webp"
                        required
                    >

                    <small>
                        Pilih gambar berformat JPG, PNG, atau WEBP.
                        Ukuran maksimal 2 MB.
                    </small>

                    <!-- PREVIEW GAMBAR -->
                    <div
                        class="addon-preview"
                        id="preview-container"
                        hidden
                    >
                        <img
                            src=""
                            id="preview-gambar"
                            alt="Preview gambar add-on"
                        >
                    </div>

                </div>

            </div>

            <!-- TOMBOL FORM -->
            <div class="addon-actions">

                <a href="index.php" class="addon-cancel">
                    Batal
                </a>

                <button
                    type="submit"
                    class="addon-submit"
                >
                    Simpan Add-on
                </button>

            </div>

        </form>

    </section>

</main>

<script>
const inputFoto = document.getElementById('foto');
const previewContainer = document.getElementById('preview-container');
const previewGambar = document.getElementById('preview-gambar');

let gambarSebelumnya = null;

inputFoto.addEventListener('change', function () {
    const file = this.files[0];

    if (gambarSebelumnya) {
        URL.revokeObjectURL(gambarSebelumnya);
        gambarSebelumnya = null;
    }

    if (!file) {
        previewContainer.hidden = true;
        previewGambar.removeAttribute('src');
        return;
    }

    const formatValid = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!formatValid.includes(file.type)) {
        alert('Format gambar harus JPG, PNG, atau WEBP.');
        this.value = '';
        previewContainer.hidden = true;
        previewGambar.removeAttribute('src');
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran gambar maksimal 2 MB.');
        this.value = '';
        previewContainer.hidden = true;
        previewGambar.removeAttribute('src');
        return;
    }

    gambarSebelumnya = URL.createObjectURL(file);
    previewGambar.src = gambarSebelumnya;
    previewContainer.hidden = false;
});
</script>

</body>
</html>
