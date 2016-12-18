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
	 * Cache des morceaux de fichiers
	 * @var string */
	private $parts = '';

	/**
	 * Cache des fichiers complets
	 * @var string */
	private $final = '';

	/**
	 * Constructeur
	 * @param Identifier $identifier
	 */
	public function __construct(Identifier $identifier) {
		$this->identifier = $identifier;
		$this->parts = new CacheRepertoire($this, 'parts');
		$this->final = new CacheRepertoire($this, 'final');
	}

	/**
	 * Pouvoir obtenir les propriétés privées sans les modifier.
	 * @param string $property
	 */
	public function __get($property) {
		if (property_exists($this, $property)) {
			return $this->$property;
		}
		$this->debug("Propriété `$property` demandée mais inexistante.");
		return null;
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
	public function obtenir_chemin($chemin, $final = true) {
		// on vérifie que ce chemin concerne bigup uniquement
		if (strpos($chemin, $this->final->dir) === 0) {
			$path = substr($chemin, strlen($this->final->dir));
		} elseif (strpos($chemin, $this->parts->dir) === 0) {
			$path = substr($chemin, strlen($this->parts->dir));
		} else {
			return false;
		}
		return ($final ? $this->final->dir : $this->parts->dir) . $path;
	}

	/**
	 * Supprimer les répertoires caches relatifs à ce formulaire / auteur
	 *
	 * Tous les fichiers partiels ou complets seront effacés,
	 * et le cache sera nettoyé
	 *
	 * @return bool
	 */
	function supprimer_repertoires() {
		$this->final->supprimer_repertoire();
		$this->parts->supprimer_repertoire();
		return true;
	}

	/**
	 * Supprimer le fichier indiqué par son identifiant
	 * @return bool
	 */
	function supprimer_fichier($identifiant) {
		$this->final->supprimer_fichier($identifiant);
		$this->parts->supprimer_fichier($identifiant);
		return true;
	}

	/**
	 * Décrire un fichier (comme dans `$_FILES`)
	 *
	 * @uses retrouver_champ_depuis_chemin()
	 * @param string $chemin
	 *     Chemin du fichier dans le cache de bigup.
	 * @return array
	 **/
	public static function decrire_fichier($chemin) {
		$filename = basename($chemin);
		$extension = pathinfo($chemin, PATHINFO_EXTENSION);
		$champ = self::retrouver_champ_depuis_chemin($chemin);
		$identifiant = self::retrouver_identifiant_depuis_chemin($chemin);
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
			'identifiant' => $identifiant,
			'extension' => corriger_extension(strtolower($extension)),
			'champ' => $champ,
		];
		return $desc;
	}

	/**
	 * Retrouve un nom de champ depuis un chemin de cache de fichier
	 *
	 * @param string $chemin
	 *     Chemin de stockage du fichier dans le cache de bigupload
	 * @return string
	 *     Nom du champ (valeur de l'attribut name de l'input d'origine)
	 */
	public static function retrouver_champ_depuis_chemin($chemin) {
		return basename(dirname(dirname($chemin)));
	}

	/**
	 * Retrouve un nom de champ depuis un chemin de cache de fichier
	 *
	 * @param string $chemin
	 *     Chemin de stockage du fichier dans le cache de bigupload
	 * @return string
	 *     Identifiant du fichier
	 */
	public static function retrouver_identifiant_depuis_chemin($chemin) {
		return basename(dirname($chemin));
	}
}