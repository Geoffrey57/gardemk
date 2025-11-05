<?php
session_start();

$pdo = require __DIR__ . '/db.php';

function login_by_email(string $email): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM masseurskines WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        // regenerate session id
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
    }
    return $user ?: null;
}

function require_login()
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /index.php');
        exit;
    }
}

function current_user(): ?array
{
    global $pdo;
    if (empty($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare('SELECT * FROM masseurskines WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function logout()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function csrf_token()
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}

function csrf_check($token)
{
    return hash_equals($_SESSION['_csrf'] ?? '', (string)$token);
}