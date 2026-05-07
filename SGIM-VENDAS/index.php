<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>SGIM - Sistema de Gestão para Igrejas e Ministérios</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#FFD700",
                        "background-dark": "#000000",
                        "charcoal": "#121212",
                        "accent-gold": "#FFD700",
                    },
                    fontFamily: {
                        "display": ["Public Sans", "sans-serif"]
                    }
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Public Sans', sans-serif; }
        .gold-gradient-text {
            background: linear-gradient(to right, #FFD700, #B8860B);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .modal { transition: opacity 0.3s ease; }
    </style>
</head>
<body class="bg-background-dark text-slate-100 font-display scroll-smooth">

<header class="sticky top-0 z-50 bg-background-dark/80 backdrop-blur-md border-b border-white/10">
    <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-8 h-8 text-accent-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m12 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            <span class="text-2xl font-black tracking-tighter text-white">SGIM</span>
        </div>
        <nav class="hidden md:flex items-center gap-10">
            <a class="text-sm font-medium text-slate-300 hover:text-accent-gold transition-colors" href="#funcionalidades">Funcionalidades</a>
            <a class="text-sm font-medium text-slate-300 hover:text-accent-gold transition-colors" href="#precos">Preços</a>
            <a class="text-sm font-medium text-slate-300 hover:text-accent-gold transition-colors" href="#depoimentos">Depoimentos</a>
            <a class="text-sm font-medium text-slate-300 hover:text-accent-gold transition-colors" href="#faq">FAQ</a>
        </nav>
        <div class="flex items-center gap-4">
            <a href="admin.php" class="hidden sm:block text-sm font-bold text-white px-4 hover:text-accent-gold transition-colors">Entrar</a>
            <a href="venda.php" class="bg-accent-gold text-black px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-yellow-400 transition-all shadow-[0_0_15px_rgba(255,215,0,0.3)]">
                Começar Agora
            </a>
        </div>
    </div>
</header>

<main>
    <!-- Hero Section -->
    <section class="relative pt-20 pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div class="flex flex-col gap-8">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 w-fit">
                    <span class="w-2 h-2 rounded-full bg-accent-gold animate-pulse"></span>
                    <span class="text-xs font-bold tracking-widest uppercase text-slate-400">Sistema Premium de Gestão</span>
                </div>
                <h1 class="text-5xl md:text-7xl font-black leading-[1.1] tracking-tight text-white">
                    Gerencie sua Igreja com <span class="gold-gradient-text">Excelência</span>
                </h1>
                <p class="text-lg text-slate-400 leading-relaxed max-w-lg">
                    A plataforma completa para modernizar a gestão da sua comunidade com tecnologia de ponta, segurança absoluta e facilidade de uso inigualável.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="venda.php" class="bg-accent-gold text-black px-8 py-4 rounded-xl font-bold hover:bg-yellow-400 transition-all flex items-center gap-2 group">
                        Começar Agora
                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
            </div>
            <div class="relative group">
                <div class="absolute -inset-1 bg-gradient-to-r from-accent-gold to-yellow-600 rounded-2xl blur opacity-25 group-hover:opacity-40 transition duration-1000"></div>
            <div class="relative group">
                <div class="absolute -inset-1 bg-gradient-to-r from-accent-gold to-yellow-600 rounded-2xl blur opacity-25 group-hover:opacity-40 transition duration-1000"></div>
                <div class="relative bg-charcoal rounded-2xl border border-white/10 overflow-hidden shadow-2xl">
                    <img src="assets/img/sgim_hero.png" alt="SGIM Dashboard" class="w-full h-auto">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section (Restored) -->
    <section class="py-24 bg-charcoal/50" id="funcionalidades">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-20 flex flex-col items-center gap-4">
                <h2 class="text-4xl font-black text-white">Tudo o que sua igreja precisa</h2>
                <div class="h-1.5 w-24 bg-accent-gold rounded-full"></div>
                <p class="text-slate-400 max-w-2xl">Ferramentas poderosas desenvolvidas para facilitar o dia a dia da liderança e automatizar processos manuais.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Feature Items -->
                <div class="p-8 rounded-2xl bg-charcoal border border-white/5 hover:border-accent-gold/50 transition-all group flex flex-col gap-6">
                    <div class="w-14 h-14 rounded-xl bg-accent-gold/10 flex items-center justify-center text-accent-gold group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-white">Gestão de Membros</h3>
                    <p class="text-slate-400 text-sm">Cadastro completo, histórico ministerial e acompanhamento de visitantes em tempo real.</p>
                </div>
                <div class="p-8 rounded-2xl bg-charcoal border border-white/5 hover:border-accent-gold/50 transition-all group flex flex-col gap-6">
                    <div class="w-14 h-14 rounded-xl bg-accent-gold/10 flex items-center justify-center text-accent-gold group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-white">Controle Financeiro</h3>
                    <p class="text-slate-400 text-sm">Gestão de dízimos, ofertas, despesas e emissão de comprovantes direto pelo sistema.</p>
                </div>
                <div class="p-8 rounded-2xl bg-charcoal border border-white/5 hover:border-accent-gold/50 transition-all group flex flex-col gap-6">
                    <div class="w-14 h-14 rounded-xl bg-accent-gold/10 flex items-center justify-center text-accent-gold group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-white">Agenda de Eventos</h3>
                    <p class="text-slate-400 text-sm">Organização de cultos, reuniões e eventos com escala de voluntários por departamentos.</p>
                </div>
                <div class="p-8 rounded-2xl bg-charcoal border border-white/5 hover:border-accent-gold/50 transition-all group flex flex-col gap-6">
                    <div class="w-14 h-14 rounded-xl bg-accent-gold/10 flex items-center justify-center text-accent-gold group-hover:scale-110 transition-transform">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-white">Relatórios</h3>
                    <p class="text-slate-400 text-sm">Análises detalhadas e gráficos intuitivos de crescimento para decisões estratégicas do ministério.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-24 bg-background-dark" id="depoimentos">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-black text-white">O que dizem os <span class="text-accent-gold">Líderes</span></h2>
                <p class="text-slate-400 mt-4 italic">Histórias reais de quem transformou sua gestão com o SGIM.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-8 rounded-2xl bg-charcoal border border-white/5 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 p-4 opacity-10">
                        <svg class="w-16 h-16 text-accent-gold" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21L14.017 18C14.017 16.8954 14.8856 16 15.9592 16H18.8571C19.4533 16 19.9367 15.5165 19.9367 14.9204V12.0204C19.9367 11.4243 19.4533 10.9408 18.8571 10.9408H15.9592C14.8856 10.9408 14.017 10.0454 14.017 8.94082V6.04082C14.017 4.93623 14.8856 4.04082 15.9592 4.04082H18.8571C19.9531 4.04082 20.817 4.90471 20.817 6.00072V14.9204C20.817 16.8954 21.6856 18.5165 22.7592 18.5165V21H14.017ZM3 21L3 18C3 16.8954 3.86857 16 4.94217 16H7.84006C8.43621 16 8.91964 15.5165 8.91964 14.9204V12.0204C8.91964 11.4243 8.43621 10.9408 7.84006 10.9408H4.94217C3.86857 10.9408 3 10.0454 3 8.94082V6.04082C3 4.93623 3.86857 4.04082 4.94217 4.04082H7.84006C8.93596 4.04082 9.79989 4.90471 9.79989 6.00072V14.9204C9.79989 16.8954 10.6685 18.5165 11.7421 18.5165V21H3Z" /></svg>
                    </div>
                    <p class="text-slate-300 mb-8 relative z-10">"O SGIM transformou nossa gestão financeira. Transparência total!"</p>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-accent-gold/20 flex items-center justify-center font-bold text-accent-gold">MS</div>
                        <div>
                            <h4 class="text-white font-bold">Pr. Marcos Silva</h4>
                            <p class="text-xs text-slate-500 italic">Igreja Pentecostal da Glória</p>
                        </div>
                    </div>
                </div>
                <div class="p-8 rounded-2xl bg-charcoal border border-white/5 relative overflow-hidden group border-b-2 border-b-accent-gold shadow-xl">
                    <p class="text-slate-300 mb-8">"Cadastrar membros e visitantes nunca foi tão rápido e organizado."</p>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-accent-gold/20 flex items-center justify-center font-bold text-accent-gold">AS</div>
                        <div>
                            <h4 class="text-white font-bold">Secretária Aline Santos</h4>
                            <p class="text-xs text-slate-500 italic">Comunidade Esperança</p>
                        </div>
                    </div>
                </div>
                <div class="p-8 rounded-2xl bg-charcoal border border-white/5 relative overflow-hidden group">
                    <p class="text-slate-300 mb-8">"O suporte é impecável e o sistema é muito simples de usar."</p>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-accent-gold/20 flex items-center justify-center font-bold text-accent-gold">RL</div>
                        <div>
                            <h4 class="text-white font-bold">Pr. Ricardo Lima</h4>
                            <p class="text-xs text-slate-500 italic">Igreja Batista Fonte</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-24 bg-charcoal/50" id="faq">
        <div class="max-w-4xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-black text-white">Perguntas Frequentes</h2>
                <div class="h-1 w-20 bg-accent-gold mx-auto mt-4 rounded-full"></div>
            </div>
            <div class="space-y-4">
                <?php
                $faqs = [
                    ["pergunta" => "O pagamento é realmente único?", "resposta" => "Sim! Você paga uma única vez pela licença vitalícia e nunca mais terá que se preocupar com mensalidades ou renovações."],
                    ["pergunta" => "Como recebo minha licença?", "resposta" => "Imediatamente após a confirmação do pagamento, você receberá sua chave de licença e os arquivos do sistema no seu e-mail cadastrado."],
                    ["pergunta" => "O sistema funciona em qualquer hospedagem?", "resposta" => "O SGIM foi desenvolvido em PHP e MySQL, funcionando em qualquer servidor de hospedagem padrão (HostGator, Hostinger, Bluehost, etc)."],
                    ["pergunta" => "Tenho suporte técnico incluído?", "resposta" => "Com certeza! Oferecemos suporte prioritário via WhatsApp para ajudar em qualquer dúvida de instalação ou uso do sistema."],
                    ["pergunta" => "Posso gerenciar mais de uma igreja?", "resposta" => "Cada licença é válida para um único domínio. Para gerenciar múltiplas igrejas em domínios diferentes, são necessárias licenças adicionais."],
                    ["pergunta" => "Quais as formas de pagamento?", "resposta" => "Aceitamos Cartão de Crédito (em até 12x), PIX com liberação imediata e Boleto Bancário."],
                    ["pergunta" => "Existe garantia de satisfação?", "resposta" => "Sim! Oferecemos 7 dias de garantia incondicional. Se não ficar satisfeito, devolvemos 100% do seu investimento."]
                ];
                foreach ($faqs as $i => $faq): ?>
                <div class="bg-charcoal border border-white/10 rounded-xl overflow-hidden">
                    <button onclick="toggleFaq(<?= $i ?>)" class="w-full px-6 py-5 flex items-center justify-between text-left hover:bg-white/5 transition-colors">
                        <span class="font-bold text-white"><?= $faq['pergunta'] ?></span>
                        <svg id="icon-<?= $i ?>" class="w-5 h-5 text-accent-gold transition-transform rotate-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"></path></svg>
                    </button>
                    <div id="ans-<?= $i ?>" class="hidden px-6 pb-6 text-slate-400 text-sm leading-relaxed border-t border-white/5 pt-4">
                        <?= $faq['resposta'] ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script>
        function toggleFaq(i) {
            const ans = document.getElementById('ans-' + i);
            const icon = document.getElementById('icon-' + i);
            const isHidden = ans.classList.contains('hidden');
            
            // Close all
            document.querySelectorAll('[id^="ans-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="icon-"]').forEach(el => el.classList.remove('rotate-180'));
            
            if (isHidden) {
                ans.classList.remove('hidden');
                icon.classList.add('rotate-180');
            }
        }
    </script>

    <!-- Pricing Section -->
    <section class="py-24 bg-background-dark" id="precos">
        <div class="max-w-7xl mx-auto px-6 flex flex-col items-center">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-black text-white">Investimento Único</h2>
                <p class="text-slate-400 mt-4">Sua igreja merece o melhor, sem mensalidades infinitas.</p>
            </div>
            <div class="w-full max-w-lg relative group">
                <div class="absolute -inset-1 bg-gradient-to-r from-accent-gold to-yellow-500 rounded-3xl blur opacity-30 group-hover:opacity-100 transition duration-500"></div>
                <div class="relative bg-black rounded-3xl border border-accent-gold p-10 flex flex-col gap-10">
                    <div class="text-center">
                        <span class="text-accent-gold font-black tracking-widest uppercase text-sm">Ofertão Especial</span>
                        <div class="mt-6 flex flex-col items-center gap-1">
                            <span class="text-slate-500 line-through text-lg">R$ 5.997</span>
                            <div class="flex items-baseline gap-2">
                                <span class="text-2xl font-bold text-white">R$</span>
                                <span class="text-6xl font-black text-accent-gold">3.597</span>
                            </div>
                        </div>
                    </div>
                    <ul class="flex flex-col gap-4">
                        <li class="flex items-center gap-3 text-slate-200">
                            <svg class="w-5 h-5 text-accent-gold" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            Membros Ilimitados
                        </li>
                        <li class="flex items-center gap-3 text-slate-200">
                            <svg class="w-5 h-5 text-accent-gold" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            Gestão Financeira Completa
                        </li>
                        <li class="flex items-center gap-3 text-slate-200">
                            <svg class="w-5 h-5 text-accent-gold" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            Suporte Prioritário 24/7
                        </li>
                    </ul>
                    <a href="venda.php" class="w-full bg-accent-gold text-black py-4 rounded-xl font-black text-center text-lg hover:bg-yellow-400 transition-all shadow-xl">
                        ADQUIRIR AGORA
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="bg-black border-t border-white/10 pt-20 pb-10">
    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-12 mb-20 text-center md:text-left">
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-center md:justify-start gap-2">
                <span class="text-2xl font-black text-white">SGIM</span>
            </div>
            <p class="text-slate-400">Modernizando a gestão do Reino através de tecnologia avançada.</p>
        </div>
        <div>
            <h4 class="text-white font-bold mb-6 italic">Links Legais</h4>
            <ul class="flex flex-col gap-4">
                <li><button onclick="openModal('modalTermos')" class="text-slate-400 hover:text-accent-gold transition-colors text-sm">Termos de Uso</button></li>
                <li><button onclick="openModal('modalPrivacidade')" class="text-slate-400 hover:text-accent-gold transition-colors text-sm">Política de Privacidade</button></li>
                <li><button onclick="openModal('modalSuporte')" class="text-slate-400 hover:text-accent-gold transition-colors text-sm">Suporte</button></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold mb-6 italic">Contato</h4>
            <p class="text-slate-400 text-sm">contato@sgim.com.br</p>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-6 pt-10 border-t border-white/5 text-center text-slate-500 text-[10px] uppercase tracking-widest">
        <p>© 2024 SGIM Church Management System. Todos os direitos reservados.</p>
    </div>
</footer>

<!-- Modais baseados no Stitch -->
<div id="modalTermos" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/90 backdrop-blur-sm p-4 modal">
    <div class="bg-charcoal border border-white/10 rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto shadow-2xl">
        <div class="p-6 border-b border-white/5 flex justify-between items-center sticky top-0 bg-charcoal">
            <h3 class="text-xl font-bold text-white">Termos de Uso</h3>
            <button onclick="closeModal('modalTermos')" class="text-slate-400 hover:text-white"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        <div class="p-8 text-slate-400 leading-relaxed space-y-4 text-sm">
            <p>Bem-vindo ao SGIM. Ao utilizar nossa plataforma, você concorda com os seguintes termos:</p>
            <h4 class="text-white font-bold">1. Uso da Licença</h4>
            <p>A licença adquirida é de uso vitalício para um único domínio organizacional. É proibida a redistribuição ou revenda do código-fonte.</p>
            <h4 class="text-white font-bold">2. Responsabilidade</h4>
            <p>O SGIM fornece as ferramentas de gestão, mas a responsabilidade pelos dados inseridos e pela conformidade legal da instituição é inteiramente do usuário.</p>
        </div>
    </div>
</div>

<div id="modalPrivacidade" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/90 backdrop-blur-sm p-4 modal">
    <div class="bg-charcoal border border-white/10 rounded-2xl w-full max-w-2xl max-h-[80vh] overflow-y-auto shadow-2xl">
        <div class="p-6 border-b border-white/5 flex justify-between items-center sticky top-0 bg-charcoal">
            <h3 class="text-xl font-bold text-white">Política de Privacidade</h3>
            <button onclick="closeModal('modalPrivacidade')" class="text-slate-400 hover:text-white"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        <div class="p-8 text-slate-400 leading-relaxed space-y-4 text-sm">
            <p>Sua privacidade é nossa prioridade absoluta.</p>
            <h4 class="text-white font-bold">1. Coleta de Dados</h4>
            <p>Coletamos apenas as informações necessárias para o funcionamento do sistema e identificação da licença (Nome, Email, Domínio).</p>
            <h4 class="text-white font-bold">2. Segurança</h4>
            <p>Todos os dados são criptografados e armazenados em servidores seguros. Não compartilhamos informações com terceiros sem autorização expressa.</p>
        </div>
    </div>
</div>

<div id="modalSuporte" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/90 backdrop-blur-sm p-4 modal">
    <div class="bg-charcoal border border-white/10 rounded-2xl w-full max-w-md shadow-2xl">
        <div class="p-6 border-b border-white/5 flex justify-between items-center">
            <h3 class="text-xl font-bold text-white">Central de Suporte</h3>
            <button onclick="closeModal('modalSuporte')" class="text-slate-400 hover:text-white"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
        </div>
        <div class="p-8 text-center space-y-6">
            <div class="w-16 h-16 bg-accent-gold/10 rounded-full flex items-center justify-center text-accent-gold mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            </div>
            <p class="text-slate-300">Precisa de ajuda? Fale com nosso time técnico agora mesmo.</p>
            <a href="https://wa.me/5500000000000" class="block w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-4 rounded-xl transition-all">
                CHAMAR NO WHATSAPP
            </a>
            <p class="text-xs text-slate-500">Tempo médio de resposta: 15 minutos</p>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    
    // Close modal on click outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.add('hidden');
        }
    }
</script>

</body>
</html>
