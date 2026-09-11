<form
    method="POST"
    action="{{ route('logout') }}"
    class="{{ $attributes->get('class') }}"
>
    @csrf

    <button
        type="submit"
        {{ $attributes->except('class') }}
    >
        {{ $slot->isEmpty() ? 'Logout' : $slot }}
    </button>
</form>
