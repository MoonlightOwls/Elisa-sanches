<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lógica de Login
    if (isset($_POST['email'], $_POST['password']) && !isset($_POST['register_action'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['is_admin'] = $user['is_admin'];

                if ($user['is_admin']) {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit();
            } else {
                $_SESSION['message'] = "Senha incorreta!";
                $_SESSION['message_type'] = "error";
                header("Location: login.php");
                exit();
            }
        } else {
            $_SESSION['message'] = "Usuário não encontrado!";
            $_SESSION['message_type'] = "error";
            header("Location: login.php");
            exit();
        }
    }

    // Lógica de Registro
    if (isset($_POST['register_action']) && isset($_POST['full_name'], $_POST['email'], $_POST['password'], $_POST['confirm_password'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
            $_SESSION['message'] = "Por favor, preencha todos os campos.";
            $_SESSION['message_type'] = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = "Endereço de e-mail inválido.";
            $_SESSION['message_type'] = "error";
        } elseif ($password !== $confirm_password) {
            $_SESSION['message'] = "As senhas não conferem.";
            $_SESSION['message_type'] = "error";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $_SESSION['message'] = "E-mail já cadastrado.";
                $_SESSION['message_type'] = "error";
            } else {
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");

                if ($stmt === false) {
                    error_log("Erro na preparação da consulta: " . $conn->error);
                    $_SESSION['message'] = "Erro ao realizar o registro.";
                    $_SESSION['message_type'] = "error";
                } else {
                    $stmt->bind_param("sss", $full_name, $email, $password_hashed);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Registro realizado com sucesso! Faça login para continuar.";
                        $_SESSION['message_type'] = "success";
                    } else {
                        error_log("Erro ao executar a consulta: " . $stmt->error);
                        $_SESSION['message'] = "Erro ao realizar o registro.";
                        $_SESSION['message_type'] = "error";
                    }
                }
            }
            $stmt->close();
        }
        header("Location: login.php");
        exit();
    }

    // Lógica de Recuperação de Senha
    if (isset($_POST['reset_email'])) {
        $reset_email = trim($_POST['reset_email']);

        if (!filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = "Endereço de e-mail inválido.";
            $_SESSION['message_type'] = "error";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $reset_email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $reset_token = bin2hex(random_bytes(16));
                $stmt = $conn->prepare("UPDATE users SET reset_token = ? WHERE email = ?");
                $stmt->bind_param("ss", $reset_token, $reset_email);

                if ($stmt->execute()) {
                    $reset_link = "http://seusite.com/reset_password.php?token=" . $reset_token;
                    mail($reset_email, "Redefinição de senha", "Clique no link para redefinir sua senha: " . $reset_link);
                    $_SESSION['message'] = "Link de redefinição de senha enviado para o seu e-mail.";
                    $_SESSION['message_type'] = "success";
                } else {
                    error_log("Erro ao executar a consulta: " . $stmt->error);
                    $_SESSION['message'] = "Erro ao gerar token de redefinição de senha.";
                    $_SESSION['message_type'] = "error";
                }
            } else {
                $_SESSION['message'] = "E-mail não encontrado.";
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        }
        header("Location: login.php");
        exit();
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
                <a href="index.php"><img src="assets/images-removebg-preview (1).png" alt="Império Odontologia Logo" class="logo"></a>
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
                <div class="form-text text-muted mb-3">
                    <a href="#" id="showResetForm">Esqueceu sua senha?</a>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">Lembrar-me</label>
                </div>
                <button type="submit" class="btn btn-auth">Entrar</button>
            </form>

            <form id="registerForm" class="auth-form" action="login.php" method="POST">
    <input type="hidden" name="register_action" value="register">
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

            <form id="resetForm" class="auth-form" action="reset_password.php" method="POST" style="display: none;">
                <input type="email" name="reset_email" class="form-control" placeholder="E-mail para recuperação" required>
                <button type="submit" class="btn btn-auth">Enviar Link de Recuperação</button>
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
            const showResetFormLink = document.getElementById('showResetForm');
            const resetForm = document.getElementById('resetForm');
            const loginForm = document.getElementById('loginForm');

            switchBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.getAttribute('data-target');
                    switchBtns.forEach(b => b.classList.remove('active'));
                    forms.forEach(f => f.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(target + 'Form').classList.add('active');
                });
            });

            showResetFormLink.addEventListener('click', function(event) {
                event.preventDefault();
                loginForm.style.display = 'none';
                resetForm.style.display = 'block';
            });

            // teste notificacao
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
