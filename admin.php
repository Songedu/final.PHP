<?php
require_once __DIR__ . '/conexao.php';
ensure_admin();

if (!function_exists('admin_escape')) {
    function admin_escape($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$pdo = db();
$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_notice'] = ['type' => 'danger', 'text' => 'A sessão expirou. Atualize a página e tente novamente.'];
        header('Location: admin.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_event') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $eventDate = $_POST['event_date'] ?? '';
            $eventTime = $_POST['event_time'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT);
            $ticketCount = filter_var($_POST['ticket_count'] ?? null, FILTER_VALIDATE_INT);
            $price = $_POST['ticket_price'] ?? '';
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDate);

            if ($title === '' || mb_strlen($title) > 150 || $description === '' || $location === '' || mb_strlen($location) > 200) {
                throw new RuntimeException('Preencha título, descrição e local. O título e local não podem exceder o limite permitido.');
            }
            if (!$date || $date->format('Y-m-d') !== $eventDate || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $eventTime)) {
                throw new RuntimeException('Informe uma data e um horário válidos.');
            }
            if ($capacity === false || $capacity < 1 || $ticketCount === false || $ticketCount < 1 || $ticketCount > $capacity) {
                throw new RuntimeException('A quantidade de bilhetes deve ser positiva e não pode superar a capacidade.');
            }
            if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $price)) {
                throw new RuntimeException('Informe um preço válido, com até duas casas decimais.');
            }

            $imageName = null;
            $uploadedImage = null;
            $upload = $_FILES['image'] ?? null;
            if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException('A imagem deve ter no máximo 5 MB.');
                }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime])) {
                    throw new RuntimeException('Use uma imagem JPG, PNG ou WebP.');
                }
                $uploadDirectory = __DIR__ . '/uploads';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
                }
                $imageName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                $uploadedImage = $uploadDirectory . '/' . $imageName;
                if (!move_uploaded_file($upload['tmp_name'], $uploadedImage)) {
                    throw new RuntimeException('Não foi possível salvar a imagem enviada.');
                }
            }

            try {
                $pdo->beginTransaction();
                $insertEvent = $pdo->prepare(
                    'INSERT INTO eventos (nome, descricao, data, hora, local, capacidade, imagem)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $insertEvent->execute([$title, $description, $eventDate, $eventTime . ':00', $location, $capacity, $imageName]);
                $eventId = (int) $pdo->lastInsertId();

                $insertTicket = $pdo->prepare('INSERT INTO bilhetes (evento_id, preco, numero_assento) VALUES (?, ?, ?)');
                for ($ticketNumber = 1; $ticketNumber <= $ticketCount; $ticketNumber++) {
                    $insertTicket->execute([$eventId, $price, 'B' . $ticketNumber]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($uploadedImage && is_file($uploadedImage)) {
                    unlink($uploadedImage);
                }
                throw $e;
            }

            $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Evento criado com os bilhetes disponíveis.'];
        } elseif ($action === 'delete_event') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $eventQuery = $pdo->prepare('SELECT imagem FROM eventos WHERE id = ?');
            $eventQuery->execute([$eventId]);
            $imageName = $eventQuery->fetchColumn();
            if ($imageName === false) {
                throw new RuntimeException('O evento não foi encontrado.');
            }

            $salesQuery = $pdo->prepare(
                'SELECT COUNT(*) FROM vendas v
                 INNER JOIN bilhetes b ON b.id = v.bilhete_id
                 WHERE b.evento_id = ?'
            );
            $salesQuery->execute([$eventId]);
            if ((int) $salesQuery->fetchColumn() > 0) {
                throw new RuntimeException('Este evento possui vendas e não pode ser excluído para preservar o histórico.');
            }

            $pdo->beginTransaction();
            $deleteTickets = $pdo->prepare('DELETE FROM bilhetes WHERE evento_id = ?');
            $deleteTickets->execute([$eventId]);
            $deleteEvent = $pdo->prepare('DELETE FROM eventos WHERE id = ?');
            $deleteEvent->execute([$eventId]);
            $pdo->commit();

            if ($imageName && basename($imageName) === $imageName) {
                $imagePath = __DIR__ . '/uploads/' . $imageName;
                if (is_file($imagePath)) {
                    unlink($imagePath);
                }
            }
            $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Evento excluído.'];
        } elseif ($action === 'delete_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId < 1 || $userId === (int) $_SESSION['user_id']) {
                throw new RuntimeException('Não é permitido excluir a própria conta.');
            }

            $userQuery = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $userQuery->execute([$userId]);
            $role = $userQuery->fetchColumn();
            if ($role === false) {
                throw new RuntimeException('O usuário não foi encontrado.');
            }
            if ($role === 'admin') {
                throw new RuntimeException('Contas de administrador não podem ser excluídas por este painel.');
            }

            $pdo->beginTransaction();
            $deleteUser = $pdo->prepare("DELETE FROM users WHERE id = ? AND role <> 'admin'");
            $deleteUser->execute([$userId]);
            if ($deleteUser->rowCount() !== 1) {
                throw new RuntimeException('Não foi possível excluir este usuário.');
            }
            $pdo->commit();
            $_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Usuário excluído.'];
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['admin_notice'] = [
            'type' => 'danger',
            'text' => $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.',
        ];
    }

    header('Location: admin.php');
    exit;
}

$events = $pdo->query(
    'SELECT e.id, e.nome, e.descricao, e.data, e.hora, e.local, e.capacidade, e.imagem,
            COUNT(DISTINCT b.id) AS ticket_count,
            COUNT(DISTINCT v.id) AS sold_count
     FROM eventos e
     LEFT JOIN bilhetes b ON b.evento_id = e.id
     LEFT JOIN vendas v ON v.bilhete_id = b.id
     GROUP BY e.id
     ORDER BY e.data DESC, e.hora DESC'
)->fetchAll();

$users = $pdo->query(
    'SELECT u.id, u.username, u.email, u.role, COUNT(v.id) AS purchase_count
     FROM users u
     LEFT JOIN vendas v ON v.user_id = u.id
     GROUP BY u.id
     ORDER BY u.id DESC'
)->fetchAll();

$eventSales = $pdo->query(
    'SELECT e.nome AS event_name,
            COUNT(v.id) AS number_of_tickets_sold,
            COALESCE(SUM(CASE WHEN v.id IS NOT NULL THEN b.preco ELSE 0 END), 0) AS total_revenue
     FROM eventos e
     LEFT JOIN bilhetes b ON b.evento_id = e.id
     LEFT JOIN vendas v ON v.bilhete_id = b.id
     GROUP BY e.id
     ORDER BY e.nome'
)->fetchAll();

$customerSales = $pdo->query(
    'SELECT u.username AS customer_name,
            COUNT(v.id) AS number_of_tickets_purchased,
            COALESCE(SUM(CASE WHEN v.id IS NOT NULL THEN b.preco ELSE 0 END), 0) AS total_spent
     FROM users u
     LEFT JOIN vendas v ON v.user_id = u.id
     LEFT JOIN bilhetes b ON b.id = v.bilhete_id
     WHERE u.role <> \'admin\'
     GROUP BY u.id
     ORDER BY u.username'
)->fetchAll();

$csrf = csrf_token();
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/include/header.php'; ?>

<main class="container my-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h1 class="h2 mb-0">Painel administrativo</h1>
        <a href="profile.php" class="btn btn-outline-secondary">Voltar ao perfil</a>
    </div>

    <?php if ($notice): ?>
        <div class="alert alert-<?= admin_escape($notice['type']) ?>" role="status">
            <?= admin_escape($notice['text']) ?>
        </div>
    <?php endif; ?>

    <section class="mb-5" aria-labelledby="new-event-heading">
        <h2 class="h4 mb-3" id="new-event-heading">Criar evento</h2>
        <form method="post" action="admin.php" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= admin_escape($csrf) ?>">
            <input type="hidden" name="action" value="add_event">
            <div class="col-md-6">
                <label for="title" class="form-label">Nome do evento</label>
                <input type="text" class="form-control" id="title" name="title" maxlength="150" required>
            </div>
            <div class="col-md-3">
                <label for="event_date" class="form-label">Data</label>
                <input type="date" class="form-control" id="event_date" name="event_date" required>
            </div>
            <div class="col-md-3">
                <label for="event_time" class="form-label">Horário</label>
                <input type="time" class="form-control" id="event_time" name="event_time" required>
            </div>
            <div class="col-md-6">
                <label for="location" class="form-label">Local</label>
                <input type="text" class="form-control" id="location" name="location" maxlength="200" required>
            </div>
            <div class="col-md-6">
                <label for="description" class="form-label">Descrição</label>
                <textarea class="form-control" id="description" name="description" rows="2" required></textarea>
            </div>
            <div class="col-md-3">
                <label for="capacity" class="form-label">Capacidade</label>
                <input type="number" class="form-control" id="capacity" name="capacity" min="1" required>
            </div>
            <div class="col-md-3">
                <label for="ticket_count" class="form-label">Bilhetes para venda</label>
                <input type="number" class="form-control" id="ticket_count" name="ticket_count" min="1" required>
            </div>
            <div class="col-md-3">
                <label for="ticket_price" class="form-label">Preço por bilhete (€)</label>
                <input type="number" class="form-control" id="ticket_price" name="ticket_price" min="0" step="0.01" required>
            </div>
            <div class="col-md-3">
                <label for="image" class="form-label">Imagem (opcional)</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-warning">Criar evento</button>
            </div>
        </form>
    </section>

    <section class="mb-5" aria-labelledby="events-heading">
        <h2 class="h4 mb-3" id="events-heading">Eventos</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th>Evento</th><th>Data</th><th>Local</th><th>Bilhetes</th><th>Vendidos</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= admin_escape($event['nome']) ?></td>
                            <td><?= admin_escape(date('d/m/Y H:i', strtotime($event['data'] . ' ' . $event['hora']))) ?></td>
                            <td><?= admin_escape($event['local']) ?></td>
                            <td><?= (int) $event['ticket_count'] ?></td>
                            <td><?= (int) $event['sold_count'] ?></td>
                            <td>
                                <?php if ((int) $event['sold_count'] === 0): ?>
                                    <form method="post" action="admin.php" onsubmit="return confirm('Excluir este evento e seus bilhetes?')">
                                        <input type="hidden" name="csrf_token" value="<?= admin_escape($csrf) ?>">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted" title="Há vendas vinculadas">Não pode excluir</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-5" aria-labelledby="users-heading">
        <h2 class="h4 mb-3" id="users-heading">Usuários</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th>ID</th><th>Usuário</th><th>E-mail</th><th>Perfil</th><th>Compras</th><th>Ação</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int) $user['id'] ?></td>
                            <td><?= admin_escape($user['username']) ?></td>
                            <td><?= admin_escape($user['email']) ?></td>
                            <td><?= admin_escape($user['role']) ?></td>
                            <td><?= (int) $user['purchase_count'] ?></td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="text-muted">Conta protegida</span>
                                <?php else: ?>
                                    <form method="post" action="admin.php" onsubmit="return confirm('<?= (int) $user['purchase_count'] > 0 ? 'Excluir o usuário? As compras serão mantidas sem identificação do comprador.' : 'Excluir este usuário?' ?>')">
                                        <input type="hidden" name="csrf_token" value="<?= admin_escape($csrf) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-5" aria-labelledby="event-sales-heading">
        <h2 class="h4 mb-3" id="event-sales-heading">Vendas por evento</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Evento</th><th>Bilhetes vendidos</th><th>Receita</th></tr></thead>
                <tbody>
                    <?php foreach ($eventSales as $sale): ?>
                        <tr>
                            <td><?= admin_escape($sale['event_name']) ?></td>
                            <td><?= (int) $sale['number_of_tickets_sold'] ?></td>
                            <td>€<?= number_format((float) $sale['total_revenue'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="customer-sales-heading">
        <h2 class="h4 mb-3" id="customer-sales-heading">Compras por usuário</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Usuário</th><th>Bilhetes comprados</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($customerSales as $sale): ?>
                        <tr>
                            <td><?= admin_escape($sale['customer_name']) ?></td>
                            <td><?= (int) $sale['number_of_tickets_purchased'] ?></td>
                            <td>€<?= number_format((float) $sale['total_spent'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/include/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
