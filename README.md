# Falcon Vendas SDK (PHP)

SDK PHP para a API do **Falcon Vendas** (CrmHub). Permite que o seu sistema:

- **valide celular** por WhatsApp, com código de 6 dígitos;
- **dispare mensagens transacionais** (boas-vindas, assinatura vencida, aviso de processamento);
- **registre clientes novos**, que viram contatos de verdade no CRM;
- **consulte o consumo** do mês.

Sem dependências além de `ext-curl` e `ext-json`.

## Instalação

```bash
composer require quantumtecnology/falcon-crmhub-sdk
```

## Antes de começar: de quem é o WhatsApp

> ⚠️ **As mensagens saem do número do dono da conta do Falcon Vendas.**
>
> Se o seu sistema disparar mensagem que as pessoas não pediram, é o WhatsApp
> **dele** que é bloqueado — e ele perde o próprio negócio, não você.
>
> Na prática:
> - Mande o que o destinatário **espera** receber: ele se cadastrou, comprou, tem
>   uma fatura vencendo, pediu um orçamento.
> - **Nunca** use isto para promoção em massa ou para quem não tem relação com o
>   negócio dele.
> - Quando vier `OptedOutException`, **pare de tentar** para aquele número e
>   registre isso do seu lado. Retry aqui é exatamente o que gera denúncia.

O dono da conta cria a chave de API no painel dele (Integrações → Chaves de API),
aceitando um termo de responsabilidade. Cada chave tem um **nome** — "DataHub",
"FiscalHub", o nome do seu sistema — e esse nome vira a **origem** dos contatos
criados por ela, para ele filtrar na tela de Clientes.

## Uso

```php
use QuantumTecnology\FalconCrmHub\CrmHubClient;
use QuantumTecnology\FalconCrmHub\CrmHubConfig;

$crm = new CrmHubClient(CrmHubConfig::make(
    'https://crm.falcon-server.com.br',
    'fvx_abc12345_...',            // a chave criada no painel
));
```

Ou com e-mail e senha do dono da conta (o token é obtido e renovado sozinho):

```php
$crm = new CrmHubClient(CrmHubConfig::withCredentials(
    'https://crm.falcon-server.com.br',
    'dono@exemplo.com',
    'senha',
));
```

Há também a facade estática, para scripts simples:

```php
use QuantumTecnology\FalconCrmHub\CrmHub;

CrmHub::configure(CrmHubConfig::make('https://crm.falcon-server.com.br', 'fvx_...'));
CrmHub::phones()->challenge('+5519999999999');
```

### Validar um celular

```php
$crm->phones()->challenge('19999999999');
// data: { phone: "+5519999999999", expires_in: 600 }

// ... a pessoa digita o código que recebeu ...

$crm->phones()->confirm('19999999999', '123456');
// data: { verified: true }
```

O número pode vir com máscara, com ou sem DDI — a normalização (incluindo a
pegadinha do 9º dígito) acontece no servidor, e o `challenge` devolve o número
em E.164 para você guardar.

Número que **não tem WhatsApp** é recusado antes do envio, com
`ValidationException` e `code = NOT_ON_WHATSAPP` — e **não consome cota**.

O `confirm` também não consome: cobrar ali seria cobrar por a pessoa ter
digitado o que recebeu. O código vale **uma vez** e expira em 10 minutos.

### Disparar uma mensagem

O texto mora num **modelo** cadastrado pelo dono da conta no painel, com
`{placeholders}`. Você manda a chave e os valores:

```php
$crm->messages()->send(
    phone: '19999999999',
    template: 'assinatura_vencida',
    variables: ['nome' => 'Ana', 'plano' => 'Pro'],
    name: 'Ana Silva',          // usado se o contato ainda não existir
);
// 202 · data: { message_id: "...", status: "queued" }
```

Isso existe para que ele corrija uma vírgula sem depender de deploy seu, e veja
no painel o que sai em nome dele.

`status: "queued"` significa **enfileirada**, não entregue — o envio real
acontece na fila do Falcon Vendas.

Variável faltando é recusada **antes** do envio: placeholder cru numa mensagem
já entregue não tem volta.

Texto livre existe, mas exige permissão na chave **e** no plano:

```php
$crm->messages()->sendText('19999999999', 'Sua nota fiscal está pronta.');
```

### Registrar um cliente novo

```php
$crm->customers()->arrived('Ana Silva', '19999999999');
// data: { created: true, contact_id: "...", phone: "+5519999999999" }
```

**Idempotente por telefone**: reenviar devolve `created: false` sem duplicar nem
redisparar boas-vindas, então pode chamar no retry sem medo. Não consome cota.

### Consultar o consumo

```php
$usage = $crm->usage()->current();

foreach ($usage->data['features'] as $feature) {
    // feature, label, description, enabled, used, limit, unlimited
}
```

> `limit = -1` significa **ilimitado**, não zero. Prefira o campo `unlimited` a
> comparar o número cru — e atenção se o seu sistema também consome o SDK do
> DataHub, que usa outra convenção para a mesma ideia.

Consultar não consome cota.

## Erros

Todas as exceções estendem `FalconException`.

| Exceção | HTTP | Quando | O que fazer |
|---|---|---|---|
| `AuthException` | 401 | Chave inválida, revogada ou expirada | Pedir uma chave nova ao dono da conta |
| `QuotaExceededException` | 402 | Cota do mês, plano sem o recurso, assinatura inativa | Ver `getReason()`, `getUsed()`, `getLimit()` |
| `OptedOutException` | 409 | O destinatário pediu para não receber | **Parar de tentar** e registrar |
| `ValidationException` | 422 | Payload inválido, modelo inexistente, variável faltando, número sem WhatsApp | Corrigir a chamada (`getErrors()`) |
| `RateLimitException` | 429 | Chamadas demais | Respeitar `getRetryAfter()` |
| `NotFoundException` | 404 | Recurso inexistente | Conferir o caminho |
| `ServerException` | 5xx | Falha do serviço | Retry com recuo |

```php
use QuantumTecnology\FalconCrmHub\Exceptions\OptedOutException;
use QuantumTecnology\FalconCrmHub\Exceptions\QuotaExceededException;

try {
    $crm->messages()->send('19999999999', 'assinatura_vencida', ['nome' => 'Ana']);
} catch (OptedOutException) {
    $this->marcarComoDescadastrado('19999999999');   // nunca retry
} catch (QuotaExceededException $e) {
    $this->avisarAdmin("Cota: {$e->getUsed()}/{$e->getLimit()} ({$e->getReason()})");
}
```

## Cache do token

Com e-mail e senha, o token é cacheado em memória por padrão. Para persistir
entre requisições, passe qualquer cache PSR-16:

```php
new CrmHubConfig(
    baseUrl: 'https://crm.falcon-server.com.br',
    email: 'dono@exemplo.com',
    password: 'senha',
    cache: $psr16Cache,
);
```

Em 401 o SDK invalida o cache e repete a chamada **uma vez**, sozinho. Com chave
de API fixa não há o que renovar, e o 401 sobe direto.

## Testes

```bash
composer test
```

## Licença

MIT.
