
<?php

require_once __DIR__ . "/guard.php";

// Fungsi bantu untuk membatalkan transaksi basis data dan menghentikan proses.
function failPosttestSubmission($conn, $message, $statusCode = 400)
{

    pg_query($conn, "ROLLBACK");

    http_response_code($statusCode);

    exit(htmlspecialchars($message));
}

// 1. Terima permintaan hanya melalui metode POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

// 2. Verifikasi token CSRF.
if (!verifyMemberPosttestCsrf()) {
    http_response_code(403);
    exit("Permintaan tidak valid.");
}

// 3. Validasi ID transaksi.
$transactionId = filter_var(
    $_POST["transaction_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($transactionId === false || $transactionId === null) {
    http_response_code(400);
    exit("ID transaksi tidak valid.");
}

// 4. Validasi jawaban yang dikirim.
$answers = $_POST["answers"] ?? null;

if (!is_array($answers)) {
    http_response_code(400);
    exit("Jawaban tidak valid.");
}

// 5. Mulai transaksi basis data.
if (!pg_query($conn, "BEGIN")) {
    error_log(pg_last_error($conn));
    exit("Gagal memulai proses pemeriksaan.");
}

// 6. Kunci baris transaksi.
// Penguncian ini mencegah pengiriman bersamaan menghasilkan
// nomor percobaan ganda untuk pendaftaran yang sama.
$queryLock = "
    SELECT transaction_id
    FROM transactions
    WHERE transaction_id = $1
    AND account_id = $2
    FOR UPDATE
";

$resultLock = pg_query_params(
    $conn,
    $queryLock,
    [
        $transactionId,
        $_SESSION["account_id"]
    ]
);

if (!$resultLock) {

    error_log(pg_last_error($conn));

    failPosttestSubmission(
        $conn,
        "Gagal memeriksa pendaftaran.",
        500
    );
}

$lockedTransaction = pg_fetch_assoc($resultLock);

if (!$lockedTransaction) {

    failPosttestSubmission(
        $conn,
        "Pendaftaran tidak ditemukan atau Anda tidak memiliki akses.",
        404
    );
}

// 7. Periksa kembali kelayakan berdasarkan data terbaru di basis data.
$context = getMemberPosttestContext(
    $conn,
    $transactionId,
    $_SESSION["account_id"]
);

if (!$context) {

    failPosttestSubmission(
        $conn,
        "Gagal memeriksa informasi posttest.",
        500
    );
}

$eligibility = checkPosttestEligibility($context);

if (!$eligibility["eligible"]) {

    pg_query($conn, "ROLLBACK");

    header(
        "Location: index.php?transaction_id="
            . $transactionId
            . "&blocked=1"
    );

    exit;
}

// 8. Pastikan Member belum dinyatakan lulus sebelumnya.
$hasPassed = hasPassedPosttest(
    $conn,
    $transactionId,
    $context["posttest_id"]
);

if ($hasPassed === null) {

    failPosttestSubmission(
        $conn,
        "Gagal memeriksa riwayat posttest.",
        500
    );
}

if ($hasPassed) {

    pg_query($conn, "ROLLBACK");

    header(
        "Location: index.php?transaction_id="
            . $transactionId
    );

    exit;
}

// 9. Ambil seluruh pertanyaan beserta kunci jawabannya.

// Kunci jawaban tetap diproses di sisi server dan tidak dikirim ke Member.
$queryQuestions = "
    SELECT

        q.question_id,
        q.question_order,

        o.option_id,
        o.option_is_correct

    FROM posttest_questions q

    JOIN posttest_options o
        ON q.question_id = o.question_id

    WHERE q.posttest_id = $1

    ORDER BY q.question_order ASC
";

$resultQuestions = pg_query_params(
    $conn,
    $queryQuestions,
    [$context["posttest_id"]]
);

if (!$resultQuestions) {

    error_log(pg_last_error($conn));

    failPosttestSubmission(
        $conn,
        "Gagal mengambil kunci jawaban.",
        500
    );
}

// 10. Susun kunci jawaban.
$questions = [];

while ($row = pg_fetch_assoc($resultQuestions)) {

    $questionId = $row["question_id"];

    if (!isset($questions[$questionId])) {

        $questions[$questionId] = [
            "options" => [],
            "correct_count" => 0,
            "correct_option_id" => null
        ];
    }

    $questions[$questionId]["options"][] =
        (int) $row["option_id"];

    if ($row["option_is_correct"] === "t") {

        $questions[$questionId]["correct_count"]++;

        $questions[$questionId]["correct_option_id"] =
            (int) $row["option_id"];
    }
}

// 11. Validasi struktur ujian.
if (count($questions) === 0) {

    failPosttestSubmission(
        $conn,
        "Posttest belum memiliki pertanyaan."
    );
}

foreach ($questions as $question) {

    if (
        count($question["options"]) !== 4 ||
        $question["correct_count"] !== 1
    ) {

        failPosttestSubmission(
            $conn,
            "Struktur pertanyaan posttest tidak valid."
        );
    }
}

// 12. Setiap pertanyaan harus memiliki tepat satu jawaban yang dikirim.
if (count($answers) !== count($questions)) {

    failPosttestSubmission(
        $conn,
        "Semua pertanyaan wajib dijawab."
    );
}

// 13. Validasi setiap pilihan jawaban dan hitung nilai.
$correctAnswers = 0;
$validatedAnswers = [];

foreach ($questions as $questionId => $question) {

    if (!array_key_exists($questionId, $answers)) {

        failPosttestSubmission(
            $conn,
            "Terdapat pertanyaan yang belum dijawab."
        );
    }

    $selectedOption = filter_var(
        $answers[$questionId],
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($selectedOption === false) {

        failPosttestSubmission(
            $conn,
            "Pilihan jawaban tidak valid."
        );
    }

    // Pastikan pilihan yang dipilih memang termasuk dalam pertanyaan tersebut.
    if (!in_array(
        (int) $selectedOption,
        $question["options"],
        true
    )) {

        failPosttestSubmission(
            $conn,
            "Pilihan jawaban tidak sesuai dengan pertanyaan."
        );
    }

    $isCorrect = (
        (int) $selectedOption ===
        $question["correct_option_id"]
    );

    if ($isCorrect) {
        $correctAnswers++;
    }

    $validatedAnswers[] = [
        "question_id" => (int) $questionId,
        "option_id" => (int) $selectedOption,
        "is_correct" => $isCorrect
    ];
}

// 14. Hitung nilai.
$totalQuestions = count($questions);

$score = round(
    ($correctAnswers / $totalQuestions) * 100,
    2
);

$passingScore = (float) $context["posttest_passing_score"];

$isPassed = ($score >= $passingScore);

// 15. Tentukan nomor percobaan berikutnya.

// Baris transaksi masih terkunci sehingga pengiriman lain untuk
// pendaftaran yang sama harus menunggu proses ini selesai.
$resultNextAttempt = pg_query_params(
    $conn,
    "
    SELECT COALESCE(MAX(attempt_number), 0) + 1 AS next_attempt
    FROM posttest_attempts
    WHERE transaction_id = $1
    ",
    [$transactionId]
);

if (!$resultNextAttempt) {

    error_log(pg_last_error($conn));

    failPosttestSubmission(
        $conn,
        "Gagal menentukan nomor percobaan.",
        500
    );
}

$attemptNumber = (int) pg_fetch_result(
    $resultNextAttempt,
    0,
    "next_attempt"
);

// 16. Simpan data percobaan.
$queryInsertAttempt = "
    INSERT INTO posttest_attempts (
        transaction_id,
        posttest_id,
        attempt_number,
        attempt_score,
        attempt_is_passed
    )

    VALUES ($1, $2, $3, $4, $5)

    RETURNING attempt_id
";

$resultAttempt = pg_query_params(
    $conn,
    $queryInsertAttempt,
    [
        $transactionId,
        $context["posttest_id"],
        $attemptNumber,
        $score,
        $isPassed ? "true" : "false"
    ]
);

if (!$resultAttempt) {

    error_log(pg_last_error($conn));

    failPosttestSubmission(
        $conn,
        "Gagal menyimpan hasil posttest.",
        500
    );
}

$attempt = pg_fetch_assoc($resultAttempt);

$attemptId = $attempt["attempt_id"];

// 17. Simpan seluruh jawaban yang dikirim.
foreach ($validatedAnswers as $answer) {

    $resultAnswer = pg_query_params(
        $conn,
        "
        INSERT INTO posttest_answers (
            attempt_id,
            question_id,
            selected_option_id,
            answer_is_correct
        )

        VALUES ($1, $2, $3, $4)
        ",
        [
            $attemptId,
            $answer["question_id"],
            $answer["option_id"],
            $answer["is_correct"] ? "true" : "false"
        ]
    );

    if (!$resultAnswer) {

        error_log(pg_last_error($conn));

        failPosttestSubmission(
            $conn,
            "Gagal menyimpan jawaban posttest.",
            500
        );
    }
}

// 18. Terbitkan sertifikat secara otomatis setelah Member lulus.

// Sertifikat hanya dibuat jika seluruh ketentuan berikut terpenuhi:
// - Member lulus posttest.
// - Transaksi benar-benar milik Member tersebut.
// - Member memiliki nama lengkap yang valid.

// Sertifikat yang sudah ada tidak dibuat ulang.

if ($isPassed) {

    $queryIssueCertificate = "
        INSERT INTO certificates (
            transaction_id,
            attempt_id,
            certificate_number,
            certificate_name,
            certificate_training_title,
            certificate_issued_at
        )

        SELECT
            tr.transaction_id,
            pa.attempt_id,

            'AJS/'
            || TO_CHAR(
                CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta',
                'YYYY'
            )
            || '/'
            || LPAD(
                tr.transaction_id::TEXT,
                GREATEST(6, LENGTH(tr.transaction_id::TEXT)),
                '0'
            ),

            BTRIM(a.account_full_name),

            LEFT(t.training_topic, 200),

            CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Jakarta'

        FROM posttest_attempts pa

        JOIN transactions tr
            ON pa.transaction_id = tr.transaction_id

        JOIN accounts a
            ON tr.account_id = a.account_id

        JOIN schedules s
            ON tr.schedule_id = s.schedule_id

        JOIN trainings t
            ON s.training_id = t.training_id

        JOIN posttests p
            ON pa.posttest_id = p.posttest_id
            AND p.training_id = t.training_id

        WHERE pa.attempt_id = $1
        AND tr.account_id = $2
        AND pa.attempt_is_passed = TRUE

        AND NULLIF(
            BTRIM(a.account_full_name),
            ''
        ) IS NOT NULL

        ON CONFLICT (transaction_id)
        DO NOTHING
    ";

    $resultCertificate = pg_query_params(
        $conn,
        $queryIssueCertificate,
        [
            $attemptId,
            $_SESSION["account_id"]
        ]
    );

    if (!$resultCertificate) {

        error_log(pg_last_error($conn));

        failPosttestSubmission(
            $conn,
            "Hasil posttest berhasil dihitung, tetapi sertifikat gagal dibuat. Silakan hubungi Admin.",
            500
        );
    }
}

// 18. Terapkan seluruh perubahan data secara bersamaan.
if (!pg_query($conn, "COMMIT")) {

    error_log(pg_last_error($conn));

    pg_query($conn, "ROLLBACK");

    exit("Gagal menyelesaikan pemeriksaan posttest.");
}

// 19. Alihkan ke halaman hasil.
header(
    "Location: result.php?attempt_id="
        . $attemptId
);

exit;

?>
