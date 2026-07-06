<?php

declare(strict_types=1);

namespace App\Module\TestBench\Contract;

/**
 * Brick contract — the test orchestrator (§2.13). The contract IS the one of
 * the delivered V0 script (banc-essai/): input {prompt, chunks, scenario
 * (script|persona), grid} → output {transcript, verdict positif|doute|negatif,
 * notes, par_message}. The V0 YAML scenarios are the canonical format.
 * Consumers: internal CLI (exists), Avatar workshop, template preview, third
 * parties (phase 2). Designed for N personas, V1 ships 1 persona + 1 judge.
 */
interface TestBenchInterface
{
    /** @param array<string, mixed> $input {prompt, chunks, scenario, grille}
     *  @return array<string, mixed> {transcript, verdict, notes, par_message} */
    public function run(array $input): array;
}
