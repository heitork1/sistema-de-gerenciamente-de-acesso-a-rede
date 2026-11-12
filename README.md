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

COMO INICIAR

Estes passos assumem Ubuntu ou Debian. 

## 1. Instalar os pacotes
```bash
sudo apt install postgresql apache2 php libapache2-mod-php php-pgsql \
     openjdk-17-jdk maven snmp cron iproute2
```

## 2. Criar o banco
```bash
sudo -u postgres psql -c "CREATE USER acesso WITH PASSWORD 'senha';" \
                      -c "CREATE DATABASE acesso OWNER acesso;"
psql -h localhost -U acesso acesso -f schema_controle_acesso.sql
psql -h localhost -U acesso acesso -f seed.sql
```

## 3. Compilar e instalar o gerente SNMP
```bash
cd gerente-snmp && mvn package
sudo mkdir -p /opt/gerente /etc/gerente
sudo cp target/gerente-snmp.jar ../web-php/gerente.sh /opt/gerente/
sudo chmod +x /opt/gerente/gerente.sh
printf 'DB_URL=jdbc:postgresql://localhost:5432/acesso\nDB_USER=acesso\nDB_PASS=senha\n' | sudo tee /etc/gerente/env
sudo chown root:www-data /etc/gerente/env && sudo chmod 640 /etc/gerente/env
```

## 4. Publicar o PHP
```bash
sudo cp web-php/*.php /var/www/html/
sudo tee /etc/apache2/conf-available/acesso.conf <<'EOF'
SetEnv DB_DSN "pgsql:host=localhost;dbname=acesso"
SetEnv DB_USER acesso
SetEnv DB_PASS senha
EOF
sudo a2enconf acesso && sudo systemctl reload apache2
```
Confirme que o `www-data` consegue usar o `crontab`: `sudo -u www-data crontab -l` não deve dar erro de permissão. Se existir `/etc/cron.allow`, inclua `www-data` nele.

## 5. Testar o SNMP antes de tudo
Com o switch na rede, use a community e o `if_index` reais:
```bash
snmpwalk -v2c -c <community> 10.0.2.2 1.3.6.1.2.1.2.2.1.2      # descobrir if_index
snmpset  -v2c -c <community> 10.0.2.2 1.3.6.1.2.1.2.2.1.7.5 i 2  # desliga a porta 5
snmpset  -v2c -c <community> 10.0.2.2 1.3.6.1.2.1.2.2.1.7.5 i 1  # religa
```
Depois corrija `if_index`, IPs, MACs e a community no banco, como descrito no manual.

## 6. Abrir o sistema
O acesso só funciona se o IP de quem abre for o do PC do professor no banco. Para testar na sua própria máquina:
```bash
psql -h localhost -U acesso acesso -c "UPDATE computador SET ip='127.0.0.1' WHERE id_pc=9;"
```
Abra `http://localhost/login.php` e entre com `prof.silva` / `professor123`. Na apresentação, volte esse IP para o do PC real do professor.

## Observações
- **Sem switch real**, os bloqueios vão falhar por timeout. O agendamento fica com status `erro` e a mensagem aparece em `log_snmp`. Isso ainda serve para testar telas, login e crontab, mas o bloqueio de verdade exige o switch (ou um simulador de agente SNMP, que não testei).
- **Para a demonstração**, o enunciado pede o switch e pelo menos 3 máquinas: o servidor web, a máquina do professor e uma de aluno cujo bloqueio possa ser observado.
- **Diagnóstico**: se algo não funcionar, olhe `log_snmp`, `crontab -l` (como `www-data`) e `/var/log/apache2/error.log`.

Se alguma etapa falhar, me mande a mensagem de erro que eu ajudo a corrigir.
