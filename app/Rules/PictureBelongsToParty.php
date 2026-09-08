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

    /**
     * A picture the host has hidden is out of a guest's reach: they never
     * received its id, and naming one anyway must not hand the file over.
     */
    public function __construct(
        private readonly Party $party,
        private readonly bool $includeHidden = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->partyPictureIds ??= $this->party->pictures()
            ->unless($this->includeHidden, fn ($query) => $query->visible())
            ->pluck('id')
            ->all();

        if (! in_array((int) $value, $this->partyPictureIds, true)) {
            $fail('The selected picture could not be found.');
        }
    }
}
