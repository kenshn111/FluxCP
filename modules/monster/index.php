<?php
if (!defined('FLUX_ROOT')) exit;
include_once(FLUX_ROOT.'/'.FLUX_MODULE_DIR.'/functions.php');

//$this->loginRequired();

$title = 'Monsters Database';

require_once 'Flux/TemporaryTable.php';
$fromTables = array("{$server->charMapDatabase}.mob_db", "{$server->charMapDatabase}.mob_db2");
$tableName  = "{$server->charMapDatabase}.monsters";
$json_arr = array();
$col_data = array();


$columns_ = array(
	'origin_table',
	'IF(origin_table like "%_db2", "yes", "no")' => 
		array(
			'pseudo' => 'custom',
			'default' => "no",
			'search' => 'checkbox',
			'choices' => array(
				'yes' => "Yes",
				'no' => "No",
			),
			'label' => "Custom",
		),
	'monsters.id' => 
		array(
			'pseudo' => 'monster_id',
			'label' => "Monster ID",
		),
	'kName' => 
		array(
			'pseudo' => 'kro_name',
			'label' => "kRO Name",
		),
	'iName' => 
		array(
			'pseudo' => 'iro_name',
			'label' => "iRO Name",
		),
	'DropCardid' => 
		array(
			'pseudo' => 'dropcard_id',
			'label' => "iRO Name",
		),
	'LV' => 
		array(
			'original' => 'LV',
			'pseudo' => 'level',
			'search' => 'range',
			'label' => "Level",
		),
	'HP' => 
		array(
			'pseudo' => 'hp',
			'search' => 'range',
			'label' => "HP",
		),
	'Scale' => 
		array(
			'pseudo' => 'size',
			'search' => 'checkbox',
			'choices' => Flux::config('MonsterSizes')->toArray(),
			'label' => "Size",
		),
	'Race' => 
		array(
			'pseudo' => 'race',
			'search' => 'checkbox',
			'choices' => Flux::config('MonsterRaces')->toArray(),
			'label' => "Race",
		),
	'(Element%10)' => 
		array(
			'pseudo' => 'element_type',
			'search' => 'checkbox',
			'choices' => Flux::config('Elements')->toArray(),
			'label' => "Element Type",
		),
	'FLOOR(Element/20)' => 
		array(
			'original' => 'FLOOR(Element/20)',
			'pseudo' => 'element_level',
			'label' => "Element Level",
			'search' => 'range',
		),
	'(EXP * '.$server->expRates['Base'].' / 100)' => 
		array(
			'pseudo' => 'exp',
			'label' => "Base Exp",
			'search' => 'range',
		),
	'(JEXP * '.$server->expRates['Job'].' / 100)' => 
		array(
			'pseudo' => 'jexp',
			'label' => "Job Exp",
			'search' => 'range',
		),
	'(mexp * '.$server->expRates['Mvp'].' / 100)' => 
		array(
			'original' => 'mexp',
			'pseudo' => 'mvp_exp',
			'label' => "MVP Exp",
			'search' => 'range',
		),
);

$col_data = array_merge($col_data,prepareColumnsString($columns_));

try {
	$tempTable  = new Flux_TemporaryTable($server->connection, $tableName, $fromTables);
	
	// Statement parameters, joins and conditions.
	$bind        = array();
	$sqlpartial  = "WHERE 1=1 ";

	$search_text    = $params->get('itemText');
	$sizeP           = $params->get('size');
	$raceP           = $params->get('race');
	$elementP        = $params->get('element_type');
	$custom         = $params->get('custom');
	$monster_id = $params->get('monster_id');

	if ($search_text && $monster=='') {
		$search_text = "%".str_replace(" ","%",$search_text)."%";
		$sqlpartial .= "AND ((ID LIKE ?) OR (kName LIKE ?) OR (iName LIKE ?) OR (DropCardid LIKE ?)) ";
		$bind[]      = $search_text;
		$bind[]      = $search_text;
		$bind[]      = $search_text;
		$bind[]      = $search_text;
	}
	if($monster_id!='') {
		$sqlpartial .= "AND (ID LIKE ?) ";
		$bind[]      = $monster_id;
	}
	if ($sizeP && $sizeP !== '-1') {
		$sqlpartial .= "AND (1=0 ";
		foreach(explode(",",$sizeP) as $size) {
			if(is_numeric($size) && (floatval($size) == intval($size))) {
				$sizes = Flux::config('MonsterSizes')->toArray();
				if (array_key_exists($size, $sizes) && $sizes[$size]) {
					$sqlpartial .= "OR Scale = ? ";
					$bind[]      = $size;
				}
			}
		}
		$sqlpartial .= ") ";
	}
	if ($raceP && $raceP !== '-1') {
		$sqlpartial .= "AND (1=0 ";
		foreach(explode(",",$raceP) as $race) {
			if(is_numeric($race) && (floatval($race) == intval($race))) {
				$races = Flux::config('MonsterRaces')->toArray();
				if (array_key_exists($race, $races) && $races[$race]) {
					$sqlpartial .= "OR Race = ? ";
					$bind[]      = $race;
				}
			}
		}
		$sqlpartial .= ") ";
	}
	if ($elementP && $elementP !== '-1') {
		$sqlpartial .= "AND (1=0 ";
		foreach(explode(",",$elementP) as $element) {
			if (count($elementSplit = explode('-', $element)) == 2) {
				$element = $elementSplit[0];
				$elementLevel = $elementSplit[1];
			}
			if (is_numeric($element) && (floatval($element) == intval($element))) {
				$elements = Flux::config('Elements')->toArray();
				if (array_key_exists($element, $elements) && $elements[$element]) {
					$sqlpartial .= "OR (Element%10 = ? ";
					$bind[]      = $element;
				} else {
					$sqlpartial .= 'OR (Element IS NULL ';
				}
				
				if (count($elementSplit) == 2 && is_numeric($elementLevel) && (floatval($elementLevel) == intval($elementLevel))) {
					$sqlpartial .= "AND CAST(Element/20 AS UNSIGNED) = ? ";
					$bind[]      = $elementLevel;
				}

				$sqlpartial .= ") ";
			}
		}
		$sqlpartial .= ") ";
	}
	
	if ($custom) {
		if(count(explode(",",$custom))!=2 || $custom !== -1) {
			if ($custom == 'yes') {
				$sqlpartial .= "AND origin_table LIKE '%mob_db2' ";
			}
			elseif ($custom == 'no') {
				$sqlpartial .= "AND origin_table LIKE '%mob_db' ";
			}
		}
	}

	foreach($col_data['s_params']['search']['range'] as $rItem => $label) {
		$val = $params->get(str_replace("`","",$rItem));
		if($val!='') {
			$g = explode(",",$val);
			if(count($g)==2) {
				if(array_key_exists('original',$label)) $rItem = $label['original'];
				if(array_key_exists('useOr',$label)) {
					$tmp = "OR ($rItem >= ? AND $rItem <= ?)";
				}
				else {
					$tmp = "AND ($rItem >= ? AND $rItem <= ? )";
				}
				$sqlpartial .= $tmp;
				$bind[] = $g[0];
				$bind[] = $g[1];
			}
		}
	}
	
	// Get total count and feed back to the paginator.
	$sth = $server->connection->getStatement("SELECT COUNT(monsters.ID) AS total, ". implode(", ",$col_data['s_params']['columns'])." FROM $tableName $sqlpartial");
	$sth->execute($bind);
	$res = $sth->fetch();
	$search_params['checkbox'] = $col_data['s_params']['search']['checkbox'];
	$json_arr['total'] = $res->total ? $res->total : 0;
	if(false) {
		echo $sth->debugDumpParams();
		echo "<hr>";
		echo "<pre>";
		print_r($res);
		echo "</pre>";
		exit();
	}

	$json_arr['defaults'] = $col_data['defaults'];
	foreach($col_data['s_params']['search']['range'] as $key => $label) {
		$key2 = str_replace("`","",$key);
		$search_params['range'][$key2] = array(
			'label' => $label['label'],
			'min' => (is_numeric($g=$res->{"min_".$key2})) ? $g+=0 : $g,
			'max' => (is_numeric($g=$res->{"max_".$key2})) ? $g+=0 : $g,
		);
	}
	$paginator = $this->getPaginator($res->total);
	$sortable = array(
		'monster_id' => 'asc', 'kro_name', 'iro_name', 'level', 'hp', 'size', 'race', 'exp', 'jexp', 'dropcard_id', 'origin_table'
	);
	$paginator->setSortableColumns($sortable);
	$sql  = $paginator->getSQL("SELECT ".implode(", ",$col_data['items_query'])." FROM $tableName $sqlpartial");
	$sth  = $server->connection->getStatement($sql);
	
	if(is_array($paginator->currentSortOrder))	$sortable = array_merge($sortable,$paginator->currentSortOrder);

	$sth->execute($bind);
	$monsters = $sth->fetchAll();

	$json_arr['monsters'] = forJSON($this,$monsters,$col_data);
	foreach($search_params['checkbox'] as $r => $d) { $t[$r] = '-1'; }
	if($params->get('item_id')!='') {
		$t['itemText'] = $params->get('item_id');
	}
	$json_arr['_params'] = $t;
	$json_arr['_params_default'] = $t;
	foreach($sortable as $k => $v) {if(!is_numeric($k)) $json_arr['_params'][$k."_order"] = $v; }
	$json_arr['perPage'] = Flux::config('ResultsPerPage');
	$json_arr['labels'] = $col_data['labels'];
	$json_arr['sortable'] = array('ASC','DESC','NONE',); 
	$json_arr['sizes'] = Flux::config('MonsterSizes')->toArray();
	$json_arr['races'] = Flux::config('MonsterRaces')->toArray();
	$json_arr['elements'] = Flux::config('Elements')->toArray();

	if($params->get('output')=='json') {
		$tmp = array();
		$data_output = $params->get('data_output');
		if($data_output!='') {
			$output = explode(",",$data_output);
			foreach($output as $opt) {
				if(array_key_exists($opt, $json_arr)) {
					$tmp[$opt] = $json_arr[$opt];
				}
			}
		}
		echo json_encode($tmp);
		exit();
	}


	/*
	$authorized = $auth->actionAllowed('monster', 'view');
	
	if ($monsters && count($monsters) === 1 && $authorized && Flux::config('SingleMatchRedirectMobs')) {
		$this->redirect($this->url('monster', 'view', array('id' => $monsters[0]->monster_id)));
	}
	*/
}
catch (Exception $e) {
	if (isset($tempTable) && $tempTable) {
		// Ensure table gets dropped.
		$tempTable->drop();
	}
	
	// Raise the original exception.
	$class = get_class($e);
	throw new $class($e->getMessage());
}
?>
