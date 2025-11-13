# Sistema de Agendamento de Salas com Créditos

Sistema de gerenciamento de agendamento de salas com controle de créditos mensais.

## 🏗️ Estrutura do Banco de Dados

### Tabelas Criadas

#### 1. **users** (básica)

Cadastro de usuários internos (autenticação e permissões):

-   `id`, `name`, `email`, `password`, `remember_token`, `email_verified_at`

#### 2. **clients** (nova)

Clientes da clínica, com controle de créditos:

-   `id` - ID do cliente
-   `name` - Nome/Razão Social
-   `email` - E-mail (único)
-   `phone` - Telefone/celular
-   `birth_date` - Data de aniversário (opcional)
-   `credit_balance` - Saldo de horas disponíveis (decimal 8,2)
-   `credit_expires_at` - Data de expiração dos créditos
-   `created_at` / `updated_at`

#### 3. **rooms**

Salas disponíveis para agendamento:

-   `id` - ID da sala
-   `number` - Número/código único da sala
-   `name` - Nome da sala
-   `description` - Descrição (opcional)
-   `capacity` - Capacidade de pessoas (opcional)
-   `is_active` - Status da sala (ativa/inativa)

#### 4. **bookings**

Agendamentos realizados:

-   `id` - ID do agendamento
-   `client_id` - Cliente responsável pelo agendamento
-   `room_id` - Sala agendada
-   `booking_date` - Data do agendamento
-   `start_time` - Hora de início
-   `end_time` - Hora de término
-   `hours_booked` - Quantidade de horas agendadas
-   `status` - Status (pending/confirmed/cancelled/completed)
-   `notes` - Observações (opcional)
-   `cancelled_at` - Data/hora do cancelamento

## 📋 Regras de Negócio

### Sistema de Créditos

1. **Expiração Mensal**: Créditos expiram no último dia do mês corrente
2. **Débito Automático**: Ao criar um agendamento, as horas são debitadas automaticamente
3. **Devolução em Cancelamento**: Ao cancelar um agendamento, as horas são devolvidas ao saldo
4. **Validação**: Sistema impede agendamento se não houver crédito suficiente

### Fluxo de Agendamento

1. Cliente solicita agendamento com quantidade de horas
2. Sistema verifica se há crédito suficiente
3. Se houver, debita o crédito e cria o agendamento
4. Se cancelar, crédito volta para o saldo do cliente

## 🚀 Instalação

### 1. Executar as Migrations

```bash
php artisan migrate
```

### 2. Instalar o Passport (se ainda não instalou)

```bash
php artisan passport:install
# ou
php artisan passport:keys
```

## 📊 Endpoints da API

Todos os endpoints estão no arquivo `routes/api.php` com prefixo `/api`.

### Autenticação

-   `POST /api/register` - Registro de usuário (TODO)
-   `POST /api/login` - Login (TODO)

### Salas

-   `GET /api/rooms` - Listar salas ativas
-   `GET /api/rooms/{id}` - Detalhes de uma sala

### Agendamentos

-   `GET /api/bookings?client_id=1` - Listar agendamentos do cliente informado
-   `POST /api/bookings` - Criar novo agendamento (informar `client_id` no payload)
-   `POST /api/bookings/{id}/cancel` - Cancelar agendamento do cliente (informar `client_id`)

### Créditos

-   `GET /api/credits/balance?client_id=1` - Consultar saldo e data de expiração de um cliente

### Relatórios

-   `GET /api/reports/popular-days` - Dias da semana mais alugados
-   `GET /api/reports/popular-times` - Horários mais populares
-   `GET /api/reports/popular-rooms` - Salas mais utilizadas
-   `GET /api/reports/birthdays?month=10` - Aniversariantes do mês
-   `GET /api/reports/birthdays/today` - Aniversariantes de hoje

## 💡 Exemplos de Uso

### Criar Agendamento

```bash
POST /api/bookings
{
  "client_id": 1,
  "room_id": 1,
  "booking_date": "2025-10-25",
  "start_time": "10:00",
  "end_time": "12:00",
  "hours_booked": 2.0,
  "notes": "Reunião de equipe"
}
```

### Adicionar Créditos (via código)

```php
$client = Client::find(1);
$client->addCredit(10); // Adiciona 10 horas com expiração no fim do mês
```

### Verificar Créditos

```php
$client = Client::find(1);
$client->checkAndExpireCredits(); // Zera créditos se expirados
$balance = $client->credit_balance;
```

### Cancelar Agendamento

```php
$booking = Booking::find(1);
$booking->cancel(); // Cancela e devolve créditos automaticamente
```

## 🎯 Métodos Úteis nos Models

### Client Model

-   `hasSufficientCredit($hours)` - Verifica se o cliente tem crédito suficiente
-   `addCredit($hours)` - Adiciona créditos (define expiração automaticamente)
-   `debitCredit($hours)` - Debita créditos
-   `creditCredit($hours)` - Devolve créditos
-   `checkAndExpireCredits()` - Verifica e expira créditos se necessário
-   `Client::birthdaysInMonth($month)` - Retorna aniversariantes do mês
-   `Client::birthdaysToday()` - Retorna aniversariantes de hoje

### Booking Model

-   `cancel()` - Cancela o agendamento
-   `Booking::active()` - Scope para agendamentos ativos
-   `Booking::cancelled()` - Scope para agendamentos cancelados
-   `Booking::dateRange($start, $end)` - Scope para filtrar por período

### Room Model

-   `Room::active()` - Scope para salas ativas

## 🔄 Observers e Eventos

O sistema utiliza **Model Events** para automatizar processos:

### Booking Model

-   **creating**: Ao criar, verifica crédito e debita automaticamente
-   **updating**: Ao cancelar (mudar status para 'cancelled'), devolve créditos

## 📈 Queries de Relatórios

### Dias da Semana Mais Alugados

```php
$stats = Booking::select(
    DB::raw('DAYOFWEEK(booking_date) as day_of_week'),
    DB::raw('COUNT(*) as total_bookings')
)
->where('status', '!=', 'cancelled')
->groupBy('day_of_week')
->orderBy('total_bookings', 'desc')
->get();
```

### Horários Mais Populares

```php
$stats = Booking::select('start_time', DB::raw('COUNT(*) as total'))
->where('status', '!=', 'cancelled')
->groupBy('start_time')
->orderBy('total', 'desc')
->get();
```

### Salas Mais Utilizadas

```php
$stats = Room::withCount(['bookings' => function ($query) {
    $query->where('status', '!=', 'cancelled');
}])->orderBy('bookings_count', 'desc')->get();
```

## ⚠️ Considerações Importantes

1. **Expiração de Créditos**: Execute `checkAndExpireCredits()` antes de operações críticas
2. **Validação**: Sempre valide se há crédito antes de permitir agendamento
3. **Cancelamentos**: Créditos são devolvidos automaticamente ao cancelar
4. **Status**: Use os status corretos (pending, confirmed, cancelled, completed)
5. **Índices**: Tabela bookings tem índices otimizados para consultas de relatórios

## 🛠️ Próximos Passos

-   [ ] Implementar autenticação (login/register)
-   [ ] Criar painel admin para gerenciar créditos
-   [ ] Adicionar notificações de aniversário
-   [ ] Criar job agendado para expirar créditos automaticamente
-   [ ] Implementar validação de conflito de horários
-   [ ] Adicionar sistema de pagamento para compra de créditos
