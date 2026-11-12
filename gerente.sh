#!/bin/bash
# Wrapper usado pelo PHP e pelo crontab (o cron não tem as variáveis de ambiente).
# /etc/gerente/env:  DB_URL=jdbc:postgresql://localhost:5432/acesso  DB_USER=...  DB_PASS=...
set -a; . /etc/gerente/env; set +a
exec java -jar /opt/gerente/gerente-snmp.jar "$@"
