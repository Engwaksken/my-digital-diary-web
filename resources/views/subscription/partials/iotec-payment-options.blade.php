@php
    $iotecGateway = ($gateways ?? collect())->first(function ($gateway) {
        return strtolower(trim((string) ($gateway->gateway_code ?? ''))) === 'iotec';
    });

    $iotecMethods = [];

    if ($iotecGateway) {
        $raw = $iotecGateway->supported_payment_methods ?? [];

        if (is_array($raw)) {
            $iotecMethods = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $iotecMethods = is_array($decoded)
                ? $decoded
                : preg_split('/[\s,;|]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        }

        $iotecMethods = collect($iotecMethods)
            ->map(fn ($method) => strtolower(trim((string) $method)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    $iotecHasMobileMoney = $iotecGateway && (
        in_array('mobile_money', $iotecMethods, true)
        || in_array('mtn', $iotecMethods, true)
        || in_array('airtel', $iotecMethods, true)
        || (bool) ($iotecGateway->supports_mtn ?? false)
        || (bool) ($iotecGateway->supports_airtel ?? false)
    );

    $iotecHasCard = $iotecGateway && count(array_intersect(
        $iotecMethods,
        ['card', 'visa', 'mastercard', 'visa_mastercard']
    )) > 0;
@endphp

@if ($iotecGateway && ($iotecHasMobileMoney || $iotecHasCard))
    <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
        <div class="flex items-start gap-3">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700">
                <i class="fa-solid fa-wallet" aria-hidden="true"></i>
            </div>

            <div>
                <h3 class="font-black text-slate-900">Pay securely with ioTec</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Choose an available ioTec channel. Your subscription is activated only after ioTec confirms successful payment.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('subscription.pay.iotec') }}" class="mt-4 space-y-4 pm-iotec-payment-form" data-iotec-payment-form>
            @csrf

            @if (!empty($selectedPlanId))
                <input type="hidden" name="subscription_plan_id" value="{{ $selectedPlanId }}">
            @else
                <div>
                    <label class="text-xs font-bold text-slate-700">Subscription plan</label>
                    <select name="subscription_plan_id" class="pm-input mt-1 w-full" required>
                        <option value="">Choose plan</option>
                        @foreach (($plans ?? collect()) as $plan)
                            <option value="{{ $plan->id }}">
                                {{ $plan->name ?? $plan->title ?? ('Plan #'.$plan->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <fieldset>
                <legend class="text-xs font-bold text-slate-700">Payment method</legend>

                <div class="mt-2 grid gap-2 {{ $iotecHasMobileMoney && $iotecHasCard ? 'sm:grid-cols-2' : '' }}">
                    @if ($iotecHasMobileMoney)
                        <label class="cursor-pointer rounded-xl border border-slate-200 p-3 hover:border-slate-300">
                            <div class="flex items-start gap-2">
                                <input
                                    type="radio"
                                    name="payment_channel"
                                    value="mobile_money"
                                    @checked(!$iotecHasCard)
                                    onchange="pmToggleStandaloneIoTecFields(this.form)"
                                >
                                <span>
                                    <strong class="block text-sm">Mobile Money</strong>
                                    <span class="mt-1 block text-[11px] text-slate-500">
                                        Approve the payment request on your phone.
                                    </span>
                                </span>
                            </div>
                        </label>
                    @endif

                    @if ($iotecHasCard)
                        <label class="cursor-pointer rounded-xl border border-slate-200 p-3 hover:border-slate-300">
                            <div class="flex items-start gap-2">
                                <input
                                    type="radio"
                                    name="payment_channel"
                                    value="card"
                                    @checked(!$iotecHasMobileMoney)
                                    onchange="pmToggleStandaloneIoTecFields(this.form)"
                                >
                                <span>
                                    <strong class="block text-sm">Visa / MasterCard</strong>
                                    <span class="mt-1 block text-[11px] text-slate-500">
                                        Continue to ioTec's secure hosted checkout.
                                    </span>
                                    <span class="mt-2 flex gap-2 text-xl text-slate-700">
                                        <i class="fa-brands fa-cc-visa" aria-hidden="true"></i>
                                        <i class="fa-brands fa-cc-mastercard" aria-hidden="true"></i>
                                    </span>
                                </span>
                            </div>
                        </label>
                    @endif
                </div>
            </fieldset>

            @if ($iotecHasMobileMoney)
                <div data-iotec-mobile-fields>
                    <label class="text-xs font-bold text-slate-700">Mobile Money Number</label>
                    <input
                        type="tel"
                        name="phone"
                        value="{{ $accountPhone ?? '' }}"
                        class="pm-input mt-1 w-full"
                        placeholder="e.g. 2567XXXXXXXX"
                    >
                </div>
            @endif

            @if ($iotecHasCard)
                <div data-iotec-card-fields class="hidden rounded-xl bg-slate-50 p-3">
                    <p class="text-[11px] leading-5 text-slate-500">
                        Your card details are entered only on ioTec's hosted checkout. My Digital Diary does not store them.
                    </p>
                    <input type="hidden" name="card_brand" value="visa">
                </div>
            @endif

            <button type="submit" class="btn-primary w-full rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                Continue with ioTec
            </button>
        </form>
    </div>

    <script>
        function pmToggleStandaloneIoTecFields(form) {
            if (!form) return;

            var selected = form.querySelector('input[name="payment_channel"]:checked');
            var channel = selected ? selected.value : '';

            var mobile = form.querySelector('[data-iotec-mobile-fields]');
            var card = form.querySelector('[data-iotec-card-fields]');
            var phone = form.querySelector('input[name="phone"]');

            if (mobile) {
                mobile.classList.toggle('hidden', channel !== 'mobile_money');
            }

            if (card) {
                card.classList.toggle('hidden', channel !== 'card');
            }

            if (phone) {
                phone.required = channel === 'mobile_money';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form[action="{{ route('subscription.pay.iotec') }}"]').forEach(function (form) {
                pmToggleStandaloneIoTecFields(form);
            });
        });
    </script>
@endif

@include('subscription.partials.iotec-confirmation')
