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
	include_spip('inc/Bigup');
	$bigup = new \Spip\Bigup\Bigup(
		\Spip\Bigup\Identifier::depuisArgumentsPipeline($flux['args'])
	);
	return $bigup;
}

/**
 * Recherche de fichiers uploadés pour ce formulaire
 *
 * La recherche est conditionné par la présence dans le contexte
 * de la clé `_rechercher_uploads`. Ceci permet d'éviter de chercher
 * des fichiers pour les formulaires qui n'en ont pas besoin.
 *
 * Réinsère les fichiers déjà présents pour ce formulaire
 * dans `$_FILES` (a priori assez peu utile dans le chargement)
 * et ajoute la description des fichiers présents pour chaque champ,
 * dans l'environnement.
 *
 * Ajoute également un hidden, qui s'il est posté, demandera à recréer $_FILES
 * juste avant la fonction verifier(). Voir `bigup_formulaire_receptionner()`
 *
 * @see bigup_formulaire_receptionner():
 * @param array $flux
 * @return array
**/
function bigup_formulaire_charger($flux) {

	if (empty($flux['data']['_rechercher_uploads'])) {
		return $flux;
	}

	$bigup = bigup_get_bigup($flux);
	if ($fichiers = $bigup->retrouver_fichiers()) {
		foreach ($fichiers as $racine => $listes) {
			// fonctionne au premier chargement, mais pas après avoir validé le formulaire
			$flux['data'][$racine] = $fichiers[$racine];
			// car SPIP prend la valeur dans le request. Du coup, on les met aussi dans le request.
			set_request($racine, $fichiers[$racine]);
		}
	}

	if (empty($flux['data']['_hidden'])) {
		$flux['data']['_hidden'] = '';
	}
	$flux['data']['_hidden'] .= '<input type="hidden" name="bigup_retrouver_fichiers" value="1" />';

	return $flux;
}


/**
 * Branchement sur la réception d'un formulaire (avant verifier())
 *
 * On remet `$_FILES` avec les fichiers présents pour ce formulaire,
 * et avant que la fonction verifier native du formulaire soit utilisée,
 * de sorte qu'elle ait accès à $_FILES rempli.
 *
 * @pipeline formulaire_receptionner
 * @param array $flux
 * @return array
 */
function bigup_formulaire_receptionner($flux) {
	if (_request('bigup_retrouver_fichiers')) {
		$bigup = bigup_get_bigup($flux);
		$bigup->gerer_fichiers_postes(); // les fichiers postés sans JS
		$liste = $bigup->reinserer_fichiers(_request('bigup_reinjecter_uniquement'));
		$bigup->surveiller_fichiers($liste);
	}
	return $flux;
}

/**
 * Branchement sur verifier
 * 
 * - Si on a demandé la suppression d'un fichier, le faire
 * - Nettoyer les fichiers injectés effacés de $_FILES.
 *
 * @param array $flux
 * @return array
**/
function bigup_formulaire_verifier($flux) {
	$identifiant = _request('bigup_enlever_fichier');
	if ($identifiant or _request('bigup_retrouver_fichiers')) {
		$bigup = bigup_get_bigup($flux);
		// enlever un fichier dont on demande sa suppression
		if ($identifiant) {
			if ($bigup->supprimer_fichiers($identifiant)) {
				// on n'affiche pas les autres erreurs
				$flux['data'] = [];
				$flux['data']['message_erreur'] = '';
				$flux['data']['message_ok'] = 'Fichier effacé';
				$flux['data']['_erreur'] = true;
			}
		} else {
			// nettoyer nos fichiers réinsérés s'ils ont été enlevés de $_FILES
			$bigup->verifier_fichiers_surveilles();
		}
	}
	return $flux;
}


/**
 * Branchement sur traiter
 *
 * - Si on a effectué les traitements sans erreur,
 * tous les fichiers restants doivent disparaître
 * du cache.
 * - Nettoyer les fichiers injectés effacés de $_FILES.
 *
 * @param array $flux
 * @return array
 **/
function bigup_formulaire_traiter($flux) {
	if (_request('bigup_retrouver_fichiers')) {
		$bigup = bigup_get_bigup($flux);
		// à voir si on cherche systématiquement
		// ou uniquement lorsqu'on a demandé à recuperer les fichiers
		if (empty($flux['data']['message_erreur'])) {
			$bigup->supprimer_fichiers(_request('bigup_reinjecter_uniquement'));
		} else {
			// nettoyer nos fichiers réinsérés s'ils ont été enlevés de $_FILES
			$bigup->verifier_fichiers_surveilles();
		}
	}
	return $flux;
}


/**
 * Ajouter bigup sur certains formulaires
 *
 * - le documents du plugin Medias
 * - le formulaire de logo de SPIP
 *
 * @param array $flux
 * @return array
 **/
function bigup_medias_formulaire_charger($flux) {
	if (in_array($flux['args']['form'], ['joindre_document', 'editer_logo', 'formidable'])) {
		$flux['data']['_rechercher_uploads'] = true;
	}
	return $flux;
}

/**
 * Utiliser Bigup sur certains formulaires
 *
 * - le documents du plugin Medias
 * - le formulaire de logo de SPIP
 *
 * @param array $flux
 * @return array
 **/
function bigup_medias_formulaire_fond($flux) {
	if (
		!empty($flux['args']['contexte']['_rechercher_uploads'])
		and in_array($flux['args']['form'], ['joindre_document', 'editer_logo', 'formidable'])
	) {
		$bigup = bigup_get_bigup(['args' => $flux['args']['contexte']]);
		$formulaire = $bigup->formulaire($flux['data'], $flux['args']['contexte']);

		switch ($flux['args']['form']) {

			case 'joindre_document':
				$formulaire->preparer_input(
					'fichier_upload[]',
					['previsualiser' => true]
				);
				$formulaire->inserer_js('bigup.documents.js');
				break;

			case 'editer_logo':
				$formulaire->preparer_input(
					['logo_on', 'logo_off'],
					['input_class' => 'bigup_logo', 'previsualiser' => true]
				);
				$formulaire->inserer_js('bigup.logos.js');
				break;

			case 'formidable':
				$formulaire->preparer_input_class(
					'bigup', // 'file' pour rendre automatique.
					['previsualiser' => true]
				);
				break;
		}

		$flux['data'] = $formulaire->get();
	}

	return $flux;
}
