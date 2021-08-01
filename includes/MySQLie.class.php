<?php
/*** *** *** *** *** ***
* @package Quadodo Login Script
* @file    MySQLie.class.php
* @start   July 18th, 2007
* @author  Douglas Rennehan
* @license http://www.opensource.org/licenses/gpl-license.php
* @version 1.1.1
* @link    http://www.quadodo.net
*** *** *** *** *** ***
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*** *** *** *** *** ***
* Comments are always before the code they are commenting.
*** *** *** *** *** ***/
if (!defined('QUADODO_IN_SYSTEM')) {
exit;	
}

/**
 * Contains all the functions to run the MySQLi extension
 */
class MySQLie {

/**
 * @var object $qls - Will contain everything else
 */
var $qls;

    /**
     * Constructs the class and initializes some variables
     * @param    string $server_name - The server name for the connection
     * @param    string $username - The username for the connection
     * @param    string $password - The password for the connection
     * @param    string $name - The name of the database
     * @param    object $qls - Contains all the other classes
     * @param bool $port
     * @optional integer $port        - Port number if needed
     * @return void but will output error if found
     */
	function __construct($server_name, $username, $password, $name, &$qls, $port = false) {
        $this->qls = &$qls;
        $this->server_name = $server_name;
        $this->username = $username;
        $this->password = $password;
        $this->name = $name;
        $this->port = $port;
		$this->total_queries = 0;

        // Connect to the database, or if you want die I don't care :P
        $this->connection = ($this->port !== false) ? mysqli_connect($this->server_name, $this->username, $this->password, $this->name, $this->port) : mysqli_connect($this->server_name, $this->username, $this->password, $this->name);

		if (mysqli_connect_errno()) {
		    die(mysqli_connect_error());
		}
	}

	/**
	 * Updates the total query count.
	 * @return void
	 */
	function update_queries() {
	    $this->total_queries = $this->total_queries + 1;
	}

	/**
	 * http://www.php.net/mysqli_affected_rows
	 */
	function affected_rows() {
	    return mysqli_affected_rows($this->connection);
	}

	/**
	 * http://www.php.net/mysqli_fetch_row
	 */
	function fetch_row($result) {
    	return mysqli_fetch_row($result);
	}

	/**
	 * http://www.php.net/mysqli_fetch_assoc
	 */
	function fetch_assoc($result) {
	    return mysqli_fetch_assoc($result);
	}

	/**
	 * http://www.php.net/mysqli_fetch_array
	 */
	function fetch_array($result) {
			return mysqli_fetch_array($result, MYSQLI_BOTH);
	}

	/**
	 * http://www.php.net/mysqli_free_result
	 */
	function free_result($result) {
	    return mysqli_free_result($result);
	}

	/**
	 * http://www.php.net/mysqli_get_client_info
	 */
	function get_client_info() {
    	return mysqli_get_client_info();
	}

	/**
	 * http://www.php.net/mysqli_insert_id
	 */
	function insert_id() {
    	return mysqli_insert_id($this->connection);
	}

	/**
	 * http://www.php.net/mysqli_num_fields
	 */
	function num_fields($result) {
	    return mysqli_num_fields($result);
	}

	/**
	 * http://www.php.net/mysqli_num_rows
	 */
	function num_rows($result) {
	    return mysqli_num_rows($result);
	}

    /**
     * Will manage transactions
     * @optional string $status - SQL statement to manage transactions
     * @param string $status
     * @return true on success and if $status not set, false on failure
     */
	function transaction($status = 'BEGIN') {
		switch (strtoupper($status)) {
			default:
                return true;
                break;
			case 'START':
			case 'START TRANSACTION':
			case 'BEGIN':
				return mysqli_query($this->connection, 'START TRANSACTION');
                break;
			case 'END':
			case 'COMMIT':
				return mysqli_query($this->connection, 'COMMIT');
                break;
			case 'ROLLBACK':
				return mysqli_query($this->connection, 'ROLLBACK');
                break;
		}
	}

    /**
     * This function selects a query from the database.
     * @param    mixed $what - An array of fields to select or a string for one field
     * @param    string $from - Which table to select from
     * @param bool $where
     * @param bool $order_by
     * @param bool $limit
     * @optional array  $where    - Array containing the information for the WHERE clause
     * @optional array  $order_by - Contains field to order by and direction
     * @optional array  $limit    - Contains offset and number
     * @return void
	 * Example:
	 * $result = $qls->SQL->select('*','table',array('attr1' => array('=',$value),'AND','attr1' => array('=',$value)));
     */
	function select($what, $from, $where = false, $order_by = false, $limit = false) {
        $new_what = '';
        $new_where = '';
        $new_order_by = '';
        $new_limit = '';

		// See if it is an array
		if (is_array($what)) {
			if (count($what) == 1 && $what[0] == '*') {
			    $new_what = '*';
			}
			else {
				/**
				 * Loop through the $what variable and if it is not the
				 * last one, add a comma in the SQL.
				 */
				foreach ($what as $key => $value) {
					if ((count($what) - 1) == $key) {
					    $new_what .= "`{$value}`";
					}
					else {
					    $new_what .= "`{$value}`,";
					}
				}
			}
		}
		else {
			// If the $what is not empty...
			if ($what != '') {
				switch ($what) {
					// What is equal to anything
					default:
                        $new_what = "`{$what}`";
                        break;
					// What is really equal to anything
					case '*':
                        $new_what = '*';
                        break;
				}
			}
			else {
			    die(SQL_SELECT_QUERY_FAILED);
			}
		}

        // Build the query
        $query = "SELECT {$new_what} FROM `{$this->qls->config['sql_prefix']}{$from}`";

		if ($where !== false) {
			if (is_array($where)) {
				// Shouldn't be divisble by 2
				if ((count($where) % 2) == 0) {
				    die(SQL_SELECT_QUERY_FAILED);
				}
				else {
				    $x = 0;

					foreach ($where as $key => $value) {
				    	$x++;

						// Is it the last one?
						if (count($where) == $x) {
							// The last one must be an array
							if (is_array($value)) {
								if (is_numeric($value[1])) {
								    $new_where .= "`{$key}`" . $value[0] . "{$value[1]}";
								}
								else if($value[1] == 'NULL'){
									$new_where .= "`{$key}` " . $value[0] . " {$value[1]}";
								}
								else {
								    $new_where .= "`{$key}`" . $value[0] . "'{$value[1]}'";
								}
							}
							else {
							    die(SQL_SELECT_QUERY_FAILED);
							}
						}
						else {
							/**
							 * If the number we are on is divsible by
							 * 2 and it's equal to one of those put
							 * the value in the variable.
							 */
							if (($x % 2) == 0 && ($value == "AND" || $value == "OR" || $value == "XOR") === true) {
							    $new_where .= " {$value} ";
							}
							else {
								// Must be an array if not... stop
								if (is_array($value)) {
									if (is_numeric($value[1])) {
									    $new_where .= "`{$key}`" . $value[0] . "{$value[1]}";
									}
									else if($value[1] == 'NOT NULL'){
										$new_where .= "`{$key}`" . $value[0] . " {$value[1]}";
									}
									else {
									    $new_where .= "`{$key}`" . $value[0] . "'{$value[1]}'";
									}
								}
								else {
								    die(SQL_SELECT_QUERY_FAILED);
								}
							}
						}
					}
				}
			}
			else {
				// It's not an array and not nothing put it in
				if ($where != '') {
				    $new_where = $where;
				}
				else {
				    die(SQL_SELECT_QUERY_FAILED);
				}
			}
		}

		// Add to query if exists
		if ($new_where != '') {
		    $query .= " WHERE {$new_where}";
		}

		if ($order_by !== false) {
			if (is_array($order_by)) {
				// Make sure the count is equal to 2
				if (count($order_by) == 2) {
					if ($order_by[1] == "ASC" || $order_by[1] == "DESC") {
					    $new_order_by = "`{$order_by[0]}` {$order_by[1]}";
					}
					else {
					    die(SQL_SELECT_QUERY_FAILED);
					}
				}
				else {
				    die(SQL_SELECT_QUERY_FAILED);
				}
			}
			else {
			    //die(SQL_SELECT_QUERY_FAILED);
			    $new_order_by = $order_by;
			}
		}

		// If it exists add to query
		if ($new_order_by != '') {
		    $query .= " ORDER BY {$new_order_by}";
		}

		if ($limit !== false) {
			if (is_array($limit)) {
				// Make sure the number of values is equal to 2
				if (count($limit) == 2) {
				    $new_limit = (int)$limit[0] . ',' . (int)$limit[1];
				}
				else if (count($limit) == 1){
					$new_limit = (int)$limit[0];
				} else {
				    die(SQL_SELECT_QUERY_FAILED);
				}
			}
			else {
			    die(SQL_SELECT_QUERY_FAILED);
			}
		}
		
		// If exists add to query
		if ($new_limit != '') {
		    $query .= " LIMIT {$new_limit}";
		}
		
        $this->update_queries();
        $this->last_query[] = $query;
		
        $result = mysqli_query($this->connection, $query) or die(mysqli_errno($this->connection) . ': ' . mysqli_error($this->connection));
        return $result;
	}
	
	/**
	 * Deletes a row from the database
	 * @param string $from  - Table to delete from
	 * @param array  $where - Array containing WHERE information
	 * @return true on success, false on failure
	 */
	function delete($from, $where) {
        $new_from = '';
        $new_where = '';

		// Thou shalt not be an array
		if (!is_array($from) && $from != '') {
		    $new_from = "`{$this->qls->config['sql_prefix']}{$from}`";
		}
		else {
		    die(SQL_DELETE_QUERY_FAILED);
		}

        // Build query
        $query = "DELETE FROM {$new_from}";

		if ($where !== false) {
			if (is_array($where)) {
				// Shouldn't be divisble by 2
				if ((count($where) % 2) == 0) {
				    die(SQL_SELECT_QUERY_FAILED);
				}
				else {
				    $x = 0;

					foreach ($where as $key => $value) {
				    	$x++;

						// Is it the last one?
						if (count($where) == $x) {
							// The last one must be an array
							if (is_array($value)) {
								if (is_numeric($value[1])) {
								    $new_where .= "`{$key}`" . $value[0] . "{$value[1]}";
								}
								else if($value[1] == 'NULL'){
									$new_where .= "`{$key}` " . $value[0] . " {$value[1]}";
								}
								else {
								    $new_where .= "`{$key}`" . $value[0] . "'{$value[1]}'";
								}
							}
							else {
							    die(SQL_SELECT_QUERY_FAILED);
							}
						}
						else {
							/**
							 * If the number we are on is divsible by
							 * 2 and it's equal to one of those put
							 * the value in the variable.
							 */
							if (($x % 2) == 0 && ($value == "AND" || $value == "OR" || $value == "XOR") === true) {
							    $new_where .= " {$value} ";
							}
							else {
								// Must be an array if not... stop
								if (is_array($value)) {
									if (is_numeric($value[1])) {
									    $new_where .= "`{$key}`" . $value[0] . "{$value[1]}";
									}
									else if($value[1] == 'NOT NULL'){
										$new_where .= "`{$key}`" . $value[0] . " {$value[1]}";
									}
									else {
									    $new_where .= "`{$key}`" . $value[0] . "'{$value[1]}'";
									}
								}
								else {
								    die(SQL_SELECT_QUERY_FAILED);
								}
							}
						}
					}
				}
			}
			else {
				// It's not an array and not nothing put it in
				if ($where != '') {
				    $new_where = $where;
				}
				else {
				    die(SQL_SELECT_QUERY_FAILED);
				}
			}
			// Add to query
			$query .= " WHERE {$new_where}";
		}

        

        $this->update_queries();
        $this->last_query[] = $query;
        mysqli_query($this->connection, $query) or die(mysqli_errno($this->connection) . ': ' . mysqli_error($this->connection));
	}

	/**
	 * Run an update query
	 * @param string $table - Table to delete from
	 * @param array  $set   - Array holding new information
	 * @param array  $where - Condition to delete
	 * @return void
	 */
	function update($table, $set, $where) {
        $new_table = '';
        $new_set = '';
        $new_where = '';

		// Can't be an array or empty
		if (!is_array($table) && $table != '') {
		    $new_table = "`{$this->qls->config['sql_prefix']}{$table}`";
		}
		else {
		    die(SQL_UPDATE_QUERY_FAILED);
		}

        // Build the query
        $query = "UPDATE {$new_table} ";

		// Must be an array
		if (is_array($set)) {
		    $x = 0;

			// $key will hold the column name and $value the new value
			foreach ($set as $key => $value) {
			    $x++;

                // Last one?
				if (count($set) == $x) {
					if($value === null) {
						$new_set .= "`{$key}`=NULL";
					} else {
						$new_set .= "`{$key}`='{$value}'";
					}
				}
				else {
					if($value === null) {
						$new_set .= "`{$key}`=NULL,";
					} else {
						$new_set .= "`{$key}`='{$value}',";
					}
				}
			}
		}
		else {
	    	die(SQL_UPDATE_QUERY_FAILED);
		}

        // Add to query
        $query .= "SET {$new_set} ";

		if (is_array($where)) {
			// Shouldn't be divisble by 2
			if ((count($where) % 2) == 0) {
			    die(SQL_UPDATE_QUERY_FAILED);
			}
			else {
			    $x = 0;

				foreach ($where as $key => $value) {
				    $x++;

					// Is it the last one?
					if (count($where) == $x) {
						// The last one must be an array
						if (is_array($value)) {
							if (is_numeric($value[1])) {
							    $new_where .= "`{$key}`" . $value[0] . "{$value[1]}";
							}
							else {
							    $new_where .= "`{$key}`" . $value[0] . "'{$value[1]}'";
							}
						}
						else {
						    die(SQL_UPDATE_QUERY_FAILED);
						}
					}
					else {
						/**
						 * If the number we are on is divsible by
						 * 2 and it's equal to one of those put
						 * the value in the variable.
						 */
						if (($x % 2) == 0 && ($value == "AND" || $value == "OR" || $value == "XOR") === true) {
						    $new_where .= " {$value} ";
						}
						else {
							if (is_array($value)) {
								if (is_numeric($value[1])) {
								    $new_where .= "`{$key}`{$value[0]}{$value[1]}";
								}
								else {
								    $new_where .= "`{$key}`{$value[0]}'{$value[1]}'";
								}
							}
							else {
							    die(SQL_UPDATE_QUERY_FAILED);
							}
						}
					}
				}
			}
		}
		else {
			// It's not an array and not nothing put it in
			if ($where != '') {
			    $new_where = $where;
			}
			else {
			    die(SQL_UPDATE_QUERY_FAILED);
			}
		}

        // Add to query
        $query .= "WHERE {$new_where}";

        $this->update_queries();
        $this->last_query[] = $query;
		
        mysqli_query($this->connection, $query) or die(mysqli_errno($this->connection) . ': ' . mysqli_error($this->connection));
	}

	/**
	 * Inserts data into a specified table
	 * @param string $table   - Table to insert into
	 * @param array  $columns - Array of columns corresponding to the values
	 * @param array  $values  - Array of values corresponding to the columns
	 * @return void
	 */
	function insert($table, $columns, $values) {
        $new_table = '';
        $new_columns = '';
        $new_values = '';
        $column_count = count($columns);
        $value_count = count($values);

		// Did they define a table?
		if ($table != '') {
		    $new_table = "`{$this->qls->config['sql_prefix']}{$table}`";
		}
		else {
		    die(SQL_INSERT_QUERY_FAILED);
		}

        // Build query
        $query = "INSERT INTO {$new_table}";

		// The column count must equal the value count
		if ($column_count == $value_count) {
			// Must be an array
			if (is_array($columns)) {
			    $x = 0;

				// Loop through the values
				foreach ($columns as $value) {
				    $x++;

					if ($x == $column_count) {
					    $new_columns .= "`{$value}`";
					}
					else {
					    $new_columns .= "`{$value}`,";
					}
				}
			}
			else {
			    die(SQL_INSERT_QUERY_FAILED);
			}

            // Add to query
            $query .= " ({$new_columns})";

			// Must be an array
			if (is_array($values)) {
		    	$x = 0;

				// Loop through the values
				foreach ($values as $value) {
				    $x++;

					if ($x == $value_count) {
			    		$new_values .= is_null($value) ? "NULL" : "'{$value}'";
					}
					else {
					    $new_values .= is_null($value) ? "NULL," : "'{$value}',";
					}
				}
			}
			else {
			    die(SQL_INSERT_QUERY_FAILED);
			}

            // Add to query
            $query .= " VALUES({$new_values})";

            $this->update_queries();
            $this->last_query[] = $query;
            mysqli_query($this->connection, $query) or die(mysqli_errno($this->connection) . ': ' . mysqli_error($this->connection));
		}
		else {
	    	die(SQL_INSERT_QUERY_FAILED);
		}
	}

    /**
     * Alters a table in the database
     * @param    string $table - Which table to alter
     * @param    string $action - Either 'add' or 'drop'
     * @param    string $column - Column name to drop/add
     * @param bool $data_type
     * @param bool $null
     * @optional string  $data_type - Contains a SQL statement for the field type
     * @optional boolean $null      - If the $action is add, is it NULL?
     * @return void
     */
	function alter($table, $action, $column, $data_type = false, $null = false, $default_value = false) {
        $new_table = '';
        $new_action = '';
        $new_column = '';
        $new_data_type = '';
        $new_null = '';

		// Check the table
		if ($table != '') {
	    	$new_table = "{$this->qls->config['sql_prefix']}{$table}";
		}
		else {
	    	die(SQL_ALTER_QUERY_FAILED);
		}

        // Build the query
        $query = "ALTER TABLE {$new_table}";

		// Go through possible $action values
		switch (strtolower($action)) {
			case 'drop':
                $new_action = 'DROP COLUMN';
                break;
			case 'add':
                $new_action = 'ADD';
                break;
			case 'alter':
				$new_action = 'ALTER';
				break;
			default:
                die(SQL_ALTER_QUERY_FAILED);
                break;
		}

        // Append to query
        $query .= " {$new_action}";

		// Check column
		if ($column != '') {
            $new_column = "`{$column}`";
		}
		else {
		    die(SQL_ALTER_QUERY_FAILED);
		}

        // Append column name to query
        $query .= " {$new_column}";

		// Are we adding a column?
		if (strtolower($action) == 'add') {
			if ($data_type !== false) {
				if ($null) {
                    // Append column information to query
                    $query .= " {$data_type} NULL";
				} else {
                    // Append...
                    $query .= " {$data_type} NOT NULL";
				}
				if($default_value !== false) {
					if($default_value === 'NULL') {
						$query .= " DEFAULT ".$default_value;
					} else {
						$query .= " DEFAULT '".$default_value."'";
					}
				}
			} else {
			    die(SQL_ALTER_QUERY_FAILED);
			}
		} else if(strtolower($action) == 'alter') {
			$query .= " SET DEFAULT '".$default_value."'";
		}
		
        $this->update_queries();
        $this->last_query[] = $query;
        mysqli_query($this->connection, $query) or die(mysqli_errno($this->connection) . ': ' . mysqli_error($this->connection));
	}
	
	/**
	 * Generates 
	 * @param string $query - SQL query to run on database
	 */
	function create_db($org_id){
		$query = "CREATE DATABASE ".CUSTOM_APP_DB_PREFIX.$org_id;
		
		$this->update_queries();
        $this->last_query[] = $query;
        mysqli_query($this->connection, $query) or die(mysqli_errno($this->connection) . ': ' . mysqli_error($this->connection));
	}
	
	/**
	 * Reads a SQL file
	 * @param string $file_name - The file name
	 * @return string
	 */
	function read_sql_file($file_name) {
	    $file_name = dirname(__FILE__) . '/schemas/' . $file_name;

		if (file_exists($file_name) && is_readable($file_name)) {
			if ($file_handle = fopen($file_name, 'r')) {
                $file_data = fread($file_handle, filesize($file_name));
                fclose($file_handle);
                return $file_data;
			}
			else {
			    return false;
			}
		}
		else {
		    return false;
		}
	}
	
	/**
	 * Creates the necessary tables for the system
	 * @param string $database_prefix - The prefix entered by the user
	 * @return void
	 */
	function create_app_table() {
        // Fetch SQL information
        $sql = $this->read_sql_file('live_account.sql');

		if ($sql === false) {
		    $this->sql_class->open_sql_file_failed();
		}

        $sql = explode(';', $sql);
        $query_count = count($sql);

		// Loop through all the SQL
		for ($x = 0; $x < $query_count; $x++) {
		    //$sql[$x] = str_replace('{database_prefix}', CUSTOM_APP_DB_PREFIX, $sql[$x]);
			if (!empty($sql[$x]) and strlen($sql[$x]) > 1) {
			    $this->connection->query($sql[$x]) or die($this->connection->error);
			}
		}
	}

	/**
	 * Runs a SQL query on the database
	 * @param string $query - SQL query to run on database
	 * @return resource
	 */
	function drop_db($org_id) {
		$query = 'DROP DATABASE app_' . $org_id;
		$this->connection->query($query) or die($this->connection->error);
	}
	
	/**
	 * Runs a SQL query on the database
	 * @param string $query - SQL query to run on database
	 * @return resource
	 */
	function drop_table($table_name) {
		$query = 'DROP TABLE ' . $table_name;
		$this->connection->query($query) or die($this->connection->error);
	}

	/**
	 * Runs a SQL query on the database
	 * @param string $query - SQL query to run on database
	 * @return resource
	 */
	function query($query) {
		if ($query != '') {
            $this->update_queries();
            $this->last_query[] = $query;
			
            $result = mysqli_query($this->connection, $query) or die(mysqli_errno($this->connection) . ': ' . mysqli_error($this->connection));

            return $result;
		}
		else {
            // Find the error for no query
            $result = mysqli_query($this->connection, '') or die(mysqli_errno($this->connection) . ': ' . mysqli_error($this->connection));
            return $result;
		}
	}

	/**
	 * http://www.php.net/mysqli_close
	 */
	function close() {
	    return mysqli_close($this->connection);
	}
}
