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
require_once(__DIR__ . '/lib/password.php');
require_once(__DIR__ . '/lib/hash_equals.php');

session_start();

if (isset($_SERVER['PHP_AUTH_USER'])) {
    $myusername = $_SERVER['PHP_AUTH_USER'];
    $mypassword = $_SERVER['PHP_AUTH_PW'];
} else {
    // Define $myusername and $mypassword
    $myusername = $_POST['myusername'];
    $mypassword = $_POST['mypassword'];
}
$myusername = sanitizeInput($myusername);
$mypassword = sanitizeInput($mypassword);
if ((USE_LDAP === true) && (($result = ldap_authenticate($myusername, $mypassword)) !== null)) {
    $_SESSION['user_ldap'] = '1';
    $myusername = safe_value($result);
} else {
    if ($mypassword != '') {
        $myusername = safe_value($myusername);
        $mypassword = safe_value($mypassword);
    } else {
        header("Location: login.php?error=emptypassword");
        die();
    }
}

$sql = "SELECT * FROM users WHERE username='$myusername'";
$result = dbquery($sql);

if (!$result) {
    $message = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $sql;
    die($message);
}

// mysql_num_row is counting table row
$usercount = mysql_num_rows($result);
if ($usercount == 0) {
    //no user found, redirect to login
    dbclose();
    header("Location: login.php?error=baduser");
} else {
    if (USE_LDAP === false) {
        $passwordInDb = mysql_result($result, 0, 'password');
        if (!password_verify($mypassword, $passwordInDb)) {
            if (!hash_equals(md5($mypassword), $passwordInDb)) {
                header("Location: login.php?error=baduser");
                die();
            } else {
                $newPasswordHash = password_hash($mypassword, PASSWORD_DEFAULT);
                updateUserPasswordHash($myusername, $newPasswordHash);
            }
        } else {
            // upgraded password is valid, continue as normal
            if (password_needs_rehash($passwordInDb, PASSWORD_DEFAULT)) {
                $newPasswordHash = password_hash($mypassword, PASSWORD_DEFAULT);
                updateUserPasswordHash($myusername, $newPasswordHash);
            }
        }
    }

    $fullname = mysql_result($result, 0, 'fullname');
    $usertype = mysql_result($result, 0, 'type');

    $sql_userfilter = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
    $result_userfilter = dbquery($sql_userfilter);

    if (!$result_userfilter) {
        $message = 'Invalid query: ' . mysql_error() . "\n";
        $message .= 'Whole query: ' . $sql_userfilter;
        die($message);
    }

    $filter[] = $myusername;
    while ($row = mysql_fetch_array($result_userfilter)) {
        $filter[] = $row['filter'];
    }

    $global_filter = address_filter_sql($filter, $usertype);

    switch ($usertype) {
        case "A":
            $global_list = "1=1";
            break;
        case "D":
            if (strpos($myusername, '@')) {
                $ar = explode("@", $myusername);
                $domainname = $ar[1];
                if ((defined('FILTER_TO_ONLY') && FILTER_TO_ONLY)) {
                    $global_filter = $global_filter . " OR to_domain='$domainname'";
                } else {
                    $global_filter = $global_filter . " OR to_domain='$domainname' OR from_domain='$domainname'";
                }
                $global_list = "to_domain='$domainname'";
            } else {
                $global_list = "to_address='$myusername'";
                foreach ($filter as $to_address) {
                    $global_list .= " OR to_address='$to_address'";
                }
            }
            break;
        case "U":
            $global_list = "to_address='$myusername'";
            foreach ($filter as $to_address) {
                $global_list .= " OR to_address='$to_address'";
            }
            break;
    }

    // If result matched $myusername and $mypassword, table row must be 1 row
    if ($usercount == 1) {
        // Register $myusername, $mypassword and redirect to file "login_success.php"
        $_SESSION['myusername'] = $myusername;
        $_SESSION['fullname'] = $fullname;
        $_SESSION['user_type'] = (isset($usertype) ? $usertype : '');
        $_SESSION['domain'] = (isset($domainname) ? $domainname : '');
        $_SESSION['global_filter'] = '(' . $global_filter . ')';
        $_SESSION['global_list'] = (isset($global_list) ? $global_list : '');
        $_SESSION['global_array'] = $filter;
        $redirect_url = 'index.php';
        if (isset($_SESSION['REQUEST_URI'])) {
            $redirect_url = sanitizeInput($_SESSION['REQUEST_URI']);
            unset($_SESSION['REQUEST_URI']);
        }
        header('Location: ' . $redirect_url);
    } else {
        header('Location: login.php?error=baduser');
    }

    // close any DB connections
    dbclose();
}
