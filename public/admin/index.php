<?php
declare(strict_types=1);
require __DIR__ . '/../../private/bootstrap.php';
require_user();
$docs = database()->query('SELECT id, title, brand, kind, revision, status, updated_at FROM documents ORDER BY updated_at DESC, id DESC')->fetchAll();
page_start('Documenti');
echo '<div class="page-title"><div><h1>Documenti</h1><p>Cataloghi, listini e schede tecniche pubblicati sul sito.</p></div><a class="primary button" href="/admin/document.php">+ Nuovo documento</a></div>';
if (!$docs) echo '<section class="panel"><p>Non ci sono ancora documenti. Aggiungi il primo catalogo.</p></section>';
else {
    echo '<div class="table-wrap"><table><thead><tr><th>Titolo</th><th>Marchio</th><th>Tipo</th><th>Stato</th><th>Aggiornato</th><th></th></tr></thead><tbody>';
    foreach ($docs as $doc) {
        echo '<tr><td><strong>' . h($doc['title']) . '</strong>' . ($doc['revision'] !== '' ? '<small>' . h($doc['revision']) . '</small>' : '') . '</td><td>' . h($doc['brand']) . '</td><td>' . h($doc['kind']) . '</td><td><span class="status ' . h($doc['status']) . '">' . h(ucfirst($doc['status'])) . '</span></td><td>' . h(substr($doc['updated_at'], 0, 10)) . '</td><td><a href="/admin/document.php?id=' . (int)$doc['id'] . '">Modifica</a></td></tr>';
    }
    echo '</tbody></table></div>';
}
page_end();
