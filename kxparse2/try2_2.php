<?php
include_once "kxparse-2_2-dev.php";
$xmlobj=new kxparse("try2_2.xml");
print_r($xmlobj->get_attributes("?:?","1:1"));
?>
