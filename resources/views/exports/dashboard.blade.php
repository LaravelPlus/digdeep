<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigDeep — Dashboard Report — {{ $data['appName'] }}</title>
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
                <span class="text-drac-comment text-xs ml-2">Dashboard Report</span>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs text-drac-comment">
            <span class="bg-drac-current px-2.5 py-1 rounded-full">{{ $data['appName'] }}</span>
            <span class="bg-drac-current px-2.5 py-1 rounded-full">Exported {{ $data['exportedAt'] }}</span>
        </div>
    </header>

    <main class="px-8 py-8 max-w-screen-2xl mx-auto">

        {{-- Stats grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-8">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Total Requests</div>
                <div class="text-2xl font-extrabold text-drac-fg leading-none">@{{ stats.total }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Avg Duration</div>
                <div class="text-2xl font-extrabold leading-none" :class="stats.avg_duration < 100 ? 'text-drac-green' : stats.avg_duration < 500 ? 'text-drac-orange' : 'text-drac-red'">@{{ stats.avg_duration }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Error Rate</div>
                <div class="text-2xl font-extrabold leading-none" :class="stats.error_rate === 0 ? 'text-drac-green' : stats.error_rate < 5 ? 'text-drac-orange' : 'text-drac-red'">@{{ stats.error_rate }}<span class="text-xs text-drac-comment font-semibold ml-0.5">%</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Success Rate</div>
                <div class="text-2xl font-extrabold text-drac-green leading-none">@{{ stats.success_rate }}<span class="text-xs text-drac-comment font-semibold ml-0.5">%</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Avg Memory</div>
                <div class="text-2xl font-extrabold text-drac-cyan leading-none">@{{ stats.avg_memory }}<span class="text-xs text-drac-comment font-semibold ml-0.5">MB</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Avg Queries</div>
                <div class="text-2xl font-extrabold text-drac-purple leading-none">@{{ stats.avg_queries }}</div>
            </div>
        </div>

        {{-- Top routes --}}
        <div v-if="topRoutes.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2">
                <h2 class="text-drac-fg text-sm font-semibold">Top Routes</h2>
                <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">@{{ topRoutes.length }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-drac-border">
                            <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2 w-20">Method</th>
                            <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Route</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Hits</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Avg Duration</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Error Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-drac-border/60">
                        <tr v-for="r in topRoutes" :key="r.method + r.url" class="hover:bg-drac-current/20 transition">
                            <td class="px-5 py-2.5">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded" :class="methodClass(r.method)">@{{ r.method }}</span>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-drac-fg">@{{ r.url }}</td>
                            <td class="text-right px-4 py-2.5 text-drac-fg text-xs font-bold">@{{ r.count }}</td>
                            <td class="text-right px-4 py-2.5">
                                <span class="text-xs font-bold" :class="r.avg_duration < 100 ? 'text-drac-green' : r.avg_duration < 500 ? 'text-drac-orange' : 'text-drac-red'">@{{ r.avg_duration }}ms</span>
                            </td>
                            <td class="text-right px-5 py-2.5">
                                <span class="text-xs font-bold" :class="r.error_rate === 0 ? 'text-drac-comment' : r.error_rate < 5 ? 'text-drac-orange' : 'text-drac-red'">@{{ r.error_rate }}%</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Profiles list --}}
        <div v-if="profiles.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
            <div class="px-5 py-3 border-b border-drac-border flex items-center gap-3">
                <h2 class="text-drac-fg text-sm font-semibold">Profiles</h2>
                <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">@{{ filteredProfiles.length }} / @{{ profiles.length }}</span>
                <div class="ml-auto">
                    <input v-model="search" type="text" placeholder="Filter by URL…"
                        class="bg-drac-current text-drac-fg text-xs font-mono rounded-lg border border-drac-border px-3 py-1.5 w-52 focus:outline-none focus:border-drac-purple placeholder:text-drac-comment/50">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-drac-border">
                            <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Method</th>
                            <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">URL</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Status</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Duration</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Queries</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Memory</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-drac-border/60">
                        <tr v-for="p in filteredProfiles" :key="p.id" class="hover:bg-drac-current/20 transition">
                            <td class="px-5 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded" :class="methodClass(p.method)">@{{ p.method }}</span>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-drac-fg max-w-xs truncate">@{{ p.url }}</td>
                            <td class="text-right px-4 py-2">
                                <span class="text-xs font-bold font-mono" :class="p.status_code < 300 ? 'text-drac-green' : p.status_code < 400 ? 'text-drac-yellow' : 'text-drac-red'">@{{ p.status_code }}</span>
                            </td>
                            <td class="text-right px-4 py-2">
                                <span class="text-xs font-bold" :class="p.duration_ms < 100 ? 'text-drac-green' : p.duration_ms < 500 ? 'text-drac-orange' : 'text-drac-red'">@{{ parseFloat(p.duration_ms).toFixed(1) }}ms</span>
                            </td>
                            <td class="text-right px-4 py-2 text-drac-purple text-xs font-bold">@{{ p.query_count }}</td>
                            <td class="text-right px-4 py-2 text-drac-cyan text-xs font-bold">@{{ parseFloat(p.memory_peak_mb).toFixed(1) }}MB</td>
                            <td class="text-right px-5 py-2 text-drac-comment text-[11px] font-mono">@{{ p.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="filteredProfiles.length === 0" class="px-5 py-8 text-center text-drac-comment text-sm">
                No profiles match your filter.
            </div>
        </div>

    </main>
</div>

<script>
const DATA = {!! json_encode($data) !!};
const { createApp } = Vue;
createApp({
    data() {
        return {
            stats: DATA.stats,
            topRoutes: DATA.topRoutes,
            profiles: DATA.profiles,
            search: '',
        };
    },
    computed: {
        filteredProfiles() {
            if (!this.search) return this.profiles;
            const q = this.search.toLowerCase();
            return this.profiles.filter(p =>
                p.url.toLowerCase().includes(q) ||
                p.method.toLowerCase().includes(q) ||
                String(p.status_code).includes(q)
            );
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
