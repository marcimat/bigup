<?php


/**
 * Assez tôt on vérifie si on demande à tester la présence d'un morceau de fichier uploadé
 * ou si on demande à envoyer un morceau de fichier.
 *
 * Flow vérifie évidement que l'accès est accrédité !
**/
function action_bigup_dist() {

	include_spip('inc/Bigup');
	$Bigup = new \SPIP\Bigup\Bigup();
	$Bigup->recuperer_parametres();
	$Bigup->repondre();
	exit;

}
