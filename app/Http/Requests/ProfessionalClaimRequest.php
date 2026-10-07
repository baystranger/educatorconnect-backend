<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class ProfessionalClaimRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'business_domain_email'=>'nullable|email',
            'business_phone'=>'nullable|string|max:30',
            'credential_information'=>'nullable|string|max:5000',
            'supporting_document'=>'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240'
        ];
    }
}
