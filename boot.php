<?php
if (rex::isBackend() && rex::getUser()) {
    // Basis-Assets
    rex_view::addCssFile($this->getAssetsUrl('css/style.css'));
    rex_view::addJsFile('https://cdnjs.cloudflare.com/ajax/libs/mark.js/8.11.1/mark.min.js');
    
    // Mermaid für Diagramme
    rex_view::addJsFile('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js');
    
    // Wir fügen ein init.js File hinzu für die Mermaid-Initialisierung
    rex_view::addJsFile($this->getAssetsUrl('js/init.js'));
    
    // Haupt-Script
    rex_view::addJsFile($this->getAssetsUrl('js/docs.js'));
}
