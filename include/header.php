<header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                    <img src="imagem/agenda.jpeg" alt="Logo" width="80" height="80" style="border-radius: 50%;" class="d-inline-block align-text-top">
                    
                    <strong class="FONTS">Agenda Cultural</strong>
                     
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
   
                <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
      <div class="navbar-nav ms-auto align-items-lg-center">
        
                        <a class="nav-link " aria-current="page" href="index.php">Explorar</a>
                       
                        <a class="nav-link" href="index.php?page=events">Eventos</a>
                        <a class="nav-link" href="index.php?page=about">Sobre</a>
                        <a class="nav-link" href="carrinho.php">Carrinho</a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a class="nav-link" href="profile.php"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Perfil'); ?></a>
                            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                <a class="nav-link text-warning" href="admin.php"><i class="fa-solid fa-gear"></i> Admin</a>
                            <?php endif; ?>
                            <a class="nav-link text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
                        <?php else: ?>
                            <a class="nav-link" href="login.php"><i class="fa-solid fa-user"></i> Login</a>
                            <a class="nav-link" href="register.php" > Registar</a>
                        <?php endif; ?>
                    </div> 
                </div>
            </div>     
        </nav>
</header>