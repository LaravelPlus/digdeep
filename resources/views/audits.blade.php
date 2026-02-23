@extends('digdeep::layout')

@section('title', 'Audits')

@section('content')
<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold text-drac-fg tracking-tight">Route Audits</h1>
        <p class="text-drac-comment text-xs mt-1">Performance and reliability analysis per route.</p>
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
        <div class="bg-drac-surface rounded-xl border border-drac-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-drac-border">
                            <th class="text-left text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-3">Route</th>
                            <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Requests</th>
                            <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Min</th>
                            <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Avg</th>
                            <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Max</th>
                            <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Avg Queries</th>
                            <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Error Rate</th>
                            <th class="text-center text-drac-comment text-[10px] uppercase font-bold tracking-wider px-4 py-3">Statuses</th>
                            <th class="text-right text-drac-comment text-[10px] uppercase font-bold tracking-wider px-5 py-3">Last Seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-drac-border/60">
                        @foreach($routeAudits as $audit)
                        <tr class="hover:bg-drac-current/30 transition">
                            <td class="px-5 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex items-center justify-center w-[44px] shrink-0 py-0.5 rounded text-[10px] font-bold
                                        {{ $audit['method'] === 'GET' ? 'bg-drac-green/10 text-drac-green' : '' }}
                                        {{ $audit['method'] === 'POST' ? 'bg-drac-cyan/10 text-drac-cyan' : '' }}
                                        {{ in_array($audit['method'], ['PUT', 'PATCH']) ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                                        {{ $audit['method'] === 'DELETE' ? 'bg-drac-red/10 text-drac-red' : '' }}
                                    ">{{ $audit['method'] }}</span>
                                    <span class="text-drac-fg font-mono text-xs truncate max-w-[300px]">{{ $audit['url'] }}</span>
                                </div>
                            </td>
                            <td class="text-center px-4 py-2.5">
                                <span class="text-drac-purple font-bold text-xs">{{ $audit['count'] }}</span>
                            </td>
                            <td class="text-center px-4 py-2.5">
                                <span class="text-drac-cyan font-bold text-xs">{{ $audit['min_duration'] }}<span class="text-drac-comment font-normal text-[10px]">ms</span></span>
                            </td>
                            <td class="text-center px-4 py-2.5">
                                <span class="font-bold text-xs {{ $audit['avg_duration'] > 500 ? 'text-drac-red' : ($audit['avg_duration'] > 200 ? 'text-drac-orange' : 'text-drac-green') }}">{{ $audit['avg_duration'] }}<span class="text-drac-comment font-normal text-[10px]">ms</span></span>
                            </td>
                            <td class="text-center px-4 py-2.5">
                                <span class="font-bold text-xs {{ $audit['max_duration'] > 500 ? 'text-drac-red' : ($audit['max_duration'] > 200 ? 'text-drac-orange' : 'text-drac-fg') }}">{{ $audit['max_duration'] }}<span class="text-drac-comment font-normal text-[10px]">ms</span></span>
                            </td>
                            <td class="text-center px-4 py-2.5">
                                <span class="text-drac-fg font-bold text-xs">{{ $audit['avg_queries'] }}</span>
                            </td>
                            <td class="text-center px-4 py-2.5">
                                <span class="font-bold text-xs {{ $audit['error_rate'] > 0 ? 'text-drac-red' : 'text-drac-green' }}">{{ $audit['error_rate'] }}%</span>
                            </td>
                            <td class="text-center px-4 py-2.5">
                                <div class="flex items-center justify-center gap-1">
                                    @foreach($audit['statuses'] as $status)
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded
                                        {{ $status < 300 ? 'bg-drac-green/10 text-drac-green' : '' }}
                                        {{ $status >= 300 && $status < 400 ? 'bg-drac-orange/10 text-drac-orange' : '' }}
                                        {{ $status >= 400 ? 'bg-drac-red/10 text-drac-red' : '' }}
                                    ">{{ $status }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-right px-5 py-2.5">
                                <span class="text-drac-comment text-xs">{{ $audit['last_seen'] }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
