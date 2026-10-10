<?php
declare(strict_types=1);
require __DIR__ . '/../../private/bootstrap.php';
$person = require_user();
$db = database();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$doc = null;
if ($id) {
    $stmt = $db->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) { http_response_code(404); exit('Documento non trovato.'); }
}
$error = '';
$values = $doc ?: ['title'=>'','brand'=>'','kind'=>'Catalogo','revision'=>'','description'=>'','external_url'=>'','stored_name'=>null,'original_name'=>null,'status'=>'draft'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach (['title','brand','kind','revision','description','external_url','status'] as $field) $values[$field] = trim((string)($_POST[$field] ?? ''));
    $values['title'] = mb_substr($values['title'], 0, 180);
    $values['brand'] = mb_substr($values['brand'], 0, 80);
    $values['kind'] = mb_substr($values['kind'], 0, 60);
    $values['revision'] = mb_substr($values['revision'], 0, 80);
    $values['description'] = mb_substr($values['description'], 0, 400);
    $values['external_url'] = mb_substr($values['external_url'], 0, 1500);
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) $values['external_url'] = '';
    if ($values['title'] === '' || $values['brand'] === '' || $values['kind'] === '') $error = 'Compila titolo, marchio e tipo.';
    elseif (!in_array($values['status'], ['draft','published','archived'], true)) $error = 'Stato non valido.';
    elseif ($values['external_url'] !== '' && !valid_document_url($values['external_url'])) $error = 'Inserisci un link HTTPS valido.';
    else {
        $newFile = null; $newCover = null;
        try {
            if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) $newFile = save_upload($_FILES['file']);
            $hasSource = $values['external_url'] !== '' || $newFile !== null || ($doc && $doc['stored_name'] && $values['external_url'] === '');
            if (!$hasSource) throw new RuntimeException('Carica un file oppure inserisci un link.');
            // Copertina: nuova immagine, rimozione, oppure si mantiene quella esistente
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) $newCover = save_cover($_FILES['cover']);
            $cover = $newCover ?? (!empty($_POST['remove_cover']) ? null : ($doc['cover_name'] ?? null));
            $stored = $newFile['stored'] ?? ($values['external_url'] !== '' ? null : ($doc['stored_name'] ?? null));
            $name = $newFile['name'] ?? ($values['external_url'] !== '' ? null : ($doc['original_name'] ?? null));
            $mime = $newFile['mime'] ?? ($values['external_url'] !== '' ? null : ($doc['mime_type'] ?? null));
            $size = $newFile['size'] ?? ($values['external_url'] !== '' ? null : ($doc['file_size'] ?? null));
            if ($id) {
                $stmt = $db->prepare('UPDATE documents SET title=?, brand=?, kind=?, revision=?, description=?, external_url=?, stored_name=?, original_name=?, mime_type=?, file_size=?, status=?, cover_name=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $stmt->execute([$values['title'],$values['brand'],$values['kind'],$values['revision'],$values['description'],$values['external_url'] ?: null,$stored,$name,$mime,$size,$values['status'],$cover,$id]);
            } else {
                $stmt = $db->prepare('INSERT INTO documents(title,brand,kind,revision,description,external_url,stored_name,original_name,mime_type,file_size,status,cover_name,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$values['title'],$values['brand'],$values['kind'],$values['revision'],$values['description'],$values['external_url'] ?: null,$stored,$name,$mime,$size,$values['status'],$cover,$person['id']]);
            }
            if ($doc && $doc['stored_name'] && $doc['stored_name'] !== $stored) @unlink(FILE_ROOT . '/' . $doc['stored_name']);
            if ($doc && !empty($doc['cover_name']) && $doc['cover_name'] !== $cover) @unlink(FILE_ROOT . '/' . $doc['cover_name']);
            flash('Documento salvato.');
            go('/admin/');
        } catch (Throwable $e) {
            if ($newFile) @unlink(FILE_ROOT . '/' . $newFile['stored']);
            if (!empty($newCover)) @unlink(FILE_ROOT . '/' . $newCover);
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Salvataggio non riuscito. Riprova.';
        }
    }
}
page_start($id ? 'Modifica documento' : 'Nuovo documento');
echo '<div class="page-title"><div><a class="back" href="/admin/">← Documenti</a><h1>' . ($id ? 'Modifica documento' : 'Nuovo documento') . '</h1><p>I campi marcati con * sono obbligatori.</p></div></div>';
if ($error) echo '<p class="error" role="alert">' . h($error) . '</p>';
echo '<section class="panel"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><div class="two"><label>Titolo *<input name="title" required maxlength="180" value="' . h($values['title']) . '" placeholder="Es. Catalogo climatizzazione 2026"></label><label>Marchio *<input name="brand" required maxlength="80" value="' . h($values['brand']) . '" list="brands"><datalist id="brands">' . implode('', array_map(fn($b) => '<option value="' . h($b['name']) . '">', brand_catalog())) . '</datalist></label></div><div class="two"><label>Tipo *<select name="kind"><option value="Catalogo"' . ($values['kind']==='Catalogo'?' selected':'') . '>Catalogo</option><option value="Listino"' . ($values['kind']==='Listino'?' selected':'') . '>Listino</option><option value="Brochure"' . ($values['kind']==='Brochure'?' selected':'') . '>Brochure</option><option value="Scheda tecnica"' . ($values['kind']==='Scheda tecnica'?' selected':'') . '>Scheda tecnica</option><option value="Altro"' . ($values['kind']==='Altro'?' selected':'') . '>Altro</option></select></label><label>Anno o revisione<input name="revision" maxlength="80" value="' . h($values['revision']) . '" placeholder="Es. 2026 · revisione maggio"></label></div><label>Descrizione breve<textarea name="description" maxlength="400" rows="3">' . h($values['description']) . '</textarea></label><div class="two"><label>Carica PDF o listino Excel<input type="file" name="file" accept=".pdf,.xls,.xlsx"></label><label>Oppure link HTTPS<input type="url" name="external_url" maxlength="1500" value="' . h($values['external_url'] ?? '') . '" placeholder="https://..."></label></div>';
if ($doc && $doc['original_name']) echo '<p class="hint">File attuale: ' . h($doc['original_name']) . '. Un nuovo file lo sostituisce.</p>';
echo '<div class="cover-field"><label>Foto di copertina (catalogo o listino)<input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label>' . (!empty($doc['cover_name']) ? '<div class="cover-current"><img src="/cover.php?id=' . (int)$id . '&amp;preview=1" alt="Copertina attuale" width="90"><label class="check"><input type="checkbox" name="remove_cover" value="1"> Rimuovi la copertina attuale</label></div>' : '') . '<p class="hint">JPG, PNG o WebP, massimo 5 MB. Meglio in formato verticale (A4). Compare nella scheda del sito.</p></div><label>Stato<select name="status"><option value="draft"' . ($values['status']==='draft'?' selected':'') . '>Bozza — non visibile</option><option value="published"' . ($values['status']==='published'?' selected':'') . '>Pubblicato — visibile sul sito</option><option value="archived"' . ($values['status']==='archived'?' selected':'') . '>Archiviato — non visibile</option></select></label><div class="actions"><button class="primary" type="submit">Salva documento</button><a href="/admin/">Annulla</a></div></form></section>';
page_end();
