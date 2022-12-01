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


$res=0;
if (! $res && file_exists("../main.inc.php")): $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/cactioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';

require_once 'lib/fusioncc.lib.php';

// ON RECUPERE LA VERSION DE DOLIBARR
$version = explode('.', DOL_VERSION);

// Load traductions files requiredby by page
$langs->load("companies");
$langs->load("other");

// Protection if external user
if ($user->societe_id > 0): accessforbidden(); endif;

/*
	TODO POUR TEST
	#OPTIONNEL : Vérifier la présence de doublons avec le même numéro de compte :
	#SELECT   COUNT(*) AS nbr_doublon, account_number
	#FROM     llx_accounting_account
	#GROUP BY account_number
*/

/*******************************************************************
* FONCTIONS
********************************************************************/
function separer_doublons($array, $key) {

	$results = array();
    $valid_array = array();
    $invalid_array = array();
    $links_array = array();

    $i = 0;
    $key_array = array();
   
    foreach($array as $k => $val):
        if (!in_array($val[$key], $key_array)):
            $key_array[$i] = $val[$key];
            $valid_array[$i] = $val;
        else:
        	$a = array_search($val[$key], $key_array);
        	$links_array[$a] = $i;
        	$invalid_array[$i] = $val;
        endif;
        $i++;
    endforeach;

    array_push($results, $valid_array);
    array_push($results, $invalid_array);
    array_push($results, $links_array);

    return $results;
}

/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');


/*******************************************************************
* ACTIONS
********************************************************************/

if($action == 'fusion_accounting' && $user->rights->fusioncc->fusionner):

	$db->begin();

	// On instancie la classe AccountingAccount;
	$accounting_account = new AccountingAccount($db);
	$tab_accounts = array();


	// On récupère tout les rowid de "accounting_account"
	$sql= "SELECT rowid FROM ".MAIN_DB_PREFIX."accounting_account";
	$results_accounts = $db->query($sql);

	// SI IL Y A DES ENTREES
	if($results_accounts): 


		$error = 0;
		$nb_doublon = 0;

		// On compte le nombre d'entrée
		$count_ids = $db->num_rows($result_prods); $i = 0;

		// POUR CHAQUE ID
		while ($i < $count_ids):

			// On récupères les infos
			$result_info = $db->fetch_object($results_accounts);
			$accounting_account->fetch($result_info->rowid);		

			// on renseigne un tableau des identifiants
			$account = array('rowid' => $accounting_account->rowid,'account_number' => $accounting_account->account_number);
			array_push($tab_accounts, $account);

			//if($i == 5):var_dump($accounting_account);endif;

			$i++;
		endwhile;

		// ON CREE 2 TABLEAUX, A AVEC DES RESULTATS VALIDES, B AVEC LES DOUBLONS DEJA INSERES DANS LE 1ER TABLEAU
		$tab_uni = separer_doublons($tab_accounts,'account_number');	
		$valid_accounts = $tab_uni[0];
		$invalid_accounts = $tab_uni[1];
		$links_accounts = $tab_uni[2];

		$nb_invalid = count($invalid_accounts);

		if(!empty($invalid_accounts)):

			// POUR CHAQUE DOUBLONS
			foreach ($invalid_accounts as $id => $doublon):

				$is_find = 0;

				// ON RECHERCHE LE "BON" DOUBLON 
				$id_tmp = array_search($id, $links_accounts);
				$bon_doublon = $valid_accounts[$id_tmp];

				$accounting_account->fetch($doublon['rowid']);

				// TEST 1
				if(!$error):
					$sql_a = "SELECT rowid FROM ".MAIN_DB_PREFIX."expensereport_det";
					$sql_a .= " WHERE fk_code_ventilation = '".$doublon['rowid']."'";
					$results_a = $db->query($sql_a);
					$nb_results_a = $db->num_rows($results_a);
					// SI IL Y A DES RESULTATS ON MET A JOUR
					if($nb_results_a > 0):
						$is_find++;
						$sql_a = "UPDATE ".MAIN_DB_PREFIX."expensereport_det SET fk_code_ventilation = '".$bon_doublon['rowid']."' WHERE fk_code_ventilation = '".$doublon['rowid']."'";
						$results_a = $db->query($sql_a);
						if(!$results_a): $error++; endif;
					endif;
				endif;

				// TEST 2
				if(!$error):
					$sql_b = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture_fourn_det";
					$sql_b .= " WHERE fk_code_ventilation = '".$doublon['rowid']."'";
					$results_b = $db->query($sql_b);
					$nb_results_b = $db->num_rows($results_b);

					// SI IL Y A DES RESULTATS ON MET A JOUR
					if($nb_results_b > 0):
						$is_find++;
						$sql_b = "UPDATE ".MAIN_DB_PREFIX."facture_fourn_det SET fk_code_ventilation = '".$bon_doublon['rowid']."' WHERE fk_code_ventilation = '".$doublon['rowid']."'";
						$results_b = $db->query($sql_b);
						if(!$results_b): $error++; endif;
					endif;
				endif;

				// TEST 3
				if(!$error):
					$sql_c = "SELECT rowid FROM ".MAIN_DB_PREFIX."facturedet";
					$sql_c .= " WHERE fk_code_ventilation = '".$doublon['rowid']."'";
					$results_c = $db->query($sql_c);
					$nb_results_c = $db->num_rows($results_c);
					// SI IL Y A DES RESULTATS ON MET A JOUR
					if($nb_results_c > 0):
						$is_find++;
						$sql_c = "UPDATE ".MAIN_DB_PREFIX."facturedet SET fk_code_ventilation = '".$bon_doublon['rowid']."' WHERE fk_code_ventilation = '".$doublon['rowid']."'";
						$results_c = $db->query($sql_c);
						if(!$results_c): $error++; endif;
					endif;
				endif;

				// TEST 4
				if(!$error):
					$sql_d = "SELECT rowid FROM ".MAIN_DB_PREFIX."fichinterdet_rec";
					$sql_d .= " WHERE fk_code_ventilation = '".$doublon['rowid']."'";
					$results_d = $db->query($sql_d);
					$nb_results_d = $db->num_rows($results_d);

					// SI IL Y A DES RESULTATS ON MET A JOUR
					if($nb_results_d > 0):
						$is_find++;
						$sql_d = "UPDATE ".MAIN_DB_PREFIX."fichinterdet_rec SET fk_code_ventilation = '".$bon_doublon['rowid']."' WHERE fk_code_ventilation = '".$doublon['rowid']."'";
						$results_d = $db->query($sql_d);
						if(!$results_d): $error++; endif;
					endif;
				endif;

				if(!$error): 

					if(!$accounting_account->delete($user,1)): $error++;else:$nb_doublon++;endif;

				endif;

			endforeach;

		endif;

		if (! $error): 
	        $db->commit();
	        if($nb_doublon == 0):
	        	setEventMessages($langs->trans('fusioncc_results_nodouble'), null, 'mesgs');
	        else:
	        	setEventMessages($langs->trans('fusioncc_results_nbdouble',$nb_doublon), null, 'mesgs');
	        endif;
	    else: 
	        $db->rollback();
	        setEventMessages($langs->trans('fusioncc_results_error'), null, 'mesgs');

	    endif;
	        
	endif;

endif;



/***************************************************
* VIEW
****************************************************/

llxHeader('',$langs->trans('Module300310Name'),''); ?>

<div id="pgsz-option">

    <?php $head = fusioncc_AdminPrepareHead(); dol_fiche_head($head, 'fusion','FusionCC', 0,'progiseize@progiseize'); ?>

    <table class="noborder centpercent pgsz-option-table" style="border-top:none;">
            <tbody>

                <?php // ?>
                <tr class="titre">
                    <td class="nobordernopadding valignmiddle col-title" style="" colspan="3">
                        <div class="titre inline-block" style="padding:16px 0"><?php echo $langs->trans('fusioncc_execute_title'); ?></div>
                    </td>
                </tr>
                <tr class="liste_titre pgsz-optiontable-coltitle" >
                    <th style="background: #f6f6f6"><?php echo $langs->trans('Description'); ?></th>
                    <th style="background: #f6f6f6" class="right"></th>
                </tr>

                <tr class="oddeven pgsz-optiontable-tr">
                	<?php if($user->rights->fusioncc->fusionner): ?>
                    <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('fusioncc_execute_desc'); ?></td>
                    <td class="right pgsz-optiontable-field ">
                    	<form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>" method="post">
							<input type="hidden" name="action" value="fusion_accounting">
							<input type="submit" name="" value="<?php echo $langs->trans('fusioncc_fusion'); ?>">
						</form>
                    </td>
                    <?php else: ?>
                    	<td class="bold pgsz-optiontable-fieldname" colspan="2"><?php echo $langs->trans('fusioncc_execute_norights'); ?></td>
					<?php endif; ?>

                </tr>

            </tbody>
        </table>
</div>

<!-- CONTENEUR GENERAL -->
<?php
// End of page
llxFooter();
$db->close();

?>
