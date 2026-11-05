<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Event;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\Http\Request\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DiscoverEventsPublicRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $merges = [];
        if ($this->has('free')) {
            $val = filter_var($this->query('free'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $merges['free'] = $val;
            }
        }
        if ($this->has('include_online')) {
            $val = filter_var($this->query('include_online'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $merges['include_online'] = $val;
            }
        }
        if (! empty($merges)) {
            $this->merge($merges);
        }
    }

    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['nullable', 'numeric', 'min:0.5', 'max:100'],
            'include_online' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', Rule::in(EventCategory::valuesArray())],
            'when' => ['nullable', 'string', Rule::in(['today', 'tonight', 'weekend']), 'prohibits:date_from,date_to'],
            'date_from' => ['nullable', 'date', 'prohibits:when'],
            'date_to' => ['nullable', 'date', 'prohibits:when', 'after_or_equal:date_from'],
            'tz' => ['nullable', 'string', 'timezone'],
            'free' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(['distance', 'date', 'relevance'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sort = $this->query('sort');
            $lat = $this->query('lat');
            $lng = $this->query('lng');

            if ($sort === 'distance' && ($lat === null || $lng === null)) {
                $validator->errors()->add('sort', __('Distance sort requires lat and lng coordinates.'));
            }
        });
    }
}
