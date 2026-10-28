<?php

declare(strict_types=1);

namespace App\Support;

/**
 * In-app user documentation (logged-in), FR/EN.
 *
 * @phpstan-type DocsModule array{id: string, name: string, summary: string, how: list<string>}
 * @phpstan-type DocsStep array{type: string, title: string, detail: string}
 * @phpstan-type DocsCopy array{
 *   page_title: string,
 *   intro_title: string,
 *   intro_body: string,
 *   modules_title: string,
 *   modules_lead: string,
 *   modules: list<DocsModule>,
 *   detection_title: string,
 *   detection_lead: string,
 *   detection_common_title: string,
 *   detection_common: list<string>,
 *   detection_modes: list<array{id: string, name: string, summary: string, how: list<string>, example: string}>,
 *   detection_note: string,
 *   detection_example_label: string,
 *   cycle_title: string,
 *   cycle_lead: string,
 *   cycle: list<array{title: string, body: string}>,
 *   zip_title: string,
 *   zip_lead: string,
 *   zip_steps: list<array{title: string, body: string}>,
 *   zip_note: string,
 *   zip_drivers_title?: string,
 *   zip_drivers_lead?: string,
 *   zip_drivers_steps?: list<array{title: string, body: string}>,
 *   procedure_title: string,
 *   procedure_lead: string,
 *   procedure_example_title: string,
 *   procedure_example_context: string,
 *   procedure_steps: list<DocsStep>,
 *   procedure_tips_title: string,
 *   procedure_tips: list<string>,
 *   procedure_wizard_title?: string,
 *   procedure_wizard_lead?: string,
 *   procedure_wizard_steps?: list<array{title: string, body: string}>,
 *   toc_modules: string,
 *   toc_status: string,
 *   toc_detection: string,
 *   toc_drivers?: string,
 *   toc_bios?: string,
 *   toc_cycle: string,
 *   toc_zip: string,
 *   toc_step_types: string,
 *   toc_procedure: string,
 *   status_title: string,
 *   status_lead: string,
 *   status_blocks: list<array{title: string, body: string}>,
 *   status_dots_title: string,
 *   status_dots: list<array{dot: string, label: string, body: string}>,
 *   status_note: string
 * }
 */
final class DocsContent
{
    /**
     * @return DocsCopy
     */
    public static function forLocale(string $locale): array
    {
        return strtolower($locale) === 'en' ? self::en() : self::fr();
    }

    /**
     * @return DocsCopy
     */
    private static function fr(): array
    {
        return [
            'page_title' => 'Documentation',
            'intro_title' => 'Guide utilisateur',
            'intro_body' => 'Ce guide décrit MECM Packaging Manager 4.5 : logiciels, pilotes Dell / HP (bêta), hubs BIOS HP / Dell / Lenovo (bêta), VirusTotal, rappels e-mail de renouvellement, le cycle packaging, et quoi faire du ZIP (deploy.ps1 ou deploy-drivers.ps1).',
            'toc_modules' => 'Les modules',
            'toc_status' => 'Fréquence, Sync et retard',
            'toc_detection' => 'Détection de version',
            'toc_drivers' => 'Pilotes Dell / HP',
            'toc_bios' => 'BIOS (HP / Dell / Lenovo)',
            'toc_cycle' => 'Cycle recommandé',
            'toc_zip' => 'Après le ZIP (MECM)',
            'toc_step_types' => 'Types d’étapes',
            'toc_procedure' => 'Exemple de procédure',
            'modules_title' => 'Les modules',
            'modules_lead' => 'Chaque module couvre une étape du cycle packaging → validation → déploiement.',
            'modules' => [
                [
                    'id' => 'catalogue',
                    'name' => 'Catalogue',
                    'summary' => 'Inventaire des logiciels à packager.',
                    'how' => [
                        'Créez ou ouvrez une fiche : nom, éditeur, catégorie (Configuration → Catégories), version, architecture, type d’installateur (MSI / EXE).',
                        'Renseignez l’URL de téléchargement (lien direct .msi/.exe de préférence) et la fréquence de revue (90 jours par défaut : rappel calendaire / e-mail, pas un téléchargement automatique).',
                        'URL de documentation (optionnelle) : c’est elle qui est poussée dans MECM (Software Center → Documentation utilisateur). Sans elle, l’URL installateur / GitHub reste utilisée comme avant.',
                        'Sur la liste et la fiche Infos : distinction « nouvelle version (synchro) » vs « version packagée actuelle » (dernier ZIP). Le voyant Sync porte sur le fichier, pas uniquement sur le numéro affiché.',
                        'Sur la liste : voyant Sync, mention « retard » et bouton « Vérifier les mises à jour » — voir « Fréquence, Sync et retard ».',
                        'Ajoutez l’icône Centre logiciel : elle sera embarquée dans chaque ZIP et poussée vers MECM.',
                    ],
                ],
                [
                    'id' => 'drivers',
                    'name' => 'Pilotes Dell / HP (bêta)',
                    'summary' => 'Catalogue des Driver Packages (module bêta). Dell (page KB) et HP (matrice SoftPaq).',
                    'how' => [
                        'Dell : nom, modèle (ex. Latitude 7440), architecture. Collez l’URL de la page KB du pack (support/kbdoc/…), pas le fichier .exe de plusieurs Go.',
                        'HP : nom, modèle tel que dans la matrice (ex. EliteBook 840 G8), Windows (24H2 / 25H2 ou « le plus récent »). L’URL de matrice x64 est préremplie. L’atelier lit SoftPaq + version (2.00 A 1).',
                        'Sur la liste : « Vérifier les mises à jour » relit chaque page constructeur et compare au dernier ZIP. L’atelier ne télécharge jamais le pack.',
                        'Voyant rouge = nouveau pack depuis le dernier ZIP → ouvrez la fiche et lancez le Pipeline. Le ZIP ne contient que deploy-drivers.ps1 et driver.json. Sur un poste console MECM, le script télécharge le pack, l’extrait (CAB/ZIP/SoftPaq EXE) et crée le Driver Package.',
                    ],
                ],
                [
                    'id' => 'bios',
                    'name' => 'BIOS HP / Dell / Lenovo (bêta)',
                    'summary' => 'Hubs de configuration BIOS pour déploiement MECM / séquence de tâches. Module encore en bêta.',
                    'how' => [
                        'Menu BIOS → choisissez le constructeur (HP, Dell ou Lenovo). Chaque hub a le même schéma : éditeur de config, scripts PowerShell, versions enregistrées, liens vers l’outil constructeur.',
                        'HP : éditeur BCU → fichiers BiosHP-N.cfg. Scripts CMSL (Apply-HPBiosConfig, Clear Setup password, Get-All). Prérequis : module HP Client Management Script Library sur le poste cible.',
                        'Dell : éditeur CCTK → fichiers BiosDell-N.ini (section [cctk]). Scripts Dell Command | Configure (cctk.exe -i). Liste d’options courantes — complétez via cctk -o sur une machine de référence si besoin.',
                        'Lenovo : éditeur TBCT → fichiers BiosLenovo-N.ini (lignes SettingName,Value). Scripts Lenovo.BIOS.Config / Think BIOS Config Tool v2 (Import-LnvWmiSettings + Save-LnvWmiSettings).',
                        'Sources des listes d’options (aussi affichées sur chaque hub) : HP — Get-HPBIOSSettingsList / guide BCU (developers.hp.com, ftp.hp.com) ; Dell — guide CLI Command | Configure « BIOS options » (dell.com/support/manuals) ; Lenovo — docs TBCT v2 / Lenovo.BIOS.Config (docs.lenovocdrt.com), noms variables par modèle via Export-LnvWmiSettings.',
                        'Cochez les options, enregistrez une version : le téléchargement fournit un ZIP (config + scripts Apply/Clear/Get + arborescence Modules/tools/Lenovo.BIOS.Config à compléter avec l’outil constructeur). Les binaires CMSL / cctk / Lenovo.BIOS.Config ne sont pas redistribués (licence).',
                        'Les scripts Apply peuvent aussi définir un mot de passe Setup / Supervisor (aléatoire ou fixe) et écrire un log (partage UNC optionnel).',
                    ],
                ],
                [
                    'id' => 'procedure',
                    'name' => 'Procédure',
                    'summary' => 'Recette structurée (étapes typées) réutilisée à chaque version.',
                    'how' => [
                        'Onglet Procédure sur la fiche logiciel.',
                        'Procédure vide ? Cliquez « Lancer l’assistant » : URL → téléchargement de l’installateur → stratégie MSI/EXE → install → uninstall → détection → récap.',
                        'Sinon, ajoutez des étapes manuellement : commande d’install, désinstall, notes, PowerShell, fichiers, registre, etc.',
                        'Les arguments silencieux et le Product Code MSI alimentent la génération du package et l’assistant Déployer.',
                    ],
                ],
                [
                    'id' => 'sync',
                    'name' => 'Synchronisation',
                    'summary' => 'Vérifie les mises à jour et télécharge l’installateur.',
                    'how' => [
                        'Rien n’est vérifié tout seul au chargement de la page. Cliquez « Vérifier les mises à jour » (liste) ou « Vérifier la mise à jour » (fiche), ou lancez le pipeline.',
                        'Choisissez un mode de détection (GitHub Releases ou URL fixe) — voir « Détection de version ».',
                        'Si l’URL ne fournit pas le .exe/.msi : onglet Infos ou Synchro → « Déposer un installateur » (jusqu’à ~1 Go). L’upload est prioritaire sur le téléchargement URL.',
                        '« Revenir à la sync URL » supprime l’installateur manuel du serveur et retélécharge à la prochaine synchro.',
                        'L’outil calcule une empreinte (SHA-256) et historise chaque synchro. Sans installateur local valide, la génération ZIP est bloquée.',
                    ],
                ],
                [
                    'id' => 'virustotal',
                    'name' => 'VirusTotal',
                    'summary' => 'Contrôle l’URL (et le SHA-256 s’il est connu) avant de générer le ZIP — logiciels et pilotes Dell / HP.',
                    'how' => [
                        'Activez la fonction et collez une clé API dans Configuration (admin).',
                        'Le pipeline envoie l’URL (installateur EXE/MSI ou pack Dell/HP) à l’API VirusTotal, plus le SHA-256 s’il est publié.',
                        'Si un moteur signale du malveillant, le pipeline se met en pause : à vous de continuer (faux positif, nom CDN, packer…) ou d’arrêter avant le ZIP.',
                        'Un rapport seulement « suspect » n’empêche pas de continuer. Désactivez l’option pour sauter cette étape.',
                    ],
                ],
                [
                    'id' => 'packages',
                    'name' => 'Packages (ZIP)',
                    'summary' => 'Produit un package prêt pour le lab et l’import MECM.',
                    'how' => [
                        'Onglet Synchro / ZIP : générer ou télécharger l’archive.',
                        'Le ZIP contient scripts silencieux, sources, icône, documentation, deploy.json et deploy.ps1.',
                        'Une seule archive est conservée par version catalogue. Voir « Après le ZIP » pour l’import MECM.',
                    ],
                ],
                [
                    'id' => 'lab',
                    'name' => 'Laboratoire VM',
                    'summary' => 'Test réel d’installation sur une machine Windows.',
                    'how' => [
                        'Configurez la VM (hôte, SSH) dans Configuration (admin).',
                        'Le pipeline transfère le ZIP, lance l’install, puis pause pour validation visuelle.',
                        'Choisissez continuer (désinstall / cleanup) ou arrêter (garder le logiciel sur la lab).',
                    ],
                ],
                [
                    'id' => 'deploy',
                    'name' => 'Déployer (MECM)',
                    'summary' => 'Prépare le profil MECM inclus dans le ZIP (site, DP, collection).',
                    'how' => [
                        'Onglet Déployer : code site, serveur site, DP, ID de collection, objectif (disponible / obligatoire), notification.',
                        'Enregistrez : le prochain ZIP généré embarque deploy.json + deploy.ps1 avec ces valeurs.',
                        'Le déploiement réel se fait ensuite hors de MECM Packaging Manager : voir la section « Après le ZIP ».',
                    ],
                ],
                [
                    'id' => 'governance',
                    'name' => 'Gouvernance',
                    'summary' => 'Accès, audit, mailing et paramètres globaux.',
                    'how' => [
                        'Rôles : admin, opérateur, lecteur.',
                        'Logs : journal d’actions.',
                        'Le mot de passe du compte admin se change dans Utilisateurs ; config/auth.php ne sert qu’à créer le compte s’il n’existe pas. L’e-mail du compte est éditable (connexion + destinataire des rappels).',
                        'Configuration : langue (FR/EN), VirusTotal, VM lab, cibles MECM (site, DP, collection), et Mailing (SMTP TLS/SSL, expéditeur, URL d’appli, mail de test).',
                        'Rappels de renouvellement : quand un logiciel dépasse sa fréquence de revue, un e-mail part au dernier packager (dernier ZIP). Envoi manuel depuis Configuration, ou cron Docker (`bin/send-renewal-mails.php`, journal storage/logs/renewal-mails.log).',
                    ],
                ],
            ],
            'status_title' => 'Fréquence, voyant Sync, vérifications et retard',
            'status_lead' => 'Sur les listes Logiciels / Pilotes et le tableau de bord, plusieurs notions se croisent : la fréquence (jours), le retard, le bouton « Vérifier les mises à jour », le voyant Sync, et la distinction version synchro / version packagée. Elles ne font pas le même travail.',
            'status_blocks' => [
                [
                    'title' => 'La fréquence (jours)',
                    'body' => 'C’est un délai de revue, pas un robot. Par défaut : 90 jours, réglable sur chaque fiche. Ouvrir la page ne télécharge rien chez l’éditeur. En Docker, un cron matinal peut envoyer les rappels e-mail (voir Gouvernance → Mailing).',
                ],
                [
                    'title' => 'Version synchro vs version packagée',
                    'body' => 'Après une synchro, la fiche peut afficher une « nouvelle version » détectée chez l’éditeur, distincte de la « version packagée actuelle » (dernier ZIP généré). Rouge / mise à jour dispo = le fichier a changé : il faut régénérer le ZIP. Le numéro catalogue seul ne suffit pas ; l’empreinte (SHA-256) décide.',
                ],
                [
                    'title' => 'La mention « retard »',
                    'body' => 'Elle s’affiche à côté de la date de dernier packaging, et dans le tableau de bord (« Logiciels en retard »). Calcul : date de la dernière revue (synchro, ou packaging s’il n’y a jamais eu de synchro) + fréquence. Jamais de revue, ou délai dépassé → retard. Ce n’est pas une nouvelle version. Un logiciel au voyant gris (jamais sync) est donc en retard. Après un clic sur « Vérifier les mises à jour », le retard disparaît : la revue vient d’être faite. Il peut réapparaître N jours plus tard, même si le voyant est encore vert, pour rappeler de revérifier — et déclencher un e-mail de rappel si le mailing est configuré.',
                ],
                [
                    'title' => 'Le bouton « Vérifier les mises à jour »',
                    'body' => 'Un clic lance une synchro de chaque fiche qui a une URL (une après l’autre). Logiciels : chaque installateur est téléchargé et comparé en SHA-256 au dernier ZIP. Pilotes Dell / HP : la page KB ou la matrice SoftPaq est lue, sans télécharger les Go ; on compare la révision / l’URL au dernier ZIP. Le voyant et le numéro de version se mettent à jour tout de suite. Même action depuis une fiche ou via le pipeline (le pipeline, lui, génère aussi le ZIP).',
                ],
                [
                    'title' => 'Le voyant Sync',
                    'body' => 'Le point coloré n’est pas un bouton : rien à cliquer. Il mémorise le dernier résultat de synchro, pas « vérifié aujourd’hui ». Vert = même fichier que le dernier package. Rouge = nouvel installateur à packager. Orange = la vérif a échoué (pas une nouvelle version). Gris = jamais synchronisé, ou pas d’URL.',
                ],
            ],
            'status_dots_title' => 'Couleurs du voyant Sync',
            'status_dots' => [
                [
                    'dot' => 'ok',
                    'label' => 'Vert — À jour',
                    'body' => 'Dernière synchro : même installateur que le dernier ZIP (empreinte identique). Pas de nouvel installateur à packager.',
                ],
                [
                    'dot' => 'ko',
                    'label' => 'Rouge — Mise à jour disponible',
                    'body' => 'Le fichier chez l’éditeur a changé (SHA-256 différent du dernier ZIP). Il y a un nouvel installateur à packager.',
                ],
                [
                    'dot' => 'err',
                    'label' => 'Orange — Erreur de synchro',
                    'body' => 'La vérif a échoué (URL, réseau, page HTML au lieu d’un MSI/EXE…). Ce n’est pas une nouvelle version. Ouvrez la fiche, onglet Synchro, pour le détail.',
                ],
                [
                    'dot' => 'muted',
                    'label' => 'Gris — Jamais sync / pas d’URL',
                    'body' => 'Aucune synchro réussie, ou la fiche n’a pas d’URL. La fiche est aussi « en retard » tant qu’aucune revue n’a eu lieu.',
                ],
            ],
            'status_note' => 'En résumé : la fréquence dit « tous les N jours ». Le retard dit « le délai est dépassé, il faut regarder » (et peut déclencher un e-mail). Le bouton dit « va chercher le fichier maintenant ». Le voyant dit « qu’a donné la dernière recherche » (vert / rouge / orange / gris). Version synchro ≠ version packagée tant que le ZIP n’est pas régénéré.',
            'detection_title' => 'Détection de la dernière version',
            'detection_lead' => 'À chaque synchro (bouton « Vérifier les mises à jour » ou pipeline), MECM Packaging Manager retrouve la source courante, puis décide s’il s’agit d’une nouveauté. Logiciels : un seul champ URL (fichier, GitHub Releases, ou dépôt manuel). Pilotes : page KB Dell ou matrice SoftPaq HP. Les configs BIOS, elles, ne se « synchronisent » pas : vous construisez le fichier .cfg / .ini dans l’éditeur, puis vous le déployez avec le script constructeur.',
            'detection_common_title' => 'Ce qui est commun à tous les modes',
            'detection_common' => [
                'La « nouvelle version » au sens packaging n’est pas un simple numéro : logiciels = SHA-256 du fichier vs dernier ZIP ; pilotes Dell = révision Axx + URL ; pilotes HP = SoftPaq + version (2.00 A 1) + URL vs dernier ZIP.',
                'Le numéro de version affiché (tag GitHub, nom de fichier, chemin CDN après redirection) est informatif : il est mémorisé sur la fiche, mais le déclencheur reste l’empreinte du binaire.',
                'Si l’URL ne change jamais de nom (MSI ou EXE evergreen), aucun numéro n’est lisible dans l’URL : après téléchargement, la version est lue dans le fichier — ProductVersion du MSI (Chrome Entreprise) ou ressource PE de l’EXE (OnlyOffice `DesktopEditors_x64.exe`). Sans cela, la fiche resterait à « unknown ».',
                'La « fréquence (jours) », le retard et le voyant Sync sont détaillés dans « Fréquence, Sync et retard » : pas de cron ; rouge ≠ orange ; retard ≠ mise à jour.',
                'Si l’URL de téléchargement ou de détection contient github.com, le mode GitHub est utilisé même si « URL fixe » est sélectionné.',
            ],
            'detection_modes' => [
                [
                    'id' => 'dell_kb',
                    'name' => 'Page KB Dell (pilotes)',
                    'summary' => 'Packs Driver Package Dell : page support/kbdoc du modèle.',
                    'how' => [
                        'Collez l’URL de la page pack du modèle (ex. https://www.dell.com/support/kbdoc/fr-fr/000211767/pack-de-pilotes-windows-10-latitude-7440).',
                        'À la vérif, l’atelier lit la page (repli en-us si la locale 403), trouve le lien downloads.dell.com et la révision (A11, A13… dans le nom de fichier).',
                        'L’empreinte comparée au dernier ZIP est version + URL du pack — pas un SHA-256 du binaire, car le pack n’est jamais téléchargé dans PHP.',
                        '« Vérifier les mises à jour » sur la liste ne fait que cette lecture. Le Pipeline sur la fiche enchaîne ensuite VirusTotal (optionnel) et le ZIP d’import MECM.',
                    ],
                    'example' => 'Latitude 7440 → page KB 000211767 → Latitude-7440-…_A11.exe.',
                ],
                [
                    'id' => 'hp_matrix',
                    'name' => 'Matrice SoftPaq HP (pilotes)',
                    'summary' => 'Catalogue public HP Driver Packs. La version n’est pas dans le nom de fichier : elle est dans l’infobulle du SoftPaq (Version: 2.00 A 1) plus l’identifiant SPnnnnnn.',
                    'how' => [
                        'Collez l’URL de la matrice (https://ftp.hp.com/pub/caps-softpaq/cmit/HP_Driverpack_Matrix_x64.html), le modèle (ex. EliteBook 840 G8) et la colonne Windows — ou « le plus récent disponible ».',
                        'À la vérif, l’atelier lit la matrice (cache ~15 min pour le bouton liste), trouve le SoftPaq et la version. PHP ne télécharge jamais le .exe.',
                        'L’empreinte comparée au dernier ZIP est SoftPaq + version + URL. Un nouveau SPnnnnnn ou un passage 2.00 A 1 → 3.00 A 1 allume le voyant rouge.',
                        'Le Pipeline enchaîne VirusTotal (optionnel) puis le ZIP. deploy-drivers.ps1 extrait le SoftPaq (/s /e /f) sur le poste console MECM.',
                    ],
                    'example' => 'EliteBook 840 G8 → Windows 11 24H2 → SP142670 · 3.00 A 1.',
                ],
                [
                    'id' => 'github',
                    'name' => 'GitHub Releases (API)',
                    'summary' => 'Idéal quand l’éditeur publie les installateurs sur GitHub (7-Zip, Brave, Git, Cryptomator…).',
                    'how' => [
                        'Renseignez uniquement la page Releases du dépôt (ex. https://github.com/ip7z/7zip/releases/). Pas besoin d’URL de fichier : l’API choisit l’asset.',
                        'À la synchro, l’outil appelle l’API GitHub /repos/{owner}/{repo}/releases/latest (hors brouillons et préversions).',
                        'La version catalogue est extraite du tag (ex. 26.02).',
                        'Parmi les fichiers de la release, un installateur Windows est choisi : MSI/MSIX d’abord, puis EXE ; l’architecture de la fiche (x64, x86, ARM) pèse dans le choix ; les portables / sources / Linux / Mac sont écartés.',
                        'Le fichier retenu est téléchargé, contrôlé (ce n’est pas une page HTML), puis comparé en SHA-256 au dernier package.',
                    ],
                    'example' => '7-Zip → github.com/ip7z/7zip/releases → asset 7z2602-x64.msi.',
                ],
                [
                    'id' => 'fixed_url',
                    'name' => 'URL fixe (téléchargement direct)',
                    'summary' => 'Lien .exe / .msi stable, éventuellement un « latest » qui redirige vers un fichier versionné (Zoom, Firefox ESR, Adobe, CRAN…).',
                    'how' => [
                        'Renseignez l’URL de téléchargement : elle doit aboutir à un binaire, pas à une page HTML de téléchargement.',
                        'À la synchro, l’outil suit les redirections (HEAD) pour exposer l’URL finale (utile quand le CDN embarque la version dans le chemin).',
                        'La version est déduite du nom de fichier ou de l’URL finale (ex. gimp-3.0.8-setup.exe, …/prod/7.1.5.43453/…).',
                        'Le type (MSI vs EXE) est deviné d’après l’extension ou des mots-clés (msi-latest, etc.).',
                        'Le fichier est téléchargé, validé, puis comparé en SHA-256 au dernier package. Si le nom de fichier n’a pas de version (Chrome MSI, OnlyOffice EXE, certains « latest »), ProductVersion est lu dans le binaire.',
                    ],
                    'example' => 'GIMP → download.gimp.org/…/gimp-3.0.8-setup.exe ; R → cran.r-project.org/…/R-4.6.1-win.exe.',
                ],
                [
                    'id' => 'manual',
                    'name' => 'Dépôt manuel (pas d’URL exploitable)',
                    'summary' => 'Portail authentifié, EULA, ou binaire introuvable en HTTP (DaVinci Resolve, Adobe CC, ClickShare…).',
                    'how' => [
                        'Déposez le .exe / .msi sur la fiche (Infos ou Synchro). Il devient prioritaire : plus aucun téléchargement URL tant qu’il est en place.',
                        'La synchro se contente de valider le fichier local (type, taille, empreinte) et de le marquer prêt pour le ZIP.',
                        '« Revenir à la sync URL » efface ce fichier serveur et réactive GitHub / URL fixe à la prochaine synchro.',
                        'La « dernière version » n’est alors pas découverte automatiquement : c’est vous qui déposez le nouvel installateur.',
                    ],
                    'example' => 'DaVinci Resolve : téléchargement sur le site Blackmagic, puis dépôt dans l’onglet Synchro.',
                ],
            ],
            'detection_note' => 'Sans installateur local valide (sync URL ou dépôt), la génération du ZIP logiciel est bloquée. Pour un pilote Dell ou HP, le ZIP d’import se génère sans le pack : PowerShell le télécharge sur le serveur MECM. L’historique conserve l’URL source, le nom de fichier, le SHA-256 s’il est connu et le statut.',
            'detection_example_label' => 'Exemple',
            'cycle_title' => 'Cycle recommandé',
            'cycle_lead' => 'Enchaînement typique pour un logiciel. Les pilotes Dell / HP suivent un cycle plus court (fiche constructeur → vérif → pipeline ZIP → deploy-drivers.ps1), sans VM. Les BIOS : éditeur → version .cfg/.ini → script Apply + outil constructeur en TS MECM.',
            'cycle' => [
                ['title' => '1. Fiche catalogue', 'body' => 'Métadonnées, URL, icône.'],
                ['title' => '2. Procédure', 'body' => 'Assistant guidé ou étapes manuelles (install / uninstall / détection).'],
                ['title' => '3. Installateur', 'body' => 'Sync URL ou dépôt manuel si le binaire n’est pas téléchargeable.'],
                ['title' => '4. Pipeline', 'body' => 'Synchro → VirusTotal (si activé) → ZIP → test VM avec confirmation.'],
                ['title' => '5. Profil MECM', 'body' => 'Onglet Déployer : site, DP, collection — puis régénérer le ZIP.'],
                ['title' => '6. Après le ZIP', 'body' => 'Copier le ZIP sur le partage sources, lancer deploy.ps1 (création MECM + DP + collection).'],
                ['title' => 'BIOS (parallèle)', 'body' => 'Hub constructeur → éditeur → enregistrer Bios*-N → télécharger Apply-*.ps1 + outil (CMSL / cctk / Lenovo.BIOS.Config) → TS ou package.'],
            ],
            'zip_title' => 'Après le ZIP : import MECM',
            'zip_lead' => 'MECM Packaging Manager s’arrête à la génération du package. L’ajout dans MECM se fait avec le script inclus dans le ZIP : deploy.ps1 (applications) ou deploy-drivers.ps1 (pilotes Dell / HP).',
            'zip_steps' => [
                [
                    'title' => '1. Télécharger le ZIP',
                    'body' => 'Depuis l’onglet Pipeline (ou Synchro / ZIP) du logiciel, téléchargez le dernier package généré (après un sync / rebuild réussi).',
                ],
                [
                    'title' => '2. Déposer sur le partage sources',
                    'body' => 'Uploadez / copiez le ZIP (ou extrayez son contenu) sur le partage de sources MECM — le chemin UNC qui servira de Content Location pour l’application.',
                ],
                [
                    'title' => '3. Lancer deploy.ps1',
                    'body' => 'Sur un poste avec la console MECM (module ConfigurationManager), ouvrez PowerShell en administrateur, placez-vous dans le dossier du package sur le partage, puis lancez : .\deploy.ps1',
                ],
                [
                    'title' => '4. Ce que fait le script automatiquement',
                    'body' => 'Crée ou met à jour l’application dans MECM ; configure le Centre logiciel (nom + version, description, icône…) ; distribue le contenu vers le Distribution Point (DP) ; déploie l’application sur la collection (par Collection ID) avec l’objectif choisi (disponible ou obligatoire).',
                ],
            ],
            'zip_note' => 'Les paramètres (site, DP, CollectionId, partages pilotes) viennent de Configuration / onglet Déployer. Vérifiez-les avant de lancer le script. Aucun argument n’est requis si le profil est déjà enregistré dans le ZIP.',
            'zip_drivers_title' => 'Pilotes Dell / HP : après le ZIP',
            'zip_drivers_lead' => 'Le ZIP pilote ne contient pas les Go de binaires. deploy-drivers.ps1 les télécharge sur le poste console MECM.',
            'zip_drivers_steps' => [
                [
                    'title' => '1. Télécharger le ZIP pilote',
                    'body' => 'Depuis la fiche pilote, après un pipeline réussi.',
                ],
                [
                    'title' => '2. Extraire sur un poste console MECM',
                    'body' => 'Le poste doit atteindre le site, le DP, et les deux partages UNC (source INF et Driver Packages) configurés dans l’atelier.',
                ],
                [
                    'title' => '3. Lancer deploy-drivers.ps1',
                    'body' => 'Le script télécharge le pack Dell ou le SoftPaq HP, l’extrait (CAB / ZIP / EXE), importe les INF, vérifie le SHA-256 s’il est connu, puis crée le Driver Package sur le second UNC et le distribue au DP.',
                ],
            ],
            'step_form_title' => 'Formulaire « Ajouter une étape »',
            'step_form_lead' => 'Dans l’onglet Procédure, ce formulaire crée une étape. Les champs communs :',
            'step_form_fields' => [
                ['name' => 'Type *', 'detail' => 'Choisit le genre d’étape (liste ci-dessous). Détermine les champs affichés ensuite.'],
                ['name' => 'Libellé', 'detail' => 'Nom libre optionnel pour retrouver l’étape dans la liste (ex. « Install MSI x64 »).'],
                ['name' => 'Si échec', 'detail' => 'stop : interrompt la procédure / le script si l’étape échoue. continue : passe à l’étape suivante malgré l’échec.'],
                ['name' => 'Activée', 'detail' => 'Décochez pour garder l’étape dans la procédure sans l’exécuter (utile pour un test temporaire).'],
            ],
            'step_types_title' => 'Options du menu Type',
            'step_types_lead' => 'Chaque type correspond à une action packaging. Voici à quoi sert chaque option et les champs principaux.',
            'step_types' => [
                [
                    'name' => 'Commande d’installation',
                    'code' => 'install_command',
                    'summary' => 'Lance l’installateur (MSI ou EXE) en mode silencieux.',
                    'fields' => 'Commande (fichier), paramètres silencieux (/qn, /S…), répertoire de travail, timeout.',
                ],
                [
                    'name' => 'Commande de désinstallation',
                    'code' => 'uninstall_command',
                    'summary' => 'Désinstalle le logiciel (souvent msiexec /x pour un MSI).',
                    'fields' => 'Commande, paramètres silencieux, Product Code MSI (GUID) si connu.',
                ],
                [
                    'name' => 'Copie de fichier',
                    'code' => 'copy_file',
                    'summary' => 'Copie un fichier ou dossier vers une destination (pré-requis, config, etc.).',
                    'fields' => 'Source, destination, option Écraser.',
                ],
                [
                    'name' => 'Suppression de fichier',
                    'code' => 'delete_file',
                    'summary' => 'Supprime un chemin (cleanup, vieux fichiers).',
                    'fields' => 'Chemin, option Récursif.',
                ],
                [
                    'name' => 'Renommage',
                    'code' => 'rename',
                    'summary' => 'Renomme un fichier ou dossier.',
                    'fields' => 'Depuis, Vers.',
                ],
                [
                    'name' => 'Création de raccourci',
                    'code' => 'create_shortcut',
                    'summary' => 'Crée un raccourci Windows (bureau, menu Démarrer…).',
                    'fields' => 'Cible, chemin du raccourci, arguments, icône.',
                ],
                [
                    'name' => 'Clé de registre',
                    'code' => 'registry',
                    'summary' => 'Crée, modifie ou supprime une valeur de registre.',
                    'fields' => 'Ruche (HKLM/HKCU…), clé, nom/type/données de valeur, action set ou delete.',
                ],
                [
                    'name' => 'Script PowerShell',
                    'code' => 'powershell',
                    'summary' => 'Exécute un script PowerShell inline ou un fichier .ps1.',
                    'fields' => 'Script (inline), chemin du script, exécuter en tant que (system/user/admin).',
                ],
                [
                    'name' => 'Note / documentation',
                    'code' => 'note',
                    'summary' => 'Texte pour l’équipe (pas d’action technique). Idéal pour consignes et pièges connus.',
                    'fields' => 'Texte libre.',
                ],
            ],
            'procedure_title' => 'Procédures : assistant et exemple',
            'procedure_lead' => 'Pour une procédure vide, préférez l’assistant : d’abord l’URL et le téléchargement (pour connaître MSI/EXE), puis install / uninstall / détection.',
            'procedure_wizard_title' => 'Assistant procédure (recommandé)',
            'procedure_wizard_lead' => 'Onglet Procédure → « Lancer l’assistant ». L’URL est demandée en premier ; le fichier téléchargé dicte la suite.',
            'procedure_wizard_steps' => [
                ['title' => '1. URL', 'body' => 'Collez le lien du .exe/.msi ou la page GitHub Releases, puis récupérez l’installateur. Le type (MSI/EXE), le nom de fichier et les commandes sont déduits.'],
                ['title' => '2. Stratégie', 'body' => 'MSI → msiexec via install.ps1. EXE → scripts type Brave. Préremplissage des arguments silencieux (/qn, /S, /VERYSILENT…).'],
                ['title' => '3. Installation', 'body' => 'Nom du setup (ex. setup.msi), paramètres silencieux, répertoire de travail, timeout.'],
                ['title' => '4. Désinstallation', 'body' => 'Commande uninstall + Product Code MSI si connu.'],
                ['title' => '5. Détection', 'body' => 'Chemins de succès (fichiers présents après install), chemins « disparus » après uninstall, processus à tuer ; aperçu du témoin MECM auto-généré.'],
                ['title' => '6. Récapitulatif', 'body' => 'Vérification puis création des étapes install + uninstall. Notes optionnelles. Relancer l’assistant remplace les étapes install/uninstall existantes si confirmé.'],
            ],
            'procedure_example_title' => 'Exemple : 7-Zip (MSI x64)',
            'procedure_example_context' => 'Logiciel MSI, installation machine (système), détection possible par fichier témoin ou Product Code. Saisie manuelle ou via l’assistant.',
            'procedure_steps' => [
                [
                    'type' => 'note',
                    'title' => 'Note / documentation',
                    'detail' => "Texte libre pour l’opérateur, par ex. : « Installateur MSI officiel 7-Zip x64. Préférer le MSI au setup EXE. »",
                ],
                [
                    'type' => 'install_command',
                    'title' => 'Commande d’installation',
                    'detail' => "Commande : nom du MSI (ex. 7z2602-x64.msi)\nParamètres silencieux : /qn /norestart\nRépertoire de travail : .\nTimeout : 600",
                ],
                [
                    'type' => 'uninstall_command',
                    'title' => 'Commande de désinstallation',
                    'detail' => "Commande : msiexec.exe\nParamètres silencieux : /x {PRODUCT-CODE} /qn /norestart\nProduct Code : GUID MSI (ex. {23170F69-40C1-2702-2602-000001000000}) — renseigné dès qu’il est connu, utile pour la détection MECM.",
                ],
                [
                    'type' => 'note',
                    'title' => 'Note — détection / témoin',
                    'detail' => "Indiquez le témoin attendu après install, ex. présence de C:\\Program Files\\7-Zip\\7z.exe, ou le fichier log C:\\SCCM_Temoins\\7Zip\\7zip.<version>.log généré par le package.",
                ],
            ],
            'procedure_tips_title' => 'Bonnes pratiques',
            'procedure_tips' => [
                'Procédure vide : commencez par « Lancer l’assistant » plutôt que d’ajouter les étapes une par une.',
                'Une procédure = la vérité opérationnelle du package : gardez-la à jour quand les args silencieux changent.',
                'Pour un EXE, utilisez les switches de l’éditeur (/S, /quiet, /VERYSILENT…) dans « Paramètres silencieux ».',
                'Chemin uninstall sans guillemets : %ProgramFiles%\\Microsoft VS Code\\unins000.exe (les guillemets autour du chemin faisaient échouer le script, exit 1603).',
                'Pas de .exe via l’URL ? Déposez l’installateur manuellement (Infos / Synchro) avant de lancer le pipeline.',
                'Le Product Code MSI, s’il est renseigné, est repris par deploy.ps1 pour la détection.',
                'Après modification de procédure ou du profil MECM, régénérez le ZIP, puis relancez deploy.ps1 depuis le partage sources.',
            ],
        ];
    }

    /**
     * @return DocsCopy
     */
    private static function en(): array
    {
        return [
            'page_title' => 'Documentation',
            'intro_title' => 'User guide',
            'intro_body' => 'This guide covers MECM Packaging Manager 4.5: software, Dell / HP drivers (beta), HP / Dell / Lenovo BIOS hubs (beta), VirusTotal, renewal e-mail reminders, the packaging cycle, and what to do with the ZIP (deploy.ps1 or deploy-drivers.ps1).',
            'toc_modules' => 'Modules',
            'toc_status' => 'Frequency, Sync, and overdue',
            'toc_detection' => 'Version detection',
            'toc_drivers' => 'Dell / HP drivers',
            'toc_bios' => 'BIOS (HP / Dell / Lenovo)',
            'toc_cycle' => 'Recommended cycle',
            'toc_zip' => 'After the ZIP (MECM)',
            'toc_step_types' => 'Step types',
            'toc_procedure' => 'Procedure example',
            'modules_title' => 'Modules',
            'modules_lead' => 'Each module covers one stage of packaging → validation → deployment.',
            'modules' => [
                [
                    'id' => 'catalogue',
                    'name' => 'Catalogue',
                    'summary' => 'Inventory of applications to package.',
                    'how' => [
                        'Create or open a record: name, publisher, category (Settings → Categories), version, architecture, installer type (MSI / EXE).',
                        'Set the download URL (prefer a direct .msi/.exe link) and review cadence (90 days by default: calendar / e-mail reminder, not an automatic download).',
                        'Documentation URL (optional): this is what MECM receives as Software Center User documentation. If empty, the installer / GitHub URL is used as before.',
                        'On the list and Info tab: distinction between “new version (synced)” and “currently packaged version” (last ZIP). The Sync indicator tracks the file, not only the displayed number.',
                        'On the list: Sync indicator, “overdue” label, and “Check for updates” — see “Frequency, Sync, and overdue”.',
                        'Add the Software Center icon: it is embedded in every ZIP and pushed to MECM.',
                    ],
                ],
                [
                    'id' => 'drivers',
                    'name' => 'Dell / HP drivers (beta)',
                    'summary' => 'Driver Package catalogue (beta module). Dell (KB page) and HP (SoftPaq matrix).',
                    'how' => [
                        'Dell: name, model (e.g. Latitude 7440), architecture. Paste the pack KB page URL (support/kbdoc/…), not the multi-gigabyte .exe.',
                        'HP: name, model as in the matrix (e.g. EliteBook 840 G8), Windows (24H2 / 25H2 or “newest”). The x64 matrix URL is pre-filled. The workshop reads SoftPaq + version (2.00 A 1).',
                        'On the list: “Check for updates” re-reads each vendor page and compares it to the last ZIP. The workshop never downloads the pack.',
                        'Red dot = new pack since the last ZIP → open the record and run Pipeline. The ZIP only contains deploy-drivers.ps1 and driver.json. On a MECM console workstation, the script downloads the pack, extracts it (CAB/ZIP/SoftPaq EXE), and creates the Driver Package.',
                    ],
                ],
                [
                    'id' => 'bios',
                    'name' => 'BIOS HP / Dell / Lenovo (beta)',
                    'summary' => 'BIOS configuration hubs for MECM / task-sequence deployment. Still a beta module.',
                    'how' => [
                        'BIOS menu → pick the vendor (HP, Dell, or Lenovo). Each hub follows the same pattern: config editor, PowerShell scripts, saved versions, links to the vendor tool.',
                        'HP: BCU editor → BiosHP-N.cfg files. CMSL scripts (Apply-HPBiosConfig, Clear Setup password, Get-All). Prerequisite: HP Client Management Script Library on the target.',
                        'Dell: CCTK editor → BiosDell-N.ini files ([cctk] section). Dell Command | Configure scripts (cctk.exe -i). Common option subset — extend via cctk -o on a reference machine if needed.',
                        'Lenovo: TBCT editor → BiosLenovo-N.ini files (SettingName,Value lines). Lenovo.BIOS.Config / Think BIOS Config Tool v2 scripts (Import-LnvWmiSettings + Save-LnvWmiSettings).',
                        'Option-list sources (also shown on each hub): HP — Get-HPBIOSSettingsList / BCU guide (developers.hp.com, ftp.hp.com); Dell — Command | Configure CLI “BIOS options” (dell.com/support/manuals); Lenovo — TBCT v2 / Lenovo.BIOS.Config docs (docs.lenovocdrt.com), model-specific names via Export-LnvWmiSettings.',
                        'Select options, save a version: download is a ZIP (config + Apply/Clear/Get scripts + Modules/tools/Lenovo.BIOS.Config tree to fill with the vendor tool). CMSL / cctk / Lenovo.BIOS.Config binaries are not redistributed (licence).',
                        'Apply scripts can also set a Setup / Supervisor password (random or fixed) and write a log (optional UNC share).',
                    ],
                ],
                [
                    'id' => 'procedure',
                    'name' => 'Procedure',
                    'summary' => 'Structured recipe (typed steps) reused for every new version.',
                    'how' => [
                        'Open the Procedure tab on the software record.',
                        'Empty procedure? Click “Start wizard”: URL → download the installer → MSI/EXE strategy → install → uninstall → detection → review.',
                        'Otherwise add steps manually: install command, uninstall, notes, PowerShell, files, registry, and more.',
                        'Silent arguments and the MSI Product Code feed package generation and the Deploy assistant.',
                    ],
                ],
                [
                    'id' => 'sync',
                    'name' => 'Synchronization',
                    'summary' => 'Checks for updates and downloads the installer.',
                    'how' => [
                        'Nothing is checked automatically when the page loads. Click “Check for updates” (list) or “Check for update” (record), or run the pipeline.',
                        'Pick a detection mode (GitHub Releases or fixed URL) — see “Version detection”.',
                        'If the URL does not provide .exe/.msi: Info or Sync tab → “Upload an installer” (up to ~1 GB). Manual upload takes priority over URL download.',
                        '“Switch back to URL sync” deletes the manual installer from the server and re-downloads on the next sync.',
                        'The tool computes a SHA-256 fingerprint and keeps sync history. Without a valid local installer, ZIP generation is blocked.',
                    ],
                ],
                [
                    'id' => 'virustotal',
                    'name' => 'VirusTotal',
                    'summary' => 'Checks the URL (and SHA-256 when known) before ZIP generation — software and Dell / HP drivers.',
                    'how' => [
                        'Enable the feature and paste an API key under Settings (admin).',
                        'The pipeline submits the URL (EXE/MSI installer or Dell/HP pack) to the VirusTotal API, plus the SHA-256 if it is published.',
                        'If any engine reports malware, the pipeline pauses: you choose to continue (false positive, CDN name, packer…) or stop before the ZIP.',
                        'A “suspicious” verdict alone does not block ZIP generation. Turn the option off to skip this step.',
                    ],
                ],
                [
                    'id' => 'packages',
                    'name' => 'Packages (ZIP)',
                    'summary' => 'Builds a package ready for the lab and MECM import.',
                    'how' => [
                        'Sync / ZIP tab: generate or download the archive.',
                        'The ZIP includes silent scripts, sources, icon, docs, deploy.json, and deploy.ps1.',
                        'Only one archive is kept per catalogue version. See “After the ZIP” for MECM import.',
                    ],
                ],
                [
                    'id' => 'lab',
                    'name' => 'Lab VM',
                    'summary' => 'Real install test on a Windows machine.',
                    'how' => [
                        'Configure the VM (host, SSH) under Settings (admin).',
                        'The pipeline copies the ZIP, runs install, then pauses for visual confirmation.',
                        'Choose continue (uninstall / cleanup) or stop (leave the app on the lab).',
                    ],
                ],
                [
                    'id' => 'deploy',
                    'name' => 'Deploy (MECM)',
                    'summary' => 'Prepares the MECM profile embedded in the ZIP (site, DP, collection).',
                    'how' => [
                        'Deploy tab: site code, site server, DP, collection ID, purpose (available / required), notification.',
                        'Save: the next generated ZIP embeds deploy.json + deploy.ps1 with those values.',
                        'Actual MECM work happens outside MECM Packaging Manager — see the “After the ZIP” section.',
                    ],
                ],
                [
                    'id' => 'governance',
                    'name' => 'Governance',
                    'summary' => 'Access, audit, mailing, and global settings.',
                    'how' => [
                        'Roles: admin, operator, viewer.',
                        'Logs: action audit trail.',
                        'The admin account password is changed under Users; config/auth.php only seeds the account if it is missing. Account e-mail is editable (sign-in + reminder recipient).',
                        'Settings: language (FR/EN), VirusTotal, lab VM, MECM defaults (site, DP, collection), and Mailing (SMTP TLS/SSL, sender, app URL, test mail).',
                        'Renewal reminders: when software exceeds its review frequency, an e-mail goes to the last packager (last ZIP). Manual send from Settings, or Docker cron (`bin/send-renewal-mails.php`, log storage/logs/renewal-mails.log).',
                    ],
                ],
            ],
            'status_title' => 'Frequency, Sync indicator, checks, and overdue',
            'status_lead' => 'On the Software / Drivers lists and the dashboard, several ideas overlap: frequency (days), overdue, “Check for updates”, the Sync indicator, and synced vs packaged version. They do different jobs.',
            'status_blocks' => [
                [
                    'title' => 'Frequency (days)',
                    'body' => 'This is a review deadline, not a robot. Default: 90 days, editable on each record. Opening the page does not download anything from the vendor. In Docker, a morning cron can send e-mail reminders (see Governance → Mailing).',
                ],
                [
                    'title' => 'Synced version vs packaged version',
                    'body' => 'After a sync, a record may show a “new version” detected at the vendor, distinct from the “currently packaged version” (last ZIP). Red / update available = the file changed: regenerate the ZIP. The catalogue number alone is not enough; the fingerprint (SHA-256) decides.',
                ],
                [
                    'title' => 'The “overdue” label',
                    'body' => 'It appears next to the last packaging date, and on the dashboard (“Overdue software”). Computation: last review date (sync, or packaging if never synced) + frequency. Never reviewed, or deadline passed → overdue. This is not a new version. A grey indicator (never synced) is therefore overdue. After “Check for updates”, overdue is cleared: the review has just been done. It can come back N days later even if the dot is still green, as a reminder to check again — and trigger a reminder e-mail if mailing is configured.',
                ],
                [
                    'title' => 'The “Check for updates” button',
                    'body' => 'One click syncs every record that has a URL (one after another). Software: each installer is downloaded and compared by SHA-256 to the last ZIP. Dell / HP drivers: the KB page or SoftPaq matrix is read, without downloading gigabytes; the revision / pack URL is compared to the last ZIP. The indicator and version number update immediately. Same action from a record or via the pipeline (the pipeline also builds the ZIP).',
                ],
                [
                    'title' => 'The Sync indicator',
                    'body' => 'The coloured dot is not a button: there is nothing to click. It stores the last sync result, not “checked today”. Green = same file as the last package. Red = new installer to package. Orange = the check failed (not a new version). Grey = never synced, or no URL.',
                ],
            ],
            'status_dots_title' => 'Sync indicator colours',
            'status_dots' => [
                [
                    'dot' => 'ok',
                    'label' => 'Green — Up to date',
                    'body' => 'Last sync: same installer as the last ZIP (identical fingerprint). No new installer to package.',
                ],
                [
                    'dot' => 'ko',
                    'label' => 'Red — Update available',
                    'body' => 'The vendor file changed (SHA-256 differs from the last ZIP). There is a new installer to package.',
                ],
                [
                    'dot' => 'err',
                    'label' => 'Orange — Sync error',
                    'body' => 'The check failed (URL, network, HTML page instead of MSI/EXE…). This is not a new version. Open the record, Sync tab, for details.',
                ],
                [
                    'dot' => 'muted',
                    'label' => 'Grey — Never synced / no URL',
                    'body' => 'No successful sync yet, or the record has no URL. The record is also “overdue” until a review has taken place.',
                ],
            ],
            'status_note' => 'In short: frequency says “every N days”. Overdue says “the deadline has passed, look now” (and may trigger e-mail). The button says “fetch the file now”. The indicator says “what did the last fetch find” (green / red / orange / grey). Synced version ≠ packaged version until the ZIP is regenerated.',
            'detection_title' => 'How the latest version is detected',
            'detection_lead' => 'On each sync (“Check for updates” or pipeline), MECM Packaging Manager locates the current source, then decides whether it is new. Software: one URL field (file, GitHub Releases, or manual upload). Drivers: Dell KB page or HP SoftPaq matrix. BIOS configs are not “synced”: you build the .cfg / .ini in the editor, then deploy it with the vendor script.',
            'detection_common_title' => 'What is common to every mode',
            'detection_common' => [
                'A “new version” for packaging is not just a version number: software = SHA-256 of the file vs last ZIP; Dell drivers = Axx revision + pack URL; HP drivers = SoftPaq + version (2.00 A 1) + URL vs last ZIP. Same file / pack → up to date. Different fingerprint → update available.',
                'The displayed version (GitHub tag, file name, CDN path after redirect) is informational: it is stored on the record, but the trigger remains the binary fingerprint.',
                'If the URL never changes name (evergreen MSI or EXE), no number is visible in the URL: after download, the version is read from the file — MSI ProductVersion (Chrome Enterprise) or the EXE PE resource (OnlyOffice `DesktopEditors_x64.exe`). Otherwise the record would stay at “unknown”.',
                'Frequency (days), overdue, and the Sync indicator are covered in “Frequency, Sync, and overdue”: no cron; red ≠ orange; overdue ≠ update available.',
                'If the download or version-check URL contains github.com, GitHub mode is used even when “Fixed URL” is selected.',
            ],
            'detection_modes' => [
                [
                    'id' => 'dell_kb',
                    'name' => 'Dell KB page (drivers)',
                    'summary' => 'Dell Driver Packages: the model support/kbdoc page.',
                    'how' => [
                        'Paste the model pack page URL (e.g. https://www.dell.com/support/kbdoc/en-us/000211767/windows-10-driver-pack-latitude-7440).',
                        'On check, the workshop reads the page (falls back to en-us on locale 403), finds the downloads.dell.com link and the revision (A11, A13… in the file name).',
                        'The fingerprint compared to the last ZIP is version + pack URL — not a SHA-256 of the binary, because PHP never downloads the pack.',
                        '“Check for updates” on the list only does this read. Pipeline on the record then runs VirusTotal (optional) and the MECM import ZIP.',
                    ],
                    'example' => 'Latitude 7440 → KB page 000211767 → Latitude-7440-…_A11.exe.',
                ],
                [
                    'id' => 'hp_matrix',
                    'name' => 'HP SoftPaq matrix (drivers)',
                    'summary' => 'Public HP Driver Packs catalogue. The version is not in the file name: it lives in the SoftPaq link tooltip (Version: 2.00 A 1) plus the SPnnnnnn id.',
                    'how' => [
                        'Paste the matrix URL (https://ftp.hp.com/pub/caps-softpaq/cmit/HP_Driverpack_Matrix_x64.html), the model (e.g. EliteBook 840 G8), and the Windows column — or “newest available”.',
                        'On check, the workshop reads the matrix (cached ~15 min for the list button), finds the SoftPaq and version. PHP never downloads the .exe.',
                        'The fingerprint compared to the last ZIP is SoftPaq + version + URL. A new SPnnnnnn or 2.00 A 1 → 3.00 A 1 turns the indicator red.',
                        'Pipeline then runs VirusTotal (optional) and the ZIP. deploy-drivers.ps1 extracts the SoftPaq (/s /e /f) on the MECM console host.',
                    ],
                    'example' => 'EliteBook 840 G8 → Windows 11 24H2 → SP142670 · 3.00 A 1.',
                ],
                [
                    'id' => 'github',
                    'name' => 'GitHub Releases (API)',
                    'summary' => 'Best when the publisher ships installers on GitHub (7-Zip, Brave, Git, Cryptomator…).',
                    'how' => [
                        'Put only the repo Releases page (e.g. https://github.com/ip7z/7zip/releases/). No file URL needed: the API picks the asset.',
                        'On sync, the tool calls the GitHub API /repos/{owner}/{repo}/releases/latest (drafts and prereleases skipped).',
                        'The catalogue version is taken from the tag (e.g. 26.02).',
                        'Among release files, a Windows installer is chosen: MSI/MSIX first, then EXE; the record architecture (x64, x86, ARM) weights the choice; portable / source / Linux / Mac assets are skipped.',
                        'The chosen file is downloaded, checked (not an HTML page), then compared by SHA-256 to the last package.',
                    ],
                    'example' => '7-Zip → github.com/ip7z/7zip/releases → asset 7z2602-x64.msi.',
                ],
                [
                    'id' => 'fixed_url',
                    'name' => 'Fixed URL (direct download)',
                    'summary' => 'Stable .exe / .msi link, including a “latest” URL that redirects to a versioned file (Zoom, Firefox ESR, Adobe, CRAN…).',
                    'how' => [
                        'Set the download URL: it must resolve to a binary, not an HTML download page.',
                        'On sync, the tool follows redirects (HEAD) to expose the final URL (useful when the CDN embeds the version in the path).',
                        'The version is guessed from the file name or final URL (e.g. gimp-3.0.8-setup.exe, …/prod/7.1.5.43453/…).',
                        'Type (MSI vs EXE) is inferred from the extension or keywords (msi-latest, etc.).',
                        'The file is downloaded, validated, then compared by SHA-256 to the last package. If the file name has no version (Chrome MSI, OnlyOffice EXE, some “latest” installers), ProductVersion is read from the binary.',
                    ],
                    'example' => 'GIMP → download.gimp.org/…/gimp-3.0.8-setup.exe ; R → cran.r-project.org/…/R-4.6.1-win.exe.',
                ],
                [
                    'id' => 'manual',
                    'name' => 'Manual upload (no usable URL)',
                    'summary' => 'Authenticated portal, EULA, or binary not available over HTTP (DaVinci Resolve, Adobe CC, ClickShare…).',
                    'how' => [
                        'Upload the .exe / .msi on the record (Info or Sync). It takes priority: no URL download while it remains in place.',
                        'Sync only validates the local file (type, size, hash) and marks it ready for the ZIP.',
                        '“Switch back to URL sync” deletes that server file and re-enables GitHub / fixed URL on the next sync.',
                        'The “latest version” is not discovered automatically: you upload the new installer.',
                    ],
                    'example' => 'DaVinci Resolve: download from the Blackmagic site, then upload on the Sync tab.',
                ],
            ],
            'detection_note' => 'Without a valid local installer (URL sync or upload), software ZIP generation is blocked. For a Dell or HP driver, the import ZIP is built without the pack: PowerShell downloads it on the MECM server. History keeps the source URL, file name, SHA-256 when known, and status.',
            'detection_example_label' => 'Example',
            'cycle_title' => 'Recommended cycle',
            'cycle_lead' => 'Typical flow for one application. Dell / HP drivers follow a shorter cycle (vendor record → check → ZIP pipeline → deploy-drivers.ps1), with no VM. BIOS: editor → .cfg/.ini version → Apply script + vendor tool in a MECM task sequence.',
            'cycle' => [
                ['title' => '1. Catalogue record', 'body' => 'Metadata, URL, icon.'],
                ['title' => '2. Procedure', 'body' => 'Guided wizard or manual steps (install / uninstall / detection).'],
                ['title' => '3. Installer', 'body' => 'URL sync or manual upload when the binary is not downloadable.'],
                ['title' => '4. Pipeline', 'body' => 'Sync → VirusTotal (if enabled) → ZIP → lab VM with confirmation.'],
                ['title' => '5. MECM profile', 'body' => 'Deploy tab: site, DP, collection — then regenerate the ZIP.'],
                ['title' => '6. After the ZIP', 'body' => 'Copy the ZIP to the source share, run deploy.ps1 (MECM app + DP + collection).'],
                ['title' => 'BIOS (parallel)', 'body' => 'Vendor hub → editor → save Bios*-N → download Apply-*.ps1 + tool (CMSL / cctk / Lenovo.BIOS.Config) → task sequence or package.'],
            ],
            'zip_title' => 'After the ZIP: MECM import',
            'zip_lead' => 'MECM Packaging Manager stops at package generation. Adding the result in MECM is done with the script inside the ZIP: deploy.ps1 (applications) or deploy-drivers.ps1 (Dell / HP drivers).',
            'zip_steps' => [
                [
                    'title' => '1. Download the ZIP',
                    'body' => 'From the software Pipeline (or Sync / ZIP) tab, download the latest generated package (after a successful sync / rebuild).',
                ],
                [
                    'title' => '2. Upload to the source share',
                    'body' => 'Copy or extract the ZIP onto the MECM source share — the UNC path that will be the application Content Location.',
                ],
                [
                    'title' => '3. Run deploy.ps1',
                    'body' => 'On a workstation with the MECM console (ConfigurationManager module), open an elevated PowerShell, cd to the package folder on the share, then run: .\deploy.ps1',
                ],
                [
                    'title' => '4. What the script does automatically',
                    'body' => 'Creates or updates the application in MECM; configures Software Center (name + version, description, icon…); distributes content to the Distribution Point (DP); deploys the application to the collection (by Collection ID) with the chosen purpose (available or required).',
                ],
            ],
            'zip_note' => 'Parameters (site, DP, CollectionId, driver shares) come from the Deploy tab / Settings. Check them before running the script. No arguments are required if the profile is already stored in the ZIP.',
            'zip_drivers_title' => 'Dell / HP drivers: after the ZIP',
            'zip_drivers_lead' => 'The driver ZIP does not contain the gigabytes of binaries. deploy-drivers.ps1 downloads them on the MECM console workstation.',
            'zip_drivers_steps' => [
                [
                    'title' => '1. Download the driver ZIP',
                    'body' => 'From the driver record, after a successful pipeline run.',
                ],
                [
                    'title' => '2. Extract on a MECM console workstation',
                    'body' => 'The workstation must reach the site, the DP, and both UNC shares (INF source and Driver Packages) configured in the workshop.',
                ],
                [
                    'title' => '3. Run deploy-drivers.ps1',
                    'body' => 'The script downloads the Dell pack or HP SoftPaq, extracts it (CAB / ZIP / EXE), imports the INFs, verifies SHA-256 when known, then creates the Driver Package on the second UNC and distributes it to the DP.',
                ],
            ],
            'step_form_title' => '“Add a step” form',
            'step_form_lead' => 'On the Procedure tab, this form creates a step. Shared fields:',
            'step_form_fields' => [
                ['name' => 'Type *', 'detail' => 'Chooses the step kind (list below). Controls which fields appear next.'],
                ['name' => 'Label', 'detail' => 'Optional free-text name to spot the step in the list (e.g. “Install MSI x64”).'],
                ['name' => 'On failure', 'detail' => 'stop: abort the procedure/script if the step fails. continue: move on to the next step anyway.'],
                ['name' => 'Enabled', 'detail' => 'Uncheck to keep the step in the procedure without running it (handy for temporary tests).'],
            ],
            'step_types_title' => 'Type menu options',
            'step_types_lead' => 'Each type is a packaging action. What it does and its main fields:',
            'step_types' => [
                [
                    'name' => 'Install command',
                    'code' => 'install_command',
                    'summary' => 'Runs the installer (MSI or EXE) silently.',
                    'fields' => 'Command (file), silent arguments (/qn, /S…), working directory, timeout.',
                ],
                [
                    'name' => 'Uninstall command',
                    'code' => 'uninstall_command',
                    'summary' => 'Uninstalls the app (often msiexec /x for MSI).',
                    'fields' => 'Command, silent arguments, MSI Product Code (GUID) when known.',
                ],
                [
                    'name' => 'Copy file',
                    'code' => 'copy_file',
                    'summary' => 'Copies a file or folder to a destination (prereqs, config, etc.).',
                    'fields' => 'Source, destination, Overwrite option.',
                ],
                [
                    'name' => 'Delete file',
                    'code' => 'delete_file',
                    'summary' => 'Deletes a path (cleanup, old files).',
                    'fields' => 'Path, Recursive option.',
                ],
                [
                    'name' => 'Rename',
                    'code' => 'rename',
                    'summary' => 'Renames a file or folder.',
                    'fields' => 'From, To.',
                ],
                [
                    'name' => 'Create shortcut',
                    'code' => 'create_shortcut',
                    'summary' => 'Creates a Windows shortcut (desktop, Start menu…).',
                    'fields' => 'Target, shortcut path, arguments, icon.',
                ],
                [
                    'name' => 'Registry key',
                    'code' => 'registry',
                    'summary' => 'Creates, updates, or deletes a registry value.',
                    'fields' => 'Hive (HKLM/HKCU…), key, value name/type/data, action set or delete.',
                ],
                [
                    'name' => 'PowerShell script',
                    'code' => 'powershell',
                    'summary' => 'Runs inline PowerShell or a .ps1 file.',
                    'fields' => 'Script (inline), script path, run as (system/user/admin).',
                ],
                [
                    'name' => 'Note / documentation',
                    'code' => 'note',
                    'summary' => 'Team-facing text (no technical action). Ideal for instructions and known pitfalls.',
                    'fields' => 'Free text.',
                ],
            ],
            'procedure_title' => 'Procedures: wizard and example',
            'procedure_lead' => 'For an empty procedure, use the wizard: URL and download first (to learn MSI/EXE), then install / uninstall / detection.',
            'procedure_wizard_title' => 'Procedure wizard (recommended)',
            'procedure_wizard_lead' => 'Procedure tab → “Start wizard”. The URL is asked first; the downloaded file drives the rest.',
            'procedure_wizard_steps' => [
                ['title' => '1. URL', 'body' => 'Paste the .exe/.msi link or the GitHub Releases page, then fetch the installer. Type (MSI/EXE), filename and commands are inferred.'],
                ['title' => '2. Strategy', 'body' => 'MSI → msiexec via install.ps1. EXE → Brave-style scripts. Common silent args pre-filled (/qn, /S, /VERYSILENT…).'],
                ['title' => '3. Install', 'body' => 'Setup filename (e.g. setup.msi), silent arguments, working directory, timeout.'],
                ['title' => '4. Uninstall', 'body' => 'Uninstall command + MSI Product Code when known.'],
                ['title' => '5. Detection', 'body' => 'Success paths (files present after install), gone paths after uninstall, processes to kill; preview of auto-generated MECM witness.'],
                ['title' => '6. Review', 'body' => 'Check then create install + uninstall steps. Optional notes. Re-running the wizard replaces existing install/uninstall steps if confirmed.'],
            ],
            'procedure_example_title' => 'Example: 7-Zip (MSI x64)',
            'procedure_example_context' => 'MSI app, machine-wide install, detection via file witness or Product Code. Manual entry or via the wizard.',
            'procedure_steps' => [
                [
                    'type' => 'note',
                    'title' => 'Note / documentation',
                    'detail' => "Free-text for operators, e.g.: “Official 7-Zip x64 MSI. Prefer MSI over the EXE setup.”",
                ],
                [
                    'type' => 'install_command',
                    'title' => 'Install command',
                    'detail' => "Command: MSI file name (e.g. 7z2602-x64.msi)\nSilent arguments: /qn /norestart\nWorking directory: .\nTimeout: 600",
                ],
                [
                    'type' => 'uninstall_command',
                    'title' => 'Uninstall command',
                    'detail' => "Command: msiexec.exe\nSilent arguments: /x {PRODUCT-CODE} /qn /norestart\nProduct Code: MSI GUID (e.g. {23170F69-40C1-2702-2602-000001000000}) — fill when known; used for MECM detection.",
                ],
                [
                    'type' => 'note',
                    'title' => 'Note — detection / witness',
                    'detail' => "Document the expected witness after install, e.g. C:\\Program Files\\7-Zip\\7z.exe, or the package log C:\\SCCM_Temoins\\7Zip\\7zip.<version>.log.",
                ],
            ],
            'procedure_tips_title' => 'Best practices',
            'procedure_tips' => [
                'Empty procedure: start with “Start wizard” rather than adding steps one by one.',
                'A procedure is the operational source of truth — update it when silent switches change.',
                'For EXE installers, put vendor switches (/S, /quiet, /VERYSILENT…) in Silent arguments.',
                'Uninstall path without quotes: %ProgramFiles%\\Microsoft VS Code\\unins000.exe (quoted paths used to crash the script, exit 1603).',
                'No .exe from the URL? Upload the installer manually (Info / Sync) before running the pipeline.',
                'If set, the MSI Product Code is reused by deploy.ps1 for detection.',
                'After editing a procedure or the MECM profile, regenerate the ZIP, then re-run deploy.ps1 from the source share.',
            ],
        ];
    }
}
