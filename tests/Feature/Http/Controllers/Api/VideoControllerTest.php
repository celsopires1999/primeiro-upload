<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\VideoController;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use Tests\Traits\TestSaves;
use Tests\Traits\TestValidations;
use Tests\Exceptions\TestException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class VideoControllerTest extends TestCase
{
    use DatabaseMigrations, TestValidations, TestSaves;

    private $video;
    private $sendData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->video = factory(Video::class)->create(['opened' => false]);
        $this->sendData = [
            'title' => 'title',
            'description' => 'description',
            'year_launched' => 2010,
            'rating' => Video::RATING_LIST[0],
            'duration' => 90,
        ];
    }

    public function testIndex()
    {
        $response = $this->get(route('videos.index'));

        $response
            ->assertStatus(200)
            ->assertJson([$this->video->toArray()]);
    }

    public function testShow()
    {
        $response = $this->get(route('videos.show', $this->video->id));

        $response
            ->assertStatus(200)
            ->assertJson($this->video->toArray());
    }

    public function testInvalidationRequired()
    {
        $data = [
            'title' => '',
            'description' => '',
            'year_launched' => '',
            'rating' => '',
            'duration' => '',
            'categories_id' => '',
            'genres_id' => '',
        ];
        $this->assertInvalidationInStoreAction($data, 'required');
        $this->assertInvalidationInUpdateAction($data, 'required');        
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

        $category = factory(Category::class)->create();
        $category->delete();
        $data = [
            'categories_id' => [$category->id]
        ];
        $this->assertInvalidationRelatedField($data, 'exists');
    }

    public function testInvalidationGenresIdField()
    {
        $data = [
            'genres_id' => 'a'
        ];
        $this->assertInvalidationRelatedField($data, 'array');

        $data = [
            'genres_id' => [100]
        ];
        $this->assertInvalidationRelatedField($data, 'exists');

        $genre = factory(Genre::class)->create();
        $genre->delete();
        $data = [
            'genres_id' => [$genre->id]
        ];
        $this->assertInvalidationRelatedField($data, 'exists');
    }

    private function assertInvalidationRelatedField($data, $rule)
    {
        $this->assertInvalidationInStoreAction($data, $rule);
        $this->assertInvalidationInUpdateAction($data, $rule);
    }

    public function testInvalidationMax()
    {
        $data = [
            'title' => str_repeat('a', 256)
        ];
        $this->assertInvalidationInStoreAction($data, 'max.string', ['max' => 255]);
        $this->assertInvalidationInUpdateAction($data, 'max.string', ['max' => 255]);
    }

    public function testInvalidationInteger()
    {
        $data = [
            'duration' => 'a'
        ];
        $this->assertInvalidationInStoreAction($data, 'integer');
        $this->assertInvalidationInUpdateAction($data, 'integer');
    }

    public function testInvalidationYearLaunchedField()
    {
        $data = [
            'year_launched' => 'a'
        ];
        $this->assertInvalidationInStoreAction($data, 'date_format', ['format' => 'Y']);
        $this->assertInvalidationInUpdateAction($data,'date_format', ['format' => 'Y']);          
    }

    public function testInvalidationOpenedField()
    {
        $data = [
            'opened' => 'a'
        ];
        $this->assertInvalidationInStoreAction($data, 'boolean');
        $this->assertInvalidationInUpdateAction($data,'boolean');          
    }

    public function testInvalidationRatingField()
    {
        $data = [
            'rating' => 0
        ];
        $this->assertInvalidationInStoreAction($data, 'in');
        $this->assertInvalidationInUpdateAction($data,'in');          
    }

    public function testInvalidationVideo()
    {
        $file = UploadedFile::fake()->create('video_file.xyz');
        $reponse = $this->json('POST', $this->routeStore(), [
            'video_file' => $file
        ]);
        $this->assertInvalidationFields($reponse, ['video_file'], 'mimetypes', ['values' => 'video/mp4']);

        $file = UploadedFile::fake()->create('video_file.mp4')->size(13);
        $reponse = $this->json('POST', $this->routeStore(), [
            'video_file' => $file
        ]);
        $this->assertInvalidationFields($reponse, ['video_file'], 'max.file', ['max' => 12]);

    }

    public function testSave()
    {
        $category = factory(Category::class)->create();
        $genre = factory(Genre::class)->create();
        $genre->categories()->sync([$category->id]);

        $relations = [
            'categories_id' => [$category->id],
            'genres_id' => [$genre->id]
        ];

        $data = [
            [
                'send_data' => $this->sendData + $relations,
                'test_data' => $this->sendData + ['opened' => false]
            ],
            [
                'send_data' => $this->sendData + ['opened' => true] + $relations,
                'test_data' => $this->sendData + ['opened' => true]
            ],
            [
                'send_data' => $this->sendData + ['rating' => Video::RATING_LIST[1]] + $relations,
                'test_data' => $this->sendData + ['rating' => Video::RATING_LIST[1]]
            ],
        ];

        foreach ($data as $key => $value) {
            $response = $this->assertStore(
                $value['send_data'],
                $value['test_data'] + ['deleted_at' => null]
            );
            $response->assertJsonStructure([
                'created_at',
                'updated_at'
            ]);
            $this->assertHasCategory(
                $response->json('id'),
                $value['send_data']['categories_id'][0]
            );
            $this->assertHasGenre(
                $response->json('id'),
                $value['send_data']['genres_id'][0]
            );

            $response = $this->assertUpdate(
                $value['send_data'],
                $value['test_data'] + ['deleted_at' => null]
            );
            $response->assertJsonStructure([
                'created_at',
                'updated_at'
            ]);
            $this->assertHasCategory(
                $response->json('id'),
                $value['send_data']['categories_id'][0]
            );
            $this->assertHasGenre(
                $response->json('id'),
                $value['send_data']['genres_id'][0]
            );
        }
    }

    public function testSaveFile()
    {
        $category = factory(Category::class)->create();
        $genre = factory(Genre::class)->create();
        $genre->categories()->sync([$category->id]);

        $relations = [
            'categories_id' => [$category->id],
            'genres_id' => [$genre->id]
        ];

        \Storage::fake();
        $file = UploadedFile::fake()->create('video_file.mp4');

        $response = $this->json('POST', $this->routeStore(), 
            $this->sendData + $relations + ['video_file' => $file]
        );
        $response->assertStatus(201);
        $id = $response->json('id');
        \Storage::assertExists("$id/{$file->hashName()}");
    }

    protected function assertHasCategory($videoId, $categoryId)
    {
        $this->assertDatabaseHas('category_video', [
            'video_id' => $videoId,
            'category_id' => $categoryId
        ]);
    }

    protected function assertHasGenre($videoId, $genreId)
    {
        $this->assertDatabaseHas('genre_video', [
            'video_id' => $videoId,
            'genre_id' => $genreId
        ]);
    }

    public function testSyncCategories()
    {
        $categoriesId = factory(Category::class, 3)->create()->pluck('id')->toArray();
        $genre = factory(Genre::class)->create();
        $genre->categories()->sync($categoriesId);
        $genreId = $genre->id;

        $response = $this->json('POST', 
            $this->routeStore(), 
            $this->sendData + [
                'genres_id' => [$genreId],
                'categories_id' => [$categoriesId[0]]
            ]
        );

        $this->assertDatabaseHas('category_video', [
            'category_id' => $categoriesId[0],
            'video_id' => $response->json('id'),
        ]);

        $response = $this->json('PUT', 
            route('videos.update', ['video' => $response->json('id')]), 
            $this->sendData + [
                'genres_id' => [$genreId],
                'categories_id' => [$categoriesId[1], $categoriesId[2]]
            ]
        );

        $this->assertDatabaseMissing('category_video', [
            'category_id' => $categoriesId[0],
            'video_id' => $response->json('id'),
        ]);
        $this->assertDatabaseHas('category_video', [
            'category_id' => $categoriesId[1],
            'video_id' => $response->json('id'),
        ]);
        $this->assertDatabaseHas('category_video', [
            'category_id' => $categoriesId[2],
            'video_id' => $response->json('id'),
        ]);
    }

    public function testSyncGenres()
    {
        $genres = factory(Genre::class, 3)->create();
        $genresId = $genres->pluck('id')->toArray();
        $categoryId = factory(Category::class)->create()->id;
        $genres->each(function($genre) use($categoryId){
            $genre->categories()->sync($categoryId);
        });

        $response = $this->json('POST', 
            $this->routeStore(), 
            $this->sendData + [
                'categories_id' => [$categoryId],
                'genres_id' => [$genresId[0]],
            ]
        );

        $this->assertDatabaseHas('genre_video', [
            'genre_id' => $genresId[0],
            'video_id' => $response->json('id'),
        ]);

        $response = $this->json('PUT', 
            route('videos.update', ['video' => $response->json('id')]), 
            $this->sendData + [
                'categories_id' => [$categoryId],
                'genres_id' => [$genresId[1], $genresId[2]]
            ]
        );

        $this->assertDatabaseMissing('genre_video', [
            'genre_id' => $genresId[0],
            'video_id' => $response->json('id'),
        ]);
        $this->assertDatabaseHas('genre_video', [
            'genre_id' => $genresId[1],
            'video_id' => $response->json('id'),
        ]);
        $this->assertDatabaseHas('genre_video', [
            'genre_id' => $genresId[2],
            'video_id' => $response->json('id'),
        ]);
    }

    // public function testRollbackStore()
    // {
    //     $controller = \Mockery::mock(VideoController::class)
    //         ->makePartial()
    //         ->shouldAllowMockingProtectedMethods();

    //     $controller
    //         ->shouldReceive('validate')
    //         ->withAnyArgs()
    //         ->andReturn($this->sendData);

    //     $controller
    //         ->shouldReceive('rulesStore')
    //         ->withAnyArgs()
    //         ->andReturn([]);

    //     $controller
    //         ->shouldReceive('handleRelations')
    //         ->once()
    //         ->andThrow(new TestException());

    //     $request = \Mockery::mock(Request::class);

    //     $request
    //         ->shouldReceive('get')
    //         ->withAnyArgs()
    //         ->andReturnNull();

    //     $hasError = false;
    //     try{
    //         $controller->store($request);
    //     } catch (TestException $exception) {
    //         $this->assertCount(1, Video::all());
    //         $hasError = true;
    //     }
    //     $this->assertTrue($hasError);
    // }

    // public function testRollbackUpdate()
    // {
    //     $updatedTitle = 'Update Rollback Test';

    //     $controller = \Mockery::mock(VideoController::class)
    //         ->makePartial()
    //         ->shouldAllowMockingProtectedMethods();

    //     $controller
    //         ->shouldReceive('validate')
    //         ->withAnyArgs()
    //         ->andReturn(['title' => $updatedTitle]);

    //     $controller
    //         ->shouldReceive('rulesUpdate')
    //         ->withAnyArgs()
    //         ->andReturn([]);

    //     $controller
    //         ->shouldReceive('handleRelations')
    //         ->once()
    //         ->andThrow(new TestException());

    //     $request = \Mockery::mock(Request::class);

    //     $request
    //         ->shouldReceive('get')
    //         ->withAnyArgs()
    //         ->andReturnNull();
            
    //     $hasError = false;
    //     try{
    //         $controller->update($request, $this->video->id);
    //     } catch (TestException $exception) {
    //         $this->video->refresh();
    //         $this->assertNotEquals($updatedTitle, $this->video->title);
    //         $hasError = true;
    //     }
    //     $this->assertTrue($hasError);
    // }
    
    public function testDestroy()
    {
        $response = $this->json('DELETE', route('videos.destroy', ['video' => $this->video->id]));
        $response->assertStatus(204);
        $this->assertNull(Video::find($this->video->id));
        $this->assertNotNull(Video::withTrashed($this->video->id));
    }

    protected function routeStore()
    {
        return route('videos.store');
    }

    protected function routeUpdate()
    {
        return route('videos.update', ['video' => $this->video->id]);
    }

    protected function model()
    {
        return Video::class;
    }
}
