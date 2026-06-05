# Arquitetura e Protocolo de Trabalho — SGIM (.architecture)

Este documento define a arquitetura técnica, as regras estruturais e o protocolo de trabalho padrão obrigatório para o desenvolvimento do ecossistema **SGIM** (Sistema de Gestão de Igrejas e Ministérios). Toda e qualquer ação de codificação deve seguir estritamente o planejamento aqui documentado.

---

## 1. Visão Geral do Ecossistema

O ecossistema SGIM é composto por dois subprojetos desacoplados no banco de dados, mas integrados pelo pipeline de atualizações e licenciamento:

```text
                               ┌────────────────────────┐
                               │       SGIM-VENDAS      │
                               │  (Portal de Vendas &   │
                               │   Servidor de OTA)     │
                               └───────────┬────────────┘
                                           │
                        Disponibiliza      │ Valida domínios
                        pacotes ZIP        │ e licenças ativas
                        e latest.json      │
                                           ▼
                               ┌────────────────────────┐
                               │      SGIM-CLIENTE      │
                               │  (Aplicação Instalada  │
                               │   nas Igrejas)         │
                               └────────────────────────┘
```

### 1.1 SGIM-VENDAS (Painel de Licenciamento)
* **Objetivo:** Controlar a comercialização do sistema, validar o uso da licença por domínio e enviar patches OTA para os clientes.
* **Componentes Principais:**
  - `/api/checkout/` e `/api/process_payment.php`: Lógica de pagamento com PIX e webhook do Mercado Pago.
  - `/api/check-domain.php` e `/api/validate-license.php`: Endpoints consultados pelos clientes para autorizar o funcionamento e instalação do sistema.
  - `/api/update/`: Diretório que hospeda o `latest.json` (manifesto de atualizações) e `/packages/` contendo os zips físicos das releases.
  - `/source_cliente/`: Código-fonte centralizado e limpo do SGIM-CLIENTE. Nenhuma alteração no código do cliente deve ser feita fora desta pasta.

### 1.2 SGIM-CLIENTE (Painel da Igreja)
* **Objetivo:** Prover o sistema administrativo e financeiro para uso final da igreja.
* **Componentes Principais:**
  - `index.php`: Ponto de entrada que carrega as releases físicas atômicas e provê o fallback Strangler Fig.
  - `setup.php`: O instalador dinâmico. Valida se o domínio está pré-ativado no servidor de Vendas e importa o `schema.sql`.
  - `/releases/`: Pasta que contém as versões físicas isoladas baixadas via OTA (ex: `/releases/v1.1.66/`).
  - `/shared/`: Pasta que armazena os dados mutáveis que sobrevivem a atualizações (banco de dados SQLite, uploads de fotos e logs).
  - `/src/`: Estrutura MVC com carregamento de classes PSR-4 para novas funcionalidades.
  - `/includes/`: Cabeçalhos e rodapés da interface visual legada.

---

## 2. Padrão Arquitetural de Roteamento e OTA

O sistema utiliza a estratégia **Strangler Fig Pattern** com **Promoção Atômica por Symlinks / Ponte Física**:

1. **Roteamento Híbrido:**
   - O `index.php` da raiz do site redireciona o tráfego para a release ativa localizada em `releases/current/index.php`.
   - Se o arquivo físico correspondente na raiz for acessado diretamente (ex: `membros.php`), a **Auto-Ponte** no topo do arquivo é executada, incluindo a versão correta do script que está dentro da pasta `releases/current/` e abortando a execução da raiz.
2. **Ciclo de Atualização OTA (Atômico):**
   - O cliente executa `ota.php` para checar no Vendas se há atualizações.
   - O `ota_download.php` aciona o `OtaOrchestrator` para baixar o ZIP do vendas e extrair em `/releases/vX.X.X/`.
   - O `ota_install.php` invoca o `SharedHostingDriver` que cria o symlink `releases/current` apontando para a pasta da nova versão (ou cria o arquivo de ponte se symlinks forem proibidos no servidor HostGator).
   - O banco de dados do cliente é migrado defensivamente usando comandos `ensureColumnExists` para evitar quebra de compatibilidade.

---

## 3. Protocolo de Trabalho Padrão (Obrigatório)

Qualquer desenvolvimento no ecossistema SGIM deve obrigatoriamente seguir as quatro fases descritas abaixo. Nenhuma modificação é entregue ou considerada concluída sem respeitar este protocolo.

### FASE 1: Planejamento Orientado a Skills
Antes de abrir o código, o desenvolvedor deve analisar a tarefa usando as 10 skills escolhidas:
1. Consultar a skill `gt-cursos-blueprint` para diretrizes de design Obsidian Gold e regras de integração.
2. Planejar a estrutura de persistência usando as diretrizes da skill `database-design`.
3. Projetar os endpoints e APIs com base em `api-security-best-practices`.
4. Organizar a lógica sob `/src/` utilizando a skill `clean-code`.

### FASE 2: Modificação Sincronizada
1. Qualquer código alterado no **SGIM-CLIENTE** deve ser editado exclusivamente sob `/SGIM-VENDAS/source_cliente/`.
2. Após salvar a alteração em `source_cliente`, ela deve ser sincronizada com a pasta de desenvolvimento local `/SGIM-CLIENTE/` para execução de testes locais.
3. Não editar código diretamente no `SGIM-CLIENTE` para evitar perda de alterações no próximo deploy/empacotamento.

### FASE 3: Validação de QA Real com DevTools
O desenvolvedor deve abrir o navegador, acessar a aplicação localmente e realizar os testes reais:
1. **Console Auditing (DevTools):** Manter o console JS aberto. Executar fluxos de cadastro, edição e login. Métrica de sucesso: **Zero erros no console**.
2. **Network Response Audit:** Monitorar a aba Network filtando por Fetch/XHR. Métrica de sucesso: **Todas as chamadas retornando HTTP 200/201 em menos de 250ms com JSON válido**.
3. **Responsividade Física:** Ativar emulação de touch e testar em resoluções de `375px` (iPhone SE) a `1440px` (Desktop). Métrica de sucesso: **Zero overflows horizontais e interface adaptada à paleta Golden Amber Neon**.
4. **Validação E2E/Navegador:** Usar a skill `playwright-skill` para disparar scripts automatizados de validação de fluxos críticos (Login, Cadastro de Membros, Transações Financeiras).

### FASE 4: Deploy Automatizado e Versionamento
Após validar localmente e obter 100% de sucesso nos testes:
1. Abrir o console PowerShell na raiz do projeto.
2. Executar o script de build e deploy:
   `.\deploy.ps1 -Versao "1.1.XX" -Mensagem "descrição da alteração"`
3. O script irá:
   - Incrementar as variáveis de versão no header, schema e setup.
   - Criar o pacote ZIP do cliente em `SGIM-VENDAS/api/update/packages/`.
   - Atualizar a assinatura SHA256 e versão em `SGIM-VENDAS/api/update/latest.json`.
   - Copiar as modificações de `source_cliente` para a raiz de `SGIM-CLIENTE` local.
   - Executar o `git add .`, realizar o `git commit` com padrão convencional e dar `git push` para o GitHub remoto.
