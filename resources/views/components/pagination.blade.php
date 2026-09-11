{{--
    Pagination links.

    Props:
        paginator : Laravel paginator instance

    Wraps $paginator->links() with consistent styling and preserves query
    parameters.

    Usage:
        <x-pagination :paginator="$users" />
--}}
@props([
    'paginator',
])

@if ($paginator->hasPages())
    <div class="pm-pagination mt-6">
        {{ $paginator->links() }}
    </div>
@endif
