<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (Auth::user() !== null) {
    Response::redirect('/admin/index.php');
}

$error = null;
$username = '';
if (Request::method() === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    try {
        Csrf::validate($_POST['_csrf'] ?? null);
        $admin = (new AdminAuthService(db()))->attempt($username, (string) ($_POST['password'] ?? ''), Request::ip());
        Auth::signIn($admin);
        Response::redirect('/admin/index.php');
    } catch (AppException $exception) {
        http_response_code($exception->status());
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('[admin login] ' . $exception->getMessage());
        http_response_code(500);
        $error = 'Sign-in is unavailable right now. Please try again.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Staff sign-in · Bella Vista Dining</title>
    <?= brand_head_tags() ?>
    <link rel="stylesheet" href="/public/assets/css/app.css?v=20261118b">
</head>
<body class="admin-auth">
<main class="admin-auth-card">
    <aside class="admin-auth-brand"><?= brand_logo(96) ?><p class="admin-auth-kicker">THE ART OF A WELL-RUN SERVICE</p><h2>Warm welcomes.<br>Seamless service.</h2><p>One thoughtful workspace for the people behind every memorable meal.</p><small>Filipino comfort food &amp; continental specialties<br>Tue–Sun · 10 AM–10 PM</small></aside>
    <section class="admin-auth-form">
    <div class="admin-lock" aria-hidden="true"><?= icon('lock') ?></div>
    <h1 class="mt-6 text-[2rem] leading-tight">Staff sign-in</h1>
    <p class="text-muted mt-1">Managers and kitchen staff only.</p>
    <?php if ($error): ?>
        <p class="form-error" role="alert"><?= icon('alert') ?><span><?= e($error) ?></span></p>
    <?php endif; ?>
    <form method="post" class="mt-6 grid gap-4" novalidate>
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <label class="field"><span>Username</span><input name="username" required autocomplete="username" autocapitalize="none" value="<?= e($username) ?>" <?= $username === '' ? 'autofocus' : '' ?>></label>
        <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password" <?= $username !== '' ? 'autofocus' : '' ?>></label>
        <button class="button button-primary" type="submit">Sign in</button>
    </form>
    <p class="admin-auth-foot">Secure staff access · Bella Vista Dining</p>
    </section>
</main>
</body>
</html>
