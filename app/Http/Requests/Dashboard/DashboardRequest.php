<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'entidad_id' => ['nullable', 'integer', Rule::exists('ENTIDAD', 'ENTI_ID')],
        ];
    }
}
