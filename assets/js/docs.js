$(document).on('rex:ready', function() {
    // Elemente selektieren
    const $searchInput = $('[data-search]');
    const $content = $('[data-content]');
    const $tocLinks = $('[data-toc-link]');
    
    // Intersection Observer für Überschriften
    const headings = $content.find('h1, h2, h3').get();
    let currentHeading = null;
    
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 1.0
    };
    
    const headingObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                currentHeading = entry.target;
                updateActiveLink(currentHeading.id);
            }
        });
    }, observerOptions);
    
    headings.forEach(heading => headingObserver.observe(heading));
    
    // Smooth Scroll für TOC Links
    $tocLinks.on('click', function(e) {
        e.preventDefault();
        const targetId = $(this).attr('href').slice(1);
        const $target = $('#' + targetId);
        
        if ($target.length) {
            $('html, body').animate({
                scrollTop: $target.offset().top
            }, 500);
            
            // URL aktualisieren ohne Scroll
            history.pushState(null, null, `#${targetId}`);
        }
    });
    
    // Live-Suche Implementierung
    let searchTimeout;
    $searchInput.on('input', function() {
        clearTimeout(searchTimeout);
        const $this = $(this);
        searchTimeout = setTimeout(() => {
            const searchTerm = $this.val().toLowerCase().trim();
            handleSearch(searchTerm);
        }, 200); // Debounce für Performance
    });
    
    function handleSearch(term) {
        // Alle durchsuchbaren Elemente
        const $searchableElements = $content.find('p, h1, h2, h3, h4, h5, h6, li, td, th');
        
        // Highlight zurücksetzen
        $('.search-highlight').each(function() {
            $(this).contents().unwrap();
        });
        
        if (!term) {
            // Wenn Suchfeld leer, alles wieder anzeigen
            $searchableElements.show().closest('section').show();
            return;
        }
        
        let hasResults = false;
        
        $searchableElements.each(function() {
            const $element = $(this);
            const text = $element.text().toLowerCase();
            const $parent = $element.closest('section').length ? 
                          $element.closest('section') : 
                          $element;
            
            if (text.includes(term)) {
                hasResults = true;
                $parent.show();
                
                // Text hervorheben
                const regex = new RegExp(`(${term})`, 'gi');
                $element.html($element.html().replace(
                    regex, 
                    '<span class="search-highlight">$1</span>'
                ));
            } else {
                if (!$parent.find(`*:not(h1, h2, h3):contains('${term}')`).length) {
                    $parent.hide();
                }
            }
        });
        
        // "Keine Ergebnisse" Nachricht
        const $noResults = $('.no-results-message');
        if (!hasResults) {
            if (!$noResults.length) {
                $('<div>')
                    .addClass('no-results-message')
                    .css({
                        textAlign: 'center',
                        padding: '2rem'
                    })
                    .text('Keine Ergebnisse gefunden')
                    .prependTo($content);
            }
        } else {
            $noResults.remove();
        }
    }
    
    function updateActiveLink(headingId) {
        // Aktiven Link in der Navigation aktualisieren
        $tocLinks.removeClass('active');
        $(`[href="#${headingId}"]`)
            .addClass('active')
            .get(0)?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
    }
    
    // Initialisierung von Mermaid Diagrammen
    if (typeof mermaid !== 'undefined') {
        mermaid.initialize({
            startOnLoad: true,
            theme: 'default',
            securityLevel: 'loose',
            themeVariables: {
                fontSize: '14px'
            }
        });
    }
    
    // Code-Block Syntax Highlighting
    if (typeof hljs !== 'undefined') {
        $('pre code').each(function(i, block) {
            hljs.highlightBlock(block);
        });
    }
    
    // Initial aktiven Abschnitt markieren (falls URL-Hash vorhanden)
    if (window.location.hash) {
        const initialId = window.location.hash.slice(1);
        updateActiveLink(initialId);
    }
});
