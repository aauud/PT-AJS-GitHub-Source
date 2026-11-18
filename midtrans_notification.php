
<?php

require_once __DIR__ . "/config/database.php";

$midtransConfig = require __DIR__ . "/config/midtrans.php";

$serverKey = $midtransConfig["server_key"];

header("Content-Type: application/json");

// Kirim respons JSON lalu hentikan proses.
function respond($httpCode, $message)
{
    http_response_code($httpCode);

    echo json_encode([
        "message" => $message
    ]);

    exit;
}

// Ubah nilai uang menjadi teks bilangan bulat dalam bentuk ratusan agar dapat dibandingkan.
function normalizeAmount($amount)
{

    $amount = trim((string) $amount);

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
        return null;
    }

    $parts = explode(".", $amount);

    $whole = ltrim($parts[0], "0");

    if ($whole === "") {
        $whole = "0";
    }

    $decimal = str_pad(
        $parts[1] ?? "",
        2,
        "0"
    );

    return $whole . $decimal;
}

// 1. Terima permintaan hanya melalui metode POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, "Method not allowed.");
}

// 2. Baca notifikasi JSON.
$rawBody = file_get_contents("php://input");

$notification = json_decode($rawBody, true);

if (!is_array($notification)) {
    respond(400, "Invalid JSON notification.");
}

// 3. Periksa kelengkapan kolom notifikasi.
$requiredFields = [
    "order_id",
    "gross_amount"
];

foreach ($requiredFields as $field) {

    if (!isset($notification[$field])) {
        respond(400, "Missing notification field.");
    }
}

// 4. Verifikasi tanda tangan notifikasi.


// 4. Siapkan Server Key untuk verifikasi status melalui permintaan terautentikasi.

$serverKey = trim($serverKey);

// Verifikasi notifikasi dilakukan dengan meminta
// status transaksi resmi secara langsung dari Midtrans.

// Jangan perbarui data di basis data hanya berdasarkan
// status yang tercantum pada notifikasi masuk.

// 5. Temukan catatan pembayaran yang sesuai di basis data lokal.
$queryPayment = "
    SELECT
        p.payment_id,
        p.payment_amount,
        p.transaction_id,
        p.payment_status,
        tr.transaction_status

    FROM payments p

    JOIN transactions tr
        ON p.transaction_id = tr.transaction_id

    WHERE p.payment_order_id = $1
";

$resultPayment = pg_query_params(
    $conn,
    $queryPayment,
    [$notification["order_id"]]
);

if (!$resultPayment) {

    error_log(pg_last_error($conn));

    respond(500, "Database error.");
}

$payment = pg_fetch_assoc($resultPayment);

if (!$payment) {
    respond(404, "Payment record not found.");
}

// 6. Bandingkan nominal pada notifikasi dengan nominal di basis data.
$notificationAmount = normalizeAmount(
    $notification["gross_amount"]
);

$databaseAmount = normalizeAmount(
    $payment["payment_amount"]
);

if (
    $notificationAmount === null ||
    $databaseAmount === null ||
    $notificationAmount !== $databaseAmount
) {

    error_log("Midtrans payment amount mismatch.");

    respond(400, "Payment amount mismatch.");
}

// 7. Ambil status transaksi resmi
// secara langsung dari Midtrans.
$statusUrl =
    "https://api.sandbox.midtrans.com/v2/"
    . rawurlencode($notification["order_id"])
    . "/status";

$ch = curl_init($statusUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $serverKey . ":",

    CURLOPT_HTTPHEADER => [
        "Accept: application/json",
        "Content-Type: application/json"
    ],

    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20
]);

$statusResponse = curl_exec($ch);

$statusHttpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$statusCurlError = curl_error($ch);

curl_close($ch);

$statusData = json_decode($statusResponse, true);

// Validasi respons HTTP.
if (
    $statusResponse === false ||
    $statusHttpCode !== 200 ||
    !is_array($statusData)
) {
    error_log(
        "Midtrans status request failed: HTTP "
            . $statusHttpCode
            . " - "
            . ($statusResponse ?: $statusCurlError)
    );

    respond(503, "Unable to verify Midtrans transaction.");
}

// Validasi informasi transaksi.
$verifiedOrderId = $statusData["order_id"] ?? "";
$verifiedTransactionStatus = strtolower(
    $statusData["transaction_status"] ?? ""
);

// 8. Pastikan informasi yang diterima sesuai dengan catatan pembayaran.
if (
    $verifiedOrderId !== $notification["order_id"] ||
    strtolower($statusData["payment_type"] ?? "") !== "qris" ||
    $verifiedTransactionStatus === ""
) {
    respond(400, "Midtrans transaction information mismatch.");
}

// Bandingkan nominal yang sudah diverifikasi.
$verifiedAmount = normalizeAmount(
    $statusData["gross_amount"] ?? ""
);

if ($verifiedAmount !== $databaseAmount) {
    respond(400, "Verified payment amount mismatch.");
}

// 9. Petakan status Midtrans ke nilai status pada basis data.
$midtransStatus = $statusData["transaction_status"] ?? "";

$paymentStatus = null;

switch ($verifiedTransactionStatus) {

    case "settlement":

        $fraudStatus = strtolower(
            (string) ($statusData["fraud_status"] ?? "")
        );

        if (
            ($statusData["status_code"] ?? "") !== "200" ||
            ($fraudStatus !== "" && $fraudStatus !== "accept")
        ) {
            respond(400, "Payment verification failed.");
        }

        $paymentStatus = "Lunas";
        break;

    case "pending":
        $paymentStatus = "Menunggu";
        break;

    case "expire":
        $paymentStatus = "Kedaluwarsa";
        break;

    case "deny":
    case "failure":
        $paymentStatus = "Gagal";
        break;

    case "cancel":
        $paymentStatus = "Dibatalkan";
        break;

    default:
        error_log(
            "Unsupported Midtrans status: "
                . $verifiedTransactionStatus
        );

        respond(200, "Notification received; status not handled.");
}

// 10. Perbarui data di dalam transaksi basis data.
if (!pg_query($conn, "BEGIN")) {

    error_log(pg_last_error($conn));

    respond(500, "Could not start database transaction.");
}

// Kunci catatan terkait untuk mencegah pembaruan yang berjalan bersamaan.
$queryLock = "
    SELECT
        p.payment_id,
        p.payment_status,
        tr.transaction_id,
        tr.transaction_status

    FROM payments p

    JOIN transactions tr
        ON p.transaction_id = tr.transaction_id

    WHERE p.payment_id = $1

    FOR UPDATE OF p, tr
";

$resultLock = pg_query_params(
    $conn,
    $queryLock,
    [$payment["payment_id"]]
);

if (!$resultLock) {

    pg_query($conn, "ROLLBACK");

    error_log(pg_last_error($conn));

    respond(500, "Could not lock payment record.");
}

// Ambil hasil hanya satu kali.
$lockedPayment = pg_fetch_assoc($resultLock);

if (!$lockedPayment) {

    pg_query($conn, "ROLLBACK");

    error_log("Payment record not found during locking.");

    respond(500, "Could not lock payment record.");
}

// Status pembayaran yang sudah lunas tidak boleh diturunkan.
$wasAlreadyPaid = (
    $lockedPayment["payment_status"] === "Lunas"
);

if ($wasAlreadyPaid && $paymentStatus !== "Lunas") {

    pg_query($conn, "COMMIT");

    respond(200, "Payment was already settled.");
}

// 11. Perbarui data pembayaran.
$queryUpdatePayment = "
    UPDATE payments

    SET
        payment_status = $2::payment_status_enum,
        payment_provider_status = $3,
        payment_reference = $4,

        payment_paid_at = CASE
            WHEN $2 = 'Lunas'
            THEN COALESCE(payment_paid_at, CURRENT_TIMESTAMP)
            ELSE payment_paid_at
        END

    WHERE payment_id = $1
";

$resultUpdatePayment = pg_query_params(
    $conn,
    $queryUpdatePayment,
    [
        $payment["payment_id"],
        $paymentStatus,
        $midtransStatus,
        $statusData["transaction_id"] ?? null
    ]
);

if (!$resultUpdatePayment) {

    pg_query($conn, "ROLLBACK");

    error_log(pg_last_error($conn));

    respond(500, "Failed to update payment.");
}

// 12. Perbarui status transaksi hanya setelah pembayaran berhasil.
if ($paymentStatus === "Lunas") {

    $resultUpdateTransaction = pg_query_params(
        $conn,
        "
        UPDATE transactions
        SET transaction_status = 'Terdaftar'
        WHERE transaction_id = $1
        AND transaction_status = 'Menunggu Pembayaran'
        ",
        [$payment["transaction_id"]]
    );

    if (!$resultUpdateTransaction) {

        pg_query($conn, "ROLLBACK");

        error_log(pg_last_error($conn));

        respond(500, "Failed to update transaction.");
    }
}

// 13. Terapkan perubahan.
if (!pg_query($conn, "COMMIT")) {

    error_log(pg_last_error($conn));

    respond(500, "Failed to commit payment update.");
}

// 14. Berikan respons penerimaan notifikasi.
respond(200, "Payment notification processed successfully.");

?>
