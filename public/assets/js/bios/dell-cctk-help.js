window.CCTK_HELP = {
    "secureboot": {
        "short": "Active Secure Boot UEFI (signatures de boot).",
        "detail": "Recommandé Enable pour Windows 11 / Credential Guard.\n\nDisable uniquement pour bootloaders non signés ou dépannage.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "tpm": {
        "short": "Active le module TPM matériel.",
        "detail": "Requis BitLocker / Windows 11. Valeur on recommandée.",
        "values": [
            "on",
            "off"
        ]
    },
    "tpmactivation": {
        "short": "Active / désactive le TPM au niveau firmware.",
        "detail": "Souvent « activate » après tpm=on. Ne pas désactiver sur un parc BitLocker sans procédure.",
        "values": [
            "activate",
            "deactivate",
            "enable",
            "disable"
        ]
    },
    "tpmppi": {
        "short": "Physical Presence Interface pour actions TPM.",
        "detail": "Enable impose parfois une confirmation physique. Disable facilite l’admin distant.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "tpmppipo": {
        "short": "PPI pour opérations TPM (provisioning).",
        "detail": "Souvent enable en entreprise avec BitLocker.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "vtfordirectedio": {
        "short": "VT-d : virtualisation / isolation DMA des périphériques.",
        "detail": "Recommandé Enable (Credential Guard, VMs).",
        "values": [
            "enable",
            "disable"
        ]
    },
    "virtualization": {
        "short": "VT-x : virtualisation CPU.",
        "detail": "Obligatoire Hyper-V / WSL2 / VMware. Enable recommandé.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "trustexecution": {
        "short": "Intel TXT (Trusted Execution Technology).",
        "detail": "Haute sécurité / measured boot. Enable seulement si requis.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "legacyorom": {
        "short": "Autorise les Option ROM legacy.",
        "detail": "Disable renforce Secure Boot. Enable pour anciens périphériques.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "uefinwstack": {
        "short": "Pile réseau UEFI (PXE IPv4/IPv6).",
        "detail": "Enable pour déploiement réseau.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "attemptlegacyboot": {
        "short": "Tente un boot legacy si UEFI échoue.",
        "detail": "Souvent Disable sur parc UEFI-only.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "bootorder": {
        "short": "Ordre de démarrage UEFI (disque, réseau, USB…).",
        "detail": "Format CCTK : uefitype,+hdd.1,+nic.1\n+ = activé, - = désactivé.\n\nOSD : réseau avant disque ; production : disque d’abord.",
        "values": [
            "uefitype,+hdd.1,+nic.1",
            "uefitype,+nic.1,+hdd.1",
            "uefitype,+hdd.1",
            "uefitype,+usb.1,+hdd.1"
        ]
    },
    "embnic1": {
        "short": "Carte réseau embarquée (avec ou sans PXE).",
        "detail": "enablewithpxe utile pour déploiement.",
        "values": [
            "enable",
            "disable",
            "enablewithpxe"
        ]
    },
    "wakeonlan": {
        "short": "Réveil réseau (WOL).",
        "detail": "enable / lanonly / wlanonly / disable selon politique MECM.",
        "values": [
            "enable",
            "disable",
            "lanonly",
            "wlanonly"
        ]
    },
    "wakeonlanbootovrd": {
        "short": "Override boot order lors d’un WOL.",
        "detail": "Utile pour forcer un chemin de boot au réveil.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "deepsleepctrl": {
        "short": "Contrôle deep sleep (S4/S5).",
        "detail": "Peut empêcher le WOL si trop agressif.",
        "values": [
            "enable",
            "disable",
            "s4s5only"
        ]
    },
    "blocks3": {
        "short": "Bloque l’état de veille S3.",
        "detail": "Enable force d’autres états (Modern Standby / S4).",
        "values": [
            "enable",
            "disable"
        ]
    },
    "fastboot": {
        "short": "Niveau de tests POST au démarrage.",
        "detail": "minimal = plus rapide ; thorough = plus de diagnostics.",
        "values": [
            "minimal",
            "thorough",
            "auto"
        ]
    },
    "numlock": {
        "short": "État NumLock au boot.",
        "detail": "Confort clavier.",
        "values": [
            "enable",
            "disable",
            "onbydefault"
        ]
    },
    "powerbutton": {
        "short": "Active le bouton power.",
        "detail": "Disable rare (kiosques verrouillés).",
        "values": [
            "enable",
            "disable"
        ]
    },
    "acpower": {
        "short": "Comportement à la reprise secteur.",
        "detail": "off / on / last (état avant coupure).",
        "values": [
            "off",
            "on",
            "last"
        ]
    },
    "usbfront": {
        "short": "Ports USB avant.",
        "detail": "Disable pour durcissement physique.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "usbrear": {
        "short": "Ports USB arrière.",
        "detail": "Disable pour réduire les ports exposés.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "usbports": {
        "short": "Ensemble des ports USB.",
        "detail": "Disable global — impact clavier/souris USB.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "usbpowership": {
        "short": "Alimentation / charge USB (PowerShare).",
        "detail": "Charge périphériques PC éteint / veille.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "sdcard": {
        "short": "Lecteur carte SD.",
        "detail": "readonly ou disable pour sécurité.",
        "values": [
            "enable",
            "disable",
            "readonly"
        ]
    },
    "bluetoothdevice": {
        "short": "Bluetooth matériel.",
        "detail": "Disable pour durcissement radio.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "wirelesslan": {
        "short": "Wi‑Fi embarqué.",
        "detail": "Disable sur postes filaires verrouillés.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "wirelesswwan": {
        "short": "WWAN / 4G-5G.",
        "detail": "Disable si non utilisé.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "wirelessswitchchanges": {
        "short": "Autorise l’utilisateur à changer le switch radio.",
        "detail": "deny = impose la politique IT.",
        "values": [
            "enable",
            "disable",
            "deny"
        ]
    },
    "camera": {
        "short": "Webcam intégrée.",
        "detail": "Disable pour confidentialité.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "microphone": {
        "short": "Microphone intégré.",
        "detail": "Disable pour confidentialité.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "smarterrors": {
        "short": "Alertes SMART disque au POST.",
        "detail": "Enable recommandé.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "embsataraid": {
        "short": "Mode contrôleur SATA (AHCI / RAID / ATA).",
        "detail": "AHCI standard Windows. RAID si Intel RST / volumes RAID.",
        "values": [
            "ahci",
            "raid",
            "ata"
        ]
    },
    "sata0": {
        "short": "Port SATA 0.",
        "detail": "Disable uniquement si le port n’est pas utilisé.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "sata1": {
        "short": "Port SATA 1.",
        "detail": "Idem SATA 0.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "sata2": {
        "short": "Port SATA 2.",
        "detail": "Idem SATA 0.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "sata3": {
        "short": "Port SATA 3.",
        "detail": "Idem SATA 0.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "hotdock": {
        "short": "Hot-dock / docking à chaud.",
        "detail": "Enable pour docks Latitude / Precision.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "adminsignature": {
        "short": "Signature admin BIOS.",
        "detail": "Fonction de sécurité Dell avancée.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "passwordlock": {
        "short": "Verrouille les changements de MDP sans Admin.",
        "detail": "Enable recommandé.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "strongpassword": {
        "short": "Impose un mot de passe Setup fort.",
        "detail": "Enable + passwordminlen.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "passwordminlen": {
        "short": "Longueur minimale du MDP Setup.",
        "detail": "Souvent 8+ en entreprise.",
        "values": [
            "4",
            "6",
            "8",
            "10",
            "12"
        ]
    },
    "pwdminage": {
        "short": "Âge minimal avant changement de MDP.",
        "detail": "0 = pas de contrainte.",
        "values": [
            "0",
            "1",
            "7",
            "30"
        ]
    },
    "asset": {
        "short": "Asset Tag (inventaire).",
        "detail": "Champ libre stocké dans le BIOS / SMBIOS.",
        "values": [
            ""
        ]
    },
    "assetownership": {
        "short": "Texte propriétaire / ownership.",
        "detail": "Affiché au boot selon modèles.",
        "values": [
            ""
        ]
    },
    "propowntag": {
        "short": "Property Ownership Tag.",
        "detail": "Tag propriétaire Dell.",
        "values": [
            ""
        ]
    },
    "sysname": {
        "short": "Nom système firmware.",
        "detail": "Identifiant libre.",
        "values": [
            ""
        ]
    },
    "bioscharacteristics": {
        "short": "Caractéristiques BIOS exposées.",
        "detail": "Laisser Enable sauf besoin spécifique.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "biosromsize": {
        "short": "Taille ROM BIOS (auto).",
        "detail": "Rarement modifié.",
        "values": [
            "auto"
        ]
    },
    "cpuxt2": {
        "short": "Execute Disable / NX bit.",
        "detail": "Enable recommandé (sécurité mémoire).",
        "values": [
            "enable",
            "disable"
        ]
    },
    "cpuid": {
        "short": "Exposition CPUID.",
        "detail": "Laisser Enable.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "logicproc": {
        "short": "Hyper-Threading / logical processors.",
        "detail": "Enable pour perfs ; Disable parfois en haute sécu.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "turbomode": {
        "short": "Intel Turbo Boost.",
        "detail": "Enable = perfs ; Disable = thermique stable.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "speedstep": {
        "short": "Intel SpeedStep (gestion fréquence).",
        "detail": "automatic recommandé.",
        "values": [
            "enable",
            "disable",
            "automatic"
        ]
    },
    "cstatesctrl": {
        "short": "États C-States CPU.",
        "detail": "Enable pour économie d’énergie.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "memfrequency": {
        "short": "Fréquence mémoire.",
        "detail": "auto recommandé.",
        "values": [
            "auto",
            "1600mhz",
            "2133mhz",
            "2400mhz",
            "2666mhz",
            "2933mhz",
            "3200mhz"
        ]
    },
    "integratedaudio": {
        "short": "Audio intégré.",
        "detail": "Disable pour kiosques silencieux.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "stealthmode": {
        "short": "Mode furtif (LEDs / bruit réduits).",
        "detail": "Selon modèles Dell.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "unmanagednic": {
        "short": "NIC non managée (PXE possible).",
        "detail": "enablewithpxe pour OSD.",
        "values": [
            "enable",
            "disable",
            "enablewithpxe"
        ]
    },
    "pxeboot": {
        "short": "Autorise le boot PXE.",
        "detail": "Enable pour MECM/OSD.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "httpboot": {
        "short": "Boot HTTP UEFI.",
        "detail": "Enable si infra HTTP boot.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "uefibootpathsecurity": {
        "short": "Sécurité des chemins de boot UEFI.",
        "detail": "alwaysexceptinternalhdd = compromis courant.",
        "values": [
            "alwaysexceptinternalhdd",
            "always",
            "never"
        ]
    },
    "autofanonac": {
        "short": "Ventilo toujours actif sur secteur.",
        "detail": "Enable = plus frais / plus bruyant.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "primarybatterycfg": {
        "short": "Profil de charge batterie.",
        "detail": "standard / express / primac selon usage dock.",
        "values": [
            "express",
            "standard",
            "primac"
        ]
    },
    "advbatterychargecfg": {
        "short": "Charge batterie avancée.",
        "detail": "Peut prolonger la durée de vie.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "peakshiftcfg": {
        "short": "Peak Shift (décalage conso secteur).",
        "detail": "Fonction entreprise énergie.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "dockwarn": {
        "short": "Avertissements docking.",
        "detail": "Enable pour alertes dock.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "fnlock": {
        "short": "Verrouillage touche Fn.",
        "detail": "Comportement clavier.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "fnlockmode": {
        "short": "Mode Fn Lock.",
        "detail": "secondary / enable selon préférence.",
        "values": [
            "enable",
            "disable",
            "secondary"
        ]
    },
    "touchpad": {
        "short": "Pavé tactile.",
        "detail": "Disable si souris exclusive.",
        "values": [
            "enable",
            "disable"
        ]
    },
    "keyboardillumination": {
        "short": "Rétroéclairage clavier.",
        "detail": "auto / niveaux / off.",
        "values": [
            "auto",
            "off",
            "25",
            "50",
            "75",
            "100"
        ]
    },
    "keyboardbacklighttimeoutac": {
        "short": "Timeout rétroéclairage sur secteur.",
        "detail": "Économie / confort.",
        "values": [
            "5s",
            "10s",
            "15s",
            "30s",
            "1m",
            "5m",
            "15m",
            "30m",
            "1h",
            "never"
        ]
    },
    "keyboardbacklighttimeoutbatt": {
        "short": "Timeout rétroéclairage sur batterie.",
        "detail": "Plus court = autonomie.",
        "values": [
            "5s",
            "10s",
            "15s",
            "30s",
            "1m",
            "5m",
            "15m",
            "30m",
            "1h",
            "never"
        ]
    }
};
