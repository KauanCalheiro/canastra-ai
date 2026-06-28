# Como rodar o projeto

Três projetos independentes, sem workspace compartilhado — ver `README.md` na raiz para o passo a passo completo e atualizado.

Resumo:

- **Backend**: `cd backend && composer install && php artisan migrate && php artisan serve --port=8000`. Testes: `./vendor/bin/pest` (sempre rodar antes de considerar uma feature pronta — ver [memory/backend.md](backend.md)).
- **Frontend**: `cd frontend && pnpm install && pnpm dev` (sobe em `:3000`). Aponta pro backend via env `NUXT_BACKEND_URL` (default `http://localhost:8000`, configurado em `runtimeConfig.backendUrl` no `nuxt.config.ts`).
- **E2E**: `cd e2e && pnpm install && pnpm test` — sobe backend e frontend automaticamente via `webServer` no `playwright.config.ts`, não precisa subir nada manualmente antes.

**Sempre que a forma de rodar o projeto mudar** (novo serviço, nova env var obrigatória, novo passo de setup), atualizar o `README.md` da raiz **e** este arquivo juntos — o README é a referência prática, este arquivo é o lembrete de que ela existe e deve ser mantida em dia.
