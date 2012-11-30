<?php

# Copyright (C) 2012  Simon Meggle, <simon.meggle@consol.de>

# this program Is free software; you can redistribute it And/Or
# modify it under the terms of the GNU General Public License
# As published by the Free Software Foundation; either version 2
# of the License, Or (at your Option) any later version.

# this program Is distributed In the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY Or FITNESS For A PARTICULAR PURPOSE.  See the
# GNU General Public License For more details.

# You should have received a copy of the GNU General Public License
# along With this program; If Not, write To the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

# PNP template for Sahi case results submitted via NSCA. 

$opt[1] = "--slope-mode --vertical-label \"seconds\"  --title \"Sahi Case Runtime For $servicedesc on $hostname\" ";


$def[1] =  "DEF:sec=$RRDFILE[1]:$DS[1]:MAX " ;
$def[1] .= "AREA:sec#00FF00:\"Sahi Case Runtime \" " ;
$def[1] .= "LINE1:sec#000000:\"\" " ;
$def[1] .= "GPRINT:sec:LAST:\"%3.4lg %s$UNIT[1] LAST \" ";
$def[1] .= "GPRINT:sec:AVERAGE:\"%3.4lg %s$UNIT[1] AVERAGE \" ";
?>

