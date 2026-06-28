# Canastra AI

Monorepo com três projetos independentes (cada um com seu próprio gerenciador de dependências, sem workspace compartilhado):

| Pasta | Stack | O que é |
|---|---|---|
| `frontend/` | Nuxt 4 + Vue | UI. Nunca chama o backend direto — sempre via rota server-side do Nuxt (proxy). |
| `backend/` | Laravel 13 + PHP | API (`/api/*`), persistência (SQLite). |
| `e2e/` | Playwright | Testes end-to-end, fora do `frontend/`. |

Convenções de cada parte (TDD, padrão Action/Data/Resource, data-testid, etc.) estão documentadas em `./.claude/memories/` — ver tabela no [CLAUDE.md](CLAUDE.md).

## Pré-requisitos

- Node.js + [pnpm](https://pnpm.io/)
- PHP 8.3+ e [Composer](https://getcomposer.org/)

## Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env   # se ainda não existir
php artisan key:generate
php artisan migrate
php artisan serve --port=8000
```

A API fica em `http://localhost:8000` (rotas em `/api/*`).

Rodar os testes (Pest):

```bash
cd backend
./vendor/bin/pest
```

## Frontend (Nuxt)

```bash
cd frontend
pnpm install
pnpm dev
```

O frontend sobe em `http://localhost:3000` e espera o backend em `http://localhost:8000` por padrão. Para apontar para outra URL, defina a env var `NUXT_BACKEND_URL` antes de rodar:

```bash
NUXT_BACKEND_URL=http://localhost:8000 pnpm dev
```

Build de produção:

```bash
pnpm build
```

## Rodando tudo junto

Em três terminais (ou usando os testes e2e, que já sobem os dois automaticamente — veja abaixo):

```bash
# terminal 1
cd backend && php artisan serve --port=8000

# terminal 2
cd frontend && pnpm dev
```

## Testes end-to-end (Playwright)

```bash
cd e2e
pnpm install
pnpm test
```

O `e2e/playwright.config.ts` sobe automaticamente o backend (`php artisan serve`) e o frontend (`pnpm dev`) antes de rodar os testes — não é necessário iniciá-los manualmente.
