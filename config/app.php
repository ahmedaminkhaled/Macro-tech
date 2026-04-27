<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function flash(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $msg = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function csrf_token(): string
{
    return (string)($_SESSION['csrf_token'] ?? '');
}

function csrf_valid(?string $token): bool
{
    $sessionToken = csrf_token();
    if ($sessionToken === '' || $token === null || $token === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}
