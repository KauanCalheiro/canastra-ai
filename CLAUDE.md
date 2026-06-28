# CLAUDE.md

Memórias e convenções do projeto ficam em `./memory/`. Antes de tomar decisões sobre algum tema abaixo, consulte o arquivo correspondente. Sempre que uma convenção nova ou correção do usuário for definida durante o trabalho, escreva-a no arquivo de memória correspondente (ou crie um novo em `./memory/` e adicione na tabela abaixo) — não deixe a decisão só na conversa.

| Arquivo | Tema | Descrição |
|---|---|---|
| [memory/git.md](memory/git.md) | Git | Convenções de mensagem de commit (uma linha, prefixo semântico, sem menção ao Claude) |
| [memory/canastra.md](memory/canastra.md) | Regras da Canastra | Regras oficiais do jogo (pontuação, canastras, curingas, obriga, contagem de pontos) usadas como contexto para a IA |
| [memory/design.md](memory/design.md) | Design | Design tokens e diretrizes visuais (cores, tipografia, raios) usados no styleguide do frontend |
| [memory/backend.md](memory/backend.md) | Backend (Laravel) | Sempre comece pelo teste (Pest/TDD); padrão Controller→Action(handle)→Data→Resource para todas as features da API |
| [memory/frontend.md](memory/frontend.md) | Frontend (Nuxt) | Testes via Playwright em `./e2e` (fora do frontend); client nunca chama o backend direto — sempre via rota server-side proxy usando `canastraClient()`; ícones sempre via `<Icon name="mdi:...">`, nunca caractere/emoji |
| [memory/running.md](memory/running.md) | Como rodar o projeto | Resumo de setup/comandos para backend, frontend e e2e — manter sincronizado com o `README.md` |
