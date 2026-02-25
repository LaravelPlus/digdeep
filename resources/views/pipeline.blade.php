@extends('digdeep::layout')

@section('title', 'Pipeline')

@section('content')
<div id="digdeep-pipeline" v-cloak>

    {{-- Route list view --}}
    <div v-if="!selectedRoute">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-xl font-bold text-drac-fg tracking-tight">Request Pipeline</h1>
                <p class="text-drac-comment text-xs mt-1">All registered routes. Click a route to inspect its lifecycle traceback.</p>
            </div>
            <div class="flex items-center gap-3">
                <input v-model="search" type="text" placeholder="Filter routes..." class="bg-drac-current text-drac-fg text-xs font-mono rounded-lg border border-drac-border px-3 py-1.5 w-56 focus:outline-none focus:border-drac-purple placeholder:text-drac-comment/50">
                <div class="flex items-center gap-1">
                    <button v-for="m in ['ALL','GET','POST','PUT','PATCH','DELETE']" :key="m"
                        @click="methodFilter = m"
                        class="text-[10px] font-bold px-2 py-1 rounded-md transition"
                        :class="methodFilter === m ? 'bg-drac-purple/20 text-drac-purple' : 'text-drac-comment hover:text-drac-fg hover:bg-drac-current'">
                        @{{ m }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-5 gap-3 mb-5">
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Total Routes</div>
                <div class="text-2xl font-extrabold text-drac-fg leading-none">@{{ routes.length }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Profiled</div>
                <div class="text-2xl font-extrabold text-drac-purple leading-none">@{{ routes.filter(r => r.profiles.length > 0).length }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Not profiled</div>
                <div class="text-2xl font-extrabold text-drac-comment leading-none">@{{ routes.filter(r => r.profiles.length === 0).length }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Total Profiles</div>
                <div class="text-2xl font-extrabold text-drac-cyan leading-none">@{{ routes.reduce((s, r) => s + r.profiles.length, 0) }}</div>
            </div>
            <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Providers</div>
                <div class="text-2xl font-extrabold text-drac-orange leading-none">@{{ serviceProviders.length }}</div>
            </div>
        </div>

        {{-- Routes table --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
            <div class="divide-y divide-drac-border/50">
                <template v-for="(route, ri) in filteredRoutes" :key="ri">
                    <div class="px-5 py-3 flex items-center gap-4 transition cursor-pointer"
                        :class="route.profiles.length > 0 ? 'hover:bg-drac-purple/5' : 'hover:bg-drac-current/30 opacity-50'"
                        @click="route.profiles.length > 0 && openRoute(ri)">
                        {{-- Method --}}
                        <span class="inline-flex items-center justify-center w-[52px] shrink-0 py-0.5 rounded-md text-[10px] font-bold tracking-wide"
                            :class="methodClass(route.method)">@{{ route.method }}</span>

                        {{-- URI --}}
                        <span class="text-drac-fg font-mono text-[13px] font-medium flex-1 min-w-0 truncate">@{{ route.uri }}</span>

                        {{-- Route name --}}
                        <span v-if="route.name" class="text-drac-comment text-[10px] font-mono shrink-0 max-w-[180px] truncate">@{{ route.name }}</span>

                        {{-- Action short --}}
                        <span class="text-drac-comment text-[10px] font-mono shrink-0 max-w-[220px] truncate">@{{ shortAction(route.action) }}</span>

                        {{-- AJAX badge --}}
                        <span v-if="route.profiles.some(p => p.is_ajax)" class="text-[9px] font-bold bg-drac-cyan/15 text-drac-cyan px-1.5 py-0.5 rounded shrink-0">XHR</span>

                        {{-- Profile count --}}
                        <span v-if="route.profiles.length > 0" class="flex items-center gap-1.5 shrink-0">
                            <span class="text-drac-purple text-[11px] font-extrabold">@{{ route.profiles.length }}</span>
                            <span class="text-drac-comment text-[10px]">profiles</span>
                        </span>
                        <span v-else class="text-drac-comment/40 text-[10px] shrink-0">no data</span>

                        {{-- Arrow --}}
                        <svg v-if="route.profiles.length > 0" class="w-4 h-4 text-drac-comment shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                    </div>
                </template>
            </div>
            <div v-if="filteredRoutes.length === 0" class="px-5 py-8 text-center text-drac-comment text-sm">No routes match your filter.</div>
        </div>
    </div>

    {{-- Single route deep view --}}
    <div v-else>
        {{-- Back + route header --}}
        <div class="flex items-center gap-3 mb-5">
            <button @click="closeRoute()" class="w-8 h-8 rounded-lg bg-drac-surface border border-drac-border flex items-center justify-center text-drac-comment hover:text-drac-purple hover:border-drac-purple/40 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </button>
            <span class="inline-flex items-center justify-center w-[52px] py-0.5 rounded-md text-[10px] font-bold tracking-wide"
                :class="methodClass(selectedRoute.method)">@{{ selectedRoute.method }}</span>
            <h1 class="text-lg font-bold text-drac-fg tracking-tight font-mono">@{{ selectedRoute.uri }}</h1>
            <span v-if="selectedRoute.name" class="text-drac-comment text-xs font-mono bg-drac-current px-2 py-0.5 rounded">@{{ selectedRoute.name }}</span>
            <span v-if="currentProfile && currentProfile.is_ajax" class="text-[10px] font-bold bg-drac-cyan/15 text-drac-cyan px-2 py-0.5 rounded">AJAX / XHR</span>
        </div>

        {{-- Route info bar --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border p-4 mb-4">
            <div class="flex items-center gap-6 text-[11px]">
                <div>
                    <span class="text-drac-comment">Action</span>
                    <span class="text-drac-cyan font-mono font-semibold ml-1.5">@{{ selectedRoute.action }}</span>
                </div>
                <div>
                    <span class="text-drac-comment">Profiles</span>
                    <span class="text-drac-purple font-bold ml-1.5">@{{ selectedRoute.profiles.length }}</span>
                </div>
            </div>
            <div v-if="selectedRoute.middleware && selectedRoute.middleware.length > 0" class="flex flex-wrap gap-1.5 mt-3">
                <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mr-1 self-center">Middleware</span>
                <span v-for="(mw, mi) in selectedRoute.middleware" :key="mi" class="bg-drac-bg text-drac-fg text-[10px] px-2 py-0.5 rounded-md border border-drac-border font-mono">@{{ mw }}</span>
            </div>
        </div>

        {{-- Service Providers --}}
        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden mb-4">
            <div class="px-5 py-2.5 flex items-center gap-2 cursor-pointer" @click="toggleStep('providers')">
                <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Service Providers</span>
                <span class="text-drac-fg text-[10px] font-bold">@{{ serviceProviders.length }}</span>
                <svg class="w-3.5 h-3.5 text-drac-comment transition-transform ml-auto" :class="openSteps.providers ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </div>
            <div v-if="openSteps.providers" class="border-t border-drac-border divide-y divide-drac-border/30 max-h-[400px] overflow-y-auto dd-fade">
                <div v-for="(sp, si) in serviceProviders" :key="'sp-'+si"
                    class="px-5 py-1.5 flex items-center justify-between hover:bg-drac-current/20 transition">
                    <span class="text-drac-fg text-[11px] font-mono truncate">@{{ sp.class }}</span>
                    <span class="text-drac-comment text-[10px] font-mono shrink-0 ml-3">@{{ sp.short }}</span>
                </div>
            </div>
        </div>

        {{-- Profile selector --}}
        <div v-if="selectedRoute.profiles.length > 1" class="flex items-center gap-2 mb-4">
            <span class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Profile</span>
            <select v-model="selectedProfileIdx" class="bg-drac-current text-drac-fg text-xs font-mono rounded-lg border border-drac-border px-3 py-1.5 focus:outline-none focus:border-drac-purple">
                <option v-for="(p, pi) in selectedRoute.profiles" :key="pi" :value="pi">
                    @{{ p.status_code }} — @{{ p.duration_ms.toFixed(1) }}ms — @{{ p.query_count }}q — @{{ p.is_ajax ? '[XHR] ' : '' }}@{{ p.created_at }}
                </option>
            </select>
        </div>

        {{-- Pipeline traceback --}}
        <div v-if="currentProfile" class="relative">
            {{-- Vertical line --}}
            <div class="absolute left-6 top-0 bottom-0 w-px bg-drac-border"></div>

            {{-- Step 1: Request Received --}}
            <div class="relative pl-14 pb-6">
                <div class="absolute left-[17px] top-2 w-5 h-5 rounded-full bg-drac-cyan flex items-center justify-center z-10">
                    <span class="text-[9px] font-bold text-drac-bg">1</span>
                </div>
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden cursor-pointer" @click="toggleStep('request')">
                    <div class="px-5 py-3 flex items-center gap-3">
                        <span class="text-drac-cyan text-sm font-bold">Request Received</span>
                        <span v-if="getPhase('bootstrap')" class="text-drac-cyan text-[10px] font-mono ml-auto">@{{ getPhase('bootstrap').duration_ms.toFixed(2) }}ms bootstrap</span>
                        <svg class="w-4 h-4 text-drac-comment transition-transform" :class="openSteps.request ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </div>
                </div>
                <div v-if="openSteps.request" class="mt-2 bg-drac-surface rounded-xl border border-drac-border overflow-hidden dd-fade">
                    <div class="divide-y divide-drac-border/50">
                        <div class="px-5 py-3 flex items-center gap-4">
                            <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Method</span>
                            <span class="text-drac-fg text-sm font-bold">@{{ currentProfile.method }}</span>
                        </div>
                        <div class="px-5 py-3 flex items-center gap-4">
                            <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">URL</span>
                            <span class="text-drac-cyan text-sm font-mono">@{{ currentProfile.url }}</span>
                        </div>
                        <div class="px-5 py-3 flex items-center gap-4">
                            <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Status</span>
                            <span class="text-sm font-bold" :class="currentProfile.status_code < 300 ? 'text-drac-green' : currentProfile.status_code < 400 ? 'text-drac-orange' : 'text-drac-red'">@{{ currentProfile.status_code }}</span>
                        </div>
                        <div class="px-5 py-3 flex items-center gap-4">
                            <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">AJAX</span>
                            <span class="text-drac-fg text-sm">@{{ currentProfile.is_ajax ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                    {{-- Request Headers --}}
                    <div v-if="currentProfile.request && currentProfile.request.headers" class="border-t border-drac-border">
                        <div class="px-5 py-2.5 bg-drac-bg/50 flex items-center justify-between cursor-pointer" @click="toggleStep('requestHeaders')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Request Headers</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="openSteps.requestHeaders ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.requestHeaders" class="divide-y divide-drac-border/30 max-h-[300px] overflow-y-auto dd-fade">
                            <div v-for="(val, key) in currentProfile.request.headers" :key="'rh-'+key" class="px-5 py-1.5 flex items-start gap-3">
                                <span class="text-drac-cyan text-[11px] font-mono font-semibold shrink-0 w-[200px]">@{{ key }}</span>
                                <span class="text-drac-fg text-[11px] font-mono break-all">@{{ Array.isArray(val) ? val.join(', ') : val }}</span>
                            </div>
                        </div>
                    </div>
                    {{-- Request Payload --}}
                    <div v-if="currentProfile.request && currentProfile.request.payload && Object.keys(currentProfile.request.payload).length > 0" class="border-t border-drac-border">
                        <div class="px-5 py-2.5 bg-drac-bg/50 flex items-center justify-between cursor-pointer" @click="toggleStep('payload')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Payload</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="openSteps.payload ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.payload" class="px-5 py-3 dd-fade">
                            <pre class="text-drac-yellow text-[11px] font-mono leading-relaxed overflow-x-auto">@{{ JSON.stringify(currentProfile.request.payload, null, 2) }}</pre>
                        </div>
                    </div>
                    {{-- Request Body --}}
                    <div v-if="currentProfile.request && currentProfile.request.body && currentProfile.request.body.length > 0" class="border-t border-drac-border">
                        <div class="px-5 py-2.5 bg-drac-bg/50 flex items-center justify-between cursor-pointer" @click="toggleStep('requestBody')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Request Body</span>
                            <div class="flex items-center gap-2">
                                <span class="text-drac-comment text-[10px] font-mono">@{{ formatBytes(currentProfile.request.body.length) }}</span>
                                <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="openSteps.requestBody ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                            </div>
                        </div>
                        <div v-if="openSteps.requestBody" class="px-5 py-3 max-h-[400px] overflow-auto dd-fade">
                            <pre class="text-drac-cyan text-[11px] font-mono leading-relaxed whitespace-pre-wrap break-all">@{{ formatBody(currentProfile.request.body, currentProfile.request.headers) }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 2: Middleware Pipeline --}}
            <div class="relative pl-14 pb-6">
                <div class="absolute left-[17px] top-2 w-5 h-5 rounded-full bg-drac-yellow flex items-center justify-center z-10">
                    <span class="text-[9px] font-bold text-drac-bg">2</span>
                </div>
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden cursor-pointer" @click="toggleStep('middleware')">
                    <div class="px-5 py-3 flex items-center gap-3">
                        <span class="text-drac-yellow text-sm font-bold">Middleware Pipeline</span>
                        <span class="text-drac-comment text-[10px]">@{{ (currentProfile.route && currentProfile.route.middleware) ? currentProfile.route.middleware.length : 0 }} layers</span>
                        <span v-if="getPhase('routing')" class="text-drac-yellow text-[10px] font-mono ml-auto">@{{ getPhase('routing').duration_ms.toFixed(2) }}ms</span>
                        <svg class="w-4 h-4 text-drac-comment transition-transform" :class="openSteps.middleware ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </div>
                </div>
                <div v-if="openSteps.middleware" class="mt-2 dd-fade">
                    {{-- Middleware with timing --}}
                    <div v-if="currentProfile.middleware_timing && currentProfile.middleware_timing.length > 0" class="space-y-1">
                        <div v-for="(mwt, mi) in currentProfile.middleware_timing" :key="'mwt-'+mi"
                            class="bg-drac-surface rounded-lg border border-drac-border px-4 py-2.5 flex items-center gap-3">
                            <div class="w-5 h-5 rounded-full bg-drac-yellow/10 flex items-center justify-center shrink-0">
                                <span class="text-drac-yellow text-[9px] font-bold">@{{ mi + 1 }}</span>
                            </div>
                            <code class="text-drac-fg text-[11px] font-mono break-all flex-1">@{{ mwt.name }}</code>
                            <span class="text-[10px] font-bold font-mono shrink-0" :class="mwt.duration_ms > 10 ? 'text-drac-orange' : 'text-drac-green'">@{{ mwt.duration_ms.toFixed(2) }}ms</span>
                        </div>
                    </div>
                    <div v-else-if="currentProfile.route && currentProfile.route.middleware && currentProfile.route.middleware.length > 0" class="space-y-1">
                        <div v-for="(mw, mi) in currentProfile.route.middleware" :key="'mw-'+mi"
                            class="bg-drac-surface rounded-lg border border-drac-border px-4 py-2.5 flex items-center gap-3">
                            <div class="w-5 h-5 rounded-full bg-drac-yellow/10 flex items-center justify-center shrink-0">
                                <span class="text-drac-yellow text-[9px] font-bold">@{{ mi + 1 }}</span>
                            </div>
                            <code class="text-drac-fg text-[11px] font-mono break-all">@{{ typeof mw === 'string' ? mw : '(closure)' }}</code>
                        </div>
                    </div>
                    <div v-else class="bg-drac-surface rounded-xl border border-drac-border p-4 text-center text-drac-comment text-xs">No middleware data captured.</div>
                </div>
            </div>

            {{-- Step 3: Route Matching --}}
            <div class="relative pl-14 pb-6">
                <div class="absolute left-[17px] top-2 w-5 h-5 rounded-full bg-drac-orange flex items-center justify-center z-10">
                    <span class="text-[9px] font-bold text-drac-bg">3</span>
                </div>
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden cursor-pointer" @click="toggleStep('route')">
                    <div class="px-5 py-3 flex items-center gap-3">
                        <span class="text-drac-orange text-sm font-bold">Route Matched</span>
                        <svg class="w-4 h-4 text-drac-comment transition-transform ml-auto" :class="openSteps.route ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </div>
                </div>
                <div v-if="openSteps.route" class="mt-2 bg-drac-surface rounded-xl border border-drac-border overflow-hidden dd-fade">
                    <div v-if="currentProfile.route && Object.keys(currentProfile.route).length > 0" class="divide-y divide-drac-border/50">
                        <div class="px-5 py-3 flex items-center gap-4">
                            <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Name</span>
                            <span class="text-drac-fg text-sm font-semibold">@{{ currentProfile.route.name || '(unnamed)' }}</span>
                        </div>
                        <div class="px-5 py-3 flex items-center gap-4">
                            <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Action</span>
                            <span class="text-drac-orange text-xs font-semibold font-mono break-all">@{{ currentProfile.route.action || '—' }}</span>
                        </div>
                        <div v-if="currentProfile.route.parameters && Object.keys(currentProfile.route.parameters).length > 0" class="px-5 py-3">
                            <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-2">Parameters</div>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="(val, key) in currentProfile.route.parameters" :key="key" class="bg-drac-bg rounded-lg px-3 py-1.5 border border-drac-border text-xs">
                                    <span class="text-drac-purple font-mono font-semibold">@{{ key }}</span>
                                    <span class="text-drac-comment mx-1">=</span>
                                    <span class="text-drac-fg font-mono">@{{ typeof val === 'string' ? val : JSON.stringify(val) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-4 text-center text-drac-comment text-xs">No route data captured.</div>
                </div>
            </div>

            {{-- Step 4: Controller --}}
            <div class="relative pl-14 pb-6">
                <div class="absolute left-[17px] top-2 w-5 h-5 rounded-full bg-drac-purple flex items-center justify-center z-10">
                    <span class="text-[9px] font-bold text-drac-bg">4</span>
                </div>
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden cursor-pointer" @click="toggleStep('controller')">
                    <div class="px-5 py-3 flex items-center gap-3">
                        <span class="text-drac-purple text-sm font-bold">Controller Action</span>
                        <span class="text-drac-comment text-[10px]">@{{ currentProfile.query_count }}q</span>
                        <span v-if="currentProfile.models && currentProfile.models.length" class="text-drac-green text-[10px]">@{{ currentProfile.models.length }} models</span>
                        <span v-if="currentProfile.mail && currentProfile.mail.length" class="text-drac-pink text-[10px]">@{{ currentProfile.mail.length }} mail</span>
                        <span v-if="currentProfile.jobs && currentProfile.jobs.length" class="text-drac-yellow text-[10px]">@{{ currentProfile.jobs.length }} jobs</span>
                        <span v-if="currentProfile.http_client && currentProfile.http_client.length" class="text-drac-cyan text-[10px]">@{{ currentProfile.http_client.length }} http</span>
                        <span v-if="getPhase('controller')" class="text-drac-purple text-[10px] font-mono ml-auto">@{{ getPhase('controller').duration_ms.toFixed(2) }}ms</span>
                        <svg class="w-4 h-4 text-drac-comment transition-transform" :class="openSteps.controller ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </div>
                </div>
                <div v-if="openSteps.controller" class="mt-2 space-y-2 dd-fade">
                    {{-- Action --}}
                    <div class="bg-drac-surface rounded-xl border border-drac-border px-5 py-3">
                        <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1">Executing</div>
                        <code class="text-drac-purple text-sm font-mono font-semibold">@{{ currentProfile.route ? currentProfile.route.action : selectedRoute.action }}</code>
                    </div>

                    {{-- Queries --}}
                    <div v-if="currentProfile.queries && currentProfile.queries.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-drac-border flex items-center gap-2 cursor-pointer" @click="toggleStep('queries')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Database Queries</span>
                            <span class="text-drac-purple text-[10px] font-bold">@{{ currentProfile.queries.length }}</span>
                            <span class="text-drac-comment text-[10px] font-mono ml-auto">total @{{ totalQueryTime.toFixed(2) }}ms</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="openSteps.queries ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.queries" class="divide-y divide-drac-border/30 max-h-[500px] overflow-y-auto dd-fade">
                            <div v-for="(q, qi) in currentProfile.queries" :key="'q-'+qi" class="px-5 py-3 hover:bg-drac-current/20 transition">
                                <div class="flex justify-between items-center mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-drac-comment text-[10px] font-bold">#@{{ qi + 1 }}</span>
                                        <span v-if="isDuplicateQuery(q.sql)" class="text-[9px] font-bold text-drac-orange bg-drac-orange/10 px-1.5 py-0.5 rounded">DUP @{{ queryDupCount(q.sql) }}x</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px]">
                                        <span class="font-bold" :class="q.time_ms > 100 ? 'text-drac-red' : q.time_ms > 10 ? 'text-drac-orange' : 'text-drac-green'">@{{ q.time_ms.toFixed(2) }}ms</span>
                                        <span v-if="q.caller" class="text-drac-comment font-mono text-[10px]">@{{ q.caller }}</span>
                                    </div>
                                </div>
                                <code class="text-drac-cyan text-[11px] font-mono leading-relaxed break-all block">@{{ q.sql }}</code>
                                <div v-if="q.bindings && q.bindings.length > 0" class="mt-1.5 text-[10px] text-drac-comment font-mono bg-drac-bg rounded px-2.5 py-1 border border-drac-border">
                                    Bindings: <span class="text-drac-yellow">@{{ JSON.stringify(q.bindings) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Events --}}
                    <div v-if="currentProfile.events && currentProfile.events.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-drac-border flex items-center gap-2 cursor-pointer" @click="toggleStep('events')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Events Dispatched</span>
                            <span class="text-drac-yellow text-[10px] font-bold">@{{ currentProfile.events.length }}</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform ml-auto" :class="openSteps.events ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.events" class="divide-y divide-drac-border/30 max-h-[300px] overflow-y-auto">
                            <div v-for="(e, ei) in currentProfile.events" :key="'ev-'+ei" class="px-5 py-2 flex items-center justify-between gap-3 hover:bg-drac-current/20 transition">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-drac-comment text-[10px] font-bold w-5 shrink-0 text-right">@{{ ei + 1 }}</span>
                                    <span class="text-drac-fg text-[11px] font-mono truncate">@{{ e.event }}</span>
                                </div>
                                <span class="text-drac-comment text-[10px] shrink-0 max-w-[200px] truncate font-mono">@{{ e.payload_summary }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Cache --}}
                    <div v-if="currentProfile.cache && currentProfile.cache.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-drac-border flex items-center gap-2 cursor-pointer" @click="toggleStep('cache')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Cache Operations</span>
                            <span class="text-drac-orange text-[10px] font-bold">@{{ currentProfile.cache.length }}</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform ml-auto" :class="openSteps.cache ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.cache" class="divide-y divide-drac-border/30">
                            <div v-for="(c, ci) in currentProfile.cache" :key="'ca-'+ci" class="px-5 py-2 flex items-center justify-between hover:bg-drac-current/20 transition">
                                <span class="text-drac-fg text-[11px] font-mono truncate">@{{ c.key }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                    :class="c.type === 'hit' ? 'bg-drac-green/10 text-drac-green' : c.type === 'miss' ? 'bg-drac-red/10 text-drac-red' : 'bg-drac-cyan/10 text-drac-cyan'">@{{ c.type.toUpperCase() }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Models --}}
                    <div v-if="currentProfile.models && currentProfile.models.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-drac-border flex items-center gap-2 cursor-pointer" @click="toggleStep('models')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Eloquent Models</span>
                            <span class="text-drac-green text-[10px] font-bold">@{{ currentProfile.models.length }}</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform ml-auto" :class="openSteps.models ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.models" class="divide-y divide-drac-border/30">
                            <div v-for="(m, mi) in currentProfile.models" :key="'mod-'+mi" class="px-5 py-2.5 hover:bg-drac-current/20 transition">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-drac-green text-[11px] font-mono font-semibold">@{{ m.class }}</span>
                                </div>
                                <div class="flex items-center gap-3 text-[10px]">
                                    <span v-if="m.retrieved" class="text-drac-cyan">
                                        <span class="font-bold">@{{ m.retrieved }}</span> retrieved
                                    </span>
                                    <span v-if="m.created" class="text-drac-green">
                                        <span class="font-bold">@{{ m.created }}</span> created
                                    </span>
                                    <span v-if="m.updated" class="text-drac-orange">
                                        <span class="font-bold">@{{ m.updated }}</span> updated
                                    </span>
                                    <span v-if="m.deleted" class="text-drac-red">
                                        <span class="font-bold">@{{ m.deleted }}</span> deleted
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Mail --}}
                    <div v-if="currentProfile.mail && currentProfile.mail.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-drac-border flex items-center gap-2 cursor-pointer" @click="toggleStep('mail')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Mail Sent</span>
                            <span class="text-drac-pink text-[10px] font-bold">@{{ currentProfile.mail.length }}</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform ml-auto" :class="openSteps.mail ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.mail" class="divide-y divide-drac-border/30">
                            <div v-for="(mail, mi) in currentProfile.mail" :key="'mail-'+mi" class="px-5 py-2.5 hover:bg-drac-current/20 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-drac-fg text-[11px] font-semibold">@{{ mail.subject }}</span>
                                    <span class="text-drac-pink text-[10px] font-mono">@{{ mail.to }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- HTTP Client Requests --}}
                    <div v-if="currentProfile.http_client && currentProfile.http_client.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-drac-border flex items-center gap-2 cursor-pointer" @click="toggleStep('httpClient')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">HTTP Client Requests</span>
                            <span class="text-drac-cyan text-[10px] font-bold">@{{ currentProfile.http_client.length }}</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform ml-auto" :class="openSteps.httpClient ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.httpClient" class="divide-y divide-drac-border/30">
                            <div v-for="(req, ri) in currentProfile.http_client" :key="'http-'+ri" class="px-5 py-2.5 hover:bg-drac-current/20 transition">
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="methodClass(req.method)">@{{ req.method }}</span>
                                    <span class="text-drac-fg text-[11px] font-mono truncate flex-1 min-w-0">@{{ req.url }}</span>
                                    <span class="text-[10px] font-bold" :class="req.status < 300 ? 'text-drac-green' : req.status < 400 ? 'text-drac-orange' : 'text-drac-red'">@{{ req.status }}</span>
                                    <span v-if="req.duration_ms" class="text-drac-comment text-[10px] font-mono">@{{ req.duration_ms.toFixed(1) }}ms</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Queued Jobs --}}
                    <div v-if="currentProfile.jobs && currentProfile.jobs.length > 0" class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-drac-border flex items-center gap-2 cursor-pointer" @click="toggleStep('jobs')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Jobs Dispatched</span>
                            <span class="text-drac-yellow text-[10px] font-bold">@{{ currentProfile.jobs.length }}</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform ml-auto" :class="openSteps.jobs ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.jobs" class="divide-y divide-drac-border/30">
                            <div v-for="(j, ji) in currentProfile.jobs" :key="'job-'+ji" class="px-5 py-2.5 hover:bg-drac-current/20 transition">
                                <div class="flex items-center justify-between">
                                    <span class="text-drac-fg text-[11px] font-mono font-semibold">@{{ j.job }}</span>
                                    <span class="text-drac-comment text-[10px] bg-drac-bg px-2 py-0.5 rounded border border-drac-border font-mono">@{{ j.queue }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 5: View Rendering --}}
            <div class="relative pl-14 pb-6">
                <div class="absolute left-[17px] top-2 w-5 h-5 rounded-full bg-drac-green flex items-center justify-center z-10">
                    <span class="text-[9px] font-bold text-drac-bg">5</span>
                </div>
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden cursor-pointer" @click="toggleStep('views')">
                    <div class="px-5 py-3 flex items-center gap-3">
                        <span class="text-drac-green text-sm font-bold">View Rendering</span>
                        <span class="text-drac-comment text-[10px]">@{{ (currentProfile.views || []).length }} views</span>
                        <span v-if="getPhase('view')" class="text-drac-green text-[10px] font-mono ml-auto">@{{ getPhase('view').duration_ms.toFixed(2) }}ms</span>
                        <svg class="w-4 h-4 text-drac-comment transition-transform" :class="openSteps.views ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </div>
                </div>
                <div v-if="openSteps.views" class="mt-2 dd-fade">
                    <div v-if="currentProfile.views && currentProfile.views.length > 0" class="space-y-1">
                        <div v-for="(v, vi) in currentProfile.views" :key="'vw-'+vi"
                            class="bg-drac-surface rounded-lg border border-drac-border px-4 py-3 hover:border-drac-comment/40 transition">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-5 h-5 rounded-full bg-drac-green/10 flex items-center justify-center shrink-0">
                                    <span class="text-drac-green text-[9px] font-bold">@{{ vi + 1 }}</span>
                                </div>
                                <span class="text-drac-fg text-sm font-semibold">@{{ v.name }}</span>
                            </div>
                            <div class="text-drac-comment text-[11px] font-mono ml-7">@{{ v.path }}</div>
                            <div v-if="v.data_keys && v.data_keys.length > 0" class="flex flex-wrap gap-1 mt-2 ml-7">
                                <span class="text-drac-comment text-[10px] mr-0.5 self-center">Data passed:</span>
                                <span v-for="key in v.data_keys" :key="key" class="bg-drac-bg text-drac-green text-[10px] px-1.5 py-0.5 rounded border border-drac-border font-mono font-medium">@{{ key }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="bg-drac-surface rounded-xl border border-drac-border p-4 text-center text-drac-comment text-xs">No views rendered (API / redirect response).</div>

                    {{-- Inertia --}}
                    <div v-if="currentProfile.inertia && currentProfile.inertia.component" class="mt-2 bg-drac-surface rounded-xl border border-drac-purple/30 overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-drac-border flex items-center gap-2 bg-drac-purple/5 cursor-pointer" @click="toggleStep('inertia')">
                            <svg class="w-3.5 h-3.5 text-drac-purple" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>
                            <span class="text-drac-purple text-[10px] uppercase font-bold tracking-wider">Inertia Response</span>
                            <span v-if="currentProfile.is_ajax" class="text-[9px] font-bold bg-drac-cyan/15 text-drac-cyan px-1.5 py-0.5 rounded">XHR</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform ml-auto" :class="openSteps.inertia ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.inertia" class="divide-y divide-drac-border/50 dd-fade">
                            <div class="px-5 py-3 flex items-center gap-4">
                                <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Component</span>
                                <span class="text-drac-purple text-sm font-semibold font-mono">@{{ currentProfile.inertia.component }}</span>
                            </div>
                            <div v-if="currentProfile.inertia.version" class="px-5 py-3 flex items-center gap-4">
                                <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">Version</span>
                                <span class="text-drac-fg text-xs font-mono">@{{ currentProfile.inertia.version }}</span>
                            </div>
                            <div v-if="currentProfile.inertia.url" class="px-5 py-3 flex items-center gap-4">
                                <span class="w-[80px] shrink-0 text-drac-comment text-[10px] uppercase font-bold tracking-wider">URL</span>
                                <span class="text-drac-cyan text-sm font-mono">@{{ currentProfile.inertia.url }}</span>
                            </div>
                            {{-- Props summary --}}
                            <div v-if="currentProfile.inertia.props && Object.keys(currentProfile.inertia.props).length > 0" class="px-5 py-3">
                                <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-2">Props Sent to Vue</div>
                                <div class="space-y-1">
                                    <div v-for="(type, name) in currentProfile.inertia.props" :key="name" class="flex items-center justify-between py-1 px-2 rounded hover:bg-drac-current/20">
                                        <span class="text-drac-purple text-[11px] font-mono font-semibold">@{{ name }}</span>
                                        <span class="text-drac-comment text-[10px] font-mono bg-drac-bg px-2 py-0.5 rounded border border-drac-border">@{{ type }}</span>
                                    </div>
                                </div>
                            </div>
                            {{-- Full prop data (expandable) --}}
                            <div v-if="currentProfile.inertia.props_raw && Object.keys(currentProfile.inertia.props_raw).length > 0" class="border-t border-drac-border">
                                <div class="px-5 py-2.5 bg-drac-bg/50 flex items-center justify-between cursor-pointer" @click="toggleStep('inertiaProps')">
                                    <span class="text-drac-purple text-[10px] uppercase font-bold tracking-wider">Full Prop Data</span>
                                    <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="openSteps.inertiaProps ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                                </div>
                                <div v-if="openSteps.inertiaProps" class="px-5 py-3 max-h-[500px] overflow-auto dd-fade">
                                    <pre class="text-drac-purple text-[11px] font-mono leading-relaxed whitespace-pre-wrap break-all">@{{ JSON.stringify(currentProfile.inertia.props_raw, null, 2) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 6: Response Sent --}}
            <div class="relative pl-14 pb-2">
                <div class="absolute left-[17px] top-2 w-5 h-5 rounded-full bg-drac-pink flex items-center justify-center z-10">
                    <span class="text-[9px] font-bold text-drac-bg">6</span>
                </div>
                <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden cursor-pointer" @click="toggleStep('response')">
                    <div class="px-5 py-3 flex items-center gap-3">
                        <span class="text-drac-pink text-sm font-bold">Response Sent</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                            :class="currentProfile.status_code < 300 ? 'bg-drac-green/10 text-drac-green' : currentProfile.status_code < 400 ? 'bg-drac-orange/10 text-drac-orange' : 'bg-drac-red/10 text-drac-red'">@{{ currentProfile.status_code }}</span>
                        <span v-if="getPhase('response')" class="text-drac-pink text-[10px] font-mono ml-auto">@{{ getPhase('response').duration_ms.toFixed(2) }}ms</span>
                        <svg class="w-4 h-4 text-drac-comment transition-transform" :class="openSteps.response ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </div>
                </div>
                <div v-if="openSteps.response" class="mt-2 bg-drac-surface rounded-xl border border-drac-border overflow-hidden dd-fade">
                    {{-- Summary --}}
                    <div class="grid grid-cols-4 gap-3 p-4">
                        <div class="bg-drac-bg rounded-lg border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Status</div>
                            <div class="text-lg font-extrabold" :class="currentProfile.status_code < 300 ? 'text-drac-green' : currentProfile.status_code < 400 ? 'text-drac-orange' : 'text-drac-red'">@{{ currentProfile.status_code }}</div>
                        </div>
                        <div class="bg-drac-bg rounded-lg border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Duration</div>
                            <div class="text-lg font-extrabold text-drac-cyan">@{{ currentProfile.duration_ms.toFixed(1) }}<span class="text-[10px] text-drac-comment ml-0.5">ms</span></div>
                        </div>
                        <div class="bg-drac-bg rounded-lg border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Memory</div>
                            <div class="text-lg font-extrabold text-drac-orange">@{{ currentProfile.memory_peak_mb.toFixed(1) }}<span class="text-[10px] text-drac-comment ml-0.5">MB</span></div>
                        </div>
                        <div class="bg-drac-bg rounded-lg border border-drac-border p-3">
                            <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1">Size</div>
                            <div class="text-lg font-extrabold text-drac-fg">@{{ formatBytes(currentProfile.response?.size || 0) }}</div>
                        </div>
                    </div>

                    {{-- Response Headers --}}
                    <div v-if="currentProfile.response && currentProfile.response.headers" class="border-t border-drac-border">
                        <div class="px-5 py-2.5 bg-drac-bg/50 flex items-center justify-between cursor-pointer" @click="toggleStep('responseHeaders')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Response Headers</span>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="openSteps.responseHeaders ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.responseHeaders" class="divide-y divide-drac-border/30 max-h-[300px] overflow-y-auto dd-fade">
                            <div v-for="(val, key) in currentProfile.response.headers" :key="'resh-'+key" class="px-5 py-1.5 flex items-start gap-3">
                                <span class="text-drac-pink text-[11px] font-mono font-semibold shrink-0 w-[200px]">@{{ key }}</span>
                                <span class="text-drac-fg text-[11px] font-mono break-all">@{{ Array.isArray(val) ? val.join(', ') : val }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Response Body --}}
                    <div v-if="currentProfile.response && currentProfile.response.body && currentProfile.response.body.length > 0" class="border-t border-drac-border">
                        <div class="px-5 py-2.5 bg-drac-bg/50 flex items-center justify-between cursor-pointer" @click="toggleStep('responseBody')">
                            <span class="text-drac-comment text-[10px] uppercase font-bold tracking-wider">Response Body</span>
                            <div class="flex items-center gap-2">
                                <span class="text-drac-comment text-[10px] font-mono">@{{ formatBytes(currentProfile.response.body.length) }}</span>
                                <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="openSteps.responseBody ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                            </div>
                        </div>
                        <div v-if="openSteps.responseBody" class="px-5 py-3 max-h-[500px] overflow-auto dd-fade">
                            <pre class="text-drac-pink text-[11px] font-mono leading-relaxed whitespace-pre-wrap break-all">@{{ formatBody(currentProfile.response.body, currentProfile.response.headers) }}</pre>
                        </div>
                    </div>

                    {{-- Exception --}}
                    <div v-if="currentProfile.exception" class="border-t border-drac-border">
                        <div class="px-5 py-2.5 bg-drac-red/5 flex items-center justify-between cursor-pointer" @click="toggleStep('exception')">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-drac-red" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                                <span class="text-drac-red text-[10px] uppercase font-bold tracking-wider">Exception</span>
                                <span class="text-drac-red text-[11px] font-mono font-semibold">@{{ currentProfile.exception.class }}</span>
                            </div>
                            <svg class="w-3.5 h-3.5 text-drac-comment transition-transform" :class="openSteps.exception ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                        <div v-if="openSteps.exception" class="p-4 dd-fade">
                            <div class="bg-drac-red/8 border border-drac-red/25 rounded-lg p-4">
                                <p class="text-drac-red/80 text-xs font-mono mb-1">@{{ currentProfile.exception.message }}</p>
                                <p class="text-drac-comment text-[10px] font-mono">@{{ currentProfile.exception.file }}:@{{ currentProfile.exception.line }}</p>
                                <div v-if="currentProfile.exception.trace && currentProfile.exception.trace.length > 0" class="mt-3">
                                    <div class="text-drac-comment text-[10px] uppercase font-bold tracking-wider mb-1">Stack Trace</div>
                                    <div class="max-h-[200px] overflow-y-auto space-y-0.5">
                                        <div v-for="(frame, fi) in currentProfile.exception.trace.slice(0, 15)" :key="'tr-'+fi" class="text-[10px] font-mono text-drac-comment">
                                            <span class="text-drac-red/60 mr-1">#@{{ fi }}</span>
                                            <span v-if="frame.file" class="text-drac-fg">@{{ frame.file }}:@{{ frame.line }}</span>
                                            <span v-if="frame.class" class="text-drac-purple ml-1">@{{ frame.class }}@{{ frame.function ? '::' + frame.function : '' }}</span>
                                            <span v-else-if="frame.function" class="text-drac-cyan ml-1">@{{ frame.function }}()</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Total time bar --}}
            <div class="pl-14 mt-2">
                <div class="bg-drac-surface rounded-xl border border-drac-border p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold">Total Lifecycle</span>
                        <span class="text-drac-fg text-sm font-extrabold">@{{ currentProfile.duration_ms.toFixed(1) }}<span class="text-[10px] text-drac-comment ml-0.5">ms</span></span>
                    </div>
                    <div v-if="currentProfile.has_lifecycle" class="relative h-3 bg-drac-current rounded-full overflow-hidden">
                        <div v-for="phase in currentProfile.phases" :key="'bar-'+phase.name"
                            class="absolute h-full"
                            :class="phaseColorClass(phase.name)"
                            :style="phaseBarStyle(phase)">
                        </div>
                    </div>
                    <div v-if="currentProfile.has_lifecycle" class="flex items-center gap-3 mt-2">
                        <span v-for="phase in currentProfile.phases" :key="'lbl-'+phase.name" class="flex items-center gap-1 text-[10px] text-drac-comment">
                            <span class="w-2 h-2 rounded-sm" :class="phaseColorClass(phase.name)"></span>
                            <span class="capitalize">@{{ phase.name }}</span>
                            <span class="font-mono font-bold" :class="phaseTextClass(phase.name)">@{{ phase.duration_ms.toFixed(1) }}ms</span>
                        </span>
                    </div>
                    {{-- Memory per phase --}}
                    <div v-if="currentProfile.has_lifecycle && currentProfile.phases.some(p => p.memory_bytes > 0)" class="mt-3">
                        <div class="text-drac-comment text-[10px] uppercase tracking-wider font-semibold mb-1.5">Memory Per Phase</div>
                        <div class="flex items-end gap-2 h-10">
                            <div v-for="phase in currentProfile.phases" :key="'mem-'+phase.name" class="flex-1 flex flex-col items-center gap-0.5">
                                <div class="w-full rounded-t opacity-70" :class="phaseColorClass(phase.name)"
                                    :style="'height:' + Math.max(8, (phase.memory_bytes / Math.max(...currentProfile.phases.map(p => p.memory_bytes || 1))) * 100) + '%'"></div>
                                <span class="text-[8px] text-drac-comment font-mono">@{{ (phase.memory_bytes / 1048576).toFixed(1) }}MB</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <a :href="'/digdeep/profile/' + currentProfile.id" class="text-drac-comment text-[11px] hover:text-drac-purple transition flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                            View full profile
                        </a>
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
            routes: @json(array_values($registeredRoutes)),
            serviceProviders: @json($serviceProviders),
            search: '',
            methodFilter: 'ALL',
            selectedRouteIdx: null,
            selectedProfileIdx: 0,
            openSteps: {
                request: false,
                requestHeaders: false,
                payload: false,
                requestBody: false,
                middleware: false,
                route: false,
                controller: true,
                queries: true,
                events: false,
                cache: false,
                models: false,
                mail: false,
                httpClient: false,
                jobs: false,
                views: false,
                inertia: false,
                inertiaProps: false,
                response: false,
                responseHeaders: false,
                responseBody: false,
                exception: false,
                providers: false,
            },
        };
    },
    computed: {
        filteredRoutes() {
            return this.routes.filter(r => {
                if (this.methodFilter !== 'ALL' && r.method !== this.methodFilter) return false;
                if (this.search) {
                    const s = this.search.toLowerCase();
                    return r.uri.toLowerCase().includes(s) || (r.name || '').toLowerCase().includes(s) || r.action.toLowerCase().includes(s);
                }
                return true;
            });
        },
        selectedRoute() {
            if (this.selectedRouteIdx === null) return null;
            return this.routes[this.selectedRouteIdx] || null;
        },
        currentProfile() {
            if (!this.selectedRoute || !this.selectedRoute.profiles.length) return null;
            return this.selectedRoute.profiles[this.selectedProfileIdx] || this.selectedRoute.profiles[0];
        },
        totalQueryTime() {
            if (!this.currentProfile || !this.currentProfile.queries) return 0;
            return this.currentProfile.queries.reduce((s, q) => s + q.time_ms, 0);
        },
        queryGroups() {
            if (!this.currentProfile || !this.currentProfile.queries) return {};
            const groups = {};
            this.currentProfile.queries.forEach(q => {
                const norm = q.sql.replace(/\s+/g, ' ').trim();
                groups[norm] = (groups[norm] || 0) + 1;
            });
            return groups;
        },
    },
    methods: {
        openRoute(idx) {
            // idx is into filteredRoutes, need the original index
            const route = this.filteredRoutes[idx];
            this.selectedRouteIdx = this.routes.indexOf(route);
            this.selectedProfileIdx = 0;
            this.openSteps = { request: false, requestHeaders: false, payload: false, requestBody: false, middleware: false, route: false, controller: true, queries: true, events: false, cache: false, models: false, mail: false, httpClient: false, jobs: false, views: false, inertia: false, inertiaProps: false, response: false, responseHeaders: false, responseBody: false, exception: false, providers: false };
        },
        closeRoute() {
            this.selectedRouteIdx = null;
            this.selectedProfileIdx = 0;
        },
        toggleStep(step) {
            this.openSteps[step] = !this.openSteps[step];
        },
        getPhase(name) {
            if (!this.currentProfile || !this.currentProfile.phases) return null;
            return this.currentProfile.phases.find(p => p.name === name) || null;
        },
        isDuplicateQuery(sql) {
            const norm = sql.replace(/\s+/g, ' ').trim();
            return (this.queryGroups[norm] || 0) > 1;
        },
        queryDupCount(sql) {
            const norm = sql.replace(/\s+/g, ' ').trim();
            return this.queryGroups[norm] || 1;
        },
        shortAction(action) {
            if (!action) return '';
            if (action.includes('@')) {
                const parts = action.split('\\');
                return parts[parts.length - 1];
            }
            const parts = action.split('\\');
            return parts[parts.length - 1];
        },
        phaseColorClass(name) {
            return { bootstrap: 'bg-drac-cyan', routing: 'bg-drac-yellow', controller: 'bg-drac-purple', view: 'bg-drac-green', response: 'bg-drac-pink' }[name] || 'bg-drac-comment';
        },
        phaseTextClass(name) {
            return { bootstrap: 'text-drac-cyan', routing: 'text-drac-yellow', controller: 'text-drac-purple', view: 'text-drac-green', response: 'text-drac-pink' }[name] || 'text-drac-fg';
        },
        phaseBarStyle(phase) {
            const total = this.currentProfile ? this.currentProfile.duration_ms : 1;
            if (total <= 0) return { left: '0%', width: '0%' };
            return { left: (phase.start_ms / total * 100) + '%', width: Math.max(phase.duration_ms / total * 100, 0.5) + '%' };
        },
        formatBytes(bytes) {
            if (!bytes || bytes <= 0) return '0 B';
            if (bytes > 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            if (bytes > 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return bytes + ' B';
        },
        formatBody(body, headers) {
            if (!body) return '';
            const ct = headers ? (Array.isArray(headers['content-type']) ? headers['content-type'][0] : (headers['content-type'] || '')) : '';
            if (ct.includes('json') || (body.trim().startsWith('{') || body.trim().startsWith('['))) {
                try { return JSON.stringify(JSON.parse(body), null, 2); } catch(e) {}
            }
            return body;
        },
        methodClass(method) {
            return { GET: 'bg-drac-green/10 text-drac-green', POST: 'bg-drac-cyan/10 text-drac-cyan', PUT: 'bg-drac-orange/10 text-drac-orange', PATCH: 'bg-drac-orange/10 text-drac-orange', DELETE: 'bg-drac-red/10 text-drac-red' }[method] || 'bg-drac-comment/10 text-drac-comment';
        },
    },
}).mount('#digdeep-pipeline');
</script>
@endsection
