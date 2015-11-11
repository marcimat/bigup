<?php
/**
 * Fonctions utiles au plugin Big Upload
 *
 * @plugin     Big Upload
 * @copyright  2015
 * @author     Matthieu Marcillaud
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * Compile la balise `#BIGUP_TOKEN` qui calcule un token
 * autorisant l'envoi de fichiers par morceaux
 *
 * À utiliser à l'intérieur d'un formulaire CVT.
 * 
 * @example
 *     - `#BIGUP_TOKEN{file}`
 *     - `#BIGUP_TOKEN` utilisera `#ENV{nom}` en nom de champ
 *     - `#BIGUP_TOKEN{#ENV{nom}}`
 *
 * @note
 *     La signature complète est `#BIGTOKEN{champ, form, formulaire_args}`
 * 
 *     La balise nécessite de connaître le nom du formulaire
 *     (par défaut `#ENV{form}` ainsi que le hash de ses arguments
 *     (par défaut `#ENV{formulaire_args}`.
 *
 *     Si cette balise est utilisée dans une inclusion, il faut penser
 *     à transmettre à l'inclure form et formulaire_args donc ;
 *     de même dans une `#SAISIE`, qui est un inclure comme un autre.
 * 
 * @balise
 * @uses calculer_balise_BIGUP_TOKEN()
 * 
 * @param Champ $p
 *     Pile au niveau de la balise
 * @return Champ
 *     Pile complétée par le code à générer
**/
function balise_BIGUP_TOKEN($p){
	if (!$_champ = interprete_argument_balise(1, $p)) {
		$_champ = "@\$Pile[0]['nom']";
	}

	if (!$_form = interprete_argument_balise(2, $p)) {
		$_form = "@\$Pile[0]['form']";
	}

	if (!$_form_args = interprete_argument_balise(3, $p)) {
		$_form_args = "@\$Pile[0]['formulaire_args']";
	}

	$p->code = "calculer_balise_BIGUP_TOKEN($_champ, $_form, $_form_args)";

	$p->interdire_scripts = false;
	return $p;
}

/**
 * Calcule un token en fonction de l'utilisateur, du champ, du formulaire…
 *
 * Retourne un token de la forme `champ:time:clé`
 * 
 * @uses calculer_action_auteur()
 * @see \Spip\Bigup\Flow::verifier_token()
 * 
 * @param string $champ
 *      Nom du champ input du formulaire
 * @param string $form
 *      Nom du formulaire
 * @param string $form_args
 *      Hash du contexte ajax du formulaire
 * @return string|false
 *      String : Le token
 *      false : Erreur : un des arguments est vide.
**/
function calculer_balise_BIGUP_TOKEN($champ, $form, $form_args) {
	if (!$champ OR !$form OR !$form_args) {
		spip_log("Demande de token bigup, mais un argument est vide", _LOG_ERREUR);
		return false;
	}
	$time = time();
	$token = $champ . ':' . $time . ':' . calculer_action_auteur("bigup/$form/$form_args/$champ/$time");
	return  $token;
}
