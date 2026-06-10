<?php
/**
 * Felles oppstart for alle innloggede sider.
 * Inkluder denne FØR all annen logikk, slik at skjemabehandling og
 * header()-redirects skjer før noe HTML er sendt til nettleseren.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
