<?php

namespace Spip\Bigup;

/**
 * Mappage entre Bigup et Flow
 *
 * @plugin     Bigup
 * @copyright  2015
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

include_spip('inc/Bigup/Cache');
include_spip('inc/Bigup/Flow');
include_spip('inc/Bigup/GestionRepertoires');
include_spip('inc/Bigup/Identifier');
include_spip('inc/Bigup/LogTrait');
include_spip('inc/Bigup/Receptionner');

/**
 * Gère la validité des requêtes et appelle Flow
**/
class Bigup {

	use LogTrait;

	/**
	 * Identification du formulaire, auteur, champ, tokem
	 * @var Identifier
	 */
	private $identifier = null;

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
	 * Retrouve les fichiers qui ont été téléchargés et sont en attente pour ce formulaire
	 * et prépare le tableau d'environnement
	 *
	 * @return array
	 */
	public function retrouver_fichiers() {
		$liste = $this->cache->trouver_fichiers_complets();
		$liste = $this->organiser_fichiers_complets($liste);
		return $liste;
	}

	/**
	 * Retrouve les fichiers qui ont été téléchargés et sont en attente pour ce formulaire
	 * et les réaffecte à `$_FILES` au passage.
	 *
	 * @param string|array $uniquement
	 *      Identifant ou liste d'identifiant de fichiers que l'on souhaite
	 *      uniquement réinsérer, le cas échéant.
	 * @return array
	**/
	public function reinserer_fichiers($uniquement = []) {

		if (!$uniquement) {
			$uniquement = [];
		} elseif (!is_array($uniquement)) {
			$uniquement = [$uniquement];
		}

		$liste = $this->cache->trouver_fichiers_complets();
		foreach ($liste as $champ => $fichiers) {
			foreach ($fichiers as $description) {
				if (!$uniquement or in_array($description['identifiant'], $uniquement)) {
					$this->integrer_fichier($description);
				}
			}
		}
		return $liste;
	}


	/**
	 * Efface tous ou certains fichiers envoyés pour ce formulaire par un auteur.
	 *
	 * @param array|string $identifiants
	 *     Identifiant de fichier ou liste des identifiants concernés, le cas échéant.
	 *     Efface tous les fichiers sinon.
	 * @return true
	 */
	public function effacer_fichiers($identifiants = []) {
		if (!$identifiants) {
			$this->debug("Suppression des fichiers");
			$this->cache->supprimer_repertoire($this->cache->dir_final());
		} else {
			$this->cache->enlever_fichier_depuis_identifiants($identifiants);
			// les fichiers avec ces identifiants n'étant possiblement plus là
			// ie: ils ont été déplacés lors du traitement du formulaire
			// on nettoie les répertoires vides complètement
			GestionRepertoires::supprimer_repertoires_vides($this->cache->dir_final());
		}
		return true;
	}


	/**
	 * Groupe en tableau les fichiers trouvés
	 *
	 * Si un champ est nommé tel que `un[sous][dossier][]` la fonction
	 * mettra la description du fichier dans un tableau php équivalent.
	 *
	 * @param array $liste Liste [ champ => [ description ]]
	 * @return array Tableau [ racine => [ cle1 => [ cle2 => ... => [ description ]]]]
	 **/
	public function organiser_fichiers_complets($liste) {
		$tries = [];
		foreach ($liste as $champ => $fichiers) {
			foreach ($fichiers as $description) {
				// recréer le tableau lorsque $champ = "a[b][c][]".
				$arborescence = explode('[', str_replace(']', '', $champ));
				$me = &$tries;
				$dernier = array_pop($arborescence);
				foreach ($arborescence as $a) {
					if (!array_key_exists($a, $me)) {
						$me[$a] = array();
					}
					$me = &$me[$a];
				}
				if (strlen($dernier)) {
					$me[$dernier] = $description;
				} else {
					$me[] = $description;
				}
			}
		}
		return $tries;
	}


	/**
	 * Intégrer le fichier indiqué dans `$FILES`
	 *
	 * Tout dépend de l'attribut name qui avait été posté.
	 *
	 * Cette info doit se trouver dans le tableau reçu
	 * dans la clé 'champ'.
	 *
	 * Avec `i` le nième fichier posté dans le champ,
	 * voici un exemple de ce qu'on peut obtenir.
	 * Noter la position de l'incrément `i` qui se trouve dans le
	 * premier crochet vide du name.
	 *
	 * - name='a' : FILES[a][name] = 'x'
	 * - name='a[]' : FILES[a][name][i] = 'x'
	 * - name='a[b]' : FILES[a][name][b] = 'x'
	 * - name='a[b][]' : FILES[a][name][b][i] = 'x'
	 * - name='a[][b][]' : FILES[a][i][name][b][0] = 'x'
	 *
	 * @param array $description
	 *     Description d'un fichier
	 * @return array
	 *     Description du fichier
	**/
	public function integrer_fichier($description) {
		// la valeur complete du name.
		$champ = $description['champ'];
		$arborescence = explode('[', str_replace(']', '', $champ));
		$racine = array_shift($arborescence);

		if (!count($arborescence)) {
			// le plus simple…
			$_FILES[$racine] = $description;
		} else {
			if (!array_key_exists($racine, $_FILES)) {
				$_FILES[$racine] = [];
			}
			$dernier = array_pop($arborescence);
			foreach ($description as $cle => $valeur) {
				if (!array_key_exists($cle, $_FILES[$racine])) {
					$_FILES[$racine][$cle] = [];
				}
				$me = &$_FILES[$racine][$cle];

				foreach ($arborescence as $a) {
					if (strlen($a)) {
						if (!array_key_exists($a, $me)) {
							$me[$a] = [];
						}
						$me = &$me[$a];
					} else {
						$i = count($me);
						$me[$i] = [];
						$me = &$me[$i];
					}
				}
				if (strlen($dernier)) {
					$me[$dernier] = $valeur;
				} else {
					$me[] = $valeur;
				}
			}
		}

		return $description;
	}


	/**
	 * Chaque fichier présent dans `$_FILES` n'étant pas en erreur
	 * est géré par Bigup
	 */
	public function gerer_fichiers_postes() {
		$liste = $this->extraire_fichiers_valides();
		foreach ($liste as $champ => $fichiers) {
			foreach ($fichiers as $description) {
				$this->cache->stocker_fichier($champ, $description);
			}
		}
	}

	/**
	 * Extrait et enlève de `$_FILES` les fichiers reçus sans erreur
	 * et crée un tableau avec pour clé le champ d'origine du fichier
	 *
	 * @return array Tableau (champ => [description])
	 */
	public function extraire_fichiers_valides() {
		$liste = [];
		if (!count($_FILES)) {
			return $liste;
		}

		$infos = []; // name, pathname, error …
		foreach ($_FILES as $racine => $descriptions) {
			$infos = array_keys($descriptions);
			break;
		}

		foreach ($_FILES as $racine => $descriptions) {
			$error = $descriptions['error'];

			// cas le plus simple : name="champ", on s'embête pas
			if (!is_array($error)) {
				if ($error == 0) {
					$liste[$racine] = [$descriptions];
					unset($_FILES[$racine]);
				}
				continue;
			}

			// cas plus compliqués :
			// name="champ[tons][][sous][la][pluie][]"
			// $_FILES[champ][error][tons][0][sous][la][pluie][0]
			else {
				$chemins = $this->extraire_sous_chemins_fichiers($error);

				foreach ($chemins['phps'] as $k => $chemin) {
					$description = [];
					foreach ($infos as $info) {
						$complet = '$_FILES[\'' . $racine . '\'][\'' . $info . '\']' . $chemin;
						eval("\$x = $complet; unset($complet);");
						$description[$info] = $x;
					}

					$complet = $racine . $chemins['names'][$k];
					if (empty($liste[$complet])) {
						$liste[$complet] = [];
					}
					$liste[$complet][] = $description;
				}
			}
		}
		return $liste;
	}


	/**
	 * Retourne l'écriture plate de l'arborescence d'un tableau
	 *
	 * - Phps a toutes les arborescences en conservant les index numériques autoincrémentés
	 *   et en mettant les autres index entre guillements
	 * - Reels a toutes les arborescences en conservant les index numériques autoincrémentés
	 * - Names a les arborescences sans les index numériques
	 *
	 * @param $tableau
	 * @return array Tableau [ phps => [], reels => [], names => []]
	 */
	public function extraire_sous_chemins_fichiers($tableau) {
		$listes = [
			'phps' => [],   // ['tons'][0]['sous']['la']['pluie'][0]
			'reels' => [],  // [tons][0][sous][la][pluie][0]
			'names' => [],  // [tons][][sous][la][pluie][]
		];

		// si le name était [], PHP ordonnera les entrées dans l'ordre, forcément.
		// si quelqu'un avait mis name="truc[8][]", ça devrait trouver la bonne écriture.
		$i = 0;

		foreach ($tableau as $cle => $valeur) {
			$reel = '[' . $cle . ']';
			$php = is_int($cle) ? $reel : '[\'' . $cle . '\']';

			if ($cle === $i) {
				$name = '[]';
			} else {
				$name = '[' . $cle . ']';
			}

			if (is_array($valeur)) {
				$ls = $this->extraire_sous_chemins_fichiers($valeur);
				foreach ($ls['phps'] as $l) {
					$listes['phps'][] = $php . $l;
				}
				foreach ($ls['reels'] as $l) {
					$listes['reels'][] = $reel . $l;
				}
				foreach ($ls['names'] as $l) {
					$listes['names'][] = $name . $l;
				}
			} else {
				$listes['phps'][] = $php;
				$listes['reels'][] = $reel;
				$listes['names'][] = $name;
			}
			$i++;
		}

		return $listes;
	}
}
