<?php

/**
 * Saisie fichier spécifique
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

if (!defined("_ECRIRE_INC_VERSION")) return;

include_spip('balise/saisie');



/**
 * Compile la balise `#SAISIE_FICHIER` qui retourne le code HTML de la saisie de formulaire indiquée.
 *
 * Identique à peu de choses près à la balise `#SAISIE`
 * - ajoute `form = #ENV{form}`
 * - ajoute `formulaire_args = #ENV{formulaire_args}`
 *
 * Ces 2 infos sont utiles à la balise `#BIGUP_TOKEN`
 *
 * @syntaxe `#SAISIE_FICHIER{type,nom[,option=xx,...]}`
 *
 * @param Champ $p
 * @return Champ
 */
function balise_SAISIE_FICHIER_dist($p) {

	// on recupere les parametres sans les traduire en code d'execution php
	$type_saisie = Pile::recuperer_et_supprimer_argument_balise(1, $p); // $type
	$titre       = Pile::recuperer_et_supprimer_argument_balise(1, $p); // $titre

	// creer #ENV*{$titre} (* pour les cas de tableau serialises par exemple, que l'on veut reutiliser)
	$env_titre   = Pile::creer_balise('ENV', array('param' => array($titre), 'etoile' => '*')); // #ENV*{titre}


	// on modifie $p pour ajouter des arguments
	// {nom=$titre, valeur=#ENV{$titre}, erreurs, type_saisie=$type, fond=saisies/_base}
	$p = Pile::creer_et_ajouter_argument_balise($p, 'nom', $titre);
	$p = Pile::creer_et_ajouter_argument_balise($p, 'valeur', $env_titre);
	$p = Pile::creer_et_ajouter_argument_balise($p, 'form'); // ajouté par rapport à `#SAISIE`
	$p = Pile::creer_et_ajouter_argument_balise($p, 'formulaire_args'); // ajouté par rapport à `#SAISIE`
	$p = Pile::creer_et_ajouter_argument_balise($p, 'type_saisie', $type_saisie);
	$p = Pile::creer_et_ajouter_argument_balise($p, 'erreurs');
	$p = Pile::creer_et_ajouter_argument_balise($p, 'fond', 'saisies/_base');

	// on appelle la balise #INCLURE
	// avec les arguments ajoutes
	if (function_exists('balise_INCLURE')) {
		return balise_INCLURE($p);
	} else {
		return balise_INCLURE_dist($p);
	}
}
