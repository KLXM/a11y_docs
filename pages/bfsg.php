<?php
$addon = rex_addon::get('a11y_docs');

// Markdown laden und parsen, inkl. Inhaltsverzeichnis
$markdown = rex_file::get(rex_path::addon('a11y_docs', 'bfsg.md'));
[$toc, $content] = rex_markdown::factory()->parseWithToc($markdown, 1, 3);

// Layout zusammenbauen
$body = '
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

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('title', $addon->i18n('bfsg_title', 'BFSG'));
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');