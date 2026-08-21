@extends('layouts.app')

@section('title', 'Subscription')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <div role="tablist" aria-label="Subscription sections" class="flex items-center gap-1 border-b border-slate-200 mb-2">
            <button type="button" role="tab" id="pm-sub-tab-subscription" aria-controls="pm-sub-panel-subscription" aria-selected="true" tabindex="0" data-tab="subscription"
                    onclick="pmSelectSubTab('subscription')" onkeydown="pmSubTabKeydown(event, 'subscription')"
                    class="pm-sub-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-[var(--brand-1)] text-[var(--brand-1)]">
                <i class="fa-solid fa-crown" aria-hidden="true"></i> Your Subscription
            </button>
            <button type="button" role="tab" id="pm-sub-tab-billing" aria-controls="pm-sub-panel-billing" aria-selected="false" tabindex="-1" data-tab="billing"
                    onclick="pmSelectSubTab('billing')" onkeydown="pmSubTabKeydown(event, 'billing')"
                    class="pm-sub-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Invoices &amp; Receipts
            </button>
        </div>

        <div role="tabpanel" id="pm-sub-panel-subscription" aria-labelledby="pm-sub-tab-subscription" tabindex="0" class="pm-sub-panel space-y-6">
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shadow-sm shrink-0">
                    <i class="fa-solid fa-crown text-lg" aria-hidden="true"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Your Subscription</h1>
            </div>

            @if ($user->isSuspended())
                <p class="text-rose-700 mb-2">Your account has been suspended.</p>
                <p class="text-sm text-slate-500">Contact support if you believe this is a mistake.</p>
            @elseif ($user->subscription_status === 'active')
                <p class="text-emerald-700 mb-2">You're subscribed thanks for being a member!</p>
                <p class="text-sm text-slate-500 mb-6">
                    @if ($user->subscriptionPlan)
                        Plan: <strong>{{ $user->subscriptionPlan->name }}</strong>.
                    @endif
                    @if ($user->subscription_expires_at)
                        Renews/expires <strong>{{ $user->subscription_expires_at->format('Y-m-d') }}</strong>.
                    @elseif ($user->subscriptionPlan && $user->subscriptionPlan->isLifetime())
                        Lifetime access never expires.
                    @endif
                </p>

                <form method="POST" action="{{ route('subscription.cancel') }}">
                    @csrf
                    <button type="submit" class="text-sm text-rose-600 underline">Cancel subscription</button>
                </form>
            @else
                @if ($user->onTrial())
                    <p class="text-slate-600 mb-6">
                        You have <span class="font-semibold">{{ $user->trialDaysLeft() }} day(s)</span> left in your free trial.
                        Subscribe any time to keep uninterrupted access.
                    </p>
                @else
                    <p class="text-rose-700 mb-6">
                        Your free trial has ended. Subscribe to keep access to your dashboard and all modules.
                    </p>
                @endif

                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 mb-6">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div><p class="text-xs uppercase tracking-wide font-bold text-emerald-700">Your value this month</p><p class="text-sm text-slate-600">See what My Digital Diary is already helping you manage.</p></div>
                        <i class="fa-solid fa-sparkles text-emerald-600"></i>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
                        <div><p class="text-slate-400 text-xs">Tasks completed</p><p class="font-bold text-slate-800">{{ $valueSummary['tasks_completed'] ?? 0 }}</p></div>
                        <div><p class="text-slate-400 text-xs">Expenses tracked</p><p class="font-bold text-slate-800">{{ format_money($valueSummary['expenses_tracked'] ?? 0) }}</p></div>
                        <div><p class="text-slate-400 text-xs">Saved</p><p class="font-bold text-slate-800">{{ format_money($valueSummary['saved'] ?? 0) }}</p></div>
                        <div><p class="text-slate-400 text-xs">AI plans</p><p class="font-bold text-slate-800">{{ $valueSummary['ai_plans'] ?? 0 }}</p></div>
                        <div><p class="text-slate-400 text-xs">Meetings</p><p class="font-bold text-slate-800">{{ $valueSummary['meetings'] ?? 0 }}</p></div>
                    </div>
                </div>

                @if ($plans->isEmpty())
                    <div class="border border-slate-200 rounded-xl p-5 mb-6 bg-gradient-to-br from-slate-50 to-white">
                        <p class="text-3xl font-bold text-slate-800">{{ format_money($settings->monthly_price) }}<span class="text-base font-normal text-slate-500">/month</span></p>
                        <p class="text-sm text-slate-500 mt-1">Full access to every module, the AI Planner, and reminders.</p>
                    </div>
                @else
                    {{-- Plan selection --}}
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="font-semibold text-slate-800">1. Choose a plan</h2>
                            @if (!empty($settings->supported_currencies))
                                <div class="flex items-center gap-2">
                                    <label for="pm-currency-select" class="text-xs text-slate-500">Show prices in</label>
                                    <select id="pm-currency-select" onchange="pmConvertPlanPrices(this.value)" class="pm-input text-sm py-1.5">
                                        <option value="{{ $settings->default_currency_code }}">{{ $settings->default_currency_code }} ({{ $settings->default_currency_symbol }})</option>
                                        @foreach ($settings->supported_currencies as $currency)
                                            <option value="{{ $currency['code'] }}" data-rate="{{ $currency['rate'] }}" data-symbol="{{ $currency['symbol'] }}">
                                                {{ $currency['code'] }} ({{ $currency['symbol'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mb-3">
                            @if (!empty($settings->supported_currencies))
                                Prices convert for viewing only you'll still be charged the {{ $settings->default_currency_code }} amount shown by default.
                            @endif
                        </p>
                        @php
                            $plansByCategory = $plans->groupBy('category');
                        @endphp

                        {{-- Individual its own full-width row, same as before. --}}
                        @if ($plansByCategory->get('individual', collect())->isNotEmpty())
                            <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mt-5 mb-3">Individual</h3>
                            @php
                                $individualPlans = $plansByCategory->get('individual');
                                $primaryPlans = $individualPlans->filter(fn($p) => in_array((int) $p->duration_months, [1, 12], true));
                                if ($primaryPlans->isEmpty()) $primaryPlans = $individualPlans->take(2);
                                $moreIndividualPlans = $individualPlans->reject(fn($p) => $primaryPlans->contains('id', $p->id));
                            @endphp
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
                                @foreach ($primaryPlans as $plan)
                                    @include('subscription.partials.plan-card', ['plan' => $plan])
                                @endforeach
                            </div>
                            @if($moreIndividualPlans->isNotEmpty())
                                <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">More billing options</summary>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                                        @foreach ($moreIndividualPlans as $plan)
                                            @include('subscription.partials.plan-card', ['plan' => $plan])
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endif

                        {{-- Family & Small Team and Enterprise share one row each is
                             typically just one card/prompt, so giving them a full-width
                             row each (like Individual, which usually has several) left a
                             lot of empty space. --}}
                        @php
                            $familyTeamPlans = $plansByCategory->get('family_team', collect());
                            $hasEnterprise = $plansByCategory->get('organization', collect())->isNotEmpty();
                        @endphp
                        @if ($familyTeamPlans->isNotEmpty() || $hasEnterprise)
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-5 items-start">
                                @if ($familyTeamPlans->isNotEmpty())
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Family &amp; Small Team</h3>
                                        <div class="grid grid-cols-1 gap-3">
                                            @foreach ($familyTeamPlans as $plan)
                                                @include('subscription.partials.plan-card', ['plan' => $plan])
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if ($hasEnterprise)
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Enterprise</h3>
                                        {{-- Sales-assisted, not self-serve no pricing cards
                                             shown here; "Contact Sales" collects a few details
                                             and an admin follows up manually. The underlying
                                             'organization'-category plans still exist and are
                                             used once a deal closes (see Admin -> Subscription
                                             Plans), just never displayed as purchasable cards. --}}
                                        <div class="rounded-xl p-5 border border-violet-200 bg-violet-50 h-full">
                                            <p class="text-sm text-violet-900 font-medium mb-1">Built for larger teams</p>
                                            <p class="text-sm text-violet-700 mb-3">
                                                Custom seats, pricing, and onboarding for bigger organizations talk to us and
                                                we'll put together something that fits.
                                            </p>
                                            <a href="{{ route('enterprise.contact') }}"
                                               class="inline-flex items-center gap-2 text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all"
                                               style="background-color: #6D28D9;">
                                                <i class="fa-solid fa-handshake" aria-hidden="true"></i>
                                                Contact Sales
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div id="pm-selected-plan-summary" class="text-sm text-slate-500 mb-4 hidden">
                        Selected: <strong id="pm-selected-plan-label"></strong>
                        <button type="button" onclick="document.getElementById('pm-checkout-modal').showModal()" class="ms-2 text-[var(--brand-1)] hover:underline">
                            Reopen checkout
                        </button>
                    </div>
                @endif
            @endif
        </div>

        {{-- ================= CHECKOUT MODAL ================= --}}
        <dialog id="pm-checkout-modal" class="rounded-2xl p-0 pm-dialog shadow-2xl backdrop:bg-slate-900/50">
            <div class="p-6 max-h-[85vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-800">Checkout</h2>
                    <button type="button" onclick="document.getElementById('pm-checkout-modal').close()" class="text-slate-400 hover:text-slate-600" aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                {{-- Order summary --}}
                <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 mb-5 text-sm space-y-1.5">
                    <div class="flex justify-between"><span class="text-slate-500">Package</span><strong id="pm-checkout-plan-name" class="text-slate-800"></strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Billing Period</span><strong id="pm-checkout-plan-period" class="text-slate-800"></strong></div>
                    <div class="flex justify-between"><span class="text-slate-500">Payment Method</span><strong id="pm-checkout-method-label" class="text-slate-800">—</strong></div>
                    <div class="flex justify-between pt-1.5 border-t border-slate-200 mt-1.5">
                        <span class="text-slate-700 font-medium">Total Payable</span>
                        <strong id="pm-checkout-plan-amount" class="text-[var(--brand-1)] text-base"></strong>
                    </div>
                </div>

                @if ($plans->isEmpty() || $gateways->isEmpty())
                    {{-- No payment gateways configured demo fallback, unchanged from before. --}}
                    <form method="POST" action="{{ route('subscription.subscribe') }}">
                        @csrf
                        <input type="hidden" name="plan_id" class="pm-plan-id-input" value="{{ $plans->first()?->id }}">
                        <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all w-full justify-center">
                            <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                            <span>Subscribe</span>
                        </button>
                    </form>
                    <p class="text-xs text-slate-400 mt-4">
                        Demo checkout this flips your account to "active" immediately with no real charge.
                        An admin can configure real payment gateways at Admin &rarr; Payment Gateways.
                    </p>
                @else
                    <fieldset class="mb-4">
                        <legend class="text-sm font-medium text-slate-700 mb-2">Choose a payment method</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($gateways as $gateway)
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="checkout_method" value="{{ $gateway->id }}" class="sr-only peer"
                                           onchange="pmSelectPaymentMethod({{ $gateway->id }}, {{ json_encode($gateway->display_name ?: $gateway->name) }})"
                                           @checked($loop->first)>
                                    <span class="flex items-center gap-2 border-2 border-slate-200 peer-checked:border-[var(--brand-1)] peer-checked:bg-[var(--brand-1-tint-10)] rounded-lg p-3 text-sm transition-colors">
                                        <i class="fa-solid {{ $gateway->isCard() ? 'fa-credit-card text-indigo-500' : ($gateway->type === 'bank' ? 'fa-building-columns text-emerald-500' : 'fa-mobile-screen-button text-amber-500') }}" aria-hidden="true"></i>
                                        <span>
                                            <span class="block">{{ $gateway->display_name ?: $gateway->name }}</span>
                                            <span class="block text-xs text-slate-400">
                                                {{ match(true) {
                                                    $gateway->isCard() => 'Card payment',
                                                    $gateway->collectsAutomatically() => 'Mobile money instant',
                                                    $gateway->type === 'bank' => 'Bank transfer',
                                                    default => 'Mobile money (manual)',
                                                } }}
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="space-y-4">
                        @foreach ($gateways as $gateway)
                            <div class="pm-gateway-section border border-slate-200 rounded-xl p-4" data-gateway-id="{{ $gateway->id }}" @if (! $loop->first) hidden @endif>
                                @if ($gateway->isCard())
                                    <p class="text-sm text-slate-600 mb-3">Pay securely by card via Stripe.</p>
                                    <form method="POST" action="{{ route('subscription.pay.card') }}">
                                        @csrf
                                        <input type="hidden" name="plan_id" class="pm-plan-id-input" value="">
                                        <button type="submit" class="pm-pay-btn inline-flex items-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed w-full justify-center"
                                                {{ $plans->isNotEmpty() ? 'disabled' : '' }}>
                                            <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                                            <span>Pay with Card</span>
                                        </button>
                                    </form>
                                @elseif ($gateway->collectsAutomatically())
                                    <p class="text-sm text-slate-600 mb-3">
                                        {{ $gateway->description ?: 'Pay instantly by mobile money a payment prompt will be sent to your phone.' }}
                                    </p>
                                    <form method="POST" action="{{ route('subscription.pay.mobile-money') }}" class="flex flex-wrap items-end gap-3">
                                        @csrf
                                        <input type="hidden" name="plan_id" class="pm-plan-id-input" value="">
                                        <div class="min-w-[10rem]">
                                            <label for="network-{{ $gateway->id }}" class="block text-sm font-medium text-slate-700 mb-1">Network</label>
                                            <select id="network-{{ $gateway->id }}" name="network" required aria-required="true" class="pm-input">
                                                @if ($gateway->supports_mtn)<option value="mtn">MTN Mobile Money</option>@endif
                                                @if ($gateway->supports_airtel)<option value="airtel">Airtel Money</option>@endif
                                            </select>
                                        </div>
                                        <div class="flex-1 min-w-[12rem]">
                                            <label for="phone-{{ $gateway->id }}" class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                                            <input type="text" id="phone-{{ $gateway->id }}" name="phone_number" required aria-required="true"
                                                   placeholder="e.g. 0700000000" value="{{ old('phone_number', $accountPhone) }}" class="pm-input">
                                        </div>
                                        <button type="submit" class="pm-pay-btn inline-flex items-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed w-full justify-center"
                                                {{ $plans->isNotEmpty() ? 'disabled' : '' }}>
                                            <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                                            <span>Pay with Mobile Money</span>
                                        </button>
                                    </form>
                                    <p class="text-xs text-slate-400 mt-2">
                                        <i class="fa-solid fa-shield-halved text-emerald-500" aria-hidden="true"></i>
                                        You'll approve this directly on your phone. We will never ask you to enter your Mobile Money PIN here.
                                    </p>
                                @else
                                    {{-- Bank or mobile money: show details, collect a reference for manual verification. --}}
                                    @if ($gateway->instructions)
                                        <p class="text-sm text-slate-600 mb-3 whitespace-pre-line">{{ $gateway->instructions }}</p>
                                    @endif

                                    <dl class="text-sm mb-4 space-y-1">
                                        @if ($gateway->type === 'bank')
                                            <div class="flex justify-between"><dt class="text-slate-500">Bank</dt><dd>{{ $gateway->configValue('bank_name') }}</dd></div>
                                            <div class="flex justify-between"><dt class="text-slate-500">Account Name</dt><dd>{{ $gateway->configValue('account_name') }}</dd></div>
                                            <div class="flex justify-between"><dt class="text-slate-500">Account Number</dt><dd class="font-mono">{{ $gateway->configValue('account_number') }}</dd></div>
                                            @if ($gateway->configValue('routing_or_swift'))
                                                <div class="flex justify-between"><dt class="text-slate-500">Routing / SWIFT</dt><dd class="font-mono">{{ $gateway->configValue('routing_or_swift') }}</dd></div>
                                            @endif
                                        @else
                                            <div class="flex justify-between"><dt class="text-slate-500">Provider</dt><dd>{{ $gateway->configValue('provider_name') }}</dd></div>
                                            <div class="flex justify-between"><dt class="text-slate-500">Number</dt><dd class="font-mono">{{ $gateway->configValue('merchant_number') }}</dd></div>
                                        @endif
                                    </dl>

                                    <form method="POST" action="{{ route('subscription.pay.manual') }}" class="flex flex-wrap items-end gap-3">
                                        @csrf
                                        <input type="hidden" name="payment_gateway_id" value="{{ $gateway->id }}">
                                        <input type="hidden" name="plan_id" class="pm-plan-id-input" value="">
                                        <div class="flex-1 min-w-[12rem]">
                                            <label for="reference-{{ $gateway->id }}" class="block text-sm font-medium text-slate-700 mb-1">
                                                Transaction Reference
                                            </label>
                                            <input type="text" id="reference-{{ $gateway->id }}" name="reference" required aria-required="true"
                                                   placeholder="e.g. transaction ID from your bank/mobile app"
                                                   class="pm-input">
                                        </div>
                                        <button type="submit" class="pm-pay-btn inline-flex items-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed w-full justify-center"
                                                {{ $plans->isNotEmpty() ? 'disabled' : '' }}>
                                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                            <span>I've Paid Submit for Verification</span>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </dialog>
        </div>
        {{-- ============= end pm-sub-panel-subscription ============= --}}

        <div role="tabpanel" id="pm-sub-panel-billing" aria-labelledby="pm-sub-tab-billing" tabindex="0" class="pm-sub-panel space-y-6" hidden>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach ([
                    ['label' => 'Total Invoices', 'value' => $billingStats['total_invoices'], 'color' => 'slate'],
                    ['label' => 'Completed', 'value' => $billingStats['completed'], 'color' => 'emerald'],
                    ['label' => 'Pending', 'value' => $billingStats['pending'], 'color' => 'amber'],
                    ['label' => 'Failed', 'value' => $billingStats['failed'], 'color' => 'rose'],
                ] as $card)
                    <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $card['color'] }}-400 p-3">
                        <p class="text-xs text-slate-500 uppercase tracking-wide">{{ $card['label'] }}</p>
                        <p class="text-xl font-bold text-slate-800">{{ $card['value'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">Account payment phone</p>
                        <p class="font-semibold text-slate-800">
                            {{ $accountPhone ?: 'Not set yet' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            This number is pre-filled for Mobile Money prompts. When you use a different number, it becomes your new payment phone.
                        </p>
                    </div>
                    @if ($accountPhone)
                        <span class="inline-flex items-center gap-2 text-sm text-[var(--brand-1)]">
                            <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                            Ready for payment prompts
                        </span>
                    @endif
                </div>
            </div>

            <form method="GET" action="{{ route('subscription.show') }}" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="tab" value="billing">
                <div class="flex-1 min-w-[180px] max-w-xs">
                    <label for="billing_q" class="sr-only">Search</label>
                    <input type="search" id="billing_q" name="billing_q" value="{{ $billingSearch }}" placeholder="Search plan or reference..." class="pm-input text-sm">
                </div>
                <div>
                    <label for="billing_period" class="sr-only">Period</label>
                    <select id="billing_period" name="billing_period" onchange="pmToggleBillingDateRange(this)" class="pm-input text-sm">
                        <option value="">Any time</option>
                        <option value="daily" @selected($billingPeriod === 'daily')>Today</option>
                        <option value="weekly" @selected($billingPeriod === 'weekly')>This week</option>
                        <option value="monthly" @selected($billingPeriod === 'monthly')>This month</option>
                        <option value="range" @selected($billingPeriod === 'range')>Custom range...</option>
                    </select>
                </div>
                <div id="pm-billing-date-range" class="flex items-center gap-2" style="{{ $billingPeriod === 'range' ? '' : 'display: none;' }}">
                    <input type="date" name="billing_from" value="{{ $billingFrom }}" class="pm-input text-sm">
                    <span class="text-slate-400 text-sm">to</span>
                    <input type="date" name="billing_to" value="{{ $billingTo }}" class="pm-input text-sm">
                </div>
                <div>
                    <label for="billing_per_page" class="sr-only">Records per page</label>
                    <select id="billing_per_page" name="billing_per_page" class="pm-input text-sm">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($billingPerPage === $size)>{{ $size }} / page</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">Filter</button>
                @if ($billingSearch || $billingPeriod || $billingPerPage !== 10)
                    <a href="{{ route('subscription.show', ['tab' => 'billing']) }}" class="text-sm text-slate-500 hover:text-slate-700 pb-2.5">Clear</a>
                @endif
            </form>

            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-4 sm:p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <caption class="sr-only">Your invoices, receipts and pending subscription payments.</caption>
                        <thead class="text-left text-slate-500">
                            <tr>
                                <th scope="col" class="py-2 pr-4">Plan</th>
                                <th scope="col" class="py-2 pr-4">Method</th>
                                <th scope="col" class="py-2 pr-4">Phone / Account</th>
                                <th scope="col" class="py-2 pr-4">Amount</th>
                                <th scope="col" class="py-2 pr-4">Status</th>
                                <th scope="col" class="py-2 pr-4">Date</th>
                                <th scope="col" class="py-2 pr-4">Documents</th>
                                <th scope="col" class="py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($payments as $payment)
                                @php
                                    $paymentPhone = $payment->paymentContactPhone() ?: $accountPhone;
                                    $statusColor = match($payment->status) {
                                        'completed' => 'text-emerald-700',
                                        'pending' => 'text-amber-600',
                                        default => 'text-rose-600',
                                    };
                                @endphp
                                <tr class="align-top">
                                    <td class="py-3 pr-4 font-medium text-slate-800">{{ $payment->plan->name ?? '—' }}</td>
                                    <td class="py-3 pr-4">{{ $payment->gateway->display_name ?? $payment->gateway->name ?? ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                                    <td class="py-3 pr-4">
                                        @if ($paymentPhone)
                                            <div class="flex flex-col">
                                                <a href="tel:{{ preg_replace('/\s+/', '', $paymentPhone) }}" class="text-[var(--brand-1)] hover:underline whitespace-nowrap font-medium">
                                                    {{ $paymentPhone }}
                                                </a>
                                                <span class="text-[11px] text-slate-400">Mobile Money phone</span>
                                            </div>
                                        @elseif ($payment->method === 'bank' && filled($payment->reference))
                                            <div class="flex flex-col">
                                                <span class="font-mono text-slate-700 break-all">{{ $payment->reference }}</span>
                                                <span class="text-[11px] text-slate-400">Bank reference</span>
                                            </div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ format_money_in($payment->amount, $payment->currency) }}</td>
                                    <td class="py-3 pr-4">
                                        <span class="{{ $statusColor }} font-medium">{{ ucfirst($payment->status) }}</span>
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ $payment->created_at->format('Y-m-d') }}</td>
                                    <td class="py-3 pr-4">
                                        <div class="flex flex-col gap-1">
                                            @if ($payment->invoice)
                                                <a href="{{ route('subscription.invoice', $payment->invoice->id) }}" class="text-[var(--brand-1)] hover:underline whitespace-nowrap">
                                                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Invoice
                                                </a>
                                            @endif
                                            @if ($payment->status === 'completed')
                                                <a href="{{ route('subscription.receipt', $payment->id) }}" class="text-[var(--brand-1)] hover:underline whitespace-nowrap">
                                                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Receipt
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        @if ($payment->status === 'pending')
                                            <div class="flex flex-wrap items-center gap-2 min-w-[150px]">
                                                <button type="button"
                                                        onclick="document.getElementById('pm-pending-payment-{{ $payment->id }}').showModal()"
                                                        class="btn-primary text-white px-3 py-2 rounded-lg text-xs font-semibold whitespace-nowrap">
                                                    <i class="fa-solid fa-wallet mr-1" aria-hidden="true"></i> Pay
                                                </button>
                                                <form method="POST" action="{{ route('subscription.payment.cancel', $payment) }}"
                                                      data-confirm="Cancel this pending subscription invoice/payment?" data-confirm-title="Cancel pending payment?" data-confirm-text="Cancel payment">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-xs font-semibold whitespace-nowrap border border-rose-200 text-rose-700 bg-rose-50 hover:bg-rose-100">
                                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancel
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>

                                @if ($payment->status === 'pending')
                                    <dialog id="pm-pending-payment-{{ $payment->id }}" class="rounded-2xl p-0 w-[min(94vw,620px)] backdrop:bg-slate-900/50">
                                        <div class="bg-white rounded-2xl overflow-hidden">
                                            <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-slate-100">
                                                <div>
                                                    <h3 class="font-bold text-lg text-slate-900">Complete pending payment</h3>
                                                    <p class="text-sm text-slate-500 mt-1">
                                                        {{ $payment->plan->name ?? 'Subscription' }} · {{ format_money_in($payment->amount, $payment->currency) }}
                                                    </p>
                                                </div>
                                                <button type="button" onclick="this.closest('dialog').close()" class="text-slate-400 hover:text-slate-700 p-1" aria-label="Close">
                                                    <i class="fa-solid fa-xmark text-xl"></i>
                                                </button>
                                            </div>

                                            <div class="p-5 space-y-5">
                                                @if ($automaticMobileGateway)
                                                    <section class="border border-slate-200 rounded-xl p-4">
                                                        <h4 class="font-semibold text-slate-800 flex items-center gap-2">
                                                            <i class="fa-solid fa-mobile-screen-button text-[var(--brand-1)]"></i>
                                                            Mobile Money prompt
                                                        </h4>
                                                        <p class="text-sm text-slate-500 mt-1 mb-3">
                                                            Resend a payment prompt to your account phone or enter another number.
                                                        </p>
                                                        <form method="POST" action="{{ route('subscription.payment.mobile-money', $payment) }}" class="grid sm:grid-cols-3 gap-3 items-end">
                                                            @csrf
                                                            <div>
                                                                <label class="block text-xs font-medium text-slate-600 mb-1">Network</label>
                                                                <select name="network" required class="pm-input text-sm">
                                                                    @if ($automaticMobileGateway->supports_mtn)<option value="mtn">MTN Mobile Money</option>@endif
                                                                    @if ($automaticMobileGateway->supports_airtel)<option value="airtel">Airtel Money</option>@endif
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-slate-600 mb-1">Phone number</label>
                                                                <input type="text" name="phone_number" required value="{{ $paymentPhone }}" placeholder="0700000000" class="pm-input text-sm">
                                                            </div>
                                                            <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                                                                Send Prompt
                                                            </button>
                                                        </form>
                                                    </section>
                                                @endif

                                                @if ($bankGateways->isNotEmpty())
                                                    <section class="space-y-3">
                                                        <div>
                                                            <h4 class="font-semibold text-slate-800 flex items-center gap-2">
                                                                <i class="fa-solid fa-building-columns text-[var(--brand-1)]"></i>
                                                                Pay by bank transfer
                                                            </h4>
                                                            <p class="text-sm text-slate-500 mt-1">Transfer the invoice amount, then enter the transaction/reference number below.</p>
                                                        </div>

                                                        @foreach ($bankGateways as $bank)
                                                            <div class="border border-slate-200 rounded-xl p-4">
                                                                <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm mb-4">
                                                                    <div><dt class="text-slate-500">Bank</dt><dd class="font-medium">{{ $bank->configValue('bank_name') ?: $bank->name }}</dd></div>
                                                                    <div><dt class="text-slate-500">Account Name</dt><dd class="font-medium">{{ $bank->configValue('account_name') ?: '—' }}</dd></div>
                                                                    <div><dt class="text-slate-500">Account Number</dt><dd class="font-mono font-medium">{{ $bank->configValue('account_number') ?: '—' }}</dd></div>
                                                                    @if ($bank->configValue('routing_or_swift'))
                                                                        <div><dt class="text-slate-500">Routing / SWIFT</dt><dd class="font-mono">{{ $bank->configValue('routing_or_swift') }}</dd></div>
                                                                    @endif
                                                                </dl>
                                                                @if ($bank->instructions)
                                                                    <p class="text-xs text-slate-500 whitespace-pre-line mb-3">{{ $bank->instructions }}</p>
                                                                @endif
                                                                <form method="POST" action="{{ route('subscription.payment.bank', $payment) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                                                                    @csrf
                                                                    <input type="hidden" name="payment_gateway_id" value="{{ $bank->id }}">
                                                                    <div class="flex-1">
                                                                        <label class="block text-xs font-medium text-slate-600 mb-1">Bank transaction reference</label>
                                                                        <input type="text" name="reference" required placeholder="Enter transaction / bank reference" class="pm-input text-sm">
                                                                    </div>
                                                                    <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                                                                        Submit Payment
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endforeach
                                                    </section>
                                                @endif

                                                @if (! $automaticMobileGateway && $bankGateways->isEmpty())
                                                    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                                        No Mobile Money collection or bank-transfer gateway is currently enabled. Please contact support.
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </dialog>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-slate-500">No invoices or payments yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($payments->total() > 0)
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-5 pt-4 border-t border-slate-100">
                        <p class="text-sm text-slate-500">
                            Showing {{ $payments->firstItem() }} to {{ $payments->lastItem() }} of {{ $payments->total() }} records
                        </p>
                        <div>{{ $payments->links() }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Display-only conversion recalculates what's SHOWN using the
        // rate an admin set (units of that currency per 1 base-currency
        // unit), never touches what's actually charged. Reads the
        // rate/symbol straight off the selected <option>'s data
        // attributes rather than a separate lookup table in JS.
        var pmBaseCurrencyCode = @json($settings->default_currency_code);
        var pmBaseCurrencySymbol = @json($settings->default_currency_symbol);
        var pmBaseCurrencyDecimals = {{ $settings->default_currency_decimals }};

        function pmConvertPlanPrices(currencyCode) {
            var select = document.getElementById('pm-currency-select');
            var selectedOption = select.options[select.selectedIndex];
            var isBase = currencyCode === pmBaseCurrencyCode;
            var rate = isBase ? 1 : parseFloat(selectedOption.dataset.rate || '1');
            var symbol = isBase ? pmBaseCurrencySymbol : (selectedOption.dataset.symbol || currencyCode);
            var decimals = isBase ? pmBaseCurrencyDecimals : 2;

            document.querySelectorAll('.pm-plan-price').forEach(function (el) {
                var basePrice = parseFloat(el.dataset.basePrice);
                var converted = isBase ? basePrice : (rate > 0 ? basePrice / rate : basePrice);
                el.textContent = symbol + ' ' + converted.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            });
        }

        function pmSelectPlan(planId, price, planName, periodLabel, cardEl) {
            document.querySelectorAll('.pm-plan-id-input').forEach(function (input) {
                input.value = planId;
            });
            document.querySelectorAll('.pm-pay-btn').forEach(function (btn) {
                btn.disabled = false;
            });
            document.querySelectorAll('.pm-plan-card').forEach(function (card) {
                var selected = card === cardEl;
                // A ring (not a border-color swap) since these cards now
                // have colored gradient backgrounds a border-color
                // change wouldn't read clearly against every gradient,
                // but a gold ring stands out consistently on all of them.
                card.classList.toggle('ring-[#FFBA00]', selected);
                card.classList.toggle('ring-transparent', !selected);
                card.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            var summary = document.getElementById('pm-selected-plan-summary');
            var label = document.getElementById('pm-selected-plan-label');
            if (summary && label) {
                summary.classList.remove('hidden');
                label.textContent = planName + ' ' + pmBaseCurrencySymbol + ' ' + price.toLocaleString(undefined, { minimumFractionDigits: pmBaseCurrencyDecimals, maximumFractionDigits: pmBaseCurrencyDecimals });
            }

            // Populate and open the checkout modal this is now where
            // the actual payment method choice + confirmation happens,
            // not inline on the page.
            var amountFormatted = pmBaseCurrencySymbol + ' ' + price.toLocaleString(undefined, { minimumFractionDigits: pmBaseCurrencyDecimals, maximumFractionDigits: pmBaseCurrencyDecimals });
            var planNameEl = document.getElementById('pm-checkout-plan-name');
            var periodEl = document.getElementById('pm-checkout-plan-period');
            var amountEl = document.getElementById('pm-checkout-plan-amount');
            if (planNameEl) { planNameEl.textContent = planName; }
            if (periodEl) { periodEl.textContent = periodLabel; }
            if (amountEl) { amountEl.textContent = amountFormatted; }

            // Default to whichever payment method radio is already
            // checked (the first one, server-rendered) so the summary's
            // method label and visible gateway section are correct even
            // if the user never touches the radios themselves.
            var checkedMethod = document.querySelector('input[name="checkout_method"]:checked');
            if (checkedMethod) {
                var checkedLabel = checkedMethod.closest('label').textContent.trim();
                pmSelectPaymentMethod(checkedMethod.value, checkedLabel);
            }

            var modal = document.getElementById('pm-checkout-modal');
            if (modal) { modal.showModal(); }
        }

        function pmSelectPaymentMethod(gatewayId, methodLabel) {
            document.querySelectorAll('.pm-gateway-section').forEach(function (section) {
                section.hidden = section.dataset.gatewayId !== String(gatewayId);
            });
            var methodLabelEl = document.getElementById('pm-checkout-method-label');
            if (methodLabelEl && methodLabel) { methodLabelEl.textContent = methodLabel; }
        }

        function pmSelectSubTab(key) {
            document.querySelectorAll('.pm-sub-tab').forEach(function (btn) {
                var isSelected = btn.dataset.tab === key;
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                btn.setAttribute('tabindex', isSelected ? '0' : '-1');
                btn.classList.toggle('border-[var(--brand-1)]', isSelected);
                btn.classList.toggle('text-[var(--brand-1)]', isSelected);
                btn.classList.toggle('border-transparent', !isSelected);
                btn.classList.toggle('text-slate-500', !isSelected);
            });
            document.querySelectorAll('.pm-sub-panel').forEach(function (panel) {
                panel.hidden = panel.id !== 'pm-sub-panel-' + key;
            });
        }

        function pmSubTabKeydown(event, currentKey) {
            var tabs = Array.prototype.map.call(document.querySelectorAll('.pm-sub-tab'), function (t) { return t.dataset.tab; });
            var index = tabs.indexOf(currentKey);
            var nextIndex = null;

            if (event.key === 'ArrowRight') { nextIndex = (index + 1) % tabs.length; }
            else if (event.key === 'ArrowLeft') { nextIndex = (index - 1 + tabs.length) % tabs.length; }
            else { return; }

            event.preventDefault();
            pmSelectSubTab(tabs[nextIndex]);
            document.getElementById('pm-sub-tab-' + tabs[nextIndex]).focus();
        }

        function pmToggleBillingDateRange(select) {
            var wrapper = document.getElementById('pm-billing-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }

        // Land on the Invoices & Receipts tab after searching/filtering/
        // paginating there, rather than resetting back to "Your
        // Subscription" on every reload.
        document.addEventListener('DOMContentLoaded', function () {
            var params = new URLSearchParams(window.location.search);
            if (params.get('tab') === 'billing' || params.has('billing_page') || params.has('billing_q') || params.has('billing_period')) {
                pmSelectSubTab('billing');
            }
        });
    </script>
@endsection
