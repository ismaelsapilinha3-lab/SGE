---
paths:
  - 'portal-angular/src/app/components/**'
---

# Components

## Notification destinations are query-param driven and cleared on manual navigation
Panels read estagio/aba/secao/separador from ActivatedRoute.queryParamMap, validate against known tabs/sections, and pass them to child components via @Input signals applied in ngOnChanges after lists load. Manual navigation must clear the params (replaceUrl) and reset the child's destinoAplicado guard so the same notification link works again. Invalid aba falls back to plano. The supervisor panel also accepts `secao=entrevistas`.
