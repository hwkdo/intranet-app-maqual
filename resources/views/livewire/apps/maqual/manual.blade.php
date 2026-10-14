<?php

use function Livewire\Volt\{title};

title('Maqual - Bedienungsanleitung');

?>

<div>
    <x-intranet-app-maqual::maqual-layout heading="Bedienungsanleitung" subheading="Schritt-für-Schritt-Anleitung zur App">
        @livewire('intranet-app-base::manual-show', ['appIdentifier' => 'maqual'])
    </x-intranet-app-maqual::maqual-layout>
</div>
