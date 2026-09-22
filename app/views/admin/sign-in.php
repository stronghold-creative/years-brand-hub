<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin.css')) ?>">
</head>
<body class="signin">
<form method="post" class="signin__card">
  <h1>Sign in</h1>
  <?php if ($error): ?><p class="signin__error"><?= e($error) ?></p><?php endif; ?>
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label>Email<input type="email" name="email" autocomplete="username" required autofocus></label>
  <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
  <button type="submit">Sign in</button>
</form>
</body>
</html>
