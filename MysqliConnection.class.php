<?php
/**********************************************
MysqliConnect
by Kay-Egil Hauan
This version 2016-08-07
**********************************************/

namespace kyegil\MysqliConnection;


class MysqliConnection extends mysqli {

public $table_prefix = ''; //	End with underscore



function __construct() {
	parent::__construct(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
	$this->query("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
	$this->query("SET TIME_ZONE = '-0:00'"); // All time is saved as GMT and converted later
}



function arrayData($config) {
	settype($config, 'object');
	
	settype($config->distinct,		'boolean');
	settype($config->flat,			'boolean');
	settype($config->returnQuery,	'boolean');

	settype($config->fields,		'array');
	settype($config->groupfields,	'string');
	settype($config->having,		'string');
	settype($config->limit, 		'string');
	settype($config->orderfields,	'string');
	settype($config->source,		'string');
	settype($config->sql,			'string');
	settype($config->where,			'string');

	$result = (object)array(
		'success' => true,
		'msg'=> "",
		'data' => null
	);

	if(!isset( $config->source ) && !isset( $config->sql ) ) {
		$result->success = false;
		$result->msg = "Inadequate input: No source parameter given in MysqliConnection::arrayData()";
		return $result;
	}

	if(!isset($config->class) or !class_exists($config->class)) {
		$config->class = 'stdClass';
	}
	
	$config->fields = implode(",\n", $config->fields);
	
	$sql =	"SELECT "
		.	($config->distinct	? "DISTINCT " : "")
		.	($config->fields	? "{$config->fields}\n" : "*\n")
		.	"FROM {$config->source}\n"
		.	($config->where		? "WHERE {$config->where}\n" : "")
		.	($config->groupfields	? "GROUP BY {$config->groupfields}\n" : "")
		.	($config->having	? "HAVING {$config->having}\n" : "")
		.	($config->orderfields	? "ORDER BY {$config->orderfields}\n" : "");
	if( $config->sql ) {
		$sql = $config->sql;
	}
	
	$result->data = array();
	$result->success = true;
	if($sett = $this->query($sql)) {
		$result->totalRows = $sett->num_rows;
	}

	if( $config->limit ) {
		$sql .= "LIMIT {$config->limit}";
		$sett = $this->query( $sql );
	}
	if($config->returnQuery) $result->sql = $sql;
	
	if(!$sett) {
		$result->success = false;
		throw new Exception("mysqli error: {$this->error}\nsql:\n{$sql}\n");
		$result->msg = $this->error;
	}
	else {
		if($config->flat) {
			while($arr = $sett->fetch_row()) {
				$result->data[] = $arr[0];
			}
		}
		else {
			while($arr = $sett->fetch_object($config->class)) {
				$result->data[] = ($arr);
			}
		}
		$sett->free();
	}

	return $result;
}



function saveToDb($config) {
	settype($config, 'object');
	
	settype($config->insert,		'boolean');
	settype($config->update,		'boolean');
	settype($config->returnQuery,	'boolean');

	settype($config->test,			'string');
	settype($config->id,			'string');
	settype($config->set,			'string');
	settype($config->table,			'string');
	settype($config->where,			'string');
	settype($config->groupfields,	'string');

	settype($config->fields,		'array');

	$result = (object)array(
		'success' => true,
		'msg'=> ""
	);
	$a = array();

	if (!($config->table)) {
		throw new Exception("No target table given");
	}
	if (!is_array($config->fields) and !is_object($config->fields)) {
		throw new Exception("No fields or data to save");
	}
	if (!isset($config->update) and !isset($config->insert)) {
		throw new Exception("Asked neither to insert nor update");
	}
	if ($config->update and !$config->where) {
		throw new Exception("'WHERE' limitations required when updating table");
	}
	foreach ($config->fields as $field => $value) {
		if( $value instanceof DateTime ) {
			throw new Exception("Value can not be DateTime object: " . var_export($value, true));
		}
		if(gettype($value) == 'array') {
			throw new Exception("Value can not be array: " . var_export($value, true));
		}
		if($value === true or $value === "true" or $value === "TRUE") {
			$a[] =	"\t$field = true";
		}
		else if($value === false or $value === "false" or $value === "FALSE") {
			$a[] =	"\t$field = false";
		}
		else if($value === null) {
			$a[] =	"\t$field = null";
		}
		else {
			$a[] =	"\t$field = '" . $this->real_escape_string(get_magic_quotes_gpc() ? stripslashes(strval($value)) : strval($value)) . "'";
		}
	}

	if ($config->insert) {
		$sql = "INSERT INTO ";
	}
	else if ($config->update) {
		$sql = "UPDATE ";
	}
	$sql .=	$config->table . "\n"
		.	(count($a) ? "SET {$config->set}\n" : " () VALUES ()\n")
		.	implode(",\n", $a)
		.	($config->insert ? "" : "\nWHERE {$config->where}\n");

	if($config->returnQuery) $result->sql = $sql;
	
	if(@$config->test) {
		echo "\n{$config->test}:\n{$sql}\n";
	}
	
	if ($result->success = $this->query($sql)) {
		if ($config->update) {
			$result->id = $config->id;
		}
		else {
			$result->id = $this->insert_id;
		}
	}
	else {
		throw new Exception("mysqli error: {$this->error}\nsql:\n{$sql}\n");
		$result->msg = $this->error;
	}

	return $result;
}


}
?>