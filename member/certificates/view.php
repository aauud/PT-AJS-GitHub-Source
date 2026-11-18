
<?php

require_once __DIR__ . "/guard.php";

// 1. Validasi ID sertifikat.
$certificateId = filter_var(
    $_GET["certificate_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($certificateId === false || $certificateId === null) {
    http_response_code(400);
    exit("ID sertifikat tidak valid.");
}

// 2. Ambil sertifikat milik Member yang sedang login.

// Sertifikat hanya ditampilkan jika percobaan posttest terkait
// benar-benar dinyatakan lulus.

$queryCertificate = "
    SELECT

        c.certificate_id,
        c.transaction_id,
        c.attempt_id,
        c.certificate_number,
        c.certificate_name,
        c.certificate_training_title,
        c.certificate_issued_at

    FROM certificates c

    JOIN transactions tr
        ON c.transaction_id = tr.transaction_id

    JOIN posttest_attempts pa
        ON c.attempt_id = pa.attempt_id
        AND c.transaction_id = pa.transaction_id

    WHERE c.certificate_id = $1
    AND tr.account_id = $2
    AND pa.attempt_is_passed = TRUE
";

$resultCertificate = pg_query_params(
    $conn,
    $queryCertificate,
    [
        $certificateId,
        $_SESSION["account_id"]
    ]
);

if (!$resultCertificate) {
    error_log(pg_last_error($conn));
    exit("Gagal mengambil data sertifikat.");
}

$certificate = pg_fetch_assoc($resultCertificate);

if (!$certificate) {
    http_response_code(404);
    exit("Sertifikat tidak ditemukan atau Anda tidak memiliki akses.");
}

// 3. Format tanggal penerbitan.
$timezone = new DateTimeZone("Asia/Jakarta");

$issueDate = new DateTimeImmutable(
    $certificate["certificate_issued_at"],
    $timezone
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sertifikat <?= htmlspecialchars($certificate["certificate_number"]) ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f2f2f2;
        }

        .certificate {
            width: 100%;
            max-width: 1100px;
            min-height: 650px;
            margin: 30px auto;
            padding: 55px;
            background: #fffdf6;
            border: 12px double #b99a53;
            color: #332b20;
            text-align: center;
            position: relative;
        }

        .certificate-label {
            color: #806327;
            letter-spacing: 4px;
            font-size: 15px;
            font-weight: bold;
        }

        .certificate-title {
            color: #806327;
            font-size: 42px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-top: 25px;
        }

        .participant-name {
            font-size: 38px;
            font-weight: bold;
            margin: 30px 0;
            color: #332b20;
        }

        .training-title {
            font-size: 27px;
            font-weight: bold;
            margin: 20px 0;
        }

        .certificate-number {
            color: #806327;
            font-size: 14px;
            margin-top: 35px;
        }

        .signature-area {
            margin-top: 65px;
        }

        .signature-line {
            width: 240px;
            border-bottom: 1px solid #332b20;
            margin: 0 auto 10px;
        }

        .no-print {
            text-align: center;
            margin: 25px 0;
        }

        @media print {

            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            body {
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .certificate {
                max-width: none;
                width: 100%;
                min-height: 175mm;
                margin: 0;
                padding: 35px;
                border: 10px double #b99a53;
                background: #fffdf6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

        }

    </style>

</head>

<body>

<div class="container-fluid">

    <div class="no-print">

        <a
            href="index.php"
            class="btn btn-secondary"
        >
            Kembali ke Sertifikat Saya
        </a>

        <button
            type="button"
            class="btn btn-primary"
            onclick="window.print()"
        >
            Cetak / Simpan PDF
        </button>

    </div>

    <div class="certificate">

        <div class="certificate-label">
            PT ARTA JAYA SAMPURNA
        </div>

        <h1 class="certificate-title">
            SERTIFIKAT PENYELESAIAN
        </h1>

        <p class="mt-5">
            Dengan ini menyatakan bahwa
        </p>

        <div class="participant-name">

            <?= htmlspecialchars($certificate["certificate_name"]) ?>

        </div>

        <p>
            telah berhasil menyelesaikan program pelatihan
        </p>

        <div class="training-title">

            <?= htmlspecialchars(
                $certificate["certificate_training_title"]
            ) ?>

        </div>

        <p class="mt-4">
            yang diselenggarakan oleh PT Arta Jaya Sampurna.
        </p>

        <div class="certificate-number">

            Nomor Sertifikat:

            <strong>
                <?= htmlspecialchars($certificate["certificate_number"]) ?>
            </strong>

        </div>

        <p class="mt-2">

            Tanggal penerbitan:

            <?= htmlspecialchars($issueDate->format("d F Y")) ?>

        </p>

        <div class="signature-area">

            <div class="signature-line"></div>

            <strong>PT Arta Jaya Sampurna</strong>

        </div>

    </div>

</div>

</body>
</html>