<?php
/**
 * Script de Migração de Versionamento - SGIM CLIENTE
 * Evolui o version.json para o padrão OTA v4.0
 */
require_once 'src/Updater/VersionManager.php';

use App\Updater\VersionManager;

$vm = new VersionManager();

echo "Estrutura atual: " . json_encode($vm->getData()) . "\n";

// Garantir novos campos
$vm->setLastCheck();
if (!$vm->getChannel()) $vm->setChannel('stable');

if ($vm->save()) {
    echo "version.json atualizado com sucesso para o padrao OTA v4.0.\n";
    echo "Nova estrutura: " . json_encode($vm->getData(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Erro ao salvar version.json. Verifique permissoes de escrita.\n";
}
?>
