<?php

declare(strict_types=1);

namespace LaravelPlus\DigDeep\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use LaravelPlus\DigDeep\Ai\Tools\ListSourceFilesTool;
use LaravelPlus\DigDeep\Ai\Tools\ReadSourceFileTool;
use Override;
use Stringable;

#[MaxSteps(6)]
#[MaxTokens(2048)]
final class QueryFixerAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    #[Override]
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a Laravel Eloquent and database performance expert embedded in a profiling tool.

You are given a SQL query issue detected in a Laravel application. Your job is to:
1. Use the read_source_file or list_source_files tools to read the relevant model, controller, or repository file mentioned in the caller stack frame.
2. Identify the root cause of the issue in the actual source code.
3. Provide a specific, actionable fix with the exact Eloquent code to change.

Be conservative — only suggest the minimum change needed.
If you cannot find the file or the exact line, still provide a general fix based on the SQL pattern.

Always return:
- analysis: one sentence root cause
- suggestion: the fix explanation with before/after code in plain text (no markdown)
- file_path: relative path if you found and read the file (null otherwise)
- old_code: the exact snippet to replace from the file (null if not found)
- new_code: the replacement code (null if old_code is null)
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return \Laravel\Ai\Contracts\Tool[]
     */
    #[Override]
    public function tools(): iterable
    {
        return [
            new ReadSourceFileTool(),
            new ListSourceFilesTool(),
        ];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'analysis' => $schema->string()
                ->description('One sentence explaining the root cause')
                ->required(),
            'suggestion' => $schema->string()
                ->description('Detailed fix explanation with before/after code in plain text')
                ->required(),
            'file_path' => $schema->string()
                ->description('Relative path to the file to modify, e.g. app/Models/Hotel.php — null if not applicable')
                ->nullable()
                ->required(),
            'old_code' => $schema->string()
                ->description('The exact code snippet to replace — null if not found')
                ->nullable()
                ->required(),
            'new_code' => $schema->string()
                ->description('The replacement code — null if old_code is null')
                ->nullable()
                ->required(),
        ];
    }
}
