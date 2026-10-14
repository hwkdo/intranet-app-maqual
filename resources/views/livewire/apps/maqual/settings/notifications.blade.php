<?php

use function Livewire\Volt\title;

title('Benachrichtigungen - Maqual');

?>

<div>
    <x-intranet-app-maqual::maqual-layout heading="Benachrichtigungen" subheading="Benachrichtigungseinstellungen für die Maqual-App">
        @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'maqual'])
    </x-intranet-app-maqual::maqual-layout>
</div>
