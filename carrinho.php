<?php
require_once __DIR__ . '/conexao.php';

$cartItems = $_SESSION['cartItems'] ?? [];
if (!is_array($cartItems)) {
    $cartItems = [];
}

$cartNotice = $_SESSION['cart_notice'] ?? '';
unset($_SESSION['cart_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $cartNotice = 'A sessão expirou. Tente novamente.';
    } elseif ($action === 'remove' && $eventId > 0) {
        unset($cartItems[$eventId]);
        $cartNotice = 'Evento removido do carrinho.';
    } elseif (($action === 'add' || $action === 'update') && $eventId > 0) {
        $stmt = $pdo->prepare(
            'SELECT e.id, e.nome AS name,
                    MIN(CASE WHEN v.id IS NULL THEN b.preco END) AS price,
                    SUM(CASE WHEN v.id IS NULL THEN 1 ELSE 0 END) AS available
             FROM eventos e
             INNER JOIN bilhetes b ON b.evento_id = e.id
             LEFT JOIN vendas v ON v.bilhete_id = b.id
             WHERE e.id = ? AND TIMESTAMP(e.data, e.hora) >= NOW()
             GROUP BY e.id, e.nome
             HAVING available > 0'
        );
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event) {
            $cartNotice = 'Este evento não tem bilhetes disponíveis.';
        } else {
            $currentQuantity = (int) ($cartItems[$eventId]['quantity'] ?? 0);
            $quantity = $action === 'add'
                ? $currentQuantity + 1
                : (int) ($_POST['quantity'] ?? 0);

            if ($quantity < 1 || $quantity > (int) $event['available']) {
                $cartNotice = 'A quantidade escolhida não está disponível.';
            } else {
                $cartItems[$eventId] = [
                    'event_id' => $eventId,
                    'name' => $event['name'],
                    'price' => (float) $event['price'],
                    'quantity' => $quantity,
                ];
                $cartNotice = 'Carrinho atualizado.';
            }
        }
    } else {
        $cartNotice = 'Não foi possível atualizar o carrinho.';
    }

    $_SESSION['cartItems'] = $cartItems;
    $_SESSION['cart_notice'] = $cartNotice;
    header('Location: carrinho.php');
    exit;
}

$pageTitle = 'Carrinho';
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://kit.fontawesome.com/8b11e3e235.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/include/header.php'; ?>

<main class="container my-5">
    <h1 class="mb-4">Meu carrinho</h1>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card bg-dark text-light border-secondary p-4">
                <h2 class="h4 card-title"><i class="fa-solid fa-receipt me-2"></i>Resumo do Pedido</h2>
                <hr class="border-secondary">

                <?php if ($cartNotice !== ''): ?>
                    <div class="alert alert-info" role="status"><?= htmlspecialchars($cartNotice, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php $total = 0.0; ?>
                <?php if ($cartItems): ?>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($cartItems as $eventId => $item):
                            if (!is_array($item)) continue;
                            $name = $item['name'] ?? $item['nome'] ?? 'Produto';
                            $price = (float) ($item['price'] ?? $item['preco'] ?? 0);
                            $qty = (int) ($item['quantity'] ?? $item['quantidade'] ?? 1);
                            $total += $price * $qty;
                        ?>
                            <li class="list-group-item bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small class="text-muted d-block">€<?= number_format($price, 2, ',', '.') ?> por bilhete</small>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <form method="post" action="carrinho.php" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                            <label class="visually-hidden" for="quantity-<?= (int) $eventId ?>">Quantidade de <?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?></label>
                                            <input id="quantity-<?= (int) $eventId ?>" class="form-control form-control-sm" type="number" name="quantity" min="1" value="<?= $qty ?>" style="width: 5rem">
                                            <button type="submit" class="btn btn-outline-light btn-sm">Atualizar</button>
                                        </form>
                                        <form method="post" action="carrinho.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Remover</button>
                                        </form>
                                    </div>
                                </div>
                                <strong>€<?= number_format($price * $qty, 2, ',', '.') ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="d-flex justify-content-between fs-4 fw-bold mt-2">
                        <span>Total:</span>
                        <span class="text-warning">€<?= number_format($total, 2, ',', '.') ?></span>
                    </div>
                    <a href="profile.php?view=checkout" class="btn btn-success w-100 mt-3">Finalizar compra</a>
                <?php else: ?>
                    <p class="text-muted mb-3">Nenhum item adicionado ao carrinho no momento.</p>
                    <a href="index.php?page=events" class="btn btn-warning">Explorar eventos</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/include/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>
