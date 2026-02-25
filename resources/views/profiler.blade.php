@extends('digdeep::layout')

@section('title', 'Profiler')

@section('content')
<div id="digdeep-profiler" v-cloak>

    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">On-Demand Profiler</h1>
            <p class="text-drac-comment text-xs mt-1">Profile any URL and inspect full results inline. Run multiple times to compare.</p>
        </div>
    </div>

    <div class="flex gap-5 items-start">

        {{-- Run History Sidebar --}}
        <div v-if="runs.length > 0" class="w-[260px] shrink-0 sticky top-[110px]">
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                <div class="px-4 py-3 border-b border-drac-border flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-drac-comment" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h2 class="text-drac-fg text-xs font-semibold">Run History</h2>
                    <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-1.5 py-0.5 rounded-full">@{{ runs.length }}</span>
                    <button @click="runs = []; activeRunIndex = -1" class="ml-auto text-drac-comment text-[10px] font-medium hover:text-drac-red transition">Clear</button>
                </div>
                <div class="divide-y divide-drac-border/60 max-h-[500px] overflow-y-auto">
                    <button v-for="(run, i) in runs" :key="run.id" @click="selectRun(i)"
                        class="w-full px-4 py-2.5 text-left transition"
                        :class="activeRunIndex === i ? 'bg-drac-purple/10' : 'hover:bg-drac-current/30'">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center justify-center w-[36px] shrink-0 py-0.5 rounded text-[9px] font-bold tracking-wide"
                                :class="{
                                    'bg-drac-green/10 text-drac-green': run.method === 'GET',
                                    'bg-drac-cyan/10 text-drac-cyan': run.method === 'POST',
                                    'bg-drac-orange/10 text-drac-orange': run.method === 'PUT' || run.method === 'PATCH',
                                    'bg-drac-red/10 text-drac-red': run.method === 'DELETE'
                                }">@{{ run.method }}</span>
                            <span class="text-drac-fg text-xs font-mono truncate flex-1"
                                :class="activeRunIndex === i ? 'text-drac-purple' : ''">@{{ run.url }}</span>
                        </div>
                        <div class="flex items-center gap-2.5 text-[10px]">
                            <span class="font-bold" :class="run.status < 300 ? 'text-drac-green' : (run.status < 400 ? 'text-drac-orange' : 'text-drac-red')">@{{ run.status }}</span>
                            <span class="text-drac-cyan font-medium">@{{ run.duration }}ms</span>
                            <span class="text-drac-comment">@{{ run.queries }} qry</span>
                            <span class="text-drac-comment ml-auto">@{{ formatTime(run.timestamp) }}</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="flex-1 min-w-0">

            {{-- Trigger Form --}}
            <div class="bg-drac-surface rounded-xl border border-drac-border p-5 mb-5">
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
                            ref="urlInput"
                            @focus="showSuggestions = true"
                            @blur="hideSuggestions"
                            @keydown.escape="showSuggestions = false"
                            @input="showSuggestions = true"
                            placeholder="Enter path or full URL — e.g. /sport-in-adrenalin"
                            class="w-full bg-drac-elevated border border-drac-border text-drac-fg rounded-lg pl-10 pr-4 py-2.5 text-sm focus:border-drac-purple focus:outline-none focus:ring-2 focus:ring-drac-purple/20 placeholder-drac-comment"
                        >
                        <div v-show="showSuggestions && suggestions.length > 0"
                             class="absolute top-full left-0 right-0 mt-1.5 bg-drac-elevated border border-drac-border rounded-xl shadow-xl shadow-black/30 z-20 overflow-hidden dd-fade max-h-[300px] overflow-y-auto">
                            <div v-if="searchHistory.length > 0" class="px-3 py-2 text-drac-comment text-[10px] font-bold uppercase tracking-widest border-b border-drac-border">Recent & Top Routes</div>
                            <div v-else class="px-3 py-2 text-drac-comment text-[10px] font-bold uppercase tracking-widest border-b border-drac-border">Top Routes</div>
                            <button v-for="item in suggestions" :key="item.url" type="button"
                                    @mousedown.prevent="url = item.url; showSuggestions = false"
                                    class="w-full px-3.5 py-2.5 text-left text-sm text-drac-fg hover:bg-drac-purple/15 hover:text-drac-purple flex items-center gap-2.5 transition">
                                <svg v-if="item.type === 'history'" class="w-3.5 h-3.5 text-drac-comment shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg v-else class="w-3.5 h-3.5 text-drac-comment shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.061a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
                                <span class="font-mono text-xs">@{{ item.url }}</span>
                                <span v-if="item.visits" class="ml-auto text-drac-comment text-[10px]">@{{ item.visits }}x</span>
                            </button>
                        </div>
                    </div>
                    <button type="submit" :disabled="loading"
                        class="bg-drac-purple text-drac-bg font-semibold px-6 py-2.5 rounded-lg text-sm hover:bg-drac-purple/90 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm flex items-center gap-2 transition">
                        <span v-if="!loading" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/></svg>
                            Profile
                        </span>
                        <span v-else class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Running...
                        </span>
                    </button>
                </form>
                <div v-if="error" class="mt-3 bg-drac-red/10 text-drac-red text-sm px-3.5 py-2.5 rounded-lg flex items-center gap-2 font-medium border border-drac-red/20">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    <span>@{{ error }}</span>
                </div>
            </div>

            {{-- Active Run Results --}}
            <div v-if="activeRun" class="dd-fade">

                {{-- Summary Bar --}}
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-5">
                    <div class="px-5 py-3 border-b border-drac-border flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center justify-center w-[48px] py-0.5 rounded-md text-[10px] font-bold tracking-wide"
                                :class="{
                                    'bg-drac-green/10 text-drac-green': activeRun.method === 'GET',
                                    'bg-drac-cyan/10 text-drac-cyan': activeRun.method === 'POST',
                                    'bg-drac-orange/10 text-drac-orange': activeRun.method === 'PUT' || activeRun.method === 'PATCH',
                                    'bg-drac-red/10 text-drac-red': activeRun.method === 'DELETE'
                                }">@{{ activeRun.method }}</span>
                            <code class="text-drac-cyan text-sm font-mono">@{{ activeRun.url }}</code>
                            {{-- Performance Badge --}}
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                :class="perfBadge.class">@{{ perfBadge.label }}</span>
                            {{-- Delta indicators --}}
                            <template v-if="previousRun">
                                <span class="text-[10px] font-mono font-bold" :class="durationDelta > 0 ? 'text-drac-red' : 'text-drac-green'">
                                    @{{ durationDelta > 0 ? '+' : '' }}@{{ durationDelta }}ms
                                </span>
                                <span v-if="queryDelta !== 0" class="text-[10px] font-mono font-bold" :class="queryDelta > 0 ? 'text-drac-red' : 'text-drac-green'">
                                    @{{ queryDelta > 0 ? '+' : '' }}@{{ queryDelta }} qry
                                </span>
                            </template>
                        </div>
                        <a :href="'/digdeep/profile/' + activeRun.id" class="text-drac-purple text-xs font-semibold hover:text-drac-pink transition flex items-center gap-1">
                            Full Profile <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                        </a>
                    </div>
                    <div class="grid grid-cols-4 divide-x divide-drac-border">
                        <div class="px-5 py-3.5 text-center">
                            <div class="text-xl font-extrabold" :class="activeRun.status < 300 ? 'text-drac-green' : (activeRun.status < 400 ? 'text-drac-orange' : 'text-drac-red')">@{{ activeRun.status }}</div>
                            <div class="text-drac-comment text-[10px] font-medium mt-0.5">Status</div>
                        </div>
                        <div class="px-5 py-3.5 text-center">
                            <div class="text-xl font-extrabold text-drac-cyan">@{{ activeRun.duration }}<span class="text-[10px] font-semibold text-drac-comment ml-0.5">ms</span></div>
                            <div class="text-drac-comment text-[10px] font-medium mt-0.5">Duration</div>
                        </div>
                        <div class="px-5 py-3.5 text-center">
                            <div class="text-xl font-extrabold text-drac-purple">@{{ activeRun.queries }}</div>
                            <div class="text-drac-comment text-[10px] font-medium mt-0.5">Queries</div>
                        </div>
                        <div class="px-5 py-3.5 text-center">
                            <div class="text-xl font-extrabold text-drac-orange">@{{ activeRun.memory }}<span class="text-[10px] font-semibold text-drac-comment ml-0.5">MB</span></div>
                            <div class="text-drac-comment text-[10px] font-medium mt-0.5">Memory</div>
                        </div>
                    </div>
                </div>

                {{-- Metrics Cards Row --}}
                <div class="grid grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Duration</div>
                        <div class="text-lg font-extrabold text-drac-cyan leading-none">@{{ Math.round(activeRun.duration) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                    </div>
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Queries</div>
                        <div class="text-lg font-extrabold text-drac-purple leading-none">@{{ activeRun.queries }}</div>
                    </div>
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Memory</div>
                        <div class="text-lg font-extrabold text-drac-orange leading-none">@{{ Number(activeRun.memory).toFixed(1) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">MB</span></div>
                    </div>
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Views</div>
                        <div class="text-lg font-extrabold text-drac-pink leading-none">@{{ (activeRun.data.views || []).length }}</div>
                    </div>
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Events</div>
                        <div class="text-lg font-extrabold text-drac-green leading-none">@{{ (activeRun.data.events || []).length }}</div>
                    </div>
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Cache Ops</div>
                        <div class="text-lg font-extrabold leading-none" :class="(activeRun.data.cache || []).length > 0 ? 'text-drac-yellow' : 'text-drac-comment'">@{{ (activeRun.data.cache || []).length }}</div>
                    </div>
                </div>

                {{-- N+1 Warning Banner --}}
                <div v-if="nPlusOne.length > 0" class="bg-drac-orange/8 border border-drac-orange/25 rounded-xl px-5 py-3 mb-5 flex items-start gap-3">
                    <svg class="w-5 h-5 text-drac-orange shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <div>
                        <div class="text-drac-orange text-sm font-semibold">N+1 Query Detected</div>
                        <div class="text-drac-orange/70 text-xs mt-0.5">@{{ nPlusOne.length }} repeated @{{ nPlusOne.length === 1 ? 'pattern' : 'patterns' }} found. Consider eager loading.</div>
                        <div class="mt-2 space-y-1.5">
                            <div v-for="(pattern, pi) in nPlusOne" :key="pi" class="bg-drac-current rounded-lg px-3 py-2">
                                <div class="text-drac-orange/70 text-xs font-mono truncate">
                                    <span class="text-drac-orange font-semibold">@{{ pattern.count }}x</span> @{{ truncate(pattern.pattern, 120) }}
                                </div>
                                <div v-if="pattern.table" class="text-drac-comment text-[10px] mt-0.5">Table: <span class="text-drac-cyan">@{{ pattern.table }}</span></div>
                                <div v-if="pattern.suggestion" class="text-drac-green text-[10px] mt-1 flex items-center gap-1">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.44 2.278a3.68 3.68 0 01-2.38 0"/></svg>
                                    @{{ pattern.suggestion }}
                                </div>
                                <div v-if="pattern.callers && pattern.callers.length" class="text-drac-comment text-[10px] mt-1 font-mono">Callers: @{{ pattern.callers.slice(0, 3).join(', ') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Duplicate Query Warning --}}
                <div v-else-if="duplicateQueryCount > 0" class="bg-drac-orange/8 border border-drac-orange/25 rounded-xl px-5 py-3 mb-5 flex items-start gap-3">
                    <svg class="w-5 h-5 text-drac-orange shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <div>
                        <div class="text-drac-orange text-sm font-semibold">Duplicate Queries Detected</div>
                        <div class="text-drac-orange/70 text-xs mt-0.5">@{{ duplicateGroups }} duplicate @{{ duplicateGroups === 1 ? 'query' : 'queries' }} found (@{{ duplicateQueryCount }} extra @{{ duplicateQueryCount === 1 ? 'execution' : 'executions' }}).</div>
                    </div>
                </div>

                {{-- Lifecycle Memory Bar Chart --}}
                <div v-if="lifecyclePhases.length > 0" class="bg-drac-surface rounded-xl border border-drac-border p-4 mb-5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-3">Lifecycle Memory</div>
                    <div class="flex items-end gap-2 h-12">
                        <div v-for="phase in lifecyclePhases" :key="phase.name" class="flex-1 flex flex-col items-center gap-1">
                            <div class="w-full rounded-t opacity-80" :class="phaseColor(phase.name)" :style="'height:' + Math.max(4, (phase.memory_bytes / maxLifecycleMem) * 100) + '%'"></div>
                            <span class="text-[9px] text-drac-comment font-bold capitalize">@{{ phase.name }}</span>
                            <span class="text-[9px] text-drac-fg font-mono">@{{ (phase.memory_bytes / 1024 / 1024).toFixed(1) }}MB</span>
                            <span v-if="phase.memory_delta_bytes !== undefined" class="text-[9px] font-mono" :class="phase.memory_delta_bytes > 0 ? 'text-drac-orange' : 'text-drac-green'">
                                @{{ phase.memory_delta_bytes > 0 ? '+' : '' }}@{{ Math.round(phase.memory_delta_bytes / 1024) }}KB
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Layout: sidebar nav + content panel --}}
                <div class="flex gap-5 items-start">
                    {{-- Sidebar Navigation --}}
                    <nav class="w-[190px] shrink-0 sticky top-[110px]">
                        <div class="space-y-0.5">
                            <button v-for="t in tabDefs" :key="t.key" @click="activeTab = t.key" class="dd-sidebar-link" :class="activeTab === t.key ? 'active' : ''">
                                <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="t.icon"/></svg>
                                <span class="flex-1">@{{ t.label }}</span>
                                <span v-if="t.count !== null" class="text-[10px] font-bold" :class="t.count > 0 ? 'opacity-60' : 'opacity-30'">@{{ t.count }}</span>
                            </button>
                        </div>
                    </nav>

                    {{-- Content Panel --}}
                    <div class="flex-1 min-w-0">

                        {{-- Queries Tab --}}
                        <div v-show="activeTab === 'queries'" class="dd-fade">
                            <template v-if="queryList.length > 0">
                                <div class="grid grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Total Time</div>
                                        <div class="text-sm font-extrabold text-drac-purple leading-none">@{{ queryStats.totalTime.toFixed(2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                                    </div>
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Average</div>
                                        <div class="text-sm font-extrabold text-drac-fg leading-none">@{{ queryStats.avgTime.toFixed(2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                                    </div>
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Slowest</div>
                                        <div class="text-sm font-extrabold text-drac-orange leading-none">@{{ queryStats.maxTime.toFixed(2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                                    </div>
                                    <div v-if="duplicateGroups > 0" class="bg-drac-surface rounded-xl border border-drac-border p-3">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Duplicates</div>
                                        <div class="text-sm font-extrabold text-drac-orange leading-none flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                                            @{{ duplicateGroups }}
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div v-for="(q, qi) in queryList" :key="qi"
                                        class="bg-drac-surface rounded-xl border border-drac-border p-4 hover:border-drac-comment/40 transition"
                                        :class="isDuplicateQuery(q.sql) ? 'ring-1 ring-drac-orange/25' : ''">
                                        <div class="flex justify-between items-center mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-drac-comment text-[11px] font-bold">#@{{ qi + 1 }}</span>
                                                <span v-if="isDuplicateQuery(q.sql)" class="text-[10px] font-bold text-drac-orange bg-drac-orange/10 px-1.5 py-0.5 rounded">DUP @{{ queryGroupCount(q.sql) }}x</span>
                                            </div>
                                            <div class="flex items-center gap-3 text-xs">
                                                <span class="font-bold" :class="q.time_ms > 100 ? 'text-drac-red' : (q.time_ms > 10 ? 'text-drac-orange' : 'text-drac-green')">@{{ Number(q.time_ms).toFixed(2) }}ms</span>
                                                <span v-if="q.caller" class="text-drac-comment font-mono text-[11px]">@{{ q.caller }}</span>
                                            </div>
                                        </div>
                                        <div class="w-full bg-drac-current rounded-full h-[3px] mb-2.5">
                                            <div class="dd-bar h-[3px] rounded-full" :class="q.time_ms > 100 ? 'bg-drac-red' : (q.time_ms > 10 ? 'bg-drac-orange' : 'bg-gradient-to-r from-drac-purple to-drac-pink')" :style="'width:' + (queryStats.maxTime > 0 ? (q.time_ms / queryStats.maxTime) * 100 : 0) + '%'"></div>
                                        </div>
                                        <code class="text-drac-cyan text-xs font-mono leading-relaxed break-all block">@{{ q.sql }}</code>
                                        <div v-if="q.bindings && q.bindings.length" class="mt-2 text-[11px] text-drac-comment font-mono bg-drac-bg rounded px-2.5 py-1.5 border border-drac-border">
                                            <span class="text-drac-comment">Bindings:</span> <span class="text-drac-yellow">@{{ JSON.stringify(q.bindings) }}</span>
                                        </div>
                                        <div class="mt-2 flex items-center gap-2">
                                            <button @click="explainQuery(qi)" :disabled="explaining[qi]"
                                                class="text-[10px] font-semibold text-drac-comment hover:text-drac-purple transition flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                                                <span v-if="explaining[qi]">...</span>
                                                <span v-else>EXPLAIN</span>
                                            </button>
                                        </div>
                                        <div v-if="explainResults[qi]" class="mt-2 pt-2 border-t border-drac-border/50">
                                            <pre class="text-[10px] font-mono text-drac-green overflow-x-auto">@{{ explainResults[qi] }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No queries recorded for this request.</p>
                            </div>
                        </div>

                        {{-- Route Tab --}}
                        <div v-show="activeTab === 'route'" class="dd-fade">
                            <template v-if="activeRun.data.route && Object.keys(activeRun.data.route).length">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border">
                                        <div class="px-5 py-4 flex items-center gap-4">
                                            <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Name</div>
                                            <div class="text-drac-fg text-sm font-semibold">@{{ activeRun.data.route.name || '(unnamed)' }}</div>
                                        </div>
                                        <div class="px-5 py-4 flex items-center gap-4">
                                            <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Action</div>
                                            <div class="text-drac-cyan text-sm font-semibold font-mono break-all">@{{ activeRun.data.route.action || '—' }}</div>
                                        </div>
                                        <div v-if="activeRun.data.route.parameters && Object.keys(activeRun.data.route.parameters).length" class="px-5 py-4">
                                            <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-2.5">Parameters</div>
                                            <div class="flex flex-wrap gap-2">
                                                <div v-for="(val, key) in activeRun.data.route.parameters" :key="key" class="bg-drac-bg rounded-lg px-3 py-1.5 border border-drac-border text-xs">
                                                    <span class="text-drac-purple font-mono font-semibold">@{{ key }}</span>
                                                    <span class="text-drac-comment mx-1">=</span>
                                                    <span class="text-drac-fg font-mono">@{{ typeof val === 'string' ? val : JSON.stringify(val) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="activeRun.data.route.middleware && activeRun.data.route.middleware.length" class="px-5 py-4">
                                            <div class="flex items-center gap-2.5 mb-2.5">
                                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Middleware Stack</div>
                                                <span v-if="activeRun.data.middleware_pipeline_ms != null" class="text-[10px] font-bold text-drac-cyan bg-drac-cyan/10 px-1.5 py-0.5 rounded">@{{ Number(activeRun.data.middleware_pipeline_ms).toFixed(1) }}ms total</span>
                                            </div>
                                            <template v-if="middlewareTiming.length > 0">
                                                <div class="space-y-1.5">
                                                    <div v-for="mwt in middlewareTiming" :key="mwt.name" class="flex items-center gap-2.5">
                                                        <span class="bg-drac-bg text-drac-fg text-[11px] px-2.5 py-1 rounded-md border border-drac-border font-mono font-medium flex-1 min-w-0 truncate">@{{ mwt.name }}</span>
                                                        <span v-if="mwt.is_estimated" class="text-[9px] font-semibold text-drac-comment bg-drac-current px-1.5 py-0.5 rounded shrink-0">est.</span>
                                                        <span class="text-[10px] font-bold font-mono shrink-0" :class="mwt.duration_ms > 10 ? 'text-drac-orange' : 'text-drac-green'">@{{ Number(mwt.duration_ms).toFixed(2) }}ms</span>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <span v-for="mw in activeRun.data.route.middleware" :key="mw" class="bg-drac-bg text-drac-fg text-[11px] px-2.5 py-1 rounded-md border border-drac-border font-mono font-medium">@{{ typeof mw === 'string' ? mw.split('\\').pop() : mw }}</span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No route matched this request.</p>
                            </div>
                        </div>

                        {{-- Events Tab --}}
                        <div v-show="activeTab === 'events'" class="dd-fade">
                            <template v-if="(activeRun.data.events || []).length > 0">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="(e, i) in activeRun.data.events" :key="i" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-drac-current/30 transition">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="text-drac-comment text-[11px] font-bold w-6 shrink-0 text-right">@{{ i + 1 }}</span>
                                                <span class="text-drac-fg text-sm font-mono truncate">@{{ e.event }}</span>
                                            </div>
                                            <span class="text-drac-comment text-xs shrink-0 max-w-[200px] truncate font-mono">@{{ e.payload_summary }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No events were dispatched.</p>
                            </div>
                        </div>

                        {{-- Views Tab --}}
                        <div v-show="activeTab === 'views'" class="dd-fade">
                            <template v-if="(activeRun.data.views || []).length > 0">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="v in activeRun.data.views" :key="v.name" class="px-5 py-3 hover:bg-drac-current/30 transition">
                                            <div class="flex items-center gap-2 mb-1">
                                                <svg class="w-3.5 h-3.5 text-drac-pink shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                <span class="text-drac-fg text-sm font-semibold">@{{ v.name }}</span>
                                            </div>
                                            <div class="text-drac-comment text-xs font-mono ml-5">@{{ v.path }}</div>
                                            <div v-if="v.data_keys && v.data_keys.length" class="flex flex-wrap gap-1 mt-1.5 ml-5">
                                                <span v-for="dk in v.data_keys" :key="dk" class="bg-drac-bg text-drac-comment text-[10px] px-1.5 py-0.5 rounded border border-drac-border font-mono">@{{ dk }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No views were rendered.</p>
                            </div>
                        </div>

                        {{-- Cache Tab --}}
                        <div v-show="activeTab === 'cache'" class="dd-fade">
                            <template v-if="(activeRun.data.cache || []).length > 0">
                                <div v-if="cacheStats.hits > 0 || cacheStats.misses > 0" class="grid grid-cols-3 gap-3 mb-4">
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Hits</div>
                                        <div class="text-sm font-extrabold text-drac-green leading-none">@{{ cacheStats.hits }}</div>
                                    </div>
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Misses</div>
                                        <div class="text-sm font-extrabold text-drac-red leading-none">@{{ cacheStats.misses }}</div>
                                    </div>
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Hit Rate</div>
                                        <div class="text-sm font-extrabold text-drac-fg leading-none">@{{ cacheStats.hitRate }}%</div>
                                    </div>
                                </div>
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="(c, ci) in activeRun.data.cache" :key="ci" class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                            <span class="text-drac-fg text-sm font-mono truncate">@{{ c.key }}</span>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0"
                                                :class="{
                                                    'bg-drac-green/10 text-drac-green': c.type === 'hit',
                                                    'bg-drac-red/10 text-drac-red': c.type === 'miss',
                                                    'bg-drac-cyan/10 text-drac-cyan': c.type === 'write'
                                                }">@{{ c.type.toUpperCase() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No cache operations.</p>
                            </div>
                        </div>

                        {{-- Inertia Tab --}}
                        <div v-show="activeTab === 'inertia'" class="dd-fade">
                            <template v-if="activeRun.data.inertia && (activeRun.data.inertia.component || (Array.isArray(activeRun.data.inertia) && activeRun.data.inertia.length))">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border">
                                        <div class="px-5 py-4 flex items-center gap-4">
                                            <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Component</div>
                                            <div class="text-drac-purple text-sm font-semibold font-mono">@{{ activeRun.data.inertia.component || '—' }}</div>
                                        </div>
                                        <div class="px-5 py-4 flex items-center gap-4">
                                            <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">URL</div>
                                            <div class="text-drac-cyan text-sm font-semibold font-mono">@{{ activeRun.data.inertia.url || '—' }}</div>
                                        </div>
                                        <div v-if="activeRun.data.inertia.version" class="px-5 py-4 flex items-center gap-4">
                                            <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Version</div>
                                            <div class="text-drac-fg text-sm font-semibold font-mono break-all">@{{ activeRun.data.inertia.version }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="activeRun.data.inertia.props && Object.keys(activeRun.data.inertia.props).length" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mt-4">
                                    <div class="px-5 py-3 border-b border-drac-border">
                                        <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Props</span>
                                        <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-1.5 py-0.5 rounded-full ml-2">@{{ Object.keys(activeRun.data.inertia.props).length }}</span>
                                    </div>
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="(pType, pName) in activeRun.data.inertia.props" :key="pName" class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                            <span class="text-drac-purple text-sm font-mono font-semibold">@{{ pName }}</span>
                                            <span class="text-drac-comment text-xs font-mono bg-drac-bg px-2 py-0.5 rounded border border-drac-border">@{{ pType }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No Inertia data detected for this request.</p>
                            </div>
                        </div>

                        {{-- Mail Tab --}}
                        <div v-show="activeTab === 'mail'" class="dd-fade">
                            <template v-if="(activeRun.data.mail || []).length > 0">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="(m, mi) in activeRun.data.mail" :key="mi" class="px-5 py-3 hover:bg-drac-current/30 transition flex items-center gap-3">
                                            <svg class="w-4 h-4 text-drac-orange shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                                            <div>
                                                <div class="text-drac-fg text-sm font-semibold">@{{ m.subject }}</div>
                                                <div class="text-drac-comment text-xs mt-0.5">To: @{{ m.to }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No mail was sent.</p>
                            </div>
                        </div>

                        {{-- HTTP Tab --}}
                        <div v-show="activeTab === 'http'" class="dd-fade">
                            <template v-if="(activeRun.data.http_client || []).length > 0">
                                <div class="space-y-2">
                                    <div v-for="(h, hi) in activeRun.data.http_client" :key="hi" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                        <div class="px-5 py-3 flex items-center justify-between gap-4 cursor-pointer hover:bg-drac-current/30 transition" @click="toggleHttpExpanded(hi)">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="h.method === 'GET' ? 'bg-drac-green/10 text-drac-green' : 'bg-drac-cyan/10 text-drac-cyan'">@{{ h.method }}</span>
                                                <span class="text-drac-fg text-sm truncate font-mono">@{{ h.url }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 text-xs shrink-0">
                                                <span :class="h.status < 300 ? 'text-drac-green' : 'text-drac-red'" class="font-bold">@{{ h.status }}</span>
                                                <span class="text-drac-comment font-medium">@{{ Number(h.duration_ms).toFixed(1) }}ms</span>
                                                <span v-if="h.response_size" class="text-drac-comment font-mono text-[10px]">@{{ (h.response_size / 1024).toFixed(1) }}KB</span>
                                                <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="httpExpanded[hi] ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                            </div>
                                        </div>
                                        <div v-if="httpExpanded[hi]" class="border-t border-drac-border dd-fade">
                                            <div v-if="h.request_headers && Object.keys(h.request_headers).length" class="px-5 py-3 border-b border-drac-border/50">
                                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1.5">Request Headers</div>
                                                <div class="text-[11px] font-mono space-y-0.5">
                                                    <div v-for="(val, key) in h.request_headers" :key="key"><span class="text-drac-cyan">@{{ key }}:</span> <span class="text-drac-fg">@{{ Array.isArray(val) ? val.join(', ') : val }}</span></div>
                                                </div>
                                            </div>
                                            <div v-if="h.request_body" class="px-5 py-3 border-b border-drac-border/50">
                                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1.5">Request Body</div>
                                                <pre class="text-drac-yellow text-[11px] font-mono leading-relaxed overflow-x-auto max-h-[200px]">@{{ h.request_body }}</pre>
                                            </div>
                                            <div v-if="h.response_headers && Object.keys(h.response_headers).length" class="px-5 py-3 border-b border-drac-border/50">
                                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1.5">Response Headers</div>
                                                <div class="text-[11px] font-mono space-y-0.5">
                                                    <div v-for="(val, key) in h.response_headers" :key="key"><span class="text-drac-pink">@{{ key }}:</span> <span class="text-drac-fg">@{{ Array.isArray(val) ? val.join(', ') : val }}</span></div>
                                                </div>
                                            </div>
                                            <div v-if="h.response_body" class="px-5 py-3">
                                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1.5">Response Body</div>
                                                <pre class="text-drac-green text-[11px] font-mono leading-relaxed overflow-x-auto max-h-[300px]">@{{ h.response_body }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No outgoing HTTP requests.</p>
                            </div>
                        </div>

                        {{-- Jobs Tab --}}
                        <div v-show="activeTab === 'jobs'" class="dd-fade">
                            <template v-if="(activeRun.data.jobs || []).length > 0">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="(j, ji) in activeRun.data.jobs" :key="ji" class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                            <span class="text-drac-fg text-sm font-mono">@{{ j.job }}</span>
                                            <span class="bg-drac-bg text-drac-comment text-[11px] px-2 py-0.5 rounded border border-drac-border font-medium">@{{ j.queue }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No jobs were dispatched.</p>
                            </div>
                        </div>

                        {{-- Commands Tab --}}
                        <div v-show="activeTab === 'commands'" class="dd-fade">
                            <template v-if="(activeRun.data.commands || []).length > 0">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="(cmd, ci) in activeRun.data.commands" :key="ci" class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <svg class="w-3.5 h-3.5 text-drac-cyan shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/></svg>
                                                <span class="text-drac-fg text-sm font-mono truncate">@{{ cmd.command }}</span>
                                            </div>
                                            <div class="flex items-center gap-2.5 shrink-0">
                                                <span class="text-drac-comment text-xs font-medium">@{{ Number(cmd.duration_ms).toFixed(1) }}ms</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="(cmd.exit_code || 0) === 0 ? 'bg-drac-green/10 text-drac-green' : 'bg-drac-red/10 text-drac-red'">
                                                    exit @{{ cmd.exit_code ?? '?' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No artisan commands were executed.</p>
                            </div>
                        </div>

                        {{-- Scheduled Tasks Tab --}}
                        <div v-show="activeTab === 'scheduled'" class="dd-fade">
                            <template v-if="(activeRun.data.scheduled_tasks || []).length > 0">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="(st, si) in activeRun.data.scheduled_tasks" :key="si" class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <svg class="w-3.5 h-3.5 text-drac-yellow shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <span class="text-drac-fg text-sm font-mono truncate">@{{ st.command }}</span>
                                            </div>
                                            <div class="flex items-center gap-2.5 shrink-0">
                                                <span class="bg-drac-bg text-drac-comment text-[11px] px-2 py-0.5 rounded border border-drac-border font-mono">@{{ st.expression }}</span>
                                                <span v-if="st.duration_s != null" class="text-drac-comment text-xs font-medium">@{{ Number(st.duration_s).toFixed(2) }}s</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No scheduled tasks ran.</p>
                            </div>
                        </div>

                        {{-- Notifications Tab --}}
                        <div v-show="activeTab === 'notifications'" class="dd-fade">
                            <template v-if="(activeRun.data.notifications || []).length > 0">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="divide-y divide-drac-border/60">
                                        <div v-for="(notif, ni) in activeRun.data.notifications" :key="ni" class="px-5 py-3 hover:bg-drac-current/30 transition">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <svg class="w-3.5 h-3.5 text-drac-pink shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                                                    <span class="text-drac-fg text-sm font-mono truncate">@{{ notif.notification ? notif.notification.split('\\').pop() : '—' }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <span class="bg-drac-bg text-drac-comment text-[11px] px-2 py-0.5 rounded border border-drac-border font-medium">@{{ notif.channel }}</span>
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="notif.sent ? 'bg-drac-green/10 text-drac-green' : 'bg-drac-orange/10 text-drac-orange'">
                                                        @{{ notif.sent ? 'SENT' : 'PENDING' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="text-drac-comment text-xs mt-1 ml-6 font-mono truncate">@{{ notif.notifiable }}</div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center py-10 bg-drac-surface rounded-xl border border-drac-border">
                                <p class="text-drac-comment text-sm">No notifications were sent.</p>
                            </div>
                        </div>

                        {{-- Request Tab --}}
                        <div v-show="activeTab === 'request'" class="dd-fade">
                            <div class="space-y-4">
                                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="px-5 py-3 border-b border-drac-border">
                                        <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Request Headers</span>
                                    </div>
                                    <div class="px-5 py-4">
                                        <pre class="text-drac-cyan text-xs font-mono leading-relaxed overflow-x-auto">@{{ JSON.stringify((activeRun.data.request || {}).headers || {}, null, 2) }}</pre>
                                    </div>
                                </div>
                                <div v-if="activeRun.data.request && activeRun.data.request.payload && Object.keys(activeRun.data.request.payload).length" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="px-5 py-3 border-b border-drac-border">
                                        <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Request Payload</span>
                                    </div>
                                    <div class="px-5 py-4">
                                        <pre class="text-drac-yellow text-xs font-mono leading-relaxed overflow-x-auto">@{{ JSON.stringify(activeRun.data.request.payload, null, 2) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Response Tab --}}
                        <div v-show="activeTab === 'response'" class="dd-fade">
                            <div v-if="activeRun.data.response" class="space-y-4">
                                <div class="grid grid-cols-3 gap-3 mb-4">
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Status</div>
                                        <div class="text-lg font-extrabold leading-none" :class="(activeRun.data.response.status_code || 0) < 300 ? 'text-drac-green' : 'text-drac-red'">@{{ activeRun.data.response.status_code || '—' }}</div>
                                    </div>
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Size</div>
                                        <div class="text-lg font-extrabold text-drac-fg leading-none">@{{ formatBytes(activeRun.data.response.size) }}</div>
                                    </div>
                                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Content Type</div>
                                        <div class="text-xs font-semibold text-drac-fg font-mono leading-none mt-1">@{{ responseContentType }}</div>
                                    </div>
                                </div>
                                <div v-if="activeRun.data.response.headers && Object.keys(activeRun.data.response.headers).length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                                    <div class="px-5 py-3 border-b border-drac-border">
                                        <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Response Headers</span>
                                    </div>
                                    <div class="px-5 py-4">
                                        <pre class="text-drac-cyan text-xs font-mono leading-relaxed overflow-x-auto">@{{ JSON.stringify(activeRun.data.response.headers, null, 2) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Empty State --}}
            <div v-else-if="!loading" class="bg-drac-surface rounded-xl border border-drac-border px-5 py-16 text-center">
                <div class="w-14 h-14 rounded-2xl bg-drac-current flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-drac-comment" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/></svg>
                </div>
                <p class="text-drac-fg text-sm font-medium mb-1">Enter a URL to profile</p>
                <p class="text-drac-comment text-xs">Results will appear here without navigating away. Run multiple times to compare.</p>
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
            runs: [],
            activeRunIndex: -1,
            activeTab: 'queries',
            searchHistory: [],
            showSuggestions: false,
            topRoutes: @json($topRoutes),
            explaining: {},
            explainResults: {},
            httpExpanded: {},
        };
    },
    computed: {
        activeRun() {
            return this.activeRunIndex >= 0 ? this.runs[this.activeRunIndex] : null;
        },
        previousRun() {
            if (!this.activeRun) return null;
            const url = this.activeRun.url;
            for (let i = this.activeRunIndex + 1; i < this.runs.length; i++) {
                if (this.runs[i].url === url) return this.runs[i];
            }
            return null;
        },
        durationDelta() {
            if (!this.previousRun) return 0;
            return Math.round(this.activeRun.duration - this.previousRun.duration);
        },
        queryDelta() {
            if (!this.previousRun) return 0;
            return this.activeRun.queries - this.previousRun.queries;
        },
        perfBadge() {
            if (!this.activeRun) return { label: '', class: '' };
            const d = this.activeRun.duration;
            if (d < 100) return { label: 'Fast', class: 'bg-drac-green/10 text-drac-green' };
            if (d < 500) return { label: 'Normal', class: 'bg-drac-orange/10 text-drac-orange' };
            return { label: 'Slow', class: 'bg-drac-red/10 text-drac-red' };
        },
        nPlusOne() {
            return this.activeRun?.data?.n_plus_one || [];
        },
        queryList() {
            return this.activeRun?.data?.queries || [];
        },
        queryGroups() {
            const groups = {};
            for (const q of this.queryList) {
                const n = q.sql.replace(/\s+/g, ' ').trim();
                groups[n] = (groups[n] || 0) + 1;
            }
            return groups;
        },
        duplicateGroups() {
            return Object.values(this.queryGroups).filter(c => c > 1).length;
        },
        duplicateQueryCount() {
            const dupes = Object.values(this.queryGroups).filter(c => c > 1);
            return dupes.reduce((sum, c) => sum + c, 0) - dupes.length;
        },
        queryStats() {
            const queries = this.queryList;
            if (!queries.length) return { totalTime: 0, avgTime: 0, maxTime: 0 };
            const times = queries.map(q => Number(q.time_ms) || 0);
            const total = times.reduce((s, t) => s + t, 0);
            return { totalTime: total, avgTime: total / times.length, maxTime: Math.max(...times) };
        },
        cacheStats() {
            const cache = this.activeRun?.data?.cache || [];
            const hits = cache.filter(c => c.type === 'hit').length;
            const misses = cache.filter(c => c.type === 'miss').length;
            const total = hits + misses;
            return { hits, misses, hitRate: total > 0 ? Math.round(hits / total * 100) : 0 };
        },
        middlewareTiming() {
            return this.activeRun?.data?.middleware_timing || [];
        },
        lifecyclePhases() {
            return this.activeRun?.data?.lifecycle?.phases || [];
        },
        maxLifecycleMem() {
            const phases = this.lifecyclePhases;
            if (!phases.length) return 1;
            return Math.max(...phases.map(p => p.memory_bytes)) || 1;
        },
        responseContentType() {
            const headers = this.activeRun?.data?.response?.headers || {};
            const ct = headers['content-type'];
            if (!ct) return '—';
            return Array.isArray(ct) ? ct[0] : ct;
        },
        suggestions() {
            const q = this.url.toLowerCase();
            const items = [];
            const seen = new Set();

            for (const h of this.searchHistory) {
                if (!q || h.toLowerCase().includes(q)) {
                    if (!seen.has(h)) {
                        seen.add(h);
                        items.push({ url: h, type: 'history', visits: null });
                    }
                }
                if (items.length >= 5) break;
            }

            for (const r of this.topRoutes) {
                if (!q || r.url.toLowerCase().includes(q)) {
                    if (!seen.has(r.url)) {
                        seen.add(r.url);
                        items.push({ url: r.url, type: 'route', visits: r.visit_count });
                    }
                }
                if (items.length >= 15) break;
            }

            return items;
        },
        tabDefs() {
            const d = this.activeRun?.data || {};
            const inertiaCount = d.inertia && (d.inertia.component || (Array.isArray(d.inertia) && d.inertia.length)) ? 1 : 0;
            return [
                { key: 'queries', label: 'Queries', count: (d.queries || []).length, icon: 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375' },
                { key: 'route', label: 'Route', count: null, icon: 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z' },
                { key: 'events', label: 'Events', count: (d.events || []).length, icon: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z' },
                { key: 'views', label: 'Views', count: (d.views || []).length, icon: 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z' },
                { key: 'cache', label: 'Cache', count: (d.cache || []).length, icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z' },
                { key: 'inertia', label: 'Inertia', count: inertiaCount, icon: 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5' },
                { key: 'mail', label: 'Mail', count: (d.mail || []).length, icon: 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75' },
                { key: 'http', label: 'HTTP', count: (d.http_client || []).length, icon: 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418' },
                { key: 'jobs', label: 'Jobs', count: (d.jobs || []).length, icon: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0' },
                { key: 'commands', label: 'Commands', count: (d.commands || []).length, icon: 'M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z' },
                { key: 'scheduled', label: 'Scheduled', count: (d.scheduled_tasks || []).length, icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' },
                { key: 'notifications', label: 'Notifs', count: (d.notifications || []).length, icon: 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0' },
                { key: 'request', label: 'Request', count: null, icon: 'M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859' },
                { key: 'response', label: 'Response', count: null, icon: 'M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5' },
            ];
        },
    },
    mounted() {
        try { this.searchHistory = JSON.parse(localStorage.getItem('digdeep_profiler_history') || '[]'); } catch { this.searchHistory = []; }
    },
    methods: {
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        hideSuggestions() { setTimeout(() => this.showSuggestions = false, 200); },
        saveToHistory(u) {
            if (!u || u === '/') return;
            this.searchHistory = [u, ...this.searchHistory.filter(h => h !== u)].slice(0, 30);
            localStorage.setItem('digdeep_profiler_history', JSON.stringify(this.searchHistory));
        },
        truncate(str, len) {
            if (!str) return '';
            return str.length > len ? str.substring(0, len) + '...' : str;
        },
        formatTime(ts) {
            const d = new Date(ts);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },
        formatBytes(bytes) {
            if (bytes === 0 || bytes === undefined || bytes === null) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },
        phaseColor(name) {
            const colors = { bootstrap: 'bg-drac-cyan', routing: 'bg-drac-yellow', controller: 'bg-drac-purple', view: 'bg-drac-green', response: 'bg-drac-pink' };
            return colors[name] || 'bg-drac-comment';
        },
        isDuplicateQuery(sql) {
            const n = sql.replace(/\s+/g, ' ').trim();
            return (this.queryGroups[n] || 0) > 1;
        },
        queryGroupCount(sql) {
            const n = sql.replace(/\s+/g, ' ').trim();
            return this.queryGroups[n] || 0;
        },
        toggleHttpExpanded(i) {
            this.httpExpanded = { ...this.httpExpanded, [i]: !this.httpExpanded[i] };
        },
        selectRun(i) {
            this.activeRunIndex = i;
            this.activeTab = 'queries';
            this.explainResults = {};
            this.httpExpanded = {};
        },
        async triggerProfile() {
            if (!this.url) { this.error = 'Enter a URL to profile.'; return; }
            this.loading = true;
            this.error = '';
            this.explainResults = {};
            this.httpExpanded = {};
            const u = this.url;
            this.saveToHistory(u);

            try {
                const triggerRes = await fetch('/digdeep/api/trigger', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ url: u, method: this.method })
                });
                const triggerData = await triggerRes.json();

                if (!triggerData.profile_id) {
                    this.error = triggerData.error || 'Profiling failed.';
                    return;
                }

                const exportRes = await fetch('/digdeep/api/profile/' + triggerData.profile_id + '/export', {
                    headers: { 'Accept': 'application/json' }
                });
                const fullData = await exportRes.json();

                const run = {
                    id: triggerData.profile_id,
                    method: this.method,
                    url: u,
                    status: triggerData.status_code,
                    duration: triggerData.duration_ms,
                    queries: triggerData.query_count,
                    memory: triggerData.memory_peak_mb,
                    timestamp: new Date().toISOString(),
                    data: fullData.data || fullData,
                };

                this.runs.unshift(run);
                this.activeRunIndex = 0;
                this.activeTab = 'queries';
            } catch (e) {
                this.error = 'Failed: ' + e.message;
            } finally {
                this.loading = false;
            }
        },
        async explainQuery(qi) {
            if (this.explaining[qi]) return;
            this.explaining = { ...this.explaining, [qi]: true };

            try {
                const q = this.activeRun.data.queries[qi];
                const res = await fetch('/digdeep/api/explain', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ sql: q.sql })
                });
                const data = await res.json();
                this.explainResults = { ...this.explainResults, [qi]: JSON.stringify(data.plan, null, 2) };
            } catch (e) {
                this.explainResults = { ...this.explainResults, [qi]: 'EXPLAIN failed: ' + e.message };
            } finally {
                this.explaining = { ...this.explaining, [qi]: false };
            }
        },
    }
}).mount('#digdeep-profiler');
</script>
@endsection
