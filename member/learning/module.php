<?php

require_once __DIR__ . "/../../includes/app_path.php";

require_once __DIR__ . "/guard.php";

// 1. Validasi ID.
$transactionId = filter_var(
    $_GET["transaction_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$moduleId = filter_var(
    $_GET["module_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (
    $transactionId === false || $transactionId === null ||
    $moduleId === false || $moduleId === null
) {
    http_response_code(400);
    exit("ID transaksi atau modul tidak valid.");
}

// 2. Verifikasi kepemilikan dan kelayakan Member.
$training = getOwnedAsyncTransaction(
    $conn,
    $transactionId,
    $_SESSION["account_id"]
);

if (!$training) {
    http_response_code(404);
    exit("Data pembelajaran tidak ditemukan atau Anda tidak memiliki akses.");
}

// 3. Ambil modul yang dipilih beserta status penyelesaiannya.
$queryModule = "
    SELECT
        tm.module_id,
        tm.module_order,
        tm.module_title,
        tm.module_description,
        tm.module_content,
        tm.module_material_url,

        mp.module_completed_at

    FROM training_modules tm

    LEFT JOIN module_progress mp
        ON tm.module_id = mp.module_id
        AND mp.transaction_id = $3

    WHERE tm.module_id = $1
    AND tm.training_id = $2
";

$resultModule = pg_query_params(
    $conn,
    $queryModule,
    [
        $moduleId,
        $training["training_id"],
        $transactionId
    ]
);

if (!$resultModule) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data modul.");
}

$module = pg_fetch_assoc($resultModule);

if (!$module) {
    http_response_code(404);
    exit("Modul tidak ditemukan.");
}

// 4. Siapkan token CSRF.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$errors = [];

// 5. Proses pengiriman status penyelesaian.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {

        $errors[] = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } elseif (
        ($_POST["action"] ?? "") !== "complete"
    ) {

        $errors[] = "Aksi tidak valid.";
    } else {

        // Simpan catatan penyelesaian modul.
        //
        // Jika Member sudah menyelesaikan modul ini,
        // waktu penyelesaian sebelumnya tetap dipertahankan.

        $queryComplete = "
            INSERT INTO module_progress (
                transaction_id,
                module_id,
                module_completed_at
            )

            VALUES ($1, $2, CURRENT_TIMESTAMP)

            ON CONFLICT (transaction_id, module_id)

            DO UPDATE SET
                module_completed_at = COALESCE(
                    module_progress.module_completed_at,
                    CURRENT_TIMESTAMP
                )
        ";

        $resultComplete = pg_query_params(
            $conn,
            $queryComplete,
            [
                $transactionId,
                $moduleId
            ]
        );

        if (!$resultComplete) {

            error_log(pg_last_error($conn));

            $errors[] = "Gagal menyimpan progres pembelajaran.";
        } else {

            // Alihkan halaman setelah pengiriman berhasil.
            header(
                "Location: module.php?transaction_id="
                    . $transactionId
                    . "&module_id="
                    . $moduleId
                    . "&completed=1"
            );

            exit;
        }
    }
}

// 6. Tentukan status penyelesaian saat ini.
$isCompleted = !empty($module["module_completed_at"]);

$completedMessage = (
    isset($_GET["completed"]) &&
    $_GET["completed"] === "1"
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Materi Pembelajaran</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
    ">

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
    require_once __DIR__ . "/../../includes/navbar.php";
    ?>

    <main class="page-content">


        <div class="container mt-5 mb-5">

            <p class="text-muted">

                <?= htmlspecialchars($training["training_topic"]) ?>

            </p>

            <a
                href="detail.php?transaction_id=<?= (int) $transactionId ?>"
                class="btn btn-secondary mb-3">

                Kembali ke Daftar Modul

            </a>

            <?php if ($completedMessage): ?>

                <div class="alert alert-success">

                    Modul berhasil ditandai sebagai selesai!

                </div>

            <?php endif; ?>

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

            <div class="card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                        <h3 class="card-title">

                            <?= (int) $module["module_order"] ?>.

                            <?= htmlspecialchars($module["module_title"]) ?>

                        </h3>

                        <?php if ($isCompleted): ?>

                            <span class="badge bg-success">
                                Selesai
                            </span>

                        <?php else: ?>

                            <span class="badge bg-secondary">
                                Belum selesai
                            </span>

                        <?php endif; ?>

                    </div>

                    <?php if (!empty($module["module_description"])): ?>

                        <p class="text-muted">

                            <?= htmlspecialchars($module["module_description"]) ?>

                        </p>

                    <?php endif; ?>

                    <hr>

                    <div class="mb-4">

                        <?php if (!empty($module["module_content"])): ?>

                            <?= nl2br(
                                htmlspecialchars($module["module_content"])
                            ) ?>

                        <?php else: ?>

                            <p class="text-muted">
                                Tidak ada konten teks untuk modul ini.
                            </p>

                        <?php endif; ?>

                    </div>

                    <?php if (!empty($module["module_material_url"])): ?>

                        <div class="mb-4">

                            <h5>Materi Tambahan</h5>

                            <a
                                href="<?= htmlspecialchars($module["module_material_url"]) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-outline-primary">

                                Buka Materi

                            </a>

                        </div>

                    <?php endif; ?>

                    <hr>

                    <?php if ($isCompleted): ?>

                        <div class="alert alert-success mb-0">

                            Anda telah menyelesaikan modul ini.

                            <br>

                            Waktu penyelesaian:

                            <?= htmlspecialchars($module["module_completed_at"]) ?>

                        </div>

                    <?php else: ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="complete">

                            <button
                                type="submit"
                                class="btn btn-success">

                                Tandai Sebagai Selesai

                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>