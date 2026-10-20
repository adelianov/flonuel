
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

if (
    !isset($_SESSION['checkout_pesanan']) ||
    !is_array($_SESSION['checkout_pesanan'])
) {
    header('Location: ../katalog.php');
    exit;
}

$checkout =& $_SESSION['checkout_pesanan'];

if ((int) ($checkout['user_id'] ?? 0) !== $user_id) {
    unset($_SESSION['checkout_pesanan']);
    header('Location: ../katalog.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| FUNGSI
|--------------------------------------------------------------------------
*/
function formatRupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fotoAddon($foto)
{
    $foto = trim((string) $foto);

    if ($foto === '') {
        return '';
    }

    // Izinkan URL eksternal hanya jika menggunakan HTTPS/HTTP.
    if (preg_match('~^https?://~i', $foto)) {
        return $foto;
    }

    $foto = str_replace('\\', '/', $foto);
    $foto = ltrim($foto, '/');

    if (strpos($foto, 'assets/') === 0 ||
        strpos($foto, 'uploads/') === 0) {
        $relative = '../../' . $foto;
        $local = __DIR__ . '/../../' . $foto;

        return is_file($local) ? $relative : '';
    }

    $nama_file = basename($foto);

    $upload_path = __DIR__ . '/../../uploads/addon/' . $nama_file;
    $asset_path = __DIR__ . '/../../assets/img/addon/' . $nama_file;

    if (is_file($upload_path)) {
        return '../../uploads/addon/' . rawurlencode($nama_file);
    }

    if (is_file($asset_path)) {
        return '../../assets/img/addon/' . rawurlencode($nama_file);
    }

    return '';
}

/*
|--------------------------------------------------------------------------
| HARGA DASAR PESANAN
|--------------------------------------------------------------------------
| Total dihitung dari harga produk + total Add-On.
|--------------------------------------------------------------------------
*/
$harga_dasar = (float) (
    $checkout['harga_produk']
    ?? $checkout['total_dasar']
    ?? $checkout['total_harga']
    ?? 0
);

$checkout['total_dasar'] = $harga_dasar;

/*
|--------------------------------------------------------------------------
| AMBIL DATA ADD-ON
|--------------------------------------------------------------------------
*/
$addons = [];

$sql = "
    SELECT
        addon_id,
        Nama_addon AS nama_addon,
        Harga AS harga,
        Foto AS foto,
        Stok AS stok
    FROM add_on
    ORDER BY Nama_addon ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    error_log('Query Add-On gagal: ' . mysqli_error($conn));
    die('Data Add-On gagal dimuat. Silakan coba kembali.');
}

while ($row = mysqli_fetch_assoc($result)) {
    $row['addon_id'] = (int) $row['addon_id'];
    $row['harga'] = (float) $row['harga'];
    $row['stok'] = (int) $row['stok'];

    $addons[] = $row;
}

$addons_session = $checkout['addons'] ?? [];

if (!is_array($addons_session)) {
    $addons_session = [];
}

$selected_ids = [];

foreach ($addons_session as $item) {
    if (!is_array($item)) {
        continue;
    }

    $id = (int) ($item['addon_id'] ?? $item['id'] ?? 0);

    if ($id > 0) {
        $selected_ids[] = $id;
    }
}

$selected_ids = array_values(array_unique($selected_ids));

$error = '';

if (empty($_SESSION['csrf_addon'])) {
    $_SESSION['csrf_addon'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');

    if (
        $csrf === '' ||
        !hash_equals($_SESSION['csrf_addon'], $csrf)
    ) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } else {
        $posted_ids = $_POST['addons'] ?? [];

        if (!is_array($posted_ids)) {
            $posted_ids = [];
        }

        $posted_ids = array_map('intval', $posted_ids);

        if (count($posted_ids) !== count(array_unique($posted_ids))) {
            $error = 'Terdapat pilihan Add-On duplikat.';
        } else {
            foreach ($posted_ids as $id) {
                if ($id <= 0) {
                    $error = 'Pilihan Add-On tidak valid.';
                    break;
                }
            }
        }

        if ($error === '' && count($posted_ids) > count($addons)) {
            $error = 'Pilihan Add-On tidak valid.';
        }

        $selected_ids = array_values(array_unique($posted_ids));
    }

    if ($error === '') {
        $selected_addons = [];
        $total_addon = 0;

        $stmt = mysqli_prepare(
            $conn,
            "SELECT addon_id, Nama_addon, Harga, Foto, Stok
             FROM add_on
             WHERE addon_id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $error = 'Sistem gagal memeriksa Add-On.';
        } else {
            foreach ($selected_ids as $addon_id) {
                mysqli_stmt_bind_param($stmt, 'i', $addon_id);
                mysqli_stmt_execute($stmt);

                $result_item = mysqli_stmt_get_result($stmt);
                $item = mysqli_fetch_assoc($result_item);

                if (!$item) {
                    $error = 'Ada Add-On yang tidak ditemukan. Muat ulang halaman.';
                    break;
                }

                if ((int) $item['Stok'] < 1) {
                    $error = 'Stok Add-On "' .
                        $item['Nama_addon'] .
                        '" sudah habis. Silakan pilih Add-On lain.';
                    break;
                }

                
$harga = (float) $item['Harga'];
$jumlah = max(
    1,
    (int) ($_POST['jumlah_addon'][$addon_id] ?? 1)
);
$stok = (int) $item['Stok'];

if ($jumlah > $stok) {
    $error = 'Jumlah Add-On "' .
        $item['Nama_addon'] .
        '" melebihi stok yang tersedia.';
    break;
}

$selected_addons[] = [
    'addon_id' => (int) $item['addon_id'],
    'nama'     => $item['Nama_addon'],
    'harga'    => $harga,
    'jumlah'   => $jumlah,
    'foto'     => $item['Foto']
];

// Harga satuan dikali quantity
$total_addon += $harga * $jumlah;


                $total_addon += $harga;
            }

            mysqli_stmt_close($stmt);
        }

        if ($error === '') {
            $checkout['addons'] = $selected_addons;
            $checkout['total_addon'] = $total_addon;
            $checkout['total_harga'] = $harga_dasar + $total_addon;

            header('Location: pickup.php');
            exit;
        }
    }
}

$total_addon_display = 0;
$jumlah_addon_display = 0;

foreach ($addons_session as $item) {
    if (!is_array($item)) {
        continue;
    }

    $jumlah = max(1, (int) ($item['jumlah'] ?? 1));
    $harga = (float) ($item['harga'] ?? 0);

    $total_addon_display += $harga * $jumlah;
    $jumlah_addon_display += $jumlah;
}

$total_pesanan_display = $harga_dasar + $total_addon_display;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pilih Add-On</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="../../assets/css/addon.css">

    <style>
        :root {
            --pink: #f38f95;
            --pink-hover: #b82b6f;
            --heading: #35242d;
            --text: #55414b;
            --muted: #8b7782;
            --border: #f1e5eb;
            --pale: #fff7fb;
        }

        body {
            color: var(--text);
        }

        .addon-header h1 {
            color: var(--heading);
        }

        .addon-header p {
            color: var(--muted);
        }

        .addon-optional-note {
            display: flex;
            gap: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #f3d2e1;
            border-radius: 12px;
            background: var(--pale);
            color: #f38f95;
            font-size: 12px;
            line-height: 1.7;
        }

        .addon-optional-note i {
            margin-top: 3px;
        }

        .addon-error {
            padding: 13px 15px;
            margin-bottom: 18px;
            border: 1px solid #f2c8d4;
            border-radius: 10px;
            background: #fff1f4;
            color: #a61e45;
            font-size: 12px;
            line-height: 1.6;
        }

        .addon-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .addon-card {
            position: relative;
            display: block;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: #fff;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s, transform .2s;
        }

        .addon-card:hover {
            transform: translateY(-2px);
            border-color: #e8b4cf;
            box-shadow: 0 8px 20px rgba(70, 30, 50, .06);
        }

        .addon-card.selected {
            border: 2px solid var(--pink);
            background: #fff9fc;
            box-shadow: 0 4px 15px rgba(214, 51, 132, .08);
        }

        .addon-card.disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .addon-card input[type="checkbox"] {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 2;
            width: 17px;
            height: 17px;
            accent-color: var(--pink);
            cursor: pointer;
        }

        .addon-card input[type="checkbox"]:disabled {
            cursor: not-allowed;
        }

        .addon-card-content {
            padding: 10px;
        }

        .addon-image {
            position: relative;
            overflow: hidden;
            height: 155px;
            border-radius: 9px;
            background: var(--pale);
        }

        .addon-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .addon-image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: #d9a1bd;
            background: #fff2f8;
        }

        .addon-image-placeholder i {
            font-size: 28px;
        }

        .addon-name {
            margin-top: 13px;
            color: var(--heading);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.5;
        }

        .addon-detail {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
        }

        .addon-price {
            margin-top: 9px;
            color: var(--pink);
            font-size: 13px;
            font-weight: 750;
        }

        .addon-check {
            display: none;
        }

        .addon-card.selected .addon-check {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: var(--pink);
            color: #fff;
            font-size: 11px;
        }

        .addon-empty {
            padding: 40px 20px;
            border: 1px dashed var(--border);
            border-radius: 12px;
            background: var(--pale);
            text-align: center;
            color: var(--muted);
        }

        .addon-empty i {
            margin-bottom: 12px;
            color: var(--pink);
            font-size: 30px;
        }

        .addon-summary {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 24px;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
        }

        .summary-left,
        .summary-right {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .summary-right {
            text-align: right;
        }

        .summary-label {
            color: var(--muted);
            font-size: 11px;
        }

        .summary-count {
            color: var(--heading);
            font-size: 13px;
            font-weight: 700;
        }

        .summary-right strong {
            color: var(--pink);
            font-size: 17px;
        }

        .addon-grand-total {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            font-size: 12px;
        }

        .addon-grand-total strong {
            color: var(--heading);
        }

        .addon-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 20px;
        }

        .back-button,
        .continue-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 9px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: .2s;
        }

        .back-button {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
        }

        .back-button:hover {
            background: var(--pale);
        }

        .continue-button {
            border: 1px solid var(--pink);
            background: var(--pink);
            color: #fff;
        }

        .continue-button:hover {
            border-color: var(--pink-hover);
            background: var(--pink-hover);
        }

        .addon-skip-note {
            margin-top: 12px;
            color: var(--muted);
            font-size: 11px;
            text-align: right;
        }
.addon-quantity {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 12px;
    font-size: 12px;
}

.addon-quantity-input {
    width: 65px;
    padding: 7px;
    border: 1px solid #f1e5eb;
    border-radius: 7px;
    text-align: center;
    font-size: 13px;
}
        @media (max-width: 800px) {
            .addon-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .addon-image {
                height: 145px;
            }
        }

        @media (max-width: 480px) {
            .addon-grid {
                gap: 10px;
            }

            .addon-card-content {
                padding: 8px;
            }

            .addon-image {
                height: 115px;
            }

            .addon-name {
                font-size: 12px;
            }

            .addon-summary {
                padding: 14px;
            }

            .addon-action {
                flex-direction: column-reverse;
            }

            .back-button,
            .continue-button {
                width: 100%;
            }

            .addon-skip-note {
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="addon-page">

    <header class="addon-header">
        <h1>PILIH ADD-ON</h1>
    </header>

    <main class="addon-container">

        <?php if ($error !== ''): ?>
            <div class="addon-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= e($error); ?>
            </div>
        <?php endif; ?>

        <div class="addon-optional-note">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Add-On bersifat opsional.</strong>
                Kamu dapat memilih aksesori tambahan atau langsung melanjutkan
                tanpa memilih Add-On.
            </div>
        </div>

        <form action="" method="POST" id="addonForm">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_addon']); ?>"
            >

            <?php if (!empty($addons)): ?>

                <div class="addon-grid">

                    <?php foreach ($addons as $addon): ?>
                        <?php
                        $addon_id = (int) $addon['addon_id'];
                        $stok = (int) $addon['stok'];
                        $is_selected = in_array(
                            $addon_id,
                            $selected_ids,
                            true
                        );
                        $foto_url = fotoAddon($addon['foto'] ?? '');
                        $habis = $stok < 1;
                        ?>

                        <label class="addon-card
                            <?= $is_selected ? 'selected' : ''; ?>
                            <?= $habis ? 'disabled' : ''; ?>">

                            <input
                                type="checkbox"
                                name="addons[]"
                                value="<?= $addon_id; ?>"
                                data-price="<?= $addon['harga']; ?>"
                                <?= $is_selected && !$habis ? 'checked' : ''; ?>
                                <?= $habis ? 'disabled' : ''; ?>
                            >

                            <div class="addon-card-content">

                                <div class="addon-image">

                                    <?php if ($foto_url !== ''): ?>
                                        <img
                                            src="<?= e($foto_url); ?>"
                                            alt="<?= e($addon['nama_addon']); ?>"
                                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                                        >
                                    <?php endif; ?>

                                    <div
                                        class="addon-image-placeholder"
                                        style="display:<?= $foto_url !== '' ? 'none' : 'flex'; ?>;"
                                    >
                                        <i class="fa-solid fa-gift"></i>
                                    </div>

                                </div>

                                <div class="addon-name">
                                    <?= e($addon['nama_addon']); ?>
                                </div>

                                <div class="addon-detail">
                                    <?php if ($habis): ?>
                                        Stok habis
                                    <?php else: ?>
                                        Stok tersedia: <?= $stok; ?>
                                    <?php endif; ?>
                                </div>

<div class="addon-price">
    <?= formatRupiah($addon['harga']); ?>
</div>

<div class="addon-quantity">
    <label for="qty_<?= $addon_id; ?>">Jumlah:</label>

    <input
        type="number"
        id="qty_<?= $addon_id; ?>"
        name="jumlah_addon[<?= $addon_id; ?>]"
        value="<?= $is_selected
            ? max(1, (int) (
                $addons_session[array_search(
                    $addon_id,
                    array_column($addons_session, 'addon_id')
                )]['jumlah'] ?? 1
            ))
            : 1; ?>"
        min="1"
        max="<?= $stok; ?>"
        class="addon-quantity-input"
        <?= (!$is_selected || $habis) ? 'disabled' : ''; ?>
    >
</div>

                            </div>

                            <div class="addon-check">
                                <i class="fa-solid fa-check"></i>
                            </div>

                        </label>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="addon-empty">
                    <i class="fa-solid fa-box-open"></i>
                    <p>Belum ada Add-On tersedia. Kamu tetap dapat melanjutkan pesanan.</p>
                </div>

            <?php endif; ?>

            <div class="addon-summary">
                <div class="summary-left">
                    <span class="summary-label">Add-On Dipilih</span>
                    <span class="summary-count" id="addonCount">
                        <?= $jumlah_addon_display; ?> item
                    </span>
                </div>

                <div class="summary-right">
                    <span class="summary-label">Total Add-On</span>
                    <strong id="addonTotal">
                        <?= formatRupiah($total_addon_display); ?>
                    </strong>
                </div>
            </div>

            <div class="addon-grand-total">
                <span>Total sementara pesanan</span>
                <strong id="grandTotal">
                    <?= formatRupiah($total_pesanan_display); ?>
                </strong>
            </div>

            <div class="addon-action">

                <a
                    href="buat.php?produk_id=<?= $produk_id; ?>"
                    class="back-button"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Kembali
                </a>

                <button type="submit" class="continue-button">
                    Lanjut ke Pickup
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </div>

            <div class="addon-skip-note">
                Tidak ingin tambahan? Biarkan semua pilihan kosong, lalu lanjutkan.
            </div>

        </form>

    </main>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = Array.from(
        document.querySelectorAll('input[name="addons[]"]:not(:disabled)')
    );

    const addonCount = document.getElementById('addonCount');
    const addonTotal = document.getElementById('addonTotal');
    const grandTotal = document.getElementById('grandTotal');
    const form = document.getElementById('addonForm');

    const hargaDasar = <?= json_encode($harga_dasar); ?>;

    function formatRupiah(number) {
        return 'Rp ' + new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0
        }).format(number);
    }

    function updateTotal() {
        let total = 0;
        let count = 0;

        checkboxes.forEach(function (checkbox) {
            const card = checkbox.closest('.addon-card');
            const quantityInput = card
                ? card.querySelector('.addon-quantity-input')
                : null;

            if (!quantityInput) return;

            const stok = parseInt(quantityInput.max, 10) || 1;

            // Jumlah aktif hanya jika Add-On dicentang.
            quantityInput.disabled = !checkbox.checked;

            if (checkbox.checked) {
                let quantity = parseInt(quantityInput.value, 10);

                if (!Number.isFinite(quantity) || quantity < 1) {
                    quantity = 1;
                }

                quantity = Math.min(quantity, stok);
                quantityInput.value = quantity;

                const harga = Number(checkbox.dataset.price) || 0;

                count += quantity;
                total += harga * quantity;
            }

            card.classList.toggle('selected', checkbox.checked);
        });

        if (addonCount) {
            addonCount.textContent = count + ' item';
        }

        if (addonTotal) {
            addonTotal.textContent = formatRupiah(total);
        }

        if (grandTotal) {
            grandTotal.textContent = formatRupiah(
                Number(hargaDasar) + total
            );
        }
    }

    checkboxes.forEach(function (checkbox) {
        const card = checkbox.closest('.addon-card');
        const quantityInput = card
            ? card.querySelector('.addon-quantity-input')
            : null;

        checkbox.addEventListener('change', function () {
            if (quantityInput) {
                if (checkbox.checked) {
                    // Saat dipilih, otomatis mulai dari 1.
                    quantityInput.value = 1;
                }

                // Perbarui total dan status input.
                updateTotal();
            }
        });

        if (quantityInput) {
            quantityInput.addEventListener('input', updateTotal);

            quantityInput.addEventListener('change', function () {
                const stok = parseInt(quantityInput.max, 10) || 1;
                let jumlah = parseInt(quantityInput.value, 10);

                if (!Number.isFinite(jumlah) || jumlah < 1) {
                    jumlah = 1;
                }

                quantityInput.value = Math.min(jumlah, stok);
                updateTotal();
            });
        }
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            let valid = true;

            checkboxes.forEach(function (checkbox) {
                if (!checkbox.checked) return;

                const card = checkbox.closest('.addon-card');
                const quantityInput = card
                    ? card.querySelector('.addon-quantity-input')
                    : null;

                if (!quantityInput) return;

                const jumlah = parseInt(quantityInput.value, 10);
                const stok = parseInt(quantityInput.max, 10);

                if (
                    !Number.isFinite(jumlah) ||
                    jumlah < 1 ||
                    jumlah > stok
                ) {
                    valid = false;
                }
            });

            if (!valid) {
                event.preventDefault();
                alert('Periksa kembali jumlah Add-On dan stok yang tersedia.');
            }
        });
    }

    updateTotal();
});
</script>

</body>
</html>

