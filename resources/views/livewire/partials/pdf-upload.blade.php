<flux:file-upload wire:model="{{ $model }}" label="{{ $label }}" accept="application/pdf,.pdf">
    <flux:file-upload.dropzone
        heading="PDF hierher ziehen oder klicken"
        text="Nur PDF, höchstens 10 MB"
        with-progress
    />
</flux:file-upload>

@if ($datei)
    <div class="mt-3">
        <flux:file-item
            :heading="$datei->getClientOriginalName()"
            :size="$datei->getSize()"
            icon="document"
        >
            <x-slot name="actions">
                <flux:file-item.remove wire:click="{{ $entfernen }}" aria-label="PDF entfernen" />
            </x-slot>
        </flux:file-item>
    </div>
@endif
