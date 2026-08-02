<nav class="page-submenu">
    <a href="{{ route('system-settings.edit') }}"
       class="page-submenu-item page-submenu-nav {{ request()->routeIs('system-settings.edit') ? 'active' : '' }}">
        {{ __('General settings') }}
    </a>
    <a href="{{ route('identity-providers.index') }}"
       class="page-submenu-item page-submenu-nav {{ request()->routeIs('identity-providers.*') ? 'active' : '' }}">
        {{ __('Identity providers') }}
    </a>
    {{-- Onglet « Environment (.env) » retiré (La Pépite) : édition bas niveau du
         .env inutile pour l'équipe, gérée par l'hébergeur/dev. --}}
</nav>
