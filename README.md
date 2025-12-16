<div align="center">
    <h1>Tbl::</h1>
    <h3>Ferramenta CLI para Geração e Sincronização de Constantes de Tabela como Helper</h3>
    <p>Gere constantes de classe PHP a partir do seu schema de banco de dados para garantir tipos estáticos e prevenir erros de digitação (typos) em nomes de tabelas e colunas.</p>
<code>Thl::users</code>
<code>Thl::users_id</code>
<code>Thl::users_role_id</code>

---------

</div>

| Status | Licença | Composer |
| :--- | :--- | :--- |
| Versão Estável (v1.0.0) | MIT | `composer require eril/tbl-schema-sync` |

---

## 🌟 Funcionalidades Principais

* **Classe Global `Tbl`:** Gera uma classe `Tbl` sem *namespace* para acesso global simplificado (ex: `Tbl::usuarios_id`).
* **Autoload Simplificado:** Use `TblInitializer::use('...')` para carregar a classe `Tbl` globalmente no *runtime*.
* **Verificação de Schema:** Modo `--check` otimizado para pipelines de CI/CD. Retorna `exit code 1` se o schema mudou, forçando a atualização das constantes.
* **Sincronização de Estado:** Usa um arquivo oculto `.tblschema/.tblsync.ini` para armazenar o *hash* MD5 do schema.

---

## Instalação

Adicione o pacote ao seu projeto via Composer. Ele é uma dependência de **Produção** devido à necessidade do `TblInitializer` no *runtime*.

```bash
composer require eril/tbl-schema-sync
```

---

## Uso e Configuração
A ferramenta `tbl-class-generate` possui dois modos de operação principais.

### Modo 1: Geração de Constantes (Padrão)
Este modo cria ou atualiza o arquivo `Tbl.php` com todas as constantes do seu banco de dados.

| Sintaxe | Exemplo |
| --- | --- |
| `vendor/bin/tbl-class-generate <output_directory> -db <database_name>` | `vendor/bin/tbl-class-generate src/Constants -db app_db` |

#### Passo Final Obrigatório (Autoload)
Para que a classe `Tbl` funcione globalmente, você **DEVE** incluir o *autoload* no *bootstrap* da sua aplicação (ex: `index.php`, `public/index.php`).

```php
// Arquivo: public/index.php (ou seu arquivo de bootstrap)

require __DIR__ . '/../vendor/autoload.php';

use Eril\TblSchemaSync\TblInitializer;

// Registra o diretório onde o Tbl.php foi gerado (relative à raiz do projeto)
TblInitializer::use('src/Constants'); 
// O Tbl.php agora está disponível globalmente.

```

---

### Modo 2: Verificação de Schema para CI/CD (`--check`)
Este modo é ideal para ser executado no início do seu pipeline de Integração Contínua (CI). Ele verifica se o *schema* do banco de dados mudou desde a última geração.

| Sintaxe | Exemplo |
| --- | --- |
| `vendor/bin/tbl-class-generate --check -db <database_name>` | `vendor/bin/tbl-class-generate --check -db app_db` |

#### Comportamento e Códigos de Saída
| Resultado | Código de Saída | Ação no CI |
| --- | --- | --- |
| **Schema Não Mudou** | **`0`** (Sucesso) | O CI continua. |
| **Schema Mudou** | **`1`** (Erro) | O CI **falha**. Força o desenvolvedor a gerar e commitar a alteração. |
| **Falha na Conexão** | **`1`** (Erro) | O CI falha. |

---

## Uso Simplificado com Composer Scripts
Para facilitar o uso diário, adicione estes *scripts* ao seu `composer.json` (substitua `<DATABASE_NAME>` e o diretório conforme necessário):

```json
"scripts": {
    "db:generate": "vendor/bin/tbl-class-generate src/Constants -db <DATABASE_NAME>",
    "db:check": "vendor/bin/tbl-class-generate --check -db <DATABASE_NAME>"
}

```

---

## Exemplo de Uso no Código

```php
<?php

use Tbl; // A classe Tbl é carregada globalmente pelo TblInitializer

// Você obtém autocomplete na sua IDE e segurança contra typos!
$sql = "SELECT " . Tbl::usuarios_nome . ", " . Tbl::usuarios_email . 
       " FROM " . Tbl::usuarios . 
       " WHERE " . Tbl::usuarios_id . " = :id";
```

---

## Configurações de Banco de Dados
A ferramenta lê as credenciais de conexão do seu banco de dados através de variáveis de ambiente (ENV).

| Variável | Padrão | Descrição |
| --- | --- | --- |
| `DB_HOST` | `localhost` | Host do banco de dados. |
| `DB_NAME` | **(Obrigatório)** | Nome do banco de dados (também pode ser passado via `-db`). |
| `DB_USER` | `root` | Usuário de conexão. |
| `DB_PASS` | (vazio) | Senha de conexão. |

---

## Arquivos Gerados (Ignorar no Git)
Adicione estes arquivos ao seu `.gitignore`:

``` git
# Gerados pelo eril/tbl-schema-sync
.tblschema/
<output_directory>/Tbl.php

```

---

##📜 LicençaEste projeto é licenciado sob a licença MIT.