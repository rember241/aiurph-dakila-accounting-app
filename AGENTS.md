## Graphify

Use the local knowledge graph at `graphify-out/graph.json` to navigate the Laravel backend and Next.js frontend.

- For codebase questions, start with `graphify query "<question>"` when the graph exists. Use `graphify explain "<symbol>"` for a symbol or `graphify path "<A>" "<B>"` to trace a relationship. Verify details against the source.
- Build the local index with `graphify extract . --code-only` and refresh it after code changes with `graphify update .`.
