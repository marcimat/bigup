<?php
/**
 * Fichier gérant l'installation et désinstallation du plugin Big Upload
 *
 * @plugin     Big Upload
 * @copyright  2015
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Installation
 */

if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * Fonction d'installation et de mise à jour du plugin Big Upload.
 * 
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 * @param string $version_cible
 *     Version du schéma de données dans ce plugin (déclaré dans paquet.xml)
 * @return void
**/
function bigup_upgrade($nom_meta_base_version, $version_cible) {
	$maj = [];

	// Configuration par défaut
	$config_defaut = [
		'max_file_size' => 5, // 5 Mb par défaut
	];

	$maj['create'] = [['ecrire_meta', 'bigup', serialize($config_defaut)]];

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}


/**
 * Fonction de désinstallation du plugin Big Upload.
 * 
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 * @return void
**/
function bigup_vider_tables($nom_meta_base_version) {
	effacer_meta($nom_meta_base_version);
}
