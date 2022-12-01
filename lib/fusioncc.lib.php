<?php
/* 
 * Copyright (C) 2022 ProgiSeize <contact@progiseize.fr>
 *
 * This program and files/directory inner it is free software: you can 
 * redistribute it and/or modify it under the terms of the 
 * GNU Affero General Public License (AGPL) as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AGPL for more details.
 *
 * You should have received a copy of the GNU AGPL
 * along with this program.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
 */

/********************************************/
/*                                          */
/********************************************/
function fusioncc_AdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("fusioncc@fusioncc");

    $h = 0;
    $head = array();

    
    $head[$h][0] = dol_buildpath("/fusioncc/index.php", 1);
    $head[$h][1] = $langs->trans("fusioncc_tab_fusion");
    $head[$h][2] = 'fusion';
    $h++;

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'fusioncc');

    return $head;
}

?>