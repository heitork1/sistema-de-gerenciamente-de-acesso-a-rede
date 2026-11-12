<?php
// POST: id_agendamento. Agendado -> cancela. Ativo -> desbloqueia agora (o gerente marca 'concluido').
require __DIR__ . '/lib.php';
[$pc, ] = exigir_acesso();
$id = (int)($_POST['id_agendamento'] ?? 0);
$st = db()->prepare('SELECT status FROM agendamento WHERE id_agendamento = ? AND id_sala = ?');
$st->execute([$id, $pc['id_sala']]);
$status = $st->fetchColumn();
if (!in_array($status, ['agendado', 'ativo'], true)) json_out(['erro' => 'Agendamento não encontrado ou já encerrado'], 404);

cron_remover($id);
if ($status === 'agendado') {
    db()->prepare("UPDATE agendamento SET status = 'cancelado' WHERE id_agendamento = ?")->execute([$id]);
} else {
    executar_agora('desbloquear', $id);
}
json_out(['ok' => true]);
