<?php

use function Livewire\Volt\{title};

title('Maqual - App-Info');

?>

<div>
<x-intranet-app-maqual::maqual-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
    @livewire('intranet-app-base::app-info', ['appIdentifier' => 'maqual'])
</x-intranet-app-maqual::maqual-layout>
</div>
