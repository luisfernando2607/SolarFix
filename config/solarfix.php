<?php

return [
    /*
    | Respaldos: además de la carpeta local, una copia en otro disco (config/filesystems.php)
    | para no perderlos si falla el servidor. Ej.: BACKUP_DISK=offsite con una carpeta montada
    | de otro equipo, un NAS o un servicio en la nube.
    */
    'backup_disk' => env('BACKUP_DISK'),
    'backup_keep' => (int) env('BACKUP_KEEP', 14),
];
