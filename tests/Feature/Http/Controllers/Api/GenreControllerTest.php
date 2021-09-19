<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\GenreController;
use App\Models\Genre;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Tests\Traits\TestSaves;
use Tests\Traits\TestValidations;
use Tests\Exceptions\TestException;
use Illuminate\Http\Request;

class GenreControllerTest extends TestCase
{
    use DatabaseMigrations, TestValidations, TestSaves;
    
    private $genre;
    private $category;
    private $relations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->genre = factory(Genre::class)->create();
        $this->category = factory(Category::class)->create();
        $this->relations = [
            'categories_id' => [$this->category->id]
        ];
    }

    public function testIndex()
    {
        $response = $this->get(route('genres.index'));

        $response
            ->assertStatus(200)
            ->assertJson([$this->genre->toArray()]);
    }

    public function testShow()
    {
        $response = $this->get(route('genres.show', $this->genre->id));

        $response
            ->assertStatus(200)
            ->assertJson($this->genre->toArray());
    }

    public function testInvalidationData()
    {
        $data = [
            'name' => '',
            'categories_id' => ''
        ];
        $this->assertInvalidationInStoreAction($data, 'required');
        $this->assertInvalidationInUpdateAction($data, 'required');

        $data = [
            'name' => str_repeat('a', 256)
        ];
        $this->assertInvalidationInStoreAction($data, 'max.string', ['max' => 255]);
        $this->assertInvalidationInUpdateAction($data, 'max.string', ['max' => 255]);
        
        $data = [
            'is_active' => 'a'
        ];
        $this->assertInvalidationInStoreAction($data, 'boolean');
        $this->assertInvalidationInUpdateAction($data, 'boolean');
    }

    public function testInvalidationCategoriesIdField()
    {
        $data = [
            'categories_id' => 'a'
        ];
        $this->assertInvalidationRelatedField($data, 'array');

        $data = [
            'categories_id' => [100]
        ];
        $this->assertInvalidationRelatedField($data, 'exists');
    }

    private function assertInvalidationRelatedField($data, $rule)
    {
        $this->assertInvalidationInStoreAction($data, $rule);
        $this->assertInvalidationInUpdateAction($data, $rule);
    }

    public function testStore()
    {
        $data =[
            'name' => 'test'
        ];
        $response = $this->assertStore($data + $this->relations, $data + ['is_active' => true, 'deleted_at' => null]);
        $response->assertJsonStructure([
            'created_at', 'updated_at'
        ]);

        $data = [
            'name' => 'test',
            'is_active' => false
        ];
        $this->assertStore($data + $this->relations, $data + ['is_active' => false]);
    }

    public function testUpdate()
    {
        $data = [
            'name' => 'test',
            'is_active' => true
        ];
        $response = $this->assertUpdate($data + $this->relations, $data + ['deleted_at' => null]);
        $response->assertJsonStructure([
            'created_at', 'updated_at'
        ]);
    }

    public function testRollbackStore()
    {
        $controller = \Mockery::mock(GenreController::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $controller
            ->shouldReceive('validate')
            ->withAnyArgs()
            ->andReturn([
                'name' => 'test'
            ]);

        $controller
            ->shouldReceive('rulesStore')
            ->withAnyArgs()
            ->andReturn([]);

        $controller
            ->shouldReceive('handleRelations')
            ->once()
            ->andThrow(new TestException());

        $request = \Mockery::mock(Request::class);

        try{
            $controller->store($request);
        } catch (TestException $exception) {
            $this->assertCount(1, Genre::all());
        }
    }

    public function testRollbackUpdate()
    {
        $updatedName = 'Update Rollback Test';

        $controller = \Mockery::mock(GenreController::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $controller
            ->shouldReceive('validate')
            ->withAnyArgs()
            ->andReturn(['title' => $updatedName]);

        $controller
            ->shouldReceive('rulesUpdate')
            ->withAnyArgs()
            ->andReturn([]);

        $controller
            ->shouldReceive('handleRelations')
            ->once()
            ->andThrow(new TestException());

        $request = \Mockery::mock(Request::class);

        try{
            $controller->update($request, $this->genre->id);
        } catch (TestException $exception) {
            $this->genre->refresh();
            $this->assertNotEquals($updatedName, $this->genre->name);
        }
    }

    public function testDestroy()
    {
        $response = $this->json('DELETE', route('genres.destroy', ['genre' => $this->genre->id]));
        $response->assertStatus(204);
        $this->assertNull(Genre::find($this->genre->id));
        $this->assertNotNull(Genre::withTrashed($this->genre->id));
    }

    protected function routeStore()
    {
        return route('genres.store');
    }

    protected function routeUpdate()
    {
        return route('genres.update', ['genre' => $this->genre->id]);
    }

    protected function model()
    {
        return Genre::class;
    }
}
