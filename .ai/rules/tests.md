---
paths:
  - 'tests/**'
---

# Tests

## Datas de teste usam subMonthNoOverflow
subMonth() pode transbordar em dias 29-31 e voltar ao mesmo mes, fazendo um periodo supostamente anterior continuar dentro do estagio. Usar subMonthNoOverflow() nos testes que constroem periodos passados a partir de datas de fabrica.
