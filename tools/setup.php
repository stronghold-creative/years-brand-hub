<?php
/**
 * One-time setup, for servers without SSH.
 *
 * Copy this file into public_html, visit it in a browser, complete the form,
 * then delete it. It refuses to run once an admin user exists, so it cannot be
 * used to add accounts later.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$errors = [];
$done   = false;

// ---------------------------------------------------------- environment check

$checks = [
    'PHP 8.1 or higher'      => PHP_VERSION_ID >= 80100,
    'PDO MySQL driver'       => in_array('mysql', PDO::getAvailableDrivers(), true),
    'Storage is writable'    => is_writable(ROOT_DIR . '/storage/assets'),
    'Config file loaded'     => is_file(APP_DIR . '/config.php'),
];

// Connecting is what actually proves the credentials, so test it separately.
$dbReady = false;
try {
    db()->query('SELECT 1');
    $dbReady = true;
} catch (Throwable $e) {
    $errors[] = 'Cannot connect to the database. Check the credentials in app/config.php. ' . $e->getMessage();
}
$checks['Database connection'] = $dbReady;

// Has setup already run?
$tablesExist = false;
$userExists  = false;
if ($dbReady) {
    try {
        $tablesExist = (bool) db()->query("SHOW TABLES LIKE 'users'")->fetch();
        if ($tablesExist) {
            $userExists = (bool) db_one("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

if ($userExists) {
    http_response_code(403);
    exit('Setup has already run. Delete this file from public_html and sign in at /admin/sign-in. '
       . 'Database updates are applied from Settings in the admin, not here.');
}

// ------------------------------------------------------------------ run setup

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbReady) {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '') {
        $errors[] = 'Enter your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'Use a password of at least 12 characters.';
    }

    if (!$errors) {
        try {
            // Apply any migration that has not run yet.
            db()->exec('CREATE TABLE IF NOT EXISTS migrations (filename VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
            $applied = array_column(db_all('SELECT filename FROM migrations'), 'filename');
            $files = glob(ROOT_DIR . '/db/*.sql') ?: [];
            sort($files);
            $ran = [];
            foreach ($files as $file) {
                $base = basename($file);
                if (in_array($base, $applied, true)) {
                    continue;
                }
                db()->exec((string) file_get_contents($file));
                db_run('INSERT INTO migrations (filename) VALUES (?)', [$base]);
                $ran[] = $base;
            }

            db_run(
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']
            );

            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Setup failed: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up the brand hub</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Poppins', system-ui, sans-serif; background: #FFFBF1; color: #303030;
         display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 2rem; }
  .card { width: min(30rem, 100%); background: #fff; border: 1px solid #E4DCCC; padding: 2rem; }
  h1 { margin: 0 0 1.5rem; font-size: 1.375rem; color: #5B3FA3; }
  ul.checks { list-style: none; padding: 0; margin: 0 0 1.5rem; font-size: .875rem; }
  ul.checks li { display: flex; justify-content: space-between; padding: .375rem 0; border-bottom: 1px solid #F0EADF; }
  .yes { color: #448D7D; } .no { color: #B63631; }
  label { display: grid; gap: .25rem; font-size: .75rem; color: #777; margin-bottom: 1rem; }
  input { font: inherit; padding: .5rem .625rem; border: 1px solid #E4DCCC; background: #FFFBF1; }
  button { font: inherit; background: #7257B7; color: #fff; border: 0; padding: .625rem 1rem; cursor: pointer; }
  .error { background: #FDE9F1; border-left: 3px solid #B63631; padding: .75rem 1rem; font-size: .875rem; margin-bottom: 1rem; }
  .ok { background: #FFF6E5; border-left: 3px solid #448D7D; padding: 1rem; }
  code { background: #FFF6E5; padding: .125rem .25rem; }
</style>
</head>
<body>
<div class="card">
<?php if ($done): ?>
  <h1>Setup complete</h1>
  <div class="ok">
    <p><strong>Delete this file from public_html now.</strong> It is the one remaining way in.</p>
    <p>Then sign in at <a href="/admin/sign-in">/admin/sign-in</a>.</p>
  </div>
<?php else: ?>
  <h1>Set up the brand hub</h1>

  <ul class="checks">
    <?php foreach ($checks as $label => $ok): ?>
      <li><span><?= e($label) ?></span><span class="<?= $ok ? 'yes' : 'no' ?>"><?= $ok ? 'ready' : 'not ready' ?></span></li>
    <?php endforeach; ?>
  </ul>

  <?php foreach ($errors as $error): ?>
    <p class="error"><?= e($error) ?></p>
  <?php endforeach; ?>

  <?php if ($dbReady): ?>
    <p style="font-size:.875rem;color:#777">This imports the database schema and creates your admin account.</p>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label>Your name<input type="text" name="name" required autofocus></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Password, at least 12 characters<input type="password" name="password" required minlength="12"></label>
      <button type="submit">Run setup</button>
    </form>
  <?php else: ?>
    <p style="font-size:.875rem">Fix the database connection in <code>app/config.php</code>, then reload this page.</p>
  <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
