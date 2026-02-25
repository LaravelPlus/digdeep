@extends('digdeep::layout')

@section('title', 'Profile — ' . $profile['url'])

@php
    $data = $profile['data'];
    $queries = $data['queries'] ?? [];
    $events = $data['events'] ?? [];
    $views = $data['views'] ?? [];
    $cache = $data['cache'] ?? [];
    $mail = $data['mail'] ?? [];
    $http = $data['http_client'] ?? [];
    $jobs = $data['jobs'] ?? [];
    $commands = $data['commands'] ?? [];
    $scheduledTasks = $data['scheduled_tasks'] ?? [];
    $notifications = $data['notifications'] ?? [];
    $route = $data['route'] ?? [];
    $inertia = $data['inertia'] ?? [];
    $isAjax = $data['is_ajax'] ?? false;
    $nPlusOne = $data['n_plus_one'] ?? [];
    $middlewareTiming = $data['middleware_timing'] ?? [];
    $middlewarePipelineMs = $data['middleware_pipeline_ms'] ?? null;
    $lifecycle = $data['lifecycle'] ?? [];

    $queryGroups = [];
    foreach ($queries as $q) {
        $normalized = preg_replace('/\s+/', ' ', trim($q['sql']));
        $queryGroups[$normalized] = ($queryGroups[$normalized] ?? 0) + 1;
    }
    $duplicates = array_filter($queryGroups, fn($c) => $c > 1);
    $duplicateCount = array_sum($duplicates) - count($duplicates);

    // Generate query hints for this profile
    $queryHints = \LaravelPlus\DigDeep\Analyzers\QueryAnalyzer::generateHints($queries);

    $totalQueryTime = array_sum(array_column($queries, 'time_ms'));
    $maxQueryTime = count($queries) ? max(array_column($queries, 'time_ms')) : 0;

    $cacheHits = count(array_filter($cache, fn($c) => $c['type'] === 'hit'));
    $cacheMisses = count(array_filter($cache, fn($c) => $c['type'] === 'miss'));

    $perfColor = $profile['duration_ms'] < 100 ? 'drac-green' : ($profile['duration_ms'] < 500 ? 'drac-orange' : 'drac-red');
    $perfLabel = $profile['duration_ms'] < 100 ? 'Fast' : ($profile['duration_ms'] < 500 ? 'Normal' : 'Slow');
@endphp

@section('content')
<div id="digdeep-show" v-cloak>
    {{-- Back + Title --}}
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3.5">
            <a href="/digdeep" class="w-8 h-8 rounded-lg bg-drac-surface border border-drac-border flex items-center justify-center text-drac-comment hover:text-drac-purple hover:border-drac-purple/40 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-[48px] py-0.5 rounded-md text-[10px] font-bold tracking-wide
                    {{ $profile['method'] === 'GET' ? 'bg-drac-green/10 text-drac-green' : '' }}
                    {{ $profile['method'] === 'POST' ? 'bg-drac-cyan/10 text-drac-cyan' : '' }}
                    {{ in_array($profile['method'], ['PUT', 'PATCH']) ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                    {{ $profile['method'] === 'DELETE' ? 'bg-drac-red/10 text-drac-red' : '' }}
                ">{{ $profile['method'] }}</span>
                <h1 class="text-lg font-bold text-drac-fg tracking-tight font-mono">{{ $profile['url'] }}</h1>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $profile['status_code'] < 300 ? 'bg-drac-green' : ($profile['status_code'] < 400 ? 'bg-drac-orange' : 'bg-drac-red') }}"></span>
                    <span class="text-base font-bold {{ $profile['status_code'] < 300 ? 'text-drac-green' : ($profile['status_code'] < 400 ? 'text-drac-orange' : 'text-drac-red') }}">{{ $profile['status_code'] }}</span>
                </span>
                <span class="text-[10px] font-bold bg-{{ $perfColor }}/10 text-{{ $perfColor }} px-2 py-0.5 rounded-full">{{ $perfLabel }}</span>
                @if($isAjax)
                <span class="text-[10px] font-bold bg-drac-pink/10 text-drac-pink px-2 py-0.5 rounded-full">XHR</span>
                @endif
                @if(!empty($inertia))
                <span class="text-[10px] font-bold bg-drac-purple/10 text-drac-purple px-2 py-0.5 rounded-full">Inertia</span>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if(($data['performance']['profiling_overhead_ms'] ?? null) !== null)
            <span class="text-[10px] font-medium bg-drac-current text-drac-comment px-2 py-0.5 rounded-full" title="Profiling overhead">
                +{{ number_format($data['performance']['profiling_overhead_ms'], 1) }}ms overhead
            </span>
            @endif
            <span class="text-drac-comment text-xs font-medium">{{ $profile['created_at'] }}</span>
            <a href="/digdeep/api/profile/{{ $profile['id'] }}/export" class="text-drac-comment text-xs hover:text-drac-cyan transition flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-drac-cyan/10" title="Export JSON">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export
            </a>
            <button @click="replayProfile()" class="text-drac-comment text-xs hover:text-drac-green transition flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-drac-green/10" title="Replay this request">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                Replay
            </button>
            <button @click="deleteProfile()" class="text-drac-comment text-xs hover:text-drac-red transition flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-drac-red/10">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                Delete
            </button>
        </div>
    </div>

    {{-- Metrics cards --}}
    <div class="grid grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
        <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Duration</div>
            <div class="text-lg font-extrabold text-drac-cyan leading-none">{{ number_format($profile['duration_ms'], 0) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Queries</div>
            <div class="text-lg font-extrabold text-drac-purple leading-none">{{ $profile['query_count'] }}</div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Memory</div>
            <div class="text-lg font-extrabold text-drac-orange leading-none">{{ number_format($profile['memory_peak_mb'], 1) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">MB</span></div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Views</div>
            <div class="text-lg font-extrabold text-drac-pink leading-none">{{ count($views) }}</div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Events</div>
            <div class="text-lg font-extrabold text-drac-green leading-none">{{ count($events) }}</div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Cache Ops</div>
            <div class="text-lg font-extrabold {{ count($cache) > 0 ? 'text-drac-yellow' : 'text-drac-comment' }} leading-none">{{ count($cache) }}</div>
        </div>
    </div>

    {{-- Tags & Notes --}}
    <div class="grid grid-cols-2 gap-3 mb-5">
        <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
            <label class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold block mb-1.5">Tags</label>
            <input type="text" v-model="tags" @blur="saveTags()" @keydown.enter="saveTags()" placeholder="e.g. slow, homepage, regression"
                class="w-full bg-drac-bg border border-drac-border text-drac-fg text-xs font-mono rounded-lg px-3 py-1.5 focus:outline-none focus:border-drac-purple placeholder-drac-comment">
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
            <label class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold block mb-1.5">Notes</label>
            <input type="text" v-model="notes" @blur="saveNotes()" @keydown.enter="saveNotes()" placeholder="Add a note about this profile..."
                class="w-full bg-drac-bg border border-drac-border text-drac-fg text-xs font-mono rounded-lg px-3 py-1.5 focus:outline-none focus:border-drac-purple placeholder-drac-comment">
        </div>
    </div>

    {{-- N+1 Warning (from QueryAnalyzer) --}}
    @if(count($nPlusOne) > 0)
    <div class="bg-drac-orange/8 border border-drac-orange/25 rounded-xl px-5 py-3 mb-5 flex items-start gap-3">
        <svg class="w-5 h-5 text-drac-orange shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        <div>
            <div class="text-drac-orange text-sm font-semibold">N+1 Query Detected</div>
            <div class="text-drac-orange/70 text-xs mt-0.5">{{ count($nPlusOne) }} repeated {{ count($nPlusOne) === 1 ? 'pattern' : 'patterns' }} found. Consider eager loading.</div>
            <div class="mt-2 space-y-1.5">
                @foreach($nPlusOne as $pattern)
                <div class="bg-drac-current rounded-lg px-3 py-2">
                    <div class="text-drac-orange/70 text-xs font-mono truncate">
                        <span class="text-drac-orange font-semibold">{{ $pattern['count'] }}x</span> {{ Str::limit($pattern['pattern'], 120) }}
                    </div>
                    @if(!empty($pattern['table']))
                    <div class="text-drac-comment text-[10px] mt-0.5">Table: <span class="text-drac-cyan">{{ $pattern['table'] }}</span></div>
                    @endif
                    @if(!empty($pattern['suggestion']))
                    <div class="text-drac-green text-[10px] mt-1 flex items-center gap-1">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.44 2.278a3.68 3.68 0 01-2.38 0"/></svg>
                        {{ $pattern['suggestion'] }}
                    </div>
                    @endif
                    @if(!empty($pattern['callers']))
                    <div class="text-drac-comment text-[10px] mt-1 font-mono">Callers: {{ implode(', ', array_slice($pattern['callers'], 0, 3)) }}</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @elseif(count($duplicates) > 0)
    <div class="bg-drac-orange/8 border border-drac-orange/25 rounded-xl px-5 py-3 mb-5 flex items-start gap-3">
        <svg class="w-5 h-5 text-drac-orange shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        <div>
            <div class="text-drac-orange text-sm font-semibold">Duplicate Queries Detected</div>
            <div class="text-drac-orange/70 text-xs mt-0.5">{{ count($duplicates) }} duplicate {{ count($duplicates) === 1 ? 'query' : 'queries' }} found ({{ $duplicateCount }} extra {{ $duplicateCount === 1 ? 'execution' : 'executions' }}).</div>
        </div>
    </div>
    @endif

    {{-- Lifecycle Memory Breakdown --}}
    @php $lifecyclePhases = $lifecycle['phases'] ?? []; @endphp
    @if(!empty($lifecyclePhases))
    <div class="bg-drac-surface rounded-xl border border-drac-border p-4 mb-5">
        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-3">Lifecycle Memory</div>
        <div class="flex items-end gap-2 h-12">
            @php $maxMem = max(array_column($lifecyclePhases, 'memory_bytes')) ?: 1; @endphp
            @foreach($lifecyclePhases as $phase)
            @php
                $memPct = ($phase['memory_bytes'] / $maxMem) * 100;
                $colors = ['bootstrap' => 'bg-drac-cyan', 'routing' => 'bg-drac-yellow', 'controller' => 'bg-drac-purple', 'view' => 'bg-drac-green', 'response' => 'bg-drac-pink'];
                $color = $colors[$phase['name']] ?? 'bg-drac-comment';
            @endphp
            <div class="flex-1 flex flex-col items-center gap-1">
                <div class="w-full rounded-t {{ $color }} opacity-80" style="height: {{ max(4, $memPct) }}%"></div>
                <span class="text-[9px] text-drac-comment font-bold capitalize">{{ $phase['name'] }}</span>
                <span class="text-[9px] text-drac-fg font-mono">{{ number_format($phase['memory_bytes'] / 1024 / 1024, 1) }}MB</span>
                @if(isset($phase['memory_delta_bytes']))
                <span class="text-[9px] font-mono {{ $phase['memory_delta_bytes'] > 0 ? 'text-drac-orange' : 'text-drac-green' }}">{{ $phase['memory_delta_bytes'] > 0 ? '+' : '' }}{{ number_format($phase['memory_delta_bytes'] / 1024, 0) }}KB</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Layout: sidebar + content --}}
    <div class="flex gap-5 items-start">
        {{-- Sidebar --}}
        <nav class="w-[190px] shrink-0 sticky top-[110px]">
            <div class="space-y-0.5">
                @php
                    $tabs = [
                        'queries'  => ['Queries',  count($queries),  'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375'],
                        'route'    => ['Route',     null,             'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z'],
                        'events'   => ['Events',    count($events),   'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'],
                        'views'    => ['Views',     count($views),    'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z'],
                        'cache'    => ['Cache',     count($cache),    'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
                        'inertia'  => ['Inertia',   !empty($inertia) ? 1 : 0, 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5'],
                        'mail'     => ['Mail',      count($mail),     'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75'],
                        'http'     => ['HTTP',      count($http),     'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418'],
                        'jobs'     => ['Jobs',      count($jobs),     'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0'],
                        'commands' => ['Commands',  count($commands), 'M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z'],
                        'scheduled' => ['Scheduled', count($scheduledTasks), 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
                        'notifications' => ['Notifs', count($notifications), 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'],
                        'request'  => ['Request',   null,             'M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859'],
                        'response' => ['Response',  null,             'M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5'],
                    ];
                @endphp
                @foreach($tabs as $key => [$label, $count, $iconPath])
                <button @click="tab = '{{ $key }}'" class="dd-sidebar-link" :class="tab === '{{ $key }}' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/></svg>
                    <span class="flex-1">{{ $label }}</span>
                    @if($count !== null)
                        @if($count > 0)
                        <span class="text-[10px] font-bold opacity-60">{{ $count }}</span>
                        @else
                        <span class="text-[10px] font-bold opacity-30">0</span>
                        @endif
                    @endif
                </button>
                @endforeach
            </div>
        </nav>

        {{-- Content panel --}}
        <div class="flex-1 min-w-0">

            {{-- ═══ Queries ═══ --}}
            <div v-show="tab === 'queries'" class="dd-fade">
                @if(empty($queries))
                    @include('digdeep::_empty', ['message' => 'No database queries were executed.'])
                @else
                    <div class="grid grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                        <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Total Time</div>
                            <div class="text-sm font-extrabold text-drac-purple leading-none">{{ number_format($totalQueryTime, 2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                        </div>
                        <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Average</div>
                            <div class="text-sm font-extrabold text-drac-fg leading-none">{{ number_format($totalQueryTime / count($queries), 2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                        </div>
                        <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Slowest</div>
                            <div class="text-sm font-extrabold text-drac-orange leading-none">{{ number_format($maxQueryTime, 2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                        </div>
                        @if(count($duplicates) > 0)
                        <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Duplicates</div>
                            <div class="text-sm font-extrabold text-drac-orange leading-none flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                                {{ count($duplicates) }}
                            </div>
                        </div>
                        @endif
                    </div>
                    @if(!empty($queryHints))
                    <div class="bg-drac-yellow/8 border border-drac-yellow/25 rounded-xl px-5 py-3 mb-4">
                        <div class="text-drac-yellow text-xs font-semibold mb-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.44 2.278a3.68 3.68 0 01-2.38 0"/></svg>
                            Optimization Hints
                        </div>
                        <div class="space-y-1.5">
                            @foreach($queryHints as $hint)
                            <div class="flex items-start gap-2">
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded shrink-0 mt-0.5
                                    {{ $hint['severity'] === 'warning' ? 'bg-drac-orange/10 text-drac-orange' : 'bg-drac-yellow/10 text-drac-yellow' }}">
                                    {{ strtoupper($hint['type']) }}
                                </span>
                                <span class="text-drac-fg text-xs">{{ $hint['message'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <div class="space-y-2">
                        @foreach($queries as $i => $q)
                        @php
                            $pct = $maxQueryTime > 0 ? ($q['time_ms'] / $maxQueryTime) * 100 : 0;
                            $normalized = preg_replace('/\s+/', ' ', trim($q['sql']));
                            $isDuplicate = ($queryGroups[$normalized] ?? 0) > 1;
                        @endphp
                        <div class="bg-drac-surface rounded-xl border border-drac-border p-4 hover:border-drac-comment/40 transition {{ $isDuplicate ? 'ring-1 ring-drac-orange/25' : '' }}">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-drac-comment text-[11px] font-bold">#{{ $i + 1 }}</span>
                                    @if($isDuplicate)
                                    <span class="text-[10px] font-bold text-drac-orange bg-drac-orange/10 px-1.5 py-0.5 rounded">DUP {{ $queryGroups[$normalized] }}x</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="font-bold {{ $q['time_ms'] > 100 ? 'text-drac-red' : ($q['time_ms'] > 10 ? 'text-drac-orange' : 'text-drac-green') }}">{{ number_format($q['time_ms'], 2) }}ms</span>
                                    <span class="text-drac-comment font-mono text-[11px]">{{ $q['caller'] }}</span>
                                </div>
                            </div>
                            <div class="w-full bg-drac-current rounded-full h-[3px] mb-2.5">
                                <div class="dd-bar h-[3px] rounded-full {{ $q['time_ms'] > 100 ? 'bg-drac-red' : ($q['time_ms'] > 10 ? 'bg-drac-orange' : 'bg-gradient-to-r from-drac-purple to-drac-pink') }}" style="width: {{ $pct }}%"></div>
                            </div>
                            <code class="text-drac-cyan text-xs font-mono leading-relaxed break-all block">{{ $q['sql'] }}</code>
                            @if(!empty($q['bindings']))
                            <div class="mt-2 text-[11px] text-drac-comment font-mono bg-drac-bg rounded px-2.5 py-1.5 border border-drac-border">
                                <span class="text-drac-comment">Bindings:</span> <span class="text-drac-yellow">{{ json_encode($q['bindings']) }}</span>
                            </div>
                            @endif
                            @if(str_starts_with(strtoupper(trim($q['sql'])), 'SELECT'))
                            <div class="mt-2 flex items-center gap-2">
                                <button @click="explainQuery({{ $i }})" class="text-[10px] font-semibold text-drac-comment hover:text-drac-purple transition flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                                    EXPLAIN
                                </button>
                            </div>
                            <div v-if="explainResults[{{ $i }}]" class="mt-2 bg-drac-bg rounded-lg border border-drac-border p-3 dd-fade">
                                <div class="text-[10px] text-drac-comment font-bold uppercase tracking-wider mb-1.5">Query Plan</div>
                                <div v-for="(row, ri) in explainResults[{{ $i }}]" :key="ri" class="text-[11px] text-drac-fg font-mono">@{{ row.detail || JSON.stringify(row) }}</div>
                                <div v-if="explainResults[{{ $i }}].length === 0" class="text-[11px] text-drac-comment">No plan data available.</div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ═══ Route ═══ --}}
            <div v-show="tab === 'route'" class="dd-fade">
                @if(empty($route))
                    @include('digdeep::_empty', ['message' => 'No route matched this request.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border">
                            <div class="px-5 py-4 flex items-center gap-4">
                                <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Name</div>
                                <div class="text-drac-fg text-sm font-semibold">{{ $route['name'] ?? '(unnamed)' }}</div>
                            </div>
                            <div class="px-5 py-4 flex items-center gap-4">
                                <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Action</div>
                                <div class="text-drac-cyan text-sm font-semibold font-mono break-all">{{ $route['action'] ?? '—' }}</div>
                            </div>
                            @if(!empty($route['parameters']))
                            <div class="px-5 py-4">
                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-2.5">Parameters</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($route['parameters'] as $key => $value)
                                    <div class="bg-drac-bg rounded-lg px-3 py-1.5 border border-drac-border text-xs">
                                        <span class="text-drac-purple font-mono font-semibold">{{ $key }}</span>
                                        <span class="text-drac-comment mx-1">=</span>
                                        <span class="text-drac-fg font-mono">{{ is_string($value) ? $value : json_encode($value) }}</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                            @if(!empty($route['middleware']))
                            <div class="px-5 py-4">
                                <div class="flex items-center gap-2.5 mb-2.5">
                                    <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Middleware Stack</div>
                                    @if($middlewarePipelineMs !== null)
                                    <span class="text-[10px] font-bold text-drac-cyan bg-drac-cyan/10 px-1.5 py-0.5 rounded">{{ number_format($middlewarePipelineMs, 1) }}ms total</span>
                                    @endif
                                </div>
                                @if(!empty($middlewareTiming))
                                <div class="space-y-1.5">
                                    @foreach($middlewareTiming as $mwt)
                                    <div class="flex items-center gap-2.5">
                                        <span class="bg-drac-bg text-drac-fg text-[11px] px-2.5 py-1 rounded-md border border-drac-border font-mono font-medium flex-1 min-w-0 truncate">{{ $mwt['name'] }}</span>
                                        @if(!empty($mwt['is_estimated']))
                                        <span class="text-[9px] font-semibold text-drac-comment bg-drac-current px-1.5 py-0.5 rounded shrink-0">est.</span>
                                        @endif
                                        <span class="text-[10px] font-bold font-mono shrink-0 {{ $mwt['duration_ms'] > 10 ? 'text-drac-orange' : 'text-drac-green' }}">{{ number_format($mwt['duration_ms'], 2) }}ms</span>
                                    </div>
                                    @endforeach
                                </div>
                                @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($route['middleware'] as $mw)
                                    <span class="bg-drac-bg text-drac-fg text-[11px] px-2.5 py-1 rounded-md border border-drac-border font-mono font-medium">{{ is_string($mw) ? $mw : get_class($mw) }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Events ═══ --}}
            <div v-show="tab === 'events'" class="dd-fade">
                @if(empty($events))
                    @include('digdeep::_empty', ['message' => 'No events were dispatched.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($events as $i => $e)
                            <div class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-drac-current/30 transition">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="text-drac-comment text-[11px] font-bold w-6 shrink-0 text-right">{{ $i + 1 }}</span>
                                    <span class="text-drac-fg text-sm font-mono truncate">{{ $e['event'] }}</span>
                                </div>
                                <span class="text-drac-comment text-xs shrink-0 max-w-[200px] truncate font-mono">{{ $e['payload_summary'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Views ═══ --}}
            <div v-show="tab === 'views'" class="dd-fade">
                @if(empty($views))
                    @include('digdeep::_empty', ['message' => 'No views were rendered.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($views as $v)
                            <div class="px-5 py-3 hover:bg-drac-current/30 transition">
                                <div class="flex items-center gap-2 mb-1">
                                    <svg class="w-3.5 h-3.5 text-drac-pink shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="text-drac-fg text-sm font-semibold">{{ $v['name'] }}</span>
                                </div>
                                <div class="text-drac-comment text-xs font-mono ml-5.5">{{ $v['path'] }}</div>
                                @if(!empty($v['data_keys']))
                                <div class="flex flex-wrap gap-1 mt-1.5 ml-5.5">
                                    @foreach($v['data_keys'] as $key)
                                    <span class="bg-drac-bg text-drac-comment text-[10px] px-1.5 py-0.5 rounded border border-drac-border font-mono">{{ $key }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Cache ═══ --}}
            <div v-show="tab === 'cache'" class="dd-fade">
                @if(empty($cache))
                    @include('digdeep::_empty', ['message' => 'No cache operations.'])
                @else
                    @if($cacheHits > 0 || $cacheMisses > 0)
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Hits</div>
                            <div class="text-sm font-extrabold text-drac-green leading-none">{{ $cacheHits }}</div>
                        </div>
                        <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Misses</div>
                            <div class="text-sm font-extrabold text-drac-red leading-none">{{ $cacheMisses }}</div>
                        </div>
                        <div class="bg-drac-surface rounded-xl border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Hit Rate</div>
                            @if($cacheHits + $cacheMisses > 0)
                            <div class="text-sm font-extrabold text-drac-fg leading-none">{{ round($cacheHits / ($cacheHits + $cacheMisses) * 100) }}%</div>
                            @else
                            <div class="text-sm font-extrabold text-drac-comment leading-none">—</div>
                            @endif
                        </div>
                    </div>
                    @endif
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($cache as $c)
                            <div class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                <span class="text-drac-fg text-sm font-mono truncate">{{ $c['key'] }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0
                                    {{ $c['type'] === 'hit' ? 'bg-drac-green/10 text-drac-green' : '' }}
                                    {{ $c['type'] === 'miss' ? 'bg-drac-red/10 text-drac-red' : '' }}
                                    {{ $c['type'] === 'write' ? 'bg-drac-cyan/10 text-drac-cyan' : '' }}
                                ">{{ strtoupper($c['type']) }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Inertia ═══ --}}
            <div v-show="tab === 'inertia'" class="dd-fade">
                @if(empty($inertia))
                    @include('digdeep::_empty', ['message' => 'No Inertia data detected for this request.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border">
                            <div class="px-5 py-4 flex items-center gap-4">
                                <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Component</div>
                                <div class="text-drac-purple text-sm font-semibold font-mono">{{ $inertia['component'] ?? '—' }}</div>
                            </div>
                            <div class="px-5 py-4 flex items-center gap-4">
                                <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">URL</div>
                                <div class="text-drac-cyan text-sm font-semibold font-mono">{{ $inertia['url'] ?? '—' }}</div>
                            </div>
                            @if(!empty($inertia['version']))
                            <div class="px-5 py-4 flex items-center gap-4">
                                <div class="w-[100px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Version</div>
                                <div class="text-drac-fg text-sm font-semibold font-mono break-all">{{ $inertia['version'] }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @if(!empty($inertia['props']))
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mt-4">
                        <div class="px-5 py-3 border-b border-drac-border">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Props</span>
                            <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-1.5 py-0.5 rounded-full ml-2">{{ count($inertia['props']) }}</span>
                        </div>
                        <div class="divide-y divide-drac-border/60">
                            @foreach($inertia['props'] as $propName => $propType)
                            <div class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                <span class="text-drac-purple text-sm font-mono font-semibold">{{ $propName }}</span>
                                <span class="text-drac-comment text-xs font-mono bg-drac-bg px-2 py-0.5 rounded border border-drac-border">{{ $propType }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endif
            </div>

            {{-- ═══ Mail ═══ --}}
            <div v-show="tab === 'mail'" class="dd-fade">
                @if(empty($mail))
                    @include('digdeep::_empty', ['message' => 'No mail was sent.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($mail as $m)
                            <div class="px-5 py-3 hover:bg-drac-current/30 transition flex items-center gap-3">
                                <svg class="w-4 h-4 text-drac-orange shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                                <div>
                                    <div class="text-drac-fg text-sm font-semibold">{{ $m['subject'] }}</div>
                                    <div class="text-drac-comment text-xs mt-0.5">To: {{ $m['to'] }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ HTTP ═══ --}}
            <div v-show="tab === 'http'" class="dd-fade">
                @if(empty($http))
                    @include('digdeep::_empty', ['message' => 'No outgoing HTTP requests.'])
                @else
                    <div class="space-y-2">
                        @foreach($http as $hi => $h)
                        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                            <div class="px-5 py-3 flex items-center justify-between gap-4 cursor-pointer hover:bg-drac-current/30 transition" @click="httpExpanded[{{ $hi }}] = !httpExpanded[{{ $hi }}]; httpExpanded = {...httpExpanded}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $h['method'] === 'GET' ? 'bg-drac-green/10 text-drac-green' : 'bg-drac-cyan/10 text-drac-cyan' }}">{{ $h['method'] }}</span>
                                    <span class="text-drac-fg text-sm truncate font-mono">{{ $h['url'] }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs shrink-0">
                                    <span class="{{ $h['status'] < 300 ? 'text-drac-green' : 'text-drac-red' }} font-bold">{{ $h['status'] }}</span>
                                    <span class="text-drac-comment font-medium">{{ number_format($h['duration_ms'], 1) }}ms</span>
                                    @if(!empty($h['response_size']))
                                    <span class="text-drac-comment font-mono text-[10px]">{{ number_format($h['response_size'] / 1024, 1) }}KB</span>
                                    @endif
                                    <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="httpExpanded[{{ $hi }}] ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                </div>
                            </div>
                            <div v-if="httpExpanded[{{ $hi }}]" class="border-t border-drac-border dd-fade">
                                @if(!empty($h['request_headers']))
                                <div class="px-5 py-3 border-b border-drac-border/50">
                                    <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1.5">Request Headers</div>
                                    <div class="text-[11px] font-mono space-y-0.5">
                                        @foreach($h['request_headers'] as $key => $val)
                                        <div><span class="text-drac-cyan">{{ $key }}:</span> <span class="text-drac-fg">{{ is_array($val) ? implode(', ', $val) : $val }}</span></div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                                @if(!empty($h['request_body']))
                                <div class="px-5 py-3 border-b border-drac-border/50">
                                    <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1.5">Request Body</div>
                                    <pre class="text-drac-yellow text-[11px] font-mono leading-relaxed overflow-x-auto max-h-[200px]">{{ $h['request_body'] }}</pre>
                                </div>
                                @endif
                                @if(!empty($h['response_headers']))
                                <div class="px-5 py-3 border-b border-drac-border/50">
                                    <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1.5">Response Headers</div>
                                    <div class="text-[11px] font-mono space-y-0.5">
                                        @foreach($h['response_headers'] as $key => $val)
                                        <div><span class="text-drac-pink">{{ $key }}:</span> <span class="text-drac-fg">{{ is_array($val) ? implode(', ', $val) : $val }}</span></div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                                @if(!empty($h['response_body']))
                                <div class="px-5 py-3">
                                    <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1.5">Response Body</div>
                                    <pre class="text-drac-green text-[11px] font-mono leading-relaxed overflow-x-auto max-h-[300px]">{{ $h['response_body'] }}</pre>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ═══ Jobs ═══ --}}
            <div v-show="tab === 'jobs'" class="dd-fade">
                @if(empty($jobs))
                    @include('digdeep::_empty', ['message' => 'No jobs were dispatched.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($jobs as $j)
                            <div class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                <span class="text-drac-fg text-sm font-mono">{{ $j['job'] }}</span>
                                <span class="bg-drac-bg text-drac-comment text-[11px] px-2 py-0.5 rounded border border-drac-border font-medium">{{ $j['queue'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Commands ═══ --}}
            <div v-show="tab === 'commands'" class="dd-fade">
                @if(empty($commands))
                    @include('digdeep::_empty', ['message' => 'No artisan commands were executed.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($commands as $cmd)
                            <div class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <svg class="w-3.5 h-3.5 text-drac-cyan shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/></svg>
                                    <span class="text-drac-fg text-sm font-mono truncate">{{ $cmd['command'] }}</span>
                                </div>
                                <div class="flex items-center gap-2.5 shrink-0">
                                    <span class="text-drac-comment text-xs font-medium">{{ number_format($cmd['duration_ms'], 1) }}ms</span>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ ($cmd['exit_code'] ?? 0) === 0 ? 'bg-drac-green/10 text-drac-green' : 'bg-drac-red/10 text-drac-red' }}">
                                        exit {{ $cmd['exit_code'] ?? '?' }}
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Scheduled Tasks ═══ --}}
            <div v-show="tab === 'scheduled'" class="dd-fade">
                @if(empty($scheduledTasks))
                    @include('digdeep::_empty', ['message' => 'No scheduled tasks ran.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($scheduledTasks as $st)
                            <div class="px-5 py-2.5 flex items-center justify-between hover:bg-drac-current/30 transition">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <svg class="w-3.5 h-3.5 text-drac-yellow shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="text-drac-fg text-sm font-mono truncate">{{ $st['command'] }}</span>
                                </div>
                                <div class="flex items-center gap-2.5 shrink-0">
                                    <span class="bg-drac-bg text-drac-comment text-[11px] px-2 py-0.5 rounded border border-drac-border font-mono">{{ $st['expression'] }}</span>
                                    @if($st['duration_s'] !== null)
                                    <span class="text-drac-comment text-xs font-medium">{{ number_format($st['duration_s'], 2) }}s</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Notifications ═══ --}}
            <div v-show="tab === 'notifications'" class="dd-fade">
                @if(empty($notifications))
                    @include('digdeep::_empty', ['message' => 'No notifications were sent.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($notifications as $notif)
                            <div class="px-5 py-3 hover:bg-drac-current/30 transition">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <svg class="w-3.5 h-3.5 text-drac-pink shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                                        <span class="text-drac-fg text-sm font-mono truncate">{{ class_basename($notif['notification']) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="bg-drac-bg text-drac-comment text-[11px] px-2 py-0.5 rounded border border-drac-border font-medium">{{ $notif['channel'] }}</span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $notif['sent'] ? 'bg-drac-green/10 text-drac-green' : 'bg-drac-orange/10 text-drac-orange' }}">
                                            {{ $notif['sent'] ? 'SENT' : 'PENDING' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-drac-comment text-xs mt-1 ml-6 font-mono truncate">{{ $notif['notifiable'] }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Request ═══ --}}
            <div v-show="tab === 'request'" class="dd-fade">
                <div class="space-y-4">
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-3 border-b border-drac-border">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Request Headers</span>
                        </div>
                        <div class="px-5 py-4">
                            <pre class="text-drac-cyan text-xs font-mono leading-relaxed overflow-x-auto">{{ json_encode($data['request']['headers'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                    @if(!empty($data['request']['payload']))
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-3 border-b border-drac-border">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Request Payload</span>
                        </div>
                        <div class="px-5 py-4">
                            <pre class="text-drac-yellow text-xs font-mono leading-relaxed overflow-x-auto">{{ json_encode($data['request']['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ═══ Response ═══ --}}
            <div v-show="tab === 'response'" class="dd-fade">
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Status</div>
                        <div class="text-lg font-extrabold {{ ($data['response']['status_code'] ?? 0) < 300 ? 'text-drac-green' : 'text-drac-red' }} leading-none">{{ $data['response']['status_code'] ?? '—' }}</div>
                    </div>
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Size</div>
                        <div class="text-lg font-extrabold text-drac-fg leading-none">{{ number_format(($data['response']['size'] ?? 0) / 1024, 1) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">KB</span></div>
                    </div>
                    <div class="bg-drac-surface rounded-xl border border-drac-border p-3.5">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Content Type</div>
                        <div class="text-xs font-semibold text-drac-fg font-mono leading-none mt-1">{{ $data['response']['headers']['content-type'][0] ?? '—' }}</div>
                    </div>
                </div>
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                    <div class="px-5 py-3 border-b border-drac-border">
                        <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Response Headers</span>
                    </div>
                    <div class="px-5 py-4">
                        <pre class="text-drac-cyan text-xs font-mono leading-relaxed overflow-x-auto">{{ json_encode($data['response']['headers'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
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
            tab: 'queries',
            tags: @json($profile['tags'] ?? ''),
            notes: @json($profile['notes'] ?? ''),
            explainResults: {},
            httpExpanded: {},
            queries: @json($queries),
        };
    },
    methods: {
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        async deleteProfile() {
            if (!confirm('Delete this profile?')) return;
            await fetch('/digdeep/api/profile/{{ $profile['id'] }}', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' }
            });
            window.location.href = '/digdeep';
        },
        async replayProfile() {
            const r = await fetch('/digdeep/api/profile/{{ $profile['id'] }}/replay', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (d.redirect) window.location.href = d.redirect;
        },
        async saveTags() {
            await fetch('/digdeep/api/profile/{{ $profile['id'] }}/tags', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ tags: this.tags })
            });
        },
        async saveNotes() {
            await fetch('/digdeep/api/profile/{{ $profile['id'] }}/notes', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ notes: this.notes })
            });
        },
        async explainQuery(index) {
            if (this.explainResults[index]) { delete this.explainResults[index]; this.explainResults = {...this.explainResults}; return; }
            try {
                const sql = this.queries[index]?.sql || '';
                const r = await fetch('/digdeep/api/explain', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ sql })
                });
                const d = await r.json();
                this.explainResults = {...this.explainResults, [index]: d.plan || []};
            } catch(e) {
                this.explainResults = {...this.explainResults, [index]: [{detail: 'Error: ' + e.message}]};
            }
        }
    }
}).mount('#digdeep-show');
</script>
@endsection
