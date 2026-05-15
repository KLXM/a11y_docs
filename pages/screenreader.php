<?php
$u = $_POST['url'] ?? '';
$e = [];
$r = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($u)) {
    try {
        $screader = new ScreenReaderEmu();
        $e = $screader->loadURL($u);
    } catch (Exception $ex) {
        $r = $ex->getMessage();
    }
}

function getFullImageUrl(string $src, string $baseUrl): string {
    if (empty($src) || empty($baseUrl)) return '';
    if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) return $src;
    try {
        $p = parse_url($baseUrl);
        if (!$p || empty($p['host'])) return $src;
        $proto = $p['scheme'] ?? 'https';
        $host  = $p['host'];
        if (str_starts_with($src, '/')) return "{$proto}://{$host}{$src}";
        $path = isset($p['path']) ? dirname($p['path']) : '';
        $path = $path === '/' ? '' : $path;
        return "{$proto}://{$host}{$path}/{$src}";
    } catch (Exception $ex) {
        return $src;
    }
}

class ScreenReaderEmu {
    private DOMDocument $domDocument;
    private DOMXPath $domXPath;
    private array $processedTexts = [];
    private string $baseUrl = '';
    /** @var array<int, int> */
    private array $headingOrder = [];

    public function loadURL(string $url): array {
        $this->processedTexts = [];
        $this->headingOrder   = [];
        $this->baseUrl = (parse_url($url, PHP_URL_SCHEME) ?? 'https') . '://' . (parse_url($url, PHP_URL_HOST) ?? '');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Ungültige URL");
        }

        $opts = ['http' => [
            'method'          => 'GET',
            'header'          => "User-Agent: Mozilla/5.0\r\n",
            'follow_location' => 1,
            'ignore_errors'   => true,
        ]];
        $context     = stream_context_create($opts);
        $htmlContent = @file_get_contents($url, false, $context);
        if ($htmlContent === false) throw new Exception("URL konnte nicht geladen werden");

        $this->domDocument = new DOMDocument();
        @$this->domDocument->loadHTML(
            mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        $this->domXPath = new DOMXPath($this->domDocument);

        return $this->analyzeContent();
    }

    private function analyzeContent(): array {
        $elements = [];
        $body = $this->domDocument->getElementsByTagName('body')->item(0);
        if ($body) $this->processNode($body, $elements, 0, 'page');
        return $elements;
    }

    private function getLandmarkLabel(string $landmark): string {
        return match($landmark) {
            'nav'    => 'Navigation',
            'main'   => 'Hauptinhalt',
            'header' => 'Kopfbereich',
            'footer' => 'Fußbereich',
            'aside'  => 'Ergänzungsbereich',
            'form'   => 'Formular',
            'search' => 'Suche',
            default  => '',
        };
    }

    private function processNode(DOMNode $node, array &$elements, int $level, string $landmark): void {
        if ($node->nodeType !== XML_ELEMENT_NODE) return;
        $tagName = strtolower($node->nodeName);
        if (in_array($tagName, ['script', 'style', 'noscript'], true)) return;

        $childLandmark = $landmark;
        if (in_array($tagName, ['nav', 'main', 'header', 'footer', 'aside', 'form'], true)) {
            $childLandmark = $tagName;
        }
        if ($node->hasAttributes()) {
            $role = $node->getAttribute('role');
            if ($role === 'search')     $childLandmark = 'search';
            if ($role === 'navigation') $childLandmark = 'nav';
            if ($role === 'main')       $childLandmark = 'main';
        }

        $info = $this->extractNodeInfo($node, $level, $childLandmark);
        if ($info) {
            $normalizedText = $this->normalizeText($info['text']);
            if ($this->isSignificantElement($info['type']) ||
                ($normalizedText !== '' && !in_array($normalizedText, $this->processedTexts, true))) {
                if ($normalizedText !== '' && $info['type'] !== 'image') {
                    $this->processedTexts[] = $normalizedText;
                }
                $elements[] = $info;
            }
        }

        if (!$node->hasChildNodes()) return;
        $childLevel = in_array($tagName, ['a', 'button', 'label'], true) ? $level : $level + 1;

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->nodeName === 'img') {
                $imgInfo = $this->extractNodeInfo($child, $childLevel, $childLandmark);
                if ($imgInfo) $elements[] = $imgInfo;
            } else {
                $this->processNode($child, $elements, $childLevel, $childLandmark);
            }
        }
    }

    private function isSignificantElement(string $type): bool {
        return in_array($type, [
            'heading', 'link', 'image', 'button', 'form-control',
            'navigation', 'main-content', 'header', 'footer', 'logo',
        ], true);
    }

    private function normalizeText(?string $text): string {
        return $text ? preg_replace('/\s+/', ' ', trim($text)) : '';
    }

    private function extractNodeInfo(DOMNode $node, int $level, string $landmark): ?array {
        $tagName     = strtolower($node->nodeName);
        $textContent = $tagName === 'img' ? '' : trim($node->textContent);

        $info = [
            'tag'        => $tagName,
            'level'      => $level,
            'text'       => $textContent,
            'attributes' => $this->extractAttributes($node),
            'landmark'   => $landmark,
        ];

        if ($tagName === 'img' && isset($info['attributes']['src'])) {
            $info['attributes']['src'] = $this->fixUrl($info['attributes']['src']);
        }

        $info['type']         = $this->determineElementType($tagName, $info['attributes']);
        $info['announcement'] = $this->createAnnouncement($info);
        $info['issues']       = $this->evaluateIssues($info);

        // Überschriften-Reihenfolge prüfen
        if ($info['type'] === 'heading') {
            $lvl = (int) substr($tagName, 1);
            $this->headingOrder[] = $lvl;
            $cnt = count($this->headingOrder);
            if ($cnt > 1) {
                $prev = $this->headingOrder[$cnt - 2];
                if ($lvl > $prev + 1) {
                    $info['issues'][] = [
                        'code'   => 'heading-skip',
                        'label'  => "Überschriften-Ebene übersprungen",
                        'detail' => "Sprung von H{$prev} auf H{$lvl}. Screenreader-Nutzer navigieren oft per H-Taste durch Überschriften und erwarten eine lückenlose Hierarchie.",
                        'sev'    => 'error',
                    ];
                }
            }
        }

        $info['severity']      = $this->calcSeverity($info['issues']);
        $info['landmarkLabel'] = $this->getLandmarkLabel($landmark);

        return $info;
    }

    private function fixUrl(string $url): string {
        if (str_starts_with($url, 'data:')) return $url;
        if (str_starts_with($url, 'http')) return $url;
        return str_starts_with($url, '/') ? $this->baseUrl . $url : $this->baseUrl . '/' . $url;
    }

    private function extractAttributes(DOMNode $node): array {
        $attributes = [];
        if (!$node->hasAttributes()) return $attributes;
        foreach (['id','class','role','aria-label','aria-hidden','alt','href','src','title','target','lang','type','placeholder'] as $attr) {
            if ($node->hasAttribute($attr)) {
                $attributes[$attr] = $node->getAttribute($attr);
            }
        }
        if ($node->nodeName === 'img' && $node->parentNode?->nodeName === 'a') {
            $parentHref = $node->parentNode->getAttribute('href');
            if ($parentHref) $attributes['parentLink'] = $parentHref;
        }
        return $attributes;
    }

    private function determineElementType(string $tagName, array $attributes): string {
        $role = $attributes['role'] ?? '';
        if ($role) {
            return match($role) {
                'navigation'  => 'navigation',
                'banner'      => 'header',
                'contentinfo' => 'footer',
                'main'        => 'main-content',
                default       => 'content',
            };
        }
        return match($tagName) {
            'h1','h2','h3','h4','h5','h6' => 'heading',
            'a'                            => 'link',
            'img'                          => 'image',
            'button'                       => 'button',
            'input','select','textarea'    => 'form-control',
            'nav'                          => 'navigation',
            'main'                         => 'main-content',
            'header'                       => 'header',
            'footer'                       => 'footer',
            default                        => 'content',
        };
    }

    private function createAnnouncement(array $info): string {
        $type = $info['type'];
        $text = $info['text'];
        $a    = $info['attributes'];
        if (isset($a['aria-label'])) $text = $a['aria-label'];

        return match($type) {
            'heading'      => sprintf("Überschrift Ebene %s: %s", substr($info['tag'], 1), $text),
            'link'         => $this->announcementLink($text, $a),
            'image'        => $this->announcementImage($a),
            'button'       => "Schaltfläche: " . ($text ?: ($a['aria-label'] ?? $a['title'] ?? 'Unbenannte Schaltfläche')),
            'navigation'   => "Navigation beginnt",
            'form-control' => $this->announcementForm($text, $a),
            'header'       => "Kopfbereich beginnt",
            'footer'       => "Fußbereich beginnt",
            'main-content' => "Hauptinhalt beginnt",
            'logo'         => "Logo" . ($text ? ": $text" : ''),
            default        => $text,
        };
    }

    private function announcementLink(string $text, array $a): string {
        $href     = $a['href'] ?? '#';
        $linkText = $text ?: ($a['aria-label'] ?? $a['title'] ?? 'Unbenannter Link');
        return $href === '#' ? "Link: $linkText" : "Link: $linkText, führt zu: $href";
    }

    private function announcementImage(array $a): string {
        $ariaHidden = ($a['aria-hidden'] ?? '') === 'true';
        $altExists  = array_key_exists('alt', $a);
        $alt        = $a['alt'] ?? null;
        $parentLink = $a['parentLink'] ?? '';

        if ($ariaHidden || $alt === '') {
            $announcement = "Bild: (dekorativ, wird übersprungen)";
        } else {
            $description  = $alt ?? ($a['title'] ?? '') ?: basename($a['src'] ?? '');
            $announcement = "Bild: " . ($description ?: 'Keine Beschreibung verfügbar');
        }
        return $parentLink ? "$announcement (Verlinkt zu: $parentLink)" : $announcement;
    }

    private function announcementForm(string $text, array $a): string {
        $inputType = $a['type'] ?? 'text';
        $label     = $text ?: ($a['aria-label'] ?? $a['placeholder'] ?? '');
        return "Eingabefeld ($inputType)" . ($label ? ": $label" : '');
    }

    /**
     * @return array<int, array{code: string, label: string, detail: string, sev: string}>
     */
    private function evaluateIssues(array $info): array {
        $issues = [];
        $type   = $info['type'];
        $a      = $info['attributes'];
        $text   = trim($info['text']);

        if ($type === 'image') {
            $ariaHidden = ($a['aria-hidden'] ?? '') === 'true';
            $altExists  = array_key_exists('alt', $a);
            $alt        = $a['alt'] ?? null;

            if (!$altExists && !$ariaHidden) {
                $issues[] = ['code' => 'img-no-alt', 'label' => 'Kein Alt-Attribut',
                    'detail' => 'Das Bild hat gar kein alt-Attribut. Screenreader lesen möglicherweise den Dateinamen vor – für Nutzer wertlos oder verwirrend.',
                    'sev' => 'error'];
            } elseif ($alt !== null && $alt !== '' && !$ariaHidden) {
                foreach (['/\.(jpg|jpeg|png|gif|webp|svg)$/i', '/^img_?\d+/i', '/[_\-]{2,}/', '/dsc\d+/i'] as $pat) {
                    if (preg_match($pat, $alt)) {
                        $issues[] = ['code' => 'img-bad-alt', 'label' => 'Alt-Text klingt nach Dateiname',
                            'detail' => 'Der Alt-Text enthält technische Zeichen oder sieht wie ein Dateiname aus. Bitte durch eine sinnvolle Bildbeschreibung ersetzen.',
                            'sev' => 'warning'];
                        break;
                    }
                }
            }
        }

        if ($type === 'link') {
            $linkText = $a['aria-label'] ?? $text;
            $lower    = strtolower(trim($linkText));
            $vague    = ['hier','hier klicken','mehr','weiterlesen','erfahren','klicken','link','lesen','weiter','mehr erfahren','details'];
            if (in_array($lower, $vague, true) || (mb_strlen($lower) <= 4 && $lower !== '')) {
                $issues[] = ['code' => 'link-vague', 'label' => 'Nichtssagender Linktext',
                    'detail' => "\"$linkText\" – Screenreader-Nutzer hören Links oft ohne Kontext, z.\u202fB. in einer Linkliste. Der Text muss das Ziel allein beschreiben.",
                    'sev' => 'error'];
            }
            if (empty(trim($linkText))) {
                $issues[] = ['code' => 'link-empty', 'label' => 'Leerer Link',
                    'detail' => 'Dieser Link hat weder sichtbaren Text noch aria-label. Screenreader kündigen ihn nur als „Link" an.',
                    'sev' => 'error'];
            }
            $href = $a['href'] ?? '';
            foreach (['pdf','docx','xlsx','pptx','odt'] as $ext) {
                if (str_ends_with(strtolower($href), ".$ext")) {
                    $extUp = strtoupper($ext);
                    $issues[] = ['code' => 'link-file', 'label' => "Datei-Link ($extUp)",
                        'detail' => "Der Link öffnet eine $extUp-Datei. Bitte im Linktext ankündigen, z.\u202fB. \"Bericht 2024 (PDF, 1,2\u202fMB)\".",
                        'sev' => 'info'];
                    break;
                }
            }
            if (($a['target'] ?? '') === '_blank') {
                $issues[] = ['code' => 'link-newtab', 'label' => 'Öffnet neues Tab',
                    'detail' => 'Links, die ein neues Tab öffnen, können Screenreader-Nutzer desorientieren. Wenn nötig, im Linktext ankündigen: „(öffnet neues Fenster)".',
                    'sev' => 'warning'];
            }
        }

        if ($type === 'button' && empty(trim($a['aria-label'] ?? $text))) {
            $issues[] = ['code' => 'btn-empty', 'label' => 'Schaltfläche ohne Beschriftung',
                'detail' => 'Screenreader kündigen diese Schaltfläche nur als „Schaltfläche" an – die Funktion bleibt unklar.',
                'sev' => 'error'];
        }

        if ($type === 'form-control' && empty(trim($a['aria-label'] ?? $text))) {
            $issues[] = ['code' => 'input-no-label', 'label' => 'Eingabefeld ohne Beschriftung',
                'detail' => 'Dieses Feld hat kein Label. Screenreader-Nutzer wissen nicht, was sie eingeben sollen. Placeholder ist kein Ersatz.',
                'sev' => 'error'];
        }

        return $issues;
    }

    private function calcSeverity(array $issues): string {
        $sevs = array_column($issues, 'sev');
        if (in_array('error',   $sevs, true)) return 'error';
        if (in_array('warning', $sevs, true)) return 'warning';
        if (in_array('info',    $sevs, true)) return 'info';
        return 'ok';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>KLXM ScreenReaderEmu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sev-error   { border-left: 4px solid #ef4444 !important; }
        .sev-warning { border-left: 4px solid #f59e0b !important; }
        .sev-info    { border-left: 4px solid #3b82f6 !important; }
        .sev-ok      { border-left: 4px solid #22c55e !important; }
        .el-active   { background: #eff6ff !important; outline: 2px solid #3b82f6; outline-offset: -2px; }
        .autoplay-hl { animation: pulseBg 1.2s ease-in-out; }
        @keyframes pulseBg { 0%{background:#fff} 40%{background:#dbeafe} 100%{background:#eff6ff} }
        .toast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
            max-width: 380px; padding: .8rem 1.2rem; border-radius: .5rem;
            font-size: .85rem; line-height: 1.5; box-shadow: 0 4px 16px rgba(0,0,0,.15);
            opacity: 0; transition: opacity .3s; pointer-events: none;
        }
        .toast.show { opacity: 1; }
        .toast-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
        .toast-warning { background: #fffbeb; color: #78350f; border: 1px solid #fcd34d; }
        .toast-info    { background: #eff6ff; color: #1e3a8a; border: 1px solid #93c5fd; }
        .filter-btn { transition: background .15s, color .15s; }
        kbd { display:inline-block; padding:1px 5px; border:1px solid #d1d5db; border-radius:3px; font-size:.78em; background:#f9fafb; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 h-screen">

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<div class="grid grid-cols-1 lg:grid-cols-[1fr,420px] h-screen">

    <!-- ===== LINKES PANEL ===== -->
    <div class="overflow-y-auto p-6">
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">

            <!-- Header -->
            <div class="flex flex-wrap justify-between items-start gap-3 mb-5">
                <div>
                    <h1 class="text-xl font-bold">KLXM ScreenReaderEmu</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Wie erleben Menschen mit Sehbehinderung Ihre Website?</p>
                </div>
                <label class="flex items-center gap-2 mt-1 cursor-pointer">
                    <input type="checkbox" id="tts" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                    <span class="text-sm text-gray-600"><i class="fas fa-volume-up mr-1"></i>Sprachausgabe</span>
                </label>
            </div>

            <!-- Einführung -->
            <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold mb-1"><i class="fas fa-info-circle mr-1"></i>Was ist ein Screenreader?</p>
                <p class="mb-2">Menschen mit Sehbehinderung nutzen Software, die den Bildschirminhalt <strong>Element für Element vorliest</strong> – von oben nach unten. Sie sehen das Layout nicht, sondern hören Texte, Überschriften, Links und Bildbeschreibungen in der Reihenfolge, in der sie im HTML stehen.</p>
                <p>
                    <a href="https://www.youtube.com/watch?v=lC6VO3ai8Bg" target="_blank" rel="noopener noreferrer" class="underline font-medium"><i class="fas fa-play-circle mr-1"></i>Video: So wird ein Screenreader verwendet</a>
                    &nbsp;·&nbsp;
                    <a href="https://www.w3.org/WAI/standards-guidelines/wcag/" target="_blank" rel="noopener noreferrer" class="underline">WCAG 2.2</a>
                </p>
            </div>

            <!-- URL-Formular -->
            <form method="post" class="flex gap-3 mb-5">
                <input type="url" name="url" placeholder="https://beispiel.de"
                       value="<?= htmlspecialchars($u ?? '') ?>"
                       required
                       class="flex-grow px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500 text-sm">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md text-sm whitespace-nowrap">
                    <i class="fas fa-search mr-1"></i>Analysieren
                </button>
            </form>

            <?php if ($r): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-5 text-sm">
                <i class="fas fa-exclamation-triangle mr-1"></i><?= htmlspecialchars($r) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($e)):
                $cntErr  = count(array_filter($e, fn($x) => $x['severity'] === 'error'));
                $cntWarn = count(array_filter($e, fn($x) => $x['severity'] === 'warning'));
                $cntInfo = count(array_filter($e, fn($x) => $x['severity'] === 'info'));
                $cntOk   = count(array_filter($e, fn($x) => $x['severity'] === 'ok'));
            ?>

            <!-- Statistik -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center mb-5">
                <div class="bg-gray-50 p-3 rounded-lg border">
                    <div class="text-lg font-bold"><?= count($e) ?></div>
                    <div class="text-xs text-gray-500">Elemente</div>
                </div>
                <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                    <div class="text-lg font-bold text-red-600"><?= $cntErr ?></div>
                    <div class="text-xs text-red-500">Fehler</div>
                </div>
                <div class="bg-amber-50 p-3 rounded-lg border border-amber-200">
                    <div class="text-lg font-bold text-amber-600"><?= $cntWarn ?></div>
                    <div class="text-xs text-amber-500">Warnungen</div>
                </div>
                <div class="bg-green-50 p-3 rounded-lg border border-green-200">
                    <div class="text-lg font-bold text-green-600"><?= $cntOk ?></div>
                    <div class="text-xs text-green-500">OK</div>
                </div>
            </div>

            <!-- Filter + Auto-Play -->
            <div class="flex flex-wrap gap-2 mb-5 items-center">
                <button id="btn-filter-all"     onclick="filterElements('all')"     class="filter-btn px-3 py-1 rounded text-sm">Alle</button>
                <button id="btn-filter-link"    onclick="filterElements('link')"    class="filter-btn px-3 py-1 rounded text-sm"><i class="fas fa-link mr-1"></i>Links</button>
                <button id="btn-filter-image"   onclick="filterElements('image')"   class="filter-btn px-3 py-1 rounded text-sm"><i class="fas fa-image mr-1"></i>Bilder</button>
                <button id="btn-filter-heading" onclick="filterElements('heading')" class="filter-btn px-3 py-1 rounded text-sm"><i class="fas fa-heading mr-1"></i>Überschriften</button>
                <button id="btn-filter-errors"  onclick="filterElements('errors')"  class="filter-btn px-3 py-1 rounded text-sm"><i class="fas fa-exclamation-circle mr-1"></i>Nur Fehler</button>
                <div class="ml-auto flex gap-2 items-center">
                    <label class="text-xs text-gray-500">Pause (s)</label>
                    <input type="number" id="autoplay-speed" value="2" min="1" max="10"
                           class="w-14 px-2 py-1 border border-gray-300 rounded text-sm text-center">
                    <button id="btn-autoplay" onclick="toggleAutoPlay()"
                            class="px-4 py-1.5 rounded text-sm bg-green-600 hover:bg-green-700 text-white font-medium">
                        <i class="fas fa-play mr-1"></i>Auto-Play
                    </button>
                </div>
            </div>

            <!-- Element-Liste -->
            <ul class="space-y-2" id="elements-list">
                <?php foreach ($e as $i => $m):
                    $sev = $m['severity'] ?? 'ok';
                    $sevIcon = match($sev) {
                        'error'   => '<i class="fas fa-times-circle text-red-500 flex-shrink-0"></i>',
                        'warning' => '<i class="fas fa-exclamation-triangle text-amber-500 flex-shrink-0"></i>',
                        'info'    => '<i class="fas fa-info-circle text-blue-400 flex-shrink-0"></i>',
                        default   => '<i class="fas fa-check-circle text-green-400 flex-shrink-0"></i>',
                    };
                    $typeLabel = match($m['type']) {
                        'heading'      => 'Überschrift ' . strtoupper($m['tag']),
                        'link'         => 'Link',
                        'image'        => 'Bild',
                        'button'       => 'Schaltfläche',
                        'form-control' => 'Eingabe',
                        'navigation'   => 'Navigation',
                        'main-content' => 'Hauptinhalt',
                        'header'       => 'Kopfbereich',
                        'footer'       => 'Fußbereich',
                        default        => $m['type'],
                    };
                    $typeColor = match($m['type']) {
                        'heading'      => 'bg-purple-100 text-purple-700',
                        'link'         => 'bg-blue-100 text-blue-700',
                        'image'        => 'bg-teal-100 text-teal-700',
                        'button'       => 'bg-orange-100 text-orange-700',
                        'form-control' => 'bg-pink-100 text-pink-700',
                        'navigation'   => 'bg-gray-200 text-gray-700',
                        default        => 'bg-gray-100 text-gray-600',
                    };
                ?>
                <li class="element-item sev-<?= $sev ?> border border-gray-200 rounded-md p-3 cursor-pointer hover:bg-gray-50 focus:bg-gray-50 transition-colors"
                    tabindex="0"
                    data-index="<?= $i ?>"
                    data-type="<?= htmlspecialchars($m['type']) ?>"
                    data-sev="<?= $sev ?>"
                    style="margin-left:<?= min($m['level'] * 14, 72) ?>px">
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-0.5 rounded-full <?= $typeColor ?> font-medium whitespace-nowrap">
                            <?= htmlspecialchars($typeLabel) ?>
                        </span>
                        <?php if (!empty($m['landmarkLabel'])): ?>
                        <span class="text-xs text-gray-400 truncate"><i class="fas fa-map-marker-alt mr-0.5"></i><?= htmlspecialchars($m['landmarkLabel']) ?></span>
                        <?php endif; ?>
                        <span class="ml-auto"><?= $sevIcon ?></span>
                    </div>
                    <div class="text-gray-600 text-xs mt-1.5 line-clamp-2 font-mono leading-snug">
                        <?= htmlspecialchars($m['announcement']) ?>
                    </div>
                    <?php if (!empty($m['issues'])): ?>
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        <?php foreach ($m['issues'] as $issue): ?>
                        <span class="text-xs px-1.5 py-0.5 rounded <?= match($issue['sev']) { 'error' => 'bg-red-100 text-red-700', 'warning' => 'bg-amber-100 text-amber-700', default => 'bg-blue-100 text-blue-700' } ?>">
                            <?= htmlspecialchars($issue['label']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($m['type'] === 'image' && !empty($m['attributes']['src'])): ?>
                    <img src="<?= htmlspecialchars(getFullImageUrl($m['attributes']['src'], $u)) ?>"
                         alt="<?= htmlspecialchars($m['attributes']['alt'] ?? '') ?>"
                         class="mt-2 max-h-12 object-contain rounded opacity-70"
                         onerror="this.style.display='none'">
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php endif; ?>
        </div>
    </div>

    <!-- ===== RECHTES PANEL ===== -->
    <div class="bg-white shadow-lg overflow-y-auto sticky top-0 flex flex-col h-screen border-l border-gray-200">
        <div class="p-5 flex-grow">
            <h2 class="text-sm font-semibold mb-4 text-gray-600 uppercase tracking-wider">
                <i class="fas fa-headphones mr-1.5 text-blue-500"></i>Screenreader-Perspektive
            </h2>
            <div id="preview">
                <div class="text-center pt-10 text-gray-400 text-sm">
                    <i class="fas fa-hand-pointer text-4xl mb-3 block text-gray-300"></i>
                    Element auswählen oder<br>Auto-Play starten
                    <div class="mt-6 text-left bg-gray-50 rounded-lg p-4 text-xs text-gray-500 space-y-1.5">
                        <div class="font-semibold text-gray-600 mb-2">Tastaturkürzel:</div>
                        <div><kbd>↑</kbd> <kbd>↓</kbd> Element vor/zurück</div>
                        <div><kbd>Tab</kbd> wie echter Screenreader vorwärts</div>
                        <div><kbd>P</kbd> Auto-Play starten/stoppen</div>
                        <div><kbd>H</kbd> nur Überschriften · <kbd>L</kbd> nur Links</div>
                        <div><kbd>B</kbd> nur Bilder · <kbd>E</kbd> nur Fehler</div>
                        <div><kbd>A</kbd> alle · <kbd>Esc</kbd> zurücksetzen</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>
<script>
const elements = <?= !empty($e) ? json_encode($e, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '[]' ?>;
let currentIndex  = -1;
let speaking      = false;
let autoPlayTimer = null;
let autoPlaying   = false;

const preview     = document.getElementById('preview');
const synth       = window.speechSynthesis;
const ttsCheck    = document.getElementById('tts');
const btnAutoPlay = document.getElementById('btn-autoplay');
const speedInput  = document.getElementById('autoplay-speed');

// ── Toast ──────────────────────────────────────────────────────────────────
let toastTimer = null;
function showToast(msg, type, ms) {
    type = type || 'info';
    ms   = ms   || 5000;
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast toast-' + type + ' show';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function() { t.className = 'toast'; }, ms);
}

// ── Filter ─────────────────────────────────────────────────────────────────
function filterElements(type) {
    document.querySelectorAll('.filter-btn').forEach(function(b) {
        b.className = 'filter-btn px-3 py-1 rounded text-sm bg-gray-200 hover:bg-gray-300 text-gray-700';
    });
    var active = document.getElementById('btn-filter-' + (type === 'errors' ? 'errors' : type));
    if (active) {
        active.className = 'filter-btn px-3 py-1 rounded text-sm ' + (type === 'errors' ? 'bg-red-600 text-white' : 'bg-gray-700 text-white');
    }
    document.querySelectorAll('.element-item').forEach(function(item) {
        var show = type === 'all'
            || item.dataset.type === type
            || (type === 'errors' && (item.dataset.sev === 'error' || item.dataset.sev === 'warning'));
        item.style.display = show ? '' : 'none';
    });
    var cur = document.querySelector('[data-index="' + currentIndex + '"]');
    if (cur && cur.style.display === 'none') {
        preview.innerHTML = '<div class="text-center pt-10 text-gray-400 text-sm"><i class="fas fa-hand-pointer text-3xl mb-2 block text-gray-300"></i>Element auswählen</div>';
        currentIndex = -1;
    }
}
filterElements('all');

// ── Auto-Play ──────────────────────────────────────────────────────────────
function toggleAutoPlay() {
    if (autoPlaying) { stopAutoPlay(); } else { startAutoPlay(); }
}

function startAutoPlay() {
    var visible = getVisible();
    if (visible.length === 0) { showToast('Keine sichtbaren Elemente vorhanden.', 'info'); return; }
    autoPlaying = true;
    btnAutoPlay.innerHTML = '<i class="fas fa-stop mr-1"></i>Stop';
    btnAutoPlay.classList.replace('bg-green-600', 'bg-red-600');
    btnAutoPlay.classList.replace('hover:bg-green-700', 'hover:bg-red-700');

    var startPos = 0;
    if (currentIndex >= 0) {
        var pos = visible.findIndex(function(el) { return parseInt(el.dataset.index) === currentIndex; });
        if (pos >= 0 && pos < visible.length - 1) startPos = pos + 1;
    }
    showToast('Auto-Play gestartet – simuliert Screenreader-Navigation Element für Element.', 'info', 3500);
    autoStep(visible, startPos);
}

function stopAutoPlay() {
    autoPlaying = false;
    clearTimeout(autoPlayTimer);
    synth.cancel();
    btnAutoPlay.innerHTML = '<i class="fas fa-play mr-1"></i>Auto-Play';
    btnAutoPlay.classList.replace('bg-red-600', 'bg-green-600');
    btnAutoPlay.classList.replace('hover:bg-red-700', 'hover:bg-green-700');
}

function autoStep(visible, pos) {
    if (!autoPlaying || pos >= visible.length) {
        stopAutoPlay();
        if (pos >= visible.length) showToast('Auto-Play beendet – alle Elemente wurden durchlaufen.', 'info', 3000);
        return;
    }
    var idx   = parseInt(visible[pos].dataset.index);
    var el    = elements[idx];
    showElement(idx, true);
    var pause = parseFloat(speedInput ? speedInput.value : '2') * 1000;

    if (ttsCheck && ttsCheck.checked) {
        speakElement(idx, function() {
            if (el && el.issues && el.issues.length > 0) showFlowToast(el);
            autoPlayTimer = setTimeout(function() { autoStep(visible, pos + 1); }, 500);
        });
    } else {
        if (el && el.issues && el.issues.length > 0) showFlowToast(el);
        autoPlayTimer = setTimeout(function() { autoStep(visible, pos + 1); }, pause);
    }
}

function showFlowToast(el) {
    var top = el.issues[0];
    if (!top) return;
    var icons = { error: '🔴', warning: '🟡', info: '🔵' };
    showToast((icons[top.sev] || '⚪') + ' ' + top.label + ': ' + top.detail, top.sev, 6500);
}

// ── Helfer ─────────────────────────────────────────────────────────────────
function getVisible() {
    return Array.from(document.querySelectorAll('.element-item')).filter(function(el) { return el.style.display !== 'none'; });
}

function escHtml(str) {
    return String(str == null ? '' : str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function resolveUrl(src) {
    if (!src) return '';
    try { new URL(src); return src; } catch(e) {}
    var base = document.querySelector('input[name="url"]') ? document.querySelector('input[name="url"]').value : '';
    try {
        var u = new URL(base);
        return src.startsWith('/') ? u.protocol + '//' + u.host + src : u.protocol + '//' + u.host + u.pathname.split('/').slice(0,-1).join('/') + '/' + src;
    } catch(e) { return src; }
}

// ── showElement ────────────────────────────────────────────────────────────
function showElement(index, scrollTo) {
    var el = elements[index];
    if (!el) return;
    scrollTo = scrollTo !== false;

    document.querySelectorAll('.element-item').forEach(function(e) { e.classList.remove('el-active','autoplay-hl'); });
    var li = document.querySelector('[data-index="' + index + '"]');
    if (li) {
        li.classList.add(autoPlaying ? 'autoplay-hl' : 'el-active');
        if (scrollTo) li.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    currentIndex = index;

    var sevColors = {
        error:   'bg-red-50 border-red-200 text-red-900',
        warning: 'bg-amber-50 border-amber-200 text-amber-900',
        info:    'bg-blue-50 border-blue-200 text-blue-900',
        ok:      'bg-green-50 border-green-200 text-green-800'
    };
    var sevIcons = {
        error:   'fas fa-times-circle text-red-500',
        warning: 'fas fa-exclamation-triangle text-amber-500',
        info:    'fas fa-info-circle text-blue-400',
        ok:      'fas fa-check-circle text-green-500'
    };

    // Screenreader-Ausgabe (Terminal-Style)
    var html = '<div class="mb-4 rounded-lg bg-gray-900 text-green-300 p-4 font-mono text-sm leading-relaxed">'
        + '<div class="text-gray-500 text-xs uppercase tracking-wider mb-1"><i class="fas fa-volume-up mr-1"></i>Screenreader spricht:</div>'
        + '<div class="text-base">&ldquo;' + escHtml(el.announcement) + '&rdquo;</div>'
        + '</div>';

    // Landmark
    if (el.landmarkLabel) {
        html += '<div class="mb-3 text-xs text-gray-500 bg-gray-50 rounded px-3 py-1.5">'
            + '<i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>'
            + 'Position auf der Seite: <strong>' + escHtml(el.landmarkLabel) + '</strong>'
            + '</div>';
    }

    // Issues
    if (el.issues && el.issues.length > 0) {
        html += '<div class="mb-4 space-y-2">';
        for (var i = 0; i < el.issues.length; i++) {
            var issue = el.issues[i];
            var c   = sevColors[issue.sev] || sevColors.info;
            var ico = sevIcons[issue.sev]  || sevIcons.info;
            html += '<div class="p-3 rounded-lg border ' + c + '">'
                + '<div class="font-semibold text-sm mb-1"><i class="' + ico + ' mr-1"></i>' + escHtml(issue.label) + '</div>'
                + '<div class="text-sm">' + escHtml(issue.detail) + '</div>'
                + '</div>';
        }
        html += '</div>';
    } else {
        html += '<div class="mb-4 p-3 rounded-lg border bg-green-50 border-green-200 text-green-800 text-sm">'
            + '<i class="fas fa-check-circle mr-1"></i>Kein Problem erkannt – dieses Element ist gut zugänglich.'
            + '</div>';
    }

    // Kontextueller Tipp
    var tips = {
        heading:       'Screenreader-Nutzer springen mit der <kbd>H</kbd>-Taste von Überschrift zu Überschrift – ähnlich wie sehende Nutzer die Seite visuell scannen. Die Hierarchie muss lückenlos sein.',
        link:          'Links werden oft als Liste ohne Kontext vorgelesen. Der Linktext muss allein verständlich sein, ohne den umgebenden Satz zu kennen.',
        image:         'Bilder sind für Screenreader-Nutzer unsichtbar. Nur der Alt-Text entscheidet, ob das Bild Informationswert hat oder stumm bleibt.',
        button:        'Schaltflächen werden mit <kbd>Leertaste</kbd> oder <kbd>Enter</kbd> aktiviert. Die Beschriftung muss die Funktion eindeutig benennen.',
        navigation:    'Screenreader kündigen Landmark-Bereiche an. Nutzer können direkt dorthin springen, ohne alles durchlesen zu müssen.',
        'form-control': 'Jedes Eingabefeld braucht ein eindeutiges Label. Placeholder-Texte verschwinden beim Tippen und sind kein Ersatz.',
        header:        'Der Kopfbereich enthält oft Logo, Navigation und Suche – Elemente, die viele Nutzer mit <kbd>H</kbd> überspringen.',
        footer:        'Der Fußbereich enthält Kontakt, Impressum und rechtliche Hinweise – für viele ein wichtiger Anlaufpunkt.'
    };
    var tip = tips[el.type];
    if (tip) {
        html += '<div class="mb-4 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-sm text-yellow-900">'
            + '<i class="fas fa-lightbulb mr-1 text-yellow-500"></i>' + tip
            + '</div>';
    }

    // Bildvorschau
    if (el.type === 'image' && el.attributes && el.attributes.src) {
        var fullSrc  = resolveUrl(el.attributes.src);
        var altText  = el.attributes.alt;
        var altLabel = altText === undefined ? '<span class="text-red-600">(fehlt!)</span>' : (altText === '' ? '<em class="text-gray-400">(leer – dekorativ)</em>' : escHtml(altText));
        html += '<div class="mb-4">'
            + '<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2"><i class="fas fa-image mr-1"></i>Bild</p>'
            + '<div class="border border-gray-200 rounded p-2 bg-gray-50">'
            + '<img src="' + escHtml(fullSrc) + '" alt="' + escHtml(el.attributes.alt || '') + '" '
            + 'class="max-w-full h-auto max-h-40 rounded object-contain mx-auto block" '
            + 'onerror="this.nextElementSibling.style.display=\'block\';this.style.display=\'none\'">'
            + '<p style="display:none" class="text-gray-400 text-xs text-center py-3">Bild nicht ladbar</p>'
            + '<p class="text-xs text-gray-500 mt-2">Alt-Text: ' + altLabel + '</p>'
            + '</div></div>';
    }

    preview.innerHTML = html;
    if (ttsCheck && ttsCheck.checked && !autoPlaying) speakElement(index);
}

// ── Sprachausgabe ──────────────────────────────────────────────────────────
function speakElement(index, onEnd) {
    synth.cancel();
    var el = elements[index];
    if (!el) { if (onEnd) onEnd(); return; }
    var u   = new SpeechSynthesisUtterance(el.announcement);
    u.lang  = 'de-DE';
    u.onstart = function() { speaking = true; };
    u.onend   = function() { speaking = false; if (onEnd) onEnd(); };
    u.onerror = function() { speaking = false; if (onEnd) onEnd(); };
    synth.speak(u);
}

ttsCheck && ttsCheck.addEventListener('change', function() {
    if (!ttsCheck.checked) { synth.cancel(); }
    else if (currentIndex >= 0) speakElement(currentIndex);
});

// ── Klick / Enter ──────────────────────────────────────────────────────────
var list = document.getElementById('elements-list');
if (list) {
    list.addEventListener('click', function(ev) {
        var li = ev.target.closest('.element-item');
        if (!li) return;
        if (autoPlaying) stopAutoPlay();
        showElement(parseInt(li.dataset.index));
    });
    list.addEventListener('keydown', function(ev) {
        var li = ev.target.closest('.element-item');
        if (!li) return;
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            if (autoPlaying) stopAutoPlay();
            showElement(parseInt(li.dataset.index));
        }
    });
}

// ── Globale Tastatur ───────────────────────────────────────────────────────
document.addEventListener('keydown', function(ev) {
    if (ev.target && ev.target.closest && ev.target.closest('input')) return;
    var vis = getVisible();
    var cur = vis.findIndex(function(el) { return parseInt(el.dataset.index) === currentIndex; });

    if (ev.key === 'ArrowDown') {
        ev.preventDefault();
        if (cur < vis.length - 1) showElement(parseInt(vis[cur + 1].dataset.index));
    } else if (ev.key === 'ArrowUp') {
        ev.preventDefault();
        if (cur > 0) showElement(parseInt(vis[cur - 1].dataset.index));
    } else if (ev.key === 'Tab' && !ev.shiftKey && currentIndex >= 0) {
        ev.preventDefault();
        if (cur < vis.length - 1) showElement(parseInt(vis[cur + 1].dataset.index));
    } else if (ev.key === 'Tab' && ev.shiftKey && currentIndex >= 0) {
        ev.preventDefault();
        if (cur > 0) showElement(parseInt(vis[cur - 1].dataset.index));
    } else if (ev.key === ' ' && currentIndex >= 0 && ttsCheck && ttsCheck.checked) {
        ev.preventDefault();
        speakElement(currentIndex);
    } else if (ev.key === 'Escape') {
        stopAutoPlay();
        filterElements('all');
    } else {
        var k = ev.key.toLowerCase();
        if      (k === 'a') filterElements('all');
        else if (k === 'l') filterElements('link');
        else if (k === 'b') filterElements('image');
        else if (k === 'h') filterElements('heading');
        else if (k === 'e') filterElements('errors');
        else if (k === 'p') toggleAutoPlay();
    }
});

if (elements.length > 0) showElement(0);
</script>
