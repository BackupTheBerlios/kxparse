<?php
/*
* KXParse/2.0
* Initially started in March 2002 By Khalid Al-Kary
* Version 2.0 was started in 24 April 2003 by Khalid Al-Kary
*-----------[ function listing ]------------
*
*--------------------------------------------------
* Note: for modularity and extensibilty reasons the code below
* is made in parts separated by commented hyphen lines tell-
* ing each part's name. The class constructor calls the constr-
* uctor of each part for the same reason.
*
* Note: It was found that no other part of the parser 
* except the xml tree mapper (expat-based) needed 
* instance variables, so they aren't made in parts
* like methods are ...
*
* TODO:
* Implement the no $tname way in case it was already selected (done)
* Implement next and prev and eol bol and beg and end (done)
* next and prev return false :-) (done)
* try to consider regs within next and prev (cancelled)
* has_text, has_child, has_attribute and has_children (done)
* implement the function xlist and xsearch... with the option of preserving keys as tag indexes! (done)
* add the ? and - array elements to the result of xlist (done)
* xsearch should be able to search with regexps (done)
* Implement tag listing and searching in the new way ;-) (done)
* Implement Ops and make general consideration for error reporting places (done)
* implement relative tag selection "-:" (done)
* check it for stability plz (done)
* implement the edition resolver and make the file swappable
* xset() (done) and xremove() (done)
* consider regs generally, remove regs cache ? or find a way to make them changes-safe
* for xsearch make the default to be non regexps and devise a fast way of  considering regexps (cancelled--not useful)
* check the old API for functions
* Implement Persistent Caching!!
* port the old options so old scripts don't crash
*/

class kxparse {
 var $document;
 var $curr_tag;
 var $xml;
 var $xml_parser;
 var $dump_pos;
 var $file;
 
/*--------------------------------------------------[the constructor]-----*/
 function kxparse($file=false) {
  $this->load($file);
  $this->init_mapper();  
 }
/*------------------------------------------[IO handling functions]---------*/
 function load($xmlfile) {
  if($xmlfile!==false) {
   $file=fopen($xmlfile,"r");
   if(!$file) {
    trigger_error("<b>KXParse</b>: <b>load</b>: Error opening file!",E_USER_ERROR);
   }
     
   while(!feof($file)) {
   $this->xml.=fread($file,4096);
   }
   fclose($file);
  }
  $this->file=$xmlfile;
 }
 function save($file=false) {
  if ($file===false) {
   $file=$this->file;
  }
  if ($file===false) {
   trigger_error("<b>KXParse</b>: <b>save</b>: No file currently loaded you should supply a file name",E_USER_ERROR);
  }
  $my_file=fopen($file,"wb");
  $my_status=fwrite($my_file,$this->xml);
  fclose($my_file);
   if($my_status!=-1) {
  return true;
  }
  else {
   return false;
  }
 }
/*------------------------------------------[ xml tree mapper (expat-based) ]-*/
 function init_mapper() {
  $this->document['pi']=array();
  $this->document['children']=array(0);
  $this->curr_tag=&$this->document;
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
   $this->curr_tag['children'][$name][$last_child]['anon_index']=$anon_last_child;
   $this->curr_tag['children'][$name][$last_child]['index']=$last_child;
   $this->curr_tag['children']['?'][$anon_last_child]=&$this->curr_tag['children'][$name][$last_child];
   $this->curr_tag=&$this->curr_tag['children'][$name][$last_child];
 }
 function end_element_handler($parser, $name) {
  if (substr($this->xml, xml_get_current_byte_index($this->xml_parser)-2,1)=="/") {
   $this->curr_tag['internal']=1;
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
  $this->xml="";
  $this->curr_tag=&$this->document;
 }
/*------------------------------------------[tree dumper]-----------------------*/
function dump_tree() {
  $this->xml="";
  $this->dump_pos=0;
  $this->curr_tag=&$this->document;

  $karr=array_keys($this->document['pi']);
  $ecount=count($karr);
  
  for ($i=0; $i<$ecount; $i++) {
   $this->xml.="<?".$karr[$i]." ".$this->document['pi'][$karr[$i]]."?>";
   $this->dump_pos+=strlen($karr[$i])+strlen($this->document['pi'][$karr[$i]])+5;
  }
  unset($karr);
  unset($ecount);
  
  $sec_curr_tag=&$this->curr_tag;
  $this->curr_tag=&$this->document['children']['?'][1];
  $this->dump_current_tag();
  $this->curr_tag=&$sec_curr_tag;
 }
 function dump_current_tag() {
  if ($this->dump_pos<strlen($this->xml)) {
   if ($this->curr_tag['internal']==0) {
    $this->xml=substr_replace($this->xml,"<".$this->curr_tag['name']."></".$this->curr_tag['name'].">",$this->dump_pos,0);
   }
   else {
    $this->xml=substr_replace($this->xml,"<".$this->curr_tag['name']."/>",$this->dump_pos,0);   
   } 
  }
  else {
   if ($this->curr_tag['internal']==0) {
    $this->xml.="<".$this->curr_tag['name']."></".$this->curr_tag['name'].">";
   }
   else {
    $this->xml.="<".$this->curr_tag['name']."/>";
   } 
  }
  if (count($this->curr_tag['attribs'])>0) {
   $this->dump_pos+=strlen($this->curr_tag['name'])+1;
   $karr=array_keys($this->curr_tag['attribs']);
   $ecount=count($karr);
   
   for ($i=0;$i<$ecount;$i++) {
    $this->xml=substr_replace($this->xml," ",$this->dump_pos++,0);
    $this->xml=substr_replace($this->xml,$karr[$i]."=\"".$this->curr_tag['attribs'][$karr[$i]]."\"",$this->dump_pos,0);
    $this->dump_pos+=strlen($karr[$i])+strlen($this->curr_tag['attribs'][$karr[$i]])+3;
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
     $this->xml=substr_replace($this->xml,$this->curr_tag['text'],$this->dump_pos,0);
     $this->dump_pos+=strlen($this->curr_tag['text']);     
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
/*------------------------------------------------[the selection engine]--*/
 function select($tname,$tindex) {
  if ($tname==="") {
   return;
  }
  
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
  
  if ($name_arr[0]==="" || $index_arr[0]==="") {
   return;
  }

  if ($name_count!=$index_count) {
   trigger_error("<b>KXParse</b>: <b>select</b>: Malformed selection string and/or selection index",E_USER_ERROR);
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
      $index_arr[$i]=1;
     }
    }
    $this->curr_tag=&$reg_arr[$index_arr[$i]];
   }
   else {
    if ($index_arr[$i]==="?") {
     return count($this->curr_tag['children'][$name_arr[$i]])-1;
    }
    else if ($index_arr[$i]<1) {
     $children_count=count($this->curr_tag['children'][$name_arr[$i]]);
     $index_arr[$i]=$children_count+$index_arr[$i];
     
     if ($index_arr[$i]<1) {
      $index_arr[$i]=1;
     }
    } 
    $this->curr_tag=&$this->curr_tag['children'][$name_arr[$i]][$index_arr[$i]];
   }
  }
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
  if ($anon==true) {
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
  if ($anon==true) {
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
  if ($anon==true) {
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
  if ($anon==true) {
   $this->curr_tag=&$this->curr_tag['parent']['children']['?'][1];
  }
  else {
   $this->curr_tag=&$this->curr_tag['parent']['children'][$this->curr_tag['name']][1];
  }
 }
/*------------------------------------------------[front-end functions]------*/
 function get_attribute($tname="",$tindex="",$attr="") {
  if ($attr==="") {
   $attr=&$tname;
   unset($tname);
   $tname="";
  }
 
  $this->select($tname,$tindex);
  $ret=&$this->curr_tag['attribs'][$attr];
  return $ret;
 }
 function get_tag_text($tname="",$tindex="") {
  $this->select($tname,$tindex);
  $ret=&$this->curr_tag['text'];
  return $ret;
 }
 function get_attributes($tname="",$tindex="") {
  $this->select($tname,$tindex);
  $ret=&$this->curr_tag['attribs'];
  return $ret;
 }
 function set_tag_text($tname="",$tindex="",$text="") {
  if ($text==="") {
   $text=&$tname;
   unset($tname);
   $tname="";
  }
  
  $this->select($tname,$tindex);
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
  $this->select($tname,$tindex);
  $this->curr_tag['attribs']=array_merge($this->curr_tag['attribs'],$atts);
  return true;
 }
 function create_tag($tname,$tindex="",$ntname="") {
  if ($tname==="(c)") {
   $ntname=&$tindex;
  }
  $this->select($tname,$tindex);
  $this->curr_tag['internal']=0;
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
  $this->curr_tag['children'][$ntname][$count]['index']=$ecount;
  $this->curr_tag['children']['?'][$ecount]=&$this->curr_tag['children'][$ntname][$count];
  unset($this->curr_tag['children']['regs']);
  return true;
 }
 function remove_tag($tname="",$tindex="") {
  $this->select($tname,$tindex);
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
  $this->select($tname,$tindex);
  $this->curr_tag['attribs'][$attr]=$val;
  return true;
 }
 function remove_attribute($tname="",$tindex="",$attr="") {
  if ($attr==="") {
   $attr=&$tname;
   unset($tname);
   $tname="";
  }
  $this->select($tname,$tindex);
  unset($this->curr_tag['attribs'][$attr]);
  return true;
 }
 function to_internal_close($tname="",$tindex="") {
  $this->select($tname,$tindex);
  if ((!isset($this->curr_tag['text']) || $this->curr_tag['text']=="") && count($this->curr_tag['children']['?'])==1) {
   $this->curr_tag['internal']=1;
   return true;
  }
  else {
   return false;
  }  
 }
/*-------------------------------[ old tag listing and searching ]---------------*/
function list_tags($tname,$tindex,$num) {
 $count=$this->count_tag($tname,$tindex);
 $num_count=-1;
 if(!is_array($num)) {
  if($num==-1) {
   $num=$count;
  }
  else if ($num>$count) {
   $num=$count;
  }
  $enum=$num;
  $num=array();

  for($j=0;$j<$enum;$j++) {
   $num[$j]=$j+1;
   $num_count++;
  }
  unset($enum);
 }
    
 $res_arr=array();
 
 if ($num_count==-1) {
  $num_count=count($num);
 }
 
 $index_portion=substr($tindex,0,strrpos($tname,":")-1);
 for ($i=0;$i<$num_count;$i++) {
  if ($num[$i]>$count) {
   continue;
  }
  
  $this->select($tname,$index_portion.$num[$i]);
  $res_arr[$i+1]=&$this->curr_tag['text'];
 }
 
 $returned=&$res_arr;
 return $returned;
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
/*--------------------------------------------------[ New tag searching and listing (xlist and xsearch)]--*/
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
  $returned=&$res_arr;
  return $returned;
 }
 function xsearch($tname,$tindex,$atts,$vals,$num=0) {
  $arr=$this->xlist($tname,$tindex,$atts,$num);
  $curr_tags=array();
  $ecount=0;
  $curr_count=count($arr);
  $atts_count=count($atts);
  $vals_count=count($vals);

  if ($vals_count!=$atts_count) {
   trigger_error("<b>KXParse</b>: <b>xsearch</b>: The number of required values doesn't match the number of given attributes",E_USER_ERROR);
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
  $returned=&$curr_tags;
  return $returned;
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
   trigger_error("<b>KXParse</b>: <b>xset</b>: number of attributes to be set doesn't equal the number of values given",E_USER_ERROR);
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
 }
/*------------------------------------------[Availability checkers (has's)]------------*/
 function has_text($tname="",$tindex="") {
  $this->select($tname,$tindex);
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
  $this->select($tname,$tindex);
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
  $this->select($tname,$tindex);
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
  $this->select($tname,$tindex);
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
/*-----------------------------------------------------*/
}
?>
