/**
 * Moduł odpowiedzialny za kolorowanie składni i manipulację kodem
 * Wersja rozszerzona z obsługą wielu języków i narzędzi
 */
(function($) {
    'use strict';
    
    // Globalna zmienna do przechowywania instancji
    window.GCACodeHighlighter = {
        initialized: false,
        prismLoaded: false,
        prismVersion: '1.29.0',
        supportedLanguages: [
            'markup', 'html', 'xml', 'svg', 'mathml', 'css', 'clike', 
            'javascript', 'js', 'abap', 'abnf', 'actionscript', 'ada', 
            'agda', 'al', 'antlr4', 'apacheconf', 'apex', 'apl', 
            'applescript', 'aql', 'arduino', 'arff', 'asciidoc', 'asm6502', 
            'aspnet', 'autohotkey', 'autoit', 'bash', 'shell', 'basic', 
            'batch', 'bbcode', 'birb', 'bison', 'bnf', 'brainfuck', 
            'brightscript', 'bro', 'bsl', 'c', 'csharp', 'cs', 'dotnet', 
            'cpp', 'cfscript', 'chaiscript', 'cil', 'clojure', 'cmake', 
            'cobol', 'coffeescript', 'concurnas', 'csp', 'coq', 'crystal', 
            'css-extras', 'csv', 'cypher', 'd', 'dart', 'dataweave', 'dax', 
            'dhall', 'diff', 'django', 'jinja2', 'dns-zone-file', 
            'docker', 'dockerfile', 'dot', 'ebnf', 'editorconfig', 'eiffel', 
            'ejs', 'elixir', 'elm', 'etlua', 'erb', 'erlang', 'excel-formula', 
            'xlsx', 'xls', 'fsharp', 'factor', 'false', 'firestore-security-rules', 
            'flow', 'fortran', 'ftl', 'gml', 'gap', 'gcode', 'gdscript', 
            'gedcom', 'gherkin', 'git', 'glsl', 'go', 'graphql', 'groovy', 
            'haml', 'handlebars', 'haskell', 'hs', 'haxe', 'hcl', 'hlsl', 
            'http', 'hpkp', 'hsts', 'ichigojam', 'icon', 'icu-message-format', 
            'idris', 'ignore', 'gitignore', 'inform7', 'ini', 'io', 'j', 
            'java', 'javadoc', 'javadoclike', 'javastacktrace', 'jexl', 
            'jolie', 'jq', 'jsdoc', 'js-extras', 'json', 'webmanifest', 
            'json5', 'jsonp', 'jsstacktrace', 'js-templates', 'julia', 
            'keyman', 'kotlin', 'kt', 'kts', 'kumir', 'latex', 'tex', 'context', 
            'latte', 'less', 'lilypond', 'liquid', 'lisp', 'livescript', 
            'llvm', 'log', 'lolcode', 'lua', 'makefile', 'markdown', 'md', 
            'markup-templating', 'matlab', 'mel', 'mizar', 'mongodb', 
            'monkey', 'moonscript', 'n1ql', 'n4js', 'nand2tetris-hdl', 
            'naniscript', 'nasm', 'neon', 'nevod', 'nginx', 'nim', 'nix', 
            'nsis', 'objectivec', 'objc', 'ocaml', 'opencl', 'openqasm', 
            'oz', 'parigp', 'parser', 'pascal', 'objectpascal', 'pascaligo', 
            'psl', 'pcaxis', 'peoplecode', 'perl', 'php', 'phpdoc', 'php-extras', 
            'plsql', 'powerquery', 'powershell', 'processing', 'prolog', 
            'promql', 'properties', 'protobuf', 'pug', 'puppet', 'pure', 
            'purebasic', 'purescript', 'python', 'py', 'q', 'qml', 'qore', 
            'r', 'racket', 'jsx', 'tsx', 'reason', 'regex', 'rego', 'renpy', 
            'rest', 'rip', 'roboconf', 'robotframework', 'ruby', 'rb', 
            'rust', 'sas', 'sass', 'scss', 'scala', 'scheme', 'shell-session', 
            'smali', 'smalltalk', 'smarty', 'sml', 'solidity', 'solution-file', 
            'soy', 'sparql', 'splunk-spl', 'sqf', 'sql', 'squirrel', 'stan', 
            'iecst', 'stylus', 'swift', 'systemd', 't4-templating', 't4-cs', 
            't4', 't4-vb', 'tap', 'tcl', 'tt2', 'textile', 'toml', 'turtle', 
            'twig', 'typescript', 'ts', 'typoscript', 'unrealscript', 'uri', 
            'url', 'v', 'vala', 'vbnet', 'velocity', 'verilog', 'vhdl', 'vim', 
            'visual-basic', 'vb', 'warpscript', 'wasm', 'wiki', 'wolfram', 
            'xeora', 'xml-doc', 'xojo', 'xquery', 'yaml', 'yml', 'yang', 'zig'
        ],
        
        init: function() {
            if (this.initialized) return;
            this.initialized = true;
            
            this.loadPrism();
            this.bindEvents();
            this.setupLineNumbersStyles();
            console.log('GCA Code Highlighter: Initialized');
        },
        
        loadPrism: function() {
            // Sprawdź, czy Prism.js został już załadowany
            if (window.Prism || this.prismLoaded) {
                this.prismLoaded = true;
                this.highlightAllCode();
                return;
            }
            
            // Wczytaj CSS Prism
            $('head').append('<link rel="stylesheet" href="' + gca_ajax.plugin_url + 'public/css/prism.css" type="text/css" />');
            
            // Wczytaj JS Prism
            $.getScript(gca_ajax.plugin_url + 'public/js/prism.js')
                .done(function() {
                    GCACodeHighlighter.prismLoaded = true;
                    GCACodeHighlighter.highlightAllCode();
                    console.log('GCA Code Highlighter: Prism.js loaded');
                })
                .fail(function(jqxhr, settings, exception) {
                    console.error('GCA Code Highlighter: Failed to load Prism.js', exception);
                });
        },
        
        setupLineNumbersStyles: function() {
            // Dodaj dodatkowe style dla numeracji linii
            $('head').append(`
                <style>
                    pre[class*=language-].line-numbers {
                        position: relative;
                        padding-left: 3.8em;
                        counter-reset: linenumber;
                    }
                    
                    pre[class*=language-].line-numbers > code {
                        position: relative;
                        white-space: inherit;
                    }
                    
                    .line-numbers .line-numbers-rows {
                        position: absolute;
                        pointer-events: none;
                        top: 0;
                        font-size: 100%;
                        left: -3.8em;
                        width: 3em;
                        letter-spacing: -1px;
                        border-right: 1px solid #999;
                        user-select: none;
                    }
                    
                    .line-numbers-rows > span {
                        display: block;
                        counter-increment: linenumber;
                    }
                    
                    .line-numbers-rows > span:before {
                        content: counter(linenumber);
                        color: #999;
                        display: block;
                        padding-right: 0.8em;
                        text-align: right;
                    }
                    
                    .line-numbers-rows > span:nth-child(even) {
                        background-color: rgba(0, 0, 0, 0.05);
                    }
                    
                    /* Ulepszone style przycisków */
                    .gca-code-toolbar .toolbar {
                        position: absolute;
                        top: 0.3em;
                        right: 0.2em;
                        transition: opacity 0.3s ease-in-out;
                        opacity: 0;
                        display: flex;
                        gap: 5px;
                    }
                    
                    .gca-code-toolbar:hover .toolbar {
                        opacity: 1;
                    }
                    
                    .gca-copy-code-btn,
                    .gca-download-code-btn,
                    .gca-toggle-line-numbers-btn {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding: 5px 10px !important;
                        color: white !important;
                        font-size: 12px !important;
                        background-color: #6366f1 !important;
                        border-radius: 4px !important;
                        cursor: pointer;
                        border: none;
                        transition: background-color 0.2s;
                    }
                    
                    .gca-copy-code-btn:hover,
                    .gca-download-code-btn:hover,
                    .gca-toggle-line-numbers-btn:hover {
                        background-color: #4f46e5 !important;
                    }
                    
                    .gca-copy-code-btn .dashicons,
                    .gca-download-code-btn .dashicons,
                    .gca-toggle-line-numbers-btn .dashicons {
                        font-size: 14px;
                        margin-right: 5px;
                    }
                    
                    /* Dodatkowe style dla powodzenia/niepowodzenia */
                    .gca-copy-success {
                        background-color: #10b981 !important;
                    }
                    
                    .gca-copy-error {
                        background-color: #ef4444 !important;
                    }
                    
                    /* Animacja kopiowania */
                    @keyframes gca-pulse {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.05); }
                        100% { transform: scale(1); }
                    }
                    
                    .gca-pulse {
                        animation: gca-pulse 0.3s ease-in-out;
                    }
                    
                    /* Dodatkowe style dla wyszukiwania w kodzie */
                    .gca-search-match {
                        background-color: rgba(255, 255, 0, 0.3);
                        outline: 1px solid rgba(255, 200, 0, 0.7);
                    }
                    
                    .gca-search-active-match {
                        background-color: rgba(255, 150, 0, 0.5);
                        outline: 2px solid rgba(255, 120, 0, 0.8);
                    }
                    
                    .gca-code-search-box {
                        position: absolute;
                        top: 3em;
                        right: 0.2em;
                        display: none;
                        background: white;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                        z-index: 100;
                        padding: 8px;
                    }
                    
                    .gca-code-search-input {
                        padding: 4px 8px;
                        width: 180px;
                        border: 1px solid #ddd;
                        border-radius: 3px;
                        margin-right: 5px;
                    }
                    
                    .gca-code-search-controls {
                        display: flex;
                        align-items: center;
                        margin-top: 5px;
                        gap: 5px;
                    }
                    
                    .gca-code-search-btn {
                        background: #f3f4f6;
                        border: 1px solid #d1d5db;
                        border-radius: 3px;
                        padding: 2px 5px;
                        cursor: pointer;
                        font-size: 12px;
                    }
                    
                    .gca-search-count {
                        margin-left: auto;
                        font-size: 12px;
                        color: #666;
                    }
                </style>
            `);
        },
        
        bindEvents: function() {
            // Obsługa przycisków kopiowania kodu
            $(document).on('click', '.gca-copy-code-btn', function(e) {
                e.preventDefault();
                const button = $(this);
                const codeBlock = $(this).closest('.code-toolbar').find('pre code');
                
                GCACodeHighlighter.copyToClipboard(codeBlock.text())
                    .then(() => {
                        // Zmień tekst przycisku tymczasowo i dodaj klasę sukcesu
                        const originalText = button.html();
                        const originalBg = button.css('background-color');
                        
                        button.html('<i class="dashicons dashicons-yes"></i> Skopiowano!');
                        button.addClass('gca-copy-success gca-pulse');
                        
                        setTimeout(function() {
                            button.html(originalText);
                            button.removeClass('gca-copy-success gca-pulse');
                        }, 2000);
                    })
                    .catch(err => {
                        // Obsługa błędu
                        const originalText = button.html();
                        
                        button.html('<i class="dashicons dashicons-no"></i> Błąd!');
                        button.addClass('gca-copy-error gca-pulse');
                        
                        console.error('Copy error:', err);
                        
                        setTimeout(function() {
                            button.html(originalText);
                            button.removeClass('gca-copy-error gca-pulse');
                        }, 2000);
                    });
            });
            
            // Obsługa przycisku pobierania kodu
            $(document).on('click', '.gca-download-code-btn', function(e) {
                e.preventDefault();
                const button = $(this);
                const codeBlock = $(this).closest('.code-toolbar').find('pre code');
                const fileName = $(this).data('filename') || 'code.' + GCACodeHighlighter.getFileExtension(codeBlock.attr('class'));
                
                try {
                    GCACodeHighlighter.downloadCode(codeBlock.text(), fileName);
                    
                    // Animacja sukcesu
                    button.addClass('gca-pulse');
                    setTimeout(function() {
                        button.removeClass('gca-pulse');
                    }, 300);
                } catch (error) {
                    console.error('Download error:', error);
                    alert('Nie udało się pobrać pliku: ' + error.message);
                }
            });
            
            // Obsługa przycisku przełączania numeracji linii
            $(document).on('click', '.gca-toggle-line-numbers-btn', function(e) {
                e.preventDefault();
                const preElement = $(this).closest('.code-toolbar').find('pre');
                const button = $(this);
                
                if (preElement.hasClass('line-numbers')) {
                    preElement.removeClass('line-numbers');
                    button.html('<i class="dashicons dashicons-editor-ol"></i> Pokaż numery linii');
                } else {
                    preElement.addClass('line-numbers');
                    button.html('<i class="dashicons dashicons-editor-ol"></i> Ukryj numery linii');
                }
            });
            
            // Dodaj obsługę przycisku wyszukiwania w kodzie
            $(document).on('click', '.gca-search-code-btn', function(e) {
                e.preventDefault();
                const toolbarContainer = $(this).closest('.code-toolbar');
                const searchBox = toolbarContainer.find('.gca-code-search-box');
                
                if (searchBox.is(':visible')) {
                    searchBox.hide();
                } else {
                    $('.gca-code-search-box').hide(); // Ukryj wszystkie widoczne pola wyszukiwania
                    searchBox.show();
                    searchBox.find('input').focus();
                }
            });
            
            // Obsługa wyszukiwania w kodzie
            $(document).on('input', '.gca-code-search-input', function() {
                const searchTerm = $(this).val();
                const codeToolbar = $(this).closest('.code-toolbar');
                const codeBlock = codeToolbar.find('pre code');
                
                if (searchTerm.length < 2) {
                    // Usuń wszystkie zaznaczenia
                    GCACodeHighlighter.clearSearchHighlights(codeBlock);
                    codeToolbar.find('.gca-search-count').text('');
                    return;
                }
                
                // Wykonaj wyszukiwanie
                GCACodeHighlighter.searchInCode(codeBlock, searchTerm);
            });
            
            // Obsługa przycisków nawigacji w wynikach wyszukiwania
            $(document).on('click', '.gca-search-next-btn', function() {
                const codeToolbar = $(this).closest('.code-toolbar');
                GCACodeHighlighter.navigateSearchResults(codeToolbar, 'next');
            });
            
            $(document).on('click', '.gca-search-prev-btn', function() {
                const codeToolbar = $(this).closest('.code-toolbar');
                GCACodeHighlighter.navigateSearchResults(codeToolbar, 'prev');
            });
            
            // Zamknij pole wyszukiwania na kliknięcie poza
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.gca-code-search-box, .gca-search-code-btn').length) {
                    $('.gca-code-search-box').hide();
                }
            });
            
            // Obsługa wciśnięcia Escape - ukrycie pola wyszukiwania
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.gca-code-search-box').hide();
                }
            });
            
            // Obsługa klawisza Enter w polu wyszukiwania
            $(document).on('keydown', '.gca-code-search-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const codeToolbar = $(this).closest('.code-toolbar');
                    GCACodeHighlighter.navigateSearchResults(codeToolbar, 'next');
                }
            });
            
            // Obserwacja mutacji DOM, aby podświetlać nowe elementy kodu
            if (window.MutationObserver) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                            // Sprawdź, czy wśród dodanych węzłów są elementy code
                            setTimeout(function() {
                                GCACodeHighlighter.highlightAllCode();
                            }, 100);
                        }
                    });
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        },
        
        highlightAllCode: function() {
            if (!this.prismLoaded || !window.Prism) return;
            
            // Znajdź wszystkie bloki kodu bez wrapperów
            $('.gca-claude-response pre > code:not(.prism-highlighted)').each(function() {
                const $this = $(this);
                let language = '';
                
                // Sprawdź, czy język jest już określony w klasie
                if ($this.attr('class') && $this.attr('class').includes('language-')) {
                    language = $this.attr('class').match(/language-(\w+)/)[1];
                } else {
                    // Spróbuj wykryć język na podstawie zawartości lub kontekstu
                    language = GCACodeHighlighter.detectLanguage($this.text(), $this.closest('.gca-file-item').text());
                    $this.addClass('language-' + language);
                }
                
                // Dodaj numerację linii i przyciski
                const $pre = $this.parent('pre');
                
                if (!$pre.parent().hasClass('code-toolbar')) {
                    // Wyciągnij nazwę pliku, jeśli jest dostępna
                    let fileName = '';
                    const fileNameMatch = $pre.prevAll().text().match(/PLIK:\s*([^\n]+)/);
                    if (fileNameMatch && fileNameMatch[1]) {
                        fileName = fileNameMatch[1].trim();
                    }
                    
                    // Dodaj toolbar z przyciskami
                    $pre.wrap('<div class="code-toolbar gca-code-toolbar"></div>');
                    const toolbar = $('<div class="toolbar"></div>');
                    
                    // Dodaj przycisk kopiowania
                    toolbar.append('<button class="gca-copy-code-btn toolbar-item" title="Kopiuj kod"><i class="dashicons dashicons-clipboard"></i> Kopiuj</button>');
                    
                    // Dodaj przycisk pobierania, jeśli znamy nazwę pliku
                    if (fileName) {
                        toolbar.append('<button class="gca-download-code-btn toolbar-item" data-filename="' + fileName + '" title="Pobierz jako plik"><i class="dashicons dashicons-download"></i> Pobierz</button>');
                    } else {
                        // Jeśli nie mamy nazwy pliku, generujemy ją na podstawie języka
                        const ext = GCACodeHighlighter.getFileExtension(language);
                        const generatedFilename = 'code.' + ext;
                        toolbar.append('<button class="gca-download-code-btn toolbar-item" data-filename="' + generatedFilename + '" title="Pobierz jako plik"><i class="dashicons dashicons-download"></i> Pobierz</button>');
                    }
                    
                    // Dodaj przycisk przełączania numeracji linii
                    toolbar.append('<button class="gca-toggle-line-numbers-btn toolbar-item" title="Przełącz numerację linii"><i class="dashicons dashicons-editor-ol"></i> Ukryj numery linii</button>');
                    
                    // Dodaj przycisk wyszukiwania w kodzie
                    toolbar.append('<button class="gca-search-code-btn toolbar-item" title="Wyszukaj w kodzie"><i class="dashicons dashicons-search"></i> Szukaj</button>');
                    
                    // Dodaj pole wyszukiwania (domyślnie ukryte)
                    const searchBox = $(`
                        <div class="gca-code-search-box">
                            <input type="text" class="gca-code-search-input" placeholder="Szukaj w kodzie...">
                            <div class="gca-code-search-controls">
                                <button class="gca-code-search-btn gca-search-prev-btn" title="Poprzedni">
                                    <i class="dashicons dashicons-arrow-up-alt2"></i>
                                </button>
                                <button class="gca-code-search-btn gca-search-next-btn" title="Następny">
                                    <i class="dashicons dashicons-arrow-down-alt2"></i>
                                </button>
                                <span class="gca-search-count"></span>
                            </div>
                        </div>
                    `);
                    
                    $pre.after(toolbar);
                    $pre.after(searchBox);
                    
                    // Dodaj klasę dla numeracji linii
                    $pre.addClass('line-numbers');
                }
                
                // Oznacz jako już podświetlony
                $this.addClass('prism-highlighted');
                
                // Uruchom Prism.js na tym elemencie
                try {
                    Prism.highlightElement(this);
                } catch (error) {
                    console.error('Highlighting error:', error, this);
                }
            });
        },
        
        detectLanguage: function(code, filename) {
            // Wykryj język na podstawie rozszerzenia pliku
            if (filename) {
                const extension = filename.split('.').pop().toLowerCase();
                
                // Mapowanie rozszerzeń plików na języki Prism
                const extensionMap = {
                    'php': 'php',
                    'js': 'javascript',
                    'jsx': 'jsx',
                    'ts': 'typescript',
                    'tsx': 'tsx',
                    'css': 'css',
                    'scss': 'scss',
                    'less': 'less',
                    'html': 'html',
                    'htm': 'html',
                    'xml': 'xml',
                    'svg': 'svg',
                    'py': 'python',
                    'rb': 'ruby',
                    'java': 'java',
                    'kt': 'kotlin',
                    'kts': 'kotlin',
                    'go': 'go',
                    'c': 'c',
                    'cpp': 'cpp',
                    'h': 'c',
                    'hpp': 'cpp',
                    'cs': 'csharp',
                    'json': 'json',
                    'md': 'markdown',
                    'sql': 'sql',
                    'sh': 'bash',
                    'bash': 'bash',
                    'yml': 'yaml',
                    'yaml': 'yaml',
                    'swift': 'swift',
                    'dart': 'dart',
                    'rs': 'rust',
                    'vue': 'javascript',
                    'pl': 'perl',
                    'r': 'r',
                    'scala': 'scala',
                    'lua': 'lua',
                    'ex': 'elixir',
                    'elm': 'elm',
                    'clj': 'clojure',
                    'erl': 'erlang'
                };
                
                if (extensionMap[extension]) {
                    return extensionMap[extension];
                }
            }
            
            // Jeśli nie można wykryć na podstawie nazwy pliku, próbuj na podstawie zawartości
            if (code.includes('<?php')) return 'php';
            if (code.includes('package ') && code.includes('class ') && code.includes('{')) return 'java';
            if (code.includes('fun ') && (code.includes('val ') || code.includes('var '))) return 'kotlin';
            if (code.includes('import React') || code.includes('import {')) return 'jsx';
            if (code.includes('<!DOCTYPE html') || code.includes('<html')) return 'html';
            if (code.includes('@media') || code.includes('{') && code.includes('}') && code.includes(':')) return 'css';
            if (code.includes('import') && code.includes('from')) return 'javascript';
            if (code.includes('SELECT') && code.includes('FROM') && (code.includes('WHERE') || code.includes('JOIN'))) return 'sql';
            if (code.includes('def ') && code.includes(':') && !code.includes('{')) return 'python';
            if (code.includes('func ') && code.includes('package ')) return 'go';
            if (code.includes('#include') && (code.includes('<stdio.h>') || code.includes('<iostream>'))) return code.includes('class') ? 'cpp' : 'c';
            if (code.includes('namespace ') && code.includes('using ') && code.includes(';')) return 'csharp';
            
            // Sprawdzanie dla języków skryptowych
            if (code.includes('#!/bin/bash') || code.includes('#!/bin/sh')) return 'bash';
            if (code.includes('#!/usr/bin/perl') || code.includes('use strict') && code.includes(';')) return 'perl';
            if (code.includes('require') && code.includes('end') && code.includes('def ')) return 'ruby';
            
            // Próba wykrycia na podstawie składni
            if (code.includes(' -> ') && code.includes('=>')) return 'typescript';
            if (code.includes('"""') && code.includes('def ')) return 'python';
            if (code.includes('fun ') && code.includes('println')) return 'kotlin';
            if (code.includes('module ') && code.includes('where')) return 'haskell';
            
            // Domyślny język
            return 'clike';
        },
        
        getFileExtension: function(language) {
            const languageMap = {
                'php': 'php',
                'javascript': 'js',
                'js': 'js',
                'jsx': 'jsx',
                'typescript': 'ts',
                'ts': 'ts',
                'tsx': 'tsx',
                'css': 'css',
                'scss': 'scss',
                'less': 'less',
                'html': 'html',
                'xml': 'xml',
                'svg': 'svg',
                'python': 'py',
                'py': 'py',
                'ruby': 'rb',
                'java': 'java',
                'kotlin': 'kt',
               'go': 'go',
               'c': 'c',
               'cpp': 'cpp',
               'csharp': 'cs',
               'cs': 'cs',
               'json': 'json',
               'markdown': 'md',
               'md': 'md',
               'sql': 'sql',
               'bash': 'sh',
               'shell': 'sh',
               'yaml': 'yml',
               'swift': 'swift',
               'dart': 'dart',
               'rust': 'rs',
               'perl': 'pl',
               'r': 'r',
               'scala': 'scala',
               'lua': 'lua',
               'elixir': 'ex',
               'elm': 'elm',
               'clojure': 'clj',
               'erlang': 'erl',
               'haskell': 'hs'
           };
           
           if (language && language.includes('language-')) {
               language = language.replace('language-', '');
           }
           
           return languageMap[language] || 'txt';
       },
       
       copyToClipboard: function(text) {
           return new Promise((resolve, reject) => {
               // Użyj nowoczesnego API schowka, jeśli jest dostępne
               if (navigator.clipboard && window.isSecureContext) {
                   navigator.clipboard.writeText(text)
                       .then(() => resolve())
                       .catch(err => {
                           console.error('GCA Code Highlighter: Failed to copy using Clipboard API', err);
                           this.fallbackCopyToClipboard(text)
                               .then(() => resolve())
                               .catch(err => reject(err));
                       });
               } else {
                   this.fallbackCopyToClipboard(text)
                       .then(() => resolve())
                       .catch(err => reject(err));
               }
           });
       },
       
       fallbackCopyToClipboard: function(text) {
           return new Promise((resolve, reject) => {
               try {
                   // Metoda zapasowa do kopiowania tekstu
                   const textArea = document.createElement("textarea");
                   textArea.value = text;
                   
                   // Popraw styl dla kompatybilności z iOS
                   textArea.style.position = "fixed";
                   textArea.style.left = "-999999px";
                   textArea.style.top = "-999999px";
                   textArea.style.opacity = "0";
                   textArea.style.zIndex = "-1";
                   
                   document.body.appendChild(textArea);
                   
                   if (navigator.userAgent.match(/ipad|ipod|iphone/i)) {
                       // iOS wymaga specjalnej obsługi
                       const range = document.createRange();
                       range.selectNodeContents(textArea);
                       
                       const selection = window.getSelection();
                       selection.removeAllRanges();
                       selection.addRange(range);
                       textArea.setSelectionRange(0, 999999);
                   } else {
                       // Inne urządzenia
                       textArea.select();
                   }
                   
                   const successful = document.execCommand('copy');
                   document.body.removeChild(textArea);
                   
                   if (successful) {
                       resolve();
                   } else {
                       reject(new Error('Nie udało się skopiować tekstu'));
                   }
               } catch (err) {
                   console.error('GCA Code Highlighter: Fallback copy failed', err);
                   reject(err);
               }
           });
       },
       
       downloadCode: function(code, fileName) {
           // Dodaj obsługę kodowania znaków
           const blob = new Blob([code], { type: 'text/plain;charset=utf-8' });
           
           // Sprawdź, czy przeglądarką obsługuje API pobierania
           if ('download' in document.createElement('a')) {
               const url = URL.createObjectURL(blob);
               
               const a = document.createElement('a');
               a.style.display = 'none';
               a.href = url;
               a.download = fileName;
               
               document.body.appendChild(a);
               a.click();
               
               // Zwolnij URL obiektowy
               setTimeout(function() {
                   document.body.removeChild(a);
                   window.URL.revokeObjectURL(url);
               }, 100);
           } else {
               // Dla starszych przeglądarek
               if (window.navigator.msSaveBlob) {
                   // IE10+
                   window.navigator.msSaveBlob(blob, fileName);
               } else {
                   // Ostatnia opcja: otwórz w nowym oknie
                   const url = URL.createObjectURL(blob);
                   window.open(url, '_blank');
                   
                   setTimeout(function() {
                       window.URL.revokeObjectURL(url);
                   }, 100);
               }
           }
       },
       
       // Nowe funkcje do wyszukiwania w kodzie
       searchInCode: function(codeElement, searchTerm) {
           // Najpierw usuń poprzednie zaznaczenia
           this.clearSearchHighlights(codeElement);
           
           if (!searchTerm || searchTerm.length < 2) return;
           
           const codeToolbar = codeElement.closest('.code-toolbar');
           const codeContent = codeElement.text();
           const searchTermLower = searchTerm.toLowerCase();
           
           // Znajdź wszystkie wystąpienia
           let startIndex = 0;
           const indices = [];
           
           while (startIndex < codeContent.length) {
               const index = codeContent.toLowerCase().indexOf(searchTermLower, startIndex);
               if (index === -1) break;
               
               indices.push(index);
               startIndex = index + searchTermLower.length;
           }
           
           if (indices.length === 0) {
               codeToolbar.find('.gca-search-count').text('Nie znaleziono');
               return;
           }
           
           // Zaznacz wszystkie wystąpienia
           const codeHTML = codeElement.html();
           let newHTML = '';
           let lastIndex = 0;
           
           indices.forEach((index, i) => {
               newHTML += codeHTML.substring(lastIndex, index);
               newHTML += `<span class="gca-search-match" data-match-index="${i}">${codeHTML.substring(index, index + searchTerm.length)}</span>`;
               lastIndex = index + searchTerm.length;
           });
           
           newHTML += codeHTML.substring(lastIndex);
           codeElement.html(newHTML);
           
           // Aktualizuj licznik wyszukiwania
           codeToolbar.find('.gca-search-count').text(`1 z ${indices.length}`);
           
           // Zaznacz pierwszy wynik
           codeToolbar.find('.gca-search-match').first().addClass('gca-search-active-match');
           
           // Przewiń do zaznaczonego wyniku
           this.scrollToActiveMatch(codeElement);
           
           // Zapisz dane wyszukiwania
           codeElement.data('searchMatches', indices.length);
           codeElement.data('currentMatch', 0);
       },
       
       clearSearchHighlights: function(codeElement) {
           // Pobierz zawartość kodu
           const originalCode = codeElement.html()
               .replace(/<span class="gca-search-match( gca-search-active-match)?" data-match-index="\d+">(.*?)<\/span>/g, '$2');
           
           // Przywróć oryginalny kod
           codeElement.html(originalCode);
           
           // Usuń dane wyszukiwania
           codeElement.removeData('searchMatches');
           codeElement.removeData('currentMatch');
       },
       
       navigateSearchResults: function(codeToolbar, direction) {
           const codeElement = codeToolbar.find('pre code');
           const totalMatches = codeElement.data('searchMatches');
           
           if (!totalMatches || totalMatches === 0) return;
           
           let currentIndex = codeElement.data('currentMatch');
           
           // Określ nowy indeks
           if (direction === 'next') {
               currentIndex = (currentIndex + 1) % totalMatches;
           } else {
               currentIndex = (currentIndex - 1 + totalMatches) % totalMatches;
           }
           
           // Aktualizuj aktywny wynik
           codeToolbar.find('.gca-search-match').removeClass('gca-search-active-match');
           codeToolbar.find(`.gca-search-match[data-match-index="${currentIndex}"]`).addClass('gca-search-active-match');
           
           // Aktualizuj licznik
           codeToolbar.find('.gca-search-count').text(`${currentIndex + 1} z ${totalMatches}`);
           
           // Zapisz nowy indeks
           codeElement.data('currentMatch', currentIndex);
           
           // Przewiń do zaznaczonego wyniku
           this.scrollToActiveMatch(codeElement);
       },
       
       scrollToActiveMatch: function(codeElement) {
           const activeMatch = codeElement.find('.gca-search-active-match');
           if (!activeMatch.length) return;
           
           const preElement = codeElement.parent();
           const containerTop = preElement.offset().top;
           const containerHeight = preElement.height();
           const matchTop = activeMatch.offset().top;
           const matchHeight = activeMatch.height();
           
           // Przewiń, jeśli wynik jest poza widocznym obszarem
           if (matchTop < containerTop || matchTop + matchHeight > containerTop + containerHeight) {
               const scrollTo = matchTop - containerTop - (containerHeight / 2) + (matchHeight / 2);
               preElement.animate({ scrollTop: preElement.scrollTop() + scrollTo }, 100);
           }
       },
       
       // Kontrola widoczności numerów linii
       toggleLineNumbers: function(preElement) {
           if (preElement.hasClass('line-numbers')) {
               preElement.removeClass('line-numbers');
           } else {
               preElement.addClass('line-numbers');
           }
       },
       
       // Dodatkowe metody pomocnicze
       countLines: function(text) {
           return text.split('\n').length;
       },
       
       limitLongCode: function(code, maxLines) {
           const lines = code.split('\n');
           if (lines.length <= maxLines) {
               return code;
           }
           
           const headLines = Math.floor(maxLines * 0.7);
           const tailLines = maxLines - headLines;
           
           const head = lines.slice(0, headLines);
           const tail = lines.slice(-tailLines);
           
           return head.join('\n') + 
                  '\n\n... [Pominięto ' + (lines.length - maxLines) + ' linii kodu] ...\n\n' + 
                  tail.join('\n');
       }
   };
   
   // Inicjalizacja przy załadowaniu strony
   $(document).ready(function() {
       GCACodeHighlighter.init();
   });

})(jQuery);