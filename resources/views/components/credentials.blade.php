{{-- One-time login details after creating an account or resetting a password. Shown once, never stored. --}}
@php($credentials = session('credentials', []))

@if ($credentials)
    <div class="rounded-2xl border border-gold-200 bg-gold-50 p-5" role="status">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="m-0 flex items-center gap-2 text-base font-semibold text-gold">
                    <x-lucide-key-round class="size-[18px]" aria-hidden="true" /> New login details
                </h2>
                <p class="m-0 mt-1 text-sm text-gold">Print or copy these now. They won’t be shown again. Ask them to change the password after they log in.</p>
            </div>
            <x-button variant="secondary" size="sm" icon="printer" type="button" onclick="window.print()">Print</x-button>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($credentials as $credential)
                <div class="rounded-xl bg-white p-4 ring-1 ring-gold-200">
                    <div class="text-xs font-semibold tracking-wide text-muted uppercase">{{ $credential['who'] }}</div>
                    <dl class="m-0 mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm">
                        <dt class="text-muted">Log in with</dt>
                        <dd class="m-0 font-mono font-semibold">{{ $credential['login'] }}</dd>
                        <dt class="text-muted">Password</dt>
                        <dd class="m-0 font-mono font-semibold">{{ $credential['password'] }}</dd>
                    </dl>
                </div>
            @endforeach
        </div>
    </div>
@endif
