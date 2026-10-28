window.TBCT_HELP = {
  "Secure Boot": {
    short: "Active Secure Boot UEFI (signatures de boot).",
    detail: "Recommandé Enabled pour Windows 11 / Credential Guard.\n\nDisabled uniquement pour bootloaders non signés ou dépannage.",
    values: ["Enabled", "Disabled"]
  },
  "SecureBoot": {
    short: "Alias Secure Boot (selon firmware).",
    detail: "Même rôle que « Secure Boot ». Le nom exact dépend du modèle — vérifier via Export-LnvWmiSettings.",
    values: ["Enabled", "Disabled"]
  },
  "TPM Device": {
    short: "Active le module TPM matériel.",
    detail: "Requis BitLocker / Windows 11. Preferer Enabled.",
    values: ["Enabled", "Disabled"]
  },
  "Security Chip": {
    short: "État du Security Chip (TPM) Lenovo.",
    detail: "Active = TPM utilisable. Inactive / Disabled selon modèles. Ne pas désactiver sur un parc BitLocker.",
    values: ["Active", "Inactive", "Disabled"]
  },
  "Security Chip State": {
    short: "État du Security Chip.",
    detail: "Active recommandé pour BitLocker / Windows 11.",
    values: ["Active", "Inactive"]
  },
  "Intel Virtualization Technology": {
    short: "VT-x / virtualisation CPU.",
    detail: "Enabled pour Hyper-V, VMware, Credential Guard. Désactiver uniquement si politique l’exige.",
    values: ["Enabled", "Disabled"]
  },
  "Intel(R) Virtualization Technology": {
    short: "VT-x (libellé Intel).",
    detail: "Alias selon firmware. Enabled recommandé en entreprise.",
    values: ["Enabled", "Disabled"]
  },
  "VT-d": {
    short: "IOMMU / virtualisation I/O.",
    detail: "Utile pour Hyper-V / Device Guard. Enabled en général.",
    values: ["Enabled", "Disabled"]
  },
  "Intel VT-d Feature": {
    short: "VT-d (libellé Lenovo).",
    detail: "Même rôle que VT-d.",
    values: ["Enabled", "Disabled"]
  },
  "Hyper-Threading Technology": {
    short: "Hyper-Threading CPU.",
    detail: "Enabled par défaut pour performances. Certaines politiques sécurité le désactivent.",
    values: ["Enabled", "Disabled"]
  },
  "Intel(R) Hyper-Threading Technology": {
    short: "Hyper-Threading (libellé Intel).",
    detail: "Alias Hyper-Threading selon modèle.",
    values: ["Enabled", "Disabled"]
  },
  "Wake On LAN": {
    short: "Réveil réseau (WOL).",
    detail: "Enabled / Primary pour déploiements MECM. AC only limite au secteur.",
    values: ["Enabled", "Disabled", "AC only", "Primary"]
  },
  "WakeOnLAN": {
    short: "Alias Wake On LAN.",
    detail: "Nom compact selon firmware. Preferer Enabled pour WOL.",
    values: ["Enabled", "Disabled"]
  },
  "Wireless LAN": {
    short: "Wi-Fi intégré.",
    detail: "Disabled possible pour postes filaires sécurisés.",
    values: ["Enabled", "Disabled"]
  },
  "Bluetooth": {
    short: "Bluetooth intégré.",
    detail: "Disabled si politique radio stricte.",
    values: ["Enabled", "Disabled"]
  },
  "WWAN": {
    short: "Modem cellulaire (WWAN).",
    detail: "Disabled si non utilisé / politique radio.",
    values: ["Enabled", "Disabled"]
  },
  "Wireless WAN": {
    short: "Alias WWAN.",
    detail: "Même rôle que WWAN.",
    values: ["Enabled", "Disabled"]
  },
  "Integrated Camera": {
    short: "Webcam intégrée.",
    detail: "Disabled pour postes sensibles (confidentialité).",
    values: ["Enabled", "Disabled"]
  },
  "Camera": {
    short: "Alias caméra.",
    detail: "Même rôle que Integrated Camera.",
    values: ["Enabled", "Disabled"]
  },
  "Microphone": {
    short: "Micro intégré.",
    detail: "Disabled possible pour confidentialité.",
    values: ["Enabled", "Disabled"]
  },
  "Fingerprint Reader": {
    short: "Lecteur d’empreintes.",
    detail: "Enabled pour Windows Hello. Disabled si non souhaité.",
    values: ["Enabled", "Disabled"]
  },
  "Fingerprint Reader Control": {
    short: "Contrôle lecteur d’empreintes.",
    detail: "Alias selon modèle.",
    values: ["Enabled", "Disabled"]
  },
  "USB Port Control": {
    short: "Contrôle des ports USB.",
    detail: "Disabled bloque les ports selon firmware — tester avant déploiement massif.",
    values: ["Enabled", "Disabled"]
  },
  "Always On USB": {
    short: "Charge USB à machine éteinte.",
    detail: "Enabled pratique ; Disabled pour économiser la batterie.",
    values: ["Enabled", "Disabled"]
  },
  "USB Emulation": {
    short: "Émulation USB legacy.",
    detail: "Souvent Enabled pour clavier/souris au boot.",
    values: ["Enabled", "Disabled"]
  },
  "Boot Mode": {
    short: "Mode de démarrage UEFI / Legacy.",
    detail: "UEFI Only recommandé Windows 10/11. Legacy Support pour anciens OS.",
    values: ["UEFI Only", "Legacy Support", "UEFI"]
  },
  "Boot Order Lock": {
    short: "Verrouille l’ordre de boot.",
    detail: "Enabled empêche les utilisateurs de changer l’ordre dans le menu F12.",
    values: ["Enabled", "Disabled"]
  },
  "Boot Up Num-Lock Status": {
    short: "Num Lock au démarrage.",
    detail: "On ou Off selon préférence clavier.",
    values: ["On", "Off"]
  },
  "Fast Boot": {
    short: "Démarrage rapide.",
    detail: "Enabled accélère le POST ; peut masquer certaines options USB au boot.",
    values: ["Enabled", "Disabled"]
  },
  "BIOS Update by Windows Update": {
    short: "MàJ BIOS via Windows Update.",
    detail: "Souvent Disabled en entreprise (contrôle via MECM / Lenovo System Update).",
    values: ["Enabled", "Disabled"]
  },
  "Windows UEFI Firmware Update": {
    short: "MàJ firmware UEFI via Windows.",
    detail: "Disabled recommandé si vous pilotez les BIOS autrement.",
    values: ["Enabled", "Disabled"]
  },
  "AMT Control": {
    short: "Intel AMT / vPro.",
    detail: "Enabled seulement si AMT est utilisé. Disabled réduit la surface d’attaque.",
    values: ["Enabled", "Disabled"]
  },
  "Intel AMT Control": {
    short: "Alias AMT Control.",
    detail: "Même rôle qu’AMT Control.",
    values: ["Enabled", "Disabled"]
  },
  "Absolute Persistence Module": {
    short: "Module Absolute / Computrace.",
    detail: "Permanently Disabled irréversible sur certains modèles — à décider avant déploiement.",
    values: ["Enabled", "Disabled", "Permanently Disabled"]
  },
  "Computrace Module Activation": {
    short: "Activation Computrace / Absolute.",
    detail: "Même famille qu’Absolute Persistence Module.",
    values: ["Enabled", "Disabled", "Permanently Disabled"]
  },
  "Password Count Exceeded Notification": {
    short: "Alerte après trop d’échecs MDP BIOS.",
    detail: "Enabled recommandé.",
    values: ["Enabled", "Disabled"]
  },
  "Password Entry on Restart": {
    short: "Demande MDP au redémarrage OS.",
    detail: "Disabled facilite les redémarrages admin / MECM.",
    values: ["Enabled", "Disabled"]
  },
  "BIOS Password at Boot Device List": {
    short: "MDP pour le menu de boot (F12).",
    detail: "Enabled renforce le contrôle du boot device.",
    values: ["Enabled", "Disabled"]
  },
  "BIOS Password at Reboot": {
    short: "MDP Supervisor à chaque reboot.",
    detail: "Enabled plus strict ; peut gêner l’automation.",
    values: ["Enabled", "Disabled"]
  },
  "Lock BIOS Settings": {
    short: "Verrouille les réglages BIOS.",
    detail: "Enabled + Supervisor password pour bloquer les modifications utilisateur.",
    values: ["Enabled", "Disabled"]
  },
  "BIOS Update Setting": {
    short: "Autorise les mises à jour BIOS.",
    detail: "Enabled pour permettre flash ; contrôler via package MECM.",
    values: ["Enabled", "Disabled"]
  },
  "Secure RollBack Prevention": {
    short: "Empêche le downgrade BIOS.",
    detail: "Enabled recommandé en production.",
    values: ["Enabled", "Disabled"]
  },
  "Physical Presence for TCG Clear": {
    short: "Présence physique pour clear TPM.",
    detail: "Enabled exige confirmation physique pour clear TPM.",
    values: ["Enabled", "Disabled"]
  },
  "Clear Security Chip": {
    short: "Efface le Security Chip (TPM).",
    detail: "Opération destructive — ne pas cocher en config de masse sans procédure BitLocker.",
    values: ["Enabled", "Disabled"]
  },
  "I/O Port Access": {
    short: "Accès ports I/O.",
    detail: "Disabled peut bloquer périphériques — tester par modèle.",
    values: ["Enabled", "Disabled"]
  },
  "Thunderbolt Device Access": {
    short: "Accès périphériques Thunderbolt.",
    detail: "User Authorization est un bon compromis sécurité / usage.",
    values: ["Enabled", "Disabled", "User Authorization"]
  },
  "Thunderbolt BIOS Assist Mode": {
    short: "Mode assist Thunderbolt BIOS.",
    detail: "Selon dock / docking Lenovo.",
    values: ["Enabled", "Disabled"]
  },
  "PCIe Tunneling over Thunderbolt": {
    short: "Tunneling PCIe via Thunderbolt.",
    detail: "Enabled pour docks complets ; Disabled durcit la sécurité.",
    values: ["Enabled", "Disabled"]
  },
  "Dock Firmware Update": {
    short: "MàJ firmware dock.",
    detail: "Enabled pour autoriser les updates dock.",
    values: ["Enabled", "Disabled"]
  },
  "Intel SGX Control": {
    short: "Intel Software Guard Extensions.",
    detail: "Souvent Software Controlled. SGX est déprécié sur CPU récents.",
    values: ["Enabled", "Disabled", "Software Controlled"]
  },
  "Device Guard": {
    short: "Device Guard / VBS related.",
    detail: "Enabled pour postures durcies Windows.",
    values: ["Enabled", "Disabled"]
  },
  "Kernel DMA Protection": {
    short: "Protection DMA noyau.",
    detail: "Enabled recommandé (Thunderbolt / ports externes).",
    values: ["Enabled", "Disabled"]
  },
  "Pre-boot DMA Protection": {
    short: "Protection DMA pré-boot.",
    detail: "Enabled recommandé avec Kernel DMA Protection.",
    values: ["Enabled", "Disabled"]
  },
  "Enhanced Windows Biometric Security": {
    short: "Sécurité biométrique Windows renforcée.",
    detail: "Enabled si Windows Hello / empreintes en production.",
    values: ["Enabled", "Disabled"]
  },
  "Owner Tag": {
    short: "Owner Tag (chaîne libre).",
    detail: "Texte inventaire / propriété. Saisir la valeur dans le champ.",
    values: []
  },
  "Asset Tag": {
    short: "Asset Tag inventaire.",
    detail: "Souvent géré par ITAM. Texte libre.",
    values: []
  },
  "Asset Number": {
    short: "Numéro d’asset.",
    detail: "Alias inventaire selon modèle.",
    values: []
  },
  "Power On with AC Attach": {
    short: "Allumage à la connexion secteur.",
    detail: "Utile pour docks / salles de réunion.",
    values: ["Enabled", "Disabled"]
  },
  "Power On Lid Open": {
    short: "Allumage à l’ouverture du capot.",
    detail: "Enabled pratique ; Disabled pour éviter allumages accidentels.",
    values: ["Enabled", "Disabled"]
  },
  "Fn and Ctrl Key swap": {
    short: "Inversion Fn / Ctrl.",
    detail: "Selon préférences clavier ThinkPad.",
    values: ["Enabled", "Disabled"]
  },
  "Fn Sticky key": {
    short: "Touche Fn sticky.",
    detail: "Disabled par défaut en général.",
    values: ["Enabled", "Disabled"]
  },
  "Keyboard Backlight Brightness": {
    short: "Rétroéclairage clavier.",
    detail: "Auto / Low / High / Off.",
    values: ["Auto", "Low", "High", "Off"]
  },
  "TrackPoint": {
    short: "TrackPoint (nipple).",
    detail: "Enabled par défaut ThinkPad.",
    values: ["Enabled", "Disabled"]
  },
  "TouchPad": {
    short: "Pavé tactile.",
    detail: "Enabled / Disabled selon usage.",
    values: ["Enabled", "Disabled"]
  },
  "Adaptive Thermal Management": {
    short: "Gestion thermique adaptative.",
    detail: "Enabled pour modes Intelligent Cooling.",
    values: ["Enabled", "Disabled"]
  },
  "Intelligent Cooling": {
    short: "Intelligent Cooling Lenovo.",
    detail: "Enabled pour modes performance / silence adaptatifs.",
    values: ["Enabled", "Disabled"]
  },
  "Battery Charge Level": {
    short: "Politique de charge batterie.",
    detail: "Full Capacity ou seuils Start/Stop selon conservation batterie.",
    values: ["Start Threshold", "Stop Threshold", "Full Capacity"]
  },
  "Charge In Battery Mode": {
    short: "Charge en mode batterie (dock).",
    detail: "Selon docking / OneLink.",
    values: ["Enabled", "Disabled"]
  }
};
