<?php
if (rex::isBackend() && rex::getUser()) {
    // Basis-Assets
    rex_view::addCssFile($this->getAssetsUrl('css/style.css'));
    rex_view::addJsFile('https://cdnjs.cloudflare.com/ajax/libs/mark.js/8.11.1/mark.min.js');
    
    // Mermaid für Diagramme
    rex_view::addJsFile('https://cdnjs.cloudflare.com/ajax/libs/mermaid/9.3.0/mermaid.min.js');
    
    // Unser Script
    rex_view::addJsFile($this->getAssetsUrl('js/docs.js'));
}
