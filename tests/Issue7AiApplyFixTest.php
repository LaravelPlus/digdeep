<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__.'/../src/Controllers/ApiController.php');

if ($source === false) {
    throw new RuntimeException('Unable to read ApiController.php');
}

$methodStart = strpos($source, 'public function aiApplyFix');
$methodEnd = strpos($source, '    private function callAiDirectly', $methodStart);

if ($methodStart === false || $methodEnd === false) {
    throw new RuntimeException('aiApplyFix method boundaries not found');
}

$method = substr($source, $methodStart, $methodEnd - $methodStart);
$guard = 'if (! class_exists(\\Laravel\\Ai\\Tools\\Request::class))';
$guardPosition = strpos($method, $guard);
$toolPosition = strpos($method, '$tool = new WriteSourceFileTool;');

if ($guardPosition === false) {
    throw new RuntimeException('aiApplyFix must guard the optional Laravel AI SDK');
}

if ($toolPosition === false || $guardPosition > $toolPosition) {
    throw new RuntimeException('aiApplyFix must check the SDK before constructing its tool');
}

if (! str_contains($method, "response()->json(['error' => 'No AI configured. Set DIGDEEP_AI_KEY in your .env file.'], 501)")) {
    throw new RuntimeException('aiApplyFix must preserve the 501 response used by other AI endpoints');
}

echo "Issue #7 regression passed\n";
