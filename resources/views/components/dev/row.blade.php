@props([
    // Component name — rendered as inline code.
    'name',
    // One-line description.
    'desc',
])

{{--
    Row header for the /dev/components gallery. Shows the component name,
    a one-line description, and a `{!! $slot !!}` block intended to contain
    the component's usage snippet. Wrap your code snippet in @verbatim
    in the caller so Blade doesn't compile it.

    Usage:
        <x-dev.row name="x-eyebrow" desc="…">
            @verbatim
<x-eyebrow color="produce">Label</x-eyebrow>
            @endverbatim
        </x-dev.row>
--}}

<div class="mt-6 space-y-2">
    <div class="flex items-baseline gap-3">
        <code class="font-semibold text-brand-ink">{{ $name }}</code>
        <span class="text-sm text-brand-ink/60">{{ $desc }}</span>
    </div>
    <pre class="overflow-x-auto rounded border border-brand-ink/10 bg-brand-ink/[0.04] p-3 text-[12px] leading-relaxed text-brand-ink/80"><code>{{ trim($slot) }}</code></pre>
</div>
