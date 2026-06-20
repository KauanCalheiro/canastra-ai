# Frontend (Nuxt)

## Testes

O frontend não tem testes unitários/Vitest. Todo teste de comportamento do frontend é feito via **Playwright**, numa pasta separada na raiz do repositório: `./e2e` (workspace próprio, com seu `package.json` e `playwright.config.ts`, fora de `./frontend`).

`e2e/playwright.config.ts` sobe backend (`php artisan serve`) e frontend (`pnpm dev`) via `webServer` antes de rodar os testes, com `baseURL` apontando para o frontend.

## Proxy para o backend

O frontend nunca chama o backend Laravel diretamente do client. Sempre: client chama `$fetch('/api/<rota>')` → rota server-side do Nuxt (`server/api/<rota>.ts`) → proxy para a API do Laravel.

A rota server-side usa um client centralizado em `server/utils/clients.ts` (função `canastraClient()`), que retorna um `$fetch.create({ baseURL, ... })` configurado a partir da runtime config do Nuxt (`runtimeConfig.backendUrl`, via env `NUXT_BACKEND_URL`).
