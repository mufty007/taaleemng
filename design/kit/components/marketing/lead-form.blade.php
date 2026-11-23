{{-- The one-field "book a demo" form used in the hero and the closing call to action. --}}
@props(['source', 'button' => 'Book a free demo', 'dark' => false])

@php
    $error = $errors->getBag($source)->first('contact');
    $sent = session('demo_sent') === $source;
@endphp

<div {{ $attributes->class(['flex w-full max-w-[520px] flex-col gap-3']) }}>
    <form
        method="POST"
        action="{{ route('demo-requests.store') }}"
        class="flex flex-wrap gap-2 rounded-[18px] border border-line bg-white p-[7px] shadow-card"
    >
        @csrf
        <input type="hidden" name="source" value="{{ $source }}">
        <input type="hidden" name="kind" value="demo">
        <div class="absolute -left-[9999px]" aria-hidden="true">
            <label for="{{ $source }}-website">Leave this empty</label>
            <input id="{{ $source }}-website" type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <label for="{{ $source }}-contact" class="sr-only">School email or phone number</label>
        <input
            id="{{ $source }}-contact"
            name="contact"
            type="text"
            autocomplete="email"
            required
            value="{{ ! $sent && old('source') === $source ? old('contact') : '' }}"
            placeholder="School email or phone number"
            @if ($error) aria-invalid="true" aria-describedby="{{ $source }}-contact-error" @endif
            class="min-w-[200px] flex-1 border-0 bg-transparent px-3.5 py-3 text-base text-ink outline-none focus:ring-0 focus-visible:outline-none"
        >
        <button
            type="submit"
            @class([
                'rounded-xl px-[22px] py-[13px] text-base font-semibold text-white transition',
                'bg-ink hover:bg-brand-700' => $dark,
                'bg-brand-600 hover:bg-ink' => ! $dark,
            ])
        >{{ $button }}</button>
    </form>

    @if ($error)
        <p id="{{ $source }}-contact-error" @class(['m-0 text-[15px] font-medium', 'text-white' => $dark, 'text-danger' => ! $dark])>{{ $error }}</p>
    @endif

    @if ($sent)
        <p role="status" @class(['m-0 text-[15px] font-medium', 'text-white' => $dark, 'text-naija' => ! $dark])>Thank you. Our team will call you to book your demo.</p>
    @endif
</div>
