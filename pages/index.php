<?php
$addon = rex_addon::get('a11y_docs');

// Seiteninhalt
$content = '';

// Suche einbinden
$content .= '
<div class="docs-header">
    <input type="text" 
           class="docs-search form-control" 
           placeholder="' . $addon->i18n('search') . '" 
           data-search>
</div>';

// Dokumentation einbinden
$content .= '
<div class="docs-container">
    <nav class="docs-nav">
        <div class="toc-content" id="toc">
            <!-- TOC wird per JavaScript generiert -->
        </div>
    </nav>
    
    <main class="docs-content">
        <div class="content-wrapper" data-content>';

// README.md als Hauptinhalt einbinden
$content .= rex_markdown::parseFile(rex_path::addon('a11y_docs', 'README.md'));

$content .= '
        </div>
    </main>
</div>';

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
