<?php
// Analyzer-Klasse für die Seitenstruktur
class SiteAnalyzer {
    private $dom;
    private $xpath;
    
    public function loadURL($url) {
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
        $this->dom = new DOMDocument();
        @$this->dom->loadHTML($html, LIBXML_NOERROR);
        $this->xpath = new DOMXPath($this->dom);
        
        return [
            'links' => $this->analyzeLinks(),
            'structure' => $this->analyzeStructure()
        ];
    }
    
    private function analyzeLinks() {
        $links = [];
        $linkTexts = []; // Für die Analyse doppelter Link-Texte
        
        foreach ($this->xpath->query('//a') as $link) {
            $text = trim($link->textContent);
            $href = $link->getAttribute('href');
            $ariaLabel = $link->getAttribute('aria-label');
            $title = $link->getAttribute('title');
            
            if ($text || $ariaLabel) {
                $linkInfo = [
                    'text' => $text,
                    'href' => $href,
                    'aria_label' => $ariaLabel,
                    'title' => $title,
                ];
                
                $links[] = $linkInfo;
                
                // Zähle Link-Texte für die Duplikat-Analyse
                $displayText = $text ?: $ariaLabel;
                $linkTexts[$displayText] = ($linkTexts[$displayText] ?? 0) + 1;
            }
        }
        
        // Markiere doppelte Link-Texte
        foreach ($links as &$link) {
            $displayText = $link['text'] ?: $link['aria_label'];
            $link['is_duplicate'] = ($linkTexts[$displayText] > 1);
            $link['occurrence_count'] = $linkTexts[$displayText];
        }
        
        return $links;
    }
    
    private function analyzeStructure() {
        $elements = [];
        $body = $this->dom->getElementsByTagName('body')->item(0);
        
        if ($body) {
            $this->processNode($body, $elements, 0);
        }
        
        return $elements;
    }
    
    private function processNode($node, &$elements, $level) {
        if (!$node || $node->nodeType !== XML_ELEMENT_NODE) return;
        if (in_array(strtolower($node->nodeName), ['script', 'style', 'noscript'])) return;
        
        $nodeInfo = $this->extractNodeInfo($node, $level);
        if ($nodeInfo && (trim($nodeInfo['text']) !== '' || $nodeInfo['type'] === 'image')) {
            $elements[] = $nodeInfo;
        }
        
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->processNode($child, $elements, $level + 1);
            }
        }
    }
    
    private function extractNodeInfo($node, $level) {
        $tag = strtolower($node->nodeName);
        $text = trim($node->textContent);
        
        $info = [
            'tag' => $tag,
            'level' => $level,
            'text' => $text,
            'attributes' => []
        ];
        
        if ($node->hasAttributes()) {
            $relevantAttrs = ['id', 'class', 'role', 'aria-label', 'alt', 'href', 'src', 'title'];
            foreach ($relevantAttrs as $attr) {
                if ($node->hasAttribute($attr)) {
                    $info['attributes'][$attr] = $node->getAttribute($attr);
                }
            }
        }
        
        $info['type'] = $this->determineElementType($tag, $info['attributes']);
        $info['announcement'] = $this->createAnnouncement($info);
        
        try {
            $tempDoc = new DOMDocument();
            $tempNode = $tempDoc->importNode($node, true);
            $tempDoc->appendChild($tempNode);
            $info['html'] = $tempDoc->saveHTML();
        } catch (Exception $e) {
            $info['html'] = '<!-- HTML konnte nicht extrahiert werden -->';
        }
        
        return $info;
    }
    
    private function determineElementType($tag, $attributes) {
        switch ($tag) {
            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                return 'heading';
            case 'a':
                return 'link';
            case 'img':
                return 'image';
            case 'button':
                return 'button';
            case 'input': case 'select': case 'textarea':
                return 'form-control';
            default:
                return $attributes['role'] ?? 'content';
        }
    }
    
    private function createAnnouncement($info) {
        switch ($info['type']) {
            case 'heading':
                $level = substr($info['tag'], 1);
                return "Überschrift Ebene $level: {$info['text']}";
            case 'link':
                $href = $info['attributes']['href'] ?? '#';
                return "Link: {$info['text']}" . ($href !== '#' ? ", führt zu: $href" : '');
            case 'image':
                return "Bild: " . ($info['attributes']['alt'] ?? 'Keine Beschreibung verfügbar');
            case 'button':
                return "Schaltfläche: {$info['text']}";
            case 'form-control':
                $type = $info['attributes']['type'] ?? 'text';
                return "Eingabefeld ($type)" . ($info['text'] ? ": {$info['text']}" : '');
            default:
                return $info['text'];
        }
    }
}

// Hauptseite
$url = rex_request('url', 'string', '');
$analysis = [];
$error = '';

if ($url) {
    try {
        $analyzer = new SiteAnalyzer();
        $analysis = $analyzer->loadURL($url);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Formular ausgeben
$content = '
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
            </div>
            
            <footer class="panel-footer">
                <div class="rex-form-panel-footer">
                    <div class="btn-toolbar">
                        <button type="submit" class="btn btn-save">Analysieren</button>
                    </div>
                </div>
            </footer>
        </div>
    </form>
</div>';

// Fehlermeldung
if ($error) {
    $content .= rex_view::error($error);
}

// Analyseergebnisse
if (!empty($analysis)) {
    $content .= '
    <div class="row">
        <div class="col-md-8">
            <!-- Seitenstruktur -->
            <div class="panel panel-default">
                <div class="panel-heading">Seitenstruktur</div>
                <div class="panel-body p-0">
                    <div class="list-group elements-list">';
    
    foreach ($analysis['structure'] as $idx => $element) {
        $margin = $element['level'] * 20;
        $content .= '
            <button class="list-group-item element-item" 
                    data-index="' . $idx . '" 
                    style="margin-left: ' . $margin . 'px">
                <span class="badge">' . rex_escape($element['type']) . '</span>
                <span class="text-muted">' . rex_escape($element['announcement']) . '</span>
            </button>';
    }
    
    $content .= '
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Links Panel -->
            <div class="panel panel-default">
                <div class="panel-heading">Links auf der Seite</div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Link-Text</th>
                                    <th>Häufigkeit</th>
                                </tr>
                            </thead>
                            <tbody>';
    
    foreach ($analysis['links'] as $link) {
        $displayText = $link['text'] ?: $link['aria_label'];
        $duplicateClass = $link['is_duplicate'] ? ' class="warning"' : '';
        
        $content .= '
                <tr' . $duplicateClass . '>
                    <td>' . rex_escape($displayText) . '</td>
                    <td>' . $link['occurrence_count'] . 'x</td>
                </tr>';
    }
    
    $content .= '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Vorschau Panel -->
            <div class="panel panel-default">
                <div class="panel-heading">Vorschau</div>
                <div class="panel-body">
                    <div id="preview-content">
                        <div class="alert alert-info">Element auswählen...</div>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="tts"> Sprachausgabe aktivieren
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    // JavaScript Dependencies und Analyzer-Daten
    $content .= '
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.21/lodash.min.js"></script>
    <script>const previewElements = ' . json_encode($analysis['structure']) . ';</script>';
}

// Fragment ausgeben
$fragment = new rex_fragment();
$fragment->setVar('title', 'Screenreader Vorschau');
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
