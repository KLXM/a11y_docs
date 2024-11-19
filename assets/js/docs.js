$(document).on('rex:ready', function() {
    const $content = $('.content-wrapper');
    const $toc = $('#toc');
    const $searchInput = $('.docs-search');
    
    // Mermaid initialisieren
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
    
    // Inhaltsverzeichnis generieren
    function generateTOC() {
        const headings = $content.find('h1, h2, h3');
        const toc = $('<ul class="toc-list"></ul>');
        
        headings.each(function(index) {
            const $heading = $(this);
            const level = parseInt($heading.prop('tagName').charAt(1));
            const title = $heading.text();
            const id = 'heading-' + index;
            
            // ID zur Überschrift hinzufügen
            $heading.attr('id', id);
            
            // TOC-Eintrag erstellen
            const $li = $('<li>').css('margin-left', (level - 1) * 20 + 'px');
            const $link = $('<a>')
                .addClass('toc-link')
                .attr('href', '#' + id)
                .text(title);
            
            $li.append($link);
            toc.append($li);
        });
        
        $toc.html(toc);
        
        // Smooth Scroll für TOC-Links
        $('.toc-link').on('click', function(e) {
            e.preventDefault();
            const target = $($(this).attr('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 20
                }, 500);
            }
        });
    }
    
    // Aktiven Abschnitt markieren
    function updateActiveSection() {
        const scrollPosition = $(window).scrollTop();
        
        $('h1, h2, h3').each(function() {
            const $heading = $(this);
            const headingPosition = $heading.offset().top;
            
            if (headingPosition - 100 <= scrollPosition) {
                const id = $heading.attr('id');
                $('.toc-link').removeClass('active');
                $('.toc-link[href="#' + id + '"]').addClass('active');
            }
        });
    }
    
    // Suche mit mark.js
    let markInstance = new Mark($content[0]);
    let searchTimeout;
    
    function performSearch() {
        const searchTerm = $searchInput.val();
        
        // Reset previous marks
        markInstance.unmark();
        
        if (searchTerm) {
            markInstance.mark(searchTerm, {
                done: function(counter) {
                    // Scroll zum ersten Ergebnis
                    if (counter > 0) {
                        const firstMark = $('mark').first();
                        if (firstMark.length) {
                            $('html, body').animate({
                                scrollTop: firstMark.offset().top - 100
                            }, 500);
                        }
                    }
                }
            });
        }
    }
    
    // Event Listeners
    $searchInput.on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    });
    
    $(window).on('scroll', _.throttle(updateActiveSection, 100));
    
    // Initialisierung
    generateTOC();
    updateActiveSection();
    
    // Wenn ein Hash in der URL ist, dorthin scrollen
    if (window.location.hash) {
        const target = $(window.location.hash);
        if (target.length) {
            setTimeout(function() {
                $('html, body').scrollTop(target.offset().top - 20);
            }, 100);
        }
    }
});
