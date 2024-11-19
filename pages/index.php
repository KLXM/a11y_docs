<?php
// Addon-Instanz holen
$addon = rex_addon::get('a11y_docs');

// Markdown-Datei einlesen
$markdown = rex_file::get(rex_path::addon('a11y_docs', 'README.md'));

// Parsedown instanziieren
$parser = new ParsedownExtra();

// Markdown zu HTML konvertieren
$content = $parser->text($markdown);

// HTML entities dekodieren
$content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Inhaltsverzeichnis generieren
$toc = [];
$html = new DOMDocument();
$html->encoding = 'UTF-8';
$html->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
$xpath = new DOMXPath($html);

// Alle Überschriften finden und IDs zuweisen
foreach ($xpath->query('//h1|//h2|//h3') as $headline) {
    $level = (int)substr($headline->nodeName, 1);
    $title = $headline->textContent;
    $id = rex_string::normalize($title);
    
    // ID zur Überschrift hinzufügen
    $headline->setAttribute('id', $id);
    
    // Für das Inhaltsverzeichnis speichern
    $toc[] = [
        'level' => $level,
        'title' => $title,
        'id' => $id
    ];
}

// HTML zurück zu String, dabei UTF-8 beibehalten
$content = $html->saveHTML();

// Content bereinigen
$content = preg_replace(
    [
        '/^<!DOCTYPE.+?>/', 
        '/<\?xml.*?>/',
        '/<html>/',
        '/<body>/',
        '/<\/body>/',
        '/<\/html>/'
    ], 
    '', 
    $content
);

// Fragment für die Ausgabe vorbereiten
$fragment = new rex_fragment();
$fragment->setVar('content', '
    <div class="a11y-docs">
        <div class="docs-header">
            <input type="text" 
                   class="docs-search form-control" 
                   placeholder="' . rex_i18n::msg('a11y_docs_search') . '" 
                   data-search>
        </div>

        <div class="docs-container">
            <nav class="docs-nav">
                <div class="toc-content">
                    <ul class="toc-list">');

// Inhaltsverzeichnis erstellen
foreach ($toc as $item) {
    $indent = ($item['level'] - 1) * 20;
    $fragment->setVar('content', $fragment->getVar('content') . '
        <li style="margin-left: '.$indent.'px">
            <a href="#'.$item['id'].'" class="toc-link" data-toc-link>
                '.htmlspecialchars($item['title']).'
            </a>
        </li>');
}

// Rest des Layouts hinzufügen
$fragment->setVar('content', $fragment->getVar('content') . '
                    </ul>
                </div>
            </nav>
            
            <main class="docs-content">
                <div class="content-wrapper" data-content>
                    '.$content.'
                </div>
            </main>
        </div>
    </div>');

// Fragment ausgeben
echo $fragment->parse('core/page/section.php');
