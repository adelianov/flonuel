
<?php
session_start();

require_once '../../config/koneksi.php';

$nomor_wa_admin = '62881023341927';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header('Location: ../../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$pesanan_id = isset($_GET['pesanan_id'])
    ? (int) $_GET['pesanan_id']
    : 0;

$error = '';
$success = '';

if ($pesanan_id <= 0) {
    header('Location: ../pesanan/index.php');
    exit;
}

function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

$sqlUser = "
    SELECT User_id, Nama, No_Hp, Email
    FROM user
    WHERE User_id = ?
    LIMIT 1
";

$stmtUser = mysqli_prepare($conn, $sqlUser);

if (!$stmtUser) {
    die('Query user gagal: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmtUser, "i", $user_id);
mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);

mysqli_stmt_close($stmtUser);

if (!$user) {
    session_destroy();
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $jumlah_pembayaran = isset($_POST['jumlah_pembayaran'])
        ? (float) $_POST['jumlah_pembayaran']
        : 0;


    $sqlOrderCheck = "
        SELECT
            p.Pesanan_id,
            p.Kode_pesanan,
            p.total_harga,
            p.status_pesanan
        FROM pesanan p
        WHERE p.Pesanan_id = ?
          AND p.User_id = ?
        LIMIT 1
    ";

    $stmtOrderCheck = mysqli_prepare($conn, $sqlOrderCheck);

    if (!$stmtOrderCheck) {

        $error = 'Query pesanan gagal: ' . mysqli_error($conn);

    } else {

        mysqli_stmt_bind_param(
            $stmtOrderCheck,
            "ii",
            $pesanan_id,
            $user_id
        );

        mysqli_stmt_execute($stmtOrderCheck);

        $resultOrderCheck = mysqli_stmt_get_result($stmtOrderCheck);
        $orderCheck = mysqli_fetch_assoc($resultOrderCheck);

        mysqli_stmt_close($stmtOrderCheck);

        if (!$orderCheck) {

            $error = 'Pesanan tidak ditemukan atau bukan milik Anda.';

        } else {

            $total_order = (float) $orderCheck['total_harga'];

            if ($jumlah_pembayaran <= 0) {

                $error = 'Jumlah pembayaran tidak valid.';

            } elseif ($jumlah_pembayaran < $total_order) {

                $error = 'Jumlah pembayaran kurang dari total pesanan.';

            } elseif (
                !isset($_FILES['bukti_pembayaran']) ||
                $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK
            ) {

                $error = 'Silakan upload bukti pembayaran.';

            } else {

                $file = $_FILES['bukti_pembayaran'];

                $nama_file = $file['name'];
                $tmp_file = $file['tmp_name'];
                $ukuran_file = $file['size'];

                $ext = strtolower(
                    pathinfo($nama_file, PATHINFO_EXTENSION)
                );

                $allowed_ext = [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp'
                ];

                if (!in_array($ext, $allowed_ext, true)) {

                    $error =
                        'Format bukti pembayaran harus JPG, JPEG, PNG, atau WEBP.';

                } elseif ($ukuran_file > 5 * 1024 * 1024) {

                    $error = 'Ukuran bukti pembayaran maksimal 5 MB.';

                } else {

    
                    $upload_dir = '../../uploads/pembayaran/';

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                
                    $nama_baru =
                        'bukti_' .
                        $pesanan_id .
                        '_' .
                        time() .
                        '_' .
                        bin2hex(random_bytes(4)) .
                        '.' .
                        $ext;

                    $target_file = $upload_dir . $nama_baru;

                    if (!move_uploaded_file($tmp_file, $target_file)) {

                        $error = 'Bukti pembayaran gagal diupload.';

                    } else {

                
                        $sqlPaymentCheck = "
                            SELECT
                                Pembayaran_id,
                                Bukti_pembayaran
                            FROM pembayaran
                            WHERE Pesanan_id = ?
                            LIMIT 1
                        ";

                        $stmtPaymentCheck = mysqli_prepare(
                            $conn,
                            $sqlPaymentCheck
                        );

                        $paymentExisting = null;

                        if ($stmtPaymentCheck) {

                            mysqli_stmt_bind_param(
                                $stmtPaymentCheck,
                                "i",
                                $pesanan_id
                            );

                            mysqli_stmt_execute($stmtPaymentCheck);

                            $resultPaymentCheck = mysqli_stmt_get_result(
                                $stmtPaymentCheck
                            );

                            $paymentExisting = mysqli_fetch_assoc(
                                $resultPaymentCheck
                            );

                            mysqli_stmt_close($stmtPaymentCheck);
                        }

                        
                        if ($paymentExisting) {

                            /*
                            | Hapus file bukti sebelumnya jika ada.
                            */
                            if (!empty($paymentExisting['Bukti_pembayaran'])) {

                                $old_file =
                                    $upload_dir .
                                    basename(
                                        $paymentExisting['Bukti_pembayaran']
                                    );

                                if (file_exists($old_file)) {
                                    @unlink($old_file);
                                }
                            }

                            $sqlUpdatePayment = "
                                UPDATE pembayaran
                                SET
                                    Tanggal_pembayaran = NOW(),
                                    Jumlah_pembayaran = ?,
                                    Bukti_pembayaran = ?,
                                    Status_pembayaran = 'menunggu',
                                    Tanggal_Verifikasi = NULL
                                WHERE Pesanan_id = ?
                            ";

                            $stmtUpdatePayment = mysqli_prepare(
                                $conn,
                                $sqlUpdatePayment
                            );

                            if ($stmtUpdatePayment) {

                                mysqli_stmt_bind_param(
                                    $stmtUpdatePayment,
                                    "dsi",
                                    $jumlah_pembayaran,
                                    $nama_baru,
                                    $pesanan_id
                                );

                                $paymentSaved = mysqli_stmt_execute(
                                    $stmtUpdatePayment
                                );

                                mysqli_stmt_close($stmtUpdatePayment);

                            } else {

                                $paymentSaved = false;
                            }

                        } else {

                            $sqlInsertPayment = "
                                INSERT INTO pembayaran
                                (
                                    Pesanan_id,
                                    Tanggal_pembayaran,
                                    Jumlah_pembayaran,
                                    Bukti_pembayaran,
                                    Status_pembayaran,
                                    Tanggal_Verifikasi
                                )
                                VALUES
                                (
                                    ?,
                                    NOW(),
                                    ?,
                                    ?,
                                    'menunggu',
                                    NULL
                                )
                            ";

                            $stmtInsertPayment = mysqli_prepare(
                                $conn,
                                $sqlInsertPayment
                            );

                            if ($stmtInsertPayment) {

                                mysqli_stmt_bind_param(
                                    $stmtInsertPayment,
                                    "ids",
                                    $pesanan_id,
                                    $jumlah_pembayaran,
                                    $nama_baru
                                );

                                $paymentSaved = mysqli_stmt_execute(
                                    $stmtInsertPayment
                                );

                                mysqli_stmt_close($stmtInsertPayment);

                            } else {

                                $paymentSaved = false;
                            }
                        }

                        if ($paymentSaved) {

                            $sqlUpdateOrder = "
                                UPDATE pesanan
                                SET status_pesanan = 'menunggu_verifikasi'
                                WHERE Pesanan_id = ?
                                  AND User_id = ?
                                  AND status_pesanan = 'menunggu_pembayaran'
                            ";

                            $stmtUpdateOrder = mysqli_prepare(
                                $conn,
                                $sqlUpdateOrder
                            );

                            if ($stmtUpdateOrder) {

                                mysqli_stmt_bind_param(
                                    $stmtUpdateOrder,
                                    "ii",
                                    $pesanan_id,
                                    $user_id
                                );

                                mysqli_stmt_execute($stmtUpdateOrder);
                                mysqli_stmt_close($stmtUpdateOrder);
                            }

                            header(
                                'Location: index.php?pesanan_id=' .
                                $pesanan_id .
                                '&success=1'
                            );

                            exit;

                        } else {

                            if (file_exists($target_file)) {
                                @unlink($target_file);
                            }

                            $error =
                                'Data pembayaran gagal disimpan: ' .
                                mysqli_error($conn);
                        }
                    }
                }
            }
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success =
        'Bukti pembayaran berhasil dikirim dan sedang menunggu verifikasi admin.';
}

$sqlOrder = "
    SELECT
        p.Pesanan_id,
        p.Kode_pesanan,
        p.Tanggal_pesanan,
        p.total_harga,
        p.status_pesanan,
        p.pilihan_bunga,
        p.wrapping,
        p.catatan_custom,
        pr.Nama_produk,
        pr.Foto_produk
    FROM pesanan p
    LEFT JOIN produk pr
        ON p.Produk_id = pr.Produk_id
    WHERE p.Pesanan_id = ?
      AND p.User_id = ?
    LIMIT 1
";

$stmtOrder = mysqli_prepare($conn, $sqlOrder);

if (!$stmtOrder) {
    die('Query detail pesanan gagal: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmtOrder,
    "ii",
    $pesanan_id,
    $user_id
);

mysqli_stmt_execute($stmtOrder);

$resultOrder = mysqli_stmt_get_result($stmtOrder);
$order = mysqli_fetch_assoc($resultOrder);

mysqli_stmt_close($stmtOrder);

if (!$order) {
    header('Location: ../pesanan/index.php');
    exit;
}


$sqlAddon = "
    SELECT
        da.addon_id,
        da.Jumlah,
        da.Harga,
        a.Nama_addon
    FROM detail_add_on da
    LEFT JOIN add_on a
        ON da.addon_id = a.addon_id
    WHERE da.Pesanan_id = ?
    ORDER BY da.addon_id ASC
";

$stmtAddon = mysqli_prepare($conn, $sqlAddon);
$detail_addons = [];

if (!$stmtAddon) {
    die('Query detail Add-On gagal: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmtAddon,
    "i",
    $pesanan_id
);

mysqli_stmt_execute($stmtAddon);

$resultAddon = mysqli_stmt_get_result($stmtAddon);

while ($rowAddon = mysqli_fetch_assoc($resultAddon)) {
    $jumlahAddon = max(1, (int)$rowAddon['Jumlah']);
    $hargaSatuan = (float)$rowAddon['Harga'];

    $detail_addons[] = [
        'nama' => $rowAddon['Nama_addon'] ?? 'Add-On',
        'jumlah' => $jumlahAddon,
        'harga' => $hargaSatuan,
        'subtotal' => $hargaSatuan * $jumlahAddon
    ];
}

mysqli_stmt_close($stmtAddon);

$sqlPayment = "
    SELECT
        Pembayaran_id,
        Tanggal_pembayaran,
        Jumlah_pembayaran,
        Bukti_pembayaran,
        Status_pembayaran,
        Tanggal_Verifikasi
    FROM pembayaran
    WHERE Pesanan_id = ?
    LIMIT 1
";

$stmtPayment = mysqli_prepare($conn, $sqlPayment);

$payment = null;

if ($stmtPayment) {

    mysqli_stmt_bind_param(
        $stmtPayment,
        "i",
        $pesanan_id
    );

    mysqli_stmt_execute($stmtPayment);

    $resultPayment = mysqli_stmt_get_result($stmtPayment);
    $payment = mysqli_fetch_assoc($resultPayment);

    mysqli_stmt_close($stmtPayment);
}


$link_whatsapp_admin = '';

if (
    isset($_GET['success']) &&
    $_GET['success'] == '1' &&
    $payment &&
    !empty($payment['Bukti_pembayaran'])
) {

    $pesan_whatsapp =
        "Halo Admin, saya baru saja mengunggah bukti pembayaran.\n\n" .
        "Kode Pesanan: " . ($order['Kode_pesanan'] ?? '-') . "\n" .
        "Nama Customer: " . ($user['Nama'] ?? '-') . "\n" .
        "Nominal Pembayaran: " .
        rupiah($payment['Jumlah_pembayaran'] ?? 0) . "\n" .
        "Status Pembayaran: Menunggu Verifikasi\n\n" .
        "Mohon periksa bukti pembayaran pada dashboard admin.";

    $nomor_bersih = preg_replace(
        '/[^0-9]/',
        '',
        $nomor_wa_admin
    );

    $link_whatsapp_admin =
        'https://wa.me/' .
        $nomor_bersih .
        '?text=' .
        urlencode($pesan_whatsapp);
}


$foto_produk = trim($order['Foto_produk'] ?? '');
$foto_path = '';

if ($foto_produk !== '') {

    $nama_foto_aman = basename($foto_produk);

    $path_upload = '../../uploads/produk/' . $nama_foto_aman;
    $path_asset = '../../assets/img/produk/' . $nama_foto_aman;

    if (file_exists($path_upload)) {

        $foto_path = $path_upload;

    } elseif (file_exists($path_asset)) {

        $foto_path = $path_asset;
    }
}

$status = $order['status_pesanan'];

$status_labels = [
    'menunggu_pembayaran' => 'Menunggu Pembayaran',
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'diproses' => 'Diproses',
    'dibuat' => 'Sedang Dibuat',
    'siap_diambil' => 'Siap Diambil',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan'
];

$status_classes = [
    'menunggu_pembayaran' => 'warning',
    'menunggu_verifikasi' => 'info',
    'diproses' => 'primary',
    'dibuat' => 'secondary',
    'siap_diambil' => 'primary',
    'selesai' => 'success',
    'dibatalkan' => 'danger'
];

$status_label = $status_labels[$status] ?? ucfirst($status);
$status_class = $status_classes[$status] ?? 'secondary';

$boleh_bayar =
    $status === 'menunggu_pembayaran' ||
    (
        $status === 'menunggu_verifikasi' &&
        $payment &&
        $payment['Status_pembayaran'] === 'ditolak'
    );
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Pembayaran</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/pembayaran.css"
    >

    <style>
        .whatsapp-notification-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
            padding: 10px 15px;
            border-radius: 10px;
            background: #25D366;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            transition: background .2s ease, transform .2s ease;
        }

        .whatsapp-notification-button:hover {
            background: #1da851;
            color: #fff;
            transform: translateY(-1px);
        }

        .payment-alert.success {
            flex-wrap: wrap;
        }

        .whatsapp-notification-button i {
            font-size: 18px;
        }

        @media (max-width: 600px) {
            .whatsapp-notification-button {
                width: 100%;
                box-sizing: border-box;
            }
        }
        
.payment-addon-list {
    width: 100%;
    margin-top: 10px;
}

.payment-addon-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f0e5e6;
}

.payment-addon-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.payment-addon-info strong {
    color: #444;
    font-size: 13px;
}

.payment-addon-info small {
    color: #888;
    font-size: 11px;
}

.payment-addon-subtotal {
    color: #d86673;
    font-size: 12px;
    white-space: nowrap;
}

.payment-addon-total {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding-top: 12px;
    font-size: 12px;
    font-weight: 700;
}

.payment-addon-total strong {
    color: #d86673;
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

    <div class="sidebar-label">
        MENU
    </div>

    <a href="../dashboard.php">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="../pesanan/index.php">
        <i class="fa-solid fa-bag-shopping"></i>
        <span>Pesanan Saya</span>
    </a>

    <a href="../katalog.php">
        <i class="fa-solid fa-book-open"></i>
        <span>Katalog</span>
    </a>

    <a
        href="index.php?pesanan_id=<?= $pesanan_id; ?>"
        class="active"
    >
        <i class="fa-solid fa-credit-card"></i>
        <span>Pembayaran</span>
    </a>

    <a
        href="../../logout.php"
        onclick="return confirm('Apakah Anda yakin ingin logout?')"
    >
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>

</nav>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">

    <header class="topbar">

        <div class="topbar-left">

            <button
                type="button"
                class="mobile-menu"
                id="mobileMenu"
                aria-label="Buka menu"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">
                Pembayaran
            </div>

        </div>

        <div class="profile-mini">

            <div class="profile-info">

                <div class="profile-name">
                    <?= htmlspecialchars($user['Nama']); ?>
                </div>

                <div class="profile-role">
                    Customer
                </div>

            </div>

            <div class="profile-avatar">
                <i class="fa-solid fa-user"></i>
            </div>

        </div>

    </header>

    <section class="content">

        <?php if ($success !== ''): ?>

            <div class="payment-alert success">

                <i class="fa-solid fa-circle-check"></i>

                <span>
                    <?= htmlspecialchars($success); ?>
                </span>

                <?php if ($link_whatsapp_admin !== ''): ?>

                    <a
                        href="<?= htmlspecialchars(
                            $link_whatsapp_admin,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="whatsapp-notification-button"
                    >
                        <i class="fa-brands fa-whatsapp"></i>
                        Beritahu Admin via WhatsApp
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <?php if ($error !== ''): ?>

            <div class="payment-alert error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <span>
                    <?= htmlspecialchars($error); ?>
                </span>

            </div>

        <?php endif; ?>

        <div class="payment-wrapper">

            <div class="order-card">

                <div class="card-title">

                    <div>
                        Detail Pesanan
                    </div>

                    <span class="status-badge <?= htmlspecialchars($status_class); ?>">
                        <?= htmlspecialchars($status_label); ?>
                    </span>

                </div>

                <div class="order-product">

                    <div class="product-image">

                        <?php if ($foto_path !== ''): ?>

                            <img
                                src="<?= htmlspecialchars($foto_path); ?>"
                                alt="<?= htmlspecialchars($order['Nama_produk'] ?? 'Produk'); ?>"
                            >

                        <?php else: ?>

                            <div class="no-image">
                                <i class="fa-solid fa-image"></i>
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="product-info">

                        <div class="product-name">
                            <?= htmlspecialchars($order['Nama_produk'] ?? 'Produk'); ?>
                        </div>

                        <div class="order-code">
                            <?= htmlspecialchars($order['Kode_pesanan']); ?>
                        </div>

                    </div>

                </div>

                <div class="order-detail-list">

                    <div class="detail-row">

                        <span>
                            Tanggal Pesanan
                        </span>

                        <strong>
                            <?= date(
                                'd M Y H:i',
                                strtotime($order['Tanggal_pesanan'])
                            ); ?>
                        </strong>

                    </div>

                    <div class="detail-row">

                        <span>
                            Pilihan Bunga
                        </span>

                        <strong>
                            <?= htmlspecialchars($order['pilihan_bunga'] ?: '-'); ?>
                        </strong>

                    </div>

                    <div class="detail-row">

                        <span>
                            Wrapping
                        </span>

                        <strong>
                            <?= htmlspecialchars($order['wrapping'] ?: '-'); ?>
                        </strong>

                    </div>
                    
<!-- DETAIL ADD-ON -->
<div class="detail-row detail-column">
    <span>Add-On</span>

    <?php if (count($detail_addons) > 0): ?>

        <div class="payment-addon-list">

            <?php foreach ($detail_addons as $addon): ?>

                <div class="payment-addon-item">

                    <div class="payment-addon-info">
                        <strong>
                            <?= htmlspecialchars($addon['nama']); ?>
                        </strong>

                        <small>
                            <?= $addon['jumlah']; ?> ×
                            <?= rupiah($addon['harga']); ?>
                        </small>
                    </div>

                    <strong class="payment-addon-subtotal">
                        <?= rupiah($addon['subtotal']); ?>
                    </strong>

                </div>

            <?php endforeach; ?>

            <div class="payment-addon-total">
                <span>Total Add-On</span>

                <strong>
                    <?php
                    $totalAddonTampil = 0;

                    foreach ($detail_addons as $addon) {
                        $totalAddonTampil += $addon['subtotal'];
                    }

                    echo rupiah($totalAddonTampil);
                    ?>
                </strong>
            </div>

        </div>

    <?php else: ?>

        <strong>Tidak ada Add-On</strong>

    <?php endif; ?>
</div>


                    <?php if (!empty($order['catatan_custom'])): ?>

                        <div class="detail-row detail-column">

                            <span>
                                Catatan
                            </span>

                            <strong>
                                <?= nl2br(
                                    htmlspecialchars($order['catatan_custom'])
                                ); ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                    <div class="total-row">

                        <span>
                            Total Pembayaran
                        </span>

                        <strong>
                            <?= rupiah($order['total_harga']); ?>
                        </strong>

                    </div>

                </div>

            </div>

            <div class="payment-card">

                <div class="card-title">
                    Pembayaran
                </div>

                <?php if (
                    $payment &&
                    $payment['Status_pembayaran'] === 'menunggu'
                ): ?>

                    <div class="waiting-payment">

                        <div class="waiting-icon">
                            <i class="fa-solid fa-clock"></i>
                        </div>

                        <h3>
                            Menunggu Verifikasi
                        </h3>

                        <p>
                            Bukti pembayaran sudah dikirim.
                            Silakan tunggu admin melakukan verifikasi.
                        </p>

                        <div class="payment-amount">
                            <?= rupiah($payment['Jumlah_pembayaran']); ?>
                        </div>

                    </div>

                <?php elseif (
                    $payment &&
                    $payment['Status_pembayaran'] === 'diverifikasi'
                ): ?>

                    <div class="waiting-payment verified">

                        <div class="waiting-icon">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>

                        <h3>
                            Pembayaran Terverifikasi
                        </h3>

                        <p>
                            Pembayaran untuk pesanan ini sudah
                            diverifikasi oleh admin.
                        </p>

                    </div>

                <?php elseif (
                    $payment &&
                    $payment['Status_pembayaran'] === 'ditolak'
                ): ?>

                    <div class="payment-alert error">

                        <i class="fa-solid fa-circle-xmark"></i>

                        <span>
                            Bukti pembayaran sebelumnya ditolak.
                            Silakan upload bukti pembayaran kembali.
                        </span>

                    </div>

                <?php endif; ?>

                <?php if ($boleh_bayar): ?>

                    <form
                        action="index.php?pesanan_id=<?= $pesanan_id; ?>"
                        method="POST"
                        enctype="multipart/form-data"
                        class="payment-form"
                    >

                        <div class="form-group">

                            <label for="jumlah_pembayaran">
                                Jumlah Pembayaran
                            </label>

                            <div class="input-money">

                                <span>
                                    Rp
                                </span>

                                <input
                                    type="number"
                                    name="jumlah_pembayaran"
                                    id="jumlah_pembayaran"
                                    min="<?= htmlspecialchars((string) $order['total_harga']); ?>"
                                    step="1000"
                                    value="<?= htmlspecialchars((string) $order['total_harga']); ?>"
                                    required
                                >

                            </div>

                            <small>
                                Minimal pembayaran:
                                <?= rupiah($order['total_harga']); ?>
                            </small>

                        </div>

                        <div class="form-group">

                            <label for="bukti_pembayaran">
                                Bukti Pembayaran
                            </label>

                            <input
                                type="file"
                                name="bukti_pembayaran"
                                id="bukti_pembayaran"
                                accept=".jpg,.jpeg,.png,.webp"
                                required
                            >

                            <small>
                                JPG, JPEG, PNG atau WEBP.
                                Maksimal 5 MB.
                            </small>

                        </div>

                        <div class="upload-preview" id="uploadPreview">

                            <img
                                id="previewImage"
                                src=""
                                alt="Preview bukti pembayaran"
                            >

                        </div>

                        <button
                            type="submit"
                            class="payment-button"
                        >
                            <i class="fa-solid fa-paper-plane"></i>
                            Kirim Bukti Pembayaran
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>

<script>

const mobileMenu = document.getElementById('mobileMenu');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');

if (mobileMenu) {
    mobileMenu.addEventListener('click', function () {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    });
}

if (overlay) {
    overlay.addEventListener('click', function () {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
}


const fileInput = document.getElementById('bukti_pembayaran');
const previewBox = document.getElementById('uploadPreview');
const previewImage = document.getElementById('previewImage');

if (fileInput) {
    fileInput.addEventListener('change', function () {
        const file = this.files[0];

        if (!file) {
            previewBox.classList.remove('show');
            return;
        }

        const maxSize = 5 * 1024 * 1024;

        if (file.size > maxSize) {
            alert('Ukuran file maksimal 5 MB.');
            this.value = '';
            previewBox.classList.remove('show');
            return;
        }

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!allowedTypes.includes(file.type)) {
            alert('Format file harus JPG, JPEG, PNG, atau WEBP.');
            this.value = '';
            previewBox.classList.remove('show');
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            previewImage.src = event.target.result;
            previewBox.classList.add('show');
        };

        reader.readAsDataURL(file);
    });
}
</script>

</body>
</html>