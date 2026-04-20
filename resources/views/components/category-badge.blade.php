@props([
    'category' => null,
    'size' => 'sm',
])

@php
    use App\Enums\ProductCategory;
    use App\Support\ProductCategoryStyle;

    $enum = $category instanceof ProductCategory
        ? $category
        : ProductCategory::tryFrom((string) $category);

    if (! $enum) {
        return;
    }

    $style = ProductCategoryStyle::for($enum);
    $sizeClasses = match ($size) {
        'xs' => 'text-[10px] px-2 py-0.5',
        'md' => 'text-xs px-3 py-1.5',
        default => 'text-[11px] px-2.5 py-1',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center font-sans font-semibold uppercase tracking-eyebrow', $sizeClasses, $style['badge_classes']]) }}>
    {{ __('admin.category.' . $enum->value) }}
</span>
