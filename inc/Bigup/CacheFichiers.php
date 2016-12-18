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


/**
 * Gère le cache des fichiers dans tmp/bigupload
 *
 **/
class CacheFichiers {

	use LogTrait;

	/**
	 * Cache racine du stockage
	 * @var CacheRepertoire */
	private $cache = null;

	/**
	 * Nom du champ
	 * @var string */
	private $champ = null;

	/**
	 * Constructeur
	 *
	 * @param string $dir_racine
	 *     Chemin de stockage de ces fichiers pour ce formulaire
	 * @param string $nom_du_champ
	 *     Nom du champ (valeur de l'attribut name) pour ces fichiers
	 */
	public function __construct(CacheRepertoire $cache, $champ) {
		$this->cache = $cache;
		$this->champ = $champ;
	}

	/**
	 * Retourne le chemin du cache pour ce champ du formulaire
	 * @return string
	 */
	public function dir_champ() {
		return $this->cache->dir . DIRECTORY_SEPARATOR . $this->champ;
	}

	/**
	 * Retourne le chemin du cache pour cet identifiant de fichier du formulaire
	 * @return string
	 */
	public function dir_identifiant($identifiant) {
		return $this->dir($identifiant);
	}

	/**
	 * Retourne le chemin du cache pour cet identifiant de fichier et nom de fichier du formulaire
	 * @return string
	 */
	public function dir_fichier($identifiant, $fichier) {
		return $this->dir($identifiant, $fichier);
	}

	/**
	 * Retourne le chemin du répertoire stockant les morceaux de fichiers
	 *
	 * Si un identifiant décrivant un fichier est fourni, retourne le chemin
	 * correspondant à cet identifiant de fichier.
	 *
	 * 	Si un fichier est fourni, en plus de l'identifiant, retourne le chemin
	 * correspondant au fichier
	 *
	 * @param string|null $identifiant
	 *     Chaîne identifiant un fichier
	 * @param string|null $fichier
	 *     Nom du fichier
	 * @return string|false
	 */
	private function dir($identifiant = null, $fichier = null) {
		if (is_null($identifiant) and is_null($fichier)) {
			return $this->dir_champ();
		} elseif ($fichier and $identifiant) {
			return $this->dir_champ()
				. DIRECTORY_SEPARATOR
				. $this->hash_identifiant($identifiant)
				. DIRECTORY_SEPARATOR
				. $this->nommer_fichier($fichier);
		} elseif ($identifiant and !$fichier) {
			return $this->dir_champ()
				. DIRECTORY_SEPARATOR
				. $this->hash_identifiant($identifiant);
		}
		return false;
	}

	/**
	 * Retourne le nom du répertoire / hash relatif à l'identifiant de fichier indiqué.
	 *
	 * Si l'identifiant transmis est déjà un hash, le retourne directement
	 *
	 * @param string $identifiant
	 * @return string
	 */
	public static function hash_identifiant($identifiant) {
		if (
			strlen($identifiant) == 10
			and $identifiant[0] == '@'
			and $identifiant[9] == '@'
			and ctype_xdigit(substr($identifiant, 1, -1))
		) {
			return $identifiant;
		}
		return '@' . substr(md5($identifiant), 0, 8) . '@';
	}

	/**
	 * Reformater le nom du fichier pour l'écrire sur le serveur
	 *
	 * @see copier_document() dans SPIP
	 * @param string $filename
	 * @return string Nom du fichier corrigé
	 */
	public static function nommer_fichier($filename) {
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		include_spip('action/ajouter_documents');
		$extension = corriger_extension($extension);
		$nom = preg_replace(
			"/[^.=\w-]+/", "_",
			translitteration(
				preg_replace("/\.([^.]+)$/", "",
					preg_replace("/<[^>]*>/", '', basename($filename))
				)
			)
		);
		return $nom . '.' . $extension;
	}


}