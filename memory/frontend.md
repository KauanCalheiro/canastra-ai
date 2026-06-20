# Frontend (Nuxt)

## Testes

O frontend não tem testes unitários/Vitest. Todo teste de comportamento do frontend é feito via **Playwright**, numa pasta separada na raiz do repositório: `./e2e` (workspace próprio, com seu `package.json` e `playwright.config.ts`, fora de `./frontend`).

`e2e/playwright.config.ts` sobe backend (`php artisan serve`) e frontend (`pnpm dev`) via `webServer` antes de rodar os testes, com `baseURL` apontando para o frontend.

O reporter usa `printSteps: true` (`reporter: [['list', { printSteps: true }]]`) — **todo teste Playwright deve usar `test.step('texto explicando o que está acontecendo', async () => { ... })`** para cada ação relevante (ex: "acessa a página de nova partida", "preenche o nome dos jogadores"), para que o terminal mostre exatamente o que o teste está fazendo passo a passo.

## data-testid

**Todo elemento interativo ou relevante para teste no frontend SEMPRE deve ter um `data-testid`** (inputs, botões, títulos/textos que os testes verificam, etc). Os testes Playwright sempre selecionam via `page.getByTestId('...')` — nunca por placeholder, texto visível ou role, que mudam com frequência e quebram o teste sem motivo real. Ao implementar qualquer componente/página nova, adicionar o `data-testid` desde já, antes mesmo de existir um teste cobrindo aquele elemento.

## Proxy para o backend

O frontend nunca chama o backend Laravel diretamente do client. Sempre: client chama `$fetch('/api/<rota>')` → rota server-side do Nuxt (`server/api/<rota>.ts`) → proxy para a API do Laravel.

A rota server-side usa um client centralizado em `server/utils/clients.ts` (função `canastraClient()`), que retorna um `$fetch.create({ baseURL, ... })` configurado a partir da runtime config do Nuxt (`runtimeConfig.backendUrl`, via env `NUXT_BACKEND_URL`).
