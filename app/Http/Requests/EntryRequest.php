<?php

namespace App\Http\Requests;

use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Domain\Entries\JournalDate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title'       => [ 'required', 'string', 'max:'.EntryContent::TITLE_LIMIT ],
            'kind'        => [ 'required', Rule::enum(EntryKind::class) ],
            'occurred_on' => [ 'required', 'date_format:Y-m-d', 'before_or_equal:today' ],
            'body'        => [ 'required', 'string', 'max:'.EntryContent::BODY_LIMIT ],
            'reflection'  => [ 'nullable', 'string', 'max:'.EntryContent::REFLECTION_LIMIT ],
        ];
    }

    public function content(): EntryContent
    {
        return new EntryContent(
            $this->string('title')->toString(),
            EntryKind::from($this->string('kind')->toString()),
            JournalDate::fromString($this->string('occurred_on')->toString()),
            $this->string('body')->toString(),
            $this->filled('reflection') ? $this->string('reflection')->toString() : null,
        );
    }
}
