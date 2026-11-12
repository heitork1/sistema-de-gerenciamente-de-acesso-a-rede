# gerente-snmp (esqueleto)

    mvn package
    export DB_URL=jdbc:postgresql://localhost:5432/acesso DB_USER=... DB_PASS=...
    java -jar target/gerente-snmp.jar bloquear 42

## Fluxo
1. O PHP grava `agendamento` + `agendamento_alvo` e, se for imediato, executa `bloquear <id>`.
2. O PHP adiciona ao crontab (e salva em `agendamento.cron_id`):
   - início agendado: `0 15 11 11 * java -jar /opt/gerente/gerente-snmp.jar bloquear 42`
   - fim:             `50 15 11 11 * java -jar /opt/gerente/gerente-snmp.jar desbloquear 42`
3. O gerente faz SNMP SET em `ifAdminStatus.<if_index>` (1 = up, 2 = down), grava `log_snmp` e atualiza `computador.acesso_internet`.
4. O front end consulta `computador` a cada poucos segundos (polling/AJAX) para atualizar os ícones.
