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
include_spip('inc/flock');

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
	public static function creer_sous_repertoire($dest){
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
			$base = sous_repertoire($base, array_pop($create));
			if (!$base) {
				return false;
			}
		}

		if (!is_writable($base)) {
			return false;
		}

		return sous_repertoire($base, $final);
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
				self::nettoyer_repertoire_recursif($chemin, $age_max);
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


	/**
	 * Supprimer le contenu d'un répertoire et nettoie les répertoires parents s'ils sont vides
	 *
	 * @param string $chemin Chemin du répertoire à supprimer
	 * @return bool
	 */
	public static function supprimer_repertoire($chemin) {
		if (!$chemin) {
			return false;
		}
		supprimer_repertoire($chemin);
		GestionRepertoires::supprimer_repertoires_vides_parents($chemin);
		return true;
	}

	/**
	 * Supprimer les répertoires vides enfants et parents (jusqu'à _DIR_TMP) d'un répertoire.
	 *
	 * @param string $chemin Chemin du repertoire à nettoyer, dans _DIR_TMP
	 * @param bool $parents True pour nettoyer les répertoires vides parents
	 * @param bool $enfants True pour nettoyer les répertoires vides enfants
	 * @return bool
	 */
	public static function supprimer_repertoires_vides($chemin, $parents = true, $enfants = true) {
		// Se nettoyer soi et les répertoires enfants vides
		if ($enfants) {
			self::supprimer_repertoires_vides_enfants($chemin);
		}
		// Se nettoyer soi et nettoyer les répertoires parents vides
		if ($parents) {
			self::supprimer_repertoires_vides_parents($chemin);
		}
		return true;
	}

	/**
	 * Supprimer les répertoires enfants vides et moi même si vide.
	 *
	 * @param string $chemin
	 *      Chemin du répertoire à nettoyer, dans _DIR_TMP
	 * @return bool
	 */
	public static function supprimer_repertoires_vides_enfants($chemin) {
		$chemin = rtrim($chemin, DIRECTORY_SEPARATOR);
		$chemin = substr($chemin, strlen(_DIR_TMP));

		if (is_dir(_DIR_TMP . $chemin)) {
			$fichiers = scandir(_DIR_TMP . $chemin);
			if ($fichiers !== false) {
				$fichiers = array_diff($fichiers, ['..', '.', '.ok']);
				if ($fichiers) {
					foreach ($fichiers as $fichier) {
						$fichier = _DIR_TMP . $chemin . DIRECTORY_SEPARATOR . $fichier;
						if (is_dir($fichier)) {
							self::supprimer_repertoires_vides_enfants($fichier);
						}
					}
				} else {
					supprimer_repertoire(_DIR_TMP . $chemin);
				}
			}
			if ($fichiers and is_dir(_DIR_TMP . $chemin)) {
				$fichiers = scandir(_DIR_TMP . $chemin);
				if ($fichiers !== false) {
					$fichiers = array_diff($fichiers, ['..', '.', '.ok']);
					if (!$fichiers) {
						supprimer_repertoire(_DIR_TMP . $chemin);
					}
				}
			}
		}
		return true;
	}

	/**
	 * Supprimer ce répertoire si vide et ses parents s'ils deviennent vides
	 *
	 * @param string $chemin
	 *      Chemin du répertoire à nettoyer, dans _DIR_TMP
	 * @return bool
	 */
	public static function supprimer_repertoires_vides_parents($chemin) {
		$chemin = rtrim($chemin, DIRECTORY_SEPARATOR);
		$chemin = substr($chemin, strlen(_DIR_TMP));

		while ($chemin and ($chemin !== '.')) {
			if (!is_dir(_DIR_TMP . $chemin)) {
				$chemin = dirname($chemin);
				continue;
			}

			$fichiers = scandir(_DIR_TMP . $chemin);
			if ($fichiers === false) {
				$chemin = dirname($chemin);
				continue;
			}

			$fichiers = array_diff($fichiers, ['..', '.', '.ok']);
			if (!$fichiers) {
				supprimer_repertoire(_DIR_TMP . $chemin);
				$chemin = dirname($chemin);
				continue;
			}

			return true;
		}
		return true;
	}
}