<?php
$addon = rex_addon::get('a11y_docs');

// URL aus Post verarbeiten
$url = rex_request('url', 'string', '');
$elements = [];
$error = '';

if ($url) {
    try {
        $context = stream_context_create([
            'http' => [
                'method' => "GET",
                'header' => "User-Agent: Mozilla/5.0\r\n",
                'follow_location' => 1,
                'ignore_errors' => true
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        
        if ($html === false) {
            throw new Exception("URL konnte nicht geladen werden");
        }
        
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);
        
        // Elemente sammeln
        $elements = [];
        $body = $dom->getElementsByTagName('body')->item(0);
        
        function processNode($node, &$elements, $level = 0) {
            if (!$node || $node->nodeType !== XML_ELEMENT_NODE) return;
            
            // Script und Style Tags überspringen
            if (in_array(strtolower($node->nodeName), ['script', 'style', 'noscript'])) return;
            
            $tag = strtolower($node->nodeName);
            $text = trim($node->textContent);
            
            // Attribute sammeln
            $attributes = [];
            if ($node->hasAttributes()) {
                $relevantAttrs = ['id', 'class', 'role', 'aria-label', 'alt', 'href', 'src', 'title', 'target'];
                foreach ($relevantAttrs as $attr) {
                    if ($node->hasAttribute($attr)) {
                        $attributes[$attr] = $node->getAttribute($attr);
                    }
                }
            }
            
            // Element-Typ bestimmen
            $type = '';
            switch ($tag) {
                case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                    $type = 'heading';
                    break;
                case 'a':
                    $type = 'link';
                    break;
                case 'img':
                    $type = 'image';
                    break;
                case 'button':
                    $type = 'button';
                    break;
                case 'input': case 'select': case 'textarea':
                    $type = 'form-control';
                    break;
                case 'nav':
                    $type = 'navigation';
                    break;
                default:
                    $type = isset($attributes['role']) ? $attributes['role'] : 'content';
            }
            
            // Screenreader-Ansage erstellen
            $announcement = '';
            switch ($type) {
                case 'heading':
                    $level = substr($tag, 1);
                    $announcement = "Überschrift Ebene $level: $text";
                    break;
                case 'link':
                    $href = $attributes['href'] ?? '#';
                    $announcement = "Link: $text" . ($href !== '#' ? ", führt zu: $href" : '');
                    break;
                case 'image':
                    $announcement = "Bild: " . ($attributes['alt'] ?? 'Keine Beschreibung verfügbar');
                    break;
                case 'button':
                    $announcement = "Schaltfläche: $text";
                    break;
                case 'form-control':
                    $inputType = $attributes['type'] ?? 'text';
                    $announcement = "Eingabefeld ($inputType)" . ($text ? ": $text" : '');
                    break;
                default:
                    $announcement = $text;
            }
            
            if ($text !== '' || $type === 'image') {
                $elements[] = [
                    'type' => $type,
                    'tag' => $tag,
                    'text' => $text,
                    'level' => $level,
                    'attributes' => $attributes,
                    'announcement' => $announcement,
                    'html' => $dom->saveHTML($node)
                ];
            }
            
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    processNode($child, $elements, $level + 1);
                }
            }
        }
        
        if ($body) {
            processNode($body, $elements);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Formular und Grundstruktur ausgeben
$content = '';

// Formular
$formContent = '
<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        <div class="panel panel-edit">
            <header class="panel-heading">
                <div class="panel-title">Website analysieren</div>
            </header>
            
            <div class="panel-body">
                <div class="form-group">
                    <label for="url">Website URL:</label>
                    <input class="form-control" type="url" id="url" name="url" value="' . rex_escape($url) . '" required>
                    <p class="help-block">Geben Sie die URL der zu analysierenden Seite ein.</p>
                </div>
                
                <footer class="panel-footer">
                    <div class="rex-form-panel-footer">
                        <div class="btn-toolbar">
                            <button type="submit" class="btn btn-save">Analysieren</button>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </form>
</div>';

$content .= $formContent;

// Fehlermeldung
if ($error) {
    $content .= rex_view::error($error);
}

// Ergebnisse
if (!empty($elements)) {
    // Statistik
    $stats = [
        'total' => count($elements),
        'headings' => count(array_filter($elements, fn($e) => $e['type'] === 'heading')),
        'links' => count(array_filter($elements, fn($e) => $e['type'] === 'link')),
        'images' => count(array_filter($elements, fn($e) => $e['type'] === 'image'))
    ];
    
    $content .= '
    <div class="panel panel-default">
        <header class="panel-heading">
            <div class="panel-title">Statistik</div>
        </header>
        <div class="panel-body">
            <div class="row text-center">
                <div class="col-sm-3">
                    <div class="h2">' . $stats['total'] . '</div>
                    <div>Elemente</div>
                </div>
                <div class="col-sm-3">
                    <div class="h2">' . $stats['headings'] . '</div>
                    <div>Überschriften</div>
                </div>
                <div class="col-sm-3">
                    <div class="h2">' . $stats['links'] . '</div>
                    <div>Links</div>
                </div>
                <div class="col-sm-3">
                    <div class="h2">' . $stats['images'] . '</div>
                    <div>Bilder</div>
                </div>
            </div>
        </div>
    </div>';
    
    // Hauptcontainer für die Vorschau
    $content .= '
    <div class="preview-container" style="display:grid; grid-template-columns:1fr 400px; gap:20px;">
        <div class="elements-list" style="overflow-y:auto;">';
    
    // Elemente auflisten
    foreach ($elements as $idx => $element) {
        $typeClass = 'preview-' . $element['type'];
        $margin = $element['level'] * 20;
        
        $content .= '
        <div class="panel panel-default element-item ' . $typeClass . '" 
             data-index="' . $idx . '" 
             style="margin-bottom:5px; margin-left:' . $margin . 'px; cursor:pointer;">
            <div class="panel-body" style="padding:10px;">
                <span class="badge" style="margin-right:5px;">' . rex_escape($element['type']) . '</span>
                <span class="text-muted">' . rex_escape($element['announcement']) . '</span>
            </div>
        </div>';
    }
    
    $content .= '
        </div>
        <div class="preview-sidebar" style="background:#f5f5f5; padding:15px; border-radius:4px;">
            <h3>Vorschau</h3>
            <div id="preview-content">Element auswählen</div>
            <div style="margin-top:20px;">
                <label class="checkbox">
                    <input type="checkbox" id="tts"> Sprachausgabe aktivieren
                </label>
            </div>
        </div>
    </div>';
}

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('title', 'Screenreader Vorschau');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Elemente für JavaScript verfügbar machen
echo '<script>const previewElements = ' . json_encode($elements) . ';</script>';
?>
<script>
$(document).on('rex:ready', function() {
    const $preview = $('#preview-content');
    const $tts = $('#tts');
    let currentIndex = -1;
    let isSpeaking = false;
    let utterance = null;
    const synth = window.speechSynthesis;

    // Element anzeigen
    function showElement(index) {
        const element = previewElements[index];
        if (!element) return;

        // Aktives Element markieren
        $('.element-item.active').removeClass('active');
        const $activeElement = $(`.element-item[data-index="${index}"]`);
        $activeElement.addClass('active')
            .css('background-color', '#e8f4f8')
            .get(0)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Vorschau erstellen
        let preview = `<div class="alert alert-info">
            <strong>Screenreader:</strong> "${element.announcement}"
        </div>`;

        // Bei Bildern das Bild anzeigen
        if (element.type === 'image' && element.attributes.src) {
            preview += `<div class="panel panel-default">
                <div class="panel-body">
                    <img src="${element.attributes.src}" 
                         alt="${element.attributes.alt || ''}" 
                         class="img-responsive" 
                         style="max-width:100%">
                </div>
            </div>`;
        }

        // HTML-Markup anzeigen
        preview += `<div class="panel panel-default">
            <div class="panel-heading">HTML</div>
            <div class="panel-body">
                <pre style="white-space:pre-wrap;">${element.html
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')}</pre>
            </div>
        </div>`;

        $preview.html(preview);
        currentIndex = index;

        // Wenn Sprachausgabe aktiviert ist, Element vorlesen
        if ($tts.is(':checked')) {
            speakElement(index);
        }
    }

    // Element vorlesen
    function speakElement(index) {
        const element = previewElements[index];
        if (!element) return;

        // Laufende Sprachausgabe stoppen
        if (isSpeaking) {
            synth.cancel();
            isSpeaking = false;
        }

        // Neue Sprachausgabe starten
        utterance = new SpeechSynthesisUtterance(element.announcement);
        utterance.lang = 'de-DE';

        // Visuelles Feedback während der Sprachausgabe
        utterance.onstart = () => {
            isSpeaking = true;
            $(`.element-item[data-index="${index}"]`).css('background-color', '#e8f4f8');
        };

        utterance.onend = () => {
            isSpeaking = false;
            $(`.element-item[data-index="${index}"]`).css('background-color', '');
        };

        synth.speak(utterance);
    }

    // Event: Element-Klick
    $('.element-item').on('click', function() {
        const index = parseInt($(this).data('index'));
        showElement(index);
    });

    // Event: Tastatur-Navigation
    $(document).on('keydown', function(e) {
        // Nicht in Formularelementen
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (currentIndex < previewElements.length - 1) {
                    showElement(currentIndex + 1);
                }
                break;

            case 'ArrowUp':
                e.preventDefault();
                if (currentIndex > 0) {
                    showElement(currentIndex - 1);
                }
                break;

            case 'h':
            case 'H':
                e.preventDefault();
                findNextElementOfType('heading');
                break;

            case 'l':
            case 'L':
                e.preventDefault();
                findNextElementOfType('link');
                break;

            case 'b':
            case 'B':
                e.preventDefault();
                findNextElementOfType('button');
                break;

            case ' ':
                e.preventDefault();
                if (currentIndex >= 0 && $tts.is(':checked')) {
                    speakElement(currentIndex);
                }
                break;
        }
    });

    // Nächstes Element eines bestimmten Typs finden
    function findNextElementOfType(type) {
        let startIndex = currentIndex + 1;
        if (startIndex >= previewElements.length) startIndex = 0;

        // Vorwärts suchen
        for (let i = startIndex; i < previewElements.length; i++) {
            if (previewElements[i].type === type) {
                showElement(i);
                return;
            }
        }

        // Von vorne suchen
        for (let i = 0; i < startIndex; i++) {
            if (previewElements[i].type === type) {
                showElement(i);
                return;
            }
        }
    }

    // Event: Sprachausgabe Toggle
    $tts.on('change', function() {
        if (!$(this).is(':checked') && isSpeaking) {
            synth.cancel();
            isSpeaking = false;
        }
    });

    // Erstes Element anzeigen, wenn vorhanden
    if (previewElements && previewElements.length > 0) {
        showElement(0);
    }

    // Tastaturkürzel anzeigen
    $('<div class="alert alert-info" style="margin-top: 15px;">')
        .html(`
            <strong>Tastaturkürzel:</strong><br>
            ↑/↓: Navigation<br>
            H: Zur nächsten Überschrift<br>
            L: Zum nächsten Link<br>
            B: Zur nächsten Schaltfläche<br>
            Leertaste: Element vorlesen (wenn Sprachausgabe aktiviert)
        `)
        .appendTo('.preview-sidebar');
});
</script>
