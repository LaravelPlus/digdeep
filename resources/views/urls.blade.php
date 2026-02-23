@extends('digdeep::layout')

@section('title', 'Discovered URLs')

@section('content')
<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold text-drac-fg tracking-tight">Discovered URLs</h1>
        <p class="text-drac-comment text-xs mt-1">All routes discovered via auto-profiling and manual triggers.</p>
    </div>

    @if(empty($topRoutes))
        <div class="bg-drac-surface rounded-xl border border-drac-border p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-drac-current flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-drac-comment" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-3.061a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757"/></svg>
            </div>
            <p class="text-drac-fg text-sm font-medium mb-1">No URLs discovered yet</p>
            <p class="text-drac-comment text-xs">Browse your app with auto-profiling enabled, or trigger URLs manually.</p>
        </div>
    @else
        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
            <div class="px-5 py-3 border-b border-drac-border flex items-center gap-2.5">
                <h2 class="text-drac-fg text-sm font-semibold">All Discovered Routes</h2>
                <span class="bg-drac-current text-drac-comment text-[10px] font-bold px-2 py-0.5 rounded-full">{{ count($topRoutes) }}</span>
            </div>
            <div class="divide-y divide-drac-border/60">
                @foreach($topRoutes as $i => $rv)
                <div class="flex items-center px-5 py-2.5 gap-4 hover:bg-drac-current/30 transition">
                    <span class="text-drac-comment text-[11px] font-bold w-6 shrink-0 text-right">{{ $i + 1 }}</span>
                    <span class="inline-flex items-center justify-center w-[48px] shrink-0 py-0.5 rounded-md text-[10px] font-bold tracking-wide
                        {{ $rv['method'] === 'GET' ? 'bg-drac-green/10 text-drac-green' : '' }}
                        {{ $rv['method'] === 'POST' ? 'bg-drac-cyan/10 text-drac-cyan' : '' }}
                        {{ in_array($rv['method'], ['PUT', 'PATCH']) ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                        {{ $rv['method'] === 'DELETE' ? 'bg-drac-red/10 text-drac-red' : '' }}
                    ">{{ $rv['method'] }}</span>
                    <span class="flex-1 min-w-0 text-drac-fg text-sm font-medium truncate font-mono">{{ $rv['url'] }}</span>
                    <div class="shrink-0 flex items-center gap-4">
                        <div class="text-center">
                            <span class="text-drac-purple text-sm font-extrabold">{{ $rv['visit_count'] }}</span>
                            <span class="text-drac-comment text-xs ml-1">visits</span>
                        </div>
                        <span class="text-drac-comment text-[11px]">{{ $rv['last_visited_at'] }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
