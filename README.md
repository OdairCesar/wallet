# Wallet — Open Finance API

Carteira digital API-only, alinhada ao Open Finance Brasil, com Event Sourcing (Kafka) e CQRS.

## Desenvolvimento local (sem Docker/Kafka)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Configure no `.env`:

```
EVENT_BUS_DRIVER=inmemory
DB_CONNECTION=sqlite
```

A API Open Finance fica em `/api/open-banking/...`.

Documentação interativa (Swagger / OpenAPI): **http://127.0.0.1:8000/docs/api**

Atalhos: `/docs` ou `/swagger` redirecionam para a documentação.

## Testes (sem e2e — não requer Kafka/Docker)

```bash
php artisan test --testsuite=Output
php artisan test --testsuite=Feature --filter=OpenFinance
```

## Deploy na VM (Docker)

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
```

Na VM, altere `EVENT_BUS_DRIVER=kafka` e instale `mateusjunges/laravel-kafka` + ext-rdkafka para produção.

## Endpoints principais

| Método | Rota |
|--------|------|
| POST | `/api/open-banking/consents/v3/consents` |
| POST | `/api/open-banking/consents/v3/consents/{id}/authorise` |
| GET | `/api/open-banking/accounts/v2/accounts` |
| POST | `/api/open-banking/accounts/v2/accounts` |
| GET | `/api/open-banking/accounts/v2/accounts/{id}/balances` |
| POST | `/api/open-banking/payments/v5/pix/payments` |
| GET | `/api/open-banking/resources/v3/resources` |

Headers: `x-fapi-interaction-id`, `x-idempotency-key` (recomendado).
