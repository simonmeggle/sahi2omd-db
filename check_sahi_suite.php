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

# PNP template for Sahi suite results submitted via NSCA. 


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

