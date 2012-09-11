package MySahi;
our @ISA = qw(DBD::MySQL::Server);

use Data::Dumper;
use YAML;

sub init {
	my $self = shift;
	my %params = @_;
$DB::single = 1;
  if ($params{mode} =~ /my::sahi::case/) {
	$self->{'dbnow'} = $params{handle}->fetchrow_array(q{
		SELECT UNIX_TIMESTAMP() FROM dual
  	});
	$self->valdiff(\%params, 'dbnow');

	my @case = $params{handle}->fetchrow_array(q{
		SELECT id,name,start,stop,warning,critical,duration,time,msg
		FROM sahi_cases sc
		WHERE sc.name = ?
		ORDER BY sc.id DESC LIMIT 1
	}, $params{name});

	if (! $case[0] =~ /\d+/) {
		printf("UNKNOWN: Could not find a sahi case %s. Re-Check parameter --name!", $params{name});
		exit 3;
	}

	my @steps = $params{handle}->fetchall_array(q{
		SELECT id,name,warning,duration,time
		FROM sahi_steps ss
		WHERE ss.sahi_cases_id = ?
		ORDER BY ss.id
	}, $case[0]);
	
	$self->{case} = \@case;
	$self->{steps} = \@steps;
  }
}

sub nagios {
	my $self = shift; 
	my %params = @_;
	if ($params{mode} =~ /my::sahi::case/) {
		# prüfe case runtime 
		# gibt es überhaupt steps? 
		# pro step: 
		# - vergleiche duration (3) mit warn (2)
		print Dumper @case;
		add_nagios_warning
	};
}


sub check_steps_state {
	my $self = shift; 
	my 
}
