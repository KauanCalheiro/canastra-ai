# Backend (Laravel)

> Regra de teste (TDD/Pest) movida para [.claude/rules/backend-tests.md](../rules/backend-tests.md).

## Padrão de Controller / Action / Data

Use este padrão para todas as features da API:

- **Controller**: só chama a Action correspondente. Não tem lógica de negócio.
- **Action**: classe nomeada com o nome da ação (ex: `CreateGame`), **sem sufixo `Service`**. Fica em `app/Actions/<Domínio>/<Ação>.php` (namespace `App\Actions\<Domínio>`). Não usa `__invoke` — usa um método `handle()`.
  - Usa a trait `Lorisleiva\Actions\Concerns\AsAction` (pacote `lorisleiva/laravel-actions`) para ganhar `make()`/`run()` de graça — não escrever um `make()` próprio em cada Action.
  - **Chamada padrão: `CreateGame::run($data)`** — atalho de `static::make()->handle(...$arguments)`. `make()` não recebe argumentos (é só `app(static::class)`); o Data de entrada é sempre argumento do `handle(CreateGameData $data)`, nunca do construtor/`make()`.
  - Estado intermediário (models criados, e o próprio `$data`) fica em propriedades públicas/protegidas da Action (ex: `protected CreateGameData $data`, `public ?Game $game`, `public array $players = []`), preenchidas por métodos auxiliares dedicados (ex: `createGame()`, `batchCreatePlayer()`, `createPlayer()`).
- **Data (entrada)**: usa `spatie/laravel-data`. Fica em `app/Data/<Domínio>/<Ação>Data.php` (ex: `CreateGameData`). Tipa e valida a entrada da request — é resolvida e validada automaticamente quando type-hintada no método do controller.
- **Data (saída)**: a Action recebe o Data de entrada e **retorna um Data de saída** (ex: `GameData`), não o model Eloquent direto.
- **Resource**: o Data de saída retornado pela Action é **depois** passado para um `JsonResource` no Controller (ex: `GameResource::make($game)`), que monta a resposta final.
- **Wrapping**: `JsonResource::withoutWrapping()` está habilitado globalmente em `AppServiceProvider::boot()` — respostas não usam envelope `data`.
- **JSON forçado**: todas as rotas em `routes/api.php` passam pelo middleware `ForceJsonResponse` (grupo `api`), que força `Accept: application/json` mesmo sem o header do cliente.

Fluxo: `Controller::store(XData $data)` → `$game = Action::run($data)` → `XResource::make($game)`.

Exemplo de referência: `App\Http\Controllers\Api\GameController`, `App\Actions\Game\CreateGame`, `App\Data\Game\CreateGameData`/`GameData`, `App\Http\Resources\GameResource`.

## Exceptions de regra de negócio

Erros de **regra de negócio** (algo que depende do estado do banco/jogo — ex: "não há cópias suficientes dessa carta", "jogador não pertence a essa dupla") usam uma **exception específica por regra**, não `ValidationException::withMessages()` genérico. Isso dá controle global sobre o formato de erro da API.

- Toda exception de regra de negócio estende `App\Exceptions\DomainException` (abstrata), que expõe `status()` (default 422), `errorCode()` (default: snake_case do nome da classe) e `context()` (array, default vazio).
- Fica em `app/Exceptions/<Domínio>/<Nome>Exception.php` quando específica de um domínio (ex: `app/Exceptions/Sequence/SequenceTooShortException.php`), ou direto em `app/Exceptions/<Nome>Exception.php` quando compartilhada entre domínios (ex: `InsufficientCardsInPoolException`, usada tanto por mãos quanto por sequências).
- Um **handler global** em `bootstrap/app.php` (dentro de `withExceptions`) captura qualquer `DomainException` e renderiza `{ error, message, context }` com o `status()` da exception — um único lugar pra manter o formato de erro consistente em toda a API, em vez de cada Action formatar sua própria resposta de erro.
- **`ValidationException` do Laravel continua sendo usado normalmente** para validação de *forma* da request (tipo/tamanho/regex dos campos do `Data` de entrada, via `rules()`) — isso já é automático e consistente. A exception específica é só para violações de regra de negócio que dependem de consultar o banco.

Exemplo de referência: `App\Exceptions\DomainException`, `App\Exceptions\InsufficientCardsInPoolException` (usada por `StorePlayerHand` e pelas Actions de sequência).
