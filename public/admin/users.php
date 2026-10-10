<?php
declare(strict_types=1);
require __DIR__ . '/../../private/bootstrap.php';
$me = require_admin();
$db = database();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'editor');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Email non valida.';
        elseif (mb_strlen($password) < 12) $error = 'La password deve avere almeno 12 caratteri.';
        elseif (!in_array($role, ['admin','editor'], true)) $error = 'Ruolo non valido.';
        else {
            try {
                $stmt = $db->prepare('INSERT INTO users(email,password_hash,role) VALUES (?,?,?)');
                $stmt->execute([$email,password_hash($password,PASSWORD_DEFAULT),$role]);
                flash('Utente creato.'); go('/admin/users.php');
            } catch (PDOException $e) { $error = 'Esiste già un utente con questa email.'; }
        }
    } elseif ($action === 'toggle') {
        $target = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$target || $target === (int)$me['id']) $error = 'Non puoi disattivare il tuo account.';
        else {
            $stmt = $db->prepare('UPDATE users SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ?');
            $stmt->execute([$target]);
            flash('Accesso utente aggiornato.'); go('/admin/users.php');
        }
    } elseif ($action === 'password') {
        $target = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $password = (string)($_POST['new_password'] ?? '');
        if (!$target || mb_strlen($password) < 12) $error = 'Scegli una password di almeno 12 caratteri.';
        else {
            $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($password,PASSWORD_DEFAULT),$target]);
            flash('Password aggiornata.'); go('/admin/users.php');
        }
    }
}
$users = $db->query('SELECT id,email,role,active,created_at FROM users ORDER BY id')->fetchAll();
page_start('Utenti');
echo '<div class="page-title"><div><h1>Utenti</h1><p>Ogni collega usa il proprio account. I redattori gestiscono solo i documenti.</p></div></div>';
if ($error) echo '<p class="error" role="alert">' . h($error) . '</p>';
echo '<section class="panel"><h2>Nuovo utente</h2><form method="post"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="add"><div class="three"><label>Email<input type="email" name="email" required></label><label>Password iniziale<input type="password" name="password" minlength="12" required></label><label>Ruolo<select name="role"><option value="editor">Redattore documenti</option><option value="admin">Amministratore</option></select></label></div><button class="primary" type="submit">Crea utente</button></form></section>';
echo '<section class="panel"><h2>Account esistenti</h2><div class="user-list">';
foreach ($users as $account) {
    echo '<div class="user-item"><div><strong>' . h($account['email']) . '</strong><small>' . ($account['role']==='admin'?'Amministratore':'Redattore') . ' · ' . ((int)$account['active']?'Attivo':'Disattivato') . '</small></div><div class="user-actions">';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="' . (int)$account['id'] . '"><label class="sr-only" for="password-' . (int)$account['id'] . '">Nuova password</label><input id="password-' . (int)$account['id'] . '" type="password" name="new_password" minlength="12" placeholder="Nuova password" required><button type="submit">Aggiorna</button></form>';
    if ((int)$account['id'] !== (int)$me['id']) echo '<form method="post"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int)$account['id'] . '"><button type="submit">' . ((int)$account['active']?'Disattiva':'Riattiva') . '</button></form>';
    echo '</div></div>';
}
echo '</div></section>';
page_end();
