<x-layouts.app title="Notice board">
    <x-page-header kicker="{{ $currentSchool->name }}" title="Notice board" :description="$canPost ? 'News and reminders for the school. Post to everyone, to staff, parents or students, or to one class.' : 'News and reminders from the school.'">
        <x-slot:actions>
            @if ($canPost)
                <x-button variant="brand" icon="megaphone" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-notice')">New notice</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="flex max-w-3xl flex-col gap-3">
        @forelse ($announcements as $announcement)
            @php($unread = $announcement->reads->isEmpty())
            <a href="{{ route('school.announcements.show', $announcement) }}" @class(['group flex gap-4 rounded-2xl border bg-white p-5 transition hover:border-brand hover:shadow-card', 'border-brand-200' => $unread, 'border-line' => ! $unread])>
                <span @class(['grid size-11 flex-none place-items-center rounded-xl', 'bg-brand-600 text-white' => $announcement->is_pinned, 'bg-brand-50 text-brand-600' => ! $announcement->is_pinned])>
                    <x-dynamic-component :component="'lucide-'.($announcement->is_pinned ? 'pin' : $announcement->audience->icon())" class="size-5" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span @class(['text-[17px]', 'font-bold' => $unread, 'font-semibold' => ! $unread])>{{ $announcement->title }}</span>
                        @if ($unread)<x-badge tone="brand">New</x-badge>@endif
                        @if ($announcement->is_pinned)<x-badge tone="gold">Pinned</x-badge>@endif
                    </span>
                    <span class="mt-1 line-clamp-2 block text-[15px] text-muted">{{ $announcement->body }}</span>
                    <span class="mt-2 block text-xs text-muted">{{ $announcement->author?->name ?? 'The school' }} · to {{ $announcement->audienceLabel() }} · {{ $announcement->published_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <x-card><x-empty-state icon="megaphone" title="No notices yet" :body="$canPost ? 'Post the first one: resumption dates, PTA meetings, sports day…' : 'When the school posts news or reminders, they appear here.'" /></x-card>
        @endforelse

        {{ $announcements->links() }}
    </div>

    @if ($canPost)
        <x-drawer name="new-notice" title="New notice" description="It shows on the notice board and lights up the bell for everyone it is meant for." :show="$errors->announcement->isNotEmpty()" width="sm:max-w-xl">
            <form method="POST" action="{{ route('school.announcements.store') }}" class="flex flex-col gap-4" id="new-notice-form" x-data="{ audience: @js(old('audience', array_key_first($audiences))) }">
                @csrf
                <x-field label="Title" name="title" bag="announcement" placeholder="e.g. PTA meeting on Saturday" required maxlength="150" />
                <div class="flex flex-col gap-1.5">
                    <label for="notice-body" class="text-sm font-semibold">Message</label>
                    <textarea id="notice-body" name="body" rows="7" maxlength="5000" class="field resize-y" required>{{ old('body') }}</textarea>
                    @error('body', 'announcement')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="notice-audience" class="text-sm font-semibold">Who is it for?</label>
                    <select id="notice-audience" name="audience" class="field" x-model="audience">
                        @foreach ($audiences as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div x-show="audience === 'class'" class="flex flex-col gap-1.5">
                    <label for="notice-class" class="text-sm font-semibold">Class</label>
                    <select id="notice-class" name="class_arm_id" class="field">
                        <option value="">Choose a class</option>
                        @foreach ($classOptions as $id => $label)<option value="{{ $id }}" @selected((int) old('class_arm_id') === $id)>{{ $label }}</option>@endforeach
                    </select>
                    <p class="m-0 text-xs text-muted">Its students, their parents and the teachers who teach it.</p>
                    @error('class_arm_id', 'announcement')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
                <x-field label="Take it down on (optional)" name="expires_at" type="date" bag="announcement" hint="It disappears from the board after this day." />
                @if ($isOffice)
                    <label class="flex items-center gap-3 text-[15px]">
                        <input type="hidden" name="is_pinned" value="0">
                        <input type="checkbox" name="is_pinned" value="1" class="size-4 accent-brand-600" @checked(old('is_pinned'))>
                        Pin it to the top of the board
                    </label>
                @endif
            </form>
            <x-slot:footer><x-button variant="brand" icon="send" form="new-notice-form">Post notice</x-button></x-slot:footer>
        </x-drawer>
    @endif
</x-layouts.app>
