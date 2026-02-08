<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoryGenerationWithImageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the story title is extracted from the text content,
     * NOT from the image URL markdown that gets prepended to the body.
     *
     * This is a regression test for the bug where image URLs were being
     * saved as story titles because the image was prepended before title extraction.
     */
    public function test_it_extracts_story_title_not_image_url_when_image_is_generated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock both text AND image API calls
        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "The Dragon's Library\n\nPage 1\nOnce upon a time, in a magical library...\n\n---PAGE BREAK---\n\nPage 2\nThe dragon loved to read...",
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://api.together.ai/test-image-12345.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a dragon who loves to read',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Title should be "The Dragon's Library", NOT the image URL
        $this->assertEquals("The Dragon's Library", $story->name);
        $this->assertStringNotContainsString('api.together.ai', $story->name);
        $this->assertStringNotContainsString('http', $story->name);

        // Body SHOULD contain the image markdown at the top
        $this->assertStringStartsWith('![](', $story->body);
        $this->assertStringContainsString('https://api.together.ai/test-image-12345.jpg', $story->body);

        // Body should also contain the actual story text
        $this->assertStringContainsString("The Dragon's Library", $story->body);
        $this->assertStringContainsString('Once upon a time', $story->body);
    }

    /**
     * Test that title extraction works correctly even when image generation fails.
     */
    public function test_it_extracts_story_title_correctly_when_image_generation_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock successful text generation but failed image generation
        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "The Brave Little Robot\n\nA story about courage...",
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'error' => 'Service unavailable',
            ], 503),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a brave robot',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Title should still be extracted correctly
        $this->assertEquals('The Brave Little Robot', $story->name);

        // Body should NOT contain image markdown
        $this->assertStringNotContainsString('![](', $story->body);
        $this->assertStringStartsWith('The Brave Little Robot', $story->body);
    }

    /**
     * Test title extraction when story starts with "Title:" prefix.
     */
    public function test_it_removes_title_prefix_from_story_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "Title: The Magical Garden\n\nOnce upon a time...",
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a magical garden',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // "Title:" prefix should be removed
        $this->assertEquals('The Magical Garden', $story->name);
        $this->assertStringNotContainsString('Title:', $story->name);
    }

    /**
     * Test that special characters are properly cleaned from title.
     */
    public function test_it_cleans_special_characters_from_title(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "# \"The Star's Journey\" *\n\nA tale of adventure...",
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a star',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Special characters should be removed
        $this->assertEquals("The Star's Journey", $story->name);
        $this->assertStringNotContainsString('#', $story->name);
        $this->assertStringNotContainsString('"', $story->name);
        $this->assertStringNotContainsString('*', $story->name);
    }

    /**
     * Test fallback to "New Story" when title is empty.
     */
    public function test_it_uses_fallback_title_when_story_text_is_empty(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '',
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'Generate a story',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Should use fallback title
        $this->assertEquals('New Story', $story->name);
    }
}
