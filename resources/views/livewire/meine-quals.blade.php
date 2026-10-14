<div>
    <x-intranet-app-maqual::maqual-layout heading="Meine Quals" subheading="Ihre Qualifizierungen">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="suche" placeholder="Name der Qualifizierung" icon="magnifying-glass" class="min-w-56 flex-1" />
            <flux:select wire:model.live="filter">
                @foreach ($this->filterOptionen() as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($darfAnlegen)
                <flux:button variant="primary" icon="plus" :href="route('apps.maqual.anlegen')" wire:navigate>Qualifizierung anlegen</flux:button>
            @endif
        </div>

        @include('intranet-app-maqual::livewire.partials.qual-tabelle', ['quals' => $quals, 'zeigeTeilnehmer' => false])
    </x-intranet-app-maqual::maqual-layout>
</div>
