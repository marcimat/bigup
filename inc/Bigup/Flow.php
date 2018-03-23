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
 * Retours de la classe Flow
 * Indique le code de réponse http, et d’éventuelles données.
 */
class FlowResponse {
	public $code = 415;
	public $data = null;
	public function __construct($code, $data = null) {
		$this->code = $code;
		$this->data = $data;
	}
}

/**
 * Réceptionne des morceaux de fichiers envoyés par flow.js
**/
class Flow {

	use LogTrait;

	/**
	 * Gestion du cache Bigup
	 * @var Cache
	 */
	private $cache = null;

	/**
	 * Préfixe utilisé par la librairie JS lors d'une requête
	 * @var string */
	private $prefixe = 'flow';

	/**
	 * Taille de fichier maximum
	 */
	private $maxSizeFile = 0;

	/**
	 * Constructeur
	 * @param Cache $cache
	**/
	public function __construct(Cache $cache) {
		$this->cache = $cache;
	}

	/**
	 * Définir la taille maximale des fichiers
	 * @param int $size En Mo
	 */
	public function setMaxSizeFile($size) {
		$this->maxSizeFile = intval($size);
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
	 * Le script retourne
	 * - le chemin du fichier complet si c’est le dernier morceau envoyé,
	 * - sinon un [code http, data] à envoyer
	 * 
	 * @return FlowResponse|string
	 *     - string : chemin du fichier terminé d’uploadé
	**/
	public function run() {
		if (!$this->trouverPrefixe()) {
			return $this->response(415);
		}
		if (!empty($_POST) and !empty($_FILES) ) {
			return $this->handleChunk();
		} elseif (!empty($_GET)) {
			return $this->handleTestChunk();
		}
		return $this->response(415);
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
	 * Retours à faire partir au navigateur
	 *
	 * @param int $code
	 * @param array|null $data
	 * @return FlowResponse
	**/
	public function response($code, $data = null) {
		return new FlowResponse($code, $data);
	}

	/**
	 * Retours avec texte d’erreur à faire au navigateur
	 *
	 * @param string $message
	 * @param int $code
	 * @return FlowResponse
	 **/
	public function responseError($message, $code = 415) {
		return $this->response($code, [
			'error' => $message
		]);
	}

	/**
	 * Teste si le morceau de fichier indiqué est déjà sur le serveur
	 *
	 * @return FlowResponse
	**/
	public function handleTestChunk() {
		$identifier  = $this->_request('identifier');
		$filename    = $this->_request('filename');
		$chunkNumber = $this->_request('chunkNumber');

		$this->info("Test chunk $identifier n°$chunkNumber");

		if (!$this->isChunkUploaded($identifier, $filename, $chunkNumber)) {
			return $this->response(204);
		} else {
			return $this->response(200);
		}
	}

	/**
	 * Enregistre un morceau de fichier
	 *
	 * @return FlowResponse|string
	 *     - string : Si fichier terminé d'uploader (réception du dernier morceau), retourne le chemin du fichier
	**/
	public function handleChunk() {
		$identifier  = $this->_request('identifier');
		$filename    = $this->_request('filename');
		$chunkNumber = $this->_request('chunkNumber');
		$chunkSize   = $this->_request('chunkSize');
		$totalSize   = $this->_request('totalSize');
		$maxSize = $this->maxSizeFile * 1024 * 1024;

		$this->info("Réception chunk $identifier n°$chunkNumber");

		if ($maxSize and $totalSize > $maxSize) {
			$this->info("Fichier reçu supérieur à taille autorisée");
			return $this->responseError(_T("bigup:erreur_taille_max", ['taille' => taille_en_octets($maxSize)]));
		}

		$file = reset($_FILES);

		if (!$this->isChunkUploaded($identifier, $filename, $chunkNumber)) {
			if (!GestionRepertoires::deplacer_fichier_upload(
				$file['tmp_name'],
				$this->tmpChunkPathFile($identifier, $filename, $chunkNumber))
			) {
				return $this->response(415);
			}
		}

		// tous les morceaux recus ?
		if ($this->isFileUploadComplete($filename, $identifier, $chunkSize, $totalSize)) {
			$this->info("Chunks complets de $identifier");

			$chemin_parts = $this->cache->parts->fichiers->dir_fichier($identifier, $filename);
			$chemin_final = $this->cache->final->fichiers->path_fichier($identifier, $filename);

			// recomposer le fichier
			$fullFile = $this->createFileFromChunks($this->getChunkFiles($chemin_parts), $chemin_final);
			if (!$fullFile) {
				// on ne devrait jamais arriver là ! 
				$this->error("! Création du fichier complet en échec (" . $chemin_final . ").");
				return $this->response(415);
			}

			// créer les infos du fichiers
			$this->cache->final->fichiers->decrire_fichier($identifier, [
				'name' => $filename,
				'tmp_name' => $fullFile,
				'size' => $totalSize,
				'type' => $file['type'],
				'error' => 0, // hum
			]);

			// nettoyer le chemin du répertoire de stockage des morceaux du fichiers
			GestionRepertoires::supprimer_repertoire($chemin_parts);

			return $fullFile;
		}

		// morceau bien reçu, mais pas encore le dernier…
		return $this->response(200);
	}

	/**
	 * Retourne le nom du fichier qui enregistre un des morceaux
	 *
	 * @param string $identifier
	 * @param string $filename
	 * @param int $chunkNumber
	 * @return string Nom de fichier
	**/
	public function tmpChunkPathFile($identifier, $filename, $chunkNumber) {
		return $this->cache->parts->fichiers->path_fichier($identifier, $filename) . '.part' . $chunkNumber;
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
	 * @param int $chunkSize
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
	 * @param string $chemin
	 *     Chemin du répertoire contenant les morceaux de fichiers
	 * @return array
	 *     Liste de chemins de fichiers
	**/
	public function getChunkFiles($chemin) {
		// Trouver tous les fichiers du répertoire
		$chunkFiles = array_diff(scandir($chemin), ['..', '.', '.ok']);

		// Utiliser un chemin complet, et aucun fichier caché.
		$chunkFiles = array_map(
			function ($f) use ($chemin) {
				if ($f and $f[0] != '.') {
					return $chemin . DIRECTORY_SEPARATOR . $f;
				}
				return '';
			},
			$chunkFiles
		);
		$chunkFiles = array_filter($chunkFiles);

		natsort($chunkFiles);

		return $chunkFiles;
	}

	/**
	 * Recrée le fichier complet à partir des morceaux de fichiers
	 *
	 * Supprime les morceaux si l'opération réussie.
	 * 
	 * @param array $chunkFiles
	 *     Chemin des morceaux de fichiers à concaténer (dans l'ordre)
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

		if (!GestionRepertoires::creer_sous_repertoire(dirname($destFile))) {
			return false;
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
