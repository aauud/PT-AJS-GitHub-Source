<?php

require_once __DIR__ . "/../../includes/app_path.php";


require_once __DIR__ . "/guard.php";

// 1. Validasi ID percobaan.
$attemptId = filter_var(
    $_GET["attempt_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($attemptId === false || $attemptId === null) {
    http_response_code(400);
    exit("ID percobaan tidak valid.");
}

// 2. Ambil percobaan milik Member yang sedang login.
$queryResult = "
    SELECT

        pa.attempt_id,
        pa.transaction_id,
        pa.attempt_number,
        pa.attempt_score,
        pa.attempt_is_passed,
        pa.attempt_submitted_at,

        p.posttest_title,
        p.posttest_passing_score,

        t.training_topic,
        t.training_code,

        (
            SELECT COUNT(*)
            FROM posttest_answers ans
            WHERE ans.attempt_id = pa.attempt_id
        ) AS total_answers,

        (
            SELECT COUNT(*)
            FROM posttest_answers ans
            WHERE ans.attempt_id = pa.attempt_id
            AND ans.answer_is_correct = TRUE
        ) AS correct_answers

    FROM posttest_attempts pa

    JOIN transactions tr
        ON pa.transaction_id = tr.transaction_id

    JOIN posttests p
        ON pa.posttest_id = p.posttest_id

    JOIN trainings t
        ON p.training_id = t.training_id

    WHERE pa.attempt_id = $1
    AND tr.account_id = $2
";

$result = pg_query_params(
    $conn,
    $queryResult,
    [
        $attemptId,
        $_SESSION["account_id"]
    ]
);

if (!$result) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil hasil posttest.");
}

$attempt = pg_fetch_assoc($result);

if (!$attempt) {
    http_response_code(404);
    exit("Hasil posttest tidak ditemukan atau Anda tidak memiliki akses.");
}

// 3. Tentukan status kelulusan.
$isPassed = ($attempt["attempt_is_passed"] === "t");

$certificateId = null;

if ($isPassed) {

    $resultCertificate = pg_query_params(
        $conn,
        "
        SELECT certificate_id
        FROM certificates
        WHERE transaction_id = $1
        AND attempt_id = $2
        ",
        [
            $attempt["transaction_id"],
            $attemptId
        ]
    );

    if (!$resultCertificate) {
        error_log(pg_last_error($conn));
        exit("Gagal memeriksa sertifikat.");
    }

    $certificate = pg_fetch_assoc($resultCertificate);

    if ($certificate) {
        $certificateId = $certificate["certificate_id"];
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hasil Posttest</title>

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

    <?php require_once __DIR__ . "/../../includes/navbar.php"; ?>

    <main class="page-content">



    <div class="container mt-5 mb-5">

        <h2>Hasil Posttest</h2>

        <p>
            <?= htmlspecialchars($attempt["training_topic"]) ?>
        </p>

        <div class="card">

            <div class="card-body">

                <h4>
                    <?= htmlspecialchars($attempt["posttest_title"]) ?>
                </h4>

                <hr>

                <p>

                    Nomor Percobaan:

                    <strong>
                        <?= (int) $attempt["attempt_number"] ?>
                    </strong>

                </p>

                <p>

                    Jumlah Jawaban Benar:

                    <strong>
                        <?= (int) $attempt["correct_answers"] ?>
                        /
                        <?= (int) $attempt["total_answers"] ?>
                    </strong>

                </p>

                <p>

                    Nilai:

                    <strong class="fs-3">

                        <?= number_format(
                            (float) $attempt["attempt_score"],
                            2,
                            ",",
                            "."
                        ) ?>

                    </strong>

                </p>

                <p>

                    Nilai Kelulusan:

                    <?= number_format(
                        (float) $attempt["posttest_passing_score"],
                        2,
                        ",",
                        "."
                    ) ?>

                </p>

                <p>

                    Waktu Pengerjaan:

                    <?= htmlspecialchars(
                        date(
                            "d/m/Y H:i:s",
                            strtotime($attempt["attempt_submitted_at"])
                        )
                    ) ?>

                </p>

                <hr>

                <?php if ($isPassed): ?>

                    <div class="alert alert-success">

                        <h4>Selamat! Anda Lulus!</h4>

                        Nilai Anda telah memenuhi persyaratan kelulusan.

                        <br><br>

                        <?php if ($certificateId): ?>

                            <a
                                href="../certificates/view.php?certificate_id=<?= (int) $certificateId ?>"
                                class="btn btn-success">
                                Lihat Sertifikat
                            </a>

                        <?php else: ?>

                            <a
                                href="../certificates/index.php"
                                class="btn btn-primary">
                                Periksa Sertifikat Saya
                            </a>

                        <?php endif; ?>

                    </div>

                <?php else: ?>

                    <div class="alert alert-danger">

                        <h4>Anda Belum Lulus</h4>

                        Nilai Anda belum mencapai nilai kelulusan.

                        <br>

                        Silakan pelajari kembali materi dan ulangi posttest
                        selama periode pengerjaan masih tersedia.

                    </div>

                <?php endif; ?>

                <a
                    href="index.php?transaction_id=<?= (int) $attempt["transaction_id"] ?>"
                    class="btn btn-primary">

                    Kembali ke Posttest

                </a>

            </div>

        </div>

    </div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>

</html>
