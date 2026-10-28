# Changelog

Toutes les modifications notables de **MECM Packaging Manager** sont documentées dans ce fichier.

Le format s’inspire de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et le projet suit le [Semantic Versioning](https://semver.org/lang/fr/).

> **Maintenance** : mettre à jour ce fichier à chaque livraison (ajout sous `[Unreleased]`,
> puis bascule vers une version numérotée au moment du tag / release).

## [Unreleased]

### Planned
- File d’attente / rapports enrichis pour les tests VM
- Assistants IA

## [4.7.0] - 2026-10-27

VM de test par utilisateur, Configuration en onglets, menu compte en barre du haut, ImageMagick `magick` dans l’image Docker.

### Added
- **VM de test personnelle** (`/account/vm`) pour les comptes avec écriture Logiciels (hôte, port SSH, identifiants, répertoire distant) — plus de config globale.
- **Migration** de l’ancienne `vm_config.json` vers le compte `mickael.rousseau@univ-tours.fr`.
- **Onglets Configuration** : Général / Logiciels / Pilotes / BIOS (remplace le sommaire ancré).
- **Menu compte** en haut à droite (nom + rôle) : mot de passe, VM de test, déconnexion.

### Changed
- Pipeline / « Tester sur la VM » utilisent la config SSH **du compte connecté**.
- Configuration admin Logiciels : renvoi vers Mon compte → VM de test.
- Image Docker : alias `magick` → `convert` (ImageMagick 6 Bookworm) pour le check install et les icônes MECM.

### Fixed
- Check droits : ImageMagick reconnu via `magick` ou `convert`.

## [4.6.0] - 2026-10-27

BIOS déployable (outils constructeur dans les ZIP), droits par module, config admin par rubrique, UX comptes.

### Added
- **Outils constructeur dans les ZIP BIOS** : HP CMSL (`Modules/`), Dell `cctk` + DLLs (`tools/`), Lenovo.BIOS.Config — plus de `PLACE_*` lorsque les binaires sont présents.
- **Config BIOS admin** (Configuration → BIOS) : chemins de log local + partage UNC injectés dans les `Apply-*.ps1` des ZIP.
- **ACL par module** pour opérateurs / lecteurs : Logiciels, Pilotes, BIOS → Aucun / Lecture / Lecture-écriture (stocké dans `users.json`).
- **Changement de mot de passe** self-service (`/account/password`) pour tout utilisateur connecté.
- **UI Utilisateurs** : clic sur un compte → modal (identité + niveaux d’accès par module) ; création aussi en modal.
- **Configuration par rubrique** : Général, Logiciels, Pilotes, BIOS (sommaire et ancrages).

### Changed
- Génération MDP BIOS : caractères stables **AZERTY / QWERTY** (exclut A/Q/W/Z/M et symboles).
- Menu **Logs** réservé aux administrateurs (plus visible ni accessible pour opérateur / lecteur).
- Scripts Apply : `$LogLocalPath` / `$LogSharePath` (HP log toujours en local d’abord).
- `BiosPackageBuilder` embarque les arbres d’outils depuis `resources/bios/{vendor}/`.

### Fixed
- Enregistrement Dell « section [cctk] attendue » : envoi `content_b64` + normalisation serveur du fichier INI.

## [4.5.0] - 2026-10-27

Module BIOS (bêta) : hubs HP / Dell / Lenovo, éditeurs de config versionnés, scripts PowerShell constructeur.

### Added
- **Hub BIOS** (`/bios`) : navigation HP, Dell, Lenovo (module marqué bêta ; Pilotes aussi en badge bêta).
- **HP** : éditeur BCU (options + aide), versions `BiosHP-N.cfg`, scripts CMSL (`Apply` / `Clear` Setup password / `Get-All`).
- **Dell** : éditeur CCTK (sous-ensemble d’options courantes), versions `BiosDell-N.ini` (`[cctk]`), scripts Dell Command | Configure (`cctk.exe`).
- **Lenovo** : éditeur TBCT (`SettingName,Value`), versions `BiosLenovo-N.ini`, scripts `Lenovo.BIOS.Config` (TBCT v2).
- **Persistance** : configs BIOS sous `storage/bios/{hp,dell,lenovo}` (volume Docker `docker-data/bios`).
- **SSH Docker** : mot de passe root configurable (`SSH_PASSWORD` / entrypoint).

### Changed
- Montage Docker du projet + volume BIOS pour conserver code et configs entre rebuilds.
- Éditeur BIOS : UI partagée (filtre, versions, aide « i »), enregistrement versionné côté serveur.

### Fixed
- Erreur réseau à l’enregistrement des configs BIOS (JSON / droits `storage/bios`).

## [4.4.0] - 2026-10-26

Mailing (rappels de renouvellement), clarté des versions synchro / packagées, et persistance Docker du code projet.

### Added
- **Mailing admin** (Configuration → Mailing) : SMTP (TLS/SSL), expéditeur, URL d’appli, mail de test, envoi manuel des rappels.
- **Rappels de renouvellement** : quand un logiciel dépasse sa fréquence de revue, email au **dernier packager** (`last_packaged_by` / historique ZIP). Script CLI `bin/send-renewal-mails.php`.
- **Cron Docker** : installation automatique au démarrage du conteneur (`0 8 * * *`, `TZ=Europe/Paris`), journal `storage/logs/renewal-mails.log`.
- **Versions** : distinction UI « nouvelle version (synchro) » vs « version packagée actuelle » (liste + fiche Infos) ; champ `last_packaged_version`.
- **Persistance Docker** : bind-mount du projet entier (`.:/var/www/html`) pour que le code survive aux rebuilds ; données runtime toujours sous `docker-data/`.

### Changed
- Email utilisateur éditable (connexion + destinataire des rappels).
- « Retard » : plus de faux positif sans URL de téléchargement (point sync gris).
- Comparaison de versions tolérante au format (`4.3.0.008` ≡ `4.3.0008`) pour éviter un faux « remplacera ».
- Fréquence de revue par défaut des fiches existantes alignée sur **90 jours** (livraison).
- Compte admin par défaut / doc : `mickael.rousseau@univ-tours.fr`.

### Fixed
- Perte des modifications code au rebuild Docker (seul `storage` était monté) — corrigé par le montage projet.

## [4.3.0] - 2026-08-25

URL de documentation dédiée pour MECM, et catégories logiciels gérées en admin.

### Added
- **URL de documentation** (optionnelle) sur la fiche logiciel : poussée dans MECM Software Center (`UserDocumentation` via `deploy.ps1`). Si vide, comportement inchangé (URL installateur / GitHub).
- **Catégories** : liste JSON (`storage/data/categories.json`), menu déroulant sur les fiches, CRUD admin dans **Configuration → Catégories**. Renommer une catégorie met à jour les fiches ; suppression bloquée si encore utilisée.

### Changed
- Défaut Deploy / `info_url` : priorise l’URL de documentation de la fiche.
- Export / import catalogue et doc ZIP : champ `documentation_url`.
- Documentation interne (`/docs`) : catégories et URL de documentation.

## [4.2.0] - 2026-08-19

Ajout du support **pilotes HP** (matrice SoftPaq), en plus de Dell. PHP ne télécharge toujours pas les Go.

### Added
- **Pilotes HP** : coller la [matrice Driver Pack x64](https://ftp.hp.com/pub/caps-softpaq/cmit/HP_Driverpack_Matrix_x64.html), le **modèle** (ex. EliteBook 840 G8) et la colonne **Windows 11** (ou « le plus récent »). L’atelier lit le SoftPaq et la version dans l’infobulle du lien (`4.00 A 1 (SP168972)`), sans télécharger le `.exe`. Empreinte = SoftPaq + version + URL vs dernier ZIP.
- `deploy-drivers.ps1` extrait aussi les SoftPaq HP (`.exe` `/s /e /f`) en plus des CAB/ZIP Dell.

### Changed
- Constructeurs du catalogue Pilotes : **Dell** et **HP**.
- Colonne Windows HP : **Windows 11 uniquement**.
- Documentation interne (`/docs`) : détection Dell KB + matrice HP.

## [4.0.0] - 2026-08-18

Catalogue **Pilotes Dell**, contrôle **VirusTotal** dans les pipelines, et page publique alignée. **HP n’est pas pris en charge** dans cette version (URL constructeur Dell uniquement).

### Added
- **Catalogue Pilotes (Dell uniquement)** : coller la **page KB** du pack (ex. Latitude 7440 / 9450). L’atelier lit la dernière révision (A11, A13…) et le lien `downloads.dell.com`, **sans télécharger les Go**. Bouton liste « Vérifier les mises à jour » (comme les logiciels) : voyant rouge = nouveau pack depuis le dernier ZIP. **Pipeline** sur la fiche : vérif URL → VirusTotal (si activé) → ZIP `deploy-drivers.ps1` + `driver.json`. Import MECM sur la console (deux partages UNC : source INF / Driver Package).
- **VirusTotal** (pipelines Logiciels et Pilotes) : après la synchro / vérif URL, analyse de l’URL (et du SHA-256 s’il est connu). Verdict malveillant → **pause opérateur** (faux positif, nom CDN, packer, vrai malware, URL sale) puis continuer ou arrêter. Activation + clé API dans **Configuration**.

### Changed
- Titre produit : **SCCM Packaging Manager** → **MECM Packaging Manager** (interface FR/EN, documentation, page publique, README).
- **Tableau de bord** : section **Pilotes** (effectifs, à traiter / à jour, liste jamais vérifiés · nouvelle version · erreur).
- **Pilotes** : l’historique n’affiche plus un faux SHA-256 `0000…` quand le pack n’est pas téléchargé (empreinte via version / URL / taille / ETag).
- Documentation interne (`/docs`) et présentation publique (`/welcome`) : logiciels, VirusTotal, pilotes Dell.

## [3.1.0] - 2026-08-18

Renommage **SCCM Packaging Manager**, identité visuelle (logo usine), catalogue sync plus lisible, documentation et page publique alignées.

### Added
- **Identité** : logo « Package Factory » (usine + lockup), favicon, affichage barre latérale, connexion et page publique.
- **Page publique** `/welcome` et `/welcome/en` réécrite autour des concepts (catalogue, assistant, lab, MECM) — FR et EN.
- **Documentation** (`/docs#status`, FR et EN) : fréquence, mention **retard**, bouton « Vérifier les mises à jour », voyant Sync (vert / rouge / orange / gris).
- **Liste Logiciels** : bouton « Vérifier les mises à jour » (synchro séquentielle), légende des voyants, rappel **retard** avec lien vers la doc.
- **Tableau de bord** : rappel explicite sur la notion de retard (≠ mise à jour disponible).
- Exemple catalogue **7-Zip** (`examples/7-Zip/`) : fiche JSON importable + icône, sans binaire.
- Capture d’écran du catalogue **Logiciels** dans le README (`docs/screenshots/software-catalogue.png`).
- Sessions PHP et fichiers temporaires sur `storage/cache/` (volume applicatif) au lieu de `/tmp` sur l’overlay Docker.

### Changed
- Renommage produit : **MECM Packaging Manager** → **SCCM Packaging Manager** (interface, docs, page publique, README).
- Voyant Sync : **rouge** = mise à jour disponible, **orange** = erreur de synchro (couleurs distinctes).
- « Vérifier les mises à jour » : parcourt chaque fiche avec URL, met à jour voyants, version et mention **retard** dans le tableau.
- **Retard** : calcul sur la dernière revue (synchro ou packaging) + fréquence ; fréquence par défaut **1 jour** pour les nouvelles fiches.
- Page publique : animation pipeline du hero recentrée sur les nœuds.
- Vérification des droits : plus de contrôle `docker-data/` (l’appli n’utilise que `storage/` et les uploads).

### Removed
- Reliquats : `docker-data/` (créé à la volée par Compose, pas dans le dépôt), `index.php` racine, scripts one-shot `bin/seed-catalog.php` / `fetch-icons.php` / `batch-vm-test.php` / `fill-procedures-and-test.php`, `config/catalog_seed.php`.

### Fixed
- Sessions PHP impossibles quand l’overlay Docker est plein (`No space left on device`) — sessions et temp déplacés sur le volume `storage/`.

## [3.0.0] - 2026-08-14

Assistant procédure **URL d’abord**, une seule source de téléchargement, version lue dans le binaire, scripts uninstall durcis, mot de passe admin géré dans l’UI.

### Added
- **Assistant procédure** : coller l’URL, **Récupérer l’installateur**, puis déduction MSI/EXE et commandes (type/arch/icône après le téléchargement).
- **Une seule URL** par fiche : lien direct `.exe`/`.msi` **ou** page GitHub Releases (plus deux champs concurrents).
- Synchro evergreen : lecture **ProductVersion** dans le MSI (table) ou l’EXE (ressource PE) — Chrome Entreprise, OnlyOffice, etc. ne restent plus à « unknown ».
- Documentation interne (`/docs#detection`, FR et EN) : GitHub, URL fixe, dépôt manuel, SHA-256, ProductVersion.
- Catalogue : **R** (CRAN), **DaVinci Resolve** (dépôt manuel), **Visual Studio Code**.
- Formulaire **Nouveau logiciel** réduit ; l’assistant procédure s’ouvre ensuite pour URL, icône et recette.

### Changed
- Compte admin : `config/auth.php` / `ADMIN_*` **créent** le compte s’il manque ; le mot de passe se change dans **Utilisateurs** (plus d’écrasement à chaque requête).
- Page publique `/welcome` et `/welcome/en` alignées sur le cycle v3 (une URL, assistant, lab, MECM).
- Sélecteur de langue **FR / EN** (Configuration + liens sur l’écran de connexion).

### Fixed
- Désinstall Inno : chemins quotés (`"%ProgramFiles%\…\unins000.exe"`) ne font plus planter `GetFileName` (**exit 1603**, cas VS Code).
- Uninstall Inno (`unins000.exe`) : code sortie **1** du clone TEMP n’est plus un échec immédiat (GIMP / WPKG).
- GIMP : désinstalleur réel sous `GIMP 3\uninst\unins000.exe` ; plus de faux succès `/ALLUSERS` si le produit était déjà présent.
- Image Docker : extensions PHP **gd** et **intl** ; ImageMagick + librsvg pour les icônes MECM.

## [2.2.3] - 2026-08-11

Renommage **MECM Packaging Manager**, dépôt manuel d’installateurs volumineux, assistant procédure guidé.

### Added
- **Dépôt manuel d’installateur** (onglet Infos / Synchro) : upload `.exe` / `.msi` / `.msix` / `.zip` jusqu’à ~1 Go ; prioritaire sur la sync URL ; retour sync URL supprime le fichier du disque
- **Assistant procédure** (onglet Procédure) : 5 écrans (stratégie → install → uninstall → détection → récap) avec validation à chaque étape ; crée les étapes install/uninstall préremplies
- Limites PHP/Apache pour uploads volumineux (~1 Go) ; alerte si ZIP indisponible

### Changed
- Renommage produit : **SCCM Packaging Factory** → **MECM Packaging Manager**
- Interface : retrait du sous-titre « Catalogue & procédures » dans la barre latérale
- Documentation (`/docs`) et page publique (`/welcome`) mises à jour

## [2.2.2] - 2026-08-10

Catalogue ~40 logiciels, export/import de fiches, sync et pipeline VM durcis (permissions, CDN, installs all-users).

### Added
- **Export / import de fiche logiciel** (JSON `sccm-pf-software-export`) : métadonnées + procédure, sans binaire — bouton « Exporter fiche » / page « Importer fiche »
- Catalogue seed étendu (~40 apps) + scripts `bin/seed-catalog.php`, `bin/fetch-icons.php`, `bin/batch-vm-test.php`
- `success_paths` / `pre_kill` / `success_gone_paths` sur les étapes install/uninstall (PDF24, GIMP, etc.)
- Helper `WebFs` : dossiers packages/exports rendus à `www-data` après écriture CLI root
- Sync : réécriture gallery Inkscape → CDN `media.inkscape.org` ; `CURLOPT_AUTOREFERER` désactivé
- Paramètre d’interface site-wide FR/EN (Settings → Interface) via `SiteConfigService`
- Documentation utilisateur connectée (`/docs`) : modules + exemple de procédure, FR/EN

### Changed
- Install EXE : attente non bloquante + détection produit (plus de faux « OK » si l’installateur ne quitte pas)
- Uninstall EXE : kill processus + succès si binaires disparus (PDF24 tray, etc.)
- GIMP : `/ALLUSERS` (install machine-wide, plus dans le profil utilisateur)
- Détection de version : parcours d’URL CDN versionnées (ex. Zoom `…/prod/7.1.5.43453/…`)
- Export ZIP : échec explicite si `close()` / taille 0 (plus de faux « ZIP OK »)

### Fixed
- Permissions `storage/packages` / `storage/exports` (worker `www-data` vs CLI root) → « ZIP manquant » / sync impossible
- Adobe Reader : URL CDN, version depuis nom AcroRdr, désinstall msiexec + ProductCode
- Brave : StandaloneSetup silencieux + codes sortie uninstall
- Index seed catalogue (écrasement 7-Zip / Brave)
- Versions placeholder `0.0.0` / `unknown` mieux gérées à la sync

## [2.2.1] - 2026-08-09

Déploiement MECM automatisé jusqu’à la collection, présentation bilingue, scripts PS robustes.

### Added
- Déployer : **ID de collection MECM** + objectif (disponible / obligatoire) → `New-CMApplicationDeployment -CollectionId` (`deploy.ps1` v5.1)
- Configuration MECM : ID collection et objectif par défaut
- Présentation `/welcome` recentrée sur les **modules** ; version anglaise `/welcome/en`

### Changed
- `deploy.ps1` : chaînes ASCII + BOM UTF-8 (évite les erreurs de parse PowerShell 5.1 sur tirets Unicode)

### Fixed
- Terminateur de chaîne manquant dans `deploy.ps1` (em dash UTF-8 mal interprété sur poste Windows)

## [2.2.0] - 2026-08-09

Import MECM depuis le ZIP : Centre logiciel prérempli, `deploy.ps1` sans argument.

### Added
- Page publique **Présentation** (`/welcome`) : but, concept, parcours d’usage ; accueil des visiteurs non connectés
- Assistant **Déployer** (`?tab=deploy`) : profil MECM par logiciel (site code, serveur de site, DP, détection, commandes)
- Chaque ZIP inclut **`deploy.json` + `deploy.ps1`** : création / mise à jour de l’application MECM, Deployment Type script, distribution vers le DP
- Préremplissage **Centre logiciel** : nom localisé + version, description, documentation, mots-clés, icône catalogue (`icon/sccm-icon.*`)
- Config globale MECM dans **Configuration** (site code `SIT`, serveur `sccm.domain.local`, DP `dp.domain.local`) gravée dans `deploy.ps1`
- Exécution `.\deploy.ps1` sans argument depuis un poste admin (connexion `-CMSiteServer`)

### Changed
- Nom d’application MECM par défaut : **Nom + version** (ex. `7-Zip 26.02`), resynchronisé à chaque version catalogue
- Génération ZIP : suppression automatique des anciennes archives de la **même version** (une seule par version)
- `Add-CMScriptDeploymentType` : détection via `-AddDetectionClause` uniquement (évite `AmbiguousParameterSet` avec `-ScriptLanguage` / `-ProductCode`)

### Fixed
- Paramètres incompatibles sur `Add-CMScriptDeploymentType` lors de l’import MECM

## [2.1.0] - 2026-08-09

Pipeline VM opérateur-centré, logs live, et durcissement des installs MSI (Firefox / Zoom).

### Added
- Confirmation opérateur après install VM (**continuer** → uninstall/cleanup, **arrêter** → cleanup lab en laissant le logiciel)
- Streaming des logs VM dans le panneau pipeline (poll ~1s, auto-scroll)
- Scripts MSI : attente mutex / retry code **1618**, log verbose msiexec, propriétés silencieuses Firefox (`INSTALL_MAINTENANCE_SERVICE=false`…) et Zoom (`ALLUSERS=1`)
- Noms d’installateurs MSI assainis dans le ZIP (sans espaces)

### Changed
- `Validate-Package.ps1` en phases `Install` | `Finish` | `Abort`
- SSH lab : `HOME` writable, `UserKnownHostsFile` dédié (plus d’erreurs `/var/www/.ssh`)
- JSON résultat VM : construction manuelle côté PowerShell + fallback PHP si parse fragile

### Fixed
- Installs MSI bloquées / timeout (Firefox ESR) à cause de `msiexec` concurrents ou script PS invalide (Unicode / `$true` interpolé par PHP)
- Faux positifs GIMP sans `/ALLUSERS` ; uninstall EXE sans chemin → exit **1603**
- « JSON résultat VM invalide » après pause confirmation

## [2.0.0] - 2026-08-08

Validation lab Windows et durcissement du pipeline de packaging.

### Added
- Pipeline **test VM Windows** via SSH (OpenSSH) : transfert du ZIP, exécution réelle de `install.ps1` / `uninstall.ps1`, contrôle du **témoin** MECM, cleanup, logs et historique
- Config VM : `config/test_vm.php` + secrets `config/test_vm.local.php` (gitignored) ; droits Apache `www-data` sur les secrets locaux
- UI « Tester sur la VM » dans l’historique ZIP + affichage des logs (échec ouvert, bouton « Voir log »)
- Synchro : refus des faux installateurs HTML ; extraction d’installateur depuis archives **Chocolatey `.nupkg`**
- `Start-Process` sans `-ArgumentList` vide (évite exit 1603 PowerShell)

### Changed
- Dashboard : suppression de la brique **Activité récente** (focus retards / stats)
- FileZilla / GIMP : URLs d’installateurs réels (Chocolatey / `download.gimp.org`) + args silencieux
- Entrypoint Docker : permissions `*.local.php` lisibles par le groupe web

### Fixed
- Mot de passe VM illisible par Apache (`test_vm.local.php` root-only) → bouton test masqué à tort
- Packages synchronisés depuis des pages web (HTML renommé `.exe`) provoquant « fichier endommagé » / exit 1603

## [1.1.0] - 2026-08-08

Stockage fichier JSON, auth admin hashée, et raffinements catalogue / sync.

### Changed
- Persistance **fichiers JSON** (`storage/data/`) à la place de MariaDB/MySQL
- Identifiants admin via `config/auth.php` (`password_hash` bcrypt ; surcharge `ADMIN_EMAIL` / `ADMIN_PASSWORD_HASH`)
- Docker Compose sans service MariaDB ; volume `storage/data`
- Fréquence de packaging par défaut : **30 jours** (au lieu de 90)

### Added
- Liste logiciels : indicateur de synchro par ligne + bouton « Vérifier les mises à jour » (sans ouvrir chaque fiche)
- Détection **GitHub Releases** (API) : résolution automatique de la dernière release + choix d’asset Windows (ex. 7-Zip → `7z####-x64.msi`)
- Règle produit : **MSI officiel toujours préféré** à l’EXE quand les deux existent (synchro + détection de type)
- Compte admin config protégé contre la suppression (recréation automatique sinon)

### Removed
- MariaDB/MySQL, migrations SQL, `DevBoxConfigParser`, config DevBox MySQL

### Security
- Mot de passe admin uniquement en hash bcrypt dans la configuration (jamais en clair)

## [1.0.0] - 2026-08-07

Première version stable de l’outil d’industrialisation du packaging SCCM/MECM.

### Added
- Authentification par sessions, rôles `admin` / `operator` / `viewer`, CSRF, journal d’audit
- Catalogue logiciels (métadonnées MECM : version, architecture, type d’installateur, URLs, fréquence)
- Procédures de packaging typées et réordonnables (install/uninstall, fichiers, raccourcis, registre, PowerShell, notes)
- Tableau de bord des logiciels en retard selon la fréquence de packaging
- Vérification de mise à jour (téléchargement + empreinte SHA-256 vs dernier package)
- Génération de ZIP MECM (EXE style Brave / MSI msiexec) avec historique des exports
- Icône MECM : upload, normalisation automatique en PNG 512×512 transparent (proportions conservées), réutilisation à chaque génération
- Mode sombre / clair (préférence mémorisée, respect du thème système par défaut)
- Lecteur de changelog intégré au pied de page

### Security
- Identifiants base de données lus depuis la config DevBox (jamais hardcodés)
- Contrôle d’accès par rôles sur les actions sensibles (ex. suppression d’exports)
