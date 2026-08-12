@php
    $price = $plan->computedPrice((float) $settings->monthly_price);
@endphp
<button type="button"
        onclick="pmSelectPlan({{ $plan->id }}, {{ $price }}, {{ json_encode($plan->name) }}, {{ json_encode($plan->isLifetime() ? 'One-time payment' : 'Every ' . $plan->duration_months . ' month(s)') }}, this)"
        data-plan-id="{{ $plan->id }}"
        style="{{ $plan->colorTintStyle() }} border-width: 2px;"
        class="pm-plan-card relative text-left rounded-xl p-4 hover:shadow-lg transition-shadow ring-2 ring-transparent w-full">
    @if ($plan->is_recommended)
        <span class="absolute -top-2.5 left-3 text-[10px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded-full" style="{{ $plan->colorSolidStyle() }}">Recommended</span>
    @elseif ($plan->is_best_value)
        <span class="absolute -top-2.5 left-3 text-[10px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded-full" style="{{ $plan->colorSolidStyle() }}">Best Value</span>
    @endif

    <div class="flex items-center justify-between mb-1 mt-1">
        <span class="font-semibold" style="{{ $plan->colorTextStyle() }}">{{ $plan->name }}</span>
        @if ($plan->badge)
            <span class="text-xs font-medium text-white px-2 py-0.5 rounded-full" style="{{ $plan->colorSolidStyle() }}">{{ $plan->badge }}</span>
        @elseif ($plan->savingsLabel())
            <span class="text-xs font-medium text-white px-2 py-0.5 rounded-full" style="{{ $plan->colorSolidStyle() }}">{{ $plan->savingsLabel() }}</span>
        @endif
    </div>
    <p class="text-2xl font-bold pm-plan-price" data-base-price="{{ $price }}" style="{{ $plan->colorTextStyle() }}">{{ format_money($price) }}</p>
    <p class="text-xs mt-0.5 text-slate-600">
        {{ $plan->isLifetime() ? 'One-time payment' : 'every ' . $plan->duration_months . ' month(s)' }}
    </p>
    @if ($plan->category !== 'individual')
        <p class="text-xs mt-2 pt-2 border-t text-slate-600" style="border-color: {{ $plan->normalizedHex() }}40;">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            {{ $plan->included_seats }} members included
            ({{ format_money($plan->pricePerSeat((float) $settings->monthly_price)) }}/member)
        </p>
        @if ($plan->additional_user_price)
            <p class="text-xs text-slate-500">+{{ format_money($plan->additional_user_price) }}/member beyond that</p>
        @endif
    @endif
</button>
