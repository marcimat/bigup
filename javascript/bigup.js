/**
 * Pour un input de type file sélectionné,
 * gère l'upload du ou des fichiers, via html5 et flow.js
 *
 * Retourne uniquement la liste des input qui viennent
 * d'être activés avec bigup.
 *
 * Ça permet de gérer des callbacks derrière, sans ajouter
 * la callback à chaque rechargement ajax du js.
 *
 *     $('input.bigup')
 *         .bigup()
 *         .on('bigup.fileSuccess', function(...){...});
 *
 * On peut aussi envoyer une fonction de callback directement.
 *
 *     $('input.bigup').bigup({}, {
 *          fileSuccess: function(...){...},
 *     });
 *
 * @param object options
 * @param object callbacks
 * @return jQuery
 */
$.fn.bigup = function(options, callbacks) {
	// les options… on verra si on l'utilisera
	var options = options || {};
	// les callbacks éventuelles directes
	var callbacks = callbacks || {};

	var inputs_a_gerer = $(this).not(".bigup_done").each(function() {
		var $editer = $(this).closest('.editer');
		if ($editer.length) {
			$editer.addClass('biguping');
			var h = $editer.get(0).offsetHeight;
			var s = $editer.attr('style');
			if (typeof s === "undefined") {
				s = '';
			}
			$editer.attr('data-prev-style',s);
			s += 'height:'+h+'px;overflow:hidden';
			$editer.attr('style',s);
		}
		// indiquer que l'input est traité. Évite de charger plusieurs fois Flow
		$(this).addClass('bigup_done');

		var $input = $(this);
		var $form = $input.parents('form');

		// Équivalent au filtre sinon
		var sinon = function(valeur, defaut) {
			return valeur ? valeur : defaut;
		}

		// config globale de bigup.
		var conf = $.extend(true, {
			maxFileSize: 0
		}, $.bigup_config || {});

		var bigup = new Bigup(
			{
				form: $form,
				input: $input,
				formulaire_action: $form.find('input[name=formulaire_action]').val(),
				formulaire_action_args: $form.find('input[name=formulaire_action_args]').val(),
				token: $input.data('token')
			},
			{
				contraintes: {
					accept: $input.prop('accept'),
					maxFiles: ($input.prop('multiple') ? sinon($input.data('maxfiles'), 0) : 1),
					maxFileSize: sinon($input.data('maxfilesize'), conf.maxFileSize),
				}
			},
			callbacks
		);

		if (!bigup.support) {
			return false;
		}

		// Prendre en compte les fichiers déjà présents à l'ouverture du formulaire
		bigup.integrer_fichiers_presents();
		// Gérer le dépot de fichiers
		bigup.gerer_depot_fichiers();
		if ($editer.length) {
			$editer.attr('style',$editer.attr('data-prev-style'));
			$editer.attr('data-prev-style',null);
			$editer.addClass('editer_with_bigup').removeClass('biguping');
		}
	});
	return inputs_a_gerer;
}

/**
 * Bigup gère les fichiers des input concernés (avec Flow.js)
 * et leur communication avec SPIP
 *
 * Nécessite un accès à Trads.
 *
 * @param [params]
 * @param {jquery} [params.form]
 * @param {jquery} [params.input]
 * @param {string} [params.formulaire_action]
 * @param {string} [params.formulaire_action_args]
 * @param {string} [params.token]
 * @param [opts]
 * @param {object} [opts.contraintes]
 * @param {string} [opts.contraintes.accept]
 * @param {int}    [opts.contraintes.maxFiles]
 * @param {int}    [opts.contraintes.maxFileSize]
 * @param [callbacks]
 * @param {function} [callbacks.fileSuccess]
 * @constructor
 */
function Bigup(params, opts, callbacks) {

	this.form = params.form;
	this.input = params.input;
	this.formulaire_action = params.formulaire_action;
	this.formulaire_action_args = params.formulaire_action_args;
	this.token = params.token;

	this.target = $.enlever_ancre(this.form.attr('action'));
	this.name = this.input.attr('name');
	this.class_name = $.nom2classe(this.name);
	this.multiple  = this.input.prop('multiple');

	this.zones = {
		depot: null,
		depot_etendu: null,
		fichiers: null
	};

	this.defaults = {
		contraintes: {
			accept: '',
			maxFiles: 1,
			maxFileSize: 0
		},
		options: {
			// previsualisation des images
			previsualisation: {
				activer: !!this.input.data("previsualiser"),
				fileSizeMax: 10 // 10 Mb
			}
		},
		templates: {
			zones: {
				// Zone de dépot des fichiers
				depot: function (name, multiple) {
					var template =
						'\n<div class="dropfile dropfile_' + name + '" style="display:none;">'
						+ '\n\t<span class="dropfilebutton bigup-btn btn btn-default">'
						+ _T('bigup:televerser')
						+ '</span>'
						+ '\n\t<span class="dropfileor">' + _T('bigup:ou') + '</span>'
						+ '\n\t<span class="dropfiletext">'
						+ '\n\t\t'
						+ Trads.singulier_ou_pluriel(multiple ? 2 : 1, 'bigup:deposer_votre_fichier_ici', 'bigup:deposer_vos_fichiers_ici')
						+ '\n\t</:span:>'
						+ '\n</div>\n';
					return template;
				},
				// Zone de liste des fichiers déposés (conteneur)
				fichiers: function (name) {
					var template = "<div class='bigup_fichiers fichiers_" + name + "'></div>";
					return template;
				},
			},
			// Présentation d'un fichier déposé
			fichier: function(file) {
				// retouver l'extension
				var extension = $.trouver_extension(file.name);

				var template =
					'\n<div class="fichier">'
					+ '\n\t<div class="description">'
					+ '\n\t\t<div class="vignette_extension ' + extension + '" title="' + file.type + '"><span></span></div>'
					+ '\n\t\t<div class="infos">'
					+ '\n\t\t\t<span class="name"><strong>' + file.name + '</strong></span>'
					+ '\n\t\t\t<span class="size">' + $.taille_en_octets(file.size) + '</span>'
					+ '\n\t\t</div>'
					+ '\n\t\t<div class="actions">'
					+ '\n\t\t\t<span class="bigup-btn btn btn-default cancel" onClick="$.bigup_enlever_fichier(this); return false;">' + _T("bigup:bouton_annuler") + '</span>'
					+ '\n\t\t</div>'
					+ '\n\t</div>'
					+ '\n</div>\n';

				return template;
			}
		}
	};

	/**
	 * Current options
	 * @type {Object}
	 */
	this.opts = $.extend(true, this.defaults, opts || {});
	// Un seul fichier aussi si multiple avec max 1 file.
	this.singleFile = !this.multiple || (this.opts.contraintes.maxFiles === 1);

	// Ajoute chaque callback transmise
	var me = this;
	$.each(callbacks || {}, function(nom, callback) {
		me.input.on('bigup.' + nom, callback);
	});

	// La librairie d'upload
	this.flow = new Flow({
		input: this.input,
		target: this.target,
		testChunks: true,
		maxFiles: this.opts.contraintes.maxFiles,
		singleFile: this.singleFile,
		simultaneousUploads: 2, // 3 par défaut
		permanentErrors : [403, 404, 413, 415, 500, 501], // ajout de 403 à la liste par défaut.
		onDropStopPropagation: true, // ne pas bubler quand la drop zone est multiple
		query: {
			action: "bigup",
			bigup_token: this.token,
			formulaire_action: this.formulaire_action,
			formulaire_action_args: this.formulaire_action_args,
			accept: this.opts.contraintes.accept, // accept pourra servir au serveur pour éviter un stockage inutile
		}
	});

	// sait on gérer (upload html5 requis) ?
	this.support = this.flow.support;

	// Bigup accessible depuis l'input
	this.input.data('bigup', this);
}

Bigup.prototype = {

	/**
	 * Redéfinir des options
	 * @param object options Options à modifier
	 */
	setOptions: function(options) {
		options = options || {};
		this.opts.options = $.extend(true, this.opts.options, options);
	},

	/**
	 * Intégrer les fichiers déjà listés dans la zone des fichiers, au chargement du formulaire
	 *
	 * Remplacer les boutons "Enlever" d'origine sur les fichiers déjà présents
	 * dans le formulaire (listés au dessus du champ).
	 * On remplace par un équivalent qui fera la chose en pur JS + ajax
	 *
	 * Affecter l'objet bigup sur chaque emplacement de fichier pour facilités.
	 */
	integrer_fichiers_presents: function() {
		// Définir la zone de listing des fichiers
		this.creer_zone_fichiers();
		var me = this;

		// Trouver les fichiers s'il y en a
		this.zones.fichiers.find('.fichier').each(function(){
			var $button = $(this).find("button[name=bigup_enlever_fichier]");
			var identifiant = $button.val();
			$button.remove();
			$(this)
				.data('bigup', me)
				.data('identifiant', identifiant);
			me.ajouter_bouton_enlever(this);
		});
	},

	/**
	 * Ajoute un "Enlever" sur un fichier
	 *
	 * @param string fichien DOM de l'emplacement de présentation du fichier
	 */
	ajouter_bouton_enlever: function(fichier) {
		var js = "$.bigup_enlever_fichier(this); return true;";
		var inserer = '<span class="bigup-btn btn btn-default" onClick="' + js + '">'
			+ _T('bigup:bouton_enlever')
			+ '</span>';
		$(fichier).find('.actions').append(inserer);
		return this;
	},

	/**
	 * Gérer le dépot des fihiers
	 */
	gerer_depot_fichiers: function() {
		this.definir_zone_depot();
		var me = this;

		// Présenter le fichier dans liste des fichiers en cours
		// Valider le fichier déposé en fonction du 'accept' de l'input (si présent).
		this.flow.on('fileAdded', function(file,  event) {
			me.ajouter_fichier(file);
			me.adapter_visibilite_zone_depot();
			if (!me.accepter_fichier(file)) {
				me.presenter_erreur(file.emplacement, file.erreur);
				return false;
			}
		});

		// Téléverser aussitôt les fichiers valides déposés
		this.flow.on('filesSubmitted', function (files) {
			if (files.length) {
				$.each(files, function(key, file) {
					me.progress.ajouter(file.emplacement);
				});
				me.flow.upload();
			}
		});

		// Actualiser la barre de progression de l'upload
		this.flow.on('fileProgress', function(file, chunk){
			var percent = Math.round(file._prevUploadedSize / file.size * 100);
			var progress = file.emplacement.find('progress');
			progress.text( percent + " %" );
			me.progress.animer(progress, percent);
		});

		// Réussite de l'opload :
		// Adapter le bouton 'Annuler' => 'Enlever'
		// Retirer la barre de progression
		this.flow.on('fileSuccess', function(file, message, chunk){
			// console.info("success", file, message, chunk);
			var desc = JSON.parse(message);
			// enlever le bouton annuler
			file.emplacement.find(".cancel").fadeOut("normal", function(){
				$(this).remove();
				// et mettre un bouton enlever !
				if (desc.bigup.identifiant) {
					file.emplacement.data('identifiant', desc.bigup.identifiant);
					me.ajouter_bouton_enlever(file.emplacement);
				}
			});
			me.progress.retirer(file.emplacement.find("progress"));
			me.input.trigger('bigup.fileSuccess', [file, desc]);
		});

		// Un fichier a été enlevé, soit par nous, soit par Flow
		// lorsqu'on a ajouté un fichier supplémentaire alors que la saisie
		// attend un fichier unique.
		this.flow.on('fileRemoved', function(file) {
			// si ce n'est pas nous qui avons supprimé ce fichier
			if (!file.bigup_deleted) {
				me.enlever_fichier(file.emplacement);
			}

		});

		// Erreur, pas de bol !
		// Afficher l'erreur
		// Retirer la barre de progression
		this.flow.on('fileError', function(file, message, chunk){
			console.warn("error", file, message, chunk);
			var message_erreur = _T('bigup:erreur_de_transfert');
			if (message) {
				data = JSON.parse(message);
				if (typeof data.error !== 'undefined') {
					message_erreur = data.error;
				}
			}
			me.progress.retirer(file.emplacement.find("progress"));
			me.presenter_erreur(file.emplacement, message_erreur);
		});
	},

	/**
	 * créer la zone de dépot et l'indiquer à Flow.js
	 */
	definir_zone_depot: function() {
		// Cacher l'input original
		this.input.hide();
		this.creer_zone_depot();

		// Voir la zone, si on n'a pas déjà atteint le quota de fichiers
		this.adapter_visibilite_zone_depot();

		// Assigner la zone et son bouton à flow.
		this.flow.assignBrowse(
			this.zones.depot.find('.dropfilebutton'),
			false,
			!this.multiple,
			{accept: this.opts.contraintes.accept}
		);
		this.flow.assignDrop(this.zones.depot_etendu);
	},

	/**
	 * Créer la zone de dépot des fichiers
	 */
	creer_zone_depot: function() {
		$.bigup_verifier_depots_etendus();

		// Trouver une zone où déposer les fichiers dans le HTML existant
		var $zone_depot = this.form.find(".dropfile_" + this.class_name);

		// S'il n'y en a pas, créer le template par défaut et l'ajouter
		if (!$zone_depot.length) {
			var template = this.opts.templates.zones.depot(this.class_name, !this.singleFile);
			this.input.after(template);
			$zone_depot = this.form.find(".dropfile_" + this.class_name);
		}

		// gerer une eventuelle zonne etendue
		var $depot_etendu = $zone_depot;
		var depot_etendu = this.input.data('drop-zone-extended');
		if (typeof depot_etendu !== "undefined") {
			$depot_etendu = jQuery(depot_etendu)
				.not('.bigup-extended-drop-zone')
				.addClass('bigup-extended-drop-zone')
				.data('dropfile-class', ".dropfile_" + this.class_name)
				.data('bigup', this)
				.add($zone_depot);
		}

		var $c=this.class_name;
		$depot_etendu.on('dragenter dragover', function(){
			$(this).addClass('drag-over');
			$zone_depot.addClass('drag-target');
		});
		$depot_etendu.on('dragleave', function(){
			$(this).removeClass('drag-over');
			$zone_depot.removeClass('drag-target');
		});
		$depot_etendu.on('drop', function(){
			// drop ne buble pas, on enleve donc tout d'un coup
			$depot_etendu.removeClass('drag-target').removeClass('drag-over');
		});

		this.zones.depot = $zone_depot;
		this.zones.depot_etendu = $depot_etendu;
	},

	/**
	 * Créer la zone de listing des fichiers téléversés ou en cours de téléversement
	 */
	creer_zone_fichiers: function() {
		// Trouver une zone où afficher les fichiers dans le HTML existant
		var $fichiers = this.form.find(".fichiers_" + this.class_name);

		// S'il n'y en a pas, créer le template par défaut et l'ajouter
		if (!$fichiers.length) {
			var template = this.opts.templates.zones.fichiers(this.class_name);
			this.input.before(template);
			$fichiers = this.form.find(".fichiers_" + this.class_name);
		}

		this.zones.fichiers = $fichiers;
	},

	/**
	 * Affiche ou cache la zone de dépot en fonction du nombre de fichiers déjà actifs
	 */
	adapter_visibilite_zone_depot: function() {
		var nb = this.zones.fichiers.find(".fichier").length;
		if (!this.opts.contraintes.maxFiles || (this.opts.contraintes.maxFiles > nb)) {
			this.zones.depot.show();
		} else {
			this.zones.depot.hide();
		}
	},

	/**
	 * Tester que le fichier est valide par rapport à l'attribut `accept` de l'input.
	 * @param FlowFile file
	 * @return true si OK.
	 */
	accepter_fichier: function(file) {
		if (this.opts.contraintes.maxFileSize) {
			var taille = this.opts.contraintes.maxFileSize * 1024 * 1024;
			if (file.size > taille) {
				file.erreur = _T('bigup:erreur_taille_max', {taille: $.taille_en_octets(taille)});
				return false;
			}
		}
		if (this.opts.contraintes.accept) {
			var accept = this.opts.contraintes.accept;
			if (accept && !this.valider_fichier(file.file, accept)) {
				file.erreur = _T('bigup:erreur_type_fichier');
				return false;
			}
		}
		return true;
	},

	/**
	 * Vérifier un fichier par rapport à un attribut 'accept'
	 * Code issu de Dropzone.
	 *
	 * @param html5.file file
	 * @param html5.accept acceptedFiles
	 * @return bool
	 */
	valider_fichier: function(file, acceptedFiles) {
		var baseMimeType, mimeType, validType, _i, _len;
		if (!acceptedFiles) {
			return true;
		}
		acceptedFiles = acceptedFiles.split(",");
		mimeType = file.type;
		baseMimeType = mimeType.replace(/\/.*$/, "");
		for (_i = 0, _len = acceptedFiles.length; _i < _len; _i++) {
			validType = acceptedFiles[_i];
			validType = validType.trim();
			if (validType.charAt(0) === ".") {
				if (file.name.toLowerCase().indexOf(validType.toLowerCase(), file.name.length - validType.length) !== -1) {
					return true;
				}
			} else if (/\/\*$/.test(validType)) {
				if (baseMimeType === validType.replace(/\/.*$/, "")) {
					return true;
				}
			} else {
				if (mimeType === validType) {
					return true;
				}
			}
		}
		return false;
	},

	/**
	 * Ajoute le fichier transmis dans la liste des fichiers
	 *
	 * @param FlowFile file
	 */
	ajouter_fichier: function(file) {

		// pouvoir nous retrouver facilement
		file.bigup = this;

		// zone de listing des fichiers
		this.creer_zone_fichiers();

		// Ajouter le fichier à la zone
		var template = this.opts.templates.fichier(file.file);
		this.zones.fichiers.append(template);

		// Conserver en mémoire l'objet sur la vue du fichier, et inversement.
		var fichier = this.zones.fichiers.find(".fichier:last-child");
		file.emplacement = fichier;

		// Calculer la preview
		this.presenter_previsualisation(file);

		fichier
			.animateAppend()
			.data('file', file)
			.data('bigup', this);

		return true;
	},

	/**
	 * Enlève le fichier transmis dans la liste des fichiers
	 *
	 * @param jquery emplacement
	 *     Emplacement du fichier dans la liste des fichiers
	 */
	enlever_fichier: function(emplacement) {
		var me = this;

		// Identifiant du fichier
		// Soit celui de flow.js, soit celui du serveur
		// pour les fichiers présents à l'ouverture du formulaire
		var identifiant = emplacement.data('identifiant')
		// si on a un data 'file', le désintégrer…
		if (file = emplacement.data('file')) {
			file.abort();
			file.bigup_deleted = true;
			file.cancel();
			if (!identifiant) {
				identifiant = file.uniqueIdentifier;
			}
		}

		this.post({
			bigup_action: 'effacer',
			identifiant: identifiant
		})
		.done(function() {
			emplacement.animateRemove(function(){
				$(this).remove();
				me.adapter_visibilite_zone_depot();
			});
		})
		.fail(function() {
			me.presenter_erreur(emplacement, _T('bigup:erreur_probleme_survenu'));
		});
	},

	/**
	 * Poster une requête ajax, en transmettant des paramètres par défaut
	 * tel que le nom du formulaire, les actions, le token…
	 *
	 * @example
	 *     bigup.post({ action:bigup_document }).done(function(){ ... });
	 * @return jqXHR
	 */
	post: function(data) {
		data = $.extend({
			action: "bigup",
			formulaire_action: this.formulaire_action,
			formulaire_action_args: this.formulaire_action_args,
			bigup_token: this.token,
		}, data);
		return $.post(this.target, data);
	},

	/**
	 * Afficher une erreur sur un fichier
	 * @param string emplacement
	 *     Emplacement du fichier dans la liste des fichiers
	 * @param string message
	 *     Message d'erreur
	 */
	presenter_erreur: function(emplacement, message) {
		emplacement
			.addClass('erreur')
			.find('.infos')
			.append("<span class='message_erreur'>" + message + "</span>");
		return this;
	},

	/**
	 * Afficher un message gentil sur un fichier
	 * @param string emplacement
	 *     Emplacement du fichier dans la liste des fichiers
	 * @param string message
	 *     Message
	 */
	presenter_succes: function(emplacement, message) {
		emplacement
			.addClass('succes')
			.find('.infos')
			.append("<span class='message_ok'>" + message + "</span>");
		return this;
	},

	/**
	 * Présenter une vignette de l'image qui vient d'être déposée,
	 * à la place du logo de la vignette.
	 *
	 * @param FileObj file
	 */
	presenter_previsualisation: function(file) {
		if (!this.opts.options.previsualisation.activer) {
			return false;
		}
		if (this.opts.options.previsualisation.fileSizeMax) {
			var taille = this.opts.options.previsualisation.fileSizeMax * 1024 * 1024;
			if (file.file.size > taille) {
				return false;
			}
		}
		this.readURL(file.file, function() {
			// source base64 de l'image dans this.result
			if (this.result) {
				var title =
					file.emplacement.find('.infos .name').text()
					+ ' (' + file.emplacement.find('.infos .size').text() + ')';

				file.emplacement
					.find('.vignette_extension')
					.removeClass('vignette_extension')
					.addClass('previsualisation')
					.attr('title', title)
					.find('> span')
					.css('background-image', 'url(' + this.result + ')');
			}
		});
	},

	/**
	 * Calculer une URL (base64) à partir d'un fichier
	 * d'image déposé.
	 *
	 * @link http://stackoverflow.com/questions/4459379/preview-an-image-before-it-is-uploaded
	 * @link https://developer.mozilla.org/en-US/docs/Web/API/FileReader/readAsDataURL
	 *
	 * @param File file
	 * @param function callback
	 *      Sera appelé dès que le fichier aura été lu
	 *      this.result contiendra l'image en base64
	 * @return bool true si fichier d'image correct, false sinon
	 */
	readURL: function(file, callback) {
		if (file) {
			var reader = new FileReader();
			// trop simple ?
			// var imageType = /^image.*/i;
			// exemple de mozilla raccourci (image/ en tête de regexp plutôt que dans chaque élément)
			var imageType = /^image\/(?:bmp|cis\-cod|gif|ief|jpeg|jpeg|jpeg|pipeg|png|svg\+xml|tiff|x\-cmu\-raster|x\-cmx|x\-icon|x\-portable\-anymap|x\-portable\-bitmap|x\-portable\-graymap|x\-portable\-pixmap|x\-rgb|x\-xbitmap|x\-xpixmap|x\-xwindowdump)$/i;

			if (!file.type.match(imageType)) {
				return false;
			}

			if (typeof callback == 'function') {
				reader.addEventListener("load", callback);
			}

			reader.readAsDataURL(file);
			return true;
		}
		return false;
	},

	progress: {
		/**
		 * Ajoute une balise progress dans le contenu, en douceur
		 * 	@param string emplacement
		 *     Emplacement du fichier dans la liste des fichiers
		 */
		ajouter: function(emplacement) {
			var progress = $('<progress value="0" max="100" style="display:none">0 %</progress>');
			emplacement.append(progress);
			progress.fadeIn(1000); /* marche pas terrible */
			return this;
		},

		/**
		 * Augmente une balise progress à la valeur indiquée. Mais doucement
		 * @param jquery progress Le progress concerné.
		 * @param int val Valeur que l'on veut attribuer au progress.
		 */
		animer: function(progress, val) {
			progress.each(function() {
				var me = this;
				$({percent: me.value}).animate({percent: val}, {
					duration: 200,
					step: function () { me.value = this.percent; }
				});
			});
			return this;
		},

		/**
		 * Retire une balise progress du html, en douceur
		 * @param jquery progress Le progress concerné.
		 */
		retirer: function(progress) {
			// meme durée que sur animer_progress() pour attendre la fin
			progress.delay(200).fadeOut("normal", function(){
				$(this).slideUp("normal", function(){ $(this).remove(); });
			});
			return this;
		}
	},


	/**
	 * Récupère les champs que le formulaire poste habituellement
	 *
	 * Peut être utile pour faire un hit ajax, sans modifier le formulaire.
	 * Code en partie repris de dropzone.js
	 *
	 * On ne récupère pas les type file, ni sumbit.
	 *
	 * @return object Couples [nom du champ => valeur]
	 */
	getFormData: function () {
		var inputName, inputType;
		var data = {};

		this.form.find("input, textarea, select, button").each(function(){
			inputName = $(this).attr('name');
			inputType = $(this).attr('type');
			if (inputName) {
				if (this.tagName === "SELECT" && this.hasAttribute("multiple")) {
					$.each(this.options, function (key, option) {
						if (option.selected) {
							data[inputName] = option.value;
						}
					});
				} else if (
					!inputType
					|| ($.inArray(inputType, ["file", "checkbox", "radio", "submit"]) == -1)
					|| this.checked
				) {
					data[inputName] = this.value;
				}
			}
		});
		return data;
	}
};


/**
 * Enlever un fichier déjà téléversé ou annuler un transfert en cours
 *
 * La différence tient dans la présence de l'identifiant du fichier.
 * C'est l'identifiant sur le serveur si le fichier est complet là bas.
 *
 * @param object me
 *   L'élément qui a cliqué
 */
$.bigup_enlever_fichier = function(me) {
	var emplacement = $(me).parents('.fichier');
	var bigup       = emplacement.data('bigup');
	bigup.enlever_fichier(emplacement);
};


$.bigup_verifier_depots_etendus = function() {
	// desactiver toutes les data-drop-zone-extended qui ne sont plus liees a un input present dans le html
	jQuery('.bigup-extended-drop-zone').each(function (){
		var c = jQuery(this).data('dropfile-class');
		if (!c || !jQuery(c).length) {
			var me = jQuery(this);
			var bigup = me.data('bigup');
			bigup.flow.unAssignDrop(me);
			me
				.removeClass('bigup-extended-drop-zone')
				.off('dragenter dragover')
				.off('dragleave drop')
				.data('dropfile-class','');
		}
	});
}