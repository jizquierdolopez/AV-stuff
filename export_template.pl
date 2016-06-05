#!/usr/bin/perl
use DBI();

#Ripped from ossim-db
my $user = `grep ^user= /etc/ossim/ossim_setup.conf | cut -f 2 -d "=" | sed '/^\$/d'`;
my $pass = `grep ^pass= /etc/ossim/ossim_setup.conf | cut -f 2 -d "=" | sed '/^\$/d'`;
#Sed should do this but just in case...
chomp($user);chomp($pass);
#Init DB. We'll use this everytime
my $dbh = DBI->connect("DBI:mysql:database=alienvault;host=127.0.0.1",$user, $pass, {'RaiseError' => 1});


$tname = "";
$i=@ARGV;
foreach(@ARGV)
{
 $tname .= "$_";
 $i -= 1;
 if ($i) {$tname .=" ";} 
}

$fname = 'template_exported.sql';
open ($file, '>', $fname) or die " >>>> Could not open file '$fname' $!";

#Template name and ID
$template = 'select hex(id) as tid, name from acl_templates where name like "'.$tname.'"';

#Run
my $sth = $dbh->prepare($template);
$sth->execute();


while (my $res = $sth->fetchrow_hashref()) {
    $tid = $res->{'tid'};
    $tname = $res->{'name'};
    print $file "insert ignore into acl_templates values (unhex('$tid'),'$tname');\n";
}

#Template details
$template = 'select ac_perm_id as permid  from acl_templates_perms where ac_templates_id = unhex("'.$tid.'")';

#Run
my $sth = $dbh->prepare($template);
$sth->execute();


while (my $res = $sth->fetchrow_hashref()) {
    $tperm = $res->{'permid'};
    print $file "insert ignore into acl_templates_perms values (unhex('$tid'),$tperm);\n";
}

close $file;

