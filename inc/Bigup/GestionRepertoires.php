<?php

namespace Spip\Bigup;

/**
 * Gère la création ou le nettoyages de répertoires
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

include_spip('inc/Bigup/LogTrait');

/**
 * Gère la création ou le nettoyages de répertoires
 *
 * @notes
 *     Certaines fonctions peuvent faire beaucoup d'accès disque.
 **/
class GestionRepertoires {

	use LogTrait;

	/**
	 * Vérifier et préparer l'arborescence jusqu'au répertoire parent
	 *
	 * @note
	 *     Code repris de SVP (action/teleporter)
	 *
	 * @param string $dest
	 * @return bool|string
	 *     false en cas d'échec
	 *     Chemin du répertoire sinon
	 */
	public static function creer_sous_repertoires($dest){
		$dest = rtrim($dest, "/");
		$final = basename($dest);
		$base = dirname($dest);
		$create = array();

		// on cree tout le chemin jusqu'a dest non inclus
		while (!is_dir($base)) {
			$create[] = basename($base);
			$base = dirname($base);
		}

		while (count($create)){
			if (!is_writable($base)) {
				return false;
			}
			$base = self::creer_sous_repertoire($base, array_pop($create));
			if (!$base) {
				return false;
			}
		}

		if (!is_writable($base)) {
			return false;
		}

		return self::creer_sous_repertoire($base, $final);
	}



	/**
	 * Nettoyer un répertoire suivant l'age et le nombre de ses fichiers
	 *
	 * Nettoie aussi les sous répertoires.
	 * Supprime automatiquement les répertoires vides.
	 *
	 * @param string $repertoire
	 *     Répertoire à nettoyer
	 * @param int $age_max
	 *     Age maxium des fichiers en seconde
	 * @param int $max_files
	 *     Nombre maximum de fichiers dans le dossier
	 * @return bool
	 *     - false : erreur de lecture du répertoire.
	 *     - true : action réalisée.
	 **/
	public static function nettoyer_repertoire_recursif($repertoire, $age_max = 24*3600) {
		include_spip('inc/flock');

		$repertoire = rtrim($repertoire, '/');
		if (!is_dir($repertoire)) {
			return false;
		}

		$fichiers = scandir($repertoire);
		if ($fichiers === false) {
			return false;
		}

		$fichiers = array_diff($fichiers, ['..', '.', '.ok']);
		if (!$fichiers) {
			supprimer_repertoire($repertoire);
			return true;
		}

		foreach ($fichiers as $fichier) {
			$chemin = $repertoire . DIRECTORY_SEPARATOR . $fichier;
			if (is_dir($chemin)) {
				bigup_nettoyer_repertoire_recursif($chemin, $age_max);
			}
			elseif (is_file($chemin) and !jeune_fichier($chemin, $age_max)) {
				supprimer_fichier($chemin);
			}
		}

		// à partir d'ici, on a pu possiblement vider le répertoire…
		// on le supprime s'il est devenu vide.
		$fichiers = scandir($repertoire);
		if ($fichiers === false) {
			return false;
		}

		$fichiers = array_diff($fichiers, ['..', '.', '.ok']);
		if (!$fichiers) {
			supprimer_repertoire($repertoire);
		}

		return true;
	}

}