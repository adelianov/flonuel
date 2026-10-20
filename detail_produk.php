
<?php
session_start();

require_once __DIR__ . '/config/koneksi.php';

// Mendukung variabel koneksi $conn atau $koneksi
$db = $conn ?? $koneksi ?? null;

if (!$db) {
    die('Koneksi database tidak ditemukan. Periksa config/koneksi.php.');
}

// Ambil ID produk dari URL
$produk_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$produk_id || $produk_id < 1) {
    header('Location: katalog.php');
    exit;
}

// Ambil detail produk
$sql = "SELECT
            Produk_id,
            Nama_produk,
            Deskripsi_produk,
            Harga,
            Jumlah_bunga,
            Foto_produk,
            Status
        FROM produk
        WHERE Produk_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($db, $sql);

if (!$stmt) {
    die('Gagal menyiapkan query produk.');
}

mysqli_stmt_bind_param($stmt, 'i', $produk_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$produk = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// Produk tidak ditemukan atau tidak aktif
if (!$produk || strtolower($produk['Status']) !== 'aktif') {
    http_response_code(404);
    $tidakDitemukan = true;
} else {
    $tidakDitemukan = false;
}

// Format harga
function formatHarga($harga)
{
    return 'Rp' . number_format((float) $harga, 0, ',', '.');
}

// Tentukan lokasi foto
$fotoUrl = null;

if (!$tidakDitemukan) {
    $namaFoto = trim((string) ($produk['Foto_produk'] ?? ''));
    $namaFoto = basename(str_replace('\\', '/', $namaFoto));

    $pathFoto = __DIR__ . '/uploads/produk/' . $namaFoto;

    if ($namaFoto !== '' && is_file($pathFoto)) {
        $fotoUrl = 'uploads/produk/' . rawurlencode($namaFoto);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $tidakDitemukan
            ? 'Produk Tidak Ditemukan | Flonuel'
            : htmlspecialchars($produk['Nama_produk'], ENT_QUOTES, 'UTF-8') . ' | Flonuel' ?>
    </title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fffafa;
            color: #333;
        }

        .container {
            width: 92%;
            max-width: 1100px;
            margin: 35px auto 60px;
        }

        .kembali {
            display: inline-block;
            margin-bottom: 25px;
            color: #b85d68;
            text-decoration: none;
            font-size: 14px;
        }

        .kembali:hover {
            color: #963f4a;
        }

        .detail-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
            padding: 28px;
            background: #fff;
            border: 1px solid #f0dede;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(150, 70, 80, .06);
        }

        .detail-foto {
            width: 100%;
            min-height: 350px;
            height: 430px;
            overflow: hidden;
            background: #f8eeee;
            border-radius: 10px;
        }

        .detail-foto img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .foto-kosong {
            width: 100%;
            height: 100%;
            min-height: 350px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            text-align: center;
            color: #999;
            font-size: 14px;
        }

        .detail-info {
            padding: 10px 0;
        }

        .label-produk {
            display: inline-block;
            padding: 7px 12px;
            margin-bottom: 15px;
            color: #963f4a;
            background: #fbe9eb;
            border-radius: 20px;
            font-size: 12px;
        }

        .detail-info h1 {
            margin-bottom: 15px;
            color: #7f3440;
            font-size: 29px;
            line-height: 1.4;
        }

        .harga {
            margin-bottom: 22px;
            color: #b85d68;
            font-size: 27px;
            font-weight: bold;
        }

        .garis {
            height: 1px;
            margin: 20px 0;
            background: #f0dede;
        }

        .judul-deskripsi {
            margin-bottom: 10px;
            font-size: 16px;
        }

        .deskripsi {
            color: #666;
            font-size: 14px;
            line-height: 1.8;
            white-space: pre-line;
        }

        .informasi {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 14px 0;
            border-bottom: 1px solid #f5e8e8;
            font-size: 14px;
        }

        .informasi span:first-child {
            color: #777;
        }

        .informasi strong {
            color: #444;
            text-align: right;
        }

        .tombol {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 46px;
            margin-top: 25px;
            padding: 12px 18px;
            background: #b85d68;
            color: #fff;
            text-decoration: none;
            text-align: center;
            border: none;
            border-radius: 7px;
            font-size: 14px;
            cursor: pointer;
            transition: .2s;
        }

        .tombol:hover {
            background: #963f4a;
        }

        .tombol-kembali {
            display: inline-block;
            margin-top: 15px;
            color: #b85d68;
            text-decoration: none;
            font-size: 14px;
        }

        .tidak-ditemukan {
            padding: 50px 20px;
            text-align: center;
            background: #fff;
            border: 1px solid #f0dede;
            border-radius: 12px;
        }

        .tidak-ditemukan h1 {
            margin-bottom: 12px;
            color: #963f4a;
        }

        .tidak-ditemukan p {
            color: #777;
            line-height: 1.6;
        }

        @media (max-width: 750px) {
            .container {
                width: 94%;
                margin-top: 22px;
            }

            .detail-card {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 16px;
            }

            .detail-foto {
                height: 330px;
                min-height: 250px;
            }

            .detail-info {
                padding: 5px 0;
            }

            .detail-info h1 {
                font-size: 24px;
            }

            .harga {
                font-size: 23px;
            }
        }

        @media (max-width: 400px) {
            .detail-foto {
                height: 260px;
            }

            .detail-card {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<main class="container">

    <a href="katalog.php" class="kembali">
        &larr; Kembali ke Katalog
    </a>

    <?php if ($tidakDitemukan): ?>

        <section class="tidak-ditemukan">
            <h1>Produk Tidak Ditemukan</h1>
            <p>
                Produk tidak tersedia, sudah dihapus, atau sedang tidak aktif.
            </p>
            <a href="katalog.php" class="tombol" style="max-width: 220px; margin: 25px auto 0;">
                Kembali ke Katalog
            </a>
        </section>

    <?php else: ?>

        <section class="detail-card">

            <div class="detail-foto">

                <?php if ($fotoUrl !== null): ?>

                    <img
                        src="<?= htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($produk['Nama_produk'], ENT_QUOTES, 'UTF-8') ?>"
                        onerror="this.onerror=null;this.style.display='none';this.parentElement.innerHTML='<div class=&quot;foto-kosong&quot;>Foto produk tidak dapat dimuat.</div>';"
                    >

                <?php else: ?>

                    <div class="foto-kosong">
                        Foto produk belum tersedia.
                    </div>

                <?php endif; ?>

            </div>

            <div class="detail-info">

                <span class="label-produk">Produk Flonuel</span>

                <h1>
                    <?= htmlspecialchars($produk['Nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                </h1>

                <div class="harga">
                    <?= formatHarga($produk['Harga']) ?>
                </div>

                <div class="garis"></div>

                <h2 class="judul-deskripsi">Deskripsi Produk</h2>

                <p class="deskripsi">
                    <?php
                    $deskripsi = trim($produk['Deskripsi_produk'] ?? '');
                    echo htmlspecialchars(
                        $deskripsi !== '' ? $deskripsi : 'Belum ada deskripsi produk.',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </p>

                <div class="garis"></div>

                <div class="informasi">
                    <span>Kode produk</span>
                    <strong>
                        <?= (int) $produk['Produk_id'] ?>
                    </strong>
                </div>

                <div class="informasi">
                    <span>Status</span>
                    <strong>Tersedia</strong>
                </div>

                <?php if (isset($_SESSION['user_id']) &&
                          ($_SESSION['role'] ?? '') === 'customer'): ?>

                    <a
                        href="customer/pesanan/index.php?produk_id=<?= (int) $produk['Produk_id'] ?>"
                        class="tombol"
                    >
                        Pesan Produk
                    </a>

                <?php else: ?>

                    <a
                        href="login.php"
                        class="tombol"
                    >
                        Login untuk Memesan
                    </a>

                <?php endif; ?>

                <a href="katalog.php" class="tombol-kembali">
                    Lihat produk lainnya
                </a>

            </div>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
