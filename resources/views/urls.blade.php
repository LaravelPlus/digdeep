@extends('digdeep::layout')

@section('title', 'Discovered URLs')

@section('content')
<div id="digdeep-urls" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Discovered URLs</h1>
            <p class="text-drac-comment text-xs mt-1">All routes discovered via auto-profiling and manual triggers.</p>
        </div>
    </div>

    @if(empty($topRoutes))
        <div class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-drac-current flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-drac-comment" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.061a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
            </div>
            <p class="text-drac-fg text-sm font-medium mb-1">No URLs discovered yet</p>
            <p class="text-drac-comment text-xs">Browse your app with auto-profiling enabled, or trigger URLs manually.</p>
        </div>
    @else
        @php
            $totalVisits = array_sum(array_column($topRoutes, 'visit_count'));
            $maxVisits = $topRoutes[0]['visit_count'] ?? 1;
            $methods = array_unique(array_column($topRoutes, 'method'));
            sort($methods);
        @endphp

        {{-- Stats cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Unique Routes</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-purple/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-purple" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.061a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-drac-fg leading-none">{{ count($topRoutes) }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Total Visits</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-green/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-drac-green leading-none">{{ number_format($totalVisits) }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">HTTP Methods</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-cyan/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-cyan" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 mt-1">
                    @foreach($methods as $m)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded
                        {{ $m === 'GET' ? 'bg-drac-green/10 text-drac-green' : '' }}
                        {{ $m === 'POST' ? 'bg-drac-cyan/10 text-drac-cyan' : '' }}
                        {{ in_array($m, ['PUT', 'PATCH']) ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                        {{ $m === 'DELETE' ? 'bg-drac-red/10 text-drac-red' : '' }}
                    ">{{ $m }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- URL list --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
            <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2.5">
                <h2 class="text-drac-fg text-sm font-semibold">All Discovered Routes</h2>
                <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">{{ count($topRoutes) }}</span>
                <div class="ml-auto flex items-center gap-2">
                    {{-- Method filter --}}
                    <div class="flex items-center gap-1">
                        <button @click="methodFilter = ''" :class="methodFilter === '' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                            class="text-[10px] font-bold px-2 py-1 rounded-md transition">All</button>
                        <button v-for="m in availableMethods" :key="m" @click="methodFilter = m"
                            :class="methodFilter === m ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                            class="text-[10px] font-bold px-2 py-1 rounded-md transition">@{{ m }}</button>
                    </div>
                    {{-- Sort --}}
                    <select v-model="sortBy" class="bg-drac-bg border border-drac-border text-drac-fg rounded-md px-2 py-1 text-[11px] focus:border-drac-purple focus:outline-none cursor-pointer">
                        <option value="visits_desc">Most Visited</option>
                        <option value="visits_asc">Least Visited</option>
                        <option value="recent">Most Recent</option>
                        <option value="url_asc">URL A-Z</option>
                    </select>
                    {{-- Search --}}
                    <div class="relative">
                        <svg class="w-3.5 h-3.5 text-drac-comment absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                        <input v-model="search" type="text" placeholder="Filter URLs..."
                            class="w-44 bg-drac-bg border border-drac-border text-drac-fg rounded-md pl-8 pr-3 py-1 text-[11px] focus:border-drac-purple focus:outline-none focus:ring-1 focus:ring-drac-purple/20 placeholder-drac-comment transition">
                    </div>
                </div>
            </div>
            <div class="divide-y divide-drac-border/60">
                <template v-for="(rv, i) in filteredRoutes" :key="rv.url + rv.method">
                <div>
                    <div @click="toggleRoute(rv)" class="px-5 py-2.5 hover:bg-drac-current/30 transition relative cursor-pointer select-none">
                        <div class="absolute inset-y-0 left-0 bg-drac-purple/5" :style="'width:' + (rv.visit_count / {{ $maxVisits }}) * 100 + '%'"></div>
                        <div class="relative flex items-center gap-3">
                            <svg class="w-3.5 h-3.5 text-drac-comment shrink-0 transition-transform duration-200" :class="{ 'rotate-90': expandedRoute === rv.method + ' ' + rv.url }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                            <span class="text-drac-comment text-[11px] font-bold w-6 shrink-0 text-right">@{{ i + 1 }}</span>
                            <span class="inline-flex items-center justify-center w-[48px] shrink-0 py-0.5 rounded-md text-[10px] font-bold tracking-wide"
                                :class="{
                                    'bg-drac-green/10 text-drac-green': rv.method === 'GET',
                                    'bg-drac-cyan/10 text-drac-cyan': rv.method === 'POST',
                                    'bg-drac-orange/10 text-drac-orange': rv.method === 'PUT' || rv.method === 'PATCH',
                                    'bg-drac-red/10 text-drac-red': rv.method === 'DELETE',
                                }">@{{ rv.method }}</span>
                            <span class="flex-1 min-w-0 text-drac-fg text-sm font-medium truncate font-mono">@{{ rv.url }}</span>
                            <div class="shrink-0 flex items-center gap-4">
                                <div class="text-center">
                                    <span class="text-drac-purple text-sm font-extrabold">@{{ rv.visit_count }}</span>
                                    <span class="text-drac-comment text-xs ml-1">visits</span>
                                </div>
                                <span class="text-drac-comment text-[11px]">@{{ rv.last_visited_at }}</span>
                            </div>
                        </div>
                    </div>
                    {{-- Expanded profiles section --}}
                    <div v-if="expandedRoute === rv.method + ' ' + rv.url" class="bg-drac-bg/50 border-t border-drac-border/40">
                        <div v-if="expandedLoading" class="px-8 py-4 space-y-2">
                            <div v-for="n in 3" :key="n" class="flex items-center gap-4 animate-pulse">
                                <div class="w-10 h-4 bg-drac-current rounded"></div>
                                <div class="w-16 h-4 bg-drac-current rounded"></div>
                                <div class="flex-1 h-4 bg-drac-current rounded"></div>
                                <div class="w-20 h-4 bg-drac-current rounded"></div>
                            </div>
                        </div>
                        <div v-else-if="expandedProfiles.length === 0" class="px-8 py-4 text-center">
                            <p class="text-drac-comment text-xs">No individual profiles found for this route.</p>
                        </div>
                        <div v-else>
                            <div class="px-8 py-1.5 flex items-center gap-4 text-[10px] font-semibold uppercase tracking-wider text-drac-comment">
                                <span class="w-12">Status</span>
                                <span class="w-20">Duration</span>
                                <span class="w-16">Queries</span>
                                <span class="w-20">Memory</span>
                                <span class="flex-1">Timestamp</span>
                            </div>
                            <a v-for="p in expandedProfiles" :key="p.id" :href="'/digdeep/profile/' + p.id"
                                class="px-8 py-1.5 flex items-center gap-4 hover:bg-drac-current/20 transition text-xs">
                                <span class="w-12 font-bold"
                                    :class="{
                                        'text-drac-green': p.status_code >= 200 && p.status_code < 300,
                                        'text-drac-orange': p.status_code >= 300 && p.status_code < 400,
                                        'text-drac-red': p.status_code >= 400,
                                    }">@{{ p.status_code }}</span>
                                <span class="w-20 text-drac-fg font-mono">@{{ p.duration_ms.toFixed(1) }}ms</span>
                                <span class="w-16 text-drac-comment">@{{ p.query_count }} q</span>
                                <span class="w-20 text-drac-comment">@{{ p.memory_peak_mb.toFixed(1) }}MB</span>
                                <span class="flex-1 text-drac-comment truncate">@{{ p.created_at }}</span>
                            </a>
                        </div>
                    </div>
                </div>
                </template>
                <div v-if="filteredRoutes.length === 0" class="px-5 py-10 text-center">
                    <p class="text-drac-comment text-sm">No routes match your search.</p>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return {
            search: '',
            methodFilter: '',
            sortBy: 'visits_desc',
            routes: @json($topRoutes ?? []),
            expandedRoute: null,
            expandedProfiles: [],
            expandedLoading: false,
        };
    },
    methods: {
        toggleRoute(rv) {
            const key = rv.method + ' ' + rv.url;
            if (this.expandedRoute === key) {
                this.expandedRoute = null;
                this.expandedProfiles = [];
                return;
            }
            this.expandedRoute = key;
            this.expandedProfiles = [];
            this.expandedLoading = true;
            fetch('/digdeep/api/profiles?route=' + encodeURIComponent(rv.url) + '&method=' + encodeURIComponent(rv.method) + '&per_page=20')
                .then(r => r.json())
                .then(data => {
                    this.expandedProfiles = data.profiles || [];
                    this.expandedLoading = false;
                })
                .catch(() => {
                    this.expandedProfiles = [];
                    this.expandedLoading = false;
                });
        },
    },
    computed: {
        availableMethods() {
            return [...new Set(this.routes.map(r => r.method))].sort();
        },
        filteredRoutes() {
            let list = this.routes;
            if (this.methodFilter) {
                list = list.filter(r => r.method === this.methodFilter);
            }
            if (this.search) {
                const q = this.search.toLowerCase();
                list = list.filter(r => r.url.toLowerCase().includes(q) || r.method.toLowerCase().includes(q));
            }
            list = [...list];
            if (this.sortBy === 'visits_desc') list.sort((a, b) => b.visit_count - a.visit_count);
            else if (this.sortBy === 'visits_asc') list.sort((a, b) => a.visit_count - b.visit_count);
            else if (this.sortBy === 'recent') list.sort((a, b) => b.last_visited_at.localeCompare(a.last_visited_at));
            else if (this.sortBy === 'url_asc') list.sort((a, b) => a.url.localeCompare(b.url));
            return list;
        }
    }
}).mount('#digdeep-urls');
</script>
@endsection
