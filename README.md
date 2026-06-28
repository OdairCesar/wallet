# Wallet — Carteira Digital Open Finance

API de carteira digital alinhada ao **Open Finance Brasil**, com arquitetura **Event Sourcing** (Kafka), **CQRS** e adapters plugáveis para integração com bancos e fintechs participantes.

A interface web expõe apenas a **home** (`/`) e a **documentação da API** (`/docs/api`). Não há páginas de login, configurações ou fluxos de consentimento no front-end — toda a operação da carteira é feita via **API REST**.

---

## Sumário

- [Visão geral](#visão-geral)
- [Stack](#stack)
- [Arquitetura](#arquitetura)
- [Pré-requisitos](#pré-requisitos)
- [Instalação local](#instalação-local)
- [Documentação da API (Swagger)](#documentação-da-api-swagger)
- [Fluxo de exemplo](#fluxo-de-exemplo)
- [Endpoints](#endpoints)
- [Variáveis de ambiente](#variáveis-de-ambiente)
- [Testes](#testes)
- [Deploy com Docker](#deploy-com-docker)
- [Estrutura do projeto](#estrutura-do-projeto)

---

## Visão geral

O projeto implementa o papel de **detentora de conta** no Open Finance:

- Expõe APIs conformes (consentimentos, contas, saldos, extrato, PIX, recursos)
- Mantém o domínio desacoplado via eventos (Kafka em produção, in-memory em dev)
- Projeta read models em banco relacional para consultas rápidas (CQRS)
- Permite conectar participantes externos via `ParticipantAdapterInterface`

---

## Stack

| Camada | Tecnologia |
|--------|------------|
| Backend | PHP 8.3+, Laravel 13 |
| API docs | [Scramble](https://scramble.dedoc.co/) (OpenAPI 3 + Swagger UI) |
| Frontend | React 19 + Inertia (somente home informativa) |
| Event bus (dev) | In-memory (síncrono) |
| Event bus (prod) | Apache Kafka |
| Banco (dev) | SQLite |
| Banco (prod) | PostgreSQL |
| Testes | Pest 4, contract tests (JSON Schema) |

---

## Arquitetura

```
Cliente (ITP / parceiro)
        │
        ▼
Open Finance Gateway  ──►  Event Bus (Kafka / in-memory)
        │                          │
        │                          ▼
        │                   Aggregates + Fraud
        │                          │
        ▼                          ▼
   Read models (PostgreSQL/SQLite) ◄── Projectors
```

- **Command side:** HTTP → validação → publica evento → `202 Accepted` (operações assíncronas)
- **Query side:** lê projeções (`GET` saldo, extrato, status)
- **Anti-Corruption Layer:** traduz contratos Open Finance ↔ eventos internos

---

## Pré-requisitos

- PHP 8.3+
- Composer 2
- Node.js 20+ e npm (para assets da home)
- Extensões PHP: `pdo`, `pdo_sqlite` (dev) ou `pdo_pgsql` (prod)

Kafka, Redis e Docker **não são obrigatórios** para desenvolvimento local.

---

## Instalação local

### 1. Dependências e banco

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 2. Variáveis mínimas no `.env`

```env
APP_NAME=Wallet
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
EVENT_BUS_DRIVER=inmemory
```

### 3. Subir a aplicação

São **dois processos** — o Vite sozinho não serve a aplicação Laravel:

**Terminal 1 — Laravel**

```bash
php artisan serve
```

**Terminal 2 — assets (hot reload da home)**

```bash
npm install
npm run dev
```

Acesse:

| URL | Descrição |
|-----|-----------|
| http://127.0.0.1:8000 | Home do projeto |
| http://127.0.0.1:8000/docs/api | Swagger / OpenAPI |
| http://127.0.0.1:8000/api/open-banking/... | API |

**Alternativa sem Vite em dev:** rode `npm run build` uma vez e use apenas `php artisan serve`.

---

## Documentação da API (Swagger)

Documentação interativa gerada automaticamente pelo [Scramble](https://github.com/dedoc/scramble):

| URL | Conteúdo |
|-----|----------|
| `/docs/api` | UI Swagger (Stoplight Elements) |
| `/docs/api.json` | Spec OpenAPI 3.1 (JSON) |
| `/docs` ou `/swagger` | Atalhos → `/docs/api` |

> Em `APP_ENV=local` a documentação é pública. Em produção, configure a gate `viewApiDocs` ou mantenha `APP_ENV=local` apenas em ambientes de homologação.

Exportar a spec:

```bash
php artisan scramble:export --path=storage/api/openapi.json
```

---

## Fluxo de exemplo

Criar consentimento → autorizar → criar conta → consultar saldo → iniciar PIX:

```bash
# 1. Criar consentimento
curl -X POST http://127.0.0.1:8000/api/open-banking/consents/v3/consents \
  -H "Content-Type: application/json" \
  -H "x-fapi-interaction-id: $(uuidgen)" \
  -d '{"data":{"permissions":["PAYMENTS_INITIATE","ACCOUNTS_READ"]}}'

# 2. Autorizar (simula usuário na detentora)
curl -X POST http://127.0.0.1:8000/api/open-banking/consents/v3/consents/{consentId}/authorise

# 3. Criar conta
curl -X POST http://127.0.0.1:8000/api/open-banking/accounts/v2/accounts \
  -H "Content-Type: application/json" \
  -d '{"data":{"accountType":"PERSONAL"}}'

# 4. Consultar saldo
curl http://127.0.0.1:8000/api/open-banking/accounts/v2/accounts/{accountId}/balances

# 5. Iniciar pagamento PIX
curl -X POST http://127.0.0.1:8000/api/open-banking/payments/v5/pix/payments \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "consentId": "{consentId}",
      "localInstrument": "DICT",
      "payment": {"amount": "100.00", "currency": "BRL"},
      "creditorAccount": {"accountId": "{accountId}"}
    }
  }'
```

---

## Endpoints

Base: `/api/open-banking`

### Consentimentos (v3)

| Método | Rota | Descrição |
|--------|------|-----------|
| `POST` | `/consents/v3/consents` | Criar consentimento |
| `GET` | `/consents/v3/consents/{consentId}` | Consultar |
| `PATCH` | `/consents/v3/consents/{consentId}` | Revogar |
| `POST` | `/consents/v3/consents/{consentId}/authorise` | Autorizar |

### Contas (v2)

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/accounts/v2/accounts` | Listar contas |
| `POST` | `/accounts/v2/accounts` | Criar conta |
| `GET` | `/accounts/v2/accounts/{accountId}` | Detalhe |
| `GET` | `/accounts/v2/accounts/{accountId}/balances` | Saldo |
| `GET` | `/accounts/v2/accounts/{accountId}/transactions` | Extrato |
| `POST` | `/accounts/v2/accounts/{accountId}/transfers` | Transferência P2P (`202`) |

### Pagamentos PIX (v5)

| Método | Rota | Descrição |
|--------|------|-----------|
| `POST` | `/payments/v5/pix/payments` | Iniciar PIX |
| `GET` | `/payments/v5/pix/payments/{paymentId}` | Consultar status |
| `PATCH` | `/payments/v5/pix/payments/{paymentId}` | Cancelar |

### Outros

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/resources/v3/resources` | Recursos disponíveis |
| `GET` | `/operations/v1/operations/{correlationId}` | Status assíncrono |

### Headers FAPI (recomendados)

| Header | Uso |
|--------|-----|
| `x-fapi-interaction-id` | UUID de correlação (gerado automaticamente se omitido) |
| `x-idempotency-key` | Idempotência em POST/PATCH de escrita |
| `Authorization` | Bearer token OAuth2/FAPI (produção) |

Respostas seguem o envelope Open Finance: `{ "data": ... }` ou `{ "errors": [...] }`.

---

## Variáveis de ambiente

| Variável | Default | Descrição |
|----------|---------|-----------|
| `EVENT_BUS_DRIVER` | `inmemory` | `inmemory` (dev) ou `kafka` (prod) |
| `KAFKA_BROKERS` | `localhost:9092` | Brokers Kafka |
| `DB_CONNECTION` | `sqlite` | `sqlite` ou `pgsql` |
| `OF_BRAND_NAME` | `APP_NAME` | Nome exibido nas respostas OF |
| `OF_ORGANISATION_ID` | — | CNPJ/ID da organização |
| `WALLET_DAILY_TRANSFER_LIMIT` | `50000` | Limite diário (centavos) |
| `FAPI_ENABLED` | `false` | Habilita validação FAPI |
| `MTLS_ENABLED` | `false` | mTLS (produção) |
| `API_VERSION` | `1.0.0` | Versão na doc OpenAPI |

---

## Testes

Não requer Kafka, Docker ou Redis — usa SQLite in-memory e event bus in-memory.

```bash
# Contratos de saída (envelope, resources, adapters)
php artisan test --testsuite=Output

# API Open Finance (feature)
php artisan test tests/Feature/OpenFinance

# Suite completa
php artisan test
```

---

## Deploy com Docker

O [`docker-compose.yml`](docker-compose.yml) sobe PostgreSQL, Redis, Kafka e a aplicação:

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
```

Na VM, ajuste o `.env`:

```env
APP_ENV=production
DB_CONNECTION=pgsql
DB_HOST=postgres
EVENT_BUS_DRIVER=kafka
KAFKA_BROKERS=kafka:9092
REDIS_HOST=redis
QUEUE_CONNECTION=redis
```

Para Kafka em produção, instale também:

```bash
composer require mateusjunges/laravel-kafka
# ext-rdkafka no PHP
```

Implemente o publish real em [`app/Infrastructure/Events/KafkaEventPublisher.php`](app/Infrastructure/Events/KafkaEventPublisher.php) (atualmente é um stub).

---

## Estrutura do projeto

```
app/
├── OpenFinance/          # Gateway HTTP, resources, adapters, consent
│   ├── Http/Controllers/
│   ├── Http/Requests/
│   └── Adapters/         # Integração plugável com participantes
├── Wallet/               # Aggregate, commands, transferências
├── Payments/             # PIX v5
├── Fraud/                # Regras antifraude
├── Projections/          # Read models + projectors
└── Infrastructure/Events/  # Event bus (in-memory / Kafka)

routes/
├── api.php               # Rotas Open Finance
└── web.php               # Home + redirects /docs

tests/
├── Output/               # Testes de contrato (saídas)
├── Feature/OpenFinance/  # Testes da API
└── Contracts/            # JSON Schema + golden files

config/
├── event_bus.php
├── open_finance.php
└── scramble.php          # Swagger / OpenAPI
```

---

## Referências

- [Open Finance Brasil — Specs](https://github.com/OpenBanking-Brasil/specs)
- [Open Finance Brasil — Segurança FAPI](https://github.com/OpenBanking-Brasil/specs-seguranca)
- [Scramble (documentação Laravel)](https://scramble.dedoc.co/)

---

## Licença

MIT
