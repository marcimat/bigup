# Big up (big upload)

Plugin SPIP permettant de téléverser de gros fichiers dans SPIP dans des formulaires CVT.
Fournit une API 

## Fonctionnement général

Un formulaire va pouvoir stoker les fichiers envoyés par un champ
input de type file, le temps de la rédaction de ce formulaire,
jusqu'au traitement de celui-ci. Ainsi, si des erreurs de saisie sont présentes,
les fichiers n'ont pas à être de nouveau envoyés.

Il est possible, dans le code html du formulaire de lister les fichiers
déjà envoyés, pour chaque champ de type file concerné (et de pouvoir demander
leur suppression).


### Amélioration javascript

Par dessus cela s'ajoute une couche javascript qui permet de téléverser
des fichiers de manière asynchrone d'une part, et volumineux d'autre part.

Dès qu'un fichier est déposé sur la zone prévue, le téléversement commence,
si le fichier cependant correspond au type attendu par l'input
(attribut html5 `accept`)

Le fichier est envoyé par morceaux (chunk), jusqu'à ce que le dernier morceau
soit envoyé. Si une interruption arrive (on quitte la page, puis on revient
dessus par exemple), et que l'utilisateur remet le même fichier à téléverser,
les morceaux déjà reçus ne seront pas réenvoyés, ce qui économise un peu de
temps et de bande passante.


## Fonctionnement technique

Le fonctionnement général s'appuie sur les formulaires CVT de SPIP
et sur un token généré par la balise `#BIGUP_TOKEN`

Pour les détails, lire : [le fonctionnement technique](https://gitlab.com/magraine/bigup/wikis/fonctionnement-technique)

### Résumé

Au chargement d'un formulaire CVT, si la clé `_bigup_rechercher_fichiers` 
est présente, le plugin Bigup se chargera de retrouver les fichiers
déjà chargés pour ce formulaire et d'ajouter leur liste, pour chaque
champ concerné du formulaire, dans l'environnement.

Ces fichiers sont stokés sur le serveur dans `_DIR_TMP/bigupload`.
[Lire plus d'informations sur ce stokage dans le wiki](https://gitlab.com/magraine/bigup/wikis/stockage-temporaire-des-fichiers)

La saisie `bigup` dans le formulaire peut ensuite gérer l'ajout
de nouveaux fichiers et présenter la liste des fichiers déjà présents.

Le javascript Bigup qui utilise [la librairie Flow.js](https://github.com/flowjs/flow.js/)
ajoute une gestion en ajax du téléversement des fichiers, même très volumineux, 
dès qu'ils sont ajoutés. Le token de l'input, qui est envoyé
à PHP sert d'autorisation pour recevoir ces fichiers.

### La saisie

La balise `#SAISIE_FICHIER` est une extension à la balise `#SAISIE`
qui ajoute automatiquement les valeurs *form* et *formulaire_args*
nécessaires au calcul de la balise `#BIGUP_TOKEN`, ainsi que la liste des *fichiers* présents.

Le plugin dispose d'une saisie `bigup` à laquelle on peut passer
un certain nombre d'options, notamment `accept` et `multiple`

Pour les détails lire : 
[la balise `#SAISIE_FICHIER`](https://gitlab.com/magraine/bigup/wikis/balises/saisie-fichier) 
ou [la saisie `bigup`](https://gitlab.com/magraine/bigup/wikis/saisies/bigup).

Exemple :

    [(#SAISIE_FICHIER{bigup, images, 
        label=Des images (par mime type),
        accept=image/*,
        multiple=oui})]

    [(#SAISIE_FICHIER{bigup, cv, 
        label=Un fichier pdf (par extension),
        accept=.pdf})]


### Les nettoyages

Un cron nettoie tous les jours les fichiers partiels ou complets
qui n'ont pas été utilisés, c'est à dire âgés de plus de 24h.

Ce même nettoyage est aussi réalisé dès qu'un fichier complet est reconstitué,
c'est à dire après la réception de son dernier morceau.

Enfin après le traitement du formulaire, tous les fichiers complets
non utilisés de celui-ci sont enlevés. 


### Création d'un formulaire pour uploader les fichiers

Pour qu'il soit compatible à la fois avec et sans javascript,
il faut prendre en compte, côté PHP et HTML, un certain nombre d'éléments.

#### Côté HTML

Le formulaire (la balise `<form>`) doit être posté et autoriser les fichiers (multipart/form-data)
ce qui revient à écrire la plupart du temps :

    <form method="post" action="#ENV{action}" enctype="multipart/form-data">

Les input qui autorisent plusieurs fichiers doivent l'indiquer :
- avec un nom de variable tableau, tel que `name='fichiers[]'`
- avec la propriété `multiple`

La saisie `bigup` s'occupe de cela dès lors que l'option multiple
est activée.


### Côté PHP

Pour recréer le tableu `$_FILES` tel que le crée habituellement PHP, 
il faut connaître la valeur de l'attribut name de la balise input. 

Cette valeur est transmise avec le token calculé, et est inscrite 
dans le chemin de cache des fichiers reçu. Cela permet, à partir 
d'un fichier cache donné, de recréer le `$_FILES` qui lui correspondait.

[Voir les notes sur `$_FILES` dans le wiki](https://gitlab.com/magraine/bigup/wikis/note-input-file-html5)

## Todo

### JS

- Compatibilité plugin roles_documents 
- Compatibilité plugin roles_logos
- Méthode pour simplifier l’auto-submit des formulaires simples ?

### PHP

- Pouvoir restreindre par mime type 
- Pouvoir restreindre par extension

### Autre

- Yaml des saisies
- Traitement pour formidable pour gérer ces champs (si réalisable ?)
- Pouvoir intégrer une saisie de documents sur champs extras.
  Cela questionne :
  - qu'est-ce qu'on enregistre et où ?
  - Crée t'on un champ dans la table (c'est la logique de champs extras)
  - ou ajoute t'on des liens sur l'objet sur spip_documents_liens…
  - affiche-t-on des documents par défaut lorsqu'on édite le champ…


