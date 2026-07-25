<?php

declare(strict_types=1);

namespace Ai\Core;

final class ContextManager
{
    /**
     * @param array<string, mixed> $baseContext
     */
    public function build(AiRequest $request, array $baseContext = []): array
    {
        return array_merge($baseContext, [
            'conversation_id' => $request->conversationId,
            'user_id' => $request->userId,
            'metadata' => $request->metadata,
        ]);
    }
}

