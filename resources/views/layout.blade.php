<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DigDeep — @yield('title', 'Profiler')</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style type="text/tailwindcss">
        @theme {
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
            --color-drac-bg: #282a36;
            --color-drac-current: #44475a;
            --color-drac-fg: #f8f8f2;
            --color-drac-comment: #6272a4;
            --color-drac-cyan: #8be9fd;
            --color-drac-green: #50fa7b;
            --color-drac-orange: #ffb86c;
            --color-drac-pink: #ff79c6;
            --color-drac-purple: #bd93f9;
            --color-drac-red: #ff5555;
            --color-drac-yellow: #f1fa8c;
            --color-drac-surface: #21222c;
            --color-drac-elevated: #343746;
            --color-drac-border: #44475a;
        }
    </style>
    <style>
        [v-cloak] { display: none !important; }
        * { -webkit-font-smoothing: antialiased; }
        .dd-tab { position: relative; color: var(--color-drac-comment); transition: color .15s; cursor: pointer; }
        .dd-tab:hover { color: var(--color-drac-fg); }
        .dd-tab.active { color: var(--color-drac-fg); font-weight: 600; }
        .dd-tab.active::after { content:''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: var(--color-drac-purple); border-radius: 1px 1px 0 0; }
        .dd-fade { animation: ddFade .2s ease-out; }
        @keyframes ddFade { from { opacity:0; transform: translateY(4px); } to { opacity:1; transform: translateY(0); } }
        .dd-bar { transition: width .4s cubic-bezier(.4,0,.2,1); }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--color-drac-current); border-radius: 3px; }
        ::-webkit-scrollbar-track { background: var(--color-drac-bg); }
        .dd-nav-link { color: var(--color-drac-comment); transition: all .15s; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 500; }
        .dd-nav-link:hover { color: var(--color-drac-fg); background: var(--color-drac-current); }
        .dd-nav-link.active { color: var(--color-drac-purple); background: rgba(189, 147, 249, 0.1); }
        .dd-sidebar-link { display: flex; align-items: center; gap: 0.625rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 500; color: var(--color-drac-comment); transition: all .15s; cursor: pointer; border: none; background: none; width: 100%; text-align: left; line-height: 1.25; }
        .dd-sidebar-link:hover { color: var(--color-drac-fg); background: var(--color-drac-current); }
        .dd-sidebar-link:hover svg { opacity: 1; }
        .dd-sidebar-link.active { color: var(--color-drac-purple); background: rgba(189, 147, 249, 0.08); font-weight: 600; }
        .dd-sidebar-link.active svg { opacity: 1; color: var(--color-drac-purple); }
    </style>
</head>
<body class="bg-drac-bg text-drac-fg min-h-screen font-sans antialiased">
    {{-- Navigation --}}
    <nav class="bg-drac-surface border-b border-drac-border sticky top-0 z-50">
        <div class="px-8 h-14 flex items-center justify-between">
            <a href="/digdeep" class="flex items-center gap-2.5 group">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-drac-purple to-drac-pink flex items-center justify-center shadow-lg shadow-drac-purple/20">
                    <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                </div>
                <span class="text-drac-fg font-bold text-[15px] tracking-tight">DigDeep</span>
            </a>
            <div class="flex items-center gap-2">
                <span class="text-drac-comment text-[11px] font-medium bg-drac-current px-2.5 py-1 rounded-full">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}</span>
                <span class="text-drac-comment text-[11px] font-medium bg-drac-current px-2.5 py-1 rounded-full">Laravel {{ app()->version() }}</span>
                <span class="text-drac-green text-[11px] font-medium bg-drac-current px-2.5 py-1 rounded-full">{{ config('app.env') }}</span>
            </div>
        </div>
        {{-- Sub-navigation --}}
        <div class="px-8 pb-2 flex items-center gap-1">
            @php
                $currentSection = $currentSection ?? 'web';
            @endphp
            <a href="/digdeep" class="dd-nav-link {{ $currentSection === 'web' ? 'active' : '' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                    Web
                </span>
            </a>
            <a href="/digdeep/security" class="dd-nav-link {{ $currentSection === 'security' ? 'active' : '' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    Security
                </span>
            </a>
            <a href="/digdeep/audits" class="dd-nav-link {{ $currentSection === 'audits' ? 'active' : '' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                    Audits
                </span>
            </a>
            <a href="/digdeep/urls" class="dd-nav-link {{ $currentSection === 'urls' ? 'active' : '' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.061a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
                    URLs
                </span>
            </a>
            <a href="/digdeep/database" class="dd-nav-link {{ $currentSection === 'database' ? 'active' : '' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                    Database
                </span>
            </a>
            <a href="/digdeep/errors" class="dd-nav-link {{ $currentSection === 'errors' ? 'active' : '' }}">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    Errors
                </span>
            </a>
        </div>
    </nav>

    <main class="px-8 py-8">
        @yield('content')
    </main>
</body>
</html>
