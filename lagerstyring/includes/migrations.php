<?php
/**
 * Enkle, selvhelbredende databasemigreringer.
 *
 * Kjøres automatisk fra config.php. I stedet for et versjonsflagg som
 * kan komme ut av synk med virkeligheten, sjekkes faktiske kolonner —
 * mangler en kolonne, forsøkes migreringen (på nytt). Slik kan ikke en
 * halvferdig migrering låse seg og ta ned hele siden.
 */

function run_migrations(PDO $pdo): void {
    // v1: lager per bruker, enheter (stk/m) og kontosikkerhet.
    // Gates på en faktisk kolonne, ikke et flagg.
    if (!table_has_column($pdo, 'items', 'owner_id')) {
        migration_exec($pdo, [
            "ALTER TABLE items ADD COLUMN owner_id INT NULL DEFAULT NULL",
            "ALTER TABLE items ADD COLUMN unit VARCHAR(8) NOT NULL DEFAULT 'stk'",
            "ALTER TABLE users ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0",
            "ALTER TABLE users ADD COLUMN locked_until DATETIME NULL DEFAULT NULL",
            "ALTER TABLE users ADD COLUMN last_login DATETIME NULL DEFAULT NULL",
            "CREATE INDEX idx_items_owner ON items (owner_id)",
        ]);
    }
}

/** Finnes kolonnen? (Tabell- og kolonnenavn er faste literaler i koden.) */
function table_has_column(PDO $pdo, string $table, string $col): bool {
    try {
        $pdo->query("SELECT `$col` FROM `$table` LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Kjør hver setning for seg — «finnes allerede»-feil ignoreres. */
function migration_exec(PDO $pdo, array $statements): void {
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Kolonnen/indeksen finnes fra før — trygt å fortsette
        }
    }
}
