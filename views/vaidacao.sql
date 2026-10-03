USE final_projecto;

-- 1. Garante a coluna profile_pic na tabela users
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) NOT NULL DEFAULT 'default-profile-pic.jpg';

-- ==========================================
-- CRIAÇÃO DAS VIEWS (CORRIGIDAS)
-- ==========================================

-- View 1: Perfis de Utilizadores
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW user_profiles AS
SELECT
    id AS user_id,
    username,
    email,
    role,
    profile_pic
FROM users;

-- View 2: Detalhes e Disponibilidade dos Eventos
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW event_details AS
SELECT
    e.id AS event_id,
    e.nome AS event_name,
    e.descricao AS description,
    e.data AS date,
    e.hora AS time,
    e.local AS venue,
    e.capacidade AS capacity,
    COALESCE(ticket_counts.total_tickets, 0) AS total_tickets,
    COALESCE(ticket_counts.sold_tickets, 0) AS sold_tickets,
    GREATEST(
        COALESCE(ticket_counts.total_tickets, 0) - COALESCE(ticket_counts.sold_tickets, 0),
        0
    ) AS available_tickets
FROM eventos AS e
LEFT JOIN (
    SELECT
        b.evento_id,
        COUNT(b.id) AS total_tickets,
        COUNT(v.id) AS sold_tickets
    FROM bilhetes AS b
    LEFT JOIN vendas AS v ON v.bilhete_id = b.id
    GROUP BY b.evento_id
) AS ticket_counts ON ticket_counts.evento_id = e.id;

-- View 3: Resumo de Vendas por Evento (Faturação)
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW event_sales_summary AS
SELECT
    e.id AS event_id,
    e.nome AS event_name,
    COUNT(v.id) AS number_of_tickets_sold,
    COALESCE(
        SUM(CASE WHEN v.id IS NOT NULL THEN b.preco ELSE 0 END),
        0.00
    ) AS total_revenue
FROM eventos AS e
LEFT JOIN bilhetes AS b ON b.evento_id = e.id
LEFT JOIN vendas AS v ON v.bilhete_id = b.id
GROUP BY e.id, e.nome;

-- View 4: Resumo de Compras por Cliente (CORRIGIDA a junção pelo user_id)
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW customer_sales_summary AS
SELECT
    c.id AS customer_id,
    c.nome AS customer_name,
    c.email AS customer_email,
    COUNT(v.id) AS number_of_tickets_purchased,
    COALESCE(SUM(b.preco), 0.00) AS total_spent
FROM clientes AS c
LEFT JOIN vendas AS v ON v.user_id = c.user_id -- Corrigido para ligar via user_id
LEFT JOIN bilhetes AS b ON b.id = v.bilhete_id
GROUP BY c.id, c.nome, c.email;

-- View 5: Estado de Todos os Bilhetes
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW tickets_status AS
SELECT
    b.id AS ticket_id,
    e.nome AS event_name,
    b.preco AS price,
    b.numero_assento AS seat_number,
    CASE WHEN v.id IS NULL THEN 'Available' ELSE 'Sold' END AS status
FROM bilhetes AS b
JOIN eventos AS e ON e.id = b.evento_id
LEFT JOIN vendas AS v ON v.bilhete_id = b.id;


-- ==========================================
-- CONSULTAS ÚTEIS SOBRE AS VIEWS (PARA O PROJETO)
-- ==========================================

-- Consulta A: Mostrar os 3 eventos que mais faturaram dinheiro
SELECT event_name, number_of_tickets_sold, total_revenue 
FROM event_sales_summary 
ORDER BY total_revenue DESC 
LIMIT 3;

-- Consulta B: Listar apenas bilhetes disponíveis para venda no evento 'MEO Marés Vivas'
SELECT ticket_id, price, seat_number 
FROM tickets_status 
WHERE event_name = 'MEO Marés Vivas' AND status = 'Available';

-- Consulta C: Ranking de clientes que mais gastaram na plataforma
SELECT customer_name, customer_email, number_of_tickets_purchased, total_spent 
FROM customer_sales_summary 
ORDER BY total_spent DESC;


-- ==========================================
-- TESTES DE VALIDAÇÃO AUTOMÁTICOS
-- ==========================================

-- Validar existência da coluna profile_pic
SELECT
    CASE WHEN EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'final_projecto'
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'profile_pic'
    ) THEN 'PASS' ELSE 'FAIL' END AS profile_pic_column;

-- Validar criação das 5 views
SELECT
    CASE WHEN COUNT(*) = 5 THEN 'PASS' ELSE 'FAIL' END AS views_created
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = 'final_projecto'
  AND TABLE_NAME IN (
        'user_profiles',
        'event_details',
        'event_sales_summary',
        'customer_sales_summary',
        'tickets_status'
  );

-- Contagem de linhas retornadas por cada view
SELECT 'user_profiles' AS view_name, COUNT(*) AS rows_returned FROM user_profiles
UNION ALL
SELECT 'event_details', COUNT(*) FROM event_details
UNION ALL
SELECT 'event_sales_summary', COUNT(*) FROM event_sales_summary
UNION ALL
SELECT 'customer_sales_summary', COUNT(*) FROM customer_sales_summary
UNION ALL
SELECT 'tickets_status', COUNT(*) FROM tickets_status;

-- Validar integridade do perfil
SELECT
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END AS profile_pic_values
FROM user_profiles
WHERE profile_pic IS NULL OR profile_pic = '';

-- Validar contagem lógica de bilhetes
SELECT
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END AS ticket_counts
FROM event_details
WHERE sold_tickets > total_tickets OR available_tickets < 0;

-- Validar valores de status válidos
SELECT
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END AS ticket_status_values
FROM tickets_status
WHERE status NOT IN ('Available', 'Sold');