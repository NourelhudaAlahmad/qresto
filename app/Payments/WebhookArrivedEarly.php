<?php

namespace App\Payments;

use RuntimeException;

/**
 * A gateway event for an order we placed whose payment does not carry the
 * intent id yet: checkout is still recording the gateway's answer. The
 * webhook answers "try again" so the gateway re-sends it a moment later,
 * instead of the event being recorded as handled and lost.
 */
final class WebhookArrivedEarly extends RuntimeException {}
