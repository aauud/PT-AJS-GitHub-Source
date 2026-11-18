<?php

require_once __DIR__ . "/../../includes/app_path.php";
require_once __DIR__ . "/guard.php";

// 1. Validasi ID transaksi.

$transactionId = filter_var(
    $_GET["transaction_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($transactionId === false || $transactionId === null) {
    http_response_code(400);
    exit("ID transaksi tidak valid.");
}

// 2. Verifikasi kepemilikan dan kelayakan.

$training = getOwnedAsyncTransaction(
    $conn,
    $transactionId,
    $_SESSION["account_id"]
);

if (!$training) {
    http_response_code(404);
    exit("Data pembelajaran tidak ditemukan atau Anda tidak memiliki akses.");
}

// 3. Ambil modul pelatihan.

$queryModules = "
    SELECT
        tm.module_id,
        tm.module_order,
        tm.module_title,
        tm.module_description,

        mp.module_completed_at

    FROM training_modules tm

    LEFT JOIN module_progress mp
        ON tm.module_id = mp.module_id
        AND mp.transaction_id = $1

    WHERE tm.training_id = $2

    ORDER BY tm.module_order ASC
";

$resultModules = pg_query_params(
    $conn,
    $queryModules,
    [
        $transactionId,
        $training["training_id"]
    ]
);

if (!$resultModules) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil daftar modul.");
}

// 4. Hitung progres pembelajaran.

$modules = [];
$completedModules = 0;

while ($row = pg_fetch_assoc($resultModules)) {

    $modules[] = $row;

    if (!empty($row["module_completed_at"])) {
        $completedModules++;
    }
}

$totalModules = count($modules);

$percentage = $totalModules > 0
    ? round(($completedModules / $totalModules) * 100)
    : 0;

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar Modul</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            app_url("assets/css/ajs-theme.css"),
            ENT_QUOTES,
            "UTF-8"
        ) ?>"
    >
</head>

<body>

    <?php
    require_once __DIR__ . "/../../includes/navbar.php";
    ?>

    <main class="page-content">


    <div class="container mt-5">

        <h2>
            <?= htmlspecialchars($training["training_topic"]) ?>
        </h2>

        <p>
            Kode:
            <?= htmlspecialchars($training["training_code"]) ?>
        </p>

        <a
            href="index.php"
            class="btn btn-secondary mb-3">
            Kembali ke My Learning
        </a>

        <div class="card mb-4">

            <div class="card-body">

                <h5>Progres Pembelajaran</h5>

                <p>

                    <?= $completedModules ?>
                    dari
                    <?= $totalModules ?>
                    modul selesai.

                </p>

                <div
                    class="progress"
                    role="progressbar"
                    aria-label="Learning progress"
                    aria-valuenow="<?= $percentage ?>"
                    aria-valuemin="0"
                    aria-valuemax="100">

                    <div
                        class="progress-bar"
                        style="width: <?= $percentage ?>%;">

                        <?= $percentage ?>%

                    </div>

                </div>

            </div>

        </div>

        <h4>Daftar Modul</h4>

        <?php if ($totalModules > 0): ?>

            <div class="list-group">

                <?php foreach ($modules as $row): ?>

                    <div class="list-group-item mb-2">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                            <div>

                                <h5 class="mb-1">

                                    <?= (int) $row["module_order"] ?>.

                                    <?= htmlspecialchars(
                                        $row["module_title"]
                                    ) ?>

                                </h5>

                                <p class="mb-1 text-muted">

                                    <?= htmlspecialchars(
                                        $row["module_description"] ?? ""
                                    ) ?>

                                </p>

                            </div>

                            <div>

                                <?php if (!empty($row["module_completed_at"])): ?>

                                    <span class="badge bg-success">
                                        Selesai
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        Belum selesai
                                    </span>

                                <?php endif; ?>

                                <a
                                    href="module.php?transaction_id=<?= (int) $transactionId ?>&module_id=<?= (int) $row["module_id"] ?>"
                                    class="btn btn-sm btn-primary ms-2">

                                    Buka Modul

                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <hr class="my-4">

            <div class="card shadow-sm">

                <div class="card-body">

                    <h5>
                        Posttest Pelatihan
                    </h5>


                    <?php if ($totalModules === 0): ?>

                        <p class="text-muted mb-0">

                            Posttest belum dapat diakses karena
                            belum tersedia modul pembelajaran.

                        </p>


                    <?php elseif ($completedModules < $totalModules): ?>

                        <p class="text-muted mb-0">

                            Selesaikan seluruh modul pembelajaran
                            terlebih dahulu sebelum mengerjakan posttest.

                        </p>


                    <?php else: ?>

                        <p class="text-success">

                            Seluruh modul pembelajaran telah diselesaikan.
                            Anda dapat memeriksa ketersediaan posttest.

                        </p>

                        <a
                            href="<?= htmlspecialchars(
                                        app_url("member/posttests/index.php")
                                            . "?transaction_id="
                                            . (int) $transactionId,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>"
                            class="btn btn-primary">
                            Buka Posttest
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        <?php else: ?>

            <div class="alert alert-info">

                Belum ada modul pembelajaran untuk program ini.

            </div>

        <?php endif; ?>

    </div>

    <br>

        </main>

    <?php
    require_once __DIR__ . "/../../includes/footer.php";
    ?>

</body>

</html>