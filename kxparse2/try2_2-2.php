<?php
include "kxparse-2_2-dev.php";
$xmlobj=new kxparse("test.xml");
print_r($xmlobj->curr_tag['name']);
/*$xmlobj->remove_tag("?:?","1:1");
print_r($xmlobj->document);
exit();
$xmlobj->create_tag("?","1","khalid");
//echo $xmlobj->count_tag("?:?","1:?");
echo $xmlobj->get_content();*/
?>
