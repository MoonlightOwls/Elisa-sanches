<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'], $_POST['password'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Consulta para buscar o usuário e verificar se é administrador
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verificar se a senha está correta
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['is_admin'] = $user['is_admin'];  // Verificar se é admin

             
                
                // Redirecionar para o painel adequado
                if ($user['is_admin']) {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit();
            } else {
                // Senha incorreta
                $_SESSION['message'] = "Senha incorreta!";
                $_SESSION['message_type'] = "error";
                header("Location: login.php");
                exit();
            }
        } else {
            // E-mail não encontrado
            $_SESSION['message'] = "Usuário não encontrado!";
            $_SESSION['message_type'] = "error";
            header("Location: login.php");
            exit();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login e Cadastro - Império Odontologia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="assets/login.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="auth-logo">
                <img src="assets/images-removebg-preview (1).png" alt="Império Odontologia Logo" class="logo">
            </div>
            <h2 class="fw-bold">Império Odontologia</h2>
            <p class="mb-0">Bem-vindo ao nosso portal</p>
        </div>

        <div class="auth-body">
            <div class="auth-switch">
                <button class="auth-switch-btn active" data-target="login">Login</button>
                <button class="auth-switch-btn" data-target="register">Cadastro</button>
            </div>

            <form id="loginForm" class="auth-form active" action="login.php" method="POST">
                <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                <input type="password" name="password" class="form-control" placeholder="Senha" required>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">Lembrar-me</label>
                </div>
                <button type="submit" class="btn btn-auth">Entrar</button>
            </form>

            <form id="registerForm" class="auth-form" action="register.php" method="POST">
                <input type="text" name="full_name" class="form-control" placeholder="Nome completo" required>
                <input type="email" name="email" class="form-control" placeholder="E-mail" required>
                <input type="password" name="password" class="form-control" placeholder="Senha" required>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirme a senha" required>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="termsAgree" required>
                    <label class="form-check-label" for="termsAgree">
                        Concordo com os <a href="#" class="forgot-password">termos e condições</a>
                    </label>
                </div>
                <button type="submit" class="btn btn-auth">Cadastrar</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const switchBtns = document.querySelectorAll('.auth-switch-btn');
            const forms = document.querySelectorAll('.auth-form');

            switchBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.getAttribute('data-target');
                    switchBtns.forEach(b => b.classList.remove('active'));
                    forms.forEach(f => f.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(target + 'Form').classList.add('active');
                });
            });

            // Exibir notificações Toastr
            <?php if (isset($_SESSION['message'])): ?>
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "5000"
                };
                toastr["<?= $_SESSION['message_type'] ?>"]("<?= htmlspecialchars($_SESSION['message']) ?>");
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
