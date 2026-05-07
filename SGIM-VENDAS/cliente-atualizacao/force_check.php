<?php
session_start();
unset($_SESSION['last_ota_check']);
unset($_SESSION['ota_available']);
echo "<h1>⚡ Verificação Forçada!</h1>";
echo "<p>O cache de atualização foi limpo. Volte para a Dashboard e o Sininho deve tocar agora.</p>";
echo "<script>setTimeout(() => { window.location.href='dashboard.php'; }, 2000);</script>";
