<?php
/**
 * Options du plugin Big Upload au chargement
 *
 * @plugin     Big Upload
 * @copyright  2015
 * @author     Matthieu Marcillaud
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Options
 */

if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * Assez tôt on vérifie si on demande à tester la présence d'un morceau de fichier uploadé
 * ou si on demande à envoyer un morceau de fichier.
 *
 * Flow vérifie évidement que l'accès est accrédité !
**/
if (_request('bigup_token')) {
	include_spip('inc/Bigup');
	$Bigup = new \SPIP\Bigup\Bigup();
	$Bigup->repondre();
	exit;
}
