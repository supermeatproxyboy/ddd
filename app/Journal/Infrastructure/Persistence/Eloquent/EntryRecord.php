<?php

namespace App\Journal\Infrastructure\Persistence\Eloquent;

use App\Journal\Domain\Entries\EntryKind;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property EntryKind $kind
 * @property CarbonInterface $occurred_on
 * @property string $body
 * @property string|null $reflection
 */
#[Fillable(['title', 'kind', 'occurred_on', 'body', 'reflection'])]
class EntryRecord extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    protected $table = 'entries';

    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => EntryKind::class,
            'occurred_on' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
