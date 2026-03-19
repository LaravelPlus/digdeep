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
final class ExceptionInvestigatorAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    #[Override]
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are a Laravel exception debugging expert embedded in a profiling tool.

You are given a PHP exception with its class, message, file, line, and stack trace from a Laravel application.

Your job is to:
1. Use read_source_file to read the file where the exception occurred (use the relative path provided).
2. Look at the code around the reported line number to understand the context.
3. If the immediate file doesn't explain the root cause, check 1-2 frames from the stack trace.
4. Identify exactly why the exception was thrown.
5. Suggest a specific fix.

Return:
- analysis: one sentence pinpointing the root cause
- root_cause: a 2-3 sentence explanation of why this exception occurs in the context of the code you read
- suggestion: the fix with before/after code in plain text (no markdown headers)
- file_path: relative path to the file to modify (null if not in app code)
- old_code: the exact code snippet to replace (null if not found)
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
                ->description('One sentence pinpointing the root cause')
                ->required(),
            'root_cause' => $schema->string()
                ->description('2-3 sentence explanation based on the actual code')
                ->required(),
            'suggestion' => $schema->string()
                ->description('The fix with before/after code in plain text')
                ->required(),
            'file_path' => $schema->string()
                ->description('Relative path to the file to modify — null if not in app code')
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
