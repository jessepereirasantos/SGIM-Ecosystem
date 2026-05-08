<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>SGIM Master - Pagamento Seguro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script src="https://unpkg.com/imask"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "accent-gold": "#eab308",
                        "background-dark": "#000000",
                        "surface-dark": "#111111",
                        "border-dark": "#222222",
                    },
                    fontFamily: { sans: ["Outfit", "sans-serif"] },
                },
            },
        }
    </script>
    <style>
        body { background-color: #000000; color: #f8fafc; font-family: 'Outfit', sans-serif; }
        .feature-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 1rem; padding: 1.5rem; transition: all 0.3s; }
        .feature-card:hover { border-color: #eab308; background: rgba(234, 179, 8, 0.05); }
        .price-card { border: 2px solid #3f3500; background: linear-gradient(145deg, #110d00 0%, #000 100%); border-radius: 1.5rem; padding: 2rem; }
        .checkout-container { background: #0a0a0a; border: 1px solid #1a1a1a; border-radius: 2.5rem; overflow: hidden; }
        .input-style { width: 100%; background: #000; border: 1px solid #1a1a1a; border-radius: 1rem; padding: 1rem 1.25rem; color: #fff; outline: none; transition: border-color 0.3s; }
        .input-style:focus { border-color: #eab308; }
        .payment-btn.active { border-color: #eab308; background: rgba(234, 179, 8, 0.1); }
        .payment-btn.active i, .payment-btn.active span { color: #eab308; }
        # cardPaymentBrick_container { min-height: 400px; padding: 0; background: transparent; border-radius: 1.5rem; overflow: hidden; }
        
        /* Ajustes para o Brick do Mercado Pago combinar com o tema */
        #cardPaymentBrick_container iframe { border-radius: 1.25rem !important; background-color: #0a0a0a !important; box-shadow: none !important; }

        /* Estilização forçada */
        .mp-brick-card-form { background-color: #0a0a0a !important; color: #ffffff !important; border: none !important; }
        
        /* Efeito de brilho Destaque Cupom */
        .coupon-success { animation: gold-glow 2s ease-in-out; }
        @keyframes gold-glow {
            0% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.4); }
            50% { box-shadow: 0 0 20px 5px rgba(234, 179, 8, 0.2); }
            100% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
        }
    </style>
</head>
<body class="antialiased pb-20 px-6">
    <!-- Cabeçalho de Checkout Seguro -->
    <header class="max-w-7xl mx-auto py-8 flex justify-between items-center border-b border-white/[0.05] mb-12">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-accent-gold rounded-xl flex items-center justify-center text-black shadow-lg shadow-accent-gold/20">
                <span class="material-icons">church</span>
            </div>
            <div>
                <h2 class="text-xl font-black text-white leading-none">SGIM <span class="text-accent-gold">MASTER</span></h2>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">Gestão Inteligente</p>
            </div>
        </div>
        <div class="flex items-center gap-4 text-slate-400">
            <div class="hidden md:flex flex-col items-end">
                <span class="text-[10px] font-black uppercase tracking-widest text-green-500 tracking-tighter">Ambiente 100% Seguro</span>
                <span class="text-[9px] font-medium">SSL / Criptografia 256-bits</span>
            </div>
            <div class="w-10 h-10 bg-white/[0.03] border border-white/[0.05] rounded-full flex items-center justify-center text-green-500">
                <i class="fas fa-shield-halved text-sm"></i>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto flex flex-col lg:flex-row gap-12 items-start">
        <!-- Lado Esquerdo: Features e Preço -->
        <div class="flex-1 space-y-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="feature-card flex items-start gap-4">
                    <span class="material-icons text-accent-gold text-3xl">group</span>
                    <div>
                        <h4 class="text-white font-bold text-sm">Gestão completa de membros</h4>
                        <p class="text-slate-500 text-xs">Controle total dos seus fiéis e visitantes.</p>
                    </div>
                </div>
                <!-- ... Outros cards ... -->
                <div class="feature-card flex items-start gap-4">
                    <span class="material-icons text-accent-gold text-3xl">account_balance_wallet</span>
                    <div>
                        <h4 class="text-white font-bold text-sm">Controle financeiro avançado</h4>
                        <p class="text-slate-500 text-xs">Dízimos, ofertas e relatórios detalhados.</p>
                    </div>
                </div>
                <div class="feature-card flex items-start gap-4">
                    <span class="material-icons text-accent-gold text-3xl">event_available</span>
                    <div>
                        <h4 class="text-white font-bold text-sm">Agenda de eventos</h4>
                        <p class="text-slate-500 text-xs">Organize cultos, reuniões e festividades.</p>
                    </div>
                </div>
                <div class="feature-card flex items-start gap-4">
                    <span class="material-icons text-accent-gold text-3xl">analytics</span>
                    <div>
                        <h4 class="text-white font-bold text-sm">Relatórios em tempo real</h4>
                        <p class="text-slate-500 text-xs">Dashboards inteligentes.</p>
                    </div>
                </div>
                <div class="feature-card flex items-start gap-4">
                    <span class="material-icons text-accent-gold text-3xl">cloud_done</span>
                    <div>
                        <h4 class="text-white font-bold text-sm">Backup automático diário</h4>
                        <p class="text-slate-500 text-xs">Seus dados sempre seguros.</p>
                    </div>
                </div>
                <div class="feature-card flex items-start gap-4">
                    <span class="material-icons text-accent-gold text-3xl">support_agent</span>
                    <div>
                        <h4 class="text-white font-bold text-sm">Suporte prioritário VIP</h4>
                        <p class="text-slate-500 text-xs">Atendimento via WhatsApp.</p>
                    </div>
                </div>
            </div>

            <div class="price-card flex flex-col md:flex-row justify-between items-center gap-8">
                <div class="space-y-1">
                    <p class="text-accent-gold font-black text-xs uppercase tracking-widest italic">Investimento Único</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-slate-300 text-2xl font-black">R$</span>
                        <span class="text-6xl font-black text-white" id="totalDisplay"><?php echo number_format(PRODUCT_PRICE, 2, ',', '.'); ?></span>
                        <span class="text-slate-400 font-bold text-sm">/ único</span>
                    </div>
                </div>
                <div class="text-center md:text-right">
                    <p class="text-slate-500 text-sm font-medium mb-1">Economize mais de R$ 2.400 anual</p>
                    <p class="text-white font-black text-lg uppercase tracking-tight">ACESSO VITALÍCIO <br> <span class="text-accent-gold">LIBERADO</span></p>
                </div>
            </div>
        </div>

        <!-- Lado Direito: Checkout -->
        <div class="w-full lg:w-[450px] shrink-0">
            <div class="checkout-container">
                <div class="p-8 border-b border-white/[0.05] flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-black text-white">Finalizar</h3>
                        <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Checkout Transparente</p>
                    </div>
                    <img src="https://img.icons8.com/color/48/mercado-pago.png" class="h-6 opacity-30 grayscale" alt="MP">
                </div>

                <form id="checkoutForm" class="p-8 space-y-6">
                    <input type="hidden" name="payment_method" id="payment_method" value="pix">
                    
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Seu Nome Completo</label>
                            <input name="nome" id="nome" required class="input-style" placeholder="Escreva seu nome" type="text">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">E-mail</label>
                            <input name="email" id="email" required class="input-style" placeholder="seu@email.com" type="email">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Criar Senha</label>
                                <input name="senha" id="senhaInput" required class="input-style" placeholder="••••••••" type="password">
                            </div>
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Confirmar Senha</label>
                                <input id="confSenhaInput" required class="input-style" placeholder="••••••••" type="password">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Telefone</label>
                                <input name="telefone" id="telInput" required class="input-style" placeholder="(00) 00000-0000" type="text" inputmode="numeric">
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Documento</label>
                                    <div class="flex gap-3">
                                        <label class="flex items-center gap-1.5 cursor-pointer group">
                                            <input type="radio" name="doc_type" value="cpf" checked class="hidden peer" onchange="switchDocMask('cpf')">
                                            <div class="w-2.5 h-2.5 rounded-full border border-accent-gold/50 peer-checked:bg-accent-gold peer-checked:border-accent-gold transition-all"></div>
                                            <span class="text-[9px] font-bold text-slate-500 peer-checked:text-white uppercase tracking-tighter">CPF</span>
                                        </label>
                                        <label class="flex items-center gap-1.5 cursor-pointer group">
                                            <input type="radio" name="doc_type" value="cnpj" class="hidden peer" onchange="switchDocMask('cnpj')">
                                            <div class="w-2.5 h-2.5 rounded-full border border-accent-gold/50 peer-checked:bg-accent-gold peer-checked:border-accent-gold transition-all"></div>
                                            <span class="text-[9px] font-bold text-slate-500 peer-checked:text-white uppercase tracking-tighter">CNPJ</span>
                                        </label>
                                    </div>
                                </div>
                                <input name="documento" id="cpfInput" required class="input-style" placeholder="000.000.000-00" type="text" inputmode="numeric">
                            </div>
                        </div>
                        
                        <!-- Cupom -->
                        <div class="pt-2">
                           <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Cupom de Desconto</p>
                           <div class="flex gap-2">
                               <input id="couponInput" name="cupom" class="input-style" placeholder="Código do cupom" type="text" autocomplete="off">
                               <button type="button" onclick="applyCoupon()" class="bg-white/[0.1] hover:bg-white/[0.2] text-white font-bold px-6 rounded-xl transition-all text-xs">Aplicar</button>
                           </div>
                           <p id="couponMessage" class="mt-2 text-[10px] font-bold hidden"></p>
                        </div>
                    </div>

                    <div class="bg-white/[0.03] p-6 rounded-2xl border border-white/[0.05]">
                        <div class="flex justify-between items-center mb-1 text-slate-400 text-xs">
                            <span>Licença Vitalícia SGIM</span>
                            <span class="text-white font-bold">R$ <?php echo number_format(PRODUCT_PRICE, 2, ',', '.'); ?></span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-white/[0.05]">
                            <span class="text-white font-black">Total</span>
                            <span id="finalTotalDisplay" class="text-accent-gold text-2xl font-black">R$ <?php echo number_format(PRODUCT_PRICE, 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Forma de Pagamento</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" onclick="setPaymentMethod('pix')" id="btn-pix" class="payment-btn active flex flex-col items-center justify-center gap-2 p-4 rounded-xl border border-white/[0.05] bg-black">
                                <span class="material-icons text-2xl">qr_code_2</span>
                                <span class="text-[10px] font-bold">PIX</span>
                            </button>
                            <button type="button" onclick="setPaymentMethod('card')" id="btn-card" class="payment-btn flex flex-col items-center justify-center gap-2 p-4 rounded-xl border border-white/[0.05] bg-black">
                                <span class="material-icons text-2xl">credit_card</span>
                                <span class="text-[10px] font-bold">Cartão</span>
                            </button>
                            <button type="button" onclick="setPaymentMethod('boleto')" id="btn-boleto" class="payment-btn flex flex-col items-center justify-center gap-2 p-4 rounded-xl border border-white/[0.05] bg-black">
                                <span class="material-icons text-2xl">description</span>
                                <span class="text-[10px] font-bold">Boleto</span>
                            </button>
                        </div>
                        <div id="cardForm" class="hidden pt-6"><div id="cardPaymentBrick_container"></div></div>
                    </div>

                    <button type="submit" id="btnSubmit" class="w-full bg-accent-gold hover:bg-yellow-500 text-black font-black py-5 rounded-2xl transform active:scale-95 transition-all shadow-xl shadow-accent-gold/20">
                        <span id="btnText">PAGAR COM PIX</span>
                    </button>
                </form>

                <div id="pixArea" class="hidden p-10 text-center">
                    <img id="qrCodeImg" src="" class="mx-auto mb-6 bg-white p-4 rounded-3xl w-56 h-56">
                    <p class="text-white font-black text-2xl mb-4">Total: <span id="pixValueDisplay" class="text-accent-gold">R$ <?php echo number_format(PRODUCT_PRICE, 2, ',', '.'); ?></span></p>
                    <div class="bg-black border border-white/[0.05] p-4 rounded-xl flex items-center gap-4 mb-6">
                        <input type="text" id="pixCode" readonly class="bg-transparent text-[10px] text-accent-gold flex-1 outline-none font-mono">
                        <button onclick="copyPix()" class="bg-accent-gold text-black px-4 py-2 rounded-lg font-bold text-xs">COPIAR</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/95 backdrop-blur-xl px-4">
        <div class="bg-[#0a0a0a] border border-white/10 p-10 md:p-14 rounded-[3rem] max-w-md w-full text-center shadow-2xl shadow-green-500/10">
            <div class="w-24 h-24 bg-green-500/10 rounded-full flex items-center justify-center text-green-500 mx-auto mb-10 border border-green-500/20 scale-110">
                <i class="fas fa-check text-5xl"></i>
            </div>
            <h3 class="text-3xl font-black text-white mb-6 leading-tight">Pagamento confirmado <br> com sucesso!</h3>
            <div class="space-y-2 mb-10">
                <p class="text-slate-300 text-lg font-medium">Seu acesso foi liberado.</p>
                <p class="text-slate-500 text-sm">Você está sendo redirecionado para sua dashboard...</p>
            </div>
            <p class="text-[10px] text-slate-600 uppercase font-black tracking-[0.3em]">Aguarde...</p>
        </div>
    </div>

<script>
(function() {
    const mp = new MercadoPago('<?php echo MP_PUBLIC_KEY; ?>');
    let cardPaymentBrickController = null;
    const currentPrice = <?php echo PRODUCT_PRICE; ?>;
    let finalPrice = currentPrice;

    let docMask = null;
    function initMasks() {
        if (document.getElementById('cpfInput')) {
            docMask = IMask(document.getElementById('cpfInput'), { mask: '000.000.000-00' });
        }
        if (document.getElementById('telInput')) IMask(document.getElementById('telInput'), { mask: '(00) 00000-0000' });
    }

    window.switchDocMask = function(type) {
        const input = document.getElementById('cpfInput');
        if (!input || !docMask) return;
        
        docMask.destroy();
        input.value = '';
        
        if (type === 'cpf') {
            docMask = IMask(input, { mask: '000.000.000-00' });
            input.placeholder = '000.000.000-00';
        } else {
            docMask = IMask(input, { mask: '00.000.000/0000-00' });
            input.placeholder = '00.000.000/0000-00';
        }
    };

    window.applyCoupon = async function() {
        const codeInput = document.getElementById('couponInput');
        const code = codeInput.value;
        const msg = document.getElementById('couponMessage');
        const container = document.getElementById('cardPaymentBrick_container');
        
        if (!code) return;
        
        codeInput.disabled = true;
        msg.innerText = 'Validando cupom...';
        msg.className = 'text-slate-500 mt-2 text-[10px] font-bold block animate-pulse';
        
        try {
            const res = await fetch(`api/checkout/validate_coupon.php?codigo=${code}&valor_pedido=${currentPrice}`);
            const data = await res.json();
            
            if (data.success) {
                finalPrice = parseFloat(data.valor_final || data.novo_valor);
                
                // Regra de Ouro: Mercado Pago Cartão exige valor mínimo (Geralmente R$ 0,50 a R$ 1,00)
                // Se o cupom for muito agressivo, avisamos
                if (finalPrice < 0.50) {
                   msg.innerText = '⚠️ O valor mínimo para cartão é R$ 0,50. Ajustando...';
                   finalPrice = 0.50;
                }

                const formatted = finalPrice.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if (document.getElementById('totalDisplay')) document.getElementById('totalDisplay').innerText = formatted;
                if (document.getElementById('finalTotalDisplay')) document.getElementById('finalTotalDisplay').innerText = 'R$ ' + formatted;
                if (document.getElementById('pixValueDisplay')) document.getElementById('pixValueDisplay').innerText = 'R$ ' + formatted;
                
                msg.innerText = '🎉 Cupom aplicado com sucesso!';
                msg.className = 'text-green-500 mt-2 text-[10px] font-bold block';
                
                // Feedback visual de brilho no container
                const chk = document.querySelector('.checkout-container');
                chk.classList.add('coupon-success');
                setTimeout(() => chk.classList.remove('coupon-success'), 2000);
                
                // Reinicialização BRUTAL do Card Brick (Necessária para atualizar o 'amount')
                if (document.getElementById('payment_method').value === 'card') {
                    try {
                        if (cardPaymentBrickController) {
                            await cardPaymentBrickController.unmount();
                            cardPaymentBrickController = null;
                        }
                    } catch(e) { console.warn('Unmount failed, continuing...'); }
                    
                    container.innerHTML = '<div class="flex flex-col items-center justify-center h-64 text-slate-500 gap-3"><i class="fas fa-circle-notch fa-spin text-accent-gold text-2xl"></i><span class="text-[9px] font-black uppercase tracking-widest animate-pulse">Recalculando taxas e parcelas...</span></div>';
                    
                    setTimeout(async () => {
                        container.innerHTML = ''; 
                        await initCardBrick();
                    }, 1200);
                }
            } else {
                msg.innerText = data.message || 'Cupom inválido.';
                msg.className = 'text-red-500 mt-2 text-[10px] font-bold block';
            }
        } catch (e) { 
            msg.innerText = 'Erro ao validar cupom.';
            msg.className = 'text-red-500 mt-2 text-[10px] font-bold block';
        } finally {
            codeInput.disabled = false;
        }
    };

    window.setPaymentMethod = function(method) {
        document.getElementById('payment_method').value = method;
        document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('btn-' + method).classList.add('active');
        
        const btnSubmit = document.getElementById('btnSubmit');
        
        if (method === 'card') {
            document.getElementById('cardForm').classList.remove('hidden');
            btnSubmit.classList.add('hidden');
            if (!cardPaymentBrickController) initCardBrick();
        } else { 
            document.getElementById('cardForm').classList.add('hidden');
            btnSubmit.classList.remove('hidden');
            btnSubmit.innerHTML = `<span id="btnText">PAGAR COM ${method.toUpperCase()}</span>`;
        }
    };

    async function initCardBrick() {
        const bricksBuilder = mp.bricks();
        const userEmail = document.getElementById('email').value || 'cliente@exemplo.com';
        
        cardPaymentBrickController = await bricksBuilder.create('cardPayment', 'cardPaymentBrick_container', {
            initialization: { 
                amount: finalPrice,
                payer: {
                    email: userEmail,
                },
            },
            customization: { 
                visual: { 
                    theme: 'dark',
                    fontFamily: 'Outfit',
                    borderRadius: '1.25rem',
                    style: {
                        theme: 'dark',
                        customVariables: {
                            formBackgroundColor: '#0a0a0a',
                            baseColor: '#ffffff',
                            primaryColor: '#eab308', // Accent Gold
                            secondaryColor: '#64748b', // Slate 500
                            inputBackgroundColor: '#000000',
                            inputBorderColor: '#1a1a1a',
                            inputFocusedBorderColor: '#eab308',
                            inputHeight: '52px',
                            buttonBackgroundColor: '#eab308',
                            buttonTextColor: '#000000',
                            buttonHeight: '56px',
                            buttonBorderRadius: '1.25rem',
                            errorColor: '#ef4444'
                        }
                    },
                    texts: {
                        formTitle: 'Dados do Cartão de Crédito',
                        emailSectionTitle: 'Titular do Cartão',
                        installmentsSectionTitle: 'Parcelas',
                        cardholderName: {
                            label: 'Nome impresso no cartão',
                            placeholder: 'Ex: MARIA S PEREIRA'
                        }
                    }
                },
                paymentMethods: {
                    maxInstallments: 12,
                    types: {
                        excluded: ['debit_card']
                    }
                }
            },
            callbacks: {
                onReady: () => {
                    document.getElementById('btnSubmit').classList.add('hidden');
                },
                onSubmit: (d) => processPayment(d),
                onError: (e) => {
                    console.error('MP Error:', e);
                    // Em caso de erro crítico, tenta resetar o container
                    document.getElementById('cardPaymentBrick_container').innerHTML = '';
                    initCardBrick();
                }
            }
        });
    }

    function showCheckoutError(msg) {
        let errBox = document.getElementById('checkout-error-box');
        if (!errBox) {
            errBox = document.createElement('div');
            errBox.id = 'checkout-error-box';
            errBox.style.cssText = 'background:#2d0a0a;border:1px solid #7f1d1d;color:#fca5a5;padding:1rem 1.25rem;border-radius:1rem;font-size:12px;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:0.75rem;';
            document.getElementById('checkoutForm').prepend(errBox);
        }
        errBox.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#ef4444;flex-shrink:0;"></i><span>' + msg + '</span>';
        errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    async function processPayment(cardData) {
        const s1 = document.getElementById('senhaInput').value;
        const s2 = document.getElementById('confSenhaInput').value;
        if (s1 !== s2) { showCheckoutError('As senhas não coincidem. Por favor, verifique.'); return; }

        // Validação básica de documento
        const docRaw = document.getElementById('cpfInput').value.replace(/\D/g, '');
        if (docRaw.length < 11) { showCheckoutError('Informe um CPF ou CNPJ válido antes de prosseguir.'); return; }

        const btn = document.getElementById('btnSubmit');
        const currentMethod = document.getElementById('payment_method').value;
        const formData = new FormData(document.getElementById('checkoutForm'));
        formData.set('documento', docRaw);
        formData.set('telefone', document.getElementById('telInput').value.replace(/\D/g, ''));
        if (cardData) {
            formData.append('token', cardData.token);
            formData.append('installments', cardData.installments);
            formData.append('payment_method_id', cardData.payment_method_id);
            formData.append('issuer_id', cardData.issuer_id);
        }
        
        // Remover erro anterior
        const prevErr = document.getElementById('checkout-error-box');
        if (prevErr) prevErr.remove();

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> PROCESSANDO...';
        try {
            const res = await fetch('api/checkout/process.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                if (data.payment_method === 'pix') {
                    document.getElementById('checkoutForm').classList.add('hidden');
                    document.getElementById('pixArea').classList.remove('hidden');
                    document.getElementById('qrCodeImg').src = 'data:image/png;base64,' + data.qr_code_base64;
                    document.getElementById('pixCode').value = data.qr_code;
                    startPolling(data.pedido_id);
                } else if (data.status === 'approved' || data.status === 'authorized') { 
                    showSuccessModal(data.redirect); 
                } else { 
                    startPolling(data.pedido_id); 
                }
            } else {
                const errMsg = data.message || 'Ocorreu um erro ao processar o pagamento. Tente novamente.';
                showCheckoutError(errMsg);
                btn.disabled = false;
                btn.innerHTML = 'PAGAR COM ' + currentMethod.toUpperCase();
            }
        } catch (e) { 
            showCheckoutError('Falha na conexão com o servidor. Verifique sua internet e tente novamente.');
            btn.disabled = false;
            btn.innerHTML = 'PAGAR COM ' + currentMethod.toUpperCase();
        }
    }

    document.getElementById('checkoutForm').addEventListener('submit', (e) => {
        e.preventDefault();
        if (document.getElementById('payment_method').value !== 'card') processPayment();
    });

    function startPolling(id) {
        let attempts = 0;
        const maxAttempts = 40;
        const int = setInterval(async () => {
            attempts++;
            try {
                // Tenta sincronizar primeiro
                await fetch('sync_all.php');
                
                // Consulta status
                const res = await fetch('api/checkout/check_payment.php?pedido_id=' + id);
                const data = await res.json();
                
                if (data.success && (data.status === 'approved' || data.status === 'authorized' || data.status === 'APROVADO')) { 
                    clearInterval(int); 
                    showSuccessModal(data.redirect); 
                }
                
                if (attempts >= maxAttempts) {
                    clearInterval(int);
                    window.location.href = 'cliente/dashboard.php?check=1';
                }
            } catch (e) {
                console.error('Erro no polling:', e);
            }
        }, 3000);
    }

    function showSuccessModal(url) {
        // Garante que a área do PIX ou formulário suma
        document.getElementById('checkoutForm').classList.add('hidden');
        document.getElementById('pixArea').classList.add('hidden');
        
        // Mostra o modal de sucesso
        const modal = document.getElementById('successModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Redirecionamento após 3 segundos
        setTimeout(() => {
            window.location.href = url || 'cliente/dashboard.php?success=1';
        }, 3000);
    }

    initMasks();
})();

function copyPix() {
    const el = document.getElementById("pixCode");
    el.select();
    document.execCommand("copy");
    alert("PIX Copiado!");
}
</script>
</body>
</html>
