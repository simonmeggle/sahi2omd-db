<?php

$opt[0] = "--vertical-label \"seconds\"  --slope-mode --title \"Sahi Suite/Case Runtime For $servicedesc on $hostname\" ";
$colors = array('#000000', '#0f0', '#ff0', '#f00', '#f0f', '#00f', '#0ff');
$ds_name[0] = "Sahi Runtime";
$def[0] = "";
$i = 0;
foreach ($this->DS as $KEY=>$VAL) {
    if(preg_match('/(.*sah)$/', $VAL['NAME'], $matches)){
        $i++;
        $def[0] .= rrd::def     ("var$KEY", $VAL['RRDFILE'], $VAL['DS'], "AVERAGE");
        #$def[0] .= rrd::area   ("var$KEY", rrd::color($i), $matches[1], 1 );
        $def[0] .= rrd::area   ("var$KEY", $colors[$i], $matches[1], 1 );
        $def[0] .= rrd::gprint  ("var$KEY", array("LAST","MAX","AVERAGE"), "%4.0lf" . $VAL['UNIT']);
    }
}
?>

