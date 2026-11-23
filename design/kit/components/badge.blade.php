@props(['tone' => 'neutral'])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap',
    match ($tone) {
        'brand' => 'bg-brand-50 text-brand-600',
        'naija', 'success' => 'bg-naija-50 text-naija-700',
        'gold', 'warning' => 'bg-gold-50 text-gold',
        'danger' => 'bg-danger-50 text-danger',
        'sky' => 'bg-sky-50 text-sky',
        'ink' => 'bg-ink text-white',
        default => 'bg-chip text-muted',
    },
]) }}>{{ $slot }}</span>
