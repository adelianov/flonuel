
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

$status_valid = [
    'menunggu_pembayaran' => 'Menunggu Pembayaran',
    'menunggu_verifikasi' => 'Menunggu Verifikasi',
    'diproses'             => 'Diproses',
    'dibuat'               => 'Proses Pembuatan',
    'siap_diambil'         => 'Siap Diambil',
    'selesai'              => 'Selesai',
    'dibatalkan'           => 'Dibatalkan'
];


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ubah_status'])
) {
    $token_form = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token_form) ||
        !hash_equals($_SESSION['csrf_token'], $token_form)
    ) {
        $_SESSION['pesan_error'] = 'Permintaan tidak valid. Silakan coba kembali.';
        header('Location: index.php');
        exit;
    }

    $pesanan_id = filter_input(
        INPUT_POST,
        'pesanan_id',
        FILTER_VALIDATE_INT
    );

    $status_baru = $_POST['status_pesanan'] ?? '';

    if (
        !$pesanan_id ||
        !is_string($status_baru) ||
        !array_key_exists($status_baru, $status_valid)
    ) {
        $_SESSION['pesan_error'] = 'Data status pesanan tidak valid.';
        header('Location: index.php');
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE pesanan
         SET status_pesanan = ?
         WHERE Pesanan_id = ?"
    );

    if (!$stmt) {
        $_SESSION['pesan_error'] = 'Gagal menyiapkan perubahan status.';
    } else {
        mysqli_stmt_bind_param(
            $stmt,
            'si',
            $status_baru,
            $pesanan_id
        );

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) >= 0) {
                $_SESSION['pesan_sukses'] =
                    'Status pesanan berhasil diperbarui menjadi ' .
                    $status_valid[$status_baru] . '.';
            } else {
                $_SESSION['pesan_error'] =
                    'Status pesanan gagal diperbarui.';
            }
        } else {
            $_SESSION['pesan_error'] =
                'Status pesanan gagal diperbarui.';
        }

        mysqli_stmt_close($stmt);
    }

    header('Location: index.php');
    exit;
}

$total_pesanan = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM pesanan"
);

if ($result) {
    $data = mysqli_fetch_assoc($result);
    $total_pesanan = (int) ($data['total'] ?? 0);
}

$pesanan_selesai = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM pesanan
     WHERE status_pesanan = 'selesai'"
);

if ($result) {
    $data = mysqli_fetch_assoc($result);
    $pesanan_selesai = (int) ($data['total'] ?? 0);
}

$sql = "
    SELECT
        p.Pesanan_id,
        p.Kode_pesanan,
        p.Tanggal_pesanan,
        p.total_harga,
        p.status_pesanan,
        u.Nama AS nama_pelanggan,
        u.No_hp AS no_hp_pelanggan,
        pr.Nama_produk
    FROM pesanan p
    LEFT JOIN user u
        ON p.User_id = u.User_id
    LEFT JOIN produk pr
        ON p.Produk_id = pr.Produk_id
    ORDER BY p.Pesanan_id DESC
";

$result_pesanan = mysqli_query($conn, $sql);

if (!$result_pesanan) {
    die(
        'Gagal mengambil data pesanan: ' .
        htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8')
    );
}

function rupiah($angka)
{
    return number_format((float) $angka, 0, ',', '.');
}

function statusPesanan($status)
{
    $status_list = [
        'menunggu_pembayaran' => [
            'Menunggu Pembayaran', 'waiting'
        ],
        'menunggu_verifikasi' => [
            'Menunggu Verifikasi', 'verification'
        ],
        'diproses' => [
            'Diproses', 'processing'
        ],
        'dibuat' => [
            'Proses Pembuatan', 'making'
        ],
        'siap_diambil' => [
            'Siap Diambil', 'ready'
        ],
        'selesai' => [
            'Selesai', 'success'
        ],
        'dibatalkan' => [
            'Dibatalkan', 'cancelled'
        ]
    ];

    return $status_list[$status] ?? [
        ucfirst(str_replace('_', ' ', $status)),
        'waiting'
    ];
}

function formatNomorWhatsApp($nomor)
{
    $nomor = preg_replace('/\D+/', '', (string) $nomor);

    if ($nomor === '') {
        return '';
    }

    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    } elseif (substr($nomor, 0, 2) !== '62') {
        // Nomor yang ditulis dengan awalan 8 menjadi 628.
        if (substr($nomor, 0, 1) === '8') {
            $nomor = '62' . $nomor;
        }
    }

    if (strlen($nomor) < 10 || strlen($nomor) > 15) {
        return '';
    }

    return $nomor;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pesanan</title>

    <link rel="stylesheet" href="../../assets/css/admin.css?v=2">
<link rel="stylesheet" href="../../assets/css/admin-pesanan.css?v=2">

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
    <img src="../../assets/img/logo flonuel.png" alt="Logo FlowerShop">
</div>
            <div class="brand-text">

                <span>Admin Panel</span>
            </div>
        </a>
    </div>

    <nav class="admin-navigation">
        <a href="../dashboard.php">Dashboard</a>
        <a href="index.php" class="active">Data Pesanan</a>
        <a href="../pembayaran/index.php">Data Pembayaran</a>
        <a href="../produk/index.php">Produk</a>
        <a href="../addon/index.php">Add on</a>
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
            <strong>Admin</strong>
            <span>Administrator</span>
        </div>

        <a href="../../logout.php"
           class="logout-button"
           title="Logout"
           onclick="return confirm('Yakin ingin logout?')">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</header>

<main class="pesanan-container">

    <!-- NOTIFIKASI -->
    <?php if (!empty($_SESSION['pesan_sukses'])): ?>
        <div class="status-alert success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars($_SESSION['pesan_sukses']) ?></span>
        </div>
        <?php unset($_SESSION['pesan_sukses']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['pesan_error'])): ?>
        <div class="status-alert error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($_SESSION['pesan_error']) ?></span>
        </div>
        <?php unset($_SESSION['pesan_error']); ?>
    <?php endif; ?>

    <!-- JUDUL -->
    <section class="page-heading">
        <h2>DATA PESANAN</h2>
        <p>Kelola pesanan dan hubungi pelanggan.</p>
    </section>

    <!-- STATISTIK -->
    <section class="pesanan-stats">
        <div class="pesanan-stat-card">
            <div class="pesanan-stat-content">
                <span>Total Pesanan</span>
                <strong><?= $total_pesanan ?></strong>
            </div>

            
        </div>

        <div class="pesanan-stat-card">
            <div class="pesanan-stat-content">
                <span>Pesanan Selesai</span>
                <strong><?= $pesanan_selesai ?></strong>
            </div>

            
        </div>
    </section>

    <!-- TABEL PESANAN -->
    <section class="pesanan-section">

        <div class="pesanan-section-header">
            <div class="section-heading-left">
                <div class="section-heading-icon">
                    <i class="fa-solid fa-list"></i>
                </div>

                <div>
                    <span>DATA TRANSAKSI</span>
                    <h2>Semua Pesanan</h2>
                </div>
            </div>

            <div class="total-data">
                <?= $total_pesanan ?> pesanan
            </div>
        </div>

        <div class="table-wrapper">

            <?php if (mysqli_num_rows($result_pesanan) === 0): ?>

                <div class="empty-pesanan">
                    <div class="empty-icon">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </div>

                    <h3>Belum Ada Pesanan</h3>
                    <p>Pesanan pelanggan akan muncul di sini.</p>
                </div>

            <?php else: ?>

                <table class="pesanan-table">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>KODE PESANAN</th>
                            <th>PELANGGAN</th>
                            <th>TANGGAL</th>
                            <th>TOTAL</th>
                            <th>STATUS PESANAN</th>
                            <th>DETAIL</th>
                            <th>UBAH STATUS</th>
                            <th>WHATSAPP</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php $no = 1; ?>

                    <?php while ($item = mysqli_fetch_assoc($result_pesanan)): ?>
                        <?php
                        $status_sekarang = $item['status_pesanan'] ?? '';

                        [$label_status, $class_status] =
                            statusPesanan($status_sekarang);

                        $tanggal = !empty($item['Tanggal_pesanan'])
                            ? date(
                                'd/m/Y',
                                strtotime($item['Tanggal_pesanan'])
                            )
                            : '-';

                        $nama = $item['nama_pelanggan'] ?: 'Pelanggan';

                        $kode_pesanan = $item['Kode_pesanan'] ?? '-';
                        $nama_produk = $item['Nama_produk'] ?? 'Produk';

                        $nomor_wa = formatNomorWhatsApp(
                            $item['no_hp_pelanggan'] ?? ''
                        );

                        $pesan_wa =
                            "Halo " . $nama . ",\n\n" .
                            "Kami dari FlowerShop ingin menginformasikan " .
                            "bahwa pesanan Anda dengan kode " .
                            $kode_pesanan .
                            " sudah siap diambil.\n\n" .
                            "Silakan datang ke FlowerShop untuk mengambil " .
                            "pesanan Anda.\n\n" .
                            "Terima kasih telah berbelanja di FlowerShop.";

                        $link_wa = $nomor_wa !== ''
                            ? 'https://wa.me/' . $nomor_wa .
                              '?text=' . rawurlencode($pesan_wa)
                            : '';
                        ?>

                        <tr>
                            <td class="number-column">
                                <?= $no++ ?>
                            </td>

                            <td>
                                <div class="order-code">
                                    <strong>
                                        <?= htmlspecialchars($kode_pesanan) ?>
                                    </strong>
                                    <span>
                                        <?= htmlspecialchars($nama_produk) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                    <span>
                                        <?= htmlspecialchars($nama) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <span class="order-date">
                                    <?= htmlspecialchars($tanggal) ?>
                                </span>
                            </td>

                            <td>
                                <strong class="order-total">
                                    Rp <?= rupiah($item['total_harga']) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="order-status <?= htmlspecialchars($class_status) ?>">
                                    <span class="status-dot"></span>
                                    <?= htmlspecialchars($label_status) ?>
                                </span>
                            </td>

                            <!-- DETAIL -->
                            <td>
                                <a class="detail-button"
                                   href="detail.php?id=<?= (int) $item['Pesanan_id'] ?>">
                                    <i class="fa-solid fa-eye"></i>
                                    Detail
                                </a>
                            </td>

                            <!-- UBAH STATUS -->
                            <td>
                                <form method="POST"
                                      action="index.php"
                                      class="status-form">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                                    <input type="hidden"
                                           name="pesanan_id"
                                           value="<?= (int) $item['Pesanan_id'] ?>">

                                    <select name="status_pesanan"
                                            class="status-select"
                                            aria-label="Pilih status pesanan"
                                            required>

                                        <?php foreach ($status_valid as $value => $label): ?>
                                            <option
                                                value="<?= htmlspecialchars($value) ?>"
                                                <?= $status_sekarang === $value ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>

                                    <button type="submit"
                                            name="ubah_status"
                                            value="1"
                                            class="status-save"
                                            onclick="return confirm('Yakin ingin mengubah status pesanan ini?')">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                        Simpan
                                    </button>
                                </form>
                            </td>

                            <!-- WHATSAPP -->
                            <td>
                                <?php if ($status_sekarang === 'siap_diambil' && $nomor_wa !== ''): ?>

                                    <a class="whatsapp-button"
                                       href="<?= htmlspecialchars($link_wa, ENT_QUOTES, 'UTF-8') ?>"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        <i class="fa-brands fa-whatsapp"></i>
                                        Hubungi
                                    </a>

                                <?php elseif ($status_sekarang === 'siap_diambil'): ?>

                                    <span class="whatsapp-unavailable">
                                        Nomor tidak tersedia
                                    </span>

                                <?php else: ?>

                                    <span class="whatsapp-pending">
                                        Aktif saat siap diambil
                                    </span>

                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>
                    </tbody>
                </table>

            <?php endif; ?>

        </div>
    </section>

</main>

</body>
</html>

