<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** Krev innlogging — videresender til login.php hvis ikke innlogget. */
function require_login(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/** Krev admin-tilgang. */
function require_admin(): void {
    require_login();
    if (empty($_SESSION['is_admin'])) {
        header('Location: index.php');
        exit;
    }
}

/** Returner CSRF-token (oppretter det hvis det ikke finnes). */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verifiser at CSRF-token fra skjema stemmer. */
function verify_csrf(string $token): bool {
    return !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** HTML-felt med CSRF-token (bruk inne i alle skjemaer). */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Rull CSRF-token etter vellykket POST-handling. */
function rotate_csrf(): void {
    unset($_SESSION['csrf_token']);
}

/** HTML-escape snarvei (tåler null fra databasen). */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Flash-melding: lagre til neste sidevisning. */
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/** Hent og slett flash-melding. */
function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
