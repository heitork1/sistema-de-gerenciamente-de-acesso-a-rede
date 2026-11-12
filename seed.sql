-- Dados de exemplo para a demonstração (rode depois de schema_controle_acesso.sql)
-- Sala F203, 2 switches, 8 PCs de alunos, 1 PC do professor, 1 usuário professor.
CREATE EXTENSION IF NOT EXISTS pgcrypto;   -- crypt()/gen_salt(): hashes bcrypt aceitos pelo password_verify do PHP

INSERT INTO sala (id_sala, identificacao) VALUES (1, 'F203');

-- ATENÇÃO: troque a community pelo valor real configurado nos switches
INSERT INTO switch (id_switch, ip, descricao, snmp_porta, snmp_community_rw) VALUES
  (1, '10.0.2.2', 'Switch F203 - principal', 161, 'privado'),
  (2, '10.0.2.3', 'Switch F203 - secundário', 161, 'privado');
INSERT INTO sala_switch (id_sala, id_switch) VALUES (1, 1), (1, 2);

-- if_index = índice da interface na MIB-II. Confirme com:
--   snmpwalk -v2c -c <community> <ip_switch> 1.3.6.1.2.1.2.2.1.2
INSERT INTO porta (id_porta, id_switch, if_index, nome, categoria, id_switch_destino) VALUES
  ( 1, 1,  1, 'Fa0/1',  'aluno',      NULL),
  ( 2, 1,  2, 'Fa0/2',  'aluno',      NULL),
  ( 3, 1,  3, 'Fa0/3',  'aluno',      NULL),
  ( 4, 1,  4, 'Fa0/4',  'aluno',      NULL),
  ( 5, 1,  5, 'Fa0/5',  'aluno',      NULL),
  ( 6, 1, 24, 'Fa0/24', 'professor',  NULL),
  ( 7, 1, 25, 'Gi0/1',  'datacenter', NULL),   -- servidor web / data center
  ( 8, 1, 26, 'Gi0/2',  'uplink',     2),      -- liga ao switch 2
  ( 9, 2,  1, 'Fa0/1',  'aluno',      NULL),
  (10, 2,  2, 'Fa0/2',  'aluno',      NULL),
  (11, 2,  3, 'Fa0/3',  'aluno',      NULL),
  (12, 2, 25, 'Gi0/1',  'uplink',     1);      -- liga ao switch 1

INSERT INTO computador (id_pc, id_sala, id_porta, nome, ip, mac) VALUES
  (1, 1,  1, 'LAB-F203-01', '10.0.2.11', 'AA:BB:CC:00:00:01'),
  (2, 1,  2, 'LAB-F203-02', '10.0.2.12', 'AA:BB:CC:00:00:02'),
  (3, 1,  3, 'LAB-F203-03', '10.0.2.13', 'AA:BB:CC:00:00:03'),
  (4, 1,  4, 'LAB-F203-04', '10.0.2.14', 'AA:BB:CC:00:00:04'),
  (5, 1,  5, 'LAB-F203-05', '10.0.2.16', 'AA:BB:CC:00:00:05'),
  (6, 1,  9, 'LAB-F203-06', '10.0.2.17', 'AA:BB:CC:00:00:06'),
  (7, 1, 10, 'LAB-F203-07', '10.0.2.18', 'AA:BB:CC:00:00:07'),
  (8, 1, 11, 'LAB-F203-08', '10.0.2.19', 'AA:BB:CC:00:00:08'),
  (9, 1,  6, 'PC-PROFESSOR-F203', '10.0.2.15', 'AA:BB:CC:00:10:15');   -- máquina autorizada (porta 'professor')

-- Senhas de exemplo: troque antes de usar de verdade
INSERT INTO usuario (id_usuario, login, senha_hash, nome, perfil) VALUES
  (1, 'prof.silva', crypt('professor123', gen_salt('bf')), 'Prof. Silva', 'professor'),
  (2, 'admin',      crypt('admin123',     gen_salt('bf')), 'Administrador', 'admin');
INSERT INTO usuario_sala (id_usuario, id_sala) VALUES (1, 1);

-- Ajusta as sequências, já que os ids foram inseridos manualmente
SELECT setval(pg_get_serial_sequence('sala','id_sala'),       (SELECT max(id_sala)    FROM sala));
SELECT setval(pg_get_serial_sequence('switch','id_switch'),   (SELECT max(id_switch)  FROM switch));
SELECT setval(pg_get_serial_sequence('porta','id_porta'),     (SELECT max(id_porta)   FROM porta));
SELECT setval(pg_get_serial_sequence('computador','id_pc'),   (SELECT max(id_pc)      FROM computador));
SELECT setval(pg_get_serial_sequence('usuario','id_usuario'), (SELECT max(id_usuario) FROM usuario));
