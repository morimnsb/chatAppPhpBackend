<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FriendshipRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_user_id.required' => "Missing 'to_user_id' in request data.",
            'to_user_id.integer'  => "Invalid 'to_user_id' format.",
            'to_user_id.exists'   => "Target user does not exist.",
        ];
    }
}
