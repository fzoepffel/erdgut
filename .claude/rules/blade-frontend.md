# Frontend (Blade + Tailwind + Alpine)

Applies to: `**/*.blade.php`, `resources/**/*.js`, `resources/**/*.css`

- **Blade**: Server-rendered; use components for reuse
- **Tailwind**: Utility-first; avoid custom CSS unless necessary
- **Alpine.js**: For interactivity (forms, modals, toggles); keep logic minimal

## Patterns

- Use `@props` for Blade component attributes
- Prefer Tailwind classes over inline styles
- Alpine: `x-data`, `x-show`, `@click`; avoid large inline scripts
