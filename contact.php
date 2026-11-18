<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

require_once __DIR__ . "/includes/app_path.php";

// Token CSRF
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Identifikasi akun yang sedang login.
// Info ini dapat digunakan, semisal yang mengirimkan pertanyaan adalah Member,
// maka di formulirnya bisa otomatis mengambil email Member.
$accountId = null;
$defaultEmail = "";

if (isset($_SESSION["account_id"])) {

    $accountId = (int) $_SESSION["account_id"];

    $queryAccount = "
        SELECT account_email
        FROM accounts
        WHERE account_id = $1
    ";

    $resultAccount = pg_query_params(
        $conn,
        $queryAccount,
        [$accountId]
    );

    if (!$resultAccount) {
        error_log(pg_last_error($conn));
        exit("Gagal mengambil informasi akun.");
    }

    $account = pg_fetch_assoc($resultAccount);

    if ($account) {

        $defaultEmail = $account["account_email"];
    } else {

        // Sesi tidak valid atau sudah kedaluwarsa.
        $accountId = null;
    }
}

$email = $defaultEmail;
$message = "";

$errors = [];

$contactSuccess = $_SESSION["contact_success"] ?? false;

unset($_SESSION["contact_success"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verifikasi token CSRF.
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        $errors[] = "Permintaan tidak valid. Silakan muat ulang halaman.";
    }


    // Ambil nilai yang dikirim melalui formulir.
    $email = strtolower(
        trim((string) ($_POST["inquiry_email"] ?? ""))
    );

    $message = trim(
        (string) ($_POST["inquiry_message"] ?? "")
    );



    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors[] = "Format email tidak valid.";
    } elseif (strlen($email) > 254) {

        $errors[] = "Email terlalu panjang.";
    }

    if ($message === "") {

        $errors[] = "Pertanyaan wajib diisi.";
    } elseif (strlen($message) < 10) {

        $errors[] = "Pertanyaan minimal 10 karakter.";
    } elseif (strlen($message) > 5000) {

        $errors[] = "Pertanyaan maksimal 5000 karakter.";
    }

    if (empty($errors)) {

        $queryInsert = "
            INSERT INTO contact_inquiries (
                account_id,
                inquiry_email,
                inquiry_message
            )

            VALUES ($1, $2, $3)

            RETURNING inquiry_id
        ";

        $resultInsert = pg_query_params(
            $conn,
            $queryInsert,
            [
                $accountId,
                $email,
                $message
            ]
        );

        if (!$resultInsert) {

            error_log(pg_last_error($conn));

            $errors[] = "Gagal mengirim pertanyaan. Silakan coba kembali.";
        } else {


            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));


            $_SESSION["contact_success"] = true;

            header("Location: contact.php");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kontak - PT AJS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

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

    <main class="container py-5 page-content">

        <div class="text-center mb-5">

            <h1 class="fw-bold text-primary">
                Hubungi Kami
            </h1>

            <p class="text-muted">

                Memiliki pertanyaan mengenai program pelatihan PT AJS?
                Sampaikan pertanyaan Anda melalui formulir berikut.

            </p>

        </div>

        <div class="row g-4 justify-content-center">

            <!-- Informasi kontak -->

            <div class="col-lg-4">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body p-4">

                        <h4 class="mb-3">
                            Kontak Kami
                        </h4>

                        <p class="text-muted">

                            Sampaikan pertanyaan Anda mengenai layanan
                            dan program pelatihan PT AJS.

                        </p>

                        <hr>

                        <div class="mb-4">

                            <h6 class="text-primary">
                                Informasi Program
                            </h6>

                            <p class="small text-muted mb-0">

                                Pertanyaan mengenai topik, kategori,
                                manfaat, dan target peserta pelatihan.

                            </p>

                        </div>

                        <div class="mb-4">

                            <h6 class="text-primary">
                                Jadwal dan Pelaksanaan
                            </h6>

                            <p class="small text-muted mb-0">

                                Pertanyaan mengenai jadwal, metode
                                pelaksanaan, dan biaya pelatihan.

                            </p>

                        </div>

                        <div>

                            <h6 class="text-primary">
                                Pendaftaran dan Pembayaran
                            </h6>

                            <p class="small text-muted mb-0">

                                Pertanyaan mengenai proses pendaftaran
                                dan transaksi program pelatihan.

                            </p>

                        </div>

                    </div>

                </div>

            </div>

            <!-- FORMULIR KONTAK -->

            <div class="col-lg-7">

                <div class="card shadow-sm border-0">

                    <div class="card-body p-4">

                        <h4 class="mb-2">
                            Kirimkan Pertanyaan
                        </h4>

                        <p class="text-muted small">

                            Masukkan alamat email yang dapat digunakan
                            untuk menindaklanjuti pertanyaan Anda.

                        </p>

                        <?php if ($contactSuccess): ?>

                            <div class="alert alert-success">

                                Pertanyaan berhasil dikirim.
                                Terima kasih telah menghubungi PT AJS.

                            </div>

                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>

                            <div class="alert alert-danger">

                                <ul class="mb-0">

                                    <?php foreach ($errors as $error): ?>

                                        <li>

                                            <?= htmlspecialchars(
                                                $error,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>

                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                            </div>

                        <?php endif; ?>

                        <form method="POST" action="contact.php">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                            $_SESSION["csrf_token"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>">

                            <div class="mb-3">

                                <label
                                    for="inquiry_email"
                                    class="form-label">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    class="form-control"
                                    id="inquiry_email"
                                    name="inquiry_email"
                                    maxlength="254"
                                    value="<?= htmlspecialchars(
                                                $email,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                    required>

                                <?php if ($accountId !== null): ?>

                                    <div class="form-text">
                                        Email akun Anda telah diisi secara otomatis.
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="mb-3">

                                <label
                                    for="inquiry_message"
                                    class="form-label">
                                    Pertanyaan
                                </label>

                                <textarea
                                    class="form-control"
                                    id="inquiry_message"
                                    name="inquiry_message"
                                    rows="7"
                                    maxlength="5000"
                                    required><?= htmlspecialchars(
                                                    $message,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?></textarea>

                                <div class="form-text">
                                    Maksimal 5000 karakter.
                                </div>

                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary w-100">
                                Kirim Pertanyaan
                            </button>

                        </form>

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