---
paths:
  - 'app/**'
---

# App

## Datas em SQLite exigem whereDate nas igualdades
Nas colunas de data (mes_referencia, data, periodo_referencia), usar whereDate nas comparacoes de igualdade. O SQLite dos testes guarda 'Y-m-d H:i:s' e um where direto nao encontra o registo, provocando violacoes de unicidade. Em consultas de intervalo, where('<', $data) e seguro e permite usar indices no MySQL.
