<?php
function getColorScheme($role) {
    switch ($role) {
        case 'pembeli':
            return [
                'navbar' => 'navbar-light bg-light-green',
                'sidebar' => 'sb-sidenav-light bg-light-green',
                'footer' => 'bg-light-green text-dark',
                'text' => 'text-dark'
            ];
        case 'petani':
            return [
                'navbar' => 'navbar-light bg-light-brown',
                'sidebar' => 'sb-sidenav-light bg-light-brown',
                'footer' => 'bg-light-brown text-dark',
                'text' => 'text-dark'
            ];
        case 'admin':
        default:
            return [
                'navbar' => 'navbar-dark bg-dark',
                'sidebar' => 'sb-sidenav-dark',
                'footer' => 'bg-dark text-light',
                'text' => 'text-light'
            ];
    }
}