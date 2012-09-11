package MySahi;
our @ISA = qw(DBD::MySQL::Server);


use Data::Dumper;
use YAML;

sub init {
	my $self = shift;
	my %params = @_;
$DB::single = 1;
	$self->{'dbnow'} = $params{handle}->fetchrow_array(q{
		SELECT UNIX_TIMESTAMP() FROM dual
  	});
	$self->valdiff(\%params, 'dbnow');

  	if ($params{mode} =~ /my::sahi::suite/) {
		# Suite --------------------------------------------
		my @suite = $params{handle}->fetchrow_array(q{
			SELECT id,name,warning,critical
			FROM sahi_suites ss
			WHERE ss.name = ? 
			ORDER BY ss.id DESC LIMIT 1
		}, $params{name});

		if (! $suite[0] =~ /\d+/) {
			printf("UNKNOWN: Could not find a sahi suite %s. Re-Check parameter --name!", $params{name});
			exit 3;
		}

		my %suitehash;
		@suitehash{qw(id name warning critical)} = @suite;
		$self->{suite} = \@suite;

		# Case ---------------------------------------------
		my @cases = $params{handle}->fetchall_array(q{
			SELECT id,result,name,start,stop,warning,critical,sahi_suites_id,duration,time,msg
			FROM sahi_cases sc
			WHERE sc.sahi_suites_id = ?
			ORDER BY sc.id
		}, $self->{suite}[0]);

		if (! scalar(@cases)) {
			print("UNKNOWN: Could not find any sahi case. " );
			exit 3;
		}
		foreach my $c_ref (@cases) {
			my %caseshash;
			@caseshash{qw(id result name start stop warning critical sahi_suites_id duration time msg)} = @$c_ref;
			push @{$self->{cases}}, \%caseshash;

			# Steps --------------------------------------------
			my @steps = $params{handle}->fetchall_array(q{
				SELECT id,result,name,warning,sahi_cases_id,duration,time
				FROM sahi_steps ss
				WHERE ss.sahi_cases_id = ?
				ORDER BY ss.id
			}, $self->{cases}[-1]{id});
			foreach my $s_ref (@steps) {
				my %stephash; 
				@stephash{qw(id result name warning sahi_cases_id duration time)} = @$s_ref;
				push @{$self->{steps}{$self->{cases}[-1]{id}}}, \%stephash;
			}
		}
	} 
}

my %ERRDB = (
	0	=> "[OK] %s '%s' (%0.2fs) ok",	
	1	=> ", step %s over runtime (%0.2fs/warn at %ds)",	
	2	=> "[WARN] %s '%s' over runtime (%0.2fs/warn at %ds)",	
	3	=> "[CRIT] %s '%s' over runtime (%0.2fs/crit at %ds)",	
	4	=> "[CRIT] %s '%s' FATAL ERROR: %s",	
);

my %ERRDB2NAG (
	0	=> 0,
	1	=> 1,
	2	=> 1,
	3	=> 2,
	4	=> 2,
);

sub worststate {
	my ($val1,$val2);
	return ($val1 > $val2 ? $val1 : $val2);
}

sub nagios {
	my $self = shift; 
	my %params = @_;

	foreach my $c_ref (@$self->{cases}) {
		my $case_output = "";
		my $case_result = 0;
		my $case_db_result = 0;

		# 1. Fatal exception
		if ($c_ref->{result} == 4) {
			$case_output = sprintf($ERRDB{4}, "case", $c_ref->{name}, $c_ref->{msg});
			$case_output = $ERRDB2NAG{4}
		} else {
		# 2.1 Case duration
			$case_db_result = case_duration_result($c_ref->{duration},$c_ref->{warning},$c_ref->{critical});
			$case_output = sprintf($ERRDB2NAG{$case_db_result},
				"case",$c_ref->{name},$c_ref->{duration},($case_db_result == 2 ? $c_ref->{warning} : $c_ref->{critical});				
		}
		# 2.2 Step duration
		foreach my $s_ref (@$self->{steps}) {
			if step_duration_result($s_ref->{duration}, $s_ref->{warning}) {
				$case_output .= sprintf($ERRDB{1}, $s_ref->{name},$s_ref->{duration},$s_ref->{warning}});
				$case_result = worststate($case_db_result,1);
			}
			$self->add_perfdata();
		}
		# addnagios
		# addperfdata case
		if ($params{mode} =~ /my::sahi::case/) {
#			check runtime of cases
		}


	}



#		debug("Case had no exception, checking case and step runtimes...");
#		$msg = 'Ok';
#		// 2.1 case duration
#		if (is_mode_db() ) {
#			$case_result_db = getcase_duration_db_result($duration, $warn, $crit ); 
#			if ($case_result_db > 0) {
#				$msg += sprintf(', but case over runtime (%0.2f/%d/%d s)',$duration,$warn,$crit); 
#				debug("Case over runtime -> case_result_db = " + $case_result_db );
#			}			
#		} else {
#			$case_result = getcase_duration_result($duration, $warn, $crit ); 
#			if ($case_result > 0) {
#				$msg += sprintf(', but case over runtime (%0.2f/%d/%d s)',$duration,$warn,$crit); 
#				debug("Case over runtime -> case_result = " + $case_result + ", msg = " + $msg);
#			}
#		}	
#			
#		// 2.2 step duration
#		debug("checking step runtimes...");
#
#		for (var $s in $steps) {
#			debug(str_repeat(" ",4 + $debug_indent) + "--- Step " + $s + ":");
#//			var $step_result = getstep_result ($steps[$s]["duration"],$steps[$s]["threshold"]);						
#			if ($steps[$s]["stepres"] > 0) { 
#				$msg += sprintf(', %s over runtime (%0.2f/%d s)', $s, $steps[$s]["duration"],$steps[$s]["threshold"]);
#				debug(str_repeat(" ",4 + $debug_indent) + $s + " was over runtime");
#			}
#			if (is_mode_db() ) {
#				$case_result_db = getworststate($case_result_db, $steps[$s]["stepres"]);
#				debug(str_repeat(" ",4 + $debug_indent) + "new case_result_db = " + $case_result_db );
#			} else {
#				$case_result = getworststate($case_result, $steps[$s]["stepres"]);
#				debug(str_repeat(" ",4 + $debug_indent) + "new case_result = " + $case_result + ", msg = " + $msg);
#			}
#		}	
#		$msg += " ";		


sub case_duration_result {
	my ($value, $warn, $crit) = @_;
	my $res;
	if (($warn>0) && ($crit>0)) {
		if ($value > $warn) {
			$res = ($value > $crit ? 3 : 2)
		}
	} else { $res = 0; }
	return $res;
}

sub step_duration_result {
	my ($value, $warn) = @_;
	return ($value > $iwarn ? 1 : 0)
}
