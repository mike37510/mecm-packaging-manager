<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bilingual copy for the public /welcome presentation.
 * Concepts only — no file formats, hashes, or ops jargon.
 *
 * @phpstan-type WelcomeCopy array{
 *   page_title: string,
 *   meta_description: string,
 *   lang_label: string,
 *   other_lang: string,
 *   other_lang_href: string,
 *   other_lang_code: string,
 *   theme_label: string,
 *   cta_login: string,
 *   cta_modules: string,
 *   cta_github: string,
 *   github_href: string,
 *   install_title: string,
 *   install_body: string,
 *   version_kicker: string,
 *   headline: string,
 *   lead: string,
 *   purpose_title: string,
 *   purpose_body: string,
 *   modules_title: string,
 *   modules_lead: string,
 *   modules: list<array{name: string, summary: string}>,
 *   cycle_title: string,
 *   cycle_lead: string,
 *   cycles: list<array{
 *     title: string,
 *     lead: string,
 *     image: string,
 *     image_alt: string,
 *     steps: list<array{title: string, body: string}>
 *   }>,
 *   close_title: string,
 *   close_body: string,
 *   demo_title: string,
 *   demo_lead: string,
 *   demo_url: string,
 *   demo_url_label: string,
 *   demo_email_label: string,
 *   demo_password_label: string,
 *   demo_email: string,
 *   demo_password: string,
 *   demo_note: string,
 *   cta_demo: string
 * }
 */
final class WelcomeContent
{
    public const GITHUB_URL = 'https://github.com/mike37510/mecm-packaging-manager';
    public const DEMO_URL = 'https://sccm-automation.borninthe80s.fr/';
    public const DEMO_EMAIL = 'admin@mecmpackagingmanager.com';

    /**
     * @return WelcomeCopy
     */
    public static function forLocale(string $locale): array
    {
        $locale = strtolower($locale) === 'en' ? 'en' : 'fr';

        return $locale === 'en' ? self::en() : self::fr();
    }

    /**
     * @return WelcomeCopy
     */
    private static function fr(): array
    {
        return [
            'page_title' => 'Présentation',
            'meta_description' => 'MECM Packaging Manager 4.5 — catalogue logiciels et pilotes, hubs BIOS, contrôle de sécurité, packages testés en lab, rappels e-mail, publication dans le Centre logiciel.',
            'lang_label' => 'FR',
            'other_lang' => 'English',
            'other_lang_href' => '/welcome/en',
            'other_lang_code' => 'en',
            'theme_label' => 'Basculer le mode sombre',
            'cta_login' => 'Se connecter',
            'cta_modules' => 'Voir les modules',
            'cta_github' => 'Installer chez soi (GitHub)',
            'github_href' => self::GITHUB_URL,
            'install_title' => 'Installer sur votre site',
            'install_body' => 'Le projet est public : vous pouvez l’installer chez vous. Le dépôt GitHub explique comment démarrer et comment le relier à votre atelier MECM.',
            'version_kicker' => 'Version 4.5',
            'headline' => 'Un outil unique pour packager logiciels, pilotes et BIOS sous MECM.',
            'lead' => 'Une seule chaîne : catalogue, contrôle de sécurité, tests en lab, configs BIOS constructeur, puis publication dans Configuration Manager — logiciels, pilotes et paramètres firmware.',
            'purpose_title' => 'À quoi ça sert',
            'purpose_body' => 'Rassembler ce qui était éparpillé : catalogues, recettes d’installation silencieuse, packs pilotes, configs BIOS HP / Dell / Lenovo, contrôle de sécurité, packages prêts pour MECM, preuve de test en lab, rappels de renouvellement, et publication dans le Centre logiciel. Une seule chaîne, suivie, pour les équipes packaging.',
            'modules_title' => 'Les modules',
            'modules_lead' => 'Chaque module a un rôle clair. Ensemble, ils couvrent le cycle : catalogue → contrôle → package → validation → déploiement (et BIOS en parallèle).',
            'modules' => [
                [
                    'name' => 'Catalogue logiciels',
                    'summary' => 'L’inventaire des applications à packager : éditeur, version synchro vs packagée, icône Centre logiciel, et le lien source. Un rappel (écran + e-mail) indique quand une fiche mérite d’être revue.',
                ],
                [
                    'name' => 'Packs pilotes',
                    'summary' => 'Un catalogue (bêta) pour suivre les Driver Packages Dell / HP et détecter une nouvelle révision.',
                ],
                [
                    'name' => 'BIOS',
                    'summary' => 'Hubs HP, Dell et Lenovo (bêta) : éditeur de config, versions enregistrées, scripts PowerShell adaptés à chaque constructeur.',
                ],
                [
                    'name' => 'Procédures',
                    'summary' => 'La recette : installer en silence, désinstaller, et reconnaître que le logiciel est bien là.',
                ],
                [
                    'name' => 'Synchronisation',
                    'summary' => 'Vérifier s’il existe une nouvelle version. Quatre états : à jour, nouvelle version, contrôle impossible, jamais vérifié.',
                ],
                [
                    'name' => 'Contrôle de sécurité',
                    'summary' => 'Analyser la source avant le package. En cas de doute, l’opérateur décide.',
                ],
                [
                    'name' => 'Packages',
                    'summary' => 'Produire le package prêt pour MECM : application ou Driver Package.',
                ],
                [
                    'name' => 'Laboratoire VM',
                    'summary' => 'Tester l’installation sur une machine Windows, puis valider à l’écran.',
                ],
                [
                    'name' => 'Déploiement MECM',
                    'summary' => 'Publier dans Configuration Manager : application Centre logiciel, ou Driver Package, puis distribution.',
                ],
                [
                    'name' => 'Gouvernance',
                    'summary' => 'Connexion, rôles, journal d’activité, mailing SMTP et rappels de renouvellement. Le mot de passe admin se change dans l’interface.',
                ],
            ],
            'cycle_title' => 'Un cycle typique',
            'cycle_lead' => 'Même outil pour les logiciels, les packs pilotes, et les configs BIOS (hubs constructeur).',
            'cycles' => [
                [
                    'title' => 'Logiciels',
                    'lead' => 'Du catalogue au Centre logiciel, avec un test réel en lab.',
                    'image' => '/assets/brand/sccm-packaging-process.png',
                    'image_alt' => 'Cycle logiciels MECM : catalogue, fiche, synchronisation, package ZIP, lab VM, validation, Centre logiciel',
                    'steps' => [
                        [
                            'title' => 'Catalogue',
                            'body' => 'Voir ce qui est à jour, ce qui a une nouvelle version, et ce qui attend une revue.',
                        ],
                        [
                            'title' => 'Fiche logiciel',
                            'body' => 'Créer ou ouvrir la fiche, puis lancer l’assistant recette s’il n’y en a pas encore.',
                        ],
                        [
                            'title' => 'Synchronisation',
                            'body' => 'Relire l’éditeur et comparer au dernier package.',
                        ],
                        [
                            'title' => 'Package ZIP',
                            'body' => 'Produire l’archive d’installation et de désinstallation silencieuses.',
                        ],
                        [
                            'title' => 'Lab VM',
                            'body' => 'Installer le package sur la machine de test, avec le suivi en direct.',
                        ],
                        [
                            'title' => 'Validation',
                            'body' => 'Confirmer à l’écran que l’installation silencieuse est correcte.',
                        ],
                        [
                            'title' => 'MECM',
                            'body' => 'Publier l’application dans le Centre logiciel, puis la distribuer.',
                        ],
                    ],
                ],
                [
                    'title' => 'Packs pilotes',
                    'lead' => 'Du catalogue au Driver Package.',
                    'image' => '/assets/brand/mecm-driver-packaging-process.png',
                    'image_alt' => 'Cycle pilotes MECM : catalogue, fiche pack, vérification, contrôle, package, Driver Package',
                    'steps' => [
                        [
                            'title' => 'Catalogue',
                            'body' => 'Suivre les packs à jour, les nouvelles révisions, et ceux qui n’ont jamais été vérifiés.',
                        ],
                        [
                            'title' => 'Fiche pack',
                            'body' => 'Créer ou ouvrir la fiche du pack.',
                        ],
                        [
                            'title' => 'Vérification',
                            'body' => 'Savoir s’il existe une nouvelle révision.',
                        ],
                        [
                            'title' => 'Contrôle',
                            'body' => 'Contrôler la source avant le package.',
                        ],
                        [
                            'title' => 'Package',
                            'body' => 'Préparer le package d’import MECM.',
                        ],
                        [
                            'title' => 'MECM',
                            'body' => 'Publier le Driver Package, puis le distribuer.',
                        ],
                    ],
                ],
            ],
            'close_title' => 'Prêt à industrialiser le packaging ?',
            'close_body' => 'Logiciels, pilotes, contrôle de sécurité, lab et MECM dans le même outil. Installez votre instance depuis GitHub.',
            'demo_title' => 'Essayer la démo en ligne',
            'demo_lead' => 'Une instance publique pour parcourir le catalogue, les procédures et le pipeline — sans rien installer.',
            'demo_url' => self::DEMO_URL,
            'demo_url_label' => 'Adresse',
            'demo_email_label' => 'Identifiant',
            'demo_password_label' => 'Mot de passe',
            'demo_email' => self::DEMO_EMAIL,
            'demo_password' => self::DEMO_EMAIL,
            'demo_note' => 'Compte de démonstration partagé : usage découverte uniquement.',
            'cta_demo' => 'Ouvrir la démo',
        ];
    }

    /**
     * @return WelcomeCopy
     */
    private static function en(): array
    {
        return [
            'page_title' => 'Overview',
            'meta_description' => 'MECM Packaging Manager 4.5 — software and driver catalogues, BIOS hubs, security checks, lab-tested packages, renewal e-mail, Software Center publication.',
            'lang_label' => 'EN',
            'other_lang' => 'Français',
            'other_lang_href' => '/welcome',
            'other_lang_code' => 'fr',
            'theme_label' => 'Toggle dark mode',
            'cta_login' => 'Sign in',
            'cta_modules' => 'Browse modules',
            'cta_github' => 'Self-host from GitHub',
            'github_href' => self::GITHUB_URL,
            'install_title' => 'Install it on your site',
            'install_body' => 'The project is public: you can run it on your own site. The GitHub repository explains how to get started and how to connect it to your MECM workshop.',
            'version_kicker' => 'Version 4.5',
            'headline' => 'One tool to package software, drivers, and BIOS with MECM.',
            'lead' => 'One chain: catalogue, security check, lab tests, vendor BIOS configs, then publication in Configuration Manager — software, drivers, and firmware settings.',
            'purpose_title' => 'What it is for',
            'purpose_body' => 'Bring together what used to be scattered: catalogues, silent-install recipes, driver packs, HP / Dell / Lenovo BIOS configs, security checks, MECM-ready packages, proof of a lab test, renewal reminders, and Software Center publication. One followed chain for packaging teams.',
            'modules_title' => 'Modules',
            'modules_lead' => 'Each module has a clear job. Together they cover the cycle: catalogue → check → package → validation → deployment (and BIOS in parallel).',
            'modules' => [
                [
                    'name' => 'Software catalogue',
                    'summary' => 'The inventory of apps to package: publisher, synced vs packaged version, Software Center icon, and the source link. A reminder (UI + e-mail) shows when a record is due for review.',
                ],
                [
                    'name' => 'Driver packs',
                    'summary' => 'A catalogue (beta) to track Dell / HP Driver Packages and detect a new revision.',
                ],
                [
                    'name' => 'BIOS',
                    'summary' => 'HP, Dell, and Lenovo hubs (beta): config editor, saved versions, PowerShell scripts tailored to each vendor.',
                ],
                [
                    'name' => 'Procedures',
                    'summary' => 'The recipe: install silently, uninstall, and recognise that the app is present.',
                ],
                [
                    'name' => 'Synchronization',
                    'summary' => 'Check whether a new version exists. Four states: up to date, new version, check failed, never checked.',
                ],
                [
                    'name' => 'Security check',
                    'summary' => 'Scan the source before packaging. If something looks wrong, the operator decides.',
                ],
                [
                    'name' => 'Packages',
                    'summary' => 'Build the package ready for MECM: an application or a Driver Package.',
                ],
                [
                    'name' => 'Lab VM',
                    'summary' => 'Test the install on a Windows machine, then confirm on screen.',
                ],
                [
                    'name' => 'MECM deployment',
                    'summary' => 'Publish in Configuration Manager: a Software Center application, or a Driver Package, then distribution.',
                ],
                [
                    'name' => 'Governance',
                    'summary' => 'Sign-in, roles, activity log, SMTP mailing and renewal reminders. The admin password is changed in the interface.',
                ],
            ],
            'cycle_title' => 'A typical cycle',
            'cycle_lead' => 'One tool for software, driver packs, and BIOS configs (vendor hubs).',
            'cycles' => [
                [
                    'title' => 'Software',
                    'lead' => 'From the catalogue to Software Center, with a real lab test.',
                    'image' => '/assets/brand/sccm-packaging-process.png',
                    'image_alt' => 'MECM software cycle: catalogue, record, synchronization, ZIP package, lab VM, validation, Software Center',
                    'steps' => [
                        [
                            'title' => 'Catalogue',
                            'body' => 'See what is up to date, what has a new version, and what is due for review.',
                        ],
                        [
                            'title' => 'Software record',
                            'body' => 'Create or open the record, then start the recipe wizard if there is none yet.',
                        ],
                        [
                            'title' => 'Synchronization',
                            'body' => 'Re-read the vendor and compare it to the last package.',
                        ],
                        [
                            'title' => 'ZIP package',
                            'body' => 'Build the silent install and uninstall archive.',
                        ],
                        [
                            'title' => 'Lab VM',
                            'body' => 'Install the package on the test machine, with live progress.',
                        ],
                        [
                            'title' => 'Validation',
                            'body' => 'Confirm on screen that the silent install looks right.',
                        ],
                        [
                            'title' => 'MECM',
                            'body' => 'Publish the application in Software Center, then distribute it.',
                        ],
                    ],
                ],
                [
                    'title' => 'Driver packs',
                    'lead' => 'From the catalogue to a Driver Package.',
                    'image' => '/assets/brand/mecm-driver-packaging-process.png',
                    'image_alt' => 'MECM driver cycle: catalogue, pack record, check, security scan, package, Driver Package',
                    'steps' => [
                        [
                            'title' => 'Catalogue',
                            'body' => 'Track packs that are up to date, new revisions, and those never checked.',
                        ],
                        [
                            'title' => 'Pack record',
                            'body' => 'Create or open the pack record.',
                        ],
                        [
                            'title' => 'Check',
                            'body' => 'See whether a new revision exists.',
                        ],
                        [
                            'title' => 'Security check',
                            'body' => 'Check the source before packaging.',
                        ],
                        [
                            'title' => 'Package',
                            'body' => 'Prepare the MECM import package.',
                        ],
                        [
                            'title' => 'MECM',
                            'body' => 'Publish the Driver Package, then distribute it.',
                        ],
                    ],
                ],
            ],
            'close_title' => 'Ready to industrialize packaging?',
            'close_body' => 'Software, drivers, security checks, lab, and MECM in one tool. Install your instance from GitHub.',
            'demo_title' => 'Try the live demo',
            'demo_lead' => 'A public instance to browse the catalogue, procedures, and pipeline — no install required.',
            'demo_url' => self::DEMO_URL,
            'demo_url_label' => 'URL',
            'demo_email_label' => 'Sign-in',
            'demo_password_label' => 'Password',
            'demo_email' => self::DEMO_EMAIL,
            'demo_password' => self::DEMO_EMAIL,
            'demo_note' => 'Shared demo account — for exploration only.',
            'cta_demo' => 'Open the demo',
        ];
    }
}
