<?php

session_start();

require_once __DIR__ . "/config/database.php";

require_once __DIR__ . "/includes/app_path.php";

// 1. CSRF
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// 2. Siapkan nilai awal formulir.
$fullName = "";
$username = "";
$email = "";
$phone = "";

$errors = [];

// 3. Proses pendaftaran akun.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verifikasi token CSRF.
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        $errors[] = "Permintaan tidak valid. Silakan muat ulang halaman.";
    }

    // Ambil nilai dari formulir.
    $fullName = trim((string) ($_POST["account_full_name"] ?? ""));
    $username = trim((string) ($_POST["account_username"] ?? ""));
    $email = strtolower(trim((string) ($_POST["account_email"] ?? "")));
    $phone = trim((string) ($_POST["account_phone"] ?? ""));

    $password = (string) ($_POST["account_pass"] ?? "");
    $confirmPassword = (string) ($_POST["confirm_password"] ?? "");

    // 4. Validasi nama lengkap.
    if ($fullName === "") {
        $errors[] = "Nama lengkap wajib diisi.";
    } elseif (strlen($fullName) > 150) {
        $errors[] = "Nama lengkap maksimal 150 karakter.";
    }

    // 5. Validasi nama pengguna.
    if ($username === "") {
        $errors[] = "Username wajib diisi.";
    } elseif (strlen($username) > 50) {
        $errors[] = "Username maksimal 50 karakter.";
    }

    // 6. Validasi alamat email.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }

    // 7. Validasi nomor telepon.
    if (strlen($phone) > 30) {
        $errors[] = "Nomor telepon terlalu panjang.";
    }

    // 8. Validasi kata sandi.
    if (strlen($password) < 8) {
        $errors[] = "Password minimal 8 karakter.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Konfirmasi password tidak sesuai.";
    }

    // 9. Simpan akun jika seluruh validasi berhasil.
    if (empty($errors)) {

        // Ambil ID peran Member.
        $queryRole = "
            SELECT role_id
            FROM roles
            WHERE role_name = 'Member'
        ";

        $resultRole = pg_query($conn, $queryRole);

        if (!$resultRole) {

            error_log(pg_last_error($conn));

            $errors[] = "Gagal memeriksa role Member.";
        } else {

            $memberRole = pg_fetch_assoc($resultRole);

            if (!$memberRole) {

                $errors[] = "Role Member belum tersedia.";
            } else {

                // Hash kata sandi sebelum menyimpannya.
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Ubah nomor telepon kosong menjadi NULL.
                $phoneValue = $phone !== "" ? $phone : null;

                $queryInsert = "
                    INSERT INTO accounts (
                        account_full_name,
                        account_username,
                        account_email,
                        account_phone,
                        account_pass,
                        role_id
                    )

                    VALUES ($1, $2, $3, $4, $5, $6)

                    ON CONFLICT DO NOTHING

                    RETURNING account_id
                ";

                $resultInsert = pg_query_params(
                    $conn,
                    $queryInsert,
                    [
                        $fullName,
                        $username,
                        $email,
                        $phoneValue,
                        $passwordHash,
                        $memberRole["role_id"]
                    ]
                );

                if (!$resultInsert) {

                    error_log(pg_last_error($conn));

                    $errors[] = "Gagal membuat akun. Silakan coba kembali.";
                } else {

                    $newAccount = pg_fetch_assoc($resultInsert);

                    if ($newAccount) {

                        // Akun berhasil dibuat.
                        header("Location: login.php?registered=1");
                        exit;
                    } else {

                        $errors[] = "Username atau email sudah digunakan.";
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar Akun Member</title>

    <!-- CSS Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Tema PT AJS -->

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
                    app_url("assets/css/ajs-theme.css"),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>">
</head>

<body>

    <?php require_once __DIR__ . "/includes/navbar.php"; ?>

    <main class="page-content">




        <div class="container mt-5 mb-5">

            <div class="row justify-content-center">

                <div class="col-md-7 col-lg-6">

                    <div class="card">

                        <div class="card-body p-4">

                            <h2 class="mb-3">
                                Daftar Akun Member
                            </h2>

                            <p class="text-muted">
                                Buat akun untuk mengakses program pelatihan PT AJS.
                            </p>

                            <?php if (!empty($errors)): ?>

                                <div class="alert alert-danger">

                                    <ul class="mb-0">

                                        <?php foreach ($errors as $error): ?>

                                            <li>
                                                <?= htmlspecialchars($error) ?>
                                            </li>

                                        <?php endforeach; ?>

                                    </ul>

                                </div>

                            <?php endif; ?>

                            <form method="POST" action="register.php">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                                <div class="mb-3">

                                    <label
                                        for="account_full_name"
                                        class="form-label">
                                        Nama Lengkap
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="account_full_name"
                                        name="account_full_name"
                                        maxlength="150"
                                        value="<?= htmlspecialchars($fullName) ?>"
                                        required>

                                </div>

                                <div class="mb-3">

                                    <label
                                        for="account_username"
                                        class="form-label">
                                        Username
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="account_username"
                                        name="account_username"
                                        maxlength="50"
                                        value="<?= htmlspecialchars($username) ?>"
                                        required>

                                </div>

                                <div class="mb-3">

                                    <label
                                        for="account_email"
                                        class="form-label">
                                        Email
                                    </label>

                                    <input
                                        type="email"
                                        class="form-control"
                                        id="account_email"
                                        name="account_email"
                                        maxlength="254"
                                        value="<?= htmlspecialchars($email) ?>"
                                        required>

                                </div>

                                <div class="mb-3">

                                    <label
                                        for="account_phone"
                                        class="form-label">
                                        Nomor Telepon (Opsional)
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="account_phone"
                                        name="account_phone"
                                        maxlength="30"
                                        value="<?= htmlspecialchars($phone) ?>">

                                </div>

                                <div class="mb-3">

                                    <label
                                        for="account_pass"
                                        class="form-label">
                                        Password
                                    </label>

                                    <input
                                        type="password"
                                        class="form-control"
                                        id="account_pass"
                                        name="account_pass"
                                        minlength="8"
                                        required>

                                </div>

                                <div class="mb-3">

                                    <label
                                        for="confirm_password"
                                        class="form-label">
                                        Konfirmasi Password
                                    </label>

                                    <input
                                        type="password"
                                        class="form-control"
                                        id="confirm_password"
                                        name="confirm_password"
                                        minlength="8"
                                        required>

                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-primary w-100">
                                    Daftar
                                </button>

                            </form>

                            <div class="text-center mt-3">

                                <p>
                                    Sudah punya akun?

                                    <a href="login.php">
                                        Login di sini
                                    </a>
                                </p>

                                <a href="catalog.php">
                                    Kembali ke Katalog
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/includes/footer.php";
    ?>

</body>

</html>