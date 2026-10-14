@foreach ($fragen as $frage)
    <flux:field>
        <flux:label>{{ $frage['label'] }}</flux:label>
        @if ($frage['typ'] === 'note')
            <flux:select wire:model="{{ $feld }}.{{ $frage['key'] }}">
                <flux:select.option value="">Bitte wählen</flux:select.option>
                @foreach ($noten as $wert => $label)
                    <flux:select.option value="{{ $wert }}">{{ $wert }} – {{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        @elseif ($frage['typ'] === 'bool')
            <flux:select wire:model="{{ $feld }}.{{ $frage['key'] }}">
                <flux:select.option value="">Bitte wählen</flux:select.option>
                <flux:select.option value="1">Ja</flux:select.option>
                <flux:select.option value="0">Nein</flux:select.option>
            </flux:select>
        @else
            <flux:textarea wire:model="{{ $feld }}.{{ $frage['key'] }}" />
        @endif
        <flux:error name="{{ $feld }}.{{ $frage['key'] }}" />
    </flux:field>
@endforeach
