<div>
    <x-intranet-app-maqual::maqual-layout heading="Personalabteilung" subheading="Alle Qualifizierungen und Budgets">
        <flux:card class="mb-6 space-y-4">
            <flux:heading size="lg">Budget festlegen</flux:heading>
            <div class="grid gap-4 md:grid-cols-4">
                <flux:field>
                    <flux:label>Geschäftsbereich</flux:label>
                    <flux:select wire:model="budgetGvpId" placeholder="GB wählen">
                        @foreach ($geschaeftsbereiche as $gvp)
                            <flux:select.option value="{{ $gvp->id }}">{{ $gvp->kuerzel }} {{ $gvp->nummer }} {{ $gvp->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="budgetGvpId" />
                </flux:field>
                <flux:field>
                    <flux:label>Kostenjahr</flux:label>
                    <flux:input wire:model="budgetJahr" type="number" />
                </flux:field>
                <flux:field>
                    <flux:label>Betrag</flux:label>
                    <flux:input wire:model="budgetBetrag" type="number" step="0.01" min="0" />
                    <flux:error name="budgetBetrag" />
                </flux:field>
                <div class="flex items-end">
                    <flux:button variant="primary" wire:click="budgetSpeichern">Budget speichern</flux:button>
                </div>
            </div>
        </flux:card>

        <div class="mb-4 grid gap-3 md:grid-cols-4">
            <flux:input wire:model.live="kostenjahr" type="number" placeholder="Kostenjahr" />
            <flux:select wire:model.live="status" placeholder="Status">
                <flux:select.option value="">Alle Status</flux:select.option>
                @foreach ($statusOptionen as $option)
                    <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="qualtyp" placeholder="Qualtyp">
                <flux:select.option value="">Alle Typen</flux:select.option>
                @foreach ($typen as $typ)
                    <flux:select.option value="{{ $typ->value }}">{{ $typ->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="gbId" placeholder="Geschäftsbereich">
                <flux:select.option value="">Alle Bereiche</flux:select.option>
                @foreach ($geschaeftsbereiche as $gvp)
                    <flux:select.option value="{{ $gvp->id }}">{{ $gvp->kuerzel }} {{ $gvp->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @include('intranet-app-maqual::livewire.partials.qual-tabelle', ['quals' => $quals, 'zeigeTeilnehmer' => true, 'zeigeKostenabweichung' => true])
    </x-intranet-app-maqual::maqual-layout>
</div>
