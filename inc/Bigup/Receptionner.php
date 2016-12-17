<?php

namespace Spip\Bigup;

/**
 * Gère la réception d'actions ajax
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

include_spip('inc/Bigup/LogTrait');

/**
 * Gère la réception d'actions ajax
 *
 * - Récupère les morceaux de fichiers
 * - Retourne les erreurs
 * - Supprime les fichiers demandés.
 *
 **/
class Receptionner {

	use LogTrait;

	/**
	 * Identification du formulaire, auteur, champ, tokem
	 * @var Identifier
	 */
	private $identifier = null;


	/**
	 * Nom d'une action demandée
	 *
	 * Si pas de précision => gestion par Flow
	 *
	 * @var string
	 **/
	private $action = '';

	/**
	 * Identifiant d'un fichier (en cas de suppression demandée)
	 *
	 * Cet identifiant est soit un md5 du chemin du fichier sur le serveur
	 * (envoyé dans la clé 'identifiant' des fichiers déjà présents pour ce formulaire),
	 *
	 * Soit un identifiant (uniqueIdentifier) qui sert au rangement du fichier, calculé
	 * par Flow.js ou Resumable.js à partir du nom et de la taille du fichier.
	 * Cet identifiant là est envoyé si on annule un fichier en cours de téléversement.
	 *
	 * @var string
	 **/
	private $identifiant = '';

	/**
	 * Gestion du cache Bigup
	 * @var Identifier
	 */
	private $cache = null;

	/**
	 * Constructeur
	 *
	 * @param Identifier $identifier
	 **/
	public function __construct(Identifier $identifier) {
		$this->identifier = $identifier;
		$this->cache = new Cache($identifier);
	}

	/**
	 * Constructeur depuis les paramètres dans l'environnement posté.
	 * @return Receptionner
	 */
	public static function depuisRequest() {
		$receptionner = new self(Identifier::depuisRequest());
		$receptionner->action = _request('bigup_action');
		$receptionner->identifiant = _request('identifiant');
		return $receptionner;
	}

	/**
	 * Répondre
	 *
	 * Envoie un statut HTTP de réponse et quitte, en fonction de ce qui était demandé,
	 *
	 * - soit tester un morceau de fichier,
	 * - soit réceptionner un morceau de fichier,
	 * - soit effacer un fichier
	 *
	 * Si les hash ne correspondaient pas, le programme quitte évidemment.
	 **/
	public function repondre() {
		if (!$this->identifier->verifier_token()) {
			return $this->send(403);
		}

		if ($this->action) {
			$repondre_action = 'repondre_' . $this->action;
			if (method_exists($this, $repondre_action)) {
				return $this->$repondre_action();
			}
			// Action inconnue.
			return $this->send(403);
		}

		return $this->repondre_flow();
	}


	/**
	 * Répondre le cas de suppression d'un fichier
	 *
	 * L'identifiant de fichier est le md5 du chemin de stockage.
	 **/
	public function repondre_effacer() {
		if (!$this->identifiant) {
			return $this->send(404);
		}
		// si c'est un md5, c'est l'identifiant
		if ($this->cache->enlever_fichier($this->identifiant)) {
			return $this->send(201);
		}
		return $this->send(404);
	}


	/**
	 * Répondre le cas de réception ou test de morceau de fichier
	 **/
	public function repondre_flow() {

		$flow = new Flow();
		$flow->definir_repertoire('parts', $this->cache->dir_parts());
		$flow->definir_repertoire('final', $this->cache->dir_final());
		$res = $flow->run();

		// le fichier est complet
		if (is_string($res)) {
			// remettre le fichier dans $FILES
			# $this->integrer_fichier($res);

			// envoyer quelques infos sur le fichier reçu
			$desc = $this->cache->decrire_fichier($res);
			// ne pas permettre de connaître le chemin complet
			unset($desc['pathname'], $desc['tmp_name']);

			// nettoyer le chemin du répertoire de stockage des morceaux du fichiers
			GestionRepertoires::supprimer_repertoire($this->cache->obtenir_chemin(dirname($res), false));

			$this->send(200, $desc);
		}

		if (is_int($res)) {
			$this->send($res);
		}

		$this->send(415);
	}

	/**
	 * Envoie le code header indiqué… et arrête tout.
	 *
	 * @param int $code
	 * @param array|null $data Données à faire envoyer en json
	 * @return void
	 **/
	public function send($code, $data = null) {
		$this->debug("> send $code");
		http_response_code($code);
		if ($data) {
			header("Content-Type: application/json; charset=" . $GLOBALS['meta']['charset']);
			echo json_encode($data);
		}
		exit;
	}
}