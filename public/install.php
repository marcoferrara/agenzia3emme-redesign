<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
if (installed()) { http_response_code(404); exit('Pagina non disponibile.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $key = (string)($_POST['setup_key'] ?? '');
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (config()['setup_key'] === 'CAMBIA-QUESTA-CHIAVE-LUNGA-E-CASUALE') $error = 'Prima modifica la chiave di installazione nel file private/config.php.';
    elseif (!hash_equals((string)config()['setup_key'], $key)) $error = 'Chiave di installazione non corretta.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Inserisci una email valida.';
    elseif (mb_strlen($password) < 12) $error = 'Usa una password di almeno 12 caratteri.';
    else {
        try {
            $db = database();
            $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL CHECK(role IN (\'admin\',\'editor\')), active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
            $db->exec('CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, brand TEXT NOT NULL, kind TEXT NOT NULL, revision TEXT NOT NULL DEFAULT \'\', description TEXT NOT NULL DEFAULT \'\', external_url TEXT, stored_name TEXT, original_name TEXT, mime_type TEXT, file_size INTEGER, status TEXT NOT NULL CHECK(status IN (\'draft\',\'published\',\'archived\')), created_by INTEGER NOT NULL REFERENCES users(id), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
            $db->exec('CREATE TABLE IF NOT EXISTS login_attempts (email TEXT NOT NULL, ip TEXT NOT NULL, attempted_at INTEGER NOT NULL)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_docs_public ON documents(status, brand, title)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_attempts ON login_attempts(email, ip, attempted_at)');
            $db->beginTransaction();
            $stmt = $db->prepare('INSERT INTO users(email, password_hash, role) VALUES (?, ?, \'admin\')');
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $db->commit();
            if (!is_dir(FILE_ROOT)) mkdir(FILE_ROOT, 0700, true);
            if (file_put_contents(STORAGE_ROOT . '/installed.lock', 'installed', LOCK_EX) === false) throw new RuntimeException('Impossibile completare l’installazione.');
            flash('Installazione completata. Accedi con il tuo account.');
            go('/admin/login.php');
        } catch (Throwable $e) { $error = 'Installazione non riuscita. Controlla i permessi della cartella storage.'; }
    }
}
page_start('Installazione');
echo '<section class="panel narrow"><h1>Attiva il pannello</h1><p>Crea il primo account amministratore. La pagina si disattiva dopo l’installazione.</p>';
if ($error) echo '<p class="error" role="alert">' . h($error) . '</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><label>Chiave di installazione<input name="setup_key" type="password" required></label><label>Email<input name="email" type="email" autocomplete="email" required></label><label>Password (almeno 12 caratteri)<input name="password" type="password" minlength="12" autocomplete="new-password" required></label><button class="primary" type="submit">Crea account</button></form></section>';
page_end();
