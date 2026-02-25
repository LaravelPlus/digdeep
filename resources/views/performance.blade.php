@extends('digdeep::layout')

@section('title', 'Performance')

@section('content')
<div id="digdeep-performance" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Performance</h1>
            <p class="text-drac-comment text-xs mt-1">Per-route percentiles, throughput, and error rates.</p>
        </div>
        <div class="flex items-center gap-1">
            <button v-for="r in ['hour','day','week','all']" :key="r" @click="range = r; loadData()"
                class="text-[10px] font-bold px-2.5 py-1 rounded-md transition"
                :class="range === r ? 'bg-drac-purple/20 text-drac-purple' : 'text-drac-comment hover:text-drac-fg hover:bg-drac-current'">
                @{{ r === 'all' ? 'All' : r.charAt(0).toUpperCase() + r.slice(1) }}
            </button>
        </div>
    </div>

    {{-- Global stats bar --}}
    <div v-if="global.total" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Total Requests</div>
            <div class="text-2xl font-extrabold text-drac-fg leading-none">@{{ global.total }}</div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">P95 Duration</div>
            <div class="text-2xl font-extrabold leading-none" :class="durationColor(global.p95)">@{{ global.p95 }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Throughput</div>
            <div class="text-2xl font-extrabold text-drac-cyan leading-none">@{{ global.throughput_rpm }}<span class="text-xs text-drac-comment font-semibold ml-0.5">req/min</span></div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Error Rate</div>
            <div class="text-2xl font-extrabold leading-none" :class="errorColor(global.error_rate)">@{{ global.error_rate }}<span class="text-xs text-drac-comment font-semibold ml-0.5">%</span></div>
        </div>
    </div>

    {{-- Route table --}}
    <div v-if="routes.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
        <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2">
            <h2 class="text-drac-fg text-sm font-semibold">Routes</h2>
            <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">@{{ routes.length }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-drac-border">
                        <th @click="sortBy('method')" class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2 cursor-pointer hover:text-drac-fg transition">Method</th>
                        <th @click="sortBy('url')" class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 cursor-pointer hover:text-drac-fg transition">Route</th>
                        <th @click="sortBy('count')" class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 cursor-pointer hover:text-drac-fg transition">Requests</th>
                        <th @click="sortBy('p50')" class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 cursor-pointer hover:text-drac-fg transition">P50</th>
                        <th @click="sortBy('p95')" class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 cursor-pointer hover:text-drac-fg transition">P95</th>
                        <th @click="sortBy('p99')" class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 cursor-pointer hover:text-drac-fg transition">P99</th>
                        <th @click="sortBy('throughput_rpm')" class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 cursor-pointer hover:text-drac-fg transition">Throughput</th>
                        <th @click="sortBy('error_rate')" class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 cursor-pointer hover:text-drac-fg transition">Error Rate</th>
                        <th @click="sortBy('avg_queries')" class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-2 cursor-pointer hover:text-drac-fg transition">Avg Queries</th>
                        <th @click="sortBy('avg_memory')" class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-2 cursor-pointer hover:text-drac-fg transition">Avg Memory</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-drac-border/60">
                    <tr v-for="r in sortedRoutes" :key="r.method + r.url"
                        class="hover:bg-drac-current/30 transition cursor-pointer"
                        @click="goToTrends(r)">
                        <td class="px-5 py-2">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded"
                                :class="r.method === 'GET' ? 'bg-drac-green/15 text-drac-green' : r.method === 'POST' ? 'bg-drac-orange/15 text-drac-orange' : 'bg-drac-purple/15 text-drac-purple'">
                                @{{ r.method }}
                            </span>
                        </td>
                        <td class="px-4 py-2"><span class="text-drac-fg text-xs font-mono">@{{ r.url }}</span></td>
                        <td class="text-right px-4 py-2"><span class="text-drac-fg text-xs font-bold">@{{ r.count }}</span></td>
                        <td class="text-right px-4 py-2"><span class="text-xs font-bold" :class="durationColor(r.p50)">@{{ r.p50 }}<span class="text-drac-comment font-normal">ms</span></span></td>
                        <td class="text-right px-4 py-2"><span class="text-xs font-bold" :class="durationColor(r.p95)">@{{ r.p95 }}<span class="text-drac-comment font-normal">ms</span></span></td>
                        <td class="text-right px-4 py-2"><span class="text-xs font-bold" :class="durationColor(r.p99)">@{{ r.p99 }}<span class="text-drac-comment font-normal">ms</span></span></td>
                        <td class="text-right px-4 py-2"><span class="text-drac-cyan text-xs font-bold">@{{ r.throughput_rpm }}<span class="text-drac-comment font-normal">/min</span></span></td>
                        <td class="text-right px-4 py-2"><span class="text-xs font-bold" :class="errorColor(r.error_rate)">@{{ r.error_rate }}%</span></td>
                        <td class="text-right px-4 py-2"><span class="text-drac-purple text-xs font-bold">@{{ r.avg_queries }}</span></td>
                        <td class="text-right px-5 py-2"><span class="text-drac-comment text-xs font-mono">@{{ r.avg_memory }}MB</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="routes.length === 0 && !loading" class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
        <svg class="w-12 h-12 text-drac-comment/30 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
        <p class="text-drac-comment text-sm">No performance data yet. Profile some routes first.</p>
    </div>
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return {
            routes: @json($routes),
            global: @json($global),
            range: 'all',
            loading: false,
            sortKey: 'p95',
            sortDir: 'desc',
        };
    },
    computed: {
        sortedRoutes() {
            return [...this.routes].sort((a, b) => {
                let va = a[this.sortKey];
                let vb = b[this.sortKey];
                if (typeof va === 'string') {
                    return this.sortDir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
                }
                return this.sortDir === 'asc' ? va - vb : vb - va;
            });
        },
    },
    methods: {
        async loadData() {
            this.loading = true;
            try {
                const r = await fetch('/digdeep/api/performance?range=' + this.range, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.routes = d.routes || [];
                this.global = d.global || {};
            } catch(e) {
                console.error('Failed to load performance data', e);
            } finally {
                this.loading = false;
            }
        },
        sortBy(key) {
            if (this.sortKey === key) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDir = 'desc';
            }
        },
        durationColor(ms) {
            if (ms < 100) return 'text-drac-green';
            if (ms < 500) return 'text-drac-orange';
            return 'text-drac-red';
        },
        errorColor(rate) {
            if (rate < 1) return 'text-drac-green';
            if (rate <= 5) return 'text-drac-orange';
            return 'text-drac-red';
        },
        goToTrends(route) {
            window.location.href = '/digdeep/trends?route=' + encodeURIComponent(route.url);
        },
    },
}).mount('#digdeep-performance');
</script>
@endsection
