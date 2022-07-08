<?php
/**
	Trida pro tvorbu SQL dotazu
	Priklady:
	```
	$r_user= db_utils::query("SELECT ::columns::, ::phone:: FROM $__DB__users WHERE id='::0::' AND updated>'::1::'")
		//vygenerovat sloupce z pole
		->setJoinComma("columns", array(
			'id',
			'name', 'surname', 'email',
			//'phone', //see query — backward compatibilty
			'position', 'nationality', 'location', 'starting_date', 'languages',
			'sharepoint_data',
			'photo', 'updated'
		), "`")
		->set("phone", "IF( phone_prefix != '' AND NOT phone LIKE '+%', CONCAT('(+', phone_prefix, ')', phone ) , phone) AS phone")
		->execute(get('id'), get('last_sync'))
		//navazuje na cQuery
		->Row();
	```
		@version 1.5.0
*/
class db_utils{
	private static $queries= array();
	private static $db;

	static function arrayColumns($ac){
		return "`".join("`, `", $ac)."`";
	}
	static function joinComma($value, $quote= "", $prefix= ""){
		if($prefix) $prefix.=".";
		return "$prefix$quote".join("$quote, $prefix$quote", $value)."$quote";
	}
	static function columnPluckRemove(&$row, $column_name){
		$col= $row->$column_name;
		unset($row->$column_name);
		return $col;
	}
	static function columnsPluckRemove(&$row /* ...cols_names */){
		$cn= func_get_args();
		array_shift($cn);
		$out= array();
		foreach($cn as $v) $out[]= self::columnPluckRemove($row, $v);
		return $out;
	}
	static function columnPluckRemoveJSON(&$row, $column_name, $assoc= false){
		return json_decode(self::columnPluckRemove($row, $column_name), $assoc);
	}
	static function setDB($db){
		__db_utils__internal__query::validateDB($db);
		self::$db= $db;
	}
	static function debugDB(){
		return new __db_utils__internal__test_cMySQL();
	}
	static function query(/* [$db, ]$query */){
		$args= func_get_args();
		if(func_num_args()===1) return new __db_utils__internal__query(self::$db, $args[0]);
		return new __db_utils__internal__query($args[0], $args[1]);
	}
	static function setQuery(/* [$db, ]$name, $query */){
		$params= func_get_args();
		if(func_num_args()===2) array_unshift($params, self::$db);
		list( $db, $name, $query )= $params;
		self::$queries[$name]= new __db_utils__internal__query($db, $query);
		return self::$queries[$name];
	}
	static function getQuery($name){
		return clone self::$queries[$name];
	}
}
class __db_utils__internal__query_freezed {
	public function __construct($db_query){
		$this->db_query= $db_query;
	}
	public function __call($name, $arguments){
		$new_instance= new __db_utils__internal__query($this->db_query);
		return call_user_func_array(array( $new_instance, $name ), $arguments);
	}
}
/**
 * @method this __construct(\__db_utils__internal__query $db)
 * @method this __construct(\cMySQL|\__db_utils__internal__test_cMySQL $db, string $query)
 * */
class __db_utils__internal__query{
	private $db;
	private $q;
	private $defaults= array();
	private $defaults_count= 0;
	private $patterns= array();
	static function validateDB($db){
		if(!$db||(!$db instanceof cMySQL&&!$db instanceof __db_utils__internal__test_cMySQL))
			throw new Exception('$db is not instance of cMySQL (try to import kernel)');
	}
	private function init($_prev){
		$this->db= $_prev->db;
		$this->q= $_prev->q;
		$this->defaults= $_prev->defaults;
		$this->defaults_count= $_prev->defaults_count;
		$this->patterns= $_prev->patterns;
		return $this;
	}
	public function __construct($db, $query){
		if($db instanceof self) return $this->init($db);
		self::validateDB($db);
		if(!$query||gettype($query)!=="string") throw new Exception('$query is not string');
		$this->db= $db;
		$this->q= $query;
		return $this;
	}
	public function __clone(){
		return new __db_utils__internal__query($this);
	}
	public function freeze(){
		return new __db_utils__internal__query_freezed($this);
	}
	public function setJoinComma($name, $value, $quote= "", $prefix= ""){
		$this->defaultUnset($name);
		$k= "::$name::";
		$pre_v= db_utils::joinComma($value, $quote, $prefix);
		$v= isset($this->patterns[$name]) ? str_replace($k, $pre_v, $this->patterns[$name]) : $pre_v;
		$this->q= str_replace($k, $v, $this->q);
		return $this;
	}
	public function set($name, $value){
		$this->defaultUnset($name);
		$k= "::$name::";
		$v= isset($this->patterns[$name]) ? str_replace($k, $value, $this->patterns[$name]) : $value;
		$this->q= str_replace($k, $v, $this->q);
		return $this;
	}
	public function map($name, $empty_value, $value_pattern){
		$this->defaultSet($name, $empty_value);
		if(gettype($value_pattern)==="string")
			$this->patterns[$name]= $value_pattern;
		return $this;
	}
	public function execute(/* params for $db->Execute */){
		$params= func_get_args();
		$res= $this->db->Query($this->generateQuery());
		call_user_func_array(array( $res, "Execute" ), $params);
		return $res;
	}
	public function executeArr($params){
		$res= $this->db->Query($this->generateQuery());
		call_user_func_array(array( $res, "Execute" ), $params);
		return $res;
	}
	public function executeRaw(/* params for $db->Execute */){
		$params= func_get_args();
		$res= $this->db->Query($this->generateQuery());
		return call_user_func_array(array( $res, "Execute" ), $params);
	}
	public function toString(){
		return $this->q;
	}
	private function generateQuery(){
		if(!$this->defaults_count) return $this->q;
		$q= $this->q;
		
		foreach ($this->defaults as $name=> $value){
			$q= str_replace("::$name::", $value, $q);
		}
		$this->q= $q;
		$this->defaults_count= 0;
		$this->defaults= array();
		return $q;
	}
	private function defaultIsset($name){
		return !$this->defaults_count ? false : isset($this->defaults[$name]);
	}
	private function defaultUnset($name){
		if(!$this->defaultIsset($name)) return false;
		unset($this->defaults[$name]);
		$this->defaults_count-= 1;
		return true;
	}
	private function defaultSet($name, $value){
		$this->defaults[$name]= $value;
		$this->defaults_count+= 1;
	}
}
// TEST part
class __db_utils__internal__test_cMySQL{
	public function Query(){
		var_dump(func_get_args());
		return new __db_utils__internal__test_cQuery();
	}
}
class __db_utils__internal__test_cQuery{
	public function __call($name, $arguments){
		var_dump($name, $arguments);
		return $this;
	}
}
