{{--
    Flash message partial.
    Uses the <x-alert> component for consistent styling.
    Include this in the layout right after the opening of #main-content.
--}}
@if (session('success'))
    <x-alert type="success" message="{{ session('success') }}" />
@endif

@if (session('warning'))
    <x-alert type="warning" message="{{ session('warning') }}" />
@endif

@if (session('info'))
    <x-alert type="info" message="{{ session('info') }}" />
@endif

@if (session('status'))
    <x-alert type="success" message="{{ session('status') }}" />
@endif

@if (session('error'))
    <x-alert type="error" message="{{ session('error') }}" />
@endif

@if ($errors->any())
    <x-alert type="error" :dismissible="false" :autoDismiss="false">
        <p class="font-medium mb-1">
            Please fix the following ({{ $errors->count() }} {{ Str::plural('error', $errors->count()) }}):
        </p>
        <ul class="list-disc list-inside mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
