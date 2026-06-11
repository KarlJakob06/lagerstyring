<?php
/**
 * Felles feilvakt for AJAX-endepunkter.
 *
 * Sørger for at selv en fatal PHP-feil (manglende utvidelse, minne osv.)
 * returneres som lesbar JSON med HTTP 500, i stedet for en tom 500-side
 * som ikke sier noe. Inkluder ØVERST i hvert AJAX-endepunkt.
 */

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Serverfeil: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo json_encode([
            'ok'    => false,
            'error' => 'Serverfeil: ' . $err['message'],
        ], JSON_UNESCAPED_UNICODE);
    }
});
