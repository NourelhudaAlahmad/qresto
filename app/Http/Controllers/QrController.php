<?php

namespace App\Http\Controllers;

use App\Services\TableSessionService;
use Inertia\Inertia;
use Inertia\Response;

class QrController extends Controller
{
    public function __construct(
        private readonly TableSessionService $tableSessionService,
    ) {}

    public function scan(string $qrToken): Response
    {
        $session = $this->tableSessionService->startFromQrToken($qrToken);

        return Inertia::render('menu', [
            'restaurant' => [
                'name' => $session->restaurant->name,
            ],
            'table' => [
                'number' => $session->table->number,
            ],
            'session' => [
                'token' => $session->token,
                'active' => $session->isActive(),
            ],
        ]);
    }
}
