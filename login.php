<?php
// pages/login.php
require_once __DIR__ . '/conexao.php';

$erro = ''; // Variável definida com valor inicial vazio
$next = ($_POST['next'] ?? $_GET['next'] ?? '') === 'profile.php?view=checkout' ? 'profile.php?view=checkout' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        
        header('Location: ' . ($next !== '' ? $next : 'profile.php'));
        exit;
    } else {
        $erro = 'Nome de utilizador ou senha incorretos.';
    }
}

$pageTitle = $pageTitle ?? 'Login';
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
    <link rel="shortcut icon" href="favicon/icon.jpeg" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/include/header.php'; ?>

<main class="container my-5">
<div class="col-md-6 offset-md-3">
    <h2>Login</h2>

    <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">Registo efetuado com sucesso! Pode entrar.</div>
    <?php endif; ?>

    <?php if (isset($_GET['comprar'])): ?>
        <div class="alert alert-info " role="status" >
            Entre na sua conta para comprar. Ainda não tem conta? <a  href="register.php"  >Faça um registo</a>.
        </div>
    <?php endif; ?>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <!-- O action envia os dados para a própria rota de login -->
    <form method="post" action="login.php" id="formLogin">
        <?php if ($next !== ''): ?>
            <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="mb-3">
            <input type="text" id="username" name="username" placeholder="Nome de Utilizador" class="form-control" required>
            <div id="erroUser" class="text-dark small"></div>
        </div>

        <div class="mb-3">
            <input type="password" id="password" name="password" placeholder="Palavra-passe" class="form-control" required>
            <div id="erroPass" class="text-dark small"></div>
        </div>

        <button type="submit" class="btn btn-warning w-100">Entrar</button>
        <p class="mt-3"><a href="register.php" class="text-dark">Não tem conta? Faça Registo</a></p>
    </form>
</div>
</main> 


<?php require_once __DIR__ . '/include/footer.php'; ?>

<script src="js/script.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>