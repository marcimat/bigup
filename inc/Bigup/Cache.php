<?php

namespace Spip\Bigup;

/**
 * Gère le cache des fichiers dans tmp/bigupload
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

include_spip('inc/Bigup/LogTrait');

/**
 * Gère le cache des fichiers dans tmp/bigupload
 **/
class Cache {

	use LogTrait;

	/**
	 * Identification du formulaire, auteur, champ, tokem
	 * @var Identifier
	 */
	private $identifier = null;

	/**
	 * Nom du répertoire, dans _DIR_TMP, qui va stocker les fichiers et morceaux de fichiers
	 * @var string */
	private $cache_dir = 'bigupload';

	/**
	 * Chemin du répertoire stockant les morceaux de fichiers
	 * @var string */
	private $dir_parts = '';

	/**
	 * Chemin du répertoire stockant les fichiers terminés
	 * @var string */
	private $dir_final = '';


	public function construct(Identifier $identifier) {
		$this->identifier = $identifier;
		$this->dir_parts = $this->calculer_chemin_repertoire('parts');
		$this->dir_final = $this->calculer_chemin_repertoire('final');
	}

	/**
	 * Calcule un chemin de répertoire de travail d'un type donné
	 * @param string $type
	 *    Type de répertoire de cache 'parts' ou 'final'.
	 * @return string
	 **/
	public function calculer_chemin_repertoire($type) {
		return
			_DIR_TMP . $this->cache_dir
			. DIRECTORY_SEPARATOR . $type
			. DIRECTORY_SEPARATOR . $this->identifier->auteur
			. DIRECTORY_SEPARATOR . $this->identifier->formulaire
			. DIRECTORY_SEPARATOR . $this->identifier->formulaire_identifiant
			. DIRECTORY_SEPARATOR . $this->identifier->champ;
	}

	/**
	 * Retrouve un nom de champ depuis un chemin de cache de fichier
	 *
	 * @param string $chemin
	 *     Chemin de stockage du fichier dans le cache de bigupload
	 * @return string
	 *     Nom du champ (valeur de l'attribut name de l'input d'origine)
	 */
	function retrouver_champ_depuis_chemin($chemin) {
		return basename(dirname(dirname($chemin)));
	}

	/**
	 * À partir d'un chemin de stockage final ou partiel d'un fichier
	 * dans le cache bigup, retrouver le chemin final ou partiel correspondant
	 *
	 * @param string $chemin
	 * @param bool $final
	 *     Retourne le chemin final, sinon le chemin partiel
	 * @return bool
	 */
	function obtenir_chemin($chemin, $final = true) {
		// on vérifie que ce chemin concerne bigup uniquement
		if (strpos($chemin, $this->dir_final) === 0) {
			$path = substr($chemin, strlen($this->dir_final));
		} elseif (strpos($chemin, $this->dir_parts) === 0) {
			$path = substr($chemin, strlen($this->dir_final));
		} else {
			return false;
		}
		return ($final ? $this->dir_final : $this->dir_parts) . $path;
	}

	/**
	 * Retourne la liste des fichiers complets, classés par champ
	 *
	 * @return array Liste [ champ => [ chemin ]]
	 **/
	public function trouver_fichiers_complets() {
		// la théorie veut ce rangement :
		// $dir/{champ}/{identifiant_fichier}/{nom du fichier.extension}
		$directory = $this->dir_final;

		// pas de répertoire… pas de fichier… simple comme bonjour :)
		if (!is_dir($directory)) {
			return [];
		}

		$liste = [];

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory)
		);

		foreach ($files as $filename) {
			if ($filename->isDir()) continue; // . ..
			if ($filename->getFilename()[0] == '.') continue; // .ok

			$chemin = $filename->getPathname();
			$champ = $this->retrouver_champ_depuis_chemin($chemin);

			if (empty($liste[$champ])) {
				$liste[$champ] = [];
			}
			$liste[$champ][] = $this->decrire_fichier($chemin);
			$this->debug("Fichier retrouvé : $chemin");
		}

		return $liste;
	}

	/**
	 * Décrire un fichier (comme dans `$_FILES`)
	 *
	 * @uses retrouver_champ_depuis_chemin()
	 * @param string $chemin
	 *     Chemin du fichier dans le cache de bigup.
	 * @return array
	 **/
	public function decrire_fichier($chemin) {
		$filename = basename($chemin);
		$extension = pathinfo($chemin, PATHINFO_EXTENSION);
		$champ = $this->retrouver_champ_depuis_chemin($chemin);
		include_spip('action/ajouter_documents');
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$desc = [
			// présent dans $_FILES
			'name' => $filename,
			'tmp_name' => $chemin,
			'size' => filesize($chemin),
			'type' => finfo_file($finfo, $chemin),
			'error' => 0, // hum
			// informations supplémentaires (pas dans $_FILES habituellement)
			'pathname' => $chemin,
			'identifiant' => md5($chemin),
			'extension' => corriger_extension(strtolower($extension)),
			'champ' => $champ,
		];
		return $desc;
	}


	/**
	 * Enlève un fichier complet dont l'identifiant est indiqué
	 *
	 * @param string|array $identifiants
	 *     Identifiant ou liste d'identifiants de fichier
	 * @return bool True si le fichier est trouvé (et donc enlevé)
	 **/
	public function enlever_fichier_depuis_identifiants($identifiants) {
		$liste = $this->trouver_fichiers_complets();
		if (!is_array($identifiants)) {
			$identifiants = [$identifiants];
		}
		$this->debug("Demande de suppression de fichier : " . implode(', ', $identifiants));

		foreach ($liste as $champ => $fichiers) {
			foreach ($fichiers as $description) {
				if (in_array($description['identifiant'], $identifiants)) {
					// en théorie, le chemin 'parts' a déjà été nettoyé
					$this->supprimer_repertoire(dirname($description['pathname']));
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Enlève un fichier (probablement partiel) dont le nom est indiqué
	 *
	 * @param string|array $repertoires
	 *     Un repertoire ou liste de répertoires de stockage du fichier.
	 *     Il correspond au `uniqueIdentifier` transmis par le JS
	 * @return bool True si le fichier est trouvé (et donc enlevé)
	 **/
	public function enlever_fichier_depuis_repertoires($repertoires) {
		if (!is_array($repertoires)) {
			$repertoires = [$repertoires];
		}
		foreach ($repertoires as $repertoire) {
			$this->debug("Demande de suppression du fichier dans : $repertoire");
			$this->supprimer_repertoire($this->dir_final . DIRECTORY_SEPARATOR . $repertoire);
		}
		return true;
	}


	/**
	 * Supprimer le répertoire indiqué et les répertoires parents éventuellement
	 *
	 * Si l'on indique une arborescence dans tmp/bigup/final/xxx, le répertoire
	 * correspondant dans tmp/bigup/parts/xxx sera également supprimé, et inversement.
	 *
	 * @param string $chemin
	 *     Chemin du répertoire stockant un fichier bigup
	 * @return bool
	 */
	function supprimer_repertoire($chemin) {
		// Suppression du contenu du fichier final
		if ($dir = $this->obtenir_chemin($chemin, true)) {
			GestionRepertoires::supprimer_repertoire($dir);
		}
		// Suppression du contenu des morceaux du fichier
		if ($dir = $this->obtenir_chemin($chemin, false)) {
			GestionRepertoires::supprimer_repertoire($dir);
		}
		return true;
	}
}