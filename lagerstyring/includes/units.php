<?php
/**
 * Automatisk deteksjon av enhet (stk eller meter) ut fra varenavnet.
 *
 * Kabel, ledning, rør o.l. telles i meter — men tilbehør som
 * «kabelsko» og «rørklemme» telles fortsatt i stk, så ord som tyder
 * på tilbehør overstyrer metervarene.
 *
 * NB: Samme ordlister finnes i JavaScript i add_item.php (for
 * live-forhåndsvisning) — hold dem i sync ved endringer.
 */

const UNITS = ['stk' => 'stk', 'm' => 'm'];

function detect_unit(string $name): string {
    $n = mb_strtolower($name);

    // Metervarer: kabel/ledning/rør/slange + vanlige kabeltypekoder
    $meterish = preg_match(
        '/kabel|ledning|wire|rør|slange|snor|lisse'
        . '|\b(pfxp|pfsp|pn|pr|rk|ix|nym|liyy|liycy|h0[57]|eq|rq|fk)\b/iu',
        $n
    );

    // Tilbehørsord som tyder på stykkvare selv om navnet inneholder «kabel» e.l.
    $stkish = preg_match(
        '/sko\b|strips|binder|klips|clips|merk|skjøt|gjennomf|innf'
        . '|feste|klemme|muffe|holder|deksel|lokk|nippel|plugg|endehylse'
        . '|beskytter|sperre/iu',
        $n
    );

    return ($meterish && !$stkish) ? 'm' : 'stk';
}

/** Vis-navn for enheten («stk» eller «m»). */
function unit_label(?string $unit): string {
    return $unit === 'm' ? 'm' : 'stk';
}

/** Valider/normaliser enhetsverdi fra skjema. */
function normalize_unit(?string $unit): string {
    return $unit === 'm' ? 'm' : 'stk';
}
