@php
    /** The graph data. The controller passes it. Use an empty graph when the value is missing. */
    $graph = isset($graph) && is_array($graph) ? $graph : ['nodes' => [], 'relationships' => []];
    $fileCount = count($graph['nodes'] ?? []);
    $linkCount = count($graph['relationships'] ?? []);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Workspace · Documentation · Feasibility agent</title>
    <script>
        /* Apply the stored or system theme before the first paint. */
        (function () {
            var theme = null;

            try {
                theme = window.localStorage.getItem('ba-panel-theme');
            } catch (error) {
                theme = null;
            }

            if (theme !== 'light' && theme !== 'dark') {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            document.documentElement.dataset.theme = theme;
        })();
    </script>
    <style>
        :root {
            color-scheme: light;
            --color-text: #111113;
            --color-muted: #5F5F68;
            --color-body: #2B2B30;
            --color-surface: #FAFAFA;
            --color-background: #FFFFFF;
            --color-accent: #2563EB;
            --color-accent-soft: #EEF3FF;
            --color-on-accent: #FFFFFF;
            --color-border: #E7E7EA;
            --color-border-strong: #D4D4D8;
            --color-grid-dot: #DEDEE3;
            --color-inverse: #111113;
            --color-on-inverse: #FFFFFF;
            --color-scrollbar: #C4C4CA;
            --font-sans: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            --header-height: 60px;
            --agent-width: 400px;
            --border: 1px solid var(--color-border);
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --color-text: #EDEDF0;
            --color-muted: #9A9AA3;
            --color-body: #D4D4DA;
            --color-surface: #111113;
            --color-background: #18181B;
            --color-accent: #3B82F6;
            --color-accent-soft: rgba(59, 130, 246, 0.14);
            --color-on-accent: #FFFFFF;
            --color-border: #2A2A30;
            --color-border-strong: #3A3A42;
            --color-grid-dot: #2C2C33;
            --color-inverse: #EDEDF0;
            --color-on-inverse: #111113;
            --color-scrollbar: #4A4A53;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--color-surface);
            color: var(--color-text);
            font-family: var(--font-sans);
            font-size: 14px;
            font-weight: 400;
            line-height: normal;
            -webkit-font-smoothing: antialiased;
        }

        h1,
        h2,
        p,
        ul {
            margin: 0;
        }

        ul {
            padding: 0;
            list-style: none;
        }

        button,
        input,
        textarea {
            font: inherit;
            color: inherit;
        }

        button {
            cursor: pointer;
        }

        button:disabled {
            cursor: not-allowed;
        }

        :focus-visible {
            outline: 2px solid var(--color-accent);
            outline-offset: 2px;
        }

        svg {
            flex-shrink: 0;
        }

        [hidden] {
            display: none !important;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Application shell */

        .app {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .app-header {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            height: var(--header-height);
            padding: 0 20px;
            background-color: var(--color-background);
            border-bottom: var(--border);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            color: var(--color-text);
        }

        .brand-mark-accent {
            fill: var(--color-accent);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 1px;
            min-width: 0;
        }

        .brand-title {
            font-weight: 600;
            letter-spacing: -0.14px;
        }

        .brand-subtitle {
            color: var(--color-muted);
            font-size: 12px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: var(--border);
            background-color: var(--color-background);
        }

        .theme-toggle {
            height: 40px;
            padding: 0 14px;
            border-radius: 10px;
            font-size: 13px;
        }

        .workspace {
            display: flex;
            flex: 1 1 auto;
            flex-wrap: wrap;
            min-height: 0;
        }

        /* Documentation section */

        .docs {
            display: flex;
            flex: 999 1 640px;
            flex-direction: column;
            min-width: 0;
            min-height: 640px;
            border-right: var(--border);
        }

        .section-toolbar {
            display: flex;
            flex-shrink: 0;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: var(--border);
        }

        .section-heading {
            display: flex;
            align-items: baseline;
            gap: 10px;
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.15px;
        }

        .section-meta {
            color: var(--color-muted);
            font-size: 12px;
        }

        .search {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 240px;
            max-width: 100%;
            height: 36px;
            padding: 0 12px;
            color: var(--color-muted);
            background-color: var(--color-background);
            border: var(--border);
            border-radius: 10px;
        }

        .search:focus-within {
            outline: 2px solid var(--color-accent);
            outline-offset: 2px;
        }

        .search-input {
            flex: 1 1 0%;
            min-width: 0;
            padding: 1px 2px;
            color: var(--color-text);
            font-size: 13px;
            background: transparent;
            border: 0;
            outline: none;
        }

        .search-input::placeholder {
            color: var(--color-muted);
        }

        .search-box {
            position: relative;
            max-width: 100%;
        }

        .search-results {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            z-index: 10;
            width: 320px;
            max-width: calc(100vw - 40px);
            max-height: 320px;
            overflow: auto;
            padding: 4px;
            background-color: var(--color-background);
            border: 1px solid var(--color-border-strong);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .search-result {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            width: 100%;
            padding: 8px 10px;
            text-align: left;
            background: transparent;
            border: 0;
            border-radius: 7px;
        }

        .search-result:hover,
        .search-result:focus-visible {
            background-color: var(--color-accent-soft);
        }

        .search-result-name {
            color: var(--color-text);
            font-size: 13px;
            font-weight: 500;
        }

        .search-result-path {
            max-width: 100%;
            overflow: hidden;
            color: var(--color-muted);
            font-family: var(--font-mono);
            font-size: 11.5px;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .search-empty {
            padding: 8px 10px;
            color: var(--color-muted);
            font-size: 13px;
        }

        .docs-body {
            position: relative;
            display: flex;
            flex: 1 1 auto;
            flex-wrap: wrap;
            min-height: 0;
            overflow: auto;
        }

        .graph {
            position: relative;
            flex: 1 1 360px;
            min-height: 440px;
            overflow: hidden;
            background-color: var(--color-surface);
            background-image: radial-gradient(var(--color-grid-dot) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        .graph-canvas {
            position: absolute;
            inset: 0;
            display: block;
            width: 100%;
            height: 100%;
            touch-action: none;
            user-select: none;
        }

        .graph-edge {
            stroke: var(--color-border-strong);
            stroke-width: 1.2;
        }

        .graph-edge.is-active {
            stroke: var(--color-accent);
            stroke-width: 1.6;
        }

        .graph-arrow path {
            fill: var(--color-border-strong);
        }

        .graph-arrow-active path {
            fill: var(--color-accent);
        }

        .graph-edge.is-hidden,
        .graph-node.is-hidden {
            display: none;
        }

        /* The button is a sibling of the graph, so the graph container stays empty. It is placed over the top left corner of the graph. */
        .graph-show-all {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 1;
            min-height: 34px;
            padding: 0 12px;
            font-size: 13px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
        }

        .graph-show-all:hover {
            border-color: var(--color-accent);
            color: var(--color-accent);
        }

        .graph-node {
            cursor: pointer;
            outline: none;
        }

        .graph-node.is-dragging {
            cursor: grabbing;
        }

        .graph-node circle {
            fill: var(--color-background);
            stroke: var(--color-muted);
            stroke-width: 1.5;
        }

        .graph-node:hover circle {
            stroke: var(--color-accent);
        }

        .graph-node:focus-visible circle {
            stroke: var(--color-accent);
            stroke-width: 3;
        }

        .graph-node.is-selected circle {
            fill: var(--color-accent);
            stroke: var(--color-accent);
        }

        .graph-node text {
            fill: var(--color-text);
            font-family: var(--font-mono);
            font-size: 11.5px;
            paint-order: stroke;
            stroke: var(--color-surface);
            stroke-width: 3px;
            stroke-linejoin: round;
            pointer-events: none;
        }

        .graph-node.is-selected text {
            font-weight: 600;
        }

        .graph-empty {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--color-muted);
            text-align: center;
        }

        /* Document reader */

        /*
         * The reader scrolls its content. It does not grow with the document.
         * Size containment removes the content height from the layout. The flex line then sets the reader height.
         */
        .reader {
            flex: 1 1 400px;
            min-width: 0;
            min-height: 440px;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            contain: size;
            scrollbar-color: var(--color-scrollbar) transparent;
            scrollbar-gutter: stable;
            scrollbar-width: thin;
            background-color: var(--color-background);
            border-left: var(--border);
        }

        .reader-bar {
            position: sticky;
            top: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px 10px 24px;
            background-color: var(--color-background);
            border-bottom: var(--border);
        }

        .reader-path {
            overflow: hidden;
            color: var(--color-muted);
            font-family: var(--font-mono);
            font-size: 12px;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .icon-button {
            display: inline-flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            padding: 1px 6px;
            background: transparent;
            border: 0;
        }

        .reader-close {
            width: 36px;
            height: 36px;
            color: var(--color-muted);
            border-radius: 8px;
        }

        .reader-content {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: 680px;
            padding: 24px 28px 32px;
        }

        .reader-empty {
            color: var(--color-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Rendered Markdown */

        .markdown {
            color: var(--color-body);
            font-size: 14.5px;
            line-height: 1.65;
            overflow-wrap: anywhere;
            min-width: 0;
        }

        .markdown > :first-child {
            margin-top: 0;
        }

        .markdown > :last-child {
            margin-bottom: 0;
        }

        .markdown h1,
        .markdown h2,
        .markdown h3 {
            color: var(--color-text);
            font-weight: 600;
        }

        .markdown h1 {
            margin: 0 0 14px;
            font-size: 24px;
            line-height: 30px;
            letter-spacing: -0.48px;
        }

        .markdown h2 {
            margin: 24px 0 10px;
            font-size: 18px;
            line-height: 1.35;
            letter-spacing: -0.2px;
        }

        .markdown h3 {
            margin: 20px 0 8px;
            font-size: 16px;
            line-height: 1.35;
            letter-spacing: -0.16px;
        }

        .markdown p,
        .markdown ul,
        .markdown ol,
        .markdown pre,
        .markdown blockquote,
        .markdown table {
            margin: 0 0 14px;
        }

        .markdown ul,
        .markdown ol {
            padding-left: 22px;
        }

        .markdown ul {
            list-style: disc;
        }

        .markdown ol {
            list-style: decimal;
        }

        .markdown li + li {
            margin-top: 4px;
        }

        .markdown a {
            color: var(--color-accent);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .markdown code {
            padding: 1px 5px;
            font-family: var(--font-mono);
            font-size: 12.5px;
            background-color: var(--color-surface);
            border: var(--border);
            border-radius: 5px;
        }

        /* Long lines in a code block or a table scroll inside the block. The reader does not scroll horizontally. */
        .markdown pre {
            max-width: 100%;
            padding: 12px 14px;
            overflow-x: auto;
            scrollbar-color: var(--color-scrollbar) transparent;
            scrollbar-width: thin;
            background-color: var(--color-surface);
            border: var(--border);
            border-radius: 8px;
        }

        .markdown pre code {
            padding: 0;
            background: transparent;
            border: 0;
        }

        .markdown blockquote {
            padding: 2px 0 2px 14px;
            color: var(--color-muted);
            border-left: 3px solid var(--color-border-strong);
        }

        .markdown table {
            display: block;
            max-width: 100%;
            overflow-x: auto;
            scrollbar-color: var(--color-scrollbar) transparent;
            scrollbar-width: thin;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        .markdown th,
        .markdown td {
            padding: 6px 10px;
            text-align: left;
            border: var(--border);
        }

        .markdown th {
            color: var(--color-text);
            font-weight: 600;
            background-color: var(--color-surface);
        }

        .markdown img {
            max-width: 100%;
            height: auto;
        }

        .markdown hr {
            margin: 20px 0;
            border: 0;
            border-top: var(--border);
        }

        .links {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-top: 18px;
            border-top: var(--border);
        }

        .links-label {
            color: var(--color-muted);
            font-size: 12px;
            font-weight: 500;
        }

        .links-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .file-chip {
            min-height: 34px;
            padding: 0 12px;
            font-family: var(--font-mono);
            font-size: 12px;
            background-color: var(--color-surface);
            border-radius: 8px;
        }

        /* Agent panel */

        .agent {
            display: flex;
            flex: 1 1 var(--agent-width);
            flex-direction: column;
            min-width: 0;
            min-height: 640px;
            background-color: var(--color-background);
        }

        .agent-header {
            padding: 12px 20px;
            gap: 10px;
        }

        .agent-heading {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .agent-actions {
            display: flex;
            gap: 6px;
        }

        .export-button {
            gap: 6px;
            height: 36px;
            padding: 0 12px;
            font-size: 12.5px;
            font-weight: 500;
            border-radius: 9px;
        }

        .export-button:disabled {
            opacity: 0.4;
        }

        .export-button-primary {
            color: var(--color-on-inverse);
            background-color: var(--color-inverse);
            border-color: var(--color-inverse);
        }

        .conversation {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            justify-content: flex-end;
            gap: 16px;
            min-height: 0;
            padding: 20px;
            overflow: auto;
        }

        .message {
            align-self: flex-start;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 90%;
            line-height: 1.6;
        }

        .suggestions {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .suggestions-label {
            color: var(--color-muted);
            font-size: 12px;
        }

        .suggestion {
            min-height: 36px;
            padding: 6px 12px;
            font-size: 13px;
            text-align: left;
            border-radius: 10px;
        }

        .composer {
            flex-shrink: 0;
            padding: 12px 16px 16px;
        }

        .composer-box {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 10px;
            background-color: var(--color-surface);
            border: 1px solid var(--color-border-strong);
            border-radius: 14px;
        }

        .composer-box:focus-within {
            border-color: var(--color-accent);
        }

        .context-chip {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 26px;
            padding: 0 4px 0 10px;
            color: var(--color-accent);
            font-size: 12px;
            background-color: var(--color-accent-soft);
            border-radius: 7px;
        }

        .context-remove {
            width: 22px;
            height: 22px;
            color: var(--color-accent);
            border-radius: 5px;
        }

        .composer-input {
            display: block;
            width: 100%;
            min-height: 71px;
            padding: 4px 6px;
            line-height: 21px;
            background: transparent;
            border: 0;
            outline: none;
            resize: none;
        }

        .composer-input::placeholder {
            color: var(--color-muted);
        }

        .composer-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .composer-hint {
            padding-left: 6px;
            color: var(--color-muted);
            font-size: 11.5px;
        }

        .send-button {
            width: 40px;
            height: 40px;
            color: var(--color-on-accent);
            background-color: var(--color-accent);
            border: 1px solid var(--color-accent);
            border-radius: 10px;
        }

        .send-button:disabled {
            opacity: 0.4;
        }

        /* Fit the layout to the viewport on wide screens. Each column scrolls on its own. */
        @media (min-width: 1041px) {
            .app {
                height: 100vh;
            }

            /* Keep one flex line. The line then gets the height of the container, not the height of the content. */
            .workspace,
            .docs-body {
                flex-wrap: nowrap;
            }

            .docs,
            .agent {
                min-height: 0;
            }
        }

        /* On narrow screens the page grows. Give the reader a bounded height, so the document scrolls inside it. */
        @media (max-width: 1040px) {
            .reader {
                min-height: min(70vh, 640px);
            }
        }
    </style>
</head>
<body>
<div class="app">
    <header class="app-header">
        <div class="brand">
            <svg class="brand-mark" xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true" focusable="false">
                <rect x="1" y="1" width="26" height="26" rx="8" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="10" cy="11" r="2.5" fill="currentColor"/>
                <circle class="brand-mark-accent" cx="18" cy="17" r="2.5"/>
                <path d="M11.8 12.6 16.2 15.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <div class="brand-text">
                <span class="brand-title">Workspace</span>
                <span class="brand-subtitle">Documentation · Feasibility agent</span>
            </div>
        </div>

        <button type="button" class="button theme-toggle" aria-label="Switch to dark theme" data-theme-toggle>
            <svg data-theme-icon="moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
            </svg>
            <svg data-theme-icon="sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" hidden>
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
            </svg>
            <span data-theme-label>Dark</span>
        </button>
    </header>

    <main class="workspace">
        <section class="docs" aria-labelledby="docs-title">
            <div class="section-toolbar">
                <div class="section-heading">
                    <h2 id="docs-title" class="section-title">Documentation</h2>
                    <span class="section-meta"><span data-file-count>{{ $fileCount }}</span> files · <span data-link-count>{{ $linkCount }}</span> links</span>
                </div>

                <div class="search-box" data-search>
                    <label class="search" for="docs-filter">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="m20 20-3.5-3.5"/>
                        </svg>
                        <span class="sr-only">Filter files</span>
                        <input id="docs-filter" class="search-input" type="search" name="filter" placeholder="Filter files" autocomplete="off" aria-controls="docs-filter-results" aria-expanded="false" data-search-input>
                    </label>

                    <div id="docs-filter-results" class="search-results" role="region" aria-label="Matching files" aria-live="polite" data-search-results hidden></div>
                </div>
            </div>

            <div class="docs-body">
                <div class="graph" role="region" aria-label="Documentation graph"></div>
                <button type="button" class="button graph-show-all" data-graph-show-all hidden>Show all files</button>

                <article class="reader" aria-labelledby="reader-title" aria-live="polite" data-reader>
                    <div class="reader-bar">
                        <span id="reader-title" class="reader-path" data-reader-path>No file open</span>
                        <button type="button" class="icon-button reader-close" aria-label="Close file" data-reader-close>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
                                <path d="M6 6l12 12M18 6 6 18"/>
                            </svg>
                        </button>
                    </div>

                    <div class="reader-content" data-reader-content>
                        <p class="reader-empty">Select a node in the graph to read the document.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="agent" aria-labelledby="agent-title">
            <div class="section-toolbar agent-header">
                <div class="agent-heading">
                    <h2 id="agent-title" class="section-title">Feasibility agent</h2>
                    <span class="section-meta">Assesses a brief against the documentation</span>
                </div>

                <div class="agent-actions">
                    <button type="button" class="button export-button" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>
                        </svg>
                        <span><span class="sr-only">Export as </span>.md</span>
                    </button>
                    <button type="button" class="button export-button export-button-primary" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>
                        </svg>
                        <span><span class="sr-only">Export as </span>.pdf</span>
                    </button>
                </div>
            </div>

            <div class="conversation" aria-live="polite">
                <div class="message">
                    <p>Hi — describe what you’d like to build and I’ll assess its feasibility against the project documentation. Open a file on the left to add it as context.</p>
                </div>

                <div class="suggestions">
                    <span class="suggestions-label">Try</span>
                    <button type="button" class="button suggestion">Assess the feasibility of a client portal with SSO</button>
                    <button type="button" class="button suggestion">What information do you need from me to start?</button>
                    <button type="button" class="button suggestion">Summarize risks based on the architecture docs</button>
                </div>
            </div>

            <div class="composer">
                <div class="composer-box">
                    <span class="context-chip" data-context hidden>
                        <span>Context: <span data-context-name></span></span>
                        <button type="button" class="icon-button context-remove" aria-label="Remove the document from context" data-context-remove>
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
                                <path d="M6 6l12 12M18 6 6 18"/>
                            </svg>
                        </button>
                    </span>

                    <label class="sr-only" for="agent-message">Message the feasibility agent</label>
                    <textarea id="agent-message" class="composer-input" name="message" rows="3" placeholder="Describe what you want to build…"></textarea>

                    <div class="composer-footer">
                        <span class="composer-hint">Enter to send · Shift+Enter for a new line</span>
                        <button type="button" class="button icon-button send-button" aria-label="Send message" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M12 19V5M5 12l7-7 7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
    /* Sync the theme toggle with the current theme. Toggle and store the theme on click. */
    (function () {
        var storageKey = 'ba-panel-theme';
        var root = document.documentElement;
        var toggle = document.querySelector('[data-theme-toggle]');

        if (!toggle) {
            return;
        }

        var label = toggle.querySelector('[data-theme-label]');
        var moon = toggle.querySelector('[data-theme-icon="moon"]');
        var sun = toggle.querySelector('[data-theme-icon="sun"]');

        function render(theme) {
            var isDark = theme === 'dark';

            root.dataset.theme = theme;
            toggle.setAttribute('aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme');

            if (label) {
                label.textContent = isDark ? 'Light' : 'Dark';
            }

            if (moon) {
                moon.toggleAttribute('hidden', isDark);
            }

            if (sun) {
                sun.toggleAttribute('hidden', !isDark);
            }
        }

        function store(theme) {
            try {
                window.localStorage.setItem(storageKey, theme);
            } catch (error) {
                /* Storage is not available. Keep the theme for this page only. */
            }
        }

        render(root.dataset.theme === 'dark' ? 'dark' : 'light');

        toggle.addEventListener('click', function () {
            var next = root.dataset.theme === 'dark' ? 'light' : 'dark';

            render(next);
            store(next);
        });
    })();
</script>
<script>
    /* Render the documentation graph, the document reader, and the file filter. */
    (function () {
        var data = {{ \Illuminate\Support\Js::from($graph) }};
        /*
         * The URLs are relative. A proxy can serve the panel over HTTPS while the app sees HTTP.
         * An absolute HTTP URL is then blocked as mixed content.
         */
        var documentUrlTemplate = {{ \Illuminate\Support\Js::from(route('ba-panel.documents', ['node' => '000000000000'], false)) }};
        var searchUrl = {{ \Illuminate\Support\Js::from(route('ba-panel.search', [], false)) }};

        var SVG_NS = 'http://www.w3.org/2000/svg';
        var NODE_RADIUS = 7;
        /* The arrow tip stops at the outer edge of the circle stroke. */
        var ARROW_GAP = NODE_RADIUS + 1;
        var ARROW_SIZE = 8;
        var ARROW_MARKER = 'ba-panel-arrow';
        var ARROW_MARKER_ACTIVE = 'ba-panel-arrow-active';
        var LABEL_OFFSET = 12;
        var EDGE_PADDING = 28;
        var REPULSION = 1400;
        var MIN_DISTANCE_SQUARED = 100;
        var SPRING_LENGTH = 110;
        var SPRING_STRENGTH = 0.02;
        var CENTER_STRENGTH = 0.004;
        var DAMPING = 0.86;
        var MAX_SPEED = 12;
        var DRIFT_STRENGTH = 0.035;
        var DRIFT_TURN = 0.35;
        var SETTLE_MIN_TICKS = 90;
        var SETTLE_MAX_TICKS = 600;
        var SETTLE_SPEED = 0.35;
        var DRAG_THRESHOLD = 4;
        var SEARCH_DELAY = 300;

        var graphElement = document.querySelector('.graph');
        var reader = document.querySelector('[data-reader]');
        var readerPath = document.querySelector('[data-reader-path]');
        var readerContent = document.querySelector('[data-reader-content]');
        var readerClose = document.querySelector('[data-reader-close]');
        var showAllButton = document.querySelector('[data-graph-show-all]');
        var contextChip = document.querySelector('[data-context]');
        var contextName = document.querySelector('[data-context-name]');
        var contextRemove = document.querySelector('[data-context-remove]');
        var searchBox = document.querySelector('[data-search]');
        var searchInput = document.querySelector('[data-search-input]');
        var searchResults = document.querySelector('[data-search-results]');
        var reducedMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

        var nodes = [];
        var nodesById = {};
        var edges = [];
        var selectedId = null;
        /* The ids of the nodes in the shown subgraph. Null when the full graph is shown. */
        var subgraphIds = null;
        /* The nodes and the edges that are shown. Only these take part in the simulation. */
        var activeNodes = [];
        var activeEdges = [];
        var documentRequest = 0;
        var currentPath = '';

        (data.nodes || []).forEach(function (item) {
            var node = {
                id: item.id,
                name: item.name,
                title: item.title,
                path: item.path,
                x: 0,
                y: 0,
                vx: 0,
                vy: 0,
                angle: Math.random() * Math.PI * 2,
                fixed: false,
                element: null
            };

            nodes.push(node);
            nodesById[node.id] = node;
        });

        var edgesByKey = {};

        (data.relationships || []).forEach(function (item) {
            var source = nodesById[item.source];
            var target = nodesById[item.target];

            if (!source || !target || source === target) {
                return;
            }

            /* A relation is directed. Draw one edge per pair of nodes. A pair declared in both directions is bidirectional. */
            var key = [source.id, target.id].sort().join('|');
            var existing = edgesByKey[key];

            if (existing) {
                if (existing.source !== source) {
                    existing.bidirectional = true;
                }

                return;
            }

            edgesByKey[key] = { source: source, target: target, bidirectional: item.bidirectional === true, element: null };
            edges.push(edgesByKey[key]);
        });

        activeNodes = nodes.slice();
        activeEdges = edges.slice();

        /* Reader */

        function documentUrl(id) {
            return documentUrlTemplate.replace(/0{12}(?=[^\/]*$)/, encodeURIComponent(id));
        }

        function showReaderMessage(text) {
            var message = document.createElement('p');

            message.className = 'reader-empty';
            message.textContent = text;
            readerContent.textContent = '';
            readerContent.appendChild(message);
        }

        function setContext(name) {
            if (!contextChip) {
                return;
            }

            contextChip.hidden = !name;

            if (contextName) {
                contextName.textContent = name || '';
            }

            if (contextRemove) {
                contextRemove.setAttribute('aria-label', name ? 'Remove ' + name + ' from context' : 'Remove the document from context');
            }
        }

        function select(id) {
            selectedId = id;

            nodes.forEach(function (node) {
                if (node.element) {
                    node.element.classList.toggle('is-selected', node.id === id);
                    node.element.setAttribute('aria-pressed', node.id === id ? 'true' : 'false');
                }
            });

            edges.forEach(function (edge) {
                if (edge.element) {
                    var active = edge.source.id === id || edge.target.id === id;

                    edge.element.classList.toggle('is-active', active);
                    setArrows(edge, active);
                }
            });
        }

        /* Set the arrow heads of the edge: at the target end, and also at the source end when the edge is bidirectional. */
        function setArrows(edge, active) {
            var marker = 'url(#' + (active ? ARROW_MARKER_ACTIVE : ARROW_MARKER) + ')';

            edge.element.setAttribute('marker-end', marker);

            if (edge.bidirectional) {
                edge.element.setAttribute('marker-start', marker);
            } else {
                edge.element.removeAttribute('marker-start');
            }
        }

        /* Show only the nodes with the given ids. Show an edge only when both ends are shown. Null shows the full graph. */
        function showNodes(ids) {
            subgraphIds = ids;
            activeNodes = nodes.filter(function (node) {
                return !ids || ids[node.id] === true;
            });
            activeEdges = edges.filter(function (edge) {
                return !ids || (ids[edge.source.id] === true && ids[edge.target.id] === true);
            });

            nodes.forEach(function (node) {
                if (node.element) {
                    node.element.classList.toggle('is-hidden', !!ids && ids[node.id] !== true);
                }
            });

            edges.forEach(function (edge) {
                if (edge.element) {
                    edge.element.classList.toggle('is-hidden', activeEdges.indexOf(edge) === -1);
                }
            });

            if (showAllButton) {
                showAllButton.hidden = !ids;
            }

            if (svg) {
                restart();
            }
        }

        /* Show the selected node and its direct neighbours, in either direction. The ids come from the documents endpoint. */
        function showSubgraph(id, subgraph) {
            var ids = {};

            ids[id] = true;

            (subgraph || []).forEach(function (item) {
                if (nodesById[item]) {
                    ids[item] = true;
                }
            });

            showNodes(ids);
        }

        function showFullGraph() {
            if (subgraphIds) {
                showNodes(null);
            }
        }

        /* Find the node for a relative link inside the rendered document. Return null for other links. */
        function nodeForLink(href) {
            var prefix = function (path) {
                return path.charAt(0) === '/' ? path : '/' + path;
            };
            var url;

            try {
                url = new URL(href, 'http://docs.invalid' + prefix(currentPath));
            } catch (error) {
                return null;
            }

            if (url.host !== 'docs.invalid') {
                return null;
            }

            var pathname = decodeURIComponent(url.pathname);

            for (var index = 0; index < nodes.length; index++) {
                if (prefix(nodes[index].path) === pathname) {
                    return nodes[index];
                }
            }

            return null;
        }

        function renderLinks(relations) {
            var section = document.createElement('div');
            var label = document.createElement('span');
            var list = document.createElement('ul');

            section.className = 'links';
            label.id = 'links-label';
            label.className = 'links-label';
            label.textContent = 'Linked files';
            list.className = 'links-list';
            list.setAttribute('aria-labelledby', 'links-label');

            relations.forEach(function (relation) {
                var item = document.createElement('li');
                var chip = document.createElement('button');

                chip.type = 'button';
                chip.className = 'button file-chip';
                chip.textContent = relation.path;
                chip.addEventListener('click', function () {
                    openDocument(relation.id);
                });

                item.appendChild(chip);
                list.appendChild(item);
            });

            section.appendChild(label);
            section.appendChild(list);

            return section;
        }

        function renderDocument(doc) {
            var body = document.createElement('div');

            currentPath = doc.path;
            readerPath.textContent = doc.path;
            readerPath.title = doc.path;

            /* The server escapes raw HTML and removes unsafe links. */
            body.className = 'markdown';
            body.innerHTML = doc.html;
            body.addEventListener('click', function (event) {
                var link = event.target.closest ? event.target.closest('a[href]') : null;
                var node = link ? nodeForLink(link.getAttribute('href')) : null;

                if (node) {
                    event.preventDefault();
                    openDocument(node.id);
                }
            });

            readerContent.textContent = '';
            readerContent.appendChild(body);

            if (doc.relations && doc.relations.length > 0) {
                readerContent.appendChild(renderLinks(doc.relations));
            }

            /* Show the top of the new document. */
            reader.scrollTop = 0;
            setContext(doc.name);
        }

        function openDocument(id) {
            var request = ++documentRequest;
            var node = nodesById[id];

            reader.hidden = false;
            reader.setAttribute('aria-busy', 'true');
            select(id);

            fetch(documentUrl(id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('The document request failed.');
                    }

                    return response.json();
                })
                .then(function (doc) {
                    if (request === documentRequest) {
                        renderDocument(doc);
                        showSubgraph(id, doc.subgraph);
                        reader.removeAttribute('aria-busy');
                    }
                })
                .catch(function () {
                    if (request === documentRequest) {
                        currentPath = '';
                        readerPath.textContent = node ? node.path : 'Unknown file';
                        showReaderMessage('The document could not be loaded.');
                        reader.scrollTop = 0;
                        setContext(null);
                        showFullGraph();
                        reader.removeAttribute('aria-busy');
                    }
                });
        }

        function closeReader() {
            var node = nodesById[selectedId];

            documentRequest++;
            currentPath = '';
            reader.hidden = true;
            reader.removeAttribute('aria-busy');
            readerPath.textContent = 'No file open';
            readerPath.removeAttribute('title');
            showReaderMessage('Select a node in the graph to read the document.');
            reader.scrollTop = 0;
            select(null);
            setContext(null);
            showFullGraph();

            if (node && node.element) {
                node.element.focus();
            }
        }

        if (readerClose) {
            readerClose.addEventListener('click', closeReader);
        }

        if (showAllButton) {
            showAllButton.addEventListener('click', function () {
                var node = nodesById[selectedId];

                showFullGraph();

                /* The button is hidden now. Move the focus to the selected node. */
                if (node && node.element) {
                    node.element.focus();
                }
            });
        }

        if (contextRemove) {
            contextRemove.addEventListener('click', function () {
                setContext(null);
            });
        }

        /* Graph */

        var svg = null;
        var width = 0;
        var height = 0;
        var frame = null;
        var ticks = 0;
        var drag = null;

        function createSvg(name, attributes) {
            var element = document.createElementNS(SVG_NS, name);

            Object.keys(attributes || {}).forEach(function (key) {
                element.setAttribute(key, attributes[key]);
            });

            return element;
        }

        /* Create an arrow head. The tip is at the line end. The marker turns at the line start, so one marker fits both ends. */
        function createArrowMarker(id, className) {
            var marker = createSvg('marker', {
                id: id,
                'class': className,
                viewBox: '0 0 10 10',
                refX: '10',
                refY: '5',
                markerWidth: String(ARROW_SIZE),
                markerHeight: String(ARROW_SIZE),
                markerUnits: 'userSpaceOnUse',
                orient: 'auto-start-reverse'
            });

            marker.appendChild(createSvg('path', { d: 'M0 0 L10 5 L0 10 Z' }));

            return marker;
        }

        function measure() {
            width = Math.max(graphElement.clientWidth, 1);
            height = Math.max(graphElement.clientHeight, 1);
            svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
        }

        function placeNodes() {
            var radius = Math.min(width, height) / 3;

            nodes.forEach(function (node, index) {
                var angle = (index / nodes.length) * Math.PI * 2;

                node.x = width / 2 + Math.cos(angle) * radius * (0.6 + Math.random() * 0.4);
                node.y = height / 2 + Math.sin(angle) * radius * (0.6 + Math.random() * 0.4);
            });
        }

        function clamp(value, min, max) {
            return Math.max(min, Math.min(max, value));
        }

        function step() {
            var count = activeNodes.length;
            var speed = 0;

            /* Repel each pair of nodes. */
            for (var i = 0; i < count; i++) {
                for (var j = i + 1; j < count; j++) {
                    var a = activeNodes[i];
                    var b = activeNodes[j];
                    var dx = b.x - a.x;
                    var dy = b.y - a.y;
                    var distanceSquared = Math.max(dx * dx + dy * dy, MIN_DISTANCE_SQUARED);
                    var distance = Math.sqrt(distanceSquared);
                    var force = REPULSION / distanceSquared;
                    var fx = (dx / distance) * force;
                    var fy = (dy / distance) * force;

                    a.vx -= fx;
                    a.vy -= fy;
                    b.vx += fx;
                    b.vy += fy;
                }
            }

            /* Pull linked nodes to the spring length. */
            activeEdges.forEach(function (edge) {
                var dx = edge.target.x - edge.source.x;
                var dy = edge.target.y - edge.source.y;
                var distance = Math.sqrt(dx * dx + dy * dy) || 1;
                var force = (distance - SPRING_LENGTH) * SPRING_STRENGTH;
                var fx = (dx / distance) * force;
                var fy = (dy / distance) * force;

                edge.source.vx += fx;
                edge.source.vy += fy;
                edge.target.vx -= fx;
                edge.target.vy -= fy;
            });

            var drift = !(reducedMotion && reducedMotion.matches && ticks > SETTLE_MIN_TICKS);

            activeNodes.forEach(function (node) {
                if (node.fixed) {
                    node.vx = 0;
                    node.vy = 0;

                    return;
                }

                /* Pull to the center. Add a slow random drift, so the graph floats. */
                node.vx += (width / 2 - node.x) * CENTER_STRENGTH;
                node.vy += (height / 2 - node.y) * CENTER_STRENGTH;

                if (drift) {
                    node.angle += (Math.random() - 0.5) * DRIFT_TURN;
                    node.vx += Math.cos(node.angle) * DRIFT_STRENGTH;
                    node.vy += Math.sin(node.angle) * DRIFT_STRENGTH;
                }

                node.vx = clamp(node.vx * DAMPING, -MAX_SPEED, MAX_SPEED);
                node.vy = clamp(node.vy * DAMPING, -MAX_SPEED, MAX_SPEED);
                node.x = clamp(node.x + node.vx, EDGE_PADDING, Math.max(EDGE_PADDING, width - EDGE_PADDING));
                node.y = clamp(node.y + node.vy, EDGE_PADDING, Math.max(EDGE_PADDING, height - EDGE_PADDING));

                speed += Math.abs(node.vx) + Math.abs(node.vy);
            });

            ticks++;

            return count > 0 ? speed / count : 0;
        }

        function draw() {
            activeEdges.forEach(function (edge) {
                var dx = edge.target.x - edge.source.x;
                var dy = edge.target.y - edge.source.y;
                var distance = Math.sqrt(dx * dx + dy * dy) || 1;
                /* Shorten the line at both ends, so the arrow tips touch the circle edges. */
                var gap = Math.min(ARROW_GAP, distance / 2);
                var ux = (dx / distance) * gap;
                var uy = (dy / distance) * gap;

                edge.element.setAttribute('x1', (edge.source.x + ux).toFixed(1));
                edge.element.setAttribute('y1', (edge.source.y + uy).toFixed(1));
                edge.element.setAttribute('x2', (edge.target.x - ux).toFixed(1));
                edge.element.setAttribute('y2', (edge.target.y - uy).toFixed(1));
            });

            activeNodes.forEach(function (node) {
                node.element.setAttribute('transform', 'translate(' + node.x.toFixed(1) + ' ' + node.y.toFixed(1) + ')');
            });
        }

        function settled(speed) {
            if (!reducedMotion || !reducedMotion.matches || drag) {
                return false;
            }

            return (ticks > SETTLE_MIN_TICKS && speed < SETTLE_SPEED) || ticks > SETTLE_MAX_TICKS;
        }

        function loop() {
            var speed = step();

            draw();
            frame = settled(speed) ? null : window.requestAnimationFrame(loop);
        }

        /* Start the simulation again. The settle counter starts from zero. */
        function restart() {
            ticks = 0;

            if (frame === null) {
                frame = window.requestAnimationFrame(loop);
            }
        }

        function pointerPosition(event) {
            var rect = svg.getBoundingClientRect();

            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        }

        function bindNode(node) {
            var element = node.element;

            element.addEventListener('pointerdown', function (event) {
                if (event.button !== 0) {
                    return;
                }

                var position = pointerPosition(event);

                drag = { node: node, startX: position.x, startY: position.y, moved: false, pointer: event.pointerId };
                node.fixed = true;
                element.setPointerCapture(event.pointerId);
                restart();
            });

            element.addEventListener('pointermove', function (event) {
                if (!drag || drag.node !== node || drag.pointer !== event.pointerId) {
                    return;
                }

                var position = pointerPosition(event);

                if (!drag.moved && Math.abs(position.x - drag.startX) + Math.abs(position.y - drag.startY) > DRAG_THRESHOLD) {
                    drag.moved = true;
                    element.classList.add('is-dragging');
                }

                if (drag.moved) {
                    node.x = clamp(position.x, EDGE_PADDING, Math.max(EDGE_PADDING, width - EDGE_PADDING));
                    node.y = clamp(position.y, EDGE_PADDING, Math.max(EDGE_PADDING, height - EDGE_PADDING));
                }
            });

            function release(event, open) {
                if (!drag || drag.node !== node || drag.pointer !== event.pointerId) {
                    return;
                }

                var click = !drag.moved;

                node.fixed = false;
                element.classList.remove('is-dragging');
                drag = null;
                restart();

                if (open && click) {
                    openDocument(node.id);
                }
            }

            element.addEventListener('pointerup', function (event) {
                release(event, true);
            });

            element.addEventListener('pointercancel', function (event) {
                release(event, false);
            });

            element.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openDocument(node.id);
                }
            });
        }

        function renderGraph() {
            if (!graphElement) {
                return;
            }

            if (nodes.length === 0) {
                var empty = document.createElement('p');

                empty.className = 'graph-empty';
                empty.textContent = 'No Markdown documents found in the configured paths.';
                graphElement.appendChild(empty);

                return;
            }

            svg = createSvg('svg', { 'class': 'graph-canvas', role: 'group', 'aria-label': 'Documents' });

            var defs = createSvg('defs');
            var edgeLayer = createSvg('g', { 'class': 'graph-edges', 'aria-hidden': 'true' });
            var nodeLayer = createSvg('g', { 'class': 'graph-nodes' });

            defs.appendChild(createArrowMarker(ARROW_MARKER, 'graph-arrow'));
            defs.appendChild(createArrowMarker(ARROW_MARKER_ACTIVE, 'graph-arrow-active'));

            edges.forEach(function (edge) {
                edge.element = createSvg('line', { 'class': 'graph-edge' });
                setArrows(edge, false);
                edgeLayer.appendChild(edge.element);
            });

            nodes.forEach(function (node) {
                var group = createSvg('g', {
                    'class': 'graph-node',
                    tabindex: '0',
                    role: 'button',
                    'aria-label': 'Open ' + node.path,
                    'aria-pressed': 'false'
                });
                var tooltip = createSvg('title');
                var label = createSvg('text', { x: String(LABEL_OFFSET), y: '4' });

                tooltip.textContent = node.title + ' (' + node.path + ')';
                label.textContent = node.name;

                group.appendChild(tooltip);
                group.appendChild(createSvg('circle', { r: String(NODE_RADIUS) }));
                group.appendChild(label);
                nodeLayer.appendChild(group);

                node.element = group;
                bindNode(node);
            });

            svg.appendChild(defs);
            svg.appendChild(edgeLayer);
            svg.appendChild(nodeLayer);
            graphElement.appendChild(svg);

            measure();
            placeNodes();
            draw();
            restart();

            if (window.ResizeObserver) {
                new ResizeObserver(function () {
                    measure();
                    restart();
                }).observe(graphElement);
            } else {
                window.addEventListener('resize', function () {
                    measure();
                    restart();
                });
            }

            if (reducedMotion && reducedMotion.addEventListener) {
                reducedMotion.addEventListener('change', restart);
            }
        }

        renderGraph();

        /* Filter. The filter shows a result list. It does not change the graph. */

        var searchTimer = null;
        var searchRequest = 0;
        var searchAbort = null;

        function closeResults() {
            if (!searchResults) {
                return;
            }

            searchResults.hidden = true;
            searchResults.textContent = '';
            searchInput.setAttribute('aria-expanded', 'false');
        }

        function showResults(content) {
            searchResults.textContent = '';
            searchResults.appendChild(content);
            searchResults.hidden = false;
            searchInput.setAttribute('aria-expanded', 'true');
        }

        function resultButtons() {
            return Array.prototype.slice.call(searchResults.querySelectorAll('.search-result'));
        }

        function renderResults(results) {
            if (results.length === 0) {
                var empty = document.createElement('p');

                empty.className = 'search-empty';
                empty.textContent = 'No matching files';
                showResults(empty);

                return;
            }

            var list = document.createElement('ul');

            results.forEach(function (result) {
                var item = document.createElement('li');
                var button = document.createElement('button');
                var name = document.createElement('span');
                var path = document.createElement('span');

                button.type = 'button';
                button.className = 'search-result';
                name.className = 'search-result-name';
                name.textContent = result.name;
                path.className = 'search-result-path';
                path.textContent = result.path;

                button.appendChild(name);
                button.appendChild(path);
                button.addEventListener('click', function () {
                    closeResults();
                    openDocument(result.id);
                });

                item.appendChild(button);
                list.appendChild(item);
            });

            showResults(list);
        }

        function runSearch(query) {
            var request = ++searchRequest;
            var separator = searchUrl.indexOf('?') === -1 ? '?' : '&';
            var options = { headers: { Accept: 'application/json' }, credentials: 'same-origin' };

            if (searchAbort) {
                searchAbort.abort();
            }

            if (window.AbortController) {
                searchAbort = new AbortController();
                options.signal = searchAbort.signal;
            }

            fetch(searchUrl + separator + 'q=' + encodeURIComponent(query), options)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('The search request failed.');
                    }

                    return response.json();
                })
                .then(function (result) {
                    if (request === searchRequest) {
                        renderResults(result.results || []);
                    }
                })
                .catch(function (error) {
                    if (request !== searchRequest || (error && error.name === 'AbortError')) {
                        return;
                    }

                    var message = document.createElement('p');

                    message.className = 'search-empty';
                    message.textContent = 'The search is not available.';
                    showResults(message);
                });
        }

        if (searchInput && searchResults) {
            searchInput.addEventListener('input', function () {
                var query = searchInput.value.trim();

                window.clearTimeout(searchTimer);

                if (query === '') {
                    searchRequest++;
                    closeResults();

                    return;
                }

                searchTimer = window.setTimeout(function () {
                    runSearch(query);
                }, SEARCH_DELAY);
            });

            searchInput.addEventListener('keydown', function (event) {
                var buttons = resultButtons();

                if (event.key === 'Escape') {
                    window.clearTimeout(searchTimer);
                    searchRequest++;
                    closeResults();
                } else if (event.key === 'ArrowDown' && buttons.length > 0) {
                    event.preventDefault();
                    buttons[0].focus();
                }
            });

            searchResults.addEventListener('keydown', function (event) {
                var buttons = resultButtons();
                var index = buttons.indexOf(document.activeElement);

                if (event.key === 'Escape') {
                    closeResults();
                    searchInput.focus();
                } else if (event.key === 'ArrowDown' && index < buttons.length - 1) {
                    event.preventDefault();
                    buttons[index + 1].focus();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    (index > 0 ? buttons[index - 1] : searchInput).focus();
                }
            });

            document.addEventListener('click', function (event) {
                if (searchBox && !searchBox.contains(event.target)) {
                    closeResults();
                }
            });
        }
    })();
</script>
</body>
</html>
