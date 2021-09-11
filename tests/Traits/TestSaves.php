<?php
declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Foundation\Testing\TestResponse;

trait TestSaves
{
    protected function assertStore(array $sendData, array $testDatabase, array $testJasonData = null): TestResponse
    {
        /**@var TestResponse $response */
        $response = $this->json('POST', $this->routeStore(), $sendData);
        if ($response->status() !== 201) {
            throw new \Exception("Response status must be 201, given {$response->status()}:\n{$response->content()}");
        }
        $model = $this->model();
        $table = (new $model)->getTable();
        $testResponse = $testJasonData ?? $testDatabase;
        $this->assertDatabaseHas($table, $testResponse + ['id' => $response->json('id')]);
        return $response;
    }
}