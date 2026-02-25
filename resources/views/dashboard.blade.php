@extends('digdeep::layout')

@section('title', 'Dashboard')

@section('content')
<div id="digdeep-app" v-cloak>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Application Monitor</h1>
            <p class="text-drac-comment text-xs mt-1">Real-time performance monitoring and request profiling.</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Time Range --}}
            <div class="flex items-center gap-1 mr-2">
                <button v-for="r in [{key:'hour',label:'1h'},{key:'day',label:'24h'},{key:'week',label:'7d'},{key:'all',label:'All'}]" :key="r.key"
                    @click="timeRange = r.key; loadRangeData()"
                    class="text-[10px] font-bold px-2.5 py-1 rounded-md transition"
                    :class="timeRange === r.key ? 'bg-drac-purple/20 text-drac-purple' : 'text-drac-comment hover:text-drac-fg hover:bg-drac-current'">
                    @{{ r.label }}
                </button>
            </div>
            <button @click="toggleAutoRefresh()" :class="autoRefresh ? 'bg-drac-green/15 text-drac-green border-drac-green/30' : 'text-drac-comment border-drac-border hover:text-drac-fg hover:border-drac-comment'"
                class="text-xs font-medium flex items-center gap-1.5 px-3 py-1.5 rounded-lg border transition">
                <span class="relative flex h-2 w-2">
                    <span v-if="autoRefresh" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-drac-green opacity-75"></span>
                    <span :class="autoRefresh ? 'bg-drac-green' : 'bg-drac-comment'" class="relative inline-flex rounded-full h-2 w-2"></span>
                </span>
                Live
            </button>
            @if(count($profiles) > 0)
            <button @click="exportAll()" class="text-drac-comment text-xs font-medium hover:text-drac-cyan transition flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-drac-cyan/10 border border-transparent hover:border-drac-cyan/20">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export
            </button>
            <button @click="clearAll()" class="text-drac-comment text-xs font-medium hover:text-drac-red transition flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-drac-red/10 border border-transparent hover:border-drac-red/20">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                Clear
            </button>
            @endif
        </div>
    </div>

    {{-- 5 Stat Cards --}}
    <div v-if="globalPerf.total" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        {{-- P95 Duration --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4 group hover:border-drac-green/30 transition">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">P95 Duration</div>
                <div class="w-7 h-7 rounded-lg bg-drac-green/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-drac-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold leading-none" :class="durationColor(globalPerf.p95)">@{{ globalPerf.p95 }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
            <div class="text-drac-comment text-[10px] font-medium mt-1.5">P50: @{{ globalPerf.p50 }}ms &middot; P99: @{{ globalPerf.p99 }}ms</div>
        </div>
        {{-- Throughput --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4 group hover:border-drac-cyan/30 transition">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Throughput</div>
                <div class="w-7 h-7 rounded-lg bg-drac-cyan/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-drac-cyan" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-drac-cyan leading-none">@{{ globalPerf.throughput_rpm }}<span class="text-xs text-drac-comment font-semibold ml-0.5">req/min</span></div>
            <div class="text-drac-comment text-[10px] font-medium mt-1.5">@{{ globalPerf.total }} total requests</div>
        </div>
        {{-- Error Rate --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4 group hover:border-drac-red/30 transition">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Error Rate</div>
                <div class="w-7 h-7 rounded-lg bg-drac-red/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-drac-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold leading-none" :class="errorColor(globalPerf.error_rate)">@{{ globalPerf.error_rate }}<span class="text-xs text-drac-comment font-semibold ml-0.5">%</span></div>
        </div>
        {{-- Avg Memory --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4 group hover:border-drac-orange/30 transition">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Avg Memory</div>
                <div class="w-7 h-7 rounded-lg bg-drac-orange/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-drac-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-drac-orange leading-none">@{{ globalPerf.avg_memory }}<span class="text-xs text-drac-comment font-semibold ml-0.5">MB</span></div>
        </div>
        {{-- Health Score --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4 group hover:border-drac-green/30 transition">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Health Score</div>
                <div class="w-7 h-7 rounded-lg flex items-center justify-center" :class="healthScore >= 80 ? 'bg-drac-green/10' : healthScore >= 50 ? 'bg-drac-orange/10' : 'bg-drac-red/10'">
                    <svg class="w-3.5 h-3.5" :class="healthScore >= 80 ? 'text-drac-green' : healthScore >= 50 ? 'text-drac-orange' : 'text-drac-red'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold leading-none" :class="healthScore >= 80 ? 'text-drac-green' : healthScore >= 50 ? 'text-drac-orange' : 'text-drac-red'">@{{ healthScore }}</div>
            <div class="w-full bg-drac-current rounded-full h-1 mt-2">
                <div class="h-1 rounded-full transition-all" :class="healthScore >= 80 ? 'bg-drac-green' : healthScore >= 50 ? 'bg-drac-orange' : 'bg-drac-red'" :style="'width:' + healthScore + '%'"></div>
            </div>
        </div>
    </div>

    {{-- Response Time Area Chart (Hero) --}}
    <div v-if="trendSeries.length > 1" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-6 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <h2 class="text-drac-fg text-sm font-semibold">Response Time</h2>
                <span class="text-drac-comment text-[10px] font-medium">@{{ trendSeries.length }} requests</span>
            </div>
            <div class="flex items-center gap-3 text-[10px] font-semibold">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-drac-green"></span> <span class="text-drac-comment">&lt;100ms</span></span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-drac-orange"></span> <span class="text-drac-comment">100-500ms</span></span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-drac-red"></span> <span class="text-drac-comment">&gt;500ms</span></span>
            </div>
        </div>
        <svg :viewBox="'0 0 ' + chartWidth + ' ' + heroChartHeight" class="w-full" style="height: 160px">
            <defs>
                <linearGradient id="areaGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="avgDuration < 100 ? 'var(--color-drac-green)' : avgDuration < 500 ? 'var(--color-drac-orange)' : 'var(--color-drac-red)'" stop-opacity="0.3"/>
                    <stop offset="100%" :stop-color="avgDuration < 100 ? 'var(--color-drac-green)' : avgDuration < 500 ? 'var(--color-drac-orange)' : 'var(--color-drac-red)'" stop-opacity="0.02"/>
                </linearGradient>
            </defs>
            <polygon :points="areaFillPoints" fill="url(#areaGradient)"/>
            <polyline :points="durationPoints" fill="none" :stroke="avgDuration < 100 ? 'var(--color-drac-green)' : avgDuration < 500 ? 'var(--color-drac-orange)' : 'var(--color-drac-red)'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle v-for="(pt, i) in durationCoords" :key="'d-'+i" :cx="pt.x" :cy="pt.y" r="4"
                :fill="avgDuration < 100 ? 'var(--color-drac-green)' : avgDuration < 500 ? 'var(--color-drac-orange)' : 'var(--color-drac-red)'"
                class="opacity-0 hover:opacity-100 transition cursor-pointer" style="filter: drop-shadow(0 0 3px rgba(0,0,0,0.3))">
                <title>@{{ trendSeries[i].duration_ms.toFixed(1) }}ms — @{{ trendSeries[i].method }} @{{ trendSeries[i].url }} (@{{ trendSeries[i].status_code }})</title>
            </circle>
        </svg>
    </div>

    {{-- Two Small Charts --}}
    <div v-if="trendSeries.length > 1" class="grid grid-cols-2 gap-4 mb-6">
        {{-- Throughput chart --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-5">
            <h2 class="text-drac-fg text-sm font-semibold mb-3">Throughput <span class="text-drac-comment text-[10px] font-medium">req/min</span></h2>
            <svg :viewBox="'0 0 ' + chartWidth + ' ' + miniChartHeight" class="w-full" style="height: 80px">
                <defs>
                    <linearGradient id="throughputGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--color-drac-cyan)" stop-opacity="0.2"/>
                        <stop offset="100%" stop-color="var(--color-drac-cyan)" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>
                <polygon :points="throughputFillPoints" fill="url(#throughputGradient)"/>
                <polyline :points="throughputPoints" fill="none" stroke="var(--color-drac-cyan)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        {{-- Memory chart --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-5">
            <h2 class="text-drac-fg text-sm font-semibold mb-3">Memory Usage <span class="text-drac-comment text-[10px] font-medium">MB</span></h2>
            <svg :viewBox="'0 0 ' + chartWidth + ' ' + miniChartHeight" class="w-full" style="height: 80px">
                <defs>
                    <linearGradient id="memoryGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--color-drac-orange)" stop-opacity="0.2"/>
                        <stop offset="100%" stop-color="var(--color-drac-orange)" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>
                <polygon :points="memoryFillPoints" fill="url(#memoryGradient)"/>
                <polyline :points="memoryPoints" fill="none" stroke="var(--color-drac-orange)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>

    {{-- Compact Profile Input --}}
    <div class="bg-drac-surface rounded-xl border border-drac-border p-4 mb-6">
        <form @submit.prevent="triggerProfile" class="flex gap-2">
            <select v-model="method" class="bg-drac-elevated border border-drac-border text-drac-fg rounded-lg px-2.5 py-2 text-xs font-bold focus:border-drac-purple focus:outline-none focus:ring-2 focus:ring-drac-purple/20 cursor-pointer">
                <option>GET</option>
                <option>POST</option>
                <option>PUT</option>
                <option>PATCH</option>
                <option>DELETE</option>
            </select>
            <div class="relative flex-1">
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-drac-comment pointer-events-none">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.061a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
                </div>
                <input
                    type="text"
                    v-model="url"
                    @focus="showSuggestions = searchHistory.length > 0"
                    @blur="hideSuggestions"
                    @keydown.escape="showSuggestions = false"
                    placeholder="Enter path or full URL to profile"
                    class="w-full bg-drac-elevated border border-drac-border text-drac-fg rounded-lg pl-9 pr-3 py-2 text-xs focus:border-drac-purple focus:outline-none focus:ring-2 focus:ring-drac-purple/20 placeholder-drac-comment"
                >
                <div v-show="showSuggestions && filteredHistory.length > 0"
                     class="absolute top-full left-0 right-0 mt-1.5 bg-drac-elevated border border-drac-border rounded-xl shadow-xl shadow-black/30 z-20 overflow-hidden dd-fade">
                    <div class="px-3 py-2 text-drac-comment text-[10px] font-bold uppercase tracking-widest border-b border-drac-border">Recent Searches</div>
                    <button v-for="item in filteredHistory" :key="item" type="button" @mousedown.prevent="url = item; showSuggestions = false"
                            class="w-full px-3.5 py-2 text-left text-xs text-drac-fg hover:bg-drac-purple/15 hover:text-drac-purple flex items-center gap-2 transition">
                        <svg class="w-3 h-3 text-drac-comment shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="font-mono">@{{ item }}</span>
                    </button>
                </div>
            </div>
            <button type="submit" :disabled="loading"
                class="bg-drac-purple text-drac-bg font-semibold px-5 py-2 rounded-lg text-xs hover:bg-drac-purple/90 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm flex items-center gap-1.5 transition">
                <span v-if="!loading" class="flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg> Profile</span>
                <span v-else class="flex items-center gap-1"><svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Running...</span>
            </button>
        </form>
        <div v-if="error" class="mt-2.5 bg-drac-red/10 text-drac-red text-xs px-3 py-2 rounded-lg flex items-center gap-2 font-medium border border-drac-red/20">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <span>@{{ error }}</span>
        </div>
    </div>

    {{-- Quick Result --}}
    <div v-if="lastResult" class="dd-fade mb-6">
        <div class="bg-drac-surface rounded-xl border border-drac-green/30 overflow-hidden">
            <div class="bg-drac-green/8 border-b border-drac-green/20 px-5 py-2.5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-drac-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-drac-green text-xs font-semibold">Profiled</span>
                    <code class="text-drac-cyan text-[11px] font-mono bg-drac-current px-2 py-0.5 rounded">@{{ lastResult.url }}</code>
                </div>
                <a :href="'/digdeep/profile/' + lastResult.profile_id" class="text-drac-purple text-xs font-semibold hover:text-drac-pink transition flex items-center gap-1">
                    View Details <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                </a>
            </div>
            <div class="grid grid-cols-4 divide-x divide-drac-border">
                <div class="px-4 py-3 text-center">
                    <div class="text-lg font-extrabold" :class="lastResult.status < 300 ? 'text-drac-green' : (lastResult.status < 400 ? 'text-drac-orange' : 'text-drac-red')">@{{ lastResult.status }}</div>
                    <div class="text-drac-comment text-[10px] font-medium">Status</div>
                </div>
                <div class="px-4 py-3 text-center">
                    <div class="text-lg font-extrabold text-drac-cyan">@{{ lastResult.duration }}<span class="text-[10px] font-semibold text-drac-comment ml-0.5">ms</span></div>
                    <div class="text-drac-comment text-[10px] font-medium">Duration</div>
                </div>
                <div class="px-4 py-3 text-center">
                    <div class="text-lg font-extrabold text-drac-purple">@{{ lastResult.queries }}</div>
                    <div class="text-drac-comment text-[10px] font-medium">Queries</div>
                </div>
                <div class="px-4 py-3 text-center">
                    <div class="text-lg font-extrabold text-drac-orange">@{{ lastResult.memory }}<span class="text-[10px] font-semibold text-drac-comment ml-0.5">MB</span></div>
                    <div class="text-drac-comment text-[10px] font-medium">Memory</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Two-column layout: Live Request Feed + Slowest Routes --}}
    <div class="flex gap-5 items-start">
        {{-- Live Request Feed --}}
        <div class="flex-1 min-w-0">
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2.5">
                    <h2 class="text-drac-fg text-sm font-semibold">Live Request Feed</h2>
                    <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full min-w-[24px] text-center">@{{ filteredProfiles.length }}</span>
                    <div class="ml-auto flex items-center gap-2">
                        <div class="relative">
                            <svg class="w-3.5 h-3.5 text-drac-comment absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                            <input v-model="search" type="text" placeholder="Filter..."
                                class="w-36 bg-drac-bg border border-drac-border text-drac-fg rounded-md pl-8 pr-3 py-1 text-[11px] focus:border-drac-purple focus:outline-none focus:ring-1 focus:ring-drac-purple/20 placeholder-drac-comment transition">
                        </div>
                        <div class="flex items-center gap-1">
                            <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                                class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">All</button>
                            <button @click="filter = 'page'" :class="filter === 'page' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                                class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">Pages</button>
                            <button @click="filter = 'ajax'" :class="filter === 'ajax' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                                class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">AJAX</button>
                        </div>
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
                        <a :href="'/digdeep/profile/' + p.id" class="flex items-center px-5 py-2.5 gap-3 hover:bg-drac-current/30 transition group relative" :class="p._new ? 'dd-fade bg-drac-purple/5' : ''">
                            {{-- Status dot --}}
                            <span class="w-2 h-2 rounded-full shrink-0" :class="p.status_code < 300 ? 'bg-drac-green' : (p.status_code < 400 ? 'bg-drac-orange' : 'bg-drac-red')"></span>
                            {{-- Method badge --}}
                            <span class="inline-flex items-center justify-center w-[44px] shrink-0 py-0.5 rounded-md text-[9px] font-bold tracking-wide"
                                :class="{
                                    'bg-drac-green/10 text-drac-green': p.method === 'GET',
                                    'bg-drac-cyan/10 text-drac-cyan': p.method === 'POST',
                                    'bg-drac-orange/10 text-drac-orange': p.method === 'PUT' || p.method === 'PATCH',
                                    'bg-drac-red/10 text-drac-red': p.method === 'DELETE'
                                }">@{{ p.method }}</span>

                            {{-- URL + metrics --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-drac-fg text-xs font-medium truncate group-hover:text-drac-purple transition">@{{ p.url }}</span>
                                    <span v-if="p.is_ajax" class="shrink-0 text-[9px] font-bold bg-drac-pink/10 text-drac-pink px-1.5 py-0.5 rounded-full">XHR</span>
                                    <template v-if="p.tags">
                                        <span v-if="p.tags.includes('slow')" class="shrink-0 text-[9px] font-bold bg-drac-red/10 text-drac-red px-1.5 py-0.5 rounded-full">SLOW</span>
                                        <span v-if="p.tags.includes('query-heavy')" class="shrink-0 text-[9px] font-bold bg-drac-orange/10 text-drac-orange px-1.5 py-0.5 rounded-full">N+Q</span>
                                    </template>
                                </div>
                                <div class="flex items-center gap-3 mt-0.5">
                                    <span class="text-[10px] font-medium" :class="durationColor(p.duration_ms)">@{{ Math.round(p.duration_ms) }}ms</span>
                                    <span class="text-drac-comment text-[10px]">@{{ p.query_count }}qry</span>
                                    <span class="text-drac-comment text-[10px]">@{{ Number(p.memory_peak_mb).toFixed(1) }}MB</span>
                                </div>
                            </div>

                            {{-- Status + time --}}
                            <div class="shrink-0 text-right">
                                <span class="text-xs font-bold" :class="p.status_code < 300 ? 'text-drac-green' : (p.status_code < 400 ? 'text-drac-orange' : 'text-drac-red')">@{{ p.status_code }}</span>
                                <div class="text-drac-comment text-[10px] mt-0.5">@{{ relativeTime(p.created_at) }}</div>
                            </div>

                            <svg class="w-3.5 h-3.5 text-drac-current group-hover:text-drac-purple transition shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                        </a>
                        </template>
                        <div v-if="filteredProfiles.length === 0" class="px-5 py-10 text-center">
                            <p class="text-drac-comment text-sm">No matching profiles found.</p>
                        </div>
                    </div>
                    <div v-if="hasMore && !search && filter === 'all'" class="px-5 py-3 border-t border-drac-border text-center">
                        <button @click="loadMore()" :disabled="loadingMore"
                            class="text-drac-purple text-xs font-semibold hover:text-drac-pink transition disabled:opacity-50">
                            <span v-if="!loadingMore">Load More</span>
                            <span v-else>Loading...</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Slowest Routes Table --}}
        <div class="w-[380px] shrink-0 sticky top-[110px]">
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                <div class="px-4 py-3 border-b border-drac-border flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-drac-comment" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    <h2 class="text-drac-fg text-xs font-semibold">Slowest Routes</h2>
                    <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-1.5 py-0.5 rounded-full">@{{ sortedRoutes.length }}</span>
                    <div class="ml-auto flex items-center gap-1">
                        <button @click="routeSortBy('p95')" class="text-[9px] font-bold px-1.5 py-0.5 rounded transition" :class="routeSortKey === 'p95' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'">P95</button>
                        <button @click="routeSortBy('error_rate')" class="text-[9px] font-bold px-1.5 py-0.5 rounded transition" :class="routeSortKey === 'error_rate' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'">Errors</button>
                        <button @click="routeSortBy('count')" class="text-[9px] font-bold px-1.5 py-0.5 rounded transition" :class="routeSortKey === 'count' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'">Reqs</button>
                    </div>
                </div>
                <div v-if="sortedRoutes.length > 0" class="divide-y divide-drac-border/60 max-h-[500px] overflow-y-auto">
                    <a v-for="r in sortedRoutes" :key="r.method + r.url"
                        :href="'/digdeep/trends?route=' + encodeURIComponent(r.url)"
                        class="block px-4 py-2.5 hover:bg-drac-current/30 transition">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded"
                                :class="r.method === 'GET' ? 'bg-drac-green/10 text-drac-green' : r.method === 'POST' ? 'bg-drac-cyan/10 text-drac-cyan' : 'bg-drac-purple/10 text-drac-purple'">@{{ r.method }}</span>
                            <span class="text-drac-fg text-[11px] font-mono truncate flex-1">@{{ r.url }}</span>
                            <span class="text-drac-comment text-[10px] font-bold shrink-0">@{{ r.count }}x</span>
                        </div>
                        <div class="flex items-center gap-3 text-[10px]">
                            <span class="font-medium" :class="durationColor(r.p50)">P50: @{{ r.p50 }}ms</span>
                            <span class="font-bold" :class="durationColor(r.p95)">P95: @{{ r.p95 }}ms</span>
                            <span class="font-medium" :class="errorColor(r.error_rate)">Err: @{{ r.error_rate }}%</span>
                        </div>
                    </a>
                </div>
                <div v-else class="px-4 py-8 text-center">
                    <p class="text-drac-comment text-xs">No route data yet.</p>
                </div>
            </div>
        </div>
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
            search: '',
            autoRefresh: false,
            autoRefreshInterval: null,
            profiles: @json($profiles),
            hasMore: @json($hasMore),
            currentPage: 1,
            loadingMore: false,
            timeRange: 'all',
            routePerf: @json($routePerf),
            globalPerf: @json($globalPerf),
            trendSeries: [],
            chartWidth: 600,
            heroChartHeight: 140,
            miniChartHeight: 60,
            routeSortKey: 'p95',
            routeSortDir: 'desc',
        };
    },
    computed: {
        filteredHistory() {
            if (!this.url) return this.searchHistory.slice(0, 10);
            const q = this.url.toLowerCase();
            return this.searchHistory.filter(h => h.toLowerCase().includes(q)).slice(0, 10);
        },
        filteredProfiles() {
            let list = this.profiles;
            if (this.filter === 'ajax') list = list.filter(p => Number(p.is_ajax) === 1);
            else if (this.filter === 'page') list = list.filter(p => Number(p.is_ajax) === 0);
            if (this.search) {
                const q = this.search.toLowerCase();
                list = list.filter(p => p.url.toLowerCase().includes(q) || p.method.toLowerCase().includes(q) || String(p.status_code).includes(q));
            }
            return list;
        },
        healthScore() {
            const profiles = this.profiles;
            if (profiles.length === 0) return 100;
            const errors = profiles.filter(p => Number(p.status_code) >= 400).length;
            const slow = profiles.filter(p => Number(p.duration_ms) > 500).length;
            return Math.max(0, 100 - (errors * 10) - (slow * 5));
        },
        avgDuration() {
            if (this.trendSeries.length === 0) return 0;
            return this.trendSeries.reduce((s, p) => s + p.duration_ms, 0) / this.trendSeries.length;
        },
        durationPoints() { return this.buildPoints(this.trendSeries.map(s => s.duration_ms), this.heroChartHeight); },
        durationCoords() { return this.buildCoords(this.trendSeries.map(s => s.duration_ms), this.heroChartHeight); },
        areaFillPoints() { return this.buildAreaFill(this.trendSeries.map(s => s.duration_ms), this.heroChartHeight); },
        throughputPoints() { return this.buildPoints(this.throughputSeries, this.miniChartHeight); },
        throughputFillPoints() { return this.buildAreaFill(this.throughputSeries, this.miniChartHeight); },
        memoryPoints() { return this.buildPoints(this.trendSeries.map(s => s.memory_peak_mb), this.miniChartHeight); },
        memoryFillPoints() { return this.buildAreaFill(this.trendSeries.map(s => s.memory_peak_mb), this.miniChartHeight); },
        throughputSeries() {
            if (this.trendSeries.length < 2) return [];
            const buckets = {};
            this.trendSeries.forEach(s => {
                const d = new Date(s.created_at);
                const key = d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate() + '-' + d.getHours() + '-' + d.getMinutes();
                buckets[key] = (buckets[key] || 0) + 1;
            });
            return Object.values(buckets);
        },
        sortedRoutes() {
            return [...this.routePerf].sort((a, b) => {
                const va = a[this.routeSortKey];
                const vb = b[this.routeSortKey];
                return this.routeSortDir === 'desc' ? vb - va : va - vb;
            });
        },
    },
    mounted() {
        try { this.searchHistory = JSON.parse(localStorage.getItem('digdeep_history') || '[]'); } catch { this.searchHistory = []; }
        this.loadTrendData();
    },
    beforeUnmount() {
        if (this.autoRefreshInterval) clearInterval(this.autoRefreshInterval);
    },
    methods: {
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        hideSuggestions() { setTimeout(() => this.showSuggestions = false, 200); },
        saveToHistory(u) {
            if (!u || u === '/') return;
            this.searchHistory = [u, ...this.searchHistory.filter(h => h !== u)].slice(0, 30);
            localStorage.setItem('digdeep_history', JSON.stringify(this.searchHistory));
        },
        relativeTime(dateStr) {
            const now = new Date();
            const date = new Date(dateStr);
            const diff = Math.floor((now - date) / 1000);
            if (diff < 5) return 'just now';
            if (diff < 60) return diff + 's ago';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
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
        routeSortBy(key) {
            if (this.routeSortKey === key) {
                this.routeSortDir = this.routeSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.routeSortKey = key;
                this.routeSortDir = 'desc';
            }
        },
        buildPoints(values, height) {
            if (values.length < 2) return '';
            const max = Math.max(...values) || 1;
            const min = Math.min(...values);
            const range = max - min || 1;
            const pad = 10;
            return values.map((v, i) => {
                const x = pad + (i / (values.length - 1)) * (this.chartWidth - pad * 2);
                const y = pad + (1 - (v - min) / range) * (height - pad * 2);
                return x.toFixed(1) + ',' + y.toFixed(1);
            }).join(' ');
        },
        buildCoords(values, height) {
            if (values.length < 2) return [];
            const max = Math.max(...values) || 1;
            const min = Math.min(...values);
            const range = max - min || 1;
            const pad = 10;
            return values.map((v, i) => ({
                x: pad + (i / (values.length - 1)) * (this.chartWidth - pad * 2),
                y: pad + (1 - (v - min) / range) * (height - pad * 2),
            }));
        },
        buildAreaFill(values, height) {
            if (values.length < 2) return '';
            const pts = this.buildPoints(values, height);
            const pad = 10;
            const firstX = pad;
            const lastX = pad + (this.chartWidth - pad * 2);
            return firstX.toFixed(1) + ',' + height + ' ' + pts + ' ' + lastX.toFixed(1) + ',' + height;
        },
        toggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;
            if (this.autoRefresh) {
                this.pollAll();
                this.autoRefreshInterval = setInterval(() => this.pollAll(), 3000);
            } else {
                if (this.autoRefreshInterval) clearInterval(this.autoRefreshInterval);
                this.autoRefreshInterval = null;
            }
        },
        async pollAll() {
            await Promise.all([this.pollProfiles(), this.loadTrendData(), this.loadPerfData()]);
        },
        async pollProfiles() {
            try {
                const last = this.profiles.length > 0 ? this.profiles[0].created_at : null;
                const url = '/digdeep/api/profiles' + (last ? '?after=' + encodeURIComponent(last) : '');
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.profiles && d.profiles.length > 0) {
                    d.profiles.forEach(p => p._new = true);
                    this.profiles = [...d.profiles, ...this.profiles];
                    setTimeout(() => { d.profiles.forEach(p => p._new = false); }, 1500);
                }
            } catch(e) { console.error('Poll failed', e); }
        },
        async loadTrendData() {
            try {
                const params = new URLSearchParams();
                if (this.timeRange !== 'all') params.set('range', this.timeRange);
                const r = await fetch('/digdeep/api/trends?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.trendSeries = d.series || [];
            } catch(e) { console.error('Failed to load trends', e); }
        },
        async loadPerfData() {
            try {
                const params = new URLSearchParams();
                if (this.timeRange !== 'all') params.set('range', this.timeRange);
                const r = await fetch('/digdeep/api/performance?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.routePerf = d.routes || [];
                this.globalPerf = d.global || {};
            } catch(e) { console.error('Failed to load performance', e); }
        },
        async loadRangeData() {
            await Promise.all([this.loadTrendData(), this.loadPerfData()]);
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
        async exportAll() {
            const ids = this.filteredProfiles.map(p => p.id);
            if (ids.length === 0) return;
            try {
                const r = await fetch('/digdeep/api/bulk-export', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ ids })
                });
                const blob = await r.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'digdeep-export.json';
                a.click();
                URL.revokeObjectURL(url);
            } catch(e) { console.error('Export failed', e); }
        },
        async loadMore() {
            this.loadingMore = true;
            try {
                this.currentPage++;
                const r = await fetch('/digdeep/api/profiles?page=' + this.currentPage + '&per_page=50', { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.profiles && d.profiles.length > 0) {
                    this.profiles = [...this.profiles, ...d.profiles];
                }
                this.hasMore = d.has_more || false;
            } catch(e) { console.error('Load more failed', e); } finally { this.loadingMore = false; }
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
