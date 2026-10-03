<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    header('Location: index.php?page=events');
    exit;
}

require_once __DIR__ . '/conexao.php';

if (!function_exists('event_escape')) {
    function event_escape($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('event_image_path')) {
    function event_image_path(string $imageName): string
    {
        $imageName = trim($imageName);
        if ($imageName === '') {
            return '';
        }

        $localPath = __DIR__ . '/imagem/' . $imageName;
        if (!is_file($localPath)) {
            return '';
        }

        return 'imagem/' . rawurlencode($imageName);
    }
}

if (!function_exists('render_event_card')) {
    function render_event_card(array $event): void
    {
        $date = strtotime((string) ($event['event_date'] ?? ''));
        $time = (string) ($event['event_time'] ?? '');
        $isUpcoming = (bool) ($event['is_upcoming'] ?? false);
        $image = trim((string) ($event['imagem'] ?? ''));
        $imageSrc = event_image_path($image);
        $showImage = $imageSrc !== '';
        ?>
        <article class="card event-card">
            <?php if ($showImage): ?>
                <div class="event-card__media">
                    <img src="<?= htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($event['title'] ?? 'Evento', ENT_QUOTES, 'UTF-8') ?>"
                         class="event-card__image">

                    <div class="event-card__overlay">
                        <p class="event-date"><?= $date !== false ? date('d/m/Y', $date) : 'Data a definir' ?></p>
                        <h3><?= event_escape($event['title'] ?? 'Evento') ?></h3>
                        <p class="muted"><?= event_escape(mb_strimwidth((string) ($event['description'] ?? ''), 0, 105, '...')) ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="event-card__fallback">
                    <p class="event-date"><?= $date !== false ? date('d/m/Y', $date) : 'Data a definir' ?></p>
                    <h3><?= event_escape($event['title'] ?? 'Evento') ?></h3>
                    <p class="muted"><?= event_escape(mb_strimwidth((string) ($event['description'] ?? ''), 0, 105, '...')) ?></p>
                </div>
            <?php endif; ?>

            <div class="event-meta">
                <span><?= event_escape($event['location'] ?? 'Local a definir') ?></span>
                <span><?= event_escape($time !== '' ? substr($time, 0, 5) : 'Hora a definir') ?></span>
            </div>
            <p class="price">€ <?= number_format((float) ($event['price'] ?? 0), 2, ',', '.') ?></p>
            <div class="event-card__actions">
                <?php if (!$isUpcoming): ?>
                    <button type="button" class="btn btn-secondary w-100 mb-2" disabled>Evento encerrado</button>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <form method="post" action="carrinho.php" class="event-card__form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="event_id" value="<?= (int) ($event['id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-warning w-100">Comprar bilhete</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-warning w-100 mb-2" href="login.php?comprar=1">Comprar bilhete</a>
                <?php endif; ?>
                <button type="button"
                        class="btn btn-secondary w-100 event-detail-btn"
                        data-image="<?= htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') ?>"
                        data-title="<?= htmlspecialchars($event['title'] ?? 'Evento', ENT_QUOTES, 'UTF-8') ?>"
                        data-description="<?= htmlspecialchars((string) ($event['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-date="<?= htmlspecialchars(date('d/m/Y', $date !== false ? $date : time()), ENT_QUOTES, 'UTF-8') ?>"
                        data-location="<?= htmlspecialchars($event['location'] ?? 'Local a definir', ENT_QUOTES, 'UTF-8') ?>"
                        data-time="<?= htmlspecialchars($time !== '' ? substr($time, 0, 5) : 'Hora a definir', ENT_QUOTES, 'UTF-8') ?>">
                    Ver evento
                </button>
            </div>
        </article>
        <?php
    }
}

$events = [];

try {
    $pdo = db();
    $stmt = $pdo->query(
        'SELECT e.id,
                e.nome AS title,
                e.descricao AS description,
                e.data AS event_date,
                e.hora AS event_time,
                e.local AS location,
                e.imagem AS imagem,
                TIMESTAMP(e.data, e.hora) >= NOW() AS is_upcoming,
                COALESCE(MIN(b.preco), 0) AS price
         FROM eventos e
         LEFT JOIN bilhetes b ON b.evento_id = e.id
         GROUP BY e.id
         ORDER BY (TIMESTAMP(e.data, e.hora) < NOW()) ASC, e.data ASC, e.hora ASC'
    );
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    // Não exibe detalhes da conexão ao visitante.
    $error = $error ?? 'Não foi possível carregar os eventos. Verifique a conexão e a estrutura da base de dados.';
}
?>
