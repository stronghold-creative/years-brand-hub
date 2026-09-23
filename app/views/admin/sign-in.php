<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>Sign in</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin.css')) ?>">
</head>
<body class="m3-signin">
<form method="post" class="m3-signin__card">
  <h1>Sign in</h1>
  <?php if ($error): ?><p class="m3-banner m3-banner--error"><?= e($error) ?></p><?php endif; ?>
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <div class="m3-field">
    <input class="m3-field__input" type="email" name="email" id="email" placeholder=" " autocomplete="username" required autofocus>
    <label class="m3-field__label" for="email">Email</label>
  </div>

  <div class="m3-field">
    <input class="m3-field__input" type="password" name="password" id="password" placeholder=" " autocomplete="current-password" required>
    <label class="m3-field__label" for="password">Password</label>
  </div>

  <button class="m3-button m3-button--filled m3-state" type="submit">Sign in</button>
</form>
</body>
</html>
