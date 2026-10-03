<?php
require_once __DIR__ . '/conexao.php';

$view = $_GET['view'] ?? 'profile';
if (!in_array($view, ['profile', 'checkout'], true)) {
    $view = 'profile';
}
if (empty($_SESSION['user_id'])) {
    $next = $view === 'checkout' ? '?next=profile.php%3Fview%3Dcheckout' : '';
    header('Location: login.php' . $next);
    exit;
}

if (!function_exists('profile_escape')) {
    function profile_escape($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$pdo = db();
$profileNotice = $_SESSION['profile_notice'] ?? null;
$checkoutNotice = $_SESSION['checkout_notice'] ?? null;
$receipt = $_SESSION['checkout_receipt'] ?? null;
unset($_SESSION['profile_notice'], $_SESSION['checkout_notice'], $_SESSION['checkout_receipt']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect = $view === 'checkout' ? 'profile.php?view=checkout' : 'profile.php';

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $noticeKey = $view === 'checkout' ? 'checkout_notice' : 'profile_notice';
        $_SESSION[$noticeKey] = 'A sessão expirou. Atualize a página e tente novamente.';
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
            $_SESSION['profile_notice'] = ['type' => 'danger', 'text' => 'Use de 3 a 20 caracteres: letras, números, _ ou -.'];
        } else {
            try {
                $updateUser = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                $updateUser->execute([$username, (int) $_SESSION['user_id']]);
                $_SESSION['username'] = $username;
                $_SESSION['profile_notice'] = ['type' => 'success', 'text' => 'Nome de usuário atualizado.'];
            } catch (PDOException $e) {
                $_SESSION['profile_notice'] = ['type' => 'danger', 'text' => 'Esse nome de usuário já está em uso.'];
            }
        }
        header('Location: profile.php');
        exit;
    }

    if ($action === 'upload_profile_pic') {
        $upload = $_FILES['profile_pic'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
            $_SESSION['profile_notice'] = ['type' => 'danger', 'text' => 'Selecione uma imagem válida de até 5 MB.'];
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                $_SESSION['profile_notice'] = ['type' => 'danger', 'text' => 'Use uma imagem JPG, PNG ou WebP.'];
            } else {
                $uploadDirectory = __DIR__ . '/uploads';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    $_SESSION['profile_notice'] = ['type' => 'danger', 'text' => 'Não foi possível preparar a pasta de imagens.'];
                } else {
                    $imageName = 'profile_' . (int) $_SESSION['user_id'] . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                    $targetPath = $uploadDirectory . '/' . $imageName;
                    if (move_uploaded_file($upload['tmp_name'], $targetPath)) {
                        $imagePath = 'uploads/' . $imageName;
                        $updateImage = $pdo->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
                        $updateImage->execute([$imagePath, (int) $_SESSION['user_id']]);
                        $_SESSION['profile_pic'] = $imagePath;
                        $_SESSION['profile_notice'] = ['type' => 'success', 'text' => 'Foto de perfil atualizada.'];
                    } else {
                        $_SESSION['profile_notice'] = ['type' => 'danger', 'text' => 'Não foi possível salvar a imagem.'];
                    }
                }
            }
        }
        header('Location: profile.php');
        exit;
    }

    if ($action === 'confirm_checkout' && $view === 'checkout') {
        $cartItems = $_SESSION['cartItems'] ?? [];
        if (!is_array($cartItems) || !$cartItems) {
            $_SESSION['checkout_notice'] = 'Seu carrinho está vazio.';
            header('Location: carrinho.php');
            exit;
        }

        $orderItems = [];
        $total = 0.0;
        try {
            $pdo->beginTransaction();
            $ticketQuery = $pdo->prepare(
                'SELECT b.id, b.preco
                 FROM bilhetes b
                 INNER JOIN eventos e ON e.id = b.evento_id
                 LEFT JOIN vendas v ON v.bilhete_id = b.id
                 WHERE b.evento_id = ? AND v.id IS NULL
                   AND TIMESTAMP(e.data, e.hora) >= NOW()
                 ORDER BY b.preco ASC, b.id ASC
                 LIMIT ? FOR UPDATE'
            );
            $saleInsert = $pdo->prepare('INSERT INTO vendas (bilhete_id, user_id) VALUES (?, ?)');
            $eventNameQuery = $pdo->prepare('SELECT nome FROM eventos WHERE id = ?');

            foreach ($cartItems as $eventId => $item) {
                $eventId = (int) $eventId;
                $quantity = is_array($item) ? (int) ($item['quantity'] ?? 0) : 0;
                if ($eventId < 1 || $quantity < 1) {
                    throw new RuntimeException('O carrinho contém um item inválido.');
                }

                $ticketQuery->bindValue(1, $eventId, PDO::PARAM_INT);
                $ticketQuery->bindValue(2, $quantity, PDO::PARAM_INT);
                $ticketQuery->execute();
                $tickets = $ticketQuery->fetchAll();
                if (count($tickets) !== $quantity) {
                    throw new RuntimeException('A disponibilidade mudou. Confira seu carrinho e tente novamente.');
                }

                $eventNameQuery->execute([$eventId]);
                $eventName = $eventNameQuery->fetchColumn();
                if ($eventName === false) {
                    throw new RuntimeException('Um evento do carrinho não está mais disponível.');
                }

                $subtotal = 0.0;
                foreach ($tickets as $ticket) {
                    $saleInsert->execute([(int) $ticket['id'], (int) $_SESSION['user_id']]);
                    $subtotal += (float) $ticket['preco'];
                }
                $orderItems[] = ['name' => $eventName, 'quantity' => $quantity, 'subtotal' => $subtotal];
                $total += $subtotal;
            }

            $pdo->commit();
            $_SESSION['cartItems'] = [];
            $_SESSION['checkout_receipt'] = ['items' => $orderItems, 'total' => $total];
            header('Location: profile.php?view=checkout&confirmed=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['checkout_notice'] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Não foi possível concluir o pedido. Tente novamente.';
            header('Location: profile.php?view=checkout');
            exit;
        }
    }

    $_SESSION['profile_notice'] = ['type' => 'danger', 'text' => 'Ação inválida.'];
    header('Location: profile.php');
    exit;
}

$userQuery = $pdo->prepare('SELECT username, email, role, profile_pic FROM users WHERE id = ? LIMIT 1');
$userQuery->execute([(int) $_SESSION['user_id']]);
$user = $userQuery->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: logout.php');
    exit;
}

$profilePic = $user['profile_pic'] ?? '';
$pageTitle = $view === 'checkout' ? 'Finalizar compra' : 'Meu perfil';
$cartItems = $_SESSION['cartItems'] ?? [];
if (!is_array($cartItems)) {
    $cartItems = [];
}
if ($view === 'checkout' && !$receipt && !$cartItems) {
    header('Location: carrinho.php');
    exit;
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/8b11e3e235.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/include/header.php'; ?>

<main class="container my-5">
    <?php if ($view === 'checkout'): ?>
        <h1 class="h2 mb-4"><?= $receipt ? 'Pedido confirmado' : 'Finalizar compra' ?></h1>
        <?php if ($receipt): ?>
            <div class="alert alert-success" role="status">Seu pedido foi registrado com sucesso.</div>
            <ul class="list-group mb-3">
                <?php foreach ($receipt['items'] as $item): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= profile_escape($item['name']) ?> x <?= (int) $item['quantity'] ?></span>
                        <strong>€<?= number_format((float) $item['subtotal'], 2, ',', '.') ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="fs-4 fw-bold">Total: €<?= number_format((float) $receipt['total'], 2, ',', '.') ?></p>
            <p class="text-muted">O pedido foi registrado. Pagamento online não está configurado.</p>
            <a href="index.php?page=events" class="btn btn-warning">Voltar aos eventos</a>
        <?php else: ?>
            <?php if ($checkoutNotice): ?>
                <div class="alert alert-warning" role="alert"><?= profile_escape($checkoutNotice) ?></div>
            <?php endif; ?>
            <?php $total = 0.0; ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Evento</th><th>Quantidade</th><th>Preço estimado</th></tr></thead>
                    <tbody>
                        <?php foreach ($cartItems as $item):
                            if (!is_array($item)) continue;
                            $name = $item['name'] ?? $item['nome'] ?? 'Evento';
                            $quantity = max(0, (int) ($item['quantity'] ?? $item['quantidade'] ?? 0));
                            $price = max(0, (float) ($item['price'] ?? $item['preco'] ?? 0));
                            $subtotal = $quantity * $price;
                            $total += $subtotal;
                        ?>
                            <tr>
                                <td><?= profile_escape($name) ?></td>
                                <td><?= $quantity ?></td>
                                <td>€<?= number_format($subtotal, 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="fs-4 fw-bold">Total estimado: €<?= number_format($total, 2, ',', '.') ?></p>
            <p class="text-muted">A confirmação registra os bilhetes disponíveis para sua conta. Pagamento online não está configurado.</p>
            <form method="post" action="profile.php?view=checkout">
                <input type="hidden" name="csrf_token" value="<?= profile_escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="confirm_checkout">
                <button type="submit" class="btn btn-success">Confirmar pedido</button>
                <a href="carrinho.php" class="btn btn-outline-secondary ms-2">Voltar ao carrinho</a>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <h1 class="h2 mb-4">Meu perfil</h1>
        <?php if ($profileNotice): ?>
            <div class="alert alert-<?= profile_escape($profileNotice['type']) ?>" role="status">
                <?= profile_escape($profileNotice['text']) ?>
            </div>
        <?php endif; ?>

        <section class="mb-5 p-4 bg-dark text-white border border-secondary rounded">
            <div class="profile-header mb-4 text-center">
                <div class="avatar-container mb-3">
                    <?php if ($profilePic && file_exists(__DIR__ . '/' . $profilePic)): ?>
                        <img src="<?= profile_escape($profilePic) ?>?v=<?= time() ?>" class="profile-avatar rounded-circle img-thumbnail" width="150" height="150" alt="Foto do perfil">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user fa-6x text-secondary"></i>
                    <?php endif; ?>
                </div>
                <h2><?= profile_escape($user['username']) ?></h2>
                <form action="profile.php" method="post" enctype="multipart/form-data" class="col-md-6 mx-auto">
                    <input type="hidden" name="csrf_token" value="<?= profile_escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload_profile_pic">
                    <label for="profile_pic" class="form-label">Alterar foto de perfil</label>
                    <input class="form-control mb-3" type="file" id="profile_pic" name="profile_pic" accept="image/jpeg,image/png,image/webp" required>
                    <button class="btn btn-warning" type="submit">Guardar foto</button>
                </form>
            </div>

            <hr class="border-secondary my-4">
            <form action="profile.php" method="post" class="col-md-8 mx-auto">
                <input type="hidden" name="csrf_token" value="<?= profile_escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-3">
                    <label for="username" class="form-label">Nome de usuário</label>
                    <input class="form-control" id="username" name="username" value="<?= profile_escape($user['username']) ?>" minlength="3" maxlength="20" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input class="form-control" id="email" value="<?= profile_escape($user['email']) ?>" disabled>
                </div>
                <p><strong>Tipo de conta:</strong> <span class="badge bg-warning text-dark"><?= profile_escape($user['role']) ?></span></p>
                <button class="btn btn-warning" type="submit">Salvar alterações</button>
            </form>
        </section>

        <div class="d-flex flex-wrap gap-2">
            <a href="index.php" class="btn btn-outline-secondary">Página principal</a>
            <a href="carrinho.php" class="btn btn-outline-secondary">Meu carrinho</a>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="admin.php" class="btn btn-outline-warning">Painel administrativo</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger">Sair</a>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/include/footer.php'; ?>
<script src="js/script.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>