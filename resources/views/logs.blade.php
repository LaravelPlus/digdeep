@extends('digdeep::layout')

@section('title', 'Logs')

@section('content')
<div id="dd-logs-app" v-cloak>

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-drac-fg text-xl font-bold">Log Viewer</h1>
      <p class="text-drac-comment text-sm mt-0.5">Laravel application logs from <code class="text-drac-cyan">storage/logs/</code></p>
    </div>
    <div class="flex items-center gap-3">
      {{-- File picker --}}
      @if(count($logFiles) > 1)
        <form method="GET" action="/digdeep/logs" class="flex items-center gap-2">
          <label class="text-drac-comment text-xs font-medium">File:</label>
          <select name="file" onchange="this.form.submit()"
            class="bg-drac-surface border border-drac-border text-drac-fg text-xs px-3 py-1.5 rounded-lg focus:border-drac-purple outline-none">
            @foreach($logFiles as $lf)
              <option value="{{ $lf['name'] }}" {{ $selectedFile === $lf['name'] ? 'selected' : '' }}>
                {{ $lf['name'] }} ({{ round($lf['size'] / 1024, 1) }}KB)
              </option>
            @endforeach
          </select>
        </form>
      @endif
      <span class="text-drac-comment text-xs bg-drac-surface px-3 py-1.5 rounded-lg border border-drac-border">
        @{{ filteredCount }} / {{ count($entries) }} entries
      </span>
    </div>
  </div>

  @if(empty($entries))
    <div class="bg-drac-surface border border-drac-border rounded-xl p-12 text-center">
      <svg class="w-10 h-10 text-drac-comment mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
      <p class="text-drac-comment text-sm">No log entries found{{ $selectedFile ? ' in '.$selectedFile : '' }}.</p>
    </div>
  @else

    {{-- Controls --}}
    <div class="flex items-center gap-3 mb-4">
      {{-- Search --}}
      <div class="relative flex-1 max-w-xl">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-drac-comment pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803 7.5 7.5 0 0016.803 15.803z"/></svg>
        <input v-model="search" type="text" placeholder="Search messages, context, stacktrace…"
          class="w-full bg-drac-surface border border-drac-border text-drac-fg text-sm pl-10 pr-4 py-2 rounded-lg focus:border-drac-purple outline-none placeholder:text-drac-comment">
      </div>

      {{-- Level filter --}}
      <div class="flex items-center gap-1">
        <button @click="toggleLevel('all')" :class="activeLevel === 'all' ? 'bg-drac-current text-drac-fg' : 'text-drac-comment hover:text-drac-fg'"
          class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">All</button>
        @foreach(['emergency','alert','critical','error','warning','notice','info','debug'] as $lvl)
          @php
            $lvlColor = match($lvl) {
                'emergency','alert','critical','error' => 'text-drac-red',
                'warning' => 'text-drac-orange',
                'notice','info' => 'text-drac-cyan',
                default => 'text-drac-comment',
            };
            $hasLevel = collect($entries)->where('level', $lvl)->count() > 0;
          @endphp
          @if($hasLevel)
            <button @click="toggleLevel('{{ $lvl }}')"
              :class="activeLevel === '{{ $lvl }}' ? 'bg-drac-current text-drac-fg' : '{{ $lvlColor }} hover:text-drac-fg'"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all capitalize">{{ $lvl }}</button>
          @endif
        @endforeach
      </div>
    </div>

    {{-- Stats bar --}}
    @php
      $levelCounts = collect($entries)->groupBy('level')->map->count()->toArray();
      $errorTotal = ($levelCounts['emergency'] ?? 0) + ($levelCounts['alert'] ?? 0) + ($levelCounts['critical'] ?? 0) + ($levelCounts['error'] ?? 0);
      $warnTotal  = $levelCounts['warning'] ?? 0;
    @endphp
    <div class="flex items-center gap-4 mb-4 text-xs text-drac-comment">
      @if($errorTotal > 0)
        <span class="text-drac-red font-semibold">{{ $errorTotal }} error{{ $errorTotal > 1 ? 's' : '' }}</span>
      @endif
      @if($warnTotal > 0)
        <span class="text-drac-orange font-semibold">{{ $warnTotal }} warning{{ $warnTotal > 1 ? 's' : '' }}</span>
      @endif
      @foreach($levelCounts as $level => $count)
        @if(!in_array($level, ['emergency','alert','critical','error','warning']))
          <span>{{ $count }} {{ $level }}</span>
        @endif
      @endforeach
      <span class="ml-auto">Showing last 500 entries (newest first)</span>
    </div>

    {{-- Entries --}}
    <div class="bg-drac-surface border border-drac-border rounded-xl overflow-hidden">
      <template v-if="filteredCount === 0">
        <div class="py-12 text-center text-drac-comment text-sm">No entries match your search.</div>
      </template>
      <template v-else>
        <div v-for="(entry, idx) in visibleEntries" :key="idx"
          class="border-b border-drac-border last:border-b-0 hover:bg-drac-elevated transition-colors">

          {{-- Entry row --}}
          <div class="flex items-start gap-3 px-4 py-3 cursor-pointer" @click="toggle(idx)">
            {{-- Level badge --}}
            <span class="mt-0.5 flex-shrink-0 text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded"
              :class="levelClass(entry.level)">@{{ entry.level }}</span>

            {{-- Main content --}}
            <div class="flex-1 min-w-0">
              <div class="text-drac-fg text-sm font-mono leading-snug" v-html="highlight(entry.message)"></div>
              <div class="text-drac-comment text-xs mt-0.5 font-mono" v-if="entry.context">@{{ entry.context.length > 120 ? entry.context.substring(0,120)+'…' : entry.context }}</div>
            </div>

            {{-- Datetime --}}
            <span class="text-drac-comment text-xs font-mono flex-shrink-0 mt-0.5">@{{ entry.datetime }}</span>

            {{-- Expand indicator --}}
            <svg v-if="entry.stacktrace || entry.context" class="w-3.5 h-3.5 text-drac-comment flex-shrink-0 mt-1 transition-transform"
              :class="{ 'rotate-180': expanded[idx] }"
              fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>

          {{-- Expanded detail --}}
          <div v-if="expanded[idx] && (entry.stacktrace || entry.context)" class="border-t border-drac-border bg-drac-bg px-4 py-3 space-y-2">
            <div v-if="entry.context">
              <div class="text-drac-comment text-[10px] font-bold uppercase tracking-wider mb-1">Context</div>
              <pre class="text-drac-fg text-xs font-mono bg-drac-surface border border-drac-border rounded-lg p-3 overflow-x-auto whitespace-pre-wrap break-words">@{{ entry.context }}</pre>
            </div>
            <div v-if="entry.stacktrace">
              <div class="text-drac-comment text-[10px] font-bold uppercase tracking-wider mb-1">Stack Trace</div>
              <pre class="text-drac-comment text-xs font-mono bg-drac-surface border border-drac-border rounded-lg p-3 overflow-x-auto max-h-64 whitespace-pre-wrap break-words">@{{ entry.stacktrace }}</pre>
            </div>
          </div>

        </div>
      </template>
    </div>

    {{-- Load more --}}
    <div v-if="showMore" class="mt-4 text-center">
      <button @click="limit += 200"
        class="text-drac-comment text-xs bg-drac-surface border border-drac-border px-4 py-2 rounded-lg hover:text-drac-fg hover:border-drac-purple transition-all">
        Load 200 more (@{{ filteredEntries.length - limit }} remaining)
      </button>
    </div>

  @endif
</div>

<script>
(function() {
  const { createApp, ref, computed, reactive } = Vue;

  const entries = @json($entries);

  createApp({
    setup() {
      const search      = ref('');
      const activeLevel = ref('all');
      const limit       = ref(200);
      const expanded    = reactive({});

      const levelClass = (level) => {
        const map = {
          emergency: 'bg-red-900/30 text-red-400',
          alert:     'bg-red-900/30 text-red-400',
          critical:  'bg-red-900/30 text-red-400',
          error:     'bg-red-900/30 text-red-400',
          warning:   'bg-orange-900/30 text-orange-400',
          notice:    'bg-cyan-900/20 text-cyan-400',
          info:      'bg-cyan-900/20 text-cyan-400',
          debug:     'bg-gray-800/50 text-gray-500',
        };
        return map[level] || 'bg-gray-800/50 text-gray-400';
      };

      const highlight = (msg) => {
        if (!search.value) return escHtml(msg);
        const q   = search.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const re  = new RegExp('(' + q + ')', 'gi');
        return escHtml(msg).replace(re, '<mark style="background:rgba(241,250,140,.35);color:#f1fa8c;border-radius:2px;padding:0 2px;">$1</mark>');
      };

      const escHtml = (s) => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

      const filteredEntries = computed(() => {
        return entries.filter(e => {
          if (activeLevel.value !== 'all' && e.level !== activeLevel.value) return false;
          if (!search.value) return true;
          const q = search.value.toLowerCase();
          return e.message.toLowerCase().includes(q)
            || (e.context && e.context.toLowerCase().includes(q))
            || (e.stacktrace && e.stacktrace.toLowerCase().includes(q));
        });
      });

      const visibleEntries  = computed(() => filteredEntries.value.slice(0, limit.value));
      const filteredCount   = computed(() => filteredEntries.value.length);
      const showMore        = computed(() => filteredEntries.value.length > limit.value);

      const toggle = (idx) => {
        expanded[idx] = !expanded[idx];
      };

      const toggleLevel = (level) => {
        activeLevel.value = level;
        limit.value = 200;
      };

      return { search, activeLevel, limit, expanded, levelClass, highlight, filteredEntries, visibleEntries, filteredCount, showMore, toggle, toggleLevel };
    }
  }).mount('#dd-logs-app');
})();
</script>
@endsection
