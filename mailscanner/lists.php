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

require_once(__DIR__ . '/functions.php');

session_start();
require(__DIR__ . '/login.function.php');

html_start("Whitelist/Blacklist", 0, false, false);

$url_type = (isset($_GET['type']) ? sanitizeInput($_GET['type']) : '');
$url_type = htmlentities($url_type);
$url_type = safe_value($url_type);

$url_to = (isset($_GET['to']) ? sanitizeInput($_GET['to']) : '');
$url_to = htmlentities($url_to);
$url_to = safe_value($url_to);

$url_host = (isset($_GET['host']) ? sanitizeInput($_GET['host']) : '');
$url_host = htmlentities($url_host);
$url_host = safe_value($url_host);

$url_from = (isset($_GET['from']) ? sanitizeInput($_GET['from']) : '');
$url_from = htmlentities($url_from);
$url_from = safe_value($url_from);

$url_submit = (isset($_GET['submit']) ? sanitizeInput($_GET['submit']) : '');
$url_submit = htmlentities($url_submit);
$url_submit = safe_value($url_submit);

$url_list = (isset($_GET['list']) ? sanitizeInput($_GET['list']) : '');
$url_list = htmlentities($url_list);
$url_list = safe_value($url_list);

$url_domain = (isset($_GET['domain']) ? sanitizeInput($_GET['domain']) : '');
$url_domain = htmlentities($url_domain);
$url_domain = safe_value($url_domain);

$url_id = (isset($_GET['id']) ? sanitizeInput($_GET['id']) : '');
$url_id = htmlentities($url_id);
$url_id = safe_value($url_id);

// Split user/domain if necessary (from detail.php)
$touser = '';
$to_domain = '';
if (preg_match('/(\S+)@(\S+)/', $url_to, $split)) {
    $touser = $split[1];
    $to_domain = $split[2];
} else {
    $to_domain = $url_to;
}

// Type
switch ($url_type) {
    case 'h':
        $from = $url_host;
        break;
    case 'f':
        $from = $url_from;
        break;
    default:
        $from = $url_from;
}

$myusername = $_SESSION['myusername'];
// Validate input against the user type
$to_user_filter = array();
$to_domain_filter = array();
$to_address = '';
switch ($_SESSION['user_type']) {
    case 'U': // User
        $sql1 = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
        $result1 = dbquery($sql1);

        if (!$result1) {
            $message = 'Invalid query: ' . mysql_error() . "\n";
            $message .= 'Whole query: ' . $sql1;
            die($message);
        }
        while ($row = mysql_fetch_array($result1)) {
            $filter[] = $row['filter'];
        }
        foreach ($filter as $user_filter_check) {
            if (preg_match("/^[^@]{1,64}@[^@]{1,255}$/", $user_filter_check)) {
                $user_filter[] = $user_filter_check;
            }
        }
        $user_filter[] = $myusername;
        foreach ($user_filter as $tempvar) {
            if (strpos($tempvar, '@')) {
                $ar = explode("@", $tempvar);
                $username = $ar[0];
                $domainname = $ar[1];
                $to_user_filter[] = $username;
                $to_domain_filter[] = $domainname;
            }
        }
        $to_user_filter = array_unique($to_user_filter);
        $to_domain_filter = array_unique($to_domain_filter);
        break;
    case 'D': // Domain Admin
        $sql1 = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
        $result1 = dbquery($sql1);

        if (!$result1) {
            $message = 'Invalid query: ' . mysql_error() . "\n";
            $message .= 'Whole query: ' . $sql1;
            die($message);
        }
        while ($row = mysql_fetch_array($result1)) {
            $to_domain_filter[] = $row['filter'];
        }
        if (strpos($_SESSION['myusername'], '@')) {
            $ar = explode("@", $_SESSION['myusername']);
            $domainname = $ar[1];
            $to_domain_filter[] = $domainname;
        }
        $to_domain_filter = array_unique($to_domain_filter);
        break;
    case 'A': // Administrator
        $to_address = 'default';
        break;
}
switch (true) {
    case(!empty($url_to)):
        $to_address = $url_to;
        if (!empty($url_domain)) {
            $to_address .= '@' . $url_domain;
        }
        break;
    case(!empty($url_domain)):
        $to_address = $url_domain;
        break;
}

// Submitted
if ($url_submit == __('add07')) {
    // Check input is valid
    if (empty($url_list)) {
        $errors[] = "You must select a list to create the entry.";
    }
    if (empty($from)) {
        $errors[] = "You must enter a from address (user@domain, domain or IP).";
    }

    $to_domain = strtolower($url_domain);
    // Insert the data
    if (!isset($errors)) {
        switch ($url_list) {
            case 'w': // Whitelist
                $list = 'whitelist';
                break;
            case 'b': // Blacklist
                $list = 'blacklist';
                break;
        }
        $sql = 'REPLACE INTO ' . $list . ' (to_address, to_domain, from_address) VALUES';
        $sql .= '(\'' . mysql_real_escape_string($to_address);
        $sql .= '\',\'' . mysql_real_escape_string($to_domain);
        $sql .= '\',\'' . mysql_real_escape_string($from) . '\')';
        @dbquery($sql);
        audit_log("Added " . $from . " to " . $list . " for " . $to_address);
        //unset($from);
        //unset($url_list);
    }
}

// Delete
if ($url_submit == 'Delete') {
    $id = $url_id;
    switch ($url_list) {
        case 'w':
            $list = 'whitelist';
            break;
        case 'b':
            $list = 'blacklist';
            break;
    }

    switch ($_SESSION['user_type']) {
        case 'U':
            $sql = "DELETE FROM $list WHERE id='$id' AND to_address='$to_address'";
            audit_log("Removed entry $id from $list");
            break;
        case 'D':
            $sql = "DELETE FROM $list WHERE id='$id' AND to_domain='$to_domain'";
            audit_log("Removed entry $id from $list");
            break;
        case 'A':
            $sql = "DELETE FROM $list WHERE id='$id'";
            audit_log("Removed entry $id from $list");
            break;
    }

    $id = mysql_real_escape_string($url_id);
    dbquery($sql);
}

function build_table($sql, $list)
{
    global $bg_colors;

    $sth = dbquery($sql);
    $rows = mysql_num_rows($sth);
    if ($rows > 0) {
        echo '<table class="blackwhitelist">' . "\n";
        echo ' <tr>' . "\n";
        echo '  <th>' . __('from07') . '</th>' . "\n";
        echo '  <th>' . __('to07') . '</th>' . "\n";
        echo '  <th>' . __('action07') . '</th>' . "\n";
        echo ' </tr>' . "\n";
        $i = 1;
        while ($row = mysql_fetch_row($sth)) {
            $i = 1 - $i;
            $bgcolor = $bg_colors[$i];
            echo ' <tr>' . "\n";
            echo '  <td style="background-color: ' . $bgcolor . '; ">' . $row[1] . '</td>' . "\n";
            echo '  <td style="background-color: ' . $bgcolor . '; ">' . $row[2] . '</td>' . "\n";
            echo '  <td style="background-color: ' . $bgcolor . '; "><a href="lists.php?submit=Delete&amp;id=' . $row[0] . '&amp;to=' . $row[2] . '&amp;list=' . $list . '">' . __('delete07') . '</a><td>' . "\n";
            echo ' </tr>' . "\n";
        }
        echo '</table>' . "\n";
    } else {
        echo "No entries found.\n";
    }
}

echo '
<form action="lists.php">
<table cellspacing="1" class="mail">
 <tr>
  <th colspan=2>' . __('addwlbl07') . '</th>
 </tr>
 <tr>
  <td class="heading">' . __('from07') . ':</td>
  <td><input type="text" name="from" size=50 value="' . $from . '"></td>
 </tr>
 <tr>
  <td class="heading">' . __('to07') . ':</td>';

switch ($_SESSION['user_type']) {
    case 'A':
        echo '<td><input type="text" name="to" size=22 value="' . $touser . '">@<input type="text" name="domain" size=25 value="' . $to_domain . '"></td>';
        break;
    case 'U':
        echo '<td> <select name="to">';
        foreach ($to_user_filter as $to_user_selection) {
            if ($touser == $to_user_selection) {
                echo '<option selected>' . $to_user_selection . '</option>';
            } else {
                echo '<option>' . $to_user_selection . '</option>';
            }
        }
        echo '</select>@<select name="domain">';
        foreach ($to_domain_filter as $to_domain_selection) {
            if ($to_domain == $to_domain_selection) {
                echo '<option selected>' . $to_domain_selection . '</option>';
            } else {
                echo '<option>' . $to_domain_selection . '</option>';
            }
        }
        echo '</td>';
        break;
    case 'D':
        echo '<td><input type="text" name="to" size=22 value="' . $touser . '">@<select name="domain">';
        foreach ($to_domain_filter as $to_domain_selection) {
            if ($to_domain == $to_domain_selection) {
                echo '<option selected>' . $to_domain_selection . '</option>';
            } else {
                echo '<option>' . $to_domain_selection . '</option>';
            }
        }
        break;
}

echo '
 </tr>
 <tr>
  <td class="heading">' . __('list07') . ':</td>
  <td>';

$w = '';
$b = '';
switch ($url_list) {
    case 'w':
        $w = 'CHECKED';
        break;
    case 'b':
        $b = 'CHECKED';
        break;
}
echo '   <input type="radio" value="w" name="list" ' . $w . '>'. __('wl07') . '&nbsp;&nbsp;' . "\n";
echo '   <input type="radio" value="b" name="list" ' . $b . '>' . __('bl07') . '' . "\n";

echo '  </td>
 </tr>
 <tr>
  <td class="heading">' . __('action07') . ':</td>
  <td><input type="reset" value="' . __('reset07') . '">&nbsp;&nbsp;<input type="submit" value="' . __('add07') . '" name="submit"></td>
 </tr>';
if (isset($errors)) {
    echo '<tr>
  <td class="heading">Errors:</td>
  <td>' . implode("<br>", $errors) . '</td>
 </tr>';
}
echo '</table>
   </form>
   <br>
<table cellspacing="1" width="100%" class="mail">
<tr>
  <th class="whitelist">' . __('wl07') . '</th>
  <th class="blacklist">' . __('bl07') . '</th>
</tr>
<tr>
  <td class="blackwhitelist">
    <!-- Whitelist -->';

build_table(
    "SELECT id, from_address, to_address FROM whitelist WHERE " . $_SESSION['global_list'] . " ORDER BY from_address",
    'w'
);
echo '</td>
 <td  class="blackwhitelist">
  <!-- Blacklist -->';
build_table(
    "SELECT id, from_address, to_address FROM blacklist WHERE " . $_SESSION['global_list'] . " ORDER BY from_address",
    'b'
);
echo '</td>
</tr>
</table>';

// Add the footer
html_end();
// close the connection to the Database
dbclose();
