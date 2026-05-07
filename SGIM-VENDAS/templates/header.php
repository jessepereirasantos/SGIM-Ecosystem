<?php
/**
 * SGIM MASTER - GLOBAL HEADER (Obsidian Amber v7.6 - SOURCE OF TRUTH)
 */
require_once 'config/database.php';
require_once 'includes/ota_helper.php';
$current_page = $current_page ?? '';
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>SGIM Vendas - Enterprise Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                "tertiary-fixed-dim": "#7cd0ff", "inverse-on-surface": "#313030", "on-tertiary-container": "#004d6a",
                "error": "#ffb4ab", "on-surface": "#e5e2e1", "on-tertiary-fixed-variant": "#004c69",
                "on-secondary-fixed-variant": "#5a4300", "tertiary-container": "#3ac2ff", "on-surface-variant": "#d7c3ae",
                "surface-container-lowest": "#0e0e0e", "on-error": "#690005", "surface-bright": "#393939",
                "surface-container-high": "#2a2a2a", "on-secondary-fixed": "#251a00", "surface-container-low": "#1c1b1b",
                "tertiary": "#9bd9ff", "outline-variant": "#524534", "outline": "#9f8e7a", "inverse-surface": "#e5e2e1",
                "on-tertiary-fixed": "#001e2c", "primary-fixed-dim": "#ffb955", "surface-variant": "#353534",
                "tertiary-fixed": "#c4e7ff", "on-secondary": "#3f2e00", "on-background": "#e5e2e1",
                "on-primary-container": "#644000", "error-container": "#93000a", "on-error-container": "#ffdad6",
                "surface": "#131313", "primary-fixed": "#ffddb4", "surface-container-highest": "#353534",
                "on-tertiary": "#00344a", "on-secondary-container": "#423000", "secondary-fixed-dim": "#f2bf3a",
                "inverse-primary": "#835500", "secondary-fixed": "#ffdf99", "surface-tint": "#ffb955",
                "surface-container": "#201f1f", "on-primary-fixed": "#291800", "secondary-container": "#c29400",
                "on-primary-fixed-variant": "#633f00", "primary-container": "#f5a623", "surface-dim": "#131313",
                "background": "#131313", "primary": "#ffc880", "on-primary": "#452b00", "secondary": "#f2bf3a"
            },
            "borderRadius": { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px" },
            "spacing": { "unit": "8px", "container-padding": "2rem", "card-gap": "1rem", "section-margin": "3rem", "gutter": "1.5rem" },
            "fontSize": {
                "body-md": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
                "title-sm": ["18px", {"lineHeight": "1.5", "fontWeight": "600"}],
                "display-lg": ["48px", {"lineHeight": "1.1", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                "body-sm": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}],
                "headline-md": ["24px", {"lineHeight": "1.3", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                "label-caps": ["12px", {"lineHeight": "1.0", "letterSpacing": "0.05em", "fontWeight": "700"}]
            }
          },
        },
      }
    </script>
    <style>
      .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24; }
      .glass-card { background: rgba(26, 26, 26, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
      .sidebar-item-active { background: linear-gradient(90deg, rgba(245, 166, 35, 0.1) 0%, rgba(245, 166, 35, 0) 100%); }
    </style>
</head>
<body class="bg-background text-on-surface font-body-md overflow-x-hidden">

<aside class="fixed left-0 top-0 h-screen w-[280px] bg-surface border-r border-outline-variant/20 flex flex-col py-8 z-50">
    <div class="px-6 mb-12">
        <h1 class="font-headline-md text-headline-md font-bold text-primary tracking-tight">SGIM Vendas</h1>
        <p class="text-on-surface-variant font-body-sm opacity-60">Enterprise Dashboard</p>
    </div>
    <nav class="flex-1 space-y-2 overflow-y-auto px-4">
        <div class="mb-6">
            <span class="px-4 text-label-caps font-label-caps text-on-surface-variant/50 block mb-2 uppercase">Visão de Negócio</span>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg <?= ($current_page == 'dashboard') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?> transition-all" href="dashboard.php">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="text-body-md font-body-md">Panorama Geral</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg <?= ($current_page == 'clientes') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?> transition-all" href="clientes.php">
                <span class="material-symbols-outlined">group</span>
                <span class="text-body-md font-body-md">Gestão de Clientes</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg <?= ($current_page == 'pedidos') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?> transition-all" href="pedidos.php">
                <span class="material-symbols-outlined">history</span>
                <span class="text-body-md font-body-md">Histórico de Vendas</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg <?= ($current_page == 'licencas') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?> transition-all" href="licencas.php">
                <span class="material-symbols-outlined">vpn_key</span>
                <span class="text-body-md font-body-md">Licenças & Ativação</span>
            </a>
        </div>
        <div class="mb-6">
            <span class="px-4 text-label-caps font-label-caps text-on-surface-variant/50 block mb-2 uppercase">Financeiro</span>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg <?= ($current_page == 'cupons') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?> transition-all" href="cupons.php">
                <span class="material-symbols-outlined">confirmation_number</span>
                <span class="text-body-md font-body-md">Cupons & Descontos</span>
            </a>
        </div>
        <div>
            <span class="px-4 text-label-caps font-label-caps text-on-surface-variant/50 block mb-2 uppercase">Infraestrutura</span>
            <a class="flex items-center gap-3 px-4 py-3 rounded-lg <?= ($current_page == 'publish') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?> transition-all" href="publish_master.php">
                <span class="material-symbols-outlined">dns</span>
                <span class="text-body-md font-body-md">Publicador OTA</span>
            </a>
        </div>
    </nav>
    <div class="px-4 mt-auto pt-8 border-t border-outline-variant/10">
        <div class="bg-primary-container/10 p-4 rounded-xl mb-6">
            <p class="text-body-sm text-primary font-bold mb-1">Upgrade Plan</p>
            <p class="text-xs text-on-surface-variant opacity-70">Desbloqueie relatórios de IA avançados.</p>
        </div>
        <a class="flex items-center gap-3 px-4 py-2 rounded-lg text-on-surface-variant opacity-70 hover:text-error transition-all" href="logout.php">
            <span class="material-symbols-outlined">logout</span>
            <span class="text-body-sm font-body-sm">Sair</span>
        </a>
    </div>
</aside>

<main class="ml-[280px] min-h-screen">
    <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
        <div class="flex items-center gap-4 bg-surface-container-low px-4 py-2 rounded-lg w-full max-w-md border border-outline-variant/20">
            <span class="material-symbols-outlined text-on-surface-variant">search</span>
            <input class="bg-transparent border-none focus:ring-0 text-body-sm w-full placeholder:text-on-surface-variant/40" placeholder="Buscar clientes ou licenças..." type="text"/>
        </div>
        <div class="flex items-center gap-6">
            <div class="text-right">
                <p class="text-body-sm font-bold text-on-surface">Admin SGIM</p>
                <p class="text-[10px] uppercase tracking-widest text-primary font-bold">v<?= get_system_version() ?></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-primary/20 flex items-center justify-center text-primary font-black">A</div>
        </div>
    </header>
    <div class="p-10 max-w-[1600px] mx-auto">
