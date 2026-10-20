
<?php
session_start();

/*
|--------------------------------------------------------------------------
| KONEKSI DATABASE
|--------------------------------------------------------------------------
| Lokasi file:
| C:\xampp\htdocs\bunga\katalog.php
| C:\xampp\htdocs\bunga\config\koneksi.php
*/

$configFile = __DIR__ . '/config/koneksi.php';

if (!file_exists($configFile)) {
    die('File config/koneksi.php tidak ditemukan. Periksa struktur folder proyek.');
}

require_once $configFile;

// Mendukung variabel koneksi $conn maupun $koneksi
$db = $conn ?? $koneksi ?? null;

if (!$db) {
    die('Koneksi database tidak ditemukan. Periksa variabel koneksi di config/koneksi.php.');
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA PRODUK
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            Produk_id,
            Nama_produk,
            Deskripsi_produk,
            Harga,
            Jumlah_bunga,
            Foto_produk
        FROM produk
        WHERE Status = 'aktif'
        ORDER BY Produk_id DESC";

$result = mysqli_query($db, $sql);

if (!$result) {
    die('Gagal mengambil data produk: ' . htmlspecialchars(mysqli_error($db)));
}

/*
|--------------------------------------------------------------------------
| FORMAT HARGA
|--------------------------------------------------------------------------
*/

function formatRupiah($angka)
{
    return 'Rp' . number_format((float) $angka, 0, ',', '.');
}

/*
|--------------------------------------------------------------------------
| LOKASI FOTO PRODUK
|--------------------------------------------------------------------------
*/

$folderFoto = __DIR__ . '/uploads/produk/';
$urlFolderFoto = 'uploads/produk/';

function cariFoto($namaFoto, $folderFoto, $urlFolderFoto)
{
    $namaFoto = trim((string) $namaFoto);

    if ($namaFoto === '') {
        return null;
    }

    // Database menyimpan nama file gambar
    $namaFile = basename(str_replace('\\', '/', $namaFoto));

    $pathFisik = $folderFoto . $namaFile;

    // Periksa apakah file benar-benar tersedia
    if (is_file($pathFisik)) {
        return $urlFolderFoto . rawurlencode($namaFile);
    }

    return null;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Katalog Produk | Flonuel</title>

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
            max-width: 1250px;
            margin: 35px auto;
        }

        .judul {
            text-align: center;
            margin-bottom: 30px;
        }

        .judul h1 {
            color: #b85d68;
            font-size: 30px;
            margin-bottom: 8px;
        }

        .judul p {
            color: #777;
            font-size: 14px;
        }

        .produk-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .produk-card {
            background: #fff;
            border: 1px solid #f0dede;
            border-radius: 12px;
            overflow: hidden;
            transition: .2s ease;
        }

        .produk-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 22px rgba(150, 70, 80, .12);
        }

        .produk-foto {
            width: 100%;
            height: 240px;
            background: #f8eeee;
            overflow: hidden;
        }

        .produk-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .foto-kosong {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            text-align: center;
            color: #999;
            font-size: 13px;
        }

        .produk-info {
            padding: 16px;
        }

        .produk-info h3 {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 8px;
            min-height: 44px;
        }

        .deskripsi {
            color: #777;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 12px;
            min-height: 39px;
        }

        .jumlah-bunga {
            color: #777;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .harga {
            color: #b85d68;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .btn-detail {
            display: block;
            padding: 11px;
            background: #b85d68;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 7px;
            font-size: 14px;
            transition: .2s;
        }

        .btn-detail:hover {
            background: #963f4a;
        }

        .produk-kosong {
            grid-column: 1 / -1;
            background: #fff;
            border: 1px solid #f0dede;
            border-radius: 12px;
            padding: 40px 15px;
            text-align: center;
            color: #777;
        }

        @media (max-width: 950px) {
            .produk-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .produk-foto {
                height: 210px;
            }
        }

        @media (max-width: 650px) {
            .container {
                width: 94%;
                margin: 25px auto;
            }

            .produk-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .produk-foto {
                height: 165px;
            }

            .produk-info {
                padding: 12px;
            }

            .produk-info h3 {
                font-size: 14px;
                min-height: 40px;
            }

            .deskripsi {
                font-size: 12px;
            }

            .harga {
                font-size: 15px;
            }

            .btn-detail {
                font-size: 12px;
                padding: 10px 5px;
            }

            .judul h1 {
                font-size: 25px;
            }
        }

        @media (max-width: 360px) {
            .produk-grid {
                grid-template-columns: 1fr;
            }

            .produk-foto {
                height: 230px;
            }
        }
    </style>
</head>

<body>

<main class="container">

    <header class="judul">
        <h1>Katalog Produk</h1>
        <p>Pilihan buket bunga dari Flonuel</p>
    </header>

    <section class="produk-grid">

        <?php if (mysqli_num_rows($result) > 0): ?>

            <?php while ($produk = mysqli_fetch_assoc($result)): ?>

                <?php
                $foto = cariFoto(
                    $produk['Foto_produk'] ?? '',
                    $folderFoto,
                    $urlFolderFoto
                );

                $deskripsi = trim($produk['Deskripsi_produk'] ?? '');

                if ($deskripsi === '') {
                    $deskripsi = 'Buket bunga pilihan Flonuel.';
                }

                if (function_exists('mb_strimwidth')) {
                    $deskripsi = mb_strimwidth(
                        $deskripsi,
                        0,
                        85,
                        '...',
                        'UTF-8'
                    );
                } elseif (strlen($deskripsi) > 85) {
                    $deskripsi = substr($deskripsi, 0, 82) . '...';
                }
                ?>

                <article class="produk-card">

                    <div class="produk-foto">

                        <?php if ($foto !== null): ?>

                            <img
                                src="<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($produk['Nama_produk'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                onerror="this.onerror=null;this.parentElement.innerHTML='<div class=&quot;foto-kosong&quot;>Gambar gagal dimuat. Periksa lokasi file.</div>';"
                            >

                        <?php else: ?>

                            <div class="foto-kosong">
                                Foto tidak ditemukan di folder uploads/produk.
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="produk-info">

                        <h3>
                            <?= htmlspecialchars($produk['Nama_produk'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p class="deskripsi">
                            <?= htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="jumlah-bunga">
                            Maksimal bunga:
                            <?= (int) $produk['Jumlah_bunga'] ?>
                        </p>

                        <div class="harga">
                            <?= formatRupiah($produk['Harga']) ?>
                        </div>

                        <a
                            href="detail_produk.php?id=<?= (int) $produk['Produk_id'] ?>"
                            class="btn-detail"
                        >
                            Lihat Detail
                        </a>

                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="produk-kosong">
                Belum ada produk aktif yang tersedia.
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
