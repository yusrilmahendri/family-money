<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

const PORTAL_PLANTATION_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const PORTAL_PLANTATION_URL = 'http://plantation.test/access/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const PORTAL_PLANTATION_PUBLIC_ID = '01PORTALPLANTATIONENTITY0001';

function portalActivatePlantation(
    App\Models\FinanceEntity $entity,
    App\Enums\PlantationIntegrationStatus $status = App\Enums\PlantationIntegrationStatus::ACTIVE,
): App\Models\PlantationIntegration {
    return App\Models\PlantationIntegration::query()->create([
        'finance_entity_id' => $entity->id,
        'plantation_entity_public_id' => PORTAL_PLANTATION_PUBLIC_ID.'-'.$entity->id,
        'status' => $status,
    ]);
}

function portalFakeHandoff(string $accessUrl = PORTAL_PLANTATION_URL): void
{
    Illuminate\Support\Facades\Http::fake(function (Illuminate\Http\Client\Request $request) use ($accessUrl) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

        if ($request->method() === 'POST' && preg_match('#/access-links$#', $path)) {
            return Illuminate\Support\Facades\Http::response([
                'data' => [
                    'id' => 21,
                    'label' => $request['label'] ?? 'Finance portal',
                    'token' => PORTAL_PLANTATION_TOKEN,
                    'access_url' => $accessUrl,
                    'is_active' => true,
                ],
            ], 201);
        }

        return Illuminate\Support\Facades\Http::response(['message' => 'Unexpected plantation request'], 500);
    });
}

function newPlantationBudgetHttpState(): object
{
    return (object) [
        'status' => 200,
        'body' => [
            'data' => [
                'public_id' => '01PLANTALLOCTEST000000001',
                'status' => 'ACTIVE',
            ],
        ],
        'throw' => null,
    ];
}

function fakeControllablePlantationBudgetHttp(object $state): void
{
    Illuminate\Support\Facades\Http::fake(function (Illuminate\Http\Client\Request $request) use ($state) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
        $method = $request->method();

        if ($method === 'POST' && $path === '/api/internal/plantation-entities') {
            return Illuminate\Support\Facades\Http::response([
                'data' => [
                    'public_id' => '01PLANTATIONENTITYTEST00001',
                    'name' => $request['name'] ?? 'Kebun',
                    'finance_entity_public_id' => $request['finance_entity_public_id'] ?? null,
                ],
            ], 201);
        }

        if ($method === 'PUT' && str_contains($path, '/budget-allocations/')) {
            if ($state->throw instanceof Throwable) {
                throw $state->throw;
            }

            return Illuminate\Support\Facades\Http::response($state->body, $state->status);
        }

        return Illuminate\Support\Facades\Http::response(['data' => ['ok' => true, 'is_active' => true]]);
    });
}

function collectPlantationLogWarnings(): \ArrayObject
{
    $logs = new \ArrayObject;
    Illuminate\Support\Facades\Log::listen(function (Illuminate\Log\Events\MessageLogged $event) use ($logs) {
        if ($event->level === 'warning' && str_starts_with((string) $event->message, 'plantation.')) {
            $logs[] = [
                'message' => $event->message,
                'context' => $event->context,
            ];
        }
    });

    return $logs;
}

function assertSafePlantationLogs(\ArrayObject $logs): void
{
    $dump = json_encode($logs->getArrayCopy(), JSON_UNESCAPED_UNICODE) ?: '';

    expect($dump)->not->toContain('testing-plantation-service-token')
        ->and($dump)->not->toContain('Authorization')
        ->and($dump)->not->toContain('Bearer ')
        ->and($dump)->not->toContain('insert into')
        ->and($dump)->not->toContain('SQLSTATE[');
}
