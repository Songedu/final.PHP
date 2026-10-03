<?php
// 1. Processamento e Roteamento
$page = $_GET['page'] ?? 'home';
$search = $_GET['search'] ?? '';
$allowed_pages = ['home', 'events', 'about', 'login', 'register', 'profile', 'admin_dashboard', 'admin_events', 'admin_users'];

// Garantir que as variáveis de alerta existam sem gerar Notice do PHP
$error = $error ?? null;
$message = $message ?? null;

// Função de segurança para evitar XSS
if (!function_exists('h')) {
    function h($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Carrega a conexão / dados de eventos
if (file_exists(__DIR__ . '/conexao.php')) {
    require_once __DIR__ . '/conexao.php';
}

if (file_exists(__DIR__ . '/evento.php')) {
    require_once __DIR__ . '/evento.php';
}

$events = $events ?? [];

if ($page === 'events' && $search !== '') {
    $searchTerm = mb_strtolower(trim((string) $search));

    $events = array_values(array_filter($events, function ($event) use ($searchTerm) {
        if ($searchTerm === '') {
            return true;
        }

        $title = mb_strtolower((string) ($event['title'] ?? ''));
        $description = mb_strtolower((string) ($event['description'] ?? ''));
        $location = mb_strtolower((string) ($event['location'] ?? ''));
        $eventDate = (string) ($event['event_date'] ?? '');
        $formattedDate = $eventDate !== '' ? date('d/m/Y', strtotime($eventDate)) : '';

        return mb_strpos($title, $searchTerm) !== false
            || mb_strpos($description, $searchTerm) !== false
            || mb_strpos($location, $searchTerm) !== false
            || mb_strpos(mb_strtolower($eventDate), $searchTerm) !== false
            || mb_strpos(mb_strtolower($formattedDate), $searchTerm) !== false;
    }));
}

$eventImages = [];
if ($page === 'home' && $events) {
    try {
        $eventImages = db()->query('SELECT id, imagem FROM eventos')->fetchAll(PDO::FETCH_KEY_PAIR);
        $imageFiles = glob(__DIR__ . '/imagem/*') ?: [];

        foreach ($events as &$event) {
            $imageName = basename((string) ($eventImages[$event['id']] ?? ''));
            $imageStem = strtolower(pathinfo($imageName, PATHINFO_FILENAME));
            $event['image_path'] = '';

            foreach ($imageFiles as $imageFile) {
                if ($imageStem !== '' && strtolower(pathinfo($imageFile, PATHINFO_FILENAME)) === $imageStem) {
                    $event['image_path'] = 'imagem/' . rawurlencode(basename($imageFile));
                    break;
                }
            }
        }
        unset($event);
    } catch (PDOException $e) {
        $eventImages = [];
    }
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portfolio Bootstrap - Eventos">
    <meta name="author" content="Gibrilo Soares Baldé">
    <meta name="keywords" content="Portfolio Bootstrap evento">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="shortcut icon" href="favicon/icon.jpeg" type="image/x-icon">
    <title>Agenda Cultural - <?= h(ucfirst($page)) ?></title>
   <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome & Bootstrap -->
    <script src="https://kit.fontawesome.com/8b11e3e235.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="font-roboto">

    <!-- Header / Navbar global -->
    <?php 
    if (file_exists(__DIR__ . '/include/header.php')) {
        require __DIR__ . '/include/header.php'; 
    }
    ?>

    <!-- Mensagens de Alerta (Aparecem logo abaixo do menu) -->
    <?php if ($error): ?>
        <div class="container mt-3">
            <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="container mt-3">
            <div class="alert alert-success" role="alert"><?= h($message) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($page === 'home'): ?>
        <section class="hero">
            <p class="event-date">Descubra. Participe. Viva.</p>
            <h1>Pequenos eventos, grandes memórias.</h1>
            <p>
                Encontre workshops, concertos e experiências perto de si.
                Compre o seu bilhete em poucos passos.
            </p>
            <a class="btn btn-warning" href="index.php?page=events">Ver eventos</a>
        </section>
    <?php endif; ?>
   

    <!-- Conteúdo Principal -->
    <main class="container my-5">

        <?php if ($page === 'home'): ?>
            <?php if ($events): ?>
                <section class="section event-carousel-section">
                    <div class="container">
                        <div class="section-heading d-flex justify-content-between align-items-center mb-4">
                            <h2>Eventos em destaque</h2>
                            <a class="btn btn-light" href="index.php?page=events">Ver todos</a>
                        </div>
                        <div id="eventCarousel" class="carousel slide event-carousel" data-bs-ride="carousel" data-bs-interval="4000" data-bs-pause="false" data-bs-wrap="true">
                            <div class="carousel-indicators">
                                <?php foreach ($events as $index => $event): ?>
                                    <button type="button" data-bs-target="#eventCarousel" data-bs-slide-to="<?= $index ?>"
                                            class="<?= $index === 0 ? 'active' : '' ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                                            aria-label="Mostrar evento <?= $index + 1 ?>"></button>
                                <?php endforeach; ?>
                            </div>
                            <div class="carousel-inner">
                                <?php foreach ($events as $index => $event): ?>
                                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                        <div class="event-carousel__slide">
                                            <?php if ($event['image_path'] !== ''): ?>
                                                <img class="event-carousel__image" src="<?= h($event['image_path']) ?>"
                                                     alt="Foto do evento <?= h($event['title']) ?>">
                                            <?php else: ?>
                                                <div class="event-carousel__placeholder" role="img" aria-label="Foto do evento indisponível">
                                                    <span>Foto do evento indisponível</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="event-carousel__caption">
                                                <p class="event-date">
                                                    <?= h(date('d/m/Y', strtotime((string) $event['event_date']))) ?>
                                                </p>
                                                <h3><?= h($event['title']) ?></h3>
                                                <p><?= h($event['description']) ?></p>
                                                <a class="btn btn-warning" href="index.php?page=events">Ver eventos</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#eventCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Anterior</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#eventCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Próximo</span>
                            </button>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

        <?php elseif ($page === 'about'): ?>
            <section class="about-page" aria-labelledby="about-title">
                <div class="about-page__copy">
                    <p class="event-date">Sobre a Agenda Cultural</p>
                    <h1 id="about-title">Mais momentos.<br><em>Menos scroll.</em></h1>
                    <p class="about-lead">
                        A Agenda Cultural  nasceu para aproximar pessoas de experiências reais. Menos
                        tempo a procurar, mais tempo a viver.
                    </p>
                    <a class="btn btn-warning" href="index.php?page=events">Explorar eventos</a>
                </div>

                <figure class="about-page__media">
                    <img class="t" src="imagem/agenda.jpeg" alt="Mariza cantando ao vivo no palco">
                    <figcaption>Experiências reais, ao vivo.</figcaption>
                </figure>
            </section>

        <?php elseif ($page === 'events'): ?>
            <!-- PÁGINA DE EVENTOS COM PESQUISA -->
            <section class="section">
                <div class="container">
                    <div class="section-heading d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <p class="event-date">Agenda</p>
                            <h2>Todos os eventos</h2>
                        </div>
                        <form method="get" class="d-flex event-search">
                            <input type="hidden" name="page" value="events">
                            <input
                                type="search"
                                class="form-control me-2"
                                name="search"
                                placeholder="Pesquisar título ou data"
                                value="<?= h($search) ?>"
                            >
                            <button class="btn btn-warning" type="submit">Pesquisar</button>
                        </form>
                    </div>
                    <div class="grid">
                        <?php foreach ($events as $event): ?>
                            <?php if (function_exists('render_event_card')) render_event_card($event); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

        <?php else: ?>
            <!-- CARREGA DINAMICAMENTE AS OUTRAS PÁGINAS (login, register, profile, etc) -->
            <?php
            if (in_array($page, $allowed_pages)) {
                $page_file = __DIR__ . "/pages/{$page}.php";
                if (file_exists($page_file)) {
                    include $page_file;
                } else {
                    echo "<div class='alert alert-warning'>Página não encontrada no diretório 'pages/'.</div>";
                }
            } else {
                $file_404 = __DIR__ . '/pages/404.php';
                if (file_exists($file_404)) {
                    include $file_404;
                } else {
                    echo "<h2>404 - Página não encontrada</h2>";
                }
            }
            ?>
        <?php endif; ?>

        
    </main>

    <!-- Footer global -->
    <?php 
    if (file_exists(__DIR__ . '/include/footer.php')) {
        require __DIR__ . '/include/footer.php'; 
    }
    ?>
    <div id="event-lightbox" class="event-lightbox" aria-hidden="true">
        <div class="event-lightbox__dialog" role="dialog" aria-modal="true" aria-labelledby="event-lightbox-title">
            <button type="button" class="event-lightbox__close" aria-label="Fechar">&times;</button>
            <img class="event-lightbox__image" src="" alt="Imagem do evento">
            <div class="event-lightbox__content">
                <p class="event-lightbox__date"></p>
                <h3 id="event-lightbox-title"></h3>
                <p class="event-lightbox__description"></p>
                <div class="event-lightbox__meta">
                    <span class="event-lightbox__location"></span>
                    <span class="event-lightbox__time"></span>
                </div>
            </div>
        </div>
    </div>
<script src="js/script.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>