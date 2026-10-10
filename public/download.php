<?php
declare(strict_types=1);
require __DIR__ . '/../private/bootstrap.php';
if (!installed()) { http_response_code(404); exit; }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit; }
$stmt = database()->prepare('SELECT stored_name, original_name, mime_type, file_size FROM documents WHERE id = ? AND status = \'published\' AND stored_name IS NOT NULL');
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc || !preg_match('/^[a-f0-9]{48}\.(pdf|xls|xlsx)$/', $doc['stored_name'])) { http_response_code(404); exit; }
$path = FILE_ROOT . '/' . $doc['stored_name'];
if (!is_file($path)) { http_response_code(404); exit; }
$safeName = str_replace(["\r","\n",'"','\\'], '', $doc['original_name']);
header('Content-Type: ' . $doc['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="documento.' . pathinfo($path, PATHINFO_EXTENSION) . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
readfile($path);
