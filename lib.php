<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('America/Sao_Paulo');
const GERENTE_SH = '/opt/gerente/gerente.sh';   // wrapper que carrega /etc/gerente/env e roda o jar

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(getenv('DB_DSN') ?: 'pgsql:host=localhost;dbname=acesso',
            getenv('DB_USER') ?: 'acesso', getenv('DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }
    return $pdo;
}

function json_out(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

/** MAC do cliente via tabela ARP/ND do servidor (só funciona se o servidor estiver no mesmo domínio L2). */
function mac_do_ip(string $ip): ?string {
    $o = shell_exec('ip neigh show ' . escapeshellarg($ip) . ' 2>/dev/null');
    return ($o && preg_match('/lladdr\s+([0-9a-f:]{17})/i', $o, $m)) ? strtolower($m[1]) : null;
}

/** Devolve o PC do professor se a requisição vier dele (IP + MAC conferem com o banco), senão null. */
function pc_autorizado(): ?array {
    $ip = $_SERVER['REMOTE_ADDR'];
    $st = db()->prepare("SELECT c.id_pc, c.id_sala, c.mac::text AS mac, s.identificacao
                           FROM computador c JOIN porta p USING (id_porta) JOIN sala s ON s.id_sala = c.id_sala
                          WHERE c.ip = ?::inet AND p.categoria = 'professor'");
    $st->execute([$ip]);
    $pc = $st->fetch();
    if (!$pc) return null;
    $mac = mac_do_ip($ip);
    if ($mac !== null && $mac !== strtolower($pc['mac'])) return null;   // IP clonado
    return $pc;
}

/** Exige máquina autorizada + login. Retorna [pc, id_usuario]. */
function exigir_acesso(bool $json = true): array {
    $pc = pc_autorizado();
    if (!$pc) { $json ? json_out(['erro' => 'Computador não autorizado'], 403) : exit(http_response_code(403) ?: 'Acesso negado'); }
    $u = $_SESSION['id_usuario'] ?? null;
    if ($u) {
        $st = db()->prepare('SELECT 1 FROM usuario_sala WHERE id_usuario = ? AND id_sala = ?');
        $st->execute([$u, $pc['id_sala']]);
        if ($st->fetch()) return [$pc, (int)$u];
    }
    $json ? json_out(['erro' => 'Login necessário'], 401) : header('Location: login.php');
    exit;
}

// ---------- crontab do usuário do servidor web ----------
function cron_ler(): string { return shell_exec('crontab -l 2>/dev/null') ?? ''; }
function cron_gravar(string $conteudo): void {
    $p = proc_open('crontab -', [0 => ['pipe', 'r']], $pipes);
    fwrite($pipes[0], rtrim($conteudo) . "\n");
    fclose($pipes[0]);
    proc_close($p);
}
/** Linha única de execução; ela mesma se remove do crontab depois de rodar (cron não tem ano). */
function cron_agendar(DateTimeImmutable $quando, string $acao, int $id): void {
    $q = $quando->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $marca = "agend:$id:$acao#";
    $linha = sprintf('%d %d %d %d * %s %s %d; (crontab -l | grep -v "%s" | crontab -) # %s',
        (int)$q->format('i'), (int)$q->format('G'), (int)$q->format('j'), (int)$q->format('n'),
        GERENTE_SH, $acao, $id, $marca, $marca);
    cron_gravar(cron_ler() . "\n" . $linha);
}
function cron_remover(int $id): void {
    $linhas = array_filter(explode("\n", cron_ler()), fn($l) => !str_contains($l, "agend:$id:"));
    cron_gravar(implode("\n", $linhas));
}
function executar_agora(string $acao, int $id): void {
    exec(sprintf('nohup %s %s %d >/dev/null 2>&1 &', GERENTE_SH, escapeshellarg($acao), $id));
}
