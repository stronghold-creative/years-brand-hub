<?php
/**
 * Exports everything that cannot be recovered from the repository: pages,
 * settings, and the asset register, as one JSON file.
 *
 * Run from the admin at /admin/export, or over the command line:
 *   php tools/export.php > backup.json
 *
 * Uploaded files themselves are not included; back those up from storage/.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

echo json_encode(export_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
