<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2015  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * In addition, as a special exception, the copyright holder gives permission to link the code of this program with
 * those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
 * that use the same license as those files), and distribute linked combinations including the two.
 * You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 * PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 * your version of the program, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your version.
 *
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// Include of necessary functions
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/filter.inc');

// Authentication checking
session_start();
require(__DIR__ . '/login.function.php');

// add the header information such as the logo, search, menu, ....
$filter = html_start("Virus Report", 0, false, true);

// Get a list of virus scanners from MailScanner.conf
$scanner = array();
$scanners = explode(" ", get_conf_var("virusscanners"));
foreach ($scanners as $vscanner) {
    switch ($vscanner) {
        case("sophos"):
            $scanner[$vscanner]['name'] = "Sophos";
            $scanner[$vscanner]['regexp'] = "/(>>>) Virus \'(\S+)\' found/";
            break;
        case("sophossavi"):
            $scanner[$vscanner]['name'] = "Sophos SAVI";
            $scanner[$vscanner]['regexp'] = "/(\S+) was infected by (\S+)/";
            break;
        case("clamav"):
            $scanner[$vscanner]['name'] = "ClamAV";
            $scanner[$vscanner]['regexp'] = "/(.+) contains (\S+)/";
            break;
        case("clamd"):
            $scanner[$vscanner]['name'] = "ClamD";
            #ORIG#$scanner[$vscanner]['regexp'] = "/(.+) contains (\S+)/";
            $scanner[$vscanner]['regexp'] = "/(.+) was infected: (\S+)/";
            break;
        case("clamavmodule"):
            $scanner[$vscanner]['name'] = "Clam AV Module";
            $scanner[$vscanner]['regexp'] = "/(.+) was infected: (\S+)/";
            break;
        case("f-prot"):
            $scanner[$vscanner]['name'] = "F-Prot";
            $scanner[$vscanner]['regexp'] = "/(.+) Infection: (\S+)/";
            break;
        case("mcafee"):
        case("mcafee6"):
            $scanner[$vscanner]['name'] = "McAfee";
            $scanner[$vscanner]['regexp'] = "/(.+) Found the (\S+) (trojan|virus) !!!/";
            break;
        case("f-secure"):
            $scanner[$vscanner]['name'] = "F-Secure";
            $scanner[$vscanner]['regexp'] = "/(.+) Infected: (\S+)/";
            break;
        case("trend"):
            $scanner[$vscanner]['name'] = "Trend";
            $scanner[$vscanner]['regexp'] = "/(Found virus) (\S+) in file (\S+)/";
            break;
        case("bitdefender"):
            $scanner[$vscanner]['name'] = "BitDefender";
            $scanner[$vscanner]['regexp'] = "/(.+) Found virus (\S+)/";
            break;
        case("kaspersky-4.5"):
            $scanner[$vscanner]['name'] = "Kaspersky";
            $scanner[$vscanner]['regexp'] = "/(.+) INFECTED (\S+)/";
            break;
        case("etrust"):
            $scanner[$vscanner]['name'] = "E-Trust";
            $scanner[$vscanner]['regexp'] = "/(\S+) is infected by virus: (\S+)/";
            break;
        case("avg"):
            $scanner[$vscanner]['name'] = "AVG";
            $scanner[$vscanner]['regexp'] = "/(Found virus) (\S+) in file (\S+)/";
            break;
        case("norman"):
            $scanner[$vscanner]['name'] = "Norman";
            $scanner[$vscanner]['regexp'] = "/(Found virus) (\S+) in file (\S+)/";
            break;
        case("nod32-1.99"):
            $scanner[$vscanner]['name'] = "NOD32';
            $scanner[$vscanner]['regexp'] = '/(Found virus) (\S+) in (\S+)/";
            break;
        case("antivir"):
            $scanner[$vscanner]['name'] = "AntiVir";
            $scanner[$vscanner]['regexp'] = "/(ALERT:) \[(\S+) \S+\]/";
            break;
    }
}

$sql = "
SELECT
 DATE_FORMAT(timestamp, '" . DATE_FORMAT . " " . TIME_FORMAT . "') as timestamp,
 report
FROM
 maillog
WHERE
 virusinfected = 1
AND
 report IS NOT NULL
" . $filter->CreateSQL() . "
ORDER BY
 date ASC, time ASC";

$result = dbquery($sql);
if (!mysql_num_rows($result) > 0) {
    die("Error: no rows retrieved from database\n");
}

$virus_array = array();

while ($row = mysql_fetch_object($result)) {
    foreach ($scanner as $scan => $vals) {
        if (preg_match($vals['regexp'], $row->report, $virus_report)) {
            $virus = $virus_report[2];
            if (!isset($virus_array[$virus])) {
                $virus_array[$virus]['first_seen'] = $row->timestamp;
                $virus_array[$virus]['scanner'] = $vals['name'];
            }
            if (isset($virus_array[$virus]['count'])) {
                $virus_array[$virus]['count']++;
            } else {
                $virus_array[$virus]['count'] = 1;
            }
        }
    }
}

reset($virus_array);
foreach ($virus_array as $key => $row) {
    $virus_count[$key] = $row['count'];
}
array_multisort($virus_count, SORT_DESC, $virus_array);

$count = 0;
$data_names = array();
while ((list($key, $val) = each($virus_array))) {
    $data[] = $val['count'];
    $data_names[] = "$key";
    $data_first_seen[] = $val['first_seen'];
    $data_scanner[] = $val['scanner'];
    $count++;
}

// HTML Code
echo "<TABLE BORDER=\"0\" CELLPADDING=\"10\" CELLSPACING=\"0\" WIDTH=\"100%\">";
echo "<TR>";
echo " <TD ALIGN=\"CENTER\"><IMG SRC=\"" . IMAGES_DIR . MS_LOGO . "\" ALT=\"MailScanner Logo\"></TD>";
echo "</TR>";
echo "<TR>";
echo "<TD ALIGN=\"CENTER\">";
echo "<TABLE WIDTH=\"500\">";
echo "<TR BGCOLOR=\"#F7CE4A\">";
echo "<TH>Virus</TH>";
echo "<TH>Scanner</TH>";
echo "<TH>First Seen</TH>";
echo "<TH>Count</TH>";
echo "</TR>";

// Write the data in table
for ($i = 0; $i < count($data_names); $i++) {
    echo "<TR BGCOLOR=\"#EBEBEB\">
 <TD>$data_names[$i]</TD>
 <TD>$data_scanner[$i]</TD>
 <TD ALIGN=\"RIGHT\">$data_first_seen[$i]</TD>
 <TD ALIGN=\"RIGHT\">" . number_format($data[$i]) . "</TD>
</TR>\n";
}

echo "
  </TABLE>
 </TD>
</TR>
</TABLE>";

// Add footer
html_end();
// Close any open db connections
dbclose();
