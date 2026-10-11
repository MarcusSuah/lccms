<?php

namespace App\Http\Requests\Access;

use App\Enums\Access\RoleName;
use App\Enums\Geography\GeoLevel;
use App\Models\User;
use App\Services\Access\RoleAssignmentRules;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Geography\GeoScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for creating and updating staff accounts. Authorization is
 * handled by UserPolicy in the controller.
 */
class SaveUserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');

        $assignable = array_map(
            fn (RoleName $role): string => $role->value,
            app(RoleAssignmentRules::class)->assignableBy($this->user()),
        );

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::in($assignable)],
            'scopes' => ['array', 'max:50'],
            'scopes.*.level' => ['required', Rule::enum(GeoLevel::class)],
            'scopes.*.area_id' => ['required', 'integer', 'min:1'],
            'scopes.*.starts_on' => ['nullable', 'date'],
            'scopes.*.ends_on' => ['nullable', 'date', 'after_or_equal:scopes.*.starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.*.in' => 'You are not allowed to grant this role.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $resolver = app(GeoAreaResolver::class);
                $geoScope = app(GeoScope::class);

                foreach ($this->input('scopes', []) as $index => $scope) {
                    $area = $resolver->find(GeoLevel::from($scope['level']), (int) $scope['area_id']);

                    if ($area === null) {
                        $validator->errors()->add("scopes.{$index}.area_id", 'The selected area does not exist.');

                        continue;
                    }

                    if (! $geoScope->allowsPath($this->user(), $resolver->fullPath($area))) {
                        $validator->errors()->add("scopes.{$index}.area_id", 'You can only grant access to areas within your own assignment.');
                    }
                }

                if (! $geoScope->for($this->user())->national && $this->input('scopes', []) === []) {
                    $validator->errors()->add('scopes', 'Assign at least one geographic area.');
                }
            },
        ];
    }
}
