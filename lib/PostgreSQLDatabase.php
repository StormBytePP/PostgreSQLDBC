<?php

/**
 * Abstract base class for managing PostgreSQL database connections.
 * 
 * This class provides a secure and efficient way to handle PostgreSQL database operations
 * using prepared statements. It includes features such as transaction management, 
 * prepared statement configuration, and performance monitoring.
 * 
 * @author 
 *   David Carlos Manuelda <david@anuubis.com>
 * @package 
 *   StormBytePP
 * @version 
 *   1.0.0
 * @link 
 *   https://github.com/StormBytePP GitHub URL for PostgreSQL project
 */
abstract class PostgreSQLDatabase {

    /**
     * Minimum value for SMALLINT.
     */
    const SMALLINT_MIN = "-32768";

    /**
     * Maximum value for SMALLINT.
     */
    const SMALLINT_MAX = "32767";

    /**
     * Minimum value for INT.
     */
    const INT_MIN = "-2147483648";

    /**
     * Maximum value for INT.
     */
    const INT_MAX = "2147483647";

    /**
     * Minimum value for BIGINT.
     */
    const BIGINT_MIN = "-9223372036854775808";

    /**
     * Maximum value for BIGINT.
     */
    const BIGINT_MAX = "9223372036854775807";

    /**
     * Database connection resource.
     * 
     * @var resource|null
     */
    private $resource;

    /**
     * Array to store prepared statements and their metadata.
     * 
     * Format: ['name'] => [stmt, lastresult, hasresults]
     * 
     * @var array
     */
    private $stmtarray;

    /**
     * Counter for the number of prepared statements.
     * 
     * @var int
     */
    private $stmtcount;

    /**
     * Configuration for prepared statements.
     * 
     * Format: ['name'] => 'SQL query'
     * 
     * @var array
     */
    private $stmtconfig;

    /**
     * Connection data for the database.
     * 
     * Format: ['server', 'user', 'pass', 'db']
     * 
     * @var array
     */
    private $connection_data;

    /**
     * Array to track the number of times each statement is executed.
     * 
     * @var array
     */
    private static $STMTCalled = array();

    /**
     * Total number of SQL executions.
     * 
     * @var int
     */
    private $executionCount;

    /**
     * Statistics for executed statements.
     * 
     * Format: ['name'] => ['executionTimeMin', 'executionTimeMax', 'calls']
     * 
     * @var array
     */
    private $executedSTMTs;

    /**
     * Total time spent executing SQL queries.
     * 
     * @var float
     */
    private $timeSpent;

    /**
     * Singleton instance of the class.
     * 
     * @var PostgreSQLDatabase|null
     */
    protected static $_instance = NULL;

    /**
     * Constructor.
     * 
     * Initializes internal variables and prepares the class for use.
     */
    protected function __construct() {
        $this->stmtarray = array();
        $this->stmtconfig = array();
        $this->stmtcount = 0;
        $this->resource = NULL;
        $this->connection_data = array();
        $this->executedSTMTs = array();
        $this->executionCount = 0;
        $this->timeSpent = 0;
    }

    /**
     * Destructor.
     * 
     * Ensures the database connection is closed and resources are freed.
     */
    public function __destruct() {
        if ($this->IsConnected()) {
            $this->Disconnect();
        }
    }

    /**
     * Connects to the PostgreSQL database.
     * 
     * @throws Exception If the connection fails.
     */
    final protected function Connect() {
        $connstring = "host=" . $this->connection_data['server'];
        $connstring .= " port=5432 dbname=" . $this->connection_data['db'];
        $connstring .= " user=" . $this->connection_data['user'];
        $connstring .= " password=" . $this->connection_data['pass'];
        $connstring .= " options='--client_encoding=UTF8'";
        $this->resource = pg_connect($connstring);
        if (!$this->resource) {
            $this->resource = NULL;
            trigger_error("Unable to connect to the database. Please try again later.", E_USER_ERROR);
        }
    }

    /**
     * Configures the database connection settings.
     * 
     * @param string $server The database server address.
     * @param string $user The username for the database.
     * @param string $pass The password for the database.
     * @param string $db The name of the database.
     */
    final protected function Configure($server, $user, $pass, $db) {
        $this->connection_data['server'] = $server;
        $this->connection_data['user'] = $user;
        $this->connection_data['pass'] = $pass;
        $this->connection_data['db'] = $db;
    }

    /**
     * Deletes a prepared statement by its name.
     * 
     * @param string $name The name of the prepared statement.
     */
    final protected function DeleteSTMT($name) {
        if (isset($this->stmtarray["name"])) {
            if (!is_null($this->stmtarray["name"][1])) {
                pg_free_result($this->stmtarray["name"][1]);
            }
            pg_free_result($this->stmtarray["name"][0]);
            $this->ExecuteQuery("DEALLOCATE " . $this->FilterString($name));
            unset($this->stmtnamearray[$name]);
            $this->stmtcount--;
        }
    }

    /**
     * Deletes all prepared statements.
     */
    final protected function DeleteAllSTMT() {
        foreach ($this->stmtarray as $stmt => $value) {
            $this->DeleteSTMT($stmt);
        }
    }

    /**
     * Disconnects from the database and clears all prepared statements.
     */
    final private function Disconnect() {
        if ($this->IsConnected()) {
            $this->DeleteAllSTMT();
            pg_close($this->resource);
            $this->resource = NULL;
        }
    }

    /**
     * Executes a raw SQL query.
     * 
     * @param string $query The SQL query to execute.
     * @return array|bool The result of the query, or false on failure.
     */
    final protected function ExecuteQuery($query) {
        if ($this->IsConnected()) {
            $start = microtime(true);
            $result = @pg_query($this->resource, $query);
            $resultarray = pg_fetch_all($result);
            pg_free_result($result);
            $this->timeSpent += (microtime(true) - $start);
            return $resultarray;
        } else {
            return false;
        }
    }

    /**
     * Configures a prepared statement.
     * 
     * @param string $name The name of the prepared statement.
     * @param string $query The SQL query for the prepared statement.
     */
    final public function ConfigureSTMT($name, $query) {
        $this->stmtconfig["$name"] = $query;
    }

    /**
     * Executes a query asynchronously.
     * 
     * @param string $query The SQL query to execute.
     */
    final public function ExecuteAsyncQuery($query) {
        if ($this->IsConnected()) {
            while (pg_get_result($this->resource) !== FALSE);
            pg_send_query($this->resource, $query);
        }
    }

    /**
     * Loads a previously configured prepared statement.
     * 
     * @param string $stmt The name of the prepared statement.
     */
    final private function LoadConfiguredSTMT($stmt) {
        if (!$this->IsConnected()) {
            $this->Connect();
        }
        $query = "";
        if (isset($this->stmtconfig["$stmt"])) {
            $query = $this->stmtconfig[$stmt];
        }
        if (!empty($query)) {
            if (!isset($this->stmtarray[$stmt]))
                $this->PrepareSTMT($stmt, $query);
        }
        else {
            trigger_error("STMT " . $stmt . " has an empty query!", E_USER_ERROR);
        }
    }

    /**
     * Processes and gets correct argument types and format.
     * 
     * @param array|null $args The arguments to process.
     * @return array|null The processed arguments.
     */
    final private function ProcessArgumentTypes($args) {
        $newparams = NULL;
        if (!is_null($args)) {
            $newparams = array();
            foreach ($args as $key => $value) {
                if (is_bool($value)) {
                    $newparams[$key] = ($value) ? 't' : 'f';
                } else if (is_array($value)) {
                    $newparams[$key] = "{";
                    for ($i = 0, $length = count($value); $i < $length; $i++) {
                        $newparams[$key] .= $value[$i];
                        if ($i < $length - 1) {
                            $newparams[$key] .= ",";
                        }
                    }
                    $newparams[$key] .= "}";
                } else {
                    $newparams[$key] = $value;
                }
            }
        }
        return $newparams;
    }

    /**
     * Debug function to load and parse all prepared statements in search for an error.
     */
    final protected function LoadAllSTMTs() {
        foreach ($this->stmtconfig as $name => $query) {
            $this->PrepareSTMT($name, $query);
        }
    }

    /**
     * Prepares and stores a prepared statement.
     * 
     * @param string $stmtname The name of the prepared statement.
     * @param string $query The SQL query for the prepared statement.
     */
    final private function PrepareSTMT($stmtname, $query) {
        if ($this->IsConnected()) {
            $hasresults = true;
            /* Remove leading spaces */
            $query = preg_replace("/^\s+/", "", $query);
            while (pg_get_result($this->resource) !== FALSE); //Just in case there are pending results
            $stmt = pg_prepare($this->resource, $stmtname, $query);
            if ($stmt) {
                $this->stmtarray[$stmtname][0] = $stmt;
                $this->stmtarray[$stmtname][1] = null;
                $this->stmtarray[$stmtname][2] = $hasresults;
                $this->stmtcount++;
            } else {
                trigger_error("The sentence '" . $stmtname . "' could not be loaded!", E_USER_ERROR);
            }
        } else {
            trigger_error("Database is NOT connected!", E_USER_ERROR);
        }
    }

    /**
     * Checks if a prepared statement returns results.
     * 
     * @param string $name The name of the prepared statement.
     * @return bool True if the statement returns results, false otherwise.
     */
    private function GetProduceResultsSTMT($name) {
        return $this->stmtarray[$name][2];
    }

    /**
     * Executes and fetches results from a prepared statement.
     * 
     * @param string $name The name of the prepared statement.
     * @param mixed ...$params The parameters for the prepared statement.
     * @return array|bool|int The result of the execution, or false on failure.
     */
    final public function ExecuteSTMT($name, ...$params) {
        $start = microtime(true);
        global $debugmode;
        $this->LoadConfiguredSTMT($name);
        if (isset($this->stmtarray[$name]) && !is_null($this->resource)) {
            $newparams = $this->ProcessArgumentTypes($params);
            if (!is_null($this->stmtarray[$name][1])) {
                pg_free_result($this->stmtarray[$name][1]);
                $this->stmtarray[$name][1] = null;
            }
            $this->stmtarray[$name][1] = @pg_execute($this->resource, $name, $newparams);
            $result = null;
            if (!$this->stmtarray[$name][1]) {
                trigger_error("An error happened executing STMT " . $name . "<br />The error was: " . pg_last_error($this->resource), E_USER_ERROR);
            } else {
                if (!$this->GetProduceResultsSTMT($name)) {
                    $result = pg_affected_rows($this->stmtarray[$name][1]);
                } else {
                    $result = pg_fetch_all($this->stmtarray[$name][1]);
                }
                $this->executionCount++;
            }
            
            //Update debug statistics
            $execTime = microtime(true) - $start;
            if (!isset($this->executedSTMTs["$name"]))
                $this->executedSTMTs["$name"] = array(
                    "executionTimeMin" => $execTime,
                    "executionTimeMax" => $execTime,
                    "calls"        => 1
                );
            else {
                $this->executedSTMTs["$name"]["calls"]++;
                $this->executedSTMTs["$name"]["executionTimeMin"] = min($this->executedSTMTs["$name"]["executionTimeMin"], $execTime);
                $this->executedSTMTs["$name"]["executionTimeMax"] = max($this->executedSTMTs["$name"]["executionTimeMax"], $execTime);
            }
            $this->timeSpent += $execTime;
            return $result;
        } else {
            trigger_error("STMT named '" . $name . "' has not been loaded before or has generated errors.", E_USER_ERROR);
            return false;
        }
    }

    /**
     * Executes a prepared statement asynchronously (ignoring result).
     * 
     * @param string $name The name of the prepared statement.
     * @param mixed ...$params The parameters for the prepared statement.
     */
    final public function ExecuteAsyncSTMT($name, ...$params) {
        $start = microtime(true);
        $this->LoadConfiguredSTMT($name);
        if (isset($this->stmtarray[$name]) && !is_null($this->resource)) {
            $newparams = $this->ProcessArgumentTypes($params);
            if (!is_null($this->stmtarray[$name][1])) {
                pg_free_result($this->stmtarray[$name][1]);
                $this->stmtarray[$name][1] = null;
            }
            while (pg_get_result($this->resource) !== FALSE);
            while (!pg_send_execute($this->resource, $name, $newparams)); //This while is because it can fail the sending of the query
            $this->executionCount++;
            //Update debug statistics
            $execTime = microtime(true) - $start;
            if (!isset($this->executedSTMTs["$name"]))
                $this->executedSTMTs["$name"] = array(
                    "executionTimeMin" => $execTime,
                    "executionTimeMax" => $execTime,
                    "calls"        => 1
                );
            else {
                $this->executedSTMTs["$name"]["calls"]++;
                $this->executedSTMTs["$name"]["executionTimeMin"] = min($this->executedSTMTs["$name"]["executionTimeMin"], $execTime);
                $this->executedSTMTs["$name"]["executionTimeMax"] = max($this->executedSTMTs["$name"]["executionTimeMax"], $execTime);
            }
        }
    }

    /**
     * Obtain affected rows of a prepared statement previously executed.
     * 
     * @param string $name The name of the prepared statement.
     * @return int The number of affected rows.
     */
    final public function GetAffectedRowsSTMT($name) {
        return pg_affected_rows($this->stmtarray[$name][1]);
    }

    /**
     * Checks if the server is connected.
     * 
     * @return bool True if connected, false otherwise.
     */
    public function IsConnected() {
        return isset($this->resource);
    }

    /**
     * Function to show all prepared statement names.
     */
    public final function ShowAllSTMTNames() {
        foreach ($this->stmtarray as $name => $stmt) {
            echo $name . "<br />";
        }
    }

    /**
     * Starts a transaction.
     */
    public final function StartTransaction() {
        $this->ExecuteQuery("START TRANSACTION");
    }

    /**
     * Locks a table within a transaction.
     * 
     * @param string $tablename The name of the table to lock.
     */
    public final function LockTable($tablename) {
        $this->ExecuteQuery("LOCK TABLE " . $tablename);
    }

    /**
     * Ends a transaction.
     * 
     * @param bool $commit True to commit data, false to rollback.
     */
    public final function EndTransaction($commit = true) {
        if ($commit) {
            $this->ExecuteQuery("COMMIT");
        } else {
            $this->ExecuteQuery("ROLLBACK");
        }
    }

    /**
     * Starts a copy from STDIN.
     * 
     * @param string $tablename The name of the table.
     */
    public final function StartCopyFromSTDIN($tablename) {
        $this->ExecuteQuery("COPY " . $tablename . " FROM STDIN");
    }

    /**
     * Puts data to STDIN for a copy.
     * 
     * @param string $data The data to copy.
     */
    public final function PutDataSTDIN($data) {
        pg_put_line($this->resource, $data);
    }

    /**
     * Ends copying data from STDIN.
     */
    public final function EndCopyFromSTDIN() {
        pg_end_copy($this->resource);
    }

    /**
     * Gets all configured prepared statements (for debug).
     * 
     * @return array The configured prepared statements.
     */
    final protected function GetAllConfiguredSTMTs() {
        return $this->stmtconfig;
    }

    /**
     * Gets the execution count of prepared statements.
     * 
     * @return array The execution count of prepared statements.
     */
    final protected function GetSTMTExecStats() {
        return self::$STMTCalled;
    }

    /**
     * Escapes a string for use in a SQL query.
     * 
     * @param string $string The string to escape.
     * @return string The escaped string.
     */
    final public function EscapeString($string) {
        if (!$this->IsConnected())
            $this->Connect();
        return pg_escape_string($this->resource, $string);
    }

    /**
     * Gets the count of executed SQL queries.
     * 
     * @return int The count of executed SQL queries.
     */
    public function GetSQLQueryExecutedCount() {
        return $this->executionCount;
    }

    /**
     * Escapes a bytea value for use in a SQL query.
     * 
     * @param string $bytea The bytea value to escape.
     * @return string The escaped bytea value.
     */
    public function EscapeByteA($bytea) {
        return pg_escape_bytea($this->resource, $bytea);
    }

    /**
     * Unescapes a bytea value.
     * 
     * @param string $bytea The bytea value to unescape.
     * @return string The unescaped bytea value.
     */
    public function UnEscapeByteA($bytea) {
        return pg_unescape_bytea($bytea);
    }

    /**
     * Gets statistics for executed prepared statements.
     * 
     * @return array The statistics for executed prepared statements.
     */
    public function GetExecutedSTMTStatistics() {
        return $this->executedSTMTs;
    }
    
    /**
     * Gets the total time spent executing SQL queries.
     * 
     * @return float The total time spent executing SQL queries.
     */
    public function GetTimeSpent() {
        return $this->timeSpent;
    }
}

?>