<?php
header("content-type:text/json");

$m = new MongoClient();
$db = $m->selectDB('network');
$c = new MongoCollection($db, 'flows');

$q = array("ts"=>array('$gt'=>intval($_REQUEST["start_ts"])));
$cursor = $c->find($q);
$lns = array();
$max = 0;
foreach ($cursor as $doc) {
	//var_dump($doc);
	$src = $doc["src"];
	$dst = $doc["dst"];
	if( $dst=="default" ) $dst = "outside";
	if( $src=="default" ) $src = "outside";
	switch( $_REQUEST["protocol"] ) {
	case "TCP":
	case "UDP":
	case "IP6":
		$sum = $doc["vals"][$_REQUEST["protocol"]];
		break;
	default:
		$sum = $doc["sum"];
	}
	$hosts[$src] = $src;
	$hosts[$dst] = $dst;
	$key = "$src-$dst";
	if( $lns[$key] ) {
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
