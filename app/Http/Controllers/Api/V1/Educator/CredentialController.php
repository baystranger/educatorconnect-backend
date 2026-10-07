<?php

namespace App\Http\Controllers\Api\V1\Educator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Educator\CredentialRequest;
use App\Http\Resources\EducatorCredentialResource;
use App\Services\EducatorProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function __construct(private readonly EducatorProfileService $profiles) {}

    public function store(CredentialRequest $request): JsonResponse
    {
        return $this->created(new EducatorCredentialResource($this->profiles->addCredential($request->user(), $request->validated())), 'Credential added.');
    }

    public function update(CredentialRequest $request, int $credential): JsonResponse
    {
        return $this->ok(new EducatorCredentialResource($this->profiles->updateCredential($request->user(), $credential, $request->validated())), 'Credential updated.');
    }

    public function destroy(Request $request, int $credential): JsonResponse
    {
        $this->profiles->deleteCredential($request->user(), $credential);

        return $this->ok(null, 'Credential removed.');
    }
}
