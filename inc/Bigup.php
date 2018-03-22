<?php

namespace Spip\Bigup;

/**
 * Gère les intéractions entre les pipelines SPIP et Bigup.
 *
 * @plugin     Bigup
 * @copyright  2015
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

include_spip('inc/Bigup/LogTrait');

include_spip('inc/Bigup/Cache');
include_spip('inc/Bigup/CacheFichiers');
include_spip('inc/Bigup/CacheRepertoire');
include_spip('inc/Bigup/Files');
include_spip('inc/Bigup/Flow');
include_spip('inc/Bigup/Formulaire');
include_spip('inc/Bigup/GestionRepertoires');
include_spip('inc/Bigup/Identifier');
include_spip('inc/Bigup/Repondre');

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
		$liste = $this->cache->final->trouver_fichiers();
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
	 *      Liste des fichiers réinsérés
	**/
	public function reinserer_fichiers($uniquement = []) {

		if (!$uniquement) {
			$uniquement = [];
		} elseif (!is_array($uniquement)) {
			$uniquement = [$uniquement];
		}

		$liste = $this->cache->final->trouver_fichiers();
		foreach ($liste as $champ => $fichiers) {
			foreach ($fichiers as $i => $description) {
				if (!$uniquement or in_array($description['bigup']['identifiant'], $uniquement)) {
					unset($description['bigup']);
					Files::integrer_fichier($champ, $description);
				} else {
					unset($liste[$champ][$i]);
				}
			}
		}
		return $liste;
	}

	/**
	 * Pour une liste (champ => description de fichier) donné, déclenche
	 * un mécanisme qui permettra de vérifier que ces fichiers sont toujours présents
	 * dans $_FILES au moment des vérifications et des traitements.
	 * Le cas échéant, les enlèvera de $_FILES.
	 *
	 * @param array $liste
	 *     Liste de descriptions de fichiers
	 * @return bool
	 *     True si des fichiers ont été mis en surveillance, false sinon.
	 */
	public function surveiller_fichiers($liste) {
		if (!$liste) {
			return false;
		}
		$surveiller = [];
		foreach ($liste as $champ => $descriptions) {
			$surveiller[$champ] = array_column($descriptions, 'tmp_name');
		}
		// Théoriquement on ne passe pas plusieurs fois ici lors d'un hit
		// seulement lorsqu'on poste 1 formulaire. Pas besoin de gérér
		// plusieurs variables.
		set_request('bigup_surveiller_fichiers', $surveiller);
		return true;
	}

	/**
	 * Enlève les fichiers surveillés par bigup qui ont été enlevés de `$_FILES`
	 *
	 * Cela signifie probablement que le fichier ne convenait pas au formulaire
	 * qui le demandait. En tout cas c'est comme cela que fait Formidable
	 * avec les fichiers qu'il reçoit qui ne lui conviennent pas.
	 *
	 * @return bool
	 */
	public function verifier_fichiers_surveilles() {
		$surveiller = _request('bigup_surveiller_fichiers');
		if (!$surveiller) {
			return true;
		}
		foreach ($surveiller as $champ => $fichiers) {
			foreach ($fichiers as $i => $fichier) {
				if (!Files::contient($champ, $fichier)) {
					GestionRepertoires::supprimer_repertoire(dirname($fichier));
					unset($surveiller[$champ][$i]);
				}
			}
			if (!count($surveiller[$champ])) {
				unset($surveiller[$champ]);
			}
		}
		set_request('bigup_surveiller_fichiers', $surveiller);

		return true;
	}

	/**
	 * Efface tous ou certains fichiers envoyés pour ce formulaire par un auteur.
	 *
	 * @param array|string $identifiants
	 *     Identifiant de fichier ou liste des identifiants concernés, le cas échéant.
	 *     Efface tous les fichiers sinon.
	 * @return true
	 */
	public function supprimer_fichiers($identifiants = []) {
		if (!$identifiants) {
			$this->debug("Suppression des fichiers");
			$this->cache->supprimer_repertoires();
		} else {
			$this->cache->final->supprimer_fichiers($identifiants);
			// les fichiers avec ces identifiants n'étant possiblement plus là
			// ie: ils ont été déplacés lors du traitement du formulaire
			// on nettoie les répertoires vides complètement
			GestionRepertoires::supprimer_repertoires_vides($this->cache->final->dir);
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
	 * Chaque fichier présent dans `$_FILES` n'étant pas en erreur
	 * est géré par Bigup
	 */
	public function gerer_fichiers_postes() {
		$liste = Files::extraire_fichiers_valides();
		foreach ($liste as $champ => $fichiers) {
			foreach ($fichiers as $description) {
				$this->cache->final->stocker_fichier($champ, $description);
			}
		}
	}

	/**
	 * Retourne une classe pour effectuer des modifications sur le code html de ce formulaire SPIP
	 *
	 * @param string $formulaire
	 *     Code HTML du formulaire
	 * @param array $contexte
	 *     Environnement du formulaire
	 * @return Formulaire
	 */
	public function formulaire($formulaire, $contexte) {
		return new Formulaire($this->identifier, $formulaire, $contexte);
	}
}
