
<?php

require_once __DIR__ . "/guard.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

if (!verifyPosttestCsrf()) {
    http_response_code(403);
    exit("Permintaan tidak valid.");
}

$posttestId = filter_var(
    $_POST["posttest_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($posttestId === false || $posttestId === null) {
    http_response_code(400);
    exit("ID posttest tidak valid.");
}

$posttest = getPosttest($conn, $posttestId);

if (!$posttest) {
    http_response_code(404);
    exit("Posttest tidak ditemukan.");
}

$currentStatus = ($posttest["posttest_is_active"] === "t");

$newStatus = !$currentStatus;

// Validasi pertanyaan hanya saat posttest diaktifkan.
if ($newStatus) {

    $queryValidation = "
        SELECT
            COUNT(*) AS total_questions,

            COUNT(*) FILTER (
                WHERE option_count = 4
                AND correct_count = 1
            ) AS valid_questions

        FROM (
            SELECT
                q.question_id,

                COUNT(o.option_id) AS option_count,

                COUNT(*) FILTER (
                    WHERE o.option_is_correct = TRUE
                ) AS correct_count

            FROM posttest_questions q

            LEFT JOIN posttest_options o
                ON q.question_id = o.question_id

            WHERE q.posttest_id = $1

            GROUP BY q.question_id
        ) AS question_validation
    ";

    $resultValidation = pg_query_params(
        $conn,
        $queryValidation,
        [$posttestId]
    );

    if (!$resultValidation) {
        error_log(pg_last_error($conn));
        exit("Gagal memeriksa kelengkapan pertanyaan.");
    }

    $validation = pg_fetch_assoc($resultValidation);

    $totalQuestions = (int) $validation["total_questions"];
    $validQuestions = (int) $validation["valid_questions"];

    if (
        $totalQuestions === 0 ||
        $totalQuestions !== $validQuestions
    ) {

        header(
            "Location: index.php?training_id="
                . $posttest["training_id"]
                . "&invalid_questions=1"
        );

        exit;
    }
}

// Perbarui status aktif.
$resultUpdate = pg_query_params(
    $conn,
    "
    UPDATE posttests
    SET posttest_is_active = $1
    WHERE posttest_id = $2
    ",
    [
        $newStatus ? "true" : "false",
        $posttestId
    ]
);

if (!$resultUpdate) {
    error_log(pg_last_error($conn));
    exit("Gagal memperbarui status posttest.");
}

header(
    "Location: index.php?training_id="
        . $posttest["training_id"]
        . "&toggled=1"
);

exit;

?>
