<?php
error_reporting(E_ALL);
include "kxparse-2_3-dev.php";
$xmlobj=new kxparse("try-2_3.xml");
$flag=$xmlobj->cnext(true);
/*$flag=$xmlobj->cnext();
$flag=$xmlobj->cnext();
$flag=$xmlobj->cnext();
$flag=$xmlobj->cnext();*/
echo $xmlobj->get_attribute("tagid");echo "<br>";
var_dump($flag);
?>
