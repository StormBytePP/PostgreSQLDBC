# PostgreSQLDBC

PostgreSQLDBC is an abstract class designed to simplify handling PostgreSQL database connections. It provides a robust and secure way to use prepared statements, helping developers avoid SQL injection vulnerabilities. With this library, you can easily configure and execute prepared statements by associating them with meaningful names.

## Features

- **Secure Database Connections**: Protects against SQL injection by enforcing the use of prepared statements.
- **Easy Configuration**: Allows you to configure prepared statements with meaningful names for reuse.
- **Singleton Pattern**: Ensures a single instance of the database connection throughout the application.
- **Transaction Support**: Includes methods for starting, committing, and rolling back transactions.
- **Performance Monitoring**: Tracks execution time and statistics for SQL queries.

## Requirements

- PHP with PostgreSQL support (`pg_connect` and related functions).
- A PostgreSQL database.

## Installation

1. Clone or download this repository.
2. Include the `PostgreSQLDatabase.php` file in your project.
3. Extend the `PostgreSQLDatabase` class to create your own database connection class.

## Documentation

See class `PostgreSQLDatabase` documentation

## Usage Example

Below is an example of how to use the library. This example assumes a PostgreSQL database with a `users` table containing the following fields:

- `id SERIAL PRIMARY KEY`
- `username TEXT NOT NULL`
- `email TEXT NOT NULL`

```php
<?php

require_once 'PostgreSQLDatabase.php';

class MyDBConnection extends PostgreSQLDatabase {
    /**
     * Instance of class
     * @var MyDBConnection
     */
    private static $instance = NULL;

    /**
     * Get an instance for DB Connection
     * @return MyDBConnection
     */
    public static function GetInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new MyDBConnection();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    protected function __construct() {
        parent::__construct();
        global $host, $database, $user, $password; // Stored in some other file
        $this->Configure($host, $user, $password, $database);
        parent::Connect();
        $this->ConfigAllSTMTs(); // Load all configured STMTs
    }

    /**
     * Configures all STMTs to be used after
     */
    private function ConfigAllSTMTs() {
        $this->ConfigureSTMT("getUserCount", "SELECT COUNT(*) AS usercount FROM users");
        $this->ConfigureSTMT("createUser", "INSERT INTO users(username,email) VALUES ($1,$2) RETURNING id");
    }

    /**
     * Gets user count
     * @return int
     */
    public function GetUserCount() {
        return $this->ExecuteSTMT("getUserCount")[0]['usercount'];
    }

    /**
     * Creates a user and returns its ID
     * @param string $username Username
     * @param string $email User Email
     * @return int User ID
     */
    public function CreateUser($username, $email) {
        return $this->ExecuteSTMT("createUser", $username, $email)[0]['id'];
    }
}

// Example usage:
$dbconn = MyDBConnection::GetInstance();
echo "Total number of users we have registered is: " . $dbconn->GetUserCount();

?>
```

## License

This project is licensed under the [GNU General Public License v3.0](LICENSE).

## Contributing

Contributions are welcome! Feel free to submit issues or pull requests to improve this library.

## Author

Developed by David Carlos Manuelda. For more information, visit the [GitHub repository](https://github.com/StormBytePP).