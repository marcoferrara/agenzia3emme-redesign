<?php
declare(strict_types=1);
// Genera l'anteprima statica del sito in docs/ (per GitHub Pages): senza database né area redazione.
// Uso: php tools/build-preview.php
define('STATIC_PREVIEW', true);
$root = dirname(__DIR__);
$out = $root . '/docs';

// Pulisce e ricrea docs/
if (is_dir($out)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
} else mkdir($out, 0755, true);

// Copia degli asset pubblici (escluso il CSS dell'area redazione)
function copy_dir(string $from, string $to): void {
    mkdir($to, 0755, true);
    foreach (scandir($from) as $name) {
        if ($name === '.' || $name === '..' || $name === 'admin.css') continue;
        is_dir("$from/$name") ? copy_dir("$from/$name", "$to/$name") : copy("$from/$name", "$to/$name");
    }
}
copy_dir($root . '/public/assets', $out . '/assets');

// Percorsi relativi, così funziona anche in una sottocartella (/nome-repo/)
function relativize(string $html): string {
    $html = str_replace(['href="/assets/', 'src="/assets/'], ['href="assets/', 'src="assets/'], $html);
    $html = str_replace(['href="/chi-siamo.html"', 'href="/#', 'href="/"'], ['href="chi-siamo.html"', 'href="index.html#', 'href="index.html"'], $html);
    // Nell'anteprima non c'è l'area redazione
    return str_replace(' · <a href="/admin/">Area redazione</a>', '', $html);
}

ob_start();
require $root . '/public/index.php';
file_put_contents($out . '/index.html', relativize(ob_get_clean()));
file_put_contents($out . '/chi-siamo.html', relativize(file_get_contents($root . '/public/chi-siamo.html')));
file_put_contents($out . '/.nojekyll', '');
echo "Anteprima generata in docs/\n";
