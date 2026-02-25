@extends('digdeep::layout')

@section('title', 'Security')

@section('content')
<div id="digdeep-security" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Security Scan</h1>
            <p class="text-drac-comment text-xs mt-1">Automated security analysis based on profiled requests.</p>
        </div>
    </div>

    @if(empty($securityIssues))
        <div class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-drac-green/10 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-drac-green" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            </div>
            <p class="text-drac-fg text-sm font-medium mb-1">No issues detected</p>
            <p class="text-drac-comment text-xs">Profile more routes to build a security analysis. Issues are detected from profiled request data.</p>
        </div>
    @else
        @php
            $dangers = array_filter($securityIssues, fn($i) => $i['type'] === 'danger');
            $warnings = array_filter($securityIssues, fn($i) => $i['type'] === 'warning');
            $infos = array_filter($securityIssues, fn($i) => $i['type'] === 'info');
            $categories = array_unique(array_column($securityIssues, 'category'));
            sort($categories);
            $totalScore = max(0, 100 - (count($dangers) * 25) - (count($warnings) * 10) - (count($infos) * 2));
            $scoreColor = $totalScore >= 80 ? 'drac-green' : ($totalScore >= 50 ? 'drac-orange' : 'drac-red');
        @endphp

        {{-- Stats cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Security Score</div>
                    <div class="w-7 h-7 rounded-lg bg-{{ $scoreColor }}/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-{{ $scoreColor }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-{{ $scoreColor }} leading-none">{{ $totalScore }}</div>
                <div class="w-full bg-drac-current rounded-full h-1 mt-2">
                    <div class="h-1 rounded-full bg-{{ $scoreColor }}" style="width: {{ $totalScore }}%"></div>
                </div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Critical</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-red/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold {{ count($dangers) > 0 ? 'text-drac-red' : 'text-drac-green' }} leading-none">{{ count($dangers) }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Warnings</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-orange/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold {{ count($warnings) > 0 ? 'text-drac-orange' : 'text-drac-green' }} leading-none">{{ count($warnings) }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Info</div>
                    <div class="w-7 h-7 rounded-lg bg-drac-cyan/10 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-drac-cyan" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-extrabold text-drac-cyan leading-none">{{ count($infos) }}</div>
            </div>
        </div>

        {{-- Issues list with filters --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
            <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2.5">
                <h2 class="text-drac-fg text-sm font-semibold">Issues Found</h2>
                <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">{{ count($securityIssues) }}</span>
                <div class="ml-auto flex items-center gap-1">
                    <button @click="filterType = 'all'" :class="filterType === 'all' ? 'bg-drac-purple/15 text-drac-purple' : 'text-drac-comment hover:text-drac-fg'"
                        class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">All</button>
                    @if(count($dangers) > 0)
                    <button @click="filterType = 'danger'" :class="filterType === 'danger' ? 'bg-drac-red/15 text-drac-red' : 'text-drac-comment hover:text-drac-fg'"
                        class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">Critical</button>
                    @endif
                    @if(count($warnings) > 0)
                    <button @click="filterType = 'warning'" :class="filterType === 'warning' ? 'bg-drac-orange/15 text-drac-orange' : 'text-drac-comment hover:text-drac-fg'"
                        class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">Warnings</button>
                    @endif
                    @if(count($infos) > 0)
                    <button @click="filterType = 'info'" :class="filterType === 'info' ? 'bg-drac-cyan/15 text-drac-cyan' : 'text-drac-comment hover:text-drac-fg'"
                        class="text-[11px] font-bold px-2.5 py-1 rounded-md transition">Info</button>
                    @endif
                </div>
            </div>
            <div class="divide-y divide-drac-border/60">
                <template v-for="issue in filteredIssues" :key="issue.message">
                <div class="px-5 py-3.5 flex items-start gap-3.5 hover:bg-drac-current/30 transition">
                    <div v-if="issue.type === 'danger'" class="w-7 h-7 rounded-lg bg-drac-red/10 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5 text-drac-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                    <div v-else-if="issue.type === 'warning'" class="w-7 h-7 rounded-lg bg-drac-orange/10 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5 text-drac-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                    <div v-else class="w-7 h-7 rounded-lg bg-drac-cyan/10 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5 text-drac-cyan" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"
                                :class="{
                                    'bg-drac-red/10 text-drac-red': issue.type === 'danger',
                                    'bg-drac-orange/10 text-drac-orange': issue.type === 'warning',
                                    'bg-drac-cyan/10 text-drac-cyan': issue.type === 'info',
                                }">@{{ issue.category }}</span>
                        </div>
                        <div class="text-drac-fg text-sm">@{{ issue.message }}</div>
                    </div>
                    <a :href="'/digdeep/profile/' + issue.profile_id" class="text-drac-purple text-xs font-medium hover:text-drac-pink transition shrink-0">
                        View Profile
                    </a>
                </div>
                </template>
                <div v-if="filteredIssues.length === 0" class="px-5 py-10 text-center">
                    <p class="text-drac-comment text-sm">No issues match this filter.</p>
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
            filterType: 'all',
            issues: @json(array_values($securityIssues ?? [])),
        };
    },
    computed: {
        filteredIssues() {
            if (this.filterType === 'all') return this.issues;
            return this.issues.filter(i => i.type === this.filterType);
        }
    }
}).mount('#digdeep-security');
</script>
@endsection
