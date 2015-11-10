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
 * @plugin     Dropzone
 * @copyright  2015
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

/**
 * Réceptionne des morceaux de fichiers envoyés par flow.js
**/
class Flow {

	private $dir = [];
	private $params = [];
	private $prefixe = 'flow';

	private $cache_dir = 'bigupload';

	/**
	 * Constructeur
	**/
	public function __construct() {}

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
	 * Des logs
	 *
	 * @param mixed $quoi
	 * @param gravite $quoi
	**/
	public function log($quoi, $gravite = _LOG_INFO_IMPORTANTE) {
		spip_log($quoi, "bigup." . $gravite);
	}

	public function debug($quoi) {
		return $this->log($quoi, _LOG_DEBUG);
	}

	public function error($quoi) {
		return $this->log($quoi, _LOG_ERREUR);
	}

	public function info($quoi) {
		return $this->log($quoi, _LOG_INFO);
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
	 * @return false|null|array
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
	 *     - string : Si fichier terminé d'uploader (réception du dernier morceau), retourne la clé utilisée dans `$_FILES` pour le décrire
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

			// au cas où le fichier complet serait déjà là…
			@unlink($this->tmpPathFile($identifier, $filename));

			// liste des morceaux
			$chunkFiles = $this->getChunkFiles($identifier);

			if ($fullFile = $this->createFileFromChunks($chunkFiles, $this->tmpPathFile($identifier, $filename))) {
				$this->info("Fichier complet recréé : " . $this->tmpPathFile($identifier, $filename));
				$this->info("Suppression des morceaux.");
				foreach ($chunkFiles as $f) {
					@unlink($f);
				}

				// on réécrit $_FILES avec les valeurs du fichier complet
				$_FILES[$key]['name'] = $filename;
				$_FILES[$key]['tmp_name'] = $fullFile;
				$_FILES[$key]['size'] = filesize($fullFile);
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$_FILES[$key]['type'] = finfo_file($finfo, $fullFile);
				$this->debug($_FILES);

				// On n'envoie rien (pas de $this->send()) : ici le fichier étant bien arrivé
				// on laisse le processus suivant se faire,
				// comme si le fichier complet avait été posté dans $_FILES
				// sur ce hit.

				// fichier complété
				return $key;

			} else {
				// on ne devrait jamais arriver là ! 
				$this->error("! Création du fichier complet en échec (" . $this->tmpPathFile($identifier, $filename) . ").");
				return $this->send(415);
			}
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
	 * Dépend de l'auteur connecté.
	 *
	 * @param string $identifier
	 * @param string $subdir Type de répertoire 
	 * @return string chemin du répertoire
	**/
	public function determine_upload($identifier = null, $subdir) {
		if (empty($this->dir[$subdir])) {
			include_spip('inc/session');
			$repertoire = sous_repertoire(_DIR_TMP, $this->cache_dir);
			$repertoire = sous_repertoire($repertoire, $subdir);
			$this->dir[$subdir] = sous_repertoire($repertoire, ($login = session_get('login')) ? $login : session_get('id_auteur'));
		}

		if ($identifier) {
			return sous_repertoire($this->dir[$subdir], $identifier);
		} else {
			return $this->dir[$subdir];
		}
	}

	/**
	 * Trouver le répertoire temporaire pour charger les morceaux de fichiers
	 *
	 * @uses determine_upload()
	 * @param string $identifier
	 * @return string chemin du répertoire
	**/
	public function determine_upload_parts($identifier = null) {
		return $this->determine_upload($identifier, 'parts');
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
	 * @param string $filename
	 * @param int $chunkNumber
	 * @return string Nom de fichier
	**/
	public function tmpChunkPathFile($identifier, $filename, $chunkNumber) {
		return $this->determine_upload_parts($identifier) . $filename . '.part' . $chunkNumber;
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
		return $this->determine_upload_final($identifier) . $filename;
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
		return file_exists($this->tmpChunkPathFile($identifier, $filename, $chunkNumber));
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
		$chunkFiles = array_diff(scandir($this->determine_upload_parts($identifier)), array('..', '.', '.ok'));

		// Utiliser un chemin complet, et aucun fichier caché.
		$chunkFiles = array_map(
			function ($f) use ($identifier) {
				if ($f and $f[0] != '.') {
					return $this->determine_upload_parts($identifier) . $f;
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
	 * @param array $crunkFiles Chemin des morceaux de fichiers à concaténer (dans l'ordre)
	 * @param string $destFile Chemin du fichier à créer avec les morceaux
	 * @return false|string
	 *     - false : erreur
	 *     - string : chemin du fichier complet sinon.
	**/
	public function createFileFromChunks($chunkFiles, $destFile) {
		$fp = fopen($destFile, 'w');
		foreach ($chunkFiles as $chunkFile) {
			fwrite($fp, file_get_contents($chunkFile));
		}
		fclose($fp);
		return file_exists($destFile) ? $destFile : false;
	}
}
