# Regra de teste: Frontend (Nuxt)

Path: `./frontend` (código) / `./e2e` (testes)

O frontend não tem testes unitários/Vitest. Todo teste de comportamento do frontend é feito via **Playwright**, numa pasta separada na raiz do repositório: `./e2e` (workspace próprio, com seu `package.json` e `playwright.config.ts`, fora de `./frontend`).

Toda feature nova de comportamento do frontend deve começar pelo teste Playwright em `./e2e` (vermelho) antes da implementação da página/componente — mesma disciplina de TDD do backend (Pest), só que aqui o "teste" é o e2e, já que não há Vitest. Escrever o `test.step` com os `data-testid` esperados primeiro, ver falhar, depois implementar a tela até passar.

Rodar com `pnpm test` dentro de `./e2e`.
