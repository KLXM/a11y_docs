<?php
// Addon-Instanz holen
$addon = rex_addon::get('a11y_docs');

// Markdown-Datei einlesen
$markdown = rex_file::get(rex_path::addon('a11y_docs', 'docs/main.md'));

// Parsedown instanziieren
$parser = new ParsedownExtra();

// Markdown zu HTML konvertieren
$content = $parser->text($markdown);

// Inhaltsverzeichnis generieren
$toc = [];
$html = new DOMDocument();
@$html->loadHTML('<?xml encoding="UTF-8">' . $content);
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

// HTML zurück zu String
$content = $html->saveHTML();

// Fragment für die Ausgabe vorbereiten
$fragment = new rex_fragment();
$fragment->setVar('content', '
    <div class="a11y-docs">
        <div class="docs-header">
            <input type="text" 
                   class="docs-search form-control" 
                   placeholder="Dokumentation durchsuchen..." 
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
                '.$item['title'].'
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
