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
 * À utiliser à l'intérieur d'un formulaire CVT ou dans un fichier de saisies.
 * Dans une utilisation dans 'saisies/', il faut transmettre `form` et `formulaire_args`
 * du formulaire pour le calcul.
 *
 * Le token généré se base sur la valeur de l'attribut `name`
 * de l'input que l'on peut recevoir soit :
 *
 * - en écriture html : `fichiers[images]`
 * - en écriture comprise par Saisies : `fichiers/images`
 *
 * Si l'input est voué à recevoir plusieurs fichiers
 * (attribut `multiple` et name avec `[]` tel que `fichiers[images][]`
 * il faut aussi l'indiquer, soit:
 *
 * - en écriture html : `fichiers[images][]`
 * - en écriture Saisies : `fichiers/images/`
 *
 * Par habitude d'usage avec le plugin Saisies, on accepte aussi
 * une forme plus habituelle en transmettant un paramètre `multiple`
 * (en 2è paramètre de la balise, valant par défaut `#ENV{multiple}`)
 * indiquant que le token concerne un input recevant plusieurs fichiers.
 * On l'écrit :
 *
 * - en écriture html : `fichiers[images]`
 * - en écriture Saisies : `fichiers/images`
 *
 * La balise accepte 4 paramètres, tous automatiquement récupérés dans l'environnement
 * s'ils ne sont pas renseignés :
 *
 * - nom : la valeur de l'attribut name. Défaut `#ENV{nom}`
 * - form : le nom du formulaire. Défaut `#ENV{form}`
 * - formulaire_args : hash des arguments du formulaire. Défaut `#ENV{formulaire_args}`
 * - multiple : indication d'un champ multiple, si valeur 'oui' ou 'multiple'. Défaut `#ENV{multiple}`
 *
 * @syntaxe `#BIGUP_TOKEN{name, multiple, form, formulaire_args}`
 * @example
 *     - `#BIGUP_TOKEN` utilisera `#ENV{nom}` en nom de champ par défaut
 *     - `#BIGUP_TOKEN{#ENV{nom}, #ENV{multiple}, #ENV{form}, #ENV{formulaire_args}}` : valeurs par défaut.
 *     - `#BIGUP_TOKEN{file}` : champ unique
 *     - `#BIGUP_TOKEN{file\[\]}` : champ multiple
 *     - `#BIGUP_TOKEN{file/}` : champ multiple
 *     - `#BIGUP_TOKEN{file, multiple}` : champ multiple
 *     - Le token sera calculé dans la saisie bigup :
 *       `[(#SAISIE{bigup, file, form, formulaire_args, label=Fichier, ... })]`
 *     - Le token est calculé dans l'appel :
 *       `[(#SAISIE{bigup, file, token=#BIGUP_TOKEN{file}, label=Fichier, ... })]`
 *
 * @see saisies/bigup.html Pour un usage dans une saisie.
 * @see balise_SAISIE_FICHIER_dist()
 * @note
 *     La signature complète est `#BIGUP_TOKEN{champ, multiple, form, formulaire_args}`
 *
 *     La balise nécessite de connaître le nom du formulaire
 *     (par défaut `#ENV{form}` ainsi que le hash de ses arguments
 *     (par défaut `#ENV{formulaire_args}`.
 *
 *     Si cette balise est utilisée dans une inclusion (tel que `#INCLURE` ou `#SAISIE`),
 *     il faut penser à transmettre à l'inclure `form` et `formulaire_args`.
 *     La balise `#SAISIE_FICHIER` s'en occupe.
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

	if (!$_multiple = interprete_argument_balise(2, $p)) {
		$_multiple = "@\$Pile[0]['multiple']";
	}

	if (!$_form = interprete_argument_balise(3, $p)) {
		$_form = "@\$Pile[0]['form']";
	}

	if (!$_form_args = interprete_argument_balise(4, $p)) {
		$_form_args = "@\$Pile[0]['formulaire_args']";
	}

	$p->code = "calculer_balise_BIGUP_TOKEN($_champ, $_multiple, $_form, $_form_args)";

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
 * @param string|bool $multiple
 *      Indique si le champ est multiple
 * @param string $form
 *      Nom du formulaire
 * @param string $form_args
 *      Hash du contexte ajax du formulaire
 * @return string|false
 *      String : Le token
 *      false : Erreur : un des arguments est vide.
**/
function calculer_balise_BIGUP_TOKEN($champ, $multiple, $form, $form_args) {

	if (!$champ OR !$form OR !$form_args) {
		spip_log("Demande de token bigup, mais un argument est vide", _LOG_ERREUR);
		return false;
	}
	$time = time();

	// le vrai nom du champ pour le token (truc/muche => truc[muche])
	$champ = bigup_nom2name($champ);

	// Ajouter [] s'il est multiple et s'il ne l'a pas déjà.
	if (in_array($multiple, [true, 'oui', 'multiple'])) {
		if (substr($champ, -2) != '[]') {
			$champ = $champ . '[]';
		}
	}
	include_spip('inc/securiser_action');
	$token = $champ . ':' . $time . ':' . calculer_action_auteur("bigup/$form/$form_args/$champ/$time");
	return $token;
}

/**
 * Retrouve les fichiers correspondant à un name d'input donné, s'il y en a.
 *
 * @param array $fichiers
 * @param string $nom
 *      Name utilisé, tel que 'oiseaux/bleus' ou 'oiseaux[bleus]'
 * @param string $multiple
 *      Si contenu, le champ est considéré multiple
 * @return array
 */
function bigup_lister_fichiers($fichiers, $nom, $multiple) {
	if (!$fichiers or !$nom) {
		return [];
	}
	$nom = bigup_name2nom($nom);
	if ($multiple) {
		$liste = table_valeur($fichiers, $nom);
	} else {
		$liste = [ table_valeur($fichiers, $nom) ];
	}
	return is_array($liste) ? array_filter($liste) : [];
}


// Duplicat fonctions de saisies, pour eviter une dependance.
// A regler mieux que ca (dans le core ?)

/**
 * Passer un nom en une valeur compatible avec un `name` de formulaire
 *
 * - toto => toto,
 * - toto/truc => toto[truc],
 * - toto[truc] => toto[truc]
 *
 * @param string $nom
 * return string
**/
function bigup_nom2name($nom) {
	if (false === strpos($nom, '/')) {
		return $nom;
	}
	$nom = explode('/', $nom);
	$premier = array_shift($nom);
	$nom = implode('][', $nom);
	return $premier . '[' . $nom . ']';
}


/**
 * Passer un nom en une valeur compatible avec une classe css
 *
 * - toto => toto,
 * - toto/truc => toto_truc,
 * - toto[truc] => toto_truc
 *
 * @param string $nom
 * return string
**/
function bigup_nom2classe($nom) {
	return str_replace(array('/', '[', ']', '&#91;', '&#93;'), array('_', '_', '', '_', ''), $nom);
}


/**
 * Passer un `name` en un format de nom compris de saisies
 *
 * - toto => toto,
 * - toto[truc] => toto/truc,
 * - toto[truc][] => toto/truc/
 * - toto/truc => toto/truc
 *
 * @see saisie_nom2name() pour l'inverse.
 * @param string $name
 * @return string
 **/
function bigup_name2nom($name) {
	if (false === strpos($name, '[')) {
		return $name;
	}
	$name = explode('[', str_replace(']', '', $name));
	return implode('/', $name);
}

