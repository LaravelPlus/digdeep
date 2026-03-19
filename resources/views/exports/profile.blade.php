<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigDeep — Profile Report — {{ $data['profile']['method'] ?? '' }} {{ $data['profile']['url'] ?? '' }}</title>
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
            --color-drac-border: #44475a;
        }
    </style>
    <style>
        [v-cloak] { display: none !important; }
        * { -webkit-font-smoothing: antialiased; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-thumb { background: #44475a; border-radius: 3px; }
        ::-webkit-scrollbar-track { background: #282a36; }
        .dd-tab { position: relative; color: #6272a4; transition: color .15s; cursor: pointer; }
        .dd-tab:hover { color: #f8f8f2; }
        .dd-tab.active { color: #f8f8f2; font-weight: 600; }
        .dd-tab.active::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: #bd93f9; border-radius: 1px 1px 0 0; }
    </style>
</head>
<body class="bg-drac-bg text-drac-fg min-h-screen font-sans">

<div id="app" v-cloak>
    {{-- Header --}}
    <header class="bg-drac-surface border-b border-drac-border px-8 py-4 flex items-center justify-between sticky top-0 z-10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-drac-purple to-drac-pink flex items-center justify-center shadow-lg">
                <svg class="w-[18px] h-[18px] text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
            </div>
            <div>
                <span class="font-bold text-drac-fg text-sm">DigDeep</span>
                <span class="text-drac-comment text-xs ml-2">Profile Report</span>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs text-drac-comment">
            <span class="bg-drac-current px-2.5 py-1 rounded-full">{{ $data['appName'] }}</span>
            <span class="bg-drac-current px-2.5 py-1 rounded-full">Exported {{ $data['exportedAt'] }}</span>
        </div>
    </header>

    <main class="px-8 py-8 max-w-screen-2xl mx-auto">

        {{-- Request summary bar --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-5 mb-6">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-[11px] font-bold px-2.5 py-1 rounded" :class="methodClass(profile.method)">@{{ profile.method }}</span>
                <span class="font-mono text-sm text-drac-fg font-semibold flex-1 min-w-0 truncate">@{{ profile.url }}</span>
                <span class="text-sm font-bold font-mono" :class="profile.status_code < 300 ? 'text-drac-green' : profile.status_code < 400 ? 'text-drac-yellow' : 'text-drac-red'">@{{ profile.status_code }}</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 mt-4">
                <div>
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Duration</div>
                    <div class="text-sm font-bold" :class="profile.duration_ms < 100 ? 'text-drac-green' : profile.duration_ms < 500 ? 'text-drac-orange' : 'text-drac-red'">@{{ parseFloat(profile.duration_ms).toFixed(1) }}ms</div>
                </div>
                <div>
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Memory</div>
                    <div class="text-sm font-bold text-drac-cyan">@{{ parseFloat(profile.memory_peak_mb).toFixed(1) }}MB</div>
                </div>
                <div>
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Queries</div>
                    <div class="text-sm font-bold text-drac-purple">@{{ profile.query_count }}</div>
                </div>
                <div>
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Cache Ops</div>
                    <div class="text-sm font-bold text-drac-orange">@{{ (profileData.cache || []).length }}</div>
                </div>
                <div>
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Events</div>
                    <div class="text-sm font-bold text-drac-yellow">@{{ (profileData.events || []).length }}</div>
                </div>
                <div>
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Captured At</div>
                    <div class="text-xs font-mono text-drac-comment">@{{ profile.created_at }}</div>
                </div>
            </div>
        </div>

        {{-- Exception alert --}}
        <div v-if="profileData.exception" class="bg-drac-red/10 border border-drac-red/40 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-drac-red shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                <div class="flex-1 min-w-0">
                    <div class="text-drac-red text-xs font-bold mb-1">@{{ profileData.exception.class }}</div>
                    <div class="text-drac-fg text-sm font-mono">@{{ profileData.exception.message }}</div>
                    <div class="text-drac-comment text-xs mt-1 font-mono">@{{ profileData.exception.file }}:@{{ profileData.exception.line }}</div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
            <div class="border-b border-drac-border px-5 flex items-center gap-5 overflow-x-auto">
                <button v-for="tab in tabs" :key="tab.key"
                    @click="activeTab = tab.key"
                    class="dd-tab text-xs py-3 whitespace-nowrap"
                    :class="activeTab === tab.key ? 'active' : ''">
                    @{{ tab.label }}
                    <span v-if="tab.count !== null" class="ml-1.5 bg-drac-current text-drac-comment text-[9px] font-bold px-1.5 py-0.5 rounded-full">@{{ tab.count }}</span>
                </button>
            </div>

            {{-- Overview tab --}}
            <div v-if="activeTab === 'overview'" class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- Route info --}}
                    <div v-if="profileData.route && Object.keys(profileData.route).length">
                        <h3 class="text-drac-comment text-[10px] uppercase tracking-wider font-bold mb-3">Route</h3>
                        <div class="space-y-2">
                            <div v-if="profileData.route.name" class="flex gap-3 text-xs">
                                <span class="text-drac-comment w-20 shrink-0">Name</span>
                                <span class="font-mono text-drac-fg">@{{ profileData.route.name }}</span>
                            </div>
                            <div v-if="profileData.route.action" class="flex gap-3 text-xs">
                                <span class="text-drac-comment w-20 shrink-0">Action</span>
                                <span class="font-mono text-drac-fg break-all">@{{ profileData.route.action }}</span>
                            </div>
                            <div v-if="profileData.route.middleware && profileData.route.middleware.length" class="flex gap-3 text-xs">
                                <span class="text-drac-comment w-20 shrink-0">Middleware</span>
                                <div class="flex flex-wrap gap-1">
                                    <span v-for="mw in profileData.route.middleware" :key="mw" class="bg-drac-current text-drac-comment px-1.5 py-0.5 rounded text-[10px] font-mono">@{{ mw }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Performance breakdown --}}
                    <div v-if="profileData.performance && Object.keys(profileData.performance).length">
                        <h3 class="text-drac-comment text-[10px] uppercase tracking-wider font-bold mb-3">Performance</h3>
                        <div class="space-y-2">
                            <div v-for="(val, key) in profileData.performance" :key="key" class="flex gap-3 text-xs">
                                <span class="text-drac-comment w-32 shrink-0 font-mono">@{{ key }}</span>
                                <span class="font-mono text-drac-fg">@{{ typeof val === 'number' ? (key.includes('ms') || key.includes('time') || key.includes('duration') ? val.toFixed(2) + 'ms' : (key.includes('mb') || key.includes('memory') ? val.toFixed(2) + 'MB' : val)) : val }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Request headers --}}
                    <div v-if="requestHeaders.length">
                        <h3 class="text-drac-comment text-[10px] uppercase tracking-wider font-bold mb-3">Request Headers</h3>
                        <div class="space-y-1">
                            <div v-for="h in requestHeaders" :key="h.name" class="flex gap-3 text-xs">
                                <span class="text-drac-comment font-mono w-44 shrink-0 truncate">@{{ h.name }}</span>
                                <span class="font-mono text-drac-fg break-all">@{{ h.value }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Response headers --}}
                    <div v-if="responseHeaders.length">
                        <h3 class="text-drac-comment text-[10px] uppercase tracking-wider font-bold mb-3">Response Headers</h3>
                        <div class="space-y-1">
                            <div v-for="h in responseHeaders" :key="h.name" class="flex gap-3 text-xs">
                                <span class="text-drac-comment font-mono w-44 shrink-0 truncate">@{{ h.name }}</span>
                                <span class="font-mono text-drac-fg break-all">@{{ h.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Queries tab --}}
            <div v-if="activeTab === 'queries'">
                <div v-if="queries.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-drac-border">
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2 w-16">#</th>
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">SQL</th>
                                <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Time</th>
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Caller</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-drac-border/60">
                            <tr v-for="(q, i) in queries" :key="i" class="hover:bg-drac-current/20 transition">
                                <td class="px-5 py-2.5 text-drac-comment text-xs font-mono">@{{ i + 1 }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-drac-fg max-w-xl">
                                    <div class="truncate" :title="q.sql">@{{ q.sql }}</div>
                                </td>
                                <td class="text-right px-4 py-2.5">
                                    <span class="text-xs font-bold font-mono" :class="q.time_ms < 5 ? 'text-drac-green' : q.time_ms < 50 ? 'text-drac-orange' : 'text-drac-red'">@{{ parseFloat(q.time_ms).toFixed(2) }}ms</span>
                                </td>
                                <td class="px-5 py-2.5 text-drac-comment text-[11px] font-mono max-w-xs truncate" :title="q.caller">@{{ q.caller }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-12 text-center text-drac-comment text-sm">No queries recorded.</div>
            </div>

            {{-- Cache tab --}}
            <div v-if="activeTab === 'cache'">
                <div v-if="cacheOps.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-drac-border">
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Type</th>
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Key</th>
                                <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">TTL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-drac-border/60">
                            <tr v-for="(op, i) in cacheOps" :key="i" class="hover:bg-drac-current/20 transition">
                                <td class="px-5 py-2.5">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded"
                                        :class="op.type === 'hit' ? 'bg-drac-green/20 text-drac-green' : op.type === 'miss' ? 'bg-drac-red/20 text-drac-red' : 'bg-drac-orange/20 text-drac-orange'">
                                        @{{ op.type }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs text-drac-fg">@{{ op.key }}</td>
                                <td class="text-right px-5 py-2.5 text-drac-comment text-xs font-mono">@{{ op.ttl ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-12 text-center text-drac-comment text-sm">No cache operations recorded.</div>
            </div>

            {{-- Events tab --}}
            <div v-if="activeTab === 'events'">
                <div v-if="events.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-drac-border">
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">#</th>
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Event</th>
                                <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Listeners</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-drac-border/60">
                            <tr v-for="(ev, i) in events" :key="i" class="hover:bg-drac-current/20 transition">
                                <td class="px-5 py-2.5 text-drac-comment text-xs font-mono">@{{ i + 1 }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-drac-fg">@{{ ev.name || ev.event || ev }}</td>
                                <td class="text-right px-5 py-2.5 text-drac-purple text-xs font-bold">@{{ (ev.listeners || []).length }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-12 text-center text-drac-comment text-sm">No events recorded.</div>
            </div>

            {{-- Views tab --}}
            <div v-if="activeTab === 'views'">
                <div v-if="views.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-drac-border">
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">#</th>
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">View</th>
                                <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Render Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-drac-border/60">
                            <tr v-for="(v, i) in views" :key="i" class="hover:bg-drac-current/20 transition">
                                <td class="px-5 py-2.5 text-drac-comment text-xs font-mono">@{{ i + 1 }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-drac-fg">@{{ v.name || v.view || v }}</td>
                                <td class="text-right px-5 py-2.5 text-drac-cyan text-xs font-mono">@{{ v.time_ms !== undefined ? parseFloat(v.time_ms).toFixed(2) + 'ms' : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-12 text-center text-drac-comment text-sm">No views recorded.</div>
            </div>

            {{-- Models tab --}}
            <div v-if="activeTab === 'models'">
                <div v-if="models.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-drac-border">
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">#</th>
                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Model</th>
                                <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Action</th>
                                <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Count</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-drac-border/60">
                            <tr v-for="(m, i) in models" :key="i" class="hover:bg-drac-current/20 transition">
                                <td class="px-5 py-2.5 text-drac-comment text-xs font-mono">@{{ i + 1 }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-drac-fg">@{{ m.model || m.class || m }}</td>
                                <td class="text-right px-4 py-2.5 text-drac-orange text-xs font-bold">@{{ m.action || '—' }}</td>
                                <td class="text-right px-5 py-2.5 text-drac-purple text-xs font-bold">@{{ m.count !== undefined ? m.count : 1 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-12 text-center text-drac-comment text-sm">No model operations recorded.</div>
            </div>
        </div>

    </main>
</div>

<script>
const DATA = {!! json_encode($data) !!};
const { createApp } = Vue;
createApp({
    data() {
        const p = DATA.profile || {};
        const d = p.data || {};
        return {
            profile: p,
            profileData: d,
            activeTab: 'overview',
            queries: d.queries || [],
            cacheOps: d.cache || [],
            events: d.events || [],
            views: d.views || [],
            models: d.models || [],
        };
    },
    computed: {
        tabs() {
            return [
                { key: 'overview', label: 'Overview', count: null },
                { key: 'queries',  label: 'Queries',  count: this.queries.length },
                { key: 'cache',    label: 'Cache',    count: this.cacheOps.length },
                { key: 'events',   label: 'Events',   count: this.events.length },
                { key: 'views',    label: 'Views',    count: this.views.length },
                { key: 'models',   label: 'Models',   count: this.models.length },
            ];
        },
        requestHeaders() {
            const h = this.profileData.request && this.profileData.request.headers;
            if (!h) return [];
            return Object.entries(h).map(([name, val]) => ({ name, value: Array.isArray(val) ? val.join(', ') : val }));
        },
        responseHeaders() {
            const h = this.profileData.response && this.profileData.response.headers;
            if (!h) return [];
            return Object.entries(h).map(([name, val]) => ({ name, value: Array.isArray(val) ? val.join(', ') : val }));
        },
    },
    methods: {
        methodClass(m) {
            return {
                GET:    'bg-drac-green/20 text-drac-green',
                POST:   'bg-drac-cyan/20 text-drac-cyan',
                PUT:    'bg-drac-orange/20 text-drac-orange',
                PATCH:  'bg-drac-yellow/20 text-drac-yellow',
                DELETE: 'bg-drac-red/20 text-drac-red',
            }[m] || 'bg-drac-current text-drac-comment';
        },
    },
}).mount('#app');
</script>
</body>
</html>
