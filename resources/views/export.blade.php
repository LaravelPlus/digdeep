@extends('digdeep::layout')

@section('title', 'Export')

@section('content')
<div id="digdeep-export" v-cloak>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-drac-fg tracking-tight">Export</h1>
            <p class="text-drac-comment text-xs mt-1">Download standalone HTML reports powered by Vue 3 and Tailwind CSS v4 via CDN.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Dashboard Summary --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-drac-purple/15 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-drac-purple" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-drac-fg text-sm font-bold">Dashboard Summary</h2>
                    <p class="text-drac-comment text-[11px]">@{{ profileCount }} profiles captured</p>
                </div>
            </div>
            <p class="text-drac-comment text-xs mb-2 leading-relaxed flex-1">
                Overall request stats, top routes by hit count with avg duration and error rates, and a searchable list of recent profiles.
            </p>
            <ul class="text-[11px] text-drac-comment space-y-1 mb-5">
                <li class="flex items-center gap-1.5"><span class="text-drac-purple">◆</span> Total requests, avg duration, error rate</li>
                <li class="flex items-center gap-1.5"><span class="text-drac-purple">◆</span> Top 20 routes by hit count</li>
                <li class="flex items-center gap-1.5"><span class="text-drac-purple">◆</span> Searchable profiles list (up to 100)</li>
            </ul>
            <button @click="download('dashboard')"
                :disabled="profileCount === 0 || loading === 'dashboard'"
                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition"
                :class="profileCount === 0 ? 'bg-drac-current text-drac-comment cursor-not-allowed opacity-60' : 'bg-drac-purple/20 text-drac-purple hover:bg-drac-purple/30 border border-drac-purple/30 cursor-pointer'">
                <svg v-if="loading !== 'dashboard'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                @{{ loading === 'dashboard' ? 'Generating…' : (profileCount === 0 ? 'No data yet' : 'Export HTML') }}
            </button>
        </div>

        {{-- Performance Report --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-drac-green/15 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-drac-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-drac-fg text-sm font-bold">Performance Report</h2>
                    <p class="text-drac-comment text-[11px]">@{{ profileCount }} profiles analysed</p>
                </div>
            </div>
            <p class="text-drac-comment text-xs mb-2 leading-relaxed flex-1">
                Per-route latency percentiles (P50 / P95 / P99), throughput, and error rates — with global aggregate stats at the top.
            </p>
            <ul class="text-[11px] text-drac-comment space-y-1 mb-5">
                <li class="flex items-center gap-1.5"><span class="text-drac-green">◆</span> Global P50 / P95 / P99</li>
                <li class="flex items-center gap-1.5"><span class="text-drac-green">◆</span> Per-route throughput &amp; error rates</li>
                <li class="flex items-center gap-1.5"><span class="text-drac-green">◆</span> Sortable routes table</li>
            </ul>
            <button @click="download('performance')"
                :disabled="profileCount === 0 || loading === 'performance'"
                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition"
                :class="profileCount === 0 ? 'bg-drac-current text-drac-comment cursor-not-allowed opacity-60' : 'bg-drac-green/15 text-drac-green hover:bg-drac-green/25 border border-drac-green/30 cursor-pointer'">
                <svg v-if="loading !== 'performance'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                @{{ loading === 'performance' ? 'Generating…' : (profileCount === 0 ? 'No data yet' : 'Export HTML') }}
            </button>
        </div>

        {{-- Profile Report --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-drac-cyan/15 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-drac-cyan" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-drac-fg text-sm font-bold">Profile Report</h2>
                    <p class="text-drac-comment text-[11px]">Full single-request detail</p>
                </div>
            </div>
            <p class="text-drac-comment text-xs mb-2 leading-relaxed flex-1">
                Complete report for one request: queries, cache ops, fired events, rendered views, model operations, and request / response headers.
            </p>
            <ul class="text-[11px] text-drac-comment space-y-1 mb-4">
                <li class="flex items-center gap-1.5"><span class="text-drac-cyan">◆</span> Queries, cache, events, views, models</li>
                <li class="flex items-center gap-1.5"><span class="text-drac-cyan">◆</span> Request &amp; response headers</li>
                <li class="flex items-center gap-1.5"><span class="text-drac-cyan">◆</span> Exception detail if applicable</li>
            </ul>
            <select v-model="selectedProfileId"
                class="w-full bg-drac-current text-drac-fg text-xs font-mono rounded-lg border border-drac-border px-3 py-2 mb-3 focus:outline-none focus:border-drac-purple">
                <option value="">Select a profile…</option>
                <option v-for="p in profiles" :key="p.id" :value="p.id">
                    @{{ p.method }} @{{ p.url }} — @{{ p.status_code }} — @{{ parseFloat(p.duration_ms).toFixed(1) }}ms — @{{ p.created_at }}
                </option>
            </select>
            <button @click="download('profile', selectedProfileId)"
                :disabled="!selectedProfileId || loading === 'profile'"
                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition"
                :class="!selectedProfileId ? 'bg-drac-current text-drac-comment cursor-not-allowed opacity-60' : 'bg-drac-cyan/15 text-drac-cyan hover:bg-drac-cyan/25 border border-drac-cyan/30 cursor-pointer'">
                <svg v-if="loading !== 'profile'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                @{{ loading === 'profile' ? 'Generating…' : (!selectedProfileId ? 'Select a profile first' : 'Export HTML') }}
            </button>
        </div>
    </div>

    {{-- Info strip --}}
    <div class="mt-6 bg-drac-surface/50 rounded-xl border border-drac-border/50 p-5">
        <h3 class="text-drac-comment text-[10px] font-bold uppercase tracking-wider mb-3">About Exported Reports</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-[11px] text-drac-comment leading-relaxed">
            <div class="flex items-start gap-2">
                <svg class="w-3.5 h-3.5 text-drac-green shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span>Self-contained <code class="bg-drac-current px-1 py-0.5 rounded text-drac-fg">.html</code> files — open in any browser without a server</span>
            </div>
            <div class="flex items-start gap-2">
                <svg class="w-3.5 h-3.5 text-drac-green shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span>Rendered by Vue 3 + Tailwind CSS v4 via CDN — requires an internet connection to render</span>
            </div>
            <div class="flex items-start gap-2">
                <svg class="w-3.5 h-3.5 text-drac-green shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span>Static data snapshot — data is embedded at export time and will not update automatically</span>
            </div>
        </div>
    </div>
</div>

<script>
const { createApp } = Vue;
createApp({
    data() {
        return {
            profiles: @json($profiles),
            selectedProfileId: '',
            loading: null,
        };
    },
    computed: {
        profileCount() {
            return this.profiles.length;
        },
    },
    methods: {
        download(template, id = null) {
            this.loading = template;
            let url = '/digdeep/api/html-export?template=' + encodeURIComponent(template);
            if (id) {
                url += '&id=' + encodeURIComponent(id);
            }
            window.location.href = url;
            setTimeout(() => { this.loading = null; }, 3000);
        },
    },
}).mount('#digdeep-export');
</script>
@endsection
