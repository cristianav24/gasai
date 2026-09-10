{{-- Capa de estilo global de GasAI: pule todo el panel de forma consistente. --}}
<style>
    /* Títulos más definidos */
    .fi-header-heading { font-weight: 800; letter-spacing: -.015em; }

    /* Radios consistentes en tarjetas, secciones, tablas y modales */
    .fi-section,
    .fi-wi-stats-overview-stat,
    .fi-ta,
    .fi-modal-window { border-radius: 1rem; }

    /* Inputs y botones un poco más redondeados y con transición suave */
    .fi-input-wrp { border-radius: .7rem; }
    .fi-btn { border-radius: .65rem; font-weight: 600; }
    .fi-btn, .fi-sidebar-item-button, .fi-input-wrp { transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease; }

    /* Sidebar: el item activo con un acento de marca claro */
    .fi-sidebar-item-active > .fi-sidebar-item-button {
        background: rgba(245, 158, 11, .12);
        box-shadow: inset 3px 0 0 #f59e0b;
    }
    .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-label { font-weight: 700; }

    /* Grupos del menú: etiqueta más sobria */
    .fi-sidebar-group-label { text-transform: uppercase; letter-spacing: .06em; font-size: .68rem; opacity: .7; }

    /* Badges y estados con un poco más de aire */
    .fi-badge { border-radius: 999px; font-weight: 700; }
</style>
