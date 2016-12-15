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

Par dessus tout cela s'ajoute une couche javascript qui permet de téléverser
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

Il y a deux parties indépendantes :

### 1. Trouver les fichiers en attente

Pour chaque formulaire qui le demande, une recherche des fichiers téléversés
pour ce formulaire par l'auteur connecté et qui sont en encore en attente
d'utilisation est réalisée.

La demande consite à envoyer la clé `_rechercher_uploads` dans le retour
de la fonction charger(). Ce plugin comprendra qu'il doit retrouver les éventuels
fichiers. La liste de ses trouvailles est ajouté dans l'environnement du formulaire, 
sous la même clé que le nom du champ. 

Les fichiers en attente (complets ou leurs morceaux en cours de téléversement), 
sont stockés selon un répertoire précis, qui dépend du formulaire, de l'auteur, 
du champ (name de l'input), du fichier lui-même, dans le répertoire `_DIR_TMP/bigupload`.

(Lire plus d'informations sur ce stokage dans le wiki)[https://gitlab.com/magraine/bigup/wikis/stockage-temporaire-des-fichiers]


### 2. Téléverser des fichiers en javascript

Le javascript utilise la librairie Flow.js pour téléverser les fichiers.
Elle découpe un fichier en morceau, et pour chaque morceau fait 
une demande au serveur pour savoir s'il possède déjà ce morceau.
S'il ne l'a pas, ce morceau est téléversé.

La réception côté PHP est géré dans un fichier d'action (bigup.php)
qui retourne simplement un header PHP avec le statut http correspondant
au traitement qui a été fait. L'autorisation de déposer un fichier
est conditionné à un token calculé à partir des informations
du formulaire, de l'auteur en cours et de l'heure.

Ce token doit être renseigné dans l'attribut `data-token` de l'input.
Si l'input possède la classe css `bigup` alors le javascript prendra
en compte ce champ (une zone de glisser déposer apparaît) à l'aide
de Flow.js.


### Le token

Le token peut être calculé en utilisant la balise `#BIGUP_TOKEN{nom}`
où 'nom' est la valeur du name de l'input. Cependant, cette balise
dans son usage par défaut doit avoir accès à `#ENV{form}` et
`#ENV{formulaire_args}` donc attention si elle est utilisée dans
une inclusion à bien transmettre `env` ou ces valeurs.

Le token est valide 24h par défaut (afficher le formulaire
donne systématiquement un nouveau token).


### La saisie

La balise `#SAISIE_FICHIER` est une extension à la balise `#SAISIE`
qui calcule automatiquement en plus les valeurs

- `token` : la valeur du résultat de `#BIGUP_TOKEN{nom}`
- `fichiers` : la liste des fichiers déjà en attente pour ce champ.

Le plugin dispose d'une saisie `bigup` à laquelle on peut passer
un certain nombre d'options. Les plus utiles sont :

- `accept` : pour limiter à certains types de fichier acceptés
           (comme la valeur de l'attribut html5)
- `multiple` : pour autoriser plusieurs fichiers pour ce champ.

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
il va falloir prendre en compte, côté PHP et HTML, un certain nombre d'éléments.

#### Côté HTML

Le formulaire (la balise `<form>`) doit être posté et autoriser les fichiers (multipart/form-data)
ce qui revient à écrire la plupart du temps :

    <form method="post" action="#ENV{action}" enctype="multipart/form-data">

Les input qui autorisent plusieurs fichiers doivent l'indiquer :
- avec un nom de variable tableau, tel que `name='fichiers[]'`
- avec la propriété `multiple`

La saisie `bigup_fichier` s'occupe de cela dès lors que l'option multiple
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

- Ne pas pouvoir envoyer des fichiers plus gros que la taille maxi
  

### PHP

- Gérer un affichage d'erreur
- Ne pas pouvoir envoyer des fichiers plus gros que la taille maxi
- Pouvoir restreindre par mime type 
- Pouvoir restreindre par extension
- Après le traitement, supprimer les fichiers non utilisés du formulaire.

### Autre

- Yaml des saisies
- Traitement pour formidable pour gérer ces champs (si réalisable ?)
- Pouvoir intégrer une saisie de documents sur champs extras.
  Cela questionne :
  - qu'est-ce qu'on enregistre et où ?
  - Crée t'on un champ dans la table (c'est la logique de champs extras)
  - ou ajoute t'on des liens sur l'objet sur spip_documents_liens…
  - affiche-t-on des documents par défaut lorsqu'on édite le champ…


