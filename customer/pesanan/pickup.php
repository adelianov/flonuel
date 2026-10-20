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

if (
    !isset($_SESSION['checkout_pesanan']) ||
    !is_array($_SESSION['checkout_pesanan'])
) {
    header('Location: ../katalog.php');
    exit;
}

$checkout = $_SESSION['checkout_pesanan'];

function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


$stmt = mysqli_prepare(
    $conn,
    "SELECT User_id, Nama, No_Hp, Email
     FROM user
     WHERE User_id = ?
     LIMIT 1"
);

if (!$stmt) {
    die('Query customer gagal: ' . e(mysqli_error($conn)));
}

mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header('Location: ../../login.php');
    exit;
}

$produk_id = (int)($checkout['produk_id'] ?? 0);
$pilihan_bunga = trim((string)($checkout['pilihan_bunga'] ?? ''));
$wrapping = trim((string)($checkout['wrapping'] ?? ''));
$catatan_custom = trim((string)($checkout['catatan_custom'] ?? ''));
$wrapping_id = (int)($checkout['wrapping_id'] ?? 0);

$pilihan_bunga_ids = $checkout['pilihan_bunga_ids'] ?? [];

if (!is_array($pilihan_bunga_ids)) {
    $pilihan_bunga_ids = [];
}

$pilihan_bunga_ids = array_values(array_unique(array_filter(
    array_map('intval', $pilihan_bunga_ids),
    function ($id) {
        return $id > 0;
    }
)));

$addons = $checkout['addons'] ?? [];

if (!is_array($addons)) {
    $addons = [];
}


if (
    $produk_id <= 0 ||
    $wrapping_id <= 0 ||
    $pilihan_bunga === '' ||
    $wrapping === '' ||
    count($pilihan_bunga_ids) < 1 ||
    count($pilihan_bunga_ids) > 3
) {
    unset($_SESSION['checkout_pesanan']);
    header('Location: ../katalog.php');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT Produk_id, Nama_produk, Harga, Foto_produk
     FROM produk
     WHERE Produk_id = ?
       AND Status = 'aktif'
     LIMIT 1"
);

if (!$stmt) {
    die('Query produk gagal: ' . e(mysqli_error($conn)));
}

mysqli_stmt_bind_param($stmt, 'i', $produk_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$produk = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$produk) {
    unset($_SESSION['checkout_pesanan']);
    header('Location: ../katalog.php');
    exit;
}

$foto_produk = trim((string)($produk['Foto_produk'] ?? ''));
$foto_path = '';

if ($foto_produk !== '') {
    $nama_file = basename($foto_produk);

    if (is_file(__DIR__ . '/../../uploads/produk/' . $nama_file)) {
        $foto_path = '../../uploads/produk/' . $nama_file;
    } elseif (is_file(__DIR__ . '/../../assets/img/produk/' . $nama_file)) {
        $foto_path = '../../assets/img/produk/' . $nama_file;
    }
}

$sqlJadwal = "
    SELECT Pickup_id, Tanggal_pickup, Jam_pickup
    FROM jadwal_pickup
    WHERE Tanggal_pickup >= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY Tanggal_pickup ASC, Jam_pickup ASC
";

$resultJadwal = mysqli_query($conn, $sqlJadwal);

if (!$resultJadwal) {
    die('Query jadwal pickup gagal: ' . e(mysqli_error($conn)));
}

$jadwal_list = [];
$jadwal_json = [];

while ($row = mysqli_fetch_assoc($resultJadwal)) {
    $jadwal_list[] = $row;

    $jadwal_json[] = [
        'id' => (int)$row['Pickup_id'],
        'tanggal' => date('Y-m-d', strtotime($row['Tanggal_pickup'])),
        'jam' => date('H:i', strtotime($row['Jam_pickup']))
    ];
}

$selected_date = $_POST['tanggal_pickup'] ?? '';
$selected_time = $_POST['jam_pickup'] ?? '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_pickup = trim($_POST['tanggal_pickup'] ?? '');
    $jam_pickup = trim($_POST['jam_pickup'] ?? '');
    $pickup_id = (int)($_POST['pickup_id'] ?? 0);

    // Validasi format tanggal dan jam
    $tanggalObj = DateTime::createFromFormat('!Y-m-d', $tanggal_pickup);
    $tanggalValid = $tanggalObj &&
        $tanggalObj->format('Y-m-d') === $tanggal_pickup;
        
    $minDateValid = date('Y-m-d', strtotime('+2 days'));

    if (!$tanggalValid) {
        $error = 'Tanggal pickup tidak valid.';
    } elseif ($tanggal_pickup < $minDateValid) {
        $error = 'Pesanan baru bisa diambil minimal 2 hari dari sekarang.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $jam_pickup)) {
        $error = 'Jam pickup tidak valid.';
    } elseif ($pickup_id <= 0) {
        $error = 'Silakan pilih jadwal pickup yang tersedia.';
    }

    if ($error === '') {
        $transactionStarted = false;

        try {
            mysqli_begin_transaction($conn);
            $transactionStarted = true;

            $stmtPickup = mysqli_prepare(
                $conn,
                "SELECT Pickup_id, Tanggal_pickup, Jam_pickup
                 FROM jadwal_pickup
                 WHERE Pickup_id = ?
                   AND Tanggal_pickup = ?
                   AND TIME(Jam_pickup) = ?
                   AND Tanggal_pickup >= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
                 FOR UPDATE"
            );

            if (!$stmtPickup) {
                throw new RuntimeException('Query jadwal pickup gagal.');
            }

            mysqli_stmt_bind_param(
                $stmtPickup,
                'iss',
                $pickup_id,
                $tanggal_pickup,
                $jam_pickup
            );

            if (!mysqli_stmt_execute($stmtPickup)) {
                throw new RuntimeException('Gagal memvalidasi jadwal pickup.');
            }

            $resultPickup = mysqli_stmt_get_result($stmtPickup);
            $pickup = mysqli_fetch_assoc($resultPickup);
            mysqli_stmt_close($stmtPickup);

            if (!$pickup) {
                throw new RuntimeException(
                    'Jadwal pickup tidak tersedia atau sudah lewat. Silakan pilih jadwal lain.'
                );
            }

            $stmtProduk = mysqli_prepare(
                $conn,
                "SELECT Produk_id, Nama_produk, Harga
                 FROM produk
                 WHERE Produk_id = ?
                   AND Status = 'aktif'
                 FOR UPDATE"
            );

            if (!$stmtProduk) {
                throw new RuntimeException('Query produk gagal.');
            }

            mysqli_stmt_bind_param($stmtProduk, 'i', $produk_id);
            mysqli_stmt_execute($stmtProduk);

            $resultProduk = mysqli_stmt_get_result($stmtProduk);
            $produkAktual = mysqli_fetch_assoc($resultProduk);
            mysqli_stmt_close($stmtProduk);

            if (!$produkAktual) {
                throw new RuntimeException('Produk tidak tersedia.');
            }

            $hargaProduk = (float)$produkAktual['Harga'];

            $stmtWrapping = mysqli_prepare(
                $conn,
                "SELECT Wrapping_id, Warna_wrapping
                 FROM wrapping
                 WHERE Wrapping_id = ?
                 LIMIT 1"
            );

            if (!$stmtWrapping) {
                throw new RuntimeException('Query wrapping gagal.');
            }

            mysqli_stmt_bind_param($stmtWrapping, 'i', $wrapping_id);
            mysqli_stmt_execute($stmtWrapping);

            $resultWrapping = mysqli_stmt_get_result($stmtWrapping);
            $dataWrapping = mysqli_fetch_assoc($resultWrapping);
            mysqli_stmt_close($stmtWrapping);

            if (!$dataWrapping) {
                throw new RuntimeException('Pilihan wrapping tidak valid.');
            }

            $stmtCekBunga = mysqli_prepare(
                $conn,
                "SELECT Bunga_id, Nama_bunga, Stok
                 FROM bunga
                 WHERE Bunga_id = ?
                 FOR UPDATE"
            );

            $stmtKurangiBunga = mysqli_prepare(
                $conn,
                "UPDATE bunga
                 SET Stok = Stok - 1
                 WHERE Bunga_id = ?
                   AND Stok > 0"
            );

            if (!$stmtCekBunga || !$stmtKurangiBunga) {
                throw new RuntimeException('Query stok bunga gagal.');
            }

            foreach ($pilihan_bunga_ids as $bunga_id) {
                mysqli_stmt_bind_param($stmtCekBunga, 'i', $bunga_id);
                mysqli_stmt_execute($stmtCekBunga);

                $resultBunga = mysqli_stmt_get_result($stmtCekBunga);
                $dataBunga = mysqli_fetch_assoc($resultBunga);

                if (!$dataBunga || (int)$dataBunga['Stok'] < 1) {
                    throw new RuntimeException(
                        'Salah satu bunga pilihan habis. Silakan kembali dan pilih bunga lain.'
                    );
                }
            }

$addonValid = [];
$totalAddonAktual = 0;
$addonIds = [];

$stmtCekAddon = mysqli_prepare(
    $conn,
    "SELECT addon_id, Nama_addon, Harga, Stok
     FROM add_on
     WHERE addon_id = ?
     FOR UPDATE"
);

$stmtKurangiAddon = mysqli_prepare(
    $conn,
    "UPDATE add_on
     SET Stok = Stok - ?
     WHERE addon_id = ?
       AND Stok >= ?"
);

if (!$stmtCekAddon || !$stmtKurangiAddon) {
    throw new RuntimeException('Query stok Add-On gagal.');
}

foreach ($addons as $addon) {
    $addon_id = (int)($addon['addon_id'] ?? 0);
    $jumlahAddon = (int)($addon['jumlah'] ?? 1);

    if (
        $addon_id <= 0 ||
        $jumlahAddon < 1 ||
        in_array($addon_id, $addonIds, true)
    ) {
        throw new RuntimeException('Data Add-On atau jumlahnya tidak valid.');
    }

    $addonIds[] = $addon_id;

    mysqli_stmt_bind_param($stmtCekAddon, 'i', $addon_id);

    if (!mysqli_stmt_execute($stmtCekAddon)) {
        throw new RuntimeException('Gagal memeriksa Add-On.');
    }

    $resultAddon = mysqli_stmt_get_result($stmtCekAddon);
    $dataAddon = mysqli_fetch_assoc($resultAddon);

    if (!$dataAddon) {
        throw new RuntimeException('Add-On tidak ditemukan.');
    }

    $stokAddon = (int)$dataAddon['Stok'];

    if ($stokAddon < $jumlahAddon) {
        throw new RuntimeException(
            'Stok Add-On "' . $dataAddon['Nama_addon'] .
            '" tidak cukup. Stok tersedia: ' . $stokAddon .
            ', jumlah yang dipilih: ' . $jumlahAddon . '.'
        );
    }

    $hargaAddon = (float)$dataAddon['Harga'];
    $addonValid[] = [
        'addon_id' => (int)$dataAddon['addon_id'],
        'nama' => $dataAddon['Nama_addon'],
        'jumlah' => $jumlahAddon,
        'harga' => $hargaAddon,
        'subtotal' => $hargaAddon * $jumlahAddon
    ];

    // Total dihitung berdasarkan harga satuan dikali jumlah.
    $totalAddonAktual += $hargaAddon * $jumlahAddon;
}

$totalHargaAktual = $hargaProduk + $totalAddonAktual;


            $kode_pesanan = 'ORD-' . date('YmdHis') . random_int(10, 99);
            $status_pesanan = 'menunggu_pembayaran';

            $sqlPesanan = "
                INSERT INTO pesanan (
                    Kode_pesanan,
                    User_id,
                    Produk_id,
                    pilihan_bunga,
                    wrapping,
                    catatan_custom,
                    Wrapping_id,
                    Pickup_id,
                    total_harga,
                    status_pesanan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmtPesanan = mysqli_prepare($conn, $sqlPesanan);

            if (!$stmtPesanan) {
                throw new RuntimeException('Query pesanan gagal disiapkan.');
            }

            mysqli_stmt_bind_param(
                $stmtPesanan,
                'siisssiids',
                $kode_pesanan,
                $user_id,
                $produk_id,
                $pilihan_bunga,
                $wrapping,
                $catatan_custom,
                $wrapping_id,
                $pickup_id,
                $totalHargaAktual,
                $status_pesanan
            );

            if (!mysqli_stmt_execute($stmtPesanan)) {
                throw new RuntimeException('Gagal menyimpan pesanan utama.');
            }

            $pesanan_id = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmtPesanan);

            /*
            |------------------------------------------------------------------
            | INSERT DETAIL_BUNGA
            |------------------------------------------------------------------
            */

            $stmtDetailBunga = mysqli_prepare(
                $conn,
                "INSERT INTO detail_bunga
                    (Pesanan_id, Bunga_id, Jumlah)
                 VALUES (?, ?, ?)"
            );

            if (!$stmtDetailBunga) {
                throw new RuntimeException('Query detail bunga gagal.');
            }

            $jumlahBunga = 1;

            foreach ($pilihan_bunga_ids as $bunga_id) {
                mysqli_stmt_bind_param(
                    $stmtDetailBunga,
                    'iii',
                    $pesanan_id,
                    $bunga_id,
                    $jumlahBunga
                );

                if (!mysqli_stmt_execute($stmtDetailBunga)) {
                    throw new RuntimeException('Gagal menyimpan detail bunga.');
                }

                mysqli_stmt_bind_param(
                    $stmtKurangiBunga,
                    'i',
                    $bunga_id
                );

                if (
                    !mysqli_stmt_execute($stmtKurangiBunga) ||
                    mysqli_stmt_affected_rows($stmtKurangiBunga) !== 1
                ) {
                    throw new RuntimeException('Gagal mengurangi stok bunga.');
                }
            }

            mysqli_stmt_close($stmtDetailBunga);
            mysqli_stmt_close($stmtCekBunga);
            mysqli_stmt_close($stmtKurangiBunga);

            

$stmtDetailAddon = mysqli_prepare(
    $conn,
    "INSERT INTO detail_add_on
        (Pesanan_id, addon_id, Jumlah, Harga)
     VALUES (?, ?, ?, ?)"
);

if (!$stmtDetailAddon) {
    throw new RuntimeException('Query detail Add-On gagal.');
}

foreach ($addonValid as $addon) {
    $addon_id = (int)$addon['addon_id'];
    $jumlahAddon = (int)$addon['jumlah'];
    $hargaAddon = (float)$addon['harga'];


    mysqli_stmt_bind_param(
        $stmtDetailAddon,
        'iiid',
        $pesanan_id,
        $addon_id,
        $jumlahAddon,
        $hargaAddon
    );

    if (!mysqli_stmt_execute($stmtDetailAddon)) {
        throw new RuntimeException('Gagal menyimpan detail Add-On.');
    }

    // Kurangi stok sebanyak jumlah yang dibeli.
    mysqli_stmt_bind_param(
        $stmtKurangiAddon,
        'iii',
        $jumlahAddon,
        $addon_id,
        $jumlahAddon
    );

    if (
        !mysqli_stmt_execute($stmtKurangiAddon) ||
        mysqli_stmt_affected_rows($stmtKurangiAddon) !== 1
    ) {
        throw new RuntimeException(
            'Gagal mengurangi stok Add-On. Silakan coba kembali.'
        );
    }
}

mysqli_stmt_close($stmtDetailAddon);
mysqli_stmt_close($stmtCekAddon);
mysqli_stmt_close($stmtKurangiAddon);


            mysqli_commit($conn);
            $transactionStarted = false;

            $_SESSION['pesanan_terakhir'] = [
                'pesanan_id' => $pesanan_id,
                'kode_pesanan' => $kode_pesanan,
                'tanggal_pickup' => $tanggal_pickup,
                'jam_pickup' => $jam_pickup
            ];

            unset($_SESSION['checkout_pesanan']);

            header(
                'Location: ../pembayaran/index.php?pesanan_id=' . $pesanan_id
            );
            exit;

        } catch (Throwable $ex) {
            if ($transactionStarted) {
                mysqli_rollback($conn);
            }

            error_log('Checkout FlowerShop: ' . $ex->getMessage());

            $error = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'Pesanan gagal dibuat. Silakan coba kembali.';
        }
    }
}


$total_addon = 0;

foreach ($addons as $addon) {
    $hargaSatuan = (float)(
        $addon['harga'] ?? $addon['Harga'] ?? 0
    );

    $jumlah = max(1, (int)($addon['jumlah'] ?? 1));

    $total_addon += $hargaSatuan * $jumlah;
}

$total_harga = (float)$produk['Harga'] + $total_addon;


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Pesanan</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/pickup.css">

    <style>
        .pickup-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .pickup-field {
            position: relative;
        }

        .pickup-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #555;
        }

        .pickup-field input,
        .pickup-field select {
            width: 100%;
            height: 48px;
            padding: 0 42px 0 14px;
            border: 1px solid #eadcde;
            border-radius: 8px;
            background: #fff;
            color: #444;
            font-family: inherit;
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
        }

        .pickup-field input:focus,
        .pickup-field select:focus {
            border-color: #e77d88;
            box-shadow: 0 0 0 3px rgba(231,125,136,.10);
        }

        .pickup-field input:disabled,
        .pickup-field select:disabled {
            background: #f7f5f5;
            color: #aaa;
            cursor: not-allowed;
        }

        .field-icon {
            position: absolute;
            right: 14px;
            bottom: 16px;
            color: #e77d88;
            pointer-events: none;
        }

        .pickup-help {
            margin-top: 8px;
            font-size: 11px;
            color: #999;
            line-height: 1.5;
        }

        .pickup-empty {
            display: none;
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 7px;
            background: #fff4f5;
            color: #d86673;
            font-size: 11px;
        }

        .pickup-empty.show {
            display: block;
        }

        .pickup-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 13px 16px;
            border: 1px solid #f1c6cc;
            border-radius: 9px;
            background: #fff4f5;
            color: #b82b6f;
            font-size: 13px;
        }

        .addon-summary {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #fff8f8;
            border: 1px solid #f2e0e2;
        }

        .addon-summary-title {
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: 700;
            color: #555;
        }

        .addon-summary-item,
        .addon-summary-total {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 5px 0;
            font-size: 11px;
            color: #777;
        }

        .addon-summary-total {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #eadcde;
            font-weight: 700;
            color: #d86673;
        }

        .product-mini-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .custom-note {
            margin-top: 14px;
            padding: 12px;
            background: #fff8f8;
            border-radius: 8px;
            font-size: 12px;
        }

        .custom-note p {
            margin-bottom: 0;
            white-space: normal;
        }

        @media (max-width: 700px) {
            .pickup-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<aside class="dashboard-sidebar" id="sidebar">
    
<a
    href="../dashboard.php"
    class="brand"
    style="
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        box-sizing: border-box;
        text-decoration: none;
        padding: 15px 10px;
        margin: 0;
    "
>
    <img
        src="/bunga/assets/img/logo%20flonuel.png?v=2"
        alt="Logo FlowerShop"
        style="
            display: block;
            width: 100%;
            max-width: 180px;
            height: 110px;
            object-fit: contain;
            object-position: center;
            flex-shrink: 0;
            margin: 0 auto;
        "
    >
</a>


    <nav class="sidebar-menu">
        <div class="sidebar-label">MENU</div>

        <a href="../dashboard.php">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="../pesanan/index.php" class="active">
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
            <button type="button" class="mobile-menu" id="mobileMenu"
                    aria-label="Buka menu">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">Pickup Pesanan</div>
        </div>

        <div class="profile-mini">
            <div class="profile-info">
                <div class="profile-name"><?= e($user['Nama']) ?></div>
                <div class="profile-role">Customer</div>
            </div>

            <div class="profile-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
        </div>
    </header>

    <section class="content">

        <?php if ($error !== ''): ?>
            <div class="pickup-alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="order-header">
            <div class="order-title">
                <i class="fa-solid fa-bag-shopping"></i>
                PESANAN
            </div>

            <div class="order-code">
                Kode Pesanan:
                <strong>Akan dibuat setelah konfirmasi</strong>
            </div>
        </div>

        <form method="POST" action="" id="pickupForm">
            <div class="pickup-section">
                <h2>Pengambilan pesanan</h2>

                <p>
                    Silakan pilih tanggal dan jam pickup sesuai jadwal
                    yang tersedia dari admin. Pesanan diambil di toko minimal 2 hari dari sekarang.
                </p>

                
<div class="pickup-fields">
    <!-- TANGGAL PICKUP -->
    <div class="pickup-field">
        <label for="tanggal_pickup">Tanggal Pickup</label>

        <input
            type="date"
            name="tanggal_pickup"
            id="tanggal_pickup"
            min="<?= e(date('Y-m-d', strtotime('+2 days'))) ?>"
            value="<?= e($selected_date) ?>"
            required
        >

        <div class="pickup-help">
            Pilih tanggal sesuai jadwal yang tersedia.
        </div>
    </div>

    <!-- JAM PICKUP -->
    <div class="pickup-field">
        <label for="jam_pickup">Jam Pickup</label>

        <select
            name="jam_pickup"
            id="jam_pickup"
            required
        >
            <option value="">Pilih jam pickup</option>
        </select>

        <!-- <i class="fa-regular fa-clock field-icon"></i> -->

        <div class="pickup-help">
            Jam pickup mengikuti jadwal admin.
        </div>
    </div>

    <!-- PESAN JIKA JADWAL TIDAK TERSEDIA -->
    <div class="pickup-empty" id="pickupEmpty">
        <i class="fa-solid fa-circle-info"></i>
        Tidak ada jadwal pickup pada tanggal tersebut.
        Silakan pilih tanggal lainnya.
    </div>
</div>

<!-- ID PICKUP HARUS BERADA DI LUAR GRID -->
<input
    type="hidden"
    name="pickup_id"
    id="pickup_id"
    value=""
>

            <div class="checkout-grid">
                <div class="payment-card">
                    <h2>Jumlah pembayaran</h2>

                    <div class="payment-row">
                        <span>Produk</span>
                        <strong><?= rupiah($produk['Harga']) ?></strong>
                    </div>

                    <div class="payment-row">
                        <span>Add-On</span>
                        <strong><?= rupiah($total_addon) ?></strong>
                    </div>

                    <div class="payment-line"></div>

                    <div class="payment-total">
                        <span>Total</span>
                        <strong><?= rupiah($total_harga) ?></strong>
                    </div>

                    <div class="product-mini">
                        <div class="product-mini-image">
                            <?php if ($foto_path !== ''): ?>
                                <img src="<?= e($foto_path) ?>"
                                     alt="<?= e($produk['Nama_produk']) ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-image"></i>
                            <?php endif; ?>
                        </div>

                        <div class="product-mini-info">
                            <strong><?= e($produk['Nama_produk']) ?></strong>
                            <span><?= e($pilihan_bunga) ?></span>
                            <span><?= e($wrapping) ?></span>
                        </div>
                    </div>

                    <?php if (count($addons) > 0): ?>
                        <div class="addon-summary">
                            <div class="addon-summary-title">Add-On Pilihan</div>

                            
<?php foreach ($addons as $addon): ?>
    <?php
    $addonNama = $addon['nama']
        ?? $addon['Nama_addon']
        ?? $addon['Nama']
        ?? 'Add-On';

    $addonHarga = (float)(
        $addon['harga'] ?? $addon['Harga'] ?? 0
    );

    $addonJumlah = max(1, (int)($addon['jumlah'] ?? 1));
    $addonSubtotal = $addonHarga * $addonJumlah;
    ?>

    <div class="addon-summary-item">
        <span>
            <?= e($addonNama) ?>
            (<?= $addonJumlah ?> × <?= rupiah($addonHarga) ?>)
        </span>
        <strong><?= rupiah($addonSubtotal) ?></strong>
    </div>
<?php endforeach; ?>


                            <div class="addon-summary-total">
                                <span>Total Add-On</span>
                                <strong><?= rupiah($total_addon) ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($catatan_custom !== ''): ?>
                        <div class="custom-note">
                            <strong>Catatan Custom</strong>
                            <p><?= nl2br(e($catatan_custom)) ?></p>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="order-button" id="orderButton">
                        <span>BUAT PESANAN</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

                <div class="account-card">
                    <h2>Rekening pembayaran</h2>

                    <div class="account-item">
                        <span>Bank</span>
                        <strong>MANDIRI</strong>
                    </div>

                    <div class="account-item">
                        <span>Nomor Rekening</span>
                        <strong>123456789</strong>
                    </div>

                    <div class="account-item">
                        <span>Atas nama</span>
                        <strong>Flonuel</strong>
                    </div>

                    <div class="account-info">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>
                            Setelah pesanan berhasil dibuat, Anda akan
                            diarahkan ke halaman pembayaran.
                        </span>
                    </div>
                </div>
            </div>
        </form>
    </section>
</main>

<script>
const jadwalPickup = <?= json_encode(
    $jadwal_json,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;

const tanggalInput = document.getElementById('tanggal_pickup');
const jamSelect = document.getElementById('jam_pickup');
const pickupIdInput = document.getElementById('pickup_id');
const pickupEmpty = document.getElementById('pickupEmpty');

const selectedTime = <?= json_encode(
    $selected_time,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;

// Pastikan dropdown jam tidak terkunci
jamSelect.disabled = false;

// 1. Ambil semua jam unik dari data jadwal database agar bisa langsung dipilih
const uniqueTimes = [...new Set(jadwalPickup.map(item => item.jam))].sort();

// 2. Kosongkan lalu isi dropdown jam saat halaman pertama kali dimuat
jamSelect.innerHTML = '';
jamSelect.add(new Option('Pilih jam pickup', ''));

uniqueTimes.forEach(jam => {
    const option = new Option(jam + ' WIB', jam);
    jamSelect.add(option);
});

// Pulihkan pilihan jam jika halaman direfresh karena error submit sebelumnya
if (selectedTime) {
    jamSelect.value = selectedTime;
}

// 3. Fungsi untuk memvalidasi ketersediaan kombinasi Tanggal dan Jam
function validateSchedule() {
    const tanggal = tanggalInput.value;
    const jam = jamSelect.value;

    // Reset ID pickup dan pesan error setiap kali ada perubahan
    pickupIdInput.value = '';
    pickupEmpty.classList.remove('show');

    // Jika KEDUANYA (Tanggal & Jam) sudah dipilih, baru cek ke database
    if (tanggal && jam) {
        const jadwal = jadwalPickup.find(
            item => item.tanggal === tanggal && item.jam === jam
        );

        if (jadwal) {
            // Jadwal tersedia, simpan ID untuk disubmit
            pickupIdInput.value = jadwal.id; 
        } else {
            // Jadwal tidak cocok/kosong pada kombinasi ini
            pickupEmpty.innerHTML = '<i class="fa-solid fa-circle-info"></i> Jadwal tidak tersedia untuk kombinasi tanggal dan jam ini. Silakan pilih waktu lain.';
            pickupEmpty.classList.add('show');
        }
    }
}

// Pantau setiap perubahan yang dilakukan pembeli pada form tanggal DAN form jam
tanggalInput.addEventListener('change', validateSchedule);
jamSelect.addEventListener('change', validateSchedule);

// Validasi saat tombol "Buat Pesanan" diklik
document.getElementById('pickupForm').addEventListener('submit', event => {
    if (!tanggalInput.value || !jamSelect.value || !pickupIdInput.value) {
        event.preventDefault(); // Cegah form terkirim
        alert('Jadwal tidak tersedia. Silakan ubah kombinasi tanggal dan jam pickup Anda.');
    }
});

/* Sidebar mobile */
const mobileMenu = document.getElementById('mobileMenu');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');

if (mobileMenu && sidebar && overlay) {
    mobileMenu.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
}

// Panggil validasi awal jika form sebelumnya sudah terisi (saat load halaman)
if (tanggalInput.value || jamSelect.value) {
    validateSchedule();
}
</script>

</body>
</html>