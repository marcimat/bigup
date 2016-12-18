<?php

namespace Spip\Bigup;

/**
 * Gestion des relations avec `$_FILES`
 *
 * @plugin     Bigup
 * @copyright  2016
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */


/**
 * Gestion des relations avec `$_FILES`
 **/
class Files {

	use LogTrait;


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
	 * @param string $champ
	 *     Valeur de l'attribut name du champ.
	 * @param array $description
	 *     Description d'un fichier
	 * @return array
	 *     Description du fichier
	 **/
	public static function integrer_fichier($champ, $description) {

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
	 * Extrait et enlève de `$_FILES` les fichiers reçus sans erreur
	 * et crée un tableau avec pour clé le champ d'origine du fichier
	 *
	 * @return array Tableau (champ => [description])
	 */
	public static function extraire_fichiers_valides() {
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
				$chemins = Files::extraire_sous_chemins_fichiers($error);

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
	public static function extraire_sous_chemins_fichiers($tableau) {
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
				$ls = Files::extraire_sous_chemins_fichiers($valeur);
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
