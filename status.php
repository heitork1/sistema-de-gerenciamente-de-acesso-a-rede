<?php
// GET: usado pelo painel em polling (a cada ~5 s) para atualizar ícones e agendamentos sem recarregar.
require __DIR__ . '/lib.php';
[$pc, ] = exigir_acesso();
$pdo = db();

$st = $pdo->prepare("SELECT c.id_pc, c.nome, host(c.ip) AS ip, c.mac::text AS mac, c.acesso_internet,
                            s.ip::text AS switch_ip, p.nome AS porta, p.categoria,
                            (SELECT a.fim_em FROM agendamento a JOIN agendamento_alvo aa USING (id_agendamento)
                              WHERE aa.id_pc = c.id_pc AND a.status = 'ativo' ORDER BY a.fim_em DESC LIMIT 1) AS libera_em,
                            (SELECT a.id_agendamento FROM agendamento a JOIN agendamento_alvo aa USING (id_agendamento)
                              WHERE aa.id_pc = c.id_pc AND a.status = 'ativo' ORDER BY a.fim_em DESC LIMIT 1) AS id_agend_ativo
                       FROM computador c JOIN porta p USING (id_porta) JOIN switch s ON s.id_switch = p.id_switch
                      WHERE c.id_sala = ? ORDER BY c.nome");
$st->execute([$pc['id_sala']]);

$ag = $pdo->prepare("SELECT id_agendamento, sala_inteira, inicio_em, fim_em, status FROM agendamento
                      WHERE id_sala = ? ORDER BY id_agendamento DESC LIMIT 20");
$ag->execute([$pc['id_sala']]);

json_out(['sala' => $pc['identificacao'], 'agora' => date('c'),
          'maquinas' => $st->fetchAll(), 'agendamentos' => $ag->fetchAll()]);
