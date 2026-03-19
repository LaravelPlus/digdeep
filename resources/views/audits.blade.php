@extends('digdeep::layout')

@section('title', 'Audits')

@section('content')
@php
    $durationThreshold  = (int) config('digdeep.thresholds.duration_ms', 500);
    $queryCountThreshold = (int) config('digdeep.thresholds.query_count', 20);
@endphp

<div id="digdeep-audits" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Route Audits</h1>
            <p class="text-drac-comment text-xs mt-1">Per-route health scores across all captured profiles.</p>
        </div>
        @if(!empty($routeAudits))
        <div class="flex items-center gap-2">
            <button @click="filter = 'all'"    :class="filter === 'all'    ? 'bg-drac-current text-drac-fg' : 'text-drac-comment hover:text-drac-fg'" class="text-xs font-semibold px-3 py-1.5 rounded-lg transition">All</button>
            <button @click="filter = 'issues'" :class="filter === 'issues' ? 'bg-drac-current text-drac-fg' : 'text-drac-comment hover:text-drac-fg'" class="text-xs font-semibold px-3 py-1.5 rounded-lg transition">Issues only</button>
            <button @click="filter = 'pass'"   :class="filter === 'pass'   ? 'bg-drac-current text-drac-fg' : 'text-drac-comment hover:text-drac-fg'" class="text-xs font-semibold px-3 py-1.5 rounded-lg transition">Passing</button>
        </div>
        @endif
    </div>

    @if(empty($routeAudits))
        <div class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-drac-current flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-drac-comment" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
            </div>
            <p class="text-drac-fg text-sm font-medium mb-1">No audit data yet</p>
            <p class="text-drac-comment text-xs">Profile routes to generate audit data.</p>
        </div>
    @else
        @php
            $totalRequests  = array_sum(array_column($routeAudits, 'count'));
            $avgScore       = count($routeAudits) > 0 ? round(array_sum(array_column($routeAudits, 'score')) / count($routeAudits)) : 100;
            $criticalRoutes = count(array_filter($routeAudits, fn($a) => $a['score'] < 50));
            $warningRoutes  = count(array_filter($routeAudits, fn($a) => $a['score'] >= 50 && $a['score'] < 80));
            $passingRoutes  = count(array_filter($routeAudits, fn($a) => $a['score'] >= 80));
            $avgScoreColor  = $avgScore >= 80 ? 'text-drac-green' : ($avgScore >= 50 ? 'text-drac-orange' : 'text-drac-red');
        @endphp

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Avg Score</div>
                <div class="text-2xl font-extrabold {{ $avgScoreColor }} leading-none">{{ $avgScore }}<span class="text-xs text-drac-comment font-semibold ml-0.5">/100</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Routes Audited</div>
                <div class="text-2xl font-extrabold text-drac-fg leading-none">{{ count($routeAudits) }}</div>
                <div class="text-drac-comment text-[10px] mt-1.5">{{ $totalRequests }} total requests</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Critical</div>
                <div class="text-2xl font-extrabold {{ $criticalRoutes > 0 ? 'text-drac-red' : 'text-drac-comment' }} leading-none">{{ $criticalRoutes }}</div>
                <div class="text-drac-comment text-[10px] mt-1.5">score &lt; 50</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Warning</div>
                <div class="text-2xl font-extrabold {{ $warningRoutes > 0 ? 'text-drac-orange' : 'text-drac-comment' }} leading-none">{{ $warningRoutes }}</div>
                <div class="text-drac-comment text-[10px] mt-1.5">score 50–79</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Passing</div>
                <div class="text-2xl font-extrabold {{ $passingRoutes > 0 ? 'text-drac-green' : 'text-drac-comment' }} leading-none">{{ $passingRoutes }}</div>
                <div class="text-drac-comment text-[10px] mt-1.5">score ≥ 80</div>
            </div>
        </div>

        {{-- Route list --}}
        <div class="space-y-2">
            @foreach($routeAudits as $audit)
            @php
                $score      = $audit['score'];
                $scoreColor = $score >= 80 ? 'text-drac-green' : ($score >= 50 ? 'text-drac-orange' : 'text-drac-red');
                $scoreBg    = $score >= 80 ? 'bg-drac-green/10' : ($score >= 50 ? 'bg-drac-orange/10' : 'bg-drac-red/10');
                $failedChecks = array_values(array_filter($audit['checks'], fn($c) => !$c['pass']));
                $maxDuration = max(array_column(array_values($routeAudits), 'max_duration'));
                $barPct      = $maxDuration > 0 ? ($audit['avg_duration'] / $maxDuration) * 100 : 0;
                $barColor    = $audit['avg_duration'] <= $durationThreshold ? 'bg-drac-green' : ($audit['avg_duration'] <= $durationThreshold * 2 ? 'bg-drac-orange' : 'bg-drac-red');
            @endphp
            <div v-show="filter === 'all' || (filter === 'issues' && {{ count($failedChecks) > 0 ? 'true' : 'false' }}) || (filter === 'pass' && {{ count($failedChecks) === 0 ? 'true' : 'false' }})"
                 class="bg-drac-surface rounded-xl border {{ count($failedChecks) > 0 ? 'border-drac-border' : 'border-drac-border' }} overflow-hidden hover:border-drac-comment/40 transition">
                <div class="px-5 py-3.5">
                    {{-- Route header row --}}
                    <div class="flex items-center gap-3 mb-3">
                        {{-- Score badge --}}
                        <div class="w-10 h-10 rounded-xl {{ $scoreBg }} flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-extrabold {{ $scoreColor }}">{{ $score }}</span>
                        </div>
                        {{-- Method + URL --}}
                        <span class="inline-flex items-center justify-center w-12 shrink-0 py-0.5 rounded-md text-[10px] font-bold tracking-wide
                            {{ $audit['method'] === 'GET'    ? 'bg-drac-green/10 text-drac-green'   : '' }}
                            {{ $audit['method'] === 'POST'   ? 'bg-drac-cyan/10 text-drac-cyan'     : '' }}
                            {{ in_array($audit['method'], ['PUT','PATCH']) ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                            {{ $audit['method'] === 'DELETE' ? 'bg-drac-red/10 text-drac-red'       : '' }}
                        ">{{ $audit['method'] }}</span>
                        <span class="text-drac-fg font-mono text-sm font-medium truncate flex-1 min-w-0">{{ $audit['url'] }}</span>
                        {{-- Counts --}}
                        <span class="text-drac-purple text-xs font-extrabold shrink-0">{{ $audit['count'] }} <span class="text-drac-comment font-medium">req</span></span>
                        @if($audit['error_rate'] > 0)
                            <span class="bg-drac-red/10 text-drac-red text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0">{{ $audit['error_rate'] }}% err</span>
                        @endif
                        {{-- Status codes --}}
                        <div class="flex items-center gap-1 shrink-0">
                            @foreach($audit['statuses'] as $s)
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded
                                {{ $s < 300 ? 'bg-drac-green/10 text-drac-green' : '' }}
                                {{ $s >= 300 && $s < 400 ? 'bg-drac-yellow/10 text-drac-yellow' : '' }}
                                {{ $s >= 400 ? 'bg-drac-red/10 text-drac-red' : '' }}
                            ">{{ $s }}</span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Duration bar --}}
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex-1">
                            <div class="flex-1 bg-drac-current rounded-full h-1.5 overflow-hidden">
                                <div class="{{ $barColor }} h-full rounded-full" style="width: {{ $barPct }}%"></div>
                            </div>
                            <div class="flex items-center gap-4 text-[10px] mt-1.5">
                                <span class="text-drac-comment">Min <span class="text-drac-cyan font-bold">{{ $audit['min_duration'] }}ms</span></span>
                                <span class="text-drac-comment">Avg <span class="{{ $audit['avg_duration'] <= $durationThreshold ? 'text-drac-green' : ($audit['avg_duration'] <= $durationThreshold * 2 ? 'text-drac-orange' : 'text-drac-red') }} font-bold">{{ $audit['avg_duration'] }}ms</span></span>
                                <span class="text-drac-comment">P95 <span class="text-drac-fg font-bold">{{ $audit['p95_duration'] }}ms</span></span>
                                <span class="text-drac-comment">Max <span class="text-drac-fg font-bold">{{ $audit['max_duration'] }}ms</span></span>
                                <span class="text-drac-comment ml-auto">Avg queries <span class="text-drac-yellow font-bold">{{ $audit['avg_queries'] }}</span></span>
                            </div>
                        </div>
                    </div>

                    {{-- Audit checks --}}
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($audit['checks'] as $check)
                        @php
                            $cPass = $check['pass'];
                            $cSev  = $check['severity'];
                            $chipColor = $cPass
                                ? 'bg-drac-green/10 text-drac-green border-drac-green/20'
                                : ($cSev === 'critical' ? 'bg-drac-red/10 text-drac-red border-drac-red/20' : 'bg-drac-orange/10 text-drac-orange border-drac-orange/20');
                        @endphp
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $chipColor }}">
                            @if($cPass)
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            @else
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            @endif
                            {{ $check['label'] }}
                        </span>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

<script>
const { createApp } = Vue;
createApp({
    data() { return { filter: 'all' }; },
}).mount('#digdeep-audits');
</script>
@endsection
