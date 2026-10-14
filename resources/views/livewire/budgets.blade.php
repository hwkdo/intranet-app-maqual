<div>
    <x-intranet-app-maqual::maqual-layout heading="Budgets" subheading="Geschäftsbereich">
        <div class="mb-4 max-w-xs">
            <flux:select wire:model.live="kostenjahr">
                @foreach ($jahre as $jahr)
                    <flux:select.option value="{{ $jahr }}">{{ $jahr }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="space-y-6">
            @foreach ($bloecke as $block)
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ $block['gvp']->kuerzel }} {{ $block['gvp']->nummer }} {{ $block['gvp']->name }}</flux:heading>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <flux:text class="text-zinc-500">Budget</flux:text>
                            <flux:heading>{{ $block['zahlen']['budget'] === null ? 'nicht festgelegt' : number_format((float) $block['zahlen']['budget'], 2, ',', '.').' €' }}</flux:heading>
                        </div>
                        <div>
                            <flux:text class="text-zinc-500">Voraussichtlich / Differenz</flux:text>
                            <flux:heading>{{ number_format((float) $block['zahlen']['voraussichtlich'], 2, ',', '.') }} €</flux:heading>
                            <flux:text>{{ $block['zahlen']['differenz_voraussichtlich'] === null ? '' : number_format((float) $block['zahlen']['differenz_voraussichtlich'], 2, ',', '.').' € zum Budget' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-zinc-500">Tatsächlich / Differenz</flux:text>
                            <flux:heading>{{ number_format((float) $block['zahlen']['tatsaechlich'], 2, ',', '.') }} €</flux:heading>
                            <flux:text>{{ $block['zahlen']['differenz_tatsaechlich'] === null ? '' : number_format((float) $block['zahlen']['differenz_tatsaechlich'], 2, ',', '.').' € zum Budget' }}</flux:text>
                        </div>
                    </div>
                    @include('intranet-app-maqual::livewire.partials.qual-tabelle', ['quals' => $block['zahlen']['quals'], 'zeigeTeilnehmer' => true])
                </flux:card>
            @endforeach
        </div>
    </x-intranet-app-maqual::maqual-layout>
</div>
