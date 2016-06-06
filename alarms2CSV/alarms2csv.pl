#!/usr/bin/perl
use DBI();
use Date::Format;
$path = "/tmp/";

#Ripped from ossim-db
my $user = `grep ^user= /etc/ossim/ossim_setup.conf | cut -f 2 -d "=" | sed '/^\$/d'`;
my $pass = `grep ^pass= /etc/ossim/ossim_setup.conf | cut -f 2 -d "=" | sed '/^\$/d'`;
#Sed should do this but just in case...
chomp($user);chomp($pass);
#Init DB. We'll use this everytime
my $dbh = DBI->connect("DBI:mysql:database=alienvault;host=127.0.0.1",$user, $pass, {'RaiseError' => 1});


$fname = $path.'openalarms-'.time2str ("%y%m%d%H%M%S", time).'.csv';
open ($file, '>', $fname) or die " >>>> Could not open file '$fname' $!";

#open alarms
$openAlarms = 'select al.timestamp as altime, al.plugin_sid as plugin_sid, al.plugin_id as plugin_id, hex(al.event_id) as event_id, hex(al.corr_engine_ctx) as ctx, pp.name as alname, inet6_ntoa(al.src_ip) as srcip, srchost.hostname as srcname, inet6_ntoa(al.dst_ip) as dstip, dsthost.hostname as dstname, al.risk as alrisk, acat.name as catname, hex(otx.pulse_id) as pulse_id, atax.subcategory as catsub from alarm as al left join plugin_sid as pp on pp.plugin_id = al.plugin_id and pp.sid = al.plugin_sid left join host_ip as srcip on srcip.ip = al.src_ip left join host as srchost on srcip.host_id = srchost.id left join host_ip as dstip on dstip.ip = al.dst_ip left join host as dsthost on dstip.host_id = dsthost.id left join alarm_taxonomy as atax on atax.sid = al.plugin_sid and atax.engine_id = al.corr_engine_ctx left join alarm_categories as acat on acat.id = atax.category left join otx_data as otx on otx.event_id = al.event_id where al.status <> "closed";';


#Run
my $sth = $dbh->prepare($openAlarms);
$sth->execute();

print $file '"Date","Name","Source","Destination","Risk","Intent & Strategy","Method"'." \n";

while (my $res = $sth->fetchrow_hashref()) {
    $srcip = ($res->{'srcname'} ne  '') ? "$res->{'srcname'} ($res->{'srcip'})" : "$res->{'srcip'}";
    $dstip = ($res->{'dstname'} ne  '') ? "$res->{'dstname'} ($res->{'dstip'})" : "$res->{'dstip'}";
    $res->{'alname'} =~ s/directive_event: //;
    print $file '"'.$res->{"altime"} . '","' . $res->{"alname"} . '","' . $srcip . '","' . $dstip . '","' . $res->{"alrisk"}. '","' . $res->{"catname"}.'","' . $res->{"catsub"}.'"'." \n";
}

close $file;

