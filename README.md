<div align="center">
    <h3>Ferramenta CLI para Geração e Sincronização de Constantes de Tabela MySQL</h3>
    <p>Gere constantes de classe PHP a partir do seu schema de banco de dados para garantir tipos estáticos e prevenir erros de digitação (typos) em nomes de tabelas e colunas.</p>
</div>

| Status | Licença | Composer |
| :--- | :--- | :--- |
| Versão Estável (v1.0.0) | MIT | `composer require eril/tbl-schema-sync --dev` |

---

## 🌟 Funcionalidades Principais

* **Classe Global `Tbl`:** Gera uma classe `Tbl` sem *namespace* para acesso global simplificado (ex: `Tbl::usuarios_id`).
* **Verificação de Schema:** Modo `--check` otimizado para pipelines de CI/CD. Retorna `exit code 1` se o schema do banco de dados mudou, forçando a atualização das constantes.
* **Sincronização de Estado:** Usa um arquivo oculto `.tblschema/.tblsync.ini` para armazenar o *hash* MD5 do schema e o *filepath* do arquivo gerado.
* **Zero Dependência em Runtime:** A ferramenta é uma dependência de desenvolvimento (`--dev`).

---

## 🛠️ Instalação

Adicione o pacote ao seu projeto via Composer como uma dependência de desenvolvimento:

```bash
composer require eril/tbl-schema-sync --dev
```

---

## 🚀 Uso e ConfiguraçãoO `tbl-class-generate` possui dois modos de operação principais.

### Modo 1: Geração de Constantes (Padrão)

Este modo cria ou atualiza o arquivo `Tbl.php` com todas as constantes do seu banco de dados.

#### Sintaxe
``` bash
vendor/bin/tbl-class-generate <output_directory> -db <database_name>

```

#### Exemplo
Assumindo que você tem um banco de dados chamado `app_db`.

```bash
vendor/bin/tbl-class-generate src/Constants/ -db app_db
```

#### ⚠️ Passo Final Obrigatório (Autoload)
Para que a classe `Tbl` (gerada sem *namespace*) funcione globalmente, você **DEVE** registrar o arquivo gerado no `composer.json` do seu projeto e rodar o *autoload* do Composer.

1. **Edite `composer.json`** na raiz do seu projeto (assumindo o exemplo `src/Constants/Tbl.php`):
```json
{
    "autoload": {
        "files": [
            "src/Constants/Tbl.php"
        ]
    }
}

```

2. **Execute o Autoload:**
```bash
composer dump-autoload
```


---

### Modo 2: Verificação de Schema para CI/CD (`--check`)
Este modo é ideal para ser executado no início do seu pipeline de Integração Contínua (CI). Ele verifica se o *schema* do banco de dados mudou desde a última geração, **sem reescrever o arquivo `Tbl.php`**.

#### Sintaxe
```bash
vendor/bin/tbl-class-generate --check -db <database_name>
```

#### Comportamento e Códigos de Saída
| Resultado | Código de Saída | Ação no CI |
| --- | --- | --- |
| **Schema Não Mudou** | **`0`** (Sucesso) | O CI continua. As constantes estão atualizadas. |
| **Schema Mudou** | **`1`** (Erro) | O CI **falha**. O desenvolvedor deve rodar o comando de geração (Modo 1) e commitar a alteração. |
| **Falha na Conexão** | **`1`** (Erro) | O CI falha. |

---

## 💻 Exemplo de Uso no Código
Após a configuração do *autoload* (Modo 1, Passo Final), você pode usar as constantes de forma segura:

```php
<?php

use Tbl;

// Exemplo de uma query MySQL utilizando as constantes:
$sql = "SELECT " . Tbl::usuarios_nome . ", " . Tbl::usuarios_email . 
       " FROM " . Tbl::usuarios . 
       " WHERE " . Tbl::usuarios_id . " = :id";

// Você obtém autocomplete na sua IDE e segurança contra typos!

```

---

##⚙️ Configurações de Banco de DadosA ferramenta lê as credenciais de conexão do seu banco de dados através de variáveis de ambiente (ENV):

| Variável | Padrão | Descrição |
| --- | --- | --- |
| `DB_HOST` | `localhost` | Host do banco de dados. |
| `DB_NAME` | **(Obrigatório)** | Nome do banco de dados (também pode ser passado via `-db`). |
| `DB_USER` | `root` | Usuário de conexão. |
| `DB_PASS` | (vazio) | Senha de conexão. |

---

##📂 Arquivos Gerados (Ignorar no Git)A ferramenta cria um diretório oculto na raiz do seu projeto. É **altamente recomendável** que você adicione estes arquivos ao seu `.gitignore`:

```
# Gerados pelo eril/tbl-schema-sync
.tblschema/
<output_directory>/Tbl.php

```

---

##🤝 ContribuiçõesContribuições são bem-vindas! Sinta-se à vontade para abrir *issues* ou *pull requests* no repositório.

---

##📜 LicençaEste projeto é licenciado sob a licença MIT.

```
