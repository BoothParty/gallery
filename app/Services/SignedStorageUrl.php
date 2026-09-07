<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class SignedStorageUrl
{
    /**
     * Durée de validité d'un lien, en jours.
     *
     * Les liens circulent (partage, historique du navigateur) : ils doivent
     * survivre à l'usage réel d'une galerie sans rester valables pour
     * l'éternité une fois la party expirée. SigV4 refuse au-delà de sept
     * jours, et l'arrondi ci-dessous ajoute jusqu'à vingt-quatre heures :
     * six est donc le maximum utilisable.
     */
    public const TTL_DAYS = 6;

    /**
     * URL signée que le navigateur charge directement depuis le stockage.
     *
     * L'échéance est arrondie à la fin de journée : deux rendus successifs
     * produisent la même URL, donc le cache navigateur porte. Avec un
     * `now()->addDays()` la signature changerait à chaque requête et la
     * grille serait re-téléchargée à chaque scroll.
     */
    public static function for(string $path): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3-public');

        return $disk->temporaryUrl($path, now()->addDays(self::TTL_DAYS)->endOfDay());
    }
}
