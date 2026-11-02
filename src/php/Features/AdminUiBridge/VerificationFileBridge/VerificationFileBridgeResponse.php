<?php

namespace VeraTag\Features\AdminUiBridge\VerificationFileBridge;

final class VerificationFileBridgeResponse
{
    public function __construct(
        private readonly array $verification_file
    ) {
    }

    public function toArray(): array
    {
        return array(
            'verificationFile' => $this->verification_file,
        );
    }
}
