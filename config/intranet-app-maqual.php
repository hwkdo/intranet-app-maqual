<?php

declare(strict_types=1);

use App\Models\Gvp;
use App\Models\User;

return [
    'user_model' => User::class,
    'gvp_model' => Gvp::class,

    /*
     * Leer lassen, bis eine Legacy-Datenbank angebunden ist.
     * Der Import-Befehl bricht ohne Connection ab.
     */
    'legacy_connection' => null,
    'legacy_files_path' => null,

    'roles' => [
        'admin' => [
            'name' => 'App-Maqual-Admin',
            'permissions' => [
                'see-app-maqual',
                'manage-app-maqual',
            ],
        ],
        'user' => [
            'name' => 'App-Maqual-Benutzer',
            'permissions' => [
                'see-app-maqual',
            ],
            'all_users' => true,
        ],
    ],

    'noten' => [
        1 => 'sehr gut',
        2 => 'gut',
        3 => 'befriedigend',
        4 => 'ausreichend',
        5 => 'mangelhaft',
        6 => 'ungenügend',
    ],

    'bewertung_fragen' => [
        ['key' => 'eins', 'label' => 'Wurden Ihre Erwartungen an das Thema erfüllt?', 'typ' => 'note'],
        ['key' => 'zwei', 'label' => 'Wie beurteilen Sie das Tempo des Vorgehens?', 'typ' => 'note'],
        ['key' => 'drei', 'label' => 'Wie beurteilen Sie den Umfang des Seminars?', 'typ' => 'note'],
        ['key' => 'vier', 'label' => 'Wie war die Seminargestaltung/Methodik?', 'typ' => 'note'],
        ['key' => 'fuenf', 'label' => 'Wie beurteilen Sie den Einsatz der verschiedenen Medien?', 'typ' => 'note'],
        ['key' => 'sechs', 'label' => 'Wie beurteilen Sie die fachliche Kompetenz des Trainers?', 'typ' => 'note'],
        ['key' => 'sieben', 'label' => 'Wie war die Rhetorik des Trainers?', 'typ' => 'note'],
        ['key' => 'acht', 'label' => 'Wie war das Auftreten des Trainers?', 'typ' => 'note'],
        ['key' => 'neun', 'label' => 'Wie war der Umgang mit den Teilnehmern?', 'typ' => 'note'],
        ['key' => 'zehn', 'label' => 'Wurde auf die Anliegen der Teilnehmer eingegangen?', 'typ' => 'note'],
        ['key' => 'elf', 'label' => 'Wurden Sie zu aktiver Mitarbeit angeregt?', 'typ' => 'note'],
        ['key' => 'zwoelf', 'label' => 'Wie beurteilen Sie die Seminarunterlagen?', 'typ' => 'note'],
        ['key' => 'dreizehn', 'label' => 'Wie war die Seminaratmosphäre?', 'typ' => 'note'],
        ['key' => 'vierzehn', 'label' => 'Wie beurteilen Sie die Gesamtveranstaltung?', 'typ' => 'note'],
        ['key' => 'fuenfzehn', 'label' => 'Anregungen / Kritik:', 'typ' => 'text'],
        ['key' => 'sechzehn', 'label' => 'Ich kann die Teilnahme am Seminar weiterempfehlen:', 'typ' => 'bool'],
    ],

    'eval_fragen' => [
        ['key' => 'eins', 'label' => 'Wie beurteilen Sie die Maßnahme in einer Nachbetrachtung', 'typ' => 'note'],
        ['key' => 'zwei', 'label' => 'Inwiefern konnten erworbene Inhalte gewinnbringend eingesetzt werden?', 'typ' => 'note'],
        ['key' => 'drei', 'label' => 'Sind Folgemaßnahmen erforderlich?', 'typ' => 'bool'],
        ['key' => 'vier', 'label' => 'Ich kann die Teilnahme am Seminar weiterempfehlen:', 'typ' => 'bool'],
    ],
];
