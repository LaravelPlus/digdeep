@extends('digdeep::layout')

@section('title', 'Dashboard')

@section('content')
<div id="digdeep-app" v-cloak>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Web Profiler</h1>
            <p class="text-drac-comment text-xs mt-1">Profile any route and inspect queries, events, views, cache and more.</p>
        </div>
        @if(count($profiles) > 0)
        <button @click="clearAll()" class="text-drac-comment text-xs font-medium hover:text-drac-red transition flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-drac-red/10">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            Clear All
        </button>
        @endif
    </div>

    {{-- Stats --}}
    @if($stats['total'] > 0)
    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-6">
        <div class="grid grid-cols-3 lg:grid-cols-6 divide-x divide-drac-border">
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Total Profiles</div>
                <div class="text-lg font-extrabold text-drac-fg leading-none">{{ $stats['total'] }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Avg Duration</div>
                <div class="text-lg font-extrabold text-drac-green leading-none">{{ number_format($stats['avg_duration'], 0) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Min Duration</div>
                <div class="text-lg font-extrabold text-drac-cyan leading-none">{{ number_format($stats['min_duration'], 0) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Max Duration</div>
                <div class="text-lg font-extrabold text-drac-red leading-none">{{ number_format($stats['max_duration'], 0) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Avg Queries</div>
                <div class="text-lg font-extrabold text-drac-yellow leading-none">{{ number_format($stats['avg_queries'], 1) }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Avg Memory</div>
                <div class="text-lg font-extrabold text-drac-orange leading-none">{{ number_format($stats['avg_memory'], 1) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">MB</span></div>
            </div>
        </div>
    </div>
    @endif

    {{-- Profile Input --}}
    <div class="bg-drac-surface rounded-xl border border-drac-border p-5 mb-6">
        <form @submit.prevent="triggerProfile" class="flex gap-2.5">
            <select v-model="method" class="bg-drac-elevated border border-drac-border text-drac-fg rounded-lg px-3 py-2.5 text-sm font-bold focus:border-drac-purple focus:outline-none focus:ring-2 focus:ring-drac-purple/20 cursor-pointer">
                <option>GET</option>
                <option>POST</option>
                <option>PUT</option>
                <option>PATCH</option>
                <option>DELETE</option>
            </select>
            <div class="relative flex-1">
                <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-drac-comment pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.061a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
                </div>
                <input
                    type="text"
                    v-model="url"
                    @focus="showSuggestions = searchHistory.length > 0"
                    @blur="hideSuggestions"
                    @keydown.escape="showSuggestions = false"
                    placeholder="Enter path or full URL — e.g. /sport-in-adrenalin"
                    class="w-full bg-drac-elevated border border-drac-border text-drac-fg rounded-lg pl-10 pr-4 py-2.5 text-sm focus:border-drac-purple focus:outline-none focus:ring-2 focus:ring-drac-purple/20 placeholder-drac-comment"
                >
                <div v-show="showSuggestions && filteredHistory.length > 0"
                     class="absolute top-full left-0 right-0 mt-1.5 bg-drac-elevated border border-drac-border rounded-xl shadow-xl shadow-black/30 z-20 overflow-hidden dd-fade">
                    <div class="px-3 py-2 text-drac-comment text-[10px] font-bold uppercase tracking-widest border-b border-drac-border">Recent Searches</div>
                    <button v-for="item in filteredHistory" :key="item" type="button" @mousedown.prevent="url = item; showSuggestions = false"
                            class="w-full px-3.5 py-2.5 text-left text-sm text-drac-fg hover:bg-drac-purple/15 hover:text-drac-purple flex items-center gap-2.5 transition">
                        <svg class="w-3.5 h-3.5 text-drac-comment shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="font-mono text-xs">@{{ item }}</span>
                    </button>
                </div>
            </div>
            <button type="submit" :disabled="loading"
                class="bg-drac-purple text-drac-bg font-semibold px-6 py-2.5 rounded-lg text-sm hover:bg-drac-purple/90 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm flex items-center gap-2 transition">
                <span v-if="!loading" class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg> Profile</span>
                <span v-else class="flex items-center gap-1.5"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Running...</span>
            </button>
        </form>
        <div v-if="error" class="mt-3 bg-drac-red/10 text-drac-red text-sm px-3.5 py-2.5 rounded-lg flex items-center gap-2 font-medium border border-drac-red/20">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span>@{{ error }}</span>
        </div>
    </div>

    {{-- Quick Result --}}
    <div v-if="lastResult" class="dd-fade mb-6">
        <div class="bg-drac-surface rounded-xl border border-drac-green/30 overflow-hidden">
            <div class="bg-drac-green/8 border-b border-drac-green/20 px-5 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-drac-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-drac-green text-sm font-semibold">Profiled</span>
                    <code class="text-drac-cyan text-xs font-mono bg-drac-current px-2 py-0.5 rounded">@{{ lastResult.url }}</code>
                </div>
                <a :href="'/digdeep/profile/' + lastResult.profile_id" class="text-drac-purple text-sm font-semibold hover:text-drac-pink transition flex items-center gap-1">
                    View Details <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                </a>
            </div>
            <div class="grid grid-cols-4 divide-x divide-drac-border">
                <div class="px-5 py-3.5 text-center">
                    <div class="text-xl font-extrabold" :class="lastResult.status < 300 ? 'text-drac-green' : (lastResult.status < 400 ? 'text-drac-orange' : 'text-drac-red')">@{{ lastResult.status }}</div>
                    <div class="text-drac-comment text-[10px] font-medium mt-0.5">Status</div>
                </div>
                <div class="px-5 py-3.5 text-center">
                    <div class="text-xl font-extrabold text-drac-cyan">@{{ lastResult.duration }}<span class="text-[10px] font-semibold text-drac-comment ml-0.5">ms</span></div>
                    <div class="text-drac-comment text-[10px] font-medium mt-0.5">Duration</div>
                </div>
                <div class="px-5 py-3.5 text-center">
                    <div class="text-xl font-extrabold text-drac-purple">@{{ lastResult.queries }}</div>
                    <div class="text-drac-comment text-[10px] font-medium mt-0.5">Queries</div>
                </div>
                <div class="px-5 py-3.5 text-center">
                    <div class="text-xl font-extrabold text-drac-orange">@{{ lastResult.memory }}<span class="text-[10px] font-semibold text-drac-comment ml-0.5">MB</span></div>
                    <div class="text-drac-comment text-[10px] font-medium mt-0.5">Memory</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Routes --}}
    @if(count($topRoutes) > 0)
    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2.5">
            <h2 class="text-drac-fg text-sm font-semibold">Top Routes</h2>
            <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">{{ count($topRoutes) }}</span>
        </div>
        <div class="divide-y divide-drac-border/60">
            @foreach($topRoutes as $rv)
            <div class="flex items-center px-5 py-2.5 gap-4 hover:bg-drac-current/30 transition">
                <span class="inline-flex items-center justify-center w-[48px] shrink-0 py-0.5 rounded-md text-[10px] font-bold tracking-wide
                    {{ $rv['method'] === 'GET' ? 'bg-drac-green/10 text-drac-green' : '' }}
                    {{ $rv['method'] === 'POST' ? 'bg-drac-cyan/10 text-drac-cyan' : '' }}
                    {{ in_array($rv['method'], ['PUT', 'PATCH']) ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                    {{ $rv['method'] === 'DELETE' ? 'bg-drac-red/10 text-drac-red' : '' }}
                ">{{ $rv['method'] }}</span>
                <span class="flex-1 min-w-0 text-drac-fg text-sm font-medium truncate font-mono">{{ $rv['url'] }}</span>
                <span class="shrink-0 text-drac-purple text-sm font-extrabold">{{ $rv['visit_count'] }}</span>
                <span class="shrink-0 text-drac-comment text-xs font-medium">visits</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Profile List --}}
    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
        <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2.5">
            <h2 class="text-drac-fg text-sm font-semibold">History</h2>
            <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[24px] text-center">{{ count($profiles) }}</span>
            <div class="ml-auto flex items-center gap-1">
                <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                    class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">All</button>
                <button @click="filter = 'page'" :class="filter === 'page' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                    class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">Pages</button>
                <button @click="filter = 'ajax'" :class="filter === 'ajax' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                    class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">AJAX</button>
            </div>
        </div>

        @if(count($profiles) === 0)
            <div class="px-5 py-16 text-center">
                <div class="w-14 h-14 rounded-2xl bg-drac-current flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-drac-comment" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                </div>
                <p class="text-drac-fg text-sm font-medium mb-1">No profiles yet</p>
                <p class="text-drac-comment text-xs">Enter a URL above or browse your app with auto-profiling enabled.</p>
            </div>
        @else
            <div class="divide-y divide-drac-border/60">
                <template v-for="p in filteredProfiles" :key="p.id">
                <a :href="'/digdeep/profile/' + p.id" class="flex items-center px-5 py-3 gap-4 hover:bg-drac-current/30 transition group relative">
                    <span class="inline-flex items-center justify-center w-[48px] shrink-0 py-0.5 rounded-md text-[10px] font-bold tracking-wide"
                        :class="{
                            'bg-drac-green/10 text-drac-green': p.method === 'GET',
                            'bg-drac-cyan/10 text-drac-cyan': p.method === 'POST',
                            'bg-drac-orange/10 text-drac-orange': p.method === 'PUT' || p.method === 'PATCH',
                            'bg-drac-red/10 text-drac-red': p.method === 'DELETE'
                        }">@{{ p.method }}</span>

                    <span class="flex-1 min-w-0 text-drac-fg text-sm font-medium truncate group-hover:text-drac-purple transition">@{{ p.url }}</span>

                    <span v-if="p.is_ajax" class="shrink-0 text-[10px] font-bold bg-drac-pink/10 text-drac-pink px-2 py-0.5 rounded-full">XHR</span>

                    <span class="shrink-0 inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" :class="p.status_code < 300 ? 'bg-drac-green' : (p.status_code < 400 ? 'bg-drac-orange' : 'bg-drac-red')"></span>
                        <span class="text-sm font-bold" :class="p.status_code < 300 ? 'text-drac-green' : (p.status_code < 400 ? 'text-drac-orange' : 'text-drac-red')">@{{ p.status_code }}</span>
                    </span>

                    <span class="shrink-0 flex items-center gap-1 text-xs text-drac-comment w-[72px]">
                        <svg class="w-3.5 h-3.5 text-drac-current shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="font-semibold text-drac-fg">@{{ Math.round(p.duration_ms) }}</span>ms
                    </span>
                    <span class="shrink-0 flex items-center gap-1 text-xs text-drac-comment w-[64px]">
                        <svg class="w-3.5 h-3.5 text-drac-current shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375"/></svg>
                        <span class="font-semibold text-drac-fg">@{{ p.query_count }}</span>qry
                    </span>
                    <span class="shrink-0 flex items-center gap-1 text-xs text-drac-comment w-[72px]">
                        <svg class="w-3.5 h-3.5 text-drac-current shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3"/></svg>
                        <span class="font-semibold text-drac-fg">@{{ Number(p.memory_peak_mb).toFixed(1) }}</span>MB
                    </span>

                    <span class="shrink-0 text-drac-comment text-[11px] w-[130px] text-right font-medium">@{{ p.created_at }}</span>

                    <svg class="w-4 h-4 text-drac-current group-hover:text-drac-purple transition shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                </a>
                </template>
            </div>
        @endif
    </div>
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return {
            url: '',
            method: 'GET',
            loading: false,
            error: '',
            lastResult: null,
            searchHistory: [],
            showSuggestions: false,
            filter: 'all',
            profiles: @json($profiles),
        };
    },
    computed: {
        filteredHistory() {
            if (!this.url) return this.searchHistory.slice(0, 10);
            const q = this.url.toLowerCase();
            return this.searchHistory.filter(h => h.toLowerCase().includes(q)).slice(0, 10);
        },
        filteredProfiles() {
            if (this.filter === 'all') return this.profiles;
            if (this.filter === 'ajax') return this.profiles.filter(p => Number(p.is_ajax) === 1);
            return this.profiles.filter(p => Number(p.is_ajax) === 0);
        }
    },
    mounted() {
        try { this.searchHistory = JSON.parse(localStorage.getItem('digdeep_history') || '[]'); } catch { this.searchHistory = []; }
    },
    methods: {
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        hideSuggestions() { setTimeout(() => this.showSuggestions = false, 200); },
        saveToHistory(u) {
            if (!u || u === '/') return;
            this.searchHistory = [u, ...this.searchHistory.filter(h => h !== u)].slice(0, 30);
            localStorage.setItem('digdeep_history', JSON.stringify(this.searchHistory));
        },
        async triggerProfile() {
            if (!this.url) { this.error = 'Enter a URL to profile.'; return; }
            this.loading = true; this.error = ''; this.lastResult = null;
            const u = this.url; this.saveToHistory(u);
            try {
                const r = await fetch('/digdeep/api/trigger', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ url: u, method: this.method })
                });
                const d = await r.json();
                if (d.profile_id) {
                    this.lastResult = { profile_id: d.profile_id, url: u, status: d.status_code, duration: d.duration_ms, queries: d.query_count, memory: d.memory_peak_mb };
                    setTimeout(() => window.location.reload(), 150);
                } else {
                    this.error = 'Profiling failed.';
                }
            } catch(e) { this.error = 'Failed: ' + e.message; } finally { this.loading = false; }
        },
        async clearAll() {
            if (!confirm('Clear all profiles?')) return;
            await fetch('/digdeep/api/clear', { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' } });
            window.location.reload();
        }
    }
}).mount('#digdeep-app');
</script>
@endsection
