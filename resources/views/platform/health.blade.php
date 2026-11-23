<x-layouts.app title="Health">
    @php
        $failing = collect($checks)->where('status', 'fail')->count();
        $warning = collect($checks)->where('status', 'warn')->count();
    @endphp

    <x-page-header kicker="Platform console" title="Health" :description="$failing ? $failing.' '.str('problem')->plural($failing).($failing === 1 ? ' needs' : ' need').' fixing.' : ($warning ? 'Working, with '.$warning.' '.str('thing')->plural($warning).' to look at.' : 'Everything is working.')" />

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($checks as $check)
            @php
                [$ring, $dot, $label] = match ($check['status']) {
                    'ok' => ['border-line', 'bg-naija', 'OK'],
                    'warn' => ['border-gold-200', 'bg-gold', 'Check'],
                    default => ['border-danger/40', 'bg-danger', 'Problem'],
                };
            @endphp
            <div class="flex flex-col gap-2 rounded-2xl border bg-white p-5 {{ $ring }}">
                <div class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 font-semibold"><x-dynamic-component :component="'lucide-'.$check['icon']" class="size-[18px] text-muted" aria-hidden="true" /> {{ $check['label'] }}</span>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold"><span class="size-2 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $label }}</span>
                </div>
                <p class="m-0 text-sm text-muted">{{ $check['detail'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card :padding="false">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <h2 class="m-0 text-base font-semibold">Failed background jobs</h2>
                    <p class="m-0 text-sm text-muted">Emails and imports that didn’t go through. Fix the cause (often the email settings), then retry.</p>
                </div>
                @if ($failedJobs->isNotEmpty())
                    <form method="POST" action="{{ route('platform.health.retry') }}">
                        @csrf
                        <input type="hidden" name="id" value="all">
                        <x-button variant="secondary" size="sm" icon="rotate-ccw">Retry all</x-button>
                    </form>
                @endif
            </div>
            @forelse ($failedJobs as $job)
                <div class="flex flex-wrap items-start gap-3 border-b border-line px-5 py-3 last:border-b-0 sm:flex-nowrap">
                    <span class="mt-0.5 grid size-8 flex-none place-items-center rounded-full bg-danger-50 text-danger"><x-lucide-circle-alert class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="font-semibold">{{ $job['name'] }} <span class="font-normal text-muted">· {{ $job['queue'] }} · {{ \Illuminate\Support\Carbon::parse($job['failed_at'])->diffForHumans() }}</span></div>
                        <div class="mt-0.5 font-mono text-xs break-words text-muted">{{ $job['error'] }}</div>
                    </div>
                    <div class="flex flex-none gap-2">
                        <form method="POST" action="{{ route('platform.health.retry') }}">
                            @csrf
                            <input type="hidden" name="id" value="{{ $job['uuid'] }}">
                            <x-button variant="soft" size="sm">Retry</x-button>
                        </form>
                        <form method="POST" action="{{ route('platform.health.forget') }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="id" value="{{ $job['uuid'] }}">
                            <x-button variant="ghost" size="sm">Delete</x-button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state icon="circle-check" title="No failed jobs" body="Every email and import has gone through." />
            @endforelse
        </x-card>

        <x-card>
            <h2 class="m-0 text-base font-semibold">What’s running</h2>
            <dl class="m-0 mt-4 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                @foreach ($versions as $name => $value)
                    <dt class="text-muted">{{ $name }}</dt>
                    <dd class="m-0 font-medium break-words">{{ $value }}</dd>
                @endforeach
            </dl>
        </x-card>
    </div>
</x-layouts.app>
