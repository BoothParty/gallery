<?php

namespace App\Rules;

use App\Models\Party;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PictureBelongsToParty implements ValidationRule
{
    /**
     * Ids of the party's pictures, fetched once and reused for every item in
     * the array being validated instead of issuing one query per id.
     *
     * @var list<int>|null
     */
    private ?array $partyPictureIds = null;

    public function __construct(private readonly Party $party) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->partyPictureIds ??= $this->party->pictures()->pluck('id')->all();

        if (! in_array((int) $value, $this->partyPictureIds, true)) {
            $fail('La photo sélectionnée est introuvable.');
        }
    }
}
