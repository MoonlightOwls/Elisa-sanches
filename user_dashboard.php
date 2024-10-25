<?php
session_start();
require_once 'config.php';


function set_feedback_message($message, $type) {
    $_SESSION['feedback_message'] = htmlspecialchars($message);
    $_SESSION['feedback_type'] = htmlspecialchars($type);
}


function log_error($message) {
    error_log($message, 3, 'errors.log');
}
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin']) {
    $_SESSION['message'] = "Acesso negado. Área restrita para usuários.";
    $_SESSION['message_type'] = "danger";
    header("Location: login.php");
    exit();
}


//if (!isset($_SESSION['user_id'])) {
    //header("Location: index.php");
    //exit();
//}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


$feedback_message = '';
$feedback_type = 'info';
if (isset($_SESSION['feedback_message'])) {
    $feedback_message = $_SESSION['feedback_message'];
    $feedback_type = $_SESSION['feedback_type'];
    unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);
}


$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if ($stmt === false) {
    log_error("Erro ao preparar a consulta para buscar informações do usuário: " . $conn->error);
    die("Erro interno. Tente novamente mais tarde.");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();


$appointments_stmt = $conn->prepare(
    "SELECT appointments.*, services.name AS service_name 
    FROM appointments 
    JOIN services ON appointments.service = services.id 
    WHERE appointments.user_id = ? 
    ORDER BY appointment_date, appointment_time"
);
if ($appointments_stmt === false) {
    log_error("Erro ao preparar a consulta para buscar agendamentos: " . $conn->error);
    die("Erro interno. Tente novamente mais tarde.");
}
$appointments_stmt->bind_param("i", $user_id);
$appointments_stmt->execute();
$appointments = $appointments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$appointments_stmt->close();


$services_stmt = $conn->prepare("SELECT id, name FROM services");
if ($services_stmt === false) {
    log_error("Erro ao preparar a consulta para buscar serviços: " . $conn->error);
    die("Erro interno. Tente novamente mais tarde.");
}
$services_stmt->execute();
$services = $services_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$services_stmt->close();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_appointment']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $service_id = $_POST['service'];
    $notes = htmlspecialchars($_POST['notes'] ?? '');
    $patient_address = htmlspecialchars($_POST['patient_address'] ?? '');
    $patient_phone = htmlspecialchars($_POST['patient_phone'] ?? '');

    
    if (empty($service_id) || !is_numeric($service_id)) {
        set_feedback_message("Erro: Serviço não foi selecionado corretamente.", 'danger');
    } elseif (strtotime($appointment_time) < strtotime('08:00') || strtotime($appointment_time) > strtotime('18:00')) {
        set_feedback_message("Erro: O horário deve estar entre 08:00 e 18:00.", 'danger');
    } else {
        
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ?");
        if ($check_stmt === false) {
            log_error("Erro ao preparar a consulta para verificar conflitos de agendamento: " . $conn->error);
            set_feedback_message("Erro interno. Tente novamente mais tarde.", 'danger');
        } else {
            $check_stmt->bind_param("ss", $appointment_date, $appointment_time);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($result['count'] > 0) {
                set_feedback_message("Erro: Já existe um agendamento para este dia e horário.", 'danger');
            } else {
                $stmt = $conn->prepare("INSERT INTO appointments (user_id, service, appointment_date, appointment_time, notes, patient_address, patient_phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())");
                if ($stmt === false) {
                    log_error("Erro ao preparar a consulta para criar agendamento: " . $conn->error);
                    set_feedback_message("Erro interno. Tente novamente mais tarde.", 'danger');
                } else {
                    $stmt->bind_param("iisssss", $user_id, $service_id, $appointment_date, $appointment_time, $notes, $patient_address, $patient_phone);
                    $stmt->execute();
                    if ($stmt->error) {
                        log_error("Erro ao executar a consulta para criar agendamento: " . $stmt->error);
                        set_feedback_message("Erro interno. Tente novamente mais tarde.", 'danger');
                    } else {
                        set_feedback_message("Agendamento criado com sucesso!", 'success');
                        
                        header("Location: user_dashboard.php");
                        exit();
                    }
                    $stmt->close();
                }
            }
        }
    }
}


if (isset($_GET['delete_appointment']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    $appointment_id = $_GET['delete_appointment'];

    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        log_error("Erro ao preparar a consulta para excluir agendamento: " . $conn->error);
        set_feedback_message("Erro interno. Tente novamente mais tarde.", 'danger');
    } else {
        $stmt->bind_param("ii", $appointment_id, $user_id);
        $stmt->execute();
        if ($stmt->error) {
            log_error("Erro ao executar a consulta para excluir agendamento: " . $stmt->error);
            set_feedback_message("Erro interno. Tente novamente mais tarde.", 'danger');
        } else {
            set_feedback_message("Agendamento excluído com sucesso!", 'success');
            
            header("Location: user_dashboard.php");
            exit();
        }
        $stmt->close();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_appointment']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $appointment_id = $_POST['appointment_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $service_id = $_POST['service'];
    $notes = htmlspecialchars($_POST['notes'] ?? '');
    $patient_address = htmlspecialchars($_POST['patient_address'] ?? '');
    $patient_phone = htmlspecialchars($_POST['patient_phone'] ?? '');

 
    if (empty($service_id) || !is_numeric($service_id)) {
        set_feedback_message("Erro: Serviço não foi selecionado corretamente.", 'danger');
    } elseif (strtotime($appointment_time) < strtotime('08:00') || strtotime($appointment_time) > strtotime('18:00')) {
        set_feedback_message("Erro: O horário deve estar entre 08:00 e 18:00.", 'danger');
    } else {
        
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND id != ?");
        if ($check_stmt === false) {
            log_error("Erro ao preparar a consulta para verificar conflitos de agendamento: " . $conn->error);
            set_feedback_message("Erro interno. Tente novamente mais tarde.", 'danger');
        } else {
            $check_stmt->bind_param("ssi", $appointment_date, $appointment_time, $appointment_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($result['count'] > 0) {
                set_feedback_message("Erro: Já existe um agendamento para este dia e horário.", 'danger');
            } else {
                $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, service = ?, notes = ?, patient_address = ?, patient_phone = ? WHERE id = ? AND user_id = ?");
                if ($stmt === false) {
                    log_error("Erro ao preparar a consulta para editar agendamento: " . $conn->error);
                    set_feedback_message("Erro interno. Tente novamente mais tarde.", 'danger');
                } else {
                    $stmt->bind_param("sssssiii", $appointment_date, $appointment_time, $service_id, $notes, $patient_address, $patient_phone, $appointment_id, $user_id);
                    $stmt->execute();
                    if ($stmt->error) {
                        log_error("Erro ao executar a consulta para editar agendamento: " . $stmt->error);
                        set_feedback_message("Erro interno. Tente novamente mais tarde.", 'danger');
                    } else {
                        set_feedback_message("Agendamento atualizado com sucesso!", 'success');
                        
                        header("Location: user_dashboard.php");
                        exit();
                    }
                    $stmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Paciente - Império Odontologia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="assets/images-removebg-preview">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Roboto', sans-serif;
        }
        .navbar {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .appointment-section {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .appointment-section:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        .btn-primary {
            background: linear-gradient(to right, #007bff, #0056b3);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.4);
        }
        .btn-danger:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.4);
        }
        .form-section-icon {
            color: #007bff;
        }
        .input-group-text {
            background: #f8f9fa;
        }
        .table thead {
            background: #007bff;
            color: white;
        }
        .modal-header {
            background-color: #007bff;
            color: #fff;
        }
        .modal-header .btn-close {
            filter: invert(1);
        }
        .modal-body {
            padding: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="assets/images-removebg-preview (1).png" alt="Odonto Prime Logo" width="70" height="70" class="d-inline-block align-top me-2">
                <span class="fw-bold text-primary">Império Odontologia</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Meus Agendamentos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-5">
       
        <?php if (!empty($feedback_message)): ?>
            <div class="alert alert-<?= $feedback_type ?> alert-dismissible fade show" role="alert">
                <i class="bi <?= $feedback_type == 'success' ? 'bi-check-circle' : ($feedback_type == 'danger' ? 'bi-exclamation-circle' : 'bi-info-circle') ?> me-2"></i>
                <?= htmlspecialchars($feedback_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <script>
                if (window.history.replaceState) {
                    window.history.replaceState(null, '', window.location.pathname);
                }
            </script>
        <?php endif; ?>


        <div class="appointment-section mb-5">
            <div class="d-flex align-items-center mb-4">
                <i class="bi bi-calendar-check form-section-icon me-2"></i>
                <h3 class="mb-0">Criar Novo Agendamento</h3>
            </div>
            <form method="POST" action="user_dashboard.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="appointment_date" class="form-label">Data do Agendamento:</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" name="appointment_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="appointment_time" class="form-label">Hora do Agendamento:</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-clock"></i></span>
                            <input type="time" name="appointment_time" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="service" class="form-label">Tipo de Consulta:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-clipboard-plus"></i></span>
                        <select name="service" class="form-select" required>
                            <option value="">Selecione o Tipo de Consulta</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="patient_address" class="form-label">Endereço do Paciente:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-house"></i></span>
                        <input type="text" name="patient_address" class="form-control" placeholder="Endereço Completo" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="patient_phone" class="form-label">Telefone do Paciente:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="tel" name="patient_phone" class="form-control" placeholder="(XX) XXXXX-XXXX" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="notes" class="form-label">Notas Adicionais:</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-pencil"></i></span>
                        <textarea name="notes" class="form-control" rows="4" placeholder="Insira qualquer detalhe adicional..."></textarea>
                    </div>
                </div>
                <button type="submit" name="create_appointment" class="btn btn-primary w-100"><i class="bi bi-check-circle me-2"></i>Agendar</button>
            </form>
        </div>

        
        <h2>Meus Agendamentos</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Tipo de Consulta</th>
                        <th>Endereço</th>
                        <th>Telefone</th>
                        <th>Notas</th>
                        <th>Status</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appointments) == 0): ?>
                        <tr>
                            <td colspan="9" class="text-center">Nenhum agendamento encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $index => $appointment): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($appointment['appointment_date']) ?></td>
                                <td><?= htmlspecialchars($appointment['appointment_time']) ?></td>
                                <td><?= htmlspecialchars($appointment['service_name']) ?></td>
                                <td><?= htmlspecialchars($appointment['patient_address']) ?></td>
                                <td><?= htmlspecialchars($appointment['patient_phone']) ?></td>
                                <td><?= htmlspecialchars($appointment['notes']) ?></td>
                                <td><span class="badge bg-success"><?= htmlspecialchars($appointment['status']) ?></span></td>
                                <td>
                                    <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editModal" onclick="populateEditModal(<?= htmlspecialchars(json_encode($appointment)) ?>)"><i class="bi bi-pencil"></i> Editar</button>
                                    <a href="user_dashboard.php?delete_appointment=<?= $appointment['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> Excluir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- edt agenda-->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Editar Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAppointmentForm" method="POST" action="user_dashboard.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="appointment_id" id="editAppointmentId">
                        <div class="mb-3">
                            <label for="editAppointmentDate" class="form-label">Data do Agendamento:</label>
                            <input type="date" name="appointment_date" id="editAppointmentDate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAppointmentTime" class="form-label">Hora do Agendamento:</label>
                            <input type="time" name="appointment_time" id="editAppointmentTime" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editService" class="form-label">Tipo de Consulta:</label>
                            <select name="service" id="editService" class="form-select" required>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= $service['id'] ?>"><?= htmlspecialchars($service['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editPatientAddress" class="form-label">Endereço do Paciente:</label>
                            <input type="text" name="patient_address" id="editPatientAddress" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPatientPhone" class="form-label">Telefone do Paciente:</label>
                            <input type="tel" name="patient_phone" id="editPatientPhone" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editNotes" class="form-label">Notas Adicionais:</label>
                            <textarea name="notes" id="editNotes" class="form-control" rows="4" placeholder="Insira qualquer detalhe adicional..."></textarea>
                        </div>
                        <button type="submit" name="edit_appointment" class="btn btn-primary w-100"><i class="bi bi-save me-2"></i>Salvar Alterações</button>
                    </form>
                </div>
            </div>
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

            // isso e corno
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
        });
    </script>

    <script>
        // edt modal
        function populateEditModal(appointment) {
            document.getElementById('editAppointmentId').value = appointment.id;
            document.getElementById('editAppointmentDate').value = appointment.appointment_date;
            document.getElementById('editAppointmentTime').value = appointment.appointment_time;
            document.getElementById('editService').value = appointment.service;
            document.getElementById('editPatientAddress').value = appointment.patient_address;
            document.getElementById('editPatientPhone').value = appointment.patient_phone;
            document.getElementById('editNotes').value = appointment.notes;
        }
    </script>
</body>
</html>
