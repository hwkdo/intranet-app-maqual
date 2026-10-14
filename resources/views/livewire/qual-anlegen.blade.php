<div>
    <x-intranet-app-maqual::maqual-layout heading="Qualifizierung anlegen" subheading="Antrag für einen Mitarbeiter">
        <flux:card class="max-w-3xl space-y-4">
            <flux:error name="anlegen" />

            <flux:field>
                <flux:label>Mitarbeiter</flux:label>
                <flux:select wire:model.live="mitarbeiterId" variant="listbox" searchable placeholder="Mitarbeiter wählen" empty="Kein Mitarbeiter gefunden">
                    @foreach ($mitarbeiter as $person)
                        <flux:select.option value="{{ $person->id }}" :keywords="$person->username">{{ $person->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="mitarbeiterId" />
            </flux:field>

            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Beschreibung</flux:label>
                <flux:textarea wire:model="beschreibung" placeholder="Worum geht es in der Qualifizierung?" />
                <flux:error name="beschreibung" />
            </flux:field>

            <flux:field>
                <flux:label>Begründung</flux:label>
                <flux:textarea wire:model="begruendung" placeholder="Warum wird die Qualifizierung beantragt?" />
                <flux:error name="begruendung" />
            </flux:field>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Qualtyp</flux:label>
                    <flux:select wire:model="qualtyp">
                        @foreach ($typen as $typ)
                            <flux:select.option value="{{ $typ->value }}">{{ $typ->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Kostenjahr</flux:label>
                    <flux:select wire:model="kostenjahr">
                        @foreach ($jahre as $jahr)
                            <flux:select.option value="{{ $jahr }}">{{ $jahr }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="kostenjahr" />
                </flux:field>
            </div>

            <flux:checkbox wire:model.live="kostenlos" label="Kostenlos" description="Die Qualifizierung verursacht keine Kosten." />

            @unless ($kostenlos)
                <flux:field>
                    <flux:label>Kosten voraussichtlich (+ MwSt.)</flux:label>
                    <flux:input wire:model="kostenVoraussichtlich" type="number" step="0.01" min="0" />
                    <flux:error name="kostenVoraussichtlich" />
                </flux:field>
            @endunless

            <flux:field>
                <flux:label>Beginn</flux:label>
                <flux:input wire:model="datumBeginn" type="date" />
                <flux:description>Kann leer bleiben, wenn der Termin noch nicht feststeht.</flux:description>
                <flux:error name="datumBeginn" />
            </flux:field>

            <flux:checkbox wire:model="pflicht" label="Pflichtqualifizierung" />

            @if ($stellvertretungen !== [])
                <flux:checkbox.group wire:model="stellvertretend" label="Stellvertretung" description="Die Zustimmung wird unter Ihrem Namen gespeichert, nicht unter dem Namen der eigentlichen Leitung.">
                    @foreach ($stellvertretungen as $option)
                        <flux:checkbox value="{{ $option['stufe'] }}" label="{{ $option['label'] }}" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <flux:button variant="primary" wire:click="save">Anlegen</flux:button>
        </flux:card>
    </x-intranet-app-maqual::maqual-layout>
</div>
