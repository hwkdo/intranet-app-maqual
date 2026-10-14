<div>
    <x-intranet-app-maqual::maqual-layout heading="Meine Mitarbeiter" subheading="Qualifizierungen im eigenen Bereich">
        <flux:card>
            @if ($mitarbeiter->isEmpty())
                <flux:text class="text-zinc-500">Keine Mitarbeiter gefunden.</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Mitarbeiter</flux:table.column>
                        <flux:table.column>Qualifizierungen</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($mitarbeiter as $person)
                            <flux:table.row :key="$person->id">
                                <flux:table.cell>{{ $person->name }}</flux:table.cell>
                                <flux:table.cell>{{ $anzahlen[$person->id] ?? 0 }}</flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:button size="sm" :href="route('apps.maqual.mitarbeiter.quals', $person)" wire:navigate>Quals</flux:button>
                                    <flux:button size="sm" variant="primary" :href="route('apps.maqual.anlegen', ['mitarbeiter' => $person->id])" wire:navigate>Anlegen</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </x-intranet-app-maqual::maqual-layout>
</div>
