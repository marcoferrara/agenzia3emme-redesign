<?php
declare(strict_types=1);

const PROJECT_ROOT = __DIR__ . '/..';
const STORAGE_ROOT = PROJECT_ROOT . '/storage';
const FILE_ROOT = STORAGE_ROOT . '/files';

function config(): array
{
    static $config;
    return $config ??= require __DIR__ . '/config.php';
}

function database(): PDO
{
    static $db;
    if ($db instanceof PDO) return $db;
    if (!is_dir(STORAGE_ROOT)) mkdir(STORAGE_ROOT, 0700, true);
    $db = new PDO('sqlite:' . STORAGE_ROOT . '/3emme.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 5000');
    ensure_cover_column($db);
    return $db;
}

/** Migrazione: aggiunge la colonna della copertina ai database creati prima di questa funzione. */
function ensure_cover_column(PDO $db): void
{
    $table = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='documents'")->fetchColumn();
    if (!$table) return; // installazione non ancora eseguita
    $columns = array_column($db->query('PRAGMA table_info(documents)')->fetchAll(), 'name');
    if (!in_array('cover_name', $columns, true)) $db->exec('ALTER TABLE documents ADD COLUMN cover_name TEXT');
}

function installed(): bool { return is_file(STORAGE_ROOT . '/installed.lock'); }
function h(?string $text): string { return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $path): never { header('Location: ' . $path, true, 303); exit; }

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('3emme_admin');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function check_csrf(): void
{
    start_session();
    $known = $_SESSION['csrf'] ?? null;
    if (!is_string($known) || $known === '' || !hash_equals($known, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Richiesta non valida. Ricarica la pagina e riprova.');
    }
}

function user(): ?array
{
    start_session();
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || !installed()) return null;
    $stmt = database()->prepare('SELECT id, email, role, active FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row && (int)$row['active'] === 1 ? $row : null;
}

function require_user(): array
{
    $user = user();
    if (!$user) go('/admin/login.php');
    return $user;
}

function require_admin(): array
{
    $user = require_user();
    if ($user['role'] !== 'admin') { http_response_code(403); exit('Accesso non autorizzato.'); }
    return $user;
}

function flash(?string $message = null): ?string
{
    start_session();
    if ($message !== null) { $_SESSION['flash'] = $message; return null; }
    $saved = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $saved;
}

function page_start(string $title): void
{
    $person = user();
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . ' · 3Emme</title><link rel="stylesheet" href="/assets/admin.css"></head><body><header class="admin-head"><div class="admin-wrap"><a class="admin-logo" href="/">3<span>EMME</span>.</a><nav>';
    if ($person) {
        echo '<a href="/admin/">Documenti</a>';
        if ($person['role'] === 'admin') echo '<a href="/admin/users.php">Utenti</a>';
        echo '<form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><button type="submit">Esci</button></form>';
    } else echo '<a href="/">Vai al sito</a>';
    echo '</nav></div></header><main class="admin-wrap">';
    if ($message = flash()) echo '<div class="notice" role="status">' . h($message) . '</div>';
}

function page_end(): void { echo '</main></body></html>'; }

function valid_document_url(string $url): bool
{
    $parts = parse_url($url);
    return is_array($parts) && strtolower($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
        && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function save_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Il caricamento non è riuscito.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > (int)config()['max_upload_bytes']) throw new RuntimeException('File troppo grande o vuoto.');
    $name = basename((string)($file['name'] ?? ''));
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $allowed = false;
    if ($extension === 'pdf' && $mime === 'application/pdf') $allowed = true;
    if ($extension === 'xls' && in_array($mime, ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/octet-stream'], true)) {
        $fp = fopen((string)$file['tmp_name'], 'rb'); $magic = fread($fp, 8); fclose($fp);
        $allowed = $magic === hex2bin('D0CF11E0A1B11AE1');
    }
    if ($extension === 'xlsx' && in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], true) && class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        if ($zip->open((string)$file['tmp_name']) === true) {
            $allowed = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('xl/workbook.xml') !== false;
            $zip->close();
        }
    }
    if (!$allowed) throw new RuntimeException('Sono ammessi PDF, XLS e XLSX validi.');
    if (!is_dir(FILE_ROOT)) mkdir(FILE_ROOT, 0700, true);
    $stored = bin2hex(random_bytes(24)) . '.' . $extension;
    if (!move_uploaded_file((string)$file['tmp_name'], FILE_ROOT . '/' . $stored)) throw new RuntimeException('Impossibile salvare il file.');
    return ['stored' => $stored, 'name' => mb_substr($name, 0, 200), 'mime' => $mime, 'size' => $size];
}

/**
 * Salva l'immagine di copertina (JPG, PNG o WebP, max 5 MB) in storage/files.
 * Il tipo viene verificato dal contenuto reale, non dall'estensione.
 */
function save_cover(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Il caricamento della copertina non è riuscito.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 5 * 1024 * 1024) throw new RuntimeException('La copertina deve pesare al massimo 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize((string)$file['tmp_name']) === false) {
        throw new RuntimeException('La copertina deve essere un file JPG, PNG o WebP.');
    }
    if (!is_dir(FILE_ROOT)) mkdir(FILE_ROOT, 0700, true);
    $stored = 'cover_' . bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string)$file['tmp_name'], FILE_ROOT . '/' . $stored)) throw new RuntimeException('Impossibile salvare la copertina.');
    return $stored;
}

/**
 * Elenco centralizzato dei marchi trattati: usato dal sito (logo e filtri)
 * e dal pannello admin (suggerimenti nel campo "Marchio").
 * Per aggiungere un marchio: copiare il logo in public/assets/brand/ e aggiungere una riga qui.
 */
function brand_catalog(): array
{
    return [
        ['name' => 'Ariston',          'logo' => 'ariston.png',         'width' => 768, 'height' => 150],
        ['name' => 'Elco',             'logo' => 'elco.jpg',            'width' => 493, 'height' => 153],
        ['name' => 'Chaffoteaux',      'logo' => 'chaffoteaux.jpg',     'width' => 206, 'height' => 39],
        ['name' => 'Bampi',            'logo' => 'bampi.jpg',           'width' => 207, 'height' => 77],
        ['name' => 'IRSAP',            'logo' => 'irsap.png',           'width' => 382, 'height' => 138],
        ['name' => 'Euroacque',        'logo' => 'euroacque.jpg',       'width' => 768, 'height' => 221],
        ['name' => 'GS Rubinetterie',  'logo' => 'gs-rubinetterie.png', 'width' => 150, 'height' => 106],
        ['name' => 'Nuova Zecchini',   'logo' => 'nuova-zecchini.png',  'width' => 252, 'height' => 60],
    ];
}
