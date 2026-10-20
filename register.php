<?php
session_start();
require_once 'config/koneksi.php';

if (isset($_SESSION['user_id'])) {

    if (($_SESSION['role'] ?? '') === 'admin') {

        header('Location: admin/dashboard.php');

    } else {

        header('Location: customer/dashboard.php');

    }

    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama'] ?? '');

    $username = trim($_POST['username'] ?? '');

    $email = trim($_POST['email'] ?? '');

    $no_hp = trim($_POST['no_hp'] ?? '');

    $password = $_POST['password'] ?? '';

    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if (
        $nama === '' ||
        $username === '' ||
        $email === '' ||
        $no_hp === '' ||
        $password === '' ||
        $konfirmasi === ''
    ) {

        $error = 'Semua data wajib diisi.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Format email tidak valid.';

    } elseif (strlen($password) < 6) {

        $error = 'Password minimal 6 karakter.';

    } elseif ($password !== $konfirmasi) {

        $error = 'Konfirmasi password tidak sama.';

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT User_id
             FROM `user`
             WHERE Username = ? OR Email = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $username,
            $email
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $exists = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);


        if ($exists) {

            $error = 'Username atau email sudah digunakan.';

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $role = 'customer';


            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO `user`
                (
                    Nama,
                    No_Hp,
                    Email,
                    Username,
                    Password,
                    Role
                )
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssssss",
                $nama,
                $no_hp,
                $email,
                $username,
                $hashed_password,
                $role
            );


            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                header(
                    'Location: login.php?registered=1'
                );

                exit;

            } else {

                $error = 'Registrasi gagal. Silakan coba lagi.';

                mysqli_stmt_close($stmt);
            }
        }
    }
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

    <title>Buat Akun | FlowerShop</title>

    <!-- CSS -->
    <link
        rel="stylesheet"
        href="assets/css/auth.css"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body>

<div class="auth-page register-page">

    <div class="auth-card register-card">

        <!-- ICON -->
        <div class="auth-icon">

            <i class="fa-regular fa-user"></i>

        </div>


        <!-- TITLE -->
        <h1 class="register-title">
            Buat akun
        </h1>

        <p class="subtitle">
            Lengkapi data berikut untuk membuat akun
        </p>


        <!-- ERROR -->
        <?php if ($error): ?>

            <div class="message error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- FORM -->
        <form method="POST">

            <!-- NAMA -->
            <label>
                Nama Lengkap
            </label>

            <div class="input-box">

                <i class="fa-regular fa-user"></i>

                <input
                    type="text"
                    name="nama"
                    placeholder="Masukkan nama lengkap"
                    value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                    required
                >

            </div>


            <!-- USERNAME -->
            <label>
                Username
            </label>

            <div class="input-box">

                <i class="fa-solid fa-at"></i>

                <input
                    type="text"
                    name="username"
                    placeholder="Username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                >

            </div>


            <!-- EMAIL -->
            <label>
                Email
            </label>

            <div class="input-box">

                <i class="fa-regular fa-envelope"></i>

                <input
                    type="email"
                    name="email"
                    placeholder="nama@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >

            </div>


            <!-- NO HP -->
            <label>
                Nomor HP
            </label>

            <div class="input-box">

                <i class="fa-solid fa-phone"></i>

                <input
                    type="text"
                    name="no_hp"
                    placeholder="08xxxxxxxxxx"
                    value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>"
                    required
                >

            </div>


            <!-- PASSWORD -->
            <label>
                Password
            </label>

            <div class="input-box">

                <i class="fa-solid fa-lock"></i>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan password"
                    required
                >

                <button
                    type="button"
                    class="eye"
                    onclick="togglePassword(
                        'password',
                        'eyeIcon1'
                    )"
                >

                    <i
                        id="eyeIcon1"
                        class="fa-regular fa-eye"
                    ></i>

                </button>

            </div>


            <!-- KONFIRMASI PASSWORD -->
            <label>
                Konfirmasi Password
            </label>

            <div class="input-box">

                <i class="fa-solid fa-lock"></i>

                <input
                    type="password"
                    id="konfirmasi_password"
                    name="konfirmasi_password"
                    placeholder="Ulangi password"
                    required
                >

                <button
                    type="button"
                    class="eye"
                    onclick="togglePassword(
                        'konfirmasi_password',
                        'eyeIcon2'
                    )"
                >

                    <i
                        id="eyeIcon2"
                        class="fa-regular fa-eye"
                    ></i>

                </button>

            </div>


            <!-- BUTTON -->
            <button
                type="submit"
                class="auth-button"
            >

                <i class="fa-solid fa-right-to-bracket"></i>

                Daftar Akun

            </button>

        </form>


        <!-- LOGIN -->
        <p class="switch-text">

            Sudah memiliki akun?

            <a href="login.php">
                Login Sekarang
            </a>

        </p>


        <!-- HOME -->
        <a
            href="index.php"
            class="back-home"
        >
            kembali ke Beranda
        </a>

    </div>

</div>


<script>

function togglePassword(inputId, iconId) {

    const input =
        document.getElementById(inputId);

    const icon =
        document.getElementById(iconId);


    if (input.type === 'password') {

        input.type = 'text';

        icon.className =
            'fa-regular fa-eye-slash';

    } else {

        input.type = 'password';

        icon.className =
            'fa-regular fa-eye';

    }

}

</script>

</body>

</html>