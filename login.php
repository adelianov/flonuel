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
$success = '';

if (isset($_GET['registered'])) {
    $success = 'Registrasi berhasil, silakan login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Username/email dan password wajib diisi.';

    } else {

        /*
        | Cari berdasarkan Username atau Email
        */
        $stmt = mysqli_prepare(
            $conn,
            "SELECT User_id, Nama, Username, Password, Role
             FROM `user`
             WHERE Username = ? OR Email = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $username,
            $username
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $user = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        /*
        | Cek password
        */
        if ($user && password_verify($password, $user['Password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['User_id'];
            $_SESSION['nama']     = $user['Nama'];
            $_SESSION['username'] = $user['Username'];
            $_SESSION['role']     = $user['Role'];

            /*
            | Redirect berdasarkan role
            */
            if ($user['Role'] === 'admin') {

                header('Location: admin/dashboard.php');

            } else {

                header('Location: customer/dashboard.php');

            }

            exit;

        } else {

            $error = 'Username/email atau password salah.';
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

    <title>Login | FlowerShop</title>

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

<div class="auth-page">

    <div class="auth-card">

        <!-- ICON -->
        <div class="auth-icon">
            <i class="fa-regular fa-user"></i>
        </div>

        <!-- JUDUL -->
        <h1>Log In</h1>

        <p class="subtitle">
            Masuk menggunakan username atau email anda
        </p>


        <!-- ERROR -->
        <?php if ($error): ?>

            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <!-- SUCCESS -->
        <?php if ($success): ?>

            <div class="message success">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <!-- FORM -->
        <form method="POST">

            <!-- USERNAME -->
            <label>
                Username / Email
            </label>

            <div class="input-box">

                <i class="fa-regular fa-user"></i>

                <input
                    type="text"
                    name="username"
                    placeholder="Masukkan Username atau email"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
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
                    onclick="togglePassword()"
                >
                    <i
                        id="eyeIcon"
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

                Login

            </button>

        </form>


        <!-- REGISTER -->
        <p class="switch-text">

            Belum memiliki akun?

            <a href="register.php">
                Daftar Sekarang
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

function togglePassword() {

    const input = document.getElementById('password');

    const icon = document.getElementById('eyeIcon');

    if (input.type === 'password') {

        input.type = 'text';

        icon.className = 'fa-regular fa-eye-slash';

    } else {

        input.type = 'password';

        icon.className = 'fa-regular fa-eye';

    }

}

</script>

</body>

</html>