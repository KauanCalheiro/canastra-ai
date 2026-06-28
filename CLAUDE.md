# CLAUDE.md

Memórias e convenções do projeto ficam em `./.claude/memories/`. Antes de tomar decisões sobre algum tema abaixo, consulte o arquivo correspondente. Sempre que uma convenção nova ou correção do usuário for definida durante o trabalho, escreva-a no arquivo de memória correspondente (ou crie um novo em `./.claude/memories/` e adicione na tabela abaixo) — não deixe a decisão só na conversa.

| Arquivo | Tema | Descrição |
|---|---|---|
| [.claude/memories/git.md](.claude/memories/git.md) | Git | Convenções de mensagem de commit (uma linha, prefixo semântico, sem menção ao Claude) |
| [.claude/memories/canastra.md](.claude/memories/canastra.md) | Regras da Canastra | Regras oficiais do jogo (pontuação, canastras, curingas, obriga, contagem de pontos) usadas como contexto para a IA |
| [.claude/memories/design.md](.claude/memories/design.md) | Design | Design tokens e diretrizes visuais (cores, tipografia, raios) usados no styleguide do frontend |
| [.claude/memories/backend.md](.claude/memories/backend.md) | Backend (Laravel) | Padrão Controller→Action(handle)→Data→Resource para todas as features da API |
| [.claude/memories/frontend.md](.claude/memories/frontend.md) | Frontend (Nuxt) | Detalhes de execução dos testes Playwright; client nunca chama o backend direto — sempre via rota server-side proxy usando `canastraClient()`; ícones sempre via `<Icon name="mdi:...">`, nunca caractere/emoji |
| [.claude/memories/running.md](.claude/memories/running.md) | Como rodar o projeto | Resumo de setup/comandos para backend, frontend e e2e — manter sincronizado com o `README.md` |

Regras de teste (TDD) ficam em `./.claude/rules/`, uma por projeto, escopadas via frontmatter `paths:` (carregam só quando um arquivo do path correspondente entra em contexto):

| Arquivo | Descrição |
|---|---|
| [.claude/rules/backend-tests.md](.claude/rules/backend-tests.md) | Sempre comece pelo teste Pest (RED → GREEN) antes de implementar |
| [.claude/rules/frontend-tests.md](.claude/rules/frontend-tests.md) | Sempre comece pelo teste Playwright em `./e2e` antes de implementar |
