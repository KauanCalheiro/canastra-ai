<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decks' => ['required', 'integer', 'in:1,2,3'],
            'targetScore' => ['required', 'integer', 'min:100'],
            'players' => ['required', 'array'],
            'players.*' => ['string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $count = count($this->input('players', []));
            if ($count !== 2 && $count !== 4) {
                $validator->errors()->add('players', 'É necessário informar 2 ou 4 jogadores.');
            }
        });
    }
}
