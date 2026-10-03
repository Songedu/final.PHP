<?php
// register.php (localizado na raiz: C:\xampp\htdocs\Projecto final\)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/conexao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass     = $_POST['password'] ?? '';

    // Validações no PHP
    if (!preg_match('/^[a-zA-Z0-9_\-]{3,20}$/', $username)) {
        $erro = 'Nome de utilizador inválido. Use 3 a 20 caracteres alfanuméricos, _ ou -.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } elseif (strlen($pass) < 8) {
        $erro = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif ($username && $email && $pass) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo = db();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->execute([$username, $email, $hash]);

            header('Location: login.php?registered=1');
            exit;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $erro = 'Nome de utilizador ou e-mail já registado.';
            } else {
                $erro = 'Ocorreu um erro ao registar a conta.';
            }
        }
    }
}

$pageTitle = $pageTitle ?? 'Agenda Cultural';
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="shortcut icon" href="favicon/icon.jpeg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/8b11e3e235.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/include/header.php'; ?>

<main class="container my-5">
<div class="col-md-6 offset-md-3">
    <h2>Registo de Utilizador</h2>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="post" action="register.php" id="registoForm">
        <div class="mb-3">
            <input type="text" id="username" name="username" placeholder="Nome de Utilizador" class="form-control" required>
            <div id="erroUser" class="text-danger small mt-1"></div>
        </div>

        <div class="mb-3">
            <input type="email" id="email" name="email" placeholder="Endereço de E-mail" class="form-control" required>
            <div id="erroEmail" class="text-danger small mt-1"></div>
        </div>

        <div class="mb-3">
            <input type="password" id="password" name="password" placeholder="Palavra-passe (mínimo 8 caracteres)" class="form-control" required>
            <div id="erroPass" class="text-danger small mt-1"></div>
        </div>

        <button type="submit" class="btn btn-warning w-100">Criar Conta</button>
    </form>

    <p class="mt-3">
        <a href="login.php" class="text-dark">Já tem conta? Faça Login</a>
    </p>
</div>
</main>

<?php require_once __DIR__ . '/include/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>