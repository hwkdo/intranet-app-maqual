<flux:card>
    @if ($quals->isEmpty())
        <flux:text class="text-zinc-500">Keine Qualifizierungen gefunden.</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                @if ($zeigeTeilnehmer)
                    <flux:table.column>Teilnehmer</flux:table.column>
                @endif
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Kostenjahr</flux:table.column>
                <flux:table.column class="text-right">Voraussichtlich</flux:table.column>
                @if ($zeigeKostenabweichung ?? false)
                    <flux:table.column align="end">Tatsächlich</flux:table.column>
                    <flux:table.column align="end">Differenz</flux:table.column>
                @endif
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($quals as $qual)
                    <flux:table.row :key="$qual->id" wire:navigate :href="route('apps.maqual.quals.show', $qual)">
                        <flux:table.cell>{{ $qual->name }}</flux:table.cell>
                        @if ($zeigeTeilnehmer)
                            <flux:table.cell>{{ $qual->teilnehmer?->name }}</flux:table.cell>
                        @endif
                        <flux:table.cell>
                            <flux:badge :color="$qual->status->color()" size="sm">{{ $qual->workflowStand() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $qual->kostenjahr }}</flux:table.cell>
                        <flux:table.cell class="text-right">{{ $qual->voraussichtlicheKostenAnzeige() }}</flux:table.cell>
                        @if ($zeigeKostenabweichung ?? false)
                            <flux:table.cell align="end">{{ $qual->tatsaechlicheKostenAnzeige() }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <span @class([
                                    'text-green-600 dark:text-green-400' => $qual->kostenDifferenzFarbe() === 'positiv',
                                    'text-red-600 dark:text-red-400' => $qual->kostenDifferenzFarbe() === 'negativ',
                                    'text-zinc-500' => $qual->kostenDifferenzFarbe() === 'neutral',
                                ])>{{ $qual->kostenDifferenzAnzeige() ?? '–' }}</span>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        @if ($quals instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="mt-4">{{ $quals->links() }}</div>
        @endif
    @endif
</flux:card>
