<?php
declare(strict_types=1);

/** Migration files in db/ that have not been applied to this database yet. */
function migrations_pending(): array
{
    try {
        db()->exec('CREATE TABLE IF NOT EXISTS migrations (filename VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $applied = array_column(db_all('SELECT filename FROM migrations'), 'filename');
    } catch (Throwable $e) {
        error_log('could not read migrations: ' . $e->getMessage());
        return [];
    }

    $pending = [];
    $files = glob(ROOT_DIR . '/db/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (!in_array($name, $applied, true)) {
            $pending[] = $name;
        }
    }
    return $pending;
}

/**
 * Applies every pending migration in filename order. Returns the names applied.
 * A failure stops the run and throws, leaving later migrations unapplied rather
 * than half-running the set.
 */
function migrations_run(): array
{
    $ran = [];
    foreach (migrations_pending() as $name) {
        $sql = file_get_contents(ROOT_DIR . '/db/' . $name);
        if ($sql === false) {
            continue;
        }
        db()->exec($sql);
        db_run('INSERT INTO migrations (filename) VALUES (?)', [$name]);
        $ran[] = $name;
    }
    return $ran;
}
