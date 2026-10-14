<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Support;

use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Models\Qual;

class QualFreigabeAdaptiveCard
{
    public const VERB_APPROVE = 'maqual.approve';

    public const VERB_REJECT = 'maqual.reject';

    public const VERB_REFRESH = 'maqual.refresh';

    /**
     * @return array<string, mixed>
     */
    public static function forQual(Qual $qual, ?string $recipientAzureUserIdOrMri = null): array
    {
        $qual->loadMissing(['teilnehmer', 'al', 'gf']);

        $body = [
            [
                'type' => 'TextBlock',
                'size' => 'Medium',
                'weight' => 'Bolder',
                'text' => 'Qualifizierung zur Freigabe',
                'wrap' => true,
            ],
            [
                'type' => 'TextBlock',
                'text' => (string) $qual->name,
                'wrap' => true,
                'spacing' => 'Small',
            ],
            [
                'type' => 'FactSet',
                'facts' => [
                    ['title' => 'Teilnehmer', 'value' => (string) ($qual->teilnehmer?->name ?: '—')],
                    ['title' => 'Qualtyp', 'value' => $qual->qualtyp->label()],
                    ['title' => 'Kostenjahr', 'value' => (string) $qual->kostenjahr],
                    ['title' => 'Beantragte Kosten', 'value' => $qual->voraussichtlicheKostenAnzeige()],
                    ['title' => 'Aktuell', 'value' => $qual->workflowStand()],
                ],
            ],
        ];

        $begruendung = trim((string) $qual->begruendung);

        if ($begruendung !== '') {
            $body[] = [
                'type' => 'TextBlock',
                'weight' => 'Bolder',
                'text' => 'Begründung',
                'spacing' => 'Medium',
            ];
            $body[] = [
                'type' => 'TextBlock',
                'text' => self::truncate($begruendung, 500),
                'wrap' => true,
            ];
        }

        if ($qual->status === QualStatus::WarteAufGf) {
            $body[] = [
                'type' => 'TextBlock',
                'text' => 'Beantragt sind '.$qual->voraussichtlicheKostenAnzeige().'. Wählen Sie eine Möglichkeit: annehmen, mit geänderten Kosten annehmen oder ablehnen.',
                'wrap' => true,
                'spacing' => 'Medium',
            ];
        } else {
            $body[] = [
                'type' => 'Input.Text',
                'id' => 'ablehnenGrund',
                'label' => 'Ablehnungsgrund (Pflicht bei Ablehnung)',
                'isMultiline' => true,
                'placeholder' => 'Grund angeben…',
            ];
        }

        $card = [
            'type' => 'AdaptiveCard',
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'version' => '1.5',
            'body' => $body,
            'actions' => [
                ...($qual->status === QualStatus::WarteAufGf ? self::gfActions($qual) : self::standardActions($qual)),
                [
                    'type' => 'Action.OpenUrl',
                    'title' => 'Im Intranet öffnen',
                    'url' => route('apps.maqual.quals.show', $qual),
                ],
            ],
        ];

        $card['refresh'] = [
            'action' => [
                'type' => 'Action.Execute',
                'title' => 'Aktualisieren',
                'verb' => self::VERB_REFRESH,
                'data' => [
                    'qual_id' => $qual->id,
                ],
            ],
            'userIds' => self::refreshUserIds($recipientAzureUserIdOrMri),
        ];

        return $card;
    }

    /**
     * @return array<string, mixed>
     */
    public static function statusCard(Qual $qual, string $headline, string $detail): array
    {
        $qual->loadMissing(['teilnehmer', 'al', 'gf']);

        return [
            'type' => 'AdaptiveCard',
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'version' => '1.5',
            'body' => [
                [
                    'type' => 'TextBlock',
                    'size' => 'Medium',
                    'weight' => 'Bolder',
                    'text' => $headline,
                    'wrap' => true,
                ],
                [
                    'type' => 'TextBlock',
                    'text' => $detail,
                    'wrap' => true,
                ],
                [
                    'type' => 'FactSet',
                    'facts' => [
                        ['title' => 'Qualifizierung', 'value' => (string) $qual->name],
                        ['title' => 'Teilnehmer', 'value' => (string) ($qual->teilnehmer?->name ?: '—')],
                        ['title' => 'Stand', 'value' => $qual->workflowStand()],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'Action.OpenUrl',
                    'title' => 'Im Intranet öffnen',
                    'url' => route('apps.maqual.quals.show', $qual),
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function standardActions(Qual $qual): array
    {
        return [
            [
                'type' => 'Action.Execute',
                'title' => 'Freigeben',
                'verb' => self::VERB_APPROVE,
                'data' => [
                    'qual_id' => $qual->id,
                ],
                'style' => 'positive',
            ],
            [
                'type' => 'Action.Execute',
                'title' => 'Ablehnen',
                'verb' => self::VERB_REJECT,
                'data' => [
                    'qual_id' => $qual->id,
                ],
                'style' => 'destructive',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function gfActions(Qual $qual): array
    {
        $kosten = $qual->voraussichtlicheKostenAnzeige();

        return [
            self::showCard('Annehmen', 'positive', [
                [
                    'type' => 'TextBlock',
                    'text' => 'Die beantragten Kosten bleiben bei '.$kosten.'. Danach prüft die Personalabteilung das Budget.',
                    'wrap' => true,
                ],
            ], [
                'type' => 'Action.Execute',
                'title' => 'Annehmen',
                'verb' => self::VERB_APPROVE,
                'style' => 'positive',
                'data' => [
                    'qual_id' => $qual->id,
                    'entscheidung' => 'annehmen',
                ],
            ]),
            self::showCard('Mit geänderten Kosten annehmen', 'default', [
                [
                    'type' => 'TextBlock',
                    'text' => 'Statt '.$kosten.'. Tragen Sie den Betrag ein, den Sie genehmigen, und begründen Sie die Änderung.',
                    'wrap' => true,
                ],
                [
                    'type' => 'Input.Text',
                    'id' => 'neueKosten',
                    'label' => 'Neuer Betrag (+ MwSt.)',
                    'placeholder' => '0,00',
                ],
                [
                    'type' => 'Input.Text',
                    'id' => 'kostenGrund',
                    'label' => 'Begründung der Kostenänderung',
                    'isMultiline' => true,
                ],
            ], [
                'type' => 'Action.Execute',
                'title' => 'Mit geänderten Kosten annehmen',
                'verb' => self::VERB_APPROVE,
                'style' => 'positive',
                'data' => [
                    'qual_id' => $qual->id,
                    'entscheidung' => 'aendern',
                ],
            ]),
            self::showCard('Ablehnen', 'destructive', [
                [
                    'type' => 'TextBlock',
                    'text' => 'Die Qualifizierung endet. Eine Begründung ist erforderlich.',
                    'wrap' => true,
                ],
                [
                    'type' => 'Input.Text',
                    'id' => 'ablehnenGrund',
                    'label' => 'Begründung der Ablehnung',
                    'isMultiline' => true,
                ],
            ], [
                'type' => 'Action.Execute',
                'title' => 'Ablehnen',
                'verb' => self::VERB_REJECT,
                'style' => 'destructive',
                'data' => [
                    'qual_id' => $qual->id,
                    'entscheidung' => 'ablehnen',
                ],
            ]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $body
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    private static function showCard(string $title, string $style, array $body, array $action): array
    {
        return [
            'type' => 'Action.ShowCard',
            'title' => $title,
            'style' => $style,
            'card' => [
                'type' => 'AdaptiveCard',
                'body' => $body,
                'actions' => [$action],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function refreshUserIds(?string $recipientAzureUserIdOrMri): array
    {
        if (! is_string($recipientAzureUserIdOrMri) || trim($recipientAzureUserIdOrMri) === '') {
            return [];
        }

        $value = trim($recipientAzureUserIdOrMri);

        if (str_starts_with($value, '29:')) {
            return [$value];
        }

        if (preg_match('/^[0-9a-f-]{36}$/i', $value) === 1) {
            return [strtolower($value)];
        }

        return [];
    }

    private static function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }
}
