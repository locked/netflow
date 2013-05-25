<?php
header("content-type:text/json");

$m = new MongoClient("mongodb://192.168.0.10:27017");
$db = $m->selectDB('network');
$c = new MongoCollection($db, 'flows');

$q = array("ts"=>array('$gt'=>intval($_REQUEST["start_ts"])));
$cursor = $c->find($q);
$lns = array();
$max = 0;
foreach ($cursor as $doc) {
	$src = $doc["src"];
	$dst = $doc["dst"];
	if( $dst=="default" ) $dst = "outside";
	if( $src=="default" ) $src = "outside";
	$version = array_key_exists("v", $doc)?$doc["v"]:"0.1";
	$protocol = array_key_exists("protocol", $_REQUEST)?$_REQUEST["protocol"]:"";
	switch( $version ) {
	case "0.2":
		//print_r($doc);exit;
		$protocols = array("TCP", "UDP", "IP6");
		if( in_array($protocol, $protocols) ) {
			$protocols = array($protocol);
		}
		$sum = 0;
		foreach( $protocols as $p ) {
			$sum += array_key_exists($p, $doc["vals"])?intval($doc["vals"][$p]):0;
		}
	break;
	case "0.1":
	default:
		switch( $protocol ) {
		case "TCP":
		case "UDP":
		case "IP6":
			$sum = $doc["vals"][$protocol];
			break;
		default:
			$sum = $doc["sum"];
		}
	}
	$hosts[$src] = $src;
	$hosts[$dst] = $dst;
	$key = "$src-$dst";
	if( array_key_exists($key, $lns) ) {
		$lns[$key][2] += $sum;
	} else {
		$lns[$key] = array($src, $dst, $sum);
	}
	if( $lns[$key][2]>$max ) $max = $lns[$key][2];
}

$nodes = array();
$i = 0;
foreach($hosts as $host) {
	switch(substr($host, 0, 9)) {
	case "192.168.2":
		$group = 4;
	break;
	case "192.168.0":
		$group = 3;
	break;
	case "outside":
		$group = 2;
	break;
	default:
		$group = 1;
	}
	$nodes[] = array("name"=>$host, "group"=>$group);
	$rnodes[$host] = $i++;
}

$links = array();
foreach($lns as $ln) {
	$value = intval(floatval($ln[2]/$max)*100);
	if($value<5)$value=5;
	$links[] = array("source"=>$rnodes[$ln[0]], "target"=>$rnodes[$ln[1]], "value"=>$value, "dir"=>$ln[0]<$ln[1]?"dst-src":"src-dst");
}

/*
$nodes = array(
	array("name"=>"leila", "group"=>1),
	array("name"=>"fry", "group"=>1),
	array("name"=>"lili", "group"=>1),
	array("name"=>"barney", "group"=>2),
);
$links = array(
	array("source"=>1, "target"=>0, "value"=>5),
	array("source"=>1, "target"=>2, "value"=>5),
	array("source"=>1, "target"=>2, "value"=>1),
	array("source"=>1, "target"=>2, "value"=>1),
	array("source"=>2, "target"=>0, "value"=>20),
	array("source"=>0, "target"=>1, "value"=>10),
);
*/

echo json_encode(array("nodes"=>$nodes, "links"=>$links));
