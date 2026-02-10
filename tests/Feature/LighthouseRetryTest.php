<?php

use App\Services\LighthouseService;
use Illuminate\Support\Facades\Process;

it('throws on chrome connection failure so the job can retry', function () {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: "Unable to connect to Chrome\n",
            exitCode: 1,
        ),
    ]);

    $service = new LighthouseService;

    expect(fn () => $service->run('https://example.com'))
        ->toThrow(RuntimeException::class, 'Chrome unavailable');
});

it('returns null scores on non-chrome process failures', function () {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: "PROTOCOL_TIMEOUT\n",
            exitCode: 1,
        ),
    ]);

    $service = new LighthouseService;
    $result = $service->run('https://example.com');

    expect($result)->toBe(['performance' => null, 'accessibility' => null]);
});
