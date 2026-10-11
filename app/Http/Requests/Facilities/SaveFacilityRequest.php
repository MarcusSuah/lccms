<?php

namespace App\Http\Requests\Facilities;

use App\Enums\Facilities\OperatingStatus;
use App\Enums\Facilities\Ownership;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Facilities\FacilityType;
use App\Services\Facilities\FacilityDetailRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFacilityRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = FacilityType::query()->find($this->integer('facility_type_id'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'facility_type_id' => ['required', 'integer', Rule::exists('facility_types', 'id')->where('is_active', true)],
            'ownership' => ['required', Rule::enum(Ownership::class)],
            'operating_status' => ['required', Rule::enum(OperatingStatus::class)],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')],
            'block_id' => ['nullable', 'integer', Rule::exists('blocks', 'id')],
            'address_description' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'established_year' => ['nullable', 'integer', 'between:1800,'.now()->year],
            'details' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'is_public' => ['boolean'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
            ...($type ? FacilityDetailRules::for($type->category) : []),
        ];
    }
}
