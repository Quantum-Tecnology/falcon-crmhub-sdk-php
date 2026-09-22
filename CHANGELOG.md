# Changelog

Todas as mudanças relevantes do SDK PHP do Falcon Vendas (CrmHub) são documentadas aqui.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

**Publicar uma versão tem três passos:** atualizar este arquivo, criar a tag no GitHub e conferir que ela chegou ao Packagist. Consumidores travam a versão no `composer.lock`, então a correção só chega a cada serviço depois de um `composer update` lá.

> O histórico anterior à 1.2.0 foi reconstruído a partir do log do Git em 2026-09-22 (o arquivo não existia).

## [1.2.0] - 2026-09-22

### Adicionado

- **`ForbiddenException` (HTTP 403)**, com `getAbility()`. O Falcon Vendas passou a ter permissão por chave de API (`customers:write`, `phones:verify`, `messages:send`, `messages:free-text`, `usage:read`), e a chave sem a permissão toma 403 `MISSING_ABILITY`.

### Corrigido

- **403 voltava como resposta comum.** Caía no `default` do `handleResponse()` e era devolvido como `ApiResponse`, sem exceção: quem integra seguia achando que a mensagem tinha saído.
- **O CI estava vermelho desde a primeira versão.** O `phpunit.xml` declara a suíte `tests/Unit`, a pasta não existia, e o Pest para antes de rodar qualquer teste. Os testes passavam localmente só quando chamados por pasta. Agora há um teste de unidade da facade.

## [1.1.0]

### Adicionado

- `ApiResponse::$code`: o código de negócio da resposta (`TEMPLATE_NOT_FOUND`, `OPTED_OUT`, `QUOTA_EXCEEDED`...), lido da raiz do envelope.

## [1.0.0]

### Adicionado

- Primeira versão: `customers()`, `phones()`, `messages()`, `usage()`, com chave de API (`fvx_...`) ou login do dono da conta, retry em 401 com credenciais e exceções tipadas (`QuotaExceededException`, `OptedOutException`, `ValidationException`...).
