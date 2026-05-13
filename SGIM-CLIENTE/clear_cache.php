<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<h1>✅ Cache OPCache resetado com sucesso!</h1>";
} else {
    echo "<h1>⚠️ OPCache não está ativo ou disponível.</h1>";
}
echo "<p>Tente acessar o sistema agora.</p>";
?>
