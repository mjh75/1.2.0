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

if ($_SESSION['user_type'] != 'A') {
    header("Location: index.php");
} else {
    html_start("Rules");

    // limit accessible files to the ones in MailScanner etc directory
    $MailscannerEtcDir = realpath(get_conf_var('%etc-dir%'));
    if (!isset($_GET['file'])) {
        $FilePath = false;
    } else {
        $FilePath = realpath(sanitizeInput($_GET['file']));
    }

    if ($FilePath === false || strpos($FilePath, $MailscannerEtcDir) !== 0) {
        //Directory Traversal
        echo "Directory traversal attempt blocked.\n";
    } else {
        echo '<table cellspacing="1" class="maildetail" width="100%">' . "\n";
        echo '<tr><td class="heading">File: ' . $FilePath . '</td></tr>' . "\n";
        echo '<tr><td><pre>' . "\n";
        if ($fh = @@fopen($FilePath, 'r')) {
            while (!feof($fh)) {
                $line = rtrim(fgets($fh, 4096));
                if (isset($_GET['strip_comments']) && $_GET['strip_comments']) {
                    if (!preg_match('/^#/', $line) && !preg_match('/^$/', $line)) {
                        echo $line . "\n";
                    }
                } else {
                    echo $line . "\n";
                }
            }
            fclose($fh);
        } else {
            echo "Unable to open file.\n";
        }
        echo '</pre></td></tr>' . "\n";
        echo '</table>' . "\n";
    }
    // Add the footer
    html_end();
}
// close the connection to the Database
dbclose();
