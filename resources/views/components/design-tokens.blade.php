{{--
    Design tokens — shared CSS custom properties for the "My Digital Diary"
    UI component library.

    Maps to the existing brand palette (--brand-1 / --brand-2 defined in the
    app layout) and the Apple-inspired 2026 visual language. Include this
    component once near the top of a page (or in the layout) so every
    component below can rely on these tokens.

    Usage:
        <x-design-tokens />
--}}
<style>
    :root {
        /* Brand / semantic colors */
        --color-primary: var(--brand-1, #00897B);
        --color-secondary: var(--brand-2, #73BEB6);
        --color-success: #16a34a;
        --color-warning: #d97706;
        --color-danger: #dc2626;
        --color-info: #2563eb;

        /* Neutrals */
        --color-border: #dce2e8;
        --color-background: #f4f6f8;
        --color-text: #111827;
        --color-muted: #667085;

        /* Radii */
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 20px;

        /* Shadows */
        --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.05);
        --shadow-md: 0 8px 26px rgba(15, 23, 42, 0.055);
        --shadow-lg: 0 14px 38px rgba(15, 23, 42, 0.08);

        /* Spacing scale */
        --spacing-1: 0.25rem;
        --spacing-2: 0.5rem;
        --spacing-3: 0.75rem;
        --spacing-4: 1rem;
        --spacing-5: 1.25rem;
        --spacing-6: 1.5rem;
        --spacing-7: 1.75rem;
        --spacing-8: 2rem;
    }
</style>
