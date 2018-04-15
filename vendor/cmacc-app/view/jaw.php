<?php

$htmlHead = `perl $lib_path/parser-head.pl $path/$dir`;

if (strlen($htmlHead) > 5){ 

  echo $htmlHead;
}

 else {
   echo "<!DOCTYPE html><head><title>$dir</title><link  href='Doc/G/Z/CSS/Doc.css' rel='stylesheet' /></head><body>";
 }


# include("$lib_path/view-tabs.php");
 
# echo "<hr>";

$lib_path = LIB_PATH;

$document = `perl $lib_path/parser.pl $path/$dir`;

$minDocLength = 1;

if (strlen($document) > $minDocLength){ 

$document=str_replace("{","{{",$document);

$document=str_replace("}","}}",$document);

  echo $document;}
 else {
   echo "Nothing to Show - because there is i) no Model.Root, or ii) a reference to a non-existent Map, or iii) a circular key inclusion (usually an effect of deprefixing!).";

}
?>
