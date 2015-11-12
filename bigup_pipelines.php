<?php
/**
 * Utilisations de pipelines par Big Upload
 *
 * @plugin     Big Upload
 * @copyright  2015
 * @author     Matthieu Marcillaud
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Charger des scripts jquery
 *
 * @pipeline jquery_plugins
 * @param array $scripts Liste à charger
 * @return array Liste complétée
**/
function bigup_jquery_plugins($scripts) {
	if (test_espace_prive() or lire_config('bigup/charger_public', false)) {
		$scripts[] = 'lib/flow/flow.js';
		$scripts[] = produire_fond_statique('javascript/bigup.js');
	}
	return $scripts;
}

/**
 * Charger des styles CSS
 *
 * @pipeline insert_head_css
 * @param string $flux Code html des styles CSS à charger
 * @return string Code html complété
**/
function bigup_insert_head_css($flux) {
	if (test_espace_prive() or lire_config('bigup/charger_public', false)) {
		$flux .= '<link rel="stylesheet" href="'.produire_fond_statique('css/vignettes.css').'" type="text/css" media="screen" />' . "\n";
		$flux .= '<link rel="stylesheet" href="'.find_in_path('css/bigup.css').'" type="text/css" media="screen" />' . "\n";
	}
	return $flux;
}

/**
 * Charger des styles CSS dans l'espace privé
 *
 * @pipeline insert_head_css
 * @param string $flux Code html des styles CSS à charger
 * @return string Code html complété
**/
function bigup_header_prive($flux) {
	$flux .= '<link rel="stylesheet" href="'.produire_fond_statique('css/vignettes.css').'" type="text/css" media="screen" />' . "\n";
	$flux .= '<link rel="stylesheet" href="'.find_in_path('css/bigup.css').'" type="text/css" media="screen" />' . "\n";
	return $flux;
}


/**
 * Obtenir une instance de la classe bigup pour ce formulaire
 *
 * @param array $flux
 *     Flux, tel que présent dans les pipelines des formulaires CVT
 * @return \SPIP\Bigup\Bigup()
**/
function bigup_get_bigup($flux) {

	// il nous faut le nom du formulaire et son hash
	// et pas de bol, le hash est pas envoyé dans le pipeline.
	// (il est calculé après charger). Alors on se recrée un hash pour nous.
	$form = $flux['args']['form'];
	$args = $flux['args']['args'];
	#$post = $flux['args']['je_suis_poste'];

	array_unshift($args, $GLOBALS['spip_lang']);
	$formulaire_args = encoder_contexte_ajax($args, $form);

	include_spip('inc/Bigup');
	$bigup = new \Spip\Bigup\Bigup($form, $formulaire_args);

	return $bigup;
}

/**
 * Recherche de fichiers uploadés pour ce formulaire
 *
 * La recherche est conditionné par la présence dans le contexte
 * de la clé `_rechercher_uploads`. Ceci permet d'éviter de chercher
 * des fichiers pour les formulaires qui n'en ont pas besoin.
 * 
 * @param array $flux
 * @return array
**/
function bigup_formulaire_charger($flux) {

	if (empty($flux['data']['_rechercher_uploads'])) {
		return $flux;
	}

	$bigup = bigup_get_bigup($flux);
	if ($fichiers = $bigup->reinserer_fichiers()) {
		$flux['data']['_fichiers'] = $fichiers;
	}

	return $flux;
}


/**
 * Branchement sur verifier
 * 
 * Si on a demandé la suppression d'un fichier, le faire
 *
 * @param array $flux
 * @return array
**/
function bigup_formulaire_verifier($flux) {

	// enlever un fichier dont on demande sa suppression
	if ($identifiant = _request('bigup_enlever_fichier')) {
		$bigup = bigup_get_bigup($flux);
		if ($bigup->enlever_fichier($identifiant)) {
			// on n'affiche pas les autres erreurs
			$flux['data'] = [];
			$flux['data']['message_erreur'] = '';
			$flux['data']['message_ok'] = 'Fichier effacé';
			$flux['data']['_erreur'] = true;
		}
	}

	return $flux;
}
