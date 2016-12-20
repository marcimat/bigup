<?php

namespace Spip\Bigup;

/**
 * Gère l'identification du formulaire
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

include_spip('inc/Bigup/LogTrait');

/**
 * Gère l'identification du formulaire
 **/
class Identifier {

	use LogTrait;

	/**
	 * Login ou identifiant de l'auteur qui intéragit
	 * @var string */
	private $auteur = '';

	/**
	 * Nom du formulaire qui utilise flow
	 * @var string */
	private $formulaire = '';

	/**
	 * Hash des arguments du formulaire
	 * @var string */
	private $formulaire_args = '';

	/**
	 * Identifie un formulaire par rapport à un autre identique sur la même page ayant un appel différent.
	 * @var string */
	private $formulaire_identifiant = '';

	/**
	 * Nom du champ dans le formulaire qui utilise flow
	 * @var string */
	private $champ = '';

	/**
	 * Token de la forme `champ:time:cle`
	 * @var string
	 **/
	private $token = '';

	/**
	 * Expiration du token (en secondes)
	 *
	 * @todo À définir en configuration
	 * @var int
	 **/
	private $token_expiration = 3600 * 24;


	/**
	 * Constructeur
	 *
	 * @param string $formulaire
	 *     Nom du formulaire.
	 * @param string $formulaire_args
	 *     Hash du formulaire
	 * @param string $token
	 *     Jeton d'autorisation
	 **/
	public function __construct($formulaire = '', $formulaire_args = '', $token = '') {
		$this->token = $token;
		$this->formulaire = $formulaire;
		$this->formulaire_args = $formulaire_args;
		$this->identifier_auteur();
		$this->identifier_formulaire();
		if ($token) {
			$this->obtenir_champ_token();
		}
	}

	/**
	 * Constructeur depuis les arguments d'un pipeline
	 *
	 * Le tableau d'argument doit avoir 'form' et 'args'.
	 * La fonction recalcule le hash du formulaire, qui servira au constructeur normal.
	 *
	 * @param array $args Arguments du pipeline, généralement `$flux['args']`
	 * @return Identifier
	 */
	public static function depuisArgumentsPipeline($args) {
		// il nous faut le nom du formulaire et son hash
		// et pas de bol, le hash est pas envoyé dans les pipelines 'formulaires_xx'.
		// (il est calculé après charger). Alors on se recrée un hash pour nous.
		#$post = $args['je_suis_poste'];
		$form = $args['form'];
		// sauf dans le cas du pipeline `formulaire_fond`
		if (!empty($args['formulaire_args'])) {
			$formulaire_args = $args['formulaire_args'];
		} else {
			$args = $args['args'];
			array_unshift($args, $GLOBALS['spip_lang']);
			$formulaire_args = encoder_contexte_ajax($args, $form);
		}
		$identifier = new self($form, $formulaire_args);
		return $identifier;
	}

	/**
	 * Constructeur depuis les paramètres dans l'environnement posté.
	 * @return Identifier
	 */
	public static function depuisRequest() {
		$identifier = new self;
		$identifier->recuperer_parametres();
		return $identifier;
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
	 * Retrouve les paramètres pertinents pour gérer le test ou la réception de fichiers.
	 **/
	public function recuperer_parametres() {
		// obligatoires
		$this->token           = _request('bigup_token');
		$this->formulaire      = _request('formulaire_action');
		$this->formulaire_args = _request('formulaire_action_args');
		$this->identifier_formulaire();
		if ($this->token) {
			$this->obtenir_champ_token();
		}
	}

	/**
	 * Identifier l'auteur qui accède
	 *
	 * Retrouve un identifiant unique, même pour les auteurs anonymes.
	 * Si on connait l'auteur, on essaie de mettre un nom humain
	 * pour une meilleure visibilité du répertoire.
	 *
	 * Retourne un identifiant d'auteur :
	 * - {id_auteur}.{login} sinon
	 * - {id_auteur} sinon
	 * - 0.{session_id}
	 *
	 * @return string
	 **/
	public function identifier_auteur() {
		// un nom d'identifiant humain si possible
		include_spip('inc/session');
		$identifiant = session_get('id_auteur');
		$complement = '';
		// visiteur anonyme ? on prend un identifiant de session PHP.
		if (!$identifiant) {
			if (session_status() == PHP_SESSION_NONE) {
				session_start();
			}
			$complement = '_' . session_id();
		} elseif ($login = session_get('login')) {
			$complement = '_' . GestionRepertoires::nommer_repertoire($login);
		}
		return $this->auteur = $identifiant . strtolower($complement);
	}

	/**
	 * Calcule un identifiant de formulaire en fonction de ses arguments et du secret du site
	 *
	 * @return string l'identifiant
	 **/
	public function identifier_formulaire() {
		include_spip('inc/securiser_action');
		return $this->formulaire_identifiant = substr(md5(secret_du_site() . $this->formulaire_args), 0, 6);
	}

	/**
	 * Récupère le champ du token
	 *
	 * @note
	 *     On permet de le calculer dès la construction de la classe,
	 *     avant même vérifier la validité du token.
	 *     Ça permet au constructeur du Cache d'avoir cette info directement,
	 *     sans utiliser de méthode supplémentaire après la vérification du token.
	 *
	 * @return bool True si le champ est trouvé.
	 */
	function obtenir_champ_token() {
		$_token = explode(':', $this->token, 2);
		if (count($_token) == 2) {
			$this->champ = reset($_token);
			return true;
		}
		return false;
	}

	/**
	 * Vérifier le token utilisé
	 *
	 * Le token doit arriver, de la forme `champ:time:clé`
	 * De même que formulaire_action et formulaire_action_args
	 *
	 * Le temps ne doit pas être trop vieux d'une part,
	 * et la clé de sécurité doit évidemment être valide.
	 *
	 * @return bool
	 **/
	public function verifier_token() {
		if (!$this->token) {
			$this->debug("Aucun token");
			return false;
		}

		$_token = explode(':', $this->token);

		if (count($_token) != 3) {
			$this->debug("Token mal formé");
			return false;
		}

		list($champ, $time, $cle) = $_token;
		$time = intval($time);
		$now = time();


		if (($now - $time) > $this->token_expiration) {
			$this->log("Token expiré");
			return false;
		}

		if (!$this->formulaire) {
			$this->log("Vérifier token : nom du formulaire absent");
			return false;
		}

		if (!$this->formulaire_args) {
			$this->log("Vérifier token : hash du formulaire absent");
			return false;
		}

		include_spip('inc/securiser_action');
		if (!verifier_action_auteur("bigup/$this->formulaire/$this->formulaire_args/$champ/$time", $cle)) {
			$this->error("Token invalide");
			return false;
		}

		$this->champ = $champ;

		$this->debug("Token OK : formulaire $this->formulaire, champ $champ, identifiant $this->formulaire_identifiant");

		return true;
	}
}