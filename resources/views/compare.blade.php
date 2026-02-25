@extends('digdeep::layout')

@section('title', 'Compare')

@section('content')
<div id="digdeep-compare" v-cloak>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Compare Profiles</h1>
            <p class="text-drac-comment text-xs mt-1">Select two profiles to compare side by side.</p>
        </div>
    </div>

    {{-- Profile selectors --}}
    <div class="grid grid-cols-2 gap-4 mb-5">
        <div>
            <label class="text-drac-comment text-[10px] uppercase font-bold tracking-wider block mb-1.5">Profile A</label>
            <select v-model="profileA" class="w-full bg-drac-current text-drac-fg text-xs font-mono rounded-lg border border-drac-border px-3 py-2 focus:outline-none focus:border-drac-purple">
                <option value="">Select a profile...</option>
                <option v-for="p in allProfiles" :key="'a-'+p.id" :value="p.id">
                    @{{ p.method }} @{{ p.url }} — @{{ p.status_code }} — @{{ parseFloat(p.duration_ms).toFixed(1) }}ms — @{{ p.created_at }}
                </option>
            </select>
        </div>
        <div>
            <label class="text-drac-comment text-[10px] uppercase font-bold tracking-wider block mb-1.5">Profile B</label>
            <select v-model="profileB" class="w-full bg-drac-current text-drac-fg text-xs font-mono rounded-lg border border-drac-border px-3 py-2 focus:outline-none focus:border-drac-purple">
                <option value="">Select a profile...</option>
                <option v-for="p in allProfiles" :key="'b-'+p.id" :value="p.id">
                    @{{ p.method }} @{{ p.url }} — @{{ p.status_code }} — @{{ parseFloat(p.duration_ms).toFixed(1) }}ms — @{{ p.created_at }}
                </option>
            </select>
        </div>
    </div>

    {{-- Comparison --}}
    <div v-if="dataA && dataB">
        {{-- Summary comparison --}}
        <div class="grid grid-cols-2 gap-4 mb-5">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded" :class="methodClass(dataA.method)">@{{ dataA.method }}</span>
                    <span class="text-drac-fg text-sm font-mono font-semibold truncate">@{{ dataA.url }}</span>
                    <span class="text-sm font-bold ml-auto" :class="dataA.status_code < 300 ? 'text-drac-green' : 'text-drac-red'">@{{ dataA.status_code }}</span>
                </div>
                <div class="text-drac-comment text-[10px]">@{{ dataA.created_at }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded" :class="methodClass(dataB.method)">@{{ dataB.method }}</span>
                    <span class="text-drac-fg text-sm font-mono font-semibold truncate">@{{ dataB.url }}</span>
                    <span class="text-sm font-bold ml-auto" :class="dataB.status_code < 300 ? 'text-drac-green' : 'text-drac-red'">@{{ dataB.status_code }}</span>
                </div>
                <div class="text-drac-comment text-[10px]">@{{ dataB.created_at }}</div>
            </div>
        </div>

        {{-- Metrics comparison with percentage --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-drac-border">
                <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Performance Comparison</span>
            </div>
            <div class="divide-y divide-drac-border/50">
                <div class="px-5 py-2.5 grid grid-cols-[1fr_100px_100px_80px_80px] items-center gap-4 text-[10px] text-drac-comment uppercase font-bold tracking-wider">
                    <span>Metric</span>
                    <span class="text-right">A</span>
                    <span class="text-right">B</span>
                    <span class="text-right">Diff</span>
                    <span class="text-right">Change</span>
                </div>
                <div v-for="metric in metrics" :key="metric.label" class="px-5 py-3 grid grid-cols-[1fr_100px_100px_80px_80px] items-center gap-4">
                    <span class="text-drac-fg text-sm font-medium">@{{ metric.label }}</span>
                    <span class="text-drac-fg text-sm font-mono text-right">@{{ metric.a }}</span>
                    <span class="text-drac-fg text-sm font-mono text-right">@{{ metric.b }}</span>
                    <span class="text-sm font-bold font-mono text-right" :class="metric.diffClass">@{{ metric.diff }}</span>
                    <span class="text-xs font-bold font-mono text-right" :class="metric.diffClass">@{{ metric.pct }}</span>
                </div>
            </div>
        </div>

        {{-- Lifecycle phase comparison --}}
        <div v-if="phasesA.length > 0 || phasesB.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-drac-border">
                <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Lifecycle Phases</span>
            </div>
            <div class="p-5">
                <div class="space-y-3">
                    <div v-for="name in ['bootstrap', 'routing', 'controller', 'view', 'response']" :key="name" class="flex items-center gap-3">
                        <span class="text-drac-fg text-xs font-medium w-20 capitalize shrink-0">@{{ name }}</span>
                        <div class="flex-1 flex items-center gap-2">
                            <div class="flex-1 h-2 bg-drac-current rounded-full overflow-hidden">
                                <div class="h-full rounded-full opacity-70" :class="phaseColorClass(name)"
                                    :style="'width:' + phaseBarPct(phasesA, name) + '%'"></div>
                            </div>
                            <span class="text-[10px] font-mono text-drac-comment w-14 text-right">@{{ phaseMs(phasesA, name) }}ms</span>
                        </div>
                        <div class="flex-1 flex items-center gap-2">
                            <div class="flex-1 h-2 bg-drac-current rounded-full overflow-hidden">
                                <div class="h-full rounded-full opacity-70" :class="phaseColorClass(name)"
                                    :style="'width:' + phaseBarPct(phasesB, name) + '%'"></div>
                            </div>
                            <span class="text-[10px] font-mono text-drac-comment w-14 text-right">@{{ phaseMs(phasesB, name) }}ms</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Queries, Views, Cache, Events side-by-side --}}
        <div class="grid grid-cols-2 gap-4 mb-5">
            {{-- Queries A --}}
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                <div class="px-5 py-2.5 border-b border-drac-border flex items-center justify-between">
                    <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Queries (A)</span>
                    <span class="text-drac-purple text-[10px] font-bold">@{{ queriesA.length }}</span>
                </div>
                <div class="divide-y divide-drac-border/30 max-h-[400px] overflow-y-auto">
                    <div v-for="(q, qi) in queriesA" :key="'qa-'+qi" class="px-5 py-2.5"
                        :class="isQueryUnique(q.sql, 'A') ? 'bg-drac-red/5' : ''">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-drac-comment text-[10px] font-bold">#@{{ qi + 1 }}</span>
                                <span v-if="isQueryUnique(q.sql, 'A')" class="text-[9px] font-bold text-drac-red bg-drac-red/10 px-1 py-0.5 rounded">ONLY IN A</span>
                            </div>
                            <span class="text-[10px] font-bold font-mono" :class="q.time_ms > 10 ? 'text-drac-orange' : 'text-drac-green'">@{{ q.time_ms.toFixed(2) }}ms</span>
                        </div>
                        <code class="text-drac-cyan text-[10px] font-mono break-all block">@{{ q.sql }}</code>
                    </div>
                    <div v-if="!queriesA.length" class="p-4 text-center text-drac-comment text-xs">No queries</div>
                </div>
            </div>
            {{-- Queries B --}}
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                <div class="px-5 py-2.5 border-b border-drac-border flex items-center justify-between">
                    <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Queries (B)</span>
                    <span class="text-drac-purple text-[10px] font-bold">@{{ queriesB.length }}</span>
                </div>
                <div class="divide-y divide-drac-border/30 max-h-[400px] overflow-y-auto">
                    <div v-for="(q, qi) in queriesB" :key="'qb-'+qi" class="px-5 py-2.5"
                        :class="isQueryUnique(q.sql, 'B') ? 'bg-drac-green/5' : ''">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-drac-comment text-[10px] font-bold">#@{{ qi + 1 }}</span>
                                <span v-if="isQueryUnique(q.sql, 'B')" class="text-[9px] font-bold text-drac-green bg-drac-green/10 px-1 py-0.5 rounded">ONLY IN B</span>
                            </div>
                            <span class="text-[10px] font-bold font-mono" :class="q.time_ms > 10 ? 'text-drac-orange' : 'text-drac-green'">@{{ q.time_ms.toFixed(2) }}ms</span>
                        </div>
                        <code class="text-drac-cyan text-[10px] font-mono break-all block">@{{ q.sql }}</code>
                    </div>
                    <div v-if="!queriesB.length" class="p-4 text-center text-drac-comment text-xs">No queries</div>
                </div>
            </div>
        </div>

        {{-- Views / Cache / Events diff --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                <div class="px-4 py-2.5 border-b border-drac-border flex items-center justify-between">
                    <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Views</span>
                    <span class="text-drac-comment text-[10px] font-bold">@{{ viewsA.length }} / @{{ viewsB.length }}</span>
                </div>
                <div class="p-3 max-h-[200px] overflow-y-auto text-[10px] font-mono space-y-1">
                    <div v-for="v in viewDiff" :key="v.name" class="flex items-center gap-1.5">
                        <span class="w-3 text-center font-bold" :class="v.status === 'both' ? 'text-drac-comment' : v.status === 'A' ? 'text-drac-red' : 'text-drac-green'">@{{ v.status === 'both' ? '=' : v.status === 'A' ? '-' : '+' }}</span>
                        <span class="text-drac-fg truncate">@{{ v.name }}</span>
                    </div>
                    <div v-if="!viewDiff.length" class="text-drac-comment text-center py-2">No views</div>
                </div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                <div class="px-4 py-2.5 border-b border-drac-border flex items-center justify-between">
                    <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Cache Ops</span>
                    <span class="text-drac-comment text-[10px] font-bold">@{{ cacheA.length }} / @{{ cacheB.length }}</span>
                </div>
                <div class="p-3 max-h-[200px] overflow-y-auto text-[10px] font-mono space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-drac-comment">Hits A/B:</span>
                        <span class="text-drac-green font-bold">@{{ cacheA.filter(c => c.type === 'hit').length }} / @{{ cacheB.filter(c => c.type === 'hit').length }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-drac-comment">Misses A/B:</span>
                        <span class="text-drac-red font-bold">@{{ cacheA.filter(c => c.type === 'miss').length }} / @{{ cacheB.filter(c => c.type === 'miss').length }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-drac-comment">Writes A/B:</span>
                        <span class="text-drac-cyan font-bold">@{{ cacheA.filter(c => c.type === 'write').length }} / @{{ cacheB.filter(c => c.type === 'write').length }}</span>
                    </div>
                </div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                <div class="px-4 py-2.5 border-b border-drac-border flex items-center justify-between">
                    <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Events</span>
                    <span class="text-drac-comment text-[10px] font-bold">@{{ eventsA.length }} / @{{ eventsB.length }}</span>
                </div>
                <div class="p-3 max-h-[200px] overflow-y-auto text-[10px] font-mono space-y-1">
                    <div v-for="e in eventDiff" :key="e.name" class="flex items-center gap-1.5">
                        <span class="w-3 text-center font-bold" :class="e.status === 'both' ? 'text-drac-comment' : e.status === 'A' ? 'text-drac-red' : 'text-drac-green'">@{{ e.status === 'both' ? '=' : e.status === 'A' ? '-' : '+' }}</span>
                        <span class="text-drac-fg truncate">@{{ e.name }}</span>
                    </div>
                    <div v-if="!eventDiff.length" class="text-drac-comment text-center py-2">No events</div>
                </div>
            </div>
        </div>
    </div>

    <div v-else-if="!profileA || !profileB" class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
        <svg class="w-12 h-12 text-drac-comment/30 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
        <p class="text-drac-comment text-sm">Select two profiles above to compare them.</p>
    </div>

    <div v-else class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
        <p class="text-drac-comment text-sm">Loading profiles...</p>
    </div>
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return {
            allProfiles: @json($profiles),
            profileA: new URLSearchParams(window.location.search).get('a') || '',
            profileB: new URLSearchParams(window.location.search).get('b') || '',
            dataA: null,
            dataB: null,
        };
    },
    computed: {
        queriesA() { return this.dataA?.data?.queries || []; },
        queriesB() { return this.dataB?.data?.queries || []; },
        viewsA() { return this.dataA?.data?.views || []; },
        viewsB() { return this.dataB?.data?.views || []; },
        cacheA() { return this.dataA?.data?.cache || []; },
        cacheB() { return this.dataB?.data?.cache || []; },
        eventsA() { return this.dataA?.data?.events || []; },
        eventsB() { return this.dataB?.data?.events || []; },
        phasesA() { return this.dataA?.data?.lifecycle || []; },
        phasesB() { return this.dataB?.data?.lifecycle || []; },
        normalizedQueriesA() { return new Set(this.queriesA.map(q => q.sql.replace(/\s+/g, ' ').trim())); },
        normalizedQueriesB() { return new Set(this.queriesB.map(q => q.sql.replace(/\s+/g, ' ').trim())); },
        viewDiff() {
            const namesA = new Set(this.viewsA.map(v => v.name));
            const namesB = new Set(this.viewsB.map(v => v.name));
            const all = [...new Set([...namesA, ...namesB])];
            return all.map(name => ({
                name,
                status: namesA.has(name) && namesB.has(name) ? 'both' : namesA.has(name) ? 'A' : 'B'
            }));
        },
        eventDiff() {
            const namesA = new Set(this.eventsA.map(e => e.event));
            const namesB = new Set(this.eventsB.map(e => e.event));
            const all = [...new Set([...namesA, ...namesB])];
            return all.map(name => ({
                name,
                status: namesA.has(name) && namesB.has(name) ? 'both' : namesA.has(name) ? 'A' : 'B'
            }));
        },
        metrics() {
            if (!this.dataA || !this.dataB) return [];
            const a = this.dataA;
            const b = this.dataB;
            const perfA = a.data?.performance || {};
            const perfB = b.data?.performance || {};

            return [
                this.buildMetric('Status Code', a.status_code, b.status_code, '', false),
                this.buildMetric('Duration', parseFloat(a.duration_ms), parseFloat(b.duration_ms), 'ms', true),
                this.buildMetric('Memory Peak', parseFloat(a.memory_peak_mb), parseFloat(b.memory_peak_mb), 'MB', true),
                this.buildMetric('Query Count', parseInt(a.query_count), parseInt(b.query_count), '', true),
                this.buildMetric('Query Time', parseFloat(perfA.query_time_ms || 0), parseFloat(perfB.query_time_ms || 0), 'ms', true),
                this.buildMetric('Views', this.viewsA.length, this.viewsB.length, '', true),
                this.buildMetric('Events', this.eventsA.length, this.eventsB.length, '', true),
                this.buildMetric('Cache Ops', this.cacheA.length, this.cacheB.length, '', true),
            ];
        },
    },
    watch: {
        profileA() { this.loadData(); },
        profileB() { this.loadData(); },
    },
    mounted() {
        if (this.profileA && this.profileB) this.loadData();
        else if (this.profileA) this.loadSingle('A');
        else if (this.profileB) this.loadSingle('B');
    },
    methods: {
        async loadData() {
            if (!this.profileA || !this.profileB) {
                if (this.profileA) this.loadSingle('A');
                if (this.profileB) this.loadSingle('B');
                return;
            }
            try {
                const r = await fetch('/digdeep/api/compare?a=' + this.profileA + '&b=' + this.profileB, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.dataA = d.a;
                this.dataB = d.b;
            } catch(e) {
                console.error('Failed to load profiles', e);
            }
        },
        async loadSingle(which) {
            const id = which === 'A' ? this.profileA : this.profileB;
            if (!id) { this['data' + which] = null; return; }
            try {
                const r = await fetch('/digdeep/api/compare?a=' + id + '&b=' + id, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this['data' + which] = d.a;
            } catch(e) { console.error('Failed', e); }
        },
        isQueryUnique(sql, side) {
            const norm = sql.replace(/\s+/g, ' ').trim();
            if (side === 'A') return !this.normalizedQueriesB.has(norm);
            return !this.normalizedQueriesA.has(norm);
        },
        phaseMs(phases, name) {
            const p = phases.find(ph => ph.name === name);
            return p ? p.duration_ms.toFixed(1) : '0.0';
        },
        phaseBarPct(phases, name) {
            const p = phases.find(ph => ph.name === name);
            if (!p) return 0;
            const max = Math.max(...phases.map(ph => ph.duration_ms || 0)) || 1;
            return Math.max(2, (p.duration_ms / max) * 100);
        },
        phaseColorClass(name) {
            return { bootstrap: 'bg-drac-cyan', routing: 'bg-drac-yellow', controller: 'bg-drac-purple', view: 'bg-drac-green', response: 'bg-drac-pink' }[name] || 'bg-drac-comment';
        },
        buildMetric(label, a, b, unit, lowerBetter) {
            const diff = b - a;
            const pct = a > 0 ? ((diff / a) * 100) : 0;
            let diffStr = '';
            let pctStr = '';
            let diffClass = 'text-drac-comment';

            if (diff > 0) {
                diffStr = '+' + (typeof a === 'number' && a % 1 !== 0 ? diff.toFixed(1) : diff) + (unit ? ' ' + unit : '');
                pctStr = '+' + pct.toFixed(0) + '%';
                diffClass = lowerBetter ? 'text-drac-red' : 'text-drac-green';
            } else if (diff < 0) {
                diffStr = (typeof a === 'number' && a % 1 !== 0 ? diff.toFixed(1) : diff) + (unit ? ' ' + unit : '');
                pctStr = pct.toFixed(0) + '%';
                diffClass = lowerBetter ? 'text-drac-green' : 'text-drac-red';
            } else {
                diffStr = '=';
                pctStr = '0%';
            }

            return { label, a: a + (unit ? ' ' + unit : ''), b: b + (unit ? ' ' + unit : ''), diff: diffStr, pct: pctStr, diffClass };
        },
        methodClass(method) {
            return { GET: 'bg-drac-green/10 text-drac-green', POST: 'bg-drac-cyan/10 text-drac-cyan', PUT: 'bg-drac-orange/10 text-drac-orange', PATCH: 'bg-drac-orange/10 text-drac-orange', DELETE: 'bg-drac-red/10 text-drac-red' }[method] || 'bg-drac-comment/10 text-drac-comment';
        },
    },
}).mount('#digdeep-compare');
</script>
@endsection
