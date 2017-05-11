<?php

/**
 * Base class for a postgreSQL database
 *
 * @author	David Carlos Manuelda <david@anuubis.com>
 * @package	StormBytePP
 * @version	1.0.0
 * @link https://github.com/StormBytePP GitHub URL for PostgreSQL project
 */
abstract class PostgreSQLDatabase {

	const SMALLINT_MIN = "-32768";
	const SMALLINT_MAX = "32767";
	const INT_MIN = "-2147483648";
	const INT_MAX = "2147483647";
	const BIGINT_MIN = "-9223372036854775808";
	const BIGINT_MAX = "9223372036854775807";

	/**
	 * Database Resource
	 * @var resource
	 */
	private $resource;

	/**
	 * Array to save stmt's used names
	 * @var array Format: ['name'] => array( 0 -> stmt, 1 -> lastresult, 2 -> hasresults )
	 */
	private $stmtarray;

	/**
	 * Autoincremental counter
	 * @var int
	 */
	private $stmtcount;

	/**
	 * Stores the SQL query of a prepared statement (and its name)
	 * @var array name => SQL query
	 */
	private $stmtconfig;

	/**
	 * Stores the connection data
	 * @var array
	 */
	private $connection_data;
	private static $STMTCalled = array();

	/**
	 * Execution Count
	 * @var int
	 */
	private $executionCount;

	/**
	 * Executed STMTs
	 * @var array "name" => count
	 */
	private $executedSTMTs;

	/**
	 * Current instance
	 * @var PostgreSQLDatabase
	 */
	protected static $_instance = NULL;

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->stmtarray = array();
		$this->stmtconfig = array();
		$this->stmtcount = 0;
		$this->resource = NULL;
		$this->connection_data = array();
		$this->executedSTMTs = array();
		$this->executionCount = 0;
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		if ($this->IsConnected()) {
			//borrar las STMT, se encarga el disconnect
			$this->Disconnect();
		}
	}

	/*
	 * Connects to Database
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
			trigger_error("No se puede conectar a la base de datos. Pruebe de nuevo pasados unos segundos.", E_USER_ERROR);
		}
	}

	/**
	 * Configures this server
	 * @param string $server
	 * @param string $user
	 * @param string $pass
	 * @param string $db 
	 */
	final protected function Configure($server, $user, $pass, $db) {
		$this->connection_data['server'] = $server;
		$this->connection_data['user'] = $user;
		$this->connection_data['pass'] = $pass;
		$this->connection_data['db'] = $db;
	}

	/**
	 * Deletes a STMT by its name
	 * @param string $name Name of STMT
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
	 * Deletes all STMTs
	 */
	final protected function DeleteAllSTMT() {
		foreach ($this->stmtarray as $stmt => $value) {
			$this->DeleteSTMT($stmt);
		}
	}

	/*
	 * Disconnects from DB and clear all STMT
	 */

	final private function Disconnect() {
		if ($this->IsConnected()) {
			$this->DeleteAllSTMT();
			pg_close($this->resource);
			$this->resource = NULL;
		}
	}

	/**
	 * Executes a SQL sentence
	 * @param string $query SQL query
	 * @return bool|resource TRUE or resource of the query
	 */
	final protected function ExecuteQuery($query) {
		if ($this->IsConnected()) {
			$result = @pg_query($this->resource, $query);
			$resultarray = pg_fetch_all($result);
			pg_free_result($result);
			return $resultarray;
		} else {
			return false;
		}
	}

	/**
	 * Configures a STMT
	 * @param string $name STMT's name
	 * @param string $query SQL Query
	 */
	final public function ConfigureSTMT($name, $query) {
		$this->stmtconfig["$name"] = $query;
	}

	/**
	 * Executes a query asynchronously
	 * @param string $query SQL Query
	 */
	final public function ExecuteAsyncQuery($query) {
		if ($this->IsConnected()) {
			while (pg_get_result($this->resource) !== FALSE);
			pg_send_query($this->resource, $query);
		}
	}

	/**
	 * Loads a previously configured stmt
	 * @param string $stmt STMT name
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
	 * Proccesses and gets correct argument types and format
	 * @param array|NULL $args
	 * @return array|NULL
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
	 * Debug function to load and parse all stmts in search for an error 
	 */
	final protected function LoadAllSTMTs() {
		foreach ($this->stmtconfig as $name => $query) {
			$this->PrepareSTMT($name, $query);
		}
	}

	/**
	 * Prepares and stores one STMT
	 * @param string $string Name to store (or alias) that STMT
	 * @param string $query SQL query
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
	 * Checks if a STMT returns results (it is SELECT, SHOW, DESCRIBE or EXPLAIN)
	 * @param string $name Name of STMT
	 * @return bool
	 */
	private function GetProduceResultsSTMT($name) {
		return $this->stmtarray[$name][2];
	}

	/**
	 * Executes and fetchs results from STMT
	 * @param string $name Name of STMT
	 * @param ... Every other parameters comma separated
	 * @return bool|array|int False if error, array if has results or integer if has affected_rows
	 * return array has 2 indexes, 1 for position, and the other is name of field. Example: $foo[5]['usermail'] will be foo@bar.com
	 */
	final public function ExecuteSTMT($name) {
		$start = microtime(true);
		global $debugmode;
		$this->LoadConfiguredSTMT($name);
		if (isset($this->stmtarray[$name]) && !is_null($this->resource)) {
			$newparams = func_get_args();
			array_shift($newparams);
			$newparams = $this->ProcessArgumentTypes($newparams);
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
					"calls"		=> 1
				);
			else {
				$this->executedSTMTs["$name"]["calls"]++;
				$this->executedSTMTs["$name"]["executionTimeMin"] = min($this->executedSTMTs["$name"]["executionTimeMin"], $execTime);
				$this->executedSTMTs["$name"]["executionTimeMax"] = max($this->executedSTMTs["$name"]["executionTimeMax"], $execTime);
			}
			return $result;
		} else {
			trigger_error("STMT named '" . $name . "' has not been loaded before or has generated errors.", E_USER_ERROR);
			return false;
		}
	}

	/**
	 * Executes a prepared statement asynchronously (ignoring result)
	 * @param string $name Prepared Statement Name
	 */
	final public function ExecuteAsyncSTMT($name) {
		$start = microtime(true);
		$this->LoadConfiguredSTMT($name);
		if (isset($this->stmtarray[$name]) && !is_null($this->resource)) {
			$newparams = func_get_args();
			array_shift($newparams);
			$newparams = $this->ProcessArgumentTypes($newparams);
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
					"calls"		=> 1
				);
			else {
				$this->executedSTMTs["$name"]["calls"]++;
				$this->executedSTMTs["$name"]["executionTimeMin"] = min($this->executedSTMTs["$name"]["executionTimeMin"], $execTime);
				$this->executedSTMTs["$name"]["executionTimeMax"] = max($this->executedSTMTs["$name"]["executionTimeMax"], $execTime);
			}
		}
	}

	/**
	 * Obtain affected rows of a STMT previously executed
	 * @param string $name Name of STMT
	 * @return int
	 */
	final public function GetAffectedRowsSTMT($name) {
		return pg_affected_rows($this->stmtarray[$name][1]);
	}

	/**
	 * Checks if server is connected
	 * @return boolean
	 */
	public function IsConnected() {
		$result = false;
		if (isset($this->resource)) {
			$result = true;
		}
		return $result;
	}

	/**
	 * Function to show all stmt names
	 */
	public final function ShowAllSTMTNames() {
		foreach ($this->stmtarray as $name => $stmt) {
			echo $name . "<br />";
		}
	}

	/**
	 * Starts a transaction
	 */
	public final function StartTransaction() {
		$this->ExecuteQuery("START TRANSACTION");
	}

	/**
	 * Locks a table within a transaction
	 * @param string $tablename Table name to lock 
	 */
	public final function LockTable($tablename) {
		$this->ExecuteQuery("LOCK TABLE " . $tablename);
	}

	/**
	 * Ends a transaction
	 * @param bool $commit True: Commit data, false: rollback 
	 */
	public final function EndTransaction($commit = true) {
		if ($commit) {
			$this->ExecuteQuery("COMMIT");
		} else {
			$this->ExecuteQuery("ROLLBACK");
		}
	}

	/**
	 * Starts a copy from STDIN
	 * @param string $tablename Table name
	 */
	public final function StartCopyFromSTDIN($tablename) {
		$this->ExecuteQuery("COPY " . $tablename . " FROM STDIN");
	}

	/**
	 * Puts data to STDIN for a copy
	 * @param string $data Data
	 */
	public final function PutDataSTDIN($data) {
		pg_put_line($this->resource, $data);
	}

	/**
	 * End copying data from stdin
	 */
	public final function EndCopyFromSTDIN() {
		pg_end_copy($this->resource);
	}

	/**
	 * Gets all configured STMT (for debug)
	 * @return array
	 */
	final protected function GetAllConfiguredSTMTs() {
		return $this->stmtconfig;
	}

	/**
	 * Gets STMT Exec Count
	 * @return int
	 */
	final protected function GetSTMTExecStats() {
		return self::$STMTCalled;
	}

	/**
	 * Escapes string
	 * @param string $string String to Scape
	 * @return string
	 */
	final public function EscapeString($string) {
		if (!$this->IsConnected())
			$this->Connect();
		return pg_escape_string($this->resource, $string);
	}

	/**
	 * Gets SQL Executed count
	 * @return int
	 */
	public function GetSQLQueryExecutedCount() {
		return $this->executionCount;
	}

	/**
	 * Escape Bytea
	 * @param string $bytea
	 * @return string
	 */
	public function EscapeByteA($bytea) {
		return pg_escape_bytea($this->resource, $bytea);
	}

	/**
	 * Unescapes bytea
	 * @param string $bytea
	 * @return string
	 */
	public function UnEscapeByteA($bytea) {
		return pg_unescape_bytea($bytea);
	}

	/**
	 * Gets a count of executed stmt
	 * @return array 'name' => array with executionTimeMin, executionTimeMax, and calls
	 */
	public function GetExecutedSTMTStatistics() {
		return $this->executedSTMTs;
	}
}

?>