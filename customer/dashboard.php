
<?php
session_start();

require_once '../config/koneksi.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];


$nama_customer = 'Customer';

$sqlUser = "
    SELECT User_id, Nama, Email, No_Hp
    FROM USER
    WHERE User_id = ?
    LIMIT 1
";

$stmtUser = mysqli_prepare($conn, $sqlUser);

if ($stmtUser) {
    mysqli_stmt_bind_param($stmtUser, "i", $user_id);
    mysqli_stmt_execute($stmtUser);

    $resultUser = mysqli_stmt_get_result($stmtUser);
    $userData = mysqli_fetch_assoc($resultUser);

    if ($userData) {
        $nama_customer = $userData['Nama'] ?? 'Customer';
    }

    mysqli_stmt_close($stmtUser);
}

$sqlBestSeller = "
    SELECT
        pr.Produk_id,
        pr.Nama_produk,
        pr.Harga,
        pr.Foto_produk,
        COUNT(ps.Pesanan_id) AS total_terjual
    FROM produk pr
    LEFT JOIN pesanan ps
        ON ps.Produk_id = pr.Produk_id
        AND ps.status_pesanan NOT IN (
            'dibatalkan',
            'menunggu_pembayaran',
            'menunggu_verifikasi'
        )
    WHERE pr.Status = 'aktif'
    GROUP BY
        pr.Produk_id,
        pr.Nama_produk,
        pr.Harga,
        pr.Foto_produk
    ORDER BY total_terjual DESC, pr.Produk_id DESC
    LIMIT 3
";

$best_seller_result = mysqli_query($conn, $sqlBestSeller);


$sqlKategori = "
    SELECT
        k.Kategori_id,
        k.Nama_kategori,
        COUNT(p.Produk_id) AS jumlah_produk
    FROM KATEGORI_PRODUK k
    LEFT JOIN PRODUK p
        ON p.Kategori_id = k.Kategori_id
        AND p.Status = 'aktif'
    GROUP BY
        k.Kategori_id,
        k.Nama_kategori
    ORDER BY k.Kategori_id ASC
";

$kategori_result = mysqli_query($conn, $sqlKategori);


function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard Customer</title>

    <!-- FONT AWESOME -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- CUSTOMER CSS -->
    <link
        rel="stylesheet"
        href="../assets/css/customer.css?v=2"
    >

    <!-- CSS KATEGORI DASHBOARD -->
    <style>

        .welcome-card {
    background: #fff9f9 !important;
    background-color: #fff9f9  !important;
    border: 1px solid #fff9f9;
    border-radius: 16px;

    overflow: hidden;
}
        /* KATEGORI BUNGA */
        .dashboard-category-section {
            width: 100%;
            margin-bottom: 30px;
        }

        .dashboard-category-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
        }

        .dashboard-category-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 25px 15px;
            text-align: center;
            text-decoration: none;
            color: #292525;
            background: #fff;
            border: 1px solid #f3dfe4;
            border-radius: 15px;
            transition:
                transform .25s ease,
                box-shadow .25s ease,
                border-color .25s ease;
        }

        .dashboard-category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(216, 102, 115, .12);
            border-color: #e77d88;
        }

        .dashboard-category-icon {
            width: 65px;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            border-radius: 50%;
            background: #fff0f2;
            color: #d86673;
            font-size: 25px;
        }

        .dashboard-category-name {
            margin-bottom: 7px;
            font-size: 15px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .dashboard-category-count {
            color: #777070;
            font-size: 12px;
        }

        .dashboard-category-link {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 18px;
            color: #d86673;
            font-size: 12px;
            font-weight: 700;
        }

        .dashboard-category-empty {
            grid-column: 1 / -1;
            padding: 35px 20px;
            text-align: center;
            background: #fff;
            border: 1px dashed #edcbd1;
            border-radius: 12px;
            color: #777070;
        }

        .dashboard-category-empty i {
            display: block;
            margin-bottom: 12px;
            color: #d86673;
            font-size: 32px;
        }

        @media (max-width: 1000px) {
            .dashboard-category-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-category-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .dashboard-category-card {
                padding: 20px 10px;
            }

            .dashboard-category-icon {
                width: 55px;
                height: 55px;
                font-size: 22px;
            }

            .dashboard-category-name {
                font-size: 13px;
            }

            .dashboard-category-count,
            .dashboard-category-link {
                font-size: 11px;
            }
        }


.dashboard-bestseller-section {
    width: 100%;
    margin: 25px 0 35px;
}

.dashboard-bestseller-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    width: 100%;
}

.dashboard-bestseller-card {
    position: relative;
    min-width: 0;
    overflow: hidden;
    background: #fff;
    border: 1px solid #f3dfe4;
    border-radius: 15px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
    transition: transform .25s ease, box-shadow .25s ease;
}

.dashboard-bestseller-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(216, 102, 115, .15);
}

.dashboard-bestseller-image {
    display: block;
    width: 100%;
    height: 220px;
    object-fit: cover;
    background: #fff0f2;
}

.dashboard-bestseller-info {
    padding: 18px;
}

.dashboard-bestseller-name {
    margin: 0 0 10px;
    color: #292525;
    font-size: 16px;
    font-weight: 700;
}

.dashboard-bestseller-price {
    margin-bottom: 12px;
    color: #d86673;
    font-size: 17px;
    font-weight: 700;
}

.dashboard-bestseller-sold {
    margin-bottom: 15px;
    color: #777070;
    font-size: 12px;
}

.dashboard-bestseller-button {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 11px;
    box-sizing: border-box;
    color: #fff;
    background: #d86673;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: background .2s ease;
}

.dashboard-bestseller-button:hover {
    color: #fff;
    background: #c34f60;
}

.dashboard-bestseller-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 2;
    padding: 7px 11px;
    color: #fff;
    background: #d86673;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.dashboard-bestseller-empty {
    grid-column: 1 / -1;
    padding: 30px 20px;
    text-align: center;
    color: #777070;
    background: #fff;
    border: 1px dashed #edcbd1;
    border-radius: 12px;
}

.dashboard-bestseller-empty i {
    display: block;
    margin-bottom: 12px;
    color: #d86673;
    font-size: 32px;
}

/* Tablet */
@media (max-width: 1000px) {
    .dashboard-bestseller-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* HP */
@media (max-width: 600px) {
    .dashboard-bestseller-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-bestseller-image {
        height: 230px;
    }
}
    </style>
</head>


<body>


<aside class="dashboard-sidebar" id="sidebar">

<a
    href="dashboard.php"
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

    <a href="dashboard.php" class="active">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>

    <a href="pesanan/index.php">
        <i class="fa-solid fa-bag-shopping"></i>
        <span>Pesanan Saya</span>
    </a>

    <a href="../customer/katalog.php">
        <i class="fa-solid fa-book-open"></i>
        <span>Katalog</span>
    </a>

    <a href="pembayaran/index.php">
        <i class="fa-solid fa-credit-card"></i>
        <span>Pembayaran</span>
    </a>

    <a
        href="../logout.php"
        onclick="return confirm('Apakah Anda yakin ingin logout?')"
    >
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>

</nav>


</aside>


<!-- SIDEBAR OVERLAY -->
<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<div class="main-content">

    <!-- TOPBAR -->
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
                Dashboard
            </div>

        </div>

        <div class="profile-mini">

            <div class="profile-info">

                <div class="profile-name">
                    <?= e($nama_customer); ?>
                </div>

                <div class="profile-role">
                    Customer
                </div>

            </div>

            <div class="profile-avatar">
                <?= e(strtoupper(substr($nama_customer, 0, 1))); ?>
            </div>

        </div>

    </header>

    <!-- CONTENT -->
    <main class="content">

        <!-- WELCOME -->
            <div class="welcome-text">

                <div class="welcome-small">
                    WELCOME TO FLONUEL
                </div>

                <h1>
                    Halo, <?= e($nama_customer); ?>!
                </h1>

                <p>
                    Temukan bunga dan buket favoritmu untuk berbagai
                    momen spesial.
                </p>

                <a
                    href="../customer/katalog.php"
                    class="btn-custom"
                >
                    Lihat Katalog
                </a>

            </div>

        </section>

    
<!-- JUDUL BEST SELLER -->
<div class="section-title">
    <h2>
        <i class="" style="color:#d86673; margin-right:7px;"></i>
        Best Seller
    </h2>
</div>

        <section class="dashboard-bestseller-section">
            <div class="dashboard-bestseller-grid">

                <?php if (
                    $best_seller_result &&
                    mysqli_num_rows($best_seller_result) > 0
                ): ?>

                    <?php while (
                        $produk = mysqli_fetch_assoc($best_seller_result)
                    ): ?>

                        <?php
                        $foto = trim((string)($produk['Foto_produk'] ?? ''));

                        $fotoPath = '../assets/img/no-image.png';

                        if ($foto !== '') {
                            $lokasiUpload = '../uploads/produk/' . $foto;
                            $lokasiAssets = '../assets/img/produk/' . $foto;

                            if (file_exists($lokasiUpload)) {
                                $fotoPath = $lokasiUpload;
                            } elseif (file_exists($lokasiAssets)) {
                                $fotoPath = $lokasiAssets;
                            }
                        }
                        ?>

                        <article class="dashboard-bestseller-card">

                            <div class="dashboard-bestseller-badge">
                                Best Seller
                            </div>

                            <img
                                src="<?= e($fotoPath); ?>"
                                alt="<?= e($produk['Nama_produk']); ?>"
                                class="dashboard-bestseller-image"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='../assets/img/logo%20flonuel.png';"
                            >

                            <div class="dashboard-bestseller-info">

                                <h3 class="dashboard-bestseller-name">
                                    <?= e($produk['Nama_produk']); ?>
                                </h3>

                                <div class="dashboard-bestseller-price">
                                    Rp <?= number_format(
                                        (float)$produk['Harga'],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                </div>

                                <div class="dashboard-bestseller-sold">
                            
                                </div>

                                <a
                                    href="../customer/katalog.php"
                                    class="dashboard-bestseller-button"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                    Lihat Produk
                                </a>

                            </div>

                        </article>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="dashboard-bestseller-empty">
                        <i class="fa-solid fa-spa"></i>
                        <p>Belum ada produk Best Seller yang tersedia.</p>
                        <a href="../customer/katalog.php" class="btn-custom">
                            Lihat Katalog
                        </a>
                    </div>

                <?php endif; ?>

            </div>
        </section>


        <div class="section-title">

            <h2>
                Kategori Buket
            </h2>

            </a>

        </div>

        <div class="dashboard-category-section">

            <div class="dashboard-category-grid">

                <?php if (
                    $kategori_result &&
                    mysqli_num_rows($kategori_result) > 0
                ): ?>

                    <?php while (
                        $kategori = mysqli_fetch_assoc($kategori_result)
                    ): ?>

                        <a
                            href="../customer/katalog.php?kategori=<?= urlencode((string) $kategori['Kategori_id']); ?>"
                            class="dashboard-category-card"
                        >

                            <div class="dashboard-category-name">
                                <?= e($kategori['Nama_kategori']); ?>
                            </div>

                            <div class="dashboard-category-count">
                                <?= (int) $kategori['jumlah_produk']; ?>
                                produk tersedia
                            </div>

                            <div class="dashboard-category-link">
                                Lihat Kategori
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>

                        </a>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="dashboard-category-empty">

                        <i class="fa-solid fa-layer-group"></i>

                        <div>
                            Belum ada kategori bunga tersedia.
                        </div>

                        <a
                            href="../katalog.php"
                            class="btn-custom"
                            style="display:inline-flex; margin-top:15px;"
                        >
                            Buka Katalog
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<script>
const mobileMenu = document.getElementById('mobileMenu');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (mobileMenu && sidebar && sidebarOverlay) {
    mobileMenu.addEventListener('click', function () {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
    });
}

if (sidebarOverlay && sidebar) {
    sidebarOverlay.addEventListener('click', function () {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    });
}

/* Tutup sidebar ketika menu diklik di mobile */
const sidebarLinks = document.querySelectorAll('.sidebar-menu a');

sidebarLinks.forEach(function (link) {
    link.addEventListener('click', function () {
        if (window.innerWidth <= 991 && sidebar && sidebarOverlay) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
    });
});

/* Reset sidebar ketika kembali ke desktop */
window.addEventListener('resize', function () {
    if (window.innerWidth > 991 && sidebar && sidebarOverlay) {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    }
});
</script>

</body>
</html>
