<?php
/*
* KXParse/2.3
* Initially started in March 2002 By Khalid Al-Kary
* Version 2.0 was started in 24 April 2003 by Khalid Al-Kary
* ----------------------------------------------------------
*/

class kxparse {
 /*--[ tree mapper ]--*/
 var $document;
 var $xml;
 var $xml_parser;
  
 /*---[tag selection engine]--*/
 var $curr_tag;

/*---[tree dumper]---*/
 var $dump_pos;
 var $dump_temp;
 
 /*----[io handling]--*/
 var $file;
   
/*--------------------------------------------------[the constructor]-----*/
 function kxparse($file=false) {
  $this->file=$file;
  if ($file!=false) {
   $flag=$this->init_mapper();
   if (!$flag) {
    return false;
   }
   $this->init_tree_dumper();
   $this->init_selection_engine();
  } 
 }
/*------------------------------------------[IO handling functions]---------*/
 function load($file=false) {
  if ($file===false) {
   $flag=$this->init_mapper();
   return $flag;
  }
  else {
   $this->file=$file;
   $flag=$this->init_mapper();
   if ($flag) {
    $this->init_tree_dumper();
    $this->init_selection_engine();
   }
   return $flag;
  }
 }
 function save($file=false) {
  if ($file==false) {
   $file=$this->file;
  }
  if (!$file) {
   return false;
  }
  $this->dump_tree();
  $flag=$this->io_put_content($file, $this->dump_temp);
  $this->dump_temp="";
  if ($flag==false) {
   return false;
  }
  return true;
 }
 function load_string($str) {
  $this->xml=$str;
  $this->file=false;
  $flag=$this->init_mapper();
  $this->init_tree_dumper();
  $this->init_selection_engine();
  return $flag;
 }
 function io_put_content($filename, $content) {
  $flag=true;
  $fd=fopen($filename, "wb");
  if ($fd==false) {
   $flag=false;
  }
  else {
   $flag=fwrite($fd, $content);
  }
  fclose($fd);
  return $flag;
 }
 function io_get_content($filename) {
  $fd=fopen($filename,"r");
  $content="";
  if ($fd==false) {
   $content=false;
  }
  else {
   while (!feof($fd)) {
    $content.=fread($fd,4096);
   }
  }
  fclose($fd);
  return $content;
 }
 function io_gen_filename($prefix) {
  $i=0;
  $filename="";
  while (true) {
   if (!file_exists(($i==0) ? $prefix : $prefix.".".$i)) {
    $filename=($i==0) ? $prefix : $prefix.".".$i;
    break;
   }
   $i++;
  }
  return $filename;
 }
 function get_content($reswap=true) {
  $this->dump_tree();
  $val=$this->dump_temp;
  $this->dump_temp="";
  return $val;  
 }
 function get_file_name() {
  return $this->file;
 }
/*------------------------------------------[ xml tree mapper (expat-based) ]-*/
 function init_mapper() {
  $this->document['pi']=array();
  $this->document['children']=array(0);
  $this->document['internal']=0;
  $this->document['length']=0;
  $this->curr_tag=&$this->document;
  $this->xml_parser=xml_parser_create();
  xml_set_object($this->xml_parser,$this);
  xml_set_element_handler($this->xml_parser,"start_element_handler","end_element_handler");
  xml_set_character_data_handler($this->xml_parser,"cdata_handler"); 
  xml_set_processing_instruction_handler($this->xml_parser,"pi_handler");
  xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, 0);
  if ($this->file) {
   $fd=fopen($this->file,"r");
   $content="";
   if (!$fd) {
    return false;
   }
   while (!feof($fd)) {
    $content=fread($fd,4096);
    xml_parse($this->xml_parser,$content);
   }
   fclose($fd);
  }
  else {
   if ($this->xml=="") {
    return false;
   }
   xml_parse($this->xml_parser,$this->xml);
  }  
  $this->init_selection_engine();
  return true;
 }
 function start_element_handler($parser, $name, $attribs) {
  $this->curr_tag['length']++;
  if (!isset($this->curr_tag['children']['?'])) {
   $this->curr_tag['children']['?']=array(0);
  }
  if (!isset($this->curr_tag['children'][$name])) {
   $this->curr_tag['children'][$name]=array(0);
  }
  $last_child=count($this->curr_tag['children'][$name]);
  $anon_last_child=count($this->curr_tag['children']['?']);
  $this->curr_tag['children'][$name][$last_child]=array();
  $this->curr_tag['children'][$name][$last_child]['attribs']=$attribs;
  $this->curr_tag['children'][$name][$last_child]['children']=array("?" => array(0));
  $this->curr_tag['children'][$name][$last_child]['parent']=&$this->curr_tag;
  $this->curr_tag['children'][$name][$last_child]['name']=$name;
  $this->curr_tag['children'][$name][$last_child]['length']=0;
  $this->curr_tag['children'][$name][$last_child]['text']="";
  $this->curr_tag['children'][$name][$last_child]['anon_index']=$anon_last_child;
  $this->curr_tag['children'][$name][$last_child]['index']=$last_child;
  $this->curr_tag['children']['?'][$anon_last_child]=&$this->curr_tag['children'][$name][$last_child];
  $this->curr_tag=&$this->curr_tag['children'][$name][$last_child];
 }
 function end_element_handler($parser, $name) {
  $this->curr_tag=&$this->curr_tag['parent'];
 }
 function cdata_handler($parser, $data) {
  $this->curr_tag['text'].=$data;
 }
 function pi_handler($parser, $target, $data) {
  $this->document['pi'][$target]=$data;
 }
/*------------------------------------------[tree dumper]-----------------------*/
 function init_tree_dumper() {
  $this->dump_temp="";
  $this->dump_pos=0;
 }
 function dump_tree() {
  $this->init_tree_dumper();
  $sec_curr_tag=&$this->curr_tag;
  $this->curr_tag=&$this->document;

  $karr=array_keys($this->document['pi']);
  $ecount=count($karr);
  
  for ($i=0; $i<$ecount; $i++) {
   $this->dump_temp.="<?".$karr[$i]." ".$this->htmlencode($this->document['pi'][$karr[$i]], true)."?>";
   $this->dump_pos+=strlen($karr[$i])+strlen($this->htmlencode($this->document['pi'][$karr[$i]], true))+5;
  }
  unset($karr);
  unset($ecount);
  
  $this->curr_tag=&$this->document['children']['?'][1];
  $this->dump_current_element();
  $this->curr_tag=&$sec_curr_tag;
 }
 function dump_current_element() {
  $internal=0;
  if ($this->curr_tag['length']>0 || (isset($this->curr_tag['text']) && $this->curr_tag['text']!="")) {
   $internal=0;
  }
  else {
   $internal=1;
  }
  if ($this->dump_pos<strlen($this->dump_temp)) {
   if ($internal==0) {
    $this->dump_temp=substr_replace($this->dump_temp,"<".$this->curr_tag['name']."></".$this->curr_tag['name'].">",$this->dump_pos,0);
   }
   else {
    $this->dump_temp=substr_replace($this->dump_temp,"<".$this->curr_tag['name']."/>",$this->dump_pos,0);   
   } 
  }
  else {
   if ($internal==0) {
    $this->dump_temp.="<".$this->curr_tag['name']."></".$this->curr_tag['name'].">";
   }
   else {
    $this->dump_temp.="<".$this->curr_tag['name']."/>";
   } 
  }
  if (count($this->curr_tag['attribs'])>0) {
   $this->dump_pos+=strlen($this->curr_tag['name'])+1;
   $karr=array_keys($this->curr_tag['attribs']);
   $ecount=count($karr);
   
   for ($i=0;$i<$ecount;$i++) {
    $this->dump_temp=substr_replace($this->dump_temp," ",$this->dump_pos++,0);
    $this->dump_temp=substr_replace($this->dump_temp,$karr[$i]."=\"".$this->htmlencode($this->curr_tag['attribs'][$karr[$i]], true)."\"",$this->dump_pos,0);
    $this->dump_pos+=strlen($karr[$i])+strlen($this->htmlencode($this->curr_tag['attribs'][$karr[$i]], true))+3;
   }
   unset($karr);
   unset($ecount);
   if ($internal==1) {
    $this->dump_pos+=2;
   }
  }
  else {
   if ($internal==0) {
    $this->dump_pos+=strlen($this->curr_tag['name'])+1;
   }
   else {
    $this->dump_pos+=strlen($this->curr_tag['name'])+3;
   } 
  }
  if ($internal==1) {
   $this->curr_tag=&$this->curr_tag['parent'];
  }
  else {
   $this->dump_pos+=1;
   $this->dump_temp=substr_replace($this->dump_temp,$this->htmlencode($this->curr_tag['text']),$this->dump_pos,0);
   $this->dump_pos+=strlen($this->htmlencode($this->curr_tag['text']));
   $ecount=count($this->curr_tag['children']['?']);
   for ($i=1;$i<$ecount;$i++) {
    $this->curr_tag=&$this->curr_tag['children']['?'][$i];
    $this->dump_current_tag();
   }
   unset($ecount);
   $this->dump_pos+=3+strlen($this->curr_tag['name']);
   $this->curr_tag=&$this->curr_tag['parent'];
  }
 }
/*------------------------------------------------[the element selection engine]--*/
 function init_selection_engine() {
  $this->curr_tag=&$this->document['children']['?'][1];
 }
 function select($tname,$tindex) {
  if ($tname==="") {
   return true;
  }
  
  $pre_sel=&$this->curr_tag;
  $name_arr=$this->explode_collect(":",$tname);
  $index_arr=explode(":",$tindex);
  $reg_array=array();
  
  
  if ($name_arr[0]!=="-" && $index_arr[0]!=="-") {
   $this->curr_tag=&$this->document;
  }
  else {
   if ($name_arr[0]=="-") {
    $name_arr=&array_slice($name_arr,1);
   }
   if ($index_arr[0]=="-") {
    $index_arr=&array_slice($index_arr,1);
   }
  }
  
  $name_count=count($name_arr);
  $index_count=count($index_arr);

  if ($name_count!=$index_count) {
   $this->curr_tag=&$pre_sel;
   return false;
  }

  for ($i=0;$i<$name_count;$i++) {
   if (preg_match("/R\(.{1,}\)/i",$name_arr[$i])===1) {
    $patern=substr($name_arr[$i],2,strlen($name_arr[$i])-3);
    $count=0;
    
    if (isset($this->curr_tag['children']['regs'][$patern])) {
     $reg_arr=&$this->curr_tag['children']['regs'][$patern];
     $count=count($reg_arr);
    }
    else {
     $reg_arr=array(0);
     $count=1;
     $ecount=count($this->curr_tag['children']['?']);
    
     for ($l=1;$l<$ecount;$l++) {
      if (preg_match($patern,$this->curr_tag['children']['?'][$l]['name'])===1) {
       $reg_arr[$count]=&$this->curr_tag['children']['?'][$l];
       $count++;
      }
     }
     $this->curr_tag['children']['regs'][$patern]=&$reg_arr;
     $count--;
    }
    if ($index_arr[$i]==="?") {
     return $count;
    }
    else if ($index_arr[$i]<1) {
     $children_count=$count;
     $index_arr[$i]=$children_count+$index_arr[$i];
     
     if ($index_arr[$i]<1) {
      $this->curr_tag=&$pre_sel;
      return false;
     }
    }
    if (isset($reg_arr[$index_arr[$i]])) {
     $this->curr_tag=&$reg_arr[$index_arr[$i]];
    }
    else {
    $this->curr_tag=&$pre_sel;
     return false;
    }
   }
   else if (strpos($name_arr[$i],"!")===0) {
    $val=$this->cselect(substr($name_arr[$i],1),$index_arr[$i]);
    if ($index_arr[$i]==="?") {
     return $val;
    }
    else if ($val===false) {
     $this->curr_tag=&$pre_sel;
     return false;
    }
   }
   else {
    if ($index_arr[$i]==="?") {
     $returned_count=count($this->curr_tag['children'][$name_arr[$i]]);
     return ($returned_count<=0) ? $returned_count : $returned_count-1;
    }
    else if ($index_arr[$i]<1) {
     $children_count=count($this->curr_tag['children'][$name_arr[$i]]);
     $index_arr[$i]=$children_count+$index_arr[$i];
     
     if ($index_arr[$i]<1) {
      $this->curr_tag=&$pre_sel;
      return false;
     }
    }
    if (isset($this->curr_tag['children'][$name_arr[$i]][$index_arr[$i]])) {
     $this->curr_tag=&$this->curr_tag['children'][$name_arr[$i]][$index_arr[$i]];
    }
    else {
     $this->curr_tag=&$pre_sel;
     return false;
    }
   }
  }
  return true;
 }
 function explode_collect($explosive,$explosion) {
    $myarr=explode($explosive,$explosion);
    return $this->collect_brackets($myarr);
 }
 function collect_brackets($arr) {
  $count=count($arr);
  $res_arr=array();
  $ecount=0;
  
  for ($i=0;$i<$count;$i++) {
   $pos=strpos($arr[$i],"(");
   if ($pos===0 || $pos===1) {
     if ($pos===0) {
      $res_arr[$ecount]=substr($arr[$i],1);
     }
     else if ($pos===1) {
      $res_arr[$ecount]=$arr[$i];
     } 
     if (strpos($arr[$i],")")!=strlen($arr[$i])-1) {
      for ($b=$i+1,$i++;$b<$count;$b++) {
       $res_arr[$ecount].=":".$arr[$b];
       if (strpos($arr[$b],")")===strlen($arr[$b])-1) {
        if ($pos===0) {
         $res_arr[$ecount]=substr($res_arr[$ecount],0,strlen($res_arr[$ecount])-1);
        }
        $ecount++;
        break;
       }
      $i++;
     } 
    }
    else {
     if ($pos===0) {
      $res_arr[$ecount]=substr($res_arr[$ecount],0,strlen($res_arr[$ecount])-1);
     }
     $ecount++;
    }
   }
   else {
    $res_arr[$ecount]=$arr[$i];
    $ecount++;
   }
  }
  return $res_arr;
 }
 function cselect($tname,$tindex) {
  $pre_sel=&$this->curr_tag;
  $pre_sel['crosssel']=true;
  $num=0;
  $flag=true;
  while ($flag) {
   $flag=$this->first_child();
   if (!$flag) $flag=$this->next();
   while (!$flag && !isset($this->curr_tag['parent']['crosssel'])) {
    $this->parent();
    $flag=$this->next();
   }
   if ($flag) {
    if ($this->get_tag_name()===$tname || $tname==="?") {
     $num++;
    } 
    if ($tindex!="?" && $tindex==$num) {
     return true;
    }
    if (@$this->curr_tag['crosssel']==true) {
     break;
    }
   }
  }
  unset($pre_sel['crosssel']);
  if ($tindex==="?") {
   return $num;
  }
  else {
   if ($num==$tindex) {
    return true;
   }
   else {
    return false;
   } 
  }
 }
 /*------------------------------------------------[while loop selection functions]-*/
 function next($anon=true) {
  if ($anon===true) {
   if (isset($this->curr_tag['parent']['children']['?'][$this->curr_tag['anon_index']+1])) {
    $this->curr_tag=&$this->curr_tag['parent']['children']['?'][$this->curr_tag['anon_index']+1];
    return true;
   } 
   else {
    return false;
   }
  }
  else {
   if (isset($this->curr_tag['parent']['children'][$this->curr_tag['name']][$this->curr_tag['index']+1])) {
    $this->curr_tag=&$this->curr_tag['parent']['children'][$this->curr_tag['name']][$this->curr_tag['index']+1];
    return true;
   }
   else {
    return false;
   }
  }
 }
 function prev($anon=true) {
  if ($anon===true) {
   if (isset($this->curr_tag['parent']['children']['?'][$this->curr_tag['anon_index']-1])) {
    $this->curr_tag=&$this->curr_tag['parent']['children']['?'][$this->curr_tag['anon_index']-1];
    return true;
   } 
   else {
    return false;
   }
  }
  else {
   if (isset($this->curr_tag['parent']['children'][$this->curr_tag['name']][$this->curr_tag['index']-1])) {
    $this->curr_tag=&$this->curr_tag['parent']['children'][$this->curr_tag['name']][$this->curr_tag['index']-1];
    return true;
   }
   else {
    return false;
   }
  }
 }
 function end($anon=true) {
  if ($anon===true) {
   $this->curr_tag=&$this->curr_tag['parent']['children']['?'][count($this->curr_tag['parent']['children']['?'])-1];
  }
  else {
   $this->curr_tag=&$this->curr_tag['parent']['children'][$this->curr_tag['name']][count($this->curr_tag['parent']['children'][$this->curr_tag['name']])-1];
  }
 }
 function first_child($anon=true) {
  if ($anon===true) {
   if (isset($this->curr_tag['children']['?'][1])) {
    $this->curr_tag=&$this->curr_tag['children']['?'][1];
    return true;
   }
   else {
    return false;
   }
  }
  else {
   if (isset($this->curr_tag['children'][$anon][1])) {
    $this->curr_tag=&$this->curr_tag['children'][$anon][1];
    return true;
   }
   else {
    return false;
   }
  }
 }
 function last_child($anon=true) {
  if ($anon===true) {
   if (isset($this->curr_tag['children']['?'][count($this->curr_tag['children']['?'])-1])) {
    $this->curr_tag=&$this->curr_tag['children']['?'][count($this->curr_tag['children']['?'])-1];
    return true;
   }
   else {
    return false;
   }
  }
  else {
   if (isset($this->curr_tag['children'][$anon][count($this->curr_tag['children'][$anon])-1])) {
    $this->curr_tag=&$this->curr_tag['children'][$anon][count($this->curr_tag['children'][$anon])-1];
    return true;
   }
   else {
    return false;
   }
  }
 }
 function beg($anon=true) {
  if ($anon===true) {
   $this->curr_tag=&$this->curr_tag['parent']['children']['?'][1];
  }
  else {
   $this->curr_tag=&$this->curr_tag['parent']['children'][$this->curr_tag['name']][1];
  }
 }
 function parent() {
  if (isset($this->curr_tag['parent'])) {
   $this->curr_tag=&$this->curr_tag['parent'];
   return true;
  }
  else {
   return false;
  }
 }
 function get_index($anon=true) {
  if ($anon===true) {
   return $this->curr_tag['anon_index'];
  }
  else {
   return $this->curr_tag['index'];
  }
 }
 function get_tag_name() {
  return $this->curr_tag['name'];
 }
 function is_last($anon=true) {
  if ($anon===true) {
   if ($this->curr_tag['anon_index']==count($this->curr_tag['parent']['children']['?'])-1) {
    return true;
   }
   else {
    return false;
   }
  }
  else {
   if ($this->curr_tag['index']==count($this->curr_tag['parent']['children'][$this->curr_tag['name']])-1) {
    return true;
   }
   else {
    return false;
   }
  }
 }
 function is_first($anon=true) {
  if ($anon===true) {
   if ($this->curr_tag['anon_index']==1) {
    return true;
   }
   else {
    return false;
   }
  }
  else {
   if ($this->curr_tag['index']==1) {
    return true;
   }
   else {
    return false;
   }
  }
 }
/*---------------------------[Cross-level iteration methods]-----------------*/ 
 function cnext($anon=true) {
  if ($anon) {
   $flag=$this->first_child();
   if (!$flag) $this->next();
   if (!$flag) $pre_sel=&$this->curr_tag;
   while (!$flag && !isset($this->curr_tag['parent']['pi'])) {
    $this->parent();
    $flag=$this->next();
   }
   if (!$flag && isset($this->curr_tag['parent']['pi'])) $this->curr_tag=&$pre_sel;
   return $flag;
  }
  else {
   $curr_name=$this->get_tag_name();
   $pre_sel=&$this->curr_tag;
   do {
    $flag=$this->cnext();
   }
   while ($flag && $this->get_tag_name()!=$curr_name);
   if ($this->get_tag_name()!=$curr_name) {
    $this->curr_tag=&$pre_sel;
   }
   return $flag;
  }
 }
 function cprev($anon=true) {
  if ($anon) {
   $flag=$this->last_child();
   if (!$flag) $flag=$this->prev();
   if (!$flag)  $pre_sel=&$this->curr_tag;
   while (!$flag && !isset($this->curr_tag['parent']['pi'])) {
    $this->parent();
    $flag=$this->prev();
   }
   if (!$flag && isset($this->curr_tag['parent']['pi'])) $this->curr_tag=&$pre_sel;
   return $flag;
  }
  else {
   $curr_name=$this->get_tag_name();
   $pre_sel=&$this->curr_tag;
   do {
    $flag=$this->cprev();
   }
   while ($flag && $this->get_tag_name()!=$curr_name);
   if ($this->get_tag_name()!=$curr_name) {
    $this->curr_tag=&$pre_sel;
   }
   return $flag;
  }
 }
 function cbeg($anon=true) {
  $flag=true;
  while ($flag) {
   $flag=$this->cprev($anon);
  }
  return true;
 }
 function cend($anon=true) {
  $flag=true;
  while ($flag) {
   $flag=$this->cnext($anon);
  }
  return true;
 }
 function cis_first($anon=true) {
  $flag=$this->cprev($anon);
  if ($flag) $this->cnext($anon) ;
  return $flag;
 }
 function cis_last($anon=true) {
  $flag=$this->cnext($anon);
  if ($flag) $this->cprev($anon);
  return $flag;
 }
/*------------------------------------------------[front-end functions]------*/
 function get_attribute($tname="",$tindex="",$attr="") {
  if ($attr==="") {
   $attr=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  if (isset($this->curr_tag['attribs'][$attr])) {
   return $this->curr_tag['attribs'][$attr];
  }
  else {
   return false;
  } 
 }
 function get_element_text($tname="",$tindex="") {
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  if (isset($this->curr_tag['text'])) {
   return $this->curr_tag['text'];
  }
  else {
   return false;
  }
 }
 function get_attributes($tname="",$tindex="",$attribs="") {
  if ($attribs==="" && is_array($tname)) {
   $attribs=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  if ($attribs=="") {
   return $this->curr_tag['attribs'];
  }
  else {
   $returned=array();
   foreach ($attribs as $att) {
    $returned[$att]=$this->get_attribute($att);
   }
   return $returned;
  }
 }
 function set_element_text($tname="",$tindex="",$text="") {
  if ($text==="") {
   $text=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $this->curr_tag['text']=$text;
  return true;
 }
 function count_element($tname="",$tindex="") {
  if ($tname==="")  {
   return $this->curr_tag['length'];
  }
  else {
   return $this->select($tname,$tindex);
  }
 }
 function set_attributes($tname="",$tindex="",$atts="") {
  if ($atts===")") {
   $atts=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $karr=array_keys($atts);
  $count=count($karr);
  for ($i=0;$i<$count;$i++) {
   $this->set_attribute($karr[$i],$atts[$karr[$i]]);
  }  
  return true;
 }
 function create_element($tname,$tindex="",$ntname="") {
  if ($tindex==="" && $ntname==="") {
   $ntname=&$tname;
   unset($tname);
   $tname="";
  }
  if ($this->document==null) {
   $this->init_mapper();
   $this->init_tree_dumper();
   $this->init_selection_engine();
  }
  $flag=$this->check_ident($ntname);
  if (!$flag) {
   return false;
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $this->curr_tag['length']++;
  if (!isset($this->curr_tag['children']['?'])) {
   $this->curr_tag['children']['?']=array(0);
  }
  if (!isset($this->curr_tag['children'][$ntname])) {
   $this->curr_tag['children'][$ntname]=array(0);
  }
  $count=count($this->curr_tag['children'][$ntname]);
  $ecount=count($this->curr_tag['children']['?']);
  $this->curr_tag['children'][$ntname][$count]=array();
  $this->curr_tag['children'][$ntname][$count]['name']=$ntname;
  $this->curr_tag['children'][$ntname][$count]['parent']=&$this->curr_tag;
  $this->curr_tag['children'][$ntname][$count]['children']=array();
  $this->curr_tag['children'][$ntname][$count]['attribs']=array();
   $this->curr_tag['children'][$ntname][$count]['index']=$ecount;
  $this->curr_tag['children']['?'][$ecount]=&$this->curr_tag['children'][$ntname][$count];
  unset($this->curr_tag['children']['regs']);
  return true;
 }
 function remove_element($tname="",$tindex="") {
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $this->curr_tag['length']--;
  $anon_index=$this->curr_tag['anon_index'];
  $index=$this->curr_tag['index'];
  $name=$this->curr_tag['name'];
  $this->curr_tag=&$this->curr_tag['parent'];
  unset($this->curr_tag['children']['?'][$anon_index]);
  unset($this->curr_tag['children'][$name][$index]);
  $count=count($this->curr_tag['children'][$name]);
  $ecount=count($this->curr_tag['children']['?']);
  for ($i=$index;$i<$count;$i++) {
   $this->curr_tag['children'][$name][$i]=&$this->curr_tag['children'][$name][$i+1];
   $this->curr_tag['children'][$name][$i]['index']=$i;
  }
  unset($this->curr_tag['children'][$name][$count]);
  unset($count);
  for($i=$anon_index;$i<$ecount;$i++) {
   $this->curr_tag['children']['?'][$i]=&$this->curr_tag['children']['?'][$i+1];
   $this->curr_tag['children']['?'][$i]['anon_index']=$i;
  }
  unset($this->curr_tag['children']['?'][$ecount]);
  unset($ecount);
  unset($this->curr_tag['children']['regs']);
  return true;
 }
 function set_attribute($tname="",$tindex="",$attr="",$val="") {
  if ($attr==="" & $val==="") {
   $val=&$tindex;
   $attr=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->check_ident($attr);
  if (!$flag) {
   return false;
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $this->curr_tag['attribs'][$attr]=$val;
  return true;
 }
 function remove_attribute($tname="",$tindex="",$attr="") {
  if ($attr==="") {
   $attr=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }  
  unset($this->curr_tag['attribs'][$attr]);
  return true;
 }
 function remove_attributes($tname="",$tindex="",$attrs="") {
  if ($attrs==="") {
   $attrs=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $count=count($attrs);
  for ($i=0;$i<$count;$i++) {
   $this->remove_attribute($attrs[$i]);
  }
  return true;
 }
 function check_ident($ntname) {
  if(is_integer(strpos($ntname," "))) {
   return false;
  }
  if(strpos(strtoupper($ntname),"XML")==0 && is_integer(strpos(strtoupper($ntname),"XML"))) {
   return false;
  }
  if(is_integer(strpos($ntname,"?")) || is_integer(strpos($ntname,"/")) ||
  is_integer(strpos($ntname,"&")) || is_integer(strpos($ntname,"~")) ||
  is_integer(strpos($ntname,"!")) || is_integer(strpos($ntname,"@")) ||
  is_integer(strpos($ntname,"#")) || is_integer(strpos($ntname,"$")) ||
  is_integer(strpos($ntname,"%")) || is_integer(strpos($ntname,"^")) ||
  is_integer(strpos($ntname,"*")) || is_integer(strpos($ntname,"(")) ||
  is_integer(strpos($ntname,")")) || is_integer(strpos($ntname,"{")) ||
  is_integer(strpos($ntname,"}")) || is_integer(strpos($ntname,"+")) ||
  is_integer(strpos($ntname,"\\")) || is_integer(strpos($ntname,",")) ||
  is_integer(strpos($ntname,";")) || is_integer(strpos($ntname,"`")) ||
  is_integer(strpos($ntname,"\"")) || is_integer(strpos($ntname,"'"))) {
   return false;
  }
  return true;
 } 
 function htmldecode($text) {
  $text=str_replace("&lt;","<",$text);
  $text=str_replace("&gt;",">",$text);
  $text=str_replace("&amp;","&",$text);
  $text=str_replace("&qout;","\"",$text);
  $text=str_replace("&apos;","'",$text);
  return $text;
 }
 function htmlencode($text,$attr=false) {
  $text=str_replace("&","&amp;",$text);
  $text=str_replace("<","&lt;",$text);
  $text=str_replace(">","&gt;",$text);
  if($attr) {
   $text=str_replace("\"","&quot;",$text);
   $text=str_replace("'","&apos;",$text);
  }
  return $text;
 }
/*--------------------------------------------------[ Collective treatment methods ]--*/
 function xlist($tname,$tindex,$atts,$preserve_index=false,$num=0) {
  if (is_integer($preserve_index) || is_array($preserve_index)) {
   $num=&$preserve_index;
   unset($preserve_index);
   $preserve_index=false;
  }
  $res_arr=array();
  $ecount=0;
  
  $count=$this->select($tname,$tindex);
  $pos=strrpos($tindex,":");
  $tindex=($pos!=0) ? substr($tindex,0,$pos+1) : $tindex;
  unset($pos);

  if ($count==0) {
   return array();
  }
  else {
   
   if (!is_array($num)) {
    if ($num==0) {
     $num=range(1,$count);
    }
    else if ($num<0) {
     $num=range($count+$num+1,$count);
    }
    else if ($num>0) {
     if ($num>$count) {
      $num=$count;
     }
     $num=range(1,$num);
    }
   }
   $num_count=count($num);
   
   for ($i=0;$i<$num_count;$i++) {
    if ($preserve_index) {
     $index=$num[$i];
    }
    else {
     $index=$ecount;
    }
    $res_arr[$index]=array();
    if ($atts==="(ALL)") {
     $res_arr[$index]=$this->get_attributes($tname,$tindex.$num[$i]);
     if ($this->has_text()) {
      $res_arr[$index]['(text)']=&$this->curr_tag['text'];
     }
    }
    else if ($atts==="(ATT)") {
     $res_arr[$index]=$this->get_attributes($tname,$tindex.$num[$i]);
    }
    else {
     $arr_count=count($atts);
     for ($b=0;$b<$arr_count;$b++) {
      if ($atts[$b]=="(text)") {
       $res_arr[$index][$atts[$b]]=$this->get_element_text($tname,$tindex.$num[$i]);
      }
      else {
       $res_arr[$index][$atts[$b]]=$this->get_attribute($tname,$tindex.$num[$i],$atts[$b]);
      }
     }
    }
    $res_arr[$index]['?']=&$this->curr_tag['name'];
    if (!$preserve_index) {
     $res_arr[$index]['-']=$num[$i];
    }
    $ecount++;
   }
  }
  return $res_arr;
 }
 function xsearch($tname,$tindex,$atts,$vals,$num=0,$limit=0) {
  $arr=$this->xlist($tname,$tindex,$atts,$num);
  $curr_tags=array();
  $ecount=0;
  $curr_count=count($arr);
  $atts_count=count($atts);
  $vals_count=count($vals);

  if ($vals_count!=$atts_count) {
   return false;
  }
  
  for ($i=0;$i<$curr_count;$i++) {
   $match=true;
   for ($b=0;$b<$atts_count;$b++) {
    if (!isset($arr[$i][$atts[$b]]) || !preg_match($vals[$b],$arr[$i][$atts[$b]])) {
     $match=false;
     break;
    }
   }
   if ($match==true) {
    $curr_tags[$ecount]=$arr[$i]['-'];
    $ecount++;
    if ($ecount==$limit) {
     break;
    }
   }
  }
  return $curr_tags;
 }
 function xset($tname,$tindex,$atts,$vals,$num=0) {
  $count=$this->count_tag($tname,$tindex);
  $pos=strrpos($tindex,":");
  $tindex=($pos==0) ? $tindex : substr($tindex,0,$pos+1);
  unset($pos);
  
  if (!is_array($num)) {
   if ($num==0) {
    $num=range(1,$count);
   }
   else if ($num<0) {
    $num=range($coun+$num+1,$count);
   }
   else if ($num>0) {
    if ($num>$count) {
     $num=$count;
    }
    $num=range(1,$num);
   }
  }
  $num_count=count($num);
  $atts_count=count($atts);
  $vals_count=count($vals);

  if ($atts_count!=$vals_count) {
   return false;
  }
  
  for ($i=0;$i<$num_count;$i++) {
   for ($b=0;$b<$atts_count;$b++) {
    if ($atts[$b]==="(text)") {
     $this->set_element_text($tname,$tindex.$num[$i],$vals[$b]);
    }
    else {
     $this->set_attribute($tname,$tindex.$num[$i],$atts[$b],$vals[$b]);
    }
   }
  }
  return true;
 }
 function xremove($tname,$tindex,$num=0) {
  $count=$this->count_tag($tname,$tindex);
  $pos=strrpos($tindex,":");
  $tindex=($pos==0) ? $tindex : substr($tindex,0,$pos+1);
  unset($pos);

  if (!is_array($num)) {
   if ($num==0) {
    $num=range(1,$count);
   }
   else if ($num<0) {
    $num=range($count+$num+1,$count);
   }
   else if ($num>0) {
    if ($num>$count) {
     $num=$count;
    }
    $num=range(1,$num);
   }
  }
  $num_count=count($num);
  for ($i=0;$i<$num_count;$i++) {
   $this->remove_element($tname,$tindex.$num[$i]);
   for ($b=$i+1;$b<$num_count;$b++) {
    if ($num[$b]>$num[$i]) {
     $num[$b]--;
    }
   }
  }
  return true;
 }
/*------------------------------------------[Availability checkers (has's)]------------*/
 function has_text($tname="",$tindex="") {
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  if (isset($this->curr_tag['text']) && $this->curr_tag['text']!="") {
   return true;
  }
  else {
   return false;
  }
 }
 function has_attribute($tname="",$tindex="",$attr="") {
  if ($attr==="")  {
   $attr=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  if (isset($this->curr_tag['attribs'][$attr])) {
   return true;
  }
  else {
   return false;
  }
 }
 function has_child($tname="",$tindex="",$child="") {
  if ($child==="")  {
   $child=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  if (isset($this->curr_tag['children'][$child])) {
   if (count($this->curr_tag['children'][$child])>1) {
    return true;
   }
   else {
    return false;
   }
  }
  else {
   return false;
  }
 }
 function has_children($tname="",$tindex="") {
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  if (isset($this->curr_tag['children']['?'])) {
   if (count($this->curr_tag['children']['?'])>1) {
    return true;
   }
   else {
    return false;
   }
  }
  else {
   return false;
  }
 }
 /*------------------------------------------[ wrappers ]--------*/
function get_tag_text($tname="", $tindex="") {
 return $this->get_element_text($tname,$tindex);
}
function set_tag_text($tname="",$tindex="",$text="") {
 if ($text==="") {
  $text=&$tname;
  unset($tname);
  $tname="";
 }
 return $this->set_element_text($tname,$tindex,$text);
}
function create_tag($tname,$tindex="",$ntname="") {
 if ($tindex==="" && $ntname==="") {
  $ntname=&$tname;
  unset($tname);
  $tname="";
 }
 return $this->create_element($tname,$tindex,$ntname);
}
function remove_tag($tname="",$tindex="") {
 return $this->remove_element($tname,$tindex);
}
function dump_current_tag() {
 $this->dump_current_element();
}
function count_tag() {
 if ($tname==="") {
  return $this->curr_tag['length'];
 }
 else {
  return $this->select($tname,$tindex);
 }
}
/*--------------------------------------------*/
}
?>
