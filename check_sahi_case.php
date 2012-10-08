<?php
#
# Copyright (c) 2006-2010 Joerg Linge (http://www.pnp4nagios.org)
#

$opt[1] = "--slope-mode --vertical-label \"seconds\"  --title \"Sahi Case Runtime For $servicedesc on $hostname\" ";


$def[1] =  "DEF:sec=$RRDFILE[1]:$DS[1]:MAX " ;
$def[1] .= "AREA:sec#00FF00:\"Sahi Case Runtime \" " ;
$def[1] .= "LINE1:sec#000000:\"\" " ;
$def[1] .= "GPRINT:sec:LAST:\"%3.4lg %s$UNIT[1] LAST \" ";
$def[1] .= "GPRINT:sec:AVERAGE:\"%3.4lg %s$UNIT[1] AVERAGE \" ";
?>

