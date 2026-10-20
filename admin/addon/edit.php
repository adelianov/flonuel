
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

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php?pesan=id_tidak_valid');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT addon_id, Nama_addon, Harga, Foto, Stok
     FROM add_on
     WHERE addon_id = ?"
);

if (!$stmt) {
    die('Query gagal disiapkan: ' . e(mysqli_error($conn)));
}

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$addon = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$addon) {
    header('Location: index.php?pesan=data_tidak_ditemukan');
    exit;
}

$nama = $addon['Nama_addon'];
$harga = $addon['Harga'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama_addon'] ?? '');
    $harga = trim($_POST['harga'] ?? '');

    $fotoLama = $addon['Foto'];
    $fotoBaru = null;
    $fotoPathBaru = null;

    // Validasi nama
    if ($nama === '') {
        $error = 'Nama add-on wajib diisi.';
    } elseif (
        $harga === '' ||
        !is_numeric($harga) ||
        (float)$harga < 0
    ) {
        $error = 'Harga harus berupa angka positif atau nol.';
    }


    if (
        $error === '' &&
        isset($_FILES['foto']) &&
        $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Terjadi kesalahan saat mengunggah gambar.';
        } elseif (
            $_FILES['foto']['size'] > 2 * 1024 * 1024
        ) {
            $error = 'Ukuran gambar maksimal 2 MB.';
        } else {

            $tmpFile = $_FILES['foto']['tmp_name'];
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

                    $fotoBaru = 'addon_' .
                        bin2hex(random_bytes(12)) .
                        '.' .
                        $formatDiizinkan[$mime];

                    $fotoPathBaru = $folderUpload . $fotoBaru;

                    if (
                        !move_uploaded_file(
                            $tmpFile,
                            $fotoPathBaru
                        )
                    ) {
                        $error = 'Gambar baru gagal diunggah.';
                    }
                }
            }
        }
    }

    if ($error === '') {

        $hargaSimpan = (float)$harga;
        $fotoSimpan = $fotoBaru ?? $fotoLama;

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE add_on
             SET Nama_addon = ?,
                 Harga = ?,
                 Foto = ?
             WHERE addon_id = ?"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                'sdsi',
                $nama,
                $hargaSimpan,
                $fotoSimpan,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

               
                if (
                    $fotoBaru !== null &&
                    !empty($fotoLama) &&
                    $fotoLama !== $fotoBaru &&
                    basename($fotoLama) === $fotoLama
                ) {
                    $folderUpload = '../../uploads/addon/';
                    $pathFotoLama = $folderUpload . $fotoLama;

                    if (is_file($pathFotoLama)) {
                        unlink($pathFotoLama);
                    }
                }

                header('Location: index.php?pesan=edit');
                exit;

            } else {

                $error = 'Add-on gagal diperbarui: ' .
                    mysqli_stmt_error($stmt);

                mysqli_stmt_close($stmt);

                // Hapus gambar baru jika update gagal
                if (
                    $fotoPathBaru &&
                    is_file($fotoPathBaru)
                ) {
                    unlink($fotoPathBaru);
                }
            }

        } else {

            $error = 'Query update gagal disiapkan: ' .
                mysqli_error($conn);

            if (
                $fotoPathBaru &&
                is_file($fotoPathBaru)
            ) {
                unlink($fotoPathBaru);
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

    <title>Edit Add-on</title>

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
        .addon-current-image {
            margin-top: 12px;
        }

        .addon-current-image img,
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

        .addon-field input[type="file"] {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px dashed #d8a8bd;
            border-radius: 10px;
            background: #fff8fb;
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
    <img src="../../assets/img/logo flonuel.png" alt="Logo FlowerShop">
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
            <strong>Administrator</strong>
            <span>Admin</span>
        </div>

        <a
            href="../../logout.php"
            class="logout-button"
            onclick="return confirm('Yakin ingin logout?')"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>

    </div>

</header>

<main class="admin-container addon-page">

    <section class="addon-card">

        <div class="addon-heading">
            <div>
                <span class="addon-eyebrow">
                    MANAJEMEN ADD-ON
                </span>

                <h1>Edit Add-on</h1>

                <p>
                    Perbarui nama, harga, atau gambar add-on.
                </p>
            </div>

            <a href="index.php" class="addon-back">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali
            </a>
        </div>

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

                <!-- HARGA ADD-ON -->
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

                <!-- GAMBAR ADD-ON -->
                <div class="addon-field addon-full">

                    <label for="foto">
                        Gambar Add-on
                    </label>

                    <input
                        type="file"
                        id="foto"
                        name="foto"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small>
                        Kosongkan jika tidak ingin mengganti gambar.
                        Format JPG, PNG, atau WEBP, maksimal 2 MB.
                    </small>

                    <!-- GAMBAR LAMA -->
                    <div class="addon-current-image">
                        <p><strong>Gambar saat ini:</strong></p>

                        <?php if (!empty($addon['Foto'])): ?>
                            <img
                                src="../../uploads/addon/<?= rawurlencode(basename($addon['Foto'])) ?>"
                                alt="<?= e($addon['Nama_addon']) ?>"
                                onerror="this.style.display='none'; document.getElementById('foto-tidak-ada').hidden=false;"
                            >

                            <small id="foto-tidak-ada" hidden>
                                Gambar lama tidak ditemukan di folder uploads/addon/.
                            </small>
                        <?php else: ?>
                            <p>Belum ada gambar.</p>
                        <?php endif; ?>
                    </div>

                    <!-- PREVIEW GAMBAR BARU -->
                    <div
                        class="addon-preview"
                        id="preview-container"
                        hidden
                    >
                        <p><strong>Preview gambar baru:</strong></p>

                        <img
                            src=""
                            id="preview-gambar"
                            alt="Preview gambar baru"
                        >
                    </div>

                </div>

            </div>

            <div class="addon-actions">

                <a href="index.php" class="addon-cancel">
                    Batal
                </a>

                <button
                    type="submit"
                    class="addon-submit"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </section>

    <footer class="addon-footer">
        &copy; <?= date('Y') ?> FlowerShop Admin Panel
    </footer>

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
