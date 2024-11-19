<?php
// Füge Assets nur im Backend hinzu und wenn ein User eingeloggt ist
if (rex::isBackend() && rex::getUser()) {
    // CSS & JS einbinden
    rex_view::addCssFile($this->getAssetsUrl('css/style.css'));
    rex_view::addJsFile($this->getAssetsUrl('js/docs.js'));
    
    // Optional: highlight.js für Code-Highlighting
    rex_view::addCssFile('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/default.min.css');
    rex_view::addJsFile('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js');
    
    // Optional: MermaidJS für Diagramme
    rex_view::addJsFile('https://cdnjs.cloudflare.com/ajax/libs/mermaid/9.3.0/mermaid.min.js');
    
    // JavaScript Initialisierung
    rex_view::addJsFile($this->getAssetsUrl('js/init.js'));
}
