<div>
    <x-intranet-app-maqual::maqual-layout :heading="$mitarbeiter->name" subheading="Qualifizierungen">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="filter">
                <flux:select.option value="aktuell">Aktuell</flux:select.option>
                <flux:select.option value="abgeschlossen">Abgeschlossen</flux:select.option>
                <flux:select.option value="alle">Alle</flux:select.option>
            </flux:select>
            @if ($darfAnlegen)
                <flux:button variant="primary" icon="plus" :href="route('apps.maqual.anlegen', ['mitarbeiter' => $mitarbeiter->id])" wire:navigate>Qualifizierung anlegen</flux:button>
            @endif
        </div>
        @include('intranet-app-maqual::livewire.partials.qual-tabelle', ['quals' => $quals, 'zeigeTeilnehmer' => false])
    </x-intranet-app-maqual::maqual-layout>
</div>
