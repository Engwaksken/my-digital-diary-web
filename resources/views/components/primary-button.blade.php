{{--
    Drop-in replacement for Breeze's default primary-button component
    (plain gray, uppercase, tracking-widest) — reskinned to match this
    app's brand. Solid primary color, secondary color on hover (not a
    gradient, not just a darker shade of the same color) — see
    .btn-primary in guest-layout.blade.php's <style> block. Uses that
    plain CSS class rather than an inline style specifically because an
    inline `style` attribute can't express a :hover state, and would
    always outrank a class-based hover rule even if it could. This class
    is genuinely plain, hand-written CSS with zero Tailwind involvement —
    unlike Tailwind's bracket-arbitrary-value syntax (which did fail to
    render reliably here before), a `<style>` block a browser parses
    natively isn't dependent on any JIT scanner picking it up.
--}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary inline-flex items-center justify-center gap-2 px-5 py-2.5 border border-transparent rounded-lg font-medium text-sm text-white shadow-sm hover:shadow-md focus:outline-none transition-colors disabled:opacity-50 disabled:cursor-not-allowed']) }}>
    {{ $slot }}
</button>
