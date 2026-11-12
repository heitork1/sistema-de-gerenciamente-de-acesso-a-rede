<?php
// POST: alvo=sala|selecionadas, ids[]=<id_pc>, quando=agora|agendar, inicio=YYYY-MM-DDTHH:MM,
//       duracao=<int>, unidade=minutos|horas|dias
require __DIR__ . '/lib.php';
[$pc, $idUsuario] = exigir_acesso();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['erro' => 'Use POST'], 405);

$salaInteira = ($_POST['alvo'] ?? '') === 'sala';
$duracao = (int)($_POST['duracao'] ?? 0);
$unidades = ['minutos' => 'minutes', 'horas' => 'hours', 'dias' => 'days'];
$un = $unidades[$_POST['unidade'] ?? ''] ?? null;
if ($duracao < 1 || !$un) json_out(['erro' => 'Informe o prazo de desbloqueio'], 422);

$agora = new DateTimeImmutable('now');
if (($_POST['quando'] ?? 'agora') === 'agora') {
    $inicio = $agora;
} else {
    $inicio = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $_POST['inicio'] ?? '') ?: null;
    if (!$inicio || $inicio < $agora->modify('-1 minute')) json_out(['erro' => 'Início inválido'], 422);
}
$fim = $inicio->modify("+$duracao $un");

$pdo = db();
// Só máquinas da SALA do professor e em portas bloqueáveis (nunca professor/uplink/datacenter).
$sql = 'SELECT c.id_pc FROM computador c JOIN porta p USING (id_porta) WHERE c.id_sala = ? AND p.bloqueavel';
$args = [$pc['id_sala']];
if (!$salaInteira) {
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    if (!$ids) json_out(['erro' => 'Selecione ao menos uma máquina'], 422);
    $sql .= ' AND c.id_pc IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    array_push($args, ...$ids);
}
$st = $pdo->prepare($sql);
$st->execute($args);
$alvos = $st->fetchAll(PDO::FETCH_COLUMN);
if (!$alvos || (!$salaInteira && count($alvos) !== count($ids))) json_out(['erro' => 'Alvo inválido para esta sala'], 422);

$pdo->beginTransaction();
$st = $pdo->prepare('INSERT INTO agendamento (id_usuario,id_sala,sala_inteira,inicio_em,fim_em)
                     VALUES (?,?,?,?,?) RETURNING id_agendamento');
$st->execute([$idUsuario, $pc['id_sala'], $salaInteira ? 't' : 'f', $inicio->format('c'), $fim->format('c')]);
$id = (int)$st->fetchColumn();
$ins = $pdo->prepare('INSERT INTO agendamento_alvo (id_agendamento,id_pc) VALUES (?,?)');
foreach ($alvos as $idPc) $ins->execute([$id, $idPc]);
$pdo->commit();

// Início: imediato (chama o gerente já) ou agendado no crontab. Fim: sempre no crontab.
if ($inicio <= $agora->modify('+1 minute')) executar_agora('bloquear', $id);
else cron_agendar($inicio, 'bloquear', $id);
cron_agendar($fim, 'desbloquear', $id);

json_out(['id_agendamento' => $id, 'alvos' => count($alvos), 'inicio' => $inicio->format('c'), 'fim' => $fim->format('c')]);
