{{--
    Standardized data table wrapper.

    Props:
        headers    : array of column header strings
        empty      : empty state message (default: "No records found.")
        responsive : wrap in overflow-x-auto for mobile (default: true)

    Slots:
        default     : table body rows (the <tbody> content)
        empty-state : optional custom empty state content
        actions     : optional actions column header (renders a trailing th)

    Usage:
        <x-table :headers="['Name', 'Email', 'Status']" empty="No users yet.">
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><x-badge color="green">{{ $user->status }}</x-badge></td>
                </tr>
            @endforeach
        </x-table>
--}}
@props([
    'headers' => [],
    'empty' => 'No records found.',
    'responsive' => true,
])

@php
    $hasRows = ! empty(trim($slot));
    $hasActions = ! empty(trim($actions ?? ''));
@endphp

<div class="{{ $responsive ? 'overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0' : '' }}">
    <table class="pm-table w-full text-sm border-collapse">
        <thead>
            <tr class="border-b border-slate-200">
                @foreach ($headers as $header)
                    <th scope="col" class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                        {{ $header }}
                    </th>
                @endforeach

                @if ($hasActions)
                    <th scope="col" class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                        Actions
                    </th>
                @endif
            </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
            @if ($hasRows)
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ count($headers) + ($hasActions ? 1 : 0) }}" class="px-4 py-10">
                        @isset($emptyState)
                            {{ $emptyState }}
                        @else
                            <div class="text-center text-slate-400">
                                <i class="fa-solid fa-inbox text-2xl mb-2" aria-hidden="true"></i>
                                <p class="text-sm">{{ $empty }}</p>
                            </div>
                        @endisset
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
