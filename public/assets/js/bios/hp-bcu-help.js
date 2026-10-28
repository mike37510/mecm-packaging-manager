window.BCU_HELP = {
    "ME Firmware Mode": {
        "short": "Active ou non le firmware Intel ME (Management Engine).",
        "detail": "Le Management Engine (ME) est un microcontrôleur Intel indépendant de Windows. Il sert notamment à AMT/vPro, à certaines fonctions de sécurité et de mise à jour.\n\n• Enable : ME actif (requis pour AMT et beaucoup de fonctions entreprise).\n• Disable : rarement utile en production ; peut casser la gestion hors bande.\n\nRecommandation parc : Enable.",
        "values": [
            "Enable"
        ]
    },
    "Custom Logo": {
        "short": "Indique si un logo personnalisé est présent au démarrage.",
        "detail": "Affiche ou enregistre l’état d’un logo custom affiché pendant le POST BIOS.\n\nSouvent géré via un outil HP dédié (pas seulement ce champ). En déploiement, laissez tel quel sauf politique graphique d’entreprise.",
        "values": [
            "Not Present",
            "Present"
        ]
    },
    "Asset Tracking Number": {
        "short": "Numéro d’inventaire / asset tag affiché dans le BIOS.",
        "detail": "Champ libre stocké dans le firmware (souvent aussi dans SMBIOS).\n\nUtile pour coller le numéro d’inventaire IT sur la machine. Visible dans le setup BIOS et parfois dans les inventaires WMI.\n\nExemple : UT-PC-00421",
        "values": [
            ""
        ]
    },
    "Ownership Tag": {
        "short": "Texte « propriétaire » affiché au boot / dans le BIOS.",
        "detail": "Message libre (ex. « Université de Tours — ne pas déplacer »).\n\nSert à identifier le propriétaire ou une consigne. Visible au démarrage selon modèles. Pas un contrôle de sécurité : purement informatif.",
        "values": [
            ""
        ]
    },
    "BIOS Update Credential Policy": {
        "short": "Quand le mot de passe Admin est exigé pour mettre à jour le BIOS.",
        "detail": "Contrôle si une mise à jour BIOS (outil HP, SoftPaq, etc.) demande le mot de passe Setup.\n\n• Never Require Credentials : plus permissif, plus risqué.\n• Require Credentials on Downgrade Only : bon compromis — bloque les downgrades sans MDP.\n• Always Require Credentials : le plus strict.\n\nRecommandation : Require Credentials on Downgrade Only ou Always.",
        "values": [
            "Never Require Credentials",
            "Require Credentials on Downgrade Only",
            "Always Require Credentials"
        ]
    },
    "Lock BIOS Version": {
        "short": "Empêche toute mise à jour / changement de version BIOS.",
        "detail": "Si Enable, le firmware refuse les mises à jour BIOS.\n\nUtile pour figer une version validée. Attention : bloque aussi les correctifs de sécurité firmware.\n\nRecommandation : Disable sauf gel temporaire validé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Native OS Firmware Update Service": {
        "short": "Autorise Windows (Windows Update / Update Capsule) à mettre à jour le firmware.",
        "detail": "Active le service de mise à jour firmware « native » côté OS (UEFI capsules).\n\n• Enable : Windows peut proposer des updates BIOS/firmware.\n• Disable : mises à jour uniquement via outils HP / SoftPaq / CMSL.\n\nEn entreprise contrôlée via SoftPaq/MECM, Disable est fréquent.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "BIOS Rollback Policy": {
        "short": "Autorise ou restreint le retour à une version BIOS plus ancienne.",
        "detail": "• Unrestricted Rollback : on peut rétrograder librement.\n• Restricted Rollback : downgrade limité (souvent lié à Minimum BIOS Version).\n\nRecommandation sécurité : Restricted, pour éviter qu’un malware ou une erreur ne réinstalle un vieux BIOS vulnérable.",
        "values": [
            "Unrestricted Rollback to older BIOS",
            "Restricted Rollback to older BIOS"
        ]
    },
    "Minimum BIOS Version": {
        "short": "Version BIOS minimale acceptée (politique de rollback).",
        "detail": "Format type 01.05.00. Utilisé avec une politique de rollback restreinte : on ne peut pas descendre sous ce plancher.\n\nRenseignez la version minimale validée par votre parc.",
        "values": [
            "00.00.00"
        ]
    },
    "Clear BIOS Event Log": {
        "short": "Efface le journal d’événements du BIOS au prochain boot.",
        "detail": "Le log BIOS enregistre des événements firmware (Sure Start, sécurité, etc.).\n\n• Clear : purge au prochain démarrage.\n• Don't Clear : conserve l’historique.\n\nÀ utiliser ponctuellement, pas en déploiement massif permanent.",
        "values": [
            "Don't Clear",
            "Clear"
        ]
    },
    "Manufacturing Programming Mode": {
        "short": "Mode programmation usine. Doit rester verrouillé en production.",
        "detail": "Unlock ouvre des fonctions réservées à la fabrication.\n\nEn production / parc utilisateur : toujours Lock. Unlock uniquement en atelier HP.",
        "values": [
            "Unlock",
            "Lock"
        ]
    },
    "Password Minimum Length": {
        "short": "Longueur minimale des mots de passe Admin et Power-on.",
        "detail": "Impose un nombre minimum de caractères pour les MDP BIOS.\n\nSouvent ≥ 8. Alignez avec votre politique de sécurité. Les scripts Apply-HPBiosConfig génèrent des MDP conformes si aléatoires.",
        "values": [
            "8"
        ]
    },
    "At least one symbol is required in Administrator and User passwords": {
        "short": "Exige au moins un caractère spécial dans les MDP BIOS.",
        "detail": "Yes = complexité renforcée (symboles). No = pas d’obligation de symbole.\n\nRecommandation entreprise : Yes.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "At least one number is required in Administrator and User passwords": {
        "short": "Exige au moins un chiffre dans les MDP BIOS.",
        "detail": "Yes = au moins un chiffre obligatoire. Recommandation : Yes.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "At least one upper case character is required in Administrator and User passwords": {
        "short": "Exige au moins une majuscule dans les MDP BIOS.",
        "detail": "Yes = majuscule obligatoire. Recommandation : Yes.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "At least one lower case character is required in Administrator and User passwords": {
        "short": "Exige au moins une minuscule dans les MDP BIOS.",
        "detail": "Yes = minuscule obligatoire. Recommandation : Yes.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "Are spaces allowed in Administrator and User passwords?": {
        "short": "Autorise les espaces dans les MDP BIOS.",
        "detail": "Yes peut compliquer la saisie et les scripts. Recommandation parc : No.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "Allow User to Modify Power-on Password": {
        "short": "Droits de l’utilisateur sur le mot de passe Power-on.",
        "detail": "• No : seul l’Admin BIOS gère le Power-on password.\n• Change Only / Change or Delete : l’utilisateur peut modifier (voire supprimer) le MDP d’allumage.\n\nRecommandation entreprise : No.",
        "values": [
            "No",
            "Change Only",
            "Change or Delete"
        ]
    },
    "Wake on LAN Power-on Password Policy": {
        "short": "Exiger ou non le MDP Power-on lors d’un réveil réseau (WOL).",
        "detail": "• Bypass Password : WOL sans saisir le MDP (pratique pour déploiement).\n• Require Password : plus strict, peut bloquer le boot réseau automatisé.\n\nPour MECM/WOL : souvent Bypass.",
        "values": [
            "Bypass Password",
            "Require Password"
        ]
    },
    "Prompt for Admin authentication on F9 (Boot Menu)": {
        "short": "Demande le MDP Admin pour ouvrir le menu de boot (F9).",
        "detail": "Empêche un utilisateur de changer le périphérique de boot sans Admin.\n\nRecommandation sécurité : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Prompt for Admin authentication on F11 (System Recovery)": {
        "short": "Demande le MDP Admin pour F11 (récupération).",
        "detail": "Protège l’accès à la récupération système. Recommandation : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Prompt for Admin authentication on F12 (Network Boot)": {
        "short": "Demande le MDP Admin pour F12 (boot PXE).",
        "detail": "Protège le boot réseau. En atelier de déploiement, Disable peut être nécessaire temporairement ; en production : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "BIOS Administrator visible at Power-on Authentication": {
        "short": "Affiche l’option Admin à l’écran d’auth au démarrage.",
        "detail": "Si un Power-on password est actif, contrôle si le compte Admin apparaît comme choix. Utile pour support ; à évaluer selon politique.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Prompt for Power-on Password on OS Restart": {
        "short": "Redemande le MDP Power-on après un redémarrage Windows.",
        "detail": "Enable = authentification à chaque reboot OS (plus sûr, plus pénible). Disable = souvent préféré en entreprise.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "TPM Device": {
        "short": "Rend le TPM visible (Available) ou masqué (Hidden).",
        "detail": "Pour BitLocker et Windows 11 : Available.\n\nHidden masque le TPM à l’OS — à éviter sauf cas très spécifiques.",
        "values": [
            "Hidden",
            "Available"
        ]
    },
    "TPM State": {
        "short": "Active le module TPM.",
        "detail": "Enable requis pour BitLocker, Credential Guard, Windows 11, attestation.\n\nRecommandation : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Clear TPM": {
        "short": "Efface le TPM au prochain boot (réinitialise les clés).",
        "detail": "ATTENTION : invalide BitLocker / clés stockées dans le TPM.\n\nNe jamais laisser « On next boot » en déploiement de masse sans procédure de récupération.",
        "values": [
            "No",
            "On next boot"
        ]
    },
    "TPM Activation Policy": {
        "short": "Comportement / prompts lors de l’activation TPM.",
        "detail": "Contrôle si l’utilisateur doit confirmer (F1, rejet, etc.).\n\nEn déploiement silencieux : préférer No prompts si disponible.",
        "values": [
            "F1 to Boot",
            "Allow user to reject",
            "No prompts"
        ]
    },
    "Verify Boot Block on every boot": {
        "short": "Vérifie l’intégrité du bloc de boot à chaque démarrage (Sure Start).",
        "detail": "Détecte une altération du BIOS boot block. Recommandation : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Dynamic Runtime Scanning of Boot Block": {
        "short": "Analyse runtime du boot block (Sure Start).",
        "detail": "Surveillance continue en plus du check au boot. Recommandation : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Sure Start BIOS Settings Protection": {
        "short": "Protège les paramètres BIOS contre les modifications non autorisées.",
        "detail": "Sure Start peut restaurer des settings si altérés. Recommandation : Enable en entreprise.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Sure Start Secure Boot Keys Protection": {
        "short": "Protège les clés Secure Boot via Sure Start.",
        "detail": "Empêche le remplacement furtif des clés. Recommandation : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Enhanced HP Firmware Runtime Intrusion Prevention and Detection": {
        "short": "Détection / prévention d’intrusion firmware en runtime.",
        "detail": "Couche Sure Start avancée. Recommandation : Enable sauf incompatibilité connue.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Sure Start Security Event Boot Notification": {
        "short": "Notification au boot après un événement Sure Start.",
        "detail": "• Time out : message puis continue.\n• Require Acknowledgment : l’utilisateur doit confirmer.\n\nAtelier : Time out ; haute sécurité : Require Acknowledgment.",
        "values": [
            "Time out after 15 seconds",
            "Require Acknowledgment"
        ]
    },
    "Sure Start Security Event Policy": {
        "short": "Action si événement sécurité Sure Start.",
        "detail": "• Log only : journalise.\n• Notify user : informe.\n• Power off : coupe (très strict).\n\nÉquilibrez sécurité et support terrain.",
        "values": [
            "Log Event Only",
            "Log Event and notify user",
            "Log Event and power off system"
        ]
    },
    "Secure Boot": {
        "short": "Valide les composants de boot par signature cryptographique.",
        "detail": "Socle de sécurité UEFI. Requis pour Credential Guard / Device Guard et fortement recommandé Windows 11.\n\nRecommandation : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Import Custom Secure Boot keys": {
        "short": "Importe des clés Secure Boot personnalisées au prochain boot.",
        "detail": "Pour environnements avec PK/KEK/db custom. « Do Nothing » en usage standard Microsoft.",
        "values": [
            "Do Nothing",
            "On next boot"
        ]
    },
    "Clear Secure Boot keys": {
        "short": "Efface les clés Secure Boot (mode setup).",
        "detail": "Dangereux : peut empêcher de booter Windows tant que les clés ne sont pas réinstallées.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Reset Secure Boot keys to factory defaults": {
        "short": "Remet les clés Secure Boot HP / Microsoft d’usine.",
        "detail": "Utile après tests de clés custom. Enable une fois puis remettre Disable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Enable MS UEFI CA key": {
        "short": "Active la clé CA UEFI Microsoft (compatibilité boot Windows / 3rd party).",
        "detail": "Yes recommandé pour Windows et beaucoup de bootloaders signés Microsoft.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "Ready to disable MS UEFI CA Key": {
        "short": "Prépare la désactivation de la CA Microsoft.",
        "detail": "Étape préalable avant retrait de la clé MS. Usage avancé uniquement.",
        "values": [
            "Not Ready",
            "Ready"
        ]
    },
    "Custom Keys Image Verification State": {
        "short": "État de vérification de l’image de clés custom.",
        "detail": "Indicateur / état lié à l’import de clés personnalisées. Rarement modifié manuellement.",
        "values": [
            "No Custom Keys",
            "Fail",
            "Success"
        ]
    },
    "Ready BIOS for Device Guard Use": {
        "short": "Prépare le BIOS pour Device Guard / Credential Guard.",
        "detail": "Configure les prérequis firmware (VTx, VTd, Secure Boot, etc.) au prochain boot.\n\n« Configure on Next Boot » pour activer ; « Clear… » pour annuler.",
        "values": [
            "Do Nothing",
            "Configure on Next Boot",
            "Clear Configuration on Next Boot"
        ]
    },
    "Remote Device Management": {
        "short": "Active la gestion à distance des appareils (fonctions HP).",
        "detail": "Active des capacités de remote management HP. Évaluez selon politique (AMT vs cloud HP).",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Permanently Disable Remote Device Management (Set Once)": {
        "short": "Désactive définitivement la gestion à distance (irréversible).",
        "detail": "Set Once : Yes est souvent permanent. À n’utiliser qu’avec une décision claire.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "Permanently Disable HP Sure Run (Set Once)": {
        "short": "Désactive définitivement HP Sure Run.",
        "detail": "Irréversible. Sure Run surveille l’intégrité de process critiques. Ne pas activer Yes sans validation.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "HP Cloud Managed": {
        "short": "Active la gestion cloud HP.",
        "detail": "Lie le poste à des services cloud HP. Disable si vous n’utilisez pas HP cloud.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Permanently Disable HP Cloud Management (Set Once)": {
        "short": "Désactive définitivement la gestion cloud HP.",
        "detail": "Action souvent irréversible. Oui seulement si politique « no cloud HP ».",
        "values": [
            "No",
            "Yes"
        ]
    },
    "Enhanced BIOS Authentication Mode": {
        "short": "Mode d’authentification BIOS renforcé (clés / anti-rejeu).",
        "detail": "Remplace ou complète le classique MDP Setup par un mécanisme à clés. Usage avancé / Sure Admin.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Physical Presence Interface": {
        "short": "Exige une présence physique pour certaines actions TPM.",
        "detail": "Ancienne exigence TCG. Disable facilite l’admin distant ; Enable renforce la sécurité physique.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Cover Removal Sensor": {
        "short": "Réaction à l’ouverture du capot / châssis.",
        "detail": "• Disable : ignore.\n• Notify user : alerte au boot.\n\nUtile contre l’ouverture physique non autorisée.",
        "values": [
            "Disable",
            "Notify user"
        ]
    },
    "Power Off Upon Cover Removal": {
        "short": "Éteint le PC si le capot est ouvert.",
        "detail": "Mesure anti-intrusion physique. Peut gêner la maintenance atelier.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Clear TPM on boot after cover removal": {
        "short": "Efface le TPM après détection d’ouverture de capot.",
        "detail": "Très agressif : détruit les secrets TPM. Réservé aux contextes haute sécurité.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Save/Restore GPT of System Hard Drive": {
        "short": "Sauvegarde / restaure la table de partitions GPT du disque système.",
        "detail": "Protection Sure Start contre corruption de la GPT. Recommandation : Enabled.",
        "values": [
            "Disabled",
            "Enabled"
        ]
    },
    "Allow OPAL Hard Drive SID Authentication": {
        "short": "Autorise l’auth SID des disques auto-chiffrants OPAL.",
        "detail": "Lié aux SED (Self-Encrypting Drives). Enable si vous gérez le SID OPAL ; sinon laissez selon défaut HP.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Permanent Disable Absolute Persistence Module Set Once": {
        "short": "Désactive définitivement Absolute (Computrace).",
        "detail": "Module antivol Absolute. Set Once = souvent irréversible. Décision légale / politique flotte.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "System Management Command": {
        "short": "Autorise certaines commandes de gestion système via firmware.",
        "detail": "Nécessaire à des outils de manageability. Disable seulement si durcissement extrême.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "SureStart Production Mode": {
        "short": "Mode production Sure Start.",
        "detail": "Active le comportement « production » de Sure Start. En général Enable sur parc déployé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Boot Sector (MBR/GPT) Recovery Policy": {
        "short": "Politique de récupération du secteur de boot / GPT.",
        "detail": "• Local user control : l’utilisateur décide.\n• Recover in event of corruption : restauration auto.\n\nEntreprise : Recover… souvent préférable.",
        "values": [
            "Local user control",
            "Recover in event of corruption"
        ]
    },
    "Virtualization Based BIOS Protection": {
        "short": "Protection BIOS basée sur la virtualisation.",
        "detail": "Isole des composants firmware via virt. Renforce la sécurité ; vérifier compatibilité.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Virtualization Based BIOS Protection Manual Recovery": {
        "short": "Autorise une récupération manuelle de la protection BIOS virtualisée.",
        "detail": "Filet de secours si la protection VBBP bloque. Enable pour faciliter le support.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Thunderbolt Option ROM": {
        "short": "Charge l’Option ROM Thunderbolt (boot / périphériques précoces).",
        "detail": "Enable si boot ou init Thunderbolt avant l’OS est nécessaire. Disable réduit la surface d’attaque pré-OS.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Host Based MAC Address": {
        "short": "Utilise une adresse MAC gérée par l’hôte (HBMA) plutôt que celle du NIC.",
        "detail": "• Disable : MAC matérielle du NIC.\n• System / Custom : MAC logique pour suivi réseau.\n\nUtile pour standardiser la MAC en entreprise.",
        "values": [
            "Disable",
            "System",
            "Custom"
        ]
    },
    "HBMA Custom MAC Address": {
        "short": "Adresse MAC personnalisée si HBMA = Custom.",
        "detail": "Format 00-11-22-33-44-55. Doit être unique sur le réseau.",
        "values": [
            "00-00-00-00-00-00"
        ]
    },
    "Pre-boot HBMA Support": {
        "short": "Applique HBMA dès le pré-boot (PXE, etc.).",
        "detail": "Important si le boot PXE doit utiliser la MAC logique.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Windows HBMA Support": {
        "short": "Maintient HBMA sous Windows.",
        "detail": "Enable pour cohérence MAC OS / pré-boot.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Single NIC Operation (Disable All Other NICs when HBMA is active on one NIC)": {
        "short": "Désactive les autres NIC quand HBMA est actif sur une interface.",
        "detail": "Évite les conflits multi-NIC. Utile docks / multi-réseau.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "HBMA Priority List": {
        "short": "Ordre de priorité des interfaces pour HBMA.",
        "detail": "Liste des NIC / docks candidats. À adapter selon matériel du parc.",
        "values": [
            "Embedded NIC:Realtek RTL8111HSH :10EC_8168:103C_8536(Disabled)",
            "Embedded NIC:Intel (Non-vPro) :8086_15FC:103C_87EA(Disabled)",
            "Embedded NIC:Intel (vPro) :8086_15FC:103C_87ED(Disabled)",
            "USB NIC Dongle:HP External Adapter :0BDA_8153",
            "USB NIC Dongle:HP USB-C Travel Dock :17E9_4352",
            "USB NIC Dongle:HP USB Travel Dock :17E9_4351",
            "USB NIC Dongle:HP 3005pr :17E9_430A",
            "USB NIC Dongle:HP Universal pr :17E9_4327",
            "Thunderbolt Dock:HP Elite Dock :14E4_1682:14E4_1682",
            "Thunderbolt Dock:HP Elite Hook 20 :8086_5502:103C_87B9"
        ]
    },
    "Select Language": {
        "short": "Langue du menu / setup BIOS.",
        "detail": "N’affecte pas Windows. Choisissez la langue des techniciens support.",
        "values": [
            "English",
            "Deutsch",
            "Espanol",
            "Italiano",
            "Francais",
            "Japanese",
            "Portugues",
            "Dansk",
            "Svenska",
            "Nederlands",
            "Norsk",
            "Suomi",
            "Simplified Chinese",
            "Traditional Chinese",
            "Russian"
        ]
    },
    "Select Keyboard Layout": {
        "short": "Disposition clavier dans le setup BIOS.",
        "detail": "Important pour saisir correctement les MDP BIOS (AZERTY vs QWERTY).",
        "values": [
            "English",
            "Deutsch",
            "Espanol",
            "Italiano",
            "Francais",
            "Japanese",
            "Portugues",
            "Dansk",
            "Svenska",
            "Nederlands",
            "Norsk",
            "Suomi",
            "Simplified Chinese",
            "Traditional Chinese",
            "Russian"
        ]
    },
    "Sunday": {
        "short": "Active l’allumage programmé ce jour-là (BIOS Power-On).",
        "detail": "Avec BIOS Power-On Hour/Minute : planifie un power-on hebdomadaire.\n\nUtile pour maintenance / WOL de secours. Disable si non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Monday": {
        "short": "Active l’allumage programmé ce jour-là (BIOS Power-On).",
        "detail": "Avec BIOS Power-On Hour/Minute : planifie un power-on hebdomadaire.\n\nUtile pour maintenance / WOL de secours. Disable si non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Tuesday": {
        "short": "Active l’allumage programmé ce jour-là (BIOS Power-On).",
        "detail": "Avec BIOS Power-On Hour/Minute : planifie un power-on hebdomadaire.\n\nUtile pour maintenance / WOL de secours. Disable si non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Wednesday": {
        "short": "Active l’allumage programmé ce jour-là (BIOS Power-On).",
        "detail": "Avec BIOS Power-On Hour/Minute : planifie un power-on hebdomadaire.\n\nUtile pour maintenance / WOL de secours. Disable si non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Thursday": {
        "short": "Active l’allumage programmé ce jour-là (BIOS Power-On).",
        "detail": "Avec BIOS Power-On Hour/Minute : planifie un power-on hebdomadaire.\n\nUtile pour maintenance / WOL de secours. Disable si non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Friday": {
        "short": "Active l’allumage programmé ce jour-là (BIOS Power-On).",
        "detail": "Avec BIOS Power-On Hour/Minute : planifie un power-on hebdomadaire.\n\nUtile pour maintenance / WOL de secours. Disable si non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Saturday": {
        "short": "Active l’allumage programmé ce jour-là (BIOS Power-On).",
        "detail": "Avec BIOS Power-On Hour/Minute : planifie un power-on hebdomadaire.\n\nUtile pour maintenance / WOL de secours. Disable si non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "BIOS Power-On Hour": {
        "short": "Heure (0–23) de l’allumage programmé BIOS.",
        "detail": "Combiné aux jours Enable et à BIOS Power-On Minute.",
        "values": [
            "0"
        ]
    },
    "BIOS Power-On Minute": {
        "short": "Minute de l’allumage programmé BIOS.",
        "detail": "Combiné à BIOS Power-On Hour et aux jours de la semaine.",
        "values": [
            "0"
        ]
    },
    "Power On When AC Detected": {
        "short": "Allume le PC dès qu’il est branché sur secteur.",
        "detail": "Pratique kiosques / stations d’accueil. Peut allumer après une coupure électrique.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Power On When Lid is Opened": {
        "short": "Allume le portable à l’ouverture du capot.",
        "detail": "Confort utilisateur. Disable pour éviter allumages involontaires.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Startup Delay (sec.)": {
        "short": "Délai avant démarrage (secondes).",
        "detail": "Laisse le temps aux docks / NAS / PXE. 0 = immédiat.",
        "values": [
            "0",
            "5",
            "10",
            "15",
            "20",
            "25",
            "30",
            "35",
            "40",
            "45",
            "50",
            "55",
            "60"
        ]
    },
    "Fast Boot": {
        "short": "Réduit les tests POST pour démarrer plus vite.",
        "detail": "Enable = boot plus rapide, moins de diagnostics. Disable pour dépannage matériel.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Audio Alerts During Boot": {
        "short": "Bips / alertes sonores pendant le POST.",
        "detail": "Utile en atelier. Disable en open-space / salles silencieuses.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "NumLock on at boot": {
        "short": "État du verrouillage numérique au démarrage.",
        "detail": "Enable active NumLock avant Windows.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "USB Storage Boot": {
        "short": "Autorise le boot depuis une clé / disque USB.",
        "detail": "Enable nécessaire pour WinPE / installation USB. Disable durcit contre boot rogue.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Network (PXE) Boot": {
        "short": "Autorise le boot PXE (déploiement réseau).",
        "detail": "Indispensable MECM/OSD. Disable en production verrouillée après déploiement.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "IPv6 during UEFI Boot": {
        "short": "Active IPv6 pendant le boot UEFI (PXE IPv6).",
        "detail": "Enable si votre infra PXE est en IPv6.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Network Boot TFTP Window Size": {
        "short": "Taille de fenêtre TFTP pour le boot réseau.",
        "detail": "Peut accélérer le chargement PXE. Ne changez que sur conseil réseau.",
        "values": [
            "4"
        ]
    },
    "Prompt on Fixed Storage Change": {
        "short": "Alerte si un disque interne a changé.",
        "detail": "Sécurité / inventaire. Peut gêner le remplacement disque en atelier.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Prompt on Battery Errors": {
        "short": "Affiche un message si erreur batterie.",
        "detail": "Utile pour maintenance portables.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "UEFI Boot Order": {
        "short": "Ordre de démarrage UEFI (disque, réseau, USB…).",
        "detail": "Définit la priorité des chemins de boot. Pour OSD : réseau avant disque ; en production : disque d’abord.",
        "values": [
            "HDD:M.2:1",
            "HDD:USB:1",
            "NETWORK IPV4:EXPANSION:1",
            "NETWORK IPV6:EXPANSION:1"
        ]
    },
    "HP_Disk0MapForUefiBootOrder": {
        "short": "Mapping technique des chemins UEFI (disque 0).",
        "detail": "Chaîne device-path EFI. Modification manuelle risquée — plutôt via outils HP / boot order.",
        "values": [
            "PciRoot(0x0)/Pci(0x6,0x2)/Pci(0x0,0x0)/NVMe(0x1,6A-10-37-51-00-2E-E4-AC)/HD(1,GPT,33105EE5-EE76-452F-8143-961F678E9B9F,0x800,0x82000)/\\EFI\\Microsoft\\Boot\\bootmgfw.efi",
            "PciRoot(0x0)/Pci(0x14,0x0)",
            "PciRoot(0x0)/Pci(0xD,0x0)/USB(0x3,0x0)/USB(0x3,0x0)/MAC(24FBE3E99B06,0x0)/IPv4(0.0.0.0,0x0,DHCP,0.0.0.0,0.0.0.0,0.0.0.0)",
            "PciRoot(0x0)/Pci(0xD,0x0)/USB(0x3,0x0)/USB(0x3,0x0)/MAC(24FBE3E99B06,0x0)/IPv6(0000:0000:0000:0000:0000:0000:0000:0000,0x0,Static,0000:0000:0000:0000:0000:0000:0000:0000,0x40,0000:0000:0000:0000:0000:0000:0000:0000)"
        ]
    },
    "Force Cold Boot": {
        "short": "Force un démarrage à froid (réinit matérielle complète).",
        "detail": "Once = une fois. Utile après certains changements firmware.",
        "values": [
            "Disable",
            "Enable",
            "Once"
        ]
    },
    "Launch Hotkeys without Fn Keypress": {
        "short": "Raccourcis (F1–F12) sans maintenir Fn.",
        "detail": "Auto/Enable selon préférence utilisateur / clavier.",
        "values": [
            "Disable",
            "Enable",
            "Auto"
        ]
    },
    "Power Control": {
        "short": "Contrôles d’alimentation avancés firmware.",
        "detail": "Active des options de gestion d’énergie BIOS. Suivre défaut HP sauf besoin.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Swap Fn and Ctrl (Keys)": {
        "short": "Inverse les touches Fn et Ctrl.",
        "detail": "Confort clavier (habitude Mac / Thinkpad).",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Fast Charge": {
        "short": "Charge batterie plus rapide.",
        "detail": "Enable = charge accélérée (plus de chaleur). Disable pour préserver la batterie.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "USB Type-C Connector System Software Interface (UCSI)": {
        "short": "Interface logicielle USB-C (UCSI) pour l’OS.",
        "detail": "Enable recommandé pour une bonne gestion USB-C / PD sous Windows.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Enable High Resolution mode when connected to a USB-C DP alt mode dock": {
        "short": "Mode haute résolution sur dock USB-C DP Alt Mode.",
        "detail": "Améliore l’affichage dock. Disable en cas d’instabilité écran.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Special Keys mapped to Fn + keypress": {
        "short": "Mappe certaines touches spéciales derrière Fn.",
        "detail": "Comportement clavier HP. Selon préférence utilisateur.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Swap Arrow Up/Down and Page Up/Down Function": {
        "short": "Inverse Flèches et Page Up/Down.",
        "detail": "Sur certains claviers compacts HP.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Power button delay before sleep or power down": {
        "short": "Délai bouton power avant veille / arrêt.",
        "detail": "Évite les appuis accidentels.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "HP Intelligent Hibernate": {
        "short": "Hibernation intelligente HP selon contexte.",
        "detail": "Peut économiser la batterie. Tester si problèmes de reprise.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Fan Always on while on AC Power": {
        "short": "Ventilateur toujours actif sur secteur.",
        "detail": "Meilleur refroidissement, plus de bruit. Enable en charge lourde / docking.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Boost Converter": {
        "short": "Convertisseur d’alimentation boost (performances / charge).",
        "detail": "Lié à l’alimentation interne. Laisser Enable sauf notice HP contraire.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Backlit keyboard timeout": {
        "short": "Délai d’extinction du rétroéclairage clavier.",
        "detail": "Never = toujours allumé (batterie). 5–30 s courant.",
        "values": [
            "5 secs.",
            "15 secs.",
            "30 secs.",
            "1 min..",
            "5 mins.",
            "Never."
        ]
    },
    "Automatic Keyboard Backlit": {
        "short": "Rétroéclairage clavier automatique (capteur / usage).",
        "detail": "Enable pour confort ; Disable pour économie.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Bluetooth": {
        "short": "Active / désactive le Bluetooth matériel.",
        "detail": "Disable pour durcissement (pas de BT). Enable pour périphériques sans fil.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Wireless Network Device (WLAN)": {
        "short": "Active / désactive le Wi‑Fi.",
        "detail": "Disable sur postes filaires verrouillés. Enable sinon.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "LAN / WLAN Auto Switching": {
        "short": "Bascule auto Wi‑Fi quand le câble Ethernet est branché.",
        "detail": "Enable évite double connexion. Pratique docks.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Lock Wireless Button": {
        "short": "Verrouille le bouton radio (empêche l’utilisateur de couper Wi‑Fi/BT).",
        "detail": "Enable pour imposer la politique radio.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Dynamic Noise Suppression": {
        "short": "Réduction de bruit micro / audio dynamique.",
        "detail": "Améliore les appels. Disable si artefacts audio.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Intel Dynamic Tuning": {
        "short": "Ajuste dynamiquement perf CPU / thermique (Intel DTT).",
        "detail": "Enable pour équilibre perf/bruit. Disable pour debug thermique.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Motion sensing cool mode": {
        "short": "Mode thermique lié aux capteurs de mouvement.",
        "detail": "Réduit chauffe selon usage. Spécifique portables HP.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "HP Application Driver": {
        "short": "Active le support des pilotes / apps HP au niveau firmware.",
        "detail": "Souvent requis pour fonctions HP sous Windows. Enable recommandé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Sanitization Mode Countdown Timer": {
        "short": "Compte à rebours du mode sanitization (nettoyage / reset).",
        "detail": "Délai avant action de sanitization. Selon procédure HP.",
        "values": [
            "15",
            "30",
            "45",
            "60",
            "75",
            "90",
            "105",
            "120",
            "135",
            "150",
            "165",
            "180",
            "195",
            "210",
            "225",
            "240",
            "255",
            "270",
            "285",
            "300"
        ]
    },
    "Enhanced Sign-In Security": {
        "short": "Renforce la sécurité de connexion (ESS / biométrie).",
        "detail": "Lie davantage Windows Hello / caméra / empreinte. Enable si biométrie.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Audio Device": {
        "short": "Active le périphérique audio intégré.",
        "detail": "Disable = pas de son intégré (kiosque silencieux).",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Wake On LAN": {
        "short": "Comportement au réveil réseau (WOL).",
        "detail": "• Disabled : pas de WOL.\n• Boot to Network : PXE au réveil.\n• Boot to Hard Drive / Normal Boot Order : démarrage classique après WOL.\n\nMECM : souvent Boot to Hard Drive ou Normal.",
        "values": [
            "Disabled",
            "Boot to Network",
            "Boot to Hard Drive",
            "Boot to Normal Boot Order"
        ]
    },
    "Microphone": {
        "short": "Active / coupe le micro intégré.",
        "detail": "Disable pour confidentialité stricte.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Internal Speakers": {
        "short": "Active les haut-parleurs internes.",
        "detail": "Disable pour forcer casque / silence.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Runtime Power Management": {
        "short": "Gestion d’énergie runtime des composants.",
        "detail": "Enable pour économie. Disable si latences / périphériques capricieux.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Extended Idle Power States": {
        "short": "États d’idle profonds (économie accrue).",
        "detail": "Peut causer des réveils lents USB/réseau. Tester avant parc entier.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Headphone Output": {
        "short": "Active la sortie casque.",
        "detail": "Disable rare (kiosques).",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Battery Health Manager": {
        "short": "Politique de charge pour la durée de vie batterie.",
        "detail": "• Maximize Battery Health : limite charge ~80 % (dock permanent).\n• Let HP Manage : équilibré.\n• Minimize : priorité autonomie max.\n\nPostes toujours branchés : Maximize Health.",
        "values": [
            "Maximize Battery Health Management",
            "Let HP Manage My Battery Health",
            "Minimize Battery Health Management"
        ]
    },
    "Disable Battery On Next Boot": {
        "short": "Désactive la batterie au prochain arrêt / boot (mode atelier).",
        "detail": "Pour maintenance. Remettre « Do not disable » ensuite.",
        "values": [
            "Do not disable",
            "Next shut down"
        ]
    },
    "Integrated Camera": {
        "short": "Active / coupe la webcam.",
        "detail": "Disable pour confidentialité.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Fingerprint Device": {
        "short": "Active le lecteur d’empreintes.",
        "detail": "Enable pour Windows Hello. Disable si non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Touch Device": {
        "short": "Active l’écran / pavé tactile.",
        "detail": "Disable sur convertibles si politique « no touch ».",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Battery Safety Mode": {
        "short": "Mode sécurité batterie (protection charge anormale).",
        "detail": "Enable pour sécurité. Suivre recommandations HP.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "HP Camera Privacy Key": {
        "short": "Comportement de la touche confidentialité caméra.",
        "detail": "Définit si l’état privacy est mémorisé ou forcé ON après boot/resume.",
        "values": [
            "Camera Privacy Key stays in last known state",
            "Camera Privacy Key ON after boot or resume"
        ]
    },
    "Video Memory Size": {
        "short": "Mémoire réservée au GPU intégré.",
        "detail": "Plus = mieux pour multi-écrans / légers graphismes ; moins de RAM système. 256–512 Mo courant.",
        "values": [
            "64 MB",
            "128 MB",
            "256 MB",
            "512 MB"
        ]
    },
    "Restrict USB Devices": {
        "short": "Restreint les classes de périphériques USB.",
        "detail": "• Allow all : aucune restriction.\n• Allow only keyboard and mouse : durcissement fort.\n• Allow all but storage and hubs : bloque clés USB / hubs.\n\nEntreprise : souvent « all but storage ».",
        "values": [
            "Allow all USB Devices",
            "Allow only keyboard and mouse",
            "Allow all but storage devices and hubs"
        ]
    },
    "USB Legacy Port Charging": {
        "short": "Charge des appareils USB même PC éteint / veille.",
        "detail": "Confort utilisateur, légère conso batterie.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Disable Charging Port in sleep/off if battery below (%):": {
        "short": "Coupe la charge USB en veille si batterie sous ce seuil %.",
        "detail": "Évite de vider la batterie en chargeant un téléphone.",
        "values": [
            "10"
        ]
    },
    "Right USB Ports": {
        "short": "Active les ports USB du côté droit.",
        "detail": "Disable pour réduire les ports disponibles (sécurité physique).",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Thunderbolt Type-C Ports": {
        "short": "Active les ports Thunderbolt / USB‑C Thunderbolt.",
        "detail": "Disable si politique no-Thunderbolt (DMA). Enable pour docks TB.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Thunderbolt Mode": {
        "short": "Active le protocole Thunderbolt (données + vidéo).",
        "detail": "Requis pour docks Thunderbolt complets. Couplé à DMA Protection.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Full encryption of main memory (DRAM)": {
        "short": "Chiffre la mémoire vive (Total Memory Encryption / équivalent).",
        "detail": "Protège contre lecture physique de la RAM. Impact perf possible. Haute sécurité.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Turbo-boost": {
        "short": "Autorise Intel Turbo Boost (fréquences boost).",
        "detail": "Enable = meilleures perfs ponctuelles. Disable = plus stable thermiquement.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Hyperthreading": {
        "short": "Active l’Hyper-Threading (SMT).",
        "detail": "Enable = plus de threads logiques. Certains guides haute sécu recommandent Disable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Virtualization Technology (VTx)": {
        "short": "Intel VT-x : virtualisation CPU.",
        "detail": "Obligatoire Hyper-V, VMware, WSL2, Credential Guard. Recommandation : Enable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Virtualization Technology for Directed I/O (VTd)": {
        "short": "Intel VT-d : virtualisation / isolation DMA des périphériques.",
        "detail": "Requis Credential Guard / Device Guard / VMs avec PCI. Enable recommandé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Trusted Execution Technology (TXT)": {
        "short": "Intel TXT : démarrage mesuré / attestation.",
        "detail": "Haute sécurité / measured boot. Enable seulement si la stack l’exige.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "USB Key Provisioning Support": {
        "short": "Provisionnement AMT via clé USB.",
        "detail": "Pour configurer Intel AMT hors bande. Disable si AMT non utilisé.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Unconfigure AMT on next boot": {
        "short": "Déprovisionne AMT au prochain boot.",
        "detail": "Efface la config AMT. Utile avant réaffectation. Remettre « Do Not Apply » ensuite.",
        "values": [
            "Do Not Apply",
            "Apply"
        ]
    },
    "SOL Terminal Emulation Mode": {
        "short": "Mode émulation Serial-over-LAN (ANSI / VT100).",
        "detail": "Pour console distante AMT. ANSI souvent OK.",
        "values": [
            "ANSI",
            "VT100"
        ]
    },
    "Verbose Boot Messages": {
        "short": "Messages de boot détaillés (debug).",
        "detail": "Enable pour diagnostic. Disable en production.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Watchdog Timer": {
        "short": "Watchdog firmware : reboot si le système ne répond plus.",
        "detail": "Utile kiosques / postes critiques.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "DMA Protection": {
        "short": "Protection contre les attaques DMA (Thunderbolt / PCIe).",
        "detail": "Recommandation : Enable (Kernel DMA Protection).",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "OS Watchdog Timer (min.)": {
        "short": "Délai watchdog côté OS (minutes).",
        "detail": "Durée avant action si l’OS ne « kick » pas le watchdog.",
        "values": [
            "5",
            "10",
            "15",
            "20",
            "25"
        ]
    },
    "BIOS Watchdog Timer (min.)": {
        "short": "Délai watchdog côté BIOS (minutes).",
        "detail": "Surveillance pré-OS / firmware.",
        "values": [
            "5",
            "10",
            "15",
            "20",
            "25"
        ]
    },
    "Pre-boot DMA protection": {
        "short": "Périmètre de la protection DMA avant le boot OS.",
        "detail": "• Thunderbolt Only\n• All PCIe Devices (plus strict)\n\nHaute sécu : All PCIe.",
        "values": [
            "Thunderbolt Only",
            "All PCIe Devices"
        ]
    },
    "Measure additional DMA settings": {
        "short": "Mesure (TPM PCR) d’options DMA supplémentaires.",
        "detail": "Pour attestation / measured boot avancé (PCR1 / PCR7).",
        "values": [
            "Disable",
            "PCR1",
            "PCR7"
        ]
    },
    "USB Redirection Support": {
        "short": "Redirection USB via AMT / remote KVM.",
        "detail": "Permet de monter une ISO distante. Enable si support AMT ; sinon Disable.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Show Unconfigure ME Confirmation Prompt": {
        "short": "Demande confirmation avant unconfigure ME/AMT.",
        "detail": "Enable évite une déconfiguration accidentelle.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "CIRA Timeout (min.)": {
        "short": "Timeout Client Initiated Remote Access (AMT).",
        "detail": "Délai d’attente CIRA. Never = pas de timeout.",
        "values": [
            "1 min",
            "2 mins",
            "3 mins",
            "4 mins",
            "Never"
        ]
    },
    "Intel Active Management Technology (AMT)": {
        "short": "Active Intel AMT (vPro) — gestion hors bande.",
        "detail": "Pilotage distant même OS down (KVM, PXE, power).\n\nEnable seulement si vPro provisionné et sécurisé. Sinon Disable pour réduire la surface d’attaque.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "OS Recovery": {
        "short": "Active la récupération OS via le firmware HP.",
        "detail": "Permet de retélécharger / réinstaller une image via le BIOS. Politique IT à valider.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Recover OS from Network": {
        "short": "Récupération OS depuis le réseau.",
        "detail": "Télécharge l’image depuis URL configurée. Nécessite réseau fonctionnel au boot.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Recover OS after Boot Failure": {
        "short": "Lance la récupération après échecs de boot répétés.",
        "detail": "Filet de secours automatique. Peut surprendre si faux positifs.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Prompt before Boot Failure Recovery": {
        "short": "Demande confirmation avant récupération auto.",
        "detail": "Enable pour éviter une réimage non voulue.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "OS Recovery Agent URL": {
        "short": "URL de l’agent de récupération OS.",
        "detail": "Par défaut souvent un serveur HP. Peut pointer vers un miroir interne.",
        "values": [
            "https://ftp.hp.com/pub/pcbios/CPR"
        ]
    },
    "OS Recovery Agent Username": {
        "short": "Identifiant pour télécharger l’agent de récupération.",
        "detail": "Si le serveur exige une auth.",
        "values": [
            ""
        ]
    },
    "OS Recovery Agent Provisioning Version": {
        "short": "Version de provisioning de l’agent.",
        "detail": "Métadonnée de déploiement agent. Rarement touchée manuellement.",
        "values": [
            "0"
        ]
    },
    "OS Recovery Agent Public Key": {
        "short": "Clé publique pour vérifier l’agent de récupération.",
        "detail": "Assure l’intégrité/authenticité de l’agent. Ne pas remplacer sans PKI interne.",
        "values": [
            "v02IkAmb7qkyNbIVBvaz/UfnocR4c37F5wV5eTIODxqML61JVP6YRJ/yWRV/I5VqqWvyaRvR2Eip22LGkJX4SjR3HladKeiAGFzKIUxLGQk3t4zPqCn2bR7CP3kjC4NZj3l+DK5sMGGNxa+1D0wE8J++ma+5I7VcLHBdffjRw9LeCoiwL11cC2ud4JfAPV90QWbOvOmYq11WSRcyNb36zv9mtCflBNn4fYTppwuQzlfVBZaZIDSLXugPP6PKc+sxnGDKwAxaRd+Kj2Aw0tLY9IpeapJzJa4Gpdbi2Y1PLskFtxex7X8bjjVBF/0dqRs48FGF0bVqeRyhT1ExoHGPuw=="
        ]
    },
    "OS Recovery Image URL": {
        "short": "URL de l’image OS de récupération.",
        "detail": "Pointez vers votre image golden / serveur interne si utilisé.",
        "values": [
            ""
        ]
    },
    "OS Recovery Image Username": {
        "short": "Identifiant pour l’image OS.",
        "detail": "Auth HTTP éventuelle.",
        "values": [
            ""
        ]
    },
    "OS Recovery Image Provisioning Version": {
        "short": "Version de provisioning de l’image.",
        "detail": "Suivi de version côté firmware.",
        "values": [
            "0"
        ]
    },
    "OS Recovery Image Public Key": {
        "short": "Clé publique de vérification de l’image OS.",
        "detail": "Signature de l’image. Critique pour la confiance de la recovery.",
        "values": [
            "v02IkAmb7qkyNbIVBvaz/UfnocR4c37F5wV5eTIODxqML61JVP6YRJ/yWRV/I5VqqWvyaRvR2Eip22LGkJX4SjR3HladKeiAGFzKIUxLGQk3t4zPqCn2bR7CP3kjC4NZj3l+DK5sMGGNxa+1D0wE8J++ma+5I7VcLHBdffjRw9LeCoiwL11cC2ud4JfAPV90QWbOvOmYq11WSRcyNb36zv9mtCflBNn4fYTppwuQzlfVBZaZIDSLXugPP6PKc+sxnGDKwAxaRd+Kj2Aw0tLY9IpeapJzJa4Gpdbi2Y1PLskFtxex7X8bjjVBF/0dqRs48FGF0bVqeRyhT1ExoHGPuw=="
        ]
    },
    "Update Source": {
        "short": "Source des mises à jour BIOS (HP ou Custom).",
        "detail": "Custom permet un serveur interne. HP = cloud/HP official.",
        "values": [
            "HP",
            "Custom"
        ]
    },
    "Automatically Check for Updates": {
        "short": "Fréquence de recherche de mises à jour BIOS.",
        "detail": "Daily / Weekly / Monthly. En entreprise SoftPaq-only : souvent updates réseau Disable.",
        "values": [
            "Daily",
            "Weekly",
            "Monthly"
        ]
    },
    "Automatic BIOS Update Setting": {
        "short": "Politique d’installation auto des updates BIOS.",
        "detail": "De « Disable » à installation silencieuse. MECM : souvent Disable + SoftPaq contrôlé.",
        "values": [
            "Disable",
            "Let user decide whether to install updates",
            "Install all updates automatically",
            "Install only important updates automatically",
            "Download and install normal BIOS updates automatically without prompts",
            "Download and install important BIOS updates automatically without prompts"
        ]
    },
    "Update Address": {
        "short": "Adresse / URL du serveur de mise à jour custom.",
        "detail": "Si Update Source = Custom.",
        "values": [
            ""
        ]
    },
    "Force Check on Reboot": {
        "short": "Force une vérif de mise à jour à chaque reboot.",
        "detail": "Plus à jour, plus de trafic / délais boot.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Update BIOS via Network": {
        "short": "Autorise la mise à jour BIOS par le réseau.",
        "detail": "Disable pour imposer uniquement SoftPaq / USB / CMSL local.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "DNS Configuration": {
        "short": "DNS pour les fonctions réseau du BIOS (auto / manuel).",
        "detail": "Manuel si le BIOS doit résoudre des noms hors DHCP.",
        "values": [
            "Automatic",
            "Manual"
        ]
    },
    "DNS Addresses": {
        "short": "Serveurs DNS manuels du BIOS.",
        "detail": "Utilisé si DNS Configuration = Manual.",
        "values": [
            ""
        ]
    },
    "Data transfer timeout": {
        "short": "Timeout transfert données (updates / recovery).",
        "detail": "Augmenter sur liens lents.",
        "values": [
            "100"
        ]
    },
    "IPv4 Address": {
        "short": "Adresse IPv4 statique du firmware (si config manuelle).",
        "detail": "Rare : la plupart du temps DHCP (Automatic).",
        "values": [
            ""
        ]
    },
    "IPv4 Configuration": {
        "short": "IPv4 auto (DHCP) ou manuelle pour le BIOS.",
        "detail": "Automatic recommandé sauf réseau sans DHCP au boot.",
        "values": [
            "Automatic",
            "Manual"
        ]
    },
    "IPv4 Gateway": {
        "short": "Passerelle IPv4 manuelle du BIOS.",
        "detail": "Avec IPv4 Configuration = Manual.",
        "values": [
            ""
        ]
    },
    "IPv4 Subnet Mask": {
        "short": "Masque de sous-réseau IPv4 du BIOS.",
        "detail": "Avec config IPv4 manuelle.",
        "values": [
            ""
        ]
    },
    "Force Default IP Configuration": {
        "short": "Force la config IP par défaut du firmware.",
        "detail": "Réinitialise le réseau BIOS. Usage dépannage.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Remote HP PC Hardware Diagnostics Custom Client Upload Url": {
        "short": "URL d’upload des résultats de diagnostics matériels.",
        "detail": "Serveur où envoyer les rapports HP Diagnostics.",
        "values": [
            ""
        ]
    },
    "Remote HP PC Hardware Diagnostics Use Custom Download Url": {
        "short": "Utiliser une URL custom pour télécharger le client diagnostics.",
        "detail": "Enable pour miroir interne.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Remote HP PC Hardware Diagnostics Custom Client Download Url": {
        "short": "URL de téléchargement du client diagnostics.",
        "detail": "Si Use Custom Download Url = Enable.",
        "values": [
            ""
        ]
    },
    "Remote HP PC Hardware Diagnostics Scheduled Execution Enabled": {
        "short": "Planifie l’exécution des diagnostics matériels.",
        "detail": "Enable + fréquence pour contrôles périodiques.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Remote HP PC Hardware Diagnostics Scheduled Execution Frequency": {
        "short": "Fréquence des diagnostics planifiés.",
        "detail": "Daily / Weekly / Monthly.",
        "values": [
            "Daily",
            "Weekly",
            "Monthly"
        ]
    },
    "Remote HP PC Hardware Diagnostics Upload Server Username": {
        "short": "Compte pour upload des rapports diagnostics.",
        "detail": "Auth du serveur d’upload.",
        "values": [
            ""
        ]
    },
    "Remote HP PC Hardware Diagnostics Upload Server Password": {
        "short": "Mot de passe serveur d’upload diagnostics.",
        "detail": "Secret : gérez-le comme un secret d’infra.",
        "values": [
            ""
        ]
    },
    "Remote HP PC Hardware Diagnostics Execute On Next Boot": {
        "short": "Lance les diagnostics matériels au prochain boot.",
        "detail": "Ponctuel pour investiguer un incident.",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "E-label": {
        "short": "Étiquette électronique réglementaire (présente ou non).",
        "detail": "Infos légales / radio dans le firmware. Rarement modifié.",
        "values": [
            "Not Present",
            "Present"
        ]
    },
    "Extended DHCP Timeout": {
        "short": "Allonge le délai d’attente DHCP au boot.",
        "detail": "Utile si le DHCP est lent (VLAN, relais).",
        "values": [
            "Disable",
            "Enable"
        ]
    },
    "Network TCP Buffer Size": {
        "short": "Taille du buffer TCP réseau du BIOS.",
        "detail": "Peut influencer débits updates/recovery. Valeurs avancées.",
        "values": [
            "32768",
            "65536",
            "2097152"
        ]
    },
    "Save Custom Defaults": {
        "short": "Enregistre la config actuelle comme défauts personnalisés.",
        "detail": "« Save » une fois pour figer un profil. Remettre « Do not Save » ensuite.",
        "values": [
            "Do not Save",
            "Save"
        ]
    },
    "Apply Custom Defaults and Exit": {
        "short": "Applique les défauts custom puis quitte.",
        "detail": "Action ponctuelle. Ne pas laisser Yes en fichier de config permanent.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "Apply Factory Defaults and Exit": {
        "short": "Restaure les défauts usine puis quitte.",
        "detail": "Remise à zéro BIOS. Perte des settings custom.",
        "values": [
            "No",
            "Yes"
        ]
    },
    "Restore Security Settings to Factory Defaults": {
        "short": "Remet uniquement les paramètres de sécurité aux valeurs usine.",
        "detail": "Plus ciblé qu’un reset complet. Vérifier l’impact Secure Boot / TPM / MDP.",
        "values": [
            "Disable",
            "Enable"
        ]
    }
};
