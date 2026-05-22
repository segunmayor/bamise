# Application ports

Bamise application-layer code depends on **`Bamise\Contract\`** interfaces (ports). This directory does not duplicate those types.

Infrastructure and framework adapters implement the contracts under `src/Contract/`. When wiring a DI container, register services against the contract interfaces (e.g. `Bamise\Contract\AuthPortInterface`).

Future modules may add thin aliases here if a project convention requires a `Port` namespace; until then, **Contract = Port**.
