<?php
// Utilitário: rode no servidor para gerar um hash bcrypt.
//   php db/gerar_hash.php "MinhaSenhaForte"
// Depois cole o hash retornado no campo senha_hash da tabela usuarios.
if ($argc < 2) {
    fwrite(STDERR, "Uso: php gerar_hash.php <senha>\n");
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;
