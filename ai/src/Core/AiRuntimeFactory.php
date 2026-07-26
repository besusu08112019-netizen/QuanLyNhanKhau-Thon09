<?php

declare(strict_types=1);

namespace Ai\Core;

use Ai\Business\HouseholdTool;
use Ai\Business\InsightTool;
use Ai\Business\ResidentTool;
use Ai\Business\StatisticsTool;

final class AiRuntimeFactory
{
    /**
     * @param array<string, object> $repositories
     */
    public static function toolRegistry(array $repositories = []): ToolRegistry
    {
        $registry = new ToolRegistry();

        $households = $repositories['household'] ?? self::newModel('App\\Models\\Household');
        if ($households !== null) {
            $registry->register(new HouseholdTool($households));
        }

        $citizens = $repositories['citizen'] ?? $repositories['resident'] ?? self::newModel('App\\Models\\Citizen');
        if ($citizens !== null) {
            $registry->register(new ResidentTool($citizens));
        }

        $statistics = $repositories['statistics'] ?? self::newModel('App\\Models\\Dashboard') ?? self::newModel('App\\Models\\PopulationStatistics');
        if ($statistics !== null) {
            $registry->register(new StatisticsTool($statistics));
        }

        $insights = $repositories['insight'] ?? self::newModel('App\\Models\\SystemInsight');
        if ($insights !== null) {
            $registry->register(new InsightTool($insights));
        }

        return $registry;
    }

    private static function newModel(string $class): ?object
    {
        if (!class_exists($class)) {
            return null;
        }

        return new $class();
    }
}
