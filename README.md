# PostgreSQLDBC

![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)
![License](https://img.shields.io/badge/License-GPLv3-blue.svg)
![Status](https://img.shields.io/badge/status-alpha-yellow)

PostgreSQLDBC is a lightweight PHP abstract base class that simplifies working with PostgreSQL using prepared statements. The class focuses on secure query execution, statement configuration and reuse, basic transaction support, and lightweight performance monitoring.

**Highlights:**
- Secure prepared statements to reduce SQL injection risk.
- Named statement configuration for reuse across the application.
- Transaction helpers and COPY support for bulk imports.
- Execution statistics and timing for basic performance insights.

**Files:**
- Library implementation: [lib/PostgreSQLDatabase.php](lib/PostgreSQLDatabase.php)
- License: [LICENSE](LICENSE)

## Requirements

- PHP 7.4 or newer
- PostgreSQL PHP extension (pg_* functions)
- PostgreSQL server

## Installation

1. Clone or download the repository.
2. Include the library in your project, for example:

```php
require_once __DIR__ . '/lib/PostgreSQLDatabase.php';
```

3. Extend `PostgreSQLDatabase` to provide application-specific methods and manage configuration.

## Quick Usage

Below is a minimal pattern showing how to extend the class and register prepared statements for reuse.

```php
<?php
require_once 'lib/PostgreSQLDatabase.php';

class MyDB extends PostgreSQLDatabase {
    private static $instance = null;

    public static function GetInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new MyDB();
        }
        return self::$instance;
    }

    protected function __construct() {
        parent::__construct();
        // configure connection: server, user, pass, db
        $this->Configure('127.0.0.1', 'dbuser', 'secret', 'dbname');
        $this->Connect();
        // register named prepared statements
        $this->ConfigureSTMT('getUserCount', 'SELECT COUNT(*) AS usercount FROM users');
        $this->ConfigureSTMT('createUser', 'INSERT INTO users(username,email) VALUES ($1,$2) RETURNING id');
    }

    public function GetUserCount() {
        $res = $this->ExecuteSTMT('getUserCount');
        return $res ? (int)$res[0]['usercount'] : 0;
    }

    public function CreateUser($username, $email) {
        $res = $this->ExecuteSTMT('createUser', $username, $email);
        return $res ? (int)$res[0]['id'] : null;
    }
}

$db = MyDB::GetInstance();
echo 'Users: ' . $db->GetUserCount();

```

## API Notes (high level)

- `Configure($server, $user, $pass, $db)` — Set connection parameters.
- `Connect()` / `Disconnect()` — Manage the database connection.
- `ConfigureSTMT($name, $query)` — Register a named prepared statement.
- `ExecuteSTMT($name, ...$params)` — Execute a named statement and return results or affected rows.
- `StartTransaction()`, `EndTransaction($commit = true)` — Transaction control helpers.
- `EscapeString()`, `EscapeByteA()`, `UnEscapeByteA()` — Utility escaping helpers.
- `GetExecutedSTMTStatistics()` / `GetTimeSpent()` — Basic performance statistics.

For full reference, see the source implementation at [lib/PostgreSQLDatabase.php](lib/PostgreSQLDatabase.php).

## Best Practices & Notes

- Prefer named prepared statements via `ConfigureSTMT()` and execute them with `ExecuteSTMT()`.
- When using array parameters, the class formats them as PostgreSQL arrays (e.g. `{a,b,c}`).
- Check `IsConnected()` before running raw queries if connecting lazily.
- The class uses `trigger_error()` for fatal problems; wrap calls appropriately in applications where you need custom error handling.

## License

This project is published under the GNU General Public License v3.0 — see [LICENSE](LICENSE) for details.

## Contributing

Contributions, bug reports and pull requests are welcome. Please open issues or PRs on the upstream repository.

---
_Developed by David Carlos Manuelda_
