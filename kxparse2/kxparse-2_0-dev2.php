<?php
/*
* KXParse/2.0
* Initially started in March 2002 By Khalid Al-Kary
* Version 2.0 was started in 24 April 2003 by Khalid Al-Kary
*-----------[ function listing ]------------
*
*--------------------------------------------------
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
 
 /*--[edition resolver]--*/
 var $add;
 var $edition;
 
 /*----[io handling]--*/
 var $file;
 var $swap;
 var $read_only;
 var $swap_file;
   
/*--------------------------------------------------[the constructor]-----*/
 function kxparse($file=false,$options="") {
  if ($options=="" && preg_match("/.{1,}=.{1,};{0,}/",$file)==1) {
   $options=$file;
   $file=false;
  }
  $this->reset_ops();
  if ($options!=";") {
   $this->set_ops($options);
  }
  $this->check_ops();
  $this->file=$file;
  if ($file!=false) {
   $flag=$this->init_io();
   if (!$flag) {
    return false;
   }
   $this->init_mapper();
   $this->init_edition_resolver();
   $this->init_tree_dumper();
   $this->init_selection_engine();
  } 
 }
/*------------------------------------------[IO handling functions]---------*/
 function load($file=false) {
  if ($this->swap && $this->xml=="") {
   $this->io_deswap();
  }
  $tmp_file=$this->file;
  if ($file!=false) {
   $this->file=$file;
  }
  if ($this->file!=false) {
   $flag=$this->init_io();
   if (!$flag) {
    $this->file=$tmp_file;
    if ($this->swap && $this->xml!="") {
     $this->io_swap;
    } 
    return false;
   }
   $this->init_mapper();
   $this->init_edition_resolver();
   $this->init_tree_dumper();
   $this->init_selection_engine();
   return true;
  }
  else {
   return false;
  }
 }
 function save($file=false) {
  if ($this->read_only) {
   return false;
  }
  $this->dump_editions();
  if ($file==false) {
   $file=$this->file;
  }
  if (!$file) {
   return false;
  }
  $flag=$this->io_put_content($file, $this->xml);
  if ($flag==false) {
   return false;
  }
  return true;
 }
 function load_string($str) {
  if ($this->swap && $this->xml=="") {
   $this->io_deswap();
  }
  $this->xml=$str;
  $this->file=false;
  $this->init_mapper();
  $this->init_edition_resolver();
  $this->init_tree_dumper();
  $this->init_selection_engine();
  return true;
 }
 function init_io() {
  $this->xml=$this->io_get_content($this->file);
  if ($this->xml===false) {
   return false;
  }
  else {
   return true;
  }
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
 function io_swap() {
  if ($this->xml!="") {
   $this->swap_file=($this->file=="") ? "xmlcontent.xml" : $this->file;
   $filename=$this->io_gen_filename(".".$this->swap_file.".swp"); 
   $this->io_put_content($filename, $this->xml);
   $this->xml="";
   $this->swap_file=$filename;
   return true;
  }
  else {
   return false;
  }
 }
 function io_deswap() {
  $this->xml=$this->io_get_content($this->swap_file);
  if ($this->xml==false) {
   return false;
  }
  unlink($this->swap_file);
  return true;
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
  if ($this->read_only) {
   $this->dump_tree();
   $var=$this->dump_temp;
   $this->dump_temp="";
   return $var;
  }
  $this->dump_editions();
  $val=$this->xml;
  if ($this->swap && $reswap==true) {
   $this->io_swap();
  }
  return $val;  
 }
 function io_empty_content() {
  if ($this->swap && $this->swap_file!="") {
   $this->io_deswap();
  }
  $this->xml="";
 }
 function get_file_name() {
  return $this->file;
 }
/*------------------------------------------[ xml tree mapper (expat-based) ]-*/
 function init_mapper() {
  $this->document['pi']=array();
  $this->document['children']=array(0);
  $this->init_selection_engine();
  $this->xml_parser=xml_parser_create();
  xml_set_object($this->xml_parser,$this);
  xml_set_element_handler($this->xml_parser,"start_element_handler","end_element_handler");
  xml_set_character_data_handler($this->xml_parser,"cdata_handler"); 
  xml_set_processing_instruction_handler($this->xml_parser,"pi_handler");
  xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, 0);
  $this->parse();
 }
 function start_element_handler($parser, $name, $attribs) {
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
   $this->curr_tag['children'][$name][$last_child]['internal']=0;
   $this->curr_tag['children'][$name][$last_child]['name']=$name;
   $this->curr_tag['children'][$name][$last_child]['start']=xml_get_current_byte_index($this->xml_parser);
   $this->curr_tag['children'][$name][$last_child]['anon_index']=$anon_last_child;
   $this->curr_tag['children'][$name][$last_child]['index']=$last_child;
   $this->curr_tag['children']['?'][$anon_last_child]=&$this->curr_tag['children'][$name][$last_child];
   $this->curr_tag=&$this->curr_tag['children'][$name][$last_child];
 }
 function end_element_handler($parser, $name) {
  if (substr($this->xml, xml_get_current_byte_index($this->xml_parser),strlen($this->curr_tag['name'])+2)==="</".$this->curr_tag['name']) {
   $this->curr_tag['internal']=0;
  }
  else {
   $this->curr_tag['internal']=1;
  }
  if ($this->curr_tag['internal']==1) {
   $this->curr_tag['end']=xml_get_current_byte_index($this->xml_parser)+1;
  }
  else {
   $this->curr_tag['end']=xml_get_current_byte_index($this->xml_parser)+strlen($name)+3;
  }
  $this->curr_tag=&$this->curr_tag['parent'];
 }
 function cdata_handler($parser, $data) {
  $this->curr_tag['text'].=$data;
 }
 function pi_handler($parser, $target, $data) {
  $this->document['pi'][$target]=$data;
 }
 function parse() {
  xml_parse($this->xml_parser,$this->xml);
  if ($this->swap) {
   $this->io_swap();
  } 
  if ($this->read_only) {
   $this->io_empty_content();
  }
  $this->init_selection_engine();
 }
/*------------------------------------------[tree dumper]-----------------------*/
 function init_tree_dumper() {
  $this->dump_temp="";
  $this->dump_pos=0;
 }
 function dump_tree() {
  $this->init_tree_dumper();
  $this->curr_tag=&$this->document;

  $karr=array_keys($this->document['pi']);
  $ecount=count($karr);
  
  for ($i=0; $i<$ecount; $i++) {
   $this->dump_temp.="<?".$karr[$i]." ".$this->htmlencode($this->document['pi'][$karr[$i]], true)."?>";
   $this->dump_pos+=strlen($karr[$i])+strlen($this->htmlencode($this->document['pi'][$karr[$i]], true))+5;
  }
  unset($karr);
  unset($ecount);
  
  $sec_curr_tag=&$this->curr_tag;
  $this->curr_tag=&$this->document['children']['?'][1];
  $this->dump_current_tag();
  $this->curr_tag=&$sec_curr_tag;
 }
 function dump_current_tag() {
  if ($this->dump_pos<strlen($this->dump_temp)) {
   if ($this->curr_tag['internal']==0) {
    $this->dump_temp=substr_replace($this->dump_temp,"<".$this->curr_tag['name']."></".$this->curr_tag['name'].">",$this->dump_pos,0);
   }
   else {
    $this->dump_temp=substr_replace($this->dump_temp,"<".$this->curr_tag['name']."/>",$this->dump_pos,0);   
   } 
  }
  else {
   if ($this->curr_tag['internal']==0) {
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
   if ($this->curr_tag['internal']==1) {
    $this->dump_pos+=2;
   }
  }
  else {
   if ($this->curr_tag['internal']==0) {
    $this->dump_pos+=strlen($this->curr_tag['name'])+1;
   }
   else {
    $this->dump_pos+=strlen($this->curr_tag['name'])+3;
   } 
  }
  if ($this->curr_tag['internal']==1) {
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
  $this->curr_tag=&$this->document;
 }
 function select($tname,$tindex) {
  if ($tname==="") {
   return true;
  }
  
  $name_arr=$this->explode_collect(":",$tname);
  $index_arr=explode(":",$tindex);
  $reg_array=array();
  
  
  if ($name_arr[0]!=="-" && $index_arr[0]!=="-") {
   $this->init_selection_engine();
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
      return false;
     }
    }
    if (isset($reg_arr[$index_arr[$i]])) {
     $this->curr_tag=&$reg_arr[$index_arr[$i]];
    }
    else {
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
      return false;
     }
    }
    if (isset($this->curr_tag['children'][$name_arr[$i]][$index_arr[$i]])) {
     $this->curr_tag=&$this->curr_tag['children'][$name_arr[$i]][$index_arr[$i]];
    }
    else {
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
 function get_tag_text($tname="",$tindex="") {
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
 function get_attributes($tname="",$tindex="") {
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  return $this->curr_tag['attribs'];
 }
 function set_tag_text($tname="",$tindex="",$text="") {
  if ($text==="") {
   $text=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $count=count($this->edition);
  $this->edition[$count]=array(0 => 3, 1 => $this->curr_tag['start'], 2 => $this->curr_tag['end'], 3 => &$this->curr_tag);
  $this->curr_tag['text']=$text;
  $this->curr_tag['internal']=0;
  return true;
 }
 function count_tag($tname="",$tindex="") {
  if ($tname==="")  {
   return count($this->curr_tag['children']['?']);
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
 function create_tag($tname,$tindex="",$ntname="") {
  if ($tindex==="" && $ntname==="") {
   $ntname=&$tname;
   unset($tname);
   $tname="";
  }
  $flag=$this->check_ident($ntname);
  if (!$flag) {
   return false;
  }
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $start_pos=0;

  if ($this->curr_tag['internal']==0) {
   $start_pos=$this->curr_tag['end']-strlen($this->curr_tag['name'])-3;
  }
  else if ($this->curr_tag['internal']==1) {
   $start_pos=$this->curr_tag['end']-1;
  }
  $end_pos=$start_pos+strlen($ntname)+3;
  
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
  $this->curr_tag['children'][$ntname][$count]['internal']=1;
  $this->curr_tag['children'][$ntname][$count]['children']=array();
  $this->curr_tag['children'][$ntname][$count]['attribs']=array();
  $this->curr_tag['children'][$ntname][$count]['start']=$start_pos;
  $this->curr_tag['children'][$ntname][$count]['end']=$end_pos;
  $this->curr_tag['children'][$ntname][$count]['index']=$ecount;
  $this->curr_tag['children']['?'][$ecount]=&$this->curr_tag['children'][$ntname][$count];
  unset($this->curr_tag['children']['regs']);
  $count=count($this->edition);
  $this->edition[$count]=array(0 => 2, 1 => $ntname, 2 => $this->curr_tag['internal'], 3 => $start_pos);
  $this->curr_tag['internal']=0;
  return true;
 }
 function remove_tag($tname="",$tindex="") {
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  $anon_index=$this->curr_tag['anon_index'];
  $index=$this->curr_tag['index'];
  $name=$this->curr_tag['name'];
  $count=count($this->edition);
  $this->edition[$count]=array(0 => 1, 1 => $this->curr_tag['start'], 2 => $this->curr_tag['end']);
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
  $count=count($this->edition);
  $this->edition[$count]=array(0 => 0, 1 => $attr, 2 => $this->htmlencode($val, true), 3 => $this->curr_tag['start'], 4 => strlen($this->htmlencode($this->curr_tag['attribs'][$attr])), 5 => $this->curr_tag['name']);
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
  $count=count($this->edition);
  $this->edition[$count]=array(0 => 4 , 1 => $attr , 2 => $this->curr_tag['start'] , 3 => strlen($this->curr_tag['attribs'][$attr]));
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
 function to_internal_close($tname="",$tindex="") {
  $flag=$this->select($tname,$tindex);
  if (!$flag) {
   return false;
  }
  if ((!isset($this->curr_tag['text']) || $this->curr_tag['text']=="") && count($this->curr_tag['children']['?'])==1 && $this->curr_tag['internal']==0) {
   $this->curr_tag['internal']=1;
   $count=count($this->edition);
   $this->edition[$count]=array(0 => 5, 1 => strlen($this->curr_tag['name']), 2 => $this->curr_tag['end']);
   return true;
  }
  else {
   return false;
  }  
 }
 function create_CD() {
  if(is_integer(strpos($text,"<![CDATA[")) || is_integer(strpos($text,"]]>"))) {
   return false;
  }
  return "<![CDATA[".$text."]]>";
 }
 function set_pi($attribute, $value) {  
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
 /*-------------------------------[ old tag listing and searching ]---------------*/
function list_tags($tname,$tindex,$num=-1) {
 $count=$this->count_tag($tname,$tindex);
 if(!is_array($num)) {
  if($num==-1) {
   $num=$count;
  }
  elseif($num>$count) {
   $num=$count;
  }
  $enum=$num;
  $num=array();
  for($j=0;$j<$enum;$j++) {
   $num[$j]=$j+1;
  }
 }
 $res=array();
 for($i=0;$i<count($num);$i++) {
  if($num[$i]>$count) {
   continue;
  }
  $res[$i+1]=$this->get_tag_text($tname,str_replace("?",$num[$i],$tindex));
 }
 return (count($res)>0) ? $res : false;
}

function list_attributes($tname,$tindex,$atts,$num=-1) {
    if($atts=="(ALL)") {
        $count=$this->count_tag($tname,$tindex);
        
        if(!is_array($num)) {
            if($num==-1) {
                $num=$count;
            } elseif($num>$count) {
                $num=$count;
            }

            $enum=$num;
            $num=array();

            for($j=0;$j<$enum;$j++) {
                $num[$j]=$j+1;
            }
        }

        $res=array();
        $ecount=count($num);
        
        for($i=0;$i<$ecount;$i++) {
            if($num[$i]>$count) {
                continue;
            }

           
            $res[$i+1]=$this->get_attributes($tname,str_replace("?",$num[$i],$tindex));
            $res[$i+1]["-"]=$num[$i];
        }
        $returned=&$res;
        return $returned;
    } else {
        $atts=explode(",",$atts);
        $atcount=count($atts);

        $count=$this->count_tag($tname,$tindex);

        if(!is_array($num)) {
            if($num==-1) {
                $num=$count;
            } elseif($num>$count) {
                $num=$count;
            }

            $enum=$num;
            $num=array();

            for($j=0;$j<$enum;$j++) {
                $num[$j]=$j+1;
            }
        }

        $res=array();
        $ecount=count($num);
        
        for($i=0;$i<$ecount;$i++) {
            if($num[$i]>$count) {
                continue;
            }

            $res[$i+1]=array();

            for($b=0;$b<$atcount;$b++) {
                $val=$this->get_attribute($tname,str_replace("?",$num[$i],$tindex),$atts[$b]);

                if($val) {
                    $res[$i+1][$atts[$b]]=$val;
                    $numatt++;
                }
            }

            $res[$i+1]["-"]=$num[$i];
        }
        $returned=&$res;
        return (count($res)>0) ? $returned : false;
    }
}
function search_attributes($tname,$tindex,$attributes,$values,$num=-1) {
    if(is_array($attributes)) {
        if(!is_array($values)) {
            die("Error: Kxparse: search_attributes: search attributes differ from the search values in type");
        }

        if(count($attributes)!=count($values)) {
            die("Error: Kxparse: search_attributes: the count of the search attributes differs from the count of the search values");
        }
    }

    if(is_string($attributes)) {
        if(!is_string($values)) {
            die("Error: Kxparse: search_attributes: search attributes differ from the search values in type");
        }

        $val=$attributes;
        $attributes=array($val);
        $val=$values;
        $values=array($val);
    }

    $attlist=implode(",",$attributes);
    $res=$this->list_attributes($tname,$tindex,$attlist,$num);

    $last=array();

    for($i=0;$i<count($attributes);$i++) {
        if(count($last)>0) {
            $res=$this->list_attributes($tname,$tindex,$attlist,$last);
        } else {
            if($i>0) {
                break;
            }
        }

        $last=array();

        $ecount=0;

        for($b=1;$b<=count($res);$b++) {
            if($res[$b][$attributes[$i]]==$values[$i]) {
                $last[$ecount]=$res[$b]["-"];
                $ecount++;
    }
   }
  }
  return(count($last)>0) ? $last : false; 
 }
 function isearch_attributes($tname,$tindex,$attributes,$values,$num=-1) {
    if(is_array($attributes)) {
     if(!is_array($values)) {
      die("Error: Kxparse: search_attributes: search attributes differ from the search values in type");
     }
    if(count($attributes)!=count($values)) {
     die("Error: Kxparse: search_attributes: the count of the search attributes differs from the count of the search values");
    }
   }
   if(is_string($attributes)) {
    if(!is_string($values)) {
     die("Error: Kxparse: search_attributes: search attributes differ from the search values in type");
    }
    $val=$attributes;
    $attributes=array($val);
    $val=$values;
    $values=array($val);
   }
   $attlist=implode(",",$attributes);
   $res=$this->list_attributes($tname,$tindex,$attlist,$num);
   $last=array();
   for($i=0;$i<count($attributes);$i++) {
    if(count($last)>0) {
     $res=$this->list_attributes($tname,$tindex,$attlist,$last);
    } 
    else {
      if($i>0) {
       break;
      }
     }
     $last=array();
     $ecount=0;
     for($b=1;$b<=count($res);$b++) {
      if(strtoupper($res[$b][$attributes[$i]])==strtoupper($values[$i])) {
       $last[$ecount]=$res[$b]["-"];
       $ecount++;
      }
     }
    }
 return(count($last)>0) ? $last : false;
}
function search_tags($tagname,$tagindex,$text) {
    $count=$this->count_tag($tagname,$tagindex);
    $arr=array();
    $vals=0;
    for($i=1;$i<=$count;$i++) {
     $currtagindex=str_replace("?",$i,$tagindex);
     if(trim($this->get_tag_text($tagname,$currtagindex))==trim($text)) {
     $arr[$vals++]=$i;
    }
   }
   if(count($arr)==0) {
    return false;
   }
   else {
    return $arr;
   }
}
function isearch_tags($tagname,$tagindex,$text) {
    $count=$this->count_tag($tagname,$tagindex);
    $arr=array();
    $vals=0;
    for($i=1;$i<=$count;$i++) {
     $currtagindex=str_replace("?",$i,$tagindex);
     if(strtoupper($this->get_tag_text($tagname,$currtagindex))==strtoupper($text)) {
      $arr[$vals++]=$i;
     }
    }
    if(count($arr)==0) {
     return false;
    } 
    else {
     return $arr;
    }
}
/*--------------------------------------------------[ New tag searching and listing]--*/
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
       $res_arr[$index][$atts[$b]]=$this->get_tag_text($tname,$tindex.$num[$i]);
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
 function xsearch($tname,$tindex,$atts,$vals,$num=0) {
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
     $this->set_tag_text($tname,$tindex.$num[$i],$vals[$b]);
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
   $this->remove_tag($tname,$tindex.$num[$i]);
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
/*------------------------------------------[Edition resolver functions]---------*/
/*
* 0 dump_att
* 1 dump_remove
* 2 dump_add
* 3 dump_text
* 4 dump_remove_att
* 5 dump_to_internal
* ---------
* $this->add:
* [-] => [1] => addition value
*     => [0] => consideratoin index
*/
 function init_edition_resolver() {
  $this->add=array();
  $this->edition=array();
 }
 function reg_edition($arr) {
  $count=count($this->edition);
  $this->edition[$count]=$arr;
 }
 function dump_editions() {
  $count=count($this->edition);
  if ($this->swap && $this->xml==="") {
   $this->io_deswap();
  }

  for ($i=0;$i<$count;$i++) {
   if ($this->edition[$i][0]==0) {
    $this->dump_att($i);
   }
   else if ($this->edition[$i][0]==1) {
    $this->dump_remove($i);
   }
   else if ($this->edition[$i][0]==2) {
    $this->dump_add($i);
   }
   else if ($this->edition[$i][0]==3) {
    $this->dump_text($i);
   }
   else if ($this->edition[$i][0]==4) {
    $this->dump_remove_att($i);
   }
   else if ($this->edition[$i][0]==5) {
    $this->dump_to_internal($i);
   }
  }
  $this->init_edition_resolver();
 }
 /*
 * 0 = type (1)
 * 1 = removal start index
 * 2 = removal end index
 */
 function dump_remove($num) {
  $count=count($this->add);
  for ($i=0;$i<$count;$i++) {
   if ($this->edition[$num][1]>$this->add[$i][0]) {
    $this->edition[$num][1]+=$this->add[$i][1];
   }
   if ($this->edition[$num][2]>$this->add[$i][0]) {
    $this->edition[$num][2]+=$this->add[$i][1];
   } 
  }
  
  $this->xml=substr_replace($this->xml,"",$this->edition[$num][1],$this->edition[$num][2]-$this->edition[$num][1]);
  $this->add[$count]=array(0 => $this->edition[$num][2], 1 => $this->edition[$num][1]-$this->edition[$num][2]);
 }
 /*
 * 0 = type (2)
 * 1 = new tag name
 * 2 = parent tag internal or not
 * 3 = the insertion index
 */
 function dump_add($num) {
  $count=count($this->add);
  for ($i=0;$i<$count;$i++) {
   if ($this->edition[$num][3]>$this->add[$i][0]) {
    $this->edition[$num][3]+=$this->add[$i][1];
   }
  }
  if ($this->edition[$num][2]==1) {
   $this->xml=substr_replace($this->xml,"></change>",$this->edition[$num][3]-2,2);
   $this->edition[$num][3]-=1;
  }
  $this->xml=substr_replace($this->xml,"<".$this->edition[$num][1]."/>",$this->edition[$num][3],0);
  $this->add[$count]=array(0 => $this->edition[$num][3], 1 => strlen($this->edition[$num][1])+3);
 }
 /*
 * 0 = type (0)
 * 1 = attrribute name
 * 2 = new value
 * 3 = tag index
 * 4 = current value's length
 * 5 = tag name
 */
 function dump_att($num) {
  $count=count($this->add);
  for ($i=0;$i<$count;$i++) {
   if ($this->edition[$num][3]>$this->add[$i][0]) {
    $this->edition[$num][3]+=$this->add[$i][1];
   }
  }
  $pos1=strpos($this->xml," ".$this->edition[$num][1]."=\"",$this->edition[$num][3]);
  $pos2=strpos($this->xml," ".$this->edition[$num][1]."='",$this->edition[$num][3]);
  $pos3=strpos($this->xml,">",$this->edition[$num][3]);
  $diff=0;
  
  if ($pos1>$pos3) {
   $pos1=false;
  }
  if ($pos2>$pos3) {
   $pos2=false;
  }
  
  if(!is_integer($pos1) && !is_integer($pos2)) {
   $pos=false;
  }
  else {
   $pos=(is_integer($pos1)) ? $pos1 : $pos2;
   if(is_integer($pos2)) {
    $single=true;
   }
   else {
    $single=false;
   }
  }
  if(is_integer($pos)) {
   $cut=($single) ? "='" : "=\"";
   $my_attribute_pos=strpos($this->xml," ".$this->edition[$num][1].$cut,$this->edition[$num][3])+1;
   $my_attribute_length=strlen($this->edition[$num][1]);
   $this->xml=substr_replace($this->xml,$this->edition[$num][2],$my_attribute_pos+$my_attribute_length+2,$this->edition[$num][4]);
   $diff=strlen($this->edition[$num][2])-$this->edition[$num][4];
  }
  else {
   $newattr=" ".$this->edition[$num][1]."=\"".$this->edition[$num][2]."\"";
   $this->xml=substr_replace($this->xml,$newattr,$this->edition[$num][3]+strlen($this->edition[$num][5])+1,0);
   $diff=strlen($newattr);
   }
  $this->add[$count]=array(0 => (is_integer($pos)) ? $pos : $this->edition[$num][3]+strlen($this->edition[$num][5])+1, 1 => $diff);
 }
 /*
 * 0 = type (4)
 * 1 = attribute name
 * 2 = tag index
 * 3 = length of current attribute value
 */
 function dump_remove_att($num) {
  $count=count($this->add);
  for ($i=0;$i<$count;$i++) {
   if ($this->edition[$num][2]>$this->add[$i][0]) {
    $this->edition[$num][2]+=$this->add[$i][0];
   }
  }
  $start=strpos($this->xml," ".$this->edition[$num][1]."=",$this->edition[$num][2]);
  $this->xml=substr_replace($this->xml,"",$start,strlen($this->edition[$num][1])+$this->edition[$num][3]+3);
  $this->add[$count]=array(0 => $start, 1 => 0-(strlen($this->edition[$num][1])+3+$this->edition[$num][3]));
 }
 /*
 * 0 = type (5)
 * 1 = tag name's length
 * 2 = tag end
 */
 function dump_to_internal($num) {
  $count=count($this->add);
  for ($i=0;$i<$count;$i++) {
   if ($this->edition[$num][2]>$this->add[$i][0]) {
    $this->edition[$num][2]+=$this->add[$i][1];
   }
  }
  $this->xml=substr_replace($this->xml,"/>",$this->edition[$num][2]-$this->edition[$num][1]-4,$this->edition[$num][1]+4);
  $this->add[$count]=array(0 => $this->edition[$num][2], 1 => 0-($this->edition[$num][1]+2));
 }
 /*
 * 0 = type (3)
 * 1 = tag index
 * 2 = tag end
 * 3 = pointer to the tag's array element
 */
 function dump_text($num) {
  $count=count($this->add);
  for ($i=0;$i<$count;$i++) {
   if ($this->edition[$num][1]>$this->add[$i][0]) {
    $this->edition[$num][1]+=$this->add[$i][1];
   }
   if ($this->edition[$num][2]>$this->add[$i][0]) {
    $this->edition[$num][2]+=$this->add[$i][1];
   }
  }

  $this->init_tree_dumper();
  $this->curr_tag=&$this->edition[$num][3];
  $this->dump_current_tag();
  $this->xml=substr_replace($this->xml,$this->dump_temp,$this->edition[$num][1],$this->edition[$num][2]-$this->edition[$num][1]);
  $this->add[$count]=array(0 => strpos($this->xml,">",$this->edition[$num][1]), 1 => strlen($this->dump_temp)-($this->edition[$num][2]-$this->edition[$num][1]));
  $this->init_tree_dumper();
 }
/*-------------------------------------[Options controling functions]--*/
 function set_ops($ops) {
  $ops_arr=explode(";",$ops);
  $i=0;
  $ecount=count($ops_arr);
  while ($i<$ecount) {
   $arr=explode("=",$ops_arr[$i]);
   if (strtolower($arr[1])=="true") {
    $arr[1]=true;
   }
   else if (strtolower($arr[1])=="false") {
    $arr[1]=false;
   }
   $this->{$arr[0]}=$arr[1];
   unset($arr);
   $i++;
  }
 }
 function reset_ops() {
  $this->swap=false;
  $this->read_only=false;
 }
 function check_ops() {
  if ($this->swap==true && $this->swap==$this->read_only) {
   trigger_error("<b>KXParse</b>: <b>check_ops</b>: swapping and the read-only flags cannot be both set to true",E_USER_ERROR);
  }
 }
 function set_ns($val) {
 }
 function set_CD($val) {
 }
 function set_cache($val) {
 }
 function set_iarea($val) {
 }
 function reset_cursors() {
 }
/*---------------------------------------------------------------------*/
}
?>
