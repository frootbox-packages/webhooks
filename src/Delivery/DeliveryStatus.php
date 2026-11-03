<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

enum DeliveryStatus: string
{
    /** In the queue, not tried yet. */
    case Pending = 'pending';

    /** Failed, the queue tries again. */
    case Retrying = 'retrying';

    case Delivered = 'delivered';

    /** Failed in every attempt. */
    case Failed = 'failed';

    /** Not sent because the webhook is paused or gone. */
    case Skipped = 'skipped';

    public function done(): bool
    {
        return self::Delivered === $this || self::Skipped === $this;
    }
}
