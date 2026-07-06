<?php

declare(strict_types=1);

namespace App\Module\Platform\Adapter\Llm;

/**
 * Model routing (prompt-engineering law): persona sheet and judge use the
 * best tier; chunking, synthesis and extraction use the standard tier.
 */
enum ModelTier: string
{
    case Best = 'best';
    case Standard = 'standard';
}
