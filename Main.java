package br.udesc.gerente;

import br.udesc.gerente.GerenteSnmp.Acao;
import java.util.List;

/**
 * Uso: java -jar gerente-snmp.jar <bloquear|desbloquear> <id_agendamento>
 * Chamado pelo script PHP (bloqueio imediato) e pelo crontab (início e fim agendados).
 * Variáveis de ambiente: DB_URL, DB_USER, DB_PASS
 */
public class Main {
    public static void main(String[] args) throws Exception {
        if (args.length != 2) {
            System.err.println("uso: gerente-snmp <bloquear|desbloquear> <id_agendamento>");
            System.exit(1);
        }
        Acao acao = Acao.valueOf(args[0].toUpperCase());
        int idAgend = Integer.parseInt(args[1]);

        try (Repositorio repo = new Repositorio(
                    System.getenv("DB_URL"), System.getenv("DB_USER"), System.getenv("DB_PASS"));
             GerenteSnmp snmp = new GerenteSnmp()) {

            // TODO: se acao == DESBLOQUEAR e o agendamento estiver 'cancelado', ainda assim liberar as portas
            // TODO: se acao == BLOQUEAR e o agendamento não estiver 'agendado', abortar
            List<Repositorio.Alvo> alvos = repo.alvos(idAgend);
            boolean tudoOk = true;

            for (Repositorio.Alvo alvo : alvos) {
                GerenteSnmp.Resultado r = snmp.set(alvo, acao);
                repo.registrarLog(idAgend, alvo, acao, r);
                if (r.sucesso()) repo.atualizarPc(alvo.idPc(), acao == Acao.DESBLOQUEAR);
                else tudoOk = false;
            }

            String status = !tudoOk ? "erro" : (acao == Acao.BLOQUEAR ? "ativo" : "concluido");
            repo.atualizarStatus(idAgend, status);
            System.out.printf("agendamento %d: %s %d porta(s) -> %s%n", idAgend, acao, alvos.size(), status);
            System.exit(tudoOk ? 0 : 2);
        }
    }
}
