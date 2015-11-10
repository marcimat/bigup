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
 * Si des fichiers d'upload sont déclarés, gérer la mécanique de déclaration et stockage
 *
 * @param array $flux
 * @return array
**/
function bigup_formulaire_charger($flux) {

	// S'il y a des champs fichiers de déclarés
	if ($fichiers = bigup_lister_fichiers_formulaire($flux['args']['form'], $flux['args']['args'])) {
		$flux['data']['_fichiers'] = $fichiers;
	}

	return $flux;
}


/**
 * Retrouve la déclaration de fichiers d'un formulaire CVT dont on a le nom et les arguments
 *
 * La liste des fichiers du formulaire peut être déclarée directement avec
 * la fonction `formulaires_xx_declarer_fichiers_dist()` dans le formulaire CVT
 * ou avec le pipeline `formulaire_declarer_fichiers`
 * 
 * @pipeline_appel formulaire_declarer_fichiers
 * 
 * @param string $form
 *     Nom du formulaire
 * @param array $args
 *     Arguments d'appel du formulaire
 * @return Bigup\Fichier[]
**/
function bigup_lister_fichiers_formulaire($form, $args) {

	// liste des fichiers déclarés
	$fichiers = array();

	if ($declarer_fichiers = charger_fonction('declarer_fichiers', 'formulaires/' . $form, true)) {
		$fichiers = call_user_func_array($declarer_fichiers, $args);
	}

	$fichiers = pipeline('formulaire_declarer_fichiers', array(
		'args' => array(
			'form' => $form,
			'args' => $args
		),
		'data' => $fichiers
	));

	return $fichiers;
}
