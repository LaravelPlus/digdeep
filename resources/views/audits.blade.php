@extends('digdeep::layout')

@section('title', 'Audits')

@section('content')
<div id="digdeep-audits" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Route Audits</h1>
            <p class="text-drac-comment text-xs mt-1">Performance and reliability analysis per route.</p>
        </div>
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
            $totalRequests = array_sum(array_column($routeAudits, 'count'));
            $avgDuration = count($routeAudits) > 0 ? array_sum(array_column($routeAudits, 'avg_duration')) / count($routeAudits) : 0;
            $maxDuration = max(array_column($routeAudits, 'max_duration'));
            $errorRoutes = count(array_filter($routeAudits, fn($a) => $a['error_rate'] > 0));
        @endphp

        {{-- Stats cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Routes Audited</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-purple/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-purple" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-drac-fg leading-none">{{ count($routeAudits) }}</div>
                <div class="text-drac-comment text-[10px] font-medium mt-2">{{ $totalRequests }} total requests</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Avg Response</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-green/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold {{ $avgDuration < 200 ? 'text-drac-green' : ($avgDuration < 500 ? 'text-drac-orange' : 'text-drac-red') }} leading-none">{{ number_format($avgDuration, 0) }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Slowest</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-red/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-drac-red leading-none">{{ number_format($maxDuration, 0) }}<span class="text-xs text-drac-comment font-semibold ml-0.5">ms</span></div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Routes w/ Errors</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-orange/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold {{ $errorRoutes > 0 ? 'text-drac-orange' : 'text-drac-green' }} leading-none">{{ $errorRoutes }}</div>
            </div>
        </div>

        {{-- Audit cards --}}
        <div class="space-y-2">
            @foreach($routeAudits as $audit)
            @php
                $durationPct = $maxDuration > 0 ? ($audit['avg_duration'] / $maxDuration) * 100 : 0;
                $perfColor = $audit['avg_duration'] < 200 ? 'drac-green' : ($audit['avg_duration'] < 500 ? 'drac-orange' : 'drac-red');
            @endphp
            <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden hover:border-drac-comment/40 transition">
                <div class="px-5 py-3.5">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="inline-flex items-center justify-center w-[48px] shrink-0 py-0.5 rounded-md text-[10px] font-bold tracking-wide
                            {{ $audit['method'] === 'GET' ? 'bg-drac-green/10 text-drac-green' : '' }}
                            {{ $audit['method'] === 'POST' ? 'bg-drac-cyan/10 text-drac-cyan' : '' }}
                            {{ in_array($audit['method'], ['PUT', 'PATCH']) ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                            {{ $audit['method'] === 'DELETE' ? 'bg-drac-red/10 text-drac-red' : '' }}
                        ">{{ $audit['method'] }}</span>
                        <span class="text-drac-fg font-mono text-sm font-medium truncate flex-1 min-w-0">{{ $audit['url'] }}</span>
                        <span class="text-drac-purple text-xs font-extrabold shrink-0">{{ $audit['count'] }} <span class="text-drac-comment font-medium">req</span></span>
                        @if($audit['error_rate'] > 0)
                        <span class="bg-drac-red/10 text-drac-red text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0">{{ $audit['error_rate'] }}% err</span>
                        @endif
                        <div class="flex items-center gap-1 shrink-0">
                            @foreach($audit['statuses'] as $status)
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded
                                {{ $status < 300 ? 'bg-drac-green/10 text-drac-green' : '' }}
                                {{ $status >= 300 && $status < 400 ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                                {{ $status >= 400 ? 'bg-drac-red/10 text-drac-red' : '' }}
                            ">{{ $status }}</span>
                            @endforeach
                        </div>
                    </div>
                    {{-- Duration range visualization --}}
                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1.5">
                                <div class="flex-1 bg-drac-current rounded-full h-1.5 relative overflow-hidden">
                                    {{-- Min-max range --}}
                                    @php
                                        $minPct = $maxDuration > 0 ? ($audit['min_duration'] / $maxDuration) * 100 : 0;
                                        $maxPct = $maxDuration > 0 ? ($audit['max_duration'] / $maxDuration) * 100 : 0;
                                    @endphp
                                    <div class="absolute h-full bg-{{ $perfColor }}/20 rounded-full" style="left: {{ $minPct }}%; width: {{ max(1, $maxPct - $minPct) }}%"></div>
                                    <div class="dd-bar h-full rounded-full bg-{{ $perfColor }}" style="width: {{ $durationPct }}%"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-[10px]">
                                <span class="text-drac-comment">Min <span class="text-drac-cyan font-bold">{{ $audit['min_duration'] }}ms</span></span>
                                <span class="text-drac-comment">Avg <span class="text-{{ $perfColor }} font-bold">{{ $audit['avg_duration'] }}ms</span></span>
                                <span class="text-drac-comment">Max <span class="text-drac-fg font-bold">{{ $audit['max_duration'] }}ms</span></span>
                                <span class="text-drac-comment ml-auto">Avg Queries <span class="text-drac-yellow font-bold">{{ $audit['avg_queries'] }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

<script>
const { createApp } = Vue;
createApp({}).mount('#digdeep-audits');
</script>
@endsection
