<?php

namespace App\DTO;

use App\Http\Requests\V1\UpdateProfileRequest;
use Illuminate\Http\UploadedFile;

final readonly class UpdateProfileData
{
    /**
     * Create a new class instance.
     */
    private function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $password,
        public ?UploadedFile $profilePicture,
    ) {}

    public static function fromRequest(UpdateProfileRequest $request): self
    {
        return new self(
            $request->validated('first_name'),
            $request->validated('last_name'),
            $request->validated('email'),
            $request->validated('password'),
            $request->file('profile_picture'),
        );
    }
}
