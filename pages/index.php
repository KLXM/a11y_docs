<?php
$addon = rex_addon::get('a11y_docs');

$content = '
<div class="a11y-docs">
    <div class="docs-header">
        <input type="text" 
               class="docs-search form-control" 
               placeholder="' . $addon->i18n('search') . '" 
               data-search>
    </div>

    <div class="docs-container">
        <nav class="docs-nav">
            <div class="toc-content" id="toc">
                <!-- TOC wird per JavaScript generiert -->
            </div>
        </nav>
        
        <main class="docs-content">
            <div class="content-wrapper" data-content>';

$content .= rex_markdown::factory()->parse(rex_file::get(rex_path::addon('a11y_docs', 'README.md')));

$content .= '
            </div>
        </main>
    </div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('body', $content, false);
$fragment->setVar('title', $addon->i18n('title'));
echo $fragment->parse('core/page/section.php');
