<?php

namespace App\Http\Requests;

use App\Journal\Application\Queries\JournalFilters;
use App\Journal\Domain\Entries\EntryKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(EntryKind::class)],
            'month' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filters(): JournalFilters
    {
        return new JournalFilters(
            $this->string('q')->toString(),
            $this->filled('kind') ? EntryKind::from($this->string('kind')->toString()) : null,
            $this->filled('month') ? $this->string('month')->toString() : null,
        );
    }
}
