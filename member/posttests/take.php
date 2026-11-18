
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

// 2. Ambil dan verifikasi transaksi milik Member.
$context = getMemberPosttestContext(
    $conn,
    $transactionId,
    $_SESSION["account_id"]
);

if (!$context) {
    http_response_code(404);
    exit("Pendaftaran tidak ditemukan atau Anda tidak memiliki akses.");
}

// 3. Periksa kelayakan.
$eligibility = checkPosttestEligibility($context);

if (!$eligibility["eligible"]) {

    header(
        "Location: index.php?transaction_id="
        . $transactionId
        . "&blocked=1"
    );

    exit;
}

// 4. Periksa apakah Member sudah lulus.
$hasPassed = hasPassedPosttest(
    $conn,
    $transactionId,
    $context["posttest_id"]
);

if ($hasPassed === null) {
    exit("Gagal memeriksa riwayat posttest.");
}

if ($hasPassed) {
    header(
        "Location: index.php?transaction_id="
        . $transactionId
    );

    exit;
}

// 5. Ambil pertanyaan beserta pilihan jawabannya.
// Kolom option_is_correct tidak diambil agar kunci jawaban tidak terlihat oleh Member.
$queryQuestions = "
    SELECT
        q.question_id,
        q.question_order,
        q.question_text,

        o.option_id,
        o.option_label,
        o.option_text

    FROM posttest_questions q

    LEFT JOIN posttest_options o
        ON q.question_id = o.question_id

    WHERE q.posttest_id = $1

    ORDER BY
        q.question_order ASC,
        o.option_label ASC
";

$resultQuestions = pg_query_params(
    $conn,
    $queryQuestions,
    [$context["posttest_id"]]
);

if (!$resultQuestions) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil pertanyaan.");
}

// 6. Kelompokkan pertanyaan dan pilihan jawabannya.
$questions = [];

while ($row = pg_fetch_assoc($resultQuestions)) {

    $questionId = $row["question_id"];

    if (!isset($questions[$questionId])) {

        $questions[$questionId] = [
            "question_id" => $questionId,
            "question_order" => $row["question_order"],
            "question_text" => $row["question_text"],
            "options" => []
        ];
    }

    if ($row["option_id"] !== null) {

        $questions[$questionId]["options"][] = [
            "option_id" => $row["option_id"],
            "option_label" => $row["option_label"],
            "option_text" => $row["option_text"]
        ];
    }
}

if (count($questions) === 0) {
    http_response_code(400);
    exit("Posttest belum memiliki pertanyaan.");
}

// 7. Validasi struktur ujian sebelum ditampilkan.
foreach ($questions as $question) {

    if (count($question["options"]) !== 4) {

        http_response_code(400);

        exit(
            "Posttest belum dapat dikerjakan karena terdapat pertanyaan "
            . "yang belum memiliki empat pilihan jawaban."
        );
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kerjakan Posttest</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


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

    <h2>
        <?= htmlspecialchars($context["posttest_title"]) ?>
    </h2>

    <h5>
        <?= htmlspecialchars($context["training_topic"]) ?>
    </h5>

    <p class="text-muted">

        Nilai kelulusan:

        <?= htmlspecialchars($context["posttest_passing_score"]) ?>%

    </p>

    <a
        href="index.php?transaction_id=<?= (int) $transactionId ?>"
        class="btn btn-secondary mb-3"
    >
        Kembali
    </a>

    <div class="alert alert-info">

        Jawab seluruh pertanyaan berikut.

        Pastikan semua jawaban sudah sesuai sebelum mengirimkan
        hasil pengerjaan.

        <br>

        Jumlah pertanyaan: <?= count($questions) ?>

    </div>

    <form
        method="POST"
        action="submit.php"
        onsubmit="return confirm('Apakah Anda yakin ingin mengirimkan jawaban?');"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
        >

        <input
            type="hidden"
            name="transaction_id"
            value="<?= (int) $transactionId ?>"
        >

        <?php foreach ($questions as $question): ?>

            <div class="card mb-4">

                <div class="card-body">

                    <h5>

                        <?= (int) $question["question_order"] ?>.

                        <?= htmlspecialchars($question["question_text"]) ?>

                    </h5>

                    <hr>

                    <?php foreach ($question["options"] as $option): ?>

                        <div class="form-check mb-2">

                            <input
                                class="form-check-input"
                                type="radio"
                                name="answers[<?= (int) $question["question_id"] ?>]"
                                id="option_<?= (int) $option["option_id"] ?>"
                                value="<?= (int) $option["option_id"] ?>"
                                required
                            >

                            <label
                                class="form-check-label"
                                for="option_<?= (int) $option["option_id"] ?>"
                            >

                                <?= htmlspecialchars($option["option_label"]) ?>.

                                <?= htmlspecialchars($option["option_text"]) ?>

                            </label>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endforeach; ?>

        <button
            type="submit"
            class="btn btn-success btn-lg"
        >

            Kirim Jawaban

        </button>

    </form>

</div>

    </main>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</body>
</html>
