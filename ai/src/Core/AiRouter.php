<?php

declare(strict_types=1);

namespace Ai\Core;

use Ai\Logging\AiLogger;

final class AiRouter
{
    public function __construct(
        private readonly AiConfig $config,
        private readonly ContextManager $contextManager,
        private readonly ConversationManager $conversationManager,
        private readonly ToolRegistry $toolRegistry,
        private readonly AiLogger $logger,
    ) {
    }

    public function route(AiRequest $request): AiResponse
    {
        $this->logger->info('ai.request.received', [
            'conversation_id' => $request->conversationId,
            'user_id' => $request->userId,
            'metadata' => $request->metadata,
        ]);

        $this->conversationManager->addMessage($request->conversationId, 'user', $request->message);

        if (!$this->config->enabled()) {
            return AiResponse::disabled();
        }

        return new AiResponse('ready', 'AI Router is ready for future phases.', [
            'context' => $this->contextManager->build($request),
            'tools' => $this->toolRegistry->describe(),
        ]);
    }
}

