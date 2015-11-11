<?php
/**
 * Tache de nettoyages de fichiers du plugin Big Upload
 *
 * @plugin     Big Upload
 * @copyright  2015
 * @author     Matthieu Marcillaud
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Genie
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Enlève les fichiers du répertoire de travail de bigup qui sont trop vieux
 *
 * @param int $last
 * @return int
**/
function genie_bigup_nettoyer_repertoire_upload_dist($last) {

	include_spip('bigup_fonctions');
	bigup_nettoyer_repertoire_recursif(_DIR_TMP . 'bigupload');

	return 1;
}
