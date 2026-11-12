<?php
require __DIR__ . '/lib.php';
$pc = pc_autorizado();
if (!$pc) { http_response_code(403); exit('<h1>Este computador não está autorizado</h1>'); }

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT u.id_usuario, u.senha_hash FROM usuario u
                           JOIN usuario_sala us USING (id_usuario) WHERE u.login = ? AND us.id_sala = ?');
    $st->execute([$_POST['usuario'] ?? '', $pc['id_sala']]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['senha'] ?? '', $u['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['id_usuario'] = (int)$u['id_usuario'];
        header('Location: painel.php');   // TODO: painel.php = tela do mockup (consome status.php e bloquear.php)
        exit;
    }
    $erro = 'Usuário ou senha inválidos';
}
?>
<!doctype html><meta charset="utf-8"><title>Login</title>
<h1>Sala <?= htmlspecialchars($pc['identificacao']) ?></h1>
<?php if ($erro) echo '<p>' . htmlspecialchars($erro) . '</p>'; ?>
<form method="post">
  <label>Usuário <input name="usuario" required></label>
  <label>Senha <input name="senha" type="password" required></label>
  <button>Entrar</button>
</form>
