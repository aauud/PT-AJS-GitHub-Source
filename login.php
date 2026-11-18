<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/app_path.php";

$username = "";
$error = "";

$next = $_POST["next"] ?? $_GET["next"] ?? "";

if (!is_string($next)) {
    $next = "";
}

function getSafeMemberDestination($destination)
{

    if (
        !is_string($destination) ||
        !preg_match(
            '#^member/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.php(?:\?[A-Za-z0-9_=&%-]*)?$#D',
            $destination
        )
    ) {
        return "member/index.php";
    }

    return $destination;
}

// Proses login

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim((string) ($_POST["username"] ?? ""));

    $password = (string) ($_POST["password"] ?? "");


    if ($username === "" || $password === "") {

        $error = "Username dan password wajib diisi.";
    } else {

        $query = "
            SELECT
                a.account_id,
                a.account_username,
                a.account_full_name,
                a.account_pass,
                a.role_id,
                r.role_name

            FROM accounts a

            JOIN roles r
                ON a.role_id = r.role_id

            WHERE a.account_username = $1
        ";

        $result = pg_query_params(
            $conn,
            $query,
            [$username]
        );


        if (!$result) {

            error_log(pg_last_error($conn));

            http_response_code(500);

            exit("Terjadi kesalahan saat mengakses database.");
        }

        $account = pg_fetch_assoc($result);

        if (
            $account &&
            password_verify($password, $account["account_pass"])
        ) {


            $_SESSION = [];


            if (!session_regenerate_id(true)) {
                exit("Gagal memperbarui sesi login.");
            }

            $_SESSION["account_id"] = $account["account_id"];

            $_SESSION["username"] = $account["account_username"];

            $_SESSION["full_name"] = $account["account_full_name"];

            $_SESSION["role_id"] = $account["role_id"];

            $_SESSION["role_name"] = $account["role_name"];



            if ($account["role_name"] === "Admin") {

                header("Location: " . app_url("admin.php"));
                exit;
            }



            $destination = getSafeMemberDestination($next);

            header("Location: " . app_url($destination));
            exit;
        } else {

            $error = "Username atau password tidak valid.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - PT AJS</title>


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

    <?php
    require_once __DIR__ . "/includes/navbar.php";
    ?>

    <main class="page-content">
        <div class="container mt-5 mb-5">

            <div class="row justify-content-center">

                <div class="col-md-6 col-lg-5">

                    <div class="card shadow-sm">

                        <div class="card-body p-4">

                            <h2 class="mb-3">
                                Login
                            </h2>

                            <p class="text-muted">
                                Masuk ke akun PT AJS Anda.
                            </p>


                            <?php if (
                                isset($_GET["registered"]) &&
                                $_GET["registered"] === "1"
                            ): ?>

                                <div class="alert alert-success">
                                    Registrasi berhasil. Silakan login.
                                </div>

                            <?php endif; ?>


                            <?php if ($error !== ""): ?>

                                <div class="alert alert-danger">

                                    <?= htmlspecialchars(
                                        $error,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </div>

                            <?php endif; ?>


                            <form method="POST" action="<?= htmlspecialchars(
                                                            app_url("login.php"),
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>">

                                <input
                                    type="hidden"
                                    name="next"
                                    value="<?= htmlspecialchars(
                                                $next,
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>">


                                <div class="mb-3">

                                    <label
                                        for="username"
                                        class="form-label">
                                        Username
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="username"
                                        name="username"
                                        value="<?= htmlspecialchars(
                                                    $username,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                        required>

                                </div>


                                <div class="mb-3">

                                    <label
                                        for="password"
                                        class="form-label">
                                        Password
                                    </label>

                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        required>

                                </div>


                                <button
                                    type="submit"
                                    class="btn btn-primary w-100">
                                    Login
                                </button>

                            </form>


                            <div class="text-center mt-3">

                                <p>

                                    Belum punya akun?

                                    <a href="<?= htmlspecialchars(
                                                    app_url("register.php"),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>">
                                        Daftar di sini
                                    </a>

                                </p>


                                <a href="<?= htmlspecialchars(
                                                app_url("catalog.php"),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>">
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