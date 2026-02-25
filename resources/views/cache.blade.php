@extends('digdeep::layout')

@section('title', 'Cache')

@section('content')
@php
    $totalOps = $cacheStats['total_ops'];
    $hitRate = $cacheStats['hit_rate'];
    $totalHits = $cacheStats['hits'];
    $totalMisses = $cacheStats['misses'];
    $totalWrites = $cacheStats['writes'];
    $mostMissed = $missedKeys;
@endphp
<div id="digdeep-cache" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Cache Effectiveness</h1>
            <p class="text-drac-comment text-xs mt-1">Cache hit rates, most-missed keys, and key pattern analysis.</p>
        </div>
        @if(isset($cacheDriverInfo))
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold bg-drac-cyan/10 text-drac-cyan px-2.5 py-1 rounded-lg">{{ strtoupper($cacheDriverInfo['driver']) }}</span>
            @if($cacheDriverInfo['prefix'])
            <span class="text-[10px] font-medium text-drac-comment bg-drac-current px-2 py-1 rounded-lg font-mono">prefix: {{ $cacheDriverInfo['prefix'] }}</span>
            @endif
        </div>
        @endif
    </div>

    @if($totalOps > 0)
    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Total Ops</div>
            <div class="text-2xl font-extrabold text-drac-fg leading-none">{{ number_format($totalOps) }}</div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Hit Rate</div>
            <div class="text-2xl font-extrabold {{ $hitRate >= 80 ? 'text-drac-green' : ($hitRate >= 50 ? 'text-drac-orange' : 'text-drac-red') }} leading-none">{{ $hitRate }}<span class="text-xs text-drac-comment font-semibold ml-0.5">%</span></div>
            <div class="w-full bg-drac-current rounded-full h-1.5 mt-2">
                <div class="h-1.5 rounded-full {{ $hitRate >= 80 ? 'bg-drac-green' : ($hitRate >= 50 ? 'bg-drac-orange' : 'bg-drac-red') }}" style="width: {{ $hitRate }}%"></div>
            </div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Hits / Misses</div>
            <div class="flex items-center gap-2">
                <span class="text-lg font-extrabold text-drac-green">{{ $totalHits }}</span>
                <span class="text-drac-comment text-xs">/</span>
                <span class="text-lg font-extrabold text-drac-red">{{ $totalMisses }}</span>
            </div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Writes</div>
            <div class="text-2xl font-extrabold text-drac-cyan leading-none">{{ $totalWrites }}</div>
        </div>
    </div>

    {{-- Layout: sidebar + content --}}
    <div class="flex gap-5 items-start">
        <nav class="w-[190px] shrink-0 sticky top-[110px]">
            <div class="space-y-0.5">
                <button @click="section = 'patterns'" class="dd-sidebar-link" :class="section === 'patterns' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/></svg>
                    <span class="flex-1">Key Patterns</span>
                    <span class="text-[10px] font-bold opacity-50">{{ count($keyPatterns) }}</span>
                </button>
                <button @click="section = 'missed'" class="dd-sidebar-link" :class="section === 'missed' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <span class="flex-1">Most Missed</span>
                    @if(count($mostMissed) > 0)
                    <span class="text-[10px] font-bold text-drac-red bg-drac-red/10 px-1.5 py-0.5 rounded-full leading-none">{{ count($mostMissed) }}</span>
                    @else
                    <span class="text-[10px] font-bold opacity-50">0</span>
                    @endif
                </button>
                <button @click="section = 'timeline'" class="dd-sidebar-link" :class="section === 'timeline' ? 'active' : ''">
                    <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="flex-1">Timeline</span>
                    <span class="text-[10px] font-bold opacity-50">{{ count($perProfileHitRate) }}</span>
                </button>
            </div>
        </nav>

        <div class="flex-1 min-w-0">
            {{-- Key Patterns --}}
            <div v-show="section === 'patterns'" class="dd-fade">
                @if(empty($keyPatterns))
                    @include('digdeep::_empty', ['message' => 'No cache key patterns detected.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-drac-border">
                                        <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-3">Pattern</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Hits</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Misses</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Writes</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Total</th>
                                        <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-3">Hit Rate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-drac-border/60">
                                    @foreach($keyPatterns as $pattern => $data)
                                    @php
                                        $patternTotal = $data['hits'] + $data['misses'] + $data['writes'];
                                        $patternHitRate = ($data['hits'] + $data['misses']) > 0 ? round($data['hits'] / ($data['hits'] + $data['misses']) * 100) : 0;
                                    @endphp
                                    <tr class="hover:bg-drac-current/30 transition">
                                        <td class="px-5 py-2.5"><span class="text-drac-cyan font-mono text-xs font-semibold">{{ $pattern }}</span></td>
                                        <td class="text-right px-4 py-2.5"><span class="text-drac-green text-xs font-bold">{{ $data['hits'] }}</span></td>
                                        <td class="text-right px-4 py-2.5"><span class="text-drac-red text-xs font-bold">{{ $data['misses'] }}</span></td>
                                        <td class="text-right px-4 py-2.5"><span class="text-drac-cyan text-xs font-bold">{{ $data['writes'] }}</span></td>
                                        <td class="text-right px-4 py-2.5"><span class="text-drac-fg text-xs font-extrabold">{{ $patternTotal }}</span></td>
                                        <td class="text-right px-5 py-2.5">
                                            <span class="text-xs font-bold {{ $patternHitRate >= 80 ? 'text-drac-green' : ($patternHitRate >= 50 ? 'text-drac-orange' : 'text-drac-red') }}">{{ $patternHitRate }}%</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Most Missed --}}
            <div v-show="section === 'missed'" class="dd-fade">
                @if(empty($mostMissed))
                    @include('digdeep::_empty', ['message' => 'No cache misses detected. Your cache is healthy!'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($mostMissed as $key => $count)
                            @php $maxMissed = reset($mostMissed); $pct = $maxMissed > 0 ? ($count / $maxMissed) * 100 : 0; @endphp
                            <div class="px-5 py-3 hover:bg-drac-current/30 transition relative">
                                <div class="absolute inset-y-0 left-0 bg-drac-red/5" style="width: {{ $pct }}%"></div>
                                <div class="relative flex items-center justify-between gap-3">
                                    <span class="text-drac-fg text-sm font-mono truncate">{{ $key }}</span>
                                    <span class="text-drac-red text-xs font-extrabold shrink-0">{{ $count }} misses</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Per-profile timeline --}}
            <div v-show="section === 'timeline'" class="dd-fade">
                @if(empty($perProfileHitRate))
                    @include('digdeep::_empty', ['message' => 'No per-profile cache data available.'])
                @else
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($perProfileHitRate as $entry)
                            <a href="/digdeep/profile/{{ $entry['id'] }}" class="px-5 py-3 flex items-center gap-4 hover:bg-drac-current/30 transition block">
                                <div class="flex-1 min-w-0">
                                    <div class="text-drac-fg text-xs font-mono truncate">{{ $entry['url'] }}</div>
                                    <div class="text-drac-comment text-[10px] mt-0.5">{{ $entry['created_at'] }}</div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-drac-green text-[10px] font-bold">{{ $entry['total_ops'] }} ops</span>
                                    <div class="w-16 bg-drac-current rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full {{ $entry['hit_rate'] >= 80 ? 'bg-drac-green' : ($entry['hit_rate'] >= 50 ? 'bg-drac-orange' : 'bg-drac-red') }}" style="width: {{ $entry['hit_rate'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-bold {{ $entry['hit_rate'] >= 80 ? 'text-drac-green' : ($entry['hit_rate'] >= 50 ? 'text-drac-orange' : 'text-drac-red') }} w-10 text-right">{{ $entry['hit_rate'] }}%</span>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @else
    <div class="bg-drac-surface rounded-xl border border-drac-border">
        @include('digdeep::_empty', ['message' => 'No cache operations recorded yet. Profile some routes that use cache.'])
    </div>
    @endif
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return { section: 'patterns' };
    },
}).mount('#digdeep-cache');
</script>
@endsection
