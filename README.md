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
Fase 0: Planejamento da rede (antes de instalar)
Defina onde fica o servidor (web, banco, gerente e cron na mesma máquina). Ele precisa alcançar o IP de gerência dos switches e ser alcançado pelo PC do professor.
Fixe o IP de cada PC da sala, por IP estático ou reserva no DHCP. A autorização compara o IP com o banco.
Mapeie os cabos: anote qual PC está em qual porta de qual switch. Se o switch tiver CLI, show mac address-table ajuda a confirmar. Um erro aqui bloqueia a máquina errada.
Identifique as portas protegidas: a do PC do professor, a do servidor (data center) e as que ligam um switch a outro.
Ative o SNMP v2c com community de escrita no switch e restrinja ao IP do servidor. Em um Cisco, por exemplo: snmp-server community SUACOMMUNITY RW. A sintaxe varia conforme a marca e o modelo.
Fase 1: Instalar os pacotes
bash
sudo apt install postgresql apache2 php libapache2-mod-php php-pgsql \
     openjdk-17-jdk maven snmp cron iproute2

Verifique: java -version mostra 17 e php -v mostra 8.1 ou superior.

Fase 2: Testar o SNMP no switch (antes de qualquer código)
bash
snmpwalk -v2c -c <community> <ip_switch> 1.3.6.1.2.1.2.2.1.2        # lista interfaces e if_index
snmpset  -v2c -c <community> <ip_switch> 1.3.6.1.2.1.2.2.1.7.<N> i 2  # desliga a porta N
snmpset  -v2c -c <community> <ip_switch> 1.3.6.1.2.1.2.2.1.7.<N> i 1  # religa

Use uma porta de um PC de teste. Verifique: o PC perde e recupera o link. Se isso falhar, o resto do projeto também falhará: revise community, IP, firewall e permissão de escrita. Anote o if_index de cada porta do mapeamento da fase 0.

Fase 3: Banco de dados
bash
sudo -u postgres psql -c "CREATE USER acesso WITH PASSWORD 'senha';" \
                      -c "CREATE DATABASE acesso OWNER acesso;"
psql -h localhost -U acesso acesso -f schema_controle_acesso.sql
psql -h localhost -U acesso acesso -f seed.sql

Se o CREATE EXTENSION pgcrypto for recusado, rode o seed como postgres. Verifique: SELECT nome, ip, acesso_internet FROM computador; lista os 9 PCs.

Fase 4: Ajustar o banco à sua rede real

Edite o que veio de exemplo no seed:

switch: IPs e snmp_community_rw.
porta: if_index, nome e categoria de cada porta, conforme as fases 0 e 2.
computador: nome, IP, MAC e id_porta de cada PC.
Senhas dos usuários. Gere um hash novo com php -r 'echo password_hash("NovaSenha", PASSWORD_DEFAULT);' e atualize usuario.senha_hash.

Verifique: a porta do servidor e as dos uplinks estão como datacenter ou uplink, e a do professor como professor. Qualquer erro nessas categorias pode cortar o acesso ao servidor.

Fase 5: Compilar e instalar o gerente SNMP
bash
cd gerente-snmp && mvn package
sudo mkdir -p /opt/gerente /etc/gerente
sudo cp target/gerente-snmp.jar ../web-php/gerente.sh /opt/gerente/
sudo chmod +x /opt/gerente/gerente.sh
printf 'DB_URL=jdbc:postgresql://localhost:5432/acesso\nDB_USER=acesso\nDB_PASS=senha\n' | sudo tee /etc/gerente/env
sudo chown root:www-data /etc/gerente/env && sudo chmod 640 /etc/gerente/env

Usei o SNMP4J 3.7.7, e a API pode ser diferente na versão do Moodle. Se o mvn package falhar, me mande o erro.

Fase 6: Testar o gerente sozinho (sem web)

Crie um agendamento de teste para um PC de aluno (aqui, o id_pc 5):

sql
INSERT INTO agendamento (id_usuario,id_sala,sala_inteira,inicio_em,fim_em)
  VALUES (1,1,false,now(),now()+interval '10 minutes') RETURNING id_agendamento;
INSERT INTO agendamento_alvo (id_agendamento,id_pc) VALUES (<id retornado>,5);

Depois rode, como www-data, para testar as permissões de verdade:

bash
sudo -u www-data /opt/gerente/gerente.sh bloquear <id>
sudo -u www-data /opt/gerente/gerente.sh desbloquear <id>

Verifique: o PC perde e recupera o link, e SELECT * FROM log_snmp ORDER BY id_log DESC LIMIT 5; mostra sucesso = true. Em caso de falha, a coluna mensagem diz o motivo.

Fase 7: Publicar a aplicação web
bash
sudo cp web-php/*.php /var/www/html/
sudo tee /etc/apache2/conf-available/acesso.conf <<'EOF'
SetEnv DB_DSN "pgsql:host=localhost;dbname=acesso"
SetEnv DB_USER acesso
SetEnv DB_PASS senha
EOF
sudo a2enconf acesso && sudo systemctl reload apache2

Confirme que o cron funciona para o www-data: sudo -u www-data crontab -l não deve dar erro de permissão. Se existir /etc/cron.allow, inclua www-data nele.

Se o PHP não enxergar as variáveis, configure-as de outro modo (por exemplo, env[...] no PHP-FPM).

Fase 8: Testar a web, em etapas
Teste local: faça seu IP valer como o do professor com UPDATE computador SET ip='127.0.0.1' WHERE id_pc=9; e abra http://127.0.0.1/login.php, não localhost, que pode resolver para IPv6. Entre com prof.silva.
Bloqueio imediato de um PC pelo painel. O cartão deve ficar vermelho em até 5 s.
Bloqueio agendado para 2 minutos à frente. Veja a linha em sudo -u www-data crontab -l e confirme que ela dispara e some depois.
Desbloqueio pelo cartão e pela tabela de agendamentos, e cancelamento de um agendamento futuro.
Sala inteira: bloqueie e libere. O PC do professor e o servidor devem continuar com rede.
Segurança: abra o sistema de outro PC. Deve aparecer “acesso negado”. Também confira que o login só aceita usuários vinculados à sala.
Fase 9: Na máquina do professor, para a apresentação

Volte o IP do PC do professor ao valor real:

sql
UPDATE computador SET ip='<ip_real_do_professor>' WHERE id_pc=9;

Acesse o servidor pelo IP real dele. Se o servidor não estiver na mesma rede L2, a checagem do MAC não funciona e o sistema só valida por IP.nele.

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
