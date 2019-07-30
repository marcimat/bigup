<?php

namespace Spip\Bigup;

/**
 * Gère les modifications du html d'un formulaire existant
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

include_spip('inc/Bigup/LogTrait');
include_spip('inc/flock');

/**
 * Gère les modifications du html d'un formulaire existant
 **/
class Formulaire
{

	use LogTrait;

	/**
	 * Identification du formulaire, auteur, champ, tokem
	 * @var Identifier
	 */
	private $identifier = null;

	/**
	 * Code HTML du formulaire
	 * @var string
	 */
	private $formulaire = '';

	/**
	 * Contexte d'environnement du formulaire
	 * @var string
	 */
	private $contexte = [];

	/**
	 * Constructeur
	 *
	 * @param Identifier $identifier
	 * @param string $formulaire
	 *     Code HTML du formulaire
	 * @param array $contexte
	 *     Environnement du formulaire
	 **/
	public function __construct(Identifier $identifier, $formulaire, $contexte) {
		$this->identifier = $identifier;
		$this->formulaire = $formulaire;
		$this->contexte   = $contexte;
		include_spip('bigup_fonctions');
		include_spip('inc/filtres');
	}

	/**
	 * Retourne le code html du formulaire
	 * @return string
	 */
	public function get() {
		return $this->formulaire;
	}


	/**
	 * Préparer les champs input d'un formulaire déjà existant
	 *
	 * Permet d'ajouter à un ou plusieurs champs de type 'file' d'un formulaire
	 * dont on reçoit le code HTML et le contexte les éléments nécessaires
	 * pour utiliser Bigup dessus.
	 *
	 * Pour les noms de champs indiqués, on ajoute :
	 *
	 * - la classe CSS 'bigup'
	 * - le token
	 * - l'attribut multiple, si le name se termine par `[]`
	 * - la liste des fichiers déjà uploadés pour ce formulaire
	 * - la classe CSS 'pleine_largeur' sur le conteneur .editer.
	 *
	 * Le tableau d'option permet de modifier certains comportements.
	 *
	 * @param string|string[] $champs
	 *     Nom du ou des champs concernés
	 * @param array $options {
	 *     @var string $input_class
	 *         Classe CSS à ajouter aux input file concernés.
	 *         Par défaut 'bigup'
	 *     @var string $editer_class
	 *         Classe CSS à ajouter au conteneur .editer
	 *         Par défaut 'pleine_largeur'
	 * }
	 * @return bool|int
	 *     False si erreur, sinon nombre de remplacements effectués.
	 */
	function preparer_input($champs, $options = []) {
		if (!$champs) {
			return false;
		}
		if (!is_array($champs)) {
			$champs = [$champs];
		}

		if (empty($this->identifier->formulaire) or empty($this->identifier->formulaire_args)) {
			return false;
		}

		// Intégrer les options par défaut.
		$options = $options + [
			'input_class' => 'bigup',
			'editer_class' => 'pleine_largeur',
			'previsualiser' => false,
			'drop-zone-extended' => '',
		];

		$remplacements = 0;

		foreach ($champs as $champ) {

			$regexp = self::regexp_input_name($champ);

			if (preg_match($regexp, $this->formulaire, $regs)) {
				$remplacements++;
				$input = $new = $regs[0];

				// dès que [] est présent dans un champ, il peut être multiple.
				// on le considère comme tel dans ce cas.
				$multiple = (strpos($champ, '[]') !== false);

				// Ajouter la classe CSS demandée
				if ($options['input_class']) {
					$new = self::completer_attribut($new, 'class', $options['input_class']);
				}

				// Ajouter multiple si le name possède []
				if ($multiple) {
					$new = self::inserer_attribut($new, 'multiple', 'multiple');
				}

				// Ajouter le token
				$token = calculer_balise_BIGUP_TOKEN(
					$champ,
					$multiple,
					$this->identifier->formulaire,
					$this->identifier->formulaire_args
				);
				$new = self::inserer_attribut($new, 'data-token', $token);

				// Ajouter l'option de previsualisation
				if ($options['previsualiser']) {
					$new = self::inserer_attribut($new, 'data-previsualiser', 'oui');
				}
				if ($options['drop-zone-extended']) {
					$new = self::inserer_attribut($new, 'data-drop-zone-extended', $options['drop-zone-extended']);
				}

				// Dans l'environnement, la liste des fichiers est la clé sans [], si [] est présent à la fin du champ
				// De même le champ sans [] final est à saisie pour calculer les classes CSS
				if ($multiple and substr($champ, -2) == '[]') {
					$champ_env = substr($champ, 0, -2);
				} else {
					$champ_env = $champ;
				}

				// Ajouter les fichiers déjà présents
				$fichiers = '';
				$liste_fichiers = table_valeur($this->contexte, bigup_name2nom($champ_env));
				if ($liste_fichiers) {
					$fichiers = recuperer_fond(
						'saisies/inc-bigup_liste_fichiers',
						[
							'nom' => $champ,
							'fichiers' => ($multiple ? $liste_fichiers : [$liste_fichiers])
						]
					);
				}

				$this->formulaire = str_replace($input, $fichiers . $new, $this->formulaire);

				// Ajouter une classe sur le conteneur
				if ($options['editer_class']) {
					$regexp = self::regexp_balise_attribut_contenant_valeur('div', 'class', 'editer editer_' . bigup_nom2classe($champ_env));
					if (preg_match($regexp, $this->formulaire, $regs)) {
						$new = self::completer_attribut($regs[0], 'class', $options['editer_class']);
						$this->formulaire = str_replace($regs[0], $new, $this->formulaire);
						break;
					}
				}
			}
		}
		return $remplacements;
	}



	/**
	 * Cherche les inputs de type file possédant une certaine la classe css
	 * et leur ajoute le token (et autres infos dessus)
	 *
	 * On retrouve les names des différents champs, puis on s'appuie
	 * sur la fonction principale qui applique les traitements à partir
	 * justement du nom du champ.
	 *
	 * @uses preparer_input()
	 * @param string|array $classes
	 *     Classe CSS ou liste de classes CSS à chercher
	 * @param array $options
	 * @return bool|int
	 *     False si erreur, sinon nombre de remplacements effectués.
	 */
	function preparer_input_class($classes, $options = []) {
		if (!$classes) {
			return false;
		}
		if (!is_array($classes)) {
			$classes = [$classes];
		}

		if (empty($this->identifier->formulaire) or empty($this->identifier->formulaire_args)) {
			return false;
		}

		if (!is_array($options)) {
			$options = [];
		}

		$options = $options + [
			'input_class' => 'bigup',
		];

		// On cherche à retrouver le name des champs input file de classe bigup
		// chercher <input ... [type="file"] ... [class="... bigup ..."] ... />
		// chercher <input ... [class="... bigup ..."] ... [type="file"] ... />
		$regexp_classes = self::regexp_input_classe($classes);
		$regexp_champ = self::regexp_input_trouver_name();
		$champs = [];

		if (preg_match_all($regexp_classes, $this->formulaire, $matches)) {
			foreach ($matches[0] as $m) {
				if (preg_match($regexp_champ, $m, $regs)) {
					$champs[] = $regs['champ'];
				}
			}
		}

		if ($champs) {
			return self::preparer_input($champs, $options);
		}

		return 0;
	}

	/**
	 * Insère un script en fin de formulaire
	 * @param string $script nom du script dans 'javascript/'
	 */
	public function inserer_js($script) {
		$js = find_in_path('javascript/' . $script);
		if ($js) {
			$script = "\n" . '<script type="text/javascript" src="' . $js . '"></script>' . "\n";
			$this->formulaire .= $script;
		}
	}


	/**
	 * Exp Regexp : du texte mais pas une fin de balise (>)
	 * @return string
	 */
	public static function exp_non_fin_balise() {
		return '(?:[^>]*)';
	}

	/**
	 * Exp Regexp : texte $attribut='$valeur' ou $attribut="$valeur"
	 * @param string $attribut
	 * @param string $valeur
	 * @param bool $quote Appliquer preg_quote() ?
	 * @return string
	 */
	public static function exp_attribut_est_valeur($attribut, $valeur, $quote = true) {
		return
			preg_quote($attribut)
			. '\s*=\s*[\"\']{1}\s*'
			. ($quote ? preg_quote($valeur) : $valeur)
			. '\s*[\"\']{1}';
	}

	/**
	 * Exp Regexp : texte $attribut='[expression]' ou $attribut="[expression]"
	 * @uses exp_attribut_est_valeur();
	 * @param string $attribut
	 * @param string $valeur
	 * @return string
	 */
	public static function exp_attribut_est_expr_valeur($attribut, $valeur) {
		return self::exp_attribut_est_valeur($attribut, $valeur, false);
	}

	/**
	 * Exp Regexp : texte $attribut='... $valeur ...' ou $attribut="... $valeur ..."
	 * @param string $attribut
	 * @param string $valeur
	 * @param bool $quote Appliquer preg_quote() ?
	 * @return string
	 */
	public static function exp_attribut_possede_valeur($attribut, $valeur, $quote = true) {
		return self::exp_attribut_est_expr_valeur(
			$attribut,
			'(?:[^\"\']+\s+)?'       // (du contenu sans " ou ' avec un espace après)?
			. ($quote ? preg_quote($valeur) : $valeur)
			. '(?:\s+[^\"\']+)?'       // (du contenu sans " ou ' avec un espace avant)?
		);
	}

	/**
	 * Exp Regexp : texte $champ='... $valeur ...' ou $champ="... $valeur ..."
	 * @param string $champ
	 * @param string $valeur
	 * @return string
	 */
	public static function exp_capturer_attribut_name() {
		return self::exp_attribut_est_expr_valeur('name', '(?<champ>[^\"\']+)');
	}

	/**
	 * Regexp : capture d'un champ input ayant un champ name indiqué
	 * @return string
	 */
	public static function regexp_input_name($champ) {
		return
			'#<input'
			. self::exp_non_fin_balise()
			. self::exp_attribut_est_valeur('name', $champ)
			. self::exp_non_fin_balise()
			. '/>#Uims';
	}

	/**
	 * Regexp : capture d'un champ input ayant une classe CSS indiquée
	 * @param string|array $classes Classe CSS ou liste de classes CSS
	 * @return string
	 */
	public static function regexp_input_classe($classes) {
		$classes = array_map('preg_quote', $classes);
		$exp_classes = '(?:' . implode('|', $classes) . ')';
		return
			'#<input'
			. self::exp_non_fin_balise()
			. '(?:'
			. self::exp_attribut_est_valeur('type', 'file')
			. self::exp_non_fin_balise()
			. self::exp_attribut_possede_valeur('class', $exp_classes, false)
			. '|'
			. self::exp_attribut_possede_valeur('class', $exp_classes, false)
			. self::exp_non_fin_balise()
			. self::exp_attribut_est_valeur('type', 'file')
			. ')'
			. self::exp_non_fin_balise()
			. '/>#Uims';
	}

	/**
	 * Regexp : capture de la valeur de l'attribut 'name' d'une balise input.
	 * La valeur sera dans la clé `champ`.
	 * @return string
	 */
	public static function regexp_input_trouver_name() {
		return
			'#<input'
			. self::exp_non_fin_balise()
			. self::exp_capturer_attribut_name()
			. self::exp_non_fin_balise()
			. '/>#Uims';
	}

	/**
	 * Regexp : capture une balise ayant un attribut contenant une valeur
	 *
	 * `<div class="editer editer_{champ}" mais pas "editer editer_{champ}_qqc" ... >`
	 *
	 * @param string $balise Nom de la balise
	 * @param string $attribut
	 * @param string $valeur
	 * @return string
	 */
	public static function regexp_balise_attribut_contenant_valeur($balise, $attribut, $valeur) {
		return
			'#<' . $balise . ' '
			. self::exp_non_fin_balise()
			. self::exp_attribut_possede_valeur($attribut, $valeur)
			. self::exp_non_fin_balise()
			. '/?>'
			. '#Uims';
	}


	/**
	 * Ajoute une valeur sur un attribut de balise html
	 * @param string $balise
	 * @param string $attribut
	 * @param string $valeur
	 * @return string Balise HTML complétée
	 */
	public static function completer_attribut($balise, $attribut, $valeur) {
		if ($balise and $attribut and $valeur) {
			if (strpos($balise, $start = $attribut . '="') !== false) {
				$balise = str_replace($start, $start . $valeur . ' ', $balise);
			} elseif (strpos($balise, $start = $attribut . '=\'') !== false) {
				$balise = str_replace($attribut . '=\'', $attribut . '=\'' . $valeur . ' ', $balise);
			} else {
				$balise = self::inserer_attribut($balise, $attribut, $valeur);
			}
		}
		return $balise;
	}

	/**
	 * Ajoute ou remplace un attribut et sa valeur sur une balise html
	 *
	 * @param string $balise
	 * @param string $attribut
	 * @param string $valeur
	 * @return string Balise HTML complétée
	 */
	public static function inserer_attribut($balise, $attribut, $valeur) {
		return inserer_attribut($balise, $attribut, $valeur);
	}
}