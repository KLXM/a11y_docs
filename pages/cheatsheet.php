<?php
$addon = rex_addon::get('a11y_docs');

// Markdown laden und parsen, inkl. Inhaltsverzeichnis
$markdown = rex_file::get(rex_path::addon('a11y_docs', 'README.md'));
[$toc, $content] = rex_markdown::factory()->parseWithToc($markdown, 1, 3);

// Suchfeld
$searchField = '
<div class="docs-header">
    <input type="text" 
           class="docs-search form-control" 
           placeholder="' . $addon->i18n('a11y_docs_search') . '" 
           data-search>
</div>';

// Layout zusammenbauen
$body = $searchField . '
<div class="docs-container">
    <nav class="docs-nav">
        <div class="toc-content" id="toc">
            ' . $toc . '
        </div>
    </nav>
    
    <main class="docs-content">
        <div class="content-wrapper" data-content>
            ' . $content . '
        </div>
    </main>
</div>';

// JavaScript für Initialisierung direkt einbetten
$js = '
<script>
jQuery(document).ready(function($) {
    // Mark.js Initialisierung für Suche
    if (typeof Mark !== "undefined") {
        const markInstance = new Mark(document.querySelector(".docs-content"));
        const searchInput = document.querySelector(".docs-search");
        if (searchInput) {
            searchInput.addEventListener("input", function(e) {
                markInstance.unmark();
                if (e.target.value) {
                    markInstance.mark(e.target.value);
                }
            });
        }
    } else {
        console.log("Mark.js not loaded");
    }
    
    // Mermaid.js Initialisierung für Diagramme
    if (typeof mermaid !== "undefined") {
        mermaid.initialize({ startOnLoad: true });
        mermaid.init(undefined, document.querySelectorAll(".language-mermaid"));
    } else {
        console.log("Mermaid.js not loaded");
    }
});
</script>';

// JavaScript in das Layout einbauen
$body = $body . $js;

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('a11y_docs_title'));
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');
