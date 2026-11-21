<x-layouts.app title="Settings">
    <x-page-header kicker="System" title="Settings" description="Admissions, promotion, payments and how document numbers are built for {{ $school->name }}." />

    <form method="POST" action="{{ route('school.settings.update') }}" class="flex flex-col gap-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">Admissions & results</h2>

                <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas px-4 py-3">
                    <input type="checkbox" name="admissions_open" value="1" @checked(old('admissions_open', $admissionsOpen)) class="mt-1 size-4 rounded border-line">
                    <span>
                        <span class="block text-sm font-semibold">Online admission form is open</span>
                        <span class="block text-sm text-muted">Families can apply at your public admissions page.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas px-4 py-3">
                    <input type="checkbox" name="show_positions" value="1" @checked(old('show_positions', $showPositions)) class="mt-1 size-4 rounded border-line">
                    <span>
                        <span class="block text-sm font-semibold">Show class positions on report cards</span>
                        <span class="block text-sm text-muted">Turn off if you prefer not to rank students publicly.</span>
                    </span>
                </label>

                <x-field
                    label="Promotion pass mark (%)"
                    name="promotion_pass_mark"
                    type="number"
                    min="0"
                    max="100"
                    step="0.5"
                    :value="old('promotion_pass_mark', $promotionPassMark)"
                    hint="Students within 5 marks below this can be promoted on probation."
                    required
                />
            </x-card>

            <x-card class="flex flex-col gap-4">
                <h2 class="m-0 text-lg font-semibold">Payments</h2>
                <p class="m-0 text-sm text-muted">Optional Paystack subaccount so online fee payments settle into the school’s own Paystack account.</p>
                <x-field
                    label="Paystack subaccount code"
                    name="paystack_subaccount"
                    :value="old('paystack_subaccount', $paystackSubaccount)"
                    placeholder="ACCT_xxxxxxxx"
                    hint="Leave blank to use the platform Paystack account."
                />
                <p class="m-0 text-sm text-muted">School profile details (name, logo, address) are under <a href="{{ route('school.profile.edit') }}" class="font-semibold text-brand-600 hover:text-ink">School information</a>.</p>
            </x-card>
        </div>

        <x-card class="flex flex-col gap-4">
            <div>
                <h2 class="m-0 text-lg font-semibold">Document numbers</h2>
                <p class="m-0 mt-1 text-sm text-muted">Tokens: <code class="rounded bg-chip px-1.5 py-0.5 text-xs">{SCHOOL}</code> <code class="rounded bg-chip px-1.5 py-0.5 text-xs">{YYYY}</code> <code class="rounded bg-chip px-1.5 py-0.5 text-xs">{YY}</code> <code class="rounded bg-chip px-1.5 py-0.5 text-xs">{SEQ:4}</code>. Changing a pattern does not reset the counter.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="py-2 pr-3">Document</th>
                            <th scope="col" class="py-2 pr-3">Pattern</th>
                            <th scope="col" class="py-2">Next number</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($numbering as $row)
                            <tr class="border-t border-line align-top">
                                <td class="py-3 pr-3">
                                    <span class="font-semibold">{{ $row['label'] }}</span>
                                    <span class="mt-0.5 block font-mono text-xs text-muted">{{ $row['key'] }} · next #{{ $row['next_value'] }}</span>
                                </td>
                                <td class="py-3 pr-3">
                                    <input
                                        type="text"
                                        name="numbering[{{ $row['key'] }}]"
                                        value="{{ old('numbering.'.$row['key'], $row['pattern']) }}"
                                        class="field font-mono text-sm @error('numbering.'.$row['key']) field-error @enderror"
                                        required
                                    >
                                    @error('numbering.'.$row['key'])
                                        <p class="m-0 mt-1 text-[13px] font-medium text-danger">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="py-3 font-mono text-sm text-muted">{{ $row['preview'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div>
            <x-button variant="brand" size="lg" icon="check">Save settings</x-button>
        </div>
    </form>
</x-layouts.app>
