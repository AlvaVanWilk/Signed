<?php
declare(strict_types=1);

namespace Signed;

/** Merkt sich, welches Label angemeldet ist. */
interface Session
{
    public function labelId(): ?int;

    public function setLabelId(?int $id): void;
}
