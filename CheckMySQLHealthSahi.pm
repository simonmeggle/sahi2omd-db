# ./check_mysql_health --hostname=sahidose --database sahi --username=sahi --password=sahipw --mode=my-sahi-suite --name='testcase0-4.suite' --timeout 3600

package MySahi;
our @ISA = qw(DBD::MySQL::Server); 

use Data::Dumper;
use YAML;

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

my %SQL_CASES = (
	"my::sahi::suite"	=>	q{SELECT sc.id,result,name,start,stop,warning,critical,sahi_suites_id,duration,time,msg
					FROM sahi_cases sc, sahi_jobs sj
					WHERE (sc.sahi_suites_id = ?) and (sc.guid = sj.guid)
					ORDER BY sc.id },

	"my::sahi::case"	=>	q{SELECT sc.id,result,name,start,stop,warning,critical,sahi_suites_id,duration,time,msg
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
        $self->valdiff(\%params, 'dbnow');

        if ($params{mode} =~ /my::sahi::suite/) {
		$self->{suite} = get_suite(%params);
		($self->{cases},$self->{steps}) = get_cases($self->{suite}->{id}, %params);
        }
}

sub nagios {
	$DB::single = 1;
        my $self = shift;
        my %params = @_;
        my $runtime = 0;
        foreach my $c_ref (@{$self->{cases}}) {
                my $case_output = "";
                my $case_result = 0;
                my $case_db_result = 0;

                $runtime += $c_ref->{duration};
                # 1. Fatal exception
                if ($c_ref->{result} == 4) {
                        $case_result = $ERRDB2NAG{4};
                        $case_output = sprintf($ERRDB{4}, "case", $c_ref->{name}, $c_ref->{msg});
                } else {
                # 2.1 Case duration
                        $case_db_result = case_duration_result($c_ref->{duration},$c_ref->{warning},$c_ref->{critical});
                        $case_result = $ERRDB2NAG{$case_db_result};
                        $case_output = sprintf($ERRDB{$case_db_result},
                                "case",$c_ref->{name},$c_ref->{duration},($case_db_result == 2 ? $c_ref->{warning} : $c_ref->{critical}));
                }
                # 2.2 Step duration
                foreach my $s_ref (@{$self->{steps}->{$c_ref->{id}}}) {
                        if (step_duration_result($s_ref->{duration}, $s_ref->{warning})) {
                                $case_output .= sprintf($ERRDB{1}, $s_ref->{name},$s_ref->{duration},$s_ref->{warning});
                                $case_result = worststate($case_db_result,1);
                        }
                        $self->add_perfdata(sprintf("%s=%0.2fs;%d;;;",$s_ref->{name}, $s_ref->{duration}, $s_ref->{warning}));
                }
                # final case result
                $self->add_nagios($case_result, sprintf("%s %s", $STATELABELS{$case_result}, $case_output));
                $self->add_perfdata(sprintf("%s=%0.2fs;%d;%d;;",$c_ref->{name},$c_ref->{duration},$c_ref->{warning},$c_ref->{critical}));
        }
        if ($params{mode} =~ /my::sahi::suite/) {

        }

}

################################################################################
#    H E L P E R   F U N C T I O N S
################################################################################

sub get_suite {
	my %params = @_; 
	my @suite = $params{handle}->fetchrow_array(q{
		SELECT ss.id,ss.name,ss.warning,ss.critical
		FROM sahi_suites ss, sahi_jobs sj
		WHERE (ss.name = ?) and (ss.guid = sj.guid)
		ORDER BY ss.id DESC LIMIT 1
	}, $params{name} );
	if (! $suite[0] =~ /\d+/) {    
		printf("UNKNOWN: Could not find a sahi suite %s. Re-Check parameter --name!", $params{name});
		exit 3;                        
	}
	my %suitehash;
	@suitehash{qw(id name warning critical)} = @suite;
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
		@caseshash{qw(id result name start stop warning critical sahi_suites_id duration time msg)} = @$c_ref;
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





				
