<?php

/**
 * Saisie fichier spécifique
 *
 * @plugin     Bigup
 * @copyright  2015
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
 * - calcule le token en fonction du nom du champ : ajoute `token = #BIGUP_TOKEN{nom}`
 * - remplace `valeur = #ENV*{nom}` par `valeur = #ENV{_fichiers/nom}`
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

	// copier, pas cloner l'objet par référence
	$fichiers = unserialize(serialize($titre));
	$fichiers[0]->texte = '_fichiers/' . $fichiers[0]->texte;

	// creer #ENV{_fichiers/$titre}
	$fichiers = Pile::creer_balise('ENV', array('param' => array($fichiers)));

	// creer #BIGUP_TOKEN{$titre}
	$token = Pile::creer_balise('BIGUP_TOKEN', array('param' => array($titre)));

	// on modifie $p pour ajouter des arguments
	// {nom=$titre, valeur=#ENV{$titre}, erreurs, type_saisie=$type, fond=saisies/_base}
	$p = Pile::creer_et_ajouter_argument_balise($p, 'nom', $titre);
	$p = Pile::creer_et_ajouter_argument_balise($p, 'valeur', $env_titre);
	$p = Pile::creer_et_ajouter_argument_balise($p, 'fichiers', $fichiers); // ajouté par rapport à `#SAISIE`
	$p = Pile::creer_et_ajouter_argument_balise($p, 'token', $token);       // ajouté par rapport à `#SAISIE`
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
