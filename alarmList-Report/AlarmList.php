<?php
/**
*
* Copyright (c) 2007-2013 AlienVault
* All rights reserved.
*
* @link       https://www.alienvault.com/
*
*   TODO 
*   include report summary (total close alarm, total open alarm)
*   include sql date where conditions
*   filter by close, open, both
*   filter by entity
*
*/

include_once 'general.php';

// Troubleshoting 
// print_r($dDBdata)


// initialize var

$NUM_HOSTS = (intval($dDBdata["top"])>0) ? $dDBdata["top"] : 10;


$date_from = ($dDB['date_from'] != "") ? $dDB['date_from'] : strftime("%Y-%m-%d", time()-$month);
$date_to = ($dDB['date_to'] != "") ? $dDB['date_to'] : strftime("%Y-%m-%d", time());

//dates
$date_from_sql = "'".$date_from." 00:00:00'";
$date_to_sql = "'".$date_to." 23:59:59'";
$datefilter = "AND (al.timestamp BETWEEN $date_from_sql AND $date_to_sql)";

$filterctx = $dDB['ctx'];
$target = "ip_dst";
$report_type="alarm";
$close = ($dDBdata['close']) ? "status = 'closed'" : "";
$open = ($dDBdata['open']) ? "status = 'open'" : "";
$where = "";
if ($dDBdata['open'] && $dDBdata['close']) {
    $where = $close ." OR ".$open;
    $status = "Open and Closed";
}elseif ($dDBdata['open']) {
    $where = $open;
    $status = "Open";
}elseif ($dDBdata['close']) {
    $where = $close;
    $status = "Closed";
}
$order = "order by al.timestamp desc";
$alarmstotal = 0;

// $geoloc = new Geolocation("/usr/share/geoip/GeoLiteCity.dat");
    
$title = _("Alarms List");
$rtitle = _($dDBdata['srtype'])." - ".$title;

// queries list 
$queries = explode(";",$dDBdata['force_sql']);
$queries[0] = $queries[0] . " " . $where . " " . $datefilter . " " . $order;

// Db handler
$db     = new ossim_db();
$dbconn = $db->connect();
$rsp = $dbconn->Execute($queries[0]);
$alarms = array();

// GET ALARMS
while (!$rsp->EOF) 
{

    $alarmstotal ++;
    $myrow = $rsp->fields;      

    // var setup
    $alarm = array();
    $alarm['date'] = $myrow['altime'];
    $alarm['name'] = str_replace("directive_event: ", "", $myrow['alname']);
    $alarm['srcip'] = ($myrow['srcname'] != '') ? $myrow['srcname']." (".$myrow['srcip'].")" : $myrow['srcip'];
    $alarm['dstip'] = ($myrow['dstname'] != '') ? $myrow['dstname']." (".$myrow['dstip'].")" : $myrow['dstip'];
    $alarm['risk'] = $myrow['alrisk'];
    $alarm['cat'] = $myrow['catname'];
    $alarm['sub'] = $myrow['catsub'];
    $alarm['status'] = $myrow['status'];
    $alarm['ctx'] = $myrow['entname'];

    // PULSE INFO
    $alarm['pulsename'] = "";
    if ($myrow['pulse_id'] != "") 
    {
        $otx   = new Otx();
        $pulse = $otx->get_pulse_detail(strtolower($myrow['pulse_id']), TRUE);
        if (!empty($pulse['name']))                        
        {
            $alarm['pulsename'] = Util::htmlentities(trim($pulse['name']), ENT_NOQUOTES);
        }
    }

    $alarms[] = $alarm;    
    $rsp->MoveNext();

}

if ($_GET['pdf'] == 'TRUE') {
    // PDF STUFF
    $htmlPdfReport->setBookmark(_($dDBdata['srtype'].' - '.$dDBdata['srname']));

    // PDF TITLE
    $htmlPdfReport->set($htmlPdfReport->newTitle($rtitle,$date_from,$date_to,$dDBdata["notes"]));

    // PDF ALARM TABLE HEADER

    $htmlPdfReport->set('
    <table align="center" cellpadding="0" cellspacing="0" class="table1 noborder">
        <tr>
            <th class="headerpr">Date</th>
            <th class="headerpr">Status</th>
            <th class="headerpr">Name</th>
            <th class="headerpr">Source</th>
            <th class="headerpr">Destination</th>
            <th class="headerpr">Risk</th>
        </tr>

    ');

    // PDF ALARM TABLE BODY
    foreach ($alarms as $alarm) 
    {

    $htmlPdfReport->set('
        <tr>
            <td style="width:35mm;" class="nobborder">'.$alarm['date'].'</td>
            <td style="width:15mm;" class="nobborder">'.$alarm['status'].'</td>
            <td style="width:60mm;" class="nobborder">'.$alarm['ctx'].'<br /><b>'.$alarm['name'].$alarm['pulsename'].'</b><br/>'.$alarm['cat'].' -> '.$alarm['sub'].'</td>
            <td style="width:30mm;" class="nobborder">'.$alarm['srcip'].'</td>
            <td style="width:30mm;" class="nobborder">'.$alarm['dstip'].'</td>
            <td style="width:5mm;" class="nobborder">'.$alarm['risk'].'</td>
        </tr>
    ');
    }

    $htmlPdfReport->set('
        </table>
        <br /><br /><br /><br />
        <div class="systemdebug">[ '._("Alarms total").' '.$alarmstotal.' ]</div>
    ');
}

if (!Session::logcheck_bool("analysis-menu", "ReportsAlarmReport")) {

?>

    <table align="center" cellpadding="0" cellspacing="0" class="table1 noborder">
        <tr><td class="headerpr"><? echo $rtitle ?></td></tr>
        <tr><td class="nobborder"><? Session::unallowed_section(null, false, "analysis-menu", "ReportsAlarmReport"); ?></td></tr>
    </table>

<?

} else {
    // HTML TITLE


if ($_GET['pdf'] != 'TRUE'  ) {
?>
    <table align="center" cellpadding="0" cellspacing="0" class="table1 noborder">
        <tr><td class="headerpr">Report - <?=$rtitle ?> - STATUS -> <?=$status?></td></tr>
    </table>
 
<?
    // HTML ALARM TABLE HEADER
?>

    <table align="center" cellpadding="0" cellspacing="0" class="table1 noborder">
        <tr>
            <th class="headerpr">Date</td>
            <th class="headerpr">Status</td>
            <th class="headerpr">Name</td>
            <th class="headerpr">Source</td>
            <th class="headerpr">Destination</td>
            <th class="headerpr">Risk</td>
        </tr>

<?  
    // ALARM TABLE BODY

    foreach ($alarms as $alarm) 
    {
?>

        <tr>
            <td class="nobborder"><?=$alarm['date']?></td>
            <td class="nobborder"><?=$alarm['status']?></td>
 
            <td class="nobborder"><?=$alarm['ctx']?><br/><b><?=$alarm['name']?><?=$alarm['pulsename']?></b><br/><?=$alarm['cat']?> -> <?=$alarm['sub']?></td>
            <td class="nobborder"><?=$alarm['srcip']?></td>
            <td class="nobborder"><?=$alarm['dstip']?></td>
            <td class="nobborder"><?=$alarm['risk']?></td>
        </tr>

<?
    }

    // TABLE END
?>

    </table>
    <br /><br /><br /><br />
    <div class='systemdebug'><?php echo '[ '._('Alarms total ').' '.$alarmstotal.' ]'?></div>
<?
    }
}
?>
