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
		$bigup->reinserer_fichiers(_request('bigup_reinjecter_uniquement'));
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
		if ($bigup->supprimer_fichiers($identifiant)) {
			// on n'affiche pas les autres erreurs
			$flux['data'] = [];
			$flux['data']['message_erreur'] = '';
			$flux['data']['message_ok'] = 'Fichier effacé';
			$flux['data']['_erreur'] = true;
		}
	}
	return $flux;
}


/**
 * Branchement sur traiter
 *
 * Si on a effectué les traitements sans erreur,
 * tous les fichiers restants doivent disparaître
 * du cache.
 *
 * @param array $flux
 * @return array
 **/
function bigup_formulaire_traiter($flux) {
	// à voir si on cherche systématiquement
	// ou uniquement lorsqu'on a demander à recuperer les fichiers
	if (empty($flux['data']['message_erreur']) and _request('bigup_retrouver_fichiers')) {
		$bigup = bigup_get_bigup($flux);
		$bigup->supprimer_fichiers(_request('bigup_reinjecter_uniquement'));
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
	if (!empty($flux['args']['contexte']['_rechercher_uploads'])) {
		$contexte = $flux['args']['contexte'];
		switch ($flux['args']['form']) {

			case 'joindre_document':
				$flux['data'] = bigup_preparer_input_file($flux['data'], 'fichier_upload', $contexte, ['previsualiser' => true]);
				$flux['data'] .= "\n" . '<script type="text/javascript" src="' . find_in_path('javascript/bigup.documents.js') . '"></script>' . "\n";
				break;

			case 'editer_logo':
				$flux['data'] = bigup_preparer_input_file($flux['data'], ['logo_on', 'logo_off'], $contexte, ['input_class' => 'bigup_logo', 'previsualiser' => true]);
				$flux['data'] .= "\n" . '<script type="text/javascript" src="' . find_in_path('javascript/bigup.logos.js') . '"></script>' . "\n";
				break;

			case 'formidable':
				$flux['data'] = bigup_preparer_input_file_bigup($flux['data'], $contexte, ['previsualiser' => true]);
				break;
		}
	}

	return $flux;
}

/**
 * Cherche les inputs de type file avec la classe bigup et ajoute le token (et autres infos dessus)
 *
 *
 * @uses bigup_preparer_input_file()
 * @param string $formulaire
 * @param array $contexte
 * @param array $options
 * @return string
 */
function bigup_preparer_input_file_bigup($formulaire, $contexte, $options = []) {
	if (empty($contexte['form']) or empty($contexte['formulaire_args'])) {
		return $formulaire;
	}

	if (!is_array($options)) {
		$options = [];
	}

	$options = $options + [
		'input_class' => '', // l'input a déjà la classe bigup !
	];

	// On cherche à retrouver le name des champs input file de classe bigup
	// chercher <input ... [type="file"] ... [class="... bigup ..."] ... />
	// chercher <input ... [class="... bigup ..." ...] [type="file" ...] />

	$exp_non_fin_balise = '(?:[^>]*)'; // du contenu sans >
	$exp_type = 'type\s*=\s*[\"\']{1}\s*file\s*[\"\']{1}'; // type='file' ou type="file"

	$exp_class =
		'class\s*=\s*[\"\']{1}\s*' // class=" ou class='
		. '(?:[^\"\']+\s+)?'       // (du contenu sans " ou ' avec un espace après)?
		. 'bigup'
		. '(?:\s+[^\"\']+)?'       // (du contenu sans " ou ' avec un espace avant)?
		. '\s*[\"\']{1}';          // fin de l'attribut class

	$exp_champ =
		'name\s*=\s*[\"\']{1}\s*' // name=" ou name='
		. '(?<champ>[^\"\']+)'    // notre champ !
		. '(?<multiple>\[\])?'    // le nom de champ a [] ? => multiple
		. '\s*[\"\']{1}';         // fin de l'attribut name

	$regexp1 =
		'#<input'
		. $exp_non_fin_balise
		. $exp_type
		. $exp_non_fin_balise
		. $exp_class
		. $exp_non_fin_balise
		. '/>#Uims';

	$regexp2 =
		'#<input'
		. $exp_non_fin_balise
		. $exp_class
		. $exp_non_fin_balise
		. $exp_type
		. $exp_non_fin_balise
		. '/>#Uims';

	$regexp_champ =
		'#<input'
		. $exp_non_fin_balise
		. $exp_champ
		. $exp_non_fin_balise
		. '/>#Uims';

	$champs = [];

	if (preg_match_all($regexp1, $formulaire, $matches)) {
		foreach ($matches as $m) {
			if (preg_match($regexp_champ, $m[0], $regs)) {
				$champs[] = $regs['champ'];
			}
		}
	}
	if (preg_match_all($regexp2, $formulaire, $matches)) {
		foreach ($matches as $m) {
			if (preg_match($regexp_champ, $m[0], $regs)) {
				$champs[] = $regs['champ'];
			}
		}
	}

	if ($champs) {
		return bigup_preparer_input_file($formulaire, $champs, $contexte, $options);
	} else {
		return $formulaire;
	}
}
/**
 * Préparer les champs input d'un formulaire déjà existant
 *
 * Permet d'ajouter à un ou plusieurs champs de type 'file' d'un formulaire
 * dont on reçoit le code HTML et le contexte les éléments nécessaires
 * pour utiliser Bigup dessus.
 *
 * Pour les noms de champs indiqués, on ajoute :
 *
 * - la classe CSS 'bigup'
 * - le token
 * - l'attribut multiple, si le name se termine par `[]`
 * - la liste des fichiers déjà uploadés pour ce formulaire
 * - la classe CSS 'pleine_largeur' sur le conteneur .editer.
 *
 * Le tableau d'option permet de modifier certains comportements.
 *
 * @param string $formulaire
 *     Contenu du formulaire
 * @param string|string[] $champs
 *     Nom du ou des champs concernés
 * @param array $contexte
 *     Le contexte doit fournir au moins 'form' et 'formulaire_args'
 * @param array $options {
 *     @var string $input_class
 *         Classe CSS à ajouter aux input file concernés.
 *         Par défaut 'bigup'
 *     @var string $editer_class
 *         Classe CSS à ajouter au conteneur .editer
 *         Par défaut 'pleine_largeur'
 * }
 * @return string
 *     Contenu du formulaire modifié
 */
function bigup_preparer_input_file($formulaire, $champs, $contexte, $options = []) {
	if (!$champs) {
		return $formulaire;
	}
	if (!is_array($champs)) {
		$champs = [$champs];
	}

	if (empty($contexte['form']) or empty($contexte['formulaire_args'])) {
		return $formulaire;
	}

	// Intégrer les options par défaut.
	$options = $options + [
		'input_class' => 'bigup',
		'editer_class' => 'pleine_largeur',
		'previsualiser' => false,
	];

	include_spip('bigup_fonctions');
	include_spip('saisies_fonctions');

	foreach ($champs as $champ) {

		$regexp =
			'#<input'
			. '(?:[^>]*)'            // du contenu sans >
			. 'name\s*=\s*[\"\']{1}' // name=" ou name='
			. preg_quote($champ)     // notre nom de champ
			. '(?<multiple>\[\])?'   // le nom de champ a [] ? => multiple
			. '[\"\']{1}'            // fin du name
			. '(?:[^>]*)'            // du contenu sans >
			. '/>#Uims';


		if (preg_match($regexp, $formulaire, $regs)) {
			$input = $new = $regs[0];
			$multiple = !empty($regs['multiple']);

			// Ajouter la classe CSS demandée
			if ($options['input_class']) {
				$new = str_replace('class="', 'class="' . $options['input_class'] . ' ', $new);
				$new = str_replace('class=\'', 'class=\'' . $options['input_class'] . ' ', $new);
			}

			// Ajouter multiple si le name possède []
			if ($multiple) {
				$new = str_replace('/>', ' multiple />', $new);
			}

			// Ajouter le token
			$token = calculer_balise_BIGUP_TOKEN(
				$champ,
				$multiple,
				$contexte['form'],
				$contexte['formulaire_args']
			);
			$new = str_replace('/>', ' data-token="' . $token . '" />', $new);

			// Ajouter l'option de previsualisation
			if ($options['previsualiser']) {
				$new = str_replace('/>', ' data-previsualiser="oui" />', $new);
			}

			// Ajouter les fichiers déjà présents
			$fichiers = '';
			$liste_fichiers = table_valeur($contexte, saisie_name2nom($champ));
			if ($liste_fichiers) {
				$fichiers = recuperer_fond(
					'saisies/inc-bigup_liste_fichiers',
					array(
						'nom' => $champ,
						'multiple' => $multiple,
						'fichiers' => ($multiple ? $liste_fichiers : array($liste_fichiers))
					)
				);
			}
			$formulaire = str_replace($input, $fichiers . $new, $formulaire);

			// Ajouter une classe sur le conteneur
			if ($options['editer_class']) {
				// <div class="editer editer_{champ}" mais pas "editer editer_{champ}_qqc"
				$regexp =
					'#<div '
					. '(?:[^>]*)'                  // du contenu sans >
					. 'class\s*=\s*[\"\']{1}'      // class=" ou class='
					. '(?:[^\"\']*)'               // pas de ' ou "
					. 'editer editer_' . $champ
					. '(?:(\s+[^\"\']*)?)'         // (espace suivi de pas de ' ou ")
					. '[\"\']{1}'                  // " ou '
					. '#Uims';

				if (preg_match($regexp, $formulaire, $regs)) {
					$div = $new = $regs[0];
					$new = str_replace(
						'editer editer_' . $champ,
						'editer editer_' . $champ . ' ' . $options['editer_class'],
						$new
					);
					$formulaire = str_replace($div, $new, $formulaire);
				}
			}
		}
	}
	return $formulaire;
}