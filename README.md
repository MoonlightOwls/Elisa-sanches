# 🦷 Clínica Odontológica - Sistema de Agendamento e Gerenciamento de Estoque

Este é um projeto de extensão onde desenvolvemos um sistema de agendamento para uma clínica odontológica. O objetivo do projeto é permitir que os usuários façam agendamentos de consultas diretamente pelo website, além de oferecer funcionalidades administrativas, como o gerenciamento de agendamentos, controle de estoque de ferramentas e itens da clínica.

## 🚀 Funcionalidades

- **Agendamento de Consultas**: Usuários podem marcar consultas com dentistas disponíveis através de uma interface intuitiva.
- **Autenticação e Autorização**: Sistema de login e registro para pacientes e administradores.
- **Recuperação de Senha**: Possibilidade de recuperar senhas via email.
- **Painel Administrativo**:
  - **Gestão de Agendamentos**: Administradores podem visualizar, editar ou cancelar consultas.
  - **Gestão de Estoque**: Controle de ferramentas e outros itens usados na clínica, com funcionalidades como adição, remoção e atualização de quantidades de estoque.
- **Proteção de Sessões com JWT**: As sessões de usuário são protegidas com tokens JWT armazenados de forma segura.

## 🛠️ Tecnologias Utilizadas

- **Frontend**: [React](https://reactjs.org/) com [Axios](https://axios-http.com/) para comunicação com a API
- **Backend**: [NestJS](https://nestjs.com/) com [Prisma ORM](https://www.prisma.io/)
- **Banco de Dados**: MySQL
- **Autenticação**: JWT (JSON Web Token)
- **Envio de Emails**: [NodeMailer](https://nodemailer.com/) para envio de emails, como recuperação de senha.
