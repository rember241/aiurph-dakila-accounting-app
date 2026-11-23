# Software Architecture (Minimal DDD)

## Layers

```
[Client]
    ↓
[Http Controllers]        ← Presentation
    ↓
[Application Handlers]    ← Use cases
    ↓
[Domain Rules/Services]   ← Business truth
    ↓
[Repositories/Eloquent]   ← Infrastructure
    ↓
[Database]
```

### Http

Controllers + Requests.

- Controllers are thin and delegate to Application.
- Requests validate input shape only.

### Application

Use-cases (Commands/Queries).

- Commands mutate state and own DB transactions.
- Queries read data and apply filters/pagination.

### Domain

Business truth.

- Rules: invariants (no overlap, 12 months, month boundaries).
- Services: domain operations (generate periods).

### Infrastructure

Persistence helpers.

- Repositories wrap Eloquent operations that will later need locking/constraints.

## Common flow: Create Resource

HTTP -> CreateResourceHandler -> ResourceCreateRules -> ResourceRepository -> return.
