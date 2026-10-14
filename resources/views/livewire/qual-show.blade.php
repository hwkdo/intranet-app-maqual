<div>
    <x-intranet-app-maqual::maqual-layout :heading="$qual->name" :subheading="$qual->teilnehmer?->name">
        <div class="space-y-6">
            <flux:error name="aktion" />

            <flux:card class="space-y-3">
                <flux:callout icon="clock">
                    <flux:heading size="lg">{{ $qual->workflowStand() }}</flux:heading>
                </flux:callout>
                <div class="flex flex-wrap items-center gap-3">
                    <flux:badge color="zinc">{{ $qual->qualtyp->label() }}</flux:badge>
                    <flux:badge color="zinc">{{ $qual->quelle->label() }}</flux:badge>
                    @if ($qual->pflicht)
                        <flux:badge color="amber">Pflicht</flux:badge>
                    @endif
                </div>
                <flux:text>{{ $qual->beschreibung }}</flux:text>
                <flux:text><strong>Begründung:</strong> {{ $qual->begruendung }}</flux:text>
                <div class="grid gap-2 md:grid-cols-3">
                    <flux:text>Kostenjahr {{ $qual->kostenjahr }}</flux:text>
                    <flux:text>Beantragte Kosten {{ $qual->voraussichtlicheKostenAnzeige() }}</flux:text>
                    <flux:text>Tatsächlich {{ $qual->kosten_tatsaechlich === null ? '–' : number_format((float) $qual->kosten_tatsaechlich, 2, ',', '.').' €' }}</flux:text>
                    <flux:text>Beginn {{ $qual->datum_beginn?->format('d.m.Y') ?? 'offen' }}</flux:text>
                    <flux:text>{{ $qual->freigabeText('al') }}</flux:text>
                    <flux:text>{{ $qual->freigabeText('gf') }}</flux:text>
                </div>
                @if ($qual->entscheidung_begruendung)
                    <flux:callout>{{ $qual->entscheidung_begruendung }}</flux:callout>
                @endif
                <div class="flex flex-wrap gap-3">
                    @if ($qual->getFirstMedia('anmeldebestatigung'))
                        <flux:button size="sm" icon="document" :href="route('apps.maqual.quals.datei', [$qual, 'anmeldebestatigung'])">Anmeldebestätigung</flux:button>
                    @endif
                    @if ($qual->getFirstMedia('zertifikat'))
                        <flux:button size="sm" icon="document" :href="route('apps.maqual.quals.datei', [$qual, 'zertifikat'])">Zertifikat</flux:button>
                    @endif
                </div>
            </flux:card>

            @if ($policy->alsGf($user, $qual))
                <flux:card class="space-y-4">
                    <flux:heading size="lg">Ihre Entscheidung</flux:heading>
                    <flux:text>
                        Beantragt sind {{ $qual->voraussichtlicheKostenAnzeige() }}.
                        Wählen Sie eine Möglichkeit: annehmen, mit geänderten Kosten annehmen oder ablehnen.
                    </flux:text>
                    <flux:radio.group wire:model.live="gfEntscheidung" variant="cards" class="flex-col">
                        <flux:radio value="annehmen" label="Annehmen" description="Die beantragten Kosten bleiben. Danach prüft die Personalabteilung das Budget." />
                        <flux:radio value="aendern" label="Mit geänderten Kosten annehmen" description="Sie legen einen anderen Betrag fest und begründen die Änderung. Danach prüft die Personalabteilung das Budget." />
                        <flux:radio value="ablehnen" label="Ablehnen" description="Die Qualifizierung endet. Eine Begründung ist erforderlich." />
                    </flux:radio.group>

                    @if ($gfEntscheidung === 'aendern')
                        <flux:field>
                            <flux:label>Neuer Betrag (+ MwSt.)</flux:label>
                            <flux:description>Statt {{ $qual->voraussichtlicheKostenAnzeige() }}</flux:description>
                            <flux:input wire:model="neueKosten" inputmode="decimal" placeholder="0,00" />
                            <flux:error name="neueKosten" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Begründung der Kostenänderung</flux:label>
                            <flux:textarea wire:model="kostenGrund" />
                            <flux:error name="kostenGrund" />
                        </flux:field>
                        <flux:button variant="primary" wire:click="gfEntscheidungSpeichern">Mit geänderten Kosten annehmen</flux:button>
                    @elseif ($gfEntscheidung === 'ablehnen')
                        <flux:field>
                            <flux:label>Begründung der Ablehnung</flux:label>
                            <flux:textarea wire:model="ablehnung" />
                            <flux:error name="ablehnung" />
                        </flux:field>
                        <flux:button variant="danger" wire:click="gfEntscheidungSpeichern">Ablehnen</flux:button>
                    @else
                        <flux:text>Die Kosten bleiben bei {{ $qual->voraussichtlicheKostenAnzeige() }}.</flux:text>
                        <flux:button variant="primary" wire:click="gfEntscheidungSpeichern">Annehmen</flux:button>
                    @endif
                </flux:card>
            @endif

            @if ($policy->alsAl($user, $qual) || $policy->alsPersonal($user, $qual))
                <flux:card class="space-y-3">
                    <flux:heading size="lg">Entscheidung</flux:heading>
                    <flux:field>
                        <flux:label>Begründung bei Ablehnung</flux:label>
                        <flux:textarea wire:model="ablehnung" />
                        <flux:error name="ablehnung" />
                    </flux:field>
                    <div class="flex flex-wrap gap-2">
                        @if ($policy->alsAl($user, $qual))
                            <flux:button variant="primary" wire:click="alZustimmen">Zustimmen</flux:button>
                            <flux:button variant="danger" wire:click="alAblehnen">Ablehnen</flux:button>
                        @endif
                        @if ($policy->alsPersonal($user, $qual))
                            <flux:button variant="primary" wire:click="persoGenehmigen">Genehmigen</flux:button>
                            <flux:button variant="danger" wire:click="persoAblehnen">Ablehnen</flux:button>
                        @endif
                    </div>
                </flux:card>
            @endif

            @if ($policy->anmeldung($user, $qual) || $policy->teilnahme($user, $qual) || $policy->nichtTeilgenommen($user, $qual) || $policy->datumSetzen($user, $qual))
                <flux:card class="space-y-3">
                    <flux:heading size="lg">Teilnahme</flux:heading>
                    @if ($policy->datumSetzen($user, $qual))
                        <flux:field>
                            <flux:label>Beginn</flux:label>
                            <flux:input wire:model="datumBeginn" type="date" />
                            <flux:error name="datumBeginn" />
                        </flux:field>
                        <flux:button wire:click="datumSpeichern">Datum speichern</flux:button>
                    @endif
                    @if ($policy->anmeldung($user, $qual))
                        @include('intranet-app-maqual::livewire.partials.pdf-upload', [
                            'model' => 'anmeldePdf',
                            'label' => 'Anmeldebestätigung (PDF)',
                            'datei' => $anmeldePdf,
                            'entfernen' => 'anmeldePdfEntfernen',
                        ])
                        <flux:button variant="primary" wire:click="anmeldungSpeichern">Anmeldung bestätigen</flux:button>
                    @endif
                    @if ($policy->teilnahme($user, $qual))
                        @include('intranet-app-maqual::livewire.partials.pdf-upload', [
                            'model' => 'zertifikatPdf',
                            'label' => 'Zertifikat oder Teilnahmebestätigung (PDF)',
                            'datei' => $zertifikatPdf,
                            'entfernen' => 'zertifikatPdfEntfernen',
                        ])
                        <flux:field>
                            <flux:label>Tatsächliche Kosten ohne Reise und Hotel</flux:label>
                            <flux:input wire:model="kostenTatsaechlich" type="number" step="0.01" min="0" />
                            <flux:error name="kostenTatsaechlich" />
                        </flux:field>
                        <flux:button variant="primary" wire:click="teilnahmeSpeichern">Teilnahme bestätigen</flux:button>
                    @endif
                    @if ($policy->nichtTeilgenommen($user, $qual))
                        <flux:field>
                            <flux:label>Nicht teilgenommen, Begründung</flux:label>
                            <flux:textarea wire:model="nichtTeilgenommenGrund" />
                            <flux:error name="nichtTeilgenommenGrund" />
                        </flux:field>
                        <flux:button variant="danger" wire:click="nichtTeilgenommen">Nicht teilgenommen</flux:button>
                    @endif
                </flux:card>
            @endif

            @if ($policy->bewerten($user, $qual))
                <flux:card class="space-y-4">
                    <flux:heading size="lg">Bewertung</flux:heading>
                    <flux:error name="fragebogen" />
                    @include('intranet-app-maqual::livewire.partials.fragebogen', ['fragen' => $bewertungFragen, 'feld' => 'bewertung', 'noten' => $noten])
                    <flux:button variant="primary" wire:click="bewertungSpeichern">Bewertung speichern</flux:button>
                </flux:card>
            @endif

            @if ($policy->evaluieren($user, $qual))
                <flux:card class="space-y-4">
                    <flux:heading size="lg">Evaluierung mit der Führungskraft</flux:heading>
                    <flux:text>
                        Teilnehmer {{ $qual->evaluierung?->teilnehmer_bestaetigt_am ? 'hat bestätigt' : 'offen' }},
                        Vorgesetzter {{ $qual->evaluierung?->vorgesetzter_bestaetigt_am ? 'hat bestätigt' : 'offen' }}.
                    </flux:text>
                    @include('intranet-app-maqual::livewire.partials.fragebogen', ['fragen' => $evalFragen, 'feld' => 'evaluierung', 'noten' => $noten])
                    <flux:button variant="primary" wire:click="evaluierungSpeichern">Evaluierung bestätigen</flux:button>
                </flux:card>
            @endif

            @if ($policy->korrigieren($user, $qual))
                <flux:card class="space-y-4">
                    <flux:heading size="lg">Korrektur durch die Personalabteilung</flux:heading>
                    <flux:field>
                        <flux:label>Name</flux:label>
                        <flux:input wire:model="name" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Beschreibung</flux:label>
                        <flux:textarea wire:model="beschreibung" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Begründung</flux:label>
                        <flux:textarea wire:model="begruendung" />
                    </flux:field>
                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:select wire:model="qualtyp">
                            @foreach ($typen as $typ)
                                <flux:select.option value="{{ $typ->value }}">{{ $typ->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="quelle">
                            @foreach ($quellen as $quelleOption)
                                <flux:select.option value="{{ $quelleOption->value }}">{{ $quelleOption->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="kostenjahr" type="number" />
                        @unless ($kostenlos)
                            <flux:input wire:model="kostenVoraussichtlich" type="number" step="0.01" />
                        @endunless
                        <flux:input wire:model="korrekturKostenTatsaechlich" type="number" step="0.01" placeholder="Kosten tatsächlich" />
                        <flux:input wire:model="datumBeginn" type="date" />
                    </div>
                    <flux:checkbox wire:model="pflicht" label="Pflichtqualifizierung" />
                    <flux:checkbox wire:model.live="kostenlos" label="Kostenlos" />
                    <flux:button wire:click="korrekturSpeichern">Änderungen speichern</flux:button>
                    @if (! $qual->status->istBeendet())
                        <flux:button variant="danger" wire:click="abbrechen">Workflow abbrechen</flux:button>
                    @endif
                    @include('intranet-app-maqual::livewire.partials.pdf-upload', [
                        'model' => 'ersatzAnmeldung',
                        'label' => 'Anmeldebestätigung austauschen',
                        'datei' => $ersatzAnmeldung,
                        'entfernen' => 'ersatzAnmeldungEntfernen',
                    ])
                    <flux:button wire:click="anmeldungErsetzen">PDF ersetzen</flux:button>
                    @include('intranet-app-maqual::livewire.partials.pdf-upload', [
                        'model' => 'ersatzZertifikat',
                        'label' => 'Zertifikat austauschen',
                        'datei' => $ersatzZertifikat,
                        'entfernen' => 'ersatzZertifikatEntfernen',
                    ])
                    <flux:button wire:click="zertifikatErsetzen">Zertifikat ersetzen</flux:button>
                </flux:card>
            @endif

            @if ($qual->bewertung)
                <flux:card>
                    <flux:heading size="lg">Gespeicherte Bewertung</flux:heading>
                    @foreach ($bewertungFragen as $frage)
                        <flux:text>{{ $frage['label'] }}: {{ $qual->bewertung->data[$frage['key']] ?? '–' }}</flux:text>
                    @endforeach
                </flux:card>
            @endif

            <flux:card class="space-y-2">
                <flux:heading size="lg">Verlauf</flux:heading>
                @forelse ($qual->aktivitaeten as $eintrag)
                    <div>
                        <flux:text>{{ $eintrag->created_at?->format('d.m.Y H:i') }} · {{ $eintrag->verlaufSatz() }}</flux:text>
                    </div>
                @empty
                    <flux:text class="text-zinc-500">Noch keine Einträge.</flux:text>
                @endforelse
            </flux:card>
        </div>
    </x-intranet-app-maqual::maqual-layout>
</div>
