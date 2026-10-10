<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
if (!installed()) { http_response_code(404); exit; }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit; }
// Il sito mostra solo le copertine dei documenti pubblicati; la redazione (utente loggato) vede anche bozze e archiviati
$onlyPublished = user() === null || empty($_GET['preview']);
$sql = 'SELECT cover_name FROM documents WHERE id = ? AND cover_name IS NOT NULL' . ($onlyPublished ? " AND status = 'published'" : '');
$stmt = database()->prepare($sql);
$stmt->execute([$id]);
$cover = $stmt->fetchColumn();
if (!$cover || !preg_match('/^cover_[a-f0-9]{48}\.(jpg|png|webp)$/', $cover)) { http_response_code(404); exit; }
$path = FILE_ROOT . '/' . $cover;
if (!is_file($path)) { http_response_code(404); exit; }
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . $types[pathinfo($path, PATHINFO_EXTENSION)]);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: ' . ($onlyPublished ? 'public, max-age=3600' : 'private, no-store'));
readfile($path);
