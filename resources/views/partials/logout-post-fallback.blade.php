{{-- Include once in the main authenticated layout before </body>. --}}
<form
    id="global-logout-form"
    method="POST"
    action="{{ route('logout') }}"
    class="hidden"
>
    @csrf
</form>

<script>
document.addEventListener('click', function (event) {
    const link = event.target.closest(
        'a[href="/logout"], a[href$="/logout"]'
    );

    if (!link) {
        return;
    }

    event.preventDefault();

    document
        .getElementById('global-logout-form')
        ?.submit();
});
</script>
