<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$u = $_POST['url'] ?? '';
$e = [];
$r = '';
$f = $_POST['filter'] ?? 'all'; // Neuer Filter-Parameter

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($u)) {
    try {
        $screader = new ScreenReaderEmu();
        $e = $screader->loadURL($u);
        
        // Filter anwenden
        if ($f !== 'all') {
            $e = array_filter($e, fn($item) => $item['type'] === $f);
        }
    } catch (Exception $ex) {
        $r = $ex->getMessage();
    }
}

function getFullImageUrl(string $src, string $baseUrl): string {
    if (empty($src) || empty($baseUrl)) {
        return '';
    }

    // Wenn die URL bereits vollständig ist
    if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
        return $src;
    }

    try {
        $baseUrlParts = parse_url($baseUrl);
        
        if (!$baseUrlParts || empty($baseUrlParts['host'])) {
            return $src;
        }

        $protocol = $baseUrlParts['scheme'] ?? 'https';
        $host = $baseUrlParts['host'];
        
        // Wenn der Pfad mit einem Slash beginnt
        if (str_starts_with($src, '/')) {
            return "{$protocol}://{$host}{$src}";
        }
        
        // Relativer Pfad
        $path = isset($baseUrlParts['path']) ? dirname($baseUrlParts['path']) : '';
        $path = $path === '/' ? '' : $path;
        
        return "{$protocol}://{$host}{$path}/{$src}";
    } catch (Exception $e) {
        return $src;
    }
}

class ScreenReaderEmu {
    private DOMDocument $domDocument;
    private DOMXPath $domXPath;
    private array $processedTexts = [];
    private string $baseUrl = '';





    public function loadURL(string $url): array {
        $this->processedTexts = [];
        $this->baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Ungültige URL");
        }

        $opts = [
            'http' => [
                'method' => "GET",
                'header' => "User-Agent: Mozilla/5.0\r\n",
                'follow_location' => 1,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($opts);
        $htmlContent = @file_get_contents($url, false, $context);

        if ($htmlContent === false) {
            throw new Exception("URL konnte nicht geladen werden");
        }

        $this->domDocument = new DOMDocument();
        @$this->domDocument->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'), 
            LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->domXPath = new DOMXPath($this->domDocument);
        
        return $this->analyzeContent();
    }

    private function analyzeContent(): array {
        $elements = [];
        $body = $this->domDocument->getElementsByTagName('body')->item(0);
        
        if ($body) {
            $this->processNode($body, $elements);
        }
        
        return $elements;
    }

    private function processNode(DOMNode $node, array &$elements, int $level = 0): void {
        if ($node->nodeType !== XML_ELEMENT_NODE) return;
        
        $tagName = strtolower($node->nodeName);
        if (in_array($tagName, ['script', 'style', 'noscript'])) return;

        $info = $this->extractNodeInfo($node, $level);
        if ($info) {
            $normalizedText = $this->normalizeText($info['text']);
            if ($this->isSignificantElement($info['type']) || 
                ($normalizedText !== '' && !in_array($normalizedText, $this->processedTexts, true))) {
                
                if ($normalizedText !== '' && $info['type'] !== 'image') {
                    $this->processedTexts[] = $normalizedText;
                }
                
                // Markiere spezielle Links
                if ($info['type'] === 'link') {
                    $keywords = ['mehr', 'erfahren', 'weiterlesen', 'hier'];
                    foreach ($keywords as $keyword) {
                        if (stripos($normalizedText, $keyword) !== false) {
                            $info['highlight'] = true;
                            break;
                        }
                    }
                }
                
                $elements[] = $info;
            }
        }

        if (!$node->hasChildNodes()) return;

        $childLevel = $level;
        if (!in_array($tagName, ['a', 'button', 'label'])) {
            $childLevel++;
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->nodeName === 'img') {
                $imgInfo = $this->extractNodeInfo($child, $childLevel);
                if ($imgInfo) $elements[] = $imgInfo;
            } else {
                $this->processNode($child, $elements, $childLevel);
            }
        }
    }

    private function isSignificantElement(string $type): bool {
        return in_array($type, [
            'heading', 'link', 'image', 'button', 'form-control',
            'navigation', 'main-content', 'header', 'footer', 'logo'
        ], true);
    }

    private function normalizeText(?string $text): string {
        return $text ? preg_replace('/\s+/', ' ', trim($text)) : '';
    }

    private function extractNodeInfo(DOMNode $node, int $level): ?array {
        $tagName = strtolower($node->nodeName);
        $textContent = $tagName === 'img' ? '' : trim($node->textContent);

        $info = [
            'tag' => $tagName,
            'level' => $level,
            'text' => $textContent,
            'html' => $this->domDocument->saveHTML($node),
            'attributes' => $this->extractAttributes($node)
        ];

        // Fix relative image URLs
        if ($tagName === 'img' && isset($info['attributes']['src'])) {
            $info['attributes']['src'] = $this->fixUrl($info['attributes']['src']);
        }

        $info['type'] = $this->determineElementType($tagName, $info['attributes']);
        $info['announcement'] = $this->createAnnouncement($info);

        return $info;
    }

    private function fixUrl(string $url): string {
        if (str_starts_with($url, 'data:')) return $url;
        if (str_starts_with($url, 'http')) return $url;
        
        return str_starts_with($url, '/') 
            ? $this->baseUrl . $url 
            : $this->baseUrl . '/' . $url;
    }

    private function extractAttributes(DOMNode $node): array {
        $attributes = [];
        if (!$node->hasAttributes()) return $attributes;

        foreach (['id', 'class', 'role', 'aria-label', 'alt', 'href', 'src', 'title', 'target', 'lang'] as $attr) {
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
                'navigation' => 'navigation',
                'banner' => 'header',
                'contentinfo' => 'footer',
                'main' => 'main-content',
                default => 'content'
            };
        }

        return match($tagName) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => 'heading',
            'a' => 'link',
            'img' => 'image',
            'button' => 'button',
            'input', 'select', 'textarea' => 'form-control',
            'nav' => 'navigation',
            'main' => 'main-content',
            'header' => 'header',
            'footer' => 'footer',
            default => 'content'
        };
    }

    private function createAnnouncement(array $info): string {
        $type = $info['type'];
        $text = $info['text'];
        $attributes = $info['attributes'];
        
        if (isset($attributes['aria-label'])) {
            $text = $attributes['aria-label'];
        }

        return match($type) {
            'heading' => sprintf("Überschrift Ebene %s: %s", 
                substr($info['tag'], 1), $text),
            'link' => $this->createLinkAnnouncement($text, $attributes),
            'image' => $this->createImageAnnouncement($attributes),
            'button' => sprintf("Schaltfläche: %s", 
                $text ?: ($attributes['aria-label'] ?? $attributes['title'] ?? 'Unbenannte Schaltfläche')),
            'navigation' => "Navigation beginnt",
            'form-control' => $this->createFormControlAnnouncement($text, $attributes),
            'header' => "Kopfbereich beginnt",
            'footer' => "Fußbereich beginnt",
            'main-content' => "Hauptinhalt beginnt",
            'logo' => "Logo" . ($text ? ": $text" : ''),
            default => $text
        };
    }

    private function createLinkAnnouncement(string $text, array $attributes): string {
        $href = $attributes['href'] ?? '#';
        $linkText = $text ?: ($attributes['aria-label'] ?? $attributes['title'] ?? 'Unbenannter Link');
        return $href === '#' ? "Link: $linkText" : "Link: $linkText, führt zu: $href";
    }

    private function createImageAnnouncement(array $attributes): string {
        $alt = $attributes['alt'] ?? '';
        $title = $attributes['title'] ?? '';
        $src = $attributes['src'] ?? '';
        $parentLink = $attributes['parentLink'] ?? '';
        
        $description = $alt ?: $title ?: basename($src);
        $announcement = "Bild: " . ($description ?: 'Keine Beschreibung verfügbar');
        
        return $parentLink ? "$announcement (Verlinkt zu: $parentLink)" : $announcement;
    }

    private function createFormControlAnnouncement(string $text, array $attributes): string {
        $inputType = $attributes['type'] ?? 'text';
        $label = $text ?: ($attributes['aria-label'] ?? $attributes['placeholder'] ?? '');
        return "Eingabefeld ($inputType)" . ($label ? ": $label" : '');
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
</head>
<body class="bg-gray-100 text-gray-800 h-screen">
    <div class="grid grid-cols-1 lg:grid-cols-[1fr,400px] h-screen">
        <!-- Left Panel -->
        <div class="overflow-y-auto p-6">
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h1 class="text-xl font-bold">KLXM ScreenReaderEmu</h1>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="tts" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-volume-up mr-1"></i>Sprachausgabe
                        </span>
                    </label>
                </div>

                <form method="post" class="flex gap-4 mb-6">
                    <input type="url" name="url" placeholder="https://beispiel.de" 
                           value="<?= htmlspecialchars($u ?? '') ?>"
                           required 
                           class="flex-grow px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500">
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md">
                            <i class="fas fa-search mr-2"></i>Analysieren
                    </button>
                </form>

                <!-- Filter -->
                <div class="flex gap-2 mb-6">
                    <button onclick="filterElements('all')" 
                            class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 text-sm">
                        Alle
                    </button>
                    <button onclick="filterElements('link')"
                            class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 text-sm">
                        <i class="fas fa-link mr-1"></i>Links
                    </button>
                    <button onclick="filterElements('image')"
                            class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 text-sm">
                        <i class="fas fa-image mr-1"></i>Bilder
                    </button>
                    <button onclick="filterElements('heading')"
                            class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 text-sm">
                        <i class="fas fa-heading mr-1"></i>Überschriften
                    </button>
                </div>

                <?php if (!empty($e)): ?>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-center mb-6">
                    <div class="bg-white p-4 rounded-lg shadow">
                        <i class="fas fa-layer-group text-blue-500 text-xl mb-2"></i>
                        <div class="text-lg font-bold"><?= count($e) ?></div>
                        <div>Elemente</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <i class="fas fa-heading text-blue-500 text-xl mb-2"></i>
                        <div class="text-lg font-bold">
                            <?= count(array_filter($e, fn($i) => $i['type'] === 'heading')) ?>
                        </div>
                        <div>Überschriften</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <i class="fas fa-link text-blue-500 text-xl mb-2"></i>
                        <div class="text-lg font-bold">
                            <?= count(array_filter($e, fn($i) => $i['type'] === 'link')) ?>
                        </div>
                        <div>Links</div>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <i class="fas fa-image text-blue-500 text-xl mb-2"></i>
                        <div class="text-lg font-bold">
                            <?= count(array_filter($e, fn($i) => $i['type'] === 'image')) ?>
                        </div>
                        <div>Bilder</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($r): ?>
                <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-6">
                    <?= htmlspecialchars($r) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($e)): ?>
                <ul class="space-y-4" id="elements-list">
                    <?php foreach ($e as $i => $m): ?>
                    <?php
                        $isMoreLink = false;
                        if ($m['type'] === 'link') {
                            $keywords = ['mehr', 'erfahren', 'weiterlesen', 'hier'];
                            $text = strtolower($m['text']);
                            foreach ($keywords as $keyword) {
                                if (str_contains($text, $keyword)) {
                                    $isMoreLink = true;
                                    break;
                                }
                            }
                        }
                    ?>
                    <li class="element-item border border-gray-300 rounded-md p-4 cursor-pointer hover:bg-gray-100 focus:bg-gray-100 <?= $isMoreLink ? 'border-orange-500' : '' ?>"
                        tabindex="0"
                        data-index="<?= $i ?>"
                        data-type="<?= htmlspecialchars($m['type']) ?>"
                        style="margin-left:<?= ($m['level'] * 20) ?>px">
                        <div class="flex items-center gap-2">
                            <span class="inline-block text-xs px-2 py-1 rounded-md <?= $isMoreLink ? 'bg-orange-100 text-orange-600' : 'bg-blue-100 text-blue-600' ?>">
                                <?= htmlspecialchars($m['type']) ?>
                            </span>
                            <?php if (!empty($m['attributes']['id'])): ?>
                            <span class="text-xs text-gray-500">
                                <i class="fas fa-hashtag"></i> <?= htmlspecialchars($m['attributes']['id']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-gray-500 text-sm mt-2 line-clamp-2">
                            <?= htmlspecialchars($m['announcement']) ?>
                        </div>
                        <?php if ($m['type'] === 'image' && !empty($m['attributes']['src'])): ?>
                        <img src="<?= getFullImageUrl($m['attributes']['src'], $u) ?>" 
                             alt="<?= htmlspecialchars($m['attributes']['alt'] ?? '') ?>"
                             class="mt-2 max-h-20 object-contain"
                             onerror="this.style.display='none'">
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="bg-white shadow-lg overflow-y-auto sticky top-0 flex flex-col h-screen">
            <div class="p-6 flex-grow">
                <h2 class="text-lg font-semibold mb-4">
                    <i class="fas fa-eye mr-2"></i>Vorschau
                </h2>
                <div id="preview" class="text-gray-600">Element auswählen</div>
            </div>
        </div>
    </div>
</body>
</html>
<script>
const elements = <?= !empty($e) ? json_encode($e, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '[]' ?>;
let currentIndex = -1;
let speaking = false;
let utterance = null;

const preview = document.getElementById('preview');
const speechSynthesis = window.speechSynthesis;
const ttsCheckbox = document.getElementById('tts');

// Filter functionality
function filterElements(type) {
    const items = document.querySelectorAll('.element-item');
    items.forEach(item => {
        if (type === 'all' || item.dataset.type === type) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });

    // Reset preview if current element is hidden
    const currentElement = document.querySelector(`[data-index="${currentIndex}"]`);
    if (currentElement?.style.display === 'none') {
        preview.innerHTML = 'Element auswählen';
        currentIndex = -1;
    }
}

function getFullImageUrl(src, baseUrl) {
    if (!src) return '';
    try {
        // Check if it's already a full URL
        new URL(src);
        return src;
    } catch {
        // If not, it's a relative URL
        const url = new URL(baseUrl);
        if (src.startsWith('/')) {
            // Absolute path
            return `${url.protocol}//${url.host}${src}`;
        } else {
            // Relative path
            const path = url.pathname.split('/').slice(0, -1).join('/');
            return `${url.protocol}//${url.host}${path}/${src}`;
        }
    }
}

function showElement(index) {
    const element = elements[index];
    if (!element) return;
    
    // Remove active class from all elements
    document.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
    
    const selectedElement = document.querySelector(`[data-index="${index}"]`);
    if (selectedElement) {
        selectedElement.classList.add('active');
        selectedElement.focus();
        selectedElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    let previewContent = `
        <div class="space-y-4">
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="font-semibold text-gray-700">
                    <i class="fas fa-comment-alt mr-2"></i>Screenreader:
                </p>
                <p class="mt-2">"${element.announcement}"</p>
            </div>`;

    // Image preview with full URL
    if (element.type === 'image' && element.attributes.src) {
        const fullImageUrl = getFullImageUrl(element.attributes.src, document.querySelector('input[name="url"]').value);
        previewContent += `
            <div class="space-y-2">
                <p class="font-semibold text-gray-700">
                    <i class="fas fa-image mr-2"></i>Bildvorschau:
                </p>
                <div class="border border-gray-200 rounded-lg p-2">
                    <img src="${fullImageUrl}" 
                         alt="${element.attributes.alt || ''}"
                         class="max-w-full h-auto rounded"
                         onerror="this.parentElement.innerHTML='<p class=\'text-red-500 text-sm\'><i class=\'fas fa-exclamation-triangle mr-1\'></i>Bild konnte nicht geladen werden</p>'">
                </div>
            </div>`;
    }

    // HTML preview
    previewContent += `
            <div class="space-y-2">
                <p class="font-semibold text-gray-700">
                    <i class="fas fa-code mr-2"></i>HTML:
                </p>
                <pre class="bg-gray-100 p-4 rounded-lg overflow-x-auto text-sm">${element.html?.replace(/</g, '&lt;').replace(/>/g, '&gt;') || ''}</pre>
            </div>
        </div>`;
    
    preview.innerHTML = previewContent;
    
    currentIndex = index;
    if (ttsCheckbox.checked) speakElement(index);
}

function speakElement(index) {
    if (!ttsCheckbox.checked) return;
    if (speaking) speechSynthesis.cancel();
    
    const element = elements[index];
    if (!element) return;
    
    utterance = new SpeechSynthesisUtterance(element.announcement);
    utterance.lang = 'de-DE';
    
    utterance.onstart = () => {
        speaking = true;
        const el = document.querySelector(`[data-index="${index}"]`);
        if (el) el.classList.add('bg-blue-50');
    };
    
    utterance.onend = () => {
        speaking = false;
        const el = document.querySelector(`[data-index="${index}"]`);
        if (el) el.classList.remove('bg-blue-50');
    };
    
    speechSynthesis.speak(utterance);
}

// Event Listeners
ttsCheckbox.addEventListener('change', () => {
    if (currentIndex >= 0) {
        if (ttsCheckbox.checked) {
            speakElement(currentIndex);
        } else {
            speechSynthesis.cancel();
        }
    }
});

document.querySelectorAll('.element-item').forEach(el => {
    el.addEventListener('click', () => {
        const index = parseInt(el.dataset.index);
        showElement(index);
    });
    
    el.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            const index = parseInt(el.dataset.index);
            showElement(index);
        }
    });
});

// Keyboard Navigation
document.addEventListener('keydown', ev => {
    if (ev.target.closest('input')) return;
    
    const visibleElements = Array.from(document.querySelectorAll('.element-item'))
        .filter(el => el.style.display !== 'none');
    
    const currentVisibleIndex = visibleElements.findIndex(el => 
        parseInt(el.dataset.index) === currentIndex
    );

    switch (ev.key) {
        case 'ArrowDown':
            ev.preventDefault();
            if (currentVisibleIndex < visibleElements.length - 1) {
                showElement(parseInt(visibleElements[currentVisibleIndex + 1].dataset.index));
            }
            break;
        case 'ArrowUp':
            ev.preventDefault();
            if (currentVisibleIndex > 0) {
                showElement(parseInt(visibleElements[currentVisibleIndex - 1].dataset.index));
            }
            break;
        case ' ':
            ev.preventDefault();
            if (currentIndex >= 0 && ttsCheckbox.checked) {
                speakElement(currentIndex);
            }
            break;
        case 'Escape':
            filterElements('all');
            break;
    }
});

// Hotkeys for filter
document.addEventListener('keydown', ev => {
    if (ev.target.closest('input')) return;
    
    switch (ev.key.toLowerCase()) {
        case 'a':
            filterElements('all');
            break;
        case 'l':
            filterElements('link');
            break;
        case 'b':
            filterElements('image');
            break;
        case 'h':
            filterElements('heading');
            break;
    }
});

// Initial element selection if elements exist
if (elements.length > 0) {
    showElement(0);
}
</script>
