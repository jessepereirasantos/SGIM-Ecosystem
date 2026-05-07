<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';

$page_title = 'SGIM - Editor de Carteirinhas';
$current_page = 'membros';

require_once 'includes/header.php';
?>

<div class="flex flex-col h-[calc(100vh-140px)] overflow-hidden rounded-xl border border-darkborder bg-darkcard">
    <!-- Editor Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-darkborder bg-white/[0.02]">
        <div class="flex items-center gap-4">
            <div class="flex items-center justify-center size-10 rounded-xl bg-brand text-black shadow-lg shadow-brand/20">
                <span class="material-symbols-outlined font-bold">badge</span>
            </div>
            <div>
                <h2 class="text-white text-lg font-black tracking-tighter leading-tight">Editor de Carteirinhas</h2>
                <p class="text-gray-500 text-[10px] uppercase tracking-widest font-bold">Personalização Visual</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="flex bg-darkbg p-1 rounded-xl border border-darkborder mr-4">
                <button class="px-4 py-1.5 text-xs font-bold rounded-lg bg-brand text-black shadow-lg shadow-brand/10">Frente</button>
                <button class="px-4 py-1.5 text-xs font-medium text-gray-500 hover:text-brand transition-colors">Verso</button>
            </div>
            <!-- Novo Botão de Upload de Fundo -->
            <input type="file" id="bg-upload" class="hidden" accept="image/*">
            <button onclick="document.getElementById('bg-upload').click()" class="flex items-center gap-2 px-4 py-2 rounded-xl border border-darkborder bg-darkbg text-brand text-xs font-bold hover:bg-brand/10 transition-all">
                <span class="material-symbols-outlined text-base">upload_file</span>
                Subir Fundo
            </button>
            
            <button class="flex items-center gap-2 px-4 py-2 rounded-xl border border-darkborder bg-darkbg text-gray-300 text-xs font-bold hover:border-brand/50 transition-all">
                <span class="material-symbols-outlined text-base">folder_open</span>
                Modelos
            </button>
            <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2 rounded-xl bg-brand text-black text-xs font-black hover:bg-brand-light transition-colors shadow-lg shadow-brand/20">
                <span class="material-symbols-outlined text-base">print</span>
                Imprimir
            </button>
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden">
        <!-- Element Sidebar -->
        <aside class="w-64 border-r border-darkborder bg-darkcard flex flex-col shrink-0">
            <div class="p-4 space-y-4 overflow-y-auto">
                <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest px-2">Adicionar Elementos</p>
                <div class="grid grid-cols-2 gap-2">
                    <button class="flex flex-col items-center gap-2 p-4 rounded-xl bg-darkbg border border-darkborder hover:border-brand/50 transition-all group">
                        <span class="material-symbols-outlined text-brand text-2xl group-hover:scale-110 transition-transform">text_fields</span>
                        <span class="text-[10px] font-bold text-gray-400">Texto</span>
                    </button>
                    <button class="flex flex-col items-center gap-2 p-4 rounded-xl bg-darkbg border border-darkborder hover:border-brand/50 transition-all group">
                        <span class="material-symbols-outlined text-brand text-2xl group-hover:scale-110 transition-transform">image</span>
                        <span class="text-[10px] font-bold text-gray-400">Imagem</span>
                    </button>
                    <button class="flex flex-col items-center gap-2 p-4 rounded-xl bg-darkbg border border-darkborder hover:border-brand/50 transition-all group">
                        <span class="material-symbols-outlined text-brand text-2xl group-hover:scale-110 transition-transform">qr_code_2</span>
                        <span class="text-[10px] font-bold text-gray-400">QR Code</span>
                    </button>
                    <button class="flex flex-col items-center gap-2 p-4 rounded-xl bg-darkbg border border-darkborder hover:border-brand/50 transition-all group">
                        <span class="material-symbols-outlined text-brand text-2xl group-hover:scale-110 transition-transform">category</span>
                        <span class="text-[10px] font-bold text-gray-400">Formas</span>
                    </button>
                </div>

                <div class="pt-6">
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest px-2 mb-4">Camadas do Documento</p>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between p-2 rounded-lg bg-white/5 border border-white/5">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-sm text-brand">account_circle</span>
                                <span class="text-xs text-gray-300 font-medium">Foto do Membro</span>
                            </div>
                            <span class="material-symbols-outlined text-sm text-gray-600">lock</span>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg hover:bg-white/5 transition-colors cursor-pointer">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-sm text-gray-500">title</span>
                                <span class="text-xs text-gray-400">Nome do Membro</span>
                            </div>
                            <span class="material-symbols-outlined text-sm text-gray-600">visibility</span>
                        </div>
                        <div class="flex items-center justify-between p-2 rounded-lg hover:bg-white/5 transition-colors cursor-pointer">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-sm text-gray-500">grid_view</span>
                                <span class="text-xs text-gray-400">Fundo Dourado</span>
                            </div>
                            <span class="material-symbols-outlined text-sm text-gray-600">lock</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Canvas Area -->
        <main class="flex-1 bg-darkbg relative overflow-hidden flex flex-col p-8">
            <!-- Tools Toolbar -->
            <div class="absolute top-8 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-darkcard/80 backdrop-blur-xl px-4 py-2 rounded-2xl border border-white/5 shadow-2xl z-20">
                <button class="p-2 hover:text-brand text-gray-500 transition-colors"><span class="material-symbols-outlined">undo</span></button>
                <button class="p-2 hover:text-brand text-gray-500 transition-colors"><span class="material-symbols-outlined">redo</span></button>
                <div class="w-px h-4 bg-darkborder mx-2"></div>
                <div class="flex items-center gap-2 bg-darkbg border border-darkborder rounded-lg px-3 py-1">
                    <button class="text-gray-500 hover:text-brand"><span class="material-symbols-outlined text-sm">remove</span></button>
                    <span class="text-[10px] font-black text-gray-300 w-8 text-center">85%</span>
                    <button class="text-gray-500 hover:text-brand"><span class="material-symbols-outlined text-sm">add</span></button>
                </div>
                <div class="w-px h-4 bg-darkborder mx-2"></div>
                <button class="p-2 hover:text-brand text-gray-500 transition-colors"><span class="material-symbols-outlined">fullscreen</span></button>
            </div>

            <!-- Designing Surface -->
            <div class="flex-1 flex items-center justify-center relative">
                <div class="canvas-grid absolute inset-0 opacity-20"></div>
                
                <!-- The ID Card Canvas -->
                <div id="card-canvas" class="relative w-[450px] h-[280px] bg-[#0A0A0A] rounded-2xl border-2 border-brand/30 shadow-2xl overflow-hidden p-6 flex flex-col justify-between group cursor-move">
                    <img id="card-bg-layer" src="" class="absolute inset-0 w-full h-full object-cover hidden pointer-events-none">
                    <div class="absolute -top-20 -right-20 w-64 h-64 bg-brand/5 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-brand"></div>
                    
                <div class="flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-3">
                            <div class="size-10 bg-brand rounded-lg flex items-center justify-center text-black">
                                <span class="material-symbols-outlined text-2xl font-bold">church</span>
                            </div>
                            <div class="cursor-pointer" onclick="this.querySelector('h1').focus()">
                                <h1 class="text-sm font-black text-white leading-tight uppercase tracking-tighter outline-none" contenteditable="true">SGIM CHURCH</h1>
                                <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest outline-none" contenteditable="true">Sistema de Gestão Integrada</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-[8px] text-brand font-black uppercase tracking-widest outline-none" contenteditable="true">Membro Oficial</p>
                            <p class="text-[10px] text-gray-400 font-mono outline-none" contenteditable="true">ID: 2024-00421</p>
                        </div>
                    </div>

                    <div class="flex gap-6 items-center mt-4 relative z-10">
                        <div class="relative">
                            <div class="size-28 rounded-xl bg-darkbg border-2 border-brand/50 overflow-hidden flex items-center justify-center shadow-lg cursor-pointer hover:border-brand transition-colors" onclick="document.getElementById('photo-upload').click()">
                                <img id="member-photo" src="" class="w-full h-full object-cover hidden">
                                <span id="photo-placeholder" class="material-symbols-outlined text-gray-700 text-5xl">person</span>
                            </div>
                            <input type="file" id="photo-upload" class="hidden" accept="image/*">
                            <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 bg-brand text-black text-[8px] font-black px-2 py-0.5 rounded-full whitespace-nowrap shadow-md outline-none" contenteditable="true">
                                VÁLIDA ATÉ 12/26
                            </div>
                        </div>

                        <div class="flex-1 space-y-3">
                            <div>
                                <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5 outline-none" contenteditable="true">Nome do Membro</p>
                                <p class="text-sm font-black text-white uppercase tracking-tight outline-none" contenteditable="true">JOÃO DA SILVA OLIVEIRA</p>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5 outline-none" contenteditable="true">Cargo / Função</p>
                                    <p class="text-[10px] font-bold text-gray-200 uppercase outline-none" contenteditable="true">DIÁCONO</p>
                                </div>
                                <div>
                                    <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5 outline-none" contenteditable="true">Congregação</p>
                                    <p class="text-[10px] font-bold text-gray-200 uppercase outline-none" contenteditable="true">SEDE CENTRAL</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-white/5 pt-4 mt-4 relative z-10">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-brand text-[14px]">verified_user</span>
                            <span class="text-[8px] text-gray-500 font-bold uppercase tracking-widest">Autenticidade Digital</span>
                        </div>
                        <div class="size-10 bg-white p-1 rounded-sm shadow-inner opacity-80 hover:opacity-100 transition-opacity">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=SGIM-VERIFY-DEMO" class="w-full h-full">
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Properties Panel -->
        <aside class="w-72 border-l border-darkborder bg-darkcard hidden xl:flex flex-col shrink-0">
            <div class="p-6 space-y-8">
                <div>
                    <h3 class="text-sm font-black text-white flex items-center gap-2 mb-6">
                        <span class="material-symbols-outlined text-brand">settings</span>
                        Propriedades
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Posicionamento</label>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="bg-darkbg border border-darkborder rounded-xl p-3">
                                    <p class="text-[9px] text-gray-600 font-bold uppercase mb-1">Eixo X</p>
                                    <p class="text-sm font-mono text-gray-300">142px</p>
                                </div>
                                <div class="bg-darkbg border border-darkborder rounded-xl p-3">
                                    <p class="text-[9px] text-gray-600 font-bold uppercase mb-1">Eixo Y</p>
                                    <p class="text-sm font-mono text-gray-300">88px</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Estilo do Elemento</label>
                            <div class="bg-darkbg border border-darkborder rounded-xl p-4 space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-400">Opacidade</span>
                                    <span class="text-xs text-brand font-bold">100%</span>
                                </div>
                                <div class="h-1 bg-darkborder rounded-full overflow-hidden">
                                    <div class="h-full bg-brand w-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-8 border-t border-darkborder">
                    <button class="w-full py-4 bg-brand hover:bg-brand-dark text-black rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-brand/20">
                        Salvar Modelo
                    </button>
                </div>
            </div>
        </aside>
    </div>
</div>

<style>
.canvas-grid {
    background-image: radial-gradient(circle, #1e1e1e 1px, transparent 1px);
    background-size: 20px 20px;
}
@media print {
    body * { visibility: hidden; }
    #card-canvas, #card-canvas * { visibility: visible; }
    #card-canvas {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        box-shadow: none;
        border: 1px solid #FFC107;
    }
    aside, header, footer, button { display: none !important; }
}
</style>

<script>
    // Lógica de Upload de Foto do Membro
    document.getElementById('photo-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const img = document.getElementById('member-photo');
                const placeholder = document.getElementById('photo-placeholder');
                img.src = event.target.result;
                img.classList.remove('hidden');
                placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    // Lógica de Upload de Fundo Personalizado
    document.getElementById('bg-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const bgLayer = document.getElementById('card-bg-layer');
                bgLayer.src = event.target.result;
                bgLayer.classList.remove('hidden');
                
                // Ocultar elementos decorativos padrão para não conflitar com o fundo novo
                document.querySelector('.canvas-grid').style.opacity = '0';
                document.querySelector('#card-canvas .bg-brand\\/5').style.display = 'none';
                document.querySelector('#card-canvas .absolute.left-0').style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });

    // Ativação dos botões de elementos e Arrastar e Soltar
    let draggedElement = null;

    function createDraggableElement(type, content) {
        const div = document.createElement('div');
        div.className = 'absolute cursor-move p-2 hover:outline hover:outline-1 hover:outline-brand group';
        div.style.left = '50px';
        div.style.top = '50px';
        div.setAttribute('draggable', 'true');
        
        let innerHTML = '';
        if (type === 'Texto') {
            innerHTML = `<p class="text-white text-sm font-bold bg-transparent border-none outline-none" contenteditable="true">${content || 'Novo Texto'}</p>`;
        } else if (type === 'Imagem') {
            innerHTML = `<div class="size-12 bg-white/10 flex items-center justify-center border border-dashed border-white/20"><span class="material-symbols-outlined text-gray-500">image</span></div>`;
        } else if (type === 'QR Code') {
            innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=SGIM-DRAG-TEST" class="size-12">`;
        } else if (type === 'Formas') {
            innerHTML = `<div class="size-10 bg-brand/50 rounded-lg"></div>`;
        }

        div.innerHTML = innerHTML + `<button class="absolute -top-2 -right-2 bg-red-500 text-white size-4 rounded-full text-[10px] hidden group-hover:flex items-center justify-center" onclick="this.parentElement.remove()">×</button>`;
        
        // Drag Events
        div.onmousedown = function(e) {
            if (e.target.contentEditable === 'true') return;
            let shiftX = e.clientX - div.getBoundingClientRect().left;
            let shiftY = e.clientY - div.getBoundingClientRect().top;

            function moveAt(pageX, pageY) {
                const canvas = document.getElementById('card-canvas');
                const rect = canvas.getBoundingClientRect();
                let x = pageX - rect.left - shiftX;
                let y = pageY - rect.top - shiftY;
                
                // Boundary check
                x = Math.max(0, Math.min(x, rect.width - div.offsetWidth));
                y = Math.max(0, Math.min(y, rect.height - div.offsetHeight));
                
                div.style.left = x + 'px';
                div.style.top = y + 'px';
                
                // Update properties panel
                document.querySelector('.font-mono.text-gray-300:nth-of-type(1)').innerText = Math.round(x) + 'px';
                document.querySelectorAll('.font-mono.text-gray-300')[1].innerText = Math.round(y) + 'px';
            }

            function onMouseMove(e) {
                moveAt(e.clientX, e.clientY);
            }

            document.addEventListener('mousemove', onMouseMove);

            document.onmouseup = function() {
                document.removeEventListener('mousemove', onMouseMove);
                document.onmouseup = null;
            };
        };

        div.ondragstart = function() { return false; };

        document.getElementById('card-canvas').appendChild(div);
    }

    const elementButtons = document.querySelectorAll('aside .grid button');
    elementButtons.forEach(btn => {
        btn.onclick = function() {
            const label = this.querySelector('span:last-child').innerText;
            createDraggableElement(label);
        };
    });

    // Função Salvar Modelo
    document.querySelector('.bg-brand.hover\\:bg-brand-dark').onclick = function() {
        const canvas = document.getElementById('card-canvas');
        // Simulação de salvamento
        const btn = this;
        const originalText = btn.innerText;
        btn.innerText = 'SALVANDO...';
        btn.disabled = true;
        
        setTimeout(() => {
            btn.innerText = 'MODELO SALVO!';
            btn.classList.replace('bg-brand', 'bg-green-500');
            setTimeout(() => {
                btn.innerText = originalText;
                btn.classList.replace('bg-green-500', 'bg-brand');
                btn.disabled = false;
            }, 2000);
        }, 1500);
    };
</script>

<?php require_once 'includes/footer.php'; ?>
