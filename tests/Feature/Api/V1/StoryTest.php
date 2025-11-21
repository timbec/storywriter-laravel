<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StoryTest extends TestCase
{
    use RefreshDatabase; 

    public function test_user_can_get_list_of_stories(): void 
    {
        //Arrange: create 2 fake stories
        $stories = Story::factory()->count(2)->create(); 

        //Act 
        $response = $this->getJson('/api/v1/stories'); 

        //Assert: status is 200 OK and data has 2 items
        $response->assertOk(); 
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                ['id', 'name', 'body']
            ]
        ]);
    }

    public function test_user_can_get_single_story(): void 
    {

        //Arrange: create a story
        $story = Story::factory()->create(); 

        //Act: Make a GET request to the endpoing with task ID
        $response = $this->getJson('/api/v1/stories/' . $story->id); 

        // Assert: response contains the correct story data 
        $response->assertOk(); 
        // dd($response->json());
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'body']
        ]);
        $response->assertJson([
            'data' => [
                'id' => $story->id, 
                'name' => $story->name,
                'body' => $story->body,
            ]
            ]);
    }
}
