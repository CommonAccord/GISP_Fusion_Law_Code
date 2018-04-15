<?php



$lib_path = LIB_PATH;
$document = `perl $lib_path/parser-kvs.pl $path/$dir`;

$minDocLength = 1;

if (strlen($document) > $minDocLength){ 
 
  echo $document;}
 else {
   echo "Nothing to Show - because there is i) no Model.Root, or ii) a reference to a non-existent Map, or iii) a circular key inclusion (usually an effect of deprefixing!).";


}
?>
