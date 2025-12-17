<div align="center">
    <h1>Constantes Estáticas Tbl::</h1>
    <h3>Ferramenta CLI para Geração e Sincronização de Constantes de Tabela como Helper</h3>
    <p>Gere constantes de classe PHP a partir do seu schema de banco de dados para garantir tipos estáticos e prevenir erros de digitação (*typos*) em nomes de tabelas e colunas.</p>

<p>Acessível globalmente para segurança e produtividade:</p>
<pre><code>Tbl::users</code></pre>
<pre><code>Tbl::users_id</code></pre>
<pre><code>Tbl::users_role_id</code></pre>

---
</div>

| Status | Licença | Instalação (Tooling) |
| :--- | :--- | :--- |
| Versão Estável (v2.0.0) | MIT | `composer require eril/tbl-schema-sync --dev` |

---

## 🌟 Funcionalidades Principais

* **Ferramenta Exclusiva de Desenvolvimento:** O pacote é uma dependência `--dev` e não introduz dependências de *runtime* (como a classe `TblInitializer`) no código de produção.
* **Classe `Tbl`:** Gera a classe `Tbl` (por padrão, sem *namespace*) para ser carregada no escopo global através do *autoload* manual do Composer.
* **Verificação de Schema para CI/CD:** O modo `--check` otimiza *pipelines* de Integração Contínua (CI). Retorna `exit code 1` se o *schema* mudou, forçando a regeneração e o *commit* das constantes.
* **Sincronização de Estado:** Usa um arquivo oculto `.tblschema/.tblsync.ini` na raiz do projeto para armazenar o *hash* MD5 do *schema* atual.

---

## 🛠️ Instalação

Adicione o pacote como uma **dependência de desenvolvimento**.

```bash
composer require eril/tbl-schema-sync --dev
```

---

##  Uso e Configuração
A ferramenta `vendor/bin/tbl-class-generate` possui dois modos de operação principais.

### Modo 1: Geração de Constantes (Padrão)
Este modo cria ou atualiza o arquivo `Tbl.php` com todas as constantes. Se o diretório for omitido, o arquivo é salvo na raiz do projeto (`./Tbl.php`).

| Sintaxe | Exemplo | Saída |
| --- | --- | --- |
| `tbl-class-generate [<dir>] -db <name>` | `tbl-class-generate src/Constants -db app_db` | `src/Constants/Tbl.php` |
| `tbl-class-generate -db app_db` | `tbl-class-generate -db app_db` | `./Tbl.php` |

#### ⚠️ Passo Final Obrigatório (Autoload Manual)
Como este pacote não injeta código de *runtime*, você **DEVE** configurar o carregamento da classe `Tbl` manualmente via `composer.json` (`autoload.files`).

No terminal de saída, a ferramenta mostrará exatamente o caminho relativo a ser adicionado.

```
// Adicione o caminho do arquivo gerado ao seu composer.json

{
    "autoload": {
        "files": [
            "src/Constants/Tbl.php" // Substitua pelo seu caminho real
        ]
    }
}

```

Após editar, execute: `composer dump-autoload` para que a classe `Tbl` seja carregada globalmente.

---

### Modo 2: Verificação de Schema para CI/CD (`--check`)
Este modo verifica se o *schema* do banco de dados mudou. É ideal para *scripts* de *pre-commit* ou *pipelines* de CI.

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

## Exemplo de Uso no Código
Uma vez configurado o *autoload* manual, você pode acessar as constantes em qualquer lugar da sua aplicação:

```php
<?php

// Não é necessário "use Tbl;" se a classe foi carregada no escopo global via autoload.files.

// Você obtém autocomplete na sua IDE e segurança contra typos!
$sql = "SELECT " . Tbl::usuarios_nome . ", " . Tbl::usuarios_email . 
       " FROM " . Tbl::usuarios . 
       " WHERE " . Tbl::usuarios_id . " = :id";

```

---

## Uso Simplificado com Composer Scripts
Para facilitar o uso diário, adicione *scripts* ao seu `composer.json`. **Lembre-se de substituir `my_database_name` e o caminho de saída.**

```json
"scripts": {
    "db:generate": "vendor/bin/tbl-class-generate src/Constants -db my_database_name",
    "db:check": "vendor/bin/tbl-class-generate --check -db my_database_name",
    "db:sync": [
        "@db:check",
        "@db:generate"
    ]
}

```

---

## Configurações de Banco de Dados
A ferramenta lê as credenciais de conexão do seu banco de dados através de variáveis de ambiente (ENV), ou você deve passá-las diretamente via `-db <nome>`.

| Variável | Padrão | Descrição |
| --- | --- | --- |
| `DB_HOST` | `localhost` | Host do banco de dados. |
| `DB_NAME` | **(Obrigatório)** | Nome do banco de dados (também pode ser passado via `-db`). |
| `DB_USER` | `root` | Usuário de conexão. |
| `DB_PASS` | (vazio) | Senha de conexão. |

---

## Arquivos Gerados (Ignorar no Git)
É **essencial** que você adicione estes arquivos ao seu `.gitignore` para evitar conflitos de *merge* e *commitar* binários desnecessários:

```gitignore
# Gerados pelo eril/tbl-schema-sync
.tblschema/
<output_directory>/Tbl.php

```

---

##📜 LicençaEste projeto é licenciado sob a licença MIT.
