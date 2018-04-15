<?php

$htmlHead = `perl $lib_path/parser-head.pl $path/$dir`;

if (strlen($htmlHead) > 5){ 

  echo $htmlHead;
}

 else {
   echo "<!DOCTYPE html><head><link  href='Doc/G/Z/CSS/Print.css' rel='stylesheet' /></head><body>";
 }


$lib_path = LIB_PATH;
$document = `perl $lib_path/parser-print.pl $path/$dir`;

$minDocLength = 1;

if (strlen($document) > $minDocLength){ 

//kludge to let html headers have formatting.

$document=str_replace("(Curly-)","{",$document);

$document=str_replace("(-Curly)","}",$document);

 
  echo $document;}
 else {
   echo "Nothing to Show - because there is i) no Model.Root, or ii) a reference to a non-existent Map, or iii) a circular key inclusion (usually an effect of deprefixing!).<br>Or you have looked for a page that doesn't exist.  If you are merely lost - try <a href='index.php?action=list&file='>the top of the file system</a>.";


}
?>
