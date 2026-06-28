---
paths:
  - "backend/**"
---

# Regra de teste: Backend (Laravel)

Para toda feature ou mudança de comportamento no backend, **SEMPRE COMECE PELO TESTE** (Pest). Escreva o teste, veja falhar (RED), só depois implemente o mínimo necessário para passar (GREEN). Nunca escrever código de produção antes do teste.

Testes ficam em `backend/tests` (Pest). Rodar com `./vendor/bin/pest` dentro de `./backend`.
