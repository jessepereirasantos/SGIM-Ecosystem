<?php
// Gerador de Chaves de Licença SGIM
function gerarChaveLicenca() {
    $chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $p1 = ""; $p2 = "";
    for($i=0; $i<4; $i++) $p1 .= $chars[rand(0, strlen($chars)-1)];
    for($i=0; $i<4; $i++) $p2 .= $chars[rand(0, strlen($chars)-1)];
    return "SGIM-{$p1}-{$p2}";
}
?>
