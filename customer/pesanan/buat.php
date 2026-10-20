
<?php
session_start();
require_once '../../config/koneksi.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header('Location: ../../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error = '';

function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$stmtUser = mysqli_prepare(
    $conn,
    "SELECT User_id, Nama, No_Hp, Email
     FROM `user`
     WHERE User_id = ?
     LIMIT 1"
);

if (!$stmtUser) {
    die('Query user gagal: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmtUser, 'i', $user_id);
mysqli_stmt_execute($stmtUser);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUser));
mysqli_stmt_close($stmtUser);

if (!$user) {
    session_destroy();
    header('Location: ../../login.php');
    exit;
}

$produk_id = (int) ($_GET['produk_id'] ?? 0);

if ($produk_id <= 0) {
    header('Location: ../katalog.php');
    exit;
}

$stmtProduk = mysqli_prepare(
    $conn,
    "SELECT
        p.Produk_id,
        p.Nama_produk,
        p.Harga,
        p.Jumlah_bunga,
        p.Foto_produk,
        p.Deskripsi_produk,
        p.Status,
        k.Nama_kategori
     FROM produk p
     LEFT JOIN kategori_produk k
        ON p.Kategori_id = k.Kategori_id
     WHERE p.Produk_id = ?
       AND p.Status = 'aktif'
     LIMIT 1"
);

if (!$stmtProduk) {
    die('Query produk gagal: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmtProduk, 'i', $produk_id);
mysqli_stmt_execute($stmtProduk);
$produk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtProduk));
mysqli_stmt_close($stmtProduk);

if (!$produk) {
    header('Location: ../katalog.php');
    exit;
}

$bunga_list = [];

$resultBunga = mysqli_query(
    $conn,
    "SELECT Bunga_id, Nama_bunga, Foto_bunga, Stok
     FROM bunga
     WHERE Stok > 0
     ORDER BY Nama_bunga ASC"
);

if (!$resultBunga) {
    die('Gagal mengambil data bunga: ' . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($resultBunga)) {
    $bunga_list[] = $row;
}

$wrapping_list = [];

$resultWrapping = mysqli_query(
    $conn,
    "SELECT Wrapping_id, Warna_wrapping, Status_wrapping
     FROM wrapping
     ORDER BY Warna_wrapping ASC"
);

if (!$resultWrapping) {
    die('Gagal mengambil data wrapping: ' . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($resultWrapping)) {
    $wrapping_list[] = $row;
}

$pilihan_post = $_POST['pilihan_bunga'] ?? [];

if (!is_array($pilihan_post)) {
    $pilihan_post = [];
}

$pilihan_post = array_values(array_unique(array_filter(
    array_map('intval', $pilihan_post),
    function ($id) {
        return $id > 0;
    }
)));

$wrapping_post = (int) ($_POST['wrapping_id'] ?? 0);
$catatan_post = trim((string) ($_POST['catatan_custom'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $jumlah_pilihan = count($pilihan_post);
    $bunga_ids_valid = [];
    $bunga_valid = [];

    if ($jumlah_pilihan < 1) {
        $error = 'Silakan pilih minimal 1 jenis bunga.';
    } elseif ($jumlah_pilihan > 3) {
        $error = 'Maksimal hanya boleh memilih 3 jenis bunga.';
    } else {
        $bunga_tersedia = [];

        $resultCekBunga = mysqli_query(
            $conn,
            "SELECT Bunga_id, Nama_bunga
             FROM bunga
             WHERE Stok > 0"
        );

        if (!$resultCekBunga) {
            $error = 'Gagal memvalidasi bunga.';
        } else {
            while ($row = mysqli_fetch_assoc($resultCekBunga)) {
                $bunga_tersedia[(int) $row['Bunga_id']] =
                    $row['Nama_bunga'];
            }

            foreach ($pilihan_post as $id_bunga) {
                if (!isset($bunga_tersedia[$id_bunga])) {
                    $error = 'Bunga yang dipilih sudah tidak tersedia. Muat ulang halaman.';
                    break;
                }

                $bunga_ids_valid[] = $id_bunga;
                $bunga_valid[] = $bunga_tersedia[$id_bunga];
            }
        }
    }

    $dataWrapping = null;

    if ($error === '' && $wrapping_post <= 0) {
        $error = 'Silakan pilih wrapping.';
    }

    if ($error === '') {
$stmtWrapping = mysqli_prepare(
    $conn,
    "SELECT Wrapping_id, Warna_wrapping, Status_wrapping
     FROM wrapping
     WHERE Wrapping_id = ?
       AND Status_wrapping = 'Ready'
     LIMIT 1"
        );

        if (!$stmtWrapping) {
            $error = 'Gagal memeriksa wrapping.';
        } else {
            mysqli_stmt_bind_param(
                $stmtWrapping,
                'i',
                $wrapping_post
            );

            mysqli_stmt_execute($stmtWrapping);
            $dataWrapping = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmtWrapping)
            );
            mysqli_stmt_close($stmtWrapping);

            if (!$dataWrapping) {
                $error = 'Wrapping yang dipilih tidak ditemukan.';
            }
        }
    }

    if ($error === '') {
        $harga_produk = (float) $produk['Harga'];

        $_SESSION['checkout_pesanan'] = [
            'user_id' => $user_id,

            'produk_id' => (int) $produk['Produk_id'],
            'nama_produk' => $produk['Nama_produk'],
            'harga_produk' => $harga_produk,

            'pilihan_bunga' => implode(', ', $bunga_valid),
            'pilihan_bunga_ids' => $bunga_ids_valid,
            'jumlah_bunga_pilihan' => count($bunga_ids_valid),

            'wrapping' => $dataWrapping['Warna_wrapping'],
            'wrapping_id' => (int) $dataWrapping['Wrapping_id'],

            'catatan_custom' => $catatan_post,

            // Add-On opsional: boleh tidak memilih apa pun.
            'addons' => [],
            'total_addon' => 0,

            // Informasi pickup diisi pada tahap pickup.
            'pickup_id' => 0,
            'tanggal_pickup' => '',
            'jam_pickup' => '',

            // Total awal tanpa Add-On.
            'total_harga' => $harga_produk
        ];

        header('Location: addon.php');
        exit;
    }
}

$foto_produk = trim((string) ($produk['Foto_produk'] ?? ''));
$foto_path = '';

if ($foto_produk !== '') {
    $namaFileProduk = basename($foto_produk);

    $pathUpload = __DIR__ . '/../../uploads/produk/' . $namaFileProduk;
    $pathAsset = __DIR__ . '/../../assets/img/produk/' . $namaFileProduk;

    if (is_file($pathUpload)) {
        $foto_path = '../../uploads/produk/' . rawurlencode($namaFileProduk);
    } elseif (is_file($pathAsset)) {
        $foto_path = '../../assets/img/produk/' . rawurlencode($namaFileProduk);
    }
}

$nama_user = $user['Nama'] ?? 'Customer';
$nama_produk = $produk['Nama_produk'] ?? 'Produk';
$harga_produk = (float) $produk['Harga'];
$deskripsi_produk = $produk['Deskripsi_produk'] ?? '';
$jumlah_bunga_produk = (int) $produk['Jumlah_bunga'];
$kategori_produk = $produk['Nama_kategori'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Pesanan</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../../assets/css/buat.css">

    <style>
        .choice-label-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 12px;
        }

        .flower-counter {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 20px;
            background: #fff1f3;
            color: #d86673;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .flower-counter.maximum {
            background: #d86673;
            color: #fff;
        }

        .flower-option input[type="checkbox"]:disabled
        + .flower-option-content {
            opacity: .45;
            cursor: not-allowed;
        }

        .order-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 10px;
            background: #fff1f3;
            border: 1px solid #f1cfd4;
            color: #c44e5c;
            font-size: 13px;
        }

        .order-info {
            margin-top: 18px;
            padding: 12px 14px;
            background: #fff7f8;
            border-radius: 8px;
            color: #9a7d80;
            font-size: 11px;
            line-height: 1.6;
        }

        .flower-stock {
            margin-top: 5px;
            color: #999;
            font-size: 10px;
        }

        .flower-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            min-height: 90px;
            color: #d86673;
            background: #fff1f3;
        }

        .flower-placeholder i {
            font-size: 24px;
        }

        .flower-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (max-width: 600px) {
            .choice-label-wrapper {
                align-items: flex-start;
                flex-direction: column;
                gap: 7px;
            }
        }
    </style>
</head>

<body>

<aside class="dashboard-sidebar" id="sidebar">
    <a href="../dashboard.php" class="brand">FlowerShop</a>

    <nav class="sidebar-menu">
        <div class="sidebar-label">MENU</div>

        <a href="../dashboard.php">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="index.php" class="active">
            <i class="fa-solid fa-bag-shopping"></i>
            <span>Pesanan Saya</span>
        </a>

        <a href="../katalog.php">
            <i class="fa-solid fa-book-open"></i>
            <span>Katalog</span>
        </a>

        <a href="../pembayaran/index.php">
            <i class="fa-solid fa-credit-card"></i>
            <span>Pembayaran</span>
        </a>

        <a href="../../logout.php"
           onclick="return confirm('Apakah Anda yakin ingin logout?')">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">

    <header class="topbar">
        <div class="topbar-left">
            <button type="button"
                    class="mobile-menu"
                    id="mobileMenu"
                    aria-label="Buka menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">Buat Pesanan</div>
        </div>

        <div class="profile-mini">
            <div class="profile-info">
                <div class="profile-name"><?= e($nama_user); ?></div>
                <div class="profile-role">Customer</div>
            </div>

            <div class="profile-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
        </div>
    </header>

    <section class="content order-create-content">

        <?php if ($error !== ''): ?>
            <div class="order-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="create-order-layout">

            <div class="selected-product">

                <div class="selected-product-name">
                    <?= e($nama_produk); ?>
                </div>

                <?php if ($kategori_produk !== ''): ?>
                    <div style="margin-bottom:10px;color:#999;font-size:10px;">
                        <?= e($kategori_produk); ?>
                    </div>
                <?php endif; ?>

                <div class="selected-product-price">
                    <?= rupiah($harga_produk); ?>
                </div>

                <div class="selected-product-image">
                    <?php if ($foto_path !== ''): ?>
                        <img src="<?= e($foto_path); ?>"
                             alt="<?= e($nama_produk); ?>">
                    <?php else: ?>
                        <div class="selected-no-image">
                            <i class="fa-solid fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (trim($deskripsi_produk) !== ''): ?>
                    <div class="selected-product-description">
                        <?= nl2br(e($deskripsi_produk)); ?>
                    </div>
                <?php endif; ?>

                <?php if ($jumlah_bunga_produk > 0): ?>
                    <div class="selected-flower-count">
                        <i class="fa-solid fa-seedling"></i>
                        <?= $jumlah_bunga_produk; ?> bunga
                    </div>
                <?php endif; ?>

                <?php if ($kategori_produk !== ''): ?>
                    <div class="selected-flower-count">
                        <i class="fa-solid fa-tag"></i>
                        <?= e($kategori_produk); ?>
                    </div>
                <?php endif; ?>

            </div>

            <div class="flower-choice-card">

                <div class="choice-title">Detail Pesanan</div>

                <form action="" method="POST" id="orderForm">

                    <div class="choice-group">

                        <div class="choice-label-wrapper">
                            <label class="choice-label">Pilih Bunga</label>

                            <span class="flower-counter" id="flowerCounter">
                                0 / 3 bunga dipilih
                            </span>
                        </div>

                        <div style="margin-bottom:15px;color:#999;font-size:11px;">
                            Pilih minimal 1 dan maksimal 3 jenis bunga dari stok yang tersedia.
                        </div>

                        <div class="flower-options">

                            <?php if (!empty($bunga_list)): ?>

                                <?php foreach ($bunga_list as $bunga): ?>
                                    <?php
                                    $bunga_id = (int) $bunga['Bunga_id'];
                                    $nama_bunga = $bunga['Nama_bunga'];
                                    $foto_bunga = trim((string) ($bunga['Foto_bunga'] ?? ''));
                                    $stok_bunga = (int) $bunga['Stok'];
                                    $foto_bunga_path = '';

                                    if ($foto_bunga !== '') {
                                        $namaFileBunga = basename($foto_bunga);

                                        $pathUploadBunga =
                                            __DIR__ . '/../../uploads/bunga/' . $namaFileBunga;

                                        $pathAssetBunga =
                                            __DIR__ . '/../../assets/img/bunga/' . $namaFileBunga;

                                        if (is_file($pathUploadBunga)) {
                                            $foto_bunga_path =
                                                '../../uploads/bunga/' . rawurlencode($namaFileBunga);
                                        } elseif (is_file($pathAssetBunga)) {
                                            $foto_bunga_path =
                                                '../../assets/img/bunga/' . rawurlencode($namaFileBunga);
                                        }
                                    }
                                    ?>

                                    <label class="flower-option">
                                        <input
                                            type="checkbox"
                                            name="pilihan_bunga[]"
                                            value="<?= $bunga_id; ?>"
                                            <?= in_array($bunga_id, $pilihan_post, true)
                                                ? 'checked'
                                                : ''; ?>
                                        >

                                        <div class="flower-option-content">
                                            <div class="flower-image">

                                                <?php if ($foto_bunga_path !== ''): ?>
                                                    <img
                                                        src="<?= e($foto_bunga_path); ?>"
                                                        alt="<?= e($nama_bunga); ?>"
                                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                                                    >
                                                <?php endif; ?>

                                                <div
                                                    class="flower-placeholder"
                                                    style="display:<?= $foto_bunga_path !== '' ? 'none' : 'flex'; ?>;"
                                                >
                                                    <i class="fa-solid fa-seedling"></i>
                                                </div>
                                            </div>

                                            <div class="flower-name">
                                                <?= e($nama_bunga); ?>
                                            </div>

                                            <div class="flower-stock">
                                                Stok tersedia: <?= $stok_bunga; ?>
                                            </div>
                                        </div>
                                    </label>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <p style="color:#999;font-size:13px;padding:15px;">
                                    Saat ini belum ada bunga yang tersedia.
                                    Silakan hubungi admin.
                                </p>

                            <?php endif; ?>

                        </div>
                    </div>

                
<div class="choice-group">
    <label for="wrapping_id" class="choice-label">
        Pilih Wrapping
    </label>

    <select
        name="wrapping_id"
        id="wrapping_id"
        class="choice-input"
        required
    >
        <option value="">-- Pilih Wrapping --</option>

        <?php foreach ($wrapping_list as $item): ?>
            <?php
            $statusWrapping = $item['Status_wrapping'] ?? 'Ready';
            $tersedia = ($statusWrapping === 'Ready');
            ?>

            <option
                value="<?= (int) $item['Wrapping_id']; ?>"
                <?= $wrapping_post === (int) $item['Wrapping_id']
                    ? 'selected'
                    : ''; ?>
                <?= !$tersedia ? 'disabled' : ''; ?>
            >
                <?= e($item['Warna_wrapping']); ?>
                <?= !$tersedia ? ' - Tidak Tersedia' : ''; ?>
            </option>
        <?php endforeach; ?>

    </select>

    <small style="display:block;margin-top:8px;color:#999;font-size:11px;">
        Wrapping bertanda Tidak Tersedia sedang dinonaktifkan oleh admin.
    </small>
</div>

<div class="choice-group">
    <label for="catatan_custom" class="choice-label">
        Catatan Custom
    </label>

    <textarea
        name="catatan_custom"
        id="catatan_custom"
        class="choice-textarea"
        maxlength="2000"
        placeholder="Tulis permintaan khusus untuk pesanan..."
    ><?= e($catatan_post); ?></textarea>
</div>

<button
    type="submit"
    class="payment-button"
    <?= empty($bunga_list) || empty($wrapping_list)
        ? 'disabled'
        : ''; ?>
>
    <i class="fa-solid fa-arrow-right"></i>
    Lanjut Pilih Add-On
</button>

                </form>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mobileMenu = document.getElementById('mobileMenu');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (mobileMenu && sidebar && overlay) {
        mobileMenu.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    const checkboxes = Array.from(
        document.querySelectorAll('input[name="pilihan_bunga[]"]')
    );

    const counter = document.getElementById('flowerCounter');
    const form = document.getElementById('orderForm');
    const wrapping = document.getElementById('wrapping_id');

    function updateCounter() {
        const checkedCount = checkboxes.filter(
            checkbox => checkbox.checked
        ).length;

        if (counter) {
            counter.textContent = checkedCount + ' / 3 bunga dipilih';
            counter.classList.toggle('maximum', checkedCount >= 3);
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.disabled =
                !checkbox.checked && checkedCount >= 3;
        });
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateCounter);
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            const checkedCount = checkboxes.filter(
                checkbox => checkbox.checked
            ).length;

            if (checkboxes.length === 0) {
                event.preventDefault();
                alert('Saat ini belum ada bunga yang tersedia.');
                return;
            }

            if (checkedCount < 1) {
                event.preventDefault();
                alert('Silakan pilih minimal 1 jenis bunga.');
                return;
            }

            if (checkedCount > 3) {
                event.preventDefault();
                alert('Maksimal hanya boleh memilih 3 jenis bunga.');
                return;
            }

            if (!wrapping || wrapping.value === '') {
                event.preventDefault();
                alert('Silakan pilih wrapping.');

                if (wrapping) {
                    wrapping.focus();
                }
            }
        });
    }

    updateCounter();
});
</script>

</body>
</html>
