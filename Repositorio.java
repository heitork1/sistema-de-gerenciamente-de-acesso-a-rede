package br.udesc.gerente;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

/** Acesso ao PostgreSQL (schema_controle_acesso.sql). */
public class Repositorio implements AutoCloseable {

    public record Alvo(int idPc, int idPorta, String switchIp, int snmpPorta, String community, int ifIndex) {}

    private final Connection con;

    public Repositorio(String url, String user, String pass) throws SQLException {
        con = DriverManager.getConnection(url, user, pass);
    }

    /** Só devolve portas bloqueáveis (categoria 'aluno'): professor/uplink/datacenter nunca entram. */
    public List<Alvo> alvos(int idAgendamento) throws SQLException {
        String sql = """
            SELECT c.id_pc, p.id_porta, host(s.ip) AS ip, s.snmp_porta, s.snmp_community_rw, p.if_index
              FROM agendamento_alvo aa
              JOIN computador c ON c.id_pc = aa.id_pc
              JOIN porta p      ON p.id_porta = c.id_porta
              JOIN switch s     ON s.id_switch = p.id_switch
             WHERE aa.id_agendamento = ? AND p.bloqueavel
            """;
        List<Alvo> r = new ArrayList<>();
        try (PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setInt(1, idAgendamento);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next())
                    r.add(new Alvo(rs.getInt(1), rs.getInt(2), rs.getString(3), rs.getInt(4), rs.getString(5), rs.getInt(6)));
            }
        }
        return r;
    }

    public void registrarLog(int idAgend, Alvo a, GerenteSnmp.Acao acao, GerenteSnmp.Resultado res) throws SQLException {
        String sql = "INSERT INTO log_snmp (id_agendamento,id_porta,acao,oid,valor,sucesso,mensagem) VALUES (?,?,?::acao_tipo,?,?,?,?)";
        try (PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setInt(1, idAgend);
            ps.setInt(2, a.idPorta());
            ps.setString(3, acao.name().toLowerCase());
            ps.setString(4, res.oid());
            ps.setInt(5, acao.valor);
            ps.setBoolean(6, res.sucesso());
            ps.setString(7, res.mensagem());
            ps.executeUpdate();
        }
    }

    /** Atualiza o estado que o front end lê para trocar o ícone (porta aberta/fechada). */
    public void atualizarPc(int idPc, boolean acessoInternet) throws SQLException {
        try (PreparedStatement ps = con.prepareStatement(
                "UPDATE computador SET acesso_internet=?, atualizado_em=now() WHERE id_pc=?")) {
            ps.setBoolean(1, acessoInternet);
            ps.setInt(2, idPc);
            ps.executeUpdate();
        }
    }

    public void atualizarStatus(int idAgend, String status) throws SQLException {
        try (PreparedStatement ps = con.prepareStatement(
                "UPDATE agendamento SET status=?::status_agend WHERE id_agendamento=?")) {
            ps.setString(1, status);
            ps.setInt(2, idAgend);
            ps.executeUpdate();
        }
    }

    @Override public void close() throws SQLException { con.close(); }
}
