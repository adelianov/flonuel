
<?php
session_start();
require_once '../../config/koneksi.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

// Ambil ID bunga
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

// Ambil data bunga
$stmt = mysqli_prepare(
    $conn,
    "SELECT Bunga_id, Nama_bunga, Foto_bunga, Stok
     FROM bunga
     WHERE Bunga_id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$bunga = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$bunga) {
    header('Location: index.php?error=not_found');
    exit;
}

$error = '';
$nama_bunga = $bunga['Nama_bunga'];
$stok = (int) $bunga['Stok'];
$foto_lama = $bunga['Foto_bunga'] ?? '';

$upload_dir = '../../uploads/bunga/';
$upload_url = '../../uploads/bunga/';

// Pastikan folder upload tersedia
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_bunga = trim($_POST['nama_bunga'] ?? '');
    $stok_input = $_POST['stok'] ?? '';

    if ($nama_bunga === '') {
        $error = 'Nama bunga wajib diisi.';
    } elseif (!is_numeric($stok_input) ||
              (string)(int)$stok_input !== (string)$stok_input ||
              (int)$stok_input < 0) {
        $error = 'Stok harus berupa bilangan bulat minimal 0.';
    } else {
        $stok = (int)$stok_input;
        $foto_baru = $foto_lama;
        $file_diunggah = false;

        // Proses foto jika admin memilih foto baru
        if (isset($_FILES['foto_bunga']) &&
            $_FILES['foto_bunga']['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES['foto_bunga']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Foto gagal diunggah. Silakan coba kembali.';
            } elseif ($_FILES['foto_bunga']['size'] > 2 * 1024 * 1024) {
                $error = 'Ukuran foto maksimal 2 MB.';
            } else {
                $tmp_file = $_FILES['foto_bunga']['tmp_name'];
                $info = getimagesize($tmp_file);

                if ($info === false) {
                    $error = 'File yang dipilih bukan gambar yang valid.';
                } else {
                    $mime = $info['mime'];
                    $ekstensi = '';

                    if ($mime === 'image/jpeg') {
                        $ekstensi = 'jpg';
                    } elseif ($mime === 'image/png') {
                        $ekstensi = 'png';
                    } elseif ($mime === 'image/webp') {
                        $ekstensi = 'webp';
                    } else {
                        $error = 'Format foto harus JPG, PNG, atau WEBP.';
                    }

                    if ($error === '') {
                        $nama_file = 'bunga_' . uniqid() . '.' . $ekstensi;
                        $tujuan = $upload_dir . $nama_file;

                        if (move_uploaded_file($tmp_file, $tujuan)) {
                            $foto_baru = $nama_file;
                            $file_diunggah = true;
                        } else {
                            $error = 'Foto gagal disimpan ke folder uploads/bunga/.';
                        }
                    }
                }
            }
        }

        // Simpan perubahan ke database
        if ($error === '') {
            $update = mysqli_prepare(
                $conn,
                "UPDATE bunga
                 SET Nama_bunga = ?, Foto_bunga = ?, Stok = ?
                 WHERE Bunga_id = ?"
            );

            mysqli_stmt_bind_param(
                $update,
                "ssii",
                $nama_bunga,
                $foto_baru,
                $stok,
                $id
            );

            if (mysqli_stmt_execute($update)) {
                mysqli_stmt_close($update);

                // Hapus foto lama setelah data berhasil diperbarui
                if ($file_diunggah && $foto_lama !== '') {
                    $foto_lama_path = $upload_dir . basename($foto_lama);

                    if (is_file($foto_lama_path)) {
                        unlink($foto_lama_path);
                    }
                }

                header('Location: index.php?pesan=edit'); exit;
                exit;
            } else {
                if ($file_diunggah) {
                    $path_baru = $upload_dir . basename($foto_baru);
                    if (is_file($path_baru)) {
                        unlink($path_baru);
                    }
                }

                $error = 'Data bunga gagal diperbarui.';
                mysqli_stmt_close($update);
            }
        }
    }
}

// Tentukan lokasi foto yang ditampilkan
$foto_path = '';

if ($foto_lama !== '') {
    $kandidat = [
        '../../uploads/bunga/' . basename($foto_lama),
        '../../assets/img/bunga/' . basename($foto_lama)
    ];

    foreach ($kandidat as $path) {
        if (is_file($path)) {
            $foto_path = $path;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bunga | FlowerShop</title>

    <link rel="stylesheet" href="../../assets/css/admin-bunga.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fffafd;
            color: #55414b;
            font-family: inherit;
            font-size: 13px;
        }

        .edit-bunga-page {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 24px;
        }

        .edit-bunga-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .edit-bunga-eyebrow {
            color: #d63384;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .edit-bunga-heading h1 {
            margin: 0 0 7px;
            color: #35242d;
            font-size: 25px;
            font-weight: 750;
        }

        .edit-bunga-heading p {
            margin: 0;
            color: #8b7782;
            line-height: 1.6;
        }

        .edit-bunga-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid #f1e5eb;
            border-radius: 9px;
            background: #fff;
            color: #55414b;
            text-decoration: none;
            font-size: 12px;
            font-weight: 650;
        }

        .edit-bunga-back:hover {
            color: #d63384;
            border-color: #e8b8d0;
        }

        .edit-bunga-card {
            padding: 25px;
            background: #fff;
            border: 1px solid #f1e5eb;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(70, 30, 50, 0.04);
        }

        .edit-bunga-card h2 {
            margin: 0 0 6px;
            color: #35242d;
            font-size: 16px;
        }

        .edit-bunga-description {
            margin: 0 0 24px;
            color: #9b8791;
            font-size: 12px;
            line-height: 1.6;
        }

        .edit-bunga-alert {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #f3c5d6;
            border-radius: 9px;
            background: #fff2f7;
            color: #a61e59;
            line-height: 1.6;
        }

        .edit-bunga-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 22px;
        }

        .edit-bunga-field {
            margin-bottom: 20px;
        }

        .edit-bunga-field.full {
            grid-column: 1 / -1;
        }

        .edit-bunga-field label {
            display: block;
            margin-bottom: 8px;
            color: #55414b;
            font-size: 12px;
            font-weight: 700;
        }

        .edit-bunga-field input[type="text"],
        .edit-bunga-field input[type="number"],
        .edit-bunga-field input[type="file"] {
            display: block;
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #eadde4;
            border-radius: 9px;
            outline: none;
            background: #fff;
            color: #55414b;
            font: inherit;
        }

        .edit-bunga-field input:focus {
            border-color: #d63384;
            box-shadow: 0 0 0 3px rgba(214, 51, 132, 0.09);
        }

        .edit-bunga-hint {
            display: block;
            margin-top: 7px;
            color: #9b8791;
            font-size: 11px;
            line-height: 1.5;
        }

        .edit-bunga-preview {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 12px;
            padding: 12px;
            border: 1px solid #f1e5eb;
            border-radius: 11px;
            background: #fff7fb;
        }

        .edit-bunga-preview img {
            width: 105px;
            height: 105px;
            object-fit: cover;
            border-radius: 9px;
            border: 1px solid #f1e5eb;
            background: #fff;
        }

        .edit-bunga-preview strong {
            display: block;
            margin-bottom: 5px;
            color: #35242d;
            font-size: 12px;
        }

        .edit-bunga-preview span {
            color: #8b7782;
            font-size: 11px;
            line-height: 1.5;
        }

        .edit-bunga-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid #f3e9ee;
        }

        .edit-bunga-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 17px;
            border: 1px solid transparent;
            border-radius: 9px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .edit-bunga-btn-primary {
            background: #d63384;
            color: #fff;
        }

        .edit-bunga-btn-primary:hover {
            background: #b82b6f;
        }

        .edit-bunga-btn-secondary {
            border-color: #f1e5eb;
            background: #fff;
            color: #55414b;
        }

        .edit-bunga-btn-secondary:hover {
            background: #fff7fb;
        }

        @media (max-width: 650px) {
            .edit-bunga-page {
                padding: 20px 14px;
            }

            .edit-bunga-card {
                padding: 18px;
            }

            .edit-bunga-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .edit-bunga-field.full {
                grid-column: auto;
            }

            .edit-bunga-heading h1 {
                font-size: 21px;
            }

            .edit-bunga-actions {
                flex-direction: column-reverse;
            }

            .edit-bunga-actions .edit-bunga-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<main class="edit-bunga-page">
    <header class="edit-bunga-heading">
        <div>
            <div class="edit-bunga-eyebrow">Manajemen Bunga</div>
            <h1>Edit Data Bunga</h1>
            <p>Perbarui informasi bunga dan stok yang tersedia.</p>
        </div>

        <a href="index.php" class="edit-bunga-back">
            &larr; Kembali ke Data Bunga
        </a>
    </header>

    <section class="edit-bunga-card">
        <h2>Informasi Bunga</h2>
        <p class="edit-bunga-description">
            Ubah data pada kolom yang diperlukan, lalu simpan perubahan.
        </p>

        <?php if ($error !== ''): ?>
            <div class="edit-bunga-alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="edit-bunga-grid">
                <div class="edit-bunga-field">
                    <label for="nama_bunga">Nama Bunga *</label>
                    <input
                        type="text"
                        id="nama_bunga"
                        name="nama_bunga"
                        value="<?= htmlspecialchars($nama_bunga, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Contoh: Mawar Merah"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="edit-bunga-field">
                    <label for="stok">Stok Bunga *</label>
                    <input
                        type="number"
                        id="stok"
                        name="stok"
                        value="<?= (int)$stok ?>"
                        min="0"
                        required
                    >
                    <span class="edit-bunga-hint">
                        Masukkan jumlah stok bunga yang tersedia.
                    </span>
                </div>

                <div class="edit-bunga-field full">
                    <label for="foto_bunga">Foto Bunga</label>
                    <input
                        type="file"
                        id="foto_bunga"
                        name="foto_bunga"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >
                    <span class="edit-bunga-hint">
                        Pilih foto baru jika ingin mengganti foto saat ini.
                        Format JPG, PNG, atau WEBP, maksimal 2 MB.
                    </span>

                    <div class="edit-bunga-preview">
                        <?php if ($foto_path !== ''): ?>
                            <img
                                id="previewFoto"
                                src="<?= htmlspecialchars($foto_path, ENT_QUOTES, 'UTF-8') ?>"
                                alt="Foto bunga"
                            >
                        <?php else: ?>
                            <div
                                id="previewFotoPlaceholder"
                                style="width:105px;height:105px;border-radius:9px;border:1px solid #f1e5eb;background:#fff;display:flex;align-items:center;justify-content:center;color:#9b8791;text-align:center;padding:8px;"
                            >
                                Foto belum tersedia
                            </div>
                            <img
                                id="previewFoto"
                                src=""
                                alt="Pratinjau foto bunga"
                                style="display:none;"
                            >
                        <?php endif; ?>

                        <div>
                            <strong>Foto saat ini</strong>
                            <span>Pratinjau akan berubah saat kamu memilih foto baru.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="edit-bunga-actions">
                <a href="index.php" class="edit-bunga-btn edit-bunga-btn-secondary">
                    Batal
                </a>
                <button
                    type="submit"
                    class="edit-bunga-btn edit-bunga-btn-primary"
                >
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </section>
</main>

<script>
const inputFoto = document.getElementById('foto_bunga');
const previewFoto = document.getElementById('previewFoto');
const placeholderFoto = document.getElementById('previewFotoPlaceholder');

inputFoto.addEventListener('change', function () {
    const file = this.files[0];

    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowedTypes.includes(file.type)) {
        alert('Format foto harus JPG, PNG, atau WEBP.');
        this.value = '';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran foto maksimal 2 MB.');
        this.value = '';
        return;
    }

    previewFoto.src = URL.createObjectURL(file);
    previewFoto.style.display = 'block';

    if (placeholderFoto) {
        placeholderFoto.style.display = 'none';
    }
});
</script>

</body>
</html>
