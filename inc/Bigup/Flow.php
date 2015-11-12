<?php

namespace Spip\Bigup;

/**
 * Intégration de flow.js (ou resumable.js) côté PHP
 *
 * @note
 *     Le fonctionnement est sensiblement le même entre resumable.js et le fork flow.js
 *     Seul le nom du préfixe des variables change
 *
 * @link https://github.com/dilab/resumable.php Inspiration
 * @link https://github.com/flowjs/flow-php-server Autre implémentation pour Flow.
 *
 * @plugin     Bigup
 * @copyright  2015
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */
 
include_spip('inc/Bigup/LogTrait');

/**
 * Réceptionne des morceaux de fichiers envoyés par flow.js
**/
class Flow {

	use LogTrait;

	/**
	 * Chemins des répertoires de travail (dans _DIR_TMP)
	 * Peut contenir la clé :
	 * - 'parts' pour les morceaux de fichiers
	 * - 'final' pour les fichiers complétés
	 * 
	 * @var array */
	private $dir = [];

	/**
	 * ?
	 * @var array */
	private $params = [];

	/**
	 * Préfixe utilisé par la librairie JS lors d'une requête
	 * @var string */
	private $prefixe = 'flow';

	/**
	 * Nom du répertoire, dans _DIR_TMP, qui va stocker les fichiers et morceaux de fichiers
	 * @var string */
	private $cache_dir = 'bigupload';

	/**
	 * Nom du formulaire qui utilise flow
	 * @var string */
	private $formulaire = '';

	/**
	 * Identifie un formulaire par rapport à un autre identique sur la même page ayant un appel différent.
	 * @var string */
	private $formulaire_identifiant = '';

	/**
	 * Nom du champ dans le formulaire qui utilise flow
	 * @var string */
	private $champ = '';

	/**
	 * Constructeur
	**/
	public function __construct() {}


	/**
	 * Définir les répertoires de travail
	 *
	 * @param string $type
	 * @param string $dir
	**/
	public function definir_repertoire($type, $chemin) {
		$this->dir[$type] = $chemin;
	}

	/**
	 * Trouve le prefixe utilisé pour envoyer les données
	 *
	 * La présence d'une des variables signale un envoi effectué par une des librairies js utilisée.
	 * 
	 * - 'flow' si flow.js
	 * - 'resumable' si resumable.js
	 *
	 * @return bool True si préfixe présent et trouvé, false sinon.
	**/
	public function trouverPrefixe() {
		if (_request('flowIdentifier')) {
			$this->prefixe = 'flow';
			return true;
		}
		if (_request('resumableIdentifier')) {
			$this->prefixe = 'resumable';
			return true;
		}
		return false;
	}



	/**
	 * Tester l'arrivée du javascript et agir en conséquence
	 *
	 * 2 possibilités :
	 * 
	 * - Le JS demande si un morceau de fichier est déjà présent (par la méthode GET)
	 * - Le JS poste une partie d'un fichier (par la méthode POST)
	 *
	 * Le script quittera tout seul si l'un ou l'autre des cas se présente,
	 * sauf si on vient de poster le dernier morceau d'un fichier.
	 * 
	 * @return false|null|string
	**/
	public function run() {
		if (!$this->trouverPrefixe()) {
			return false;
		}
		if (!empty($_POST) and !empty($_FILES) ) {
			return $this->handleChunk();
		}
		elseif (!empty($_GET)) {
			return $this->handleTestChunk();
		}
		return false;
	}


	/**
	 * Envoie le code header indiqué… et arrête tout.
	 *
	 * @param int $code
	 * @return void
	**/
	public function send($code) {
		$this->debug("> send $code");
		http_response_code($code);
		exit;
	}

	/**
	 * Teste si le morceau de fichier indiqué est déjà sur le serveur
	 *
	 * @return void
	**/
	public function handleTestChunk() {
		$identifier  = $this->_request('identifier');
		$filename    = $this->_request('filename');
		$chunkNumber = $this->_request('chunkNumber');

		$this->info("Test chunk $identifier n°$chunkNumber");

		if (!$this->isChunkUploaded($identifier, $filename, $chunkNumber)) {
			return $this->send(204);
		} else {
			return $this->send(200);
		}
	}

	/**
	 * Enregistre un morceau de fichier
	 *
	 * @return void|false|string
	 *     - exit : Si morceau de fichier reçu (et que ce n'est pas le dernier), la fonction retourne un statut http et quitte.
	 *     - string : Si fichier terminé d'uploader (réception du dernier morceau), retourne le chemin du fichier
	 *     - false  : si aucun morceau de fichier reçu.
	**/
	public function handleChunk() {
		$identifier  = $this->_request('identifier');
		$filename    = $this->_request('filename');
		$chunkNumber = $this->_request('chunkNumber');
		$chunkSize   = $this->_request('chunkSize');
		$totalSize   = $this->_request('totalSize');

		$this->info("Réception chunk $identifier n°$chunkNumber");

		$file = reset($_FILES);
		$key = key($_FILES);

		if (!$this->isChunkUploaded($identifier, $filename, $chunkNumber)) {
			if (!$this->deplacer_fichier_upload($file['tmp_name'], $this->tmpChunkPathFile($identifier, $filename, $chunkNumber))) {
				return $this->send(415);
			}
		}

		// tous les morceaux recus ?
		if ($this->isFileUploadComplete($filename, $identifier, $chunkSize, $totalSize)) {
			$this->info("Chunks complets de $identifier");

			// recomposer le fichier
			$fullFile = $this->createFileFromChunks(
				$this->getChunkFiles($identifier),
				$this->tmpPathFile($identifier, $filename)
			);
			if (!$fullFile) {
				// on ne devrait jamais arriver là ! 
				$this->error("! Création du fichier complet en échec (" . $this->tmpPathFile($identifier, $filename) . ").");
				return $this->send(415);
			}

			return $fullFile;
		} else {
			// morceau bien reçu, mais pas encore le dernier… 
			return $this->send(200);
		}

		// pas de morceaux recu, pas de fichier complété
		return false;
	}

	/**
	 * Retrouve un paramètre de flow
	 *
	 * @param string $nom
	 * @return mixed
	**/
	public function _request($nom) {
		return _request($this->prefixe . ucfirst($nom));
	}

	/**
	 * Trouver le chemin d'un répertoire temporaire 
	 *
	 * @param string $identifier
	 * @param string $subdir Type de répertoire
	 * @param bool $nocreate true pour ne pas créer le répertoire s'il manque.
	 * @return string|false
	 *     - string : chemin du répertoire
	 *     - false : échec.
	**/
	public function determine_upload($identifier, $subdir, $nocreate = false) {
		if (empty($this->dir[$subdir])) {
			return false;
		}

		$dir = $this->dir[$subdir] . DIRECTORY_SEPARATOR . $identifier;

		if ($nocreate) {
			return $dir;
		}

		include_spip('bigup_fonctions');
		if (!bigup_sous_repertoires($dir)) {
			return false;
		}

		return $dir;
	}

	/**
	 * Trouver le répertoire temporaire pour charger les morceaux de fichiers
	 *
	 * @uses determine_upload()
	 * @param string $identifier
	 * @param bool $nocreate
	 * @return string chemin du répertoire
	**/
	public function determine_upload_parts($identifier = null, $nocreate = false) {
		return $this->determine_upload($identifier, 'parts', $nocreate);
	}


	/**
	 * Trouver le répertoire temporaire pour stocker les fichiers complets reconstitués
	 *
	 * @uses determine_upload()
	 * @param string $identifier
	 * @return string chemin du répertoire
	**/
	public function determine_upload_final($identifier = null) {
		return $this->determine_upload($identifier, 'final');
	}

	/**
	 * Déplacer ou copier un fichier
	 *
	 * @note
	 *     Proche de inc/documents: deplacer_fichier_upload()
	 *     mais sans l'affichage d'erreur éventuelle.
	 * 
	 * @uses _DIR_RACINE
	 * @uses spip_unlink()
	 * 
	 * @param string $source
	 *     Fichier source à copier
	 * @param string $dest
	 *     Fichier de destination
	 * @param bool $move
	 *     - `true` : on déplace le fichier source vers le fichier de destination
	 *     - `false` : valeur par défaut. On ne fait que copier le fichier source vers la destination.
	 * @return bool|mixed|string
	 */
	function deplacer_fichier_upload($source, $dest, $move=false) {
		// Securite
		if (substr($dest, 0, strlen(_DIR_RACINE)) == _DIR_RACINE) {
			$dest = _DIR_RACINE . preg_replace(',\.\.+,', '.', substr($dest, strlen(_DIR_RACINE)));
		} else {
			$dest = preg_replace(',\.\.+,', '.', $dest);
		}

		if ($move) {
			$ok = @rename($source, $dest);
		} else {
			$ok = @copy($source, $dest);
		}
		if (!$ok) {
			$ok = @move_uploaded_file($source, $dest);
		}
		if ($ok) {
			@chmod($dest, _SPIP_CHMOD & ~0111);
		}

		return $ok ? $dest : false;
	}

	/**
	 * Retourne le nom du fichier qui enregistre un des morceaux
	 *
	 * @param string $identifier
	 * @param string $filename
	 * @param int $chunkNumber
	 * @param bool $nocreate
	 *     - true pour ne pas créer le répertoire s'il manque.
	 * @return string Nom de fichier
	**/
	public function tmpChunkPathFile($identifier, $filename, $chunkNumber, $nocreate = false) {
		return $this->determine_upload_parts($identifier, $nocreate) . DIRECTORY_SEPARATOR . $filename . '.part' . $chunkNumber;
	}

	/**
	 * Retourne le chemin du fichier final
	 *
	 * Note, ce n'est pas l'emplacement définitif après traitements,
	 * Juste l'emplacement qui concatène tous les morceaux.
	 *
	 * @param string $filename
	 * @param int $chunkNumber
	 * @return string Nom de fichier
	**/
	public function tmpPathFile($identifier, $filename) {
		return $this->determine_upload_final($identifier) . DIRECTORY_SEPARATOR . $filename;
	}

	/**
	 * Indique si un morceau de fichier a déjà été sauvegardé
	 *
	 * @param string $identifier
	 * @param string $filename
	 * @param int $chunkNumber
	 * @return bool True si présent
	**/
	public function isChunkUploaded($identifier, $filename, $chunkNumber) {
		return file_exists($this->tmpChunkPathFile($identifier, $filename, $chunkNumber, true));
	}

	/**
	 * Indique si tous les morceaux d'un fichier ont été reçus
	 *
	 * @param string $filename
	 * @param string $identifier
	 * @param int $chunksize
	 * @param int $totalSize
	 * @return bool
	**/
	public function isFileUploadComplete($filename, $identifier, $chunkSize, $totalSize) {
		if ($chunkSize <= 0) {
			return false;
		}
		$numOfChunks = intval($totalSize / $chunkSize) + ($totalSize % $chunkSize == 0 ? 0 : 1);
		for ($i = 1; $i < $numOfChunks; $i++) {
			if (!$this->isChunkUploaded($identifier, $filename, $i)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Retrouve les morceaux d'un fichier, dans l'ordre !
	 *
	 * @param string $identifier
	 * @return array Liste de chemins de fichiers
	**/
	public function getChunkFiles($identifier) {
		// Trouver tous les fichiers du répertoire
		$chunkFiles = array_diff(scandir($this->determine_upload_parts($identifier, true)), ['..', '.', '.ok']);

		// Utiliser un chemin complet, et aucun fichier caché.
		$chunkFiles = array_map(
			function ($f) use ($identifier) {
				if ($f and $f[0] != '.') {
					return $this->determine_upload_parts($identifier, true) . DIRECTORY_SEPARATOR . $f;
				}
				return '';
			},
			$chunkFiles
		);
		$chunkFiles = array_filter($chunkFiles);

		sort($chunkFiles);

		return $chunkFiles;
	}

	/**
	 * Recrée le fichier complet à partir des morceaux de fichiers
	 *
	 * Supprime les morceaux si l'opération réussie.
	 * 
	 * @param array $crunkFiles Chemin des morceaux de fichiers à concaténer (dans l'ordre)
	 * @param string $destFile Chemin du fichier à créer avec les morceaux
	 * @return false|string
	 *     - false : erreur
	 *     - string : chemin du fichier complet sinon.
	**/
	public function createFileFromChunks($chunkFiles, $destFile) {
		// au cas où le fichier complet serait déjà là…
		if (file_exists($destFile)) {
			@unlink($destFile);
		}

		// Si un seul morceau c'est qu'il est complet.
		// on le déplace simplement au bon endroit
		if (count($chunkFiles) == 1) {
			if (@rename($chunkFiles[0], $destFile)) {
				$this->info("Fichier complet déplacé : " . $destFile);
				return $destFile;
			}
		}

		$fp = fopen($destFile, 'w');
		foreach ($chunkFiles as $chunkFile) {
			fwrite($fp, file_get_contents($chunkFile));
		}
		fclose($fp);

		if (!file_exists($destFile)) {
			return false;
		}

		$this->info("Fichier complet recréé : " . $destFile);
		$this->debug("Suppression des morceaux.");
		foreach ($chunkFiles as $f) {
			@unlink($f);
		}

		return $destFile;
	}
}
