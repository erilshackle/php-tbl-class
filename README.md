<div align="center">
    <h1>Tbl::class v3</h1>
    <h3>CLI Tool to Generate Database Table Constants</h3>
    <p>Generate PHP class constants from your database schema for type safety and to prevent typos in table/column names.</p>

<p>Accessible globally for safety and productivity:</p>
<div>
  <code title="table"> Tbl::table</code> 
  <code title="column">Tbl::table_column</code>
  <code style="font-size:small;opacity:0.4;" title="comming soon">Tbl::fk_table1_table2</code>
</div>

---
</div>

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4?style=for-the-badge&logo=php&logoColor=white) ![Version](https://img.shields.io/badge/Version-3.0.0-blue?style=for-the-badge)  ![Downloads](https://img.shields.io/packagist/dt/eril/tbl-class?style=for-the-badge&color=orange) ![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)


| Status | License | Installation |
| :--- | :--- | :--- |
| Stable v3.0.0 | MIT | `composer require eril/tbl-class --dev` |

---

## 🚀 Quick Start

```bash
# Install
composer require eril/tbl-class --dev

# Generate constants
vendor/bintbl-class

# Setup tblclass.yaml 

# That's it! Constants ready to use:
echo Tbl::users;        // 'users'
echo Tbl::users_email;  // 'email'
```

---

## ✨ Features



* **Zero Runtime Dependencies** - Pure development tool, no production overhead
* **Multi-Database Support** - MySQL & SQLite out of the box
* **CI/CD Ready** -  `--check` mode for pipeline integration
* **Simple Configuration** - Clean YAML config with sensible defaults
* **Smart Connection** - Auto-detects .env, environment vars, or custom callbacks
* **Namespace Support** - Generate namespaced or global classes
* **Change Tracking** - Logs schema changes for audit trail

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
  # Optional custom connection:
  # connection: 'App\Database::getConnection'

  driver: mysql           # mysql or sqlite
  
  # For MySQL:
  host: DB_HOST          # or 'localhost'
  port: DB_PORT          # or 3306
  name: DB_NAME          # required
  user: DB_USER          # or 'root'
  password: DB_PASS      # or ''
  
  # For SQLite:
  # driver: sqlite
  # path: database.sqlite   # or DB_PATH env var

# Output configuration  
output:
  path: "./"              # Where to save Tbl.php
  namespace: ""           # PHP namespace (optional)
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
         " WHERE " . Tbl::users_email . " = ?";

// Get autocomplete in your IDE
echo Tbl::products;       // 'products'
echo Tbl::products_price; // 'price'
echo Tbl::orders;         // 'orders'
```

### Autoload Configuration:
After generation, add to `composer.json`:
```json
{
  "autoload": {
    "files": ["src/Models/Tbl.php"]
  }
}
```
Then run: `composer dump-autoload`

---

## 📊 Generated Output Example

```php
<?php
namespace App\Models;

/**
 * Database table constants
 * - Schema: my_database
 * - Date: 2024-01-15 10:30:15
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

### Getting Help:
```bash
# Show all options
tbl-class --help
tbl-class-logs --help
```

---

## 📁 Project Structure

```
tbl-class/
├── bin/
│   ├── tbl-class          # Main generator
│   └── tbl-class-logs     # Log viewer
├── src/
│   ├── Config.php         # YAML configuration
│   ├── ConnectionResolver.php # Database connections
│   ├── Generator.php      # Constants generator
│   └── Logger.php         # Logging system
└── .tblclass/             # State and logs directory
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
