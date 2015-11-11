<?php

namespace Spip\Bigup;

/**
 * Trait de log de bigup
 *
 * @plugin     Bigup
 * @copyright  2015
 * @author     marcimat
 * @licence    GNU/GPL
 * @package    SPIP\Bigup\Fonctions
 */

/**
 * Gère les logs de bigup
**/
trait LogTrait {
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
}
