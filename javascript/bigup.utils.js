/**
 * Déclaration de fonctions utilitaires,
 * indépendances de Bigup.
 *
 * - Gestion de clés de traductions en JS
 * - Reprise de fonctions PHP de SPIP en JS
 */

/** Alias pour avoir une fonction _T utilisable en js */
function _T(code, contexte) {
	return Trads.traduire(code, contexte);
}

/**
 * Gestion de traductions.
 *
 * Actuellement on ne déclare pas de langue, considérant
 * que les traductions ajoutées sont dans la langue de l’utilisateur.
 */
function Traductions() {
	this.modules = {};
};

Traductions.prototype = {

	set: function(module, couples_cle_traduction) {
		this.modules[module] = $.extend(this.modules[module] || {}, couples_cle_traduction);
	},

	get: function(module, code, contexte) {
		if (typeof this.modules[module] === 'undefined') {
			return '';
		}
		if (typeof this.modules[module][code] === 'undefined') {
			return '';
		}
		var texte = this.modules[module][code];
		$.each(contexte, function(cle, val) {
			texte = texte.replace('@' + cle + '@', val);
		});
		return texte;
	},

	/**
	 * Traduction d'un code de langue
	 *
	 * trads.traduire('bigup:supprimer_fichier');
	 * trads.traduire('bigup:supprimer_fichier', {fichier: 'nom.jpg'});
	 */
	traduire: function(code, contexte) {
		var desc = this.trouver_module_et_code(code);
		return this.get(desc.module, desc.code, contexte) || code;
	},

	/** Retrouve le nom du module et la clé d’un code de langue 'module:cle_de_langue' */
	trouver_module_et_code: function(code) {
		var list = code.split(':', 2);
		var module = list.shift();
		var cle = list.shift();
		if (cle) {
			return { module:module, code:cle };
		}
		return { module: 'spip', cle: module};
	},

	/**
	 * retourne le texte singulier si nb vaut 1, sinon le texte pluriel.
	 */
	singulier_ou_pluriel: function(nb, code_singulier, code_pluriel) {
		return parseInt(nb, 10) === 1
			? this.traduire(code_singulier)
			: this.traduire(code_pluriel, {nb: nb});
	}
};


// Déclarons un spécimen de traduction global.
Trads = new Traductions();


/**
 * Enlève une ancre d'une URL
 * @param string $url
 */
$.enlever_ancre = function(url) {
	var p = url.indexOf('#');
	if (p !== -1) {
		// var ancre = url.substring(p);
		url = url.substring(0,p);
	}
	return url;
};

/**
 * Transforme un name en classe
 * @see saisie_nom2classe() en PHP.
 * @param string name
 * @return string
 */
$.nom2classe = function(nom) {
	return nom.replace(/\/|\[|&#91/g, '_').replace(/\]|&#93/g, '');
};

/**
 * Calcule une taille en octets humainement lisible
 * @param int taille Taille en octets
 * @return string
 */
$.taille_en_octets = function(taille) {
	var ko = 1024;
	if (taille < ko) {
		return _T('unites:taille_octets', {taille: taille});
	} else if (taille < ko * ko) {
		return _T('unites:taille_ko', {taille: Math.round(taille/ko * 10) / 10});
	} else if (taille < ko * ko * ko) {
		return _T('unites:taille_mo', {taille: Math.round(taille/ko/ko * 10) / 10});
	} else {
		return _T('unites:taille_go', {taille: Math.round(taille/ko/ko/ko * 10) / 10});
	}
};


/**
 * Retrouve (et corrige) une extension dans un nom de fichier
 * @param string name Nom d'un fichier
 * @return string Extension du fichier
 */
$.trouver_extension = function(name) {
	// retouver l'extension
	var re = /(?:\.([^.]+))?$/;
	var extension = re.exec(name)[1];
	extension = extension.toLowerCase();

	// cf corriger_extension() dans plugin medias.
	switch (extension) {
		case 'htm':
			extension='html';
			break;
		case 'jpeg':
			extension='jpg';
			break;
		case 'tiff':
			extension='tif';
			break;
		case 'aif':
			extension='aiff';
			break;
		case 'mpeg':
			extension='mpg';
			break;
	}
	return extension;
};

$.mime_type_image = function(extension) {
	extension = extension.toLowerCase();
	var mime = "image/" + extension;
	// cas particuliers
	switch (extension) {
		case 'bmp':
			mime = "image/x-ms-bmp";
			break;
		case 'jpg':
			mime = "image/jpeg";
			break;
		case 'svg':
			mime = "image/svg+xml";
			break;
		case 'tif':
			mime = "image/tiff";
			break;
	}
	return mime;
};