<?php

isset($_GET['debug']) ? $DEBUG = $_GET['debug'] : $DEBUG = 0;

$col_invisible = '#00000000';

$col_suite_runtime_line = '#903789';
$col_suite_runtime_area = '#b646ad';

# Case colors
$col_case_line = array('#00005e','#14145e','#28285e','#3c3c5e','#50505e','#64645e','#78785e','#8c8c5e','#a0a05e');
$col_case_area = array('#0000b3','#1414b3','#2828b3','#3c3cb3','#5050b3','#6464b3','#7878b3','#8c8cb3','#a0a0b3');
$col_case_area_opacity = "AA";
# Step colors
$col_step_line = array('#752f00','#754617','#914918','#916131','#917931','#ad7c4a');
$col_step_area = array('#8e3900','#8e551c','#aa551c','#aa7139','#aa8e39','#c68e55');
$col_step_area_opacity = "FF";

# State colors
$col_OK = "#008500";
$col_WARN = "#ffcc00";
$col_CRIT = "#d30000";

# ticker stuff
$ticker_frac = "-0.04";
$ticker_opacity = "88";
$ticker_dist_factor = "1.05";


sort($this->DS);
if (preg_match('/^check_sahi_db.*?suite/', $this->MACRO['CHECK_COMMAND'])) {
		$suitename = preg_replace('/^suite_runtime_(.*)$/', '$1', end($NAME));
		$ds_name[0] = "Sahi Suite '" . $suitename . "'";
		$opt[0] = "--vertical-label \"seconds\"  -l 0 --slope-mode --title \"$servicedesc (Sahi Suite $suitename) on $hostname\" ";
		$def[0] = "";

		$def[0] .= rrd::comment("Suite\g");
		if((end($WARN) != "") && (end($CRIT) != "")) {
			$def[0] .= rrd::comment(" (\g");
			$def[0] .= rrd::hrule(end($WARN), $col_WARN, "Warning  ".end($WARN).end($UNIT));
			$def[0] .= rrd::hrule(end($CRIT), $col_CRIT, "Critical  ".end($CRIT).end($UNIT)."\g");
			$def[0] .= rrd::comment(")\g");
		} 
			
		$def[0] .= rrd::comment("\:\\n");

		$def[0] .= rrd::def("suite", end($RRDFILE), end($DS), "AVERAGE");
	        $def[0] .= rrd::area("suite", $col_suite_runtime_area );
	        $def[0] .= rrd::line1("suite", $col_suite_runtime_line, $suitename );
		$def[0] .= rrd::gprint("suite", "LAST", "%3.2lf ".end($UNIT)." LAST");
		$def[0] .= rrd::gprint("suite", "MAX", "%3.2lf ".end($UNIT)." MAX");
		$def[0] .= rrd::gprint("suite", "AVERAGE", "%3.2lf ".end($UNIT)." AVERAGE \j");
		$def[0] .= rrd::comment(" \\n");
	
		# AREA
		foreach($this->DS as $k=>$v) {
			if (preg_match('/c_(\d?)_(.*)/', $v["LABEL"], $c_matches)) {
				$casecount = $c_matches[1];
				$casename = $c_matches[2];
				$def[0] .= rrd::def("c_area$casecount", $v["RRDFILE"], $v["DS"], "AVERAGE");
				if ($casecount == "1") {
					$def[0] .= rrd::comment("Cases\: \\n");
					$def[0] .= rrd::cdef("c_area_stackbase$casecount", "c_area$casecount,1,*");
					$def[0] .= rrd::area("c_area$casecount", $col_case_area[$casecount].$col_case_area_opacity, $casename, 0);
				} else {
					# invisible line to stack upon
					$def[0] .= rrd::line1("c_area_stackbase".($casecount-1),"#00000000");
					$def[0] .= rrd::area("c_area$casecount", $col_case_area[$casecount].$col_case_area_opacity, $casename, 1);
					# add value to stackbase
					$def[0] .= rrd::cdef("c_area_stackbase$casecount", "c_area_stackbase".($casecount-1).",c_area$casecount,+");
				}
				$def[0] .= rrd::gprint("c_area$casecount", "LAST", "%3.2lf $UNIT[$casecount] LAST");
				$def[0] .= rrd::gprint("c_area$casecount", "MAX", "%3.2lf $UNIT[$casecount] MAX ");
				$def[0] .= rrd::gprint("c_area$casecount", "AVERAGE", "%3.2lf $UNIT[$casecount] AVERAGE \j");
			}
		}
		# LINE
		foreach($this->DS as $k=>$v) {
			if (preg_match('/c_(\d?)_(.*)/', $v["LABEL"], $c_matches)) {
				$casecount = $c_matches[1];
				$casename = $c_matches[2];
				$def[0] .= rrd::def("c_line$casecount", $v["RRDFILE"], $v["DS"], "AVERAGE");
				if ($casecount == "1") {
					$def[0] .= rrd::cdef("c_line_stackbase$casecount", "c_line$casecount,1,*");
					$def[0] .= rrd::line1("c_line$casecount", $col_case_line[$casecount], "", 0);
				} else {
					# invisible line to stack upon
					$def[0] .= rrd::line1("c_line_stackbase".($casecount-1),"#00000000");
					$def[0] .= rrd::line1("c_line$casecount", $col_case_area[$casecount], "", 1);
					# add value to stackbase
					$def[0] .= rrd::cdef("c_line_stackbase$casecount", "c_line_stackbase".($casecount-1).",c_line$casecount,+");
				}
			}
		}	
	        $def[0] .= rrd::line1("suite", $col_suite_runtime_line );

		# invisible line above maximum (for space between MAX and TICKER)		
		$def[0] .= rrd::def("suite_max", end($RRDFILE), end($DS), "MAX") ;
		$def[0] .= rrd::cdef("suite_maxplus", "suite_max,".$ticker_dist_factor.",*");
		$def[0] .= rrd::line1("suite_maxplus", $col_invisible);
		

		# TICKER
		$def[0] .= rrd::cdef("suite_state", $this->MACRO['SERVICESTATE'].",suite,POP") ;
		$def[0] .= rrd::ticker("suite_state", "1", "2", $ticker_frac,$ticker_opacity,$col_OK,$col_WARN,$col_CRIT) ;
}

foreach ($this->DS as $KEY=>$VAL) {

	if(preg_match('/^c_(\d?)_(.*)/', $VAL['LABEL'], $c_matches)) {
		$casecount = $c_matches[1];
		$casename = $c_matches[2];
		$ds_name[$casecount] = "Sahi Case $casename";
		$opt[$casecount] = "--vertical-label \"seconds\"  -l 0 -M --slope-mode --title \"$servicedesc (Sahi case $casecount) on $hostname\" ";
		$def[$casecount] = "";

		$def[$casecount] .= rrd::comment("Case ".$casecount ."\g");
		if(($VAL["WARN"] != "") && ($VAL["CRIT"] != "")) {
			$def[$casecount] .= rrd::comment(" (\g");
			$def[$casecount] .= rrd::hrule($VAL["WARN"], "#FFFF00", "Warning  ".$VAL["WARN"].$UNIT[$casecount]);
			$def[$casecount] .= rrd::hrule($VAL["CRIT"], "#FF0000", "Critical  ".$VAL["CRIT"].$UNIT[$casecount]."\g");
			$def[$casecount] .= rrd::comment(")\g");
		}

		$def[$casecount] .= rrd::comment("\:\\n");

	        $def[$casecount] .= rrd::def("case$casecount", $VAL['RRDFILE'], $VAL['DS'], "AVERAGE");
	        $def[$casecount] .= rrd::area   ("case$casecount", $col_case_area[$casecount].$col_case_area_opacity, $casename );
	        $def[$casecount] .= rrd::line1   ("case$casecount", $col_case_line[$casecount] );
		$def[$casecount] .= rrd::gprint ("case$casecount", "LAST", "%3.2lf $UNIT[$casecount] LAST");
		$def[$casecount] .= rrd::gprint ("case$casecount", "MAX", "%3.2lf $UNIT[$casecount] MAX");
		$def[$casecount] .= rrd::gprint ("case$casecount", "AVERAGE", "%3.2lf $UNIT[$casecount] AVERAGE \j");
		$def[$casecount] .= rrd::comment(" \\n");

		# AREA
		foreach ($this->DS as $k=>$v) {
			if (preg_match('/^s_'.$casecount.'_(\d?)_(.*)/', $v['LABEL'], $s_matches)) {
				$stepcount = $s_matches[1];
				$stepname = $s_matches[2];
				$def[$casecount] .= rrd::def("s_area$stepcount", $v['RRDFILE'], $v['DS'], "AVERAGE");
				if ($stepcount == "1"){
					$def[$casecount] .= rrd::comment("Steps\: \\n");
					$def[$casecount] .= rrd::cdef("s_area_stackbase$stepcount", "s_area$stepcount,1,*");
	        			$def[$casecount] .= rrd::area("s_area$stepcount", $col_step_area[$stepcount].$col_step_area_opacity,$stepname, 0 );
				} else {
					# invisible line to stack upon
					$def[$casecount] .= rrd::line1("s_area_stackbase".($stepcount-1),"#00000000");	
					$def[$casecount] .= rrd::area("s_area$stepcount", $col_step_area[$stepcount].$col_step_area_opacity,$stepname, 1 );
					# add value to s_area_stackbase
					$def[$casecount] .= rrd::cdef("s_area_stackbase$stepcount", "s_area_stackbase".($stepcount-1).",s_area$stepcount,+");
				}
				$def[$casecount] .= rrd::gprint("s_area$stepcount", "LAST", "%3.2lf $UNIT[$stepcount] LAST");
				$def[$casecount] .= rrd::gprint("s_area$stepcount", "MAX", "%3.2lf $UNIT[$stepcount] MAX ");
				$def[$casecount] .= rrd::gprint("s_area$stepcount", "AVERAGE", "%3.2lf $UNIT[$stepcount] AVERAGE \j");
			}
		}

		# invisible line above maximum (for space between MAX and TICKER)		
		$def[$casecount] .= rrd::def("case".$casecount."_max", $VAL['RRDFILE'], $VAL['DS'], "MAX") ;
		$def[$casecount] .= rrd::cdef("case".$casecount."_maxplus", "case".$casecount."_max,".$ticker_dist_factor.",*");
		$def[$casecount] .= rrd::line1("case".$casecount."_maxplus", $col_invisible);

		# LINE & TICK
		foreach ($this->DS as $k=>$v) {
			if (preg_match('/^s_'.$casecount.'_(\d?)_(.*)/', $v['LABEL'], $s_matches)) {
				$stepcount = $s_matches[1];
				$stepname = $s_matches[2];
				$def[$casecount] .= rrd::def("s_line$stepcount", $v['RRDFILE'], $v['DS'], "AVERAGE");
				if ($stepcount == "1"){
					$def[$casecount] .= rrd::cdef("s_line_stackbase$stepcount", "s_line$stepcount,1,*");
					$def[$casecount] .= rrd::line1("s_line$stepcount", $col_step_line[$stepcount], "", 0 );
				} else {
					# invisible line to stack upon
					$def[$casecount] .= rrd::line1("s_line_stackbase".($stepcount-1),"#00000000");	
					$def[$casecount] .= rrd::line1("s_line$stepcount", $col_step_line[$stepcount], "", 1 );
					# add value to s_line_stackbase
					$def[$casecount] .= rrd::cdef("s_line_stackbase$stepcount", "s_line_stackbase".($stepcount-1).",s_line$stepcount,+");
				}
			} elseif(preg_match('/^c_'.$casecount.'state/', $v['LABEL'], $state_matches)) {
				$def[$casecount] .= rrd::def("case".$casecount."_state", $v['RRDFILE'], $v['DS'], "AVERAGE") ;
				$def[$casecount] .= rrd::ticker("case".$casecount."_state", 1, 2,$ticker_frac,$ticker_opacity,$col_OK,$col_WARN,$col_CRIT) ;
			}
		}


	}

}


if ( $DEBUG == 1 ) {
throw new Kohana_exception(print_r($def,TRUE));
}
?>

