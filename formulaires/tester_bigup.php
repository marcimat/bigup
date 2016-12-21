<?php

/**
 * L'objectif de ce formulaire de test est de vérifier la simplicité d'usage
 * de l'envoi de fichier, même des gros.
 *
 * Il y a beaucoup de contraintes à gérer :
 * 
 * - la gestion d'un ou plusieurs champs de type fichier
 * - la gestion de gros fichiers (envois par morceaux (chunk))
 * - la gestion d'une zone de glisser déposer
 * - le stockage des fichiers envoyés avant le traitement du formulaire
 * - leur nettoyage si l'utilisateur finalement ne soumet pas le formulaire
 * - la sécurité : ne pas pouvoir exécuter/télécharger les fichiers envoyés directement
 * - la gestion de plusieurs formulaires sur la même page (avec des appels différents) :
 *   ils ne doivent pas se mélanger les pinceaux.
 * 
 * @package SPIP\Bigup\Formulaires
**/



function formulaires_tester_bigup_charger_dist($id = 0) {
	$valeurs = [
		'titre' => '',
	];

	// demander la gestion de fichiers d'upload
	$valeurs['_rechercher_uploads'] = true;

	spip_log("> charger tester_bigup", "bigup");

	return $valeurs;
}



function formulaires_tester_bigup_verifier_dist($id = 0) {
	$erreurs = [];

	spip_log('> verifier tester_bigup', "bigup");

	// ceux là sont obligatoires
	foreach (['titre', 'texte'] as $obli) {
		if (!_request($obli)) {
			$erreurs[$obli] = _T('info_obligatoire');
		}
	}

	return $erreurs;
}



function formulaires_tester_bigup_traiter_dist($id = 0) {
	spip_log('> traiter tester_bigup', "bigup");

	$retours = array(
		'message_ok' => "Formulaire pris en compte",
		'editable' => true,
	);

	return $retours;
}
