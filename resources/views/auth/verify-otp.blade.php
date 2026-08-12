<x-guest-layout>
    <style>
        /*
        |--------------------------------------------------------------------------
        | OTP secondary actions
        |--------------------------------------------------------------------------
        | These are POST buttons, not primary actions. The shared authentication
        | styles target submit buttons globally, so reset them explicitly here.
        */
        .otp-secondary-action,
        .otp-secondary-action:hover,
        .otp-secondary-action:focus,
        .otp-secondary-action:active {
            width: auto !important;
            min-width: 0 !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
            box-shadow: none !important;
            outline: none !important;
            color: var(--auth-primary, var(--brand-1, #009688)) !important;
            font: inherit !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            line-height: 1.5 !important;
            text-decoration: none !important;
            cursor: pointer !important;
            appearance: none !important;
            -webkit-appearance: none !important;
        }

        .otp-secondary-action:hover {
            text-decoration: underline !important;
            opacity: 0.85;
        }

        .otp-secondary-action:focus-visible {
            text-decoration: underline !important;
        }

        .otp-start-over {
            font-size: 0.8125rem !important;
        }
    </style>

    <div class="space-y-1">
        <h1 class="text-lg font-semibold text-slate-800">
            Enter your code
        </h1>

        <p class="text-sm text-slate-500 leading-relaxed">
            We emailed a 6-digit code to your address. It expires in 10 minutes.
        </p>
    </div>

    <x-auth-session-status
        class="mt-4 mb-4"
        :status="session('status')"
    />

    <form
        method="POST"
        action="{{ route('otp.verify.submit') }}"
        class="mt-6"
    >
        @csrf

        <x-auth-field
            name="code"
            label="Verification Code"
            type="text"
            placeholder="000000"
            hint="Check spam too, if it hasn't shown up."
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            autofocus
            required
            autocomplete="one-time-code"
            class="text-center text-2xl"
            style="letter-spacing: 0.5em;"
        />

        {{--
            Keep Verify first in the HTML so pressing Enter verifies the OTP.
            Resend uses formaction and formnovalidate, but remains a plain text
            action visually.
        --}}
        <div class="flex flex-wrap items-center justify-between gap-4 mt-6">

            <button
                type="submit"
                formaction="{{ route('otp.resend') }}"
                formnovalidate
                class="otp-secondary-action order-1"
            >
                Resend code
            </button>

            <x-primary-button class="order-2 gap-2">
                <i
                    class="fa-solid fa-check text-xs"
                    aria-hidden="true"
                ></i>
                Verify &amp; Sign In
            </x-primary-button>

        </div>
    </form>

    <form
        method="POST"
        action="{{ route('otp.cancel') }}"
        class="mt-5 text-center"
    >
        @csrf

        <button
            type="submit"
            class="otp-secondary-action otp-start-over"
        >
            <span class="text-slate-500">Not you?</span>
            <span>Start over</span>
        </button>
    </form>
</x-guest-layout>
