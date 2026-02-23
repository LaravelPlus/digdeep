@extends('digdeep::layout')

@section('title', 'Errors')

@section('content')
<div id="digdeep-errors" v-cloak>
    <div class="mb-6">
        <h1 class="text-xl font-bold text-drac-fg tracking-tight">Errors</h1>
        <p class="text-drac-comment text-xs mt-1">Exceptions and errors captured from profiled requests.</p>
    </div>

    {{-- Stats bar --}}
    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-6">
        <div class="grid grid-cols-3 divide-x divide-drac-border">
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Total Errors</div>
                <div class="text-lg font-extrabold text-drac-red leading-none">{{ $errorStats['total'] }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Unique Types</div>
                <div class="text-lg font-extrabold text-drac-orange leading-none">{{ $errorStats['unique_classes'] }}</div>
            </div>
            <div class="px-4 py-3.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Error Rate</div>
                <div class="text-lg font-extrabold {{ $errorStats['error_rate'] > 10 ? 'text-drac-red' : ($errorStats['error_rate'] > 0 ? 'text-drac-orange' : 'text-drac-green') }} leading-none">{{ $errorStats['error_rate'] }}%</div>
            </div>
        </div>
    </div>

    @if(empty($errors))
        <div class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-drac-current flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-drac-green" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-drac-fg text-sm font-medium mb-1">No errors captured</p>
            <p class="text-drac-comment text-xs">All profiled requests completed without exceptions.</p>
        </div>
    @else
        {{-- Layout: sidebar + content --}}
        <div class="flex gap-5 items-start">
            {{-- Sidebar --}}
            <nav class="w-[190px] shrink-0 sticky top-[110px]">
                <div class="space-y-0.5">
                    <button @click="section = 'types'" class="dd-sidebar-link" :class="section === 'types' ? 'active' : ''">
                        <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                        <span class="flex-1">Error Types</span>
                        <span class="text-[10px] font-bold opacity-50">{{ count($errorsByClass) }}</span>
                    </button>
                    <button @click="section = 'list'" class="dd-sidebar-link" :class="section === 'list' ? 'active' : ''">
                        <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <span class="flex-1">All Errors</span>
                        @if(count($errors) > 0)
                        <span class="text-[10px] font-bold text-drac-red bg-drac-red/10 px-1.5 py-0.5 rounded-full leading-none">{{ count($errors) }}</span>
                        @else
                        <span class="text-[10px] font-bold opacity-50">0</span>
                        @endif
                    </button>
                </div>
            </nav>

            {{-- Content panel --}}
            <div class="flex-1 min-w-0">

                {{-- ═══ Error Types ═══ --}}
                <div v-show="section === 'types'" class="dd-fade">
                    @if(!empty($errorsByClass))
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="divide-y divide-drac-border/60">
                            @foreach($errorsByClass as $className => $summary)
                            <div class="px-5 py-3.5 flex items-center gap-4 hover:bg-drac-current/30 transition">
                                <div class="flex-1 min-w-0">
                                    <div class="text-drac-red text-sm font-mono font-semibold truncate">{{ $className }}</div>
                                    <div class="text-drac-comment text-xs mt-0.5 truncate">{{ $summary['last_message'] }}</div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="bg-drac-red/15 text-drac-red text-xs font-bold px-2 py-0.5 rounded-full">{{ $summary['count'] }}x</span>
                                    <span class="text-drac-comment text-xs">{{ $summary['last_seen'] }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                        @include('digdeep::_empty', ['message' => 'No error types to display.'])
                    @endif
                </div>

                {{-- ═══ All Errors ═══ --}}
                <div v-show="section === 'list'" class="dd-fade">
                    <div class="space-y-3">
                        @foreach($errors as $error)
                        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                            {{-- Error header --}}
                            <div class="px-5 py-3.5 border-b border-drac-border">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2.5">
                                        <span class="inline-flex items-center justify-center w-[44px] shrink-0 py-0.5 rounded text-[10px] font-bold
                                            {{ $error['method'] === 'GET' ? 'bg-drac-green/10 text-drac-green' : '' }}
                                            {{ $error['method'] === 'POST' ? 'bg-drac-cyan/10 text-drac-cyan' : '' }}
                                            {{ in_array($error['method'], ['PUT', 'PATCH']) ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                                            {{ $error['method'] === 'DELETE' ? 'bg-drac-red/10 text-drac-red' : '' }}
                                        ">{{ $error['method'] }}</span>
                                        <span class="text-drac-fg font-mono text-xs">{{ $error['url'] }}</span>
                                        <span class="bg-drac-red/15 text-drac-red text-[10px] font-bold px-1.5 py-0.5 rounded">{{ $error['status_code'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-drac-comment text-xs">{{ $error['created_at'] }}</span>
                                        <a href="/digdeep/profile/{{ $error['profile_id'] }}" class="text-drac-purple hover:text-drac-pink transition text-xs font-medium">View Profile</a>
                                    </div>
                                </div>
                                <div class="text-drac-red text-sm font-mono font-bold">{{ $error['class'] }}</div>
                                <div class="text-drac-fg text-sm mt-1">{{ $error['message'] }}</div>
                                @if($error['code'])
                                <span class="text-drac-comment text-xs mt-1 inline-block">Code: {{ $error['code'] }}</span>
                                @endif
                            </div>

                            {{-- File location --}}
                            <div class="px-5 py-2.5 border-b border-drac-border bg-drac-current/30">
                                <div class="flex items-center gap-2 text-xs">
                                    <svg class="w-3.5 h-3.5 text-drac-comment shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    <span class="text-drac-cyan font-mono truncate">{{ $error['file'] }}</span>
                                    <span class="text-drac-comment">:</span>
                                    <span class="text-drac-yellow font-mono font-bold">{{ $error['line'] }}</span>
                                </div>
                            </div>

                            {{-- Previous exception --}}
                            @if($error['previous'])
                            <div class="px-5 py-2.5 border-b border-drac-border bg-drac-current/20">
                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1">Caused by</div>
                                <div class="text-drac-orange text-xs font-mono font-semibold">{{ $error['previous']['class'] }}</div>
                                <div class="text-drac-comment text-xs mt-0.5">{{ $error['previous']['message'] }}</div>
                            </div>
                            @endif

                            {{-- Stack trace --}}
                            @if(!empty($error['trace']))
                            <div class="px-5 py-3">
                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-2">Stack Trace</div>
                                <div class="space-y-1 max-h-[300px] overflow-y-auto">
                                    @foreach($error['trace'] as $i => $frame)
                                    <div class="flex items-start gap-2 text-xs">
                                        <span class="text-drac-comment font-mono w-5 text-right shrink-0">#{{ $i }}</span>
                                        <div class="min-w-0">
                                            @if($frame['file'])
                                            <span class="text-drac-fg font-mono truncate block">{{ basename($frame['file']) }}:{{ $frame['line'] }}</span>
                                            @endif
                                            <span class="text-drac-purple font-mono">
                                                @if($frame['class']){{ class_basename($frame['class']) }}@endif{{ $frame['class'] ? '::' : '' }}{{ $frame['function'] }}()
                                            </span>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    @endif
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return { section: 'types' };
    }
}).mount('#digdeep-errors');
</script>
@endsection
