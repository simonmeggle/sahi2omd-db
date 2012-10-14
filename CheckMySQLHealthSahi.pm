# ./check_mysql_health --hostname=sahidose --database sahi --username=sahi --password=sahipw --mode=my-sahi-suite --name='testcase0-4.suite' --timeout 3600

package MySahi;
our @ISA = qw(DBD::MySQL::Server); 

use Data::Dumper;
use YAML;
use MIME::Base64;
use Encode qw(encode); 

my %ERRDB = (
        0       => "%s '%s' (%0.2fs) ok",       
        1       => ", step '%s' over runtime (%0.2fs/warn at %ds)",     
        2       => "%s '%s' over runtime (%0.2fs/warn at %ds)", 
        3       => "%s '%s' over runtime (%0.2fs/crit at %ds)", 
        4       => "%s '%s' FATAL ERROR: %s",   
);

my %ERRDB2NAG = (
        0       => 0,
        1       => 1,
        2       => 1,
        3       => 2,
        4       => 2,
);

my %STATELABELS = (
        0       => "[OK]",
        1       => "[WARN]",
        2       => "[CRIT]",
        3       => "[UNKN]",
);

my %STATESHORT = (
        0       => "o",
        1       => "w",
        2       => "c",
        3       => "u",
);

my %ERRORS=( OK => 0, WARNING => 1, CRITICAL => 2, UNKNOWN => 3 );

# Queries for sub get_cases, depending on working mode
my %SQL_CASES = (
	"my::sahi::suite"	=>	q{SELECT sc.id,result,name,start,stop,warning,critical,sahi_suites_id,duration,UNIX_TIMESTAMP(time),msg,screenshot
					FROM sahi_cases sc, sahi_jobs sj
					WHERE (sc.sahi_suites_id = ?) and (sc.guid = sj.guid)
					ORDER BY sc.id },

	"my::sahi::case"	=>	q{SELECT sc.id,result,name,start,stop,warning,critical,sahi_suites_id,duration,UNIX_TIMESTAMP(time),msg,screenshot
					FROM sahi_cases sc, sahi_jobs sj
					WHERE (sc.name = ?) and (sc.guid = sj.guid)
					ORDER BY sc.id DESC LIMIT 1}
);




sub init {
	$DB::single = 1;
        my $self = shift;
        my %params = @_;
        $self->{'dbnow'} = $params{handle}->fetchrow_array(q{
                SELECT UNIX_TIMESTAMP() FROM dual
        });

        if ($params{mode} =~ /my::sahi::suite/) {
		$self->{suite} = get_suite(%params);
		($self->{cases},$self->{steps}) = get_cases($self->{suite}->{id}, %params);
        } elsif ($params{mode} =~ /my::sahi::case/) {
		($self->{cases},$self->{steps}) = get_cases($params{name}, %params);
        }

}

sub nagios {
	$DB::single = 1;
        my $self = shift;
        my %params = @_;
        my $runtime = 0;
	my $casecount= 0;
        foreach my $c_ref (@{$self->{cases}}) {
                my $case_output = "";
                my $case_result = 0;
                my $case_db_result = 0;
		$casecount++;

                $runtime += $c_ref->{duration};

		# 0. Stale result?
		my $case_stale = (($self->{dbnow}) - ($c_ref->{time}) > $params{name2}) || 0;
		if ($case_stale) {
			$case_result = 3;
			$case_output = sprintf("Sahi Case '%s' did not run for more than %d seconds!", $c_ref->{name}, $params{name2});
		} elsif ($c_ref->{result} == 4) {
                # 1. Fatal exception
                        $case_result = $ERRDB2NAG{4};
                        $case_output = sprintf($ERRDB{4}, "case", $c_ref->{name}, $c_ref->{msg});
			if (defined($c_ref->{screenshot})) {
				my $imgb64 = encode_base64($c_ref->{screenshot},"");
				$case_output .= "<div style=\"width:640px\" id=\"case$casecount\"><img style=\"width:98%;border:2px solid gray;display: block;margin-left:auto;margin-right:auto;margin-bottom:4px\" src=\"data:image/jpg;base64,$imgb64\"></div>";
			}
		
                } 
		# 2.1 Case duration
		$case_db_result = case_duration_result($c_ref->{duration},$c_ref->{warning},$c_ref->{critical});
		if (! ($case_stale || ($c_ref->{result} == 4) )) { 
			$case_result = $ERRDB2NAG{$case_db_result};
			$case_output = sprintf($ERRDB{$case_db_result},
				"case",$c_ref->{name},$c_ref->{duration},($case_db_result == 2 ? $c_ref->{warning} : $c_ref->{critical}));
		}
		# 2.2 Step duration
		my $stepcount = 0;
		foreach my $s_ref (@{$self->{steps}->{$c_ref->{id}}}) {
			$stepcount++;
			if (step_duration_result($s_ref->{duration}, $s_ref->{warning}) and not $case_stale and not ($c_ref->{result} == 4)) {
				$case_output .= sprintf($ERRDB{1}, $s_ref->{name},$s_ref->{duration},$s_ref->{warning});
				$case_result = $ERRDB2NAG{worststate($case_db_result,1)};
			}
			if ($case_stale) {
				$self->add_perfdata(sprintf("s_%d_%d_%s=%s;;;;",$casecount,$stepcount,$s_ref->{name}, "U"));
			} else {
				$self->add_perfdata(sprintf("s_%d_%d_%s=%0.2fs;%d;;;",$casecount,$stepcount,$s_ref->{name}, $s_ref->{duration}, $s_ref->{warning}));
			}
		}
                # final case result
                $self->add_nagios($case_result, sprintf("%s %s", $STATELABELS{$case_result}, $case_output));
		if ($case_stale) {
	                $self->add_perfdata(sprintf("c_%d_%s=%ss;;;;",$casecount,$c_ref->{name},"U"));

		} else {
	                $self->add_perfdata(sprintf("c_%d_%s=%0.2fs;%d;%d;;",$casecount,$c_ref->{name},$c_ref->{duration},$c_ref->{warning},$c_ref->{critical}));
		}
	        $self->add_perfdata(sprintf("c_%dstate=%d;;;;",$casecount, $case_result));
        }
        if ($params{mode} =~ /my::sahi::suite/) {
		my $worst_suite;
		foreach my $level ("OK", "UNKNOWN", "WARNING", "CRITICAL") {
			if (scalar(@{$self->{nagios}->{messages}->{$ERRORS{$level}}})) {
				$worst_suite = $ERRORS{$level};
			}
		}	
		$self->add_perfdata(sprintf("suite_state=%d;;;;",$worst_suite));	

		if (($self->{dbnow}) - ($self->{suite}{time}) > $params{name2}) {
			$self->add_nagios(3,sprintf("%s Sahi Suite '%s' did not run for more than %d seconds!", $STATELABELS{3}, $params{name}, $params{name2}));
			$self->add_perfdata(sprintf("suite_runtime_%s=%s;;;;",$params{name},"U"));
		} elsif ($params{warningrange} && $params{criticalrange}) {
			my $st = $self->check_thresholds($runtime, $params{warningrange}, $params{criticalrange});
			$self->add_nagios(
				$st,sprintf ("%s Sahi Suite %s ran in %0.2f seconds",$STATELABELS{$st},$params{name}, $runtime)
			);
			$self->add_perfdata(sprintf("suite_runtime_%s=%0.2fs;%d;%d;;",$params{name},$runtime,$params{warningrange}, $params{criticalrange}));
		}

        }

}

################################################################################
#    H E L P E R   F U N C T I O N S
################################################################################

sub get_suite {
	my %params = @_; 
	my @suite = $params{handle}->fetchrow_array(q{
		SELECT ss.id,ss.name,UNIX_TIMESTAMP(ss.time)
		FROM sahi_suites ss, sahi_jobs sj
		WHERE (ss.name = ?) and (ss.guid = sj.guid)
		ORDER BY ss.id DESC LIMIT 1
	}, $params{name} );
	if (! $suite[0] =~ /\d+/) {    
		printf("UNKNOWN: Could not find a sahi suite %s. Re-Check parameter --name!", $params{name});
		exit 3;                        
	}
	my %suitehash;
	@suitehash{qw(id name time)} = @suite;
	return \%suitehash;
}

sub get_cases {
	my $searchfor = shift;
	my %params = @_; 
	my $ret_cases = [];
	my $ret_steps = {};
	my $query = $SQL_CASES{$params{mode}};

	# Cases -----------------------------------------------------
	my @cases = $params{handle}->fetchall_array($query, $searchfor);
	if (! scalar(@cases)) {
		print("UNKNOWN: Could not find any sahi case. " );
		exit 3;
	}
	foreach my $c_ref (@cases) {
		my %caseshash;
		@caseshash{qw(id result name start stop warning critical sahi_suites_id duration time msg screenshot)} = @$c_ref;
		push @{$ret_cases}, \%caseshash;

		# Steps --------------------------------------------
		my @steps = $params{handle}->fetchall_array(q{
			SELECT id,result,name,warning,sahi_cases_id,duration,time
			FROM sahi_steps ss
			WHERE ss.sahi_cases_id = ?
			ORDER BY ss.id
		}, $ret_cases->[-1]{id});
		foreach my $s_ref (@steps) {
			my %stephash;
			@stephash{qw(id result name warning sahi_cases_id duration time)} = @$s_ref;
			push @{$ret_steps->{$ret_cases->[-1]{id}}}, \%stephash;
		}
	}
	return ($ret_cases, $ret_steps);
}

sub case_duration_result {
        my ($value, $warn, $crit) = @_;
        my $res;
        if (($warn>0) && ($crit>0)) {
                if ($value > $warn) {
                        $res = ($value > $crit ? 3 : 2)
                } else {$res = 0;}
        } else { $res = 0; }
        return $res;
}

sub step_duration_result {
        my ($value, $warn) = @_;
        return ($value > $warn ? 1 : 0)
}
sub worststate {
        my ($val1,$val2) = @_;
        return ($val1 > $val2 ? $val1 : $val2);
}





				
