<?php
/**
 * Enkle, idempotente databasemigreringer.
 *
 * Kjøres automatisk fra config.php ved hver sidevisning (én billig
 * SELECT når alt er oppdatert). Nye kolonner legges dermed til av seg
 * selv etter automatisk deploy fra GitHub — ingen manuelle steg.
 */

function run_migrations(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        name  VARCHAR(50) PRIMARY KEY,
        value VARCHAR(255) NOT NULL
    )");

    $ver = (int)($pdo->query(
        "SELECT value FROM settings WHERE name = 'schema_version'"
    )->fetchColumn() ?: 0);

    if ($ver < 1) {
        // v1: lager per bruker, enheter (stk/m) og kontosikkerhet
        migration_exec($pdo, [
            "ALTER TABLE items ADD COLUMN owner_id INT NULL DEFAULT NULL",
            "ALTER TABLE items ADD COLUMN unit VARCHAR(8) NOT NULL DEFAULT 'stk'",
            "ALTER TABLE users ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0",
            "ALTER TABLE users ADD COLUMN locked_until DATETIME NULL DEFAULT NULL",
            "ALTER TABLE users ADD COLUMN last_login DATETIME NULL DEFAULT NULL",
            "CREATE INDEX idx_items_owner ON items (owner_id)",
        ]);
        set_schema_version($pdo, 1);
    }
}

/** Kjør hver setning for seg — feil ignoreres slik at allerede
 *  eksisterende kolonner/indekser ikke stopper migreringen. */
function migration_exec(PDO $pdo, array $statements): void {
    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Kolonnen/indeksen finnes fra før — trygt å fortsette
        }
    }
}

function set_schema_version(PDO $pdo, int $version): void {
    $pdo->prepare("DELETE FROM settings WHERE name = 'schema_version'")->execute();
    $pdo->prepare("INSERT INTO settings (name, value) VALUES ('schema_version', ?)")
        ->execute([(string)$version]);
}
