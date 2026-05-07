<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= $page_title ?? 'SGIM - Vendas Central' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#FFC107',
                        darkbg: '#0F172A',
                        darkcard: '#1E293B',
                        darkborder: '#334155'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>
<body class="bg-darkbg text-gray-100 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-darkcard border-b border-darkborder h-16 flex items-center px-8 shrink-0">
        <div class="flex items-center gap-2">
            <div class="size-8 bg-brand rounded-md flex items-center justify-center text-black font-bold">
                <span class="material-symbols-outlined text-sm">vpn_key</span>
            </div>
            <span class="font-bold text-white text-lg tracking-tight">SGIM <span class="text-brand">Central</span></span>
        </div>
        <nav class="ml-10 flex gap-6">
            <a href="index.php" class="<?= (isset($current_page) && $current_page == 'dashboard') ? 'text-brand border-b-2 border-brand font-semibold' : 'text-gray-400 hover:text-white' ?> text-sm py-5">Dashboard</a>
            <a href="clientes.php" class="<?= (isset($current_page) && $current_page == 'clientes') ? 'text-brand border-b-2 border-brand font-semibold' : 'text-gray-400 hover:text-white' ?> text-sm py-5">Clientes e Licenças</a>
            <a href="pedidos_ativacao.php" class="<?= (isset($current_page) && $current_page == 'pedidos') ? 'text-brand border-b-2 border-brand font-semibold' : 'text-gray-400 hover:text-white' ?> text-sm py-5">Pedidos de Ativação</a>
        </nav>
    </header>
    <main class="flex-1 p-8 max-w-7xl w-full mx-auto">
