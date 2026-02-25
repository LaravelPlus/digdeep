@extends('digdeep::layout')

@section('title', 'Database')

@section('content')
<div id="digdeep-db" v-cloak>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-xl font-bold text-drac-fg tracking-tight">Database Inspector</h1>
        <p class="text-drac-comment text-xs mt-1">Query performance, table access patterns, schema exploration.</p>
    </div>

    {{-- Stats bar --}}
    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-6">
        <div class="grid grid-cols-3 lg:grid-cols-7 divide-x divide-drac-border">
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Queries</div>
                <div class="text-lg font-extrabold text-drac-fg leading-none">{{ $dbStats['total_queries'] }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Reads</div>
                <div class="text-lg font-extrabold text-drac-green leading-none">{{ $dbStats['reads'] }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Writes</div>
                <div class="text-lg font-extrabold text-drac-orange leading-none">{{ $dbStats['writes'] }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Total Time</div>
                <div class="text-lg font-extrabold text-drac-cyan leading-none">{{ $dbStats['total_time'] }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Avg / Query</div>
                <div class="text-lg font-extrabold text-drac-purple leading-none">{{ $dbStats['avg_time'] }}<span class="text-[10px] text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Tables</div>
                <div class="text-lg font-extrabold text-drac-pink leading-none">{{ $dbStats['table_count'] }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">R / W Ratio</div>
                @if($dbStats['total_queries'] > 0)
                @php $readPct = round($dbStats['reads'] / $dbStats['total_queries'] * 100); $writePct = 100 - $readPct; @endphp
                <div class="flex items-center gap-2">
                    <div class="flex h-2 flex-1 rounded-full overflow-hidden bg-drac-current">
                        <div class="bg-drac-green rounded-l-full" style="width: {{ $readPct }}%"></div>
                        <div class="bg-drac-orange rounded-r-full" style="width: {{ $writePct }}%"></div>
                    </div>
                    <span class="text-[10px] text-drac-fg font-bold">{{ $readPct }}%</span>
                </div>
                @else
                <div class="text-lg font-extrabold text-drac-comment leading-none">—</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Layout: sidebar + content --}}
    <div class="flex gap-5 items-start">
        {{-- Sidebar --}}
        <nav class="w-[190px] shrink-0 sticky top-[110px]">
            <div class="space-y-0.5">
                <button @click="section = 'tables'" class="dd-sidebar-link" :class="section === 'tables' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    <span class="flex-1">Table Access</span>
                    <span class="text-[10px] font-bold opacity-50">{{ count($tableAccess) }}</span>
                </button>
                <button @click="section = 'schema'" class="dd-sidebar-link" :class="section === 'schema' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375"/></svg>
                    <span class="flex-1">Schema</span>
                    <span class="text-[10px] font-bold opacity-50">{{ count($schema) }}</span>
                </button>
                <button @click="section = 'slow'" class="dd-sidebar-link" :class="section === 'slow' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="flex-1">Slow Queries</span>
                    @if(count($slowQueries) > 0)
                    <span class="text-[10px] font-bold text-drac-red bg-drac-red/10 px-1.5 py-0.5 rounded-full leading-none">{{ count($slowQueries) }}</span>
                    @else
                    <span class="text-[10px] font-bold opacity-50">0</span>
                    @endif
                </button>
                <button @click="section = 'indexes'" class="dd-sidebar-link" :class="section === 'indexes' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
                    <span class="flex-1">Indexes</span>
                    @php $totalIndexes = array_sum(array_column($schema, 'index_count')); @endphp
                    <span class="text-[10px] font-bold opacity-50">{{ $totalIndexes }}</span>
                </button>
                <button @click="section = 'fkeys'" class="dd-sidebar-link" :class="section === 'fkeys' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.061a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
                    <span class="flex-1">Foreign Keys</span>
                    @php $totalFks = array_sum(array_column($schema, 'fk_count')); @endphp
                    <span class="text-[10px] font-bold opacity-50">{{ $totalFks }}</span>
                </button>
                <button @click="section = 'hints'" class="dd-sidebar-link" :class="section === 'hints' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.44 2.278a3.68 3.68 0 01-2.38 0"/></svg>
                    <span class="flex-1">Hints</span>
                    @if(!empty($hints))
                    <span class="text-[10px] font-bold text-drac-orange bg-drac-orange/10 px-1.5 py-0.5 rounded-full leading-none">{{ count($hints) }}</span>
                    @else
                    <span class="text-[10px] font-bold opacity-50">0</span>
                    @endif
                </button>
            </div>
        </nav>

        {{-- Content panel --}}
        <div class="flex-1 min-w-0">

            {{-- ═══ Table Access ═══ --}}
            <div v-show="section === 'tables'" class="dd-fade">
                @if(empty($tableAccess))
                    @include('digdeep::_empty', ['message' => 'No table access data yet. Profile some routes first.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-drac-border">
                                        <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-3">Table</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Reads</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Writes</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Total</th>
                                        <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3 w-[200px]">Volume</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-3">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-drac-border/60">
                                    @foreach($tableAccess as $table => $access)
                                    @php $totalAccess = $access['reads'] + $access['writes']; $maxAccess = max(array_map(fn($a) => $a['reads'] + $a['writes'], $tableAccess)); $pct = $maxAccess > 0 ? ($totalAccess / $maxAccess) * 100 : 0; @endphp
                                    <tr class="hover:bg-drac-current/30 transition">
                                        <td class="px-5 py-2.5"><span class="text-drac-cyan font-mono text-xs font-semibold">{{ $table }}</span></td>
                                        <td class="text-right px-4 py-2.5"><span class="text-drac-green text-xs font-bold">{{ $access['reads'] }}</span></td>
                                        <td class="text-right px-4 py-2.5"><span class="text-drac-orange text-xs font-bold">{{ $access['writes'] }}</span></td>
                                        <td class="text-right px-4 py-2.5"><span class="text-drac-fg text-xs font-extrabold">{{ $totalAccess }}</span></td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-1.5 rounded-full bg-drac-current overflow-hidden">
                                                    <div class="dd-bar h-full rounded-full bg-gradient-to-r from-drac-purple to-drac-pink" style="width: {{ $pct }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right px-5 py-2.5"><span class="text-drac-comment text-xs font-medium">{{ number_format($access['total_time'], 1) }}ms</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Schema ═══ --}}
            <div v-show="section === 'schema'" class="dd-fade">
                @if(empty($schema))
                    @include('digdeep::_empty', ['message' => 'Could not read database schema.'])
                @else
                    <div class="space-y-2">
                        @foreach($schema as $tIdx => $table)
                        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden group/table" :class="openTables.includes({{ $tIdx }}) ? 'ring-1 ring-drac-purple/20' : ''">
                            <button @click="toggleTable({{ $tIdx }})" class="w-full px-5 py-3 flex items-center gap-4 cursor-pointer hover:bg-drac-current/30 transition">
                                <svg class="w-3.5 h-3.5 text-drac-comment transition-transform duration-200 shrink-0" :class="openTables.includes({{ $tIdx }}) ? 'rotate-90 text-drac-purple' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                <span class="text-drac-purple text-sm font-mono font-bold">{{ $table['name'] }}</span>
                                <div class="flex items-center gap-2 ml-auto">
                                    <span class="bg-drac-bg text-drac-comment text-[10px] font-semibold px-2 py-0.5 rounded-md border border-drac-border">{{ count($table['columns']) }} cols</span>
                                    @if($table['index_count'] > 0)
                                    <span class="bg-drac-bg text-drac-comment text-[10px] font-semibold px-2 py-0.5 rounded-md border border-drac-border">{{ $table['index_count'] }} idx</span>
                                    @endif
                                    @if($table['fk_count'] > 0)
                                    <span class="bg-drac-bg text-drac-comment text-[10px] font-semibold px-2 py-0.5 rounded-md border border-drac-border">{{ $table['fk_count'] }} fk</span>
                                    @endif
                                    <span class="bg-drac-yellow/10 text-drac-yellow text-[10px] font-bold px-2 py-0.5 rounded-md">{{ number_format($table['row_count']) }} rows</span>
                                </div>
                            </button>

                            <div v-show="openTables.includes({{ $tIdx }})">
                                {{-- Columns --}}
                                <div class="border-t border-drac-border overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="bg-drac-bg/50">
                                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider pl-5 pr-3 py-2">Column</th>
                                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-3 py-2">Type</th>
                                                <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-3 py-2">Nullable</th>
                                                <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-3 py-2">Key</th>
                                                <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-3 py-2">Default</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-drac-border/40">
                                            @foreach($table['columns'] as $col)
                                            <tr class="hover:bg-drac-current/20 transition-colors">
                                                <td class="pl-5 pr-3 py-[7px]">
                                                    <div class="flex items-center gap-2">
                                                        @if($col['pk'])<span class="w-1 h-1 rounded-full bg-drac-purple shrink-0"></span>@endif
                                                        <span class="text-drac-cyan font-mono font-semibold">{{ $col['name'] }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-[7px]"><span class="text-drac-orange font-mono">{{ $col['type'] ?: '—' }}</span></td>
                                                <td class="text-center px-3 py-[7px]">
                                                    @if($col['nullable'])
                                                    <span class="inline-block w-4 h-4 leading-4 text-center rounded bg-drac-yellow/10 text-drac-yellow text-[9px] font-bold">Y</span>
                                                    @else
                                                    <span class="inline-block w-4 h-4 leading-4 text-center text-drac-current text-[9px]">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-center px-3 py-[7px]">
                                                    @if($col['pk'])<span class="inline-block bg-drac-purple/15 text-drac-purple text-[9px] font-bold px-1.5 py-0.5 rounded leading-none">PK</span>@endif
                                                </td>
                                                <td class="px-3 py-[7px]">
                                                    @if($col['default'] !== null)<code class="text-drac-green font-mono">{{ $col['default'] }}</code>@else<span class="text-drac-current">—</span>@endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Indexes (inline) --}}
                                @if(!empty($table['indexes']))
                                <div class="border-t border-drac-border bg-drac-bg/30 px-5 py-3">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Indexes</span>
                                        <span class="h-px flex-1 bg-drac-border/50"></span>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach($table['indexes'] as $idx)
                                        <div class="flex items-center gap-2.5 text-xs">
                                            @if($idx['unique'])
                                            <span class="w-[46px] text-center bg-drac-green/10 text-drac-green text-[9px] font-bold py-0.5 rounded shrink-0">UNIQUE</span>
                                            @else
                                            <span class="w-[46px] text-center bg-drac-elevated text-drac-comment text-[9px] font-bold py-0.5 rounded shrink-0">INDEX</span>
                                            @endif
                                            <span class="text-drac-fg/70 font-mono text-[11px] truncate">{{ $idx['name'] }}</span>
                                            <svg class="w-3 h-3 text-drac-border shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                            <span class="text-drac-cyan font-mono text-[11px]">{{ implode(', ', $idx['columns']) }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                {{-- Foreign Keys (inline) --}}
                                @if(!empty($table['foreign_keys']))
                                <div class="border-t border-drac-border bg-drac-bg/30 px-5 py-3">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Foreign Keys</span>
                                        <span class="h-px flex-1 bg-drac-border/50"></span>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach($table['foreign_keys'] as $fk)
                                        <div class="flex items-center gap-2.5 text-xs">
                                            <span class="w-[46px] text-center bg-drac-pink/10 text-drac-pink text-[9px] font-bold py-0.5 rounded shrink-0">FK</span>
                                            <span class="text-drac-cyan font-mono text-[11px] font-medium">{{ $fk['from'] }}</span>
                                            <svg class="w-3 h-3 text-drac-border shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                            <span class="text-drac-purple font-mono text-[11px] font-medium">{{ $fk['table'] }}<span class="text-drac-comment/60">.</span>{{ $fk['to'] }}</span>
                                            <span class="text-drac-comment/50 text-[10px] ml-auto font-mono">{{ $fk['on_delete'] }} / {{ $fk['on_update'] }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ═══ Slow Queries ═══ --}}
            <div v-show="section === 'slow'" class="dd-fade">
                @if(empty($slowQueries))
                    @include('digdeep::_empty', ['message' => 'No slow queries detected (> 5ms). All clear.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($slowQueries as $i => $sq)
                            @php $maxSlow = $slowQueries[0]['time_ms'] ?? 1; $pct = ($sq['time_ms'] / $maxSlow) * 100; @endphp
                            <div class="px-5 py-4 hover:bg-drac-current/20 transition">
                                <div class="flex items-start justify-between gap-4 mb-2.5">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-extrabold shrink-0
                                            {{ $sq['time_ms'] > 100 ? 'bg-drac-red/15 text-drac-red' : ($sq['time_ms'] > 20 ? 'bg-drac-orange/15 text-drac-orange' : 'bg-drac-yellow/15 text-drac-yellow') }}
                                        ">{{ $i + 1 }}</span>
                                        <span class="text-sm font-extrabold {{ $sq['time_ms'] > 100 ? 'text-drac-red' : ($sq['time_ms'] > 20 ? 'text-drac-orange' : 'text-drac-yellow') }}">{{ number_format($sq['time_ms'], 2) }}<span class="text-[10px] font-semibold opacity-60 ml-0.5">ms</span></span>
                                    </div>
                                    <div class="flex items-center gap-3 text-[11px] shrink-0">
                                        <span class="text-drac-comment font-mono">{{ $sq['caller'] }}</span>
                                        <a href="/digdeep/profile/{{ $sq['profile_id'] }}" class="text-drac-purple hover:text-drac-pink transition font-semibold">{{ $sq['url'] }}</a>
                                    </div>
                                </div>
                                <div class="w-full h-1 rounded-full bg-drac-current mb-3">
                                    <div class="dd-bar h-1 rounded-full {{ $sq['time_ms'] > 100 ? 'bg-drac-red' : ($sq['time_ms'] > 20 ? 'bg-drac-orange' : 'bg-drac-purple') }}" style="width: {{ $pct }}%"></div>
                                </div>
                                <code class="text-drac-cyan/80 text-[11px] font-mono leading-relaxed break-all block">{{ $sq['sql'] }}</code>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══ Indexes ═══ --}}
            <div v-show="section === 'indexes'" class="dd-fade">
                @if(empty($schema))
                    @include('digdeep::_empty', ['message' => 'Could not read database schema.'])
                @else
                    @php $hasAnyIndex = false; @endphp
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($schema as $table)
                                @if(!empty($table['indexes']))
                                @php $hasAnyIndex = true; @endphp
                                @foreach($table['indexes'] as $idx)
                                <div class="px-5 py-2.5 flex items-center gap-3 hover:bg-drac-current/20 transition">
                                    <span class="text-drac-purple font-mono text-xs font-bold min-w-[120px] shrink-0 truncate">{{ $table['name'] }}</span>
                                    <span class="text-drac-border">.</span>
                                    @if($idx['unique'])
                                    <span class="w-[50px] text-center bg-drac-green/10 text-drac-green text-[9px] font-bold py-0.5 rounded shrink-0">UNIQUE</span>
                                    @else
                                    <span class="w-[50px] text-center bg-drac-elevated text-drac-comment text-[9px] font-bold py-0.5 rounded shrink-0">INDEX</span>
                                    @endif
                                    <span class="text-drac-fg/60 font-mono text-[11px] truncate flex-1 min-w-0">{{ $idx['name'] }}</span>
                                    <svg class="w-3 h-3 text-drac-border shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                    <span class="text-drac-cyan font-mono text-[11px] shrink-0">{{ implode(', ', $idx['columns']) }}</span>
                                </div>
                                @endforeach
                                @endif
                            @endforeach
                        </div>
                        @if(!$hasAnyIndex)
                            @include('digdeep::_empty', ['message' => 'No indexes found on any table.'])
                        @endif
                    </div>
                @endif
            </div>

            {{-- ═══ Foreign Keys ═══ --}}
            <div v-show="section === 'fkeys'" class="dd-fade">
                @if(empty($schema))
                    @include('digdeep::_empty', ['message' => 'Could not read database schema.'])
                @else
                    @php $hasAnyFk = false; @endphp
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($schema as $table)
                                @if(!empty($table['foreign_keys']))
                                @php $hasAnyFk = true; @endphp
                                @foreach($table['foreign_keys'] as $fk)
                                <div class="px-5 py-2.5 flex items-center gap-3 hover:bg-drac-current/20 transition">
                                    <span class="bg-drac-pink/10 text-drac-pink text-[9px] font-bold px-2 py-0.5 rounded shrink-0">FK</span>
                                    <span class="text-drac-purple font-mono text-xs font-bold shrink-0">{{ $table['name'] }}</span>
                                    <span class="text-drac-border">.</span>
                                    <span class="text-drac-cyan font-mono text-xs font-semibold shrink-0">{{ $fk['from'] }}</span>
                                    <svg class="w-4 h-4 text-drac-comment/40 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                    <span class="text-drac-purple font-mono text-xs font-bold shrink-0">{{ $fk['table'] }}</span>
                                    <span class="text-drac-border">.</span>
                                    <span class="text-drac-cyan font-mono text-xs font-semibold shrink-0">{{ $fk['to'] }}</span>
                                    <div class="ml-auto flex items-center gap-2 shrink-0">
                                        <span class="text-[10px] text-drac-comment/50 font-mono">DEL:<span class="text-drac-orange font-semibold">{{ $fk['on_delete'] }}</span></span>
                                        <span class="text-[10px] text-drac-comment/50 font-mono">UPD:<span class="text-drac-orange font-semibold">{{ $fk['on_update'] }}</span></span>
                                    </div>
                                </div>
                                @endforeach
                                @endif
                            @endforeach
                        </div>
                        @if(!$hasAnyFk)
                            @include('digdeep::_empty', ['message' => 'No foreign keys found on any table.'])
                        @endif
                    </div>
                @endif
            </div>

            {{-- ═══ Query Hints ═══ --}}
            <div v-show="section === 'hints'" class="dd-fade">
                @if(empty($hints))
                    @include('digdeep::_empty', ['message' => 'No optimization hints. Your queries look good!'])
                @else
                    <div class="space-y-2">
                        @foreach($hints as $hint)
                        @php
                            $severityColors = [
                                'warning' => ['border' => 'border-drac-orange/30', 'bg' => 'bg-drac-orange/8', 'text' => 'text-drac-orange', 'badge' => 'bg-drac-orange/15 text-drac-orange'],
                                'info' => ['border' => 'border-drac-yellow/30', 'bg' => 'bg-drac-yellow/8', 'text' => 'text-drac-yellow', 'badge' => 'bg-drac-yellow/15 text-drac-yellow'],
                            ];
                            $colors = $severityColors[$hint['severity']] ?? $severityColors['info'];
                        @endphp
                        <div class="bg-drac-surface rounded-xl border {{ $colors['border'] }} overflow-hidden">
                            <div class="px-5 py-3 {{ $colors['bg'] }} flex items-center gap-3">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $colors['badge'] }} uppercase">{{ $hint['severity'] }}</span>
                                <span class="text-sm font-semibold {{ $colors['text'] }}">{{ $hint['type'] }}</span>
                                @if(!empty($hint['count']))
                                <span class="text-drac-comment text-[10px] font-bold">{{ $hint['count'] }}x</span>
                                @endif
                            </div>
                            <div class="px-5 py-3">
                                <div class="text-drac-fg text-xs mb-2">{{ $hint['message'] }}</div>
                                @if(!empty($hint['sql']))
                                <code class="text-drac-cyan text-[11px] font-mono break-all block bg-drac-bg rounded px-3 py-2 border border-drac-border">{{ Str::limit($hint['sql'], 200) }}</code>
                                @endif
                                @if(!empty($hint['suggestion']))
                                <div class="text-drac-green text-[10px] mt-2 flex items-center gap-1">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.44 2.278a3.68 3.68 0 01-2.38 0"/></svg>
                                    {{ $hint['suggestion'] }}
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return { section: 'tables', openTables: [] };
    },
    methods: {
        toggleTable(idx) {
            const i = this.openTables.indexOf(idx);
            if (i === -1) { this.openTables.push(idx); } else { this.openTables.splice(i, 1); }
        }
    }
}).mount('#digdeep-db');
</script>
@endsection
