<div align="center">
    <h1>Tbl::class v3</h1>
    <h3>CLI Tool to Generate Database Table Constants</h3>
    <p>Generate PHP class constants from your database schema for type safety and to prevent typos in table/column names.</p>

<p>Accessible globally for safety and productivity:</p>
<div>
  <code title="table"> Tbl::table</code> 
  <code title="column">Tbl::table_column</code>
  <code title="foreign key">Tbl::fk_table1_table2</code>
</div>

---
</div>

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4?style=for-the-badge&logo=php&logoColor=white)  ![Version](https://img.shields.io/badge/Version-3.1.0-blue?style=for-the-badge)  ![Downloads](https://img.shields.io/packagist/dt/eril/tbl-class?style=for-the-badge&color=orange) ![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)


| version | install comand|
| :------ |:------ |
| stable |`composer require eril/tbl-class --dev` |
| latest |`composer require eril/tbl-class:dev-main --dev` |

---

## 🚀 Quick Start

```bash
# Install
composer require eril/tbl-class --dev

# Generate constants
vendor/bin/tbl-class

# Setup tblclass.yaml 

# That's it! Constants ready to use:
echo Tbl::users;          // 'users'
echo Tbl::users_email;    // 'email'
echo Tbl::fk_posts_users; // 'user_id'
```

---

## ✨ Features


* **Zero Runtime Dependencies** - Pure development tool, no production overhead
* **Multi-Database Support** - MySQL & SQLite out of the box
* **CI/CD Ready** - `--check` mode with consistent hashing
* **Simple Configuration** - Clean YAML config with sensible defaults
* **Smart Connection** - Auto-detects .env, environment vars, or custom callbacks
* **Namespace Support** - Generate namespaced or global classes
* **Change Tracking** - Logs schema changes for audit trail
* **Schema Hash Verification** - Detect real schema changes only
* **Global Constants Mode** - Generate global `const` without class
* **Foreign Key Support** - Auto-included foreign key constants
* **Smart Naming Strategies** - `full`, `abbr`, `smart` modes for table/column/FK names
* **Multi-language Dictionary** - Built-in English/Portuguese abbreviation dictionaries
* **Custom Dictionaries** - Add your own abbreviation rules

---

## 📦 Installation

```bash
# Local project
composer require eril/tbl-class --dev

# Global installation
composer global require eril/tbl-class
```

---

## 🛠️ Usage

### Basic Usage
```bash
# Generate constants (creates config if missing)
tbl-class


# With custom output directory
tbl-class src/Models/

# With namespace
tbl-class --namespace="App\Models"

# Global mode - Generate global constants (tbl_constants.php)
tbl-class --global

# Check for schema changes (CI/CD)
tbl-class --check
```

### View/Manage Configuration
_setup your tblclass.yaml_
```bash
# Show config file
tbl-class --config
```

### View Logs
```bash
# Show generation logs
tbl-class-logs

# Clear logs
tbl-class-logs --clear
```

---

## ⚙️ Configuration


On first run, a `tblclass.yaml` file is created:

```yaml
# Database configuration
database:
  # Optional custom PDO connection:
  # connection: 'App\Database::getConnection'

  driver: mysql           # mysql or sqlite
  
  # For MySQL (recommended: use environment variables):
  host: env(DB_HOST)      # or 'localhost'
  port: env(DB_PORT)      # or 3306
  name: env(DB_NAME)      # required
  user: env(DB_USER)      # or 'root'
  password: env(DB_PASS)  # or ''
  
  # For SQLite:
  # driver: sqlite
  # path: env(DB_PATH)      # or 'database.sqlite'

# Output configuration 
output:
  path: "./"              # Where to save Tbl.php
  namespace: ""           # PHP namespace (optional)
  
  # NEW: Naming strategies for constants (full, abbr, smart)
  naming:
    table: "full"         # full, abbr, smart
    column: "full"        # full, abbr, smart  
    foreign_key: "smart"  # full, abbr, smart
    
    # Abbreviation settings
    abbreviation:
      dictionary_path: null   # custom dictionary path (relative to project)
      dictionary_lang: "en"   # 'en', 'pt', or 'all'
      max_length: 20          # max abbreviation length
```
> You can copy it and put on your project root, filename must be **"tblclass.yaml"**

### Connection Methods (choose one):

1. **Environment Variables** (recommended):
   ```bash
   # .env file
   DB_NAME=my_database
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   ```

2. **Direct Values in YAML** (not recomended, but &#x1f644; ):
   ```yaml
   database:
     name: my_database
     host: localhost
     user: root
     password: ""
   ```

3. **Custom PDO Callback**:
   ```yaml
   database:
     connection: 'App\Database::getConnection'
   ```

---

## 🔄 CI/CD Integration

### GitHub Actions Example:
```yaml
name: Check Database Schema

on: [push, pull_request]

jobs:
  check-schema:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
      
      - name: Check schema changes
        env:
          DB_NAME: ${{ secrets.DB_NAME }}
          DB_USER: ${{ secrets.DB_USER }}
          DB_PASS: ${{ secrets.DB_PASS }}
        run: vendor/bin/tbl-class --check
        # Exit code 0 = unchanged, 1 = changed
```

### Composer Scripts:
```json
{
  "scripts": {
    "tbl:class": "tbl-class",
    "tbl:sync": ["@tbl:class", "@tbl:class --check"],
    "tbl:logs": "tbl-class-logs",
    "tbl-class-generate": "@tbl:class .",
    }
}
```

---

## 💡 Usage in Code

```php
<?php
// Once generated and autoload configured:

// Use constants anywhere in your app
$query = "SELECT * FROM " . Tbl::users . 
         " WHERE " . Tbl::users_email . " = ?" .
         " AND " . Tbl::fk_posts_users . " = ?";

// Get autocomplete in your IDE
echo Tbl::products;          // 'products'
echo Tbl::products_price;    // 'price'
echo Tbl::fk_orders_users;   // 'user_id'

// Global mode (tbl_constants.php)
echo tbl_users;              // 'users'
echo tbl_fk_posts_users;     // 'user_id'

// With 'abbr' strategy (using built-in dictionary):
echo Tbl::usr;           // 'usuarios' (abbreviated)
echo Tbl::usr_email;     // 'email'
echo Tbl::fk_usr_prof;   // 'profissional_id'

// With 'smart' strategy (abbreviates only long names):
echo Tbl::users;         // 'users' (short, keeps full)
echo Tbl::configuracoes; // 'cfg' (long, abbreviates)

// With 'full' strategy (original behavior):
echo Tbl::usuarios;      // 'usuarios'
echo Tbl::usuarios_id;   // 'id'
```

### Autoload Configuration:
After generation, add to `composer.json`:
```json
{
  "autoload": {
    "files": ["path/To/Tbl.php"]
  }
}
```
Then run: `composer dump-autoload`

---

## 📚 Dictionary System

Tbl-class includes *built-in* abbreviation dictionaries:
```php
// example
return [
    'user' => 'usr',
    'customer' => 'cust',
    'product' => 'prod',
    // ...
];
```

### Custom Dictionary
Create **data/my_dict.php** in your project:

```php
return [
    'minha_tabela' => 'mytbl',
    'outra_tabela' => 'otbl',
];
```
| <small>Then reference in config:</small>
```yaml
abbreviation:
  dictionary_path: "data/my_dict.php"
```
_filepath can be anywhere you like in your project._


---

## 📊 Generated Output Example

```php
<?php
namespace App\Models;

/**
 * Database constants for schema 'example'
 * 
 * @generated 2025-12-15 17:24:59
 * @tool      tbl-class 
 * 
 * @warning AUTO-GENERATED - DO NOT EDIT MANUALLY
 */
class Tbl
{
    // Table: users
    public const users = 'users';
    public const users_id = 'id';
    public const users_email = 'email';
    public const users_name = 'name';
    
    // Table: products
    public const products = 'products';
    public const products_id = 'id';
    public const products_price = 'price';

    // --- Foreign Keys ---

    /** users.id → posts.user_id */
    public const fk_posts_users = 'user_id';
}
```

---

## 🐛 Troubleshooting

### Common Issues:

1. **"Autoload not found"**
   ```bash
   # Run composer install
   composer install
   ```

2. **"Database name not configured"**
   ```bash
   # Edit config file
   tbl-class --config
   # Set database.name in tblclass.yaml
   ```

3. **Connection fails**
   ```bash
   # Check your .env or config file
   # Ensure database server is running
   ```
4. **"Schema changed!" on every check**
   ```bash
   # v3.1.0+ has consistent hashing
   # Run once to initialize state:
   tbl-class
   # Then check will work correctly
   ```
* ### Getting Help
    ```bash
    # Show all options
    tbl-class --help
    tbl-class-logs --help
    ```
---

## 🤝 Contributing
![Contributions Welcome](https://img.shields.io/badge/Contributions-Welcome-brightgreen?style=for-the-badge)

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

---

## 📄 License
![MIT License](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge)

MIT License - see [LICENSE](LICENSE) file for details.

---

## 🎯 Why tbl-class?

* **Type Safety** - Eliminate string typos in SQL queries
* **IDE Autocomplete** - Get instant table/column name suggestions
* **Refactoring Friendly** - Easy to find and update table references
* **Schema Documentation** - Generated class serves as schema reference
* **CI/CD Integration** - Automatic schema change detection

---

<div align="center">

![Star](https://img.shields.io/github/stars/erilshackle/php-tbl-class?style=social) ![Fork](https://img.shields.io/github/forks/erilshackle/php-tbl-class?style=social) ![Watch](https://img.shields.io/github/watchers/erilshackle/php-tbl-class?style=social)

<strong>Stop writing strings, start using constants! Tbl:: 🚀 </strong>
</div>
