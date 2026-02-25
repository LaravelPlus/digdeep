@extends('digdeep::layout')

@section('title', 'Errors')

@section('content')
<div id="digdeep-errors" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Errors</h1>
            <p class="text-drac-comment text-xs mt-1">Exceptions and errors captured from profiled requests.</p>
        </div>
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Total Errors</div>
                <div class="w-7 h-7 rounded-lg bg-drac-red/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-drac-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold {{ $errorStats['total'] > 0 ? 'text-drac-red' : 'text-drac-green' }} leading-none">{{ $errorStats['total'] }}</div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Unique Types</div>
                <div class="w-7 h-7 rounded-lg bg-drac-orange/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-drac-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-drac-orange leading-none">{{ $errorStats['unique_classes'] }}</div>
        </div>
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Error Rate</div>
                <div class="w-7 h-7 rounded-lg bg-{{ $errorStats['error_rate'] > 10 ? 'drac-red' : ($errorStats['error_rate'] > 0 ? 'drac-orange' : 'drac-green') }}/10 flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-{{ $errorStats['error_rate'] > 10 ? 'drac-red' : ($errorStats['error_rate'] > 0 ? 'drac-orange' : 'drac-green') }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold {{ $errorStats['error_rate'] > 10 ? 'text-drac-red' : ($errorStats['error_rate'] > 0 ? 'text-drac-orange' : 'text-drac-green') }} leading-none">{{ $errorStats['error_rate'] }}%</div>
            <div class="w-full bg-drac-current rounded-full h-1 mt-2">
                <div class="h-1 rounded-full {{ $errorStats['error_rate'] > 10 ? 'bg-drac-red' : ($errorStats['error_rate'] > 0 ? 'bg-drac-orange' : 'bg-drac-green') }}" style="width: {{ min(100, $errorStats['error_rate']) }}%"></div>
            </div>
        </div>
    </div>

    @if(empty($errors))
        <div class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-drac-green/10 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-drac-green" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-drac-fg text-sm font-medium mb-1">No errors captured</p>
            <p class="text-drac-comment text-xs">All profiled requests completed without exceptions.</p>
        </div>
    @else
        {{-- Layout: sidebar + content --}}
        <div class="flex gap-5 items-start">
            {{-- Sidebar --}}
            <nav class="w-[220px] shrink-0 sticky top-[110px]">
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                    <div class="px-4 py-3 border-b border-drac-border">
                        <span class="text-drac-fg text-xs font-semibold">Error Types</span>
                    </div>
                    <div class="divide-y divide-drac-border/60">
                        <button @click="selectedClass = ''" class="dd-sidebar-link" :class="selectedClass === '' ? 'active' : ''">
                            <span class="flex-1">All Errors</span>
                            <span class="text-[10px] font-bold text-drac-red bg-drac-red/10 px-1.5 py-0.5 rounded-full leading-none">{{ count($errors) }}</span>
                        </button>
                        @foreach($errorsByClass as $className => $summary)
                        <button @click="selectedClass = '{{ addslashes($className) }}'" class="dd-sidebar-link" :class="selectedClass === '{{ addslashes($className) }}' ? 'active' : ''">
                            <div class="flex-1 min-w-0">
                                <span class="block text-xs truncate">{{ class_basename($className) }}</span>
                            </div>
                            <span class="text-[10px] font-bold bg-drac-red/10 text-drac-red px-1.5 py-0.5 rounded-full leading-none shrink-0">{{ $summary['count'] }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>
            </nav>

            {{-- Content panel --}}
            <div class="flex-1 min-w-0">
                <div class="space-y-3">
                    <template v-for="error in filteredErrors" :key="error.profile_id">
                    <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden hover:border-drac-comment/40 transition">
                        {{-- Error header --}}
                        <div class="px-5 py-3.5 border-b border-drac-border">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex items-center justify-center w-[44px] shrink-0 py-0.5 rounded text-[10px] font-bold"
                                        :class="{
                                            'bg-drac-green/10 text-drac-green': error.method === 'GET',
                                            'bg-drac-cyan/10 text-drac-cyan': error.method === 'POST',
                                            'bg-drac-orange/10 text-drac-orange': error.method === 'PUT' || error.method === 'PATCH',
                                            'bg-drac-red/10 text-drac-red': error.method === 'DELETE',
                                        }">@{{ error.method }}</span>
                                    <span class="text-drac-fg font-mono text-xs">@{{ error.url }}</span>
                                    <span class="bg-drac-red/15 text-drac-red text-[10px] font-bold px-1.5 py-0.5 rounded">@{{ error.status_code }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-drac-comment text-xs">@{{ error.created_at }}</span>
                                    <a :href="'/digdeep/profile/' + error.profile_id" class="text-drac-purple hover:text-drac-pink transition text-xs font-medium">View Profile</a>
                                </div>
                            </div>
                            <div class="text-drac-red text-sm font-mono font-bold">@{{ error.class }}</div>
                            <div class="text-drac-fg text-sm mt-1">@{{ error.message }}</div>
                            <span v-if="error.code" class="text-drac-comment text-xs mt-1 inline-block">Code: @{{ error.code }}</span>
                        </div>

                        {{-- File location --}}
                        <div class="px-5 py-2.5 border-b border-drac-border bg-drac-current/30">
                            <div class="flex items-center gap-2 text-xs">
                                <svg class="w-3.5 h-3.5 text-drac-comment shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                <span class="text-drac-cyan font-mono truncate">@{{ error.file }}</span>
                                <span class="text-drac-comment">:</span>
                                <span class="text-drac-yellow font-mono font-bold">@{{ error.line }}</span>
                            </div>
                        </div>

                        {{-- Previous exception --}}
                        <div v-if="error.previous" class="px-5 py-2.5 border-b border-drac-border bg-drac-current/20">
                            <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1">Caused by</div>
                            <div class="text-drac-orange text-xs font-mono font-semibold">@{{ error.previous.class }}</div>
                            <div class="text-drac-comment text-xs mt-0.5">@{{ error.previous.message }}</div>
                        </div>

                        {{-- Stack trace (collapsible) --}}
                        <div v-if="error.trace && error.trace.length > 0" class="px-5 py-3">
                            <button @click="toggleTrace(error.profile_id)" class="flex items-center gap-1.5 text-drac-comment text-[10px] uppercase font-bold tracking-wider hover:text-drac-fg transition mb-2">
                                <svg class="w-3 h-3 transition-transform" :class="openTraces.includes(error.profile_id) ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                Stack Trace (@{{ error.trace.length }} frames)
                            </button>
                            <div v-show="openTraces.includes(error.profile_id)" class="space-y-1 max-h-[300px] overflow-y-auto dd-fade">
                                <div v-for="(frame, i) in error.trace" :key="i" class="flex items-start gap-2 text-xs">
                                    <span class="text-drac-comment font-mono w-5 text-right shrink-0">#@{{ i }}</span>
                                    <div class="min-w-0">
                                        <span v-if="frame.file" class="text-drac-fg font-mono truncate block">@{{ frame.file.split('/').pop() }}:@{{ frame.line }}</span>
                                        <span class="text-drac-purple font-mono">
                                            <template v-if="frame.class">@{{ frame.class.split('\\').pop() }}::</template>@{{ frame.function }}()
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </template>
                    <div v-if="filteredErrors.length === 0" class="bg-drac-surface rounded-xl border border-drac-border p-10 text-center">
                        <p class="text-drac-comment text-sm">No errors for this type.</p>
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
        return {
            selectedClass: '',
            openTraces: [],
            errors: @json(array_values($errors ?? [])),
        };
    },
    computed: {
        filteredErrors() {
            if (!this.selectedClass) return this.errors;
            return this.errors.filter(e => e.class === this.selectedClass);
        }
    },
    methods: {
        toggleTrace(id) {
            const i = this.openTraces.indexOf(id);
            if (i === -1) { this.openTraces.push(id); } else { this.openTraces.splice(i, 1); }
        }
    }
}).mount('#digdeep-errors');
</script>
@endsection
