<?php
if (rex::isBackend() && rex::getUser()) {
    $page = rex_be_controller::getCurrentPage();
    
    // Assets nur auf der Dokumentations-Seite laden
    if ($page === 'a11y_docs/cheatsheet') {
        // Basis-Assets
        rex_view::addCssFile($this->getAssetsUrl('css/style.css'));
        rex_view::addJsFile('https://cdnjs.cloudflare.com/ajax/libs/mark.js/8.11.1/mark.min.js');
        
        // Mermaid für Diagramme (lokal)
        rex_view::addJsFile($this->getAssetsUrl('js/mermaid.min.js'));
        
        // Haupt-Script
        rex_view::addJsFile($this->getAssetsUrl('js/docs.js'));
    }
}
