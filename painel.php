<?php
require __DIR__ . '/lib.php';
[$pc, ] = exigir_acesso(false);   // sem máquina autorizada: 403; sem login: vai para login.php
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Controle de Acesso à Internet</title>
<style>
body{margin:0;font-family:Inter,system-ui,sans-serif;color:#14181F;background:#F4F6F9}
.bar{display:flex;align-items:center;justify-content:space-between;min-height:64px;padding:0 32px;background:#fff;border-bottom:1px solid #DDE2EA;font-weight:700}
.wrap{display:flex;gap:24px;padding:24px 32px;align-items:flex-start;flex-wrap:wrap}.main{flex:1;min-width:520px;display:flex;flex-direction:column;gap:20px}
.card{background:#fff;border:1px solid #DDE2EA;border-radius:12px;padding:20px}
.btn{min-height:44px;padding:0 18px;border-radius:8px;border:1px solid #1F4FA8;background:#1F4FA8;color:#fff;font:600 14px inherit;cursor:pointer}
.btn.sec{background:#fff;color:#1F4FA8}.btn.red{background:#B42318;border-color:#B42318}
.chip{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600;white-space:nowrap}
.ok{background:#E3F4EA;color:#14693A}.bad{background:#FDE8E6;color:#9B1C12}.warn{background:#FFF1D6;color:#6B3F00}.mute{background:#E9EDF3;color:#3C4757}
.dot{width:8px;height:8px;border-radius:50%;background:currentColor}
input,select{min-height:44px;border:1px solid #8A95A6;border-radius:8px;padding:0 12px;font:14px inherit;box-sizing:border-box;width:100%;background:#fff}
input[type=checkbox],input[type=radio]{width:18px;min-height:18px;margin:0}
label{display:block;font-size:13px;font-weight:600;margin:0 0 6px;color:#3C4757}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
.pc{background:#fff;border:1px solid #DDE2EA;border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:10px}
.pc.b{background:#FFF7F6;border-color:#F0B3AC}.pc.p{background:#F4F6F9;border-style:dashed}
.m{font-size:12px;color:#4A5565;line-height:1.5}.mono{font-family:ui-monospace,Menlo,monospace}
.row{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
table{width:100%;border-collapse:collapse;font-size:13px}th{text-align:left;padding:10px 12px;border-bottom:1px solid #DDE2EA;color:#4A5565}td{padding:10px 12px;border-bottom:1px solid #EEF1F5}
.side{width:340px;display:flex;flex-direction:column;gap:16px}h2,h3,p{margin:0}
</style></head><body>
<div class="bar"><span>Controle de Acesso à Internet</span>
  <span class="row"><span class="chip mute">Sala <?= htmlspecialchars($pc['identificacao']) ?></span><a href="logout.php">Sair</a></span></div>
<div class="wrap">
 <div class="main">
  <div class="row"><h2 style="font-size:22px">Sala <?= htmlspecialchars($pc['identificacao']) ?></h2><div id="resumo" class="row"></div></div>
  <div class="card row" style="padding:12px 16px">
    <label style="display:flex;gap:8px;align-items:center;margin:0"><input type="checkbox" id="todas">Selecionar todas as máquinas</label>
    <div class="row"><button class="btn red" id="bSala">Bloquear sala inteira</button><button class="btn sec" id="lSala">Liberar sala inteira</button></div></div>
  <div class="grid" id="grid"></div>
  <p class="m"><span id="live" class="chip mute"><span class="dot"></span>Ao vivo</span> Atualização a cada 5 s · última: <span id="ultima">—</span></p>
  <div class="card"><h3 style="font-size:17px;margin-bottom:8px">Agendamentos</h3>
    <table><thead><tr><th>ID</th><th>Alvo</th><th>Início</th><th>Desbloqueio</th><th>Status</th><th></th></tr></thead><tbody id="ags"></tbody></table></div>
 </div>
 <form class="card side" id="form">
  <h3 style="font-size:17px">Novo bloqueio</h3>
  <div><label for="alvo">Alvo</label><select id="alvo" name="alvo"><option value="selecionadas">Máquinas selecionadas</option><option value="sala">Sala inteira</option></select></div>
  <div><label>Quando</label><div class="row" style="justify-content:flex-start;gap:16px">
    <label style="display:flex;gap:8px;align-items:center;font-weight:500"><input type="radio" name="quando" value="agora" checked>Agora</label>
    <label style="display:flex;gap:8px;align-items:center;font-weight:500"><input type="radio" name="quando" value="agendar">Agendar</label></div></div>
  <div><label for="inicio">Início</label><input type="datetime-local" id="inicio" name="inicio" disabled></div>
  <div><label for="duracao">Duração até o desbloqueio</label><div class="row" style="flex-wrap:nowrap"><input id="duracao" name="duracao" type="number" min="1" value="50"><select name="unidade" id="unidade"><option value="minutos">minutos</option><option value="horas">horas</option><option value="dias">dias</option></select></div></div>
  <p class="m">Desbloqueio previsto: <b id="fim">—</b></p>
  <button class="btn red" type="submit">Aplicar bloqueio</button>
  <p class="m" id="msg" role="status"></p>
 </form>
</div>
<script>
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const t = v => v === true || v === 't';                       // boolean do PDO pode vir como 't'/'f'
const fmt = v => v ? new Date(v).toLocaleString('pt-BR', {dateStyle:'short', timeStyle:'short'}) : '—';
const sel = new Set(); let dados = null;

async function post(url, fd) {
  const r = await fetch(url, {method:'POST', body: fd});
  const j = await r.json().catch(() => ({erro:'Resposta inválida'}));
  if (r.status === 401 || r.status === 403) location = 'login.php';
  return j;
}
function aviso(j) { $('#msg').textContent = j.erro ? 'Erro: ' + j.erro : 'Pedido registrado (#' + (j.id_agendamento ?? '') + ').'; carregar(); }

async function carregar() {
  try {
    const r = await fetch('status.php');
    if (r.status === 401 || r.status === 403) { location = 'login.php'; return; }
    dados = await r.json(); desenhar();
    $('#live').className = 'chip ok'; $('#ultima').textContent = new Date().toLocaleTimeString('pt-BR');
  } catch (e) { $('#live').className = 'chip bad'; }
}

function desenhar() {
  const alunos = dados.maquinas.filter(m => m.categoria === 'aluno');
  const bloq = alunos.filter(m => !t(m.acesso_internet)).length;
  $('#resumo').innerHTML = `<span class="chip ok"><span class="dot"></span>${alunos.length - bloq} liberadas</span>
    <span class="chip bad"><span class="dot"></span>${bloq} bloqueadas</span>
    <span class="chip mute">${dados.maquinas.length - alunos.length} protegidas</span>`;
  $('#grid').innerHTML = dados.maquinas.map(m => {
    const id = Number(m.id_pc);
    if (m.categoria !== 'aluno') return `<div class="pc p"><div class="row"><b>${esc(m.nome)}</b><span class="chip mute">Protegida</span></div>
      <div class="m mono">${esc(m.ip)}<br>Porta ${esc(m.porta)}</div><div class="m">Nunca é bloqueada.</div></div>`;
    const b = !t(m.acesso_internet);
    return `<div class="pc${b ? ' b' : ''}"><div class="row"><label style="display:flex;gap:8px;align-items:center;margin:0;color:#14181F">
      <input type="checkbox" data-id="${id}" ${sel.has(id) ? 'checked' : ''} aria-label="Selecionar ${esc(m.nome)}">${esc(m.nome)}</label>
      <span class="chip ${b ? 'bad' : 'ok'}"><span class="dot"></span>${b ? 'Bloqueada' : 'Liberada'}</span></div>
      <div class="m mono">${esc(m.ip)}<br>${esc(m.mac)}<br>Switch ${esc(m.switch_ip)} · ${esc(m.porta)}</div>
      <div class="m">${b && m.libera_em ? 'Libera em ' + fmt(m.libera_em) : '&nbsp;'}</div>
      ${b ? `<button class="btn sec" data-lib="${m.id_agend_ativo}">Desbloquear</button>` : `<button class="btn red" data-blq="${id}">Bloquear</button>`}</div>`;
  }).join('');
  $('#ags').innerHTML = dados.agendamentos.map(a => {
    const cls = {agendado:'warn', ativo:'bad', concluido:'ok', cancelado:'mute', erro:'bad'}[a.status] || 'mute';
    const acao = ['agendado','ativo'].includes(a.status) ? `<button class="btn sec" data-lib="${a.id_agendamento}">${a.status === 'ativo' ? 'Desbloquear agora' : 'Cancelar'}</button>` : '';
    return `<tr><td class="mono">#${a.id_agendamento}</td><td>${t(a.sala_inteira) ? 'Sala inteira' : 'Selecionadas'}</td><td>${fmt(a.inicio_em)}</td><td>${fmt(a.fim_em)}</td><td><span class="chip ${cls}">${esc(a.status)}</span></td><td>${acao}</td></tr>`;
  }).join('');
  $('#todas').checked = alunos.length > 0 && alunos.every(m => sel.has(Number(m.id_pc)));
}

async function enviar(alvo, ids) {
  const fd = new FormData($('#form')); fd.set('alvo', alvo);
  if (alvo === 'selecionadas') ids.forEach(i => fd.append('ids[]', i));
  aviso(await post('bloquear.php', fd));
}
async function liberar(id) { const fd = new FormData(); fd.set('id_agendamento', id); aviso(await post('cancelar.php', fd)); }

$('#grid').addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b) return;
  if (b.dataset.blq) enviar('selecionadas', [b.dataset.blq]);
  if (b.dataset.lib) liberar(b.dataset.lib);
});
$('#grid').addEventListener('change', e => { const id = Number(e.target.dataset.id); if (id) { e.target.checked ? sel.add(id) : sel.delete(id); } });
$('#ags').addEventListener('click', e => { const b = e.target.closest('button'); if (b) liberar(b.dataset.lib); });
$('#todas').addEventListener('change', e => {
  dados.maquinas.filter(m => m.categoria === 'aluno').forEach(m => e.target.checked ? sel.add(Number(m.id_pc)) : sel.delete(Number(m.id_pc))); desenhar();
});
$('#bSala').onclick = () => enviar('sala', []);
$('#lSala').onclick = async () => {   // cancela/encerra todos os agendamentos ativos ou futuros da sala
  for (const a of dados.agendamentos.filter(a => ['agendado','ativo'].includes(a.status))) await liberar(a.id_agendamento);
};
$('#form').addEventListener('submit', e => { e.preventDefault(); enviar($('#alvo').value, [...sel]); });
document.querySelectorAll('[name=quando]').forEach(r => r.addEventListener('change', () => {
  $('#inicio').disabled = $('[name=quando]:checked').value === 'agora'; fim();
}));
function fim() {
  const n = Number($('#duracao').value), u = {minutos:60e3, horas:36e5, dias:864e5}[$('#unidade').value];
  const ini = $('[name=quando]:checked').value === 'agora' || !$('#inicio').value ? new Date() : new Date($('#inicio').value);
  $('#fim').textContent = n > 0 ? fmt(new Date(ini.getTime() + n * u)) : '—';
}
['#duracao', '#unidade', '#inicio'].forEach(s => $(s).addEventListener('input', fim));
fim(); carregar(); setInterval(carregar, 5000);
</script></body></html>
