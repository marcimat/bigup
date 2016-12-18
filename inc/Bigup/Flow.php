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
	 * Gestion du cache Bigup
	 * @var Cache
	 */
	private $cache = null;

	/**
	 * Préfixe utilisé par la librairie JS lors d'une requête
	 * @var string */
	private $prefixe = 'flow';


	/**
	 * Constructeur
	**/
	public function __construct(Cache $cache) {
		$this->cache = $cache;
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
			if (!GestionRepertoires::deplacer_fichier_upload(
				$file['tmp_name'],
				$this->tmpChunkPathFile($identifier, $filename, $chunkNumber))
			) {
				return $this->send(415);
			}
		}

		// tous les morceaux recus ?
		if ($this->isFileUploadComplete($filename, $identifier, $chunkSize, $totalSize)) {
			$this->info("Chunks complets de $identifier");

			// recomposer le fichier
			$chemin_parts = $this->cache->parts->fichiers->dir_identifiant($identifier);
			$chemin_final = $this->cache->final->fichiers->dir_fichier($identifier, $filename);
			$fullFile = $this->createFileFromChunks($this->getChunkFiles($chemin_parts), $chemin_final);
			if (!$fullFile) {
				// on ne devrait jamais arriver là ! 
				$this->error("! Création du fichier complet en échec (" . $chemin_final . ").");
				return $this->send(415);
			}

			// nettoyer le chemin du répertoire de stockage des morceaux du fichiers
			GestionRepertoires::supprimer_repertoire($chemin_parts);

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
	 * Retourne le nom du fichier qui enregistre un des morceaux
	 *
	 * @param string $identifier
	 * @param string $filename
	 * @param int $chunkNumber
	 * @return string Nom de fichier
	**/
	public function tmpChunkPathFile($identifier, $filename, $chunkNumber) {
		return $this->cache->parts->fichiers->dir_fichier($identifier, $filename) . '.part' . $chunkNumber;
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

		sort($chunkFiles);

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
