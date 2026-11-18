<?php
// ===========================
// Tentukan root path aplikasi
// ===========================

if (!defined("APP_BASE_PATH")) {

    $scriptPath = str_replace(
        "\\",
        "/",
        $_SERVER["SCRIPT_NAME"] ?? "/"
    );

    $markers = [
        "/member/",
        "/admin/"
    ];

    $positions = [];

    foreach ($markers as $marker) {

        $position = strpos($scriptPath, $marker);

        if ($position !== false) {
            $positions[] = $position;
        }
    }

    if (!empty($positions)) {

        // Ambil jalur aplikasi sebelum folder bertingkat.
        $rootPosition = min($positions);

        $appRoot = substr(
            $scriptPath,
            0,
            $rootPosition
        );
    } else {

        // Halaman PHP pada direktori utama.
        $appRoot = rtrim(
            str_replace(
                "\\",
                "/",
                dirname($scriptPath)
            ),
            "/"
        );
    }

    if ($appRoot === "." || $appRoot === "/") {
        $appRoot = "";
    }

    define("APP_BASE_PATH", $appRoot);
}

// ===================
// Bentuk URL aplikasi
// ===================

if (!function_exists("app_url")) {

    function app_url(string $path = ""): string
    {

        $path = ltrim($path, "/");

        return APP_BASE_PATH . "/" . $path;
    }
}
