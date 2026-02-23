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
    $route = $data['route'] ?? [];
    $inertia = $data['inertia'] ?? [];
    $isAjax = $data['is_ajax'] ?? false;

    $queryGroups = [];
    foreach ($queries as $q) {
        $normalized = preg_replace('/\s+/', ' ', trim($q['sql']));
        $queryGroups[$normalized] = ($queryGroups[$normalized] ?? 0) + 1;
    }
    $duplicates = array_filter($queryGroups, fn($c) => $c > 1);
    $duplicateCount = array_sum($duplicates) - count($duplicates);

    $totalQueryTime = array_sum(array_column($queries, 'time_ms'));
    $maxQueryTime = count($queries) ? max(array_column($queries, 'time_ms')) : 0;

    $cacheHits = count(array_filter($cache, fn($c) => $c['type'] === 'hit'));
    $cacheMisses = count(array_filter($cache, fn($c) => $c['type'] === 'miss'));
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
                @if($isAjax)
                <span class="text-[10px] font-bold bg-drac-pink/10 text-drac-pink px-2 py-0.5 rounded-full">XHR</span>
                @endif
                @if(!empty($inertia))
                <span class="text-[10px] font-bold bg-drac-purple/10 text-drac-purple px-2 py-0.5 rounded-full">Inertia</span>
                @endif
            </div>
        </div>
        <span class="text-drac-comment text-xs font-medium">{{ $profile['created_at'] }}</span>
    </div>

    {{-- Metrics Bar --}}
    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-5">
        <div class="grid grid-cols-3 lg:grid-cols-6 divide-x divide-drac-border">
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Duration</div>
                <div class="text-lg font-extrabold text-drac-cyan leading-none">{{ number_format($profile['duration_ms'], 0) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Queries</div>
                <div class="text-lg font-extrabold text-drac-purple leading-none">{{ $profile['query_count'] }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Memory</div>
                <div class="text-lg font-extrabold text-drac-orange leading-none">{{ number_format($profile['memory_peak_mb'], 1) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">MB</span></div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Views</div>
                <div class="text-lg font-extrabold text-drac-pink leading-none">{{ count($views) }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Events</div>
                <div class="text-lg font-extrabold text-drac-green leading-none">{{ count($events) }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Cache Ops</div>
                <div class="text-lg font-extrabold {{ count($cache) > 0 ? 'text-drac-yellow' : 'text-drac-comment' }} leading-none">{{ count($cache) }}</div>
            </div>
        </div>
    </div>

    {{-- N+1 Warning --}}
    @if(count($duplicates) > 0)
    <div class="bg-drac-orange/8 border border-drac-orange/25 rounded-xl px-5 py-3 mb-5 flex items-start gap-3">
        <svg class="w-5 h-5 text-drac-orange shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        <div>
            <div class="text-drac-orange text-sm font-semibold">Possible N+1 Detected</div>
            <div class="text-drac-orange/70 text-xs mt-0.5">{{ count($duplicates) }} duplicate {{ count($duplicates) === 1 ? 'query' : 'queries' }} found ({{ $duplicateCount }} extra {{ $duplicateCount === 1 ? 'execution' : 'executions' }}). Consider eager loading.</div>
            <div class="mt-2 space-y-1">
                @foreach($duplicates as $sql => $count)
                <div class="text-drac-orange/70 text-xs font-mono bg-drac-current px-2 py-1 rounded truncate">
                    <span class="text-drac-orange font-semibold">{{ $count }}x</span> {{ Str::limit($sql, 120) }}
                </div>
                @endforeach
            </div>
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
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-4">
                        <div class="grid grid-cols-3 lg:grid-cols-4 divide-x divide-drac-border">
                            <div class="px-4 py-3">
                                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Total Time</div>
                                <div class="text-sm font-extrabold text-drac-purple leading-none">{{ number_format($totalQueryTime, 2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                            </div>
                            <div class="px-4 py-3">
                                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Average</div>
                                <div class="text-sm font-extrabold text-drac-fg leading-none">{{ number_format($totalQueryTime / count($queries), 2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                            </div>
                            <div class="px-4 py-3">
                                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Slowest</div>
                                <div class="text-sm font-extrabold text-drac-orange leading-none">{{ number_format($maxQueryTime, 2) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
                            </div>
                            @if(count($duplicates) > 0)
                            <div class="px-4 py-3">
                                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Duplicates</div>
                                <div class="text-sm font-extrabold text-drac-orange leading-none flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                                    {{ count($duplicates) }}
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
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
                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-2.5">Middleware Stack</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($route['middleware'] as $mw)
                                    <span class="bg-drac-bg text-drac-fg text-[11px] px-2.5 py-1 rounded-md border border-drac-border font-mono font-medium">{{ is_string($mw) ? $mw : get_class($mw) }}</span>
                                    @endforeach
                                </div>
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
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-4">
                        <div class="grid grid-cols-3 divide-x divide-drac-border">
                            <div class="px-4 py-3">
                                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Hits</div>
                                <div class="text-sm font-extrabold text-drac-green leading-none">{{ $cacheHits }}</div>
                            </div>
                            <div class="px-4 py-3">
                                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Misses</div>
                                <div class="text-sm font-extrabold text-drac-red leading-none">{{ $cacheMisses }}</div>
                            </div>
                            <div class="px-4 py-3">
                                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-0.5">Hit Rate</div>
                                @if($cacheHits + $cacheMisses > 0)
                                <div class="text-sm font-extrabold text-drac-fg leading-none">{{ round($cacheHits / ($cacheHits + $cacheMisses) * 100) }}%</div>
                                @else
                                <div class="text-sm font-extrabold text-drac-comment leading-none">—</div>
                                @endif
                            </div>
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
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($http as $h)
                            <div class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-drac-current/30 transition">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="text-drac-cyan text-xs font-bold shrink-0">{{ $h['method'] }}</span>
                                    <span class="text-drac-fg text-sm truncate">{{ $h['url'] }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs shrink-0">
                                    <span class="{{ $h['status'] < 300 ? 'text-drac-green' : 'text-drac-red' }} font-bold">{{ $h['status'] }}</span>
                                    <span class="text-drac-border">|</span>
                                    <span class="text-drac-comment font-medium">{{ number_format($h['duration_ms'], 1) }}ms</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
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
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-4">
                    <div class="grid grid-cols-3 divide-x divide-drac-border">
                        <div class="px-4 py-3.5">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Status</div>
                            <div class="text-lg font-extrabold {{ ($data['response']['status_code'] ?? 0) < 300 ? 'text-drac-green' : 'text-drac-red' }} leading-none">{{ $data['response']['status_code'] ?? '—' }}</div>
                        </div>
                        <div class="px-4 py-3.5">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Size</div>
                            <div class="text-lg font-extrabold text-drac-fg leading-none">{{ number_format(($data['response']['size'] ?? 0) / 1024, 1) }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">KB</span></div>
                        </div>
                        <div class="px-4 py-3.5">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Content Type</div>
                            <div class="text-xs font-semibold text-drac-fg font-mono leading-none mt-1">{{ $data['response']['headers']['content-type'][0] ?? '—' }}</div>
                        </div>
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
            tab: 'queries'
        };
    }
}).mount('#digdeep-show');
</script>
@endsection
