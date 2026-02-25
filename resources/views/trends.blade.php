@extends('digdeep::layout')

@section('title', 'Trends')

@section('content')
<div id="digdeep-trends" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Performance Trends</h1>
            <p class="text-drac-comment text-xs mt-1">Track performance over time per route.</p>
        </div>
        <div class="flex items-center gap-3">
            <select v-model="route" @change="loadData()" class="bg-drac-current text-drac-fg text-xs font-mono rounded-lg border border-drac-border px-3 py-1.5 focus:outline-none focus:border-drac-purple min-w-[200px]">
                <option value="">All routes</option>
                <option v-for="r in routes" :key="r" :value="r">@{{ r }}</option>
            </select>
            <div class="flex items-center gap-1">
                <button v-for="r in ['hour','day','week','all']" :key="r" @click="range = r; loadData()"
                    class="text-[10px] font-bold px-2.5 py-1 rounded-md transition"
                    :class="range === r ? 'bg-drac-purple/20 text-drac-purple' : 'text-drac-comment hover:text-drac-fg hover:bg-drac-current'">
                    @{{ r === 'all' ? 'All' : r.charAt(0).toUpperCase() + r.slice(1) }}
                </button>
            </div>
        </div>
    </div>

    {{-- Stats cards --}}
    <div v-if="stats && stats.count > 0" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Avg Duration</div>
            <div class="text-2xl font-extrabold text-drac-green leading-none">@{{ stats.avg_duration }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">P95 Duration</div>
            <div class="text-2xl font-extrabold text-drac-orange leading-none">@{{ stats.p95_duration }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Avg Memory</div>
            <div class="text-2xl font-extrabold text-drac-cyan leading-none">@{{ stats.avg_memory }}<span class="text-xs text-drac-comment font-semibold ml-0.5">MB</span></div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Avg Queries</div>
            <div class="text-2xl font-extrabold text-drac-purple leading-none">@{{ stats.avg_queries }}</div>
            <div class="text-drac-comment text-[10px] mt-1">@{{ stats.count }} profiles</div>
        </div>
    </div>

    {{-- Duration chart --}}
    <div v-if="series.length > 1" class="space-y-4 mb-6">
        <div class="bg-drac-surface rounded-xl border border-drac-border p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-drac-fg text-sm font-semibold">Duration Over Time</h2>
                <div class="flex items-center gap-2 text-[10px]">
                    <span class="text-drac-comment">Min: @{{ stats.min_duration }}ms</span>
                    <span class="text-drac-comment">Max: @{{ stats.max_duration }}ms</span>
                </div>
            </div>
            <svg :viewBox="'0 0 ' + chartWidth + ' ' + chartHeight" class="w-full" style="height: 140px">
                <polyline :points="durationPoints" fill="none" stroke="var(--color-drac-green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle v-for="(pt, i) in durationCoords" :key="'d-'+i" :cx="pt.x" :cy="pt.y" r="3" fill="var(--color-drac-green)" class="opacity-0 hover:opacity-100 transition cursor-pointer">
                    <title>@{{ series[i].duration_ms.toFixed(1) }}ms — @{{ series[i].created_at }}</title>
                </circle>
            </svg>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-5">
                <h2 class="text-drac-fg text-sm font-semibold mb-3">Memory Over Time</h2>
                <svg :viewBox="'0 0 ' + chartWidth + ' ' + chartHeight" class="w-full" style="height: 100px">
                    <polyline :points="memoryPoints" fill="none" stroke="var(--color-drac-cyan)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-5">
                <h2 class="text-drac-fg text-sm font-semibold mb-3">Query Count Over Time</h2>
                <svg :viewBox="'0 0 ' + chartWidth + ' ' + chartHeight" class="w-full" style="height: 100px">
                    <polyline :points="queryPoints" fill="none" stroke="var(--color-drac-purple)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Data table --}}
    <div v-if="series.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
        <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2">
            <h2 class="text-drac-fg text-sm font-semibold">Profile Data</h2>
            <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">@{{ series.length }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-drac-border">
                        <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Time</th>
                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Duration</th>
                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Memory</th>
                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2">Queries</th>
                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2">Query Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-drac-border/60">
                    <tr v-for="s in series" :key="s.id" class="hover:bg-drac-current/30 transition cursor-pointer" @click="window.location.href='/digdeep/profile/' + s.id">
                        <td class="px-5 py-2"><span class="text-drac-fg text-xs font-mono">@{{ s.created_at }}</span></td>
                        <td class="text-right px-4 py-2"><span class="text-xs font-bold" :class="s.duration_ms < 100 ? 'text-drac-green' : s.duration_ms < 500 ? 'text-drac-orange' : 'text-drac-red'">@{{ s.duration_ms.toFixed(1) }}ms</span></td>
                        <td class="text-right px-4 py-2"><span class="text-drac-cyan text-xs font-bold">@{{ s.memory_peak_mb.toFixed(1) }}MB</span></td>
                        <td class="text-right px-4 py-2"><span class="text-drac-purple text-xs font-bold">@{{ s.query_count }}</span></td>
                        <td class="text-right px-5 py-2"><span class="text-drac-comment text-xs font-mono">@{{ (s.query_time_ms || 0).toFixed(1) }}ms</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="series.length === 0 && !loading" class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
        <svg class="w-12 h-12 text-drac-comment/30 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
        <p class="text-drac-comment text-sm">No trend data yet. Profile some routes first.</p>
    </div>
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return {
            route: '',
            range: 'all',
            series: [],
            stats: {},
            routes: [],
            loading: false,
            chartWidth: 600,
            chartHeight: 120,
        };
    },
    computed: {
        durationPoints() { return this.buildPoints(this.series.map(s => s.duration_ms)); },
        memoryPoints() { return this.buildPoints(this.series.map(s => s.memory_peak_mb)); },
        queryPoints() { return this.buildPoints(this.series.map(s => s.query_count)); },
        durationCoords() { return this.buildCoords(this.series.map(s => s.duration_ms)); },
    },
    mounted() {
        this.loadData();
    },
    methods: {
        async loadData() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.route) params.set('route', this.route);
                if (this.range) params.set('range', this.range);
                const r = await fetch('/digdeep/api/trends?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.series = d.series || [];
                this.stats = d.stats || {};
                this.routes = d.routes || [];
            } catch(e) {
                console.error('Failed to load trends', e);
            } finally {
                this.loading = false;
            }
        },
        buildPoints(values) {
            if (values.length < 2) return '';
            const max = Math.max(...values) || 1;
            const min = Math.min(...values);
            const range = max - min || 1;
            const pad = 10;
            return values.map((v, i) => {
                const x = pad + (i / (values.length - 1)) * (this.chartWidth - pad * 2);
                const y = pad + (1 - (v - min) / range) * (this.chartHeight - pad * 2);
                return x.toFixed(1) + ',' + y.toFixed(1);
            }).join(' ');
        },
        buildCoords(values) {
            if (values.length < 2) return [];
            const max = Math.max(...values) || 1;
            const min = Math.min(...values);
            const range = max - min || 1;
            const pad = 10;
            return values.map((v, i) => ({
                x: pad + (i / (values.length - 1)) * (this.chartWidth - pad * 2),
                y: pad + (1 - (v - min) / range) * (this.chartHeight - pad * 2),
            }));
        },
    },
}).mount('#digdeep-trends');
</script>
@endsection
