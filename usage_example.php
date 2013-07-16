<?php

/**************************************************************************************/
/** As it can be seen here, it is very easy to implement a                           **/
/** secured from SQL injection database connection to Postgre                        **/
/** by configuring sentences. To look at more advantages to                          **/
/** use prepared statements instead of simple SQL queries look at                    **/
/** http://stormbyte.blogspot.com.es/2012/06/programming-with-database-using.html    **/
/** We will suppose we have a table called 'users' with following fields             **/
/** id SERIAL, PRIMARY KEY                                                           **/
/** username TEXT NOT NULL							     **/
/** email TEXT NOT NULL                                                              **/
/**************************************************************************************/

require_once 'PostgreSQLDatabase.php';

class MyDBConnection extends PostgreSQLDatabase {
	/**
	 * Instance of class
	 * @var MyDBConnection
	 */
	private static $instance=NULL;
	
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
		global $host, $database, $user, $password; //Stored in some other file
		$this->Configure($host, $user, $password, $database);
		parent::Connect();
		$this->ConfigAllSTMTs(); //Load all configured STMTs
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
	 * Creates an user and returns its ID
	 * @param string $username Username
	 * @param string $email User Email
	 * @return int User ID
	 */
	public function CreateUser($username, $email) {
		return $this->ExecuteSTMT("createUser", $username, $email)[0]['id'];
	}
}

//In order to use your new class (singleton) this could be a good example:

$dbconn=  MyDBConnection::GetInstance();
echo "Total number of users we have registered is: ".$dbconn->GetUserCount();

?>
