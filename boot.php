<?php
if (rex::isBackend() && rex::getUser()) {
    $page = rex_be_controller::getCurrentPage();
    
    // Assets nur auf der Dokumentations-Seite laden
    if ($page === 'a11y_docs/cheatsheet' || $page === 'a11y_docs/bfsg') {
        // Basis-Assets
        rex_view::addCssFile($this->getAssetsUrl('css/style.css'));
        
        // Lokale Mark.js und Mermaid.js
        rex_view::addJsFile($this->getAssetsUrl('js/mark.js'));
        rex_view::addJsFile($this->getAssetsUrl('js/mermaid.js'));
        
        // Haupt-Script
        rex_view::addJsFile($this->getAssetsUrl('js/docs.js'));
    }
}
