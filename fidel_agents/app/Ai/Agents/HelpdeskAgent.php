<?php

namespace App\Ai\Agents;

class HelpdeskAgent
{
    public function handle(array $input): array
    {
        return [
            'message' => 'You have reached the support agent. Please follow the guidance below.',
            'helpdesk_issue' => $input['question'] ?? $input['help'] ?? 'general support request',
            'resolution' => 'Review the request details and provide the appropriate support path.',
            'confidence' => 0.65,
        ];
    }
}
