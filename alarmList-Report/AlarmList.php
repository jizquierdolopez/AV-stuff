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

$ctx = $dDB['ctx'];
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

if (!Session::logcheck_bool("analysis-menu", "ReportsAlarmReport")) {
	?>

    <table align="center" cellpadding="0" cellspacing="0" class="table1 noborder">
        <tr><td class="headerpr"><?php echo $rtitle ?></td></tr>
        <tr><td class="nobborder"><?php Session::unallowed_section(null, false, "analysis-menu", "ReportsAlarmReport"); ?></td></tr>
    </table>
<?php
} else {

    $htmlPdfReport->setBookmark(_($dDBdata['srtype'].' - '.$dDBdata['srname']));


    // queries list 
    $queries = explode(";",$dDBdata['force_sql']);
    $queries[0] = $queries[0] . " " . $where . " " . $datefilter . " " . $order;

    // Db handler
    $db     = new ossim_db();
    $dbconn = $db->connect();
    $rsp = $dbconn->Execute($queries[0]);


    // PDF TITLE
    $htmlPdfReport->set($htmlPdfReport->newTitle($rtitle,$date_from,$date_to,$dDBdata["notes"]));
        
    // HTML TITLE
    ?>
    <table align="center" cellpadding="0" cellspacing="0" class="table1 noborder">
        <tr><td class="headerpr">Report - <?=$rtitle ?> - STATUS -> <?=$status?></td></tr>
    </table>
 
    <?// PDF ALARM TABLE HEADER?>
    <?$htmlPdfReport->set('
    <table align="center" cellpadding="0" cellspacing="0" class="table1 noborder">
        <tr>
            <th class="headerpr">Date</th>
            <th class="headerpr">Status</th>
            <th class="headerpr">Name</th>
            <th class="headerpr">Soruce</th>
            <th class="headerpr">Destination</th>
            <th class="headerpr">Risk</th>
        </tr>

    ');


    // HTML ALARM TABLE HEADER
?>
    <table align="center" cellpadding="0" cellspacing="0" class="table1 noborder">
        <tr>
            <th class="headerpr">Date</td>
            <th class="headerpr">Status</td>
            <th class="headerpr">Name</td>
            <th class="headerpr">Soruce</td>
            <th class="headerpr">Destination</td>
            <th class="headerpr">Risk</td>
        </tr>

    <? while (!$rsp->EOF) 
    {
        $alarmstotal ++;
        $myrow = $rsp->fields;      

        // var setup

        $date = $myrow['altime'];
        $name = str_replace("directive_event: ", "", $myrow['alname']);
        $srcip = ($myrow['srcname'] != '') ? $myrow['srcname']." (".$myrow['srcip'].")" : $myrow['srcip'];
        $dstip = ($myrow['dstname'] != '') ? $myrow['dstname']." (".$myrow['dstip'].")" : $myrow['dstip'];
        $risk = $myrow['alrisk'];
        $cat = $myrow['catname'];
        $sub = $myrow['catsub'];
        $alstatus = $myrow['status'];

        // PULSE INFO
        $pulsename = "";
        if ($myrow['pulse_id'] != "") 
        {
            $otx   = new Otx();
            $pulse = $otx->get_pulse_detail(strtolower($myrow['pulse_id']), TRUE);
            if (!empty($pulse['name']))                        
            {
                $pulsename = Util::htmlentities(trim($pulse['name']), ENT_NOQUOTES);
            }
        }

        // ALARM TABLE BODY
    ?>
        <tr>
            <td class="nobborder"><?=$date?></td>
            <td class="nobborder"><?=$alstatus?></td>
 
            <td class="nobborder"><b><?=$name?><?=$pulsename?></b><br/><?=$cat?> -> <?=$sub?></td>
            <td class="nobborder"><?=$srcip?></td>
            <td class="nobborder"><?=$dstip?></td>
            <td class="nobborder"><?=$risk?></td>
        </tr>

        <?
        // PDF ALARM TABLE BODY
        $htmlPdfReport->set('
            <tr>
                <td style="width:35mm;" class="nobborder">'.$date.'</td>
                <td style="width:15mm;" class="nobborder">'.$alstatus.'</td>
                <td style="width:60mm;" class="nobborder"><b>'.$name.$pulsename.'</b><br/>'.$cat.' -> '.$sub.'</td>
                <td style="width:30mm;" class="nobborder">'.$srcip.'</td>
                <td style="width:30mm;" class="nobborder">'.$dstip.'</td>
                <td style="width:5mm;" class="nobborder">'.$risk.'</td>
            </tr>
        ');


        $rsp->MoveNext();


    // TABLE END
    }?>

    </table>
    <br /><br /><br /><br />

    <?$htmlPdfReport->set('
        </table>
        <br /><br /><br /><br />
        <div class="systemdebug">[ '._("Alarms total").' '.$alarmstotal.' ]</div>
    ');

}
    ?>



<div class='systemdebug'><?php echo '[ '._('Alarms total ').' '.$alarmstotal.' ]'?></div>



