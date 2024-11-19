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
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);
        
        // Links sammeln
        $links = $xpath->query('//a');
        $linkList = [];
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);
            if ($href && $text) {
                $linkList[] = [
                    'text' => $text,
                    'href' => $href,
                    'aria' => $link->getAttribute('aria-label')
                ];
            }
        }
        
        // Hauptelemente sammeln
        $mainElements = [];
        $elements = $xpath->query('//*[self::h1 or self::h2 or self::h3 or self::p or self::img or self::button or self::input or self::a]');
        foreach ($elements as $element) {
            $type = $element->tagName;
            $text = trim($element->textContent);
            
            if ($type === 'img') {
                $alt = $element->getAttribute('alt');
                $mainElements[] = [
                    'type' => 'Bild',
                    'text' => $alt ?: 'Kein Alternativtext vorhanden',
                    'announcement' => "Bild: " . ($alt ?: 'Kein Alternativtext vorhanden')
                ];
            } elseif (preg_match('/h(\d)/', $type, $matches)) {
                $level = $matches[1];
                $mainElements[] = [
                    'type' => 'Überschrift',
                    'text' => $text,
                    'level' => $level,
                    'announcement' => "Überschrift Ebene $level: $text"
                ];
            } elseif ($type === 'a') {
                $mainElements[] = [
                    'type' => 'Link',
                    'text' => $text,
                    'href' => $element->getAttribute('href'),
                    'announcement' => "Link: $text"
                ];
            } else {
                $mainElements[] = [
                    'type' => ucfirst($type),
                    'text' => $text,
                    'announcement' => $text
                ];
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Formular
$formContent = '
<div class="rex-form">
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        <div class="panel panel-edit">
            <header class="panel-heading">
                <div class="panel-title">URL analysieren</div>
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

// Ergebnis
$resultContent = '';
if ($error) {
    $resultContent .= rex_view::error($error);
} elseif ($url) {
    // Vorschau Panel
    $resultContent .= '
    <div class="panel panel-default">
        <header class="panel-heading">
            <div class="panel-title">Screenreader Vorschau</div>
        </header>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-8">
                    <div class="list-group" id="elements-list">';
    
    foreach ($mainElements as $idx => $element) {
        $level = isset($element['level']) ? " (Ebene {$element['level']})" : "";
        $resultContent .= '
        <button class="list-group-item" data-announcement="' . rex_escape($element['announcement']) . '">
            <h4 class="list-group-item-heading">' . rex_escape($element['type']) . $level . '</h4>
            <p class="list-group-item-text text-muted">' . rex_escape($element['text']) . '</p>
        </button>';
    }
    
    $resultContent .= '
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="well">
                        <h3>Screenreader Ausgabe</h3>
                        <div id="announcement" class="alert alert-info">
                            Element auswählen...
                        </div>
                        <label class="checkbox">
                            <input type="checkbox" id="tts"> Sprachausgabe aktivieren
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    // Links Panel
    if ($linkList) {
        $resultContent .= '
        <div class="panel panel-default">
            <header class="panel-heading">
                <div class="panel-title">Links auf der Seite</div>
            </header>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Link-Text</th>
                                <th>ARIA-Label</th>
                                <th>URL</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($linkList as $link) {
            $resultContent .= '
                <tr>
                    <td>' . rex_escape($link['text']) . '</td>
                    <td>' . ($link['aria'] ? rex_escape($link['aria']) : '<span class="text-muted">-</span>') . '</td>
                    <td><code>' . rex_escape($link['href']) . '</code></td>
                </tr>';
        }
        
        $resultContent .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>';
    }
}

// JavaScript für die Interaktion
$resultContent .= '
<script>
$(document).on("rex:ready", function() {
    const tts = $("#tts");
    const announcement = $("#announcement");
    const synth = window.speechSynthesis;
    let speaking = false;
    
    $("#elements-list button").on("click", function() {
        const text = $(this).data("announcement");
        announcement.text(text);
        
        if (tts.is(":checked")) {
            if (speaking) synth.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = "de-DE";
            synth.speak(utterance);
            speaking = true;
            
            utterance.onend = function() {
                speaking = false;
            };
        }
    });
    
    tts.on("change", function() {
        if (!$(this).is(":checked") && speaking) {
            synth.cancel();
            speaking = false;
        }
    });
});
</script>';

// Ausgabe
$fragment = new rex_fragment();
$fragment->setVar('title', 'Screenreader Vorschau');
$fragment->setVar('body', $formContent . $resultContent, false);
echo $fragment->parse('core/page/section.php');
