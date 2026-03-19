<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigDeep — Performance Report — {{ $data['appName'] }}</title>
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
        th[data-sort] { cursor: pointer; user-select: none; }
        th[data-sort]:hover { color: var(--color-drac-fg) !important; }
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
                <span class="text-drac-comment text-xs ml-2">Performance Report</span>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs text-drac-comment">
            <span class="bg-drac-current px-2.5 py-1 rounded-full">{{ $data['appName'] }}</span>
            <span class="bg-drac-current px-2.5 py-1 rounded-full">Exported {{ $data['exportedAt'] }}</span>
        </div>
    </header>

    <main class="px-8 py-8 max-w-screen-2xl mx-auto">

        {{-- Global stats --}}
        <div v-if="global.total" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-8">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Total Requests</div>
                <div class="text-2xl font-extrabold text-drac-fg leading-none">@{{ global.total }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">P50 Latency</div>
                <div class="text-2xl font-extrabold leading-none" :class="durationColor(global.p50)">@{{ global.p50 }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">P95 Latency</div>
                <div class="text-2xl font-extrabold leading-none" :class="durationColor(global.p95)">@{{ global.p95 }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">P99 Latency</div>
                <div class="text-2xl font-extrabold leading-none" :class="durationColor(global.p99)">@{{ global.p99 }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Error Rate</div>
                <div class="text-2xl font-extrabold leading-none" :class="global.error_rate === 0 ? 'text-drac-green' : global.error_rate < 5 ? 'text-drac-orange' : 'text-drac-red'">@{{ global.error_rate }}<span class="text-xs text-drac-comment font-semibold ml-0.5">%</span></div>
            </div>
        </div>

        {{-- Routes table --}}
        <div v-if="sortedRoutes.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
            <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2">
                <h2 class="text-drac-fg text-sm font-semibold">Routes</h2>
                <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">@{{ sortedRoutes.length }}</span>
                <span class="text-drac-comment text-[10px] ml-auto">Click column headers to sort</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-drac-border">
                            <th @click="sortBy('method')" data-sort class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2 transition" :class="sortField === 'method' ? 'text-drac-fg' : ''">
                                Method <span v-if="sortField === 'method'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('url')" data-sort class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 transition" :class="sortField === 'url' ? 'text-drac-fg' : ''">
                                Route <span v-if="sortField === 'url'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('count')" data-sort class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 transition" :class="sortField === 'count' ? 'text-drac-fg' : ''">
                                Hits <span v-if="sortField === 'count'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('p50')" data-sort class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 transition" :class="sortField === 'p50' ? 'text-drac-fg' : ''">
                                P50 <span v-if="sortField === 'p50'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('p95')" data-sort class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 transition" :class="sortField === 'p95' ? 'text-drac-fg' : ''">
                                P95 <span v-if="sortField === 'p95'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('p99')" data-sort class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 transition" :class="sortField === 'p99' ? 'text-drac-fg' : ''">
                                P99 <span v-if="sortField === 'p99'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('throughput_rpm')" data-sort class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 transition" :class="sortField === 'throughput_rpm' ? 'text-drac-fg' : ''">
                                Throughput <span v-if="sortField === 'throughput_rpm'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('error_rate')" data-sort class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 transition" :class="sortField === 'error_rate' ? 'text-drac-fg' : ''">
                                Error Rate <span v-if="sortField === 'error_rate'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('avg_queries')" data-sort class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 transition" :class="sortField === 'avg_queries' ? 'text-drac-fg' : ''">
                                Avg Queries <span v-if="sortField === 'avg_queries'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                            <th @click="sortBy('avg_memory')" data-sort class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2 transition" :class="sortField === 'avg_memory' ? 'text-drac-fg' : ''">
                                Avg Memory <span v-if="sortField === 'avg_memory'">@{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-drac-border/60">
                        <tr v-for="r in sortedRoutes" :key="r.method + r.url" class="hover:bg-drac-current/20 transition">
                            <td class="px-5 py-2.5">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded" :class="methodClass(r.method)">@{{ r.method }}</span>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-drac-fg">@{{ r.url }}</td>
                            <td class="text-right px-4 py-2.5 text-drac-fg text-xs font-bold">@{{ r.count }}</td>
                            <td class="text-right px-4 py-2.5"><span class="text-xs font-bold" :class="durationColor(r.p50)">@{{ r.p50 }}ms</span></td>
                            <td class="text-right px-4 py-2.5"><span class="text-xs font-bold" :class="durationColor(r.p95)">@{{ r.p95 }}ms</span></td>
                            <td class="text-right px-4 py-2.5"><span class="text-xs font-bold" :class="durationColor(r.p99)">@{{ r.p99 }}ms</span></td>
                            <td class="text-right px-4 py-2.5 text-drac-cyan text-xs font-bold">@{{ r.throughput_rpm }}<span class="text-[10px] text-drac-comment font-normal ml-0.5">rpm</span></td>
                            <td class="text-right px-4 py-2.5"><span class="text-xs font-bold" :class="r.error_rate === 0 ? 'text-drac-comment' : r.error_rate < 5 ? 'text-drac-orange' : 'text-drac-red'">@{{ r.error_rate }}%</span></td>
                            <td class="text-right px-4 py-2.5 text-drac-purple text-xs font-bold">@{{ r.avg_queries }}</td>
                            <td class="text-right px-5 py-2.5 text-drac-cyan text-xs font-bold">@{{ r.avg_memory }}<span class="text-[10px] text-drac-comment font-normal ml-0.5">MB</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="routes.length === 0" class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
            <p class="text-drac-comment text-sm">No performance data in this report.</p>
        </div>

    </main>
</div>

<script>
const DATA = {!! json_encode($data) !!};
const { createApp } = Vue;
createApp({
    data() {
        return {
            routes: DATA.routes,
            global: DATA.global || {},
            sortField: 'p95',
            sortDir: 'desc',
        };
    },
    computed: {
        sortedRoutes() {
            const field = this.sortField;
            const dir = this.sortDir === 'asc' ? 1 : -1;
            return [...this.routes].sort((a, b) => {
                const av = a[field];
                const bv = b[field];
                if (typeof av === 'string') return dir * av.localeCompare(bv);
                return dir * (av - bv);
            });
        },
    },
    methods: {
        sortBy(field) {
            if (this.sortField === field) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDir = 'desc';
            }
        },
        durationColor(ms) {
            if (ms < 100) return 'text-drac-green';
            if (ms < 500) return 'text-drac-orange';
            return 'text-drac-red';
        },
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
