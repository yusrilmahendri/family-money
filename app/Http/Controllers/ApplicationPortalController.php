<?php

namespace App\Http\Controllers;

use App\Exceptions\PlantationServiceException;
use App\Models\FinanceEntity;
use App\Services\ApplicationPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

class ApplicationPortalController extends Controller
{
    public function __construct(
        private readonly ApplicationPortalService $portal,
    ) {}

    public function index(): View
    {
        return view('portal.index', [
            'title' => 'Portal Arusku',
            'destinations' => $this->portal->destinations(),
            'accessName' => $this->portal->accessLabel(),
        ]);
    }

    public function handoff(FinanceEntity $financeEntity): RedirectResponse|Response
    {
        try {
            $url = $this->portal->issuePlantationHandoffUrl($financeEntity);
        } catch (InvalidArgumentException) {
            return response()->view('entity.access-invalid', [
                'title' => 'Akses tidak valid',
            ], 404);
        } catch (PlantationServiceException) {
            return redirect()
                ->route('home')
                ->with('danger', 'Management Kebun sedang tidak dapat dibuka. Coba lagi nanti.');
        }

        return redirect()
            ->away($url)
            ->header('Referrer-Policy', 'no-referrer');
    }
}
