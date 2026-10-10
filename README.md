# Agenzia Murru · 3Emme — sito con redazione PHP

Frontend statico del redesign, pronto per GitHub Pages. La homepage e la pagina `chi-siamo.html` funzionano senza backend. La ricerca e i filtri della documentazione funzionano nel browser; i collegamenti ai cataloghi aprono ancora le pagine del sito 3Emme attuale e andranno migrati prima di dismetterlo.

Il backend PHP e i file caricati non fanno parte di questa repo. Per provarla in locale, apri index.html nel browser.

## Struttura del progetto

```
sito/
├── public/            ← document root dell'hosting
│   ├── index.php      home (documenti letti dal database)
│   ├── chi-siamo.html
│   ├── install.php / download.php
│   ├── admin/         pannello redazione
│   └── assets/        style.css, admin.css, loghi, emblema, brand/ (loghi fornitori)
├── private/           bootstrap.php (funzioni, elenco marchi), config.php — NON pubblico
└── storage/           database SQLite e file caricati — NON pubblico, ignorato da git
```

## Aggiungere un marchio

1. Copiare il logo in `public/assets/brand/`.
2. Aggiungere una riga in `brand_catalog()` dentro `private/bootstrap.php` (nome, file, dimensioni).

Il marchio compare in automatico nella griglia, nei filtri della documentazione e nei suggerimenti del pannello admin.

## Prova locale

Da PowerShell, nella cartella `sito/` (lo script `Start-PhpSite.ps1` è bloccato dalla execution policy):

```powershell
& 'C:\Users\marco\Documents\Codex\tools\php\8.5.11\php.exe' -S 127.0.0.1:5188 -t public 'C:\Users\marco\Documents\Codex\tools\php\router.php'
```
