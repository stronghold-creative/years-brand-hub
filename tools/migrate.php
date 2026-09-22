<?php
// Applies any .sql file in db/ that has not run yet.
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

db()->exec('CREATE TABLE IF NOT EXISTS migrations (filename VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');

$applied = array_column(db_all('SELECT filename FROM migrations'), 'filename');
$files = glob(ROOT_DIR . '/db/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    db()->exec(file_get_contents($file));
    db_run('INSERT INTO migrations (filename) VALUES (?)', [$name]);
    echo "Applied {$name}\n";
}

echo "Up to date.\n";
