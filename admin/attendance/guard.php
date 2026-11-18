<?php

require_once __DIR__ . "/../guard.php";

function verifyAttendanceAdminCsrf(): bool
{
    $submittedToken = $_POST["csrf_token"] ?? "";

    return (
        is_string($submittedToken) &&
        !empty($_SESSION["csrf_token"]) &&
        hash_equals($_SESSION["csrf_token"], $submittedToken)
    );
}