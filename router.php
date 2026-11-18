<?php

$requestPath = parse_url(
    $_SERVER["REQUEST_URI"] ?? "/",
    PHP_URL_PATH
);

$requestPath = is_string($requestPath)
    ? rawurldecode($requestPath)
    : "/";

$requestPath = str_replace("\\", "/", $requestPath);

$segments = array_values(
    array_filter(
        explode("/", $requestPath),
        "strlen"
    )
);

// Direktori yang tidak boleh diakses melalui browser.
$blockedDirectories = [
    "config",
    "database",
    ".git",
    ".vscode",
    "private",
    "backups",
    "logs"
];

// Nama file yang tidak boleh diakses melalui browser.
$blockedFiles = [
    "router.php",
    "readme.txt",
    "notes.txt"
];

// Ekstensi file sensitif.
$blockedExtensions = [
    "sql",
    "backup",
    "dump",
    "bak",
    "env",
    "ini",
    "log",
    "key",
    "pem",
    "p12",
    "crt"
];

$blocked = false;

foreach ($segments as $segment) {

    $segmentLower = strtolower($segment);

    if (
        $segment === "." ||
        $segment === ".." ||
        str_starts_with($segment, ".") ||
        in_array(
            $segmentLower,
            $blockedDirectories,
            true
        )
    ) {
        $blocked = true;
        break;
    }
}

$fileName = strtolower(basename($requestPath));

$fileExtension = strtolower(
    pathinfo($fileName, PATHINFO_EXTENSION)
);

if (
    in_array($fileName, $blockedFiles, true) ||
    in_array($fileExtension, $blockedExtensions, true)
) {
    $blocked = true;
}

if ($blocked) {

    http_response_code(404);

    exit("Halaman tidak ditemukan.");
}

// Lanjutkan pemrosesan halaman dan file statis.
return false;
