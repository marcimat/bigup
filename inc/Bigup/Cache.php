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
	 * Pouvoir obtenir les propriétés privées sans les modifier.
	 * @param string $property
	 */
	public function __isset($property) {
		if (property_exists($this, $property)) {
			return isset($this->$property);
		}
		return false;
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
}