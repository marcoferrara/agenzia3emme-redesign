<?php
declare(strict_types=1);
require __DIR__ . '/../../private/bootstrap.php';
if (!installed()) go('/install.php');
if (user()) go('/admin/');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $db = database();
    $db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([time() - 900]);
    $count = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip = ? AND attempted_at >= ?');
    $count->execute([$email, $ip, time() - 900]);
    if ((int)$count->fetchColumn() >= 8) $error = 'Troppi tentativi. Riprova tra 15 minuti.';
    else {
        $stmt = $db->prepare('SELECT id, password_hash, active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if ($account && (int)$account['active'] === 1 && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$account['id'];
            $db->prepare('DELETE FROM login_attempts WHERE email = ? AND ip = ?')->execute([$email, $ip]);
            go('/admin/');
        }
        $db->prepare('INSERT INTO login_attempts(email, ip, attempted_at) VALUES (?, ?, ?)')->execute([$email, $ip, time()]);
        $error = 'Email o password non corretti.';
    }
}
page_start('Accedi');
echo '<section class="panel narrow"><h1>Accesso redazione</h1><p>Entra per aggiungere o aggiornare i documenti.</p>';
if ($error) echo '<p class="error" role="alert">' . h($error) . '</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><label>Email<input type="email" name="email" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="primary" type="submit">Accedi</button></form></section>';
page_end();
