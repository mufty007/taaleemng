<x-layouts.school-public title="Apply for admission" :school="$school">
    <div class="flex flex-col gap-2">
        <div class="kicker">Admissions · {{ $session->name }} session</div>
        <h1 class="m-0 text-[32px] leading-tight font-bold tracking-[-0.03em]">Apply for admission</h1>
        <p class="m-0 text-[15px] text-muted">Fill in the form below. You will get an application number, and the school will contact you about the next steps, such as an entrance exam.</p>
    </div>

    @if ($errors->any())
        <div class="mt-6 rounded-2xl bg-danger-50 px-5 py-3 text-[15px] font-medium text-danger" role="alert">Please check the highlighted fields.</div>
    @endif

    <form method="POST" action="{{ route('apply.store') }}" class="mt-6 flex flex-col gap-6 rounded-2xl border border-line bg-white p-5 shadow-card sm:p-8" novalidate>
        @csrf
        <div class="hidden" aria-hidden="true">
            <label for="website">Leave this empty</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        @include('admissions._application-fields')

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
            <p class="m-0 max-w-md text-[13px] text-muted">By applying you agree that {{ $school->name }} may keep these details to process the application.</p>
            <x-button variant="brand" size="lg" icon="send">Submit application</x-button>
        </div>
    </form>
</x-layouts.school-public>
