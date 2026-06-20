# Backend (Laravel)

## Sempre comece pelo teste

Para toda feature ou mudança de comportamento no backend, **SEMPRE COMECE PELO TESTE** (Pest). Escreva o teste, veja falhar (RED), só depois implemente o mínimo necessário para passar (GREEN). Nunca escrever código de produção antes do teste.

## Padrão de Controller / Action / Data

Use este padrão para todas as features da API:

- **Controller**: só chama a Action correspondente. Não tem lógica de negócio.
- **Action**: classe nomeada com o nome da ação (ex: `CreateGame`), **sem sufixo `Service`**. Fica em `app/Actions/<Domínio>/<Ação>.php` (namespace `App\Actions\<Domínio>`). Não usa `__invoke` — usa um método `handle()`.
  - Construtor recebe o Data de entrada (`protected CreateGameData $data`) e expõe um construtor estático fluente `make($data)`.
  - Estado intermediário (models criados) fica em propriedades públicas da Action (ex: `public ?Game $game`, `public array $players = []`), preenchidas por métodos auxiliares dedicados (ex: `createGame()`, `batchCreatePlayer()`, `createPlayer()`).
  - Uso: `CreateGame::make($data)->handle()`.
- **Data (entrada)**: usa `spatie/laravel-data`. Fica em `app/Data/<Domínio>/<Ação>Data.php` (ex: `CreateGameData`). Tipa e valida a entrada da request — é resolvida e validada automaticamente quando type-hintada no método do controller.
- **Data (saída)**: a Action recebe o Data de entrada e **retorna um Data de saída** (ex: `GameData`), não o model Eloquent direto.
- **Resource**: o Data de saída retornado pela Action é **depois** passado para um `JsonResource` no Controller (ex: `GameResource::make($game)`), que monta a resposta final.
- **Wrapping**: `JsonResource::withoutWrapping()` está habilitado globalmente em `AppServiceProvider::boot()` — respostas não usam envelope `data`.
- **JSON forçado**: todas as rotas em `routes/api.php` passam pelo middleware `ForceJsonResponse` (grupo `api`), que força `Accept: application/json` mesmo sem o header do cliente.

Fluxo: `Controller::store(XData $data)` → `$game = Action::make($data)->handle()` → `XResource::make($game)`.

Exemplo de referência: `App\Http\Controllers\Api\GameController`, `App\Actions\Game\CreateGame`, `App\Data\Game\CreateGameData`/`GameData`, `App\Http\Resources\GameResource`.
