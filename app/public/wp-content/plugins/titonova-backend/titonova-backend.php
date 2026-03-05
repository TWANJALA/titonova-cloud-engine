<?php
/**
 * Plugin Name: TitoNova Backend
 * Description: Intelligent prompt detection that generates full, industry-aware websites.
 * Version: 2.0.0
 */

add_action('rest_api_init', function () {
    register_rest_route('titonova/v1', '/generate', [
        'methods' => ['GET', 'POST'],
        'callback' => 'titonova_generate_website',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('titonova/v1', '/generate/v2', [
        'methods' => ['POST'],
        'callback' => 'titonova_generate_website_v2',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('titonova/v1', '/render/preview', [
        'methods' => ['POST'],
        'callback' => 'titonova_render_preview',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('titonova/v1', '/render/export', [
        'methods' => ['POST'],
        'callback' => 'titonova_render_export',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('titonova/v1', '/learning/signal', [
        'methods' => ['POST'],
        'callback' => 'titonova_track_learning_signal',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('titonova/v1', '/admin/user/action', [
        'methods' => ['POST'],
        'callback' => 'titonova_handle_user_admin_action',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);

    register_rest_route('titonova/v1', '/site/(?P<id>\d+)', [
        'methods' => ['GET'],
        'callback' => 'titonova_render_site',
        'permission_callback' => '__return_true',
    ]);
});

add_action('admin_menu', 'titonova_register_settings_page');
add_action('admin_menu', 'titonova_register_admin_menu');
add_action('admin_init', 'titonova_register_stripe_settings');
add_action('init', 'titonova_register_generation_cpt');
add_action('init', 'titonova_register_site_cpt');
add_action('init', 'titonova_schedule_pattern_review');
add_action('init', 'titonova_schedule_site_health_scan');
add_action('titonova_review_learning_patterns', 'titonova_review_learning_patterns');
add_action('titonova_site_health_scan', 'titonova_run_site_health_scan');
add_action('template_redirect', 'titonova_route_domain_request', 0);
add_action('wp_enqueue_scripts', 'titonova_enqueue_inline_edit_hover_ui');

register_activation_hook(__FILE__, 'titonova_install_tables');

function titonova_register_generation_cpt(): void
{
    register_post_type('titonova_generation', [
        'labels' => [
            'name' => 'TitoNova Generations',
            'singular_name' => 'TitoNova Generation',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'supports' => ['title', 'custom-fields'],
        'capability_type' => 'post',
    ]);
}

function titonova_register_site_cpt(): void
{
    register_post_type('titonova_site', [
        'labels' => [
            'name' => 'TitoNova Sites',
            'singular_name' => 'TitoNova Site',
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'supports' => ['title', 'custom-fields'],
        'capability_type' => 'post',
    ]);
}

function titonova_enqueue_inline_edit_hover_ui(): void
{
    if (is_admin()) {
        return;
    }

    wp_register_style('titonova-inline-edit-hover', false, [], '1.0.0');
    wp_enqueue_style('titonova-inline-edit-hover');

    $css = <<<CSS
:root {
    --titonova-brand-rgb: 0, 0, 128;
}

[data-tn-editable],
[contenteditable="true"] {
    position: relative;
}

.tn-inline-edit-hover {
    outline: 1px solid rgba(var(--titonova-brand-rgb), 0.4);
    box-shadow: 0 0 0 1px rgba(var(--titonova-brand-rgb), 0.4);
}

.tn-inline-edit-label {
    position: absolute;
    top: -22px;
    left: 0;
    font-size: 11px;
    line-height: 1.4;
    background: rgba(0, 0, 0, 0.65);
    color: #fff;
    padding: 2px 6px;
    border-radius: 6px;
    pointer-events: auto;
    opacity: 0;
    transform: translateY(2px);
    transition: opacity 120ms ease, transform 120ms ease;
    white-space: nowrap;
    z-index: 9999;
}

.tn-inline-edit-hover > .tn-inline-edit-label {
    opacity: 1;
    transform: translateY(0);
}

.tn-inline-edit-label .tn-undo {
    margin-left: 6px;
    text-decoration: underline;
    cursor: pointer;
}

.tn-inline-edit-chip {
    position: absolute;
    display: inline-flex;
    gap: 6px;
    padding: 6px;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.95);
    color: #fff;
    font-size: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    z-index: 10000;
}

.tn-inline-edit-chip button {
    appearance: none;
    border: none;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 12px;
    line-height: 1.2;
}

.tn-inline-edit-chip button:hover {
    background: rgba(255, 255, 255, 0.2);
}

.tn-inline-edit-chip .tn-inline-edit-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 6px;
    font-size: 11px;
    color: rgba(255,255,255,0.8);
}

.tn-inline-edit-swatch {
    width: 16px;
    height: 16px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.4);
    cursor: pointer;
}

.tn-inline-edit-toolbar {
    position: fixed;
    left: 16px;
    right: 16px;
    bottom: 16px;
    padding: 12px;
    border-radius: 16px;
    background: rgba(15, 23, 42, 0.96);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    z-index: 10006;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
}

.tn-inline-edit-toolbar button,
.tn-inline-edit-toolbar .tn-inline-edit-swatch {
    min-height: 44px;
    min-width: 44px;
    font-size: 14px;
}

.tn-inline-edit-toolbar .tn-inline-edit-meta {
    font-size: 12px;
    opacity: 0.8;
}

.tn-inline-edit-saved {
    position: fixed;
    top: 16px;
    right: 16px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.9);
    color: #fff;
    font-size: 12px;
    opacity: 0;
    transform: translateY(-6px);
    transition: opacity 160ms ease, transform 160ms ease;
    z-index: 10007;
    pointer-events: none;
}

.tn-inline-edit-saved.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.tn-inline-edit-warning {
    position: absolute;
    background: rgba(17, 24, 39, 0.92);
    color: #fff;
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    transform: translateY(4px);
    transition: opacity 140ms ease, transform 140ms ease;
    z-index: 10001;
    pointer-events: none;
}

.tn-inline-edit-warning.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.tn-inline-edit-rebalance {
    letter-spacing: -0.01em;
    line-height: 1.1;
}

.tn-inline-edit-ghost {
    position: fixed;
    pointer-events: none;
    white-space: pre-wrap;
    color: currentColor;
    opacity: 0.35;
    z-index: 10002;
}
CSS;

    wp_add_inline_style('titonova-inline-edit-hover', $css);

    wp_register_script('titonova-inline-edit-hover', '', [], '1.0.0', true);
    wp_enqueue_script('titonova-inline-edit-hover');

    $inlineConfig = [
        'isAdmin' => current_user_can('manage_options'),
        'advancedMode' => false,
        'brandPalette' => [
            '#0f172a',
            '#1f2937',
            '#0ea5e9',
            '#22c55e',
            '#f59e0b',
            '#ef4444'
        ]
    ];

    wp_add_inline_script(
        'titonova-inline-edit-hover',
        'window.TN_INLINE_EDIT=' . wp_json_encode($inlineConfig) . ';',
        'before'
    );

    $js = <<<JS
(function() {
    var selector = '[data-tn-editable], [contenteditable="true"]';
    var config = window.TN_INLINE_EDIT || { isAdmin: false, advancedMode: false, brandPalette: [] };
    var advancedMode = !!config.advancedMode;
    var chip = null;
    var toolbar = null;
    var activeEditable = null;
    var warning = null;
    var ghost = null;
    var ghostText = '';
    var ghostTimer = null;
    var savedPulse = null;
    var saveQueueKey = 'tnInlineSaveQueue';
    var saving = false;
    var scoreMap = new WeakMap();
    var historyMap = new WeakMap();
    var lastValueMap = new WeakMap();
    var lastEditMap = new WeakMap();
    var editedElements = new Set();

    function isTextEditable(el) {
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        return el.isContentEditable || tag === 'input' || tag === 'textarea';
    }

    function isStructureLocked() {
        return !(config.isAdmin && advancedMode);
    }

    function sanitizeToPlainText(el) {
        var text = el.textContent || '';
        if (el.innerHTML !== text) {
            el.textContent = text;
        }
    }

    function mountAdvancedToggle() {
        if (!config.isAdmin) {
            return;
        }
        var stored = window.localStorage.getItem('tnAdvancedMode');
        if (stored !== null) {
            advancedMode = stored === 'true';
        }
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.style.position = 'fixed';
        toggle.style.right = '16px';
        toggle.style.bottom = '16px';
        toggle.style.zIndex = '10005';
        toggle.style.padding = '8px 12px';
        toggle.style.borderRadius = '999px';
        toggle.style.border = '1px solid rgba(148,163,184,0.5)';
        toggle.style.background = 'rgba(15,23,42,0.9)';
        toggle.style.color = '#fff';
        toggle.style.fontSize = '12px';
        toggle.style.cursor = 'pointer';
        var render = function() {
            toggle.textContent = advancedMode ? 'Advanced Mode: On' : 'Advanced Mode: Off';
        };
        toggle.addEventListener('click', function() {
            advancedMode = !advancedMode;
            window.localStorage.setItem('tnAdvancedMode', advancedMode ? 'true' : 'false');
            render();
        });
        render();
        document.body.appendChild(toggle);
    }

    function hideGallerySection() {
        var gallerySection = document.getElementById('gallery');
        if (gallerySection) {
            gallerySection.style.display = 'none';
        }
    }

    function hideEmptyGallerySection() {
        var gallerySection = document.getElementById('gallery');
        if (!gallerySection) {
            return;
        }
        var galleryImages = gallerySection.querySelectorAll('.gallery img');
        if (galleryImages.length === 0) {
            hideGallerySection();
        }
    }

    function getCurrentValue(el) {
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea') {
            return el.value;
        }
        return el.innerHTML;
    }

    function setCurrentValue(el, value) {
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea') {
            el.value = value;
        } else {
            el.innerHTML = value;
        }
    }

    function pushHistory(el, reason) {
        var history = historyMap.get(el);
        if (!history) {
            history = [];
            historyMap.set(el, history);
        }
        var value = getCurrentValue(el);
        var lastValue = lastValueMap.get(el);
        if (value === lastValue && reason !== 'snapshot') {
            return;
        }
        history.push({ value: value, timestamp: Date.now(), reason: reason || 'input' });
        lastValueMap.set(el, value);
        lastEditMap.set(el, Date.now());
        editedElements.add(el);
    }

    function undoLast(el) {
        var history = historyMap.get(el);
        if (!history || history.length < 2) {
            return;
        }
        history.pop();
        var previous = history[history.length - 1];
        if (previous) {
            setCurrentValue(el, previous.value);
            lastValueMap.set(el, previous.value);
            lastEditMap.set(el, Date.now());
        }
    }

    function formatAgo(ms) {
        var seconds = Math.max(1, Math.round(ms / 1000));
        if (seconds < 60) {
            return seconds + 's ago';
        }
        var minutes = Math.round(seconds / 60);
        return minutes + 'm ago';
    }

    function scoreIndustryMatch(el, text) {
        var industry = (el.getAttribute('data-tn-industry') || '').toLowerCase();
        if (!industry) {
            return 0.5;
        }
        var keywords = {
            healthcare: ['care', 'clinic', 'patient', 'health', 'wellness'],
            finance: ['finance', 'invest', 'secure', 'wealth', 'advice'],
            hospitality: ['stay', 'guest', 'experience', 'comfort', 'welcome'],
            retail: ['shop', 'buy', 'collection', 'product', 'store']
        };
        var list = keywords[industry] || [];
        var hits = list.filter(function(k) { return text.toLowerCase().includes(k); }).length;
        return Math.min(1, hits / Math.max(1, list.length));
    }

    function scoreClarity(text) {
        var words = text.trim().split(/\s+/).filter(Boolean);
        if (!words.length) {
            return 0;
        }
        var avgWordLen = words.reduce(function(acc, w) { return acc + w.length; }, 0) / words.length;
        var sentenceCount = Math.max(1, text.split(/[.!?]/).filter(Boolean).length);
        var wordsPerSentence = words.length / sentenceCount;
        var clarity = 1;
        if (avgWordLen > 6.5) {
            clarity -= 0.2;
        }
        if (wordsPerSentence > 20) {
            clarity -= 0.2;
        }
        return Math.max(0.2, Math.min(1, clarity));
    }

    function scoreConversionIntent(el, text) {
        var intent = (el.getAttribute('data-tn-intent') || '').toLowerCase();
        var ctaWords = ['get', 'start', 'book', 'request', 'join', 'buy', 'try', 'schedule', 'contact'];
        var hit = ctaWords.some(function(word) { return text.toLowerCase().includes(word); });
        if (intent === 'cta') {
            return hit ? 1 : 0.4;
        }
        return hit ? 0.7 : 0.5;
    }

    function scoreDesignConsistency(el) {
        var style = window.getComputedStyle(el);
        var lineHeight = parseFloat(style.lineHeight || '0');
        var fontSize = parseFloat(style.fontSize || '0');
        if (!lineHeight || !fontSize) {
            return 0.7;
        }
        var ratio = lineHeight / fontSize;
        if (ratio < 1.05 || ratio > 1.6) {
            return 0.6;
        }
        return 0.9;
    }

    function calculateConfidence(el) {
        var text = (el.textContent || '').trim();
        var industry = scoreIndustryMatch(el, text);
        var clarity = scoreClarity(text);
        var intent = scoreConversionIntent(el, text);
        var design = scoreDesignConsistency(el);
        var score = (industry * 0.3) + (clarity * 0.3) + (intent * 0.2) + (design * 0.2);
        return Math.round(score * 100);
    }

    function suggestImprovements(el) {
        var intent = (el.getAttribute('data-tn-intent') || 'text').toLowerCase();
        if (intent === 'headline') {
            showWarning(el, 'Suggestion: make the headline more specific and benefit-led');
            return;
        }
        if (intent === 'cta') {
            showWarning(el, 'Suggestion: use a clearer action verb');
            return;
        }
        showWarning(el, 'Suggestion: tighten wording for clarity');
    }

    function updateConfidence(el) {
        if (!el || !el.isContentEditable) {
            return;
        }
        var score = calculateConfidence(el);
        var prev = scoreMap.get(el) || score;
        scoreMap.set(el, score);
        if (score < prev) {
            suggestImprovements(el);
        }
    }

    function getElementId(el) {
        if (el.dataset.tnId) {
            return el.dataset.tnId;
        }
        var base = el.id || el.getAttribute('data-tn-key') || el.tagName.toLowerCase();
        var index = Array.prototype.indexOf.call(document.querySelectorAll(selector), el);
        var id = base + '-' + index;
        el.dataset.tnId = id;
        return id;
    }

    function loadQueue() {
        try {
            var raw = window.localStorage.getItem(saveQueueKey);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveQueue(queue) {
        try {
            window.localStorage.setItem(saveQueueKey, JSON.stringify(queue));
        } catch (e) {
            return;
        }
    }

    function showSavedPulse() {
        if (!savedPulse) {
            savedPulse = document.createElement('div');
            savedPulse.className = 'tn-inline-edit-saved';
            savedPulse.textContent = 'Saved ✓';
            document.body.appendChild(savedPulse);
        }
        savedPulse.classList.add('is-visible');
        clearTimeout(savedPulse._tnTimer);
        savedPulse._tnTimer = setTimeout(function() {
            savedPulse.classList.remove('is-visible');
        }, 900);
    }

    function enqueueSave(el, source) {
        var queue = loadQueue();
        queue.push({
            id: getElementId(el),
            value: getCurrentValue(el),
            timestamp: Date.now(),
            source: source || 'input'
        });
        saveQueue(queue);
    }

    function flushQueue() {
        if (saving) {
            return;
        }
        var queue = loadQueue();
        if (!queue.length) {
            return;
        }
        if (!navigator.onLine) {
            return;
        }
        saving = true;
        queue.shift();
        saveQueue(queue);
        saving = false;
        showSavedPulse();
        if (queue.length) {
            flushQueue();
        }
    }

    function getEditableType(el) {
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        if (el.getAttribute('data-tn-type')) {
            return el.getAttribute('data-tn-type');
        }
        if (el.hasAttribute('data-tn-color-editable')) {
            return 'color';
        }
        if (tag === 'img') {
            return 'image';
        }
        if (tag === 'button' || (tag === 'a' && (el.classList.contains('button') || el.getAttribute('role') === 'button'))) {
            return 'button';
        }
        return 'text';
    }

    function ensureEditable(el) {
        if (!el.isContentEditable && el.getAttribute('contenteditable') !== 'true') {
            el.setAttribute('contenteditable', 'true');
            el.setAttribute('spellcheck', 'false');
        }
    }

    function getMaxLines(el) {
        if (el.getAttribute('data-tn-max-lines')) {
            return parseInt(el.getAttribute('data-tn-max-lines'), 10) || 2;
        }
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        if (tag === 'button' || (tag === 'a' && el.getAttribute('role') === 'button')) {
            return 1;
        }
        if (tag === 'h1' || tag === 'h2' || tag === 'h3') {
            return 2;
        }
        return 4;
    }

    function getDynamicMaxChars(el) {
        var styles = window.getComputedStyle(el);
        var fontSize = parseFloat(styles.fontSize || '16');
        var avgCharWidth = fontSize * 0.55;
        var lines = getMaxLines(el);
        var maxChars = Math.floor((el.clientWidth / avgCharWidth) * lines);
        return Math.max(12, maxChars);
    }

    function storeOriginalMetrics(el) {
        if (!el.dataset.tnFontSize) {
            var styles = window.getComputedStyle(el);
            el.dataset.tnFontSize = styles.fontSize;
            el.dataset.tnLineHeight = styles.lineHeight;
        }
    }

    function resetFontMetrics(el) {
        if (el.dataset.tnFontSize) {
            el.style.fontSize = el.dataset.tnFontSize;
        }
        if (el.dataset.tnLineHeight) {
            el.style.lineHeight = el.dataset.tnLineHeight;
        }
        el.classList.remove('tn-inline-edit-rebalance');
    }

    function showWarning(el, message) {
        if (!warning) {
            warning = document.createElement('div');
            warning.className = 'tn-inline-edit-warning';
            document.body.appendChild(warning);
        }
        warning.textContent = message;
        var rect = el.getBoundingClientRect();
        var top = rect.top + window.scrollY - 28;
        var left = rect.left + window.scrollX;
        if (top < 8) {
            top = rect.bottom + window.scrollY + 8;
        }
        warning.style.top = top + 'px';
        warning.style.left = left + 'px';
        warning.classList.add('is-visible');
        clearTimeout(warning._tnTimer);
        warning._tnTimer = setTimeout(function() {
            warning.classList.remove('is-visible');
        }, 1600);
    }

    function preventBreakingLine(el, text) {
        var maxLines = getMaxLines(el);
        if (maxLines === 1) {
            return text.replace(/\n+/g, ' ');
        }
        return text;
    }

    function enforceLayoutSafety(el) {
        if (!el || !el.isContentEditable) {
            return;
        }

        storeOriginalMetrics(el);
        var rawText = el.textContent || '';
        var sanitized = preventBreakingLine(el, rawText);
        if (sanitized !== rawText) {
            el.textContent = sanitized;
        }

        resetFontMetrics(el);

        var maxChars = getDynamicMaxChars(el);
        var overflowWidth = el.scrollWidth > el.clientWidth + 1;
        var overflowHeight = el.scrollHeight > el.clientHeight + 1;
        var tooLong = sanitized.trim().length > maxChars;

        if (overflowWidth || overflowHeight || tooLong) {
            var scaleX = el.clientWidth / Math.max(el.scrollWidth, 1);
            var scaleY = el.clientHeight / Math.max(el.scrollHeight, 1);
            var scale = Math.min(scaleX, scaleY, 1);
            var styles = window.getComputedStyle(el);
            var baseSize = parseFloat(el.dataset.tnFontSize || styles.fontSize || '16');
            var newSize = Math.max(baseSize * scale, baseSize * 0.82);
            el.style.fontSize = newSize + 'px';
            el.classList.add('tn-inline-edit-rebalance');
            showWarning(el, 'A bit long — adjusting layout for you');
        }
    }

    function getTextNodes(root) {
        var nodes = [];
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function(node) {
                return node.nodeValue && node.nodeValue.trim().length ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }
        return nodes;
    }

    function getTextIndex(root, node, offset) {
        var nodes = getTextNodes(root);
        var index = 0;
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i] === node) {
                return index + offset;
            }
            index += nodes[i].nodeValue.length;
        }
        return null;
    }

    function setRangeByIndex(root, start, end) {
        var nodes = getTextNodes(root);
        var index = 0;
        var range = document.createRange();
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            var len = node.nodeValue.length;
            if (start >= index && start <= index + len) {
                range.setStart(node, Math.max(0, start - index));
            }
            if (end >= index && end <= index + len) {
                range.setEnd(node, Math.max(0, end - index));
                break;
            }
            index += len;
        }
        var selection = window.getSelection();
        if (selection) {
            selection.removeAllRanges();
            selection.addRange(range);
        }
    }

    function findSentenceBounds(text, index) {
        var start = 0;
        var end = text.length;
        var before = text.slice(0, index);
        var after = text.slice(index);
        var lastStop = Math.max(before.lastIndexOf('.'), before.lastIndexOf('!'), before.lastIndexOf('?'));
        if (lastStop !== -1) {
            start = lastStop + 1;
        }
        while (start < text.length && /\s/.test(text[start])) {
            start += 1;
        }
        var candidates = [after.indexOf('.'), after.indexOf('!'), after.indexOf('?')].filter(function(v) {
            return v !== -1;
        });
        var nextStopRel = candidates.length ? Math.min.apply(Math, candidates) : -1;
        if (nextStopRel !== -1) {
            end = index + nextStopRel + 1;
        }
        return { start: start, end: end };
    }

    function getCaretIndex(root) {
        var selection = window.getSelection();
        if (!selection || !selection.rangeCount) {
            return null;
        }
        var range = selection.getRangeAt(0);
        if (!root.contains(range.startContainer)) {
            return null;
        }
        return getTextIndex(root, range.startContainer, range.startOffset);
    }

    function lengthMatchSuggestion(text, targetLength) {
        if (targetLength <= 0) {
            return '';
        }
        var cleaned = text.replace(/\s+/g, ' ').trim();
        if (cleaned.length === targetLength) {
            return cleaned;
        }
        if (cleaned.length > targetLength) {
            var slice = cleaned.slice(0, Math.max(1, targetLength));
            var lastSpace = slice.lastIndexOf(' ');
            if (lastSpace > 8) {
                slice = slice.slice(0, lastSpace);
            }
            if (slice.length > 3) {
                return slice.slice(0, Math.max(1, targetLength - 1)) + '…';
            }
            return slice;
        }
        var fillers = ['with', 'for', 'and', 'that', 'today', 'now', 'local', 'trusted', 'premium'];
        var i = 0;
        while (cleaned.length < targetLength && i < 20) {
            cleaned += ' ' + fillers[i % fillers.length];
            i += 1;
        }
        return cleaned.slice(0, targetLength).trim();
    }

    function getSuggestionSource(el) {
        var custom = el.getAttribute('data-tn-suggestions');
        if (custom) {
            return custom.split('|').map(function(s) { return s.trim(); }).filter(Boolean);
        }
        var intent = (el.getAttribute('data-tn-intent') || '').toLowerCase();
        if (intent === 'headline') {
            return [
                'Elevate your brand with clarity and confidence.',
                'A smarter way to win trust fast.',
                'Build momentum with a sharper message.'
            ];
        }
        if (intent === 'cta') {
            return [
                'Get started today',
                'Book a call',
                'Request a quote',
                'See pricing',
                'Start your trial'
            ];
        }
        var industry = (el.getAttribute('data-tn-industry') || '').toLowerCase();
        var tone = (el.getAttribute('data-tn-tone') || '').toLowerCase();
        var base = {
            default: ['Clear, confident messaging that builds trust.', 'Make it easy to choose you right now.'],
            healthcare: ['Compassionate care with fast, reliable support.', 'Trusted care when it matters most.'],
            finance: ['Secure guidance that protects your future.', 'Confidence for every financial decision.'],
            hospitality: ['Thoughtful experiences designed for comfort.', 'Warm welcomes and effortless stays.'],
            retail: ['Curated essentials that fit your lifestyle.', 'Find what you need in moments.'],
        };
        var toneBoost = {
            luxury: 'Elevate every detail with refined expertise.',
            corporate: 'Professional solutions that scale with you.',
            startup: 'Move faster with bold, focused momentum.'
        };
        var pool = base[industry] || base.default;
        if (toneBoost[tone]) {
            pool = pool.concat([toneBoost[tone]]);
        }
        return pool;
    }

    function detectIntent(el) {
        if (el.getAttribute('data-tn-intent')) {
            return el.getAttribute('data-tn-intent');
        }
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        var text = (el.textContent || '').trim();
        if (tag === 'h1' || tag === 'h2' || el.closest('[data-tn-role="hero"]')) {
            return 'headline';
        }
        if (tag === 'button' || (tag === 'a' && (el.classList.contains('button') || el.getAttribute('role') === 'button'))) {
            return 'cta';
        }
        if (text.match(/[$€£]\s?\d/)) {
            return 'pricing';
        }
        if (text.match(/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/)) {
            return 'email';
        }
        if (text.match(/\+?\d[\d\s().-]{7,}/)) {
            return 'phone';
        }
        return 'text';
    }

    function ensureIntent(el) {
        var intent = detectIntent(el);
        el.setAttribute('data-tn-intent', intent);
        return intent;
    }

    function formatPricing(el) {
        var text = (el.textContent || '').trim();
        if (!text) {
            return;
        }
        var currency = el.getAttribute('data-tn-currency');
        if (!currency) {
            var match = text.match(/[$€£]/);
            currency = match ? match[0] : '$';
            el.setAttribute('data-tn-currency', currency);
        }
        var numeric = text.replace(/[^0-9.]/g, '');
        if (!numeric) {
            numeric = '0';
        }
        var value = parseFloat(numeric);
        if (isNaN(value)) {
            return;
        }
        var formatted = value.toLocaleString(undefined, {
            minimumFractionDigits: numeric.includes('.') ? 2 : 0,
            maximumFractionDigits: 2
        });
        el.textContent = currency + formatted;
    }

    function normalizePhone(text) {
        var digits = text.replace(/\D/g, '');
        if (digits.length === 10) {
            return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
        }
        if (digits.length === 11 && digits[0] === '1') {
            return '+1 (' + digits.slice(1, 4) + ') ' + digits.slice(4, 7) + '-' + digits.slice(7);
        }
        return text;
    }

    function isValidEmail(text) {
        return /^[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}$/.test(text.trim());
    }

    function isValidPhone(text) {
        var digits = text.replace(/\D/g, '');
        return digits.length >= 10;
    }

    function applyAutoRules(el) {
        var intent = ensureIntent(el);
        if (intent === 'pricing') {
            formatPricing(el);
        }
        if (intent === 'email') {
            var email = (el.textContent || '').trim();
            if (email && !isValidEmail(email)) {
                showWarning(el, 'Email looks off — try name@domain.com');
            }
        }
        if (intent === 'phone') {
            var phone = (el.textContent || '').trim();
            if (phone) {
                var normalized = normalizePhone(phone);
                el.textContent = normalized;
                if (!isValidPhone(normalized)) {
                    showWarning(el, 'Phone looks off — try (555) 555-5555');
                }
            }
        }
    }

    function replaceHeading(el, newTag) {
        var replacement = document.createElement(newTag);
        Array.prototype.slice.call(el.attributes).forEach(function(attr) {
            replacement.setAttribute(attr.name, attr.value);
        });
        while (el.firstChild) {
            replacement.appendChild(el.firstChild);
        }
        el.parentNode.replaceChild(replacement, el);
        return replacement;
    }

    function getHeadingLevel(el) {
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        if (tag.match(/^h[1-6]$/)) {
            return parseInt(tag[1], 10);
        }
        return null;
    }

    function suggestAltText(img) {
        var existing = (img.getAttribute('alt') || '').trim();
        if (existing) {
            return existing;
        }
        var nearby = img.closest('figure') || img.parentElement;
        var candidate = '';
        if (nearby) {
            var caption = nearby.querySelector('figcaption');
            if (caption) {
                candidate = caption.textContent.trim();
            }
        }
        if (!candidate) {
            var heading = img.closest('section') ? img.closest('section').querySelector('h1, h2, h3') : null;
            candidate = heading ? heading.textContent.trim() : '';
        }
        if (!candidate) {
            candidate = 'Image related to the section content';
        }
        return candidate.slice(0, 120);
    }

    function ensureSeo(el) {
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        var level = getHeadingLevel(el);
        if (level) {
            var headings = Array.prototype.slice.call(document.querySelectorAll('h1'));
            if (level === 1 && headings.length > 1) {
                var firstH1 = headings[0];
                if (el !== firstH1) {
                    el = replaceHeading(el, 'h2');
                    showWarning(el, 'Adjusted heading order for clarity');
                }
            }
            if (level >= 3) {
                var hasPrevH2 = false;
                var prev = el.previousElementSibling;
                while (prev) {
                    var prevLevel = getHeadingLevel(prev);
                    if (prevLevel === 2) {
                        hasPrevH2 = true;
                        break;
                    }
                    prev = prev.previousElementSibling;
                }
                if (!hasPrevH2) {
                    el = replaceHeading(el, 'h2');
                    showWarning(el, 'Adjusted heading order for clarity');
                }
            }
        }

        if (tag === 'a' && !el.getAttribute('aria-label')) {
            var linkText = (el.textContent || '').trim();
            if (linkText) {
                el.setAttribute('aria-label', linkText);
            }
        }

        if (tag === 'img') {
            var altText = suggestAltText(el);
            el.dataset.tnAltSuggestion = altText;
        }

        return el;
    }

    function showSeoHints(el) {
        if (!el) {
            return;
        }
        var tag = el.tagName ? el.tagName.toLowerCase() : '';
        if (tag === 'img') {
            var suggestion = el.dataset.tnAltSuggestion || suggestAltText(el);
            showWarning(el, 'Alt text suggestion: ' + suggestion);
            return;
        }
        if (getHeadingLevel(el)) {
            showWarning(el, 'Headings stay in order for clarity');
            return;
        }
        if (tag === 'a') {
            showWarning(el, 'Link label refined for clarity');
            return;
        }
        var intent = el.getAttribute('data-tn-intent') || '';
        if (intent) {
            showWarning(el, 'SEO hint: align keywords with ' + intent);
        }
    }

    function buildSuggestion(el, sentenceLength) {
        var source = getSuggestionSource(el);
        if (!source.length) {
            return '';
        }
        var suggestion = source[Math.floor(Math.random() * source.length)];
        return lengthMatchSuggestion(suggestion, sentenceLength);
    }

    function createGhost() {
        if (!ghost) {
            ghost = document.createElement('span');
            ghost.className = 'tn-inline-edit-ghost';
            document.body.appendChild(ghost);
        }
    }

    function hideGhost() {
        if (ghost) {
            ghost.textContent = '';
            ghost.style.display = 'none';
        }
        ghostText = '';
    }

    function positionGhost(el) {
        if (!ghost) {
            return;
        }
        var selection = window.getSelection();
        var rect = null;
        if (selection && selection.rangeCount) {
            rect = selection.getRangeAt(0).getBoundingClientRect();
        }
        if (!rect || (!rect.width && !rect.height)) {
            rect = el.getBoundingClientRect();
        }
        var styles = window.getComputedStyle(el);
        ghost.style.fontFamily = styles.fontFamily;
        ghost.style.fontSize = styles.fontSize;
        ghost.style.lineHeight = styles.lineHeight;
        ghost.style.fontWeight = styles.fontWeight;
        ghost.style.display = 'block';
        ghost.style.left = (rect.left + window.scrollX) + 'px';
        ghost.style.top = (rect.top + window.scrollY) + 'px';
    }

    function showGhost(el, suggestion) {
        if (!suggestion) {
            hideGhost();
            return;
        }
        createGhost();
        ghostText = suggestion;
        ghost.textContent = suggestion;
        positionGhost(el);
    }

    function getSentenceBoundsForElement(el) {
        var text = el.textContent || '';
        var caretIndex = getCaretIndex(el);
        if (caretIndex === null) {
            caretIndex = Math.min(text.length - 1, 0);
        }
        return findSentenceBounds(text, caretIndex);
    }

    function replaceSentence(el, replacement) {
        var text = el.textContent || '';
        var bounds = getSentenceBoundsForElement(el);
        var before = text.slice(0, bounds.start);
        var after = text.slice(bounds.end);
        var nextText = before + replacement + after;
        el.textContent = nextText;
    }

    function insertSuggestionAtCaret(el, suggestion) {
        var selection = window.getSelection();
        if (!selection || !selection.rangeCount) {
            el.textContent += suggestion;
            return;
        }
        var range = selection.getRangeAt(0);
        range.deleteContents();
        var node = document.createTextNode(suggestion);
        range.insertNode(node);
        range.setStartAfter(node);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function selectSentence(el, event) {
        var text = el.textContent || '';
        if (!text.trim()) {
            return;
        }
        var rangeFromPoint = null;
        if (document.caretRangeFromPoint) {
            rangeFromPoint = document.caretRangeFromPoint(event.clientX, event.clientY);
        } else if (document.caretPositionFromPoint) {
            var pos = document.caretPositionFromPoint(event.clientX, event.clientY);
            if (pos) {
                rangeFromPoint = document.createRange();
                rangeFromPoint.setStart(pos.offsetNode, pos.offset);
                rangeFromPoint.collapse(true);
            }
        }
        var index = null;
        if (rangeFromPoint) {
            index = getTextIndex(el, rangeFromPoint.startContainer, rangeFromPoint.startOffset);
        }
        if (index === null) {
            index = Math.min(text.length - 1, 0);
        }
        var bounds = findSentenceBounds(text, index);
        setRangeByIndex(el, bounds.start, bounds.end);
    }

    function removeChip() {
        if (chip && chip.parentNode) {
            chip.parentNode.removeChild(chip);
        }
        chip = null;
    }

    function removeToolbar() {
        if (toolbar && toolbar.parentNode) {
            toolbar.parentNode.removeChild(toolbar);
        }
        toolbar = null;
    }

    function showChip(el, actions) {
        removeChip();
        chip = document.createElement('div');
        chip.className = 'tn-inline-edit-chip';
        actions.forEach(function(action) {
            if (typeof action === 'object' && action.type === 'meta') {
                var meta = document.createElement('span');
                meta.className = 'tn-inline-edit-meta';
                meta.textContent = action.text;
                chip.appendChild(meta);
                return;
            }
            if (typeof action === 'object' && action.type === 'swatch') {
                var swatch = document.createElement('span');
                swatch.className = 'tn-inline-edit-swatch';
                swatch.style.background = action.color;
                swatch.setAttribute('data-tn-color', action.color);
                swatch.addEventListener('click', function() {
                    var role = el.getAttribute('data-tn-color-role') || 'text';
                    if (role === 'background') {
                        el.style.backgroundColor = action.color;
                    } else {
                        el.style.color = action.color;
                    }
                });
                chip.appendChild(swatch);
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = action;
            chip.appendChild(btn);
        });
        document.body.appendChild(chip);
        var rect = el.getBoundingClientRect();
        var top = rect.top + window.scrollY - chip.offsetHeight - 8;
        var left = rect.left + window.scrollX;
        if (top < 8) {
            top = rect.bottom + window.scrollY + 8;
        }
        chip.style.top = top + 'px';
        chip.style.left = left + 'px';
    }

    function showToolbar(actions) {
        removeToolbar();
        toolbar = document.createElement('div');
        toolbar.className = 'tn-inline-edit-toolbar';
        actions.forEach(function(action) {
            if (typeof action === 'object' && action.type === 'meta') {
                var meta = document.createElement('span');
                meta.className = 'tn-inline-edit-meta';
                meta.textContent = action.text;
                toolbar.appendChild(meta);
                return;
            }
            if (typeof action === 'object' && action.type === 'swatch') {
                var swatch = document.createElement('span');
                swatch.className = 'tn-inline-edit-swatch';
                swatch.style.background = action.color;
                swatch.setAttribute('data-tn-color', action.color);
                swatch.addEventListener('click', function() {
                    if (!activeEditable) {
                        return;
                    }
                    var role = activeEditable.getAttribute('data-tn-color-role') || 'text';
                    if (role === 'background') {
                        activeEditable.style.backgroundColor = action.color;
                    } else {
                        activeEditable.style.color = action.color;
                    }
                });
                toolbar.appendChild(swatch);
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = action;
            toolbar.appendChild(btn);
        });
        document.body.appendChild(toolbar);
    }

    function getImageMeta(img) {
        var naturalW = img.naturalWidth || img.width;
        var naturalH = img.naturalHeight || img.height;
        var displayW = img.clientWidth || img.width;
        var displayH = img.clientHeight || img.height;
        var ratio = naturalW && naturalH ? (naturalW / naturalH) : 1;
        if (!img.dataset.tnAspectRatio) {
            img.dataset.tnAspectRatio = ratio.toFixed(4);
        }
        var scale = (displayW && naturalW) ? displayW / naturalW : 1;
        var perfScore = 100;
        if (naturalW * naturalH > 2000000) {
            perfScore -= 20;
        }
        if (scale > 1.05) {
            perfScore -= 25;
        }
        if (displayW > 1200) {
            perfScore -= 15;
        }
        perfScore = Math.max(40, perfScore);
        return {
            naturalW: naturalW,
            naturalH: naturalH,
            displayW: displayW,
            displayH: displayH,
            perfScore: perfScore,
            scale: scale
        };
    }

    function enforceImageSafety(img) {
        var meta = getImageMeta(img);
        if (meta.scale > 1.05) {
            img.style.imageRendering = 'auto';
            showWarning(img, 'Image may soften — consider higher resolution');
        }
        img.style.objectFit = img.style.objectFit || 'cover';
    }

    function addLabel(el) {
        if (el.querySelector(':scope > .tn-inline-edit-label')) {
            return;
        }
        var label = document.createElement('span');
        label.className = 'tn-inline-edit-label';
        label.textContent = 'Click to edit';
        label.addEventListener('click', function(e) {
            if (label.dataset.tnUndo === 'true') {
                e.preventDefault();
                e.stopPropagation();
                undoLast(el);
                enforceLayoutSafety(el);
            }
        });
        el.insertBefore(label, el.firstChild);
    }

    function removeLabel(el) {
        var label = el.querySelector(':scope > .tn-inline-edit-label');
        if (label) {
            label.remove();
        }
    }

    function activate(el) {
        el.classList.add('tn-inline-edit-hover');
        addLabel(el);
        var label = el.querySelector(':scope > .tn-inline-edit-label');
        var lastEdit = lastEditMap.get(el);
        if (label && lastEdit) {
            var ago = formatAgo(Date.now() - lastEdit);
            label.innerHTML = 'Edited ' + ago + ' • <span class="tn-undo">Undo</span>';
            label.dataset.tnUndo = 'true';
        } else if (label) {
            label.textContent = 'Click to edit';
            label.dataset.tnUndo = 'false';
        }
        el.style.cursor = isTextEditable(el) ? 'text' : 'pointer';
    }

    function deactivate(el) {
        el.classList.remove('tn-inline-edit-hover');
        el.style.cursor = '';
        removeLabel(el);
    }

    function enterEdit(el, event) {
        var type = getEditableType(el);
        var isTouch = event && (event.pointerType === 'touch' || 'ontouchstart' in window || navigator.maxTouchPoints > 0);
        if (type === 'color') {
            var palette = (config.brandPalette || []).map(function(color) {
                return { type: 'swatch', color: color };
            });
            if (palette.length) {
                if (isTouch) {
                    showToolbar(palette);
                } else {
                    showChip(el, palette);
                }
            }
            return;
        }
        if (type === 'image') {
            var meta = getImageMeta(el);
            var sizeText = meta.naturalW + '×' + meta.naturalH + ' • Score ' + meta.perfScore;
            var imageActions = [
                { type: 'meta', text: sizeText },
                'Enhance clarity',
                'Remove background',
                'Improve lighting',
                'Match brand tone'
            ];
            if (isTouch) {
                showToolbar(imageActions);
            } else {
                showChip(el, imageActions);
            }
            ensureSeo(el);
            enforceImageSafety(el);
            return;
        }

        if (type === 'button' && el.getAttribute('data-tn-editing') === 'true') {
            if (isTouch) {
                showToolbar(['Link', 'Style', 'Animation']);
            } else {
                showChip(el, ['Link', 'Style', 'Animation']);
            }
            return;
        }

        ensureEditable(el);
        if (!historyMap.get(el)) {
            pushHistory(el, 'init');
        }
        el.setAttribute('data-tn-editing', 'true');
        el.focus();
        selectSentence(el, event);
        enforceLayoutSafety(el);
        if (isStructureLocked()) {
            sanitizeToPlainText(el);
        }
        applyAutoRules(el);
        el = ensureSeo(el);
        if (activeEditable && activeEditable !== el) {
            activeEditable = el;
        }
    }

    var isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    if (!isTouchDevice) {
        document.addEventListener('mouseover', function(e) {
            var target = e.target.closest(selector);
            if (!target) {
                return;
            }
            activate(target);
        }, true);

        document.addEventListener('mouseout', function(e) {
            var target = e.target.closest(selector);
            if (!target) {
                return;
            }
            if (target.contains(e.relatedTarget)) {
                return;
            }
            deactivate(target);
        }, true);
    }

    document.addEventListener('click', function(e) {
        var target = e.target.closest(selector);
        if (!target) {
            removeChip();
            removeToolbar();
            hideGhost();
            return;
        }

        var type = getEditableType(target);
        if (type !== 'image') {
            e.preventDefault();
            target = ensureSeo(target);
        }

        activeEditable = target;
        enterEdit(target, e);
    }, true);

    var longPressTimer = null;
    document.addEventListener('pointerdown', function(e) {
        if (!isTouchDevice) {
            return;
        }
        var target = e.target.closest(selector);
        if (!target) {
            return;
        }
        longPressTimer = setTimeout(function() {
            activeEditable = target;
            enterEdit(target, e);
        }, 450);
    }, true);

    document.addEventListener('pointerup', function() {
        if (longPressTimer) {
            clearTimeout(longPressTimer);
            longPressTimer = null;
        }
    }, true);

    document.addEventListener('input', function(e) {
        var target = e.target.closest(selector);
        if (!target) {
            return;
        }
        if (!target.isContentEditable) {
            return;
        }
        if (isStructureLocked()) {
            sanitizeToPlainText(target);
        }
        applyAutoRules(target);
        target = ensureSeo(target);
        updateConfidence(target);
        pushHistory(target, 'input');
        enqueueSave(target, 'input');
        flushQueue();
        hideGhost();
        clearTimeout(ghostTimer);
        ghostTimer = setTimeout(function() {
            if (document.activeElement !== target) {
                return;
            }
            var text = target.textContent || '';
            if (!text.trim()) {
                return;
            }
            var bounds = getSentenceBoundsForElement(target);
            var sentence = text.slice(bounds.start, bounds.end).trim();
            var suggestion = buildSuggestion(target, sentence.length);
            showGhost(target, suggestion);
        }, 600);
        enforceLayoutSafety(target);
    }, true);

    document.addEventListener('paste', function(e) {
        var target = e.target.closest(selector);
        if (!target || !target.isContentEditable) {
            return;
        }
        if (!isStructureLocked()) {
            return;
        }
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text');
        document.execCommand('insertText', false, text);
    }, true);

    document.addEventListener('keydown', function(e) {
        var target = e.target.closest(selector);
        if (!target || !target.isContentEditable) {
            return;
        }
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undoLast(target);
            enforceLayoutSafety(target);
            return;
        }
    }, true);

    document.addEventListener('keydown', function(e) {
        var target = e.target.closest(selector);
        if (!target || !target.isContentEditable) {
            return;
        }
        if (!ghostText) {
            return;
        }
        if (e.key === 'Tab') {
            e.preventDefault();
            insertSuggestionAtCaret(target, ghostText);
            hideGhost();
            enforceLayoutSafety(target);
            return;
        }
        if (e.key === 'Backspace') {
            e.preventDefault();
            replaceSentence(target, ghostText);
            hideGhost();
            enforceLayoutSafety(target);
        }
    }, true);

    document.addEventListener('focusout', function(e) {
        var target = e.target.closest(selector);
        if (!target) {
            return;
        }
        target.removeAttribute('data-tn-editing');
        if (target.isContentEditable) {
            enqueueSave(target, 'blur');
            flushQueue();
            updateConfidence(target);
        }
    }, true);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            removeChip();
            removeToolbar();
            hideGhost();
        }
    }, true);

    document.addEventListener('click', function(e) {
        if (chip && !chip.contains(e.target)) {
            if (!activeEditable || !activeEditable.contains(e.target)) {
                removeChip();
            }
        }
        if (toolbar && !toolbar.contains(e.target)) {
            if (!activeEditable || !activeEditable.contains(e.target)) {
                removeToolbar();
            }
        }
    }, true);

    window.addEventListener('scroll', function() {
        hideGhost();
    }, true);

    window.addEventListener('resize', function() {
        hideGhost();
    });

    window.addEventListener('online', function() {
        flushQueue();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            mountAdvancedToggle();
            hideEmptyGallerySection();
        });
    } else {
        mountAdvancedToggle();
        hideEmptyGallerySection();
    }

    window.TN_SHOW_SEO_HINTS = function() {
        var target = activeEditable || (document.activeElement && document.activeElement.closest ? document.activeElement.closest(selector) : null);
        showSeoHints(target);
    };

    setInterval(function() {
        editedElements.forEach(function(el) {
            if (!document.body.contains(el)) {
                editedElements.delete(el);
                return;
            }
            pushHistory(el, 'snapshot');
        });
    }, 10000);
})();
JS;

    wp_add_inline_script('titonova-inline-edit-hover', $js);
}

function titonova_install_tables(): void
{
    global $wpdb;

    $charsetCollate = $wpdb->get_charset_collate();
    $sitesTable = titonova_sites_table();
    $domainsTable = titonova_domains_table();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sitesSql = "CREATE TABLE {$sitesTable} (
        id CHAR(36) NOT NULL,
        user_id BIGINT UNSIGNED NULL,
        slug VARCHAR(190) NOT NULL,
        html LONGTEXT NOT NULL,
        css LONGTEXT NULL,
        config_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY slug (slug),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) {$charsetCollate};";

    $domainsSql = "CREATE TABLE {$domainsTable} (
        id CHAR(36) NOT NULL,
        domain VARCHAR(255) NOT NULL,
        site_id CHAR(36) NOT NULL,
        status ENUM('pending','verified','active') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY domain (domain),
        KEY site_id (site_id),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charsetCollate};";

    dbDelta($sitesSql);
    dbDelta($domainsSql);
}

function titonova_sites_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'titonova_sites';
}

function titonova_domains_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'titonova_domains';
}

function titonova_table_exists(string $table): bool
{
    global $wpdb;
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    return $found === $table;
}

function titonova_register_settings_page(): void
{
    add_options_page(
        'TitoNova Payments',
        'TitoNova Payments',
        'manage_options',
        'titonova-payments',
        'titonova_render_settings_page'
    );
}

function titonova_register_admin_menu(): void
{
    add_menu_page(
        'Admin Panel',
        'Admin Panel',
        'manage_options',
        'titonova-admin',
        'titonova_render_users_all',
        'dashicons-admin-generic',
        58
    );

    add_submenu_page(
        'titonova-admin',
        'All Users',
        'Users ⭐',
        'manage_options',
        'titonova-admin',
        'titonova_render_users_all'
    );

    add_submenu_page(
        'titonova-admin',
        'All Users',
        'All Users',
        'manage_options',
        'titonova-users',
        'titonova_render_users_all'
    );

    add_submenu_page(
        'titonova-admin',
        'Power Users',
        'Power Users',
        'manage_options',
        'titonova-users-power',
        'titonova_render_users_power'
    );

    add_submenu_page(
        'titonova-admin',
        'At Risk',
        'At Risk',
        'manage_options',
        'titonova-users-at-risk',
        'titonova_render_users_at_risk'
    );

    add_submenu_page(
        'titonova-admin',
        'Agencies',
        'Agencies',
        'manage_options',
        'titonova-users-agencies',
        'titonova_render_users_agencies'
    );

    add_submenu_page(
        'titonova-admin',
        'Suspended',
        'Suspended',
        'manage_options',
        'titonova-users-suspended',
        'titonova_render_users_suspended'
    );

    add_submenu_page(
        'titonova-admin',
        'Industries',
        'Industries',
        'manage_options',
        'titonova-industries',
        'titonova_render_core_industries'
    );

    add_submenu_page(
        'titonova-admin',
        'Emerging Micro-Industries',
        'Emerging Micro-Industries',
        'manage_options',
        'titonova-emerging-industries',
        'titonova_render_emerging_industries'
    );

    add_submenu_page(
        'titonova-admin',
        'Learning Metrics',
        'Learning Metrics',
        'manage_options',
        'titonova-learning-metrics',
        'titonova_render_learning_metrics'
    );

    add_submenu_page(
        'titonova-admin',
        'System Rules',
        'System Rules',
        'manage_options',
        'titonova-system-rules',
        'titonova_render_system_rules'
    );

    add_submenu_page(
        'titonova-admin',
        'Settings',
        'Settings',
        'manage_options',
        'titonova-settings',
        'titonova_render_settings_page'
    );
}

function titonova_register_stripe_settings(): void
{
    register_setting('titonova_payments', 'titonova_stripe_secret_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('titonova_payments', 'titonova_stripe_publishable_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('titonova_payments', 'titonova_stripe_success_url', ['sanitize_callback' => 'esc_url_raw']);
    register_setting('titonova_payments', 'titonova_stripe_cancel_url', ['sanitize_callback' => 'esc_url_raw']);
    register_setting('titonova_payments', 'titonova_stripe_currency', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('titonova_payments', 'titonova_default_phone', ['sanitize_callback' => 'sanitize_text_field']);

    add_settings_section(
        'titonova_stripe_section',
        'Stripe Checkout',
        '__return_null',
        'titonova-payments'
    );

    add_settings_field(
        'titonova_stripe_secret_key',
        'Stripe Secret Key',
        'titonova_render_password_field',
        'titonova-payments',
        'titonova_stripe_section',
        ['option' => 'titonova_stripe_secret_key', 'placeholder' => 'sk_live_...']
    );

    add_settings_field(
        'titonova_stripe_publishable_key',
        'Stripe Publishable Key',
        'titonova_render_text_field',
        'titonova-payments',
        'titonova_stripe_section',
        ['option' => 'titonova_stripe_publishable_key', 'placeholder' => 'pk_live_...']
    );

    add_settings_field(
        'titonova_stripe_success_url',
        'Success URL',
        'titonova_render_url_field',
        'titonova-payments',
        'titonova_stripe_section',
        ['option' => 'titonova_stripe_success_url', 'placeholder' => 'https://example.com/success']
    );

    add_settings_field(
        'titonova_stripe_cancel_url',
        'Cancel URL',
        'titonova_render_url_field',
        'titonova-payments',
        'titonova_stripe_section',
        ['option' => 'titonova_stripe_cancel_url', 'placeholder' => 'https://example.com/cancel']
    );

    add_settings_field(
        'titonova_stripe_currency',
        'Default Currency',
        'titonova_render_text_field',
        'titonova-payments',
        'titonova_stripe_section',
        ['option' => 'titonova_stripe_currency', 'placeholder' => 'usd']
    );

    add_settings_section(
        'titonova_contact_section',
        'Contact Defaults',
        '__return_null',
        'titonova-payments'
    );

    add_settings_field(
        'titonova_default_phone',
        'Default Phone',
        'titonova_render_text_field',
        'titonova-payments',
        'titonova_contact_section',
        ['option' => 'titonova_default_phone', 'placeholder' => '+1 (000) 000-0000']
    );
}

function titonova_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    echo '<div class="wrap">';
    echo '<h1>TitoNova Payments</h1>';
    echo '<p>Configure Stripe Checkout keys and defaults used by the payment endpoint.</p>';
    echo '<form method="post" action="options.php">';
    settings_fields('titonova_payments');
    do_settings_sections('titonova-payments');
    submit_button('Save Settings');
    echo '</form>';
    echo '</div>';
}

function titonova_render_admin_overview(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>Admin Panel</h1>';
    echo '<p>Use the Users and Industries sections to review activity and learning signals.</p>';
    echo '</div>';
}

function titonova_render_users_all(): void
{
    titonova_render_users_table('all', 'All Users');
}

function titonova_render_users_power(): void
{
    titonova_render_users_table('power', 'Power Users');
}

function titonova_render_users_at_risk(): void
{
    titonova_render_users_table('at_risk', 'At Risk');
}

function titonova_render_users_agencies(): void
{
    titonova_render_users_table('agencies', 'Agencies');
}

function titonova_render_users_suspended(): void
{
    titonova_render_users_table('suspended', 'Suspended');
}

function titonova_render_users_table(string $filter, string $title): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $args = [
        'number' => 50,
        'orderby' => 'registered',
        'order' => 'DESC',
        'fields' => ['ID', 'display_name', 'user_email', 'user_registered'],
    ];

    $metaQuery = [];
    if ($filter === 'power') {
        $metaQuery[] = [
            'key' => '_titonova_user_tier',
            'value' => 'Power User',
            'compare' => '=',
        ];
    }
    if ($filter === 'at_risk') {
        $metaQuery[] = [
            'key' => '_titonova_user_lifecycle_status',
            'value' => 'At Risk',
            'compare' => '=',
        ];
    }
    if ($filter === 'agencies') {
        $metaQuery[] = [
            'key' => '_titonova_user_tier',
            'value' => 'Enterprise / Agency',
            'compare' => '=',
        ];
    }
    if ($filter === 'suspended') {
        $metaQuery[] = [
            'key' => '_titonova_user_suspended',
            'value' => true,
            'compare' => '=',
        ];
    }

    if (!empty($metaQuery)) {
        $args['meta_query'] = $metaQuery;
    }

    $users = get_users($args);

    echo '<div class="wrap">';
    echo '<h1>' . esc_html($title) . '</h1>';
    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Name</th><th>Email</th><th>Tier</th><th>Status</th><th>Tags</th><th>Registered</th></tr></thead><tbody>';

    if (!empty($users)) {
        foreach ($users as $user) {
            $tier = (string) get_user_meta($user->ID, '_titonova_user_tier', true);
            $status = (string) get_user_meta($user->ID, '_titonova_user_lifecycle_status', true);
            $tags = get_user_meta($user->ID, '_titonova_user_tags', true);
            $tags = is_array($tags) ? implode(', ', array_keys($tags)) : '';
            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . '</td>';
            echo '<td>' . esc_html($user->user_email) . '</td>';
            echo '<td>' . esc_html($tier) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html($tags) . '</td>';
            echo '<td>' . esc_html($user->user_registered) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">No users found.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

function titonova_render_core_industries(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>Core Industries</h1>';
    echo '<p>Core industries are defined by the base detection map and layout rules. Learning updates never override these rules.</p>';
    echo '</div>';
}

function titonova_render_emerging_industries(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $view = sanitize_text_field((string) ($_GET['view'] ?? 'table'));
    $view = in_array($view, ['table', 'detail', 'audit'], true) ? $view : 'table';

    echo '<div class="wrap">';
    echo '<h1>Emerging Micro-Industries ⭐</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a class="nav-tab ' . ($view === 'table' ? 'nav-tab-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=titonova-emerging-industries&view=table')) . '">Table View</a>';
    echo '<a class="nav-tab ' . ($view === 'detail' ? 'nav-tab-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=titonova-emerging-industries&view=detail')) . '">Detail Drawer</a>';
    echo '<a class="nav-tab ' . ($view === 'audit' ? 'nav-tab-active' : '') . '" href="' . esc_url(admin_url('admin.php?page=titonova-emerging-industries&view=audit')) . '">Audit Log</a>';
    echo '</h2>';

    if ($view === 'table') {
        $profiles = get_option('titonova_micro_industry_profiles', []);
        if (!is_array($profiles) || empty($profiles)) {
            echo '<p>No emerging profiles yet.</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Name</th><th>Status</th><th>Parent Industry</th><th>Default Confidence</th><th>Last Updated</th></tr></thead><tbody>';
            foreach ($profiles as $key => $profile) {
                if (!is_array($profile)) {
                    continue;
                }
                echo '<tr>';
                echo '<td><a href="' . esc_url(admin_url('admin.php?page=titonova-emerging-industries&view=detail&profile=' . urlencode((string) $key))) . '">' . esc_html((string) ($profile['name'] ?? '')) . '</a></td>';
                echo '<td>' . esc_html((string) ($profile['status'] ?? 'experimental')) . '</td>';
                echo '<td>' . esc_html((string) ($profile['parent_industry'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($profile['default_confidence'] ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) ($profile['last_updated'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    if ($view === 'detail') {
        $profiles = get_option('titonova_micro_industry_profiles', []);
        $selectedKey = sanitize_text_field((string) ($_GET['profile'] ?? ''));
        if (!is_array($profiles) || $selectedKey === '' || !isset($profiles[$selectedKey])) {
            echo '<p>Select a profile from the Table View to inspect details.</p>';
        } else {
            $profile = $profiles[$selectedKey];
            echo '<h3>' . esc_html((string) ($profile['name'] ?? '')) . '</h3>';
            echo '<p><strong>Status:</strong> ' . esc_html((string) ($profile['status'] ?? 'experimental')) . '</p>';
            echo '<p><strong>Parent Industry:</strong> ' . esc_html((string) ($profile['parent_industry'] ?? '')) . '</p>';
            echo '<p><strong>Default Confidence:</strong> ' . esc_html((string) ($profile['default_confidence'] ?? 0)) . '</p>';
            echo '<p><strong>Dominant Goal:</strong> ' . esc_html((string) ($profile['dominant_goal'] ?? '')) . '</p>';
            echo '<p><strong>Trust Signals:</strong> ' . esc_html(implode(', ', array_map('strval', $profile['trust_signals'] ?? []))) . '</p>';
            echo '<p><strong>Feature Defaults:</strong> ' . esc_html(implode(', ', array_map('strval', $profile['feature_defaults'] ?? []))) . '</p>';
        }
    }

    if ($view === 'audit') {
        $query = new WP_Query([
            'post_type' => 'titonova_generation',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Generation</th><th>Signal</th><th>Timestamp</th></tr></thead><tbody>';
        if (!empty($query->posts)) {
            foreach ($query->posts as $post) {
                $signals = get_post_meta($post->ID, '_titonova_learning_signals', true);
                if (!is_array($signals) || empty($signals)) {
                    continue;
                }
                $latest = end($signals);
                echo '<tr>';
                echo '<td>' . esc_html((string) $post->post_title) . '</td>';
                echo '<td>' . esc_html((string) ($latest['signal'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($latest['timestamp'] ?? '')) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
    }

    echo '</div>';
}

function titonova_render_learning_metrics(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $sessionPatterns = get_option('titonova_session_patterns', []);
    $patternsCount = is_array($sessionPatterns) ? count($sessionPatterns) : 0;
    echo '<div class="wrap">';
    echo '<h1>Learning Metrics</h1>';
    echo '<p>Patterns tracked: ' . esc_html((string) $patternsCount) . '</p>';
    echo '</div>';
}

function titonova_render_system_rules(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>System Rules</h1>';
    echo '<ul>';
    echo '<li>Core industry rules are never overridden.</li>';
    echo '<li>Learning influence capped at ±30% of base relevance.</li>';
    echo '<li>Universal layout and accessibility rules are preserved.</li>';
    echo '<li>Learning memory cannot be edited directly.</li>';
    echo '<li>Confidence scores cannot be faked.</li>';
    echo '<li>Audit history cannot be altered.</li>';
    echo '</ul>';
    echo '</div>';
}

function titonova_render_text_field(array $args): void
{
    $option = $args['option'];
    $value = esc_attr(get_option($option, ''));
    $placeholder = esc_attr($args['placeholder'] ?? '');
    echo "<input type=\"text\" class=\"regular-text\" name=\"{$option}\" value=\"{$value}\" placeholder=\"{$placeholder}\" />";
}

function titonova_render_password_field(array $args): void
{
    $option = $args['option'];
    $value = esc_attr(get_option($option, ''));
    $placeholder = esc_attr($args['placeholder'] ?? '');
    echo "<input type=\"password\" class=\"regular-text\" name=\"{$option}\" value=\"{$value}\" placeholder=\"{$placeholder}\" autocomplete=\"new-password\" />";
}

function titonova_render_url_field(array $args): void
{
    $option = $args['option'];
    $value = esc_url(get_option($option, ''));
    $placeholder = esc_attr($args['placeholder'] ?? '');
    echo "<input type=\"url\" class=\"regular-text\" name=\"{$option}\" value=\"{$value}\" placeholder=\"{$placeholder}\" />";
}

function titonova_generate_website(WP_REST_Request $request)
{
    $prompt = (string) $request->get_param('prompt');
    $prompt = sanitize_text_field($prompt);

    if ($prompt === '') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Prompt is required.'
        ], 400);
    }

    $data = titonova_detect_intent_and_context($prompt);
    $heroImage = $request->get_param('hero_image');
    if (!empty($heroImage)) {
        $normalizedHeroImage = titonova_normalize_single_image($heroImage);
        if ($normalizedHeroImage !== '') {
            $data['meta']['hero_image'] = $normalizedHeroImage;
            $data['hero_image'] = $normalizedHeroImage;
        }
    }
    $galleryImages = $request->get_param('gallery_images');
    if (!empty($galleryImages)) {
        $normalizedGalleryImages = titonova_normalize_gallery_images($galleryImages);
        if (!empty($normalizedGalleryImages)) {
            $data['meta']['gallery_images'] = $normalizedGalleryImages;
            $data['gallery_images'] = $normalizedGalleryImages;
        }
    }
    $imageUrls = titonova_generate_image_urls($data['image_prompts'] ?? [], $data);
    $imageUrls = titonova_ensure_hero_image($imageUrls, $data);
    $data['image_urls'] = $imageUrls;
    $data['meta']['image_urls'] = $imageUrls;
    if (!empty($imageUrls['hero_source'])) {
        $data['meta']['hero_source'] = (string) $imageUrls['hero_source'];
    }
    $html = titonova_apply_image_urls_to_html(titonova_build_html($data), $imageUrls);
    $pages = titonova_apply_image_urls_to_pages(titonova_build_multipage_site($data), $imageUrls);

    return new WP_REST_Response([
        'success' => true,
        'html' => $html,
        'pages' => $pages,
        'meta' => $data['meta'],
        'image_prompts' => $data['image_prompts'] ?? [],
        'image_urls' => $data['image_urls'] ?? [],
    ], 200);
}

function titonova_generate_website_v2(WP_REST_Request $request): WP_REST_Response
{
    $prompt = sanitize_text_field((string) $request->get_param('prompt'));
    if ($prompt === '') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Prompt is required.'
        ], 400);
    }

    $previousGenerationId = (int) $request->get_param('previous_generation_id');
    if ($previousGenerationId > 0) {
        titonova_append_learning_signal($previousGenerationId, 'regenerate', [
            'source' => 'generate_v2'
        ]);
    }

    $galleryImages = $request->get_param('gallery_images');
    $heroImage = $request->get_param('hero_image');
    $pipeline = titonova_run_generation_pipeline($prompt, [
        'gallery_images' => $galleryImages,
        'hero_image' => $heroImage,
    ]);

    return new WP_REST_Response([
        'success' => true,
        'pipeline' => $pipeline['pipeline'],
        'html' => $pipeline['html'],
        'pages' => $pipeline['pages'],
        'meta' => $pipeline['data']['meta'],
        'generation_id' => $pipeline['generation_id'],
        'image_prompts' => $pipeline['data']['image_prompts'] ?? [],
        'image_urls' => $pipeline['data']['image_urls'] ?? [],
    ], 200);
}

function titonova_run_generation_pipeline(string $prompt, array $context = []): array
{
    $data = titonova_detect_intent_and_context($prompt);
    if (!empty($context['hero_image'])) {
        $normalizedHeroImage = titonova_normalize_single_image($context['hero_image']);
        if ($normalizedHeroImage !== '') {
            $data['meta']['hero_image'] = $normalizedHeroImage;
            $data['hero_image'] = $normalizedHeroImage;
        }
    }
    if (!empty($context['gallery_images'])) {
        $normalizedGalleryImages = titonova_normalize_gallery_images($context['gallery_images']);
        if (!empty($normalizedGalleryImages)) {
            $data['meta']['gallery_images'] = $normalizedGalleryImages;
            $data['gallery_images'] = $normalizedGalleryImages;
        }
    }

    $intent = titonova_detect_intent($prompt);
    $industry = $data['industry'];
    $layout = titonova_select_layout($data);
    $data['meta']['industry_signals'] = titonova_extract_industry_signals($prompt, $data, $intent);
    $data['meta']['industryConfidenceScore'] = titonova_calculate_industry_confidence(
        $data['meta']['industry_signals'],
        $data
    );
    $microMatch = titonova_match_micro_profile($data);
    if ($microMatch !== null) {
        $data = titonova_apply_micro_profile($data, $microMatch);
    }
    $data = titonova_apply_low_confidence_trend($prompt, $data);
    $icsScore = (int) ($data['meta']['industryConfidenceScore'] ?? 0);
    $data['meta']['icsTier'] = $icsScore >= 80 ? 'high' : ($icsScore >= 50 ? 'mid' : 'low');
    if ($data['meta']['icsTier'] === 'mid') {
        $data['meta']['layoutMode'] = 'hybrid';
    }
    if ($data['meta']['icsTier'] === 'low') {
        $layout = 'universal';
        $data['meta']['layoutMode'] = 'universal';
        $data['meta']['clarificationQuestion'] = titonova_build_clarification_question(
            $data['meta']['industry_signals'],
            $data,
            $intent
        );
    }

    $prompts = titonova_build_layered_prompts($data, $intent, $layout);
    $ai = titonova_ai_generate($prompts, $data);
    $misaligned = titonova_detect_generation_misalignment($data, $ai);
    if ($misaligned) {
        $layout = $layout === 'universal' ? 'modern-default' : $layout;
        $data['meta']['layoutMode'] = 'hybrid';
        $data['meta']['generation_misaligned'] = true;
        $data['meta']['layoutDowngraded'] = 'hybrid';
    }

    $data['ai'] = $ai;
    $data['meta']['layout'] = $layout;
    $data['meta']['intent'] = $intent;

    $imageUrls = titonova_generate_image_urls($data['image_prompts'] ?? [], $data);
    $imageUrls = titonova_ensure_hero_image($imageUrls, $data);
    $data['image_urls'] = $imageUrls;
    $data['meta']['image_urls'] = $imageUrls;
    if (!empty($imageUrls['hero_source'])) {
        $data['meta']['hero_source'] = (string) $imageUrls['hero_source'];
    }

    $html = titonova_apply_image_urls_to_html(titonova_build_html($data), $imageUrls);
    $pages = titonova_apply_image_urls_to_pages(titonova_build_multipage_site($data), $imageUrls);

    $generationId = titonova_save_generation($prompt, $data, $prompts, $ai, $html, $pages);
    if ($generationId) {
        titonova_store_generation_session_summary($generationId, $data, $ai);
    }

    return [
        'generation_id' => $generationId,
        'data' => $data,
        'html' => $html,
        'pages' => $pages,
        'pipeline' => [
            'intent' => $intent,
            'industry' => $industry,
            'layout' => $layout,
            'prompts' => $prompts,
        ],
    ];
}

function titonova_detect_intent(string $prompt): string
{
    $clean = strtolower($prompt);
    if (preg_match('/book|schedule|reserve/', $clean)) {
        return 'booking';
    }
    if (preg_match('/demo|trial|walkthrough/', $clean)) {
        return 'demo';
    }
    if (preg_match('/donate|support|fundraise/', $clean)) {
        return 'donation';
    }
    if (preg_match('/shop|buy|checkout|order/', $clean)) {
        return 'commerce';
    }
    if (preg_match('/hire|recruit|jobs/', $clean)) {
        return 'recruiting';
    }
    return 'lead';
}

function titonova_detect_advanced_features(string $clean): array
{
    $matchesPayments = preg_match('/payment|payments|billing|invoice|checkout|subscription|stripe|paypal|card processing/i', $clean) === 1;
    $matchesDashboard = preg_match('/dashboard|analytics|reporting|admin portal|admin panel|control panel|insights/i', $clean) === 1;
    $matchesAccounts = preg_match('/account|accounts|login|signup|sign up|sign-in|sign in|authentication|user profile|member|membership/i', $clean) === 1;

    $modules = [];
    if ($matchesPayments) {
        $modules[] = 'Payments & billing';
    }
    if ($matchesAccounts) {
        $modules[] = 'Customer accounts';
    }
    if ($matchesDashboard) {
        $modules[] = 'Dashboard & analytics';
    }

    return [
        'hasAdvanced' => $matchesPayments || $matchesDashboard || $matchesAccounts,
        'payments' => $matchesPayments,
        'dashboard' => $matchesDashboard,
        'accounts' => $matchesAccounts,
        'modules' => $modules,
    ];
}

function titonova_extract_industry_signals(string $prompt, array $data, string $intent): array
{
    $clean = strtolower(trim($prompt));
    $clean = preg_replace('/\s+/', ' ', $clean);

    $industry = strtolower($data['industry'] ?? '');
    $audience = strtolower($data['audience'] ?? '');

    $keywords = [
        'healthcare & medical' => ['clinic', 'hospital', 'care', 'patient', 'medical', 'telehealth', 'doctor'],
        'ecommerce & retail' => ['shop', 'store', 'product', 'checkout', 'cart', 'retail', 'sale'],
        'technology & software' => ['software', 'saas', 'platform', 'app', 'api', 'cloud', 'automation'],
        'creative & agency' => ['portfolio', 'creative', 'branding', 'studio', 'campaign', 'design'],
        'restaurant & food services' => ['menu', 'dining', 'restaurant', 'chef', 'reservation', 'catering'],
        'real estate brokerage' => ['listing', 'property', 'realtor', 'real estate', 'home', 'open house'],
        'education & training' => ['course', 'training', 'academy', 'learn', 'curriculum', 'coaching'],
        'nonprofit & community' => ['nonprofit', 'charity', 'donate', 'volunteer', 'impact', 'cause'],
    ];

    $actionVerbs = [
        'booking' => ['book', 'schedule', 'reserve'],
        'commerce' => ['buy', 'shop', 'order', 'checkout'],
        'demo' => ['demo', 'trial', 'walkthrough'],
        'donation' => ['donate', 'support', 'fundraise'],
        'lead' => ['contact', 'inquire', 'consult', 'quote'],
    ];

    $monetization = [
        'pricing' => ['pricing', 'plans', 'packages', 'tuition'],
        'appointments' => ['appointment', 'booking', 'reservation'],
        'sales' => ['sale', 'checkout', 'cart', 'buy'],
        'donations' => ['donate', 'sponsor', 'fundraiser'],
    ];

    $audienceIndicators = [
        'clients' => ['clients', 'businesses', 'teams'],
        'patients' => ['patients', 'caregivers', 'families'],
        'customers' => ['customers', 'buyers', 'shoppers'],
        'students' => ['students', 'learners', 'trainees'],
        'guests' => ['guests', 'diners'],
    ];

    $featureExpectations = [
        'dashboard' => ['dashboard', 'analytics', 'reporting'],
        'gallery' => ['gallery', 'portfolio', 'showcase'],
        'menu' => ['menu', 'dining', 'chef'],
        'booking' => ['booking', 'reservation', 'appointment'],
        'listings' => ['listings', 'properties', 'inventory'],
    ];

    $industryRelevanceMap = [
        'healthcare & medical' => [
            'keyword' => ['healthcare & medical'],
            'action' => ['booking', 'lead'],
            'monetization' => ['appointments'],
            'audience' => ['patients'],
            'feature' => ['booking', 'dashboard'],
        ],
        'ecommerce & retail' => [
            'keyword' => ['ecommerce & retail'],
            'action' => ['commerce'],
            'monetization' => ['sales', 'pricing'],
            'audience' => ['customers'],
            'feature' => ['listings'],
        ],
        'technology & software' => [
            'keyword' => ['technology & software'],
            'action' => ['demo', 'lead'],
            'monetization' => ['pricing', 'sales'],
            'audience' => ['clients'],
            'feature' => ['dashboard'],
        ],
        'creative & agency' => [
            'keyword' => ['creative & agency'],
            'action' => ['lead'],
            'monetization' => ['pricing'],
            'audience' => ['clients'],
            'feature' => ['gallery'],
        ],
        'restaurant & food services' => [
            'keyword' => ['restaurant & food services'],
            'action' => ['booking', 'commerce'],
            'monetization' => ['sales', 'appointments'],
            'audience' => ['guests'],
            'feature' => ['menu', 'booking'],
        ],
        'real estate brokerage' => [
            'keyword' => ['real estate brokerage'],
            'action' => ['lead'],
            'monetization' => ['sales'],
            'audience' => ['clients'],
            'feature' => ['listings'],
        ],
        'education & training' => [
            'keyword' => ['education & training'],
            'action' => ['lead', 'booking'],
            'monetization' => ['pricing'],
            'audience' => ['students'],
            'feature' => ['dashboard'],
        ],
        'nonprofit & community' => [
            'keyword' => ['nonprofit & community'],
            'action' => ['donation', 'lead'],
            'monetization' => ['donations'],
            'audience' => ['clients'],
            'feature' => [],
        ],
    ];

    $industryRelevance = $industryRelevanceMap[$industry] ?? [];
    $learningWeights = titonova_get_industry_signal_weights($industry);
    $confidenceDampener = titonova_get_industry_confidence_dampener($industry);
    $scoreRelevance = function (string $type, string $label) use ($industryRelevance, $intent, $learningWeights, $confidenceDampener): int {
        $baseRelevance = 0;
        $expected = $industryRelevance[$type] ?? [];
        if (!empty($expected) && in_array($label, $expected, true)) {
            $baseRelevance += 2;
        }
        if ($type === 'keyword' && in_array($label, $expected, true)) {
            $baseRelevance += 1;
        }
        if ($type === 'action' && $label === $intent) {
            $baseRelevance += 1;
        }

        $learningDelta = 0;
        if (isset($learningWeights[$type][$label])) {
            $learningDelta = (int) $learningWeights[$type][$label];
        }

        $maxLearningInfluence = $baseRelevance > 0 ? (int) ceil($baseRelevance * 0.3) : 0;
        if ($maxLearningInfluence > 0) {
            $learningDelta = max(-$maxLearningInfluence, min($maxLearningInfluence, $learningDelta));
        } else {
            $learningDelta = 0;
        }

        $relevance = $baseRelevance + $learningDelta;
        if ($confidenceDampener > 0) {
            $relevance -= $confidenceDampener;
        }

        return $relevance;
    };

    $scoreMatches = function (array $terms, int $weight = 2) use ($clean): int {
        $score = 0;
        foreach ($terms as $term) {
            if (str_contains($clean, $term)) {
                $score += $weight;
            }
        }
        return $score;
    };

    $industryScore = 0;
    if (isset($keywords[$industry])) {
        $industryScore += $scoreMatches($keywords[$industry], 3);
    }

    $intentScore = 0;
    if (isset($actionVerbs[$intent])) {
        $intentScore += $scoreMatches($actionVerbs[$intent], 2);
    }

    $keywordSignals = [];
    foreach ($keywords as $label => $terms) {
        $baseScore = $scoreMatches($terms, 2);
        $relevance = $scoreRelevance('keyword', $label);
        $score = $baseScore + $relevance;
        if ($score > 0) {
            $keywordSignals[] = [
                'type' => 'keyword',
                'label' => $label,
                'score' => $score,
                'base_score' => $baseScore,
                'relevance' => $relevance,
                'terms' => $terms,
            ];
        }
    }

    $verbSignals = [];
    foreach ($actionVerbs as $label => $terms) {
        $baseScore = $scoreMatches($terms, 2);
        $relevance = $scoreRelevance('action', $label);
        $score = $baseScore + $relevance;
        if ($score > 0) {
            $verbSignals[] = [
                'type' => 'action',
                'label' => $label,
                'score' => $score,
                'base_score' => $baseScore,
                'relevance' => $relevance,
                'terms' => $terms,
            ];
        }
    }

    $monetizationSignals = [];
    foreach ($monetization as $label => $terms) {
        $baseScore = $scoreMatches($terms, 2);
        $relevance = $scoreRelevance('monetization', $label);
        $score = $baseScore + $relevance;
        if ($score > 0) {
            $monetizationSignals[] = [
                'type' => 'monetization',
                'label' => $label,
                'score' => $score,
                'base_score' => $baseScore,
                'relevance' => $relevance,
                'terms' => $terms,
            ];
        }
    }

    $audienceSignals = [];
    foreach ($audienceIndicators as $label => $terms) {
        $baseScore = $scoreMatches($terms, 1);
        $relevance = $scoreRelevance('audience', $label);
        $score = $baseScore + $relevance;
        if ($score > 0 || ($audience !== '' && str_contains($audience, $label))) {
            $audienceSignals[] = [
                'type' => 'audience',
                'label' => $label,
                'score' => $score,
                'base_score' => $baseScore,
                'relevance' => $relevance,
                'terms' => $terms,
            ];
        }
    }

    $featureSignals = [];
    foreach ($featureExpectations as $label => $terms) {
        $baseScore = $scoreMatches($terms, 2);
        $relevance = $scoreRelevance('feature', $label);
        $score = $baseScore + $relevance;
        if ($score > 0) {
            $featureSignals[] = [
                'type' => 'feature',
                'label' => $label,
                'score' => $score,
                'base_score' => $baseScore,
                'relevance' => $relevance,
                'terms' => $terms,
            ];
        }
    }

    $signals = array_merge($keywordSignals, $verbSignals, $monetizationSignals, $audienceSignals, $featureSignals);
    usort($signals, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return [
        'industry' => $industry,
        'intent' => $intent,
        'industry_score' => $industryScore,
        'intent_score' => $intentScore,
        'signals' => $signals,
    ];
}

function titonova_calculate_industry_confidence(array $signals, array $data): int
{
    $score = 0;

    $industryScore = (int) ($signals['industry_score'] ?? 0);
    $hasStrongKeyword = $industryScore >= 3;
    if ($hasStrongKeyword) {
        $score += 20;
    }

    $signalList = $signals['signals'] ?? [];
    $hasMonetization = false;
    $hasFeature = false;
    $hasAudience = false;
    foreach ($signalList as $signal) {
        $type = $signal['type'] ?? '';
        $baseScore = (int) ($signal['base_score'] ?? $signal['score'] ?? 0);
        $relevance = (int) ($signal['relevance'] ?? 0);
        $aligned = $baseScore > 0 && ($relevance > 0 || $baseScore >= 2);

        if ($type === 'monetization' && $aligned) {
            $hasMonetization = true;
        }
        if ($type === 'feature' && $aligned) {
            $hasFeature = true;
        }
        if ($type === 'audience' && ($aligned || $baseScore > 0)) {
            $hasAudience = true;
        }
    }

    if ($hasMonetization) {
        $score += 20;
    }
    if ($hasFeature) {
        $score += 20;
    }

    $audience = trim((string) ($data['audience'] ?? ''));
    if ($hasAudience || $audience !== '') {
        $score += 20;
    }

    $tone = trim((string) ($data['meta']['tone'] ?? $data['tone'] ?? ''));
    if ($tone !== '') {
        $score += 20;
    }

    return min(100, $score);
}

function titonova_build_clarification_question(array $signals, array $data, string $intent): ?string
{
    $icsScore = (int) ($data['meta']['industryConfidenceScore'] ?? 0);
    if ($icsScore >= 50) {
        return null;
    }

    $industryScore = (int) ($signals['industry_score'] ?? 0);
    $intentScore = (int) ($signals['intent_score'] ?? 0);
    $audience = trim((string) ($data['audience'] ?? ''));

    if ($industryScore < 3 && $intentScore === 0 && $audience === '') {
        return 'What industry is this website for?';
    }

    if ($industryScore < 3) {
        return 'What industry is this website for?';
    }

    if ($audience === '') {
        return 'Who is the primary audience for this website?';
    }

    if ($intentScore === 0 && $intent === 'lead') {
        return 'What is the primary goal for this website (e.g., contact, book, buy)?';
    }

    return null;
}

function titonova_detect_generation_misalignment(array $data, array $ai): bool
{
    $industry = strtolower((string) ($data['industry'] ?? ''));
    if ($industry === '') {
        return false;
    }

    $keywords = [
        'healthcare & medical' => ['clinic', 'hospital', 'care', 'patient', 'medical', 'telehealth', 'doctor'],
        'ecommerce & retail' => ['shop', 'store', 'product', 'checkout', 'cart', 'retail', 'sale'],
        'technology & software' => ['software', 'saas', 'platform', 'app', 'api', 'cloud', 'automation'],
        'creative & agency' => ['portfolio', 'creative', 'branding', 'studio', 'campaign', 'design'],
        'restaurant & food services' => ['menu', 'dining', 'restaurant', 'chef', 'reservation', 'catering'],
        'real estate brokerage' => ['listing', 'property', 'realtor', 'real estate', 'home', 'open house'],
        'education & training' => ['course', 'training', 'academy', 'learn', 'curriculum', 'coaching'],
        'nonprofit & community' => ['nonprofit', 'charity', 'donate', 'volunteer', 'impact', 'cause'],
    ];

    $textParts = [];
    foreach (['headline', 'subheadline', 'cta'] as $key) {
        if (!empty($ai[$key]) && is_string($ai[$key])) {
            $textParts[] = $ai[$key];
        }
    }
    if (!empty($ai['sections']) && is_array($ai['sections'])) {
        foreach ($ai['sections'] as $section) {
            if (is_string($section)) {
                $textParts[] = $section;
            } elseif (is_array($section)) {
                $textParts[] = implode(' ', array_filter($section, 'is_string'));
            }
        }
    }

    $combined = strtolower(trim(implode(' ', $textParts)));
    if ($combined === '') {
        return false;
    }

    $scores = [];
    foreach ($keywords as $label => $terms) {
        $score = 0;
        foreach ($terms as $term) {
            if (str_contains($combined, $term)) {
                $score += 1;
            }
        }
        $scores[$label] = $score;
    }

    arsort($scores);
    $topIndustry = array_key_first($scores);
    $topScore = $scores[$topIndustry] ?? 0;
    $selectedScore = $scores[$industry] ?? 0;

    return $topIndustry !== null
        && $topIndustry !== $industry
        && $topScore >= 3
        && $topScore > $selectedScore + 1;
}

function titonova_select_layout(array $data): string
{
    $industry = strtolower($data['industry']);
    $style = $data['meta']['style'];
    $layoutMode = $data['meta']['layoutMode'] ?? '';

    if ($layoutMode === 'universal') {
        return 'universal';
    }
    $preferredLayout = titonova_get_industry_layout_preference($industry);

    if ($preferredLayout !== '' && $layoutMode !== 'universal') {
        return $preferredLayout;
    }

    $layoutMap = [
        'ecommerce & retail' => 'storefront',
        'restaurant & food services' => 'menu-focus',
        'real estate brokerage' => 'listings-grid',
        'hospitality & travel' => 'booking-hero',
        'healthcare & medical' => 'care-focused',
        'technology & software' => 'product-saas',
        'fintech & payments' => 'trust-first',
        'creative & agency' => 'portfolio',
    ];

    if (isset($layoutMap[$industry])) {
        return $layoutMap[$industry];
    }

    if (in_array($style, ['minimal', 'elegant'], true)) {
        return 'editorial';
    }

    return 'modern-default';
}

function titonova_build_layered_prompts(array $data, string $intent, string $layout): array
{
    $audience = $data['audience'] !== '' ? $data['audience'] : 'modern businesses';
    $icsTier = $data['meta']['icsTier'] ?? 'high';
    if (($data['meta']['layoutMode'] ?? '') === 'hybrid' && $icsTier !== 'low') {
        $icsTier = 'mid';
    }
    $microPromptHigh = 'Focus on learning outcomes, curriculum clarity, supportive tone, and measurable progress. Highlight enrollment paths, structured modules, and learner success stories. Use accessible, encouraging language and clear calls to action.';
    $microPromptMid = 'Use a hybrid industry layout with flexible sections. Avoid niche assumptions. Emphasize general conversion CTAs and adaptable messaging.';
    $microPromptLow = 'Use a universal professional structure. Avoid pricing, advanced features, or niche claims. Keep messaging broad and trustworthy.';
    $microPrompt = $icsTier === 'high' ? $microPromptHigh : ($icsTier === 'mid' ? $microPromptMid : $microPromptLow);
    $accuracyPrompt = $icsTier === 'high'
        ? 'Review the selected industry and confidence score before final generation. Never state unverifiable or incorrect claims. Avoid assumptions not supported by the prompt.'
        : ($icsTier === 'mid'
            ? 'Review the selected industry and confidence score before final generation. If uncertain, reduce specificity and avoid niche claims. Prefer adaptable language over assumptions. Never generate incorrect claims.'
            : 'Review the selected industry and confidence score before final generation. Use broad, adaptable language and avoid niche assumptions. Never generate incorrect claims or pricing/advanced features.');
    $conversionPrompt = $icsTier === 'high'
        ? 'Apply aggressive optimization and conversion logic: primary CTA above the fold, repeated high-intent CTAs, pricing and social proof where appropriate.'
        : ($icsTier === 'mid'
            ? 'Use flexible, general conversion CTAs without niche assumptions.'
            : 'Use universal CTAs and avoid pricing or advanced features.');
    $industryPrompt = "You are creating a {$data['industry']} website for {$data['business']}.";
    $microProfile = $data['meta']['microProfile'] ?? null;
    $microProfilePrompt = '';
    if (is_array($microProfile)) {
        $microName = $microProfile['name'] ?? '';
        if ($microName !== '') {
            $industryPrompt = "You are creating a {$microName} website for {$data['business']}.";
        }
        $microProfilePrompt = titonova_build_micro_profile_prompt($microProfile);
    }
    $tonePrompt = "Use a calm, professional, and reassuring tone with {$data['meta']['style']} styling. Prioritize clarity, trust, safety, compliance, and compassion. Avoid aggressive or hype language. {$microProfilePrompt} {$microPrompt} {$conversionPrompt} {$accuracyPrompt}";
    $toneVariantsPrompt = "Provide 3 micro-variants for tone: luxury, corporate, and startup. Keep each under 20 words.";
    $layoutPrompt = "Follow the {$layout} layout pattern. Ensure strong hero, trust signals, and clear CTAs. {$microProfilePrompt} {$microPrompt} {$conversionPrompt} {$accuracyPrompt}";
    $sectionHeroPrompt = "Hero section: craft a headline and subheadline that clearly state what the company does and who it serves. Keep it calm, professional, and reassuring for {$audience}. {$microPrompt} {$conversionPrompt} {$accuracyPrompt}";
    $sectionServicesPrompt = "Services section: 4-6 bullets tailored to {$data['industry']} with outcome-first language. {$microPrompt} {$accuracyPrompt}";
    $sectionCtaPrompt = "CTA section: write a strong, professional contact CTA line and button label for intent {$intent}. {$microPrompt} {$conversionPrompt} {$accuracyPrompt}";
    $contentPrompt = "Before generating, silently critique your own plan to improve clarity, layout, and conversion. Then generate headings, short paragraphs, and bullet points for services, features, and FAQs. Audience: {$audience}. {$microPrompt} {$conversionPrompt} {$accuracyPrompt}";
    $seoPrompt = "SEO enhancement: produce a meta title (<= 60 chars), meta description (<= 155 chars), and 5 target keywords.";

    return [
        'industry' => $industryPrompt,
        'tone' => $tonePrompt,
        'tone_variants' => $toneVariantsPrompt,
        'layout' => $layoutPrompt,
        'sections' => [
            'hero' => $sectionHeroPrompt,
            'services' => $sectionServicesPrompt,
            'cta' => $sectionCtaPrompt,
        ],
        'micro' => $microPrompt,
        'conversion' => $conversionPrompt,
        'content' => $contentPrompt,
        'seo' => $seoPrompt,
        'intent' => "Primary intent: {$intent}.",
    ];
}

function titonova_ai_generate(array $prompts, array $data): array
{
    $default = [
        'headline' => $data['tagline'],
        'subheadline' => $data['about'],
        'cta' => $data['cta'],
        'sections' => [
            'hero' => "{$data['business']} helps {$data['industry']} teams move faster with modern experiences.",
            'value' => 'Performance-led design, clear messaging, and scalable delivery.',
        ],
    ];

    $generated = apply_filters('titonova_ai_generate', $default, $prompts, $data);
    if (!is_array($generated)) {
        return $default;
    }

    return array_merge($default, $generated);
}

function titonova_save_generation(string $prompt, array $data, array $prompts, array $ai, string $html = '', array $pages = []): ?int
{
    $postId = wp_insert_post([
        'post_type' => 'titonova_generation',
        'post_status' => 'publish',
        'post_title' => $data['business'] . ' — ' . $data['industry'],
    ], true);

    if (is_wp_error($postId)) {
        return null;
    }

    $payload = [
        'prompt' => $prompt,
        'data' => $data,
        'prompts' => $prompts,
        'ai' => $ai,
        'html' => $html ?? null,
        'pages' => $pages ?? null,
    ];

    update_post_meta($postId, '_titonova_generation_payload', wp_json_encode($payload));
    update_post_meta($postId, '_titonova_prompt', $prompt);
    update_post_meta($postId, '_titonova_prompt_hash', md5($prompt));
    update_post_meta($postId, '_titonova_industry', (string) ($data['industry'] ?? ''));
    update_post_meta(
        $postId,
        '_titonova_industry_confidence',
        (int) ($data['meta']['industryConfidenceScore'] ?? 0)
    );
    titonova_record_low_confidence_prompt($prompt, (int) ($data['meta']['industryConfidenceScore'] ?? 0));

    $userId = get_current_user_id();
    if ($userId > 0) {
        update_post_meta($postId, '_titonova_generation_user_id', $userId);
        titonova_increment_user_metric($userId, '_titonova_sites_generated', 1);
        titonova_update_user_activity($userId, 'generation');
        titonova_update_user_tier($userId);
        titonova_update_user_lifecycle_status($userId);
        titonova_update_user_tags($userId, [
            'ics' => (int) ($data['meta']['industryConfidenceScore'] ?? 0),
            'intent' => (string) ($data['meta']['intent'] ?? ''),
            'micro_status' => (string) ($data['meta']['microProfileStatus'] ?? ''),
        ]);
    }

    return (int) $postId;
}

function titonova_track_learning_signal(WP_REST_Request $request): WP_REST_Response
{
    $generationId = (int) $request->get_param('generation_id');
    $signal = sanitize_text_field((string) $request->get_param('signal'));
    $metadata = $request->get_param('metadata');

    if ($generationId < 1 || $signal === '') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'generation_id and signal are required.'
        ], 400);
    }

    $allowedSignals = [
        'edit_section_headings',
        'minor_text_edits',
        'layout_content_edit',
        'delete_sections',
        'add_sections',
        'regenerate',
        'change_industry',
        'activate_advanced_features',
        'publish_without_changes',
    ];

    if (!in_array($signal, $allowedSignals, true)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Unsupported signal.'
        ], 400);
    }

    $sanitizedMetadata = titonova_sanitize_signal_metadata($metadata);
    $stored = titonova_append_learning_signal($generationId, $signal, $sanitizedMetadata);

    return new WP_REST_Response([
        'success' => $stored,
    ], $stored ? 200 : 500);
}

function titonova_increment_user_metric(int $userId, string $metaKey, int $increment = 1): void
{
    $current = (int) get_user_meta($userId, $metaKey, true);
    $next = max(0, $current + $increment);
    update_user_meta($userId, $metaKey, $next);
}

function titonova_update_user_tier(int $userId): string
{
    $generated = (int) get_user_meta($userId, '_titonova_sites_generated', true);
    $published = (int) get_user_meta($userId, '_titonova_sites_published', true);
    $upgrades = (int) get_user_meta($userId, '_titonova_upgrades', true);
    $enterpriseFlag = get_user_meta($userId, '_titonova_is_enterprise', true);
    $accountType = strtolower((string) get_user_meta($userId, '_titonova_account_type', true));

    $tier = 'Visitor';
    if ($accountType === 'enterprise' || $enterpriseFlag) {
        $tier = 'Enterprise / Agency';
    } elseif ($generated >= 5 || $upgrades > 0) {
        $tier = 'Power User';
    } elseif ($published >= 1) {
        $tier = 'Publisher';
    } elseif ($generated >= 1) {
        $tier = 'Builder';
    }

    update_user_meta($userId, '_titonova_user_tier', $tier);

    return $tier;
}

function titonova_update_user_activity(int $userId, string $event): void
{
    $now = current_time('timestamp');
    update_user_meta($userId, '_titonova_last_activity_at', $now);

    if ($event === 'generation') {
        update_user_meta($userId, '_titonova_last_generation_at', $now);
    }

    if ($event === 'publish') {
        update_user_meta($userId, '_titonova_last_publish_at', $now);
    }
}

function titonova_get_generation_user_id(int $generationId): int
{
    return (int) get_post_meta($generationId, '_titonova_generation_user_id', true);
}

function titonova_update_user_tags(int $userId, array $context = []): array
{
    $tags = get_user_meta($userId, '_titonova_user_tags', true);
    $tags = is_array($tags) ? $tags : [];

    $intent = (string) ($context['intent'] ?? '');
    $ics = (int) ($context['ics'] ?? 0);
    $microStatus = (string) ($context['micro_status'] ?? '');

    $generated = (int) get_user_meta($userId, '_titonova_sites_generated', true);
    $published = (int) get_user_meta($userId, '_titonova_sites_published', true);
    $regenerates = (int) get_user_meta($userId, '_titonova_regenerate_count', true);
    $tier = (string) get_user_meta($userId, '_titonova_user_tier', true);

    if (in_array($intent, ['booking', 'commerce'], true)) {
        $tags['High Intent'] = true;
    }
    if ($ics > 0 && $ics < 50) {
        $tags['Low Confidence Detection'] = true;
    }
    if ($microStatus === 'experimental') {
        $tags['Emerging Industry Driver'] = true;
    }
    if ($regenerates >= 3 || !empty($context['regenerate'])) {
        $tags['Frequent Regenerator'] = true;
    }
    if ($tier === 'Enterprise / Agency' || ($generated >= 5 && $published >= 2)) {
        $tags['Likely Agency'] = true;
    }

    $tags = array_filter($tags, static function ($value) {
        return (bool) $value;
    });

    update_user_meta($userId, '_titonova_user_tags', $tags);

    return $tags;
}

function titonova_update_user_lifecycle_status(int $userId): string
{
    $generated = (int) get_user_meta($userId, '_titonova_sites_generated', true);
    $published = (int) get_user_meta($userId, '_titonova_sites_published', true);
    $lastActivity = (int) get_user_meta($userId, '_titonova_last_activity_at', true);

    $now = current_time('timestamp');
    $daysSinceActivity = $lastActivity > 0 ? floor(($now - $lastActivity) / DAY_IN_SECONDS) : 999;

    $status = 'Dormant';
    if ($published >= 1) {
        $status = 'Converted';
    } elseif ($generated >= 1) {
        $status = 'At Risk';
    }

    if ($status !== 'Converted' && $daysSinceActivity <= 7) {
        $status = 'Active';
    } elseif ($status !== 'Converted' && $daysSinceActivity <= 30 && $daysSinceActivity > 7) {
        $status = 'Inactive';
    } elseif ($status !== 'Converted' && $daysSinceActivity > 30 && $generated >= 1 && $published === 0) {
        $status = 'At Risk';
    } elseif ($status !== 'Converted' && $daysSinceActivity > 30) {
        $status = 'Dormant';
    }

    update_user_meta($userId, '_titonova_user_lifecycle_status', $status);

    return $status;
}

function titonova_handle_user_admin_action(WP_REST_Request $request): WP_REST_Response
{
    if (!current_user_can('manage_options')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Unauthorized.'
        ], 403);
    }

    $userId = (int) $request->get_param('user_id');
    $action = sanitize_text_field((string) $request->get_param('action'));
    $payload = $request->get_param('payload');

    if ($userId < 1 || $action === '') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'user_id and action are required.'
        ], 400);
    }

    $allowed = [
        'change_role',
        'upgrade_tier',
        'flag_review',
        'suspend',
        'reactivate',
        'assign_note',
    ];

    if (!in_array($action, $allowed, true)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Unsupported action.'
        ], 400);
    }

    $payload = is_array($payload) ? $payload : [];
    if (!titonova_validate_admin_action_payload($payload)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Forbidden payload fields.'
        ], 403);
    }

    $result = titonova_apply_user_admin_action($userId, $action, $payload);

    return new WP_REST_Response([
        'success' => $result,
    ], $result ? 200 : 500);
}

function titonova_validate_admin_action_payload(array $payload): bool
{
    $forbiddenKeys = [
        'learning_memory',
        'confidence_score',
        'ics',
        'audit',
        'audit_history',
    ];

    foreach ($forbiddenKeys as $key) {
        if (array_key_exists($key, $payload)) {
            return false;
        }
    }

    return true;
}

function titonova_apply_user_admin_action(int $userId, string $action, $payload): bool
{
    $user = get_user_by('id', $userId);
    if (!$user) {
        return false;
    }

    $payload = is_array($payload) ? $payload : [];

    switch ($action) {
        case 'change_role':
            $role = sanitize_text_field((string) ($payload['role'] ?? ''));
            if ($role === '') {
                return false;
            }
            $user->set_role($role);
            return true;
        case 'upgrade_tier':
            $tier = sanitize_text_field((string) ($payload['tier'] ?? ''));
            if ($tier === '') {
                return false;
            }
            update_user_meta($userId, '_titonova_user_tier', $tier);
            return true;
        case 'flag_review':
            update_user_meta($userId, '_titonova_flagged_for_review', true);
            return true;
        case 'suspend':
            update_user_meta($userId, '_titonova_user_suspended', true);
            update_user_meta($userId, '_titonova_user_lifecycle_status', 'Suspended');
            return true;
        case 'reactivate':
            update_user_meta($userId, '_titonova_user_suspended', false);
            titonova_update_user_lifecycle_status($userId);
            return true;
        case 'assign_note':
            $note = sanitize_textarea_field((string) ($payload['note'] ?? ''));
            if ($note === '') {
                return false;
            }
            $notes = get_user_meta($userId, '_titonova_internal_notes', true);
            $notes = is_array($notes) ? $notes : [];
            $notes[] = [
                'note' => $note,
                'author' => (int) get_current_user_id(),
                'timestamp' => current_time('mysql'),
            ];
            update_user_meta($userId, '_titonova_internal_notes', $notes);
            return true;
    }

    return false;
}

function titonova_append_learning_signal(int $generationId, string $signal, array $metadata = []): bool
{
    $existing = get_post_meta($generationId, '_titonova_learning_signals', true);
    $signals = is_array($existing) ? $existing : [];

    $signals[] = [
        'signal' => $signal,
        'metadata' => $metadata,
        'timestamp' => current_time('mysql'),
    ];

    $stored = (bool) update_post_meta($generationId, '_titonova_learning_signals', $signals);
    if (!$stored) {
        return false;
    }

    $lfs = titonova_calculate_learning_feedback_score($signals);
    update_post_meta($generationId, '_titonova_learning_feedback_score', $lfs);
    titonova_apply_learning_feedback($generationId, $signal, $metadata, $lfs);
    titonova_update_session_patterns_from_signal($generationId, $signal, $metadata, $lfs);

    if ($signal === 'regenerate') {
        $userId = titonova_get_generation_user_id($generationId);
        if ($userId > 0) {
            titonova_increment_user_metric($userId, '_titonova_regenerate_count', 1);
            titonova_update_user_tags($userId, ['regenerate' => true]);
        }
    }

    return true;
}

function titonova_calculate_learning_feedback_score(array $signals): int
{
    $score = 0;
    foreach ($signals as $entry) {
        $signal = $entry['signal'] ?? '';
        switch ($signal) {
            case 'publish_without_changes':
                $score += 30;
                break;
            case 'minor_text_edits':
            case 'edit_section_headings':
                $score += 20;
                break;
            case 'layout_content_edit':
                $score += 10;
                break;
            case 'regenerate':
                $score -= 10;
                break;
            case 'delete_sections':
                $score -= 20;
                break;
            case 'change_industry':
                $score -= 30;
                break;
        }
    }

    if ($score > 100) {
        return 100;
    }
    if ($score < -100) {
        return -100;
    }

    return $score;
}

function titonova_apply_learning_feedback(int $generationId, string $signal, array $metadata, int $lfs): void
{
    $industry = (string) get_post_meta($generationId, '_titonova_industry', true);
    $ics = (int) get_post_meta($generationId, '_titonova_industry_confidence', true);

    if ($industry === '') {
        $payload = get_post_meta($generationId, '_titonova_generation_payload', true);
        $decoded = json_decode((string) $payload, true);
        if (is_array($decoded) && !empty($decoded['data']['industry'])) {
            $industry = (string) $decoded['data']['industry'];
        }
        if ($ics === 0 && is_array($decoded)) {
            $ics = (int) ($decoded['data']['meta']['industryConfidenceScore'] ?? 0);
        }
    }

    $finalStructure = $metadata['structure'] ?? $metadata['sections'] ?? null;
    $finalLayout = $metadata['layout'] ?? null;
    if (is_array($finalStructure) || is_string($finalStructure)) {
        update_post_meta($generationId, '_titonova_final_structure', $finalStructure);
    }

    update_post_meta($generationId, '_titonova_learning_summary', [
        'industry' => $industry,
        'initial_ics' => $ics,
        'lfs' => $lfs,
        'final_structure' => $finalStructure,
        'final_layout' => $finalLayout,
    ]);

    if ($industry === '') {
        return;
    }

    if ($lfs >= 20 && $finalLayout !== null) {
        titonova_set_industry_layout_preference($industry, (string) $finalLayout);
        titonova_adjust_industry_confidence_dampener($industry, -1);
    } elseif ($lfs >= 0) {
        titonova_set_industry_layout_mode($industry, 'hybrid');
    } else {
        titonova_adjust_industry_confidence_dampener($industry, 1);
    }

    if ($lfs >= 20) {
        $signalWeights = [
            'publish_without_changes' => 2,
            'minor_text_edits' => 1,
            'layout_content_edit' => 1,
            'edit_section_headings' => 1,
        ];
        $adjustment = $signalWeights[$signal] ?? 0;
    } elseif ($lfs < 0) {
        $signalWeights = [
            'regenerate' => -1,
            'delete_sections' => -2,
            'change_industry' => -3,
        ];
        $adjustment = $signalWeights[$signal] ?? 0;
    } else {
        $adjustment = 0;
    }

    if ($adjustment === 0) {
        return;
    }

    $signals = $metadata['signals'] ?? [];
    if (!is_array($signals)) {
        $signals = [];
    }

    $weights = get_option('titonova_industry_signal_weights', []);
    if (!is_array($weights)) {
        $weights = [];
    }
    if (!isset($weights[$industry]) || !is_array($weights[$industry])) {
        $weights[$industry] = [];
    }

    foreach ($signals as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $type = (string) ($entry['type'] ?? '');
        $label = (string) ($entry['label'] ?? '');
        if ($type === '' || $label === '') {
            continue;
        }
        if (!isset($weights[$industry][$type]) || !is_array($weights[$industry][$type])) {
            $weights[$industry][$type] = [];
        }
        $current = (int) ($weights[$industry][$type][$label] ?? 0);
        $next = $current + $adjustment;
        if ($next > 5) {
            $next = 5;
        }
        if ($next < -5) {
            $next = -5;
        }
        $weights[$industry][$type][$label] = $next;
    }

    update_option('titonova_industry_signal_weights', $weights, false);
}

function titonova_get_industry_signal_weights(string $industry): array
{
    $weights = get_option('titonova_industry_signal_weights', []);
    if (!is_array($weights)) {
        return [];
    }
    $industryWeights = $weights[$industry] ?? [];
    return is_array($industryWeights) ? $industryWeights : [];
}

function titonova_get_industry_layout_preference(string $industry): string
{
    $layouts = get_option('titonova_industry_layout_preference', []);
    if (!is_array($layouts)) {
        return '';
    }
    $layout = $layouts[$industry] ?? '';
    return is_string($layout) ? $layout : '';
}

function titonova_apply_low_confidence_trend(string $prompt, array $data): array
{
    if (!titonova_is_low_confidence_trending($prompt)) {
        return $data;
    }

    $current = (int) ($data['meta']['industryConfidenceScore'] ?? 0);
    $adjusted = max(0, $current - 20);
    $data['meta']['industryConfidenceScore'] = $adjusted;
    $data['meta']['lowConfidenceTrend'] = true;
    $data['meta']['layoutMode'] = 'universal';

    return $data;
}

function titonova_record_low_confidence_prompt(string $prompt, int $ics): void
{
    if ($ics >= 50) {
        return;
    }

    $hash = md5(trim(strtolower($prompt)));
    $store = get_option('titonova_low_confidence_prompts', []);
    if (!is_array($store)) {
        $store = [];
    }

    $entry = $store[$hash] ?? ['count' => 0, 'last_seen' => 0];
    $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
    $entry['last_seen'] = time();
    $store[$hash] = $entry;

    update_option('titonova_low_confidence_prompts', $store, false);
}

function titonova_is_low_confidence_trending(string $prompt): bool
{
    $hash = md5(trim(strtolower($prompt)));
    $store = get_option('titonova_low_confidence_prompts', []);
    if (!is_array($store) || empty($store[$hash])) {
        return false;
    }

    $entry = $store[$hash];
    $count = (int) ($entry['count'] ?? 0);
    $lastSeen = (int) ($entry['last_seen'] ?? 0);
    $windowSeconds = 30 * DAY_IN_SECONDS;

    return $count >= 3 && $lastSeen >= (time() - $windowSeconds);
}

function titonova_set_industry_layout_preference(string $industry, string $layout): void
{
    if ($industry === '' || $layout === '') {
        return;
    }
    $layouts = get_option('titonova_industry_layout_preference', []);
    if (!is_array($layouts)) {
        $layouts = [];
    }
    $layouts[$industry] = $layout;
    update_option('titonova_industry_layout_preference', $layouts, false);
}

function titonova_set_industry_layout_mode(string $industry, string $mode): void
{
    if ($industry === '' || $mode === '') {
        return;
    }
    $modes = get_option('titonova_industry_layout_mode', []);
    if (!is_array($modes)) {
        $modes = [];
    }
    $modes[$industry] = $mode;
    update_option('titonova_industry_layout_mode', $modes, false);
}

function titonova_get_industry_confidence_dampener(string $industry): int
{
    $dampeners = get_option('titonova_industry_confidence_dampener', []);
    if (!is_array($dampeners)) {
        return 0;
    }
    $value = (int) ($dampeners[$industry] ?? 0);
    if ($value < 0) {
        return 0;
    }
    if ($value > 3) {
        return 3;
    }
    return $value;
}

function titonova_schedule_pattern_review(): void
{
    if (!wp_next_scheduled('titonova_review_learning_patterns')) {
        wp_schedule_event(time() + 3600, 'daily', 'titonova_review_learning_patterns');
    }
}

function titonova_schedule_site_health_scan(): void
{
    if (!wp_next_scheduled('titonova_site_health_scan')) {
        wp_schedule_event(time() + 900, 'daily', 'titonova_site_health_scan');
    }
}

function titonova_run_site_health_scan(): void
{
    global $wpdb;

    $table = titonova_sites_table();
    if (!titonova_table_exists($table)) {
        return;
    }

    $rows = $wpdb->get_results("SELECT id, html, css, config_json FROM {$table}", ARRAY_A);
    if (empty($rows)) {
        return;
    }

    foreach ($rows as $row) {
        $siteId = (string) ($row['id'] ?? '');
        $html = (string) ($row['html'] ?? '');
        $css = (string) ($row['css'] ?? '');
        $config = json_decode((string) ($row['config_json'] ?? ''), true);
        $config = is_array($config) ? $config : [];
        $meta = $config['meta'] ?? [];

        $signals = titonova_calculate_confidence_signals($html, $css, $config);
        $confidenceScore = titonova_calculate_confidence_score($signals);

        $previousScore = (float) ($meta['confidence_score'] ?? 0);
        $meta['confidence_score'] = $confidenceScore;
        $meta['confidence_signals'] = $signals;

        if ($previousScore > 0 && $confidenceScore < $previousScore) {
            $meta['confidence_soft_hint'] = 'This change reduced conversion confidence';
        }

        if ($confidenceScore < 0.75) {
            titonova_enqueue_ai_suggestions($siteId, $confidenceScore, $signals);
        }

        $issues = titonova_detect_site_issues($html, $css, $config, $signals);
        if (!empty($issues)) {
            [$html, $css, $meta] = titonova_apply_auto_repairs($html, $css, $config, $issues, $meta);
        }

        $config['meta'] = $meta;
        $updatedConfig = wp_json_encode($config);
        $wpdb->update(
            $table,
            [
                'html' => $html,
                'css' => $css,
                'config_json' => $updatedConfig,
            ],
            ['id' => $siteId],
            ['%s', '%s', '%s'],
            ['%s']
        );

        $postId = titonova_find_site_post_id($siteId);
        if ($postId) {
            update_post_meta($postId, '_titonova_confidence_score', $confidenceScore);
            update_post_meta($postId, '_titonova_confidence_signals', $signals);
            if (!empty($meta['confidence_soft_hint'])) {
                update_post_meta($postId, '_titonova_confidence_hint', $meta['confidence_soft_hint']);
            }
        }
    }
}

function titonova_find_site_post_id(string $siteUuid): int
{
    $query = new WP_Query([
        'post_type' => 'titonova_site',
        'post_status' => 'publish',
        'meta_key' => '_titonova_site_uuid',
        'meta_value' => $siteUuid,
        'fields' => 'ids',
        'posts_per_page' => 1,
        'no_found_rows' => true,
    ]);

    if (!empty($query->posts[0])) {
        return (int) $query->posts[0];
    }

    return 0;
}

function titonova_calculate_confidence_signals(string $html, string $css, array $config): array
{
    $text = trim(wp_strip_all_tags($html));
    $industry = strtolower((string) ($config['industry'] ?? $config['meta']['industry'] ?? ''));
    $goal = titonova_normalize_goal((string) ($config['meta']['primary_goal'] ?? $config['meta']['intent'] ?? $config['intent'] ?? 'leads'));
    $heroSource = (string) ($config['meta']['hero_source'] ?? '');

    $clarity = titonova_score_clarity($text);
    $ctaStrength = titonova_score_cta_strength($html, $goal);
    $industryAlignment = titonova_score_industry_alignment($text, $industry);
    $trustSignals = titonova_score_trust_signals($text);
    $mobileSafety = titonova_score_mobile_safety($css);
    $speedScore = titonova_score_speed($html);
    $heroSourceScore = titonova_score_hero_source($heroSource);

    return [
        'clarity_score' => $clarity,
        'cta_strength' => $ctaStrength,
        'industry_alignment' => $industryAlignment,
        'trust_signals' => $trustSignals,
        'mobile_safety' => $mobileSafety,
        'speed_score' => $speedScore,
        'hero_source_score' => $heroSourceScore,
    ];
}

function titonova_calculate_confidence_score(array $signals): float
{
    $clarity = (float) ($signals['clarity_score'] ?? 0);
    $cta = (float) ($signals['cta_strength'] ?? 0);
    $industry = (float) ($signals['industry_alignment'] ?? 0);
    $trust = (float) ($signals['trust_signals'] ?? 0);
    $mobile = (float) ($signals['mobile_safety'] ?? 0);
    $speed = (float) ($signals['speed_score'] ?? 0);
    $heroSourceScore = (float) ($signals['hero_source_score'] ?? 0);

    $score = ($clarity * 0.25) + ($cta * 0.20) + ($industry * 0.20) + ($trust * 0.15) + ($mobile * 0.10) + ($speed * 0.10);
    $scoreWithBonus = min(100, ($score * 100) + $heroSourceScore) / 100;
    return round($scoreWithBonus, 4);
}

function titonova_score_hero_source(string $source): int
{
    $normalized = strtolower(trim($source));
    if ($normalized === 'user') {
        return 25;
    }
    if ($normalized === 'stock') {
        return 15;
    }
    return 0;
}

function titonova_score_clarity(string $text): float
{
    $words = preg_split('/\s+/', trim($text));
    $words = array_filter($words, static fn($word) => $word !== '');
    if (empty($words)) {
        return 0.0;
    }
    $wordCount = count($words);
    $avgWordLen = array_sum(array_map('strlen', $words)) / max(1, $wordCount);
    $sentences = preg_split('/[.!?]+/', $text);
    $sentenceCount = max(1, count(array_filter($sentences, static fn($s) => trim($s) !== '')));
    $wordsPerSentence = $wordCount / $sentenceCount;

    $score = 1.0;
    if ($avgWordLen > 6.5) {
        $score -= 0.2;
    }
    if ($wordsPerSentence > 20) {
        $score -= 0.2;
    }

    return max(0.2, min(1.0, $score));
}

function titonova_score_cta_strength(string $html, string $goal): float
{
    preg_match_all('/<a[^>]*class=["\"][^"\"]*cta[^"\"]*["\"][^>]*>(.*?)<\/a>/i', $html, $matches);
    $ctas = $matches[1] ?? [];
    if (empty($ctas)) {
        return 0.2;
    }

    $rules = titonova_load_industry_goal_rules('', $goal);
    $verbs = array_map('strtolower', $rules['cta_verbs'] ?? []);
    if (empty($verbs)) {
        $verbs = ['get', 'request', 'book', 'call', 'talk', 'speak'];
    }

    $strong = 0;
    foreach ($ctas as $cta) {
        $label = strtolower(trim(wp_strip_all_tags($cta)));
        foreach ($verbs as $verb) {
            if ($verb !== '' && str_contains($label, $verb)) {
                $strong += 1;
                break;
            }
        }
    }

    return min(1.0, $strong / max(1, count($ctas)));
}

function titonova_score_industry_alignment(string $text, string $industry): float
{
    if ($industry === '') {
        return 0.5;
    }

    $keywords = [
        'healthcare & medical' => ['clinic', 'hospital', 'care', 'patient', 'medical', 'telehealth', 'doctor'],
        'ecommerce & retail' => ['shop', 'store', 'product', 'checkout', 'cart', 'retail', 'sale'],
        'technology & software' => ['software', 'saas', 'platform', 'app', 'api', 'cloud', 'automation'],
        'creative & agency' => ['portfolio', 'creative', 'branding', 'studio', 'campaign', 'design'],
        'restaurant & food services' => ['menu', 'dining', 'restaurant', 'chef', 'reservation', 'catering'],
        'real estate brokerage' => ['listing', 'property', 'realtor', 'real estate', 'home', 'open house'],
        'education & training' => ['course', 'training', 'academy', 'learn', 'curriculum', 'coaching'],
        'nonprofit & community' => ['nonprofit', 'charity', 'donate', 'volunteer', 'impact', 'cause'],
    ];

    $terms = $keywords[$industry] ?? [];
    if (empty($terms)) {
        return 0.6;
    }

    $hits = 0;
    $clean = strtolower($text);
    foreach ($terms as $term) {
        if (str_contains($clean, $term)) {
            $hits += 1;
        }
    }

    return min(1.0, $hits / max(3, count($terms)));
}

function titonova_score_trust_signals(string $text): float
{
    $terms = ['testimonial', 'review', 'case study', 'trusted', 'security', 'compliance', 'privacy', 'accessibility', 'certification', 'badge'];
    $clean = strtolower($text);
    $hits = 0;
    foreach ($terms as $term) {
        if (str_contains($clean, $term)) {
            $hits += 1;
        }
    }
    return min(1.0, $hits / 5);
}

function titonova_score_mobile_safety(string $css): float
{
    if (str_contains($css, '@media') && str_contains($css, 'max-width')) {
        return 0.9;
    }
    return 0.4;
}

function titonova_score_speed(string $html): float
{
    preg_match_all('/<img\b[^>]*>/i', $html, $matches);
    $count = count($matches[0] ?? []);
    if ($count === 0) {
        return 0.9;
    }
    if ($count > 10) {
        return 0.4;
    }
    if ($count > 6) {
        return 0.6;
    }
    return 0.8;
}

function titonova_enqueue_ai_suggestions(string $siteId, float $score, array $signals): void
{
    $queue = get_option('titonova_ai_suggestion_queue', []);
    if (!is_array($queue)) {
        $queue = [];
    }
    $queue[] = [
        'site_id' => $siteId,
        'score' => $score,
        'signals' => $signals,
        'timestamp' => current_time('mysql', true),
    ];
    update_option('titonova_ai_suggestion_queue', $queue, false);
}

function titonova_detect_site_issues(string $html, string $css, array $config, array $signals): array
{
    $issues = [];
    $text = wp_strip_all_tags($html);
    $ctaStrength = (float) ($signals['cta_strength'] ?? 0);
    $trustSignals = (float) ($signals['trust_signals'] ?? 0);
    $mobileSafety = (float) ($signals['mobile_safety'] ?? 0);
    $speedScore = (float) ($signals['speed_score'] ?? 0);

    if (preg_match('/<h[1-3][^>]*>([^<]{80,})<\/h[1-3]>/', $html)) {
        $issues[] = ['type' => 'text_overflow', 'severity' => 'medium', 'message' => 'Long headings detected'];
    }
    if ($ctaStrength < 0.5) {
        $issues[] = ['type' => 'weak_cta', 'severity' => 'medium', 'message' => 'CTA verbs could be stronger'];
    }
    if ($trustSignals < 0.4) {
        $issues[] = ['type' => 'missing_trust', 'severity' => 'medium', 'message' => 'Trust signals missing'];
    }
    if ($mobileSafety < 0.5) {
        $issues[] = ['type' => 'mobile_spacing', 'severity' => 'medium', 'message' => 'Mobile spacing adjustments recommended'];
    }
    if ($speedScore < 0.6) {
        $issues[] = ['type' => 'slow_images', 'severity' => 'medium', 'message' => 'Images could be optimized'];
    }

    return $issues;
}

function titonova_apply_auto_repairs(string $html, string $css, array $config, array $issues, array $meta): array
{
    $goal = titonova_normalize_goal((string) ($config['meta']['primary_goal'] ?? $config['meta']['intent'] ?? $config['intent'] ?? 'leads'));
    $rules = titonova_load_industry_goal_rules('', $goal);
    $repairs = $meta['auto_repairs'] ?? [];
    if (!is_array($repairs)) {
        $repairs = [];
    }

    foreach ($issues as $issue) {
        $type = $issue['type'] ?? '';
        $severity = $issue['severity'] ?? 'medium';

        if ($severity === 'critical') {
            titonova_queue_user_approval_fix($config, $issue);
            continue;
        }

        switch ($type) {
            case 'weak_cta':
                $replacement = $rules['cta_verbs'][0] ?? 'Get in touch';
                $html = preg_replace_callback(
                    '/(<a[^>]*class=["\"][^"\"]*cta[^"\"]*["\"][^>]*>)(.*?)(<\/a>)/i',
                    static function ($matches) use ($replacement) {
                        return $matches[1] . esc_html($replacement) . $matches[3];
                    },
                    $html
                );
                $repairs[] = ['type' => $type, 'action' => 'CTA verbs strengthened', 'timestamp' => current_time('mysql', true)];
                break;
            case 'text_overflow':
                $html = preg_replace_callback('/(<h[1-3][^>]*>)([^<]{80,})(<\/h[1-3]>)/i', static function ($matches) {
                    $trimmed = mb_substr($matches[2], 0, 70) . '…';
                    return $matches[1] . esc_html($trimmed) . $matches[3];
                }, $html);
                $repairs[] = ['type' => $type, 'action' => 'Headlines shortened', 'timestamp' => current_time('mysql', true)];
                break;
            case 'missing_trust':
                if (!str_contains(strtolower($html), 'trust badges')) {
                    $trustSection = '<section class="section"><h2>Trust badges</h2><div class="logo-cloud"><span class="logo-pill">Security-first</span><span class="logo-pill">Privacy-aware</span><span class="logo-pill">Compliance-ready</span></div></section>';
                    $html = str_replace('</footer>', $trustSection . '</footer>', $html);
                }
                $repairs[] = ['type' => $type, 'action' => 'Trust badges added', 'timestamp' => current_time('mysql', true)];
                break;
            case 'slow_images':
                $html = preg_replace_callback('/<img\b(?![^>]*\bloading=)([^>]*)>/i', static function ($matches) {
                    return '<img loading="lazy"' . $matches[1] . '>';
                }, $html);
                $html = preg_replace('/(https:\/\/images\.unsplash\.com[^"\']*)(?=["\'])/i', '$1&auto=format&fit=crop&w=1200&q=80', $html);
                $repairs[] = ['type' => $type, 'action' => 'Images optimized', 'timestamp' => current_time('mysql', true)];
                break;
            case 'mobile_spacing':
                if (!str_contains($css, '@media')) {
                    $css .= '\n@media (max-width: 768px){.cta{width:100%;max-width:320px}.hero,.hero-grid{grid-template-columns:1fr}}';
                }
                $repairs[] = ['type' => $type, 'action' => 'Mobile spacing adjusted', 'timestamp' => current_time('mysql', true)];
                break;
            default:
                break;
        }
    }

    $meta['auto_repairs'] = $repairs;
    return [$html, $css, $meta];
}

function titonova_queue_user_approval_fix(array $config, array $issue): void
{
    $queue = get_option('titonova_fix_queue', []);
    if (!is_array($queue)) {
        $queue = [];
    }
    $queue[] = [
        'site' => $config['meta']['site_id'] ?? null,
        'issue' => $issue,
        'timestamp' => current_time('mysql', true),
    ];
    update_option('titonova_fix_queue', $queue, false);
}

function titonova_store_generation_session_summary(int $generationId, array $data, array $ai): void
{
    $industry = (string) ($data['industry'] ?? '');
    $ics = (int) ($data['meta']['industryConfidenceScore'] ?? 0);
    $lfs = (int) get_post_meta($generationId, '_titonova_learning_feedback_score', true);
    $intent = (string) ($data['meta']['intent'] ?? '');

    $secondaryTraits = [];
    $signals = $data['meta']['industry_signals']['signals'] ?? [];
    if (is_array($signals)) {
        foreach ($signals as $signal) {
            if (!is_array($signal)) {
                continue;
            }
            if (($signal['type'] ?? '') === 'keyword' && ($signal['label'] ?? '') !== $industry) {
                $secondaryTraits[] = (string) $signal['label'];
            }
        }
    }
    $secondaryTraits = array_values(array_unique($secondaryTraits));

    $features = [];
    if (!empty($data['features']) && is_array($data['features'])) {
        $features = array_values(array_unique(array_map('strval', $data['features'])));
    }

    $sections = [];
    if (!empty($ai['sections']) && is_array($ai['sections'])) {
        $sections = array_keys($ai['sections']);
    }

    $summary = [
        'generation_id' => $generationId,
        'industry' => $industry,
        'secondary_traits' => $secondaryTraits,
        'intent' => $intent,
        'ics' => $ics,
        'lfs' => $lfs,
        'features' => $features,
        'sections' => $sections,
        'created_at' => current_time('mysql'),
    ];

    update_post_meta($generationId, '_titonova_session_summary', $summary);
    titonova_update_session_patterns($summary);
}

function titonova_update_session_patterns_from_signal(int $generationId, string $signal, array $metadata, int $lfs): void
{
    $summary = get_post_meta($generationId, '_titonova_session_summary', true);
    if (!is_array($summary)) {
        return;
    }

    $summary['lfs'] = $lfs;
    if ($signal === 'delete_sections' || $signal === 'add_sections') {
        $sections = $metadata['sections'] ?? [];
        if (is_array($sections)) {
            $summary['edited_sections'] = array_values(array_unique(array_merge(
                $summary['edited_sections'] ?? [],
                array_map('strval', $sections)
            )));
        }
    }

    update_post_meta($generationId, '_titonova_session_summary', $summary);
    titonova_update_session_patterns($summary);
}

function titonova_update_session_patterns(array $summary): void
{
    $industry = (string) ($summary['industry'] ?? '');
    if ($industry === '') {
        return;
    }

    $traits = $summary['secondary_traits'] ?? [];
    if (!is_array($traits)) {
        $traits = [];
    }
    sort($traits);
    $features = $summary['features'] ?? [];
    if (!is_array($features)) {
        $features = [];
    }
    sort($features);

    $intent = (string) ($summary['intent'] ?? '');

    $groupKey = md5(strtolower($industry . '|' . implode(',', $traits) . '|' . implode(',', $features) . '|' . $intent));

    $groups = get_option('titonova_session_patterns', []);
    if (!is_array($groups)) {
        $groups = [];
    }

    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = [
            'industry' => $industry,
            'secondary_traits' => $traits,
            'features' => $features,
            'intent' => $intent,
            'intent_counts' => [],
            'sessions' => 0,
            'avg_ics' => 0,
            'avg_lfs' => 0,
            'common_sections' => [],
            'edited_sections' => [],
            'emerging_micro_industry_candidate' => false,
            'candidate_reason' => null,
            'last_seen' => current_time('mysql'),
        ];
    }

    $group = $groups[$groupKey];
    $sessions = (int) ($group['sessions'] ?? 0) + 1;
    $avgIcs = (float) ($group['avg_ics'] ?? 0);
    $avgLfs = (float) ($group['avg_lfs'] ?? 0);
    $ics = (int) ($summary['ics'] ?? 0);
    $lfs = (int) ($summary['lfs'] ?? 0);
    $group['sessions'] = $sessions;
    $group['avg_ics'] = ($avgIcs * ($sessions - 1) + $ics) / $sessions;
    $group['avg_lfs'] = ($avgLfs * ($sessions - 1) + $lfs) / $sessions;

    if ($intent !== '') {
        $group['intent_counts'][$intent] = ($group['intent_counts'][$intent] ?? 0) + 1;
    }

    $sections = $summary['sections'] ?? [];
    if (is_array($sections)) {
        foreach ($sections as $section) {
            $section = (string) $section;
            $group['common_sections'][$section] = ($group['common_sections'][$section] ?? 0) + 1;
        }
    }

    $edited = $summary['edited_sections'] ?? [];
    if (is_array($edited)) {
        foreach ($edited as $section) {
            $section = (string) $section;
            $group['edited_sections'][$section] = ($group['edited_sections'][$section] ?? 0) + 1;
        }
    }

    $group['last_seen'] = current_time('mysql');
    $group['emerging_micro_industry_candidate'] = false;
    $group['candidate_reason'] = null;
    if (
        $group['sessions'] >= 5
        && $group['avg_ics'] >= 40
        && $group['avg_ics'] <= 70
    ) {
        $topIntentCount = 0;
        if (!empty($group['intent_counts'])) {
            $topIntentCount = max($group['intent_counts']);
        }
        $topEditedSectionCount = 0;
        if (!empty($group['edited_sections'])) {
            $topEditedSectionCount = max($group['edited_sections']);
        }

        if ($topIntentCount >= 5 && $topEditedSectionCount >= 5) {
            $group['emerging_micro_industry_candidate'] = true;
            $group['candidate_reason'] = 'Repeated intent, section edits, and feature usage with mid confidence.';
        }
    }
    $groups[$groupKey] = $group;
    update_option('titonova_session_patterns', $groups, false);
}

function titonova_review_learning_patterns(): void
{
    $query = new WP_Query([
        'post_type' => 'titonova_generation',
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
    ]);

    if (empty($query->posts)) {
        return;
    }

    $pairCounts = [];
    $pairLfs = [];
    $microSignalCounts = [];

    foreach ($query->posts as $post) {
        $generationId = (int) $post->ID;
        $summary = get_post_meta($generationId, '_titonova_learning_summary', true);
        $industry = is_array($summary) ? (string) ($summary['industry'] ?? '') : '';
        $lfs = is_array($summary) ? (int) ($summary['lfs'] ?? 0) : 0;

        $signals = get_post_meta($generationId, '_titonova_learning_signals', true);
        if (!is_array($signals)) {
            $signals = [];
        }

        foreach ($signals as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $signal = (string) ($entry['signal'] ?? '');
            $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];

            if ($signal === 'change_industry') {
                $from = (string) ($metadata['from_industry'] ?? $industry);
                $to = (string) ($metadata['to_industry'] ?? $metadata['industry'] ?? '');
                if ($from !== '' && $to !== '' && $from !== $to) {
                    $pairKey = strtolower($from) . '::' . strtolower($to);
                    $pairCounts[$pairKey] = ($pairCounts[$pairKey] ?? 0) + 1;
                    $pairLfs[$pairKey] = ($pairLfs[$pairKey] ?? 0) + $lfs;
                }
            }

            if ($lfs >= 20 && !empty($metadata['signals']) && is_array($metadata['signals'])) {
                foreach ($metadata['signals'] as $signalEntry) {
                    if (!is_array($signalEntry)) {
                        continue;
                    }
                    $type = (string) ($signalEntry['type'] ?? '');
                    $label = (string) ($signalEntry['label'] ?? '');
                    if ($industry === '' || $type === '' || $label === '') {
                        continue;
                    }
                    if (!isset($microSignalCounts[$industry])) {
                        $microSignalCounts[$industry] = [];
                    }
                    $key = $type . '::' . $label;
                    $microSignalCounts[$industry][$key] = ($microSignalCounts[$industry][$key] ?? 0) + 1;
                }
            }
        }
    }

    $hybridPatterns = get_option('titonova_hybrid_patterns', []);
    if (!is_array($hybridPatterns)) {
        $hybridPatterns = [];
    }

    foreach ($pairCounts as $pairKey => $count) {
        if ($count < 3) {
            continue;
        }
        $avgLfs = (int) round(($pairLfs[$pairKey] ?? 0) / max(1, $count));
        if ($avgLfs < 0) {
            continue;
        }
        [$from, $to] = explode('::', $pairKey);
        $hybridPatterns[$pairKey] = [
            'industries' => [$from, $to],
            'count' => $count,
            'avg_lfs' => $avgLfs,
            'last_seen' => current_time('mysql'),
        ];
    }

    update_option('titonova_hybrid_patterns', $hybridPatterns, false);

    $microPatterns = get_option('titonova_micro_patterns', []);
    if (!is_array($microPatterns)) {
        $microPatterns = [];
    }

    foreach ($microSignalCounts as $industry => $counts) {
        arsort($counts);
        $topSignals = array_slice($counts, 0, 5, true);
        $microPatterns[$industry] = [
            'signals' => array_keys($topSignals),
            'last_seen' => current_time('mysql'),
        ];
    }

    update_option('titonova_micro_patterns', $microPatterns, false);

    $sessionPatterns = get_option('titonova_session_patterns', []);
    if (is_array($sessionPatterns)) {
        titonova_generate_micro_industry_profiles($sessionPatterns);
        titonova_update_micro_profile_status($sessionPatterns);
    }
}

function titonova_generate_micro_industry_profiles(array $sessionPatterns): void
{
    $profiles = get_option('titonova_micro_industry_profiles', []);
    if (!is_array($profiles)) {
        $profiles = [];
    }

    foreach ($sessionPatterns as $groupKey => $group) {
        if (!is_array($group)) {
            continue;
        }
        if (empty($group['emerging_micro_industry_candidate'])) {
            continue;
        }

        $intentCounts = $group['intent_counts'] ?? [];
        $dominantGoal = titonova_infer_dominant_goal($intentCounts);
        $preferredTone = titonova_infer_preferred_tone((float) ($group['avg_ics'] ?? 0));
        $layoutRules = titonova_infer_layout_rules($group);
        $trustSignals = titonova_infer_trust_signals($group);
        $featureDefaults = $group['features'] ?? [];
        $ctaPatterns = titonova_infer_cta_patterns($dominantGoal);
        $contentEmphasis = titonova_infer_content_emphasis($group, $dominantGoal);

        $microName = titonova_build_micro_industry_name($group, $dominantGoal);

        $profiles[$groupKey] = [
            'name' => $microName,
            'parent_industry' => (string) ($group['industry'] ?? ''),
            'dominant_goal' => $dominantGoal,
            'tone_rules' => $preferredTone,
            'layout_rules' => $layoutRules,
            'feature_defaults' => array_values(array_unique(array_map('strval', $featureDefaults))),
            'secondary_traits' => $group['secondary_traits'] ?? [],
            'intent' => $group['intent'] ?? null,
            'trust_signals' => $trustSignals,
            'cta_patterns' => $ctaPatterns,
            'content_emphasis_rules' => $contentEmphasis,
            'default_confidence' => 40,
            'reusable' => true,
            'status' => 'experimental',
            'source_group' => $groupKey,
            'last_updated' => current_time('mysql'),
        ];
    }

    update_option('titonova_micro_industry_profiles', $profiles, false);
}

function titonova_update_micro_profile_status(array $sessionPatterns): void
{
    $profiles = get_option('titonova_micro_industry_profiles', []);
    if (!is_array($profiles) || empty($profiles)) {
        return;
    }

    foreach ($profiles as $key => $profile) {
        if (!is_array($profile)) {
            continue;
        }
        $sourceGroup = $profile['source_group'] ?? null;
        if (!is_string($sourceGroup) || !isset($sessionPatterns[$sourceGroup])) {
            continue;
        }

        $group = $sessionPatterns[$sourceGroup];
        $sessions = (int) ($group['sessions'] ?? 0);
        $avgIcs = (float) ($group['avg_ics'] ?? 0);
        $avgLfs = (float) ($group['avg_lfs'] ?? 0);
        $lastSeen = $group['last_seen'] ?? null;

        if ($sessions >= 10 && $avgIcs >= 75 && $avgLfs >= 20) {
            $profile['status'] = 'stable';
            $profile['default_confidence'] = max((int) ($profile['default_confidence'] ?? 40), 60);
            $profile['reusable'] = true;
            $profile['locked_core_rules'] = true;
        } else {
            $profile['status'] = $profile['status'] ?? 'experimental';
        }

        if ($sessions < 3 || $avgIcs < 40 || $avgLfs < 0) {
            $profile['default_confidence'] = max(20, (int) ($profile['default_confidence'] ?? 40) - 5);
        }

                if (is_string($lastSeen)) {
                        $profile['last_seen'] = $lastSeen;
                }

        $profiles[$key] = $profile;
    }

    update_option('titonova_micro_industry_profiles', $profiles, false);
}

function titonova_match_micro_profile(array $data): ?array
{
    $profiles = get_option('titonova_micro_industry_profiles', []);
    if (!is_array($profiles) || empty($profiles)) {
        return null;
    }

    $industry = strtolower((string) ($data['industry'] ?? ''));
    $intent = (string) ($data['meta']['intent'] ?? '');
    $traits = titonova_extract_secondary_traits($data['meta']['industry_signals'] ?? [], $industry);
    $features = $data['features'] ?? [];
    if (!is_array($features)) {
        $features = [];
    }

    $best = null;
    $bestScore = 0.0;

    foreach ($profiles as $profile) {
        if (!is_array($profile) || ($profile['status'] ?? '') !== 'experimental') {
            continue;
        }

        $parent = strtolower((string) ($profile['parent_industry'] ?? ''));
        if ($parent === '' || $industry === '' || $parent !== $industry) {
            continue;
        }

        $profileIntent = (string) ($profile['intent'] ?? '');
        $profileTraits = $profile['secondary_traits'] ?? [];
        if (!is_array($profileTraits)) {
            $profileTraits = [];
        }
        $profileFeatures = $profile['feature_defaults'] ?? [];
        if (!is_array($profileFeatures)) {
            $profileFeatures = [];
        }

        $intentMatch = $profileIntent !== '' && $intent !== '' && $profileIntent === $intent ? 1.0 : 0.0;
        $traitsMatch = titonova_overlap_ratio($traits, $profileTraits);
        $featureMatch = titonova_overlap_ratio($features, $profileFeatures);

        $score = (0.3 * 1.0) + (0.2 * $intentMatch) + (0.25 * $traitsMatch) + (0.25 * $featureMatch);

        $threshold = ($profile['status'] ?? 'experimental') === 'stable' ? 0.6 : 0.7;
        if ($score >= $threshold && $score > $bestScore) {
            $bestScore = $score;
            $best = $profile;
        }
    }

    if ($best === null) {
        return null;
    }

    return [
        'profile' => $best,
        'score' => $bestScore,
    ];
}

function titonova_apply_micro_profile(array $data, array $match): array
{
    $profile = $match['profile'] ?? null;
    if (!is_array($profile)) {
        return $data;
    }

    $data['meta']['microProfile'] = $profile;
    $data['meta']['microProfileScore'] = (float) ($match['score'] ?? 0);
    $data['meta']['microIndustryName'] = (string) ($profile['name'] ?? '');
    $data['meta']['microProfileStatus'] = (string) ($profile['status'] ?? 'experimental');
    $data['meta']['primaryIndustry'] = (string) ($data['industry'] ?? '');

    $defaultConfidence = (int) ($profile['default_confidence'] ?? 40);
    $current = (int) ($data['meta']['industryConfidenceScore'] ?? 0);
    $status = (string) ($profile['status'] ?? 'experimental');
    $weight = $status === 'stable' ? 0.65 : 0.45;
    $data['meta']['industryConfidenceScore'] = (int) round(($current * (1 - $weight)) + ($defaultConfidence * $weight));
    $data['meta']['microProfileReuse'] = $status === 'stable' ? 'full' : 'limited';
    $data['meta']['assumptionMode'] = $status === 'stable' ? 'standard' : 'conservative';

    return $data;
}

function titonova_build_micro_profile_prompt(array $profile): string
{
    $tone = $profile['tone_rules']['tone'] ?? '';
    $avoid = $profile['tone_rules']['avoid'] ?? '';
    $layout = $profile['layout_rules']['prioritize_sections'] ?? [];
    $ctaPatterns = $profile['cta_patterns'] ?? [];
    $emphasis = $profile['content_emphasis_rules'] ?? [];
    $status = (string) ($profile['status'] ?? 'experimental');

    $parts = [];
    if ($tone !== '') {
        $parts[] = "Tone rules: {$tone}.";
    }
    if ($avoid !== '') {
        $parts[] = "Avoid: {$avoid}.";
    }
    if (is_array($layout) && !empty($layout)) {
        $parts[] = 'Prioritize sections: ' . implode(', ', array_map('strval', $layout)) . '.';
    }
    if (is_array($ctaPatterns) && !empty($ctaPatterns)) {
        $parts[] = 'CTA patterns: ' . implode(', ', array_map('strval', $ctaPatterns)) . '.';
    }
    if (is_array($emphasis) && !empty($emphasis)) {
        $parts[] = 'Content emphasis: ' . implode(', ', array_map('strval', $emphasis)) . '.';
    }
    if ($status !== 'stable') {
        $parts[] = 'Use conservative assumptions and adaptable language.';
    }

    return implode(' ', $parts);
}

function titonova_extract_secondary_traits(array $industrySignals, string $industry): array
{
    $signals = $industrySignals['signals'] ?? [];
    if (!is_array($signals)) {
        return [];
    }

    $traits = [];
    foreach ($signals as $signal) {
        if (!is_array($signal)) {
            continue;
        }
        if (($signal['type'] ?? '') === 'keyword' && strtolower((string) ($signal['label'] ?? '')) !== $industry) {
            $traits[] = (string) $signal['label'];
        }
    }

    return array_values(array_unique($traits));
}

function titonova_overlap_ratio(array $left, array $right): float
{
    $left = array_values(array_unique(array_map('strval', $left)));
    $right = array_values(array_unique(array_map('strval', $right)));
    if (empty($right)) {
        return 0.0;
    }
    $intersection = array_intersect($left, $right);
    return count($intersection) / max(1, count($right));
}

function titonova_infer_dominant_goal(array $intentCounts): string
{
    if (empty($intentCounts)) {
        return 'leads';
    }

    arsort($intentCounts);
    $topIntent = array_key_first($intentCounts);
    switch ($topIntent) {
        case 'booking':
            return 'booking';
        case 'commerce':
            return 'sales';
        case 'donation':
            return 'authority';
        case 'demo':
            return 'leads';
        case 'recruiting':
            return 'authority';
        default:
            return 'leads';
    }
}

function titonova_infer_preferred_tone(float $avgIcs): array
{
    if ($avgIcs >= 60) {
        return [
            'clarity_first' => true,
            'tone' => 'confident, professional, and specific',
            'avoid' => 'overly broad or vague claims',
        ];
    }

    return [
        'clarity_first' => true,
        'tone' => 'professional, adaptable, and reassuring',
        'avoid' => 'niche assumptions or unverifiable claims',
    ];
}

function titonova_infer_layout_rules(array $group): array
{
    $commonSections = $group['common_sections'] ?? [];
    if (!is_array($commonSections)) {
        $commonSections = [];
    }
    arsort($commonSections);
    $topSections = array_slice(array_keys($commonSections), 0, 6);

    return [
        'prioritize_sections' => $topSections,
        'repeatable_structure' => !empty($topSections),
    ];
}

function titonova_infer_trust_signals(array $group): array
{
    $signals = ['clear outcomes', 'social proof', 'transparent process'];
    $sections = $group['common_sections'] ?? [];
    if (!is_array($sections)) {
        $sections = [];
    }

    foreach ($sections as $section => $count) {
        $section = strtolower((string) $section);
        if (str_contains($section, 'testimonial') || str_contains($section, 'review')) {
            $signals[] = 'testimonials and reviews';
        }
        if (str_contains($section, 'faq')) {
            $signals[] = 'FAQ clarity';
        }
        if (str_contains($section, 'case')) {
            $signals[] = 'case studies';
        }
        if (str_contains($section, 'cert') || str_contains($section, 'compliance')) {
            $signals[] = 'certifications and compliance';
        }
    }

    return array_values(array_unique($signals));
}

function titonova_infer_cta_patterns(string $goal): array
{
    switch ($goal) {
        case 'booking':
            return ['Book now', 'Schedule a visit'];
        case 'sales':
            return ['Shop now', 'Buy online'];
        case 'authority':
            return ['Learn more', 'Request info'];
        default:
            return ['Get in touch', 'Request a quote'];
    }
}

function titonova_infer_content_emphasis(array $group, string $goal): array
{
    $emphasis = ['clarity of offering', 'audience fit', 'trust and proof'];
    if ($goal === 'booking') {
        $emphasis[] = 'availability and scheduling';
    }
    if ($goal === 'sales') {
        $emphasis[] = 'product benefits and purchase flow';
    }
    if ($goal === 'authority') {
        $emphasis[] = 'expertise and credibility';
    }

    $traits = $group['secondary_traits'] ?? [];
    if (is_array($traits) && !empty($traits)) {
        $emphasis[] = 'bridge language for ' . implode(', ', $traits);
    }

    return $emphasis;
}

function titonova_build_micro_industry_name(array $group, string $goal): string
{
    $industry = (string) ($group['industry'] ?? '');
    $traits = $group['secondary_traits'] ?? [];
    if (!is_array($traits)) {
        $traits = [];
    }

    $context = !empty($traits) ? implode(' + ', array_slice($traits, 0, 2)) : $industry;
    $primaryFunctionMap = [
        'booking' => 'Booking',
        'sales' => 'Sales',
        'authority' => 'Authority',
        'leads' => 'Lead Generation',
    ];

    $primaryFunction = $primaryFunctionMap[$goal] ?? 'Lead Generation';

    $name = trim($primaryFunction . ' ' . $context);
    if ($name === '') {
        $name = 'Lead Generation Service';
    }

    return $name;
}

function titonova_adjust_industry_confidence_dampener(string $industry, int $delta): void
{
    if ($industry === '' || $delta === 0) {
        return;
    }
    $dampeners = get_option('titonova_industry_confidence_dampener', []);
    if (!is_array($dampeners)) {
        $dampeners = [];
    }
    $current = (int) ($dampeners[$industry] ?? 0);
    $next = $current + $delta;
    if ($next < 0) {
        $next = 0;
    }
    if ($next > 3) {
        $next = 3;
    }
    $dampeners[$industry] = $next;
    update_option('titonova_industry_confidence_dampener', $dampeners, false);
}

function titonova_sanitize_signal_metadata($metadata): array
{
    if (!is_array($metadata)) {
        return [];
    }

    $sanitizeValue = function ($value) use (&$sanitizeValue) {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $cleanKey = is_string($key) ? sanitize_text_field($key) : $key;
                $clean[$cleanKey] = $sanitizeValue($item);
            }
            return $clean;
        }

        if (is_scalar($value) || $value === null) {
            return sanitize_text_field((string) $value);
        }

        return '';
    };

    return $sanitizeValue($metadata);
}

function titonova_get_generation_payload(int $generationId): ?array
{
    $payload = get_post_meta($generationId, '_titonova_generation_payload', true);
    if (!$payload) {
        return null;
    }
    $decoded = json_decode((string) $payload, true);
    return is_array($decoded) ? $decoded : null;
}

function titonova_ensure_rendered_payload(array $payload): array
{
    if (!empty($payload['html']) && !empty($payload['pages'])) {
        return $payload;
    }

    if (!empty($payload['data']) && is_array($payload['data'])) {
        $data = titonova_apply_goal_engine($payload['data']);
        $imageUrls = $data['image_urls'] ?? ($data['meta']['image_urls'] ?? []);
        if (empty($imageUrls)) {
            $imageUrls = titonova_generate_image_urls($data['image_prompts'] ?? [], $data);
        }
        $imageUrls = titonova_ensure_hero_image($imageUrls, $data);
        $data['image_urls'] = $imageUrls;
        $data['meta']['image_urls'] = $imageUrls;
        if (!empty($imageUrls['hero_source'])) {
            $data['meta']['hero_source'] = (string) $imageUrls['hero_source'];
        }
        $payload['html'] = titonova_apply_image_urls_to_html(titonova_build_html($data), $imageUrls);
        $payload['pages'] = titonova_apply_image_urls_to_pages(titonova_build_multipage_site($data), $imageUrls);
        $payload['data'] = $data;
    }

    return $payload;
}

function titonova_normalize_goal(string $goal): string
{
    $goal = strtolower(trim($goal));
    $map = [
        'calls' => 'calls',
        'call' => 'calls',
        'whatsapp' => 'whatsapp',
        'bookings' => 'booking',
        'booking' => 'booking',
        'lead' => 'leads',
        'leads' => 'leads',
        'sales' => 'sales',
        'commerce' => 'sales',
        'buy' => 'sales',
    ];

    return $map[$goal] ?? 'leads';
}

function titonova_load_industry_goal_rules(string $industry, string $goal): array
{
    $industry = strtolower(trim($industry));
    $goal = titonova_normalize_goal($goal);

    $sectionWeights = [
        'hero' => 100,
        'services' => 80,
        'features' => 70,
        'testimonials' => 60,
        'pricing' => 60,
        'faq' => 50,
        'contact' => 90,
        'cta' => 95,
    ];

    if ($goal === 'calls') {
        $sectionWeights['contact'] = 120;
        $sectionWeights['cta'] = 110;
    }
    if ($goal === 'whatsapp') {
        $sectionWeights['contact'] = 120;
        $sectionWeights['cta'] = 110;
    }
    if ($goal === 'booking') {
        $sectionWeights['pricing'] = 90;
        $sectionWeights['cta'] = 100;
    }
    if ($goal === 'sales') {
        $sectionWeights['pricing'] = 110;
        $sectionWeights['features'] = 90;
    }

    $ctaVerbs = [
        'calls' => ['Call', 'Talk', 'Speak'],
        'whatsapp' => ['Chat on WhatsApp', 'Message us', 'Start WhatsApp chat'],
        'booking' => ['Book now', 'Schedule a visit', 'Reserve your spot'],
        'leads' => ['Get in touch', 'Request a quote', 'Start a conversation'],
        'sales' => ['Shop now', 'Buy online', 'View offers'],
    ];

    $tracking = [
        'calls' => 'goal_calls',
        'whatsapp' => 'goal_whatsapp',
        'booking' => 'goal_booking',
        'leads' => 'goal_leads',
        'sales' => 'goal_sales',
    ];

    return [
        'goal' => $goal,
        'industry' => $industry,
        'section_weights' => $sectionWeights,
        'cta_verbs' => $ctaVerbs[$goal] ?? $ctaVerbs['leads'],
        'tracking_key' => $tracking[$goal] ?? 'goal_leads',
    ];
}

function titonova_reorder_sections_by_priority(array $sections, array $weights): array
{
    $weighted = [];
    foreach ($sections as $section) {
        $key = strtolower((string) $section);
        $weight = $weights[$key] ?? 10;
        $weighted[] = ['name' => $section, 'weight' => $weight];
    }

    usort($weighted, static function ($a, $b) {
        return $b['weight'] <=> $a['weight'];
    });

    return array_values(array_map(static function ($item) {
        return $item['name'];
    }, $weighted));
}

function titonova_apply_goal_engine(array $data): array
{
    $primaryGoal = (string) ($data['meta']['primary_goal'] ?? $data['meta']['intent'] ?? $data['intent'] ?? 'leads');
    $industry = (string) ($data['industry'] ?? $data['meta']['industry'] ?? '');
    $rules = titonova_load_industry_goal_rules($industry, $primaryGoal);
    $defaultPhone = (string) get_option('titonova_default_phone', '+1 (000) 000-0000');
    if (empty($data['meta']['phone'])) {
        $data['meta']['phone'] = $defaultPhone;
    }

    $sections = $data['meta']['sections'] ?? $data['sections'] ?? [];
    if (is_array($sections) && !empty($sections)) {
        $ordered = titonova_reorder_sections_by_priority($sections, $rules['section_weights']);
        $data['meta']['sections'] = $ordered;
        $data['meta']['section_order'] = $ordered;
    }

    $ctaOptions = $rules['cta_verbs'];
    $data['cta'] = $ctaOptions[0] ?? ($data['cta'] ?? 'Get in touch');
    $data['meta']['goal_tracking'] = $rules['tracking_key'];
    $data['meta']['primary_goal'] = $rules['goal'];
    $data['meta']['industry_goal_rules'] = [
        'goal' => $rules['goal'],
        'cta_verbs' => $rules['cta_verbs'],
        'section_weights' => $rules['section_weights'],
    ];

    return titonova_apply_industry_dna($data);
}

function titonova_apply_industry_dna(array $data): array
{
    $industry = strtolower((string) ($data['industry'] ?? ''));
    $dna = titonova_load_industry_dna($industry);
    if (empty($dna)) {
        return $data;
    }

    $data['meta']['industry_dna'] = $dna;
    $data['meta']['trust_requirements'] = $dna['trust_requirements'] ?? [];
    $data['meta']['tone_profile'] = $dna['tone_profile'] ?? '';
    $data['meta']['image_style'] = $dna['image_style'] ?? '';

    $sections = $data['meta']['sections'] ?? $data['sections'] ?? [];
    if (is_array($sections) && !empty($sections) && !empty($dna['ideal_section_order'])) {
        $ordered = titonova_enforce_section_order($sections, $dna['ideal_section_order']);
        $data['meta']['sections'] = $ordered;
        $data['meta']['section_order'] = $ordered;
    }

    $primaryGoal = titonova_normalize_goal((string) ($data['meta']['primary_goal'] ?? $data['meta']['intent'] ?? $data['intent'] ?? 'leads'));
    if ($primaryGoal !== 'calls' && !empty($dna['cta_verbs'])) {
        $data['cta'] = $dna['cta_verbs'][0];
    }

    return $data;
}

function titonova_load_industry_dna(string $industry): array
{
    $map = [
        'healthcare & medical' => [
            'ideal_section_order' => ['hero', 'credibility', 'services', 'trust', 'faq', 'contact'],
            'cta_verbs' => ['Request care', 'Talk to a specialist'],
            'trust_requirements' => ['compliance badges', 'privacy assurance', 'patient outcomes'],
            'image_style' => 'clinical, calm, trustworthy',
            'tone_profile' => 'conservative, reassuring, clear',
            'regional_variants' => [],
        ],
        'finance & payments' => [
            'ideal_section_order' => ['hero', 'trust', 'features', 'pricing', 'faq', 'contact'],
            'cta_verbs' => ['Get started', 'Request a consult'],
            'trust_requirements' => ['security badges', 'compliance', 'risk disclosure'],
            'image_style' => 'clean, premium, stable',
            'tone_profile' => 'professional, confident, precise',
            'regional_variants' => [],
        ],
        'technology & software' => [
            'ideal_section_order' => ['hero', 'features', 'integrations', 'proof', 'pricing', 'contact'],
            'cta_verbs' => ['Start free trial', 'Book a demo'],
            'trust_requirements' => ['security badges', 'case studies', 'SLAs'],
            'image_style' => 'modern, product-forward',
            'tone_profile' => 'clear, direct, modern',
            'regional_variants' => [],
        ],
        'nonprofit & community' => [
            'ideal_section_order' => ['hero', 'impact', 'trust', 'ways-to-help', 'faq', 'contact'],
            'cta_verbs' => ['Donate now', 'Get involved'],
            'trust_requirements' => ['impact badges', 'financial transparency', 'partners'],
            'image_style' => 'human, warm, empathetic',
            'tone_profile' => 'compassionate, hopeful, grounded',
            'regional_variants' => [],
        ],
    ];

    return $map[$industry] ?? [];
}

function titonova_enforce_section_order(array $sections, array $idealOrder): array
{
    $normalized = array_map('strtolower', array_map('strval', $sections));
    $ordered = [];
    foreach ($idealOrder as $ideal) {
        $index = array_search(strtolower((string) $ideal), $normalized, true);
        if ($index !== false) {
            $ordered[] = $sections[$index];
            unset($sections[$index], $normalized[$index]);
        }
    }

    foreach ($sections as $remaining) {
        $ordered[] = $remaining;
    }

    return array_values($ordered);
}

function titonova_render_preview(WP_REST_Request $request): WP_REST_Response
{
    $generationId = (int) $request->get_param('generation_id');
    if ($generationId < 1) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'generation_id is required.'
        ], 400);
    }

    $payload = titonova_get_generation_payload($generationId);
    if (!$payload) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Generation not found.'
        ], 404);
    }

    $payload = titonova_ensure_rendered_payload($payload);

    return new WP_REST_Response([
        'success' => true,
        'html' => $payload['html'] ?? null,
        'pages' => $payload['pages'] ?? null,
    ], 200);
}

function titonova_render_hosted(WP_REST_Request $request): WP_REST_Response
{
    $generationId = (int) $request->get_param('generation_id');
    if ($generationId < 1) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'generation_id is required.'
        ], 400);
    }

    $payload = titonova_get_generation_payload($generationId);
    if (!$payload) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Generation not found.'
        ], 404);
    }

    $payload = titonova_ensure_rendered_payload($payload);
    $siteId = titonova_store_site($payload);

    if (!$siteId) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to create hosted site.'
        ], 500);
    }

    $userId = get_current_user_id();
    if ($userId > 0) {
        titonova_increment_user_metric($userId, '_titonova_sites_published', 1);
        titonova_update_user_activity($userId, 'publish');
        titonova_update_user_tier($userId);
        titonova_update_user_lifecycle_status($userId);
        titonova_update_user_tags($userId, ['published' => true]);
    }

    $siteUrl = rest_url('titonova/v1/site/' . $siteId);

    return new WP_REST_Response([
        'success' => true,
        'site_id' => $siteId,
        'url' => $siteUrl,
    ], 200);
}

function titonova_render_export(WP_REST_Request $request): WP_REST_Response
{
    $generationId = (int) $request->get_param('generation_id');
    if ($generationId < 1) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'generation_id is required.'
        ], 400);
    }

    $payload = titonova_get_generation_payload($generationId);
    if (!$payload) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Generation not found.'
        ], 404);
    }

    $payload = titonova_ensure_rendered_payload($payload);
    $zipUrl = titonova_export_zip($payload);

    if ($zipUrl === null) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to export ZIP.'
        ], 500);
    }

    return new WP_REST_Response([
        'success' => true,
        'url' => $zipUrl,
    ], 200);
}

function titonova_store_site(array $payload): ?int
{
    $title = $payload['data']['business'] ?? 'TitoNova Site';
    $siteId = wp_insert_post([
        'post_type' => 'titonova_site',
        'post_status' => 'publish',
        'post_title' => $title,
    ], true);

    if (is_wp_error($siteId)) {
        return null;
    }

    update_post_meta($siteId, '_titonova_site_payload', wp_json_encode($payload));

    $siteUuid = wp_generate_uuid4();
    $userId = get_current_user_id();
    $slug = titonova_build_site_slug($payload, (int) $siteId);
    $payload = titonova_ensure_rendered_payload($payload);

    $inserted = titonova_insert_site_record($siteUuid, $userId > 0 ? $userId : null, $slug, $payload);
    if ($inserted) {
        update_post_meta($siteId, '_titonova_site_uuid', $siteUuid);
    }

    return (int) $siteId;
}

function titonova_insert_site_record(string $siteId, ?int $userId, string $slug, array $payload): bool
{
    global $wpdb;

    $table = titonova_sites_table();
    if (!titonova_table_exists($table)) {
        return false;
    }
    $html = $payload['html'] ?? '';
    $css = $payload['css'] ?? '';
    $configJson = wp_json_encode($payload['data'] ?? $payload);

    if ($html === '') {
        return false;
    }

    $data = [
        'id' => $siteId,
        'user_id' => $userId,
        'slug' => $slug,
        'html' => $html,
        'css' => $css,
        'config_json' => $configJson,
        'created_at' => current_time('mysql', true),
    ];

    $formats = ['%s', '%d', '%s', '%s', '%s', '%s', '%s'];

    $inserted = $wpdb->insert($table, $data, $formats);
    return $inserted !== false;
}

function titonova_build_site_slug(array $payload, int $fallbackId): string
{
    $raw = (string) ($payload['data']['business'] ?? 'site-' . $fallbackId);
    $slug = sanitize_title($raw);
    if ($slug === '') {
        $slug = 'site-' . $fallbackId;
    }

    return titonova_ensure_unique_slug($slug, $fallbackId);
}

function titonova_ensure_unique_slug(string $slug, int $fallbackId): string
{
    global $wpdb;

    $table = titonova_sites_table();
    $base = $slug;
    $suffix = 1;

    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug))) {
        $suffix++;
        $slug = $base . '-' . $suffix;
        if ($suffix > 1000) {
            $slug = $base . '-' . $fallbackId;
            break;
        }
    }

    return $slug;
}

function titonova_render_site(WP_REST_Request $request): WP_REST_Response
{
    $siteId = (int) $request->get_param('id');
    if ($siteId < 1) {
        return new WP_REST_Response('Not found', 404);
    }

    $payload = get_post_meta($siteId, '_titonova_site_payload', true);
    if (!$payload) {
        return new WP_REST_Response('Not found', 404);
    }

    $decoded = json_decode((string) $payload, true);
    if (!is_array($decoded)) {
        return new WP_REST_Response('Not found', 404);
    }

    $decoded = titonova_ensure_rendered_payload($decoded);
    $html = $decoded['html'] ?? '';

    return new WP_REST_Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
}

// Incoming Request
// → Read Host Header
// → Normalize Domain
// → Lookup Domain
// → Load Site
// → Serve HTML
function titonova_route_domain_request(): void
{
    if (titonova_should_skip_routing()) {
        return;
    }

    $host = titonova_get_request_host();
    if ($host === '') {
        return;
    }

    if (titonova_is_wordpress_host($host)) {
        return;
    }

    $site = titonova_get_site_for_host($host);
    if ($site === null) {
        return;
    }

    titonova_send_site_response($site);
}

function titonova_should_skip_routing(): bool
{
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return true;
    }

    if (defined('WP_CLI') && WP_CLI) {
        return true;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri !== '' && preg_match('#^/(wp-admin|wp-login\.php|wp-cron\.php|wp-json)#i', $uri)) {
        return true;
    }

    return false;
}

function titonova_get_request_host(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    return titonova_normalize_domain($host);
}

function titonova_normalize_domain(string $host): string
{
    $host = strtolower(trim($host));
    $host = preg_replace('/:\d+$/', '', $host);
    $host = trim($host, '.');

    if ($host === '') {
        return '';
    }

    if (function_exists('idn_to_ascii')) {
        $converted = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (is_string($converted) && $converted !== '') {
            $host = strtolower($converted);
        }
    }

    return $host;
}

function titonova_str_starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    return strncmp($haystack, $needle, strlen($needle)) === 0;
}

function titonova_str_ends_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    $length = strlen($needle);
    return substr($haystack, -$length) === $needle;
}

function titonova_get_platform_domain(): string
{
    $domain = (string) get_option('titonova_platform_domain', 'cname.titonova.app');
    $domain = titonova_normalize_domain($domain);
    return $domain !== '' ? $domain : 'cname.titonova.app';
}

function titonova_is_wordpress_host(string $host): bool
{
    $wpHost = titonova_normalize_domain((string) parse_url(home_url(), PHP_URL_HOST));
    return $wpHost !== '' && $host === $wpHost;
}

function titonova_get_site_for_host(string $host): ?array
{
    $cacheKey = 'titonova_site_host_' . md5($host);
    $cached = wp_cache_get($cacheKey, 'titonova');
    if ($cached !== false) {
        return is_array($cached) ? $cached : null;
    }

    $site = titonova_lookup_site_by_domain($host);
    if ($site !== null) {
        wp_cache_set($cacheKey, $site, 'titonova', 60);
        return $site;
    }

    $platform = titonova_get_platform_domain();
    if ($platform !== '' && titonova_str_ends_with($host, '.' . $platform)) {
        $subdomain = substr($host, 0, -strlen('.' . $platform));
        $subdomain = preg_replace('/^www\./', '', $subdomain);
        if ($subdomain !== '' && $subdomain !== 'www') {
            $site = titonova_lookup_site_by_slug($subdomain);
            if ($site !== null) {
                wp_cache_set($cacheKey, $site, 'titonova', 60);
                return $site;
            }
        }
    }

    wp_cache_set($cacheKey, null, 'titonova', 60);
    return null;
}

function titonova_lookup_site_by_domain(string $host): ?array
{
    global $wpdb;

    $domainsTable = titonova_domains_table();
    if (!titonova_table_exists($domainsTable)) {
        return null;
    }
    $normalized = titonova_normalize_domain($host);
    if ($normalized === '') {
        return null;
    }

    $candidates = [$normalized];
    if (titonova_str_starts_with($normalized, 'www.')) {
        $candidates[] = substr($normalized, 4);
    } else {
        $candidates[] = 'www.' . $normalized;
    }

    $placeholders = implode(',', array_fill(0, count($candidates), '%s'));
    $query = $wpdb->prepare(
        "SELECT site_id, status FROM {$domainsTable} WHERE domain IN ({$placeholders}) LIMIT 1",
        $candidates
    );

    $record = $wpdb->get_row($query, ARRAY_A);
    if (!$record) {
        return null;
    }

    if ($record['status'] !== 'active') {
        return null;
    }

    return titonova_fetch_site_by_id((string) $record['site_id']);
}

function titonova_lookup_site_by_slug(string $slug): ?array
{
    global $wpdb;

    $sitesTable = titonova_sites_table();
    if (!titonova_table_exists($sitesTable)) {
        return null;
    }
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return null;
    }

    $query = $wpdb->prepare("SELECT id, html, css, config_json, slug FROM {$sitesTable} WHERE slug = %s LIMIT 1", $slug);
    $site = $wpdb->get_row($query, ARRAY_A);

    return $site ?: null;
}

function titonova_fetch_site_by_id(string $siteId): ?array
{
    global $wpdb;

    $sitesTable = titonova_sites_table();
    if (!titonova_table_exists($sitesTable)) {
        return null;
    }
    $query = $wpdb->prepare("SELECT id, html, css, config_json, slug FROM {$sitesTable} WHERE id = %s LIMIT 1", $siteId);
    $site = $wpdb->get_row($query, ARRAY_A);

    return $site ?: null;
}

function titonova_detect_visitor_context(): array
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $isMobile = str_contains($ua, 'iphone') || str_contains($ua, 'android') || str_contains($ua, 'mobile');
    $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $country = (string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ($_SERVER['HTTP_X_GEO_COUNTRY'] ?? ''));
    $hour = (int) current_time('H');
    $returning = !empty($_COOKIE['titonova_returning']);

    return [
        'device' => $isMobile ? 'mobile' : 'desktop',
        'time_of_day' => $hour,
        'return_visit' => $returning,
        'referral_source' => $referrer,
        'location' => $country,
    ];
}

function titonova_select_variant(array $context, array $config): array
{
    $variant = [
        'prefer_whatsapp' => false,
        'mobile' => $context['device'] === 'mobile',
        'returning' => (bool) $context['return_visit'],
        'night' => $context['time_of_day'] >= 20 || $context['time_of_day'] <= 5,
    ];

    $africa = ['NG', 'KE', 'ZA', 'GH', 'UG', 'TZ', 'RW', 'ET', 'CM', 'DZ', 'MA', 'SN'];
    if (in_array(strtoupper((string) $context['location']), $africa, true)) {
        $variant['prefer_whatsapp'] = true;
    }

    return $variant;
}

function titonova_apply_variant_to_html(string $html, array $variant, array $config): string
{
    if ($html === '') {
        return $html;
    }

    $goal = titonova_normalize_goal((string) ($config['meta']['primary_goal'] ?? $config['meta']['intent'] ?? $config['intent'] ?? 'leads'));
    $phone = (string) ($config['meta']['phone'] ?? get_option('titonova_default_phone', '+1 (000) 000-0000'));
    $digits = preg_replace('/\D+/', '', $phone);
    $telHref = $digits !== '' ? 'tel:+' . ltrim($digits, '+') : '#contact';
    $whatsappHref = $digits !== '' ? 'https://wa.me/' . ltrim($digits, '+') : '#contact';

    if ($variant['prefer_whatsapp']) {
        $goal = 'whatsapp';
    }

    switch ($goal) {
        case 'calls':
            $ctaText = 'Call';
            break;
        case 'whatsapp':
            $ctaText = 'Chat on WhatsApp';
            break;
        case 'booking':
            $ctaText = 'Book now';
            break;
        case 'sales':
            $ctaText = 'Shop now';
            break;
        default:
            $ctaText = 'Get in touch';
            break;
    }

    $ctaHref = $goal === 'whatsapp' ? $whatsappHref : ($goal === 'calls' ? $telHref : '#contact');

    if ($variant['mobile']) {
        $mobileStyle = '<style>.tn-sticky-cta{position:fixed;left:16px;right:16px;bottom:18px;z-index:9999;background:#0f172a;color:#fff;border-radius:999px;padding:14px 18px;text-align:center;font-weight:700;box-shadow:0 12px 30px rgba(0,0,0,0.35);}</style>';
        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $mobileStyle . '</head>', $html, 1);
        }
        $sticky = "<a class=\"tn-sticky-cta\" href=\"{$ctaHref}\">{$ctaText}</a>";
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('/<\/body>/i', $sticky . '</body>', $html, 1);
        } else {
            $html .= $sticky;
        }
    }

    if ($variant['returning']) {
        $trustNote = '<div class="announcement">Welcome back — proof and trust at a glance.</div>';
        $html = preg_replace('/<body([^>]*)>/', '<body$1>' . $trustNote, $html, 1);
    }

    if ($variant['night']) {
        $html = preg_replace('/(<a[^>]*class=["\"][^"\"]*cta[^"\"]*["\"][^>]*>)(.*?)(<\/a>)/i', '$1' . esc_html($ctaText) . '$3', $html, 1);
    }

    return $html;
}

function titonova_send_site_response(array $site): void
{
    $html = (string) ($site['html'] ?? '');
    $css = (string) ($site['css'] ?? '');
    $config = json_decode((string) ($site['config_json'] ?? ''), true);
    $config = is_array($config) ? $config : [];

    if ($html === '') {
        status_header(404);
        exit;
    }

    if ($css !== '' && stripos($html, '<style') === false) {
        $styleTag = "<style>" . $css . "</style>";
        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $styleTag . '</head>', $html, 1);
        } else {
            $html = $styleTag . $html;
        }
    }

    $context = titonova_detect_visitor_context();
    $variant = titonova_select_variant($context, $config);
    $html = titonova_apply_variant_to_html($html, $variant, $config);

    if (empty($_COOKIE['titonova_returning'])) {
        setcookie('titonova_returning', '1', time() + DAY_IN_SECONDS * 30, '/');
    }

    status_header(200);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

function titonova_export_zip(array $payload): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }

    $payload = titonova_ensure_rendered_payload($payload);
    $html = $payload['html'] ?? '';
    $pages = $payload['pages'] ?? [];

    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . 'titonova-exports';
    if (!wp_mkdir_p($dir)) {
        return null;
    }

    $filename = 'titonova-site-' . time() . '.zip';
    $path = trailingslashit($dir) . $filename;

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return null;
    }

    $zip->addFromString('index.html', $html);
    if (is_array($pages)) {
        foreach ($pages as $page) {
            if (!isset($page['filename'], $page['html'])) {
                continue;
            }
            $zip->addFromString($page['filename'], $page['html']);
        }
    }
    $zip->close();

    return trailingslashit($upload['baseurl']) . 'titonova-exports/' . $filename;
}

function titonova_detect_intent_and_context(string $prompt): array
{
    $clean = strtolower(trim($prompt));
    $clean = preg_replace('/\s+/', ' ', $clean);
    $advancedFeatures = titonova_detect_advanced_features($clean);
    $intent = titonova_detect_intent($prompt);

    $industries = [
        'hospitality & travel' => [
            'keywords' => ['hotel', 'resort', 'travel', 'tour', 'tourism', 'vacation', 'booking', 'bnb'],
            'services' => ['Luxury stays', 'Travel planning', 'Experience curation', 'Group bookings', 'Concierge support'],
            'features' => ['Instant availability', 'Curated itineraries', 'Global destinations', 'Guest-first service'],
            'tagline' => 'Crafting journeys worth remembering',
            'gallery' => ['travel', 'hotel', 'resort', 'beach', 'cityscape'],
            'palette' => ['#0b1120', '#7dd3fc', '#e6f6ff']
        ],
        'fitness & wellness' => [
            'keywords' => ['fitness', 'gym', 'workout', 'trainer', 'wellness', 'yoga', 'pilates', 'nutrition'],
            'services' => ['Personal training', 'Group classes', 'Wellness coaching', 'Nutrition plans', 'Online programs'],
            'features' => ['Expert coaches', 'Flexible schedules', 'Progress tracking', 'Community-driven'],
            'tagline' => 'Stronger every day',
            'gallery' => ['fitness', 'gym', 'training', 'yoga', 'wellness'],
            'palette' => ['#0a1412', '#76f7c0', '#e9fff6']
        ],
        'beauty & personal care' => [
            'keywords' => ['beauty', 'salon', 'spa', 'skincare', 'hair', 'makeup', 'aesthetics'],
            'services' => ['Signature treatments', 'Beauty consultations', 'Luxury spa', 'Membership programs', 'Product lines'],
            'features' => ['Premium products', 'Expert stylists', 'Relaxing ambiance', 'Tailored care'],
            'tagline' => 'Where beauty meets wellbeing',
            'gallery' => ['spa', 'salon', 'beauty', 'skincare', 'makeup'],
            'palette' => ['#1b0f16', '#ff9acb', '#fff1f7']
        ],
        'legal & compliance' => [
            'keywords' => ['legal', 'law', 'attorney', 'lawyer', 'compliance', 'litigation', 'firm'],
            'services' => ['Legal advisory', 'Compliance audits', 'Contract review', 'Dispute resolution', 'Regulatory support'],
            'features' => ['Trusted counsel', 'Risk management', 'Confidential service', 'Clear guidance'],
            'tagline' => 'Clarity in every decision',
            'gallery' => ['law', 'legal', 'court', 'documents', 'consulting'],
            'palette' => ['#0b1221', '#9aa6ff', '#eef1ff']
        ],
        'automotive & mobility' => [
            'keywords' => ['automotive', 'auto', 'car', 'vehicle', 'dealership', 'mobility', 'fleet'],
            'services' => ['Vehicle sales', 'Service & maintenance', 'Fleet solutions', 'Financing', 'Trade-in support'],
            'features' => ['Certified technicians', 'Transparent pricing', 'Fast scheduling', 'Trusted inventory'],
            'tagline' => 'Moving you forward',
            'gallery' => ['car', 'automotive', 'showroom', 'service', 'drive'],
            'palette' => ['#0b0f1a', '#67e8f9', '#ecfeff']
        ],
        'nonprofit & community' => [
            'keywords' => ['nonprofit', 'charity', 'foundation', 'community', 'ngo', 'donation', 'cause'],
            'services' => ['Fundraising', 'Volunteer programs', 'Impact reporting', 'Community outreach', 'Partnerships'],
            'features' => ['Transparent impact', 'Secure donations', 'Engaged volunteers', 'Global reach'],
            'tagline' => 'Together, we create impact',
            'gallery' => ['community', 'volunteer', 'charity', 'help', 'impact'],
            'palette' => ['#0b1b1b', '#86efac', '#f0fff4']
        ],
        'logistics & supply chain' => [
            'keywords' => ['logistics', 'shipping', 'freight', 'supply chain', 'warehouse', 'distribution'],
            'services' => ['Freight management', 'Warehousing', 'Last-mile delivery', 'Customs clearance', 'Tracking & analytics'],
            'features' => ['Real-time tracking', 'Global coverage', 'Reliable timelines', 'Optimized routing'],
            'tagline' => 'Delivering certainty worldwide',
            'gallery' => ['logistics', 'shipping', 'warehouse', 'delivery', 'cargo'],
            'palette' => ['#0b1320', '#93c5fd', '#eff6ff']
        ],
        'manufacturing & industrial' => [
            'keywords' => ['manufacturing', 'industrial', 'factory', 'production', 'engineering', 'machinery'],
            'services' => ['Process optimization', 'Product manufacturing', 'Quality assurance', 'Supply management', 'Industrial design'],
            'features' => ['Lean operations', 'Certified quality', 'On-time delivery', 'Custom builds'],
            'tagline' => 'Precision engineered for scale',
            'gallery' => ['factory', 'manufacturing', 'industry', 'machinery', 'engineering'],
            'palette' => ['#0a1017', '#a3e635', '#f7fee7']
        ],
        'media & entertainment' => [
            'keywords' => ['media', 'entertainment', 'studio', 'film', 'music', 'production', 'streaming'],
            'services' => ['Content production', 'Brand storytelling', 'Studio services', 'Distribution strategy', 'Audience growth'],
            'features' => ['Cinematic quality', 'Creative direction', 'Global distribution', 'Audience insights'],
            'tagline' => 'Stories that move the world',
            'gallery' => ['film', 'studio', 'music', 'production', 'entertainment'],
            'palette' => ['#120b1a', '#f472b6', '#fff1f8']
        ],
        'event & decoration' => [
            'keywords' => ['decor', 'decoration', 'event', 'wedding', 'party', 'styling', 'floral', 'venue'],
            'services' => ['Event decoration', 'Wedding styling', 'Venue design', 'Floral arrangements', 'Themed experiences'],
            'features' => ['Custom concepts', 'On-site setup', 'Premium materials', 'Timeline coordination'],
            'tagline' => 'Transforming moments into unforgettable experiences',
            'gallery' => ['wedding', 'event', 'decor', 'flowers', 'party'],
            'palette' => ['#0f0b1f', '#f7c6d9', '#fef7fb']
        ],
        'staffing & recruitment' => [
            'keywords' => ['staff', 'staffing', 'recruit', 'hire', 'workforce', 'hr', 'talent'],
            'services' => ['Talent sourcing', 'Workforce planning', 'Compliance support', 'Onboarding', 'Executive search'],
            'features' => ['Vetted candidates', 'Fast turnaround', 'Industry-specific expertise', 'Compliance-first'],
            'tagline' => 'Connecting talent with opportunity',
            'gallery' => ['interview', 'office', 'teamwork', 'recruitment', 'corporate'],
            'palette' => ['#0b1b2b', '#66e1ff', '#f4fbff']
        ],
        'restaurant & food services' => [
            'keywords' => ['restaurant', 'food', 'cafe', 'menu', 'dining', 'catering', 'chef', 'kitchen'],
            'services' => ['Dine-in service', 'Catering', 'Private events', 'Seasonal menus', 'Online reservations'],
            'features' => ['Fresh ingredients', 'Signature dishes', 'Warm ambiance', 'Fast reservations'],
            'tagline' => 'Exceptional dining experiences',
            'gallery' => ['restaurant', 'food', 'chef', 'dining', 'coffee'],
            'palette' => ['#1a0f0a', '#ff9c5a', '#fff3ea']
        ],
        'construction & real estate' => [
            'keywords' => ['construction', 'building', 'contractor', 'renovation', 'real estate', 'property'],
            'services' => ['Residential builds', 'Commercial construction', 'Renovations', 'Project management', 'Site planning'],
            'features' => ['Licensed experts', 'Transparent budgeting', 'On-time delivery', 'Quality craftsmanship'],
            'tagline' => 'Building with precision and trust',
            'gallery' => ['construction', 'architecture', 'building', 'interior', 'real-estate'],
            'palette' => ['#0f172a', '#9bdc6c', '#f5fff0']
        ],
        'real estate brokerage' => [
            'keywords' => ['realtor', 'realty', 'broker', 'listings', 'homes', 'property sales'],
            'services' => ['Property listings', 'Home valuation', 'Buyer representation', 'Marketing strategy', 'Closing support'],
            'features' => ['Local expertise', 'Virtual tours', 'Market insights', 'Trusted negotiation'],
            'tagline' => 'Helping you move with confidence',
            'gallery' => ['real-estate', 'home', 'interior', 'architecture', 'city'],
            'palette' => ['#0b1220', '#7dd3fc', '#e0f2fe']
        ],
        'ecommerce & retail' => [
            'keywords' => ['shop', 'store', 'ecommerce', 'sell', 'product', 'online', 'retail', 'checkout'],
            'services' => ['Online storefront', 'Secure payments', 'Inventory management', 'Global shipping', 'Customer support'],
            'features' => ['Mobile-first', 'Fast checkout', 'Product discovery', 'Order tracking'],
            'tagline' => 'Sell globally with confidence',
            'gallery' => ['shopping', 'product', 'retail', 'store', 'commerce'],
            'palette' => ['#0b1020', '#7aa2ff', '#f2f6ff']
        ],
        'fintech & payments' => [
            'keywords' => ['fintech', 'payments', 'banking', 'wallet', 'crypto', 'digital bank'],
            'services' => ['Payment gateways', 'Digital wallets', 'Compliance readiness', 'Fraud prevention', 'Financial APIs'],
            'features' => ['Bank-grade security', 'Global transactions', 'Real-time insights', 'Fast onboarding'],
            'tagline' => 'The future of financial experience',
            'gallery' => ['fintech', 'payments', 'banking', 'mobile', 'security'],
            'palette' => ['#0a1222', '#5eead4', '#ecfeff']
        ],
        'technology & software' => [
            'keywords' => ['tech', 'software', 'saas', 'ai', 'app', 'startup', 'platform', 'cloud'],
            'services' => ['Product strategy', 'SaaS platforms', 'Automation', 'Cloud deployment', 'API integrations'],
            'features' => ['Scalable architecture', 'Security-first', 'Rapid iteration', 'Analytics ready'],
            'tagline' => 'Powering innovation through technology',
            'gallery' => ['technology', 'software', 'code', 'server', 'startup'],
            'palette' => ['#0b1120', '#60f5b2', '#effff8']
        ],
        'agriculture & food production' => [
            'keywords' => ['agriculture', 'farm', 'farming', 'agritech', 'organic', 'produce'],
            'services' => ['Farm management', 'Supply partnerships', 'Sustainable practices', 'Wholesale distribution', 'Product branding'],
            'features' => ['Sustainable sourcing', 'Traceability', 'Seasonal planning', 'Global distribution'],
            'tagline' => 'Growing value from the ground up',
            'gallery' => ['farm', 'agriculture', 'organic', 'fields', 'produce'],
            'palette' => ['#0b1b14', '#86efac', '#f0fdf4']
        ],
        'healthcare & medical' => [
            'keywords' => ['health', 'medical', 'clinic', 'hospital', 'care', 'doctor', 'wellness'],
            'services' => ['Patient care', 'Consultations', 'Diagnostics', 'Telehealth', 'Treatment planning'],
            'features' => ['Licensed clinicians', 'Patient-first care', 'Modern facilities', 'Trusted outcomes'],
            'tagline' => 'Compassionate care. Modern solutions.',
            'gallery' => ['medical', 'clinic', 'healthcare', 'doctor', 'wellness'],
            'palette' => ['#0b1f25', '#5ce1e6', '#ecffff']
        ],
        'public sector & government' => [
            'keywords' => ['government', 'public sector', 'municipal', 'city council', 'civic', 'policy'],
            'services' => ['Citizen services', 'Digital modernization', 'Accessibility audits', 'Public engagement', 'Service portals'],
            'features' => ['Secure infrastructure', 'Accessibility-first', 'Transparent communication', '24/7 availability'],
            'tagline' => 'Serving communities with clarity',
            'gallery' => ['government', 'city', 'civic', 'public', 'community'],
            'palette' => ['#0b1220', '#93c5fd', '#eff6ff']
        ],
        'education & training' => [
            'keywords' => ['school', 'education', 'training', 'course', 'academy', 'learning'],
            'services' => ['Online learning', 'Certification programs', 'Corporate training', 'Mentorship', 'Skill pathways'],
            'features' => ['Expert instructors', 'Flexible schedules', 'Career support', 'Global access'],
            'tagline' => 'Empowering minds worldwide',
            'gallery' => ['education', 'classroom', 'learning', 'student', 'workshop'],
            'palette' => ['#0b1b2b', '#72d2ff', '#f0fbff']
        ],
        'finance & consulting' => [
            'keywords' => ['finance', 'financial', 'consulting', 'advisory', 'tax', 'accounting', 'audit'],
            'services' => ['Financial planning', 'Business advisory', 'Tax strategy', 'Risk assessment', 'Reporting'],
            'features' => ['Certified experts', 'Data-driven insights', 'Regulatory compliance', 'Clear guidance'],
            'tagline' => 'Strategic advice. Confident decisions.',
            'gallery' => ['finance', 'analysis', 'consulting', 'business', 'planning'],
            'palette' => ['#0a1526', '#7bd3ff', '#f1f8ff']
        ],
        'creative & agency' => [
            'keywords' => ['agency', 'branding', 'design', 'creative', 'marketing', 'studio'],
            'services' => ['Brand strategy', 'Web design', 'Content production', 'Campaigns', 'Social media'],
            'features' => ['Distinctive identity', 'Story-led design', 'Performance marketing', 'Full-funnel'],
            'tagline' => 'Designing brands people remember',
            'gallery' => ['design', 'branding', 'creative', 'studio', 'marketing'],
            'palette' => ['#110b1f', '#ff8bd7', '#fff0fa']
        ],
    ];

    $defaults = [
        'industry' => 'modern professional services',
        'services' => ['Custom website design', 'Mobile-first responsive layouts', 'SEO-optimized structure', 'Secure architecture', 'Global-ready deployment'],
        'features' => ['Fast launch', 'Modern design system', 'SEO-ready content', 'Conversion-focused'],
        'tagline' => 'Built for your business. Ready for the world.',
        'gallery' => ['business', 'technology', 'office', 'teamwork', 'startup'],
        'palette' => ['#020d08', '#00ff9c', '#eafff4']
    ];

    $selected = $defaults;
    $bestScore = 0;
    foreach ($industries as $name => $data) {
        $score = 0;
        foreach ($data['keywords'] as $keyword) {
            if (str_contains($clean, $keyword)) {
                $score++;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $selected = array_merge($defaults, $data);
            $selected['industry'] = $name;
        }
    }

    $cta = 'Get Started';
    if (preg_match('/book|schedule|reserve/i', $clean)) {
        $cta = 'Book Now';
    } elseif (preg_match('/demo|trial|walkthrough/i', $clean)) {
        $cta = 'Book a Demo';
    } elseif (preg_match('/contact|quote|consult|estimate/i', $clean)) {
        $cta = 'Request Quote';
    } elseif (preg_match('/buy|shop|order/i', $clean)) {
        $cta = 'Shop Now';
    } elseif (preg_match('/learn|enroll|course/i', $clean)) {
        $cta = 'Enroll Today';
    } elseif (preg_match('/donate|support/i', $clean)) {
        $cta = 'Support Us';
    }

    $name = titonova_extract_business_name($prompt, $selected['industry']);
    $location = titonova_extract_location($prompt);
    $tone = titonova_extract_tone($clean);
    $style = titonova_extract_style($clean);
    $global = titonova_detect_global_reach($clean);
    $offerings = titonova_extract_offerings($prompt);

    if (!empty($offerings)) {
        $selected['services'] = $offerings;
    }

    $palette = titonova_pick_palette($tone, $style, $selected['palette']);
    $selected['palette'] = $palette;

    $about = "{$name} specializes in {$selected['industry']} with solutions designed for {$tone} brands";
    if ($location !== '') {
        $about .= " based in {$location}";
    }
    if ($global) {
        $about .= ' serving clients across global markets';
    }
    $about .= '. We combine strategy, design, and delivery to create measurable results across modern markets.';

    $audience = titonova_extract_audience($clean);
    $tagline = $selected['tagline'];
    if ($audience !== '') {
        $tagline .= " for {$audience}";
    }

    $visuals = titonova_detect_visuals($clean, $selected['industry']);
    $imageRequirements = titonova_detect_image_requirements($prompt, $clean);

    $intent = titonova_detect_intent($prompt);
    $industrySignals = titonova_extract_industry_signals($prompt, [
        'industry' => $selected['industry'],
        'audience' => $audience,
    ], $intent);
    $industryConfidence = titonova_calculate_industry_confidence($industrySignals, [
        'audience' => $audience,
        'meta' => ['tone' => $tone],
    ]);
    $microApplied = null;
    $microMatch = titonova_match_micro_profile([
        'industry' => $selected['industry'],
        'features' => $selected['features'],
        'meta' => [
            'industry_signals' => $industrySignals,
            'industryConfidenceScore' => $industryConfidence,
            'intent' => $intent,
        ],
    ]);
    if ($microMatch !== null) {
        $microApplied = titonova_apply_micro_profile([
            'industry' => $selected['industry'],
            'features' => $selected['features'],
            'meta' => [
                'industry_signals' => $industrySignals,
                'industryConfidenceScore' => $industryConfidence,
                'intent' => $intent,
            ],
        ], $microMatch);
        $industryConfidence = (int) ($microApplied['meta']['industryConfidenceScore'] ?? $industryConfidence);
        $industrySignals = $microApplied['meta']['industry_signals'] ?? $industrySignals;
    }
    $icsTier = $industryConfidence >= 80 ? 'high' : ($industryConfidence >= 50 ? 'mid' : 'low');
    $clarificationQuestion = null;
    if ($icsTier === 'low') {
        $clarificationQuestion = titonova_build_clarification_question($industrySignals, [
            'audience' => $audience,
            'meta' => ['industryConfidenceScore' => $industryConfidence],
        ], $intent);
    }

    $meta = [
        'industry' => $selected['industry'],
        'businessName' => $name,
        'location' => $location,
        'cta' => $cta,
        'tone' => $tone,
        'style' => $style,
        'globalReach' => $global,
        'visuals' => $visuals,
        'advancedFeatures' => $advancedFeatures,
        'industrySignals' => $industrySignals,
        'industryConfidenceScore' => $industryConfidence,
        'icsTier' => $icsTier,
        'primaryIndustry' => $selected['industry'],
        'microIndustry' => $microApplied['meta']['microIndustryName'] ?? null,
        'microProfileStatus' => $microApplied['meta']['microProfileStatus'] ?? null,
        'clarificationQuestion' => $clarificationQuestion,
        'intent' => $intent,
        'image_requirements' => $imageRequirements,
    ];

    $data = [
        'prompt' => $prompt,
        'business' => $name,
        'industry' => $selected['industry'],
        'tagline' => $tagline,
        'cta' => $cta,
        'services' => $selected['services'],
        'features' => $selected['features'],
        'about' => $about,
        'gallery' => $selected['gallery'],
        'palette' => $selected['palette'],
        'audience' => $audience,
        'meta' => $meta,
        'visuals' => $visuals,
        'advanced_features' => $advancedFeatures,
    ];

    $imagePrompts = titonova_build_image_prompts($data, $imageRequirements);
    $data['image_prompts'] = $imagePrompts;
    $data['meta']['image_prompts'] = $imagePrompts;

    return $data;
}

function titonova_detect_visuals(string $clean, string $industry): array
{
    $buildingKeywords = [
        'building', 'architecture', 'office', 'headquarters', 'campus', 'facility',
        'real estate', 'property', 'construction', 'interior', 'design', 'workspace',
        'hotel', 'resort', 'clinic', 'hospital', 'school', 'venue', 'storefront',
        'warehouse', 'factory', 'plant', 'showroom', 'estate', 'homes'
    ];

    $peopleKeywords = [
        'team', 'people', 'staff', 'employees', 'community', 'clients', 'patients',
        'students', 'leaders', 'partners', 'coaches', 'trainers', 'founders',
        'volunteers', 'guests', 'customers', 'residents', 'families'
    ];

    $peopleIndustries = [
        'staffing & recruitment', 'nonprofit & community', 'education & training',
        'healthcare & medical', 'fitness & wellness', 'hospitality & travel',
        'beauty & personal care', 'restaurant & food services', 'event & decoration',
        'public sector & government', 'finance & consulting', 'creative & agency'
    ];

    $buildingIndustries = [
        'construction & real estate', 'real estate brokerage', 'hospitality & travel',
        'manufacturing & industrial', 'logistics & supply chain', 'automotive & mobility',
        'healthcare & medical', 'education & training', 'restaurant & food services',
        'public sector & government', 'technology & software', 'media & entertainment'
    ];

    $includeBuildings = false;
    foreach ($buildingKeywords as $keyword) {
        if (str_contains($clean, $keyword)) {
            $includeBuildings = true;
            break;
        }
    }

    $includePeople = false;
    foreach ($peopleKeywords as $keyword) {
        if (str_contains($clean, $keyword)) {
            $includePeople = true;
            break;
        }
    }

    if (in_array($industry, $buildingIndustries, true)) {
        $includeBuildings = true;
    }

    if (in_array($industry, $peopleIndustries, true)) {
        $includePeople = true;
    }

    return [
        'buildings' => $includeBuildings,
        'people' => $includePeople,
    ];
}

function titonova_parse_number_from_phrase(string $value): ?int
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return (int) $value;
    }

    $map = [
        'one' => 1,
        'two' => 2,
        'three' => 3,
        'four' => 4,
        'five' => 5,
        'six' => 6,
        'seven' => 7,
        'eight' => 8,
        'nine' => 9,
        'ten' => 10,
    ];

    return $map[$value] ?? null;
}

function titonova_detect_image_requirements(string $prompt, string $clean): array
{
    $requirements = [
        'enabled' => false,
        'hero' => null,
        'services' => null,
        'about_background' => null,
        'source_prompt' => $prompt,
    ];

    $hasImageSignal = preg_match('/image-aware|image aware|image prompts?|hero image|service image|background image|return image prompts separately/i', $prompt) === 1;
    if ($hasImageSignal) {
        $requirements['enabled'] = true;
    }

    $heroRatio = null;
    if (preg_match('/hero[^\n\r]*image[^\n\r]*\(?\s*(\d+)\s*[:x]\s*(\d+)\s*\)?/i', $prompt, $match)) {
        $heroRatio = $match[1] . ':' . $match[2];
        $requirements['enabled'] = true;
    }

    if (preg_match('/hero\s+image/i', $prompt)) {
        $requirements['hero'] = [
            'count' => 1,
            'aspect_ratio' => $heroRatio,
        ];
        $requirements['enabled'] = true;
    }

    $serviceCount = null;
    if (preg_match('/(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+service\s+section\s+images?/i', $clean, $match)) {
        $serviceCount = titonova_parse_number_from_phrase($match[1]);
    } elseif (preg_match('/(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+service\s+images?/i', $clean, $match)) {
        $serviceCount = titonova_parse_number_from_phrase($match[1]);
    }

    if ($serviceCount !== null && $serviceCount > 0) {
        $requirements['services'] = [
            'count' => $serviceCount,
            'aspect_ratio' => null,
        ];
        $requirements['enabled'] = true;
    }

    if (preg_match('/about\s+section\s+background\s+image/i', $clean) || preg_match('/about\s+background\s+image/i', $clean)) {
        $requirements['about_background'] = [
            'count' => 1,
            'aspect_ratio' => null,
        ];
        $requirements['enabled'] = true;
    }

    return $requirements;
}

function titonova_format_image_prompt(string $subject, array $data, ?string $aspectRatio = null): string
{
    $business = (string) ($data['business'] ?? 'the brand');
    $industry = (string) ($data['industry'] ?? 'modern business');
    $tone = (string) ($data['meta']['tone'] ?? 'professional');
    $style = (string) ($data['meta']['style'] ?? 'modern');
    $imageStyle = (string) ($data['meta']['image_style'] ?? 'clean, premium, modern');
    $audience = (string) ($data['audience'] ?? 'modern businesses');

    $base = "{$subject} for {$business}, a {$industry} brand. {$imageStyle}. {$tone} tone, {$style} styling. Designed for {$audience}. Natural lighting, sharp focus, high-end editorial composition. No text, no logos.";
    if ($aspectRatio) {
        $base .= " Aspect ratio {$aspectRatio}.";
    }

    return $base;
}

function titonova_build_image_prompts(array $data, array $requirements): array
{
    if (empty($requirements['enabled'])) {
        return [];
    }

    $prompts = [];

    if (!empty($requirements['hero'])) {
        $aspect = $requirements['hero']['aspect_ratio'] ?? null;
        $prompts['hero'] = [
            'prompt' => titonova_format_image_prompt('Hero image', $data, $aspect),
            'aspect_ratio' => $aspect,
        ];
    }

    if (!empty($requirements['services'])) {
        $count = (int) ($requirements['services']['count'] ?? 0);
        $aspect = $requirements['services']['aspect_ratio'] ?? null;
        $servicePrompts = [];
        for ($i = 1; $i <= $count; $i++) {
            $servicePrompts[] = [
                'index' => $i,
                'prompt' => titonova_format_image_prompt("Service section image {$i}", $data, $aspect),
                'aspect_ratio' => $aspect,
            ];
        }
        $prompts['services'] = $servicePrompts;
    }

    if (!empty($requirements['about_background'])) {
        $aspect = $requirements['about_background']['aspect_ratio'] ?? null;
        $prompts['about_background'] = [
            'prompt' => titonova_format_image_prompt('About section background image', $data, $aspect),
            'aspect_ratio' => $aspect,
        ];
    }

    return $prompts;
}

function titonova_normalize_image_urls(array $urls): array
{
    $normalized = [];

    if (isset($urls['hero'])) {
        $normalized['hero'] = is_array($urls['hero']) ? (string) ($urls['hero']['url'] ?? '') : (string) $urls['hero'];
    }

    if (isset($urls['about_background'])) {
        $normalized['about_background'] = is_array($urls['about_background'])
            ? (string) ($urls['about_background']['url'] ?? '')
            : (string) $urls['about_background'];
    }

    if (isset($urls['services']) && is_array($urls['services'])) {
        $services = [];
        foreach ($urls['services'] as $item) {
            if (is_array($item)) {
                $services[] = (string) ($item['url'] ?? '');
            } else {
                $services[] = (string) $item;
            }
        }
        $normalized['services'] = array_values(array_filter($services, static function ($url) {
            return $url !== '';
        }));
    }

    return array_filter($normalized, static function ($value) {
        if (is_array($value)) {
            return !empty($value);
        }
        return $value !== '';
    });
}

function titonova_generate_image_urls(array $imagePrompts, array $data): array
{
    if (empty($imagePrompts)) {
        return [];
    }

    $filtered = apply_filters('titonova_image_api_generate', null, $imagePrompts, $data);
    if (is_array($filtered)) {
        return titonova_normalize_image_urls($filtered);
    }

    $endpoint = trim((string) get_option('titonova_image_api_url', ''));
    if ($endpoint === '') {
        return [];
    }

    $apiKey = (string) get_option('titonova_image_api_key', '');
    $payload = [
        'images' => $imagePrompts,
        'meta' => [
            'business' => (string) ($data['business'] ?? ''),
            'industry' => (string) ($data['industry'] ?? ''),
            'style' => (string) ($data['meta']['style'] ?? ''),
            'tone' => (string) ($data['meta']['tone'] ?? ''),
        ],
    ];

    $headers = ['Content-Type' => 'application/json'];
    if ($apiKey !== '') {
        $headers['Authorization'] = 'Bearer ' . $apiKey;
    }

    $response = wp_remote_post($endpoint, [
        'headers' => $headers,
        'timeout' => 30,
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        return [];
    }

    $urls = $decoded['images'] ?? ($decoded['urls'] ?? null);
    if (!is_array($urls)) {
        return [];
    }

    return titonova_normalize_image_urls($urls);
}

function titonova_apply_image_urls_to_html(string $html, array $imageUrls): string
{
    if ($html === '' || empty($imageUrls)) {
        return titonova_sanitize_placeholders($html);
    }

    $replacements = [];
    if (!empty($imageUrls['hero'])) {
        $replacements['{{heroImage}}'] = esc_url($imageUrls['hero']);
    }
    $replacements['{{heroClass}}'] = titonova_get_hero_class($imageUrls);
    if (!empty($imageUrls['about_background'])) {
        $replacements['{{aboutBackgroundImage}}'] = esc_url($imageUrls['about_background']);
    }
    if (!empty($imageUrls['services']) && is_array($imageUrls['services'])) {
        foreach ($imageUrls['services'] as $index => $url) {
            $slot = $index + 1;
            $replacements['{{serviceImage' . $slot . '}}'] = esc_url($url);
        }
    }

    if (empty($replacements)) {
        return $html;
    }

    $html = str_replace(array_keys($replacements), array_values($replacements), $html);
    return titonova_sanitize_placeholders($html);
}

function titonova_sanitize_placeholders(string $html): string
{
    $html = preg_replace('/<span[^>]*class=["\"]hero-credit["\"][^>]*>.*?<\/span>/i', '', $html) ?? $html;
    $html = preg_replace('/Preview image/i', '', $html) ?? $html;
    return preg_replace('/Gallery image/i', '', $html) ?? $html;
}

function titonova_apply_image_urls_to_pages(array $pages, array $imageUrls): array
{
    if (empty($pages) || empty($imageUrls)) {
        return $pages;
    }

    foreach ($pages as $index => $page) {
        if (!isset($page['html'])) {
            continue;
        }
        $pages[$index]['html'] = titonova_apply_image_urls_to_html((string) $page['html'], $imageUrls);
    }

    return $pages;
}

function titonova_extract_business_name(string $prompt, string $fallbackIndustry): string
{
    $patterns = [
        '/(?:called|named)\s+([A-Z][\w\s&\-]{2,})/i',
        '/"([^"]{3,})"/',
        '/\bfor\s+([A-Z][\w\s&\-]{2,})/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $prompt, $matches)) {
            return trim($matches[1]);
        }
    }

    return ucwords($fallbackIndustry) . ' Studio';
}

function titonova_extract_location(string $prompt): string
{
    if (preg_match('/\b(?:in|based in|located in|from|near|around)\s+([A-Z][A-Za-z\s\-]+(?:,\s*[A-Z][A-Za-z\s\-]+)?)\b/i', $prompt, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function titonova_extract_audience(string $clean): string
{
    $audiences = [
        'startups' => ['startup', 'founder', 'early stage'],
        'enterprises' => ['enterprise', 'large company', 'corporate'],
        'local businesses' => ['local', 'nearby', 'community'],
        'luxury clients' => ['luxury', 'premium', 'high-end'],
        'students' => ['students', 'learners', 'graduates'],
        'patients' => ['patients', 'families', 'caregivers'],
    ];

    foreach ($audiences as $label => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($clean, $keyword)) {
                return $label;
            }
        }
    }

    return '';
}

function titonova_extract_tone(string $clean): string
{
    if (preg_match('/luxury|premium|high-end/', $clean)) {
        return 'premium';
    }
    if (preg_match('/friendly|welcoming|warm/', $clean)) {
        return 'friendly';
    }
    if (preg_match('/modern|sleek|minimal/', $clean)) {
        return 'modern';
    }
    if (preg_match('/bold|energetic|vibrant/', $clean)) {
        return 'bold';
    }
    return 'professional';
}

function titonova_extract_style(string $clean): string
{
    if (preg_match('/minimal|minimalist|clean|simple/', $clean)) {
        return 'minimal';
    }
    if (preg_match('/futuristic|next-gen|cutting-edge/', $clean)) {
        return 'futuristic';
    }
    if (preg_match('/playful|fun|youthful|colorful/', $clean)) {
        return 'playful';
    }
    if (preg_match('/elegant|refined|editorial/', $clean)) {
        return 'elegant';
    }
    if (preg_match('/bold|impactful|statement/', $clean)) {
        return 'bold';
    }
    return 'modern';
}

function titonova_detect_global_reach(string $clean): bool
{
    return (bool) preg_match('/global|worldwide|international|multinational|across borders|multi-region/', $clean);
}

function titonova_pick_palette(string $tone, string $style, array $fallback): array
{
    $palettes = [
        'premium' => ['#0b0b0f', '#d6b36a', '#fff7e8'],
        'friendly' => ['#0b1d1f', '#6be8c7', '#eafff6'],
        'modern' => ['#0b1120', '#60f5b2', '#effff8'],
        'bold' => ['#0b1020', '#ff4f8b', '#fff0f5'],
        'minimal' => ['#0f1115', '#cbd5e1', '#f8fafc'],
        'futuristic' => ['#080b1a', '#7dd3fc', '#e0f2fe'],
        'playful' => ['#1a1033', '#ffcf4a', '#fff7d6'],
        'elegant' => ['#0f0a14', '#c4b5fd', '#f5f3ff'],
        'professional' => ['#0a1526', '#7bd3ff', '#f1f8ff'],
    ];

    if (isset($palettes[$style])) {
        return $palettes[$style];
    }
    if (isset($palettes[$tone])) {
        return $palettes[$tone];
    }
    return $fallback;
}

function titonova_extract_offerings(string $prompt): array
{
    $matches = [];
    if (preg_match('/(?:services|solutions|offer|provide|including|products|packages)\s*:\s*([^\.]+)/i', $prompt, $matches)) {
        $list = preg_split('/,|\n|;/', $matches[1]);
        $list = array_filter(array_map('trim', $list));
        if (count($list) >= 2) {
            return array_slice($list, 0, 6);
        }
    }
    return [];
}

function titonova_make_service_description(string $service, array $data): string
{
    $audience = $data['audience'] !== '' ? " for {$data['audience']}" : '';
    $location = $data['meta']['location'] !== '' ? " in {$data['meta']['location']}" : '';
    $industry = $data['industry'];
    return "{$service}{$audience}{$location} built for {$industry} teams. Includes discovery, planning, execution, and ongoing optimization to deliver measurable outcomes.";
}

function titonova_make_feature_description(string $feature, array $data): string
{
    $audience = $data['audience'] !== '' ? " for {$data['audience']}" : '';
    return "{$feature} that simplifies decisions{$audience} and improves clarity across every customer touchpoint.";
}

function titonova_make_business_email(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '', $name)));
    if ($slug === '') {
        $slug = 'business';
    }
    return "hello@{$slug}.com";
}

function titonova_build_multipage_site(array $data): array
{
    $icsTier = $data['meta']['icsTier'] ?? 'high';
    $navPages = [
        ['slug' => 'index', 'title' => 'Home', 'href' => 'index.html'],
        ['slug' => 'about', 'title' => 'About', 'href' => 'about.html'],
        ['slug' => 'services', 'title' => 'Services', 'href' => 'services.html'],
        ['slug' => 'work', 'title' => 'Work', 'href' => 'work.html'],
        ['slug' => 'pricing', 'title' => 'Pricing', 'href' => 'pricing.html'],
        ['slug' => 'blog', 'title' => 'Blog', 'href' => 'blog.html'],
        ['slug' => 'faq', 'title' => 'FAQ', 'href' => 'faq.html'],
    ];

    if ($icsTier === 'low') {
        $navPages = array_values(array_filter($navPages, function ($page) {
            return $page['slug'] !== 'pricing';
        }));
    }

    $pages = [];
    $pages[] = [
        'slug' => 'index',
        'title' => 'Home',
        'filename' => 'index.html',
        'html' => titonova_build_page_shell($data, 'Home', titonova_build_home_content($data), 'index', $navPages),
    ];

    $pages[] = [
        'slug' => 'about',
        'title' => 'About',
        'filename' => 'about.html',
        'html' => titonova_build_page_shell($data, 'About', titonova_build_about_content($data), 'about', $navPages),
    ];

    $pages[] = [
        'slug' => 'services',
        'title' => 'Services',
        'filename' => 'services.html',
        'html' => titonova_build_page_shell($data, 'Services', titonova_build_services_content($data), 'services', $navPages),
    ];

    $pages[] = [
        'slug' => 'work',
        'title' => 'Work',
        'filename' => 'work.html',
        'html' => titonova_build_page_shell($data, 'Work', titonova_build_work_content($data), 'work', $navPages),
    ];

    if ($icsTier !== 'low') {
        $pages[] = [
            'slug' => 'pricing',
            'title' => 'Pricing',
            'filename' => 'pricing.html',
            'html' => titonova_build_page_shell($data, 'Pricing', titonova_build_pricing_content($data), 'pricing', $navPages),
        ];
    }

    $pages[] = [
        'slug' => 'blog',
        'title' => 'Blog',
        'filename' => 'blog.html',
        'html' => titonova_build_page_shell($data, 'Blog', titonova_build_blog_content($data), 'blog', $navPages),
    ];

    $pages[] = [
        'slug' => 'faq',
        'title' => 'FAQ',
        'filename' => 'faq.html',
        'html' => titonova_build_page_shell($data, 'FAQ', titonova_build_faq_content(), 'faq', $navPages),
    ];

    return $pages;
}

function titonova_build_page_shell(array $data, string $pageTitle, string $content, string $activeSlug, array $navPages): string
{
    [$bg, $accent, $soft] = $data['palette'];
    $title = esc_html($data['business']);
    $industry = esc_html(ucwords($data['industry']));
    $cta = esc_html($data['cta']);
    $primaryGoal = titonova_normalize_goal((string) ($data['meta']['primary_goal'] ?? $data['meta']['intent'] ?? $data['intent'] ?? 'leads'));
    $isCallGoal = $primaryGoal === 'calls';
    $industryKey = strtolower((string) ($data['industry'] ?? ''));
    $isNonprofit = $industryKey === 'nonprofit & community';
    $phone = esc_html((string) ($data['meta']['phone'] ?? '+1 (000) 000-0000'));
    $telRaw = preg_replace('/\D+/', '', (string) ($data['meta']['phone'] ?? '+10000000000'));
    $telHref = $telRaw !== '' ? 'tel:+' . ltrim($telRaw, '+') : '#contact';
    $callCtaVerbs = ['Call', 'Talk', 'Speak'];
    $navCtaText = $isCallGoal ? $callCtaVerbs[0] : ($isNonprofit ? 'Donate Now' : 'Request a Consultation');
    $navCtaHref = $isCallGoal ? $telHref : 'contact.html';
    $heroCtaText = $isCallGoal ? $callCtaVerbs[1] : ($isNonprofit ? 'Donate Now' : $cta);
    $heroCtaHref = $isCallGoal ? $telHref : 'contact.html';
    $footerGoalCta = $isCallGoal
        ? "<div style=\"margin-top:16px;\"><a class=\"cta block small\" href=\"{$telHref}\">{$callCtaVerbs[2]}</a></div>"
        : '';
    $advanced = $data['advanced_features']['hasAdvanced'] ?? false;
    $audience = esc_html($data['audience'] !== '' ? $data['audience'] : 'modern businesses');

    $navLinks = '';
    foreach ($navPages as $page) {
        $isActive = $page['slug'] === $activeSlug ? 'active' : '';
        $pageTitleEsc = esc_html($page['title']);
        $href = esc_html($page['href']);
        $navLinks .= "<a class='nav-link {$isActive}' href='{$href}'>{$pageTitleEsc}</a>";
    }

    $pageTitleEsc = esc_html($pageTitle);

    $whatYouGetList = '<li>Multipage site structure</li>'
        . '<li>Consistent design system</li>'
        . '<li>Global-ready messaging</li>'
        . '<li>Conversion-focused layouts</li>';
    if ($advanced) {
        $whatYouGetList .= '<li>Modular platform-ready structure</li>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{$title} — {$pageTitleEsc}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* { box-sizing: border-box; }
:root { --bg: {$bg}; --accent: {$accent}; --soft: {$soft}; }
html { scroll-behavior: smooth; }
body { margin:0; font-family: "Plus Jakarta Sans", Inter, "Segoe UI", sans-serif; background: radial-gradient(circle at 12% -8%, rgba(56,189,248,0.34), transparent 42%), radial-gradient(circle at 88% 8%, rgba(59,130,246,0.3), transparent 40%), radial-gradient(circle at 50% 100%, rgba(37,99,235,0.24), transparent 46%), #020617; color: #e2e8f0; }
.container { max-width: 1180px; margin: auto; padding: 28px 24px 70px; }
h1, h2, h3, h4 { margin: 0 0 12px; letter-spacing: -0.02em; line-height: 1.15; }
h2 { font-size: 30px; }
h3 { font-size: 22px; }
h4 { font-size: 18px; }
p { font-size: 16px; line-height: 1.65; margin: 0 0 12px; color: #cbd5e1; }
a { color: inherit; }
small { font-size: 12px; opacity: .78; color: #94a3b8; }
.announcement { background: linear-gradient(120deg, rgba(30,41,59,0.9), rgba(15,23,42,0.7)); border: 1px solid rgba(125,211,252,0.36); color: #e0f2fe; padding: 10px 16px; border-radius: 999px; display: inline-flex; gap: 10px; align-items: center; font-size: 13px; margin-bottom: 18px; box-shadow: 0 18px 40px rgba(2,6,23,0.58), inset 0 1px 0 rgba(186,230,253,0.24); }
.nav { display:flex; align-items:center; justify-content: space-between; gap: 24px; margin-bottom: 30px; flex-wrap: wrap; position: sticky; top: 12px; z-index: 10; background: linear-gradient(120deg, rgba(15,23,42,0.9), rgba(30,41,59,0.76)); border: 1px solid rgba(125,211,252,0.34); padding: 12px 16px; border-radius: 999px; backdrop-filter: blur(12px); box-shadow: 0 20px 48px rgba(2,6,23,0.62), inset 0 1px 0 rgba(191,219,254,0.18); }
.logo { font-weight: 700; font-size: 20px; letter-spacing: 0.5px; }
.nav-links { display:flex; gap: 14px; flex-wrap: wrap; }
.nav-link { color: #bfdbfe; text-decoration: none; opacity: .92; font-weight: 600; }
.nav-link.active { opacity: 1; color: #93c5fd; }
.nav-actions { display:flex; gap:12px; }
.cta { padding: 12px 24px; border-radius: 999px; border: 1px solid rgba(191,219,254,0.7); background: linear-gradient(135deg, #2563eb, #1d4ed8 45%, #1e40af); color: #f8fbff; font-weight: 700; cursor: pointer; text-decoration: none; box-shadow: 0 22px 38px rgba(30,64,175,0.55), 0 0 36px rgba(59,130,246,0.45); }
.cta.block { display: inline-block; text-align: center; text-decoration: none; }
.cta.small { padding: 10px 18px; font-size: 14px; }
.cta.ghost { background: linear-gradient(120deg, rgba(30,41,59,0.86), rgba(15,23,42,0.72)); border: 1px solid rgba(147,197,253,0.55); color: #dbeafe; box-shadow: inset 0 1px 0 rgba(191,219,254,0.18); }
.pill { display: inline-block; background: rgba(30,64,175,0.34); color: #dbeafe; padding: 6px 14px; border-radius: 999px; font-weight: 600; margin-bottom: 16px; border: 1px solid rgba(147,197,253,0.35); }
.section { margin-bottom: 52px; }
.hero { min-height: 74vh; display: flex; align-items: flex-end; padding: clamp(32px, 5vw, 72px); gap: 24px; border-radius: 28px; background-size: cover; background-position: center; color: #ffffff; background-image: linear-gradient(115deg, rgba(15,23,42,.8), rgba(15,23,42,.45)), url("{{heroImage}}"); box-shadow: 0 30px 72px rgba(15,23,42,0.5), 0 0 80px rgba(59,130,246,0.24); }
.hero-content { max-width: 720px; }
.hero-credit { display: inline-flex; margin-top: 18px; font-size: 12px; opacity: 0.78; letter-spacing: 0.02em; text-transform: uppercase; }
.hero-gradient { background: radial-gradient(circle at 20% 20%, rgba(56,189,248,0.42), transparent 42%), radial-gradient(circle at 80% 30%, rgba(59,130,246,0.34), transparent 45%), linear-gradient(135deg, #0b1734, #1e3a8a) !important; }
.hero h1 { font-size: clamp(34px, 4.8vw, 52px); color: #ffffff; margin-bottom: 12px; }
.hero p { opacity: .95; font-size: 17px; color: rgba(255,255,255,0.9); }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; }
.service-image { width: 100%; height: 168px; border-radius: 14px; background-size: cover; background-position: center; margin-bottom: 14px; }
.about-section { background-image: url("{{aboutBackgroundImage}}"); background-size: cover; background-position: center; border-radius: 20px; padding: 32px; border: 1px solid rgba(96,165,250,0.28); }
.about-section .card { background: rgba(15,23,42,0.8); }
.card { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); padding: 24px; border-radius: 16px; border: 1px solid rgba(125,211,252,0.28); box-shadow: 0 22px 42px rgba(2,6,23,0.58), inset 0 1px 0 rgba(191,219,254,0.12); }
.card h4 { margin: 0 0 10px; }
.card p { margin: 0 0 12px; }
.meta { opacity: .94; color: #94a3b8; font-size: 14px; margin: 0; }
.list { list-style: none; padding: 0; margin: 0; }
.list li { padding: 10px 0; border-bottom: 1px solid rgba(148,163,184,0.22); color: #cbd5e1; }
.stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:18px; }
.stat { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); border: 1px solid rgba(125,211,252,0.26); border-radius: 16px; padding: 20px; text-align:center; box-shadow: 0 18px 34px rgba(2,6,23,0.52), inset 0 1px 0 rgba(191,219,254,0.1); }
.gallery,
.gallery-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.gallery img,
.gallery-grid img { width: 100%; height: 200px; border-radius: 14px; object-fit: cover; box-shadow: 0 10px 24px rgba(15,23,42,.1); }
.logo-cloud { display: flex; flex-wrap: wrap; gap: 10px; }
.logo-pill { border: 1px solid rgba(147,197,253,0.3); padding: 8px 14px; border-radius: 999px; font-weight: 600; font-size: 13px; opacity: .95; background: rgba(15,23,42,0.74); color: #dbeafe; }
.chips { display:flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
.chip { background: rgba(15,23,42,0.74); border: 1px solid rgba(147,197,253,0.28); padding: 8px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #dbeafe; }
.timeline { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.timeline-step { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); border: 1px solid rgba(125,211,252,0.26); border-radius: 16px; padding: 18px; box-shadow: 0 18px 34px rgba(2,6,23,0.52), inset 0 1px 0 rgba(191,219,254,0.1); }
.accordion-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
.accordion { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); border: 1px solid rgba(125,211,252,0.26); border-radius: 16px; padding: 16px; box-shadow: 0 18px 34px rgba(2,6,23,0.52), inset 0 1px 0 rgba(191,219,254,0.1); }
.accordion summary { cursor: pointer; font-weight: 600; margin-bottom: 8px; }
.accordion p { margin-top: 8px; }
.form { display:grid; gap: 10px; }
.input { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(147,197,253,0.4); background: linear-gradient(180deg, rgba(15,23,42,0.82), rgba(2,6,23,0.58)); color: #e2e8f0; box-shadow: inset 0 1px 0 rgba(191,219,254,0.1); }
.textarea { resize: vertical; }
.cta-banner { display:flex; align-items:center; justify-content: space-between; gap: 18px; background: linear-gradient(135deg, rgba(56,189,248,0.24), rgba(37,99,235,0.34) 45%, rgba(15,23,42,0.92)); border: 1px solid rgba(191,219,254,0.44); padding: 24px; border-radius: 20px; box-shadow: 0 24px 48px rgba(2,6,23,0.62), 0 0 60px rgba(59,130,246,0.24); }
.footer-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; margin-top: 30px; }
.footer-grid a { text-decoration: none; opacity: .95; color: #bfdbfe; }
.socials { display:flex; gap: 12px; }
footer { text-align:center; color: #93c5fd; margin-top: 70px; }
@media (max-width: 768px) {
    .nav { justify-content: center; }
    .nav-actions { width: 100%; justify-content: center; }
    .cta { width: 100%; max-width: 320px; }
    .hero { min-height: auto; align-items: flex-start; }
    .hero h1 { font-size: 34px; }
    .cta-banner { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="container" id="top">
    <div class="announcement">Mission-first impact • Community support • Transparent outcomes</div>
    <nav class="nav">
        <div class="logo">{$title}</div>
        <div class="nav-links">{$navLinks}</div>
        <div class="nav-actions">
            <a class="cta block" href="{$navCtaHref}">{$navCtaText}</a>
        </div>
    </nav>

    <section class="section hero {{heroClass}}">
        <div class="hero-content">
            <span class="pill">{$industry}</span>
            <h1>{$title}</h1>
            <p>{$title} advances a clear mission with compassionate support, measurable impact, and community care.</p>
            <a class="cta block small" href="{$heroCtaHref}">{$heroCtaText}</a>
            <span class="hero-credit">Preview image</span>
        </div>
        <div class="card">
            <h4>What you get</h4>
            <ul class="list">
                {$whatYouGetList}
            </ul>
        </div>
    </section>

    {$content}

    <footer>
        <div class="footer-grid">
            <div>
                <strong>{$title}</strong>
                <p class="meta">{$industry} — modern, scalable, and conversion-ready.</p>
            </div>
            <div>
                <strong>Company</strong><br>
                <a href="about.html">About</a><br>
                <a href="services.html">Services</a><br>
                <a href="pricing.html">Pricing</a>
            </div>
            <div>
                <strong>Resources</strong><br>
                <a href="blog.html">Insights</a><br>
                <a href="faq.html">FAQs</a><br>
                <a href="work.html">Work</a>
            </div>
            <div>
                <strong>Social</strong>
                <div class="socials">
                    <span>LinkedIn</span>
                    <span>Behance</span>
                    <span>Dribbble</span>
                </div>
            </div>
        </div>
        {$footerGoalCta}
    </footer>
</div>
</body>
</html>
HTML;
}

function titonova_render_platform_section(array $data): string
{
    $advanced = $data['advanced_features'] ?? ['hasAdvanced' => false];
    if (empty($advanced['hasAdvanced'])) {
        return '';
    }

    $modules = $advanced['modules'] ?? [];
    if (empty($modules)) {
        $modules = ['Payments & billing', 'Customer accounts', 'Dashboard & analytics'];
    }

    $descriptions = [
        'Payments & billing' => 'Checkout-ready flows, invoices, and subscription logic prepared for seamless integration.',
        'Customer accounts' => 'Secure profiles, authentication paths, and role-ready access patterns for future expansion.',
        'Dashboard & analytics' => 'Reporting surfaces and KPI widgets structured to connect data sources later.',
    ];

    $cards = '';
    foreach ($modules as $module) {
        $title = esc_html($module);
        $desc = esc_html($descriptions[$module] ?? 'Modular capability ready to be activated when needed.');
        $cards .= "<div class='card'><h4>{$title}</h4><p class='meta'>{$desc}</p></div>";
    }

    return <<<HTML
    <section class="section" id="platform">
        <h2>Platform-ready architecture</h2>
        <p class="meta">Advanced capabilities are detected. The site is structured with modular sections so upgrades like payments, dashboards, and accounts can be added without redesign.</p>
        <div class="grid">{$cards}</div>
        <div class="card" style="margin-top:18px;">
            <h4>Upgrade path</h4>
            <ul class="list">
                <li>API-ready structure for secure integrations</li>
                <li>Role-based access patterns and onboarding flows</li>
                <li>Billing logic and compliance-ready messaging</li>
                <li>Analytics surfaces mapped to business KPIs</li>
            </ul>
        </div>
    </section>
HTML;
}

function titonova_render_credibility_section(array $data): string
{
    $industry = esc_html(ucwords($data['industry']));
    $audience = esc_html($data['audience'] !== '' ? $data['audience'] : 'modern businesses');

    return <<<HTML
    <section class="section" id="credibility">
        <h2>Credibility and trust</h2>
        <p class="meta">Clear process, compassionate support, and compliant delivery for {$industry} teams serving {$audience}.</p>
        <div class="grid">
            <div class="card"><h4>Safety-first delivery</h4><p class="meta">Responsible handling of sensitive information with privacy-by-design practices.</p></div>
            <div class="card"><h4>Compliance awareness</h4><p class="meta">Clear documentation, audit-ready workflows, and policy-aligned implementation.</p></div>
            <div class="card"><h4>Compassionate guidance</h4><p class="meta">Respectful communication that supports users and stakeholders at every step.</p></div>
        </div>
    </section>
HTML;
}

function titonova_build_home_content(array $data): string
{
    $icsTier = $data['meta']['icsTier'] ?? 'high';
    if (($data['meta']['layoutMode'] ?? '') === 'hybrid' && $icsTier !== 'low') {
        $icsTier = 'mid';
    }
    $industryKey = strtolower($data['industry']);
    $isNonprofit = $industryKey === 'nonprofit & community';
    $isRestaurant = $industryKey === 'restaurant & food services';
    $primaryCta = $isNonprofit ? 'Donate Now' : $data['cta'];
    $supportCta = $isNonprofit ? 'Support Our Mission' : $data['cta'];
    $involveCta = $isNonprofit ? 'Get Involved' : $data['cta'];
    $services = titonova_render_service_cards($data);
    $features = titonova_render_feature_cards($data);
    $testimonials = titonova_render_testimonials();
    $faqs = titonova_render_faqs();
    $about = esc_html($data['about']);
    $logoCloud = titonova_render_logo_cloud();
    $trustBadges = titonova_render_trust_badges();
    $gallery = titonova_render_gallery($data);
    $impactGallery = $gallery !== '' ? "<div class='gallery-grid'>{$gallery}</div>" : '';
    $integrationChips = titonova_render_integration_chips();
    $timeline = titonova_render_timeline();
    $ctaBanner = titonova_render_cta_banner($data, '#contact');
    $industrySections = $icsTier === 'high' ? titonova_render_industry_sections($data) : '';
    $platformSection = $icsTier === 'high' ? titonova_render_platform_section($data) : '';
    $credibilitySection = titonova_render_credibility_section($data);
    $outcomeStatement = $isNonprofit
        ? esc_html('Families and communities feel supported, seen, and empowered.')
        : esc_html('Customers feel supported, informed, and confident in their decisions.');
    $impactTitle = $isNonprofit ? 'Impact in action' : 'Highlights';
    $servicesTitle = $isNonprofit ? 'Programs & services' : 'Services';
    $outcomesTitle = $isNonprofit ? 'Impact outcomes' : 'Outcomes';
    $locationLabel = $data['meta']['location'] !== '' ? " in {$data['meta']['location']}" : '';
    $location = esc_html($data['meta']['location'] !== '' ? $data['meta']['location'] : '');
    $visitSection = '';
    $reservationSection = '';
    if ($isRestaurant) {
        $visitSection = <<<HTML
    <section class="section">
        <h2>Visit us</h2>
        <div class="card">
            <p class="meta">We look forward to hosting you. Reserve a table or send an inquiry.</p>
            <a class="cta block" href="#contact">Reserve a Table</a>
        </div>
    </section>
HTML;

        $reservationSection = <<<HTML
    <section class="section" id="contact">
        <h2>Location, hours, and reservations</h2>
        <div class="card">
            <p>Location: {$location}</p>
            <p>Hours: Mon–Thu 11:30 AM–9:00 PM • Fri–Sat 11:30 AM–10:00 PM • Sun 11:00 AM–8:00 PM</p>
            <form class="form" style="margin-top:16px;">
                <label>Full name</label>
                <input class="input" type="text" placeholder="Jane Doe" />
                <label>Email</label>
                <input class="input" type="email" placeholder="you@email.com" />
                <label>Party size</label>
                <input class="input" type="text" placeholder="2" />
                <label>Preferred date & time</label>
                <input class="input" type="text" placeholder="Friday at 7:00 PM" />
                <button class="cta" type="button">Reserve a Table</button>
            </form>
        </div>
    </section>
HTML;
    }

    return <<<HTML
    <section class="section">
        <h2>Trusted by modern teams</h2>
        <div class="logo-cloud">{$logoCloud}</div>
    </section>

    <section class="section" id="impact">
        <h2>{$impactTitle}{$locationLabel}</h2>
        {$impactGallery}
        <a class="cta block small" href="#contact">{$primaryCta}</a>
    </section>

    <section class="section">
        <h2>Trust badges</h2>
        <div class="logo-cloud">{$trustBadges}</div>
    </section>

    {$credibilitySection}

    <section class="section">
        <h2>Problem → Solution → Outcome</h2>
        <div class="grid">
            <div class="card"><h4>Problem</h4><p class="meta">Communities face barriers to essential resources and support.</p></div>
            <div class="card"><h4>Solution</h4><p class="meta">Mission-driven programs, compassionate services, and trusted partners.</p></div>
            <div class="card"><h4>Outcome</h4><p class="meta">{$outcomeStatement}</p></div>
        </div>
    </section>

    <section class="section about-section">
        <h2>Overview</h2>
        <div class="card">
            <p>{$about}</p>
            <a class="cta block small" href="#contact">{$involveCta}</a>
        </div>
    </section>

    <section class="section">
        <h2>{$servicesTitle}</h2>
        <div class="grid">{$services}</div>
        <a class="cta block small" href="#contact">{$involveCta}</a>
    </section>

    {$platformSection}

    {$industrySections}

    <section class="section">
        <h2>{$outcomesTitle}</h2>
        <div class="grid">{$features}</div>
        <a class="cta block small" href="#contact">{$primaryCta}</a>
    </section>

    <section class="section">
        <h2>Modern capabilities</h2>
        <div class="card">
            <p class="meta">Integrations that power modern experiences.</p>
            <div class="chips">{$integrationChips}</div>
        </div>
    </section>

    <section class="section">
        <h2>Delivery timeline</h2>
        <div class="timeline">{$timeline}</div>
    </section>

    <section class="section">
        <h2>Reviews</h2>
        <div class="grid">{$testimonials}</div>
        <a class="cta block small" href="#contact">{$supportCta}</a>
    </section>

    <section class="section">
        <h2>FAQs</h2>
        <div class="accordion-grid">{$faqs}</div>
        <a class="cta ghost block small" href="faq.html">Read All FAQs</a>
    </section>

    <section class="section">{$ctaBanner}</section>

    {$visitSection}

    <section class="section">
        <h2>Newsletter</h2>
        <div class="card">
            <form class="form">
                <label>Email address</label>
                <input class="input" type="email" placeholder="you@company.com" />
                <button class="cta" type="button">Join updates</button>
                <small>Monthly insights on performance, design, and growth.</small>
            </form>
        </div>
    </section>

    {$reservationSection}
HTML;
}

function titonova_build_about_content(array $data): string
{
    $industry = esc_html(ucwords($data['industry']));
    $audience = esc_html($data['audience'] !== '' ? $data['audience'] : 'modern businesses');
    $industryKey = strtolower($data['industry']);
    $isNonprofit = $industryKey === 'nonprofit & community';
    $intent = $data['meta']['intent'] ?? '';
    $cta = esc_html($data['cta']);
    $primaryCta = $isNonprofit ? 'Donate Now' : $cta;
    $coverage = $data['meta']['globalReach'] ? 'Worldwide' : 'Regional + remote-friendly';
    $about = esc_html($data['about']);
    $business = esc_html($data['business']);

    return <<<HTML
    <section class="section about-section">
        <h2>About {$business}</h2>
        <div class="card">
            <p>{$about}</p>
            <ul class="list">
                <li>Industry focus: {$industry}</li>
                <li>Primary audience: {$audience}</li>
                <li>Coverage: {$coverage}</li>
                <li>Approach: strategy, design, delivery</li>
                <li>Operating model: agile sprints, weekly updates</li>
            </ul>
            <a class="cta block small" href="services.html">View Services</a>
        </div>
    </section>

    <section class="section">
        <h2>Our Values</h2>
        <div class="grid">
            <div class="card"><h4>Clarity</h4><p class="meta">We prioritize simple, focused messaging that guides every decision.</p></div>
            <div class="card"><h4>Performance</h4><p class="meta">Each page is built for speed, conversion, and long-term growth.</p></div>
            <div class="card"><h4>Partnership</h4><p class="meta">We collaborate closely to ensure your brand remains authentic.</p></div>
        </div>
        <a class="cta ghost block small" href="pricing.html">See Pricing</a>
    </section>
HTML;
}

function titonova_build_services_content(array $data): string
{
    $services = titonova_render_service_cards($data);
    $features = titonova_render_feature_cards($data);
    $integrationChips = titonova_render_integration_chips();
    $timeline = titonova_render_timeline();
    $platformSection = titonova_render_platform_section($data);
    $credibilitySection = titonova_render_credibility_section($data);

    return <<<HTML
    <section class="section">
        <h2>Service Lines</h2>
        <div class="grid">{$services}</div>
        <a class="cta block small" href="#contact">Contact Us</a>
    </section>

    <section class="section">
        <h2>Care-focused highlights</h2>
        <div class="grid">{$features}</div>
        <a class="cta ghost block small" href="#contact">Ask a Question</a>
    </section>

    {$credibilitySection}

    <section class="section">
        <h2>Process</h2>
        <div class="grid">
            <div class="card"><h4>Discovery</h4><p class="meta">Align on goals, audiences, and outcomes.</p></div>
            <div class="card"><h4>Design</h4><p class="meta">Shape messaging, layout, and visual system.</p></div>
            <div class="card"><h4>Delivery</h4><p class="meta">Launch with analytics, QA, and optimization.</p></div>
        </div>
        <a class="cta block small" href="blog.html">Read Launch Playbooks</a>
    </section>

    {$platformSection}

    <section class="section">
        <h2>Compliance-friendly stack</h2>
        <div class="card">
            <p class="meta">Accessible, secure, and policy-aligned tools designed for calm user experiences.</p>
            <div class="chips">{$integrationChips}</div>
        </div>
    </section>

    <section class="section">
        <h2>Delivery timeline</h2>
        <div class="timeline">{$timeline}</div>
        <a class="cta ghost block small" href="pricing.html">Plan a launch</a>
    </section>
HTML;
}

function titonova_build_work_content(array $data): string
{
    $gallery = titonova_render_gallery($data);
    $logoCloud = titonova_render_logo_cloud();
    $gallerySection = '';
    if ($gallery !== '') {
        $gallerySection = <<<HTML
    <section class="section">
        <h2>Gallery</h2>
        <div class="gallery-grid">{$gallery}</div>
        <a class="cta ghost block small" href="blog.html">Explore Insights</a>
    </section>
HTML;
    }

    return <<<HTML
    <section class="section">
        <h2>Case Studies</h2>
        <div class="grid">
            <div class="card"><h4>Global rollout</h4><p class="meta">Launched a multi-region site with localized content in 10 days.</p></div>
            <div class="card"><h4>Conversion uplift</h4><p class="meta">Improved lead capture by 42% through UX optimization.</p></div>
            <div class="card"><h4>Brand refresh</h4><p class="meta">Modernized the identity with a scalable design system.</p></div>
        </div>
        <a class="cta block small" href="pricing.html">Start Your Project</a>
    </section>

    {$gallerySection}

    <section class="section">
        <h2>Trusted outcomes</h2>
        <div class="logo-cloud">{$logoCloud}</div>
    </section>
HTML;
}

function titonova_build_pricing_content(array $data): string
{
    $industry = esc_html(ucwords($data['industry']));
    $cta = esc_html($data['cta']);
    $integrationChips = titonova_render_integration_chips();
    $location = $data['meta']['location'] !== '' ? ' in ' . esc_html($data['meta']['location']) : '';

    return <<<HTML
    <section class="section">
        <h2>Pricing{$location}</h2>
        <div class="grid">
            <div class="card">
                <h4>Starter</h4>
                <p class="meta">Best for new {$industry} teams.</p>
                <p><strong>$1,200</strong> / project</p>
                <ul class="list">
                    <li>Multipage site</li>
                    <li>Industry messaging</li>
                    <li>Basic analytics</li>
                    <li>Launch support</li>
                </ul>
            </div>
            <div class="card">
                <h4>Growth</h4>
                <p class="meta">For scaling brands with campaigns.</p>
                <p><strong>$3,500</strong> / project</p>
                <ul class="list">
                    <li>Conversion optimization</li>
                    <li>Content expansion</li>
                    <li>Performance reviews</li>
                    <li>Priority support</li>
                </ul>
            </div>
            <div class="card">
                <h4>Enterprise</h4>
                <p class="meta">Global teams with advanced needs.</p>
                <p><strong>Custom</strong> pricing</p>
                <ul class="list">
                    <li>Localization strategy</li>
                    <li>Design system rollout</li>
                    <li>Dedicated success lead</li>
                    <li>Security reviews</li>
                </ul>
            </div>
        </div>
        <div class="card" style="margin-top:20px;">
            <h4>Ready to discuss your property goals?</h4>
            <p class="meta">We provide clear pricing, next steps, and local market guidance.</p>
            <a class="cta block" href="contact.html">Request a Consultation</a>
        </div>
    </section>

    <section class="section">
        <h2>Everything included</h2>
        <div class="card">
            <p class="meta">Modern performance, accessibility, and integrations.</p>
            <div class="chips">{$integrationChips}</div>
        </div>
    </section>
HTML;
}

function titonova_build_blog_content(array $data): string
{
    $industry = esc_html(ucwords($data['industry']));

    return <<<HTML
    <section class="section">
        <h2>Latest Insights</h2>
        <div class="grid">
            <div class="card">
                <h4>How {$industry} teams scale globally</h4>
                <p class="meta">A playbook for distributed growth, localization, and performance.</p>
            </div>
            <div class="card">
                <h4>Designing conversion-first experiences</h4>
                <p class="meta">Key layouts and messaging patterns that increase engagement.</p>
            </div>
            <div class="card">
                <h4>Launch checklist for 2026</h4>
                <p class="meta">Everything to validate before you go live.</p>
            </div>
        </div>
        <a class="cta block small" href="pricing.html">Get a Launch Plan</a>
    </section>

    <section class="section">
        <h2>Featured Series</h2>
        <div class="grid">
            <div class="card"><h4>Global brand foundations</h4><p class="meta">Messaging, visuals, and positioning.</p></div>
            <div class="card"><h4>Growth experiments</h4><p class="meta">Iteration cycles that compound results.</p></div>
            <div class="card"><h4>Operational readiness</h4><p class="meta">Processes for reliable delivery at scale.</p></div>
        </div>
        <a class="cta ghost block small" href="faq.html">Read FAQs</a>
    </section>

    <section class="section">
        <h2>Stay in the loop</h2>
        <div class="card">
            <form class="form">
                <label>Email address</label>
                <input class="input" type="email" placeholder="you@company.com" />
                <button class="cta" type="button">Subscribe</button>
                <small>Monthly playbooks and launch checklists.</small>
            </form>
        </div>
    </section>
HTML;
}

function titonova_build_faq_content(): string
{
    $faqs = titonova_render_faqs();

    return <<<HTML
    <section class="section">
        <h2>FAQs</h2>
        <div class="accordion-grid">{$faqs}</div>
        <a class="cta block small" href="#contact">Contact Us</a>
    </section>

    <section class="section">
        <h2>Need more help?</h2>
        <div class="grid">
            <div class="card"><h4>Support line</h4><p class="meta">Speak with a specialist about eligibility, services, and next steps.</p></div>
            <div class="card"><h4>Guided intake</h4><p class="meta">We help you gather the right information before moving forward.</p></div>
            <div class="card"><h4>Privacy and compliance</h4><p class="meta">We follow clear procedures for sensitive information and consent.</p></div>
        </div>
    </section>
HTML;
}

function titonova_render_service_cards(array $data): string
{
    $services = '';
    $index = 0;
    foreach ($data['services'] as $service) {
        $index++;
        $serviceTitle = esc_html($service);
        $serviceDesc = esc_html(titonova_make_service_description($service, $data));
        $imageSlot = '';
        if ($index <= 3) {
            $imageSlot = "<div class='service-image' style=\"background-image:url('{{serviceImage{$index}}}')\"></div>";
        }
        $services .= "<div class='card'>{$imageSlot}<h4>{$serviceTitle}</h4><p>{$serviceDesc}</p><p class='meta'>Deliverables: discovery, roadmap, implementation, QA, and post-launch optimization.</p></div>";
    }
    return $services;
}

function titonova_render_feature_cards(array $data): string
{
    $features = '';
    foreach ($data['features'] as $feature) {
        $featureTitle = esc_html($feature);
        $featureDesc = esc_html(titonova_make_feature_description($feature, $data));
        $features .= "<div class='card'><h4>{$featureTitle}</h4><p>{$featureDesc}</p><p class='meta'>Designed to reduce uncertainty and support confident decisions.</p></div>";
    }
    return $features;
}

function titonova_render_faqs(): string
{
    $faqs = [
        [
            'q' => 'What makes your approach different?',
            'a' => 'We combine industry intelligence with performance-driven design to create websites that convert. Every engagement includes discovery workshops, clear success metrics, and fast iterations.'
        ],
        [
            'q' => 'How fast can we launch?',
            'a' => 'Most projects are ready within days, with a clear roadmap from discovery to deployment. We also provide staging previews, QA checks, and post-launch monitoring.'
        ],
        [
            'q' => 'Can this scale with our growth?',
            'a' => 'Yes. Every build includes scalable architecture, modern tooling, and future-ready design. We plan for content expansion, traffic growth, and new integrations from day one.'
        ],
    ];

    $faqHtml = '';
    foreach ($faqs as $faq) {
        $question = esc_html($faq['q']);
        $answer = esc_html($faq['a']);
        $faqHtml .= "<details class='accordion'><summary>{$question}</summary><p>{$answer}</p></details>";
    }

    return $faqHtml;
}

function titonova_render_testimonials(): string
{
    $testimonials = [
        ['name' => 'Alex Morgan', 'role' => 'Operations Lead', 'quote' => 'The site felt designed specifically for our industry. Fast, elegant, and effective. We saw a measurable lift in inquiries within the first week.'],
        ['name' => 'Sarah Kim', 'role' => 'Founder', 'quote' => 'We launched with a complete online presence in record time. The team handled messaging, visuals, and performance without any friction.'],
        ['name' => 'Daniel Okoye', 'role' => 'Director', 'quote' => 'Strategy, design, and clarity all in one place. The final experience is refined, responsive, and built to scale.'],
    ];

    $testimonialHtml = '';
    foreach ($testimonials as $t) {
        $testimonialHtml .= "<div class='card'><p>“{$t['quote']}”</p><strong>{$t['name']}</strong><br><small>{$t['role']}</small></div>";
    }

    return $testimonialHtml;
}

function titonova_render_industry_sections(array $data): string
{
    $industry = strtolower($data['industry']);
    $sections = [];

    switch ($industry) {
        case 'hospitality & travel':
            $sections = [
                ['title' => 'Stays & Experiences', 'items' => ['Luxury accommodations', 'Curated tours', 'Local partnerships']],
                ['title' => 'Reservations', 'items' => ['Real-time availability', 'Flexible booking', 'Group coordination']],
                ['title' => 'Guest Care', 'items' => ['Concierge support', 'Personalized itineraries', '24/7 assistance']],
            ];
            break;
        case 'fitness & wellness':
            $sections = [
                ['title' => 'Programs', 'items' => ['Personal coaching', 'Group classes', 'Wellness plans']],
                ['title' => 'Progress', 'items' => ['Goal tracking', 'Performance metrics', 'Community support']],
                ['title' => 'Member Experience', 'items' => ['Easy scheduling', 'Mobile access', 'On-demand content']],
            ];
            break;
        case 'beauty & personal care':
            $sections = [
                ['title' => 'Signature Services', 'items' => ['Skincare rituals', 'Hair & styling', 'Makeup artistry']],
                ['title' => 'Bookings', 'items' => ['Online scheduling', 'Memberships', 'Gift cards']],
                ['title' => 'Products', 'items' => ['Retail catalog', 'Product recommendations', 'Bundles']],
            ];
            break;
        case 'legal & compliance':
            $sections = [
                ['title' => 'Practice Areas', 'items' => ['Advisory', 'Litigation', 'Compliance']],
                ['title' => 'Client Intake', 'items' => ['Secure consultations', 'Case assessments', 'Document exchange']],
                ['title' => 'Insights', 'items' => ['Regulatory updates', 'Risk guidance', 'Case studies']],
            ];
            break;
        case 'automotive & mobility':
            $sections = [
                ['title' => 'Inventory', 'items' => ['Featured vehicles', 'Certified listings', 'Trade-in tools']],
                ['title' => 'Service', 'items' => ['Maintenance booking', 'Service plans', 'Parts catalog']],
                ['title' => 'Financing', 'items' => ['Loan options', 'Lease programs', 'Payment calculator']],
            ];
            break;
        case 'nonprofit & community':
            $sections = [
                ['title' => 'Impact', 'items' => ['Stories', 'Programs', 'Annual reports']],
                ['title' => 'Get Involved', 'items' => ['Volunteer signup', 'Events', 'Partnerships']],
                ['title' => 'Donate', 'items' => ['Secure giving', 'Recurring support', 'Fundraisers']],
            ];
            break;
        case 'logistics & supply chain':
            $sections = [
                ['title' => 'Capabilities', 'items' => ['Freight management', 'Warehousing', 'Last-mile delivery']],
                ['title' => 'Tracking', 'items' => ['Live shipment status', 'Route visibility', 'Alerts']],
                ['title' => 'Compliance', 'items' => ['Customs support', 'Documentation', 'Safety standards']],
            ];
            break;
        case 'manufacturing & industrial':
            $sections = [
                ['title' => 'Production', 'items' => ['Custom builds', 'Quality assurance', 'On-time delivery']],
                ['title' => 'Capabilities', 'items' => ['Engineering services', 'Lean operations', 'Supply planning']],
                ['title' => 'Safety', 'items' => ['Certifications', 'Compliance', 'Training']],
            ];
            break;
        case 'media & entertainment':
            $sections = [
                ['title' => 'Studios', 'items' => ['Production services', 'Creative direction', 'Post-production']],
                ['title' => 'Distribution', 'items' => ['Streaming strategy', 'Audience growth', 'Licensing']],
                ['title' => 'Portfolio', 'items' => ['Featured work', 'Campaigns', 'Press kit']],
            ];
            break;
        case 'event & decoration':
            $sections = [
                ['title' => 'Event Design', 'items' => ['Wedding styling', 'Corporate events', 'Themed décor']],
                ['title' => 'Planning', 'items' => ['Timelines', 'Vendor coordination', 'On-site setup']],
                ['title' => 'Gallery', 'items' => ['Past events', 'Mood boards', 'Seasonal looks']],
            ];
            break;
        case 'staffing & recruitment':
            $sections = [
                ['title' => 'Talent Solutions', 'items' => ['Permanent staffing', 'Contract roles', 'Executive search']],
                ['title' => 'Industries', 'items' => ['Tech', 'Operations', 'Finance']],
                ['title' => 'Candidates', 'items' => ['Job listings', 'Career coaching', 'Onboarding']],
            ];
            break;
        case 'restaurant & food services':
            $sections = [
                ['title' => 'Menu Highlights', 'items' => ['Signature dishes', 'Seasonal specials', 'Chef picks']],
                ['title' => 'Reservations', 'items' => ['Online booking', 'Private events', 'Catering']],
                ['title' => 'Location & Hours', 'items' => ['Find us', 'Delivery zones', 'Contact']],
            ];
            break;
        case 'construction & real estate':
            $sections = [
                ['title' => 'Projects', 'items' => ['Residential', 'Commercial', 'Renovations']],
                ['title' => 'Services', 'items' => ['Project management', 'Permits', 'Design-build']],
                ['title' => 'Capabilities', 'items' => ['Budgeting', 'Scheduling', 'Quality control']],
            ];
            break;
        case 'real estate brokerage':
            $sections = [
                ['title' => 'Listings', 'items' => ['Featured homes', 'New listings', 'Virtual tours']],
                ['title' => 'Buy & Sell', 'items' => ['Valuation tools', 'Buyer guidance', 'Closing support']],
                ['title' => 'Neighborhoods', 'items' => ['Market insights', 'Local guides', 'School info']],
            ];
            break;
        case 'ecommerce & retail':
            $sections = [
                ['title' => 'Product Highlights', 'items' => ['Best sellers', 'New arrivals', 'Bundles']],
                ['title' => 'Shopping', 'items' => ['Fast checkout', 'Secure payments', 'Order tracking']],
                ['title' => 'Support', 'items' => ['Returns', 'FAQ', 'Live chat']],
            ];
            break;
        case 'fintech & payments':
            $sections = [
                ['title' => 'Platform', 'items' => ['Payment rails', 'Wallets', 'APIs']],
                ['title' => 'Security', 'items' => ['Fraud prevention', 'Compliance', 'Risk monitoring']],
                ['title' => 'Growth', 'items' => ['Onboarding', 'KYC flows', 'Global coverage']],
            ];
            break;
        case 'technology & software':
            $sections = [
                ['title' => 'Platform', 'items' => ['Product overview', 'Key features', 'Integrations']],
                ['title' => 'Security', 'items' => ['Compliance', 'Data privacy', 'SLA support']],
                ['title' => 'Resources', 'items' => ['Docs', 'Changelog', 'Developer tools']],
            ];
            break;
        case 'agriculture & food production':
            $sections = [
                ['title' => 'Operations', 'items' => ['Farm management', 'Supply partners', 'Forecasting']],
                ['title' => 'Sustainability', 'items' => ['Traceability', 'Certifications', 'Impact']],
                ['title' => 'Distribution', 'items' => ['Wholesale', 'Retail partners', 'Logistics']],
            ];
            break;
        case 'healthcare & medical':
            $sections = [
                ['title' => 'Care Services', 'items' => ['Consultations', 'Diagnostics', 'Telehealth']],
                ['title' => 'Patient Resources', 'items' => ['Appointments', 'Insurance', 'Forms']],
                ['title' => 'Outcomes', 'items' => ['Clinical team', 'Facilities', 'Patient stories']],
            ];
            break;
        case 'public sector & government':
            $sections = [
                ['title' => 'Services', 'items' => ['Citizen services', 'Permits', 'Payments']],
                ['title' => 'Transparency', 'items' => ['Budgets', 'Reports', 'Open data']],
                ['title' => 'Engagement', 'items' => ['Public meetings', 'News', 'Feedback']],
            ];
            break;
        case 'education & training':
            $sections = [
                ['title' => 'Programs', 'items' => ['Courses', 'Certifications', 'Workshops']],
                ['title' => 'Admissions', 'items' => ['Enrollment', 'Tuition', 'Scholarships']],
                ['title' => 'Student Success', 'items' => ['Mentorship', 'Career services', 'Alumni']],
            ];
            break;
        case 'finance & consulting':
            $sections = [
                ['title' => 'Advisory', 'items' => ['Strategy', 'Risk', 'Tax planning']],
                ['title' => 'Insights', 'items' => ['Reports', 'Market updates', 'Case studies']],
                ['title' => 'Client Success', 'items' => ['Onboarding', 'Reviews', 'Support']],
            ];
            break;
        case 'creative & agency':
            $sections = [
                ['title' => 'Capabilities', 'items' => ['Branding', 'Web design', 'Campaigns']],
                ['title' => 'Portfolio', 'items' => ['Featured work', 'Case studies', 'Awards']],
                ['title' => 'Process', 'items' => ['Discovery', 'Creative sprints', 'Launch']],
            ];
            break;
        default:
            $sections = [
                ['title' => 'Capabilities', 'items' => ['Strategy', 'Design', 'Development']],
                ['title' => 'Operations', 'items' => ['Support', 'Analytics', 'Optimization']],
                ['title' => 'Growth', 'items' => ['Conversion', 'Content', 'Automation']],
            ];
            break;
    }

    $html = '<section class="section"><h2>Industry essentials</h2><div class="grid">';
    foreach ($sections as $section) {
        $title = esc_html($section['title']);
        $itemsHtml = '';
        foreach ($section['items'] as $item) {
            $itemsHtml .= '<li>' . esc_html($item) . '</li>';
        }
        $html .= "<div class='card'><h4>{$title}</h4><ul class='list'>{$itemsHtml}</ul></div>";
    }
    $html .= '</div></section>';

    return $html;
}

function titonova_normalize_gallery_images($images): array
{
    if (empty($images)) {
        return [];
    }

    if (is_string($images)) {
        $decoded = json_decode($images, true);
        if (is_array($decoded)) {
            $images = $decoded;
        } else {
            $images = preg_split('/\s*,\s*|\r?\n/', $images);
        }
    }

    if (!is_array($images)) {
        return [];
    }

    $normalized = array_values(array_filter(array_map('strval', $images), static function ($url) {
        return $url !== '';
    }));

    return $normalized;
}

function titonova_normalize_single_image($image): string
{
    if (is_array($image)) {
        if (isset($image['url'])) {
            return (string) $image['url'];
        }
        $first = reset($image);
        return is_string($first) ? $first : '';
    }
    if (is_string($image)) {
        return trim($image);
    }
    return '';
}

function titonova_get_industry_hero_map(): array
{
    return [
        'healthcare' => [
            'modern healthcare clinic interior bright',
            'nurse and patient consultation professional',
            'medical team in clean modern hospital',
        ],
        'tours' => [
            'kenya safari sunrise landscape',
            'african travel adventure scenic',
            'tourists overlooking african valley',
        ],
        'technology' => [
            'futuristic technology abstract background',
            'AI data visualization dark theme',
            'modern software development workspace',
        ],
        'business' => [
            'executive boardroom modern',
            'corporate strategy meeting luxury office',
            'professional business team confidence',
        ],
        'real_estate' => [
            'luxury modern home exterior',
            'high-end apartment interior',
            'real estate city skyline sunset',
        ],
        'default' => [
            'modern minimal website hero background',
            'abstract professional gradient background',
            'clean tech-inspired abstract design',
        ],
    ];
}

function titonova_resolve_industry_hero_key(string $industry): string
{
    $normalized = strtolower($industry);
    if ($normalized === '') {
        return 'default';
    }
    if (str_contains($normalized, 'health')) {
        return 'healthcare';
    }
    if (str_contains($normalized, 'tour') || str_contains($normalized, 'travel') || str_contains($normalized, 'hospitality')) {
        return 'tours';
    }
    if (str_contains($normalized, 'real estate') || str_contains($normalized, 'property') || str_contains($normalized, 'broker')) {
        return 'real_estate';
    }
    if (str_contains($normalized, 'tech') || str_contains($normalized, 'software') || str_contains($normalized, 'saas') || str_contains($normalized, 'ai')) {
        return 'technology';
    }
    if (str_contains($normalized, 'business') || str_contains($normalized, 'professional') || str_contains($normalized, 'consult')) {
        return 'business';
    }
    return 'default';
}

function titonova_get_fallback_hero_image(): string
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900" viewBox="0 0 1600 900"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0f172a"/><stop offset="50%" stop-color="#1f2937"/><stop offset="100%" stop-color="#0ea5e9"/></linearGradient></defs><rect width="1600" height="900" fill="url(#g)"/></svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function titonova_get_industry_hero_image(string $industry): string
{
    $map = titonova_get_industry_hero_map();
    $key = titonova_resolve_industry_hero_key($industry);
    $queries = $map[$key] ?? ($map['default'] ?? []);
    if (empty($queries)) {
        return titonova_get_fallback_hero_image();
    }
    $index = array_rand($queries);
    $query = $queries[$index];
    return 'https://source.unsplash.com/1600x900/?' . rawurlencode($query);
}

function titonova_resolve_hero_image(string $userHeroImage = '', string $industry = 'default'): array
{
    $userHeroImage = titonova_normalize_single_image($userHeroImage);
    if ($userHeroImage !== '' && strlen($userHeroImage) > 5) {
        return [
            'type' => 'user',
            'src' => $userHeroImage,
        ];
    }

    $stock = titonova_get_industry_hero_image($industry);
    if ($stock !== '') {
        return [
            'type' => 'stock',
            'src' => $stock,
        ];
    }

    return [
        'type' => 'fallback',
        'src' => null,
    ];
}

function titonova_ensure_hero_image(array $imageUrls, array $data): array
{
    $userHero = (string) ($data['meta']['hero_image'] ?? ($data['hero_image'] ?? ''));
    $resolved = titonova_resolve_hero_image($userHero, (string) ($data['industry'] ?? 'default'));
    $hero = $resolved['src'] ?? '';
    if ($hero === '' || $hero === null) {
        $hero = titonova_get_fallback_hero_image();
    }
    $imageUrls['hero'] = $hero;
    $imageUrls['hero_source'] = (string) ($resolved['type'] ?? 'fallback');
    return $imageUrls;
}

function titonova_is_fallback_hero(string $url): bool
{
    return str_starts_with($url, 'data:image/svg+xml;utf8,');
}

function titonova_get_hero_class(array $imageUrls): string
{
    $hero = (string) ($imageUrls['hero'] ?? '');
    if ($hero !== '' && titonova_is_fallback_hero($hero)) {
        return 'hero-gradient';
    }
    return '';
}

function titonova_get_industry_stock_map(): array
{
    return [
        'healthcare' => [
            'healthcare clinic interior',
            'nurse patient consultation',
            'medical office modern',
        ],
        'tours' => [
            'african safari tour',
            'kenya travel landscape',
            'tourists sightseeing africa',
        ],
        'business' => [
            'modern office workspace',
            'business team meeting',
            'corporate strategy session',
        ],
        'technology' => [
            'futuristic technology interface',
            'software development workspace',
            'AI data visualization',
        ],
        'default' => [
            'modern professional website',
            'minimal business branding',
            'clean web design',
        ],
    ];
}

function titonova_resolve_industry_stock_key(string $industry): string
{
    $normalized = strtolower($industry);
    if ($normalized === '') {
        return 'default';
    }
    if (str_contains($normalized, 'health')) {
        return 'healthcare';
    }
    if (str_contains($normalized, 'tour') || str_contains($normalized, 'travel') || str_contains($normalized, 'hospitality')) {
        return 'tours';
    }
    if (str_contains($normalized, 'tech') || str_contains($normalized, 'software') || str_contains($normalized, 'saas') || str_contains($normalized, 'ai')) {
        return 'technology';
    }
    if (str_contains($normalized, 'business') || str_contains($normalized, 'professional') || str_contains($normalized, 'consult')) {
        return 'business';
    }
    return 'default';
}

function titonova_fetch_stock_images(string $industry, int $count = 3): array
{
    $count = max(1, $count);
    $stockMap = titonova_get_industry_stock_map();
    $stockKey = titonova_resolve_industry_stock_key($industry);
    $queries = $stockMap[$stockKey] ?? ($stockMap['default'] ?? []);
    if (empty($queries)) {
        return [];
    }

    $images = [];
    $queryCount = count($queries);
    for ($i = 0; $i < $count; $i++) {
        $query = $queries[$i % $queryCount];
        $images[] = 'https://source.unsplash.com/800x600/?' . rawurlencode($query);
    }

    return $images;
}

function titonova_resolve_gallery_images(array $userImages, string $industry = 'default', int $minImages = 3): array
{
    $minImages = max(1, $minImages);
    if (count($userImages) >= $minImages) {
        return $userImages;
    }

    return titonova_fetch_stock_images($industry, $minImages);
}

function titonova_collect_gallery_images(array $data): array
{
    $candidateKeys = [
        ['meta', 'gallery_images'],
        ['meta', 'user_gallery_images'],
        ['meta', 'uploaded_gallery_images'],
        ['meta', 'user_images'],
        ['meta', 'uploaded_images'],
        ['gallery_images'],
        ['user_gallery_images'],
        ['uploaded_gallery_images'],
        ['user_images'],
        ['uploaded_images'],
    ];

    $userImages = [];
    foreach ($candidateKeys as $path) {
        if ($path[0] === 'meta') {
            $candidate = $data['meta'][$path[1]] ?? null;
        } else {
            $candidate = $data[$path[0]] ?? null;
        }

        $normalized = titonova_normalize_gallery_images($candidate);
        if (!empty($normalized)) {
            $userImages = $normalized;
            break;
        }
    }

    $industry = (string) ($data['industry'] ?? 'default');
    $galleryUrls = titonova_resolve_gallery_images($userImages, $industry, 3);
    if (empty($galleryUrls)) {
        $galleryTerms = $data['gallery'] ?? [];
        if (!is_array($galleryTerms) || empty($galleryTerms)) {
            return [];
        }

        if (!empty($data['visuals']['buildings'])) {
            $galleryTerms = array_merge($galleryTerms, ['building', 'architecture', 'office', 'interior']);
        }
        if (!empty($data['visuals']['people'])) {
            $galleryTerms = array_merge($galleryTerms, ['people', 'team', 'professionals', 'community']);
        }
        $galleryTerms = array_values(array_unique($galleryTerms));
        if (empty($galleryTerms)) {
            return [];
        }

        foreach ($galleryTerms as $term) {
            $galleryUrls[] = 'https://source.unsplash.com/800x600/?' . rawurlencode($term);
        }
    }

    return array_values(array_filter($galleryUrls, static function ($url) {
        return $url !== '';
    }));
}

function titonova_render_gallery(array $data): string
{
    $galleryUrls = titonova_collect_gallery_images($data);
    if (empty($galleryUrls)) {
        return '';
    }

    $galleryHtml = '';
    foreach ($galleryUrls as $url) {
        $src = esc_url($url);
        if ($src === '') {
            continue;
        }
        $galleryHtml .= "<img src='{$src}' alt='Gallery image' loading='lazy' />";
    }

    return $galleryHtml;
}

function titonova_render_logo_cloud(): string
{
    $logos = ['Nimbus', 'Aurora', 'Vertex', 'Pulse', 'Atlas', 'Signal', 'Lumen', 'Nova'];
    $html = '';
    foreach ($logos as $logo) {
        $name = esc_html($logo);
        $html .= "<span class='logo-pill'>{$name}</span>";
    }
    return $html;
}

function titonova_render_trust_badges(): string
{
    $badges = ['Security-first', 'Privacy-aware', 'Compliance-ready', 'Accessibility-friendly', 'Clear documentation'];
    $html = '';
    foreach ($badges as $badge) {
        $label = esc_html($badge);
        $html .= "<span class='logo-pill'>{$label}</span>";
    }
    return $html;
}

function titonova_render_integration_chips(): string
{
    $chips = ['Analytics', 'CRM', 'Payments', 'Email', 'Automation', 'Search', 'CMS', 'CDN', 'Chat', 'Bookings'];
    $html = '';
    foreach ($chips as $chip) {
        $label = esc_html($chip);
        $html .= "<span class='chip'>{$label}</span>";
    }
    return $html;
}

function titonova_render_timeline(): string
{
    $steps = [
        ['title' => 'Discovery', 'desc' => 'Align goals, audiences, and KPIs.'],
        ['title' => 'Strategy', 'desc' => 'Shape messaging, IA, and conversion paths.'],
        ['title' => 'Design', 'desc' => 'Build a modern UI system and prototypes.'],
        ['title' => 'Launch', 'desc' => 'QA, performance tuning, and go-live.'],
        ['title' => 'Growth', 'desc' => 'Iteration, experiments, and scale.'],
    ];
    $html = '';
    foreach ($steps as $step) {
        $title = esc_html($step['title']);
        $desc = esc_html($step['desc']);
        $html .= "<div class='timeline-step'><h4>{$title}</h4><p class='meta'>{$desc}</p></div>";
    }
    return $html;
}

function titonova_render_cta_banner(array $data, string $href = 'pricing.html'): string
{
    $cta = esc_html($data['cta']);
    $business = esc_html($data['business']);
    $link = esc_url($href);
    return "<div class='cta-banner'><div><h3>Ready to plan the next step for {$business}?</h3><p class='meta'>We provide clear scope, timelines, and delivery plans aligned to your goals.</p></div><a class='cta block' href='{$link}'>{$cta}</a></div>";
}

function titonova_build_html(array $data): string
{
    $services = '';
    foreach ($data['services'] as $service) {
        $serviceTitle = esc_html($service);
        $serviceDesc = esc_html(titonova_make_service_description($service, $data));
        $services .= "<div class='card'><h4>{$serviceTitle}</h4><p>{$serviceDesc}</p><p class='meta'>Deliverables: discovery, roadmap, implementation, QA, and post-launch optimization.</p></div>";
    }
    $features = '';
    foreach ($data['features'] as $feature) {
        $featureTitle = esc_html($feature);
        $featureDesc = esc_html(titonova_make_feature_description($feature, $data));
        $features .= "<div class='card'><h4>{$featureTitle}</h4><p>{$featureDesc}</p><p class='meta'>Designed to reduce uncertainty and support confident decisions.</p></div>";
    }

    $icsTier = $data['meta']['icsTier'] ?? 'high';
    $industrySections = $icsTier === 'high' ? titonova_render_industry_sections($data) : '';
    $platformSection = $icsTier === 'high' ? titonova_render_platform_section($data) : '';
    $galleryHtml = titonova_render_gallery($data);
    $impactGallery = $galleryHtml !== '' ? "<div class=\"gallery\">{$galleryHtml}</div>" : '';
    $gallerySection = '';
    if ($galleryHtml !== '') {
        $gallerySection = <<<HTML
    <section class="section" id="gallery">
        <h2>Gallery</h2>
        <div class="gallery-grid">{$galleryHtml}</div>
        <a class="cta ghost block small" href="#contact">Donate Now</a>
    </section>
HTML;
    }

    $faqs = [
        [
            'q' => 'What makes your approach different?',
            'a' => 'We combine industry intelligence with performance-driven design to create websites that convert. Every engagement includes discovery workshops, clear success metrics, and fast iterations.'
        ],
        [
            'q' => 'How fast can we launch?',
            'a' => 'Most projects are ready within days, with a clear roadmap from discovery to deployment. We also provide staging previews, QA checks, and post-launch monitoring.'
        ],
        [
            'q' => 'Can this scale with our growth?',
            'a' => 'Yes. Every build includes scalable architecture, modern tooling, and future-ready design. We plan for content expansion, traffic growth, and new integrations from day one.'
        ],
    ];

    $faqHtml = '';
    foreach ($faqs as $faq) {
        $question = esc_html($faq['q']);
        $answer = esc_html($faq['a']);
        $faqHtml .= "<details class='accordion'><summary>{$question}</summary><p>{$answer}</p></details>";
    }

    $testimonials = [
        ['name' => 'Alex Morgan', 'role' => 'Operations Lead', 'quote' => 'The site felt designed specifically for our industry. Fast, elegant, and effective. We saw a measurable lift in inquiries within the first week.'],
        ['name' => 'Sarah Kim', 'role' => 'Founder', 'quote' => 'We launched with a complete online presence in record time. The team handled messaging, visuals, and performance without any friction.'],
        ['name' => 'Daniel Okoye', 'role' => 'Director', 'quote' => 'Strategy, design, and clarity all in one place. The final experience is refined, responsive, and built to scale.'],
    ];

    $testimonialHtml = '';
    foreach ($testimonials as $t) {
        $testimonialHtml .= "<div class='card'><p>“{$t['quote']}”</p><strong>{$t['name']}</strong><br><small>{$t['role']}</small></div>";
    }

    $trustBadges = titonova_render_trust_badges();
    $logoCloud = titonova_render_logo_cloud();
    $integrationChips = titonova_render_integration_chips();
    $timeline = titonova_render_timeline();
    $ctaBanner = titonova_render_cta_banner($data, '#contact');

    [$bg, $accent, $soft] = $data['palette'];
    $title = esc_html($data['business']);
    $tagline = esc_html($data['tagline']);
    $cta = esc_html($data['cta']);
    $about = esc_html($data['about']);
    $industry = esc_html(ucwords($data['industry']));
    $audience = esc_html($data['audience'] !== '' ? $data['audience'] : 'modern businesses');
    $industryKey = strtolower($data['industry']);
    $isNonprofit = $industryKey === 'nonprofit & community';
    $intent = $data['meta']['intent'] ?? '';
    $primaryCta = $isNonprofit ? 'Donate Now' : $cta;
    $primaryGoal = titonova_normalize_goal((string) ($data['meta']['primary_goal'] ?? $intent ?? 'leads'));
    $isCallGoal = $primaryGoal === 'calls';
    $callCtaVerbs = ['Call', 'Talk', 'Speak'];
    if ($isCallGoal) {
        $primaryCta = $callCtaVerbs[0];
    }
    $location = esc_html($data['meta']['location'] !== '' ? $data['meta']['location'] : 'City Center');
    $coverage = 'Worldwide (remote-first)';
    if ($data['meta']['location'] !== '' && $data['meta']['globalReach']) {
        $coverage = $location . ' and global markets';
    } elseif ($data['meta']['location'] !== '') {
        $coverage = $location;
    } elseif ($data['meta']['globalReach']) {
        $coverage = 'Global markets';
    }
    $aboutDetails = '';
    $aboutItems = [
        "Industry focus: {$industry}",
        "Primary audience: {$audience}",
        "Capabilities: strategy, experience design, development, and growth enablement",
        "Delivery model: agile sprints, weekly updates, and transparent reporting",
        "Coverage: {$coverage}",
    ];
    foreach ($aboutItems as $item) {
        $aboutDetails .= '<li>' . esc_html($item) . '</li>';
    }

    $email = esc_html(titonova_make_business_email($data['business']));
    $phone = esc_html((string) ($data['meta']['phone'] ?? '+1 (000) 000-0000'));
    $telRaw = preg_replace('/\D+/', '', (string) ($data['meta']['phone'] ?? '+10000000000'));
    $telHref = $telRaw !== '' ? 'tel:+' . ltrim($telRaw, '+') : '#contact';
    $heroPrimaryCta = $isCallGoal
        ? "<a class=\"cta block\" href=\"{$telHref}\">{$callCtaVerbs[0]}</a>"
        : "<a class=\"cta block\" href=\"#contact\">{$primaryCta}</a>";
    $heroSecondaryCta = $isCallGoal
        ? "<a class=\"ghost\" href=\"{$telHref}\">{$callCtaVerbs[1]}</a>"
        : "<a class=\"ghost\" href=\"#impact\">View Impact</a>";
    $navCtaText = $isCallGoal ? $callCtaVerbs[0] : ($isNonprofit ? 'Donate Now' : $primaryCta);
    $navCtaHref = $isCallGoal ? $telHref : '#contact';
    $footerGoalCta = $isCallGoal
        ? "<section class=\"section\"><a class=\"cta block\" href=\"{$telHref}\">{$callCtaVerbs[2]}</a></section>"
        : '';
    $responseTime = esc_html('Response time: within 24 hours');
    $officeHours = esc_html('Office hours: Mon–Fri, 9:00 AM–6:00 PM');
    $credibilitySection = titonova_render_credibility_section($data);
    $showDonationPlans = $isNonprofit || $intent === 'donation';
    $servicesLabel = $isNonprofit ? 'Programs & services' : 'Services';
    $servicesCta = $isNonprofit ? 'Get Involved' : $cta;
    $donationSection = '';
    if ($showDonationPlans) {
        $donationSection = <<<HTML
    <section class="section" id="pricing">
        <h2>Ways to support</h2>
        <div class="grid">
            <div class="card">
                <h4>Supporter</h4>
                <p class="meta">Monthly giving</p>
                <p><strong>$25</strong> / month</p>
                <ul class="list">
                    <li>Essential services</li>
                    <li>Community meals</li>
                    <li>Resource support</li>
                </ul>
            </div>
            <div class="card">
                <h4>Advocate</h4>
                <p class="meta">Program sponsor</p>
                <p><strong>$150</strong> / month</p>
                <ul class="list">
                    <li>Program expansion</li>
                    <li>Family support</li>
                    <li>Impact reporting</li>
                </ul>
            </div>
            <div class="card">
                <h4>Partner</h4>
                <p class="meta">Custom giving</p>
                <p><strong>Custom</strong> support</p>
                <ul class="list">
                    <li>Corporate matching</li>
                    <li>Program underwriting</li>
                    <li>Dedicated stewardship</li>
                </ul>
            </div>
        </div>
        <div class="card" style="margin-top:20px;">
            <p class="meta">Ready to support the mission?</p>
            <a class="cta block" href="#contact">Donate Now</a>
        </div>
    </section>
HTML;
    }

    $brandAssets = [
        [
            'title' => 'Team uniforms with brand identity',
            'term' => 'workwear',
            'desc' => 'Uniforms featuring the company name for a cohesive global presence.'
        ],
        [
            'title' => 'Headquarters and storefront signage',
            'term' => 'building',
            'desc' => 'Building signage that showcases the company name across locations.'
        ],
    ];

    $brandHtml = '';
    foreach ($brandAssets as $asset) {
        $termQuery = rawurlencode($asset['term']);
        $src = esc_url("https://images.unsplash.com/featured/?{$termQuery},{$industryQuery},global");
        $assetTitle = esc_html($asset['title']);
        $assetDesc = esc_html($asset['desc']);
        $brandHtml .= "<div class='brand-card'><div class='brand-image' style=\"background-image:url('{$src}')\"><span class='brand-mark'>{$title}</span></div><h4>{$assetTitle}</h4><p class='meta'>{$assetDesc}</p></div>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* { box-sizing: border-box; }
:root { --bg: {$bg}; --accent: {$accent}; --soft: {$soft}; }
html { scroll-behavior: smooth; }
body { margin:0; font-family: "Plus Jakarta Sans", Inter, "Segoe UI", sans-serif; background: radial-gradient(circle at 12% -8%, rgba(56,189,248,0.34), transparent 42%), radial-gradient(circle at 88% 8%, rgba(59,130,246,0.3), transparent 40%), radial-gradient(circle at 50% 100%, rgba(37,99,235,0.24), transparent 46%), #020617; color: #e2e8f0; }
.container { max-width: 1180px; margin: auto; padding: 28px 24px 70px; }
h1, h2, h3, h4 { margin: 0 0 12px; letter-spacing: -0.02em; line-height: 1.15; }
h2 { font-size: 30px; }
h3 { font-size: 22px; }
h4 { font-size: 18px; }
p { font-size: 16px; line-height: 1.65; margin: 0 0 12px; color: #cbd5e1; }
a { color: inherit; }
small { font-size: 12px; opacity: .78; color: #94a3b8; }
.nav { display:flex; align-items:center; justify-content: space-between; gap: 18px; margin-bottom: 30px; position: sticky; top: 12px; background: linear-gradient(120deg, rgba(15,23,42,0.9), rgba(30,41,59,0.76)); border: 1px solid rgba(125,211,252,0.34); border-radius: 999px; padding: 12px 16px; backdrop-filter: blur(12px); box-shadow: 0 20px 48px rgba(2,6,23,0.62), inset 0 1px 0 rgba(191,219,254,0.18); z-index: 12; }
.logo { font-weight: 700; font-size: 20px; letter-spacing: 0.5px; }
.nav-actions { display:flex; gap:12px; }
.hero-grid { min-height: 74vh; display: flex; align-items: flex-end; padding: clamp(32px, 5vw, 72px); border-radius: 28px; background-size: cover; background-position: center; color: #ffffff; background-image: linear-gradient(115deg, rgba(15,23,42,.8), rgba(15,23,42,.45)), url("{{heroImage}}"); box-shadow: 0 30px 72px rgba(15,23,42,0.5), 0 0 80px rgba(59,130,246,0.24); }
.hero-content { max-width: 720px; }
.hero-credit { display: inline-flex; margin-top: 18px; font-size: 12px; opacity: 0.78; letter-spacing: 0.02em; text-transform: uppercase; }
.hero-gradient { background: radial-gradient(circle at 20% 20%, rgba(56,189,248,0.42), transparent 42%), radial-gradient(circle at 80% 30%, rgba(59,130,246,0.34), transparent 45%), linear-gradient(135deg, #0b1734, #1e3a8a) !important; }
.hero-copy h1 { font-size: clamp(34px, 5vw, 56px); color: #ffffff; margin-bottom: 12px; }
.hero-copy p { opacity: .95; font-size: 18px; color: rgba(255,255,255,0.9); }
.pill { display: inline-block; background: rgba(30,64,175,0.34); color: #dbeafe; padding: 6px 14px; border-radius: 999px; font-weight: 600; margin-bottom: 16px; border: 1px solid rgba(147,197,253,0.35); }
.hero-actions { display:flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
.cta { padding: 14px 28px; border-radius: 999px; border: 1px solid rgba(191,219,254,0.7); background: linear-gradient(135deg, #2563eb, #1d4ed8 45%, #1e40af); color: #f8fbff; font-weight: 700; cursor: pointer; text-decoration: none; box-shadow: 0 22px 38px rgba(30,64,175,0.55), 0 0 36px rgba(59,130,246,0.45); }
.cta.block { display: inline-block; text-align: center; text-decoration: none; }
.cta.small { padding: 10px 18px; font-size: 14px; }
.cta.ghost { padding: 14px 24px; border-radius: 999px; border: 1px solid rgba(147,197,253,0.55); background: linear-gradient(120deg, rgba(30,41,59,0.86), rgba(15,23,42,0.72)); color: #dbeafe; font-weight: 600; cursor: pointer; box-shadow: inset 0 1px 0 rgba(191,219,254,0.18); }
.ghost { padding: 14px 24px; border-radius: 999px; border: 1px solid rgba(147,197,253,0.55); background: linear-gradient(120deg, rgba(30,41,59,0.86), rgba(15,23,42,0.72)); color: #dbeafe; font-weight: 600; cursor: pointer; text-decoration: none; box-shadow: inset 0 1px 0 rgba(191,219,254,0.18); }
.trust { display:flex; gap: 12px; margin-top: 18px; flex-wrap: wrap; font-size: 13px; }
.trust span { background: linear-gradient(120deg, rgba(56,189,248,0.2), rgba(15,23,42,0.62)); border: 1px solid rgba(191,219,254,0.34); border-radius: 999px; padding: 6px 12px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.2); }
.section { margin-bottom: 54px; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; }
.service-image { width: 100%; height: 168px; border-radius: 14px; background-size: cover; background-position: center; margin-bottom: 14px; }
.about-section { background-image: url("{{aboutBackgroundImage}}"); background-size: cover; background-position: center; border-radius: 20px; padding: 32px; border: 1px solid rgba(96,165,250,0.28); }
.about-section .card { background: rgba(15,23,42,0.8); }
.card { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); padding: 24px; border-radius: 16px; border: 1px solid rgba(125,211,252,0.28); box-shadow: 0 22px 42px rgba(2,6,23,0.58), inset 0 1px 0 rgba(191,219,254,0.12); }
.card h4 { margin: 0 0 10px; }
.card p { margin: 0 0 12px; }
.meta { opacity: .94; color: #94a3b8; font-size: 14px; margin: 0; }
.list { list-style: none; padding: 0; margin: 0; }
.list li { padding: 10px 0; border-bottom: 1px solid rgba(148,163,184,0.22); color: #cbd5e1; }
.steps { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:16px; }
.step { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); border: 1px solid rgba(125,211,252,0.26); border-radius: 16px; padding: 20px; box-shadow: 0 18px 34px rgba(2,6,23,0.52), inset 0 1px 0 rgba(191,219,254,0.1); }
.stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:18px; }
.stat { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); border: 1px solid rgba(125,211,252,0.26); border-radius: 16px; padding: 20px; text-align:center; box-shadow: 0 18px 34px rgba(2,6,23,0.52), inset 0 1px 0 rgba(191,219,254,0.1); }
.stat small { display:block; opacity:.7; margin-top: 6px; }
.gallery,
.gallery-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.gallery img,
.gallery-grid img { width: 100%; height: 200px; border-radius: 14px; object-fit: cover; box-shadow: 0 10px 24px rgba(15,23,42,.1); }
.logo-cloud { display: flex; flex-wrap: wrap; gap: 10px; }
.logo-pill { border: 1px solid rgba(147,197,253,0.3); padding: 8px 14px; border-radius: 999px; font-weight: 600; font-size: 13px; opacity: .95; background: rgba(15,23,42,0.74); color: #dbeafe; }
.chips { display:flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
.chip { background: rgba(15,23,42,0.74); border: 1px solid rgba(147,197,253,0.28); padding: 8px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #dbeafe; }
.timeline { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.timeline-step { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); border: 1px solid rgba(125,211,252,0.26); border-radius: 16px; padding: 18px; box-shadow: 0 18px 34px rgba(2,6,23,0.52), inset 0 1px 0 rgba(191,219,254,0.1); }
.accordion-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
.accordion { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); border: 1px solid rgba(125,211,252,0.26); border-radius: 16px; padding: 16px; box-shadow: 0 18px 34px rgba(2,6,23,0.52), inset 0 1px 0 rgba(191,219,254,0.1); }
.accordion summary { cursor: pointer; font-weight: 600; margin-bottom: 8px; }
.accordion p { margin-top: 8px; }
.form { display:grid; gap: 10px; }
.input { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(147,197,253,0.4); background: linear-gradient(180deg, rgba(15,23,42,0.82), rgba(2,6,23,0.58)); color: #e2e8f0; box-shadow: inset 0 1px 0 rgba(191,219,254,0.1); }
.textarea { resize: vertical; }
.cta-banner { display:flex; align-items:center; justify-content: space-between; gap: 18px; background: linear-gradient(135deg, rgba(56,189,248,0.24), rgba(37,99,235,0.34) 45%, rgba(15,23,42,0.92)); border: 1px solid rgba(191,219,254,0.44); padding: 24px; border-radius: 20px; box-shadow: 0 24px 48px rgba(2,6,23,0.62), 0 0 60px rgba(59,130,246,0.24); }
.brand-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
.brand-card { background: linear-gradient(165deg, rgba(30,41,59,0.9), rgba(15,23,42,0.8)); padding: 18px; border-radius: 16px; border: 1px solid rgba(125,211,252,0.26); box-shadow: 0 22px 42px rgba(2,6,23,0.58), inset 0 1px 0 rgba(191,219,254,0.12); }
.brand-image { position: relative; height: 240px; border-radius: 16px; background-size: cover; background-position: center; overflow: hidden; display: flex; align-items: flex-end; padding: 18px; }
.brand-image::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0.0) 30%, rgba(0,0,0,0.6) 100%); }
.brand-mark { position: relative; z-index: 1; display: inline-block; background: rgba(0,0,0,0.55); color: #fff; padding: 8px 16px; border-radius: 999px; font-weight: 700; letter-spacing: 0.5px; }
footer { text-align:center; color: #93c5fd; margin-top: 70px; }
@media (max-width: 768px) {
    .nav { flex-direction: column; gap: 16px; }
    .cta, .ghost { width: 100%; max-width: 320px; }
    .hero-grid { min-height: auto; align-items: flex-start; }
    .hero-copy h1 { font-size: 36px; }
    .cta-banner { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="container">
    <nav class="nav">
        <div class="logo">{$title}</div>
        <div class="nav-actions">
            <a class="cta block" href="{$navCtaHref}">{$navCtaText}</a>
        </div>
    </nav>

    <section class="hero-grid {{heroClass}}">
        <div class="hero-copy hero-content">
            <span class="pill">{$industry}</span>
            <h1>{$tagline}</h1>
            <p>{$about}</p>
            <div class="hero-actions">
                {$heroPrimaryCta}
                {$heroSecondaryCta}
            </div>
            <div class="trust">
                <span>Trusted delivery</span>
                <span>Clear outcomes</span>
                <span>Modern experience</span>
            </div>
            <span class="hero-credit">Preview image</span>
        </div>
    </section>

    <section class="section" id="impact">
        <h2>Highlights</h2>
        <div class="grid">
            <div class="card"><h4>Clarity & strategy</h4><p class="meta">Clear positioning and messaging aligned to outcomes.</p></div>
            <div class="card"><h4>Experience design</h4><p class="meta">Calm, accessible journeys that guide decisions.</p></div>
            <div class="card"><h4>Measured outcomes</h4><p class="meta">Performance tracking and continuous improvement.</p></div>
        </div>
        {$impactGallery}
        <a class="cta block small" href="#contact">{$primaryCta}</a>
    </section>

    <section class="section">
        <h2>Trusted by modern teams</h2>
        <div class="logo-cloud">{$logoCloud}</div>
    </section>
    
    <section class="section">
        <h2>Trust badges</h2>
        <div class="logo-cloud">{$trustBadges}</div>
    </section>

    {$credibilitySection}

    <section class="section">
        <h2>Problem → Solution → Outcome</h2>
        <div class="steps">
            <div class="step"><h4>Problem</h4><p class="meta">Communities face barriers to essential resources and support.</p></div>
            <div class="step"><h4>Solution</h4><p class="meta">Compassionate programs with trusted partners and clear pathways.</p></div>
            <div class="step"><h4>Outcome</h4><p class="meta">People feel supported and outcomes are measured transparently.</p></div>
        </div>
    </section>

    <section class="section">
        <h2>About</h2>
        <div class="card">
            <p>{$about}</p>
            <ul class="list">{$aboutDetails}</ul>
            <a class="cta block small" href="#contact">Get Involved</a>
        </div>
    </section>

    <section class="section" id="services">
        <h2>{$servicesLabel}</h2>
        <div class="grid">{$services}</div>
        <a class="cta block small" href="#contact">{$servicesCta}</a>
    </section>

    {$industrySections}

    <section class="section">
        <h2>Modern capabilities</h2>
        <div class="card">
            <p class="meta">Composable tools and integrations for modern websites.</p>
            <div class="chips">{$integrationChips}</div>
        </div>
    </section>

    <section class="section">
        <h2>Outcomes</h2>
        <div class="grid">{$features}</div>
        <a class="cta block small" href="#contact">{$primaryCta}</a>
    </section>

    <section class="section">
        <h2>Delivery timeline</h2>
        <div class="timeline">{$timeline}</div>
    </section>

    <section class="section">
        <h2>Performance Highlights</h2>
        <div class="stats">
            <div class="stat"><h3>98%</h3><p>Client satisfaction</p><small>Consistent delivery across multi-phase projects.</small></div>
            <div class="stat"><h3>24/7</h3><p>Global availability</p><small>Support coverage for distributed teams and time zones.</small></div>
            <div class="stat"><h3>7 Days</h3><p>Average launch time</p><small>From kickoff to live deployment with QA and analytics.</small></div>
        </div>
        <a class="cta block small" href="#contact">Support Our Mission</a>
    </section>

    <section class="section">
        <h2>Get Started</h2>
        <div class="card">
            <p class="meta">Tell us how you would like to support the mission.</p>
            <a class="cta block" href="#contact">Get Involved</a>
        </div>
    </section>

    <section class="section" id="reviews">
        <h2>Reviews</h2>
        <div class="grid">{$testimonialHtml}</div>
        <a class="cta block small" href="#contact">Support Our Mission</a>
    </section>

    {$donationSection}

    <section class="section" id="faq">
        <h2>FAQs</h2>
        <div class="accordion-grid">{$faqHtml}</div>
        <a class="cta block small" href="#contact">Get Involved</a>
    </section>

    <section class="section">{$ctaBanner}</section>

    <section class="section">
        <h2>Newsletter</h2>
        <div class="card">
            <form class="form">
                <label>Email address</label>
                <input class="input" type="email" placeholder="you@company.com" />
                <button class="cta" type="button">Join updates</button>
                <small>Monthly insights on design, performance, and growth.</small>
            </form>
        </div>
    </section>

    {$gallerySection}

    <section class="section" id="contact">
        <h2>Get involved</h2>
        <div class="card">
            <p>Email: {$email}</p>
            <p>Phone: {$phone}</p>
            <p>{$officeHours}</p>
            <p>{$responseTime}</p>
            <p>Tell us how you would like to support or partner with the mission.</p>
            <form class="form" style="margin-top:16px;">
                <label>Full name</label>
                <input class="input" type="text" placeholder="Jane Doe" />
                <label>Email</label>
                <input class="input" type="email" placeholder="you@email.com" />
                <label>How would you like to help?</label>
                <input class="input" type="text" placeholder="Donate / Volunteer / Partner" />
                <label>Message</label>
                <textarea class="input textarea" rows="4" placeholder="Share how you'd like to get involved." ></textarea>
                <button class="cta" type="button">Get Involved</button>
            </form>
            <a class="cta block small" href="#top">Back to Top</a>
        </div>
    </section>

        {$footerGoalCta}

    <footer>Generated by TitoNova Cloud Engine</footer>
</div>
</body>
</html>
HTML;
}

function titonova_create_stripe_checkout(WP_REST_Request $request): WP_REST_Response
{
    $secret = trim((string) get_option('titonova_stripe_secret_key', ''));
    if ($secret === '') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Stripe secret key is not configured.'
        ], 400);
    }

    $priceId = sanitize_text_field((string) $request->get_param('price_id'));
    $amount = $request->get_param('amount');
    $currency = sanitize_text_field((string) $request->get_param('currency'));
    $description = sanitize_text_field((string) $request->get_param('description'));
    $quantity = (int) $request->get_param('quantity');
    $successUrl = esc_url_raw((string) $request->get_param('success_url'));
    $cancelUrl = esc_url_raw((string) $request->get_param('cancel_url'));
    $customerEmail = sanitize_email((string) $request->get_param('customer_email'));
    $metadata = $request->get_param('metadata');

    if ($currency === '') {
        $currency = (string) get_option('titonova_stripe_currency', 'usd');
    }

    if ($successUrl === '') {
        $successUrl = (string) get_option('titonova_stripe_success_url', '');
    }
    if ($cancelUrl === '') {
        $cancelUrl = (string) get_option('titonova_stripe_cancel_url', '');
    }

    if ($successUrl === '' || $cancelUrl === '') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Success and cancel URLs are required.'
        ], 400);
    }

    if ($quantity < 1) {
        $quantity = 1;
    }

    if ($priceId === '') {
        if ($amount === null || $amount === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Amount or price_id is required.'
            ], 400);
        }

        $amountValue = (float) $amount;
        $unitAmount = (int) round($amountValue * 100);
        if ($unitAmount < 1) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Amount must be greater than zero.'
            ], 400);
        }
    }

    $body = [
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'payment_method_types[0]' => 'card',
    ];

    if ($priceId !== '') {
        $body['line_items[0][price]'] = $priceId;
        $body['line_items[0][quantity]'] = $quantity;
    } else {
        $productName = $description !== '' ? $description : 'TitoNova Purchase';
        $body['line_items[0][price_data][currency]'] = strtolower($currency);
        $body['line_items[0][price_data][unit_amount]'] = $unitAmount;
        $body['line_items[0][price_data][product_data][name]'] = $productName;
        $body['line_items[0][quantity]'] = $quantity;
    }

    if ($customerEmail !== '') {
        $body['customer_email'] = $customerEmail;
    }

    if (is_array($metadata)) {
        foreach ($metadata as $key => $value) {
            $metaKey = sanitize_key((string) $key);
            if ($metaKey === '') {
                continue;
            }
            $body["metadata[{$metaKey}]"] = sanitize_text_field((string) $value);
        }
    }

    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret,
        ],
        'body' => $body,
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $response->get_error_message(),
        ], 502);
    }

    $statusCode = wp_remote_retrieve_response_code($response);
    $payload = json_decode(wp_remote_retrieve_body($response), true);

    if ($statusCode < 200 || $statusCode >= 300 || empty($payload['id'])) {
        $errorMessage = $payload['error']['message'] ?? 'Stripe Checkout session failed.';
        return new WP_REST_Response([
            'success' => false,
            'message' => $errorMessage,
            'stripe' => $payload,
        ], 400);
    }

    return new WP_REST_Response([
        'success' => true,
        'session_id' => $payload['id'],
        'url' => $payload['url'] ?? null,
    ], 200);
}
