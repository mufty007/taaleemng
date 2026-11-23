<x-layouts.app :title="$label">
    <x-card class="mx-auto mt-8 w-full max-w-lg">
        <x-empty-state icon="lock" :title="$label.' isn’t switched on'" :body="$label.' isn’t part of '.$school->name.'’s plan yet. Ask Ta’aleem to switch it on, or to move to a plan that includes it.'">
            <x-button variant="secondary" :href="route('school.dashboard')" icon="arrow-left">Back to the dashboard</x-button>
        </x-empty-state>
    </x-card>
</x-layouts.app>
