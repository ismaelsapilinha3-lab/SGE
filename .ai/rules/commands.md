---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## Backup em tar.gz sem extensão zip
A extensão zip não está disponível neste ambiente. O app:backup cria um tar com PharData e comprime com zlib para .tar.gz. O destino deve ficar fora de storage/app para não se incluir a si próprio, e a retenção usa o padrão sge-backup-*.tar.gz.
