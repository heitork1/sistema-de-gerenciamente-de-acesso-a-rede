package br.udesc.gerente;

import java.io.IOException;
import java.net.InetAddress;
import org.snmp4j.CommunityTarget;
import org.snmp4j.PDU;
import org.snmp4j.Snmp;
import org.snmp4j.event.ResponseEvent;
import org.snmp4j.mp.SnmpConstants;
import org.snmp4j.smi.Integer32;
import org.snmp4j.smi.OID;
import org.snmp4j.smi.OctetString;
import org.snmp4j.smi.UdpAddress;
import org.snmp4j.smi.VariableBinding;
import org.snmp4j.transport.DefaultUdpTransportMapping;

/** Envia SNMP SET em ifAdminStatus (MIB-II) para ligar/desligar uma porta do switch. */
public class GerenteSnmp implements AutoCloseable {

    /** ifAdminStatus: 1 = up (liberar), 2 = down (bloquear). */
    public enum Acao {
        BLOQUEAR(2), DESBLOQUEAR(1);
        public final int valor;
        Acao(int valor) { this.valor = valor; }
    }

    public record Resultado(boolean sucesso, String oid, String mensagem) {}

    private static final String IF_ADMIN_STATUS = "1.3.6.1.2.1.2.2.1.7.";
    private final Snmp snmp;

    public GerenteSnmp() throws IOException {
        snmp = new Snmp(new DefaultUdpTransportMapping());
        snmp.listen();
    }

    public Resultado set(Repositorio.Alvo alvo, Acao acao) {
        String oid = IF_ADMIN_STATUS + alvo.ifIndex();
        try {
            CommunityTarget<UdpAddress> t = new CommunityTarget<>();
            t.setCommunity(new OctetString(alvo.community()));
            t.setAddress(new UdpAddress(InetAddress.getByName(alvo.switchIp()), alvo.snmpPorta()));
            t.setVersion(SnmpConstants.version2c);
            t.setRetries(2);
            t.setTimeout(1500);

            PDU pdu = new PDU();
            pdu.setType(PDU.SET);
            pdu.add(new VariableBinding(new OID(oid), new Integer32(acao.valor)));

            ResponseEvent<UdpAddress> ev = snmp.send(pdu, t);
            PDU resp = ev.getResponse();
            if (resp == null) return new Resultado(false, oid, "timeout (sem resposta do agente)");
            if (resp.getErrorStatus() != PDU.noError)
                return new Resultado(false, oid, resp.getErrorStatusText());
            // TODO: opcional - fazer um GET em oid e conferir se o valor realmente mudou
            return new Resultado(true, oid, "ok");
        } catch (Exception e) {
            return new Resultado(false, oid, e.toString());
        }
    }

    @Override public void close() throws IOException { snmp.close(); }
}
