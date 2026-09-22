# Falcon Vendas SDK (PHP)

SDK PHP da API de integração do **Falcon Vendas** (CrmHub). É o lado consumidor
do produto que o CrmHub passou a oferecer: validação de celular por WhatsApp,
disparo de mensagem transacional, registro de cliente e consulta de consumo.

Espelha o `falcon-datahub-sdk-php` peça por peça — **não** o `Pagarme-SDK`, que
é repository-pattern acoplado ao Laravel. Zero dependências além de `ext-curl` e
`ext-json`.

## Decisões

### O nome da chave é a origem do contato, e não vem do payload

O `arrived()` não aceita `source_system`. A origem é o **nome da chave de API**,
que o dono da conta define no painel. Se o consumidor pudesse escolher a própria
identidade, `"DataHub"` e `"datahub"` coexistiriam e o filtro por origem na tela
de Clientes ficaria furado.

### Modelo de mensagem em vez de texto livre por padrão

`send()` usa uma chave de modelo cadastrado; `sendText()` exige permissão na
chave **e** no plano. A mensagem sai do WhatsApp do dono da conta, e é o número
**dele** que é banido se virar spam — com modelo ele vê no painel o que sai em
nome dele e corrige o texto sem depender de deploy do integrado.

### Exceptions próprias para o que o molde não tinha

- `QuotaExceededException` (402) carrega `used`/`limit`/`reason` acessíveis: o
  integrado precisa distinguir "espere o mês virar" de "faça upgrade" sem fazer
  parsing de string.
- `OptedOutException` (409) existe para o opt-out **não** ser tratado como falha
  transitória. Retry em quem pediu para sair é o caminho mais curto para o
  número do dono da conta ser banido.

## Pegadinhas

### O caminho do login NÃO é o mesmo do DataHub

DataHub: `/auth/v1/login`. Falcon Vendas: **`/user/public/auth/v1/login`**.
Copiar o do outro SDK dá 404 travestido de falha de autenticação —
`TokenManager::authenticate()` lê a mensagem do corpo e reporta como
`AuthException`, então o sintoma não aponta a causa.

### A chave de cache do token precisa ser diferente

`falcon_crmhub_token`, nunca `falcon_datahub_token`. Os dois SDKs convivem no
mesmo processo (o CrmHub consome o DataHub, o DataHub consome o CrmHub): com a
mesma chave, um token sobrescreve o outro e cada chamada leva a credencial
errada, produzindo 401 intermitente sem nada no log explicando.

### `limit = -1` é ilimitado, e a convenção difere entre as casas

O Falcon Vendas usa `-1` para ilimitado; o DataHub usa coluna unsigned + flag.
Quem consome os dois SDKs precisa converter num lugar só. O campo `unlimited`
existe no payload de consumo justamente para não depender de quem lê lembrar
disso.

### `status: "queued"` não é entrega

O disparo responde 202 assim que **enfileira**. O envio real passa pela fila do
Falcon Vendas, com jitter anti-rajada. Prometer entrega aqui seria mentir para
quem integra.

## Dois erros do SDK do DataHub que este não repete

1. **Resources tipando `?int` onde o id é hashid string** — no DataHub isso
   obrigou o `DataHubGeoService` a chamar o `get()` protegido via reflection.
2. **`ApiResponse` ignorando o bloco `pagination`** irmão de `data` — até a
   1.6.1, milhares de registros viravam 15 **sem erro nenhum**. O
   `ApiResponse` daqui já nasceu com a correção.

## Relacionado

O lado provedor vive em `Falcon-CrmHub-service`, sob `routes/integration.php` e
`app/Http/Controllers/Integration/V1/`. O gate de cota é o
`app/Services/Billing/FeatureGateService.php`, e a medição o
`app/Models/FeatureUsage.php`.
