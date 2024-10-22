<?php
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    
    if (empty($full_name) || empty($email) || empty($password)) {
        echo "Por favor, preencha todos os campos.";
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Endereço de e-mail inválido.";
        exit();
    }

    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
    if ($stmt === false) {
        error_log("Erro na preparação da consulta: " . $conn->error);
        echo "Erro ao realizar o registro.";
        exit();
    }
    $stmt->bind_param("sss", $full_name, $email, $password_hashed);

    if ($stmt->execute()) {
        echo "Registro realizado com sucesso!";
    } else {
        error_log("Erro ao executar a consulta: " . $stmt->error);
        echo "Erro ao realizar o registro.";
    }
}
?>
