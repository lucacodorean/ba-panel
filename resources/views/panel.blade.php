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

        .docs-body {
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

        /* Document reader */

        .reader {
            flex: 1 1 400px;
            min-width: 0;
            min-height: 440px;
            max-height: 100%;
            overflow: auto;
            background-color: var(--color-background);
            border-left: var(--border);
        }

        .reader-bar {
            position: sticky;
            top: 0;
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

        .reader-title {
            margin: 0 0 4px;
            font-size: 24px;
            font-weight: 600;
            line-height: 30px;
            letter-spacing: -0.48px;
        }

        .reader-text {
            color: var(--color-body);
            font-size: 14.5px;
            line-height: 1.65;
        }

        .reader-subtitle {
            margin: 10px 0 0;
            font-size: 16px;
            font-weight: 600;
            line-height: 1.35;
            letter-spacing: -0.16px;
        }

        .outline {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-left: 2px;
        }

        .outline-item {
            display: flex;
            gap: 10px;
            color: var(--color-body);
            font-size: 14.5px;
            line-height: 1.6;
        }

        .outline-bullet {
            flex: 0 0 18px;
            color: var(--color-muted);
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

            .docs,
            .agent {
                min-height: 0;
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
                    <span class="section-meta"><span>10</span> files · <span>13</span> links</span>
                </div>

                <label class="search" for="docs-filter">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m20 20-3.5-3.5"/>
                    </svg>
                    <span class="sr-only">Filter files</span>
                    <input id="docs-filter" class="search-input" type="search" name="filter" placeholder="Filter files" autocomplete="off">
                </label>
            </div>

            <div class="docs-body">
                <div class="graph" role="region" aria-label="Documentation graph"></div>

                <article class="reader" aria-labelledby="reader-title">
                    <div class="reader-bar">
                        <span class="reader-path"><span>README.md</span></span>
                        <button type="button" class="icon-button reader-close" aria-label="Close file">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
                                <path d="M6 6l12 12M18 6 6 18"/>
                            </svg>
                        </button>
                    </div>

                    <div class="reader-content">
                        <h1 id="reader-title" class="reader-title">Project documentation</h1>
                        <p class="reader-text">Entry point for the knowledge base the feasibility agent reads from. Each node in the graph is a Markdown file; each line is a link between two files.</p>

                        <h2 class="reader-subtitle">Sections</h2>
                        <ul class="outline">
                            <li class="outline-item"><span class="outline-bullet" aria-hidden="true">•</span><span>Architecture — how the system is put together</span></li>
                            <li class="outline-item"><span class="outline-bullet" aria-hidden="true">•</span><span>API — endpoints and authentication</span></li>
                            <li class="outline-item"><span class="outline-bullet" aria-hidden="true">•</span><span>Agents — the feasibility agent and its prompts</span></li>
                            <li class="outline-item"><span class="outline-bullet" aria-hidden="true">•</span><span>Guides — getting started and the glossary</span></li>
                        </ul>

                        <div class="links">
                            <span id="links-label" class="links-label">Linked files</span>
                            <ul class="links-list" aria-labelledby="links-label">
                                <li><button type="button" class="button file-chip">architecture/overview.md</button></li>
                                <li><button type="button" class="button file-chip">guides/getting-started.md</button></li>
                                <li><button type="button" class="button file-chip">agents/feasibility-agent.md</button></li>
                            </ul>
                        </div>
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
                    <span class="context-chip">
                        <span>Context: <span>README.md</span></span>
                        <button type="button" class="icon-button context-remove" aria-label="Remove README.md from context">
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
</body>
</html>
