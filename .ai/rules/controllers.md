---
paths:
  - app/Http/Controllers/CoordenadorController.php
---

# Controllers

## Coordenador ve estagios da direcao mesmo sem id_coordenador
O coordenador so via estagios cuja candidatura tinha id_coordenador definido, por isso estagios iniciados a partir de candidaturas nao encaminhadas ficavam invisiveis e nao podiam receber supervisor. As queries de estagio (lista e estagioDoCoordenador) filtram por id_departamento_selecionado E (id_coordenador = user OU id_coordenador IS NULL); a lista assume a candidatura orfa via Candidatura::garantirCoordenador(). O EstagioAdminController::iniciarEstagio tambem chama garantirCoordenador() ao criar o estagio ativo.
