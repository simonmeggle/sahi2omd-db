<?php

isset($_GET['debug']) ? $DEBUG = $_GET['debug'] : $DEBUG = 0;

$col_invisible = '#00000000';

$col_suite_runtime_line = '#CE0071';
$col_suite_runtime_area = '#E73A98';

# Case colors
$col_case_line = array('','#225ea8','#0c2c84','#1d91c0','#41b6c4','#7fcdbb','#c7e9b4','#edf8b1','#E9F698');
$col_case_area = array('','#5692dc','#154be0','#5692DC','#1d91c0','#8ED4DC','#b6e2d8','#d7f0c7','#f3fac7');
$col_case_area_opacity = "BB";

# Step colors
$col_step_line = array('#01c510a','#bf812d','#dfc27d','#c7eae5','#80cdc1','#35978f','#01665e');
$col_step_area = array('#01c510a','#bf812d','#dfc27d','#c7eae5','#80cdc1','#35978f','#01665e');
$col_step_area_opacity = "CC";

# State colors
$col_OK = "#008500";
$col_WARN = "#ffcc00";
$col_CRIT = "#d30000";
$col_UNKN = "#d6d6d6";
$col_NOK = "#ff8000";

# ticker stuff
$ticker_frac = "-0.04";
$ticker_opacity = "BB";
$ticker_dist_factor = "1.05";

# Unknown ticker
$unkn_tick_frac = "1.0";
$unkn_tick_opacity = "FF";

sort($this->DS);

# SUITE Graph  #############################################################
if (preg_match('/^check_sahi_db.*?suite/', $this->MACRO['CHECK_COMMAND'])) {
		$suitename = preg_replace('/^suite_runtime_(.*)$/', '$1', end($NAME));
		$ds_name[0] = "Sahi Suite '" . $suitename . "'";
		$opt[0] = "--vertical-label \"seconds\"  -l 0 --slope-mode --title \"$servicedesc (Sahi Suite $suitename) on $hostname\" ";
		$def[0] = "";
		# AREA  ---------------------------------------------------------------------
		foreach($this->DS as $k=>$v) {
			if (preg_match('/c_(\d?)_(.*)/', $v["LABEL"], $c_matches)) {
				$casecount = $c_matches[1];
				$casename = $c_matches[2];
				$def[0] .= rrd::def("c_area$casecount", $v["RRDFILE"], $v["DS"], "AVERAGE");
				if ($casecount == "1") {
					$def[0] .= rrd::comment("Sahi Cases\: \\n");
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
		# LINE ---------------------------------------------------------------------
		$c_last_index = "";
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
				# is this a unknown value? 
				$def[0] .= rrd::cdef("c_".$casecount."_unknown", "c_line$casecount,UN,1,0,IF");
				$c_last_index = $casecount;
			}
		}	
		$def[0] .= rrd::comment(" \\n");
		$def[0] .= rrd::comment("Sahi Suite\g");
		if((end($WARN) != "") && (end($CRIT) != "")) {
			$def[0] .= rrd::comment(" (\g");
			$def[0] .= rrd::hrule(end($WARN), $col_WARN, "Warning  ".end($WARN).end($UNIT));
			$def[0] .= rrd::hrule(end($CRIT), $col_CRIT, "Critical  ".end($CRIT).end($UNIT)."\g");
			$def[0] .= rrd::comment(")\g");
		} 
		$def[0] .= rrd::comment("\:\\n");
		$def[0] .= rrd::def("suite", end($RRDFILE), end($DS), "AVERAGE");
		if ($c_last_index != "") {
			$def[0] .= rrd::cdef("suite_diff", "suite,c_line_stackbase".$c_last_index.",UN,0,c_line_stackbase".$c_last_index.",IF,-");
			# invisible line to stack upon
			$def[0] .= rrd::line1("c_line_stackbase".($c_last_index),"#00000000");
			$def[0] .= rrd::area("suite_diff", $col_suite_runtime_area,$suitename,1 );
			# invisible line to stack upon
			$def[0] .= rrd::line1("c_line_stackbase".($c_last_index),"#00000000");
			$def[0] .= rrd::line1("suite_diff", $col_suite_runtime_line, "",1 );
		} else {
			# no cases, no STACKing
			$def[0] .= rrd::area("suite", $col_suite_runtime_area,$suitename );
			$def[0] .= rrd::line1("suite", $col_suite_runtime_line, "" );
		}

		$def[0] .= rrd::gprint("suite", "LAST", "%3.2lf ".end($UNIT)." LAST");
		$def[0] .= rrd::gprint("suite", "MAX", "%3.2lf ".end($UNIT)." MAX");
		$def[0] .= rrd::gprint("suite", "AVERAGE", "%3.2lf ".end($UNIT)." AVERAGE \j");
		# invisible line above maximum (for space between MAX and TICKER) -------------------------------------	
		$def[0] .= rrd::def("suite_max", end($RRDFILE), end($DS), "MAX") ;
		$def[0] .= rrd::cdef("suite_maxplus", "suite_max,".$ticker_dist_factor.",*");
		$def[0] .= rrd::line1("suite_maxplus", $col_invisible);
		# TICKER ---------------------------------------------------------------------
		$idxm1 = count($this->DS)-1;
		$def[0] .= rrd::def("suite_state", $RRDFILE[$idxm1], $DS[$idxm1], "MAX") ;
		$def[0] .= rrd::cdef("suite_state_unknown", "suite_state,2,GT,suite_state,0,IF") ;
		$def[0] .= rrd::cdef("suite_state_nok", "suite_state,0,GT,suite_state,0,IF") ;
		$def[0] .= rrd::cdef("suite_state_nok2", "suite_state_nok,3,LT,suite_state_nok,0,IF") ;
		$def[0] .= "TICK:suite_state_nok2".$col_NOK.$ticker_opacity.":".$ticker_frac.":not_ok " ;
		$def[0] .= "TICK:suite_state_unknown".$col_UNKN.$unkn_tick_opacity.":".$unkn_tick_frac.":unknown/stale " ;
		for ($i=1; $i<=$c_last_index; $i++) {
			$def[0] .= "TICK:c_".$i."_unknown".$col_UNKN.$unkn_tick_opacity.":".$unkn_tick_frac.": " ;
		}
		$def[0] .= "VRULE:".$NAGIOS_TIMET."#000000:\"Last Service Check \\n\" ";
}  

# CASE Graphs  #############################################################
foreach ($this->DS as $KEY=>$VAL) {
	if(preg_match('/^c_(\d?)_(.*)/', $VAL['LABEL'], $c_matches)) {
		$casecount = $c_matches[1];
		$casename = $c_matches[2];
		$ds_name[$casecount] = "Sahi Case $casename";
		$opt[$casecount] = "--vertical-label \"seconds\"  -l 0 -M --slope-mode --title \"$servicedesc (Sahi case $casecount) on $hostname\" ";
		$def[$casecount] = "";
		# STEP AREA ---------------------------------------------------------------------
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
		# invisible line above maximum (for space between MAX and TICKER) ---------------	
		$def[$casecount] .= rrd::def("case".$casecount."_max", $VAL['RRDFILE'], $VAL['DS'], "MAX") ;
		$def[$casecount] .= rrd::cdef("case".$casecount."_maxplus", "case".$casecount."_max,".$ticker_dist_factor.",*");
		$def[$casecount] .= rrd::line1("case".$casecount."_maxplus", $col_invisible);
		# STEP LINE ---------------------------------------------------------------------
		$s_last_index = "";
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
				$s_last_index = $stepcount;
			}
		}
		# CASE Warn/Crit -----------------------------------------------------------------
		$def[$casecount] .= rrd::comment(" \\n");
		$def[$casecount] .= rrd::comment("Case ".$casecount ."\g");
		if(($VAL["WARN"] != "") && ($VAL["CRIT"] != "")) {
			$def[$casecount] .= rrd::comment(" (\g");
			$def[$casecount] .= rrd::hrule($VAL["WARN"], "#FFFF00", "Warning  ".$VAL["WARN"].$UNIT[$casecount]);
			$def[$casecount] .= rrd::hrule($VAL["CRIT"], "#FF0000", "Critical  ".$VAL["CRIT"].$UNIT[$casecount]."\g");
			$def[$casecount] .= rrd::comment(")\g");
		}
		# CASE LINE & AREA --------------------------------------------------------------
		$def[$casecount] .= rrd::comment("\:\\n");
	        $def[$casecount] .= rrd::def("case$casecount", $VAL['RRDFILE'], $VAL['DS'], "AVERAGE");
		# is this a unknown value?
		$def[$casecount] .= rrd::cdef("case".$casecount."_unknown", "case$casecount,UN,1,0,IF");
		if ($s_last_index != "") {
			$def[$casecount] .= rrd::cdef("case_diff$casecount","case$casecount,s_line_stackbase$s_last_index,-");
			# invisible line to stack upon
			$def[$casecount] .= rrd::line1("s_line_stackbase$s_last_index","#00000000");	
			$def[$casecount] .= rrd::area   ("case_diff$casecount", $col_case_area[$casecount].$col_case_area_opacity, $casename,1 );
			# invisible line to stack upon
			$def[$casecount] .= rrd::line1("s_line_stackbase$s_last_index","#00000000");	
			$def[$casecount] .= rrd::line1   ("case_diff$casecount", $col_case_line[$casecount],"",1);
		} else {
			# no steps, no stacks
			$def[$casecount] .= rrd::area   ("case$casecount", $col_case_area[$casecount].$col_case_area_opacity, $casename );
			$def[$casecount] .= rrd::line1   ("case$casecount", $col_case_line[$casecount],"");
		}
		$def[$casecount] .= rrd::gprint ("case$casecount", "LAST", "%3.2lf $UNIT[$casecount] LAST");
		$def[$casecount] .= rrd::gprint ("case$casecount", "MAX", "%3.2lf $UNIT[$casecount] MAX");
		$def[$casecount] .= rrd::gprint ("case$casecount", "AVERAGE", "%3.2lf $UNIT[$casecount] AVERAGE \j");
		# TICKS ---------------------------------------------------------------------
		foreach ($this->DS as $k=>$v) {
			if(preg_match('/^c_'.$casecount.'state/', $v['LABEL'], $state_matches)) {
				$def[$casecount] .= rrd::def("case".$casecount."_state", $v['RRDFILE'], $v['DS'], "MAX") ;
				$def[$casecount] .= rrd::cdef("case".$casecount."_state_unknown", "case".$casecount."_state,2,GT,case".$casecount."_state,0,IF") ;
				$def[$casecount] .= rrd::cdef("case".$casecount."_state_nok", "case".$casecount."_state,0,GT,case".$casecount."_state,0,IF") ;
				$def[$casecount] .= rrd::cdef("case".$casecount."_state_nok2", "case".$casecount."_state_nok,3,LT,case".$casecount."_state_nok,0,IF") ;
				$def[$casecount] .= "TICK:case".$casecount."_state_nok2".$col_NOK.$ticker_opacity.":".$ticker_frac.":not_ok " ;
				$def[$casecount] .= "TICK:case".$casecount."_state_unknown".$col_UNKN.$unkn_tick_opacity.":".$unkn_tick_frac.": " ;
			}
		}
		$def[$casecount] .= "TICK:case".$casecount."_unknown".$col_UNKN.$unkn_tick_opacity.":".$unkn_tick_frac.":unknown/stale " ;
		$def[$casecount] .= "VRULE:".$NAGIOS_TIMET."#000000:\"Last Service Check \\n\" ";
	}
}


if ( $DEBUG == 1 ) {
#throw new Kohana_exception(print_r($def,TRUE));
throw new Kohana_exception(print_r($idxm1,TRUE));
}
?>

