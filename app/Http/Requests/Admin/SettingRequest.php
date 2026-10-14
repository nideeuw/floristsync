<?php

namespace App\Http\Requests\Admin;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'store_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'whatsapp_number' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'shipping_origin_id' => ['nullable', 'integer', 'min:1'],
            'shipping_origin_label' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];

        foreach (SettingService::DAYS as $day) {
            $rules["hours.$day.closed"] = ['nullable', 'boolean'];
            $rules["hours.$day.open"] = ["exclude_if:hours.$day.closed,1", "required_without:hours.$day.closed", 'nullable', 'date_format:H:i'];
            $rules["hours.$day.close"] = ["exclude_if:hours.$day.closed,1", "required_without:hours.$day.closed", 'nullable', 'date_format:H:i', "after:hours.$day.open"];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'whatsapp_number.regex' => 'The WhatsApp number may only contain digits, spaces, + and -.',
            'phone.regex' => 'The phone may only contain digits, spaces, + - and parentheses.',
            'hours.*.open.required_without' => 'Set an opening time or mark the day as closed.',
            'hours.*.close.required_without' => 'Set a closing time or mark the day as closed.',
            'hours.*.close.after' => 'The closing time must be later than the opening time.',
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        $hours = [];

        foreach (SettingService::DAYS as $day) {
            $closed = $this->boolean("hours.$day.closed");

            $hours[$day] = [
                'closed' => $closed,
                'open' => $closed ? null : $this->input("hours.$day.open"),
                'close' => $closed ? null : $this->input("hours.$day.close"),
            ];
        }

        $whatsapp = preg_replace('/\D+/', '', $data['whatsapp_number']);

        if (str_starts_with($whatsapp, '0')) {
            $whatsapp = '62' . substr($whatsapp, 1);
        }

        return [
            'store_name' => $data['store_name'],
            'phone' => $data['phone'] ?? null,
            'whatsapp_number' => $whatsapp,
            'address' => $data['address'] ?? null,
            'shipping_origin_id' => $data['shipping_origin_id'] ?? null,
            'shipping_origin_label' => $data['shipping_origin_label'] ?? null,
            'store_latitude' => $data['latitude'] ?? null,
            'store_longitude' => $data['longitude'] ?? null,
            'opening_hours' => $hours,
        ];
    }
}
