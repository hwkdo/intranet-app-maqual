@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

@php
    $navItems = !empty($navItems) ? $navItems : \Hwkdo\IntranetAppMaqual\Support\MaqualNavigation::items();
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('maqual');
@endphp

@if($customBgUrl)
    @push('app-styles')
    <style data-app-bg data-ts="{{ uniqid() }}">
        :root { --app-bg-image: url('{{ $customBgUrl }}'); }
    </style>
    @endpush
@endif

<x-intranet-app-base::app-layout
    app-identifier="maqual"
    :heading="$heading"
    :subheading="$subheading"
    :nav-items="$navItems"
    :wrap-in-card="true"
>
    {{ $slot }}
</x-intranet-app-base::app-layout>
