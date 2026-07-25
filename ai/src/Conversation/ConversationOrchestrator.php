<?php

declare(strict_types=1);

namespace Ai\Conversation;

use Ai\Core\ConversationManager;
use Ai\Intent\IntentRecognizer;

final class ConversationOrchestrator
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly IntentRecognizer $intentRecognizer,
        private readonly ClarificationManager $clarificationManager = new ClarificationManager(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handleText(string $conversationId, string $text): array
    {
        $this->conversationManager->addUserMessage($conversationId, $text);

        $intent = $this->intentRecognizer->recognize($text);
        $memory = $this->conversationManager->context($conversationId)['memory'];
        $missing = $this->clarificationManager->missingFields($intent, is_array($memory) ? $memory : []);

        if (isset($intent->entities['module']) && is_string($intent->entities['module'])) {
            $this->conversationManager->remember($conversationId, 'last_module', $intent->entities['module']);
        }

        if ($missing !== []) {
            $prompt = $this->clarificationManager->prompt($intent, $missing);
            $this->conversationManager->setPendingClarification($conversationId, $intent->intent, $missing, $prompt);
            return [
                'status' => 'needs_clarification',
                'intent' => $intent->toArray(),
                'missing' => $missing,
                'prompt' => $prompt,
                'context' => $this->conversationManager->context($conversationId),
            ];
        }

        $this->conversationManager->clearPendingClarification($conversationId);
        return [
            'status' => 'recognized',
            'intent' => $intent->toArray(),
            'context' => $this->conversationManager->context($conversationId),
        ];
    }
}
