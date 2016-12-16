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
	public static function log($quoi, $gravite = _LOG_INFO_IMPORTANTE) {
		spip_log($quoi, "bigup." . $gravite);
	}

	public static function debug($quoi) {
		return self::log($quoi, _LOG_DEBUG);
	}

	public static function error($quoi) {
		return self::log($quoi, _LOG_ERREUR);
	}

	public static function info($quoi) {
		return self::log($quoi, _LOG_INFO);
	}
}
