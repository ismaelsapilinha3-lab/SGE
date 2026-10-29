---
paths:
  - app/Http/Controllers/CoordenadorController.php
  - 'app/Http/Controllers/**'
---

# Controllers

## Coordenador ve estagios da direcao mesmo sem id_coordenador
O coordenador so via estagios cuja candidatura tinha id_coordenador definido, por isso estagios iniciados a partir de candidaturas nao encaminhadas ficavam invisiveis e nao podiam receber supervisor. As queries de estagio (lista e estagioDoCoordenador) filtram por id_departamento_selecionado E (id_coordenador = user OU id_coordenador IS NULL); a lista assume a candidatura orfa via Candidatura::garantirCoordenador(). O EstagioAdminController::iniciarEstagio tambem chama garantirCoordenador() ao criar o estagio ativo.

## Notification deep links use the fixed panel URL scheme
Every notificacoes->para*() call should pass a URL: intern '/area-reservada?separador=geral|relatorios|documentos|plano'; supervisor '/supervisor?estagio={id}&aba=plano|contrato|relatorios|assiduidade|avaliacoes|feedback' or '/supervisor?secao=entrevistas'; coordinator '/coordenador?estagio={id}&aba=plano'; admin '/admin?secao=candidaturas|relatorios'; candidate '/estagiario'. NotificacaoService and Notificacao forward an optional ?string $url.

## Interviews are an advisory step
`Entrevista` is scheduled by an administrator with an active supervisor representative. The result (`pendente|aprovado|reprovado`) is recorded by the administrator and is advisory: it must not change the candidatura state or bypass the coordinator's final decision. While an interview is pending, the coordinator cannot approve or reject the candidatura; the administrator must record the result first. The candidate, coordinator, and supervisor representative can read it through their existing panels; only the administrator can create/reschedule/record it.

## Password recovery uses the Angular portal URL
`POST /api/password/forgot` and `POST /api/password/reset` use Laravel's password broker. `User::sendPasswordResetNotification` builds `{FRONTEND_URL}/redefinir-password?token=...&email=...`, so set `FRONTEND_URL` per environment. A successful reset revokes all Sanctum tokens; keep the forgot response generic to avoid e-mail enumeration.

## Resource authorization lives in policies
Use `Gate::forUser($user)->allows(...)` inside scoped helpers when a denial must stay a 404, and `Gate::authorize(...)` for hard 403 boundaries. Policies live in `app/Policies` (Candidatura, Estagio, Relatorio, Documento, DocumentoEstagio, Notificacao, PlanoEstagio) and encode role plus relationship checks.
