<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    $_SESSION['message'] = "Acesso negado. Área restrita para administradores.";
    $_SESSION['message_type'] = "danger";
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function buscarAgendamentos($conn) {
    $stmt = $conn->prepare("SELECT appointments.id, users.full_name, appointments.appointment_date, appointments.appointment_time, services.name AS service_name, appointments.notes, appointments.status FROM appointments JOIN users ON appointments.user_id = users.id JOIN services ON appointments.service = services.id ORDER BY appointment_date, appointment_time");
    if ($stmt === false) {
        error_log("Erro na preparação da consulta: " . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        error_log("Erro ao executar consulta: " . $stmt->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

$total_users_stmt = $conn->prepare("SELECT COUNT(*) AS total_users FROM users");
$total_users_stmt->execute();
$total_users = $total_users_stmt->get_result()->fetch_assoc()['total_users'];
$total_users_stmt->close();

$total_appointments_stmt = $conn->prepare("SELECT COUNT(*) AS total_appointments FROM appointments");
$total_appointments_stmt->execute();
$total_appointments = $total_appointments_stmt->get_result()->fetch_assoc()['total_appointments'];
$total_appointments_stmt->close();

$agendamentos = buscarAgendamentos($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_agendamento'])) {
    $appointment_id = $_POST['appointment_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = $_POST['notes'];
    $status = $_POST['status'];

    $valid_statuses = ['Agendado', 'Completo', 'Cancelado'];
    if (!in_array($status, $valid_statuses)) {
        $_SESSION['message'] = "Status inválido.";
        $_SESSION['message_type'] = "danger";
        header("Location: admin_dashboard.php");
        exit();
    }

    $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, notes = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $appointment_date, $appointment_time, $notes, $status, $appointment_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Agendamento atualizado com sucesso.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Erro ao atualizar agendamento.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir_agendamento'])) {
    $appointment_id = $_POST['appointment_id'];

    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Agendamento excluído com sucesso.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Erro ao excluir agendamento.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: admin_dashboard.php");
    exit();
}

function buscarUsuarios($conn) {
    $stmt = $conn->prepare("SELECT id, full_name, email, is_admin FROM users ORDER BY full_name");
    if ($stmt === false) {
        error_log("Erro na preparação da consulta: " . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        error_log("Erro ao executar consulta: " . $stmt->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

$usuarios = buscarUsuarios($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['alterar_permissao'])) {
    $user_id = $_POST['user_id'];
    $is_admin = $_POST['is_admin'] == '1' ? 1 : 0;

    $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_admin, $user_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Permissão do usuário atualizada com sucesso.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Erro ao atualizar permissão do usuário.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir_usuario'])) {
    $user_id = $_POST['user_id'];

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Usuário excluído com sucesso.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Erro ao excluir usuário.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: admin_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Administrador - Império Odontologia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/adm.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="assets/images-removebg-preview (1).png" alt="Império Odontologia Logo">
                <span class="ms-2">Império Odontologia</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-5">
        <div class="dashboard-cards">
            <div class="card">
                <i class="bi bi-people-fill"></i>
                <h3><?= $total_users ?></h3>
                <p>Usuários Registrados</p>
            </div>
            <div class="card">
                <i class="bi bi-calendar2-check-fill"></i>
                <h3><?= $total_appointments ?></h3>
                <p>Agendamentos Totais</p>
            </div>
            <div class="card">
                <i class="bi bi-bar-chart-fill"></i>
                <h3><?= count($agendamentos) ?></h3>
                <p>Agendamentos Visíveis</p>
            </div>
        </div>

        <div class="table-container mt-5">
            <h4>Todos os Agendamentos</h4>
            <?php if (count($agendamentos) == 0): ?>
                <p class="text-center">Nenhum agendamento encontrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="agendamentosTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Usuário</th>
                                <th>Serviço</th>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Notas</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agendamentos as $index => $agendamento): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($agendamento['full_name']) ?></td>
                                    <td><?= htmlspecialchars($agendamento['service_name']) ?></td>
                                    <td><?= htmlspecialchars($agendamento['appointment_date']) ?></td>
                                    <td><?= htmlspecialchars($agendamento['appointment_time']) ?></td>
                                    <td><?= htmlspecialchars($agendamento['notes']) ?></td>
                                    <td>
                                        <?php if ($agendamento['status'] == 'Agendado'): ?>
                                            <span class="badge bg-info"><i class="bi bi-calendar-event"></i> <?= htmlspecialchars($agendamento['status']) ?></span>
                                        <?php elseif ($agendamento['status'] == 'Completo'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($agendamento['status']) ?></span>
                                        <?php elseif ($agendamento['status'] == 'Cancelado'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($agendamento['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#editarAgendamentoModal" data-id="<?= $agendamento['id'] ?>" data-date="<?= $agendamento['appointment_date'] ?>" data-time="<?= $agendamento['appointment_time'] ?>" data-notes="<?= htmlspecialchars($agendamento['notes']) ?>" data-status="<?= $agendamento['status'] ?>"><i class="bi bi-pencil"></i> Editar</button>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="appointment_id" value="<?= $agendamento['id'] ?>">
                                            <button class="btn btn-danger btn-sm" type="submit" name="excluir_agendamento" onclick="return confirm('Tem certeza que deseja excluir este agendamento?');"><i class="bi bi-trash"></i> Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="table-container mt-5">
            <h4>Todos os Usuários</h4>
            <?php if (count($usuarios) == 0): ?>
                <p class="text-center">Nenhum usuário encontrado.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="usuariosTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Permissão</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $index => $usuario): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($usuario['full_name']) ?></td>
                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td><?= $usuario['is_admin'] ? 'Administrador' : 'Usuário' ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?= $usuario['id'] ?>">
                                            <input type="hidden" name="is_admin" value="<?= $usuario['is_admin'] ? 0 : 1 ?>">
                                            <button class="btn btn-secondary btn-sm" type="submit" name="alterar_permissao">
                                                <?= $usuario['is_admin'] ? 'Rebaixar para Usuário' : 'Promover a Administrador' ?>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?= $usuario['id'] ?>">
                                            <button class="btn btn-danger btn-sm" type="submit" name="excluir_usuario" onclick="return confirm('Tem certeza que deseja excluir este usuário?');"><i class="bi bi-trash"></i> Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal fade" id="editarAgendamentoModal" tabindex="-1" aria-labelledby="editarAgendamentoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarAgendamentoModalLabel">Editar Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="appointment_id" id="editAppointmentId">
                        <div class="mb-3">
                            <label for="editAppointmentDate" class="form-label">Data do Agendamento</label>
                            <input type="date" class="form-control" id="editAppointmentDate" name="appointment_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAppointmentTime" class="form-label">Hora do Agendamento</label>
                            <input type="time" class="form-control" id="editAppointmentTime" name="appointment_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="editNotes" class="form-label">Notas</label>
                            <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editStatus" class="form-label">Status</label>
                            <select class="form-select" id="editStatus" name="status" required>
                                <option value="Agendado">Agendado</option>
                                <option value="Completo">Completo</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" name="editar_agendamento">Salvar Alterações</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#agendamentosTable').DataTable();
            $('#usuariosTable').DataTable();

            $('#editarAgendamentoModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var date = button.data('date');
                var time = button.data('time');
                var notes = button.data('notes');
                var status = button.data('status');

                var modal = $(this);
                modal.find('#editAppointmentId').val(id);
                modal.find('#editAppointmentDate').val(date);
                modal.find('#editAppointmentTime').val(time);
                modal.find('#editNotes').val(notes);
                modal.find('#editStatus').val(status);
            });
        });

        <?php if (isset($_SESSION['message'])): ?>
            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-bottom-right",
                "timeOut": "4000",
                "extendedTimeOut": "2000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };
            toastr["<?= $_SESSION['message_type'] ?>"]("<?= htmlspecialchars($_SESSION['message']) ?>");
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
