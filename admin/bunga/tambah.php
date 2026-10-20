
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

$nama_bunga = '';
$stok = '';
$error = '';

$uploadDir = '../../uploads/bunga/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama_bunga = trim($_POST['Nama_bunga'] ?? '');
    $stokInput = trim($_POST['Stok'] ?? '');

    // Validasi nama bunga
    if ($nama_bunga === '') {
        $error = 'Nama bunga wajib diisi.';
    } elseif (mb_strlen($nama_bunga) > 100) {
        $error = 'Nama bunga maksimal 100 karakter.';
    }

    // Validasi stok
    if ($error === '') {
        if (
            $stokInput === '' ||
            !ctype_digit($stokInput)
        ) {
            $error = 'Stok harus berupa bilangan bulat nol atau lebih.';
        } elseif ((int)$stokInput > 2147483647) {
            $error = 'Jumlah stok terlalu besar.';
        } else {
            $stok = (int)$stokInput;
        }
    }

    // Validasi foto
    $namaFileFoto = '';

    if ($error === '' && isset($_FILES['Foto_bunga'])) {

        $foto = $_FILES['Foto_bunga'];

        if ($foto['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($foto['error'] !== UPLOAD_ERR_OK) {
                $error = 'Foto gagal diunggah. Silakan coba kembali.';
            } elseif ($foto['size'] > 2 * 1024 * 1024) {
                $error = 'Ukuran foto maksimal 2 MB.';
            } else {

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($foto['tmp_name']);

                $tipeDiizinkan = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($tipeDiizinkan[$mime])) {
                    $error = 'Format foto harus JPG, PNG, atau WEBP.';
                } else {

                    $infoGambar = @getimagesize($foto['tmp_name']);

                    if ($infoGambar === false) {
                        $error = 'File yang diunggah bukan gambar yang valid.';
                    } else {
                        $namaFileFoto = 'bunga_' .
                            bin2hex(random_bytes(12)) . '.' .
                            $tipeDiizinkan[$mime];
                    }
                }
            }
        }
    }

    if ($error === '') {

        $fotoTersimpan = false;
        $pathFoto = '';

        if ($namaFileFoto !== '') {

            if (
                !is_dir($uploadDir) &&
                !mkdir($uploadDir, 0755, true)
            ) {
                $error = 'Folder upload foto tidak dapat dibuat.';
            } else {

                $pathFoto = $uploadDir . $namaFileFoto;

                if (
                    !move_uploaded_file(
                        $_FILES['Foto_bunga']['tmp_name'],
                        $pathFoto
                    )
                ) {
                    $error = 'Foto gagal disimpan ke folder upload.';
                } else {
                    $fotoTersimpan = true;
                }
            }
        }

        if ($error === '') {

            // Jika foto tidak diunggah, simpan string kosong.
            $fotoDatabase = $namaFileFoto;

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO bunga (Nama_bunga, Foto_bunga, Stok)
                 VALUES (?, ?, ?)"
            );

            if (!$stmt) {
                $error = 'Query tambah bunga gagal disiapkan.';
            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    'ssi',
                    $nama_bunga,
                    $fotoDatabase,
                    $stok
                );

                if (mysqli_stmt_execute($stmt)) {

                    mysqli_stmt_close($stmt);

                    header('Location: index.php?pesan=tambah');
                    exit;

                } else {

                    $error = 'Data bunga gagal ditambahkan. ' .
                        mysqli_stmt_error($stmt);

                    mysqli_stmt_close($stmt);

                    // Hapus foto jika penyimpanan database gagal.
                    if ($fotoTersimpan && is_file($pathFoto)) {
                        unlink($pathFoto);
                    }
                }
            }

            // Hapus foto jika query tidak berhasil disiapkan.
            if (
                $error !== '' &&
                $fotoTersimpan &&
                is_file($pathFoto) &&
                !isset($stmt)
            ) {
                unlink($pathFoto);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Tambah Bunga | FlowerShop Admin</title>

    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-bunga.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        .tambah-bunga-card {
            max-width: 760px;
            margin: 25px auto;
            padding: 28px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .tambah-bunga-card h2 {
            margin: 0 0 8px;
            color: #333;
            font-size: 22px;
        }

        .tambah-bunga-card .deskripsi {
            margin: 0 0 25px;
            color: #888;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #444;
            font-size: 14px;
            font-weight: 600;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="file"] {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #d86673;
            box-shadow: 0 0 0 3px rgba(216, 102, 115, 0.12);
        }

        .form-help {
            display: block;
            margin-top: 7px;
            color: #888;
            font-size: 12px;
        }

        .preview-container {
            display: none;
            margin-top: 15px;
        }

        .preview-container img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 1px solid #eee;
            border-radius: 10px;
        }

        .form-alert {
            margin-bottom: 20px;
            padding: 13px 15px;
            border-radius: 8px;
            background: #fff0f0;
            color: #b42332;
            font-size: 14px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .btn-batal,
        .btn-simpan {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-batal {
            background: #f2f2f2;
            color: #555;
        }

        .btn-simpan {
            background: #d86673;
            color: #fff;
        }

        .btn-simpan:hover {
            background: #c65362;
        }

        @media (max-width: 600px) {
            .tambah-bunga-card {
                margin: 15px auto;
                padding: 20px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn-batal,
            .btn-simpan {
                width: 100%;
                box-sizing: border-box;
            }
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


<header class="admin-header">

    <div class="brand">

        <a href="dashboard.php">

<div class="brand-logo">
    <img src="../../assets/img/logo flonuel.png" alt="Logo FlowerShop">
</div>

            <div class="brand-text">
                <span>Admin Panel</span>
            </div>
        </a>
    </div>

    <button
        type="button"
        class="mobile-menu-toggle"
        id="menuToggle"
        aria-label="Buka menu navigasi"
        aria-expanded="false"
    >
        <i class="fa-solid fa-bars"></i>
    </button>

    <nav class="admin-navigation" id="adminNavigation">
        <a href="../dashboard.php">Dashboard</a>
        <a href="../pesanan/index.php">Data Pesanan</a>
        <a href="../pembayaran/index.php">Data Pembayaran</a>
        <a href="../produk/index.php">Produk</a>
        <a href="../addon/index.php">Add-on</a>
        <a href="index.php" class="active">Bunga</a>
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
            <span>Admin FlowerShop</span>
        </div>

        <a
            href="../../logout.php"
            class="logout-button"
            onclick="return confirm('Yakin ingin logout?')"
            title="Logout"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>

</header>

<main class="admin-container bunga-page">

    <section class="bunga-heading">
        <div>
            <h1>Tambah Bunga</h1>
            <p>Tambahkan jenis bunga baru ke inventaris.</p>
        </div>
    </section>

    <section class="tambah-bunga-card">

        <h2>Form Tambah Bunga</h2>
        <p class="deskripsi">
            Lengkapi informasi bunga yang ingin ditambahkan.
        </p>

        <?php if ($error !== ''): ?>
            <div class="form-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label for="Nama_bunga">Nama Bunga *</label>

                <input
                    type="text"
                    id="Nama_bunga"
                    name="Nama_bunga"
                    maxlength="100"
                    placeholder="Contoh: Mawar Merah"
                    value="<?= e($nama_bunga) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="Stok">Jumlah Stok *</label>

                <input
                    type="number"
                    id="Stok"
                    name="Stok"
                    min="0"
                    step="1"
                    placeholder="Masukkan jumlah stok"
                    value="<?= e($stok) ?>"
                    required
                >

                <small class="form-help">
                    Masukkan jumlah stok bunga yang tersedia.
                </small>
            </div>

            <div class="form-group">
                <label for="Foto_bunga">Foto Bunga</label>

                <input
                    type="file"
                    id="Foto_bunga"
                    name="Foto_bunga"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                <small class="form-help">
                    Format JPG, PNG, atau WEBP. Maksimal 2 MB.
                    Foto boleh dikosongkan.
                </small>

                <div class="preview-container" id="previewContainer">
                    <img id="previewFoto" src="" alt="Preview foto bunga">
                </div>
            </div>

            <div class="form-actions">

                <a href="index.php" class="btn-batal">
                    <i class="fa-solid fa-arrow-left"></i>
                    Kembali
                </a>

                <button type="submit" class="btn-simpan">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Simpan Bunga
                </button>

            </div>

        </form>

    </section>

</main>

<script>
const menuToggle = document.getElementById('menuToggle');
const adminNavigation = document.getElementById('adminNavigation');

if (menuToggle && adminNavigation) {
    menuToggle.addEventListener('click', function () {
        const terbuka = adminNavigation.classList.toggle('show');

        menuToggle.setAttribute(
            'aria-expanded',
            terbuka ? 'true' : 'false'
        );

        menuToggle.innerHTML = terbuka
            ? '<i class="fa-solid fa-xmark"></i>'
            : '<i class="fa-solid fa-bars"></i>';
    });
}

// Preview foto sebelum disimpan
const inputFoto = document.getElementById('Foto_bunga');
const previewFoto = document.getElementById('previewFoto');
const previewContainer = document.getElementById('previewContainer');

inputFoto.addEventListener('change', function () {
    const file = this.files[0];

    if (!file) {
        previewContainer.style.display = 'none';
        previewFoto.src = '';
        return;
    }

    const tipeDiizinkan = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!tipeDiizinkan.includes(file.type)) {
        alert('Format foto harus JPG, PNG, atau WEBP.');
        this.value = '';
        previewContainer.style.display = 'none';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert('Ukuran foto maksimal 2 MB.');
        this.value = '';
        previewContainer.style.display = 'none';
        return;
    }

    previewFoto.src = URL.createObjectURL(file);
    previewContainer.style.display = 'block';
});
</script>

</body>
</html>